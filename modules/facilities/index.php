<?php
$pageTitle = 'Facilities Management';
require_once '../../includes/header.php';
requireAuth();

// Only super admin can access
if (!isSuperAdmin()) {
    errorResponse('Access denied', [], 403);
    exit;
}
?>

<div class="glass-card mt-4" style="animation: slideIn 0.5s ease-out;">
    <div class="flex-between mb-4">
        <h1 class="data-table-title">Facilities Management</h1>
        <button class="btn-primary" onclick="window.location.href='<?php echo APP_URL; ?>/modules/facilities/add.php'">
            <i class="bi bi-plus-circle"></i> Add Facility
        </button>
    </div>

    <div id="facilities-table"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const facilitiesTable = initDataTable('facilities-table', '/api/facilities/', [
        { field: 'name', label: 'Facility Name', type: 'string' },
        { field: 'type', label: 'Type', type: 'string' },
        { field: 'location', label: 'Location', type: 'string' },
        { field: 'capacity', label: 'Capacity', type: 'number' },
        { field: 'current_population', label: 'Population', type: 'number' },
        { field: 'staff_count', label: 'Staff', type: 'number' },
        { field: 'incidents_month', label: 'Incidents', type: 'number' },
        { 
            field: 'status', 
            label: 'Status',
            render: (value) => `<span class="status-badge status-${value?.toLowerCase() || 'active'}">${value}</span>`
        }
    ], {
        title: 'All Facilities',
        searchFields: ['name', 'type', 'location'],
        perPage: 20,
        actions: buildActions([
            { view: {
                icon: 'bi-eye',
                label: 'View',
                callback: (row) => window.location.href = `<?php echo APP_URL; ?>/modules/facilities/view.php?id=${row.id}`
            }},
            { edit: {
                icon: 'bi-pencil',
                label: 'Edit',
                callback: (row) => window.location.href = `<?php echo APP_URL; ?>/modules/facilities/edit.php?id=${row.id}`
            }}
        ])
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>
