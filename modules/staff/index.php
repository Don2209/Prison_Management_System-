<?php
$pageTitle = 'Staff Management';
require_once '../../includes/header.php';
requireAuth();
verifyPermission('view', 'staff');
$facility = getCurrentFacility();
?>

<div class="glass-card mt-4" style="animation: slideIn 0.5s ease-out;">
    <div class="flex-between mb-4">
        <h1 class="data-table-title">Staff Management</h1>
        <?php if (hasPermission('create', 'staff')): ?>
        <button class="btn-primary" onclick="window.location.href='<?php echo APP_URL; ?>/modules/staff/add.php'">
            <i class="bi bi-plus-circle"></i> Add Staff Member
        </button>
        <?php endif; ?>
    </div>

    <div id="staff-table"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const staffTable = initDataTable('staff-table', '/api/staff?facility_id=<?php echo $facility; ?>', [
        { field: 'staff_id', label: 'Staff ID', type: 'string' },
        { field: 'first_name', label: 'First Name', type: 'string' },
        { field: 'last_name', label: 'Last Name', type: 'string' },
        { field: 'staff_type', label: 'Type', type: 'string' },
        { field: 'department', label: 'Department', type: 'string' },
        { field: 'rank', label: 'Rank', type: 'string' },
        { 
            field: 'is_active', 
            label: 'Status', 
            render: (value) => `<span class="status-badge ${value ? 'status-active' : 'status-inactive'}">${value ? 'Active' : 'Inactive'}</span>`
        }
    ], {
        title: 'All Staff Members',
        searchFields: ['staff_id', 'first_name', 'last_name', 'department'],
        perPage: 20,
        actions: buildActions([
            { view: {
                icon: 'bi-eye',
                label: 'View',
                callback: (row) => window.location.href = `<?php echo APP_URL; ?>/modules/staff/view.php?id=${row.id}`
            }},
            <?php if (hasPermission('edit', 'staff')): ?> { edit: {
                icon: 'bi-pencil',
                label: 'Edit',
                callback: (row) => window.location.href = `<?php echo APP_URL; ?>/modules/staff/edit.php?id=${row.id}`
            }} <?php endif; ?>
        ])
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>
