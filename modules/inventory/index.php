<?php
$pageTitle = 'Inventory Management';
require_once '../../includes/header.php';
requireAuth();
verifyPermission('view', 'inventory');
$facility = getCurrentFacility();
?>

<div class="glass-card mt-4" style="animation: slideIn 0.5s ease-out;">
    <div class="flex-between mb-4">
        <h1 class="data-table-title">Inventory Management</h1>
        <?php if (hasPermission('create', 'inventory')): ?>
        <button class="btn-primary" onclick="window.location.href='<?php echo APP_URL; ?>/modules/inventory/add-item.php'">
            <i class="bi bi-plus-circle"></i> Add Item
        </button>
        <?php endif; ?>
    </div>

    <div id="inventory-table"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const inventoryTable = initDataTable('inventory-table', '/api/inventory?facility_id=<?php echo $facility; ?>', [
        { field: 'sku', label: 'SKU', type: 'string' },
        { field: 'name', label: 'Item Name', type: 'string' },
        { field: 'category_name', label: 'Category', type: 'string' },
        { field: 'warehouse_name', label: 'Warehouse', type: 'string' },
        { field: 'current_quantity', label: 'Quantity', type: 'number' },
        { field: 'unit_cost', label: 'Unit Cost', type: 'currency' },
        { 
            field: 'needs_reorder', 
            label: 'Status',
            render: (value, row) => {
                const quantity = row.current_quantity;
                const reorder = row.reorder_level;
                if (quantity <= reorder) {
                    return '<span class="status-badge status-critical">⚠️ Low Stock</span>';
                }
                return '<span class="status-badge status-active">✓ In Stock</span>';
            }
        }
    ], {
        title: 'Inventory Items',
        searchFields: ['sku', 'name', 'category_name'],
        perPage: 20,
        actions: buildActions([
            { view: {
                icon: 'bi-eye',
                label: 'View',
                callback: (row) => window.location.href = `<?php echo APP_URL; ?>/modules/inventory/view-item.php?id=${row.id}`
            }},
            <?php if (hasPermission('edit', 'inventory')): ?> { edit: {
                icon: 'bi-pencil',
                label: 'Edit',
                callback: (row) => window.location.href = `<?php echo APP_URL; ?>/modules/inventory/edit-item.php?id=${row.id}`
            }} <?php endif; ?>
        ])
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>
