<?php
/**
 * Facilities Management API
 * Handles CRUD operations for prison facilities (SUPER_ADMIN only)
 */

header('Content-Type: application/json');
require_once '../../config/app.php';

// Require authentication and super admin role
requireAuth();

$method = getRequestMethod();

try {
    switch ($method) {
        case 'GET':
            handleGetFacilities();
            break;
        case 'POST':
            handleCreateFacility();
            break;
        case 'PUT':
            handleUpdateFacility();
            break;
        case 'DELETE':
            handleDeleteFacility();
            break;
        default:
            jsonResponse(['error' => 'Method not allowed'], 405);
    }
} catch (Exception $e) {
    logError('Facilities API Error', $e->getMessage());
    errorResponse($e->getMessage(), [], 500);
}

/**
 * Handle GET requests for facilities
 */
function handleGetFacilities() {
    $facilityId = getParam('id');
    $status = getParam('status');
    $page = (int) getParam('page') ?: 1;
    $perPage = (int) getParam('per_page') ?: 20;
    
    if ($facilityId) {
        // Get single facility
        $query = "SELECT f.*,
                         (SELECT COUNT(*) FROM inmates i WHERE i.facility_id = f.id AND i.deleted_at IS NULL AND i.status IN ('REMAND', 'CONVICTED')) as current_population,
                         (SELECT COUNT(*) FROM staff s WHERE s.facility_id = f.id AND s.deleted_at IS NULL) as staff_count,
                         (SELECT COUNT(*) FROM incidents i WHERE i.facility_id = f.id AND i.deleted_at IS NULL AND MONTH(i.created_at) = MONTH(NOW())) as incidents_month
                  FROM facilities f
                  WHERE f.id = ? AND f.deleted_at IS NULL";
        
        $facility = fetchOne($query, [$facilityId], 'i');
        
        if (!$facility) {
            errorResponse('Facility not found', [], 404);
            return;
        }
        
        successResponse($facility, 'Facility retrieved');
        return;
    }
    
    // Get all facilities list (Super admin only can see all)
    if (!isSuperAdmin()) {
        errorResponse('Unauthorized access', [], 403);
        return;
    }
    
    $offset = ($page - 1) * $perPage;
    
    $query = "SELECT f.id, f.name, f.type, f.location, f.capacity, f.status, f.contact_email, f.contact_phone,
                     (SELECT COUNT(*) FROM inmates i WHERE i.facility_id = f.id AND i.deleted_at IS NULL AND i.status IN ('REMAND', 'CONVICTED')) as current_population,
                     (SELECT COUNT(*) FROM staff s WHERE s.facility_id = f.id AND s.deleted_at IS NULL) as staff_count,
                     (SELECT COUNT(*) FROM incidents i WHERE i.facility_id = f.id AND i.deleted_at IS NULL AND MONTH(i.created_at) = MONTH(NOW())) as incidents_month
              FROM facilities f
              WHERE f.deleted_at IS NULL";
    
    $params = [];
    $types = '';
    
    // Filter by status if provided
    if ($status) {
        $query .= " AND f.status = ?";
        $params[] = $status;
        $types .= 's';
    }
    
    // Count total
    $countQuery = "SELECT COUNT(*) as total FROM facilities WHERE deleted_at IS NULL";
    if ($status) {
        $countQuery .= " AND status = ?";
    }
    $countResult = fetchOne($countQuery, $params, $types);
    $total = $countResult['total'] ?? 0;
    
    // Get paginated results
    $query .= " ORDER BY f.name ASC LIMIT ?, ?";
    $params[] = $offset;
    $params[] = $perPage;
    $types .= 'ii';
    
    $facilities = fetchAll($query, $params, $types);
    
    successResponse([
        'data' => $facilities,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => ceil($total / $perPage)
        ]
    ], 'Facilities list retrieved');
}

/**
 * Handle POST requests to create facility
 */
function handleCreateFacility() {
    // Only super admin can create facilities
    if (!isSuperAdmin()) {
        errorResponse('Unauthorized: Only super admin can create facilities', [], 403);
        return;
    }
    
    $data = getJsonInput();
    
    // Validate required fields
    $required = ['name', 'type', 'location', 'capacity'];
    validateRequired($data, $required);
    
    // Validate facility type
    $validTypes = ['PRISON', 'ADMIN_OFFICE', 'HOLDING_CENTER'];
    if (!in_array($data['type'], $validTypes)) {
        errorResponse('Invalid facility type. Must be: ' . implode(', ', $validTypes), [], 400);
        return;
    }
    
    // Check name uniqueness
    $existingFacility = fetchOne(
        "SELECT id FROM facilities WHERE name = ? AND deleted_at IS NULL",
        [$data['name']],
        's'
    );
    
    if ($existingFacility) {
        errorResponse('A facility with this name already exists', [], 400);
        return;
    }
    
    // Prepare facility data
    $facilityData = [
        'name' => $data['name'],
        'type' => $data['type'],
        'location' => $data['location'],
        'capacity' => (int) $data['capacity'],
        'status' => $data['status'] ?? 'ACTIVE',
        'director_name' => $data['director_name'] ?? null,
        'contact_email' => $data['contact_email'] ?? null,
        'contact_phone' => $data['contact_phone'] ?? null,
        'address' => $data['address'] ?? null,
        'postal_code' => $data['postal_code'] ?? null,
        'state_province' => $data['state_province'] ?? null
    ];
    
    // Insert facility
    $result = insert('facilities', $facilityData);
    
    if (!$result) {
        throw new Exception('Failed to create facility');
    }
    
    // Log action
    logAction(
        'CREATE',
        'FACILITY',
        'facilities',
        $result['id'],
        "Created facility: {$data['name']}",
        'SUCCESS',
        null
    );
    
    successResponse(['id' => $result['id']], 'Facility created successfully');
}

/**
 * Handle PUT requests to update facility
 */
function handleUpdateFacility() {
    // Only super admin can update facilities
    if (!isSuperAdmin()) {
        errorResponse('Unauthorized: Only super admin can update facilities', [], 403);
        return;
    }
    
    $data = getJsonInput();
    
    if (!isset($data['id'])) {
        errorResponse('Facility ID is required', [], 400);
        return;
    }
    
    $facilityId = $data['id'];
    
    // Get existing facility
    $facility = fetchOne(
        "SELECT * FROM facilities WHERE id = ? AND deleted_at IS NULL",
        [$facilityId],
        'i'
    );
    
    if (!$facility) {
        errorResponse('Facility not found', [], 404);
        return;
    }
    
    // Prepare updateable fields
    $updateData = [];
    $allowedFields = ['name', 'type', 'location', 'capacity', 'status', 'director_name', 
                      'contact_email', 'contact_phone', 'address', 'postal_code', 'state_province'];
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updateData[$field] = $data[$field];
        }
    }
    
    if (empty($updateData)) {
        errorResponse('No fields to update', [], 400);
        return;
    }
    
    // Validate type if being updated
    if (isset($updateData['type'])) {
        $validTypes = ['PRISON', 'ADMIN_OFFICE', 'HOLDING_CENTER'];
        if (!in_array($updateData['type'], $validTypes)) {
            errorResponse('Invalid facility type', [], 400);
            return;
        }
    }
    
    // Update facility
    $affected = update('facilities', $updateData, 'id = ?', [$facilityId]);
    
    if ($affected === false) {
        throw new Exception('Failed to update facility');
    }
    
    // Log action
    logAction(
        'UPDATE',
        'FACILITY',
        'facilities',
        $facilityId,
        'Updated facility details',
        'SUCCESS',
        null
    );
    
    successResponse(['affected_rows' => $affected], 'Facility updated successfully');
}

/**
 * Handle DELETE requests to soft delete facility
 */
function handleDeleteFacility() {
    // Only super admin can delete facilities
    if (!isSuperAdmin()) {
        errorResponse('Unauthorized: Only super admin can delete facilities', [], 403);
        return;
    }
    
    $data = getJsonInput();
    
    if (!isset($data['id'])) {
        errorResponse('Facility ID is required', [], 400);
        return;
    }
    
    $facilityId = $data['id'];
    
    // Get existing facility
    $facility = fetchOne(
        "SELECT * FROM facilities WHERE id = ? AND deleted_at IS NULL",
        [$facilityId],
        'i'
    );
    
    if (!$facility) {
        errorResponse('Facility not found', [], 404);
        return;
    }
    
    // Check if facility has active inmates or staff
    $inmateCount = countRows(
        'inmates',
        'facility_id = ? AND deleted_at IS NULL AND status IN ("REMAND", "CONVICTED")',
        [$facilityId]
    );
    
    if ($inmateCount > 0) {
        errorResponse('Cannot delete facility with active inmates', [], 400);
        return;
    }
    
    // Soft delete facility
    $affected = softDelete('facilities', 'id = ?', [$facilityId]);
    
    if ($affected === false) {
        throw new Exception('Failed to delete facility');
    }
    
    // Log action
    logAction(
        'DELETE',
        'FACILITY',
        'facilities',
        $facilityId,
        'Soft-deleted facility',
        'SUCCESS',
        null
    );
    
    successResponse(['affected_rows' => $affected], 'Facility deleted successfully');
}
