<?php
$pageTitle = 'User Profile';
require_once '../../includes/header.php';
requireAuth();
$sessionUser = getCurrentUser();
// Fetch full record so we have created_at and any other DB fields
$user = fetchOne(
    'SELECT id, username, email, first_name, last_name, role, facility_id, is_active, last_login, created_at
     FROM users WHERE id = ? AND deleted_at IS NULL',
    [$sessionUser['id']],
    'i'
) ?: $sessionUser;
?>

<div class="glass-card mt-4" style="max-width: 600px; margin-left: auto; margin-right: auto; animation: slideIn 0.5s ease-out;">
    <h1 class="data-table-title mb-4">User Profile</h1>

    <div class="form-glass">
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Username</label>
                <div style="padding: 0.75rem 1rem; background: var(--glass-dark); border-radius: 8px; border: var(--glass-border);">
                    <?php echo htmlspecialchars($user['username']); ?>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Email</label>
                <div style="padding: 0.75rem 1rem; background: var(--glass-dark); border-radius: 8px; border: var(--glass-border);">
                    <?php echo htmlspecialchars($user['email']); ?>
                </div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">First Name</label>
                <div style="padding: 0.75rem 1rem; background: var(--glass-dark); border-radius: 8px; border: var(--glass-border);">
                    <?php echo htmlspecialchars($user['first_name']); ?>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Last Name</label>
                <div style="padding: 0.75rem 1rem; background: var(--glass-dark); border-radius: 8px; border: var(--glass-border);">
                    <?php echo htmlspecialchars($user['last_name']); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Role</label>
            <div style="padding: 0.75rem 1rem; background: var(--glass-dark); border-radius: 8px; border: var(--glass-border);">
                <span class="status-badge status-active"><?php echo $user['role']; ?></span>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Member Since</label>
            <div style="padding: 0.75rem 1rem; background: var(--glass-dark); border-radius: 8px; border: var(--glass-border);">
                <?php echo formatDate($user['created_at']); ?>
            </div>
        </div>

        <div style="display: flex; gap: 1rem; margin-top: 2rem;">
            <button class="btn-primary" onclick="window.location.href='<?php echo APP_URL; ?>/modules/auth/change-password.php'">
                🔐 Change Password
            </button>
            <button class="btn-secondary" onclick="window.history.back()">Back</button>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
