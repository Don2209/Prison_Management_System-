<?php
/**
 * Global Application Functions & Helpers
 * Core utility functions for the Prison Management System
 */

// Include required configurations
require_once dirname(__FILE__) . '/../config/db.php';
require_once dirname(__FILE__) . '/../config/app.php';

// ===================================================================
// FACILITY HELPERS
// ===================================================================

/**
 * Get all facilities accessible to user
 */
function getAccessibleFacilities($userId = null) {
    if (!$userId) {
        $userId = getCurrentUserId();
    }
    
    try {
        $user = fetchOne(
            "SELECT role, facility_id FROM users WHERE id = ?",
            [$userId],
            'i'
        );
        
        if (!$user) {
            return [];
        }
        
        // Super admin see all facilities
        if ($user['role'] === 'SUPER_ADMIN') {
            return fetchAll(
                "SELECT id, name, location, type, status FROM facilities WHERE deleted_at IS NULL ORDER BY name"
            );
        }
        
        // Others see only their facility
        return fetchAll(
            "SELECT id, name, location, type, status FROM facilities WHERE id = ? AND deleted_at IS NULL",
            [$user['facility_id']],
            'i'
        );
    } catch (Exception $e) {
        logError('Get Accessible Facilities', $e->getMessage());
        return [];
    }
}

/**
 * Get facility details
 */
function getFacility($facilityId) {
    try {
        return fetchOne(
            "SELECT * FROM facilities WHERE id = ? AND deleted_at IS NULL",
            [$facilityId],
            'i'
        );
    } catch (Exception $e) {
        logError('Get Facility', $e->getMessage());
        return null;
    }
}

/**
 * Get facility population count
 */
function getFacilityPopulation($facilityId) {
    try {
        $result = fetchOne(
            "SELECT COUNT(*) as count FROM inmates WHERE facility_id = ? AND status IN ('REMAND', 'CONVICTED') AND deleted_at IS NULL",
            [$facilityId],
            'i'
        );
        return $result['count'] ?? 0;
    } catch (Exception $e) {
        return 0;
    }
}

// ===================================================================
// USER & ROLE HELPERS
// ===================================================================

/**
 * Get user by ID
 */
function getUser($userId) {
    try {
        return fetchOne(
            "SELECT id, facility_id, username, email, first_name, last_name, role, is_active, last_login FROM users WHERE id = ? AND deleted_at IS NULL",
            [$userId],
            'i'
        );
    } catch (Exception $e) {
        logError('Get User', $e->getMessage());
        return null;
    }
}

/**
 * Get user by username
 */
function getUserByUsername($username) {
    try {
        return fetchOne(
            "SELECT * FROM users WHERE username = ? AND deleted_at IS NULL",
            [$username],
            's'
        );
    } catch (Exception $e) {
        logError('Get User By Username', $e->getMessage());
        return null;
    }
}

/**
 * Get users in facility
 */
function getFacilityUsers($facilityId, $role = null) {
    try {
        if ($role) {
            return fetchAll(
                "SELECT id, username, email, first_name, last_name, role, is_active FROM users WHERE facility_id = ? AND role = ? AND deleted_at IS NULL ORDER BY first_name",
                [$facilityId, $role],
                'is'
            );
        }
        
        return fetchAll(
            "SELECT id, username, email, first_name, last_name, role, is_active FROM users WHERE facility_id = ? AND deleted_at IS NULL ORDER BY first_name",
            [$facilityId],
            'i'
        );
    } catch (Exception $e) {
        logError('Get Facility Users', $e->getMessage());
        return [];
    }
}

// ===================================================================
// INMATE HELPERS
// ===================================================================

/**
 * Get inmate by ID
 */
function getInmate($inmateId) {
    try {
        return fetchOne(
            "SELECT * FROM inmates WHERE id = ? AND deleted_at IS NULL",
            [$inmateId],
            'i'
        );
    } catch (Exception $e) {
        logError('Get Inmate', $e->getMessage());
        return null;
    }
}

/**
 * Get inmate by ID number
 */
function getInmateByID($inmate_id) {
    try {
        return fetchOne(
            "SELECT * FROM inmates WHERE inmate_id = ? AND deleted_at IS NULL",
            [$inmate_id],
            's'
        );
    } catch (Exception $e) {
        logError('Get Inmate By ID', $e->getMessage());
        return null;
    }
}

/**
 * Get facility inmates
 */
function getFacilityInmates($facilityId, $status = null, $limit = 100, $offset = 0) {
    try {
        if ($status) {
            $result = fetchAll(
                "SELECT * FROM inmates WHERE facility_id = ? AND status = ? AND deleted_at IS NULL ORDER BY admission_date DESC LIMIT ? OFFSET ?",
                [$facilityId, $status, $limit, $offset],
                'isii'
            );
        } else {
            $result = fetchAll(
                "SELECT * FROM inmates WHERE facility_id = ? AND deleted_at IS NULL ORDER BY admission_date DESC LIMIT ? OFFSET ?",
                [$facilityId, $limit, $offset],
                'iii'
            );
        }
        return $result;
    } catch (Exception $e) {
        logError('Get Facility Inmates', $e->getMessage());
        return [];
    }
}

/**
 * Get all inmates across all facilities (super admin use)
 */
function getAllInmates($status = null, $limit = 100, $offset = 0) {
    try {
        if ($status) {
            $result = fetchAll(
                "SELECT * FROM inmates WHERE status = ? AND deleted_at IS NULL ORDER BY admission_date DESC LIMIT ? OFFSET ?",
                [$status, $limit, $offset],
                'sii'
            );
        } else {
            $result = fetchAll(
                "SELECT * FROM inmates WHERE deleted_at IS NULL ORDER BY admission_date DESC LIMIT ? OFFSET ?",
                [$limit, $offset],
                'ii'
            );
        }
        return $result;
    } catch (Exception $e) {
        logError('Get All Inmates', $e->getMessage());
        return [];
    }
}

/**
 * Get inmate current cell
 */
function getInmateCell($inmateId) {
    try {
        $result = fetchOne(
            "SELECT c.* FROM cells c 
            JOIN inmate_cell_assignment ica ON c.id = ica.cell_id 
            WHERE ica.inmate_id = ? AND ica.status = 'ACTIVE' AND ica.deleted_at IS NULL",
            [$inmateId],
            'i'
        );
        return $result;
    } catch (Exception $e) {
        logError('Get Inmate Cell', $e->getMessage());
        return null;
    }
}

// ===================================================================
// STAFF HELPERS
// ===================================================================

/**
 * Get staff member
 */
function getStaff($staffId) {
    try {
        return fetchOne(
            "SELECT s.*, u.username, u.email, u.first_name, u.last_name FROM staff s
            JOIN users u ON s.user_id = u.id
            WHERE s.id = ? AND s.deleted_at IS NULL",
            [$staffId],
            'i'
        );
    } catch (Exception $e) {
        logError('Get Staff', $e->getMessage());
        return null;
    }
}

/**
 * Get facility staff
 */
function getFacilityStaff($facilityId, $staffType = null) {
    try {
        if ($staffType) {
            return fetchAll(
                "SELECT s.*, u.username, u.email, u.first_name, u.last_name FROM staff s
                JOIN users u ON s.user_id = u.id
                WHERE s.facility_id = ? AND s.staff_type = ? AND s.deleted_at IS NULL
                ORDER BY u.first_name",
                [$facilityId, $staffType],
                'is'
            );
        }
        
        return fetchAll(
            "SELECT s.*, u.username, u.email, u.first_name, u.last_name FROM staff s
            JOIN users u ON s.user_id = u.id
            WHERE s.facility_id = ? AND s.deleted_at IS NULL
            ORDER BY u.first_name",
            [$facilityId],
            'i'
        );
    } catch (Exception $e) {
        logError('Get Facility Staff', $e->getMessage());
        return [];
    }
}

// ===================================================================
// INCIDENT HELPERS
// ===================================================================

/**
 * Get incidents for facility
 */
function getFacilityIncidents($facilityId, $status = null, $limit = 50) {
    try {
        if ($status) {
            return fetchAll(
                "SELECT i.*, ic.name as category_name, u.first_name, u.last_name FROM incidents i
                JOIN incident_categories ic ON i.incident_category_id = ic.id
                JOIN users u ON i.reported_by = u.id
                WHERE i.facility_id = ? AND i.status = ? AND i.deleted_at IS NULL
                ORDER BY i.incident_date DESC LIMIT ?",
                [$facilityId, $status, $limit],
                'isi'
            );
        }
        
        return fetchAll(
            "SELECT i.*, ic.name as category_name, u.first_name, u.last_name FROM incidents i
            JOIN incident_categories ic ON i.incident_category_id = ic.id
            JOIN users u ON i.reported_by = u.id
            WHERE i.facility_id = ? AND i.deleted_at IS NULL
            ORDER BY i.incident_date DESC LIMIT ?",
            [$facilityId, $limit],
            'ii'
        );
    } catch (Exception $e) {
        logError('Get Facility Incidents', $e->getMessage());
        return [];
    }
}

// ===================================================================
// INVENTORY HELPERS
// ===================================================================

/**
 * Get inventory item
 */
function getInventoryItem($itemId) {
    try {
        return fetchOne(
            "SELECT i.*, c.name as category_name, w.name as warehouse_name, s.name as supplier_name
            FROM inventory_items i
            LEFT JOIN inventory_categories c ON i.category_id = c.id
            LEFT JOIN warehouses w ON i.warehouse_id = w.id
            LEFT JOIN suppliers s ON i.supplier_id = s.id
            WHERE i.id = ? AND i.deleted_at IS NULL",
            [$itemId],
            'i'
        );
    } catch (Exception $e) {
        logError('Get Inventory Item', $e->getMessage());
        return null;
    }
}

/**
 * Get items needing reorder
 */
function getItemsNeedingReorder($facilityId) {
    try {
        return fetchAll(
            "SELECT i.* FROM inventory_items i
            WHERE i.facility_id = ? AND i.current_quantity <= i.reorder_level AND i.deleted_at IS NULL
            ORDER BY i.current_quantity ASC",
            [$facilityId],
            'i'
        );
    } catch (Exception $e) {
        logError('Get Items Needing Reorder', $e->getMessage());
        return [];
    }
}

/**
 * Get warehouse inventory
 */
function getWarehouseInventory($warehouseId) {
    try {
        return fetchAll(
            "SELECT i.*, c.name as category_name FROM inventory_items i
            LEFT JOIN inventory_categories c ON i.category_id = c.id
            WHERE i.warehouse_id = ? AND i.deleted_at IS NULL
            ORDER BY c.name, i.name",
            [$warehouseId],
            'i'
        );
    } catch (Exception $e) {
        logError('Get Warehouse Inventory', $e->getMessage());
        return [];
    }
}

// ===================================================================
// FINANCIAL HELPERS
// ===================================================================

/**
 * Get inmate account
 */
function getInmateAccount($inmateId) {
    try {
        return fetchOne(
            "SELECT * FROM inmate_accounts WHERE inmate_id = ? AND deleted_at IS NULL",
            [$inmateId],
            'i'
        );
    } catch (Exception $e) {
        logError('Get Inmate Account', $e->getMessage());
        return null;
    }
}

/**
 * Get account transactions
 */
function getAccountTransactions($accountId, $limit = 50) {
    try {
        return fetchAll(
            "SELECT * FROM inmate_transactions WHERE account_id = ? AND deleted_at IS NULL
            ORDER BY created_at DESC LIMIT ?",
            [$accountId, $limit],
            'ii'
        );
    } catch (Exception $e) {
        logError('Get Account Transactions', $e->getMessage());
        return [];
    }
}

/**
 * Process transaction
 */
function processTransaction($accountId, $type, $amount, $description, $userId) {
    try {
        $account = fetchOne(
            "SELECT * FROM inmate_accounts WHERE id = ?",
            [$accountId],
            'i'
        );
        
        if (!$account) {
            throw new Exception('Account not found');
        }
        
        $balanceBefore = $account['account_balance'];
        $balanceAfter = $balanceBefore;
        
        if ($type === 'DEPOSIT') {
            $balanceAfter += $amount;
        } elseif ($type === 'WITHDRAWAL') {
            if ($balanceBefore < $amount) {
                throw new Exception('Insufficient balance');
            }
            $balanceAfter -= $amount;
        }
        
        // Update account balance
        update('inmate_accounts', ['account_balance' => $balanceAfter], 'id = ?', [$accountId]);
        
        // Record transaction
        insert('inmate_transactions', [
            'facility_id' => $account['facility_id'],
            'account_id' => $accountId,
            'transaction_type' => $type,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'description' => $description,
            'processed_by' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        return true;
    } catch (Exception $e) {
        logError('Process Transaction', $e->getMessage());
        throw $e;
    }
}

// ===================================================================
// CELL & CAPACITY HELPERS
// ===================================================================

/**
 * Get cell block
 */
function getCellBlock($blockId) {
    try {
        return fetchOne(
            "SELECT * FROM cell_blocks WHERE id = ? AND deleted_at IS NULL",
            [$blockId],
            'i'
        );
    } catch (Exception $e) {
        logError('Get Cell Block', $e->getMessage());
        return null;
    }
}

/**
 * Check if cell is available
 */
function isCellAvailable($cellId) {
    try {
        $cell = fetchOne(
            "SELECT capacity, current_occupancy FROM cells WHERE id = ? AND deleted_at IS NULL",
            [$cellId],
            'i'
        );
        
        return $cell && $cell['current_occupancy'] < $cell['capacity'];
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get facility occupancy
 */
function getFacilityOccupancy($facilityId) {
    try {
        $result = fetchOne(
            "SELECT COUNT(*) as current FROM inmates WHERE facility_id = ? AND status IN ('REMAND', 'CONVICTED') AND deleted_at IS NULL",
            [$facilityId],
            'i'
        );
        
        $facility = getFacility($facilityId);
        
        return [
            'current' => $result['current'] ?? 0,
            'capacity' => $facility['total_capacity'] ?? 0,
            'percentage' => $facility['total_capacity'] ? round(($result['current'] / $facility['total_capacity']) * 100, 2) : 0,
            'is_overcrowded' => ($result['current'] ?? 0) > ($facility['total_capacity'] ?? 0)
        ];
    } catch (Exception $e) {
        logError('Get Facility Occupancy', $e->getMessage());
        return null;
    }
}

// ===================================================================
// MEDICAL HELPERS
// ===================================================================

/**
 * Get latest medical record
 */
function getLatestMedicalRecord($inmateId) {
    try {
        return fetchOne(
            "SELECT * FROM medical_records WHERE inmate_id = ? AND deleted_at IS NULL
            ORDER BY record_date DESC LIMIT 1",
            [$inmateId],
            'i'
        );
    } catch (Exception $e) {
        logError('Get Latest Medical Record', $e->getMessage());
        return null;
    }
}

/**
 * Get medications for inmate
 */
function getInmateMedications($inmateId) {
    try {
        return fetchAll(
            "SELECT * FROM medication_history WHERE inmate_id = ? AND (end_date IS NULL OR end_date >= CURDATE()) AND deleted_at IS NULL",
            [$inmateId],
            'i'
        );
    } catch (Exception $e) {
        logError('Get Inmate Medications', $e->getMessage());
        return [];
    }
}

// ===================================================================
// PROGRAM HELPERS
// ===================================================================

/**
 * Get active programs in facility
 */
function getActiveProgramsInFacility($facilityId) {
    try {
        return fetchAll(
            "SELECT * FROM rehabilitation_programs WHERE facility_id = ? AND status = 'ACTIVE' AND deleted_at IS NULL
            ORDER BY name",
            [$facilityId],
            'i'
        );
    } catch (Exception $e) {
        logError('Get Active Programs', $e->getMessage());
        return [];
    }
}

/**
 * Get enrollments for program
 */
function getProgramEnrollments($programId) {
    try {
        return fetchAll(
            "SELECT pe.*, i.first_name, i.last_name, i.inmate_id FROM program_enrollment pe
            JOIN inmates i ON pe.inmate_id = i.id
            WHERE pe.program_id = ? AND pe.deleted_at IS NULL
            ORDER BY pe.enrollment_date DESC",
            [$programId],
            'i'
        );
    } catch (Exception $e) {
        logError('Get Program Enrollments', $e->getMessage());
        return [];
    }
}

// ===================================================================
// COMPLAINT HELPERS
// ===================================================================

/**
 * Get pending complaints
 */
function getPendingComplaints($facilityId, $limit = 20) {
    try {
        return fetchAll(
            "SELECT c.*, i.first_name, i.last_name, i.inmate_id FROM complaints c
            JOIN inmates i ON c.inmate_id = i.id
            WHERE c.facility_id = ? AND c.status IN ('SUBMITTED', 'UNDER_REVIEW') AND c.deleted_at IS NULL
            ORDER BY c.submission_date DESC LIMIT ?",
            [$facilityId, $limit],
            'ii'
        );
    } catch (Exception $e) {
        logError('Get Pending Complaints', $e->getMessage());
        return [];
    }
}

// ===================================================================
// STATISTICS & REPORTING
// ===================================================================

/**
 * Get facility statistics
 */
function getFacilityStatistics($facilityId) {
    try {
        $stats = [];
        
        // Total inmates
        $result = fetchOne(
            "SELECT COUNT(*) as count FROM inmates WHERE facility_id = ? AND status IN ('REMAND', 'CONVICTED') AND deleted_at IS NULL",
            [$facilityId],
            'i'
        );
        $stats['total_inmates'] = $result['count'] ?? 0;
        
        // Staff count
        $result = fetchOne(
            "SELECT COUNT(*) as count FROM staff WHERE facility_id = ? AND employment_status = 'ACTIVE' AND deleted_at IS NULL",
            [$facilityId],
            'i'
        );
        $stats['total_staff'] = $result['count'] ?? 0;
        
        // Incidents this month
        $result = fetchOne(
            "SELECT COUNT(*) as count FROM incidents WHERE facility_id = ? AND MONTH(incident_date) = MONTH(NOW()) AND YEAR(incident_date) = YEAR(NOW()) AND deleted_at IS NULL",
            [$facilityId],
            'i'
        );
        $stats['incidents_month'] = $result['count'] ?? 0;
        
        // Pending approvals
        $result = fetchOne(
            "SELECT COUNT(*) as count FROM inmate_transfers WHERE facility_to = ? AND approval_status = 'PENDING' AND deleted_at IS NULL",
            [$facilityId],
            'i'
        );
        $stats['pending_transfers'] = $result['count'] ?? 0;
        
        return $stats;
    } catch (Exception $e) {
        logError('Get Facility Statistics', $e->getMessage());
        return [];
    }
}

// ===================================================================
// UTILITY FUNCTIONS
// ===================================================================

/**
 * Get all roles (for admin functions)
 */
function getAllRoles() {
    return [
        'SUPER_ADMIN' => 'Super Administrator',
        'FACILITY_ADMIN' => 'Facility Administrator',
        'OFFICER' => 'Officer',
        'MEDICAL_STAFF' => 'Medical Staff',
        'RECORDS_OFFICER' => 'Records Officer',
        'FINANCE_OFFICER' => 'Finance Officer'
    ];
}

/**
 * Get status labels
 */
function getStatusLabels($type = 'inmate') {
    $statuses = [
        'inmate' => ['REMAND' => 'Remand', 'CONVICTED' => 'Convicted', 'RELEASED' => 'Released', 'TRANSFERRED' => 'Transferred', 'DECEASED' => 'Deceased'],
        'staff' => ['ACTIVE' => 'Active', 'INACTIVE' => 'Inactive', 'ON_LEAVE' => 'On Leave', 'TERMINATED' => 'Terminated'],
        'incident' => ['REPORTED' => 'Reported', 'UNDER_INVESTIGATION' => 'Under Investigation', 'RESOLVED' => 'Resolved'],
        'transfer' => ['PENDING' => 'Pending', 'APPROVED' => 'Approved', 'REJECTED' => 'Rejected']
    ];
    
    return $statuses[$type] ?? [];
}

/**
 * Convert array to where clause conditions
 */
function buildWhereClause($conditions) {
    $where = [];
    foreach ($conditions as $field => $value) {
        if ($value === null) {
            $where[] = "$field IS NULL";
        } else {
            $where[] = "$field = '$value'";
        }
    }
    return implode(' AND ', $where);
}

// ===================================================================
// SUPER-ADMIN: SYSTEM-WIDE AGGREGATE HELPERS
// ===================================================================

/**
 * Get system-wide statistics (all facilities).
 * Used by Super Admin dashboard.
 */
function getSystemStatistics() {
    try {
        $stats = [];

        $r = fetchOne("SELECT COUNT(*) as c FROM inmates WHERE status IN ('REMAND','CONVICTED') AND deleted_at IS NULL");
        $stats['total_inmates'] = (int)($r['c'] ?? 0);

        $r = fetchOne("SELECT COUNT(*) as c FROM staff WHERE employment_status='ACTIVE' AND deleted_at IS NULL");
        $stats['total_staff'] = (int)($r['c'] ?? 0);

        $r = fetchOne("SELECT COUNT(*) as c FROM incidents WHERE MONTH(incident_date)=MONTH(NOW()) AND YEAR(incident_date)=YEAR(NOW()) AND deleted_at IS NULL");
        $stats['incidents_month'] = (int)($r['c'] ?? 0);

        $r = fetchOne("SELECT COUNT(*) as c FROM inmate_transfers WHERE approval_status='PENDING' AND deleted_at IS NULL");
        $stats['pending_transfers'] = (int)($r['c'] ?? 0);

        $r = fetchOne("SELECT COUNT(*) as c FROM facilities WHERE deleted_at IS NULL");
        $stats['total_facilities'] = (int)($r['c'] ?? 0);

        $r = fetchOne("SELECT COUNT(*) as c FROM users WHERE is_active=1 AND deleted_at IS NULL");
        $stats['total_users'] = (int)($r['c'] ?? 0);

        return $stats;
    } catch (Exception $e) {
        logError('Get System Statistics', $e->getMessage());
        return [];
    }
}

/**
 * Get system-wide occupancy totals + per-facility breakdown.
 * Returns ['current', 'capacity', 'percentage', 'is_overcrowded', 'facilities' => [...]]
 */
function getSystemOccupancy() {
    try {
        $facilities = fetchAll(
            "SELECT f.id, f.name, f.location, f.total_capacity,
                    COUNT(i.id) as current_population
             FROM facilities f
             LEFT JOIN inmates i ON i.facility_id = f.id
                 AND i.status IN ('REMAND','CONVICTED') AND i.deleted_at IS NULL
             WHERE f.deleted_at IS NULL
             GROUP BY f.id
             ORDER BY f.name"
        );

        $totalCurrent  = 0;
        $totalCapacity = 0;
        foreach ($facilities as &$fac) {
            $fac['current_population'] = (int)$fac['current_population'];
            $fac['total_capacity']     = (int)($fac['total_capacity'] ?? 0);
            $fac['occupancy_pct']      = $fac['total_capacity'] > 0
                ? round(($fac['current_population'] / $fac['total_capacity']) * 100, 1)
                : 0;
            $fac['is_overcrowded']     = $fac['current_population'] > $fac['total_capacity'];
            $totalCurrent  += $fac['current_population'];
            $totalCapacity += $fac['total_capacity'];
        }
        unset($fac);

        return [
            'current'       => $totalCurrent,
            'capacity'      => $totalCapacity,
            'percentage'    => $totalCapacity > 0 ? round(($totalCurrent / $totalCapacity) * 100, 1) : 0,
            'is_overcrowded'=> $totalCurrent > $totalCapacity,
            'facilities'    => $facilities,
        ];
    } catch (Exception $e) {
        logError('Get System Occupancy', $e->getMessage());
        return ['current'=>0,'capacity'=>0,'percentage'=>0,'is_overcrowded'=>false,'facilities'=>[]];
    }
}

/**
 * Get all pending complaints across all facilities (Super Admin).
 */
function getAllPendingComplaints($limit = 20) {
    try {
        return fetchAll(
            "SELECT c.*, i.first_name, i.last_name, i.inmate_id, f.name as facility_name
             FROM complaints c
             JOIN inmates i  ON c.inmate_id   = i.id
             JOIN facilities f ON c.facility_id = f.id
             WHERE c.status IN ('SUBMITTED','UNDER_REVIEW') AND c.deleted_at IS NULL
             ORDER BY c.submission_date DESC LIMIT ?",
            [$limit], 'i'
        );
    } catch (Exception $e) {
        logError('Get All Pending Complaints', $e->getMessage());
        return [];
    }
}

/**
 * Get all recent incidents across all facilities (Super Admin).
 */
function getAllRecentIncidents($limit = 20) {
    try {
        return fetchAll(
            "SELECT i.*, ic.name as category_name,
                    u.first_name, u.last_name,
                    f.name as facility_name
             FROM incidents i
             JOIN incident_categories ic ON i.incident_category_id = ic.id
             JOIN users u       ON i.reported_by  = u.id
             JOIN facilities f  ON i.facility_id  = f.id
             WHERE i.deleted_at IS NULL
             ORDER BY i.incident_date DESC LIMIT ?",
            [$limit], 'i'
        );
    } catch (Exception $e) {
        logError('Get All Recent Incidents', $e->getMessage());
        return [];
    }
}
