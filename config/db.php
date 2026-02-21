<?php
/**
 * Database Configuration
 * Production-ready database connection handler
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'prison_management');
define('DB_CHARSET', 'utf8mb4');

/**
 * Establish database connection
 */
function getDBConnection() {
    static $connection = null;
    
    if ($connection === null) {
        try {
            $connection = new mysqli(
                DB_HOST,
                DB_USER,
                DB_PASS,
                DB_NAME
            );
            
            // Check connection
            if ($connection->connect_error) {
                throw new Exception('Database connection failed: ' . $connection->connect_error);
            }
            
            // Set charset
            if (!$connection->set_charset(DB_CHARSET)) {
                throw new Exception('Error setting charset: ' . $connection->error);
            }
            
        } catch (Exception $e) {
            error_log('Database Connection Error: ' . $e->getMessage());
            die(json_encode([
                'success' => false,
                'message' => 'Database connection failed',
                'error' => $e->getMessage()
            ]));
        }
    }
    
    return $connection;
}

/**
 * Get database instance - alias for getDBConnection
 */
function getDB() {
    return getDBConnection();
}

/**
 * Execute prepared statement with parameters
 */
function executeQuery($query, $params = [], $types = '') {
    $db = getDB();
    
    if (!$types && !empty($params)) {
        // Auto-detect types if not provided
        $types = str_repeat('s', count($params));
    }
    
    $stmt = $db->prepare($query);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $db->error);
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }
    
    return $stmt;
}

/**
 * Fetch single row as associative array
 */
function fetchOne($query, $params = [], $types = '') {
    $stmt = executeQuery($query, $params, $types);
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row;
}

/**
 * Fetch all rows
 */
function fetchAll($query, $params = [], $types = '') {
    $stmt = executeQuery($query, $params, $types);
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

/**
 * Insert query helper
 */
function insert($table, $data) {
    $db = getDB();
    $columns = array_keys($data);
    $values = array_values($data);
    $placeholders = array_fill(0, count($columns), '?');
    
    $query = sprintf(
        'INSERT INTO %s (%s) VALUES (%s)',
        $table,
        implode(',', $columns),
        implode(',', $placeholders)
    );
    
    // Build types string
    $types = '';
    foreach ($values as $value) {
        if (is_int($value)) $types .= 'i';
        elseif (is_float($value)) $types .= 'd';
        else $types .= 's';
    }
    
    $stmt = $db->prepare($query);
    if (!$stmt) {
        throw new Exception('Insert prepare failed: ' . $db->error);
    }
    
    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    
    $result = [
        'id' => $stmt->insert_id,
        'affected_rows' => $stmt->affected_rows
    ];
    
    $stmt->close();
    return $result;
}

/**
 * Update query helper
 */
function update($table, $data, $where, $whereParams = []) {
    $db = getDB();
    
    $sets = [];
    $values = [];
    $types = '';
    
    foreach ($data as $column => $value) {
        $sets[] = "$column = ?";
        $values[] = $value;
        if (is_int($value)) $types .= 'i';
        elseif (is_float($value)) $types .= 'd';
        else $types .= 's';
    }
    
    foreach ($whereParams as $value) {
        $values[] = $value;
        if (is_int($value)) $types .= 'i';
        elseif (is_float($value)) $types .= 'd';
        else $types .= 's';
    }
    
    $query = sprintf(
        'UPDATE %s SET %s WHERE %s',
        $table,
        implode(', ', $sets),
        $where
    );
    
    $stmt = $db->prepare($query);
    if (!$stmt) {
        throw new Exception('Update prepare failed: ' . $db->error);
    }
    
    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    
    $result = $stmt->affected_rows;
    $stmt->close();
    return $result;
}

/**
 * Delete query helper
 */
function delete($table, $where, $params = []) {
    $db = getDB();
    
    $types = '';
    foreach ($params as $value) {
        if (is_int($value)) $types .= 'i';
        elseif (is_float($value)) $types .= 'd';
        else $types .= 's';
    }
    
    $query = "DELETE FROM $table WHERE $where";
    
    $stmt = $db->prepare($query);
    if (!$stmt) {
        throw new Exception('Delete prepare failed: ' . $db->error);
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->affected_rows;
    $stmt->close();
    return $result;
}

/**
 * Soft delete helper
 */
function softDelete($table, $where, $params = []) {
    return update($table, ['deleted_at' => date('Y-m-d H:i:s')], $where, $params);
}

/**
 * Count rows
 */
function countRows($table, $where = '', $params = []) {
    $query = "SELECT COUNT(*) as count FROM $table";
    if (!empty($where)) {
        $query .= " WHERE $where";
    }
    
    $result = fetchOne($query, $params);
    return $result['count'] ?? 0;
}