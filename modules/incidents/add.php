<?php
$pageTitle = 'Report Incident';
require_once '../../includes/header.php';
requireAuth();
verifyPermission('create', 'incidents');

$fid = getCurrentFacility();
$uid = getCurrentUserId();

/* ══════════════ LOOKUP DATA ══════════════ */
$categories = fetchAll(
    "SELECT id, name, severity FROM incident_categories WHERE deleted_at IS NULL ORDER BY name",
    [], ''
);

$inmates = fetchAll(
    "SELECT id, inmate_id, first_name, last_name FROM inmates
     WHERE facility_id = ? AND deleted_at IS NULL AND status = 'INCARCERATED'
     ORDER BY last_name, first_name",
    [$fid], 'i'
);

$staff = fetchAll(
    "SELECT s.id, CONCAT(u.first_name,' ',u.last_name) AS full_name, s.staff_type
     FROM staff s JOIN users u ON s.user_id = u.id
     WHERE s.facility_id = ? AND s.deleted_at IS NULL AND s.employment_status = 'ACTIVE'
     ORDER BY u.last_name, u.first_name",
    [$fid], 'i'
);

/* ══════════════ DEFAULTS ══════════════ */
$errors = [];
$old = [
    'incident_category_id' => '',
    'inmate_id'            => '',
    'staff_id'             => '',
    'incident_date'        => date('Y-m-d\TH:i'),
    'location'             => '',
    'severity'             => '',
    'description'          => '',
    'status'               => 'REPORTED',
];

/* ══════════════ POST HANDLER ══════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['incident_category_id'] = trim($_POST['incident_category_id'] ?? '');
    $old['inmate_id']            = trim($_POST['inmate_id']            ?? '');
    $old['staff_id']             = trim($_POST['staff_id']             ?? '');
    $old['incident_date']        = trim($_POST['incident_date']        ?? '');
    $old['location']             = trim($_POST['location']             ?? '');
    $old['severity']             = trim($_POST['severity']             ?? '');
    $old['description']          = trim($_POST['description']          ?? '');
    $old['status']               = trim($_POST['status']               ?? 'REPORTED');

    /* Validate */
    if (!$old['incident_category_id'] || !ctype_digit($old['incident_category_id']))
        $errors[] = 'Incident category is required.';
    if (!$old['incident_date'])
        $errors[] = 'Incident date and time is required.';
    if (!in_array($old['severity'], ['LOW','MEDIUM','HIGH','CRITICAL']))
        $errors[] = 'Severity is required.';
    if (!$old['description'])
        $errors[] = 'Description is required.';
    if (!in_array($old['status'], ['REPORTED','UNDER_INVESTIGATION','RESOLVED']))
        $errors[] = 'Invalid status.';

    /* Category exists */
    if (!$errors) {
        $catCheck = fetchOne("SELECT id FROM incident_categories WHERE id=? AND deleted_at IS NULL",
            [(int)$old['incident_category_id']], 'i');
        if (!$catCheck) $errors[] = 'Selected category does not exist.';
    }

    /* Insert */
    if (!$errors) {
        $inmateVal = $old['inmate_id'] && ctype_digit($old['inmate_id']) ? (int)$old['inmate_id'] : null;
        $staffVal  = $old['staff_id']  && ctype_digit($old['staff_id'])  ? (int)$old['staff_id']  : null;
        $incDate   = date('Y-m-d H:i:s', strtotime($old['incident_date']));

        executeQuery(
            "INSERT INTO incidents
             (facility_id, incident_category_id, inmate_id, staff_id, reported_by,
              incident_date, description, location, severity, status, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW())",
            [
                $fid,
                (int)$old['incident_category_id'],
                $inmateVal,
                $staffVal,
                $uid,
                $incDate,
                $old['description'],
                $old['location'] ?: null,
                $old['severity'],
                $old['status'],
            ],
            'iiiiissss' . 's'
        );

        header('Location: ' . APP_URL . '/modules/incidents/index.php?created=1');
        exit;
    }
}

/* ══════════════ SEV CONFIG ══════════════ */
$sevConfig = [
    'LOW'      => ['#3fb950', 'rgba(63,185,80,.15)',  'bi-arrow-down-circle',          'Low — Minor incident, no immediate threat'],
    'MEDIUM'   => ['#f39c12', 'rgba(243,156,18,.15)', 'bi-dash-circle',                'Medium — Notable incident requiring attention'],
    'HIGH'     => ['#f85149', 'rgba(248,81,73,.15)',  'bi-arrow-up-circle',            'High — Serious incident, prompt action needed'],
    'CRITICAL' => ['#ff6b6b', 'rgba(139,0,0,.3)',     'bi-exclamation-triangle-fill',  'Critical — Immediate threat, emergency response'],
];
?>
<style>
:root{--sur:#161b22;--bdr:#21262d;--bg:#0d1117;--txt:#e6edf3;--mut:#8b949e;--acc:#1f6feb;--acc2:#388bfd}

.ph{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:.75rem}
.ph h1{font-size:1.35rem;font-weight:700;color:var(--txt);margin:0}
.bc{font-size:.78rem;color:#6e7681;display:flex;align-items:center;gap:.3rem;margin-bottom:.3rem}
.bc a{color:#388bfd;text-decoration:none}.bc a:hover{text-decoration:underline}

.card{background:var(--sur);border:1px solid var(--bdr);border-radius:16px;overflow:hidden;margin-bottom:1.5rem}
.card-head{padding:.9rem 1.4rem;border-bottom:1px solid var(--bdr);display:flex;align-items:center;gap:.65rem}
.card-head h2{margin:0;font-size:.95rem;font-weight:700;color:var(--txt)}
.ch-ico{width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0}
.card-body{padding:1.4rem}

.fg-row{display:grid;gap:1.1rem;margin-bottom:1.1rem}
.fg-row.two{grid-template-columns:1fr 1fr}
.fg-row.three{grid-template-columns:1fr 1fr 1fr}
@media(max-width:640px){.fg-row.two,.fg-row.three{grid-template-columns:1fr}}
.fg{display:flex;flex-direction:column;gap:.35rem}
.fg-label{font-size:.8rem;font-weight:600;color:var(--mut)}
.req{color:#f85149}
.fg-hint{font-size:.72rem;color:#484f58}

.fi{background:#0d1117;border:1px solid #30363d;border-radius:9px;color:var(--txt);padding:.55rem .85rem;font-size:.875rem;width:100%;outline:none;transition:border-color .2s,box-shadow .2s;font-family:inherit;box-sizing:border-box}
.fi::placeholder{color:#484f58}
.fi:focus{border-color:#388bfd;box-shadow:0 0 0 3px rgba(56,139,253,.15)}
.fi.is-error{border-color:#f85149;box-shadow:0 0 0 3px rgba(248,81,73,.12)}
select.fi{cursor:pointer;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='%238b949e' d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right .75rem center;background-size:11px;padding-right:2rem;appearance:none}
textarea.fi{resize:vertical;min-height:110px}
.fi-icon-wrap{position:relative}
.fi-icon-wrap i.ico{position:absolute;left:.8rem;top:50%;transform:translateY(-50%);color:#8b949e;font-size:.82rem;pointer-events:none}
.fi-icon-wrap .fi{padding-left:2.2rem}

.err-box{background:rgba(248,81,73,.08);border:1px solid rgba(248,81,73,.3);border-radius:10px;padding:.8rem 1rem;margin-bottom:1.25rem;font-size:.84rem;color:#f85149}
.err-box ul{margin:.4rem 0 0 1rem;padding:0}.err-box ul li{margin-bottom:.2rem}

/* Severity selector */
.sev-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:.7rem}
@media(max-width:640px){.sev-grid{grid-template-columns:repeat(2,1fr)}}
.sev-opt{border:2px solid #30363d;border-radius:11px;padding:.75rem .9rem;cursor:pointer;transition:all .2s;position:relative;text-align:center}
.sev-opt input{position:absolute;opacity:0;pointer-events:none}
.sev-opt:hover{border-color:#8b949e}
.sev-opt.selected{border-width:2px}
.sev-ico{font-size:1.2rem;margin-bottom:.3rem}
.sev-lbl{font-size:.78rem;font-weight:800;letter-spacing:.05em;text-transform:uppercase}
.sev-desc{font-size:.68rem;color:#8b949e;margin-top:.25rem;line-height:1.3}

/* Involved party toggle */
.toggle-sec{background:#0d1117;border:1px solid #30363d;border-radius:10px;margin-bottom:.9rem}
.toggle-header{display:flex;align-items:center;justify-content:space-between;padding:.75rem 1rem;cursor:pointer;user-select:none}
.toggle-header span{font-size:.85rem;font-weight:600;color:var(--txt);display:flex;align-items:center;gap:.5rem}
.toggle-arrow{color:#8b949e;transition:transform .2s;font-size:.8rem}
.toggle-body{padding:0 1rem 1rem;display:none}
.toggle-body.open{display:block}

/* Live preview strip */
.preview-strip{background:#0d1117;border-bottom:1px solid var(--bdr);padding:.9rem 1.4rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap}
.prev-ico{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;transition:all .3s}
.prev-meta{flex:1;min-width:0}
.prev-title{font-weight:700;font-size:.9rem;color:var(--txt);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.prev-sub{font-size:.75rem;color:#8b949e;display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;margin-top:.2rem}
.chip{display:inline-block;padding:2px 9px;border-radius:20px;font-size:.7rem;font-weight:700}

.note{background:rgba(56,139,253,.07);border:1px solid rgba(56,139,253,.2);border-radius:9px;padding:.65rem 1rem;font-size:.8rem;color:#58a6ff;display:flex;align-items:flex-start;gap:.5rem;margin-bottom:1rem}
.note i{flex-shrink:0;margin-top:1px}

/* Buttons */
.btn-primary{background:linear-gradient(135deg,#1f6feb,#388bfd);color:#fff;padding:.62rem 1.5rem;border-radius:9px;font-size:.9rem;font-weight:600;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:.45rem;transition:opacity .18s}
.btn-primary:hover{opacity:.87}
.btn-ghost{background:transparent;border:1px solid #30363d;color:#8b949e;padding:.6rem 1.2rem;border-radius:9px;font-size:.9rem;font-weight:500;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;text-decoration:none;transition:all .18s}
.btn-ghost:hover{border-color:#58a6ff;color:#58a6ff}
.form-footer{display:flex;align-items:center;justify-content:flex-end;gap:.75rem;padding-top:1rem;border-top:1px solid var(--bdr);flex-wrap:wrap}
</style>

<!-- PAGE HEADER -->
<div class="ph">
  <div>
    <div class="bc">
      <a href="<?php echo APP_URL; ?>/dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
      <i class="bi bi-chevron-right" style="font-size:.6rem"></i>
      <a href="<?php echo APP_URL; ?>/modules/incidents/index.php">Incidents</a>
      <i class="bi bi-chevron-right" style="font-size:.6rem"></i>
      <span>Report Incident</span>
    </div>
    <h1><i class="bi bi-exclamation-triangle-fill" style="color:#f39c12;margin-right:.45rem"></i>Report Incident</h1>
  </div>
  <a href="<?php echo APP_URL; ?>/modules/incidents/index.php" class="btn-ghost">
    <i class="bi bi-arrow-left"></i> Back
  </a>
</div>

<?php if ($errors): ?>
<div class="err-box">
  <strong><i class="bi bi-exclamation-triangle-fill"></i> Please fix the following:</strong>
  <ul><?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<form method="POST" id="incForm" novalidate>

<!-- ═══ SECTION 1: Basic Info ═══ -->
<div class="card">
  <!-- Live preview -->
  <div class="preview-strip">
    <div class="prev-ico" id="prevIco" style="background:rgba(243,156,18,.15);color:#f39c12">
      <i class="bi bi-exclamation-triangle-fill" id="prevIcoIcon"></i>
    </div>
    <div class="prev-meta">
      <div class="prev-title" id="prevTitle">New Incident Report</div>
      <div class="prev-sub">
        <span id="prevCat" class="chip" style="background:rgba(56,139,253,.12);color:#58a6ff">Category</span>
        <span id="prevSev" class="chip" style="background:rgba(243,156,18,.12);color:#f39c12">Severity</span>
        <span id="prevDate" style="color:#8b949e"><i class="bi bi-calendar3"></i> Date &amp; Time</span>
      </div>
    </div>
  </div>

  <div class="card-head">
    <div class="ch-ico" style="background:rgba(243,156,18,.15);color:#f39c12"><i class="bi bi-info-circle-fill"></i></div>
    <h2>Incident Details</h2>
  </div>
  <div class="card-body">

    <div class="fg-row two">
      <div class="fg">
        <label class="fg-label">Category <span class="req">*</span></label>
        <select name="incident_category_id" id="fCat" class="fi <?php echo in_array('Incident category is required.',$errors)||in_array('Selected category does not exist.',$errors)?'is-error':''; ?>" required onchange="updatePreview()">
          <option value="">— Select category —</option>
          <?php foreach ($categories as $cat): ?>
          <option value="<?php echo $cat['id']; ?>"
            data-sev="<?php echo htmlspecialchars($cat['severity']); ?>"
            <?php echo $old['incident_category_id']==(string)$cat['id']?'selected':''; ?>>
            <?php echo htmlspecialchars($cat['name']); ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="fg">
        <label class="fg-label">Incident Date &amp; Time <span class="req">*</span></label>
        <input type="datetime-local" name="incident_date" id="fDate" class="fi <?php echo in_array('Incident date and time is required.',$errors)?'is-error':''; ?>"
               value="<?php echo htmlspecialchars($old['incident_date']); ?>"
               max="<?php echo date('Y-m-d\TH:i'); ?>"
               required onchange="updatePreview()">
      </div>
    </div>

    <div class="fg-row two">
      <div class="fg">
        <label class="fg-label">Location</label>
        <div class="fi-icon-wrap">
          <i class="bi bi-geo-alt ico"></i>
          <input type="text" name="location" id="fLoc" class="fi"
                 placeholder="e.g. Cell Block A, Recreation Yard"
                 value="<?php echo htmlspecialchars($old['location']); ?>">
        </div>
      </div>
      <div class="fg">
        <label class="fg-label">Initial Status <span class="req">*</span></label>
        <select name="status" class="fi">
          <?php foreach (['REPORTED'=>'Reported','UNDER_INVESTIGATION'=>'Under Investigation','RESOLVED'=>'Resolved'] as $sv=>$sl): ?>
          <option value="<?php echo $sv; ?>" <?php echo $old['status']===$sv?'selected':''; ?>><?php echo $sl; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="fg" style="margin-bottom:1.1rem">
      <label class="fg-label">Description <span class="req">*</span></label>
      <textarea name="description" class="fi <?php echo in_array('Description is required.',$errors)?'is-error':''; ?>"
                placeholder="Provide a detailed account of what happened, when, and any immediate actions taken…"
                required><?php echo htmlspecialchars($old['description']); ?></textarea>
    </div>

  </div>
</div>

<!-- ═══ SECTION 2: Severity ═══ -->
<div class="card">
  <div class="card-head">
    <div class="ch-ico" style="background:rgba(248,81,73,.15);color:#f85149"><i class="bi bi-shield-exclamation"></i></div>
    <h2>Severity Level <span style="color:#f85149;font-size:.8rem">*</span></h2>
  </div>
  <div class="card-body">
    <div class="sev-grid" id="sevGrid">
      <?php foreach ($sevConfig as $sv => [$clr,$bg,$ico,$desc]): ?>
      <label class="sev-opt <?php echo $old['severity']===$sv?'selected':''; ?>"
             id="sev-<?php echo $sv; ?>"
             style="<?php echo $old['severity']===$sv?"border-color:$clr;background:$bg":''; ?>"
             onclick="selectSev('<?php echo $sv; ?>')">
        <input type="radio" name="severity" value="<?php echo $sv; ?>" <?php echo $old['severity']===$sv?'checked':''; ?>>
        <div class="sev-ico" style="color:<?php echo $clr; ?>"><i class="bi <?php echo $ico; ?>"></i></div>
        <div class="sev-lbl" style="color:<?php echo $clr; ?>"><?php echo $sv; ?></div>
        <div class="sev-desc"><?php echo $desc; ?></div>
      </label>
      <?php endforeach; ?>
    </div>
    <?php if (in_array('Severity is required.',$errors)): ?>
    <div style="color:#f85149;font-size:.8rem;margin-top:.5rem"><i class="bi bi-exclamation-triangle"></i> Please select a severity level.</div>
    <?php endif; ?>
  </div>
</div>

<!-- ═══ SECTION 3: Involved Parties ═══ -->
<div class="card">
  <div class="card-head">
    <div class="ch-ico" style="background:rgba(56,139,253,.15);color:#388bfd"><i class="bi bi-people-fill"></i></div>
    <h2>Involved Parties <span style="font-size:.78rem;color:#8b949e;font-weight:400">(optional)</span></h2>
  </div>
  <div class="card-body">
    <div class="note">
      <i class="bi bi-info-circle-fill"></i>
      Link the inmate and/or staff member directly involved in this incident. Both fields are optional.
    </div>
    <div class="fg-row two">
      <div class="fg">
        <label class="fg-label"><i class="bi bi-person-badge" style="margin-right:.3rem"></i>Inmate Involved</label>
        <select name="inmate_id" class="fi">
          <option value="">— None / Unknown —</option>
          <?php foreach ($inmates as $inm): ?>
          <option value="<?php echo $inm['id']; ?>" <?php echo $old['inmate_id']==(string)$inm['id']?'selected':''; ?>>
            <?php echo htmlspecialchars($inm['inmate_id'].' — '.$inm['first_name'].' '.$inm['last_name']); ?>
          </option>
          <?php endforeach; ?>
        </select>
        <?php if (empty($inmates)): ?>
        <span class="fg-hint">No active inmates in this facility.</span>
        <?php endif; ?>
      </div>
      <div class="fg">
        <label class="fg-label"><i class="bi bi-person-workspace" style="margin-right:.3rem"></i>Staff Involved</label>
        <select name="staff_id" class="fi">
          <option value="">— None / Unknown —</option>
          <?php foreach ($staff as $st): ?>
          <option value="<?php echo $st['id']; ?>" <?php echo $old['staff_id']==(string)$st['id']?'selected':''; ?>>
            <?php echo htmlspecialchars($st['full_name'].' ('.$st['staff_type'].')'); ?>
          </option>
          <?php endforeach; ?>
        </select>
        <?php if (empty($staff)): ?>
        <span class="fg-hint">No active staff in this facility.</span>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- FOOTER -->
<div class="form-footer">
  <a href="<?php echo APP_URL; ?>/modules/incidents/index.php" class="btn-ghost">
    <i class="bi bi-x"></i> Cancel
  </a>
  <button type="submit" class="btn-primary">
    <i class="bi bi-flag-fill"></i> Submit Report
  </button>
</div>
</form>

<script>
var sevData = {
  LOW:      {clr:'#3fb950', bg:'rgba(63,185,80,.15)',  ico:'bi-arrow-down-circle',         lbl:'Low'},
  MEDIUM:   {clr:'#f39c12', bg:'rgba(243,156,18,.15)', ico:'bi-dash-circle',               lbl:'Medium'},
  HIGH:     {clr:'#f85149', bg:'rgba(248,81,73,.15)',  ico:'bi-arrow-up-circle',           lbl:'High'},
  CRITICAL: {clr:'#ff6b6b', bg:'rgba(139,0,0,.3)',     ico:'bi-exclamation-triangle-fill', lbl:'Critical'},
};

function selectSev(val) {
  document.querySelectorAll('.sev-opt').forEach(el => {
    el.classList.remove('selected');
    el.style.borderColor = '#30363d';
    el.style.background  = '';
  });
  var opt = document.getElementById('sev-'+val);
  if (opt) {
    opt.classList.add('selected');
    var d = sevData[val];
    opt.style.borderColor = d.clr;
    opt.style.background  = d.bg;
    opt.querySelector('input').checked = true;
  }
  updatePreview();
}

function updatePreview() {
  var catEl  = document.getElementById('fCat');
  var catTxt = catEl.options[catEl.selectedIndex]?.text || 'Category';
  var catSev = catEl.options[catEl.selectedIndex]?.dataset?.sev || '';
  var dateEl = document.getElementById('fDate');
  var dateTxt = dateEl.value ? new Date(dateEl.value).toLocaleString('en-US',{month:'short',day:'numeric',year:'numeric',hour:'2-digit',minute:'2-digit'}) : 'Date & Time';

  // Active severity
  var checked = document.querySelector('input[name="severity"]:checked');
  var sevVal  = checked ? checked.value : (catSev || '');
  var sev     = sevData[sevVal] || {clr:'#f39c12', bg:'rgba(243,156,18,.15)', ico:'bi-exclamation-triangle-fill', lbl:'Severity'};

  document.getElementById('prevIco').style.background = sev.bg;
  document.getElementById('prevIco').style.color      = sev.clr;
  document.getElementById('prevIcoIcon').className    = 'bi ' + sev.ico;

  var loc = document.getElementById('fLoc').value.trim();
  document.getElementById('prevTitle').textContent = catTxt !== 'Category' ? catTxt + (loc?' @ '+loc:'') : 'New Incident Report';
  document.getElementById('prevCat').textContent   = catTxt;
  document.getElementById('prevSev').textContent   = sev.lbl;
  document.getElementById('prevSev').style.background = sev.bg;
  document.getElementById('prevSev').style.color      = sev.clr;
  document.getElementById('prevDate').innerHTML    = '<i class="bi bi-calendar3"></i> ' + dateTxt;
}

// Auto-select severity from category default
document.getElementById('fCat').addEventListener('change', function() {
  var catSev = this.options[this.selectedIndex]?.dataset?.sev;
  if (catSev && !document.querySelector('input[name="severity"]:checked')) {
    selectSev(catSev);
  }
  updatePreview();
});

document.getElementById('fDate').addEventListener('change', updatePreview);
document.getElementById('fLoc').addEventListener('input', updatePreview);

// Init
updatePreview();
</script>

<?php require_once '../../includes/footer.php'; ?>
