<?php
$pageTitle = 'Reports & Analytics';
require_once '../../includes/header.php';
requireAuth();
verifyPermission('view', 'reports');
$facility = getCurrentFacility();
$stats = getFacilityStatistics($facility);
?>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-top: 2rem;">
    <!-- Total Inmates -->
    <div class="glass-card-sm p-3">
        <div class="flex-between">
            <div>
                <div style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">Total Inmates</div>
                <div style="font-size: 2rem; font-weight: 700; color: var(--primary-light);">
                    <?php echo $stats['total_inmates'] ?? 0; ?>
                </div>
            </div>
            <div style="font-size: 3rem; opacity: 0.5;">👥</div>
        </div>
    </div>

    <!-- Total Staff -->
    <div class="glass-card-sm p-3">
        <div class="flex-between">
            <div>
                <div style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">Total Staff</div>
                <div style="font-size: 2rem; font-weight: 700; color: var(--primary-light);">
                    <?php echo $stats['total_staff'] ?? 0; ?>
                </div>
            </div>
            <div style="font-size: 3rem; opacity: 0.5;">👨‍💼</div>
        </div>
    </div>

    <!-- Incidents This Month -->
    <div class="glass-card-sm p-3">
        <div class="flex-between">
            <div>
                <div style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">Incidents</div>
                <div style="font-size: 2rem; font-weight: 700; color: var(--danger);">
                    <?php echo $stats['incidents_month'] ?? 0; ?>
                </div>
            </div>
            <div style="font-size: 3rem; opacity: 0.5;">⚠️</div>
        </div>
    </div>

    <!-- Pending Transfers -->
    <div class="glass-card-sm p-3">
        <div class="flex-between">
            <div>
                <div style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">Pending Transfers</div>
                <div style="font-size: 2rem; font-weight: 700; color: var(--warning);">
                    <?php echo $stats['pending_transfers'] ?? 0; ?>
                </div>
            </div>
            <div style="font-size: 3rem; opacity: 0.5;"><i class="bi bi-arrow-repeat"></i></div>
        </div>
    </div>
</div>

<div class="glass-card mt-4" style="animation: slideIn 0.5s ease-out;">
    <h2 class="data-table-title mb-3">Report Generator</h2>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
        <button class="btn-primary" onclick="generateReport('inmate-statistics')">
            📊 Inmate Statistics
        </button>
        <button class="btn-primary" onclick="generateReport('incident-report')">
            ⚠️ Incident Report
        </button>
        <button class="btn-primary" onclick="generateReport('staff-roster')">
            👨‍💼 Staff Roster
        </button>
        <button class="btn-primary" onclick="generateReport('occupancy-report')">
            📈 Occupancy Report
        </button>
        <button class="btn-primary" onclick="generateReport('audit-log')">
            📋 Audit Log
        </button>
        <button class="btn-primary" onclick="generateReport('finance-summary')">
            💰 Finance Summary
        </button>
    </div>
</div>

<div id="report-data" style="margin-top: 2rem;"></div>

<script>
async function generateReport(reportType) {
    const reportDiv = document.getElementById('report-data');
    reportDiv.innerHTML = '<div class="loader"></div> Generating report...';
    
    try {
        const result = await apiCall(`/reports?type=${reportType}&facility_id=<?php echo $facility; ?>`);
        
        if (result.success) {
            displayReport(result.data, reportType);
        }
    } catch (error) {
        reportDiv.innerHTML = `<div class="alert-glass alert-danger">Error: ${error.message}</div>`;
    }
}

function displayReport(data, reportType) {
    const reportDiv = document.getElementById('report-data');
    
    let html = `
        <div class="glass-card">
            <div class="flex-between mb-3">
                <h3 class="data-table-title">Report: ${reportType}</h3>
                <button class="btn-secondary btn-sm" onclick="exportReport('${reportType}')">
                    📥 Export CSV
                </button>
            </div>
            <div id="report-content"></div>
        </div>
    `;
    
    reportDiv.innerHTML = html;
    
    // Display data in table format
    if (data && Array.isArray(data)) {
        const reportContent = document.getElementById('report-content');
        const cols = Object.keys(data[0] || {});
        
        let table = '<div style="overflow-x: auto;"><table class="modern-table"><thead><tr>';
        cols.forEach(col => {
            table += `<th>${col}</th>`;
        });
        table += '</tr></thead><tbody>';
        
        data.forEach(row => {
            table += '<tr>';
            cols.forEach(col => {
                table += `<td>${row[col] || '-'}</td>`;
            });
            table += '</tr>';
        });
        
        table += '</tbody></table></div>';
        reportContent.innerHTML = table;
    }
}

function exportReport(reportType) {
    showAlert('Report exported to CSV', 'success');
    // In production, trigger actual file download
}
</script>

<?php require_once '../../includes/footer.php'; ?>
