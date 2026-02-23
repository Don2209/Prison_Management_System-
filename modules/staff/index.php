<?php
$pageTitle = 'Staff Management';
require_once '../../includes/header.php';
requireAuth();
verifyPermission('view', 'staff');

$isSA = isSuperAdmin();
$fid  = getCurrentFacility();
$uid  = getCurrentUserId();

$canCreate = hasPermission('create', 'staff') || $isSA;
$canEdit   = hasPermission('edit',   'staff') || $isSA;

/* ══════════════ POST: toggle status ══════════════ */
$flash = ['type' => '', 'msg' => ''];
if (isset($_GET['created'])) {
    $flash = ['type' => 'success', 'msg' => 'Staff member and system account created successfully.'];
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEdit) {
    $act    = trim($_POST['action'] ?? '');
    $sid    = (int)($_POST['staff_id'] ?? 0);
    $newSt  = trim($_POST['new_status'] ?? '');
    $allowed = ['ACTIVE','INACTIVE','ON_LEAVE','TERMINATED'];
    if ($act === 'set_status' && $sid && in_array($newSt, $allowed)) {
        executeQuery("UPDATE staff SET employment_status=?, updated_at=NOW() WHERE id=?", [$newSt, $sid], 'si');
        $flash = ['type' => 'success', 'msg' => 'Staff status updated successfully.'];
    }
}

/* ══════════════ FILTERS ══════════════ */
$fStatus   = $_GET['status']  ?? '';
$fType     = $_GET['type']    ?? '';
$fSearch   = trim($_GET['q']  ?? '');
$fFacility = (int)($_GET['fid'] ?? 0);

/* ══════════════ WHERE ══════════════ */
$wParts  = ["s.deleted_at IS NULL"];
$wParams = []; $wTypes = '';

if (!$isSA) {
    $wParts[]  = "s.facility_id = ?";
    $wParams[] = $fid; $wTypes .= 'i';
} elseif ($fFacility) {
    $wParts[]  = "s.facility_id = ?";
    $wParams[] = $fFacility; $wTypes .= 'i';
}
if ($fStatus) { $wParts[] = "s.employment_status = ?"; $wParams[] = $fStatus; $wTypes .= 's'; }
if ($fType)   { $wParts[] = "s.staff_type = ?";        $wParams[] = $fType;   $wTypes .= 's'; }
if ($fSearch) {
    $s = "%$fSearch%";
    $wParts[]  = "(u.first_name LIKE ? OR u.last_name LIKE ? OR s.staff_id_number LIKE ? OR s.position LIKE ? OR u.email LIKE ?)";
    $wParams[] = $s; $wParams[] = $s; $wParams[] = $s; $wParams[] = $s; $wParams[] = $s;
    $wTypes   .= 'sssss';
}
$where = implode(' AND ', $wParts);

/* ══════════════ MAIN QUERY ══════════════ */
$staffList = fetchAll(
    "SELECT s.*,
            u.first_name, u.last_name, u.email, u.role, u.is_active AS user_active,
            f.name AS facility_name
     FROM staff s
     JOIN users     u ON s.user_id     = u.id
     JOIN facilities f ON s.facility_id = f.id
     WHERE $where
     ORDER BY u.first_name, u.last_name",
    $wParams, $wTypes
);

/* ══════════════ KPI ══════════════ */
$kBase = $isSA ? "s.deleted_at IS NULL" : "s.facility_id={$fid} AND s.deleted_at IS NULL";
$kTotal      = (int)(fetchOne("SELECT COUNT(*) c FROM staff s WHERE $kBase")['c'] ?? 0);
$kActive     = (int)(fetchOne("SELECT COUNT(*) c FROM staff s WHERE $kBase AND employment_status='ACTIVE'")['c'] ?? 0);
$kOnLeave    = (int)(fetchOne("SELECT COUNT(*) c FROM staff s WHERE $kBase AND employment_status='ON_LEAVE'")['c'] ?? 0);
$kInactive   = (int)(fetchOne("SELECT COUNT(*) c FROM staff s WHERE $kBase AND employment_status='INACTIVE'")['c'] ?? 0);
$kOfficers   = (int)(fetchOne("SELECT COUNT(*) c FROM staff s WHERE $kBase AND staff_type='OFFICER'")['c'] ?? 0);
$kMedical    = (int)(fetchOne("SELECT COUNT(*) c FROM staff s WHERE $kBase AND staff_type='NURSE'")['c'] ?? 0);

/* ══════════════ SUPPORT DATA ══════════════ */
$allFacilities = $isSA ? getAccessibleFacilities() : [];

/* ══════════════ HELPERS ══════════════ */
$typeColors = [
    'OFFICER'       => ['chip'=>'chip-officer',   'bg'=>'rgba(56,139,253,.15)',  'clr'=>'#58a6ff'],
    'NURSE'         => ['chip'=>'chip-nurse',      'bg'=>'rgba(26,188,156,.15)', 'clr'=>'#1abc9c'],
    'COUNSELOR'     => ['chip'=>'chip-counselor',  'bg'=>'rgba(155,89,182,.15)', 'clr'=>'#bb8fce'],
    'ADMINISTRATOR' => ['chip'=>'chip-admin',      'bg'=>'rgba(243,156,18,.15)', 'clr'=>'#f39c12'],
    'GUARD'         => ['chip'=>'chip-guard',      'bg'=>'rgba(248,81,73,.15)',  'clr'=>'#f85149'],
    'SUPPORT'       => ['chip'=>'chip-support',    'bg'=>'rgba(139,148,158,.15)','clr'=>'#8b949e'],
];
$stColors = [
    'ACTIVE'     => 'chip-active',
    'INACTIVE'   => 'chip-inactive',
    'ON_LEAVE'   => 'chip-leave',
    'TERMINATED' => 'chip-terminated',
];
?>
<!-- ════════════════════════════════════ STYLES ════════════════════════════════════ -->
<style>
:root{--sur:#161b22;--bdr:#21262d;--bg:#0d1117;--txt:#e6edf3;--mut:#8b949e;--acc:#1f6feb;--acc2:#388bfd}

/* KPI */
.kpi-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:14px;margin-bottom:1.5rem}
.kpi{background:var(--sur);border:1px solid var(--bdr);border-radius:14px;padding:18px 16px 14px;position:relative;overflow:hidden;transition:transform .2s,box-shadow .2s,border-color .2s;cursor:default}
.kpi::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--kpi-bar,#388bfd);border-radius:14px 14px 0 0}
.kpi:hover{transform:translateY(-3px);box-shadow:0 10px 28px rgba(0,0,0,.4);border-color:var(--kpi-clr,#388bfd)}
.kpi-ico{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;margin-bottom:10px}
.kpi-val{font-size:1.75rem;font-weight:800;color:var(--txt);line-height:1}
.kpi-lbl{font-size:.72rem;color:var(--mut);text-transform:uppercase;letter-spacing:.07em;margin-top:3px}

/* Filter bar */
.filter-bar{background:var(--sur);border:1px solid var(--bdr);border-radius:12px;padding:14px 16px;margin-bottom:1.25rem;display:flex;flex-wrap:wrap;gap:.6rem;align-items:center}
.filter-bar .fi{background:#0d1117;border:1px solid #30363d;border-radius:8px;color:var(--txt);padding:.4rem .75rem;font-size:.82rem;outline:none;transition:border-color .2s}
.filter-bar .fi:focus{border-color:var(--acc2)}
.search-wrap{position:relative;flex:1;min-width:190px}
.search-wrap i{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--mut);font-size:.8rem;pointer-events:none}
.search-wrap input{padding-left:30px;width:100%}

/* Tab strip */
.tab-strip{display:flex;gap:4px;background:#0d1117;border:1px solid var(--bdr);border-radius:10px;padding:4px;margin-bottom:1.25rem;flex-wrap:wrap}
.tab-btn{padding:.35rem .95rem;border-radius:7px;font-size:.82rem;font-weight:600;cursor:pointer;border:none;color:var(--mut);background:transparent;transition:all .18s;white-space:nowrap;display:flex;align-items:center;gap:.35rem;text-decoration:none}
.tab-btn:hover{color:var(--txt);background:rgba(255,255,255,.05)}
.tab-btn.active{background:var(--sur);color:var(--txt);box-shadow:0 1px 6px rgba(0,0,0,.3)}
.tab-btn .cnt{background:#21262d;border-radius:20px;padding:1px 7px;font-size:.68rem;font-weight:700}
.tab-btn.active .cnt{background:var(--acc);color:#fff}

/* Table */
.tbl-wrap{background:var(--sur);border:1px solid var(--bdr);border-radius:14px;overflow:hidden}
.tbl-header{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid var(--bdr);flex-wrap:wrap;gap:.5rem}
.tbl-title{font-size:.9rem;font-weight:700;color:var(--txt)}
.tbl-sub{font-size:.75rem;color:var(--mut)}
table.t{width:100%;border-collapse:collapse;color:var(--txt);font-size:.85rem}
table.t thead th{background:#0d1117;color:var(--mut);font-size:.72rem;text-transform:uppercase;letter-spacing:.07em;font-weight:600;padding:10px 14px;border-bottom:1px solid var(--bdr);white-space:nowrap}
table.t tbody td{padding:11px 14px;border-bottom:1px solid var(--bdr);vertical-align:middle}
table.t tbody tr:last-child td{border-bottom:none}
table.t tbody tr:hover td{background:#1c2128}

/* Chips */
.chip{display:inline-block;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:600;letter-spacing:.02em;white-space:nowrap}
.chip-active    {background:rgba(63,185,80,.18); color:#3fb950}
.chip-inactive  {background:rgba(139,148,158,.15);color:#8b949e}
.chip-leave     {background:rgba(243,156,18,.18); color:#f39c12}
.chip-terminated{background:rgba(248,81,73,.18);  color:#f85149}
.chip-officer   {background:rgba(56,139,253,.15);  color:#58a6ff}
.chip-nurse     {background:rgba(26,188,156,.15);  color:#1abc9c}
.chip-counselor {background:rgba(155,89,182,.15);  color:#bb8fce}
.chip-admin     {background:rgba(243,156,18,.15);  color:#f39c12}
.chip-guard     {background:rgba(248,81,73,.15);   color:#f85149}
.chip-support   {background:rgba(139,148,158,.15); color:#8b949e}

/* Avatar */
.av{width:38px;height:38px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:700;color:#fff;flex-shrink:0}
.av-off {background:linear-gradient(135deg,#1f6feb,#388bfd)}
.av-nur {background:linear-gradient(135deg,#1a7f37,#2ea043)}
.av-cou {background:linear-gradient(135deg,#6e40c9,#a371f7)}
.av-adm {background:linear-gradient(135deg,#9e6a03,#d29922)}
.av-grd {background:linear-gradient(135deg,#b62324,#da3633)}
.av-sup {background:linear-gradient(135deg,#444c56,#768390)}

/* Btn */
.btn-act{display:inline-flex;align-items:center;gap:.3rem;padding:.3rem .7rem;border-radius:7px;font-size:.76rem;font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:all .18s;white-space:nowrap}
.btn-view {background:#21262d;color:#8b949e}.btn-view:hover{background:#30363d;color:#e6edf3}
.btn-edit {background:rgba(56,139,253,.15);color:#58a6ff}.btn-edit:hover{background:rgba(56,139,253,.28)}
.btn-primary-sm{background:linear-gradient(135deg,#1f6feb,#388bfd);color:#fff;padding:.45rem 1rem;border-radius:8px;font-size:.85rem;font-weight:600;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;transition:opacity .18s}
.btn-primary-sm:hover{opacity:.88}

/* Flash */
.flash{display:flex;align-items:center;gap:.6rem;padding:.75rem 1rem;border-radius:10px;font-size:.85rem;margin-bottom:1rem;animation:fIn .3s ease}
.flash-success{background:rgba(63,185,80,.12);border:1px solid rgba(63,185,80,.3);color:#3fb950}
.flash-error  {background:rgba(248,81,73,.12);border:1px solid rgba(248,81,73,.3);color:#f85149}
@keyframes fIn{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}

/* Modals */
.mbk{position:fixed;inset:0;background:rgba(0,0,0,.72);backdrop-filter:blur(4px);z-index:1050;display:none;align-items:center;justify-content:center;padding:1rem}
.mbk.open{display:flex}
.mbox{background:#161b22;border:1px solid #30363d;border-radius:14px;width:100%;max-width:560px;max-height:92vh;overflow-y:auto;animation:mIn .22s ease}
@keyframes mIn{from{opacity:0;transform:scale(.96)}to{opacity:1;transform:scale(1)}}
.mhead{display:flex;align-items:center;justify-content:space-between;padding:.9rem 1.25rem;border-bottom:1px solid #21262d;position:sticky;top:0;background:#161b22;z-index:2}
.mhead h3{margin:0;font-size:1rem;font-weight:700;color:#e6edf3;display:flex;align-items:center;gap:.5rem}
.mbody{padding:1.25rem}
.mfoot{display:flex;justify-content:flex-end;gap:.5rem;padding:.9rem 1.25rem;border-top:1px solid #21262d;position:sticky;bottom:0;background:#161b22}
.btn-x{background:none;border:none;color:#8b949e;font-size:1rem;cursor:pointer;padding:.2rem;line-height:1;transition:color .18s}
.btn-x:hover{color:#e6edf3}

/* Detail rows */
.dr{display:flex;justify-content:space-between;align-items:flex-start;padding:.5rem 0;border-bottom:1px solid #21262d;font-size:.84rem;gap:.5rem}
.dr:last-child{border-bottom:none}
.dk{color:#8b949e;font-size:.76rem;min-width:130px;flex-shrink:0}
.dv{color:#e6edf3;text-align:right;word-break:break-word;max-width:280px}

/* Profile banner in modal */
.profile-banner{display:flex;align-items:center;gap:1rem;background:#0d1117;border:1px solid #21262d;border-radius:10px;padding:1rem 1.25rem;margin-bottom:1.25rem}
.pb-name{font-weight:700;font-size:1rem;color:#e6edf3}
.pb-meta{font-size:.76rem;color:#8b949e;margin-top:3px}

/* Status dropdown */
.st-select{background:#0d1117;border:1px solid #30363d;border-radius:7px;color:#e6edf3;padding:.3rem .65rem;font-size:.76rem;cursor:pointer;outline:none}
.st-select:focus{border-color:#388bfd}

/* Empty */
.empty{text-align:center;padding:4rem 2rem;color:#8b949e}
.empty i{font-size:2.5rem;display:block;margin-bottom:.75rem;opacity:.35}

/* Page header */
.ph{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;flex-wrap:wrap;gap:.75rem}
.ph h1{font-size:1.35rem;font-weight:700;color:#e6edf3;margin:0}
.bc{font-size:.78rem;color:#6e7681;display:flex;align-items:center;gap:.3rem;margin-bottom:.3rem}
.bc a{color:#388bfd;text-decoration:none}.bc a:hover{text-decoration:underline}

/* fi generic */
.fi{background:#0d1117;border:1px solid #30363d;border-radius:8px;color:#e6edf3;padding:.5rem .8rem;font-size:.875rem;width:100%;outline:none;transition:border-color .2s,box-shadow .2s;font-family:inherit}
.fi:focus{border-color:#388bfd;box-shadow:0 0 0 3px rgba(56,139,253,.15)}
select.fi{cursor:pointer;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='%238b949e' d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right .7rem center;background-size:12px;padding-right:2rem;appearance:none}

/* Quick status form */
.qs-form{display:inline-flex;align-items:center;gap:.4rem}
</style>

<!-- ════════════════════════ PAGE HEADER ════════════════════════ -->
<div class="ph">
  <div>
    <div class="bc">
      <a href="<?php echo APP_URL; ?>/dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
      <i class="bi bi-chevron-right" style="font-size:.6rem"></i>
      <span>Staff</span>
    </div>
    <h1>
      <i class="bi bi-people-fill" style="color:#388bfd;margin-right:.4rem"></i>
      Staff Management
    </h1>
  </div>
  <?php if ($canCreate): ?>
  <a href="<?php echo APP_URL; ?>/modules/staff/add.php" class="btn-primary-sm">
    <i class="bi bi-plus-lg"></i> Add Staff Member
  </a>
  <?php endif; ?>
</div>

<!-- Flash -->
<?php if ($flash['msg']): ?>
<div class="flash flash-<?php echo $flash['type']; ?>">
  <i class="bi <?php echo $flash['type']==='success'?'bi-check-circle-fill':'bi-exclamation-triangle-fill'; ?>"></i>
  <?php echo htmlspecialchars($flash['msg']); ?>
</div>
<?php endif; ?>

<!-- ════════════════════════ KPI CARDS ════════════════════════ -->
<div class="kpi-grid">
<?php
$kpis = [
  ['Total Staff',   $kTotal,    'bi-people',           '#388bfd','rgba(31,111,235,.15)','#388bfd'],
  ['Active',        $kActive,   'bi-check-circle-fill','#3fb950','rgba(63,185,80,.15)' ,'#3fb950'],
  ['On Leave',      $kOnLeave,  'bi-clock-fill',       '#f39c12','rgba(243,156,18,.15)','#f39c12'],
  ['Inactive',      $kInactive, 'bi-dash-circle-fill', '#8b949e','rgba(139,148,158,.15)','#8b949e'],
  ['Officers',      $kOfficers, 'bi-shield-fill',      '#58a6ff','rgba(56,139,253,.15)','#58a6ff'],
  ['Medical Staff', $kMedical,  'bi-heart-pulse-fill', '#1abc9c','rgba(26,188,156,.15)','#1abc9c'],
];
foreach ($kpis as [$lbl,$val,$icon,$clr,$bg,$bar]): ?>
<div class="kpi" style="--kpi-bar:<?php echo $bar; ?>;--kpi-clr:<?php echo $clr; ?>">
  <div class="kpi-ico" style="background:<?php echo $bg; ?>;color:<?php echo $clr; ?>">
    <i class="bi <?php echo $icon; ?>"></i>
  </div>
  <div class="kpi-val"><?php echo number_format($val); ?></div>
  <div class="kpi-lbl"><?php echo $lbl; ?></div>
</div>
<?php endforeach; ?>
</div>

<!-- ════════════════════════ TABS ════════════════════════ -->
<div class="tab-strip">
<?php
$tabs = [
  '' => ['label'=>'All Staff','icon'=>'bi-list-ul'],
  'ACTIVE'     => ['label'=>'Active',     'icon'=>'bi-check-circle'],
  'ON_LEAVE'   => ['label'=>'On Leave',   'icon'=>'bi-clock'],
  'INACTIVE'   => ['label'=>'Inactive',   'icon'=>'bi-dash-circle'],
  'TERMINATED' => ['label'=>'Terminated', 'icon'=>'bi-x-circle'],
];
foreach ($tabs as $tval => $tinfo):
  $isA  = $fStatus === $tval;
  $tCnt = match($tval){ 'ACTIVE'=>$kActive,'ON_LEAVE'=>$kOnLeave,'INACTIVE'=>$kInactive,default=>$kTotal };
?>
<a href="?status=<?php echo $tval; ?><?php echo $fSearch?"&q=".urlencode($fSearch):''; ?>"
   class="tab-btn <?php echo $isA?'active':''; ?>">
  <i class="bi <?php echo $tinfo['icon']; ?>"></i>
  <?php echo $tinfo['label']; ?>
  <span class="cnt"><?php echo $tCnt; ?></span>
</a>
<?php endforeach; ?>
</div>

<!-- ════════════════════════ FILTER BAR ════════════════════════ -->
<form method="GET" class="filter-bar">
  <?php if ($fStatus): ?><input type="hidden" name="status" value="<?php echo htmlspecialchars($fStatus); ?>"><?php endif; ?>

  <div class="search-wrap">
    <i class="bi bi-search"></i>
    <input type="text" name="q" class="fi" placeholder="Search name, ID, position, email…" value="<?php echo htmlspecialchars($fSearch); ?>">
  </div>

  <select name="type" class="fi" onchange="this.form.submit()" style="min-width:155px">
    <option value="">All Types</option>
    <?php foreach (['OFFICER','NURSE','COUNSELOR','ADMINISTRATOR','GUARD','SUPPORT'] as $t): ?>
    <option value="<?php echo $t; ?>" <?php echo $fType===$t?'selected':''; ?>><?php echo ucfirst(strtolower($t)); ?></option>
    <?php endforeach; ?>
  </select>

  <?php if ($isSA): ?>
  <select name="fid" class="fi" onchange="this.form.submit()" style="min-width:160px">
    <option value="">All Facilities</option>
    <?php foreach ($allFacilities as $af): ?>
    <option value="<?php echo $af['id']; ?>" <?php echo $fFacility==$af['id']?'selected':''; ?>><?php echo htmlspecialchars($af['name']); ?></option>
    <?php endforeach; ?>
  </select>
  <?php endif; ?>

  <button type="submit" class="btn-act btn-edit" style="padding:.42rem .9rem"><i class="bi bi-funnel-fill"></i> Filter</button>
  <?php if ($fSearch || $fType || $fFacility): ?>
  <a href="?status=<?php echo htmlspecialchars($fStatus); ?>" class="btn-act btn-view"><i class="bi bi-x"></i> Clear</a>
  <?php endif; ?>
</form>

<!-- ════════════════════════ TABLE ════════════════════════ -->
<div class="tbl-wrap">
  <div class="tbl-header">
    <div>
      <div class="tbl-title"><i class="bi bi-table me-2" style="color:#388bfd"></i>Staff Directory</div>
      <div class="tbl-sub"><?php echo count($staffList); ?> member<?php echo count($staffList)!==1?'s':''; ?> found</div>
    </div>
  </div>

  <?php if (empty($staffList)): ?>
  <div class="empty">
    <i class="bi bi-people"></i>
    <div style="font-size:.95rem;font-weight:600;color:#e6edf3;margin-bottom:.35rem">No staff members found</div>
    <div style="font-size:.82rem">Adjust filters or add a new staff member.</div>
  </div>
  <?php else: ?>
  <div class="table-responsive">
  <table class="t">
    <thead>
      <tr>
        <th>Staff</th>
        <th>Staff ID</th>
        <?php if ($isSA): ?><th>Facility</th><?php endif; ?>
        <th>Type</th>
        <th>Position</th>
        <th>Hire Date</th>
        <th>Phone</th>
        <th>Status</th>
        <th style="text-align:right">Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($staffList as $s):
      $avClass  = ['OFFICER'=>'av-off','NURSE'=>'av-nur','COUNSELOR'=>'av-cou','ADMINISTRATOR'=>'av-adm','GUARD'=>'av-grd','SUPPORT'=>'av-sup'][$s['staff_type']] ?? 'av-off';
      $tyChip   = $typeColors[$s['staff_type']]['chip']  ?? 'chip-support';
      $stChip   = $stColors[$s['employment_status']]     ?? 'chip-inactive';
      $initials = strtoupper(substr($s['first_name'],0,1).substr($s['last_name'],0,1));
    ?>
    <tr>
      <td>
        <div style="display:flex;align-items:center;gap:10px">
          <div class="av <?php echo $avClass; ?>"><?php echo $initials; ?></div>
          <div>
            <div style="font-weight:600;font-size:.86rem"><?php echo htmlspecialchars($s['first_name'].' '.$s['last_name']); ?></div>
            <div style="font-size:.72rem;color:#8b949e"><?php echo htmlspecialchars($s['email']); ?></div>
          </div>
        </div>
      </td>
      <td style="font-size:.8rem;color:#58a6ff;font-weight:600;font-family:monospace">
        <?php echo htmlspecialchars($s['staff_id_number']); ?>
      </td>
      <?php if ($isSA): ?>
      <td style="font-size:.78rem;color:#8b949e"><?php echo htmlspecialchars($s['facility_name']); ?></td>
      <?php endif; ?>
      <td><span class="chip <?php echo $tyChip; ?>"><?php echo ucfirst(strtolower($s['staff_type'])); ?></span></td>
      <td style="font-size:.82rem"><?php echo htmlspecialchars($s['position'] ?: '—'); ?></td>
      <td style="font-size:.8rem;color:#8b949e;white-space:nowrap">
        <?php echo $s['hire_date'] ? date('M d, Y', strtotime($s['hire_date'])) : '—'; ?>
      </td>
      <td style="font-size:.8rem;color:#8b949e"><?php echo htmlspecialchars($s['phone'] ?: '—'); ?></td>
      <td>
        <?php if ($canEdit): ?>
        <form class="qs-form" method="POST" onchange="this.submit()">
          <input type="hidden" name="action"   value="set_status">
          <input type="hidden" name="staff_id" value="<?php echo $s['id']; ?>">
          <select name="new_status" class="st-select">
            <?php foreach (['ACTIVE','INACTIVE','ON_LEAVE','TERMINATED'] as $st): ?>
            <option value="<?php echo $st; ?>" <?php echo $s['employment_status']===$st?'selected':''; ?>>
              <?php echo ucfirst(str_replace('_',' ',strtolower($st))); ?>
            </option>
            <?php endforeach; ?>
          </select>
        </form>
        <?php else: ?>
        <span class="chip <?php echo $stChip; ?>"><?php echo ucfirst(str_replace('_',' ',strtolower($s['employment_status']))); ?></span>
        <?php endif; ?>
      </td>
      <td style="text-align:right;white-space:nowrap">
        <button class="btn-act btn-view" onclick='viewStaff(<?php echo json_encode($s); ?>)'>
          <i class="bi bi-eye"></i>
        </button>
        <?php if ($canEdit): ?>
        <a href="<?php echo APP_URL; ?>/modules/staff/edit.php?id=<?php echo $s['id']; ?>" class="btn-act btn-edit">
          <i class="bi bi-pencil"></i>
        </a>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>

<!-- ════════════════════════════════════════════
     MODAL: VIEW STAFF
     ════════════════════════════════════════════ -->
<div class="mbk" id="viewModal">
  <div class="mbox">
    <div class="mhead">
      <h3><i class="bi bi-person-badge" style="color:#388bfd"></i> Staff Details</h3>
      <button class="btn-x" onclick="closeM('viewModal')"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="mbody" id="viewBody"></div>
    <div class="mfoot">
      <button class="btn-act btn-view" onclick="closeM('viewModal')">Close</button>
    </div>
  </div>
</div>

<!-- ════════════════════════ JAVASCRIPT ════════════════════════ -->
<script>
function openM(id)  { document.getElementById(id).classList.add('open'); }
function closeM(id) { document.getElementById(id).classList.remove('open'); }

document.querySelectorAll('.mbk').forEach(function(m){
  m.addEventListener('click', function(e){ if(e.target===this) closeM(this.id); });
});
document.addEventListener('keydown', function(e){
  if(e.key==='Escape') document.querySelectorAll('.mbk.open').forEach(function(m){ m.classList.remove('open'); });
});

var typeClr = {
  OFFICER:'#58a6ff',NURSE:'#1abc9c',COUNSELOR:'#bb8fce',
  ADMINISTRATOR:'#f39c12',GUARD:'#f85149',SUPPORT:'#8b949e'
};
var stClr = {ACTIVE:'#3fb950',INACTIVE:'#8b949e',ON_LEAVE:'#f39c12',TERMINATED:'#f85149'};
var avCls = {OFFICER:'av-off',NURSE:'av-nur',COUNSELOR:'av-cou',ADMINISTRATOR:'av-adm',GUARD:'av-grd',SUPPORT:'av-sup'};

function esc(s){
  var d=document.createElement('div');
  d.appendChild(document.createTextNode(String(s??'')));
  return d.innerHTML;
}
function fmtDate(s){
  if(!s) return '—';
  try{return new Date(s).toLocaleDateString('en-GB',{day:'numeric',month:'short',year:'numeric'});}
  catch(e){return s;}
}

function viewStaff(m) {
  var initials = ((m.first_name||'?')[0]+(m.last_name||'?')[0]).toUpperCase();
  var avCl = avCls[m.staff_type] || 'av-off';
  var tc   = typeClr[m.staff_type] || '#8b949e';
  var sc   = stClr[m.employment_status] || '#8b949e';

  var html = '<div class="profile-banner">'
    + '<div class="av '+avCl+'" style="width:52px;height:52px;font-size:1.1rem">'+esc(initials)+'</div>'
    + '<div>'
    +   '<div class="pb-name">'+esc(m.first_name+' '+m.last_name)+'</div>'
    +   '<div class="pb-meta">'+esc(m.email || '—')+'</div>'
    +   '<div style="margin-top:5px;display:flex;gap:6px;flex-wrap:wrap">'
    +     '<span class="chip" style="background:'+tc+'22;color:'+tc+'">'+esc(m.staff_type)+'</span>'
    +     '<span class="chip" style="background:'+sc+'22;color:'+sc+'">'+esc(m.employment_status.replace(/_/g,' '))+'</span>'
    +   '</div>'
    + '</div>'
    + '</div>';

  var rows = [
    ['Staff ID',       m.staff_id_number || '—'],
    ['Position',       m.position        || '—'],
    ['Role',           m.role            || '—'],
    ['Facility',       m.facility_name   || '—'],
    ['Phone',          m.phone           || '—'],
    ['Gender',         m.gender          || '—'],
    ['Date of Birth',  fmtDate(m.date_of_birth)],
    ['Hire Date',      fmtDate(m.hire_date)],
    ['Added On',       fmtDate(m.created_at)],
  ];
  html += '<div>';
  rows.forEach(function(r){
    html += '<div class="dr"><span class="dk">'+esc(r[0])+'</span><span class="dv">'+esc(r[1])+'</span></div>';
  });
  html += '</div>';

  document.getElementById('viewBody').innerHTML = html;
  openM('viewModal');
}
</script>

<?php require_once '../../includes/footer.php'; ?>
