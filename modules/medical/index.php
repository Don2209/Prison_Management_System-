<?php
$pageTitle = 'Medical Records';
require_once '../../includes/header.php';
requireAuth();
verifyPermission('view', 'medical_records');
$facility = getCurrentFacility();
?>

<div class="glass-card mt-4" style="animation: slideIn 0.5s ease-out;">
    <div class="flex-between mb-4">
        <h1 class="data-table-title">Medical Records</h1>
        <?php if (hasPermission('create', 'medical_records')): ?>
        <button class="btn-primary" onclick="window.location.href='<?php echo APP_URL; ?>/modules/medical/add-record.php'">
            <i class="bi bi-plus-circle"></i> Add Record
        </button>
        <?php endif; ?>
    </div>

    <div id="medical-table"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const medicalTable = initDataTable('medical-table', '/api/medical?facility_id=<?php echo $facility; ?>', [
        { field: 'inmate_number', label: 'Inmate #', type: 'string' },
        { field: 'first_name', label: 'First Name', type: 'string' },
        { field: 'last_name', label: 'Last Name', type: 'string' },
        { field: 'diagnosis', label: 'Diagnosis', type: 'string' },
        { field: 'record_date', label: 'Date', type: 'date' },
        { field: 'doctor_first', label: 'Doctor', type: 'string' }
    ], {
        title: 'Medical Records',
        searchFields: ['inmate_number', 'first_name', 'last_name', 'diagnosis'],
        perPage: 20,
        actions: buildActions([
            { view: {
                icon: 'bi-eye',
                label: 'View',
                callback: (row) => window.location.href = `<?php echo APP_URL; ?>/modules/medical/view-record.php?id=${row.id}`
            }},
            <?php if (hasPermission('edit', 'medical_records')): ?> { edit: {
                icon: 'bi-pencil',
                label: 'Edit',
                callback: (row) => window.location.href = `<?php echo APP_URL; ?>/modules/medical/edit-record.php?id=${row.id}`
            }}, { medications: {
                icon: 'bi-capsule',
                label: 'Meds',
                callback: (row) => window.location.href = `<?php echo APP_URL; ?>/modules/medical/medications.php?record_id=${row.id}`
            }} <?php endif; ?>
        ])
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>
