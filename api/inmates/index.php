<?php
/**
 * Inmates Management API Endpoint
 */

require_once dirname(__FILE__) . '/../../config/app.php';
require_once dirname(__FILE__) . '/../../config/db.php';
require_once dirname(__FILE__) . '/../../includes/functions.php';
require_once dirname(__FILE__) . '/../../includes/auth.php';

requireAuth();
verifyPermission('view', 'inmates');

$method = getRequestMethod();

try {
    switch ($method) {
        case 'GET':
            handleGetInmates();
            break;
        case 'POST':
            verifyPermission('create', 'inmates');
            handleCreateInmate();
            break;
        case 'PUT':
            verifyPermission('edit', 'inmates');
            handleUpdateInmate();
            break;
        default:
            errorResponse('Method not allowed', [], 405);
    }
} catch (Exception $e) {
    logError('Inmates API Error', $e->getMessage());
    errorResponse($e->getMessage(), [], 500);
}

/**
 * Handle GET requests
 */
function handleGetInmates() {
    $id = getParam('id');
    $requestedFacilityId = getParam('facility_id');
    $status = getParam('status');
    $page = (int)getParam('page', 1);
    $perPage = (int)getParam('per_page', 20);

    // Super admins see all facilities unless a specific one is requested.
    // Non-super admins are always scoped to their own facility.
    $filterByFacility = !isSuperAdmin() || $requestedFacilityId;
    $facilityId = $requestedFacilityId ?: getCurrentFacility();

    // Validate facility access only when filtering by a specific facility
    if ($filterByFacility && $facilityId) {
        requireFacilityAccess($facilityId);
    }

    if ($id) {
        // Get single inmate
        $inmate = getInmate($id);
        if (!$inmate) {
            errorResponse('Inmate not found', [], 404);
        }

        // Get cell assignment
        $cell = getInmateCell($id);
        $inmate['cell'] = $cell;

        // Get medical records
        $medical = getLatestMedicalRecord($id);
        $inmate['medical'] = $medical;

        // Get medications
        $medications = getInmateMedications($id);
        $inmate['medications'] = $medications;

        successResponse($inmate);
    } else {
        // List inmates
        if ($filterByFacility && $facilityId) {
            // Scoped to one facility
            $total  = countRows('inmates', 'facility_id = ? AND deleted_at IS NULL', [$facilityId]);
            $pagination = paginate($total, $page, $perPage);
            $inmates = getFacilityInmates($facilityId, $status, $pagination['per_page'], $pagination['offset']);
        } else {
            // Super admin – all facilities
            $total  = countRows('inmates', 'deleted_at IS NULL' . ($status ? ' AND status = ?' : ''), $status ? [$status] : []);
            $pagination = paginate($total, $page, $perPage);
            $inmates = getAllInmates($status, $pagination['per_page'], $pagination['offset']);
        }

        successResponse([
            'inmates' => $inmates,
            'pagination' => $pagination
        ]);
    }
}

/**
 * Handle POST requests - Create inmate
 */
function handleCreateInmate() {
    $data = getPostData();
    
    // Validate required fields
    $validation = validateRequired($data, ['inmate_id', 'first_name', 'last_name', 'date_of_birth', 'gender', 'admission_date']);
    if ($validation !== true) {
        errorResponse('Missing required fields: ' . implode(', ', $validation), [], 400);
    }
    
    $facilityId = $data['facility_id'] ?? getCurrentFacility();
    requireFacilityAccess($facilityId);
    
    // Check if inmate ID exists
    if (getInmateByID($data['inmate_id'])) {
        errorResponse('Inmate ID already exists', [], 409);
    }
    
    // Create inmate
    $inmateData = [
        'facility_id' => (int)$facilityId,
        'inmate_id' => sanitize($data['inmate_id']),
        'first_name' => sanitize($data['first_name']),
        'last_name' => sanitize($data['last_name']),
        'date_of_birth' => $data['date_of_birth'],
        'gender' => $data['gender'],
        'admission_date' => $data['admission_date'],
        'status' => $data['status'] ?? 'REMAND',
        'risk_classification' => $data['risk_classification'] ?? 'MEDIUM',
        'national_id' => $data['national_id'] ?? null,
        'biometric_id' => $data['biometric_id'] ?? null,
        'sentence_length_months' => isset($data['sentence_length_months']) ? (int)$data['sentence_length_months'] : null,
        'religion' => $data['religion'] ?? null,
        'mother_tongue' => $data['mother_tongue'] ?? null,
        'next_of_kin_name' => $data['next_of_kin_name'] ?? null,
        'next_of_kin_phone' => $data['next_of_kin_phone'] ?? null,
        'medical_conditions' => $data['medical_conditions'] ?? null,
        'physical_marks' => $data['physical_marks'] ?? null,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    $result = insert('inmates', $inmateData);
    
    // Create inmate account
    insert('inmate_accounts', [
        'facility_id' => (int)$facilityId,
        'inmate_id' => $result['id'],
        'account_balance' => 0.00,
        'account_status' => 'ACTIVE',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    
    // Log action
    logAction('CREATE_INMATE', 'INMATES', 'inmate', $result['id'], "Admitted inmate: {$data['inmate_id']}", 'SUCCESS');
    
    successResponse(['id' => $result['id']], 'Inmate admitted successfully', 201);
}

/**
 * Handle PUT requests - Update inmate
 */
function handleUpdateInmate() {
    $data = getPostData();
    
    if (!isset($data['id'])) {
        errorResponse('Inmate ID is required', [], 400);
    }
    
    $inmate = getInmate($data['id']);
    if (!$inmate) {
        errorResponse('Inmate not found', [], 404);
    }
    
    requireFacilityAccess($inmate['facility_id']);
    
    // Build update data
    $updateData = [];
    $allowedFields = ['first_name', 'last_name', 'status', 'risk_classification', 'release_date', 'medical_conditions', 'physical_marks'];
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updateData[$field] = $field === 'medical_conditions' || $field === 'physical_marks' 
                ? $data[$field] 
                : sanitize($data[$field]);
        }
    }
    
    if (empty($updateData)) {
        errorResponse('No fields to update', [], 400);
    }
    
    $updateData['updated_at'] = date('Y-m-d H:i:s');
    
    update('inmates', $updateData, 'id = ?', [$data['id']]);
    
    // Log action
    logAction('UPDATE_INMATE', 'INMATES', 'inmate', $data['id'], 'Updated inmate details', 'SUCCESS');
    
    successResponse([], 'Inmate updated successfully');
}

/**
 * Handle DELETE requests
 */
function handleDeleteUser() {
    $data = getPostData();
    
    if (!isset($data['id'])) {
        errorResponse('Inmate ID is required', [], 400);
    }
    
    verifyPermission('delete', 'inmates');
    
    $inmate = getInmate($data['id']);
    if (!$inmate) {
        errorResponse('Inmate not found', [], 404);
    }
    
    requireFacilityAccess($inmate['facility_id']);
    
    // Soft delete
    softDelete('inmates', 'id = ?', [$data['id']]);
    
    // Log action
    logAction('DELETE_INMATE', 'INMATES', 'inmate', $data['id'], 'Deleted inmate record', 'SUCCESS');
    
    successResponse([], 'Inmate record deleted');
}
