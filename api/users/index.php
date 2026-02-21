<?php
/**
 * Users Management API Endpoint
 * GET /api/users/list.php
 * POST /api/users/create.php
 * GET /api/users/get.php?id=X
 * PUT /api/users/update.php
 * DELETE /api/users/delete.php
 */

require_once dirname(__FILE__) . '/../../config/app.php';
require_once dirname(__FILE__) . '/../../config/db.php';
require_once dirname(__FILE__) . '/../../includes/functions.php';

requireAuth();

$method = getRequestMethod();
$action = getParam('action', 'list');

try {
    switch ($method) {
        case 'GET':
            handleGetUsers();
            break;
        case 'POST':
            requireRole(['SUPER_ADMIN', 'FACILITY_ADMIN']);
            handleCreateUser();
            break;
        case 'PUT':
            requireRole(['SUPER_ADMIN', 'FACILITY_ADMIN']);
            handleUpdateUser();
            break;
        case 'DELETE':
            requireRole(['SUPER_ADMIN', 'FACILITY_ADMIN']);
            handleDeleteUser();
            break;
        default:
            errorResponse('Method not allowed', [], 405, 'METHOD_NOT_ALLOWED');
    }
} catch (Exception $e) {
    logError('Users API Error', $e->getMessage());
    errorResponse('Operation failed: ' . $e->getMessage(), [], 500);
}

/**
 * Handle GET requests
 */
function handleGetUsers() {
    $id = getParam('id');
    
    if ($id) {
        // Get single user
        $user = getUser($id);
        if (!$user) {
            errorResponse('User not found', [], 404, 'USER_NOT_FOUND');
        }
        
        // Check facility access
        requireFacilityAccess($user['facility_id']);
        
        successResponse($user);
    } else {
        // List users
        $facilityId = getParam('facility_id', getCurrentFacility());
        
        if (!isSuperAdmin()) {
            $facilityId = getCurrentFacility();
        }
        
        $users = getFacilityUsers($facilityId);
        successResponse(['users' => $users, 'count' => count($users)]);
    }
}

/**
 * Handle POST requests - Create user
 */
function handleCreateUser() {
    $data = getPostData();
    
    // Validate required fields
    $validation = validateRequired($data, ['username', 'email', 'password', 'first_name', 'last_name', 'role', 'facility_id']);
    if ($validation !== true) {
        errorResponse('Missing required fields: ' . implode(', ', $validation), [], 400);
    }
    
    // Check facility access
    requireFacilityAccess($data['facility_id']);
    
    // Check if username exists
    if (getUserByUsername($data['username'])) {
        errorResponse('Username already exists', [], 409, 'USERNAME_EXISTS');
    }
    
    // Check if email exists
    if (fetchOne("SELECT id FROM users WHERE email = ? AND deleted_at IS NULL", [$data['email']], 's')) {
        errorResponse('Email already exists', [], 409, 'EMAIL_EXISTS');
    }
    
    // Validate role
    $roles = array_keys(getAllRoles());
    if (!in_array($data['role'], $roles)) {
        errorResponse('Invalid role', [], 400, 'INVALID_ROLE');
    }
    
    // Create user
    $result = insert('users', [
        'facility_id' => (int)$data['facility_id'],
        'username' => sanitize($data['username']),
        'email' => sanitize($data['email']),
        'password_hash' => hashPassword($data['password']),
        'first_name' => sanitize($data['first_name']),
        'last_name' => sanitize($data['last_name']),
        'role' => $data['role'],
        'is_active' => true,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    
    // Log action
    logAction('CREATE_USER', 'USERS', 'user', $result['id'], "Created user: {$data['username']}", 'SUCCESS');
    
    successResponse(['id' => $result['id']], 'User created successfully', 201);
}

/**
 * Handle PUT requests - Update user
 */
function handleUpdateUser() {
    $data = getPostData();
    
    if (!isset($data['id'])) {
        errorResponse('User ID is required', [], 400);
    }
    
    $user = getUser($data['id']);
    if (!$user) {
        errorResponse('User not found', [], 404);
    }
    
    // Check facility access
    requireFacilityAccess($user['facility_id']);
    
    // Check if email is being changed
    if (isset($data['email']) && $data['email'] !== $user['email']) {
        if (fetchOne("SELECT id FROM users WHERE email = ? AND id != ? AND deleted_at IS NULL", [$data['email'], $data['id']], 'si')) {
            errorResponse('Email already in use', [], 409);
        }
    }
    
    // Build update data
    $updateData = [];
    if (isset($data['first_name'])) $updateData['first_name'] = sanitize($data['first_name']);
    if (isset($data['last_name'])) $updateData['last_name'] = sanitize($data['last_name']);
    if (isset($data['email'])) $updateData['email'] = sanitize($data['email']);
    if (isset($data['is_active'])) $updateData['is_active'] = (bool)$data['is_active'];
    if (isset($data['role']) && isSuperAdmin()) {
        $roles = array_keys(getAllRoles());
        if (in_array($data['role'], $roles)) {
            $updateData['role'] = $data['role'];
        }
    }
    
    if (empty($updateData)) {
        errorResponse('No fields to update', [], 400);
    }
    
    $updateData['updated_at'] = date('Y-m-d H:i:s');
    
    update('users', $updateData, 'id = ?', [$data['id']]);
    
    // Log action
    logAction('UPDATE_USER', 'USERS', 'user', $data['id'], "Updated user profile", 'SUCCESS');
    
    successResponse([], 'User updated successfully');
}

/**
 * Handle DELETE requests to soft delete user
 */
function handleDeleteUser() {
    $data = getJsonInput();
    
    if (!isset($data['id'])) {
        errorResponse('User ID is required', [], 400);
        return;
    }
    
    $userId = $data['id'];
    $facilityId = getCurrentFacility();
    
    // Get existing user record
    $user = fetchOne(
        "SELECT * FROM users WHERE id = ? AND facility_id = ? AND deleted_at IS NULL",
        [$userId, $facilityId],
        'ii'
    );
    
    if (!$user) {
        errorResponse('User not found', [], 404);
        return;
    }
    
    // Prevent self-deletion
    if ($userId == getCurrentUserId()) {
        errorResponse('Cannot delete your own account', [], 400);
        return;
    }
    
    // Soft delete user record
    $affected = softDelete('users', 'id = ?', [$userId]);
    
    if ($affected === false) {
        throw new Exception('Failed to delete user');
    }
    
    // Log action
    logAction(
        'DELETE',
        'USERS',
        'users',
        $userId,
        "Soft-deleted user: {$user['username']}",
        'SUCCESS',
        $facilityId
    );
    
    successResponse(['affected_rows' => $affected], 'User deleted successfully');
}