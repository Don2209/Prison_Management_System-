<?php
/**
 * Staff Management API
 * Handles CRUD operations for staff members
 */

header('Content-Type: application/json');
require_once '../../config/app.php';
require_once '../../includes/auth.php';

// Require authentication
requireAuth();

$method = getRequestMethod();

try {
    switch ($method) {
        case 'GET':
            handleGetStaff();
            break;
        case 'POST':
            handleCreateStaff();
            break;
        case 'PUT':
            handleUpdateStaff();
            break;
        case 'DELETE':
            handleDeleteStaff();
            break;
        default:
            jsonResponse(['error' => 'Method not allowed'], 405);
    }
} catch (Exception $e) {
    logError('Staff API Error', $e->getMessage());
    errorResponse($e->getMessage(), [], 500);
}

/**
 * Handle GET requests for staff
 */
function handleGetStaff() {
    $staffId = getParam('id');
    $facilityId = getParam('facility_id') ?: getCurrentFacility();
    $staffType = getParam('staff_type');
    $page = (int) getParam('page') ?: 1;
    $perPage = (int) getParam('per_page') ?: 20;
    
    // Check facility access
    if (!belongsToFacility($facilityId)) {
        errorResponse('Access denied', [], 403);
        return;
    }
    
    if ($staffId) {
        // Get single staff member
        $query = "SELECT s.*, u.username, u.email, u.first_name, u.last_name, u.is_active, 
                         (SELECT COUNT(*) FROM duty_schedule ds WHERE ds.staff_id = s.id AND ds.status = 'ACTIVE') as active_shifts
                  FROM staff s
                  JOIN users u ON s.user_id = u.id
                  WHERE s.id = ? AND s.deleted_at IS NULL";
        
        $staff = fetchOne($query, [$staffId], 'i');
        
        if (!$staff) {
            errorResponse('Staff member not found', [], 404);
            return;
        }
        
        successResponse($staff, 'Staff member retrieved');
        return;
    }
    
    // Get paginated list
    $offset = ($page - 1) * $perPage;
    
    $query = "SELECT s.id, s.staff_id, s.staff_type, s.department, s.rank, s.specializations, 
                     u.first_name, u.last_name, u.email, u.is_active,
                     DATE_FORMAT(s.employment_start_date, '%Y-%m-%d') as employment_start_date,
                     (SELECT COUNT(*) FROM duty_schedule ds WHERE ds.staff_id = s.id AND ds.status = 'ACTIVE') as active_shifts
              FROM staff s
              JOIN users u ON s.user_id = u.id
              WHERE s.facility_id = ? AND s.deleted_at IS NULL";
    
    $params = [$facilityId];
    $types = 'i';
    
    // Filter by staff type if provided
    if ($staffType) {
        $query .= " AND s.staff_type = ?";
        $params[] = $staffType;
        $types .= 's';
    }
    
    // Count total records
    $countQuery = "SELECT COUNT(*) as total FROM staff s 
                   WHERE s.facility_id = ? AND s.deleted_at IS NULL";
    $countParams = [$facilityId];
    $countTypes = 'i';
    
    if ($staffType) {
        $countQuery .= " AND s.staff_type = ?";
        $countParams[] = $staffType;
        $countTypes .= 's';
    }
    
    $countResult = fetchOne($countQuery, $countParams, $countTypes);
    $total = $countResult['total'] ?? 0;
    
    // Get paginated results
    $query .= " ORDER BY u.last_name, u.first_name ASC LIMIT ?, ?";
    $params[] = $offset;
    $params[] = $perPage;
    $types .= 'ii';
    
    $staff = fetchAll($query, $params, $types);
    
    successResponse([
        'data' => $staff,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => ceil($total / $perPage)
        ]
    ], 'Staff list retrieved');
}

/**
 * Handle POST requests to create staff
 */
function handleCreateStaff() {
    // Check permission
    verifyPermission('create', 'staff');
    
    $data = getJsonInput();
    
    // Validate required fields
    $required = ['user_id', 'staff_type', 'department'];
    validateRequired($data, $required);
    
    $facilityId = getCurrentFacility();
    
    // Generate unique staff_id
    $staffIdPrefix = substr(strtoupper($data['staff_type']), 0, 3);
    $staffIdNum = countRows('staff', 'staff_type = ?', [$data['staff_type']]) + 1;
    $staffId = $staffIdPrefix . '-' . str_pad($staffIdNum, 5, '0', STR_PAD_LEFT);
    
    // Verify user exists and belongs to facility
    $user = getUser($data['user_id']);
    if (!$user) {
        errorResponse('User not found', [], 404);
        return;
    }
    
    if ($user['facility_id'] != $facilityId && !isSuperAdmin()) {
        errorResponse('User does not belong to your facility', [], 400);
        return;
    }
    
    // Check if user already has a staff record
    $existingStaff = fetchOne(
        "SELECT id FROM staff WHERE user_id = ? AND deleted_at IS NULL",
        [$data['user_id']],
        'i'
    );
    
    if ($existingStaff) {
        errorResponse('This user already has a staff record', [], 400);
        return;
    }
    
    // Prepare staff data
    $staffData = [
        'facility_id' => $facilityId,
        'user_id' => $data['user_id'],
        'staff_id' => $staffId,
        'staff_type' => $data['staff_type'],
        'department' => $data['department'],
        'rank' => $data['rank'] ?? null,
        'specializations' => $data['specializations'] ?? null,
        'employment_start_date' => $data['employment_start_date'] ?? date('Y-m-d'),
        'contact_number' => $data['contact_number'] ?? null,
        'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
        'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
        'is_active' => 1
    ];
    
    // Insert staff record
    $result = insert('staff', $staffData);
    
    if (!$result) {
        throw new Exception('Failed to create staff record');
    }
    
    // Log action
    logAction(
        'CREATE',
        'STAFF',
        'staff',
        $result['id'],
        "Added staff member: {$user['first_name']} {$user['last_name']}",
        'SUCCESS',
        $facilityId
    );
    
    successResponse([
        'id' => $result['id'],
        'staff_id' => $staffId
    ], 'Staff member created successfully');
}

/**
 * Handle PUT requests to update staff
 */
function handleUpdateStaff() {
    // Check permission
    verifyPermission('edit', 'staff');
    
    $data = getJsonInput();
    
    if (!isset($data['id'])) {
        errorResponse('Staff ID is required', [], 400);
        return;
    }
    
    $staffId = $data['id'];
    $facilityId = getCurrentFacility();
    
    // Get existing staff record
    $staff = fetchOne(
        "SELECT * FROM staff WHERE id = ? AND facility_id = ? AND deleted_at IS NULL",
        [$staffId, $facilityId],
        'ii'
    );
    
    if (!$staff) {
        errorResponse('Staff member not found', [], 404);
        return;
    }
    
    // Prepare updateable fields
    $updateData = [];
    $allowedFields = ['staff_type', 'department', 'rank', 'specializations', 'employment_start_date', 
                      'contact_number', 'emergency_contact_name', 'emergency_contact_phone', 'is_active'];
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updateData[$field] = $data[$field];
        }
    }
    
    if (empty($updateData)) {
        errorResponse('No fields to update', [], 400);
        return;
    }
    
    // Update staff record
    $affected = update('staff', $updateData, 'id = ?', [$staffId]);
    
    if ($affected === false) {
        throw new Exception('Failed to update staff record');
    }
    
    // Log action
    logAction(
        'UPDATE',
        'STAFF',
        'staff',
        $staffId,
        'Updated staff member details',
        'SUCCESS',
        $facilityId
    );
    
    successResponse(['affected_rows' => $affected], 'Staff member updated successfully');
}

/**
 * Handle DELETE requests to soft delete staff
 */
function handleDeleteStaff() {
    // Check permission
    verifyPermission('delete', 'staff');
    
    $data = getJsonInput();
    
    if (!isset($data['id'])) {
        errorResponse('Staff ID is required', [], 400);
        return;
    }
    
    $staffId = $data['id'];
    $facilityId = getCurrentFacility();
    
    // Get existing staff record
    $staff = fetchOne(
        "SELECT * FROM staff WHERE id = ? AND facility_id = ? AND deleted_at IS NULL",
        [$staffId, $facilityId],
        'ii'
    );
    
    if (!$staff) {
        errorResponse('Staff member not found', [], 404);
        return;
    }
    
    // Soft delete staff record
    $affected = softDelete('staff', 'id = ?', [$staffId]);
    
    if ($affected === false) {
        throw new Exception('Failed to delete staff record');
    }
    
    // Log action
    logAction(
        'DELETE',
        'STAFF',
        'staff',
        $staffId,
        'Soft-deleted staff member',
        'SUCCESS',
        $facilityId
    );
    
    successResponse(['affected_rows' => $affected], 'Staff member deleted successfully');
}
