<?php
/**
 * Application Configuration & Global Helpers
 * Production-ready configuration and utility functions
 */

// ===================================================================
// SESSION CONFIGURATION (must be set before session_start)
// ===================================================================
ini_set('session.gc_maxlifetime', 86400); // 24 hours
ini_set('session.cookie_lifetime', 86400);
$__isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? 80) == 443);
ini_set('session.cookie_secure', $__isHttps ? '1' : '0');
ini_set('session.cookie_httponly', true);

// ===================================================================
// SESSION INITIALIZATION
// ===================================================================
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ===================================================================
// APPLICATION CONSTANTS
// ===================================================================
define('APP_NAME', 'Advanced Multi-Prison Management System');
define('APP_VERSION', '1.0.0');
define('APP_ENVIRONMENT', 'production'); // 'development', 'staging', 'production'
define('APP_ROOT', dirname(dirname(__FILE__)));
$__protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? 80) == 443)) ? 'https' : 'http';
$__host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$__docRoot  = realpath($_SERVER['DOCUMENT_ROOT'] ?? getcwd());
$__appRoot  = realpath(dirname(__DIR__));
$__appPath  = ($__docRoot && strpos($__appRoot, $__docRoot) === 0)
    ? str_replace('\\', '/', substr($__appRoot, strlen($__docRoot)))
    : '/PMS';
define('APP_URL', $__protocol . '://' . $__host . $__appPath);
define('API_URL', APP_URL . '/api');
unset($__protocol, $__host, $__docRoot, $__appRoot, $__appPath, $__isHttps);

// ===================================================================
// TIMEZONE
// ===================================================================
date_default_timezone_set('UTC');

// ===================================================================
// CORS HEADERS FOR API REQUESTS ONLY
// ===================================================================
if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
    header('Access-Control-Allow-Origin: ' . APP_URL);
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Content-Type: application/json; charset=utf-8');
}

// ===================================================================
// ERROR REPORTING
// ===================================================================
if (APP_ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

// ===================================================================
// AUTHENTICATION FUNCTIONS
// ===================================================================

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current logged-in user
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'email' => $_SESSION['email'] ?? null,
        'role' => $_SESSION['role'] ?? null,
        'facility_id' => $_SESSION['facility_id'] ?? null,
        'first_name' => $_SESSION['first_name'] ?? null,
        'last_name' => $_SESSION['last_name'] ?? null
    ];
}

/**
 * Get current user ID
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user role
 */
function getCurrentUserRole() {
    return $_SESSION['role'] ?? null;
}

/**
 * Get current user facility
 */
function getCurrentFacility() {
    return $_SESSION['facility_id'] ?? null;
}

/**
 * Check if user has specific role
 */
function hasRole($role) {
    if (is_array($role)) {
        return in_array(getCurrentUserRole(), $role);
    }
    return getCurrentUserRole() === $role;
}

/**
 * Check if user is Super Admin
 */
function isSuperAdmin() {
    return hasRole('SUPER_ADMIN');
}

/**
 * Check if user can access national level data
 */
function canAccessNationalData() {
    return isSuperAdmin();
}

/**
 * Check if user belongs to facility (or is Super Admin)
 */
function belongsToFacility($facilityId) {
    return isSuperAdmin() || getCurrentFacility() == $facilityId;
}

/**
 * Require authentication - redirect to login if not authenticated
 */
function requireAuth() {
    if (!isLoggedIn()) {
        if (isAPIRequest()) {
            jsonResponse([
                'success' => false,
                'message' => 'Authentication required',
                'code' => 'AUTH_REQUIRED'
            ], 401);
        }
        header('Location: ' . APP_URL . '/modules/auth/login.php');
        exit;
    }
}

/**
 * Require specific role
 */
function requireRole($roles = []) {
    requireAuth();
    
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    
    if (!hasRole($roles)) {
        if (isAPIRequest()) {
            jsonResponse([
                'success' => false,
                'message' => 'Access denied',
                'code' => 'ACCESS_DENIED'
            ], 403);
        }
        header('Location: ' . APP_URL . '/dashboard.php');
        exit;
    }
}

/**
 * Require facility access
 */
function requireFacilityAccess($facilityId) {
    requireAuth();
    
    if (!belongsToFacility($facilityId)) {
        if (isAPIRequest()) {
            jsonResponse([
                'success' => false,
                'message' => 'Access denied to this facility',
                'code' => 'FACILITY_ACCESS_DENIED'
            ], 403);
        }
        header('Location: ' . APP_URL . '/dashboard.php');
        exit;
    }
}

/**
 * Logout user
 */
function logout() {
    if (isset($_SESSION['user_id'])) {
        logActivity($_SESSION['user_id'], 'LOGOUT', 'system');
    }
    
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    
    header('Location: ' . APP_URL . '/modules/auth/login.php');
    exit;
}

// ===================================================================
// REQUEST HANDLING
// ===================================================================

/**
 * Check if current request is API
 */
function isAPIRequest() {
    return strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false;
}

/**
 * Get request method
 */
function getRequestMethod() {
    return $_SERVER['REQUEST_METHOD'] ?? 'GET';
}

/**
 * Get JSON input
 */
function getJsonInput() {
    $input = file_get_contents('php://input');
    return json_decode($input, true) ?? [];
}

/**
 * Get POST data
 */
function getPostData() {
    if (empty($_POST)) {
        return getJsonInput();
    }
    return $_POST;
}

/**
 * Get GET parameter
 */
function getParam($key, $default = null) {
    return $_GET[$key] ?? $default;
}

/**
 * Get POST parameter
 */
function postParam($key, $default = null) {
    $data = getPostData();
    return $data[$key] ?? $default;
}

// ===================================================================
// RESPONSE HANDLERS
// ===================================================================

/**
 * Return JSON response
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

/**
 * Return success response
 */
function successResponse($data = [], $message = 'Success') {
    jsonResponse([
        'success' => true,
        'message' => $message,
        'data' => $data
    ]);
}

/**
 * Return error response
 */
function errorResponse($message, $data = [], $statusCode = 400, $code = null) {
    jsonResponse([
        'success' => false,
        'message' => $message,
        'code' => $code,
        'data' => $data
    ], $statusCode);
}

// ===================================================================
// VALIDATION & SANITIZATION
// ===================================================================

/**
 * Sanitize string input
 */
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8'));
}

/**
 * Validate email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate required fields
 */
function validateRequired($data, $fields) {
    $missing = [];
    foreach ($fields as $field) {
        if (empty($data[$field])) {
            $missing[] = $field;
        }
    }
    return empty($missing) ? true : $missing;
}

/**
 * Hash password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verify password
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// ===================================================================
// LOGGING & AUDIT TRAIL
// ===================================================================

/**
 * Log system action
 */
function logAction($action, $module, $entityType, $entityId, $description, $status = 'SUCCESS', $facilityId = null) {
    try {
        require_once APP_ROOT . '/config/db.php';
        
        $userId = getCurrentUserId();
        $facilityId = $facilityId ?? getCurrentFacility();
        
        insert('system_logs', [
            'user_id' => $userId,
            'facility_id' => $facilityId,
            'action' => $action,
            'module' => $module,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'description' => $description,
            'status' => $status,
            'ip_address' => getClientIP(),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    } catch (Exception $e) {
        logError('Audit Logging Error', $e->getMessage());
    }
}

/**
 * Log activity
 */
function logActivity($userId, $activityType, $resource) {
    try {
        require_once APP_ROOT . '/config/db.php';
        
        insert('activity_logs', [
            'user_id' => $userId,
            'activity_type' => $activityType,
            'resource' => $resource,
            'ip_address' => getClientIP(),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    } catch (Exception $e) {
        logError('Activity Logging Error', $e->getMessage());
    }
}

/**
 * Log error
 */
function logError($title, $message) {
    $logFile = APP_ROOT . '/logs/error.log';
    if (!is_dir(dirname($logFile))) {
        mkdir(dirname($logFile), 0755, true);
    }
    
    error_log(sprintf(
        "[%s] %s: %s\n",
        date('Y-m-d H:i:s'),
        $title,
        $message
    ), 3, $logFile);
}

// ===================================================================
// UTILITY FUNCTIONS
// ===================================================================

/**
 * Get client IP address
 */
function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
}

/**
 * Generate secure token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Handle file uploads
 */
function handleFileUpload($fileInputName, $allowedMimes = [], $maxSize = 5242880) {
    if (!isset($_FILES[$fileInputName]) || $_FILES[$fileInputName]['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload error');
    }
    
    $file = $_FILES[$fileInputName];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    
    if (!empty($allowedMimes) && !in_array($mimeType, $allowedMimes)) {
        throw new Exception('Invalid file type');
    }
    
    if ($file['size'] > $maxSize) {
        throw new Exception('File size exceeds maximum');
    }
    
    $uploadDir = APP_ROOT . '/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $filename = generateToken(16) . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
    $uploadPath = $uploadDir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        throw new Exception('Failed to save file');
    }
    
    return $filename;
}

/**
 * Format date for display
 */
function formatDate($date, $format = 'Y-m-d H:i:s') {
    if (empty($date)) {
        return '-';
    }
    return date($format, strtotime($date));
}

/**
 * Generate pagination
 */
function paginate($total, $page, $perPage) {
    $totalPages = ceil($total / $perPage);
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;
    
    return [
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'total_pages' => $totalPages,
        'offset' => $offset,
        'has_prev' => $page > 1,
        'has_next' => $page < $totalPages
    ];
}