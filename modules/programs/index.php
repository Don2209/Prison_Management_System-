<?php
$pageTitle = 'Rehabilitation Programs';
require_once '../../includes/header.php';
requireAuth();
verifyPermission('view', 'programs');
$facility = getCurrentFacility();
?>

<div class="glass-card mt-4" style="animation: slideIn 0.5s ease-out;">
    <div class="flex-between mb-4">
        <h1 class="data-table-title">Rehabilitation Programs</h1>
        <?php if (hasPermission('create', 'programs')): ?>
        <button class="btn-primary" onclick="window.location.href='<?php echo APP_URL; ?>/modules/programs/create.php'">
            <i class="bi bi-plus-circle"></i> Create Program
        </button>
        <?php endif; ?>
    </div>

    <div id="programs-table"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const programsTable = initDataTable('programs-table', `/api/programs?facility_id=<?php echo $facility; ?>`, [
        { field: 'program_name', label: 'Program', type: 'string' },
        { field: 'description', label: 'Description', type: 'string' },
        { field: 'program_type', label: 'Type', type: 'string' },
        { field: 'enrollment_count', label: 'Enrolled', type: 'number' },
        { field: 'duration_weeks', label: 'Duration', type: 'string' },
        { 
            field: 'status', 
            label: 'Status',
            render: (value) => `<span class="status-badge status-${value?.toLowerCase() || 'active'}">${value}</span>`
        }
    ], {
        title: 'Rehabilitation Programs',
        searchFields: ['program_name', 'description', 'program_type'],
        perPage: 20,
        actions: buildActions([
            { view: {
                icon: 'bi-eye',
                label: 'View',
                callback: (row) => window.location.href = `<?php echo APP_URL; ?>/modules/programs/view.php?id=${row.id}`
            }},
            <?php if (hasPermission('edit', 'programs')): ?> { enroll: {
                icon: 'bi-plus-circle',
                label: 'Enroll',
                callback: (row) => window.location.href = `<?php echo APP_URL; ?>/modules/programs/enroll.php?id=${row.id}`
            }} <?php endif; ?>
        ])
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>
