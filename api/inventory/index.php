<?php
/**
 * Inventory Management API
 * Handles items, warehouses, suppliers, and inventory transactions
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
            if ($action === 'low-stock') {
                handleGetLowStockItems();
            } elseif ($action === 'warehouses') {
                handleGetWarehouses();
            } elseif ($action === 'suppliers') {
                handleGetSuppliers();
            } else {
                handleGetInventory();
            }
            break;
        case 'POST':
            if ($action === 'warehouse') {
                handleCreateWarehouse();
            } elseif ($action === 'supplier') {
                handleCreateSupplier();
            } elseif ($action === 'transaction') {
                handleCreateTransaction();
            } else {
                handleCreateInventoryItem();
            }
            break;
        case 'PUT':
            if ($action === 'warehouse') {
                handleUpdateWarehouse();
            } else {
                handleUpdateInventoryItem();
            }
            break;
        case 'DELETE':
            handleDeleteInventoryItem();
            break;
        default:
            jsonResponse(['error' => 'Method not allowed'], 405);
    }
} catch (Exception $e) {
    logError('Inventory API Error', $e->getMessage());
    errorResponse($e->getMessage(), [], 500);
}

/**
 * Get inventory items
 */
function handleGetInventory() {
    verifyPermission('view', 'inventory');
    
    $itemId = getParam('id');
    $facilityId = getCurrentFacility();
    $warehouseId = getParam('warehouse_id');
    $categoryId = getParam('category_id');
    $status = getParam('status');
    $page = (int) getParam('page') ?: 1;
    $perPage = (int) getParam('per_page') ?: 20;
    
    if ($itemId) {
        // Get single item with full details
        $query = "SELECT i.*, c.name as category_name, w.name as warehouse_name, s.name as supplier_name
                  FROM inventory_items i
                  LEFT JOIN inventory_categories c ON i.category_id = c.id
                  LEFT JOIN warehouses w ON i.warehouse_id = w.id
                  LEFT JOIN suppliers s ON i.supplier_id = s.id
                  WHERE i.id = ? AND i.facility_id = ? AND i.deleted_at IS NULL";
        
        $item = fetchOne($query, [$itemId, $facilityId], 'ii');
        
        if (!$item) {
            errorResponse('Item not found', [], 404);
            return;
        }
        
        successResponse($item, 'Item retrieved');
        return;
    }
    
    // Build query
    $offset = ($page - 1) * $perPage;
    
    $query = "SELECT i.id, i.sku, i.name, i.description, i.category_id, i.warehouse_id,
                     c.name as category_name, w.name as warehouse_name, s.name as supplier_name,
                     i.unit_cost, i.current_quantity, i.reorder_level, i.reorder_quantity, i.status,
                     CASE WHEN i.current_quantity <= i.reorder_level THEN true ELSE false END as needs_reorder
              FROM inventory_items i
              LEFT JOIN inventory_categories c ON i.category_id = c.id
              LEFT JOIN warehouses w ON i.warehouse_id = w.id
              LEFT JOIN suppliers s ON i.supplier_id = s.id
              WHERE i.facility_id = ? AND i.deleted_at IS NULL";
    
    $params = [$facilityId];
    $types = 'i';
    
    if ($warehouseId) {
        $query .= " AND i.warehouse_id = ?";
        $params[] = $warehouseId;
        $types .= 'i';
    }
    
    if ($categoryId) {
        $query .= " AND i.category_id = ?";
        $params[] = $categoryId;
        $types .= 'i';
    }
    
    if ($status) {
        $query .= " AND i.status = ?";
        $params[] = $status;
        $types .= 's';
    }
    
    // Count total
    $countQuery = "SELECT COUNT(*) as total FROM inventory_items 
                   WHERE facility_id = ? AND deleted_at IS NULL";
    $countParams = [$facilityId];
    $countTypes = 'i';
    
    if ($warehouseId) {
        $countQuery .= " AND warehouse_id = ?";
        $countParams[] = $warehouseId;
        $countTypes .= 'i';
    }
    if ($categoryId) {
        $countQuery .= " AND category_id = ?";
        $countParams[] = $categoryId;
        $countTypes .= 'i';
    }
    if ($status) {
        $countQuery .= " AND status = ?";
        $countParams[] = $status;
        $countTypes .= 's';
    }
    
    $countResult = fetchOne($countQuery, $countParams, $countTypes);
    $total = $countResult['total'] ?? 0;
    
    // Paginate
    $query .= " ORDER BY i.name ASC LIMIT ?, ?";
    $params[] = $offset;
    $params[] = $perPage;
    $types .= 'ii';
    
    $items = fetchAll($query, $params, $types);
    
    successResponse([
        'data' => $items,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => ceil($total / $perPage)
        ]
    ], 'Inventory items retrieved');
}

/**
 * Get low stock items
 */
function handleGetLowStockItems() {
    verifyPermission('view', 'inventory');
    
    $facilityId = getCurrentFacility();
    
    $items = getItemsNeedingReorder($facilityId);
    
    successResponse($items, 'Low stock items retrieved');
}

/**
 * Create inventory item
 */
function handleCreateInventoryItem() {
    verifyPermission('create', 'inventory');
    
    $data = getJsonInput();
    $facilityId = getCurrentFacility();
    
    // Validate required fields
    $required = ['sku', 'name', 'category_id', 'warehouse_id', 'unit_cost', 'current_quantity', 'reorder_level'];
    validateRequired($data, $required);
    
    // Check SKU uniqueness
    $existing = fetchOne(
        "SELECT id FROM inventory_items WHERE sku = ? AND facility_id = ? AND deleted_at IS NULL",
        [$data['sku'], $facilityId],
        'si'
    );
    
    if ($existing) {
        errorResponse('SKU already exists in this facility', [], 400);
        return;
    }
    
    // Verify warehouse exists
    $warehouse = fetchOne(
        "SELECT id FROM warehouses WHERE id = ? AND facility_id = ? AND deleted_at IS NULL",
        [$data['warehouse_id'], $facilityId],
        'ii'
    );
    
    if (!$warehouse) {
        errorResponse('Warehouse not found', [], 404);
        return;
    }
    
    // Prepare item data
    $itemData = [
        'facility_id' => $facilityId,
        'sku' => strtoupper($data['sku']),
        'name' => $data['name'],
        'description' => $data['description'] ?? null,
        'category_id' => (int) $data['category_id'],
        'warehouse_id' => (int) $data['warehouse_id'],
        'supplier_id' => isset($data['supplier_id']) ? (int) $data['supplier_id'] : null,
        'unit_cost' => (float) $data['unit_cost'],
        'current_quantity' => (int) $data['current_quantity'],
        'reorder_level' => (int) $data['reorder_level'],
        'reorder_quantity' => (int) ($data['reorder_quantity'] ?? $data['reorder_level']),
        'status' => $data['status'] ?? 'ACTIVE'
    ];
    
    // Insert item
    $result = insert('inventory_items', $itemData);
    
    if (!$result) {
        throw new Exception('Failed to create inventory item');
    }
    
    // Log action
    logAction('CREATE', 'INVENTORY', 'inventory_items', $result['id'], 
              "Added inventory item: {$data['name']}", 'SUCCESS', $facilityId);
    
    // Log initial transaction
    insert('inventory_transactions', [
        'facility_id' => $facilityId,
        'item_id' => $result['id'],
        'transaction_type' => 'INITIAL',
        'quantity_change' => (int) $itemData['current_quantity'],
        'reference_number' => 'INIT-' . $result['id'],
        'user_id' => getCurrentUserId(),
        'notes' => 'Initial inventory entry'
    ]);
    
    successResponse(['id' => $result['id']], 'Inventory item created successfully');
}

/**
 * Update inventory item
 */
function handleUpdateInventoryItem() {
    verifyPermission('edit', 'inventory');
    
    $data = getJsonInput();
    $facilityId = getCurrentFacility();
    
    if (!isset($data['id'])) {
        errorResponse('Item ID is required', [], 400);
        return;
    }
    
    // Get existing item
    $item = fetchOne(
        "SELECT * FROM inventory_items WHERE id = ? AND facility_id = ? AND deleted_at IS NULL",
        [$data['id'], $facilityId],
        'ii'
    );
    
    if (!$item) {
        errorResponse('Item not found', [], 404);
        return;
    }
    
    // Prepare updateable fields
    $updateData = [];
    $allowedFields = ['name', 'description', 'category_id', 'warehouse_id', 'supplier_id', 
                      'unit_cost', 'reorder_level', 'reorder_quantity', 'status'];
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updateData[$field] = $data[$field];
        }
    }
    
    // Handle quantity change
    if (isset($data['quantity_adjustment'])) {
        $adjustment = (int) $data['quantity_adjustment'];
        $newQuantity = max(0, $item['current_quantity'] + $adjustment);
        $updateData['current_quantity'] = $newQuantity;
        
        // Log transaction
        insert('inventory_transactions', [
            'facility_id' => $facilityId,
            'item_id' => $data['id'],
            'transaction_type' => $adjustment > 0 ? 'IN' : 'OUT',
            'quantity_change' => abs($adjustment),
            'reference_number' => isset($data['ref_number']) ? $data['ref_number'] : 'ADJ-' . time(),
            'user_id' => getCurrentUserId(),
            'notes' => $data['notes'] ?? 'Quantity adjustment'
        ]);
    }
    
    if (empty($updateData)) {
        errorResponse('No fields to update', [], 400);
        return;
    }
    
    // Update item
    $affected = update('inventory_items', $updateData, 'id = ?', [$data['id']]);
    
    if ($affected === false) {
        throw new Exception('Failed to update inventory item');
    }
    
    logAction('UPDATE', 'INVENTORY', 'inventory_items', $data['id'], 
              'Updated inventory item', 'SUCCESS', $facilityId);
    
    successResponse(['affected_rows' => $affected], 'Inventory item updated successfully');
}

/**
 * Delete inventory item
 */
function handleDeleteInventoryItem() {
    verifyPermission('delete', 'inventory');
    
    $data = getJsonInput();
    $facilityId = getCurrentFacility();
    
    if (!isset($data['id'])) {
        errorResponse('Item ID is required', [], 400);
        return;
    }
    
    // Soft delete item
    $affected = softDelete('inventory_items', 'id = ? AND facility_id = ?', 
                          [$data['id'], $facilityId]);
    
    if ($affected === false) {
        throw new Exception('Failed to delete inventory item');
    }
    
    logAction('DELETE', 'INVENTORY', 'inventory_items', $data['id'], 
              'Soft-deleted inventory item', 'SUCCESS', $facilityId);
    
    successResponse(['affected_rows' => $affected], 'Inventory item deleted successfully');
}

/**
 * Get warehouses
 */
function handleGetWarehouses() {
    verifyPermission('view', 'inventory');
    
    $facilityId = getCurrentFacility();
    
    $warehouses = fetchAll(
        "SELECT * FROM warehouses WHERE facility_id = ? AND deleted_at IS NULL ORDER BY name ASC",
        [$facilityId],
        'i'
    );
    
    successResponse($warehouses, 'Warehouses retrieved');
}

/**
 * Create warehouse
 */
function handleCreateWarehouse() {
    verifyPermission('create', 'inventory');
    
    $data = getJsonInput();
    $facilityId = getCurrentFacility();
    
    validateRequired($data, ['name', 'location']);
    
    $result = insert('warehouses', [
        'facility_id' => $facilityId,
        'name' => $data['name'],
        'location' => $data['location'],
        'capacity' => $data['capacity'] ?? null,
        'manager_name' => $data['manager_name'] ?? null,
        'contact_number' => $data['contact_number'] ?? null,
        'status' => 'ACTIVE'
    ]);
    
    logAction('CREATE', 'INVENTORY', 'warehouses', $result['id'], 
              "Added warehouse: {$data['name']}", 'SUCCESS', $facilityId);
    
    successResponse(['id' => $result['id']], 'Warehouse created successfully');
}

/**
 * Update warehouse
 */
function handleUpdateWarehouse() {
    verifyPermission('edit', 'inventory');
    
    $data = getJsonInput();
    $facilityId = getCurrentFacility();
    
    if (!isset($data['id'])) {
        errorResponse('Warehouse ID is required', [], 400);
        return;
    }
    
    $updateData = [];
    $allowedFields = ['name', 'location', 'capacity', 'manager_name', 'contact_number', 'status'];
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updateData[$field] = $data[$field];
        }
    }
    
    $affected = update('warehouses', $updateData, 'id = ? AND facility_id = ?', 
                      [$data['id'], $facilityId]);
    
    successResponse(['affected_rows' => $affected], 'Warehouse updated successfully');
}

/**
 * Get suppliers
 */
function handleGetSuppliers() {
    verifyPermission('view', 'inventory');
    
    $facilityId = getCurrentFacility();
    
    $suppliers = fetchAll(
        "SELECT * FROM suppliers WHERE facility_id = ? AND deleted_at IS NULL ORDER BY name ASC",
        [$facilityId],
        'i'
    );
    
    successResponse($suppliers, 'Suppliers retrieved');
}

/**
 * Create supplier
 */
function handleCreateSupplier() {
    verifyPermission('create', 'inventory');
    
    $data = getJsonInput();
    $facilityId = getCurrentFacility();
    
    validateRequired($data, ['name', 'contact_person']);
    
    $result = insert('suppliers', [
        'facility_id' => $facilityId,
        'name' => $data['name'],
        'contact_person' => $data['contact_person'],
        'email' => $data['email'] ?? null,
        'phone' => $data['phone'] ?? null,
        'address' => $data['address'] ?? null,
        'payment_terms' => $data['payment_terms'] ?? null,
        'status' => 'ACTIVE'
    ]);
    
    logAction('CREATE', 'INVENTORY', 'suppliers', $result['id'], 
              "Added supplier: {$data['name']}", 'SUCCESS', $facilityId);
    
    successResponse(['id' => $result['id']], 'Supplier created successfully');
}

/**
 * Create inventory transaction
 */
function handleCreateTransaction() {
    verifyPermission('edit', 'inventory');
    
    $data = getJsonInput();
    $facilityId = getCurrentFacility();
    
    validateRequired($data, ['item_id', 'transaction_type', 'quantity_change']);
    
    $result = insert('inventory_transactions', [
        'facility_id' => $facilityId,
        'item_id' => (int) $data['item_id'],
        'transaction_type' => $data['transaction_type'],
        'quantity_change' => (int) $data['quantity_change'],
        'reference_number' => $data['reference_number'] ?? 'TXN-' . time(),
        'user_id' => getCurrentUserId(),
        'notes' => $data['notes'] ?? null
    ]);
    
    // Update item quantity
    $item = getInventoryItem($data['item_id']);
    if ($item) {
        $adjustment = $data['transaction_type'] === 'OUT' ? 
                     -abs($data['quantity_change']) : abs($data['quantity_change']);
        
        update('inventory_items', 
               ['current_quantity' => max(0, $item['current_quantity'] + $adjustment)],
               'id = ?',
               [$data['item_id']]);
    }
    
    successResponse(['id' => $result['id']], 'Transaction recorded successfully');
}
