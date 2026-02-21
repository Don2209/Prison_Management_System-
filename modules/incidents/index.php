<?php
$pageTitle = 'Incidents Management';
require_once '../../includes/header.php';
requireAuth();
verifyPermission('view', 'incidents');
$facility = getCurrentFacility();
?>

<div class="glass-card mt-4" style="animation: slideIn 0.5s ease-out;">
    <div class="flex-between mb-4">
        <h1 class="data-table-title">Incidents</h1>
        <?php if (hasPermission('create', 'incidents')): ?>
        <button class="btn-primary" onclick="window.location.href='<?php echo APP_URL; ?>/modules/incidents/report.php'">
            <i class="bi bi-plus-circle"></i> Report Incident
        </button>
        <?php endif; ?>
    </div>

    <div id="incidents-table"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const incidentsTable = initDataTable('incidents-table', '/api/incidents?facility_id=<?php echo $facility; ?>', [
        { field: 'incident_type', label: 'Type', type: 'string' },
        { field: 'category_name', label: 'Category', type: 'string' },
        { 
            field: 'severity', 
            label: 'Severity',
            render: (value) => {
                const colors = {
                    'LOW': '#10b981',
                    'MEDIUM': '#f59e0b',
                    'HIGH': '#ef4444',
                    'CRITICAL': '#7c2d12'
                };
                return `<span style="color: ${colors[value] || '#fff'}; font-weight: 600;">● ${value}</span>`;
            }
        },
        { field: 'location', label: 'Location', type: 'string' },
        { field: 'reported_date', label: 'Date', type: 'date' },
        { 
            field: 'status', 
            label: 'Status',
            render: (value) => `<span class="status-badge status-${value?.toLowerCase() || 'open'}">${value}</span>`
        }
    ], {
        title: 'Incident Reports',
        searchFields: ['incident_type', 'category_name', 'location'],
        perPage: 20,
        actions: buildActions([
            { view: {
                icon: 'bi-eye',
                label: 'View',
                callback: (row) => window.location.href = `<?php echo APP_URL; ?>/modules/incidents/view.php?id=${row.id}`
            }},
            <?php if (hasPermission('edit', 'incidents')): ?> { investigate: {
                icon: 'bi-search',
                label: 'Investigate',
                callback: (row) => window.location.href = `<?php echo APP_URL; ?>/modules/incidents/investigate.php?id=${row.id}`
            }} <?php endif; ?>
        ])
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>
