<?php
$pageTitle = 'Rehabilitation Programs';
require_once '../../includes/header.php';
requireAuth();
verifyPermission('view', 'programs');

$fid  = getCurrentFacility();
$isSA = isSuperAdmin();
$uid  = getCurrentUserId();

$facCl    = $isSA ? "rp.deleted_at IS NULL" : "rp.deleted_at IS NULL AND rp.facility_id=$fid";
$facPlain = $isSA ? "deleted_at IS NULL" : "deleted_at IS NULL AND facility_id=$fid";

/* ══ POST HANDLER ══ */
$flash = ''; $flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* ── CREATE ── */
    if ($action === 'create') {
        verifyPermission('create', 'programs');
        $name     = trim($_POST['name']         ?? '');
        $desc     = trim($_POST['description']  ?? '');
        $type     = trim($_POST['program_type'] ?? '');
        $capacity = trim($_POST['capacity']     ?? '');
        $start    = trim($_POST['start_date']   ?? '');
        $end      = trim($_POST['end_date']     ?? '');
        $status   = trim($_POST['status']       ?? 'ACTIVE');
        $instrId  = (int)($_POST['instructor_id'] ?? 0);
        $pFid     = $isSA ? (int)($_POST['facility_id'] ?? $fid) : $fid;

        $validTypes   = ['EDUCATION','VOCATIONAL','COUNSELING','SKILLS_TRAINING','SPORTS'];
        $validStatus  = ['ACTIVE','INACTIVE','COMPLETED'];
        $errs = [];
        if (!$name)                           $errs[] = 'Program name is required.';
        if (!in_array($type,$validTypes))     $errs[] = 'Invalid program type.';
        if (!in_array($status,$validStatus))  $errs[] = 'Invalid status.';
        if ($capacity !== '' && (!ctype_digit($capacity) || (int)$capacity < 1))
                                              $errs[] = 'Capacity must be a positive number.';
        if (!$start)                          $errs[] = 'Start date is required.';
        if (!$errs) {
            $dup = fetchOne("SELECT id FROM rehabilitation_programs WHERE name=? AND facility_id=? AND deleted_at IS NULL", [$name,$pFid], 'si');
            if ($dup) $errs[] = 'A program with that name already exists in this facility.';
        }
        if (!$errs) {
            executeQuery(
                "INSERT INTO rehabilitation_programs (facility_id,name,description,program_type,start_date,end_date,capacity,instructor_id,status,created_at,updated_at)
                 VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())",
                [$pFid, $name, $desc ?: null, $type,
                 $start, $end ?: null, $capacity !== '' ? (int)$capacity : null,
                 $instrId ?: null, $status],
                'issssssis'
            );
            $flash = "Program '$name' created successfully.";
        } else { $flash = implode(' ',$errs); $flashType = 'error'; }
    }

    /* ── EDIT ── */
    if ($action === 'edit') {
        verifyPermission('edit', 'programs');
        $progId   = (int)($_POST['prog_id']     ?? 0);
        $name     = trim($_POST['name']         ?? '');
        $desc     = trim($_POST['description']  ?? '');
        $type     = trim($_POST['program_type'] ?? '');
        $capacity = trim($_POST['capacity']     ?? '');
        $start    = trim($_POST['start_date']   ?? '');
        $end      = trim($_POST['end_date']     ?? '');
        $status   = trim($_POST['status']       ?? 'ACTIVE');
        $instrId  = (int)($_POST['instructor_id'] ?? 0);

        $validTypes   = ['EDUCATION','VOCATIONAL','COUNSELING','SKILLS_TRAINING','SPORTS'];
        $validStatus  = ['ACTIVE','INACTIVE','COMPLETED'];
        $errs = [];
        if (!$progId)                         $errs[] = 'Program not found.';
        if (!$name)                           $errs[] = 'Program name is required.';
        if (!in_array($type,$validTypes))     $errs[] = 'Invalid program type.';
        if (!in_array($status,$validStatus))  $errs[] = 'Invalid status.';
        if (!$start)                          $errs[] = 'Start date is required.';
        if (!$errs) {
            $prog = fetchOne("SELECT facility_id FROM rehabilitation_programs WHERE id=? AND deleted_at IS NULL", [$progId], 'i');
            if (!$prog || (!$isSA && $prog['facility_id'] != $fid)) $errs[] = 'Program not found.';
        }
        if (!$errs) {
            executeQuery(
                "UPDATE rehabilitation_programs SET name=?,description=?,program_type=?,start_date=?,end_date=?,capacity=?,instructor_id=?,status=?,updated_at=NOW() WHERE id=?",
                [$name, $desc ?: null, $type, $start, $end ?: null,
                 $capacity !== '' ? (int)$capacity : null, $instrId ?: null, $status, $progId],
                'sssssisi'
            );
            $flash = "Program updated successfully.";
        } else { $flash = implode(' ',$errs); $flashType = 'error'; }
    }

    /* ── DELETE ── */
    if ($action === 'delete') {
        verifyPermission('delete', 'programs');
        $progId = (int)($_POST['prog_id'] ?? 0);
        $errs   = [];
        if (!$progId) $errs[] = 'Program not found.';
        if (!$errs) {
            $prog = fetchOne("SELECT facility_id,name FROM rehabilitation_programs WHERE id=? AND deleted_at IS NULL", [$progId], 'i');
            if (!$prog || (!$isSA && $prog['facility_id'] != $fid)) $errs[] = 'Program not found.';
        }
        if (!$errs) {
            $enrolled = fetchOne("SELECT COUNT(*) cnt FROM program_enrollment WHERE program_id=? AND status IN ('ENROLLED','ACTIVE') AND deleted_at IS NULL", [$progId], 'i');
            if (($enrolled['cnt'] ?? 0) > 0) $errs[] = 'Cannot delete: program has active enrollments.';
        }
        if (!$errs) {
            executeQuery("UPDATE rehabilitation_programs SET deleted_at=NOW() WHERE id=?", [$progId], 'i');
            $flash = "Program deleted.";
        } else { $flash = implode(' ',$errs); $flashType = 'error'; }
    }

    /* ── ENROLL ── */
    if ($action === 'enroll') {
        verifyPermission('create', 'programs');
        $progId   = (int)($_POST['prog_id']  ?? 0);
        $inmateId = (int)($_POST['inmate_id'] ?? 0);
        $enrollDate = trim($_POST['enrollment_date'] ?? date('Y-m-d'));
        $errs = [];
        if (!$progId || !$inmateId) $errs[] = 'Program and inmate are required.';
        if (!$errs) {
            $prog   = fetchOne("SELECT id,capacity,facility_id,name FROM rehabilitation_programs WHERE id=? AND deleted_at IS NULL AND status='ACTIVE'", [$progId], 'i');
            $inmate = fetchOne("SELECT id,facility_id FROM inmates WHERE id=? AND deleted_at IS NULL", [$inmateId], 'i');
            if (!$prog)   $errs[] = 'Active program not found.';
            if (!$inmate) $errs[] = 'Inmate not found.';
            if ($prog && $inmate && !$isSA && $prog['facility_id'] != $fid) $errs[] = 'Access denied.';
            if (!$errs) {
                $already = fetchOne("SELECT id FROM program_enrollment WHERE program_id=? AND inmate_id=? AND status IN ('ENROLLED','ACTIVE') AND deleted_at IS NULL", [$progId,$inmateId], 'ii');
                if ($already) $errs[] = 'Inmate is already enrolled in this program.';
            }
            if (!$errs && $prog['capacity']) {
                $cnt = fetchOne("SELECT COUNT(*) cnt FROM program_enrollment WHERE program_id=? AND status IN ('ENROLLED','ACTIVE') AND deleted_at IS NULL", [$progId], 'i');
                if (($cnt['cnt']??0) >= $prog['capacity']) $errs[] = 'Program is at full capacity.';
            }
        }
        if (!$errs) {
            $eFid = $isSA ? $prog['facility_id'] : $fid;
            executeQuery(
                "INSERT INTO program_enrollment (facility_id,program_id,inmate_id,enrollment_date,status,progress_percentage,created_at,updated_at)
                 VALUES (?,?,?,?,'ENROLLED',0,NOW(),NOW())",
                [$eFid,$progId,$inmateId,$enrollDate], 'iiis'
            );
            $flash = 'Inmate enrolled successfully.';
        } else { $flash = implode(' ',$errs); $flashType = 'error'; }
    }

    if (!$flash || $flashType === 'success') {
        $redir = '?';
        if ($_POST['tab'] ?? '') $redir .= 'tab='.urlencode($_POST['tab']).'&';
        if ($flash) $redir .= 'msg='.urlencode($flash).'&';
        header('Location: '.$redir); exit;
    }
}
if (!$flash && !empty($_GET['msg'])) { $flash = $_GET['msg']; $flashType = 'success'; }

/* ══ DATA ══ */
$activeTab  = $_GET['tab']    ?? 'ALL';
$statusFilt = $_GET['status'] ?? 'ALL';
$search     = trim($_GET['q'] ?? '');

$validTypes  = ['ALL','EDUCATION','VOCATIONAL','COUNSELING','SKILLS_TRAINING','SPORTS'];
$validStatus = ['ALL','ACTIVE','INACTIVE','COMPLETED'];
if (!in_array($activeTab,$validTypes))   $activeTab  = 'ALL';
if (!in_array($statusFilt,$validStatus)) $statusFilt = 'ALL';

$typeCl   = $activeTab   !== 'ALL' ? " AND rp.program_type='$activeTab'" : '';
$statCl   = $statusFilt  !== 'ALL' ? " AND rp.status='$statusFilt'" : '';
$srchCl   = '';
if ($search) { $esc = addslashes($search); $srchCl = " AND rp.name LIKE '%$esc%'"; }

$programs = fetchAll(
    "SELECT rp.*,
            f.name facility_name,
            u.username instructor_name,
            COUNT(pe.id) enrolled
     FROM rehabilitation_programs rp
     JOIN facilities f ON rp.facility_id=f.id
     LEFT JOIN users u ON rp.instructor_id=u.id
     LEFT JOIN program_enrollment pe ON rp.id=pe.program_id AND pe.status IN ('ENROLLED','ACTIVE') AND pe.deleted_at IS NULL
     WHERE $facCl$typeCl$statCl$srchCl
     GROUP BY rp.id
     ORDER BY rp.status='ACTIVE' DESC, rp.start_date DESC",
    [], ''
);

/* KPIs */
$kpiRows = fetchAll("SELECT status,COUNT(*) cnt FROM rehabilitation_programs WHERE $facPlain GROUP BY status", [], '');
$km = []; foreach ($kpiRows as $r) $km[$r['status']] = $r['cnt'];
$totalProgs = array_sum($km);
$totEnrolled = fetchOne("SELECT COUNT(*) cnt FROM program_enrollment pe JOIN rehabilitation_programs rp ON pe.program_id=rp.id WHERE pe.deleted_at IS NULL AND pe.status IN ('ENROLLED','ACTIVE') AND $facCl", [], '')['cnt'] ?? 0;
$totalCap = fetchOne("SELECT COALESCE(SUM(capacity),0) cap FROM rehabilitation_programs WHERE $facPlain AND status='ACTIVE'", [], '')['cap'] ?? 0;
$availSpots = max(0, (int)$totalCap - (int)$totEnrolled);

/* Tab counts */
$tabCounts = ['ALL' => $totalProgs];
foreach (['EDUCATION','VOCATIONAL','COUNSELING','SKILLS_TRAINING','SPORTS'] as $t) {
    $r = fetchOne("SELECT COUNT(*) cnt FROM rehabilitation_programs WHERE $facPlain AND program_type='$t'", [], '');
    $tabCounts[$t] = $r['cnt'] ?? 0;
}

/* For enroll modal: active inmates in facility */
$inmates = fetchAll(
    "SELECT i.id, i.inmate_id, i.first_name, i.last_name
     FROM inmates i WHERE i.deleted_at IS NULL " . ($isSA ? '' : "AND i.facility_id=$fid") . "
     AND i.status NOT IN ('RELEASED','TRANSFERRED','DECEASED')
     ORDER BY i.last_name, i.first_name LIMIT 200",
    [], ''
);

/* For create/edit: facilities (SA) */
$facilities = $isSA ? fetchAll("SELECT id,name FROM facilities WHERE deleted_at IS NULL ORDER BY name", [], '') : [];

$canEdit   = hasPermission('edit','programs');
$canCreate = hasPermission('create','programs');
$canDelete = hasPermission('delete','programs');

$typeCfg = [
    'EDUCATION'      => ['#388bfd','rgba(56,139,253,.15)','bi-book-fill','Education'],
    'VOCATIONAL'     => ['#f39c12','rgba(243,156,18,.15)','bi-tools','Vocational'],
    'COUNSELING'     => ['#bb8fce','rgba(187,143,206,.15)','bi-chat-heart-fill','Counseling'],
    'SKILLS_TRAINING'=> ['#3fb950','rgba(63,185,80,.15)','bi-lightning-fill','Skills Training'],
    'SPORTS'         => ['#f85149','rgba(248,81,73,.15)','bi-trophy-fill','Sports'],
];
$statusCfg = [
    'ACTIVE'    => ['#3fb950','rgba(63,185,80,.15)','check-circle-fill'],
    'INACTIVE'  => ['#8b949e','rgba(139,148,158,.15)','dash-circle-fill'],
    'COMPLETED' => ['#388bfd','rgba(56,139,253,.15)','check2-circle'],
];
?>
<style>
:root{--sur:#161b22;--bdr:#21262d;--bg:#0d1117;--txt:#e6edf3;--mut:#8b949e}
.ph{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:.75rem}
.ph h1{font-size:1.35rem;font-weight:700;color:var(--txt);margin:0}
.bc{font-size:.78rem;color:#6e7681;display:flex;align-items:center;gap:.3rem;margin-bottom:.3rem}
.bc a{color:#388bfd;text-decoration:none}.bc a:hover{text-decoration:underline}
.ph-actions{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap}
.btn-ghost{display:inline-flex;align-items:center;gap:.4rem;padding:.42rem .85rem;border-radius:9px;border:1px solid #30363d;background:transparent;color:#c9d1d9;font-size:.82rem;font-weight:600;cursor:pointer;text-decoration:none;transition:all .18s}
.btn-ghost:hover{background:#21262d;border-color:#388bfd;color:#388bfd}
.btn-primary-sm{display:inline-flex;align-items:center;gap:.4rem;padding:.42rem .9rem;border-radius:9px;border:none;background:#1f6feb;color:#fff;font-size:.82rem;font-weight:600;cursor:pointer;transition:all .18s}
.btn-primary-sm:hover{background:#388bfd;transform:translateY(-1px)}

/* KPI */
.kpi-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:1rem;margin-bottom:1.5rem}
@media(max-width:1100px){.kpi-grid{grid-template-columns:repeat(3,1fr)}}
@media(max-width:640px){.kpi-grid{grid-template-columns:repeat(2,1fr)}}
.kpi{background:var(--sur);border:1px solid var(--bdr);border-radius:14px;padding:1rem 1.1rem;transition:transform .18s,box-shadow .18s}
.kpi:hover{transform:translateY(-2px);box-shadow:0 6px 24px rgba(0,0,0,.35)}
.kpi-ico{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:.9rem;margin-bottom:.65rem}
.kpi-val{font-size:1.5rem;font-weight:800;color:var(--txt);line-height:1;margin-bottom:.2rem}
.kpi-lbl{font-size:.7rem;font-weight:600;color:var(--mut);text-transform:uppercase;letter-spacing:.05em}

/* Tabs */
.tab-strip{display:flex;gap:.35rem;margin-bottom:1.1rem;flex-wrap:wrap;padding:.4rem .45rem;background:var(--sur);border:1px solid var(--bdr);border-radius:12px;width:fit-content}
.tab{padding:.34rem .78rem;border-radius:7px;font-size:.78rem;font-weight:600;cursor:pointer;color:var(--mut);background:transparent;border:none;display:flex;align-items:center;gap:.38rem;transition:all .18s;text-decoration:none;white-space:nowrap}
.tab:hover,.tab.active{background:#21262d;color:var(--txt)}
.tbadge{padding:1px 6px;border-radius:20px;font-size:.65rem;font-weight:700;background:rgba(139,148,158,.15)}

/* Toolbar */
.toolbar{display:flex;align-items:center;gap:.6rem;margin-bottom:1.25rem;flex-wrap:wrap}
.search-wrap{position:relative;flex:1;min-width:160px;max-width:300px}
.search-wrap i{position:absolute;left:.7rem;top:50%;transform:translateY(-50%);color:#484f58;font-size:.8rem;pointer-events:none}
.search-wrap input{width:100%;background:#161b22;border:1px solid #30363d;color:var(--txt);padding:.45rem .8rem .45rem 2rem;border-radius:8px;font-size:.82rem;outline:none;transition:border .2s;box-sizing:border-box}
.search-wrap input::placeholder{color:#484f58}
.search-wrap input:focus{border-color:#388bfd;box-shadow:0 0 0 3px rgba(56,139,253,.12)}
.filter-sel{background:#161b22;border:1px solid #30363d;color:var(--txt);padding:.44rem .75rem;border-radius:8px;font-size:.82rem;outline:none;cursor:pointer;transition:border .2s}
.filter-sel:focus{border-color:#388bfd}
.count-lbl{font-size:.78rem;color:#8b949e;margin-left:auto;white-space:nowrap}

/* Program cards grid */
.cards-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:1.1rem}
.prog-card{background:var(--sur);border:1px solid var(--bdr);border-radius:16px;overflow:hidden;transition:transform .18s,box-shadow .18s,border-color .18s;display:flex;flex-direction:column}
.prog-card:hover{transform:translateY(-3px);box-shadow:0 8px 28px rgba(0,0,0,.4);border-color:#30363d}
.prog-card-top{padding:1.1rem 1.2rem .8rem;display:flex;align-items:flex-start;gap:.85rem}
.prog-type-ico{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0}
.prog-card-info{flex:1;min-width:0}
.prog-name{font-size:.95rem;font-weight:700;color:var(--txt);line-height:1.3;margin-bottom:.35rem;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}
.prog-type-lbl{font-size:.7rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.4rem}
.prog-desc{font-size:.78rem;color:#8b949e;line-height:1.5;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;min-height:2.3rem}
.prog-card-meta{padding:.65rem 1.2rem;border-top:1px solid #1c2128;display:flex;align-items:center;gap:1.1rem;flex-wrap:wrap}
.meta-item{display:flex;align-items:center;gap:.3rem;font-size:.75rem;color:#8b949e}
.meta-item i{font-size:.78rem}
.meta-item strong{color:#c9d1d9;font-weight:600}
.prog-card-cap{padding:.65rem 1.2rem;border-top:1px solid #1c2128}
.cap-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:.3rem}
.cap-label{font-size:.72rem;color:#8b949e;font-weight:600}
.cap-num{font-size:.78rem;font-weight:700;color:#e6edf3}
.cap-bar{height:6px;background:#21262d;border-radius:3px;overflow:hidden}
.cap-fill{height:100%;border-radius:3px;transition:width .4s ease}
.prog-card-actions{padding:.75rem 1.2rem;border-top:1px solid #1c2128;display:flex;align-items:center;gap:.45rem;margin-top:auto}
.ic-btn{height:30px;padding:0 .7rem;border-radius:7px;border:1px solid #30363d;background:transparent;color:#8b949e;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:600;gap:.3rem;transition:all .18s;white-space:nowrap}
.ic-btn:hover{background:#21262d;color:#e6edf3}
.ic-btn.enroll:hover{border-color:#3fb950;color:#3fb950;background:rgba(63,185,80,.08)}
.ic-btn.edit-btn:hover{border-color:#388bfd;color:#388bfd;background:rgba(56,139,253,.08)}
.ic-btn.del-btn:hover{border-color:#f85149;color:#f85149;background:rgba(248,81,73,.08)}
.status-chip{display:inline-flex;align-items:center;gap:.28rem;padding:2px 9px;border-radius:20px;font-size:.68rem;font-weight:700}

/* Empty */
.empty-state{text-align:center;padding:4rem 1rem;color:#8b949e;background:var(--sur);border:1px solid var(--bdr);border-radius:16px}
.empty-state i{font-size:2.8rem;display:block;margin-bottom:.85rem;opacity:.3}
.empty-state p{font-size:.875rem;margin:.3rem 0 0}

/* Flash */
.flash{display:flex;align-items:center;gap:.6rem;padding:.75rem 1.1rem;border-radius:10px;margin-bottom:1.25rem;font-size:.875rem;font-weight:500}
.flash.success{background:rgba(63,185,80,.12);border:1px solid rgba(63,185,80,.3);color:#3fb950}
.flash.error{background:rgba(248,81,73,.12);border:1px solid rgba(248,81,73,.3);color:#f85149}

/* Modals */
.modal-ov{position:fixed;inset:0;background:rgba(1,4,9,.78);z-index:1000;display:flex;align-items:center;justify-content:center;padding:1rem;opacity:0;pointer-events:none;transition:opacity .22s}
.modal-ov.open{opacity:1;pointer-events:auto}
.modal-box{background:#161b22;border:1px solid #30363d;border-radius:18px;width:100%;max-width:540px;max-height:90vh;overflow-y:auto;transform:translateY(20px);transition:transform .22s}
.modal-ov.open .modal-box{transform:translateY(0)}
.modal-hdr{display:flex;align-items:center;justify-content:space-between;padding:1.1rem 1.4rem;border-bottom:1px solid #21262d;position:sticky;top:0;background:#161b22;z-index:1}
.modal-hdr h2{margin:0;font-size:1rem;font-weight:700;color:#e6edf3;display:flex;align-items:center;gap:.5rem}
.modal-close{background:none;border:none;color:#8b949e;font-size:1.1rem;cursor:pointer;width:30px;height:30px;border-radius:7px;display:flex;align-items:center;justify-content:center;transition:all .18s}
.modal-close:hover{background:#21262d;color:#e6edf3}
.modal-body{padding:1.4rem}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:.9rem}
.form-group{margin-bottom:.9rem}
.form-group label{display:block;font-size:.74rem;font-weight:600;color:#8b949e;margin-bottom:.38rem;text-transform:uppercase;letter-spacing:.04em}
.form-group input,.form-group select,.form-group textarea{width:100%;background:#0d1117;border:1px solid #30363d;color:#e6edf3;padding:.52rem .8rem;border-radius:9px;font-size:.875rem;outline:none;transition:border .2s,box-shadow .2s;box-sizing:border-box;resize:none}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{border-color:#388bfd;box-shadow:0 0 0 3px rgba(56,139,253,.12)}
.form-group input::placeholder,.form-group textarea::placeholder{color:#484f58}
.modal-footer{display:flex;justify-content:flex-end;gap:.6rem;padding-top:1rem;border-top:1px solid #21262d;margin-top:.5rem;flex-wrap:wrap}
.del-warning{background:rgba(248,81,73,.08);border:1px solid rgba(248,81,73,.25);border-radius:10px;padding:.85rem 1rem;color:#f85149;font-size:.84rem;margin-bottom:1rem;display:flex;align-items:flex-start;gap:.6rem}
</style>

<!-- PAGE HEADER -->
<div class="ph">
  <div>
    <div class="bc">
      <a href="<?php echo APP_URL; ?>/dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
      <i class="bi bi-chevron-right" style="font-size:.6rem"></i>
      <span>Programs</span>
    </div>
    <h1><i class="bi bi-mortarboard-fill" style="color:#388bfd;margin-right:.45rem"></i>Rehabilitation Programs</h1>
  </div>
  <div class="ph-actions">
    <?php if ($canCreate): ?>
    <button class="btn-primary-sm" onclick="openCreate()">
      <i class="bi bi-plus-lg"></i> New Program
    </button>
    <?php endif; ?>
  </div>
</div>

<?php if ($flash): ?>
<div class="flash <?php echo $flashType; ?>">
  <i class="bi bi-<?php echo $flashType==='success'?'check-circle-fill':'exclamation-triangle-fill'; ?>"></i>
  <span><?php echo htmlspecialchars($flash); ?></span>
</div>
<?php endif; ?>

<!-- KPI GRID -->
<div class="kpi-grid">
  <div class="kpi" style="border-top:3px solid #388bfd">
    <div class="kpi-ico" style="background:rgba(56,139,253,.15);color:#388bfd"><i class="bi bi-mortarboard-fill"></i></div>
    <div class="kpi-val"><?php echo $totalProgs; ?></div>
    <div class="kpi-lbl">Total Programs</div>
  </div>
  <div class="kpi" style="border-top:3px solid #3fb950">
    <div class="kpi-ico" style="background:rgba(63,185,80,.15);color:#3fb950"><i class="bi bi-check-circle-fill"></i></div>
    <div class="kpi-val"><?php echo $km['ACTIVE'] ?? 0; ?></div>
    <div class="kpi-lbl">Active</div>
  </div>
  <div class="kpi" style="border-top:3px solid #8b949e">
    <div class="kpi-ico" style="background:rgba(139,148,158,.15);color:#8b949e"><i class="bi bi-dash-circle-fill"></i></div>
    <div class="kpi-val"><?php echo $km['INACTIVE'] ?? 0; ?></div>
    <div class="kpi-lbl">Inactive</div>
  </div>
  <div class="kpi" style="border-top:3px solid #58a6ff">
    <div class="kpi-ico" style="background:rgba(88,166,255,.15);color:#58a6ff"><i class="bi bi-check2-circle"></i></div>
    <div class="kpi-val"><?php echo $km['COMPLETED'] ?? 0; ?></div>
    <div class="kpi-lbl">Completed</div>
  </div>
  <div class="kpi" style="border-top:3px solid #bb8fce">
    <div class="kpi-ico" style="background:rgba(187,143,206,.15);color:#bb8fce"><i class="bi bi-people-fill"></i></div>
    <div class="kpi-val"><?php echo $totEnrolled; ?></div>
    <div class="kpi-lbl">Enrolled</div>
  </div>
  <div class="kpi" style="border-top:3px solid #f39c12">
    <div class="kpi-ico" style="background:rgba(243,156,18,.15);color:#f39c12"><i class="bi bi-person-plus-fill"></i></div>
    <div class="kpi-val"><?php echo $availSpots; ?></div>
    <div class="kpi-lbl">Available Spots</div>
  </div>
</div>

<!-- TABS -->
<?php
$tabLabels = [
    'ALL'             => 'All',
    'EDUCATION'       => 'Education',
    'VOCATIONAL'      => 'Vocational',
    'COUNSELING'      => 'Counseling',
    'SKILLS_TRAINING' => 'Skills Training',
    'SPORTS'          => 'Sports',
];
$qStr = ($search ? '&q='.urlencode($search) : '').($statusFilt!=='ALL'?'&status='.urlencode($statusFilt):'');
?>
<div class="tab-strip">
<?php foreach ($tabLabels as $key => $label): ?>
  <a href="?tab=<?php echo $key.$qStr; ?>"
     class="tab <?php echo $activeTab===$key?'active':''; ?>">
    <?php echo $label; ?>
    <?php if ($tabCounts[$key] > 0): ?>
    <span class="tbadge"><?php echo $tabCounts[$key]; ?></span>
    <?php endif; ?>
  </a>
<?php endforeach; ?>
</div>

<!-- TOOLBAR -->
<form method="GET">
  <input type="hidden" name="tab" value="<?php echo htmlspecialchars($activeTab); ?>">
  <div class="toolbar">
    <div class="search-wrap">
      <i class="bi bi-search"></i>
      <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>"
             placeholder="Search programs…" onchange="this.form.submit()">
    </div>
    <select name="status" class="filter-sel" onchange="this.form.submit()">
      <option value="ALL" <?php echo $statusFilt==='ALL'?'selected':''; ?>>All Statuses</option>
      <option value="ACTIVE" <?php echo $statusFilt==='ACTIVE'?'selected':''; ?>>Active</option>
      <option value="INACTIVE" <?php echo $statusFilt==='INACTIVE'?'selected':''; ?>>Inactive</option>
      <option value="COMPLETED" <?php echo $statusFilt==='COMPLETED'?'selected':''; ?>>Completed</option>
    </select>
    <?php if ($search || $statusFilt!=='ALL'): ?>
    <a href="?tab=<?php echo urlencode($activeTab); ?>" class="btn-ghost" style="padding:.38rem .7rem">
      <i class="bi bi-x-lg"></i> Clear
    </a>
    <?php endif; ?>
    <span class="count-lbl"><?php echo count($programs); ?> program<?php echo count($programs)!=1?'s':''; ?></span>
  </div>
</form>

<!-- PROGRAM CARDS -->
<?php if (empty($programs)): ?>
<div class="empty-state">
  <i class="bi bi-mortarboard"></i>
  <p>No programs found<?php echo ($search||$statusFilt!=='ALL'||$activeTab!=='ALL') ? ' for the selected filters' : ''; ?>.</p>
  <?php if ($canCreate): ?>
  <button class="btn-primary-sm" onclick="openCreate()" style="margin-top:1rem">
    <i class="bi bi-plus-lg"></i> Create First Program
  </button>
  <?php endif; ?>
</div>
<?php else: ?>
<div class="cards-grid">
<?php foreach ($programs as $p):
  [$tc,$tbg,$ti,$tlabel] = $typeCfg[$p['program_type']] ?? ['#8b949e','rgba(139,148,158,.15)','bi-circle','Unknown'];
  [$sc,$sbg,$si] = $statusCfg[$p['status']] ?? ['#8b949e','rgba(139,148,158,.15)','circle'];
  $cap = (int)($p['capacity'] ?? 0);
  $enrolled = (int)$p['enrolled'];
  $fillPct = $cap > 0 ? min(100, round($enrolled / $cap * 100)) : 0;
  $fillClr = $fillPct >= 100 ? '#f85149' : ($fillPct >= 80 ? '#f39c12' : '#3fb950');
  $startFmt = $p['start_date'] ? date('M j, Y', strtotime($p['start_date'])) : '—';
  $endFmt   = $p['end_date']   ? date('M j, Y', strtotime($p['end_date']))   : 'Ongoing';
  $pJson = htmlspecialchars(json_encode([
    'id'           => $p['id'],
    'name'         => $p['name'],
    'description'  => $p['description'] ?? '',
    'program_type' => $p['program_type'],
    'status'       => $p['status'],
    'capacity'     => $p['capacity'],
    'start_date'   => $p['start_date'],
    'end_date'     => $p['end_date'] ?? '',
    'instructor_id'=> $p['instructor_id'] ?? '',
    'facility_id'  => $p['facility_id'],
  ]), ENT_QUOTES);
?>
<div class="prog-card">
  <!-- TOP -->
  <div class="prog-card-top">
    <div class="prog-type-ico" style="background:<?php echo $tbg; ?>;color:<?php echo $tc; ?>">
      <i class="bi <?php echo $ti; ?>"></i>
    </div>
    <div class="prog-card-info">
      <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;margin-bottom:.3rem">
        <span class="status-chip" style="color:<?php echo $sc; ?>;background:<?php echo $sbg; ?>">
          <i class="bi bi-<?php echo $si; ?>"></i><?php echo $p['status']; ?>
        </span>
        <?php if ($isSA): ?>
        <span style="font-size:.68rem;color:#8b949e"><?php echo htmlspecialchars($p['facility_name']); ?></span>
        <?php endif; ?>
      </div>
      <div class="prog-name"><?php echo htmlspecialchars($p['name']); ?></div>
      <div class="prog-type-lbl" style="color:<?php echo $tc; ?>"><?php echo $tlabel; ?></div>
    </div>
  </div>

  <?php if ($p['description']): ?>
  <div style="padding:0 1.2rem .7rem"><div class="prog-desc"><?php echo htmlspecialchars($p['description']); ?></div></div>
  <?php endif; ?>

  <!-- META -->
  <div class="prog-card-meta">
    <div class="meta-item">
      <i class="bi bi-calendar-event"></i>
      <span><strong><?php echo $startFmt; ?></strong></span>
    </div>
    <div class="meta-item">
      <i class="bi bi-calendar-check"></i>
      <span><strong><?php echo $endFmt; ?></strong></span>
    </div>
    <?php if ($p['instructor_name']): ?>
    <div class="meta-item">
      <i class="bi bi-person-badge"></i>
      <span><?php echo htmlspecialchars($p['instructor_name']); ?></span>
    </div>
    <?php endif; ?>
  </div>

  <!-- CAPACITY BAR -->
  <div class="prog-card-cap">
    <div class="cap-row">
      <span class="cap-label"><i class="bi bi-people"></i> Enrollment</span>
      <span class="cap-num"><?php echo $enrolled; ?><?php echo $cap > 0 ? ' / '.$cap : ''; ?></span>
    </div>
    <?php if ($cap > 0): ?>
    <div class="cap-bar">
      <div class="cap-fill" style="width:<?php echo $fillPct; ?>%;background:<?php echo $fillClr; ?>"></div>
    </div>
    <?php else: ?>
    <div style="font-size:.7rem;color:#484f58;margin-top:.15rem">No capacity limit set</div>
    <?php endif; ?>
  </div>

  <!-- ACTIONS -->
  <div class="prog-card-actions">
    <?php if ($canCreate && $p['status']==='ACTIVE'): ?>
    <button class="ic-btn enroll" onclick='openEnroll(<?php echo $p["id"]; ?>,"<?php echo htmlspecialchars(addslashes($p['name'])); ?>")' title="Enroll Inmate">
      <i class="bi bi-person-plus-fill"></i> Enroll
    </button>
    <?php endif; ?>
    <?php if ($canEdit): ?>
    <button class="ic-btn edit-btn" onclick='openEdit(<?php echo $pJson; ?>)' title="Edit">
      <i class="bi bi-pencil"></i> Edit
    </button>
    <?php endif; ?>
    <?php if ($canDelete): ?>
    <button class="ic-btn del-btn" onclick='openDelete(<?php echo $p["id"]; ?>,"<?php echo htmlspecialchars(addslashes($p['name'])); ?>")' title="Delete" style="margin-left:auto">
      <i class="bi bi-trash3"></i>
    </button>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ══ CREATE MODAL ══ -->
<?php if ($canCreate): ?>
<div class="modal-ov" id="createOv" onclick="if(event.target===this)closeCreate()">
  <div class="modal-box">
    <div class="modal-hdr">
      <h2><i class="bi bi-plus-lg" style="color:#3fb950"></i> New Program</h2>
      <button class="modal-close" onclick="closeCreate()"><i class="bi bi-x-lg"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="create">
      <input type="hidden" name="tab" value="<?php echo htmlspecialchars($activeTab); ?>">
      <div class="modal-body">
        <?php if ($isSA && $facilities): ?>
        <div class="form-group">
          <label>Facility</label>
          <select name="facility_id">
            <?php foreach ($facilities as $f): ?>
            <option value="<?php echo $f['id']; ?>"><?php echo htmlspecialchars($f['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <div class="form-group">
          <label>Program Name *</label>
          <input type="text" name="name" placeholder="e.g. Adult Literacy Program" required maxlength="255">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Type *</label>
            <select name="program_type" required>
              <option value="">— select —</option>
              <option value="EDUCATION">Education</option>
              <option value="VOCATIONAL">Vocational</option>
              <option value="COUNSELING">Counseling</option>
              <option value="SKILLS_TRAINING">Skills Training</option>
              <option value="SPORTS">Sports</option>
            </select>
          </div>
          <div class="form-group">
            <label>Status</label>
            <select name="status">
              <option value="ACTIVE">Active</option>
              <option value="INACTIVE">Inactive</option>
              <option value="COMPLETED">Completed</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Start Date *</label>
            <input type="date" name="start_date" required>
          </div>
          <div class="form-group">
            <label>End Date</label>
            <input type="date" name="end_date">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Capacity</label>
            <input type="number" name="capacity" min="1" placeholder="e.g. 20">
          </div>
          <div class="form-group">
            <label>Instructor (User)</label>
            <select name="instructor_id">
              <option value="">— none —</option>
              <?php
              $users = fetchAll("SELECT id,username FROM users WHERE deleted_at IS NULL ORDER BY username", [], '');
              foreach ($users as $u): ?>
              <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['username']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label>Description</label>
          <textarea name="description" rows="3" placeholder="Brief program description…" maxlength="1000"></textarea>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-ghost" onclick="closeCreate()">Cancel</button>
          <button type="submit" class="btn-primary-sm"><i class="bi bi-plus-lg"></i> Create Program</button>
        </div>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- ══ EDIT MODAL ══ -->
<?php if ($canEdit): ?>
<div class="modal-ov" id="editOv" onclick="if(event.target===this)closeEdit()">
  <div class="modal-box">
    <div class="modal-hdr">
      <h2><i class="bi bi-pencil" style="color:#388bfd"></i> Edit Program</h2>
      <button class="modal-close" onclick="closeEdit()"><i class="bi bi-x-lg"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="tab" value="<?php echo htmlspecialchars($activeTab); ?>">
      <input type="hidden" name="prog_id" id="edit_prog_id">
      <div class="modal-body">
        <div class="form-group">
          <label>Program Name *</label>
          <input type="text" name="name" id="edit_name" required maxlength="255">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Type *</label>
            <select name="program_type" id="edit_type">
              <option value="EDUCATION">Education</option>
              <option value="VOCATIONAL">Vocational</option>
              <option value="COUNSELING">Counseling</option>
              <option value="SKILLS_TRAINING">Skills Training</option>
              <option value="SPORTS">Sports</option>
            </select>
          </div>
          <div class="form-group">
            <label>Status</label>
            <select name="status" id="edit_status">
              <option value="ACTIVE">Active</option>
              <option value="INACTIVE">Inactive</option>
              <option value="COMPLETED">Completed</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Start Date *</label>
            <input type="date" name="start_date" id="edit_start" required>
          </div>
          <div class="form-group">
            <label>End Date</label>
            <input type="date" name="end_date" id="edit_end">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Capacity</label>
            <input type="number" name="capacity" id="edit_capacity" min="1">
          </div>
          <div class="form-group">
            <label>Instructor (User)</label>
            <select name="instructor_id" id="edit_instructor">
              <option value="">— none —</option>
              <?php foreach ($users as $u): ?>
              <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['username']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label>Description</label>
          <textarea name="description" id="edit_desc" rows="3" maxlength="1000"></textarea>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-ghost" onclick="closeEdit()">Cancel</button>
          <button type="submit" class="btn-primary-sm"><i class="bi bi-check-lg"></i> Save Changes</button>
        </div>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- ══ ENROLL MODAL ══ -->
<?php if ($canCreate): ?>
<div class="modal-ov" id="enrollOv" onclick="if(event.target===this)closeEnroll()">
  <div class="modal-box" style="max-width:420px">
    <div class="modal-hdr">
      <h2><i class="bi bi-person-plus-fill" style="color:#3fb950"></i> Enroll Inmate</h2>
      <button class="modal-close" onclick="closeEnroll()"><i class="bi bi-x-lg"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="enroll">
      <input type="hidden" name="tab" value="<?php echo htmlspecialchars($activeTab); ?>">
      <input type="hidden" name="prog_id" id="enroll_prog_id">
      <div class="modal-body">
        <div style="background:#0d1117;border:1px solid #21262d;border-radius:10px;padding:.7rem 1rem;margin-bottom:1rem;font-size:.84rem;color:#8b949e">
          Program: <strong id="enroll_prog_name" style="color:#e6edf3"></strong>
        </div>
        <div class="form-group">
          <label>Select Inmate *</label>
          <select name="inmate_id" required>
            <option value="">— choose inmate —</option>
            <?php foreach ($inmates as $inm): ?>
            <option value="<?php echo $inm['id']; ?>">
              <?php echo htmlspecialchars($inm['inmate_id'].' — '.$inm['first_name'].' '.$inm['last_name']); ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Enrollment Date</label>
          <input type="date" name="enrollment_date" value="<?php echo date('Y-m-d'); ?>">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-ghost" onclick="closeEnroll()">Cancel</button>
          <button type="submit" class="btn-primary-sm" style="background:linear-gradient(135deg,#238636,#3fb950)">
            <i class="bi bi-person-plus-fill"></i> Enroll
          </button>
        </div>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- ══ DELETE MODAL ══ -->
<?php if ($canDelete): ?>
<div class="modal-ov" id="delOv" onclick="if(event.target===this)closeDel()">
  <div class="modal-box" style="max-width:400px">
    <div class="modal-hdr">
      <h2><i class="bi bi-trash3" style="color:#f85149"></i> Delete Program</h2>
      <button class="modal-close" onclick="closeDel()"><i class="bi bi-x-lg"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="tab" value="<?php echo htmlspecialchars($activeTab); ?>">
      <input type="hidden" name="prog_id" id="del_prog_id">
      <div class="modal-body">
        <div class="del-warning">
          <i class="bi bi-exclamation-triangle-fill" style="flex-shrink:0;margin-top:.1rem"></i>
          <span>This will permanently remove <strong id="del_prog_name"></strong>. Programs with active enrollments cannot be deleted.</span>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-ghost" onclick="closeDel()">Cancel</button>
          <button type="submit" class="btn-primary-sm" style="background:linear-gradient(135deg,#b91c1c,#f85149)">
            <i class="bi bi-trash3"></i> Delete
          </button>
        </div>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
function openCreate() { document.getElementById('createOv').classList.add('open'); }
function closeCreate() { document.getElementById('createOv').classList.remove('open'); }

function openEdit(p) {
  document.getElementById('edit_prog_id').value  = p.id;
  document.getElementById('edit_name').value     = p.name;
  document.getElementById('edit_type').value     = p.program_type;
  document.getElementById('edit_status').value   = p.status;
  document.getElementById('edit_start').value    = p.start_date || '';
  document.getElementById('edit_end').value      = p.end_date   || '';
  document.getElementById('edit_capacity').value = p.capacity   || '';
  document.getElementById('edit_instructor').value = p.instructor_id || '';
  document.getElementById('edit_desc').value     = p.description || '';
  document.getElementById('editOv').classList.add('open');
}
function closeEdit() { document.getElementById('editOv').classList.remove('open'); }

function openEnroll(id, name) {
  document.getElementById('enroll_prog_id').value      = id;
  document.getElementById('enroll_prog_name').textContent = name;
  document.getElementById('enrollOv').classList.add('open');
}
function closeEnroll() { document.getElementById('enrollOv').classList.remove('open'); }

function openDelete(id, name) {
  document.getElementById('del_prog_id').value       = id;
  document.getElementById('del_prog_name').textContent = name;
  document.getElementById('delOv').classList.add('open');
}
function closeDel() { document.getElementById('delOv').classList.remove('open'); }

document.querySelectorAll('.modal-ov').forEach(m =>
  m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); })
);
</script>

<?php require_once '../../includes/footer.php'; ?>
