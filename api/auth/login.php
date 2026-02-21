<?php
/**
 * Authentication Login API Endpoint
 * POST /api/auth/login.php
 */

require_once dirname(__FILE__) . '/../../config/app.php';
require_once dirname(__FILE__) . '/../../config/db.php';
require_once dirname(__FILE__) . '/../../includes/functions.php';
require_once dirname(__FILE__) . '/../../includes/auth.php';

// Only allow POST requests
if (getRequestMethod() !== 'POST') {
    errorResponse('Method not allowed', [], 405, 'METHOD_NOT_ALLOWED');
}

try {
    // Get input data
    $data = getPostData();
    
    // Validate required fields
    $validation = validateRequired($data, ['username', 'password']);
    if ($validation !== true) {
        errorResponse('Missing required fields: ' . implode(', ', $validation), [], 400, 'VALIDATION_ERROR');
    }
    
    $username = sanitize($data['username']);
    $password = $data['password']; // Don't sanitize password
    
    // Authenticate user
    $result = authenticateUser($username, $password);
    
    if (!$result['success']) {
        logAction('LOGIN_FAILED', 'AUTH', 'user', 0, 'Failed login attempt for username: ' . $username, 'FAILURE');
        errorResponse($result['message'], [], 401, $result['code']);
    }
    
    // Log successful login
    logAction('LOGIN', 'AUTH', 'user', $result['user']['id'], 'User logged in', 'SUCCESS');
    
    successResponse($result['user'], 'Login successful');
    
} catch (Exception $e) {
    logError('Login API Error', $e->getMessage());
    errorResponse('Login failed', [], 500, 'LOGIN_ERROR');
}
