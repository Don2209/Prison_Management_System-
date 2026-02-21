<?php
/**
 * Finance Management API
 * Handles inmate accounts and transactions (deposits, withdrawals, canteen)
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
            if ($action === 'statement') {
                handleGetStatement();
            } else {
                handleGetAccount();
            }
            break;
        case 'POST':
            if ($action === 'deposit') {
                handleDeposit();
            } elseif ($action === 'withdrawal') {
                handleWithdrawal();
            } elseif ($action === 'transfer') {
                handleTransfer();
            } else {
                handleGetAccount();
            }
            break;
        default:
            jsonResponse(['error' => 'Method not allowed'], 405);
    }
} catch (Exception $e) {
    logError('Finance API Error', $e->getMessage());
    errorResponse($e->getMessage(), [], 500);
}

/**
 * Get inmate account
 */
function handleGetAccount() {
    verifyPermission('view', 'finance');
    
    $accountId = getParam('account_id');
    $inmateId = getParam('inmate_id');
    $facilityId = getCurrentFacility();
    $page = (int) getParam('page') ?: 1;
    $perPage = (int) getParam('per_page') ?: 20;
    
    if ($accountId) {
        // Get single account
        $query = "SELECT ia.*, i.inmate_id, i.first_name, i.last_name
                  FROM inmate_accounts ia
                  JOIN inmates i ON ia.inmate_id = i.id
                  WHERE ia.id = ? AND i.facility_id = ? AND ia.deleted_at IS NULL";
        
        $account = fetchOne($query, [$accountId, $facilityId], 'ii');
        
        if (!$account) {
            errorResponse('Account not found', [], 404);
            return;
        }
        
        // Get recent transactions
        $transactions = fetchAll(
            "SELECT * FROM inmate_transactions 
             WHERE account_id = ? AND deleted_at IS NULL
             ORDER BY created_at DESC LIMIT 50",
            [$accountId],
            'i'
        );
        
        $account['transactions'] = $transactions;
        
        successResponse($account, 'Account retrieved');
        return;
    }
    
    if ($inmateId) {
        // Get account by inmate ID
        $account = getInmateAccount($inmateId);
        
        if (!$account) {
            errorResponse('Inmate not found or account not created', [], 404);
            return;
        }
        
        successResponse($account, 'Account retrieved');
        return;
    }
    
    // Get all accounts in facility
    $offset = ($page - 1) * $perPage;
    
    $query = "SELECT ia.id, ia.inmate_id as inmate_db_id, i.inmate_id, i.first_name, i.last_name, 
                     ia.status, ia.account_balance, ia.created_at, ia.updated_at
              FROM inmate_accounts ia
              JOIN inmates i ON ia.inmate_id = i.id
              WHERE i.facility_id = ? AND ia.deleted_at IS NULL
              ORDER BY i.last_name, i.first_name ASC
              LIMIT ?, ?";
    
    $accounts = fetchAll($query, [$facilityId, $offset, $perPage], 'iii');
    
    $countResult = fetchOne(
        "SELECT COUNT(*) as total FROM inmate_accounts ia
         JOIN inmates i ON ia.inmate_id = i.id
         WHERE i.facility_id = ? AND ia.deleted_at IS NULL",
        [$facilityId],
        'i'
    );
    $total = $countResult['total'] ?? 0;
    
    successResponse([
        'data' => $accounts,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => ceil($total / $perPage)
        ]
    ], 'Accounts retrieved');
}

/**
 * Handle deposit
 */
function handleDeposit() {
    verifyPermission('create', 'finance');
    
    $data = getJsonInput();
    $facilityId = getCurrentFacility();
    
    validateRequired($data, ['account_id', 'amount', 'depositor_name']);
    
    $amount = (float) $data['amount'];
    if ($amount <= 0) {
        errorResponse('Amount must be greater than 0', [], 400);
        return;
    }
    
    // Get account and verify facility access
    $account = fetchOne(
        "SELECT ia.*, i.facility_id FROM inmate_accounts ia
         JOIN inmates i ON ia.inmate_id = i.id
         WHERE ia.id = ? AND ia.deleted_at IS NULL",
        [$data['account_id']],
        'i'
    );
    
    if (!$account) {
        errorResponse('Account not found', [], 404);
        return;
    }
    
    if ($account['facility_id'] != $facilityId) {
        errorResponse('Access denied', [], 403);
        return;
    }
    
    // Update account balance
    $newBalance = $account['account_balance'] + $amount;
    $affected = update('inmate_accounts', 
                      ['account_balance' => $newBalance],
                      'id = ?',
                      [$data['account_id']]);
    
    if (!$affected) {
        throw new Exception('Failed to update account balance');
    }
    
    // Record transaction
    $transactionResult = insert('inmate_transactions', [
        'account_id' => $data['account_id'],
        'transaction_type' => 'DEPOSIT',
        'amount' => $amount,
        'balance_after' => $newBalance,
        'description' => "Deposit by {$data['depositor_name']}",
        'reference_number' => 'DEP-' . time(),
        'created_by_user_id' => getCurrentUserId()
    ]);
    
    // Log action
    logAction('DEPOSIT', 'FINANCE', 'inmate_accounts', $data['account_id'],
              "Deposit: {$amount} by {$data['depositor_name']}", 'SUCCESS', $facilityId);
    
    successResponse([
        'transaction_id' => $transactionResult['id'],
        'new_balance' => $newBalance
    ], 'Deposit processed successfully');
}

/**
 * Handle withdrawal
 */
function handleWithdrawal() {
    verifyPermission('edit', 'finance');
    
    $data = getJsonInput();
    $facilityId = getCurrentFacility();
    
    validateRequired($data, ['account_id', 'amount', 'withdrawal_reason']);
    
    $amount = (float) $data['amount'];
    if ($amount <= 0) {
        errorResponse('Amount must be greater than 0', [], 400);
        return;
    }
    
    // Get account and verify facility access
    $account = fetchOne(
        "SELECT ia.*, i.facility_id FROM inmate_accounts ia
         JOIN inmates i ON ia.inmate_id = i.id
         WHERE ia.id = ? AND ia.deleted_at IS NULL",
        [$data['account_id']],
        'i'
    );
    
    if (!$account) {
        errorResponse('Account not found', [], 404);
        return;
    }
    
    if ($account['facility_id'] != $facilityId) {
        errorResponse('Access denied', [], 403);
        return;
    }
    
    // Check sufficient balance
    if ($account['account_balance'] < $amount) {
        errorResponse('Insufficient balance', [], 400);
        return;
    }
    
    // Update account balance
    $newBalance = $account['account_balance'] - $amount;
    $affected = update('inmate_accounts',
                      ['account_balance' => $newBalance],
                      'id = ?',
                      [$data['account_id']]);
    
    if (!$affected) {
        throw new Exception('Failed to update account balance');
    }
    
    // Record transaction
    $transactionResult = insert('inmate_transactions', [
        'account_id' => $data['account_id'],
        'transaction_type' => 'WITHDRAWAL',
        'amount' => $amount,
        'balance_after' => $newBalance,
        'description' => $data['withdrawal_reason'],
        'reference_number' => 'WTH-' . time(),
        'created_by_user_id' => getCurrentUserId()
    ]);
    
    // Log action
    logAction('WITHDRAWAL', 'FINANCE', 'inmate_accounts', $data['account_id'],
              "Withdrawal: {$amount} - {$data['withdrawal_reason']}", 'SUCCESS', $facilityId);
    
    successResponse([
        'transaction_id' => $transactionResult['id'],
        'new_balance' => $newBalance
    ], 'Withdrawal processed successfully');
}

/**
 * Handle transfer between accounts
 */
function handleTransfer() {
    verifyPermission('edit', 'finance');
    
    $data = getJsonInput();
    $facilityId = getCurrentFacility();
    
    validateRequired($data, ['from_account_id', 'to_account_id', 'amount']);
    
    $amount = (float) $data['amount'];
    if ($amount <= 0) {
        errorResponse('Amount must be greater than 0', [], 400);
        return;
    }
    
    if ($data['from_account_id'] == $data['to_account_id']) {
        errorResponse('Cannot transfer to the same account', [], 400);
        return;
    }
    
    // Get both accounts
    $fromAccount = fetchOne(
        "SELECT ia.*, i.facility_id FROM inmate_accounts ia
         JOIN inmates i ON ia.inmate_id = i.id
         WHERE ia.id = ? AND ia.deleted_at IS NULL",
        [$data['from_account_id']],
        'i'
    );
    
    $toAccount = fetchOne(
        "SELECT ia.*, i.facility_id FROM inmate_accounts ia
         JOIN inmates i ON ia.inmate_id = i.id
         WHERE ia.id = ? AND ia.deleted_at IS NULL",
        [$data['to_account_id']],
        'i'
    );
    
    if (!$fromAccount || !$toAccount) {
        errorResponse('One or both accounts not found', [], 404);
        return;
    }
    
    // Verify facility access
    if ($fromAccount['facility_id'] != $facilityId || $toAccount['facility_id'] != $facilityId) {
        errorResponse('Access denied', [], 403);
        return;
    }
    
    // Check balance
    if ($fromAccount['account_balance'] < $amount) {
        errorResponse('Insufficient balance in source account', [], 400);
        return;
    }
    
    // Update balances
    $fromNewBalance = $fromAccount['account_balance'] - $amount;
    $toNewBalance = $toAccount['account_balance'] + $amount;
    
    update('inmate_accounts', ['account_balance' => $fromNewBalance], 'id = ?', [$data['from_account_id']]);
    update('inmate_accounts', ['account_balance' => $toNewBalance], 'id = ?', [$data['to_account_id']]);
    
    // Record transactions
    $referenceId = 'TRF-' . time();
    
    insert('inmate_transactions', [
        'account_id' => $data['from_account_id'],
        'transaction_type' => 'TRANSFER_OUT',
        'amount' => $amount,
        'balance_after' => $fromNewBalance,
        'description' => 'Transfer out to another inmate',
        'reference_number' => $referenceId,
        'created_by_user_id' => getCurrentUserId()
    ]);
    
    insert('inmate_transactions', [
        'account_id' => $data['to_account_id'],
        'transaction_type' => 'TRANSFER_IN',
        'amount' => $amount,
        'balance_after' => $toNewBalance,
        'description' => 'Transfer in from another inmate',
        'reference_number' => $referenceId,
        'created_by_user_id' => getCurrentUserId()
    ]);
    
    // Log action
    logAction('TRANSFER', 'FINANCE', 'inmate_accounts', $data['from_account_id'],
              "Transfer: {$amount} to another inmate", 'SUCCESS', $facilityId);
    
    successResponse([
        'reference_id' => $referenceId,
        'from_new_balance' => $fromNewBalance,
        'to_new_balance' => $toNewBalance
    ], 'Transfer completed successfully');
}

/**
 * Get account statement
 */
function handleGetStatement() {
    verifyPermission('view', 'finance');
    
    $accountId = getParam('account_id');
    $facilityId = getCurrentFacility();
    $startDate = getParam('start_date');
    $endDate = getParam('end_date');
    
    if (!$accountId) {
        errorResponse('Account ID is required', [], 400);
        return;
    }
    
    // Verify account access
    $account = fetchOne(
        "SELECT ia.*, i.facility_id FROM inmate_accounts ia
         JOIN inmates i ON ia.inmate_id = i.id
         WHERE ia.id = ? AND ia.deleted_at IS NULL",
        [$accountId],
        'i'
    );
    
    if (!$account || $account['facility_id'] != $facilityId) {
        errorResponse('Account not found or access denied', [], 404);
        return;
    }
    
    // Build query
    $query = "SELECT * FROM inmate_transactions 
              WHERE account_id = ? AND deleted_at IS NULL";
    
    $params = [$accountId];
    $types = 'i';
    
    if ($startDate) {
        $query .= " AND DATE(created_at) >= ?";
        $params[] = $startDate;
        $types .= 's';
    }
    
    if ($endDate) {
        $query .= " AND DATE(created_at) <= ?";
        $params[] = $endDate;
        $types .= 's';
    }
    
    $query .= " ORDER BY created_at DESC";
    
    $transactions = fetchAll($query, $params, $types);
    
    // Calculate statistics
    $deposits = array_sum(array_map(function($t) { 
        return $t['transaction_type'] === 'DEPOSIT' ? $t['amount'] : 0; 
    }, $transactions));
    
    $withdrawals = array_sum(array_map(function($t) { 
        return in_array($t['transaction_type'], ['WITHDRAWAL', 'TRANSFER_OUT']) ? $t['amount'] : 0; 
    }, $transactions));
    
    successResponse([
        'account' => $account,
        'transactions' => $transactions,
        'summary' => [
            'current_balance' => $account['account_balance'],
            'total_deposits' => $deposits,
            'total_withdrawals' => $withdrawals,
            'transaction_count' => count($transactions)
        ]
    ], 'Statement retrieved');
}
