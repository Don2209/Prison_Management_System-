<?php
$pageTitle = 'Finance Management';
require_once '../../includes/header.php';
requireAuth();
verifyPermission('view', 'finance');
$facility = getCurrentFacility();
?>

<div class="glass-card mt-4" style="animation: slideIn 0.5s ease-out;">
    <div class="flex-between mb-4">
        <h1 class="data-table-title">Inmate Accounts</h1>
    </div>

    <div id="accounts-table"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const accountsTable = initDataTable('accounts-table', '/api/finance?facility_id=<?php echo $facility; ?>', [
        { field: 'inmate_id', label: 'Inmate #', type: 'string' },
        { field: 'first_name', label: 'First Name', type: 'string' },
        { field: 'last_name', label: 'Last Name', type: 'string' },
        { field: 'account_balance', label: 'Balance', type: 'currency' },
        { 
            field: 'status', 
            label: 'Status',
            render: (value) => `<span class="status-badge status-${value?.toLowerCase() || 'active'}">${value}</span>`
        },
        { field: 'created_at', label: 'Created', type: 'date' }
    ], {
        title: 'Inmate Accounts',
        searchFields: ['inmate_id', 'first_name', 'last_name'],
        perPage: 20,
        actions: buildActions([
            { view: {
                icon: 'bi-wallet2',
                label: 'Statement',
                callback: (row) => window.location.href = `<?php echo APP_URL; ?>/modules/finance/statement.php?account_id=${row.id}`
            }},
            <?php if (hasPermission('create', 'finance')): ?> { deposit: {
                icon: 'bi-arrow-down-circle',
                label: 'Deposit',
                callback: (row) => showDepositModal(row.id)
            }} <?php endif; ?>
        ])
    });
});

async function showDepositModal(accountId) {
    const amount = prompt('Enter deposit amount:');
    if (amount && !isNaN(amount)) {
        const depositorName = prompt('Depositor name:');
        if (depositorName) {
            try {
                const result = await apiCall('/finance/?action=deposit', {
                    method: 'POST',
                    data: {
                        account_id: accountId,
                        amount: amount,
                        depositor_name: depositorName
                    }
                });
                if (result.success) {
                    showAlert('Deposit processed successfully', 'success');
                    location.reload();
                }
            } catch (error) {
                showAlert(error.message, 'error');
            }
        }
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>
