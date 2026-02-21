
</div><!-- /#page-content -->

    <!-- Footer -->
    <footer id="page-footer">
        <span>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(APP_NAME); ?> &nbsp;v<?php echo APP_VERSION; ?></span>
        <span>Secure Multi-Prison Management System</span>
    </footer>
</div><!-- /#main-wrapper -->

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- App scripts -->
<script src="<?php echo APP_URL; ?>/assets/js/main.js"></script>
<script src="<?php echo APP_URL; ?>/assets/js/data-table.js"></script>
<script>
const APP_URL  = '<?php echo APP_URL; ?>';
const API_URL  = '<?php echo API_URL; ?>';
const CURRENT_USER = <?php echo json_encode(getCurrentUser()); ?>;

/* ---------- Sidebar toggle (mobile) ---------- */
function toggleSidebar() {
    const sb  = document.getElementById('sidebar');
    const bd  = document.getElementById('sidebarBackdrop');
    const open = sb.classList.toggle('show');
    bd.classList.toggle('show', open);
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('show');
    document.getElementById('sidebarBackdrop').classList.remove('show');
}

/* ---------- Facility switcher ---------- */
function switchFacility(facilityId) {
    fetch(APP_URL + '/api/auth/switch-facility.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ facility_id: facilityId })
    }).then(() => location.reload());
}

/* ---------- Global alert helper ---------- */
function showAlert(msg, type = 'success') {
    const container = document.getElementById('alertsContainer');
    if (!container) return;
    const id  = 'alert-' + Date.now();
    const div = document.createElement('div');
    div.id = id;
    div.className = `alert alert-${type} alert-dismissible fade show shadow-sm`;
    div.style.cssText = 'border-radius:8px; font-size:0.875rem;';
    div.innerHTML = msg + `<button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>`;
    container.appendChild(div);
    setTimeout(() => { const el = document.getElementById(id); if(el) el.remove(); }, 5000);
}
</script>
</body>
</html>

