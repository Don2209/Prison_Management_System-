<?php
/**
 * Standalone User Creation Form
 * No login required — use to bootstrap admin accounts
 * Access: http://localhost/PMS/create-user.php
 */

require_once __DIR__ . '/config/db.php';

$errors   = [];
$success  = '';

// ── Handle form submission ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username   = trim($_POST['username']   ?? '');
    $email      = trim($_POST['email']      ?? '');
    $password   = $_POST['password']        ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name']  ?? '');
    $role       = $_POST['role']            ?? '';
    $facility_id = (int)($_POST['facility_id'] ?? 0);
    $is_active  = isset($_POST['is_active']) ? 1 : 0;

    $allowed_roles = ['SUPER_ADMIN','FACILITY_ADMIN','OFFICER','MEDICAL_STAFF','RECORDS_OFFICER','FINANCE_OFFICER'];

    // Validation
    if ($username === '')            $errors[] = 'Username is required.';
    elseif (strlen($username) < 3)  $errors[] = 'Username must be at least 3 characters.';
    if ($first_name === '')          $errors[] = 'First name is required.';
    if ($last_name === '')           $errors[] = 'Last name is required.';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))
                                     $errors[] = 'Invalid email address.';
    if ($password === '')            $errors[] = 'Password is required.';
    elseif (strlen($password) < 8)  $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm)      $errors[] = 'Passwords do not match.';
    if (!in_array($role, $allowed_roles)) $errors[] = 'Please select a valid role.';
    if ($facility_id <= 0)           $errors[] = 'Please select a facility.';

    if (empty($errors)) {
        $db = getDBConnection();

        // Check duplicate username
        $stmt = $db->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = "Username '$username' is already taken.";
        }
        $stmt->close();

        // Check duplicate email
        if ($email !== '' && empty($errors)) {
            $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $errors[] = "Email '$email' is already registered.";
            }
            $stmt->close();
        }

        if (empty($errors)) {
            $hash      = password_hash($password, PASSWORD_BCRYPT);
            $now       = date('Y-m-d H:i:s');
            $email_val = $email !== '' ? $email : null;

            $sql  = "INSERT INTO users (facility_id, username, email, password_hash, first_name, last_name, role, is_active, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($sql);
            $stmt->bind_param('issssssiss',
                $facility_id,
                $username,
                $email_val,
                $hash,
                $first_name,
                $last_name,
                $role,
                $is_active,
                $now,
                $now
            );

            if ($stmt->execute()) {
                $new_id = $stmt->insert_id;
                $success = "User <strong>$username</strong> created successfully! (ID: $new_id, Role: $role)";
                // Reset form fields
                $username = $email = $first_name = $last_name = $role = '';
                $facility_id = 0;
                $is_active = 1;
            } else {
                $errors[] = 'Database error: ' . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// ── Fetch facilities for dropdown ───────────────────────────────────
$facilities = [];
try {
    $db = getDBConnection();
    $res = $db->query("SELECT id, name FROM facilities WHERE status = 'ACTIVE' ORDER BY name");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $facilities[] = $row;
        }
    }
} catch (Exception $e) {
    // DB not set up yet — form will still render
}

$roles = [
    'SUPER_ADMIN'      => 'Super Admin',
    'FACILITY_ADMIN'   => 'Facility Admin',
    'OFFICER'          => 'Officer',
    'MEDICAL_STAFF'    => 'Medical Staff',
    'RECORDS_OFFICER'  => 'Records Officer',
    'FINANCE_OFFICER'  => 'Finance Officer',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create User — Prison Management System</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    min-height: 100vh;
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    padding: 30px 16px;
  }

  .card {
    background: rgba(255,255,255,0.07);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border: 1px solid rgba(255,255,255,0.15);
    border-radius: 16px;
    padding: 40px 44px;
    width: 100%;
    max-width: 520px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.4);
  }

  .card-header {
    text-align: center;
    margin-bottom: 30px;
  }

  .card-header .icon {
    font-size: 3rem;
    display: block;
    margin-bottom: 10px;
  }

  .card-header h1 {
    color: #fff;
    font-size: 1.5rem;
    font-weight: 700;
    letter-spacing: 0.5px;
  }

  .card-header p {
    color: rgba(255,255,255,0.55);
    font-size: 0.85rem;
    margin-top: 6px;
  }

  .alert {
    border-radius: 8px;
    padding: 12px 16px;
    margin-bottom: 20px;
    font-size: 0.875rem;
    line-height: 1.5;
  }

  .alert-error {
    background: rgba(220, 53, 69, 0.2);
    border: 1px solid rgba(220, 53, 69, 0.4);
    color: #ff8a95;
  }

  .alert-success {
    background: rgba(25, 195, 125, 0.15);
    border: 1px solid rgba(25, 195, 125, 0.35);
    color: #5debb5;
  }

  .alert ul { padding-left: 18px; }
  .alert ul li { margin-top: 4px; }

  .form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
  }

  .form-group {
    margin-bottom: 18px;
  }

  label {
    display: block;
    color: rgba(255,255,255,0.75);
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    margin-bottom: 6px;
  }

  input, select {
    width: 100%;
    padding: 10px 14px;
    border-radius: 8px;
    border: 1px solid rgba(255,255,255,0.2);
    background: rgba(255,255,255,0.08);
    color: #fff;
    font-size: 0.93rem;
    outline: none;
    transition: border-color 0.2s, background 0.2s;
  }

  input::placeholder { color: rgba(255,255,255,0.3); }

  input:focus, select:focus {
    border-color: #4a9eff;
    background: rgba(74, 158, 255, 0.08);
  }

  select option {
    background: #1e2d45;
    color: #fff;
  }

  .checkbox-group {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 22px;
  }

  .checkbox-group input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: #4a9eff;
  }

  .checkbox-group label {
    margin: 0;
    text-transform: none;
    font-size: 0.9rem;
    cursor: pointer;
    letter-spacing: 0;
    font-weight: 500;
  }

  .btn-submit {
    width: 100%;
    padding: 13px;
    border: none;
    border-radius: 9px;
    background: linear-gradient(135deg, #4a9eff, #0055cc);
    color: #fff;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    letter-spacing: 0.5px;
    transition: opacity 0.2s, transform 0.1s;
  }

  .btn-submit:hover  { opacity: 0.9; }
  .btn-submit:active { transform: scale(0.98); }

  .back-link {
    display: block;
    text-align: center;
    margin-top: 20px;
    color: rgba(255,255,255,0.45);
    font-size: 0.82rem;
    text-decoration: none;
    transition: color 0.2s;
  }

  .back-link:hover { color: rgba(255,255,255,0.75); }

  .divider {
    border: none;
    border-top: 1px solid rgba(255,255,255,0.1);
    margin: 24px 0;
  }
</style>
</head>
<body>

<div class="card">
  <div class="card-header">
    <span class="icon">👤</span>
    <h1>Create User Account</h1>
    <p>Prison Management System — no login required</p>
  </div>

  <?php if (!empty($errors)): ?>
  <div class="alert alert-error">
    <ul>
      <?php foreach ($errors as $e): ?>
        <li><?= htmlspecialchars($e) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

  <?php if ($success): ?>
  <div class="alert alert-success">
    ✅ <?= $success ?>
    <br><small>You can now <a href="<?= dirname($_SERVER['PHP_SELF']) ?>/index.php" style="color:#5debb5;">log in</a> with these credentials.</small>
  </div>
  <?php endif; ?>

  <form method="POST" action="">

    <div class="form-row">
      <div class="form-group">
        <label for="first_name">First Name *</label>
        <input type="text" id="first_name" name="first_name"
               value="<?= htmlspecialchars($first_name ?? '') ?>"
               placeholder="John" required>
      </div>
      <div class="form-group">
        <label for="last_name">Last Name *</label>
        <input type="text" id="last_name" name="last_name"
               value="<?= htmlspecialchars($last_name ?? '') ?>"
               placeholder="Doe" required>
      </div>
    </div>

    <div class="form-group">
      <label for="username">Username *</label>
      <input type="text" id="username" name="username"
             value="<?= htmlspecialchars($username ?? '') ?>"
             placeholder="e.g. john_doe" required autocomplete="off">
    </div>

    <div class="form-group">
      <label for="email">Email Address</label>
      <input type="email" id="email" name="email"
             value="<?= htmlspecialchars($email ?? '') ?>"
             placeholder="john@example.com">
    </div>

    <div class="form-row">
      <div class="form-group">
        <label for="password">Password *</label>
        <input type="password" id="password" name="password"
               placeholder="Min. 8 characters" required autocomplete="new-password">
      </div>
      <div class="form-group">
        <label for="confirm_password">Confirm Password *</label>
        <input type="password" id="confirm_password" name="confirm_password"
               placeholder="Repeat password" required autocomplete="new-password">
      </div>
    </div>

    <div class="form-group">
      <label for="role">Role *</label>
      <select id="role" name="role" required>
        <option value="">— Select Role —</option>
        <?php foreach ($roles as $val => $label): ?>
          <option value="<?= $val ?>" <?= (($role ?? '') === $val ? 'selected' : '') ?>>
            <?= $label ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group">
      <label for="facility_id">Facility *</label>
      <select id="facility_id" name="facility_id" required>
        <option value="">— Select Facility —</option>
        <?php if (empty($facilities)): ?>
          <option value="1">Facility 1 (default — run database.sql first)</option>
        <?php else: ?>
          <?php foreach ($facilities as $f): ?>
            <option value="<?= $f['id'] ?>" <?= (($facility_id ?? 0) == $f['id'] ? 'selected' : '') ?>>
              <?= htmlspecialchars($f['name']) ?>
            </option>
          <?php endforeach; ?>
        <?php endif; ?>
      </select>
    </div>

    <hr class="divider">

    <div class="checkbox-group">
      <input type="checkbox" id="is_active" name="is_active" value="1"
             <?= (isset($is_active) ? ($is_active ? 'checked' : '') : 'checked') ?>>
      <label for="is_active">Activate account immediately</label>
    </div>

    <button type="submit" class="btn-submit">Create Account</button>

  </form>

  <a class="back-link" href="index.php">← Back to Login</a>
</div>

</body>
</html>
