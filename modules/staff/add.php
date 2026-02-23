<?php
$pageTitle = 'Add Staff Member';
require_once '../../includes/header.php';
requireAuth();
verifyPermission('create', 'staff');

$isSA = isSuperAdmin();
$fid  = getCurrentFacility();

/* ══════════════ SUPPORT DATA ══════════════ */
$facilities = $isSA
    ? fetchAll("SELECT id, name FROM facilities WHERE deleted_at IS NULL ORDER BY name")
    : fetchAll("SELECT id, name FROM facilities WHERE id=?", [$fid], 'i');

// Auto-generate next staff ID number
$lastNum = fetchOne("SELECT staff_id_number FROM staff ORDER BY id DESC LIMIT 1");
$nextNum = 1;
if ($lastNum) {
    preg_match('/\d+$/', $lastNum['staff_id_number'], $m);
    $nextNum = isset($m[0]) ? (int)$m[0] + 1 : 1;
}
$nextStaffId = 'STF' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);

/* ══════════════ VALIDATION HELPERS ══════════════ */
$errors = [];
$old    = [];   // re-populate form on error

/* ══════════════ POST HANDLER ══════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ── User account fields ──
    $old['first_name']   = trim($_POST['first_name']   ?? '');
    $old['last_name']    = trim($_POST['last_name']    ?? '');
    $old['email']        = trim($_POST['email']        ?? '');
    $old['username']     = trim($_POST['username']     ?? '');
    $old['role']         = trim($_POST['role']         ?? 'OFFICER');
    $old['password']     = $_POST['password']          ?? '';
    $old['confirm_pass'] = $_POST['confirm_pass']      ?? '';

    // ── Staff fields ──
    $old['facility_id']         = (int)($_POST['facility_id']         ?? ($isSA ? 0 : $fid));
    $old['staff_id_number']     = trim($_POST['staff_id_number']      ?? $nextStaffId);
    $old['staff_type']          = trim($_POST['staff_type']           ?? 'OFFICER');
    $old['position']            = trim($_POST['position']             ?? '');
    $old['phone']               = trim($_POST['phone']                ?? '');
    $old['gender']              = trim($_POST['gender']               ?? '');
    $old['date_of_birth']       = trim($_POST['date_of_birth']        ?? '');
    $old['hire_date']           = trim($_POST['hire_date']            ?? date('Y-m-d'));
    $old['employment_status']   = trim($_POST['employment_status']    ?? 'ACTIVE');

    // ── Validate user ──
    if (!$old['first_name']) $errors[] = 'First name is required.';
    if (!$old['last_name'])  $errors[] = 'Last name is required.';
    if (!$old['email'] || !filter_var($old['email'], FILTER_VALIDATE_EMAIL))
        $errors[] = 'A valid email address is required.';
    if (!$old['username'] || strlen($old['username']) < 3)
        $errors[] = 'Username must be at least 3 characters.';
    if (!$old['password'] || strlen($old['password']) < 8)
        $errors[] = 'Password must be at least 8 characters.';
    if ($old['password'] !== $old['confirm_pass'])
        $errors[] = 'Passwords do not match.';

    // ── Validate staff ──
    if (!$old['facility_id'])    $errors[] = 'Please select a facility.';
    if (!$old['staff_id_number']) $errors[] = 'Staff ID is required.';
    if (!$old['staff_type'])     $errors[] = 'Staff type is required.';
    if (!$old['hire_date'])      $errors[] = 'Hire date is required.';

    // ── Uniqueness checks ──
    if (!$errors) {
        if (fetchOne("SELECT id FROM users WHERE email=? AND deleted_at IS NULL", [$old['email']], 's'))
            $errors[] = 'That email address is already in use.';
        if (fetchOne("SELECT id FROM users WHERE username=? AND deleted_at IS NULL", [$old['username']], 's'))
            $errors[] = 'That username is already taken.';
        if (fetchOne("SELECT id FROM staff WHERE staff_id_number=? AND deleted_at IS NULL", [$old['staff_id_number']], 's'))
            $errors[] = 'Staff ID number already exists.';
    }

    // ── Insert ──
    if (!$errors) {
        $hash = password_hash($old['password'], PASSWORD_BCRYPT);

        // Insert user
        $uRes = executeQuery(
            "INSERT INTO users (facility_id,username,email,password_hash,first_name,last_name,role,is_active,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,1,NOW(),NOW())",
            [
                $old['facility_id'], $old['username'], $old['email'],
                $hash, $old['first_name'], $old['last_name'], $old['role']
            ],
            'issssss'
        );
        $newUserId = $uRes['id'];

        // Insert staff record
        executeQuery(
            "INSERT INTO staff (facility_id,user_id,staff_id_number,position,staff_type,hire_date,employment_status,phone,date_of_birth,gender,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW())",
            [
                $old['facility_id'], $newUserId,
                $old['staff_id_number'],
                $old['position']   ?: null,
                $old['staff_type'],
                $old['hire_date'],
                $old['employment_status'],
                $old['phone']          ?: null,
                $old['date_of_birth']  ?: null,
                $old['gender']         ?: null,
            ],
            'iisssssss'
        );

        // Redirect back to index with success
        header('Location: ' . APP_URL . '/modules/staff/index.php?created=1');
        exit;
    }
} else {
    $old = [
        'first_name' => '', 'last_name' => '', 'email' => '', 'username' => '',
        'role' => 'OFFICER', 'password' => '', 'confirm_pass' => '',
        'facility_id' => $fid, 'staff_id_number' => $nextStaffId,
        'staff_type' => 'OFFICER', 'position' => '', 'phone' => '',
        'gender' => '', 'date_of_birth' => '', 'hire_date' => date('Y-m-d'),
        'employment_status' => 'ACTIVE',
    ];
}
?>
<!-- ════════════════════════════════════ STYLES ════════════════════════════════════ -->
<style>
:root{--sur:#161b22;--bdr:#21262d;--bg:#0d1117;--txt:#e6edf3;--mut:#8b949e;--acc:#1f6feb;--acc2:#388bfd}

/* Page header */
.ph{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:.75rem}
.ph h1{font-size:1.35rem;font-weight:700;color:var(--txt);margin:0}
.bc{font-size:.78rem;color:#6e7681;display:flex;align-items:center;gap:.3rem;margin-bottom:.3rem}
.bc a{color:#388bfd;text-decoration:none}.bc a:hover{text-decoration:underline}

/* Card */
.card{background:var(--sur);border:1px solid var(--bdr);border-radius:16px;overflow:hidden;margin-bottom:1.5rem}
.card-head{padding:1rem 1.5rem;border-bottom:1px solid var(--bdr);display:flex;align-items:center;gap:.6rem}
.card-head h2{margin:0;font-size:.95rem;font-weight:700;color:var(--txt)}
.card-head .ch-ico{width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0}
.card-body{padding:1.5rem}

/* Form grid */
.fg-row{display:grid;gap:1.1rem;margin-bottom:1.1rem}
.fg-row.two   {grid-template-columns:1fr 1fr}
.fg-row.three {grid-template-columns:1fr 1fr 1fr}
@media(max-width:640px){.fg-row.two,.fg-row.three{grid-template-columns:1fr}}
.fg{display:flex;flex-direction:column;gap:.35rem}
.fg-label{font-size:.8rem;font-weight:600;color:var(--mut)}
.fg-label .req{color:#f85149}
.fg-hint{font-size:.71rem;color:#484f58;margin-top:.15rem}

/* Inputs */
.fi{background:#0d1117;border:1px solid #30363d;border-radius:9px;color:var(--txt);padding:.55rem .85rem;font-size:.875rem;width:100%;outline:none;transition:border-color .2s,box-shadow .2s;font-family:inherit;box-sizing:border-box}
.fi::placeholder{color:#484f58}
.fi:focus{border-color:#388bfd;box-shadow:0 0 0 3px rgba(56,139,253,.15)}
.fi.is-error{border-color:#f85149;box-shadow:0 0 0 3px rgba(248,81,73,.12)}
select.fi{cursor:pointer;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='%238b949e' d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right .75rem center;background-size:12px;padding-right:2.2rem;appearance:none}

/* Password toggle */
.pw-wrap{position:relative}
.pw-wrap .fi{padding-right:2.6rem}
.pw-toggle{position:absolute;right:.7rem;top:50%;transform:translateY(-50%);background:none;border:none;color:#8b949e;cursor:pointer;padding:.2rem;font-size:.9rem;line-height:1;transition:color .18s}
.pw-toggle:hover{color:#e6edf3}

/* Strength bar */
.pw-strength{height:3px;border-radius:2px;margin-top:.35rem;transition:width .3s,background .3s;width:0}

/* Input with icon */
.fi-icon-wrap{position:relative}
.fi-icon-wrap i{position:absolute;left:.8rem;top:50%;transform:translateY(-50%);color:#8b949e;font-size:.82rem;pointer-events:none}
.fi-icon-wrap .fi{padding-left:2.2rem}

/* Error list */
.err-box{background:rgba(248,81,73,.08);border:1px solid rgba(248,81,73,.3);border-radius:10px;padding:.8rem 1rem;margin-bottom:1.25rem;font-size:.84rem;color:#f85149}
.err-box ul{margin:.4rem 0 0 1rem;padding:0}
.err-box ul li{margin-bottom:.2rem}

/* Buttons */
.btn-primary{background:linear-gradient(135deg,#1f6feb,#388bfd);color:#fff;padding:.6rem 1.4rem;border-radius:9px;font-size:.9rem;font-weight:600;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:.45rem;transition:opacity .18s,transform .1s}
.btn-primary:hover{opacity:.88}
.btn-primary:active{transform:scale(.97)}
.btn-ghost{background:transparent;border:1px solid #30363d;color:#8b949e;padding:.6rem 1.2rem;border-radius:9px;font-size:.9rem;font-weight:500;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;text-decoration:none;transition:all .18s}
.btn-ghost:hover{border-color:#58a6ff;color:#58a6ff}
.form-footer{display:flex;align-items:center;justify-content:flex-end;gap:.75rem;padding-top:1rem;border-top:1px solid var(--bdr);flex-wrap:wrap}

/* Staff ID prefix badge */
.sid-group{display:flex;gap:0}
.sid-prefix{background:#21262d;border:1px solid #30363d;border-right:none;border-radius:9px 0 0 9px;padding:.55rem .85rem;font-size:.875rem;color:#8b949e;white-space:nowrap;display:flex;align-items:center}
.sid-group .fi{border-radius:0 9px 9px 0}

/* Preview avatar */
.preview-wrap{display:flex;align-items:center;gap:1rem;padding:1rem 1.5rem;background:#0d1117;border-bottom:1px solid var(--bdr)}
.preview-av{width:52px;height:52px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.1rem;font-weight:700;color:#fff;flex-shrink:0;transition:background .3s}
.preview-name{font-weight:700;font-size:.95rem;color:var(--txt)}
.preview-sub{font-size:.75rem;color:var(--mut);margin-top:2px}

/* Section divider */
.sec-div{display:flex;align-items:center;gap:.75rem;margin:1.5rem 0 1.25rem;color:#8b949e;font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.07em}
.sec-div::before,.sec-div::after{content:'';flex:1;height:1px;background:#21262d}

/* Info note */
.note{background:rgba(56,139,253,.07);border:1px solid rgba(56,139,253,.2);border-radius:9px;padding:.65rem 1rem;font-size:.8rem;color:#58a6ff;display:flex;align-items:flex-start;gap:.5rem;margin-bottom:1.1rem}
.note i{flex-shrink:0;margin-top:1px}
</style>

<!-- ════════════════════════ PAGE HEADER ════════════════════════ -->
<div class="ph">
  <div>
    <div class="bc">
      <a href="<?php echo APP_URL; ?>/dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
      <i class="bi bi-chevron-right" style="font-size:.6rem"></i>
      <a href="<?php echo APP_URL; ?>/modules/staff/index.php">Staff</a>
      <i class="bi bi-chevron-right" style="font-size:.6rem"></i>
      <span>Add Member</span>
    </div>
    <h1>
      <i class="bi bi-person-plus-fill" style="color:#388bfd;margin-right:.5rem"></i>
      Add Staff Member
    </h1>
  </div>
  <a href="<?php echo APP_URL; ?>/modules/staff/index.php" class="btn-ghost">
    <i class="bi bi-arrow-left"></i> Back to Staff
  </a>
</div>

<!-- Error box -->
<?php if ($errors): ?>
<div class="err-box">
  <strong><i class="bi bi-exclamation-triangle-fill"></i> Please fix the following:</strong>
  <ul>
    <?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<form method="POST" id="staffForm" novalidate>

<!-- ════════════════════════ LIVE PREVIEW ════════════════════════ -->
<div class="card">
  <div class="preview-wrap">
    <div class="preview-av" id="previewAv" style="background:linear-gradient(135deg,#1f6feb,#388bfd)">
      <span id="previewInit">?</span>
    </div>
    <div>
      <div class="preview-name" id="previewName">New Staff Member</div>
      <div class="preview-sub" id="previewSub">Fill in the form below</div>
    </div>
  </div>

  <!-- ── SECTION 1: Personal Info ── -->
  <div class="card-body">
    <div class="card-head" style="padding-left:0;border-bottom:none;padding-bottom:0;margin-bottom:.75rem">
      <div class="ch-ico" style="background:rgba(56,139,253,.15);color:#388bfd"><i class="bi bi-person-fill"></i></div>
      <h2>Personal Information</h2>
    </div>

    <div class="fg-row three">
      <div class="fg">
        <label class="fg-label">First Name <span class="req">*</span></label>
        <input type="text" name="first_name" id="firstName" class="fi <?php echo in_array('First name is required.', $errors)?'is-error':''; ?>"
               placeholder="e.g. John" value="<?php echo htmlspecialchars($old['first_name']); ?>" required autofocus>
      </div>
      <div class="fg">
        <label class="fg-label">Last Name <span class="req">*</span></label>
        <input type="text" name="last_name" id="lastName" class="fi <?php echo in_array('Last name is required.', $errors)?'is-error':''; ?>"
               placeholder="e.g. Smith" value="<?php echo htmlspecialchars($old['last_name']); ?>" required>
      </div>
      <div class="fg">
        <label class="fg-label">Gender</label>
        <select name="gender" class="fi">
          <option value="">— Select —</option>
          <?php foreach (['MALE','FEMALE','OTHER'] as $g): ?>
          <option value="<?php echo $g; ?>" <?php echo $old['gender']===$g?'selected':''; ?>><?php echo ucfirst(strtolower($g)); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="fg-row two">
      <div class="fg">
        <label class="fg-label">Date of Birth</label>
        <input type="date" name="date_of_birth" class="fi"
               value="<?php echo htmlspecialchars($old['date_of_birth']); ?>"
               max="<?php echo date('Y-m-d', strtotime('-18 years')); ?>">
        <span class="fg-hint">Must be 18+ years old</span>
      </div>
      <div class="fg">
        <label class="fg-label">Phone</label>
        <div class="fi-icon-wrap">
          <i class="bi bi-telephone"></i>
          <input type="tel" name="phone" class="fi" placeholder="+1 555 000 0000"
                 value="<?php echo htmlspecialchars($old['phone']); ?>">
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ════════════════════════ SECTION 2: Employment ════════════════════════ -->
<div class="card">
  <div class="card-head">
    <div class="ch-ico" style="background:rgba(26,188,156,.15);color:#1abc9c"><i class="bi bi-briefcase-fill"></i></div>
    <h2>Employment Details</h2>
  </div>
  <div class="card-body">

    <?php if ($isSA): ?>
    <div class="fg-row">
      <div class="fg">
        <label class="fg-label">Facility <span class="req">*</span></label>
        <select name="facility_id" class="fi" required>
          <option value="">— Select Facility —</option>
          <?php foreach ($facilities as $fac): ?>
          <option value="<?php echo $fac['id']; ?>" <?php echo $old['facility_id']==$fac['id']?'selected':''; ?>>
            <?php echo htmlspecialchars($fac['name']); ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <?php else: ?>
    <input type="hidden" name="facility_id" value="<?php echo $fid; ?>">
    <?php endif; ?>

    <div class="fg-row two">
      <div class="fg">
        <label class="fg-label">Staff ID <span class="req">*</span></label>
        <div class="sid-group">
          <span class="sid-prefix"><i class="bi bi-hash me-1"></i></span>
          <input type="text" name="staff_id_number" class="fi" required
                 value="<?php echo htmlspecialchars($old['staff_id_number']); ?>"
                 placeholder="STF001">
        </div>
        <span class="fg-hint">Auto-generated — change if needed</span>
      </div>
      <div class="fg">
        <label class="fg-label">Staff Type <span class="req">*</span></label>
        <select name="staff_type" id="staffType" class="fi" required>
          <?php
          $types = ['OFFICER'=>'Officer','NURSE'=>'Nurse','COUNSELOR'=>'Counselor',
                    'ADMINISTRATOR'=>'Administrator','GUARD'=>'Guard','SUPPORT'=>'Support Staff'];
          foreach ($types as $tv => $tl): ?>
          <option value="<?php echo $tv; ?>" <?php echo $old['staff_type']===$tv?'selected':''; ?>>
            <?php echo $tl; ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="fg-row two">
      <div class="fg">
        <label class="fg-label">Position / Title</label>
        <input type="text" name="position" class="fi" placeholder="e.g. Senior Guard, Head Nurse"
               value="<?php echo htmlspecialchars($old['position']); ?>">
      </div>
      <div class="fg">
        <label class="fg-label">Employment Status <span class="req">*</span></label>
        <select name="employment_status" class="fi" required>
          <?php
          $statuses = ['ACTIVE'=>'Active','INACTIVE'=>'Inactive','ON_LEAVE'=>'On Leave','TERMINATED'=>'Terminated'];
          foreach ($statuses as $sv => $sl): ?>
          <option value="<?php echo $sv; ?>" <?php echo $old['employment_status']===$sv?'selected':''; ?>><?php echo $sl; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="fg-row">
      <div class="fg">
        <label class="fg-label">Hire Date <span class="req">*</span></label>
        <input type="date" name="hire_date" class="fi" required
               value="<?php echo htmlspecialchars($old['hire_date']); ?>">
      </div>
    </div>

  </div>
</div>

<!-- ════════════════════════ SECTION 3: System Account ════════════════════════ -->
<div class="card">
  <div class="card-head">
    <div class="ch-ico" style="background:rgba(155,89,182,.15);color:#bb8fce"><i class="bi bi-shield-lock-fill"></i></div>
    <h2>System Account</h2>
  </div>
  <div class="card-body">

    <div class="note">
      <i class="bi bi-info-circle-fill"></i>
      A login account will be created for this staff member using the credentials below.
      The staff member will use their username and password to access the system.
    </div>

    <div class="fg-row two">
      <div class="fg">
        <label class="fg-label">Email Address <span class="req">*</span></label>
        <div class="fi-icon-wrap">
          <i class="bi bi-envelope"></i>
          <input type="email" name="email" class="fi <?php echo (in_array('A valid email address is required.',$errors)||in_array('That email address is already in use.',$errors))?'is-error':''; ?>"
                 placeholder="staff@facility.local"
                 value="<?php echo htmlspecialchars($old['email']); ?>" required>
        </div>
      </div>
      <div class="fg">
        <label class="fg-label">Username <span class="req">*</span></label>
        <div class="fi-icon-wrap">
          <i class="bi bi-at"></i>
          <input type="text" name="username" id="username" class="fi <?php echo (in_array('Username must be at least 3 characters.',$errors)||in_array('That username is already taken.',$errors))?'is-error':''; ?>"
                 placeholder="e.g. jsmith" autocomplete="off"
                 value="<?php echo htmlspecialchars($old['username']); ?>" required minlength="3">
        </div>
        <span class="fg-hint">Letters, numbers, underscores only</span>
      </div>
    </div>

    <div class="fg-row">
      <div class="fg">
        <label class="fg-label">System Role <span class="req">*</span></label>
        <select name="role" class="fi" required>
          <?php
          $roles = [
            'OFFICER'         => 'Officer',
            'MEDICAL_STAFF'   => 'Medical Staff',
            'RECORDS_OFFICER' => 'Records Officer',
            'FINANCE_OFFICER' => 'Finance Officer',
            'FACILITY_ADMIN'  => 'Facility Admin',
          ];
          if ($isSA) $roles['SUPER_ADMIN'] = 'Super Admin';
          foreach ($roles as $rv => $rl): ?>
          <option value="<?php echo $rv; ?>" <?php echo $old['role']===$rv?'selected':''; ?>><?php echo $rl; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="fg-row two">
      <div class="fg">
        <label class="fg-label">Password <span class="req">*</span></label>
        <div class="pw-wrap">
          <input type="password" name="password" id="password" class="fi <?php echo in_array('Password must be at least 8 characters.',$errors)?'is-error':''; ?>"
                 placeholder="Min. 8 characters" required minlength="8" autocomplete="new-password"
                 oninput="checkStrength(this.value)">
          <button type="button" class="pw-toggle" onclick="togglePw('password',this)" title="Show/hide">
            <i class="bi bi-eye"></i>
          </button>
        </div>
        <div class="pw-strength" id="pwStrength"></div>
        <span class="fg-hint" id="pwHint">Use letters, numbers &amp; symbols</span>
      </div>
      <div class="fg">
        <label class="fg-label">Confirm Password <span class="req">*</span></label>
        <div class="pw-wrap">
          <input type="password" name="confirm_pass" id="confirmPass" class="fi <?php echo in_array('Passwords do not match.',$errors)?'is-error':''; ?>"
                 placeholder="Re-enter password" required autocomplete="new-password"
                 oninput="checkMatch()">
          <button type="button" class="pw-toggle" onclick="togglePw('confirmPass',this)" title="Show/hide">
            <i class="bi bi-eye"></i>
          </button>
        </div>
        <span class="fg-hint" id="matchHint" style="color:#484f58">Passwords must match</span>
      </div>
    </div>

  </div>
</div>

<!-- ════════════════════════ FOOTER ════════════════════════ -->
<div class="form-footer">
  <a href="<?php echo APP_URL; ?>/modules/staff/index.php" class="btn-ghost">
    <i class="bi bi-x"></i> Cancel
  </a>
  <button type="submit" class="btn-primary">
    <i class="bi bi-person-check-fill"></i> Create Staff Member
  </button>
</div>

</form>

<!-- ════════════════════════ JAVASCRIPT ════════════════════════ -->
<script>
var typeAvatarBg = {
  OFFICER      : 'linear-gradient(135deg,#1f6feb,#388bfd)',
  NURSE        : 'linear-gradient(135deg,#1a7f37,#2ea043)',
  COUNSELOR    : 'linear-gradient(135deg,#6e40c9,#a371f7)',
  ADMINISTRATOR: 'linear-gradient(135deg,#9e6a03,#d29922)',
  GUARD        : 'linear-gradient(135deg,#b62324,#da3633)',
  SUPPORT      : 'linear-gradient(135deg,#444c56,#768390)',
};

var typeLabel = {
  OFFICER:'Officer',NURSE:'Nurse',COUNSELOR:'Counselor',
  ADMINISTRATOR:'Administrator',GUARD:'Guard',SUPPORT:'Support'
};

function updatePreview() {
  var fn   = document.getElementById('firstName').value.trim();
  var ln   = document.getElementById('lastName').value.trim();
  var type = document.getElementById('staffType').value;
  var sid  = document.querySelector('[name=staff_id_number]').value.trim();

  var init = ((fn[0]||'')+(ln[0]||'')).toUpperCase() || '?';
  document.getElementById('previewInit').textContent = init;
  document.getElementById('previewName').textContent = (fn||'First') + ' ' + (ln||'Last');
  document.getElementById('previewSub').textContent  = (typeLabel[type]||type) + (sid ? ' · '+sid : '');
  document.getElementById('previewAv').style.background = typeAvatarBg[type] || typeAvatarBg.OFFICER;
}

['firstName','lastName','staffType'].forEach(function(id){
  document.getElementById(id).addEventListener('input', updatePreview);
});
document.querySelector('[name=staff_id_number]').addEventListener('input', updatePreview);

// Auto-suggest username from first+last name
document.getElementById('firstName').addEventListener('blur', suggestUsername);
document.getElementById('lastName').addEventListener('blur', suggestUsername);
function suggestUsername(){
  var u = document.getElementById('username');
  if(u.value) return;
  var fn = document.getElementById('firstName').value.trim().toLowerCase().replace(/\s+/g,'');
  var ln = document.getElementById('lastName').value.trim().toLowerCase().replace(/\s+/g,'');
  if(fn && ln) u.value = fn[0]+ln;
}

// Password show/hide
function togglePw(id, btn){
  var inp = document.getElementById(id);
  var icon = btn.querySelector('i');
  if(inp.type==='password'){ inp.type='text'; icon.className='bi bi-eye-slash'; }
  else { inp.type='password'; icon.className='bi bi-eye'; }
}

// Password strength
function checkStrength(val){
  var bar  = document.getElementById('pwStrength');
  var hint = document.getElementById('pwHint');
  var score = 0;
  if(val.length>=8)  score++;
  if(/[A-Z]/.test(val)) score++;
  if(/[0-9]/.test(val)) score++;
  if(/[^A-Za-z0-9]/.test(val)) score++;
  var cfg = [
    {pct:'0%',  bg:'transparent',   lbl:''},
    {pct:'25%', bg:'#f85149',       lbl:'Weak'},
    {pct:'50%', bg:'#f39c12',       lbl:'Fair'},
    {pct:'75%', bg:'#388bfd',       lbl:'Good'},
    {pct:'100%',bg:'#3fb950',       lbl:'Strong'},
  ][score] || {pct:'0%',bg:'transparent',lbl:''};
  bar.style.width      = cfg.pct;
  bar.style.background = cfg.bg;
  hint.textContent     = cfg.lbl || 'Use letters, numbers & symbols';
  hint.style.color     = cfg.bg  || '#484f58';
}

// Password match indicator
function checkMatch(){
  var pw  = document.getElementById('password').value;
  var cp  = document.getElementById('confirmPass').value;
  var hint = document.getElementById('matchHint');
  if(!cp){ hint.textContent='Passwords must match'; hint.style.color='#484f58'; return; }
  if(pw===cp){ hint.textContent='✓ Passwords match'; hint.style.color='#3fb950'; }
  else        { hint.textContent='✗ Passwords do not match'; hint.style.color='#f85149'; }
}

// Run preview on load if re-populating after error
updatePreview();
</script>

<?php require_once '../../includes/footer.php'; ?>
