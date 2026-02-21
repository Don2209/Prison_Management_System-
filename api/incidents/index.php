<?php
/**
 * Incidents Management API
 * Handles incident reporting, investigation, and resolution
 */

header('Content-Type: application/json');
require_once '../../config/app.php';
require_once '../../includes/auth.php';

// Require authentication
requireAuth();

$method = getRequestMethod();
$action = getParam('action');

try {
    switch ($method) {
        case 'GET':
            if ($action === 'categories') {
                handleGetCategories();
            } else {
                handleGetIncidents();
            }
            break;
        case 'POST':
            handleReportIncident();
            break;
        case 'PUT':
            handleUpdateIncident();
            break;
        case 'DELETE':
            handleDeleteIncident();
            break;
        default:
            jsonResponse(['error' => 'Method not allowed'], 405);
    }
} catch (Exception $e) {
    logError('Incidents API Error', $e->getMessage());
    errorResponse($e->getMessage(), [], 500);
}

/**
 * Get incidents
 */
function handleGetIncidents() {
    verifyPermission('view', 'incidents');
    
    $incidentId = getParam('id');
    $facilityId = getCurrentFacility();
    $status = getParam('status');
    $categoryId = getParam('category_id');
    $severity = getParam('severity');
    $startDate = getParam('start_date');
    $endDate = getParam('end_date');
    $page = (int) getParam('page') ?: 1;
    $perPage = (int) getParam('per_page') ?: 20;
    
    if ($incidentId) {
        // Get single incident with full details
        $query = "SELECT i.*, c.name as category_name, u.first_name as reported_by_first, u.last_name as reported_by_last,
                         ic.first_name as investigator_first, ic.last_name as investigator_last
                  FROM incidents i
                  LEFT JOIN incident_categories c ON i.category_id = c.id
                  LEFT JOIN users u ON i.reported_by_user_id = u.id
                  LEFT JOIN users ic ON i.investigating_officer_id = ic.id
                  WHERE i.id = ? AND i.facility_id = ? AND i.deleted_at IS NULL";
        
        $incident = fetchOne($query, [$incidentId, $facilityId], 'ii');
        
        if (!$incident) {
            errorResponse('Incident not found', [], 404);
            return;
        }
        
        successResponse($incident, 'Incident retrieved');
        return;
    }
    
    // Get paginated list
    $offset = ($page - 1) * $perPage;
    
    $query = "SELECT i.id, i.incident_type, i.severity, i.status, i.category_id, c.name as category_name,
                     i.reporting_officer_id, i.location, i.reported_date, i.description,
                     u.first_name as reported_by_first, u.last_name as reported_by_last
              FROM incidents i
              LEFT JOIN incident_categories c ON i.category_id = c.id
              LEFT JOIN users u ON i.reported_by_user_id = u.id
              WHERE i.facility_id = ? AND i.deleted_at IS NULL";
    
    $params = [$facilityId];
    $types = 'i';
    
    if ($status) {
        $query .= " AND i.status = ?";
        $params[] = $status;
        $types .= 's';
    }
    
    if ($categoryId) {
        $query .= " AND i.category_id = ?";
        $params[] = $categoryId;
        $types .= 'i';
    }
    
    if ($severity) {
        $query .= " AND i.severity = ?";
        $params[] = $severity;
        $types .= 's';
    }
    
    if ($startDate) {
        $query .= " AND DATE(i.reported_date) >= ?";
        $params[] = $startDate;
        $types .= 's';
    }
    
    if ($endDate) {
        $query .= " AND DATE(i.reported_date) <= ?";
        $params[] = $endDate;
        $types .= 's';
    }
    
    // Count total
    $countQuery = "SELECT COUNT(*) as total FROM incidents 
                   WHERE facility_id = ? AND deleted_at IS NULL";
    $countParams = [$facilityId];
    $countTypes = 'i';
    
    if ($status) {
        $countQuery .= " AND status = ?";
        $countParams[] = $status;
        $countTypes .= 's';
    }
    if ($categoryId) {
        $countQuery .= " AND category_id = ?";
        $countParams[] = $categoryId;
        $countTypes .= 'i';
    }
    if ($severity) {
        $countQuery .= " AND severity = ?";
        $countParams[] = $severity;
        $countTypes .= 's';
    }
    
    $countResult = fetchOne($countQuery, $countParams, $countTypes);
    $total = $countResult['total'] ?? 0;
    
    // Get paginated results
    $query .= " ORDER BY i.reported_date DESC LIMIT ?, ?";
    $params[] = $offset;
    $params[] = $perPage;
    $types .= 'ii';
    
    $incidents = fetchAll($query, $params, $types);
    
    successResponse([
        'data' => $incidents,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => ceil($total / $perPage)
        ]
    ], 'Incidents retrieved');
}

/**
 * Report incident
 */
function handleReportIncident() {
    verifyPermission('create', 'incidents');
    
    $data = getJsonInput();
    $facilityId = getCurrentFacility();
    
    validateRequired($data, ['incident_type', 'severity', 'category_id', 'description']);
    
    // Validate severity
    $validSeverities = ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'];
    if (!in_array($data['severity'], $validSeverities)) {
        errorResponse('Invalid severity level', [], 400);
        return;
    }
    
    // Verify category exists
    $category = fetchOne(
        "SELECT id FROM incident_categories WHERE id = ? AND deleted_at IS NULL",
        [$data['category_id']],
        'i'
    );
    
    if (!$category) {
        errorResponse('Category not found', [], 404);
        return;
    }
    
    // Prepare incident data
    $incidentData = [
        'facility_id' => $facilityId,
        'incident_type' => $data['incident_type'],
        'category_id' => (int) $data['category_id'],
        'severity' => $data['severity'],
        'status' => 'OPEN',
        'reported_by_user_id' => getCurrentUserId(),
        'reported_date' => $data['reported_date'] ?? date('Y-m-d H:i:s'),
        'location' => $data['location'] ?? null,
        'description' => $data['description'],
        'involved_inmates' => $data['involved_inmates'] ?? null,
        'involved_staff' => $data['involved_staff'] ?? null,
        'witnesses' => $data['witnesses'] ?? null,
        'property_damage' => isset($data['property_damage']) ? (float) $data['property_damage'] : 0,
        'emergency_services_called' => $data['emergency_services_called'] ?? false
    ];
    
    // Insert incident
    $result = insert('incidents', $incidentData);
    
    if (!$result) {
        throw new Exception('Failed to report incident');
    }
    
    // Log action
    logAction('CREATE', 'INCIDENTS', 'incidents', $result['id'],
              "Incident reported: {$data['incident_type']} ({$data['severity']})", 'SUCCESS', $facilityId);
    
    successResponse(['id' => $result['id']], 'Incident reported successfully');
}

/**
 * Update incident
 */
function handleUpdateIncident() {
    verifyPermission('edit', 'incidents');
    
    $data = getJsonInput();
    $facilityId = getCurrentFacility();
    
    if (!isset($data['id'])) {
        errorResponse('Incident ID is required', [], 400);
        return;
    }
    
    // Get existing incident
    $incident = fetchOne(
        "SELECT * FROM incidents WHERE id = ? AND facility_id = ? AND deleted_at IS NULL",
        [$data['id'], $facilityId],
        'ii'
    );
    
    if (!$incident) {
        errorResponse('Incident not found', [], 404);
        return;
    }
    
    // Prepare updateable fields
    $updateData = [];
    $allowedFields = ['severity', 'status', 'investigating_officer_id', 'investigation_notes',
                      'resolution_date', 'resolution_summary', 'property_damage', 
                      'emergency_services_called'];
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updateData[$field] = $data[$field];
        }
    }
    
    // Validate status if being updated
    if (isset($updateData['status'])) {
        $validStatuses = ['OPEN', 'UNDER_INVESTIGATION', 'RESOLVED', 'CLOSED', 'DISMISSED'];
        if (!in_array($updateData['status'], $validStatuses)) {
            errorResponse('Invalid status', [], 400);
            return;
        }
    }
    
    if (empty($updateData)) {
        errorResponse('No fields to update', [], 400);
        return;
    }
    
    // Update incident
    $affected = update('incidents', $updateData, 'id = ?', [$data['id']]);
    
    if ($affected === false) {
        throw new Exception('Failed to update incident');
    }
    
    // Log action
    logAction('UPDATE', 'INCIDENTS', 'incidents', $data['id'],
              'Updated incident details', 'SUCCESS', $facilityId);
    
    successResponse(['affected_rows' => $affected], 'Incident updated successfully');
}

/**
 * Delete incident
 */
function handleDeleteIncident() {
    verifyPermission('delete', 'incidents');
    
    $data = getJsonInput();
    $facilityId = getCurrentFacility();
    
    if (!isset($data['id'])) {
        errorResponse('Incident ID is required', [], 400);
        return;
    }
    
    // Soft delete incident
    $affected = softDelete('incidents', 'id = ? AND facility_id = ?',
                          [$data['id'], $facilityId]);
    
    if ($affected === false) {
        throw new Exception('Failed to delete incident');
    }
    
    // Log action
    logAction('DELETE', 'INCIDENTS', 'incidents', $data['id'],
              'Soft-deleted incident', 'SUCCESS', $facilityId);
    
    successResponse(['affected_rows' => $affected], 'Incident deleted successfully');
}

/**
 * Get incident categories
 */
function handleGetCategories() {
    $categories = fetchAll(
        "SELECT * FROM incident_categories WHERE deleted_at IS NULL ORDER BY name ASC",
        [],
        ''
    );
    
    successResponse($categories, 'Categories retrieved');
}
