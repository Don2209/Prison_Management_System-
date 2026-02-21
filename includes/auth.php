<?php
/**
 * Authentication System
 * Handles user login, session management, and authentication checks
 */

require_once dirname(__FILE__) . '/../config/app.php';
require_once dirname(__FILE__) . '/../config/db.php';
require_once dirname(__FILE__) . '/functions.php';

// ===================================================================
// LOGIN FUNCTION
// ===================================================================

/**
 * Authenticate user with username and password
 */
function authenticateUser($username, $password) {
    try {
        // Get user by username
        $user = getUserByUsername($username);
        
        if (!$user) {
            return [
                'success' => false,
                'message' => 'Invalid username or password',
                'code' => 'INVALID_CREDENTIALS'
            ];
        }
        
        // Check if user is active
        if (!$user['is_active']) {
            return [
                'success' => false,
                'message' => 'Account is disabled',
                'code' => 'ACCOUNT_DISABLED'
            ];
        }
        
        // Verify password
        if (!verifyPassword($password, $user['password_hash'])) {
            // Log failed attempt
            logActivity($user['id'], 'LOGIN', 'Failed login attempt');
            
            return [
                'success' => false,
                'message' => 'Invalid username or password',
                'code' => 'INVALID_CREDENTIALS'
            ];
        }
        
        // Create session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['facility_id'] = $user['facility_id'];
        $_SESSION['login_time'] = time();
        
        // Update last login
        update('users', ['last_login' => date('Y-m-d H:i:s')], 'id = ?', [$user['id']]);
        
        // Log successful login
        logActivity($user['id'], 'LOGIN', 'Successful login');
        
        return [
            'success' => true,
            'message' => 'Login successful',
            'user' => getCurrentUser()
        ];
        
    } catch (Exception $e) {
        logError('Authentication Error', $e->getMessage());
        return [
            'success' => false,
            'message' => 'Authentication failed',
            'code' => 'AUTH_ERROR',
            'error' => $e->getMessage()
        ];
    }
}

// ===================================================================
// PASSWORD RESET FUNCTIONS
// ===================================================================

/**
 * Request password reset
 */
function requestPasswordReset($email) {
    try {
        $user = fetchOne(
            "SELECT id FROM users WHERE email = ? AND deleted_at IS NULL",
            [$email],
            's'
        );
        
        if (!$user) {
            // Don't reveal if email exists
            return [
                'success' => true,
                'message' => 'If email exists, password reset link has been sent'
            ];
        }
        
        // Generate reset token
        $token = generateToken(32);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Save token
        update('users', [
            'password_reset_token' => hash('sha256', $token),
            'password_reset_expires' => $expiresAt
        ], 'id = ?', [$user['id']]);
        
        // In production, send email here
        // sendPasswordResetEmail($email, $token);
        
        return [
            'success' => true,
            'message' => 'Password reset link sent to email',
            'token' => $token // Remove in production, only for testing
        ];
        
    } catch (Exception $e) {
        logError('Password Reset Request', $e->getMessage());
        return [
            'success' => false,
            'message' => 'Password reset request failed',
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Reset password with token
 */
function resetPasswordWithToken($token, $newPassword) {
    try {
        // Verify token
        $user = fetchOne(
            "SELECT id FROM users WHERE password_reset_token = ? AND password_reset_expires > NOW() AND deleted_at IS NULL",
            [hash('sha256', $token)],
            's'
        );
        
        if (!$user) {
            return [
                'success' => false,
                'message' => 'Invalid or expired reset token',
                'code' => 'INVALID_TOKEN'
            ];
        }
        
        // Update password
        update('users', [
            'password_hash' => hashPassword($newPassword),
            'password_reset_token' => null,
            'password_reset_expires' => null
        ], 'id = ?', [$user['id']]);
        
        logAction('RESET_PASSWORD', 'LOGIN', 'user', $user['id'], 'Password reset successful');
        
        return [
            'success' => true,
            'message' => 'Password reset successful'
        ];
        
    } catch (Exception $e) {
        logError('Password Reset', $e->getMessage());
        return [
            'success' => false,
            'message' => 'Password reset failed',
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Change password (authenticated user)
 */
function changePassword($userId, $oldPassword, $newPassword) {
    try {
        $user = getUser($userId);
        
        if (!$user) {
            return [
                'success' => false,
                'message' => 'User not found',
                'code' => 'USER_NOT_FOUND'
            ];
        }
        
        // Verify old password
        if (!verifyPassword($oldPassword, $user['password_hash'])) {
            return [
                'success' => false,
                'message' => 'Current password is incorrect',
                'code' => 'INVALID_PASSWORD'
            ];
        }
        
        // Update password
        update('users', [
            'password_hash' => hashPassword($newPassword),
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$userId]);
        
        logAction('CHANGE_PASSWORD', 'LOGIN', 'user', $userId, 'Password changed');
        
        return [
            'success' => true,
            'message' => 'Password changed successfully'
        ];
        
    } catch (Exception $e) {
        logError('Change Password', $e->getMessage());
        return [
            'success' => false,
            'message' => 'Password change failed',
            'error' => $e->getMessage()
        ];
    }
}

// ===================================================================
// ROLE-BASED ACCESS CONTROL (RBAC)
// ===================================================================

/**
 * Check permission for action
 */
function hasPermission($action, $resourceType) {
    $role = getCurrentUserRole();
    
    $permissions = [
        'SUPER_ADMIN' => [
            'view' => ['*'],
            'create' => ['*'],
            'edit' => ['*'],
            'delete' => ['*']
        ],
        'FACILITY_ADMIN' => [
            'view' => ['facilities', 'users', 'inmates', 'staff', 'incidents', 'inventory', 'finance', 'reports'],
            'create' => ['users', 'inmates', 'staff', 'incidents', 'inventory'],
            'edit' => ['users', 'inmates', 'staff', 'inventory'],
            'delete' => []
        ],
        'OFFICER' => [
            'view' => ['inmates', 'incidents', 'visits', 'transfers'],
            'create' => ['incidents', 'visits'],
            'edit' => ['incidents'],
            'delete' => []
        ],
        'MEDICAL_STAFF' => [
            'view' => ['inmates', 'medical_records', 'incidents'],
            'create' => ['medical_records', 'incidents'],
            'edit' => ['medical_records'],
            'delete' => []
        ],
        'RECORDS_OFFICER' => [
            'view' => ['inmates', 'staff', 'reports', 'transfers', 'visits'],
            'create' => ['transfers', 'visits'],
            'edit' => ['transfers'],
            'delete' => []
        ],
        'FINANCE_OFFICER' => [
            'view' => ['inmates', 'finance', 'inventory', 'reports'],
            'create' => ['finance'],
            'edit' => ['finance'],
            'delete' => []
        ]
    ];
    
    if (!isset($permissions[$role])) {
        return false;
    }
    
    $rolePerms = $permissions[$role];
    
    if (!isset($rolePerms[$action])) {
        return false;
    }
    
    $allowedResources = $rolePerms[$action];
    
    // Check wildcard or specific resource
    return in_array('*', $allowedResources) || in_array($resourceType, $allowedResources);
}

/**
 * Verify permission and return error if denied
 */
function verifyPermission($action, $resourceType) {
    if (!hasPermission($action, $resourceType)) {
        if (isAPIRequest()) {
            errorResponse('Access denied', [], 403, 'FORBIDDEN');
        }
        die('Access denied');
    }
}

// ===================================================================
// FACILITY-BASED ACCESS CONTROL
// ===================================================================

/**
 * Get accessible facilities for user (for filtering)
 */
function getAccessibleFacilityIds() {
    if (isSuperAdmin()) {
        // Return all facility IDs
        $results = fetchAll("SELECT id FROM facilities WHERE deleted_at IS NULL");
        return array_column($results, 'id');
    }
    
    // Return only user's facility
    $facility = getCurrentFacility();
    return $facility ? [$facility] : [];
}

/**
 * Add facility filter to query builder
 */
function addFacilityFilter(&$query, &$params, $facilityIdColumn = 'facility_id') {
    if (!isSuperAdmin()) {
        $query .= " AND $facilityIdColumn = ?";
        $params[] = getCurrentFacility();
    }
}

// ===================================================================
// AUDIT & LOGGING
// ===================================================================

/**
 * Log authentication attempt
 */
function logAuthAttempt($username, $success, $reason = null) {
    try {
        $user = getUserByUsername($username);
        $userId = $user ? $user['id'] : null;
        
        logActivity($userId, $success ? 'LOGIN' : 'LOGIN_FAILED', 'auth');
    } catch (Exception $e) {
        logError('Auth Logging Error', $e->getMessage());
    }
}

// ===================================================================
// SESSION SECURITY
// ===================================================================

/**
 * Regenerate session ID (for security)
 */
function regenerateSessionId() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

/**
 * Validate session
 */
function validateSession() {
    // Check session timeout (24 hours)
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 86400)) {
        logout();
    }
    
    // Validate user still exists and is active
    if (isLoggedIn()) {
        $user = getUser(getCurrentUserId());
        if (!$user || !$user['is_active']) {
            logout();
        }
    }
}

// Validate session on every request
validateSession();
