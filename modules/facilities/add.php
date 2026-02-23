<?php
$pageTitle = 'Add Facility';
require_once '../../includes/header.php';
requireAuth();

if (!isSuperAdmin()) {
    header('Location: ' . APP_URL . '/dashboard.php');
    exit;
}

/* ══════════════ VALIDATION / POST ══════════════ */
$errors = [];
$old    = [
    'name'               => '',
    'type'               => 'PRISON',
    'location'           => '',
    'total_capacity'     => '',
    'current_population' => '0',
    'status'             => 'ACTIVE',
    'contact_person'     => '',
    'contact_email'      => '',
    'contact_phone'      => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['name']               = trim($_POST['name']               ?? '');
    $old['type']               = trim($_POST['type']               ?? 'PRISON');
    $old['location']           = trim($_POST['location']           ?? '');
    $old['total_capacity']     = trim($_POST['total_capacity']     ?? '');
    $old['current_population'] = trim($_POST['current_population'] ?? '0');
    $old['status']             = trim($_POST['status']             ?? 'ACTIVE');
    $old['contact_person']     = trim($_POST['contact_person']     ?? '');
    $old['contact_email']      = trim($_POST['contact_email']      ?? '');
    $old['contact_phone']      = trim($_POST['contact_phone']      ?? '');

    /* ── Validate ── */
    if (!$old['name'])
        $errors[] = 'Facility name is required.';
    if (!in_array($old['type'], ['PRISON','ADMIN_OFFICE','HOLDING_CENTER']))
        $errors[] = 'Invalid facility type.';
    if ($old['total_capacity'] === '' || !ctype_digit((string)$old['total_capacity']) || (int)$old['total_capacity'] < 1)
        $errors[] = 'Total capacity must be a positive number.';
    if (!ctype_digit((string)$old['current_population']) || (int)$old['current_population'] < 0)
        $errors[] = 'Current population must be 0 or greater.';
    if ((int)$old['current_population'] > (int)$old['total_capacity'] && !$errors)
        $errors[] = 'Current population cannot exceed total capacity.';
    if ($old['contact_email'] && !filter_var($old['contact_email'], FILTER_VALIDATE_EMAIL))
        $errors[] = 'Contact email is not valid.';

    /* ── Duplicate name check ── */
    if (!$errors) {
        $dup = fetchOne("SELECT id FROM facilities WHERE name=? AND deleted_at IS NULL", [$old['name']], 's');
        if ($dup) $errors[] = 'A facility with that name already exists.';
    }

    /* ── Insert ── */
    if (!$errors) {
        executeQuery(
            "INSERT INTO facilities
             (name, type, location, total_capacity, current_population, status,
              contact_person, contact_email, contact_phone, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())",
            [
                $old['name'],
                $old['type'],
                $old['location']           ?: null,
                (int)$old['total_capacity'],
                (int)$old['current_population'],
                $old['status'],
                $old['contact_person']     ?: null,
                $old['contact_email']      ?: null,
                $old['contact_phone']      ?: null,
            ],
            'sssiiisss'
        );
        header('Location: ' . APP_URL . '/modules/facilities/index.php?created=1');
        exit;
    }
}
?>
<!-- ════════════════════════════════ STYLES ════════════════════════════════ -->
<style>
:root{--sur:#161b22;--bdr:#21262d;--bg:#0d1117;--txt:#e6edf3;--mut:#8b949e;--acc:#1f6feb;--acc2:#388bfd}

.ph{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:.75rem}
.ph h1{font-size:1.35rem;font-weight:700;color:var(--txt);margin:0}
.bc{font-size:.78rem;color:#6e7681;display:flex;align-items:center;gap:.3rem;margin-bottom:.3rem}
.bc a{color:#388bfd;text-decoration:none}.bc a:hover{text-decoration:underline}

/* Cards */
.card{background:var(--sur);border:1px solid var(--bdr);border-radius:16px;overflow:hidden;margin-bottom:1.5rem}
.card-head{padding:1rem 1.5rem;border-bottom:1px solid var(--bdr);display:flex;align-items:center;gap:.65rem}
.card-head h2{margin:0;font-size:.95rem;font-weight:700;color:var(--txt)}
.ch-ico{width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0}
.card-body{padding:1.5rem}

/* Form */
.fg-row{display:grid;gap:1.1rem;margin-bottom:1.1rem}
.fg-row.two  {grid-template-columns:1fr 1fr}
.fg-row.three{grid-template-columns:1fr 1fr 1fr}
@media(max-width:640px){.fg-row.two,.fg-row.three{grid-template-columns:1fr}}
.fg{display:flex;flex-direction:column;gap:.35rem}
.fg-label{font-size:.8rem;font-weight:600;color:var(--mut)}
.fg-label .req{color:#f85149}
.fg-hint{font-size:.71rem;color:#484f58;margin-top:.15rem}

.fi{background:#0d1117;border:1px solid #30363d;border-radius:9px;color:var(--txt);padding:.55rem .85rem;font-size:.875rem;width:100%;outline:none;transition:border-color .2s,box-shadow .2s;font-family:inherit;box-sizing:border-box}
.fi::placeholder{color:#484f58}
.fi:focus{border-color:#388bfd;box-shadow:0 0 0 3px rgba(56,139,253,.15)}
.fi.is-error{border-color:#f85149;box-shadow:0 0 0 3px rgba(248,81,73,.12)}
select.fi{cursor:pointer;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='%238b949e' d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right .75rem center;background-size:12px;padding-right:2.2rem;appearance:none}
.fi-icon-wrap{position:relative}
.fi-icon-wrap i{position:absolute;left:.8rem;top:50%;transform:translateY(-50%);color:#8b949e;font-size:.82rem;pointer-events:none}
.fi-icon-wrap .fi{padding-left:2.2rem}

/* Error box */
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

/* Live preview */
.preview-wrap{display:flex;align-items:center;gap:1rem;padding:1rem 1.5rem;background:#0d1117;border-bottom:1px solid var(--bdr)}
.prev-ico{width:52px;height:52px;border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:1.25rem;flex-shrink:0;transition:background .3s}
.prev-name{font-weight:700;font-size:1rem;color:var(--txt)}
.prev-sub{font-size:.76rem;color:var(--mut);margin-top:3px;display:flex;align-items:center;gap:.4rem;flex-wrap:wrap}
.chip{display:inline-block;padding:2px 9px;border-radius:20px;font-size:.7rem;font-weight:700}

/* Capacity meter */
.cap-meter{background:#0d1117;border:1px solid var(--bdr);border-radius:10px;padding:.75rem 1rem;margin-top:.75rem}
.cap-bar-bg{height:8px;background:#21262d;border-radius:4px;overflow:hidden;margin:.4rem 0}
.cap-bar-fill{height:100%;border-radius:4px;transition:width .4s,background .4s}
.cap-nums{display:flex;justify-content:space-between;font-size:.75rem;color:#8b949e}

/* Note */
.note{background:rgba(56,139,253,.07);border:1px solid rgba(56,139,253,.2);border-radius:9px;padding:.65rem 1rem;font-size:.8rem;color:#58a6ff;display:flex;align-items:flex-start;gap:.5rem;margin-bottom:1.1rem}
.note i{flex-shrink:0;margin-top:1px}
</style>

<!-- ════════════════════════ PAGE HEADER ════════════════════════ -->
<div class="ph">
  <div>
    <div class="bc">
      <a href="<?php echo APP_URL; ?>/dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
      <i class="bi bi-chevron-right" style="font-size:.6rem"></i>
      <a href="<?php echo APP_URL; ?>/modules/facilities/index.php">Facilities</a>
      <i class="bi bi-chevron-right" style="font-size:.6rem"></i>
      <span>Add Facility</span>
    </div>
    <h1>
      <i class="bi bi-building-add" style="color:#388bfd;margin-right:.5rem"></i>
      Add Facility
    </h1>
  </div>
  <a href="<?php echo APP_URL; ?>/modules/facilities/index.php" class="btn-ghost">
    <i class="bi bi-arrow-left"></i> Back to Facilities
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

<form method="POST" id="facForm" novalidate>

<!-- ════════════════ SECTION 1: Basic Info ════════════════ -->
<div class="card">
  <!-- Live preview -->
  <div class="preview-wrap">
    <div class="prev-ico" id="prevIco" style="background:rgba(248,81,73,.15);color:#f85149">
      <i class="bi bi-building-lock" id="prevIcoIcon"></i>
    </div>
    <div>
      <div class="prev-name" id="prevName">New Facility</div>
      <div class="prev-sub">
        <span id="prevType" class="chip" style="background:rgba(248,81,73,.15);color:#f85149">Prison</span>
        <span id="prevStatus" class="chip" style="background:rgba(63,185,80,.18);color:#3fb950">Active</span>
        <span id="prevLoc" style="color:#8b949e"></span>
      </div>
    </div>
  </div>

  <div class="card-body">
    <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.9rem">
      <div class="ch-ico" style="background:rgba(56,139,253,.15);color:#388bfd"><i class="bi bi-info-circle-fill"></i></div>
      <span style="font-size:.9rem;font-weight:700;color:var(--txt)">Basic Information</span>
    </div>

    <div class="fg-row">
      <div class="fg">
        <label class="fg-label">Facility Name <span class="req">*</span></label>
        <input type="text" name="name" id="fName" class="fi <?php echo in_array('Facility name is required.',$errors)||in_array('A facility with that name already exists.',$errors)?'is-error':''; ?>"
               placeholder="e.g. Northern Correctional Facility"
               value="<?php echo htmlspecialchars($old['name']); ?>" required autofocus>
      </div>
    </div>

    <div class="fg-row two">
      <div class="fg">
        <label class="fg-label">Facility Type <span class="req">*</span></label>
        <select name="type" id="fType" class="fi" required>
          <?php foreach (['PRISON'=>'Prison','ADMIN_OFFICE'=>'Admin Office','HOLDING_CENTER'=>'Holding Center'] as $tv=>$tl): ?>
          <option value="<?php echo $tv; ?>" <?php echo $old['type']===$tv?'selected':''; ?>><?php echo $tl; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="fg">
        <label class="fg-label">Status <span class="req">*</span></label>
        <select name="status" id="fStatus" class="fi" required>
          <?php foreach (['ACTIVE'=>'Active','INACTIVE'=>'Inactive','UNDER_MAINTENANCE'=>'Under Maintenance'] as $sv=>$sl): ?>
          <option value="<?php echo $sv; ?>" <?php echo $old['status']===$sv?'selected':''; ?>><?php echo $sl; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="fg-row">
      <div class="fg">
        <label class="fg-label">Location / Address</label>
        <div class="fi-icon-wrap">
          <i class="bi bi-geo-alt"></i>
          <input type="text" name="location" id="fLoc" class="fi"
                 placeholder="e.g. 123 North District Road"
                 value="<?php echo htmlspecialchars($old['location']); ?>">
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ════════════════ SECTION 2: Capacity ════════════════ -->
<div class="card">
  <div class="card-head">
    <div class="ch-ico" style="background:rgba(26,188,156,.15);color:#1abc9c"><i class="bi bi-grid-3x3-gap-fill"></i></div>
    <h2>Capacity &amp; Population</h2>
  </div>
  <div class="card-body">

    <div class="note">
      <i class="bi bi-info-circle-fill"></i>
      Current population will be updated automatically as inmates are admitted or transferred. Set the initial value here if you are migrating existing data.
    </div>

    <div class="fg-row two">
      <div class="fg">
        <label class="fg-label">Total Capacity <span class="req">*</span></label>
        <div class="fi-icon-wrap">
          <i class="bi bi-people"></i>
          <input type="number" name="total_capacity" id="fCap" class="fi <?php echo in_array('Total capacity must be a positive number.',$errors)?'is-error':''; ?>"
                 placeholder="e.g. 500" min="1"
                 value="<?php echo htmlspecialchars($old['total_capacity']); ?>"
                 required oninput="updateMeter()">
        </div>
      </div>
      <div class="fg">
        <label class="fg-label">Current Population</label>
        <div class="fi-icon-wrap">
          <i class="bi bi-person-fill"></i>
          <input type="number" name="current_population" id="fPop" class="fi <?php echo in_array('Current population cannot exceed total capacity.',$errors)||in_array('Current population must be 0 or greater.',$errors)?'is-error':''; ?>"
                 placeholder="0" min="0"
                 value="<?php echo htmlspecialchars($old['current_population']); ?>"
                 oninput="updateMeter()">
        </div>
      </div>
    </div>

    <!-- Live capacity meter -->
    <div class="cap-meter">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.1rem">
        <span style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#8b949e">Occupancy Preview</span>
        <span id="meterPct" style="font-size:.85rem;font-weight:800;color:#3fb950">0%</span>
      </div>
      <div class="cap-bar-bg">
        <div class="cap-bar-fill" id="meterFill" style="width:0%;background:#3fb950"></div>
      </div>
      <div class="cap-nums">
        <span id="meterPop">0 inmates</span>
        <span id="meterCap">of 0 capacity</span>
      </div>
    </div>

  </div>
</div>

<!-- ════════════════ SECTION 3: Contact ════════════════ -->
<div class="card">
  <div class="card-head">
    <div class="ch-ico" style="background:rgba(155,89,182,.15);color:#bb8fce"><i class="bi bi-person-lines-fill"></i></div>
    <h2>Contact Information</h2>
  </div>
  <div class="card-body">
    <div class="fg-row three">
      <div class="fg">
        <label class="fg-label">Contact Person</label>
        <div class="fi-icon-wrap">
          <i class="bi bi-person"></i>
          <input type="text" name="contact_person" class="fi"
                 placeholder="e.g. Warden John Smith"
                 value="<?php echo htmlspecialchars($old['contact_person']); ?>">
        </div>
      </div>
      <div class="fg">
        <label class="fg-label">Contact Phone</label>
        <div class="fi-icon-wrap">
          <i class="bi bi-telephone"></i>
          <input type="tel" name="contact_phone" class="fi"
                 placeholder="+1 555 000 0000"
                 value="<?php echo htmlspecialchars($old['contact_phone']); ?>">
        </div>
      </div>
      <div class="fg">
        <label class="fg-label">Contact Email</label>
        <div class="fi-icon-wrap">
          <i class="bi bi-envelope"></i>
          <input type="email" name="contact_email" class="fi <?php echo in_array('Contact email is not valid.',$errors)?'is-error':''; ?>"
                 placeholder="admin@facility.gov"
                 value="<?php echo htmlspecialchars($old['contact_email']); ?>">
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ════════════════ FOOTER ════════════════ -->
<div class="form-footer">
  <a href="<?php echo APP_URL; ?>/modules/facilities/index.php" class="btn-ghost">
    <i class="bi bi-x"></i> Cancel
  </a>
  <button type="submit" class="btn-primary">
    <i class="bi bi-building-add"></i> Create Facility
  </button>
</div>

</form>

<!-- ════════════════════════ JAVASCRIPT ════════════════════════ -->
<script>
var typeConfig = {
  PRISON:         { icon:'bi-building-lock', bg:'rgba(248,81,73,.15)',  clr:'#f85149', label:'Prison'         },
  ADMIN_OFFICE:   { icon:'bi-building-gear', bg:'rgba(56,139,253,.15)', clr:'#58a6ff', label:'Admin Office'   },
  HOLDING_CENTER: { icon:'bi-shield-lock',   bg:'rgba(155,89,182,.15)', clr:'#bb8fce', label:'Holding Center' },
};
var statusConfig = {
  ACTIVE:             { bg:'rgba(63,185,80,.18)',   clr:'#3fb950', label:'Active'            },
  INACTIVE:           { bg:'rgba(139,148,158,.18)', clr:'#8b949e', label:'Inactive'          },
  UNDER_MAINTENANCE:  { bg:'rgba(243,156,18,.18)',  clr:'#f39c12', label:'Under Maintenance' },
};

function updatePreview() {
  var name   = document.getElementById('fName').value.trim() || 'New Facility';
  var type   = document.getElementById('fType').value;
  var status = document.getElementById('fStatus').value;
  var loc    = document.getElementById('fLoc').value.trim();
  var tc     = typeConfig[type]   || typeConfig.PRISON;
  var sc     = statusConfig[status] || statusConfig.ACTIVE;

  document.getElementById('prevName').textContent             = name;
  document.getElementById('prevIco').style.background        = tc.bg;
  document.getElementById('prevIco').style.color             = tc.clr;
  document.getElementById('prevIcoIcon').className           = 'bi ' + tc.icon;
  document.getElementById('prevType').textContent            = tc.label;
  document.getElementById('prevType').style.background       = tc.bg;
  document.getElementById('prevType').style.color            = tc.clr;
  document.getElementById('prevStatus').textContent          = sc.label;
  document.getElementById('prevStatus').style.background     = sc.bg;
  document.getElementById('prevStatus').style.color          = sc.clr;
  document.getElementById('prevLoc').textContent             = loc || '';
}

function updateMeter() {
  var pop = parseInt(document.getElementById('fPop').value) || 0;
  var cap = parseInt(document.getElementById('fCap').value) || 0;
  var pct = cap > 0 ? Math.min(100, Math.round(pop / cap * 100)) : 0;
  var clr = pct >= 90 ? '#f85149' : (pct >= 70 ? '#f39c12' : '#3fb950');

  document.getElementById('meterFill').style.width      = pct + '%';
  document.getElementById('meterFill').style.background = clr;
  document.getElementById('meterPct').textContent       = pct + '%';
  document.getElementById('meterPct').style.color       = clr;
  document.getElementById('meterPop').textContent       = pop + ' inmate' + (pop!==1?'s':'');
  document.getElementById('meterCap').textContent       = 'of ' + cap + ' capacity';
}

['fName','fType','fStatus','fLoc'].forEach(function(id){
  var el = document.getElementById(id);
  if(el) el.addEventListener('input', updatePreview);
  if(el) el.addEventListener('change', updatePreview);
});

// Init
updatePreview();
updateMeter();
</script>

<?php require_once '../../includes/footer.php'; ?>
