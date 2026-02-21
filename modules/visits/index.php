<?php
$pageTitle = 'Visitor Management';
require_once '../../includes/header.php';
requireAuth();
verifyPermission('view', 'visits');
$facility = getCurrentFacility();
?>

<div class="glass-card mt-4" style="animation: slideIn 0.5s ease-out;">
    <div class="flex-between mb-4">
        <h1 class="data-table-title">Visitor Management</h1>
    </div>

    <!-- Tabs for different views -->
    <div class="glass-card-sm mb-4" style="display: flex; gap: 1rem; flex-wrap: wrap;">
        <button class="btn-secondary" onclick="switchTab('pending')">📋 Pending Approvals</button>
        <button class="btn-secondary" onclick="switchTab('approved')">✓ Approved Visits</button>
        <button class="btn-secondary" onclick="switchTab('completed')">✓ Completed Visits</button>
    </div>

    <div id="visits-table"></div>
</div>

<script>
let currentTab = 'pending';

function switchTab(tab) {
    currentTab = tab;
    location.reload(); // In production, update data dynamically
}

document.addEventListener('DOMContentLoaded', function() {
    const statusFilter = currentTab === 'pending' ? 'PENDING' : (currentTab === 'approved' ? 'APPROVED' : 'COMPLETED');
    
    const visitsTable = initDataTable('visits-table', `/api/visits?facility_id=<?php echo $facility; ?>&status=${statusFilter}`, [
        { field: 'visitor_name', label: 'Visitor Name', type: 'string' },
        { field: 'inmate_id', label: 'Inmate #', type: 'string' },
        { field: 'inmate_first', label: 'Inmate First', type: 'string' },
        { field: 'inmate_last', label: 'Inmate Last', type: 'string' },
        { field: 'relationship', label: 'Relationship', type: 'string' },
        { field: 'visit_date', label: 'Date', type: 'date' },
        { 
            field: 'status', 
            label: 'Status',
            render: (value) => `<span class="status-badge status-${value?.toLowerCase() || 'pending'}">${value}</span>`
        }
    ], {
        title: `Visits - ${currentTab.charAt(0).toUpperCase() + currentTab.slice(1)}`,
        searchFields: ['visitor_name', 'inmate_id', 'inmate_first', 'inmate_last'],
        perPage: 20,
        actions: buildActions([
            { view: {
                icon: 'bi-eye',
                label: 'View',
                callback: (row) => window.location.href = `<?php echo APP_URL; ?>/modules/visits/view.php?id=${row.id}`
            }},
            <?php if (hasPermission('edit', 'visits')): ?> { approve: {
                icon: 'bi-check-circle',
                label: 'Approve',
                callback: (row) => {
                    if (currentTab === 'pending') {
                        approveVisit(row.id);
                    }
                }
            }} <?php endif; ?>
        ])
    });
});

async function approveVisit(visitId) {
    if (confirm('Approve this visit request?')) {
        try {
            const result = await apiCall(`/visits/`, {
                method: 'PUT',
                data: { id: visitId, status: 'APPROVED' }
            });
            if (result.success) {
                showAlert('Visit approved', 'success');
                location.reload();
            }
        } catch (error) {
            showAlert(error.message, 'error');
        }
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>
