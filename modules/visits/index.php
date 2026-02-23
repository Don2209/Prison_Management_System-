<?php
/**
 * Visits Management – Full UI
 */
$pageTitle = 'Visits';
require_once '../../includes/header.php';
requireAuth();
verifyPermission('view', 'visits');

$isSA      = isSuperAdmin();
$fid       = getCurrentFacility();
$uid       = getCurrentUserId();
$canEdit   = hasPermission('create', 'visits') || $isSA
           || in_array(getCurrentUserRole(), ['FACILITY_ADMIN','SUPER_ADMIN','RECORDS_OFFICER','OFFICER']);
$canApprove = $isSA || in_array(getCurrentUserRole(), ['FACILITY_ADMIN','SUPER_ADMIN']);

/* ══════════════ FLASH ══════════════ */
$flash = ['type' => '', 'msg' => ''];

/* ══════════════ POST HANDLERS ══════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = trim($_POST['action'] ?? '');

    /* ── Schedule / Create ── */
    if ($act === 'create' && $canEdit) {
        $v_first   = trim($_POST['visitor_first']    ?? '');
        $v_last    = trim($_POST['visitor_last']     ?? '');
        $v_nid     = trim($_POST['visitor_nid']      ?? '');
        $v_phone   = trim($_POST['visitor_phone']    ?? '');
        $v_email   = trim($_POST['visitor_email']    ?? '');
        $v_rel     = trim($_POST['visitor_rel']      ?? '');
        $inmate_id = (int)($_POST['inmate_id']       ?? 0);
        $vtype     = trim($_POST['visit_type']       ?? 'PERSONAL');
        $vdate     = trim($_POST['visit_date']       ?? '');
        $duration  = (int)($_POST['duration']        ?? 60);
        $room      = trim($_POST['visit_room']       ?? '');
        $notes     = trim($_POST['notes']            ?? '');
        $fac_id    = $isSA ? (int)($_POST['facility_id'] ?? $fid) : $fid;

        if ($v_first && $v_last && $inmate_id && $vdate) {
            // Find or create visitor
            $visitor = $v_nid
                ? fetchOne("SELECT id FROM visitors WHERE national_id=? AND deleted_at IS NULL", [$v_nid], 's')
                : null;
            if (!$visitor) {
                executeQuery(
                    "INSERT INTO visitors (first_name,last_name,national_id,phone,email,relationship_to_inmate,status,created_at,updated_at)
                     VALUES (?,?,?,?,?,?,'APPROVED',NOW(),NOW())",
                    [$v_first, $v_last, $v_nid ?: null, $v_phone ?: null, $v_email ?: null, $v_rel ?: null],
                    'ssssss'
                );
                $visitor_id = getDB()->insert_id;
            } else {
                $visitor_id = $visitor['id'];
            }
            executeQuery(
                "INSERT INTO visits (facility_id,inmate_id,visitor_id,visit_date,duration_minutes,visit_type,approval_status,visit_room,notes,created_at,updated_at)
                 VALUES (?,?,?,?,?,?,'PENDING',?,?,NOW(),NOW())",
                [$fac_id, $inmate_id, $visitor_id, $vdate, $duration, $vtype, $room ?: null, $notes ?: null],
                'iiiisis'
            );
            $flash = ['type' => 'success', 'msg' => 'Visit scheduled and pending approval.'];
        } else {
            $flash = ['type' => 'error', 'msg' => 'Please fill all required fields.'];
        }

    /* ── Approve ── */
    } elseif ($act === 'approve' && $canApprove) {
        $vid = (int)($_POST['visit_id'] ?? 0);
        $v   = fetchOne("SELECT * FROM visits WHERE id=? AND deleted_at IS NULL", [$vid], 'i');
        if ($v && $v['approval_status'] === 'PENDING') {
            executeQuery(
                "UPDATE visits SET approval_status='APPROVED', approved_by=?, updated_at=NOW() WHERE id=?",
                [$uid, $vid], 'ii'
            );
            $flash = ['type' => 'success', 'msg' => 'Visit approved successfully.'];
        } else {
            $flash = ['type' => 'error', 'msg' => 'Visit not found or already processed.'];
        }

    /* ── Reject ── */
    } elseif ($act === 'reject' && $canApprove) {
        $vid  = (int)($_POST['visit_id']     ?? 0);
        $rnote = trim($_POST['reject_notes'] ?? 'Rejected.');
        $v    = fetchOne("SELECT * FROM visits WHERE id=? AND deleted_at IS NULL", [$vid], 'i');
        if ($v && $v['approval_status'] === 'PENDING') {
            executeQuery(
                "UPDATE visits SET approval_status='REJECTED', approved_by=?, notes=CONCAT(COALESCE(notes,''),' | Rejected: ',?), updated_at=NOW() WHERE id=?",
                [$uid, $rnote, $vid], 'isi'
            );
            $flash = ['type' => 'success', 'msg' => 'Visit rejected.'];
        } else {
            $flash = ['type' => 'error', 'msg' => 'Visit not found or already processed.'];
        }

    /* ── Cancel / Delete ── */
    } elseif ($act === 'delete' && $canEdit) {
        $vid = (int)($_POST['visit_id'] ?? 0);
        $v   = fetchOne("SELECT * FROM visits WHERE id=? AND deleted_at IS NULL", [$vid], 'i');
        if ($v && $v['approval_status'] === 'PENDING') {
            executeQuery("UPDATE visits SET deleted_at=NOW() WHERE id=?", [$vid], 'i');
            $flash = ['type' => 'success', 'msg' => 'Visit request cancelled.'];
        }
    }
}

/* ══════════════ FILTERS ══════════════ */
$fStatus   = $_GET['status']    ?? '';
$fType     = $_GET['type']      ?? '';
$fSearch   = trim($_GET['q']    ?? '');
$fDateFrom = $_GET['date_from'] ?? '';
$fDateTo   = $_GET['date_to']   ?? '';
$fFacility = (int)($_GET['fid'] ?? 0);

/* ══════════════ WHERE BUILDER ══════════════ */
$wParts = ["v.deleted_at IS NULL"];
$wParams = []; $wTypes = '';

if (!$isSA) {
    $wParts[]  = "v.facility_id = ?";
    $wParams[] = $fid; $wTypes .= 'i';
} elseif ($fFacility) {
    $wParts[]  = "v.facility_id = ?";
    $wParams[] = $fFacility; $wTypes .= 'i';
}
if ($fStatus) { $wParts[] = "v.approval_status = ?"; $wParams[] = $fStatus; $wTypes .= 's'; }
if ($fType)   { $wParts[] = "v.visit_type = ?";      $wParams[] = $fType;   $wTypes .= 's'; }
if ($fSearch) {
    $s = "%$fSearch%";
    $wParts[]  = "(vr.first_name LIKE ? OR vr.last_name LIKE ? OR i.first_name LIKE ? OR i.last_name LIKE ? OR i.inmate_id LIKE ?)";
    $wParams[] = $s; $wParams[] = $s; $wParams[] = $s; $wParams[] = $s; $wParams[] = $s;
    $wTypes   .= 'sssss';
}
if ($fDateFrom) { $wParts[] = "DATE(v.visit_date) >= ?"; $wParams[] = $fDateFrom; $wTypes .= 's'; }
if ($fDateTo)   { $wParts[] = "DATE(v.visit_date) <= ?"; $wParams[] = $fDateTo;   $wTypes .= 's'; }

$where = implode(' AND ', $wParts);

/* ══════════════ MAIN QUERY ══════════════ */
$visits = fetchAll(
    "SELECT v.*,
            vr.first_name AS vis_first, vr.last_name AS vis_last,
            vr.national_id AS vis_nid, vr.phone AS vis_phone,
            vr.email AS vis_email, vr.relationship_to_inmate AS vis_rel,
            vr.status AS vis_status,
            i.first_name AS inm_first, i.last_name AS inm_last,
            i.inmate_id AS inm_num, i.risk_classification AS inm_risk,
            f.name AS facility_name,
            CONCAT(u.first_name,' ',u.last_name) AS approver_name
     FROM visits v
     JOIN visitors  vr ON v.visitor_id  = vr.id
     JOIN inmates   i  ON v.inmate_id   = i.id
     JOIN facilities f  ON v.facility_id = f.id
     LEFT JOIN users u  ON v.approved_by = u.id
     WHERE $where
     ORDER BY v.visit_date DESC",
    $wParams, $wTypes
);

/* ══════════════ KPI ══════════════ */
$kBase    = $isSA ? "v.deleted_at IS NULL" : "v.facility_id={$fid} AND v.deleted_at IS NULL";
$kTotal   = (int)(fetchOne("SELECT COUNT(*) c FROM visits v WHERE $kBase")['c'] ?? 0);
$kPending = (int)(fetchOne("SELECT COUNT(*) c FROM visits v WHERE $kBase AND approval_status='PENDING'")['c'] ?? 0);
$kApproved= (int)(fetchOne("SELECT COUNT(*) c FROM visits v WHERE $kBase AND approval_status='APPROVED'")['c'] ?? 0);
$kRejected= (int)(fetchOne("SELECT COUNT(*) c FROM visits v WHERE $kBase AND approval_status='REJECTED'")['c'] ?? 0);
$kToday   = (int)(fetchOne("SELECT COUNT(*) c FROM visits v WHERE $kBase AND DATE(visit_date)=CURDATE()")['c'] ?? 0);
$kWeek    = (int)(fetchOne("SELECT COUNT(*) c FROM visits v WHERE $kBase AND YEARWEEK(visit_date,1)=YEARWEEK(NOW(),1)")['c'] ?? 0);

/* ══════════════ SUPPORT DATA ══════════════ */
$allFacilities = $isSA ? getAccessibleFacilities() : [];
$inmateList    = $isSA
    ? fetchAll("SELECT id, inmate_id, first_name, last_name FROM inmates WHERE status IN ('REMAND','CONVICTED') AND deleted_at IS NULL ORDER BY first_name LIMIT 500")
    : fetchAll("SELECT id, inmate_id, first_name, last_name FROM inmates WHERE facility_id=? AND status IN ('REMAND','CONVICTED') AND deleted_at IS NULL ORDER BY first_name", [$fid], 'i');
?>
<!-- ════════════════════════════════ STYLES ════════════════════════════════ -->
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
.chip-pending  {background:rgba(243,156,18,.18);color:#f39c12}
.chip-approved {background:rgba(63,185,80,.18); color:#3fb950}
.chip-rejected {background:rgba(248,81,73,.18); color:#f85149}
.chip-personal {background:rgba(56,139,253,.15);color:#58a6ff}
.chip-legal    {background:rgba(155,89,182,.15);color:#bb8fce}
.chip-official {background:rgba(26,188,156,.15);color:#1abc9c}

/* Avatar */
.av{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#1f6feb,#388bfd);display:inline-flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:700;color:#fff;flex-shrink:0}
.av-green{background:linear-gradient(135deg,#1a7f37,#2ea043)}

/* Buttons */
.btn-act{display:inline-flex;align-items:center;gap:.3rem;padding:.3rem .7rem;border-radius:7px;font-size:.76rem;font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:all .18s;white-space:nowrap}
.btn-view   {background:#21262d;color:#8b949e}  .btn-view:hover  {background:#30363d;color:#e6edf3}
.btn-approve{background:rgba(63,185,80,.15);color:#3fb950}.btn-approve:hover{background:rgba(63,185,80,.28)}
.btn-reject {background:rgba(248,81,73,.12);color:#f85149}.btn-reject:hover {background:rgba(248,81,73,.25)}
.btn-del    {background:transparent;color:#8b949e;padding:.3rem .5rem}.btn-del:hover{color:#f85149}
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
.mbox{background:#161b22;border:1px solid #30363d;border-radius:14px;width:100%;max-width:580px;max-height:92vh;overflow-y:auto;animation:mIn .22s ease}
.mbox.wide{max-width:660px}
@keyframes mIn{from{opacity:0;transform:scale(.96)}to{opacity:1;transform:scale(1)}}
.mhead{display:flex;align-items:center;justify-content:space-between;padding:.9rem 1.25rem;border-bottom:1px solid #21262d;position:sticky;top:0;background:#161b22;z-index:2}
.mhead h3{margin:0;font-size:1rem;font-weight:700;color:#e6edf3;display:flex;align-items:center;gap:.5rem}
.mbody{padding:1.25rem}
.mfoot{display:flex;justify-content:flex-end;gap:.5rem;padding:.9rem 1.25rem;border-top:1px solid #21262d;position:sticky;bottom:0;background:#161b22}
.btn-x{background:none;border:none;color:#8b949e;font-size:1rem;cursor:pointer;padding:.2rem;line-height:1;transition:color .18s}
.btn-x:hover{color:#e6edf3}

/* Form inside modal */
.f-sec{background:rgba(255,255,255,.025);border:1px solid #21262d;border-radius:10px;padding:1rem;margin-bottom:1rem}
.f-sec-title{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#8b949e;margin-bottom:.75rem;display:flex;align-items:center;gap:.4rem}
.fg{display:flex;flex-direction:column;gap:.3rem}
.fg-label{font-size:.78rem;font-weight:600;color:#8b949e}
.fg-label .req{color:#f85149}
.fg-row{display:grid;gap:.75rem;margin-bottom:.75rem}
.fg-row.two{grid-template-columns:1fr 1fr}
.fg-row.three{grid-template-columns:1fr 1fr 1fr}
@media(max-width:520px){.fg-row.two,.fg-row.three{grid-template-columns:1fr}}
.fi{background:#0d1117;border:1px solid #30363d;border-radius:8px;color:#e6edf3;padding:.5rem .8rem;font-size:.875rem;width:100%;outline:none;transition:border-color .2s,box-shadow .2s;font-family:inherit}
.fi::placeholder{color:#484f58}
.fi:focus{border-color:#388bfd;box-shadow:0 0 0 3px rgba(56,139,253,.15)}
select.fi{cursor:pointer;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='%238b949e' d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right .7rem center;background-size:12px;padding-right:2rem;appearance:none}
textarea.fi{resize:vertical;min-height:75px}

/* Detail rows */
.dr{display:flex;justify-content:space-between;align-items:flex-start;padding:.5rem 0;border-bottom:1px solid #21262d;font-size:.84rem;gap:.5rem}
.dr:last-child{border-bottom:none}
.dk{color:#8b949e;font-size:.76rem;min-width:130px;flex-shrink:0}
.dv{color:#e6edf3;text-align:right;word-break:break-word;max-width:280px}

/* Visitor card inside detail */
.vis-card{display:flex;align-items:center;gap:.75rem;background:#0d1117;border:1px solid #21262d;border-radius:10px;padding:.75rem 1rem;margin-bottom:.5rem}
.vc-name{font-weight:700;font-size:.9rem;color:#e6edf3}
.vc-meta{font-size:.75rem;color:#8b949e;margin-top:2px}

/* Today badge */
.today-badge{display:inline-flex;align-items:center;gap:.3rem;background:rgba(63,185,80,.12);border:1px solid rgba(63,185,80,.3);border-radius:20px;padding:2px 8px;font-size:.7rem;color:#3fb950;font-weight:600;margin-left:.4rem}

/* Empty */
.empty{text-align:center;padding:4rem 2rem;color:#8b949e}
.empty i{font-size:2.5rem;display:block;margin-bottom:.75rem;opacity:.35}

/* Page header */
.ph{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;flex-wrap:wrap;gap:.75rem}
.ph h1{font-size:1.35rem;font-weight:700;color:#e6edf3;margin:0}
.bc{font-size:.78rem;color:#6e7681;display:flex;align-items:center;gap:.3rem;margin-bottom:.3rem}
.bc a{color:#388bfd;text-decoration:none}.bc a:hover{text-decoration:underline}
</style>

<!-- ════════════════════════ PAGE HEADER ════════════════════════ -->
<div class="ph">
  <div>
    <div class="bc">
      <a href="<?php echo APP_URL; ?>/dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
      <i class="bi bi-chevron-right" style="font-size:.6rem"></i>
      <span>Visits</span>
    </div>
    <h1>
      <i class="bi bi-calendar-check-fill" style="color:#388bfd;margin-right:.4rem"></i>
      Visitor Management
    </h1>
  </div>
  <?php if ($canEdit): ?>
  <button class="btn-primary-sm" onclick="openM('createModal')">
    <i class="bi bi-plus-lg"></i> Schedule Visit
  </button>
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
  ['Total Visits',   $kTotal,    'bi-calendar-check',    '#388bfd','rgba(31,111,235,.15)','#388bfd'],
  ['Pending',        $kPending,  'bi-hourglass-split',   '#f39c12','rgba(243,156,18,.15)','#f39c12'],
  ['Approved',       $kApproved, 'bi-check-circle-fill', '#3fb950','rgba(63,185,80,.15)' ,'#3fb950'],
  ['Rejected',       $kRejected, 'bi-x-circle-fill',     '#f85149','rgba(248,81,73,.15)' ,'#f85149'],
  ["Today's Visits", $kToday,    'bi-calendar-day',      '#1abc9c','rgba(26,188,156,.15)','#1abc9c'],
  ['This Week',      $kWeek,     'bi-calendar-week',     '#9b59b6','rgba(155,89,182,.15)','#9b59b6'],
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
  '' => ['label'=>'All','icon'=>'bi-list-ul'],
  'PENDING'  => ['label'=>'Pending',  'icon'=>'bi-hourglass-split'],
  'APPROVED' => ['label'=>'Approved', 'icon'=>'bi-check-circle'],
  'REJECTED' => ['label'=>'Rejected', 'icon'=>'bi-x-circle'],
];
foreach ($tabs as $tval => $tinfo):
  $isA  = $fStatus === $tval;
  $tCnt = match($tval){ 'PENDING'=>$kPending,'APPROVED'=>$kApproved,'REJECTED'=>$kRejected,default=>$kTotal };
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
    <input type="text" name="q" class="fi" placeholder="Search visitor or inmate…" value="<?php echo htmlspecialchars($fSearch); ?>">
  </div>

  <select name="type" class="fi" onchange="this.form.submit()" style="min-width:140px">
    <option value="">All Types</option>
    <?php foreach (['PERSONAL','LEGAL','OFFICIAL'] as $t): ?>
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

  <input type="date" name="date_from" class="fi" value="<?php echo htmlspecialchars($fDateFrom); ?>" title="From" onchange="this.form.submit()" style="min-width:125px">
  <input type="date" name="date_to"   class="fi" value="<?php echo htmlspecialchars($fDateTo); ?>"   title="To"   onchange="this.form.submit()" style="min-width:125px">

  <button type="submit" class="btn-act btn-approve" style="padding:.42rem .9rem"><i class="bi bi-funnel-fill"></i> Filter</button>
  <?php if ($fSearch || $fType || $fFacility || $fDateFrom || $fDateTo): ?>
  <a href="?status=<?php echo htmlspecialchars($fStatus); ?>" class="btn-act btn-view"><i class="bi bi-x"></i> Clear</a>
  <?php endif; ?>
</form>

<!-- ════════════════════════ TABLE ════════════════════════ -->
<div class="tbl-wrap">
  <div class="tbl-header">
    <div>
      <div class="tbl-title"><i class="bi bi-table me-2" style="color:#388bfd"></i>Visit Records</div>
      <div class="tbl-sub"><?php echo count($visits); ?> record<?php echo count($visits)!==1?'s':''; ?> found</div>
    </div>
  </div>

  <?php if (empty($visits)): ?>
  <div class="empty">
    <i class="bi bi-calendar-x"></i>
    <div style="font-size:.95rem;font-weight:600;color:#e6edf3;margin-bottom:.35rem">No visits found</div>
    <div style="font-size:.82rem">Adjust filters or click <strong>Schedule Visit</strong> to add one.</div>
  </div>
  <?php else: ?>
  <div class="table-responsive">
  <table class="t">
    <thead>
      <tr>
        <th>#</th>
        <th>Visitor</th>
        <th>Inmate</th>
        <?php if ($isSA): ?><th>Facility</th><?php endif; ?>
        <th>Type</th>
        <th>Visit Date &amp; Time</th>
        <th>Duration</th>
        <th>Room</th>
        <th>Status</th>
        <th style="text-align:right">Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($visits as $v):
      $stClr = ['PENDING'=>'chip-pending','APPROVED'=>'chip-approved','REJECTED'=>'chip-rejected'][$v['approval_status']] ?? 'chip-pending';
      $tyClr = ['PERSONAL'=>'chip-personal','LEGAL'=>'chip-legal','OFFICIAL'=>'chip-official'][$v['visit_type']] ?? 'chip-personal';
      $isToday = $v['visit_date'] && date('Y-m-d', strtotime($v['visit_date'])) === date('Y-m-d');
    ?>
    <tr>
      <td style="color:#8b949e;font-size:.76rem"><?php echo $v['id']; ?></td>
      <td>
        <div style="display:flex;align-items:center;gap:8px">
          <div class="av av-green"><?php echo strtoupper(substr($v['vis_first'],0,1).substr($v['vis_last'],0,1)); ?></div>
          <div>
            <div style="font-weight:600;font-size:.84rem"><?php echo htmlspecialchars($v['vis_first'].' '.$v['vis_last']); ?></div>
            <div style="font-size:.72rem;color:#8b949e"><?php echo htmlspecialchars($v['vis_rel'] ?: '—'); ?></div>
          </div>
        </div>
      </td>
      <td>
        <div style="display:flex;align-items:center;gap:8px">
          <div class="av"><?php echo strtoupper(substr($v['inm_first'],0,1).substr($v['inm_last'],0,1)); ?></div>
          <div>
            <div style="font-weight:600;font-size:.84rem"><?php echo htmlspecialchars($v['inm_first'].' '.$v['inm_last']); ?></div>
            <div style="font-size:.72rem;color:#8b949e"><?php echo htmlspecialchars($v['inm_num']); ?></div>
          </div>
        </div>
      </td>
      <?php if ($isSA): ?>
      <td style="font-size:.78rem;color:#8b949e"><?php echo htmlspecialchars($v['facility_name']); ?></td>
      <?php endif; ?>
      <td><span class="chip <?php echo $tyClr; ?>"><?php echo ucfirst(strtolower($v['visit_type'])); ?></span></td>
      <td style="font-size:.82rem;white-space:nowrap">
        <?php if ($v['visit_date']): ?>
          <?php echo date('M d, Y', strtotime($v['visit_date'])); ?>
          <div style="font-size:.72rem;color:#8b949e"><?php echo date('H:i', strtotime($v['visit_date'])); ?></div>
          <?php if ($isToday): ?><span class="today-badge"><i class="bi bi-circle-fill" style="font-size:.4rem"></i> Today</span><?php endif; ?>
        <?php else: ?>—<?php endif; ?>
      </td>
      <td style="font-size:.82rem;color:#8b949e"><?php echo $v['duration_minutes'] ? $v['duration_minutes'].' min' : '—'; ?></td>
      <td style="font-size:.82rem;color:#8b949e"><?php echo htmlspecialchars($v['visit_room'] ?: '—'); ?></td>
      <td><span class="chip <?php echo $stClr; ?>"><?php echo ucfirst(strtolower($v['approval_status'])); ?></span></td>
      <td style="text-align:right;white-space:nowrap">
        <button class="btn-act btn-view" onclick='viewVisit(<?php echo json_encode($v); ?>)'><i class="bi bi-eye"></i></button>
        <?php if ($v['approval_status'] === 'PENDING'): ?>
          <?php if ($canApprove): ?>
          <button class="btn-act btn-approve" onclick="doApprove(<?php echo $v['id']; ?>)"><i class="bi bi-check-lg"></i> Approve</button>
          <button class="btn-act btn-reject"  onclick="openReject(<?php echo $v['id']; ?>)"><i class="bi bi-x-lg"></i> Reject</button>
          <?php endif; ?>
          <?php if ($canEdit): ?>
          <button class="btn-act btn-del" onclick="doDelete(<?php echo $v['id']; ?>)" title="Cancel"><i class="bi bi-trash"></i></button>
          <?php endif; ?>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>

<!-- ════════════════════════════════════════════════
     MODAL: SCHEDULE VISIT
     ════════════════════════════════════════════════ -->
<div class="mbk" id="createModal">
  <div class="mbox wide">
    <div class="mhead">
      <h3><i class="bi bi-calendar-plus" style="color:#388bfd"></i> Schedule a Visit</h3>
      <button class="btn-x" onclick="closeM('createModal')"><i class="bi bi-x-lg"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="create">
      <div class="mbody">

        <div class="f-sec">
          <div class="f-sec-title"><i class="bi bi-person-badge"></i> Inmate Selection</div>
          <div class="fg-row">
            <div class="fg">
              <label class="fg-label">Select Inmate <span class="req">*</span></label>
              <select name="inmate_id" class="fi" required>
                <option value="">— Choose inmate —</option>
                <?php foreach ($inmateList as $im): ?>
                <option value="<?php echo $im['id']; ?>">
                  <?php echo htmlspecialchars($im['first_name'].' '.$im['last_name'].' ('.$im['inmate_id'].')'); ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php if ($isSA): ?>
            <div class="fg">
              <label class="fg-label">Facility</label>
              <select name="facility_id" class="fi">
                <?php foreach ($allFacilities as $af): ?>
                <option value="<?php echo $af['id']; ?>"><?php echo htmlspecialchars($af['name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="f-sec">
          <div class="f-sec-title"><i class="bi bi-person-check"></i> Visitor Information</div>
          <div class="fg-row two">
            <div class="fg">
              <label class="fg-label">First Name <span class="req">*</span></label>
              <input type="text" name="visitor_first" class="fi" placeholder="First name" required>
            </div>
            <div class="fg">
              <label class="fg-label">Last Name <span class="req">*</span></label>
              <input type="text" name="visitor_last" class="fi" placeholder="Last name" required>
            </div>
          </div>
          <div class="fg-row three">
            <div class="fg">
              <label class="fg-label">National ID</label>
              <input type="text" name="visitor_nid" class="fi" placeholder="ID number">
            </div>
            <div class="fg">
              <label class="fg-label">Phone</label>
              <input type="tel" name="visitor_phone" class="fi" placeholder="+1 000 000 0000">
            </div>
            <div class="fg">
              <label class="fg-label">Relationship</label>
              <select name="visitor_rel" class="fi">
                <option value="">— Select —</option>
                <?php foreach (['Spouse','Parent','Child','Sibling','Friend','Lawyer','Other'] as $r): ?>
                <option value="<?php echo $r; ?>"><?php echo $r; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="fg-row">
            <div class="fg">
              <label class="fg-label">Email</label>
              <input type="email" name="visitor_email" class="fi" placeholder="visitor@example.com">
            </div>
          </div>
        </div>

        <div class="f-sec">
          <div class="f-sec-title"><i class="bi bi-calendar3"></i> Visit Details</div>
          <div class="fg-row three">
            <div class="fg">
              <label class="fg-label">Visit Type <span class="req">*</span></label>
              <select name="visit_type" class="fi" required>
                <option value="PERSONAL">Personal</option>
                <option value="LEGAL">Legal</option>
                <option value="OFFICIAL">Official</option>
              </select>
            </div>
            <div class="fg">
              <label class="fg-label">Date &amp; Time <span class="req">*</span></label>
              <input type="datetime-local" name="visit_date" class="fi" required min="<?php echo date('Y-m-d\TH:i'); ?>">
            </div>
            <div class="fg">
              <label class="fg-label">Duration (min)</label>
              <input type="number" name="duration" class="fi" value="60" min="15" max="480" step="15">
            </div>
          </div>
          <div class="fg-row two">
            <div class="fg">
              <label class="fg-label">Visit Room</label>
              <input type="text" name="visit_room" class="fi" placeholder="e.g. Room A">
            </div>
            <div class="fg">
              <label class="fg-label">Notes</label>
              <input type="text" name="notes" class="fi" placeholder="Optional…">
            </div>
          </div>
        </div>

        <div style="background:rgba(56,139,253,.08);border:1px solid rgba(56,139,253,.2);border-radius:8px;padding:.65rem .9rem;font-size:.79rem;color:#58a6ff;display:flex;align-items:flex-start;gap:.5rem">
          <i class="bi bi-info-circle-fill" style="margin-top:1px;flex-shrink:0"></i>
          Visit will be <strong>PENDING</strong> until approved by a Facility Administrator.
        </div>
      </div>
      <div class="mfoot">
        <button type="button" class="btn-act btn-view" onclick="closeM('createModal')">Cancel</button>
        <button type="submit" class="btn-primary-sm"><i class="bi bi-calendar-check"></i> Schedule Visit</button>
      </div>
    </form>
  </div>
</div>

<!-- ════════════════════════════════════════════════
     MODAL: VIEW DETAILS
     ════════════════════════════════════════════════ -->
<div class="mbk" id="viewModal">
  <div class="mbox wide">
    <div class="mhead">
      <h3><i class="bi bi-info-circle" style="color:#388bfd"></i> Visit Details</h3>
      <button class="btn-x" onclick="closeM('viewModal')"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="mbody" id="viewBody"></div>
    <div class="mfoot">
      <button class="btn-act btn-view" onclick="closeM('viewModal')">Close</button>
    </div>
  </div>
</div>

<!-- ════════════════════════════════════════════════
     MODAL: REJECT
     ════════════════════════════════════════════════ -->
<div class="mbk" id="rejectModal">
  <div class="mbox" style="max-width:420px">
    <div class="mhead">
      <h3><i class="bi bi-x-circle" style="color:#f85149"></i> Reject Visit Request</h3>
      <button class="btn-x" onclick="closeM('rejectModal')"><i class="bi bi-x-lg"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="reject">
      <input type="hidden" name="visit_id" id="rejectVid">
      <div class="mbody">
        <div class="fg">
          <label class="fg-label">Reason for Rejection <span class="req">*</span></label>
          <textarea name="reject_notes" class="fi" rows="4" required placeholder="Provide reason…"></textarea>
        </div>
      </div>
      <div class="mfoot">
        <button type="button" class="btn-act btn-view" onclick="closeM('rejectModal')">Cancel</button>
        <button type="submit" class="btn-act btn-reject" style="padding:.5rem 1rem;font-size:.85rem"><i class="bi bi-x-lg"></i> Confirm Rejection</button>
      </div>
    </form>
  </div>
</div>

<!-- Hidden utility forms -->
<form method="POST" id="approveForm" style="display:none">
  <input type="hidden" name="action" value="approve">
  <input type="hidden" name="visit_id" id="approveVid">
</form>
<form method="POST" id="deleteForm" style="display:none">
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="visit_id" id="deleteVid">
</form>

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

function esc(s){
  var d=document.createElement('div');
  d.appendChild(document.createTextNode(String(s??'')));
  return d.innerHTML;
}
function fmtDT(s){
  try {
    var d=new Date(s);
    return d.toLocaleDateString('en-GB',{day:'numeric',month:'short',year:'numeric'})
          +' '+d.toLocaleTimeString('en-GB',{hour:'2-digit',minute:'2-digit'});
  } catch(e){ return s; }
}

function viewVisit(v) {
  var stClr={PENDING:'#f39c12',APPROVED:'#3fb950',REJECTED:'#f85149'};
  var tyClr={PERSONAL:'#58a6ff',LEGAL:'#bb8fce',OFFICIAL:'#1abc9c'};

  var html='';
  html+='<div class="vis-card"><div class="av av-green">'
       +esc((v.vis_first||'?')[0]).toUpperCase()+esc((v.vis_last||'?')[0]).toUpperCase()
       +'</div><div><div class="vc-name">'+esc(v.vis_first+' '+v.vis_last)+'</div>'
       +'<div class="vc-meta">'+(v.vis_rel||'—')+' &nbsp;·&nbsp; '+(v.vis_phone||'No phone')+'</div></div></div>';

  html+='<div class="vis-card" style="margin-bottom:1rem"><div class="av">'
       +esc((v.inm_first||'?')[0]).toUpperCase()+esc((v.inm_last||'?')[0]).toUpperCase()
       +'</div><div><div class="vc-name">'+esc(v.inm_first+' '+v.inm_last)+'</div>'
       +'<div class="vc-meta">Inmate # '+esc(v.inm_num)+'</div></div></div>';

  var rows=[
    ['Visit Type',   '<span style="color:'+(tyClr[v.visit_type]||'#8b949e')+';font-weight:700">'+esc(v.visit_type)+'</span>'],
    ['Status',       '<span style="color:'+(stClr[v.approval_status]||'#8b949e')+';font-weight:700">'+esc(v.approval_status)+'</span>'],
    ['Date & Time',  v.visit_date ? fmtDT(v.visit_date) : '—'],
    ['Duration',     v.duration_minutes ? v.duration_minutes+' minutes' : '—'],
    ['Room',         esc(v.visit_room||'—')],
    ['Facility',     esc(v.facility_name||'—')],
    ['Notes',        esc(v.notes||'—')],
    ['Approved By',  v.approver_name ? esc(v.approver_name) : '—'],
    ['Scheduled On', v.created_at ? fmtDT(v.created_at) : '—'],
  ];
  html+='<div>';
  rows.forEach(function(r){
    html+='<div class="dr"><span class="dk">'+r[0]+'</span><span class="dv">'+r[1]+'</span></div>';
  });
  html+='</div>';

  document.getElementById('viewBody').innerHTML=html;
  openM('viewModal');
}

function doApprove(vid){
  if(!confirm('Approve this visit request?')) return;
  document.getElementById('approveVid').value=vid;
  document.getElementById('approveForm').submit();
}
function openReject(vid){
  document.getElementById('rejectVid').value=vid;
  openM('rejectModal');
}
function doDelete(vid){
  if(!confirm('Cancel this visit request? This cannot be undone.')) return;
  document.getElementById('deleteVid').value=vid;
  document.getElementById('deleteForm').submit();
}
</script>

<?php require_once '../../includes/footer.php'; ?>
