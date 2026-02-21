<?php
/**
 * Medical Management API
 * Handles medical records, medications, and health information
 */

header('Content-Type: application/json');
require_once '../../config/app.php';
require_once '../../includes/auth.php';

// Require authentication
requireAuth();

$method = getRequestMethod();
$action = getParam('action');

try {
    switch ($method) {
        case 'GET':
            if ($action === 'medications') {
                handleGetMedications();
            } else {
                handleGetMedicalRecords();
            }
            break;
        case 'POST':
            if ($action === 'medication') {
                handleAddMedication();
            } else {
                handleCreateMedicalRecord();
            }
            break;
        case 'PUT':
            if ($action === 'medication') {
                handleUpdateMedication();
            } else {
                handleUpdateMedicalRecord();
            }
            break;
        case 'DELETE':
            if ($action === 'medication') {
                handleDeleteMedication();
            } else {
                handleDeleteMedicalRecord();
            }
            break;
        default:
            jsonResponse(['error' => 'Method not allowed'], 405);
    }
} catch (Exception $e) {
    logError('Medical API Error', $e->getMessage());
    errorResponse($e->getMessage(), [], 500);
}

/**
 * Get medical records
 */
function handleGetMedicalRecords() {
    verifyPermission('view', 'medical_records');
    
    $recordId = getParam('id');
    $inmateId = getParam('inmate_id');
    $facilityId = getCurrentFacility();
    $page = (int) getParam('page') ?: 1;
    $perPage = (int) getParam('per_page') ?: 20;
    
    if ($recordId) {
        // Get single record with medications
        $query = "SELECT mr.*, i.inmate_id, i.first_name, i.last_name, u.first_name as doctor_first, u.last_name as doctor_last
                  FROM medical_records mr
                  JOIN inmates i ON mr.inmate_id = i.id
                  LEFT JOIN users u ON mr.recording_doctor_id = u.id
                  WHERE mr.id = ? AND i.facility_id = ? AND mr.deleted_at IS NULL";
        
        $record = fetchOne($query, [$recordId, $facilityId], 'ii');
        
        if (!$record) {
            errorResponse('Medical record not found', [], 404);
            return;
        }
        
        // Get medications
        $medications = fetchAll(
            "SELECT * FROM medication_history 
             WHERE medical_record_id = ? AND deleted_at IS NULL
             ORDER BY date_prescribed DESC",
            [$recordId],
            'i'
        );
        
        $record['medications'] = $medications;
        
        successResponse($record, 'Medical record retrieved');
        return;
    }
    
    if ($inmateId) {
        // Get all records for inmate
        $inmate = getInmate($inmateId);
        if (!$inmate || $inmate['facility_id'] != $facilityId) {
            errorResponse('Inmate not found or access denied', [], 404);
            return;
        }
        
        $records = fetchAll(
            "SELECT * FROM medical_records 
             WHERE inmate_id = ? AND deleted_at IS NULL
             ORDER BY record_date DESC",
            [$inmateId],
            'i'
        );
        
        successResponse($records, 'Records retrieved');
        return;
    }
    
    // Get all records in facility
    $offset = ($page - 1) * $perPage;
    
    $query = "SELECT mr.id, mr.inmate_id, i.inmate_id as inmate_number, i.first_name, i.last_name,
                     mr.record_date, mr.diagnosis, mr.treatment_provided, mr.vital_signs,
                     u.first_name as doctor_first, u.last_name as doctor_last
              FROM medical_records mr
              JOIN inmates i ON mr.inmate_id = i.id
              LEFT JOIN users u ON mr.recording_doctor_id = u.id
              WHERE i.facility_id = ? AND mr.deleted_at IS NULL
              ORDER BY mr.record_date DESC
              LIMIT ?, ?";
    
    $records = fetchAll($query, [$facilityId, $offset, $perPage], 'iii');
    
    $countResult = fetchOne(
        "SELECT COUNT(DISTINCT mr.id) as total FROM medical_records mr
         JOIN inmates i ON mr.inmate_id = i.id
         WHERE i.facility_id = ? AND mr.deleted_at IS NULL",
        [$facilityId],
        'i'
    );
    $total = $countResult['total'] ?? 0;
    
    successResponse([
        'data' => $records,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => ceil($total / $perPage)
        ]
    ], 'Medical records retrieved');
}

/**
 * Create medical record
 */
function handleCreateMedicalRecord() {
    verifyPermission('create', 'medical_records');
    
    $data = getJsonInput();
    $facilityId = getCurrentFacility();
    
    validateRequired($data, ['inmate_id', 'diagnosis']);
    
    // Verify inmate exists and belongs to facility
    $inmate = getInmate($data['inmate_id']);
    if (!$inmate || $inmate['facility_id'] != $facilityId) {
        errorResponse('Inmate not found or access denied', [], 404);
        return;
    }
    
    // Prepare record data
    $recordData = [
        'inmate_id' => $data['inmate_id'],
        'record_date' => $data['record_date'] ?? date('Y-m-d'),
        'diagnosis' => $data['diagnosis'],
        'treatment_provided' => $data['treatment_provided'] ?? null,
        'vital_signs' => $data['vital_signs'] ?? null,
        'recording_doctor_id' => getCurrentUserId(),
        'notes' => $data['notes'] ?? null,
        'follow_up_required' => $data['follow_up_required'] ?? false,
        'follow_up_date' => $data['follow_up_date'] ?? null
    ];
    
    // Insert record
    $result = insert('medical_records', $recordData);
    
    if (!$result) {
        throw new Exception('Failed to create medical record');
    }
    
    // Log action
    logAction('CREATE', 'MEDICAL', 'medical_records', $result['id'],
              "Medical record created for inmate: {$inmate['inmate_id']}", 'SUCCESS', $facilityId);
    
    successResponse(['id' => $result['id']], 'Medical record created successfully');
}

/**
 * Update medical record
 */
function handleUpdateMedicalRecord() {
    verifyPermission('edit', 'medical_records');
    
    $data = getJsonInput();
    $facilityId = getCurrentFacility();
    
    if (!isset($data['id'])) {
        errorResponse('Record ID is required', [], 400);
        return;
    }
    
    // Get existing record with facility check
    $record = fetchOne(
        "SELECT mr.* FROM medical_records mr
         JOIN inmates i ON mr.inmate_id = i.id
         WHERE mr.id = ? AND i.facility_id = ? AND mr.deleted_at IS NULL",
        [$data['id'], $facilityId],
        'ii'
    );
    
    if (!$record) {
        errorResponse('Medical record not found', [], 404);
        return;
    }
    
    // Prepare updateable fields
    $updateData = [];
    $allowedFields = ['diagnosis', 'treatment_provided', 'vital_signs', 'notes',
                      'follow_up_required', 'follow_up_date'];
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updateData[$field] = $data[$field];
        }
    }
    
    if (empty($updateData)) {
        errorResponse('No fields to update', [], 400);
        return;
    }
    
    // Update record
    $affected = update('medical_records', $updateData, 'id = ?', [$data['id']]);
    
    if ($affected === false) {
        throw new Exception('Failed to update medical record');
    }
    
    // Log action
    logAction('UPDATE', 'MEDICAL', 'medical_records', $data['id'],
              'Updated medical record', 'SUCCESS', $facilityId);
    
    successResponse(['affected_rows' => $affected], 'Medical record updated successfully');
}

/**
 * Delete medical record
 */
function handleDeleteMedicalRecord() {
    verifyPermission('delete', 'medical_records');
    
    $data = getJsonInput();
    $facilityId = getCurrentFacility();
    
    if (!isset($data['id'])) {
        errorResponse('Record ID is required', [], 400);
        return;
    }
    
    // Verify facility access
    $record = fetchOne(
        "SELECT mr.* FROM medical_records mr
         JOIN inmates i ON mr.inmate_id = i.id
         WHERE mr.id = ? AND i.facility_id = ? AND mr.deleted_at IS NULL",
        [$data['id'], $facilityId],
        'ii'
    );
    
    if (!$record) {
        errorResponse('Medical record not found', [], 404);
        return;
    }
    
    // Soft delete record
    $affected = softDelete('medical_records', 'id = ?', [$data['id']]);
    
    // Also soft delete associated medications
    softDelete('medication_history', 'medical_record_id = ?', [$data['id']]);
    
    if ($affected === false) {
        throw new Exception('Failed to delete medical record');
    }
    
    logAction('DELETE', 'MEDICAL', 'medical_records', $data['id'],
              'Soft-deleted medical record', 'SUCCESS', $facilityId);
    
    successResponse(['affected_rows' => $affected], 'Medical record deleted successfully');
}

/**
 * Get medications for inmate
 */
function handleGetMedications() {
    verifyPermission('view', 'medical_records');
    
    $inmateId = getParam('inmate_id');
    $facilityId = getCurrentFacility();
    $activeOnly = getParam('active_only') === 'true';
    
    if (!$inmateId) {
        errorResponse('Inmate ID is required', [], 400);
        return;
    }
    
    // Verify inmate exists
    $inmate = getInmate($inmateId);
    if (!$inmate || $inmate['facility_id'] != $facilityId) {
        errorResponse('Inmate not found or access denied', [], 404);
        return;
    }
    
    // Get medications
    $query = "SELECT mh.*, mr.record_date, u.first_name, u.last_name
              FROM medication_history mh
              JOIN medical_records mr ON mh.medical_record_id = mr.id
              LEFT JOIN users u ON mh.prescribed_by_user_id = u.id
              WHERE mr.inmate_id = ? AND mh.deleted_at IS NULL";
    
    if ($activeOnly) {
        $query .= " AND (mh.end_date IS NULL OR mh.end_date > NOW())";
    }
    
    $query .= " ORDER BY mh.date_prescribed DESC";
    
    $medications = fetchAll($query, [$inmateId], 'i');
    
    successResponse($medications, 'Medications retrieved');
}

/**
 * Add medication
 */
function handleAddMedication() {
    verifyPermission('create', 'medical_records');
    
    $data = getJsonInput();
    $facilityId = getCurrentFacility();
    
    validateRequired($data, ['medical_record_id', 'medication_name', 'dosage', 'frequency']);
    
    // Verify medical record exists and belongs to facility
    $record = fetchOne(
        "SELECT mr.* FROM medical_records mr
         JOIN inmates i ON mr.inmate_id = i.id
         WHERE mr.id = ? AND i.facility_id = ? AND mr.deleted_at IS NULL",
        [$data['medical_record_id'], $facilityId],
        'ii'
    );
    
    if (!$record) {
        errorResponse('Medical record not found or access denied', [], 404);
        return;
    }
    
    // Prepare medication data
    $medData = [
        'medical_record_id' => $data['medical_record_id'],
        'medication_name' => $data['medication_name'],
        'dosage' => $data['dosage'],
        'frequency' => $data['frequency'],
        'route' => $data['route'] ?? 'ORAL',
        'date_prescribed' => $data['date_prescribed'] ?? date('Y-m-d'),
        'prescribed_by_user_id' => getCurrentUserId(),
        'start_date' => $data['start_date'] ?? date('Y-m-d'),
        'end_date' => $data['end_date'] ?? null,
        'side_effects' => $data['side_effects'] ?? null,
        'notes' => $data['notes'] ?? null
    ];
    
    // Insert medication
    $result = insert('medication_history', $medData);
    
    if (!$result) {
        throw new Exception('Failed to add medication');
    }
    
    logAction('CREATE', 'MEDICAL', 'medication_history', $result['id'],
              "Added medication: {$data['medication_name']}", 'SUCCESS', $facilityId);
    
    successResponse(['id' => $result['id']], 'Medication added successfully');
}

/**
 * Update medication
 */
function handleUpdateMedication() {
    verifyPermission('edit', 'medical_records');
    
    $data = getJsonInput();
    $facilityId = getCurrentFacility();
    
    if (!isset($data['id'])) {
        errorResponse('Medication ID is required', [], 400);
        return;
    }
    
    // Verify medication and facility access
    $med = fetchOne(
        "SELECT mh.* FROM medication_history mh
         JOIN medical_records mr ON mh.medical_record_id = mr.id
         JOIN inmates i ON mr.inmate_id = i.id
         WHERE mh.id = ? AND i.facility_id = ? AND mh.deleted_at IS NULL",
        [$data['id'], $facilityId],
        'ii'
    );
    
    if (!$med) {
        errorResponse('Medication not found', [], 404);
        return;
    }
    
    // Prepare updateable fields
    $updateData = [];
    $allowedFields = ['medication_name', 'dosage', 'frequency', 'route', 'start_date',
                      'end_date', 'side_effects', 'notes'];
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updateData[$field] = $data[$field];
        }
    }
    
    if (empty($updateData)) {
        errorResponse('No fields to update', [], 400);
        return;
    }
    
    // Update medication
    $affected = update('medication_history', $updateData, 'id = ?', [$data['id']]);
    
    if ($affected === false) {
        throw new Exception('Failed to update medication');
    }
    
    logAction('UPDATE', 'MEDICAL', 'medication_history', $data['id'],
              'Updated medication', 'SUCCESS', $facilityId);
    
    successResponse(['affected_rows' => $affected], 'Medication updated successfully');
}

/**
 * Delete medication
 */
function handleDeleteMedication() {
    verifyPermission('delete', 'medical_records');
    
    $data = getJsonInput();
    $facilityId = getCurrentFacility();
    
    if (!isset($data['id'])) {
        errorResponse('Medication ID is required', [], 400);
        return;
    }
    
    // Verify facility access
    $med = fetchOne(
        "SELECT mh.* FROM medication_history mh
         JOIN medical_records mr ON mh.medical_record_id = mr.id
         JOIN inmates i ON mr.inmate_id = i.id
         WHERE mh.id = ? AND i.facility_id = ? AND mh.deleted_at IS NULL",
        [$data['id'], $facilityId],
        'ii'
    );
    
    if (!$med) {
        errorResponse('Medication not found', [], 404);
        return;
    }
    
    // Soft delete medication
    $affected = softDelete('medication_history', 'id = ?', [$data['id']]);
    
    if ($affected === false) {
        throw new Exception('Failed to delete medication');
    }
    
    logAction('DELETE', 'MEDICAL', 'medication_history', $data['id'],
              'Soft-deleted medication', 'SUCCESS', $facilityId);
    
    successResponse(['affected_rows' => $affected], 'Medication deleted successfully');
}
