<?php
$pageTitle = 'Change Password';
require_once '../../includes/header.php';
requireAuth();
?>

<div class="glass-card mt-4" style="max-width: 500px; margin-left: auto; margin-right: auto; animation: slideIn 0.5s ease-out;">
    <h1 class="data-table-title mb-4">Change Password</h1>

    <form id="changePasswordForm" class="form-glass">
        <div class="form-group">
            <label class="form-label">Current Password *</label>
            <input type="password" name="current_password" class="form-input" placeholder="Enter your current password" required>
        </div>

        <div class="form-group">
            <label class="form-label">New Password *</label>
            <input type="password" name="new_password" class="form-input" placeholder="Enter new password (min. 8 characters)" required minlength="8">
            <div style="font-size: 0.85rem; color: rgba(255, 255, 255, 0.6); margin-top: 0.5rem;">
                Password must be at least 8 characters long
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Confirm New Password *</label>
            <input type="password" name="confirm_password" class="form-input" placeholder="Confirm new password" required minlength="8">
        </div>

        <div id="formMessage"></div>

        <div style="display: flex; gap: 1rem; margin-top: 2rem;">
            <button type="submit" class="btn-primary">✓ Change Password</button>
            <button type="button" class="btn-secondary" onclick="window.history.back()">Cancel</button>
        </div>
    </form>
</div>

<script>
document.getElementById('changePasswordForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const newPassword = formData.get('new_password');
    const confirmPassword = formData.get('confirm_password');
    
    // Validate passwords match
    if (newPassword !== confirmPassword) {
        document.getElementById('formMessage').innerHTML = 
            '<div class="alert-glass alert-danger">✗ Passwords do not match</div>';
        return;
    }
    
    const data = {
        current_password: formData.get('current_password'),
        new_password: newPassword,
        confirm_password: confirmPassword
    };
    
    try {
        const result = await apiCall('/auth/change-password', {
            method: 'POST',
            data: data
        });
        
        if (result.success) {
            document.getElementById('formMessage').innerHTML = 
                '<div class="alert-glass alert-success">✓ Password changed successfully! Redirecting...</div>';
            setTimeout(() => {
                window.location.href = '<?php echo APP_URL; ?>/modules/auth/profile.php';
            }, 1500);
        }
    } catch (error) {
        document.getElementById('formMessage').innerHTML = 
            `<div class="alert-glass alert-danger">✗ Error: ${error.message}</div>`;
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
