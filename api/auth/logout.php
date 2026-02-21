<?php
/**
 * Authentication Logout API Endpoint  
 * POST /api/auth/logout.php
 */

require_once dirname(__FILE__) . '/../../config/app.php';
require_once dirname(__FILE__) . '/../../config/db.php';
require_once dirname(__FILE__) . '/../../includes/functions.php';

requireAuth();

// Log logout
$user = getCurrentUser();
logAction('LOGOUT', 'AUTH', 'user',$user['id'], 'User logged out', 'SUCCESS');

// Destroy session
$_SESSION = [];
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

successResponse([], 'Logged out successfully');
