<?php
$pageTitle = 'Settings';
require_once '../../includes/header.php';
requireAuth();

$cu     = getCurrentUser();
$isSA   = isSuperAdmin();
$fid    = getCurrentFacility();
$userId = getCurrentUserId();

/* ══════════════════════════════════════════════════
   POST HANDLERS
══════════════════════════════════════════════════ */

$flash = ['type'=>'','msg'=>''];

/* ── Facility actions (Super Admin only) ── */
if ($_SERVER['REQUEST_METHOD']==='POST' && $isSA) {

    /* Create facility */
    if ($_POST['_action']==='facility_create') {
        $name  = trim($_POST['name']??'');
        $type  = trim($_POST['type']??'PRISON');
        $loc   = trim($_POST['location']??'');
        $cap   = (int)($_POST['total_capacity']??0);
        $st    = trim($_POST['status']??'ACTIVE');
        $cp    = trim($_POST['contact_person']??'');
        $ce    = trim($_POST['contact_email']??'');
        $cph   = trim($_POST['contact_phone']??'');
        if ($name && $loc && $cap>0) {
            $ok = executeQuery(
                "INSERT INTO facilities(name,type,location,total_capacity,status,contact_person,contact_email,contact_phone,created_at,updated_at)
                 VALUES(?,?,?,?,?,?,?,?,NOW(),NOW())",
                [$name,$type,$loc,$cap,$st,$cp,$ce,$cph],'sssisss'
            );
            $flash = $ok ? ['type'=>'ok','msg'=>"Facility '$name' created."] : ['type'=>'err','msg'=>'DB error creating facility.'];
        } else {
            $flash = ['type'=>'err','msg'=>'Name, location, and capacity are required.'];
        }
    }

    /* Edit facility */
    if ($_POST['_action']==='facility_edit') {
        $eid  = (int)($_POST['facility_id']??0);
        $name = trim($_POST['name']??'');
        $type = trim($_POST['type']??'PRISON');
        $loc  = trim($_POST['location']??'');
        $cap  = (int)($_POST['total_capacity']??0);
        $st   = trim($_POST['status']??'ACTIVE');
        $cp   = trim($_POST['contact_person']??'');
        $ce   = trim($_POST['contact_email']??'');
        $cph  = trim($_POST['contact_phone']??'');
        if ($eid && $name && $loc && $cap>0) {
            $ok = executeQuery(
                "UPDATE facilities SET name=?,type=?,location=?,total_capacity=?,status=?,contact_person=?,contact_email=?,contact_phone=?,updated_at=NOW() WHERE id=? AND deleted_at IS NULL",
                [$name,$type,$loc,$cap,$st,$cp,$ce,$cph,$eid],'sssissssi'
            );
            $flash = $ok ? ['type'=>'ok','msg'=>"Facility updated."] : ['type'=>'err','msg'=>'DB error updating facility.'];
        }
    }

    /* Delete facility */
    if ($_POST['_action']==='facility_delete') {
        $eid = (int)($_POST['facility_id']??0);
        if ($eid) {
            $ok = executeQuery("UPDATE facilities SET deleted_at=NOW() WHERE id=?",[$eid],'i');
            $flash = $ok ? ['type'=>'ok','msg'=>'Facility removed.'] : ['type'=>'err','msg'=>'DB error.'];
        }
    }

    /* Create user */
    if ($_POST['_action']==='user_create') {
        $un   = trim($_POST['username']??'');
        $em   = trim($_POST['email']??'');
        $fn   = trim($_POST['first_name']??'');
        $ln   = trim($_POST['last_name']??'');
        $role_= trim($_POST['role']??'OFFICER');
        $ufid = (int)($_POST['facility_id']??1);
        $pw   = trim($_POST['password']??'');
        if ($un && $em && $fn && $ln && $pw && strlen($pw)>=6) {
            $hash = password_hash($pw, PASSWORD_BCRYPT);
            $ok   = executeQuery(
                "INSERT INTO users(facility_id,username,email,password_hash,first_name,last_name,role,is_active,created_at,updated_at)
                 VALUES(?,?,?,?,?,?,?,1,NOW(),NOW())",
                [$ufid,$un,$em,$hash,$fn,$ln,$role_],'isssss' . 's'
            );
            $flash = $ok ? ['type'=>'ok','msg'=>"User '$un' created."] : ['type'=>'err','msg'=>'DB error — username/email may already exist.'];
        } else {
            $flash = ['type'=>'err','msg'=>'All fields required. Password must be at least 6 characters.'];
        }
    }

    /* Edit user */
    if ($_POST['_action']==='user_edit') {
        $eid  = (int)($_POST['user_id']??0);
        $fn   = trim($_POST['first_name']??'');
        $ln   = trim($_POST['last_name']??'');
        $em   = trim($_POST['email']??'');
        $role_= trim($_POST['role']??'OFFICER');
        $ufid = (int)($_POST['facility_id']??1);
        $pw   = trim($_POST['password']??'');
        if ($eid && $fn && $ln && $em) {
            if ($pw && strlen($pw)>=6) {
                $hash = password_hash($pw, PASSWORD_BCRYPT);
                $ok   = executeQuery(
                    "UPDATE users SET first_name=?,last_name=?,email=?,role=?,facility_id=?,password_hash=?,updated_at=NOW() WHERE id=? AND deleted_at IS NULL",
                    [$fn,$ln,$em,$role_,$ufid,$hash,$eid],'ssssssi'
                );
            } else {
                $ok = executeQuery(
                    "UPDATE users SET first_name=?,last_name=?,email=?,role=?,facility_id=?,updated_at=NOW() WHERE id=? AND deleted_at IS NULL",
                    [$fn,$ln,$em,$role_,$ufid,$eid],'sssssi'
                );
            }
            $flash = $ok ? ['type'=>'ok','msg'=>'User updated.'] : ['type'=>'err','msg'=>'DB error updating user.'];
        }
    }

    /* Toggle user active */
    if ($_POST['_action']==='user_toggle') {
        $eid = (int)($_POST['user_id']??0);
        $act = (int)($_POST['is_active']??0);
        if ($eid && $eid!==$userId) {
            $ok = executeQuery("UPDATE users SET is_active=?,updated_at=NOW() WHERE id=? AND deleted_at IS NULL",[$act?0:1,$eid],'ii');
            $flash = $ok ? ['type'=>'ok','msg'=>'User status updated.'] : ['type'=>'err','msg'=>'DB error.'];
        } else {
            $flash = ['type'=>'err','msg'=>'Cannot deactivate your own account.'];
        }
    }

    /* Delete user */
    if ($_POST['_action']==='user_delete') {
        $eid = (int)($_POST['user_id']??0);
        if ($eid && $eid!==$userId) {
            $ok = executeQuery("UPDATE users SET deleted_at=NOW() WHERE id=?",[$eid],'i');
            $flash = $ok ? ['type'=>'ok','msg'=>'User removed.'] : ['type'=>'err','msg'=>'DB error.'];
        } else {
            $flash = ['type'=>'err','msg'=>'Cannot delete your own account.'];
        }
    }
}

/* ── Change password (any authenticated user) ── */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_action']??'')==='change_password') {
    $oldPw  = $_POST['old_password']??'';
    $newPw  = $_POST['new_password']??'';
    $confPw = $_POST['confirm_password']??'';
    if (!$oldPw || !$newPw || !$confPw) {
        $flash = ['type'=>'err','msg'=>'All password fields are required.'];
    } elseif ($newPw !== $confPw) {
        $flash = ['type'=>'err','msg'=>'New passwords do not match.'];
    } elseif (strlen($newPw) < 6) {
        $flash = ['type'=>'err','msg'=>'New password must be at least 6 characters.'];
    } else {
        $row = fetchOne("SELECT password_hash FROM users WHERE id=?",[$userId],'i');
        if ($row && password_verify($oldPw, $row['password_hash'])) {
            $hash = password_hash($newPw, PASSWORD_BCRYPT);
            $ok   = executeQuery("UPDATE users SET password_hash=?,updated_at=NOW() WHERE id=?",[$hash,$userId],'si');
            $flash = $ok ? ['type'=>'ok','msg'=>'Password changed successfully.'] : ['type'=>'err','msg'=>'DB error.'];
        } else {
            $flash = ['type'=>'err','msg'=>'Current password is incorrect.'];
        }
    }
}

/* ══════════════════════════════════════════════════
   DATA QUERIES
══════════════════════════════════════════════════ */

/* Facilities */
$facilities = $isSA
    ? fetchAll("SELECT f.*,
            COALESCE(sc.cnt,0) staff_count,
            COALESCE(ic.cnt,0) inmate_count
        FROM facilities f
        LEFT JOIN (SELECT facility_id,COUNT(*) cnt FROM staff   WHERE deleted_at IS NULL GROUP BY facility_id) sc ON sc.facility_id=f.id
        LEFT JOIN (SELECT facility_id,COUNT(*) cnt FROM inmates WHERE deleted_at IS NULL AND status IN ('REMAND','CONVICTED') GROUP BY facility_id) ic ON ic.facility_id=f.id
        WHERE f.deleted_at IS NULL ORDER BY f.name", [], '')
    : fetchAll("SELECT f.*,
            COALESCE(sc.cnt,0) staff_count,
            COALESCE(ic.cnt,0) inmate_count
        FROM facilities f
        LEFT JOIN (SELECT facility_id,COUNT(*) cnt FROM staff   WHERE deleted_at IS NULL GROUP BY facility_id) sc ON sc.facility_id=f.id
        LEFT JOIN (SELECT facility_id,COUNT(*) cnt FROM inmates WHERE deleted_at IS NULL AND status IN ('REMAND','CONVICTED') GROUP BY facility_id) ic ON ic.facility_id=f.id
        WHERE f.id=? AND f.deleted_at IS NULL ORDER BY f.name", [$fid],'i');

/* Users */
$allUsers = $isSA
    ? fetchAll("SELECT u.*,f.name facility_name FROM users u LEFT JOIN facilities f ON u.facility_id=f.id WHERE u.deleted_at IS NULL ORDER BY u.role,u.username", [], '')
    : fetchAll("SELECT u.*,f.name facility_name FROM users u LEFT JOIN facilities f ON u.facility_id=f.id WHERE u.deleted_at IS NULL AND u.facility_id=? ORDER BY u.role,u.username", [$fid],'i');

/* All facilities for user form selects */
$allFacilities = fetchAll("SELECT id,name FROM facilities WHERE deleted_at IS NULL ORDER BY name", [], '');

/* Activity & system logs */
$logLimit = 200;
$actLogs = fetchAll(
    "SELECT al.*, u.username, u.first_name, u.last_name
     FROM activity_logs al LEFT JOIN users u ON al.user_id=u.id
     ".($isSA ? '' : "WHERE u.facility_id=$fid")."
     ORDER BY al.created_at DESC LIMIT $logLimit",
    [], ''
);
$sysLogs = fetchAll(
    "SELECT sl.*, u.username
     FROM system_logs sl LEFT JOIN users u ON sl.user_id=u.id
     ".($isSA ? '' : "WHERE sl.facility_id=$fid")."
     ORDER BY sl.created_at DESC LIMIT $logLimit",
    [], ''
);

/* Current user full record */
$me = fetchOne("SELECT * FROM users WHERE id=? AND deleted_at IS NULL",[$userId],'i');

/* Roles list */
$allRoles = ['SUPER_ADMIN','FACILITY_ADMIN','OFFICER','MEDICAL_STAFF','RECORDS_OFFICER','FINANCE_OFFICER'];

/* Active tab */
$activeTab = $_GET['tab'] ?? 'facilities';
$validTabs = ['facilities','users','logs','security'];
if (!in_array($activeTab,$validTabs)) $activeTab = 'facilities';

/* KPIs */
$kFac  = count($facilities);
$kUsr  = count($allUsers);
$kAct  = (int)array_sum(array_map(fn($u)=>$u['is_active'],$allUsers));
$kLogs = count($actLogs)+count($sysLogs);

/* helpers */
$roleColors=[
    'SUPER_ADMIN'    =>'#f85149',
    'FACILITY_ADMIN' =>'#f39c12',
    'OFFICER'        =>'#388bfd',
    'MEDICAL_STAFF'  =>'#bb8fce',
    'RECORDS_OFFICER'=>'#3fb950',
    'FINANCE_OFFICER'=>'#e3b341',
];
$facStatusColors=['ACTIVE'=>'#3fb950','INACTIVE'=>'#8b949e','UNDER_MAINTENANCE'=>'#f39c12'];
$facTypeColors=['PRISON'=>'#388bfd','ADMIN_OFFICE'=>'#f39c12','HOLDING_CENTER'=>'#bb8fce'];
$actTypeColors=['LOGIN'=>'#3fb950','LOGOUT'=>'#8b949e','VIEW'=>'#388bfd','CREATE'=>'#e3b341','UPDATE'=>'#f39c12','DELETE'=>'#f85149'];

function stbg($val,$map,$def='#8b949e'){
    $c=$map[$val]??$def;
    preg_match('/^#([0-9a-f]{6})$/i',$c,$m);
    $bg=$m?'rgba('.hexdec(substr($m[1],0,2)).','.hexdec(substr($m[1],2,2)).','.hexdec(substr($m[1],4,2)).',0.15)':'rgba(139,148,158,.15)';
    return "<span style='display:inline-flex;align-items:center;padding:2px 9px;border-radius:20px;font-size:.67rem;font-weight:700;letter-spacing:.03em;color:$c;background:$bg'>".htmlspecialchars($val)."</span>";
}
?>
<style>
:root{--sur:#161b22;--bdr:#21262d;--bg:#0d1117;--txt:#e6edf3;--mut:#8b949e}
*{box-sizing:border-box}
.ph{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:.75rem}
.ph h1{font-size:1.35rem;font-weight:700;color:var(--txt);margin:0}
.bc{font-size:.78rem;color:#6e7681;display:flex;align-items:center;gap:.3rem;margin-bottom:.3rem}
.bc a{color:#388bfd;text-decoration:none}.bc a:hover{text-decoration:underline}
.ph-act{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap}
.btn-pr{display:inline-flex;align-items:center;gap:.4rem;padding:.42rem .9rem;border-radius:9px;border:none;background:#388bfd;color:#fff;font-size:.8rem;font-weight:600;cursor:pointer;transition:all .18s}
.btn-pr:hover{background:#58a6ff;box-shadow:0 4px 14px rgba(56,139,253,.3)}
.btn-ghost{display:inline-flex;align-items:center;gap:.4rem;padding:.42rem .85rem;border-radius:9px;border:1px solid #30363d;background:transparent;color:#c9d1d9;font-size:.8rem;font-weight:600;cursor:pointer;text-decoration:none;transition:all .18s}
.btn-ghost:hover{background:#21262d;border-color:#388bfd;color:#388bfd}
.btn-xs{display:inline-flex;align-items:center;gap:.3rem;padding:.28rem .6rem;border-radius:7px;border:1px solid #30363d;background:transparent;color:#8b949e;font-size:.72rem;font-weight:600;cursor:pointer;transition:all .18s;white-space:nowrap}
.btn-xs:hover{background:#21262d;color:#e6edf3}
.btn-xs.danger:hover{border-color:#f85149;color:#f85149}
.btn-xs.success:hover{border-color:#3fb950;color:#3fb950}
.sec-2col{display:grid;grid-template-columns:1fr 1fr;gap:1.2rem}
@media(max-width:700px){.sec-2col{grid-template-columns:1fr}}

/* KPI */
.kpi-row{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.4rem}
@media(max-width:700px){.kpi-row{grid-template-columns:repeat(2,1fr)}}
.kpi{background:var(--sur);border:1px solid var(--bdr);border-radius:14px;padding:1rem 1.1rem}
.kpi-ico{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:.9rem;margin-bottom:.55rem}
.kpi-val{font-size:1.55rem;font-weight:800;color:var(--txt);line-height:1;margin-bottom:.18rem}
.kpi-lbl{font-size:.68rem;font-weight:600;color:var(--mut);text-transform:uppercase;letter-spacing:.05em}

/* Tabs */
.tab-strip{display:flex;gap:.3rem;margin-bottom:1.25rem;flex-wrap:wrap;padding:.38rem .42rem;background:var(--sur);border:1px solid var(--bdr);border-radius:12px;width:fit-content}
.tab-lnk{display:flex;align-items:center;gap:.35rem;padding:.36rem .82rem;border-radius:7px;font-size:.79rem;font-weight:600;cursor:pointer;color:var(--mut);background:transparent;border:none;text-decoration:none;transition:all .18s;white-space:nowrap}
.tab-lnk:hover,.tab-lnk.on{background:#21262d;color:var(--txt)}

/* Section */
.sct{display:none}.sct.active{display:block}
.sec{background:var(--sur);border:1px solid var(--bdr);border-radius:14px;overflow:hidden;margin-bottom:1.2rem}
.sec-hdr{display:flex;align-items:center;justify-content:space-between;padding:.75rem 1rem;border-bottom:1px solid #1c2128}
.sec-hdr h3{margin:0;font-size:.88rem;font-weight:700;color:var(--txt);display:flex;align-items:center;gap:.42rem}
.cnt-b{font-size:.7rem;color:#8b949e;background:#21262d;padding:1px 8px;border-radius:20px;font-weight:600}

/* Table */
.dtab{width:100%;border-collapse:collapse;font-size:.795rem}
.dtab th{padding:.55rem .85rem;text-align:left;font-size:.68rem;font-weight:600;color:#8b949e;text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid #1c2128;white-space:nowrap;position:sticky;top:0;background:#161b22;z-index:1}
.dtab td{padding:.52rem .85rem;border-bottom:1px solid #0d1117;color:#c9d1d9;vertical-align:middle}
.dtab tr:last-child td{border-bottom:none}
.dtab tbody tr:hover{background:rgba(56,139,253,.04)}
.tbl-wrap{overflow-x:auto;max-height:450px;overflow-y:auto}
.tbl-toolbar{display:flex;align-items:center;gap:.6rem;padding:.6rem 1rem;border-bottom:1px solid #1c2128;flex-wrap:wrap}
.srch{flex:1;min-width:140px;max-width:260px;position:relative}
.srch i{position:absolute;left:.65rem;top:50%;transform:translateY(-50%);color:#484f58;font-size:.75rem;pointer-events:none}
.srch input{width:100%;background:#0d1117;border:1px solid #30363d;color:#e6edf3;padding:.38rem .7rem .38rem 1.9rem;border-radius:8px;font-size:.79rem;outline:none;transition:border .18s}
.srch input:focus{border-color:#388bfd;box-shadow:0 0 0 3px rgba(56,139,253,.1)}
.srch input::placeholder{color:#484f58}
.rec-lbl{font-size:.74rem;color:#8b949e;margin-left:auto;white-space:nowrap}
.hidden-row{display:none}

/* Facility cards */
.fac-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:1.1rem;padding:1rem}
.fac-card{background:#0d1117;border:1px solid #21262d;border-radius:12px;padding:1.1rem 1.2rem;position:relative;transition:box-shadow .18s}
.fac-card:hover{box-shadow:0 4px 20px rgba(0,0,0,.4)}
.fac-card-top{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:.7rem}
.fac-card-name{font-size:.97rem;font-weight:700;color:#e6edf3;margin-bottom:.2rem}
.fac-card-loc{font-size:.76rem;color:#8b949e;display:flex;align-items:center;gap:.3rem}
.fac-stat-row{display:flex;gap:.5rem;margin-top:.85rem;flex-wrap:wrap}
.fac-stat{flex:1;min-width:80px;background:#161b22;border-radius:8px;padding:.5rem .7rem;text-align:center}
.fac-stat .v{font-size:1.1rem;font-weight:700;color:#e6edf3}
.fac-stat .l{font-size:.66rem;color:#8b949e;font-weight:600;text-transform:uppercase;margin-top:.1rem}
.cap-bar{height:4px;background:#21262d;border-radius:2px;overflow:hidden;margin-top:.65rem}
.cap-fill{height:100%;border-radius:2px}
.fac-card-acts{display:flex;gap:.4rem;margin-top:.8rem}

/* Security card */
.sec-card{background:#0d1117;border:1px solid #21262d;border-radius:12px;padding:1.2rem 1.4rem;margin-bottom:1rem}
.sec-card h4{margin:0 0 .6rem;font-size:.88rem;font-weight:700;color:#e6edf3;display:flex;align-items:center;gap:.4rem}
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:.7rem .9rem}
@media(max-width:600px){.info-grid{grid-template-columns:1fr}}
.info-row{display:flex;flex-direction:column;gap:.15rem}
.info-lbl{font-size:.69rem;font-weight:600;color:#8b949e;text-transform:uppercase;letter-spacing:.04em}
.info-val{font-size:.83rem;color:#e6edf3;font-weight:500}

/* Form */
.fld{display:flex;flex-direction:column;gap:.35rem;margin-bottom:.8rem}
.fld label{font-size:.74rem;font-weight:600;color:#8b949e;text-transform:uppercase;letter-spacing:.04em}
.fld input,.fld select,.fld textarea{background:#0d1117;border:1px solid #30363d;color:#e6edf3;padding:.5rem .75rem;border-radius:9px;font-size:.82rem;outline:none;width:100%;transition:border .18s}
.fld input:focus,.fld select:focus,.fld textarea:focus{border-color:#388bfd;box-shadow:0 0 0 3px rgba(56,139,253,.1)}
.fld input::placeholder{color:#484f58}
.fld select option{background:#161b22;color:#e6edf3}
.frow{display:grid;grid-template-columns:1fr 1fr;gap:.6rem}
@media(max-width:500px){.frow{grid-template-columns:1fr}}
.pw-wrap{position:relative}
.pw-wrap input{padding-right:2.5rem}
.pw-eye{position:absolute;right:.75rem;top:50%;transform:translateY(-50%);cursor:pointer;color:#8b949e;font-size:.82rem;background:none;border:none;padding:0}
.pw-eye:hover{color:#388bfd}

/* Flash */
.flash{display:flex;align-items:center;gap:.6rem;padding:.7rem 1rem;border-radius:10px;font-size:.82rem;font-weight:500;margin-bottom:1.1rem}
.flash.ok{background:rgba(63,185,80,.1);border:1px solid rgba(63,185,80,.3);color:#3fb950}
.flash.err{background:rgba(248,81,73,.1);border:1px solid rgba(248,81,73,.3);color:#f85149}

/* Modal overlay */
.modal-ov{display:none;position:fixed;inset:0;background:rgba(1,4,9,.75);z-index:1500;align-items:center;justify-content:center;padding:1rem}
.modal-ov.open{display:flex}
.modal-box{background:#161b22;border:1px solid #30363d;border-radius:16px;width:100%;max-width:520px;max-height:90vh;overflow-y:auto;box-shadow:0 16px 60px rgba(0,0,0,.6)}
.modal-hdr{display:flex;align-items:center;justify-content:space-between;padding:.85rem 1.2rem;border-bottom:1px solid #21262d}
.modal-hdr h4{margin:0;font-size:.95rem;font-weight:700;color:#e6edf3;display:flex;align-items:center;gap:.45rem}
.modal-close{background:none;border:none;color:#8b949e;font-size:1.1rem;cursor:pointer;padding:.2rem .4rem;border-radius:6px;line-height:1;display:flex}
.modal-close:hover{background:#21262d;color:#f85149}
.modal-body{padding:1.1rem 1.2rem}
.modal-foot{display:flex;justify-content:flex-end;gap:.6rem;padding:.85rem 1.2rem;border-top:1px solid #21262d}
.btn-sub{display:inline-flex;align-items:center;gap:.4rem;padding:.45rem .95rem;border-radius:9px;border:none;background:#388bfd;color:#fff;font-size:.82rem;font-weight:600;cursor:pointer;transition:all .18s}
.btn-sub:hover{background:#58a6ff;box-shadow:0 4px 14px rgba(56,139,253,.3)}
.btn-sub.danger{background:#f85149}
.btn-sub.danger:hover{background:#ff7b72;box-shadow:0 4px 14px rgba(248,81,73,.3)}
.btn-cancel{display:inline-flex;align-items:center;gap:.4rem;padding:.45rem .9rem;border-radius:9px;border:1px solid #30363d;background:transparent;color:#8b949e;font-size:.82rem;font-weight:600;cursor:pointer;transition:all .18s}
.btn-cancel:hover{background:#21262d;color:#e6edf3}

/* avatar */
.av{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.77rem;font-weight:700;flex-shrink:0}
/* log action */
.log-act{display:inline-flex;align-items:center;padding:1px 7px;border-radius:4px;font-size:.68rem;font-weight:700}
</style>

<!-- PAGE HEADER -->
<div class="ph">
  <div>
    <div class="bc">
      <a href="<?php echo APP_URL; ?>/dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
      <i class="bi bi-chevron-right" style="font-size:.6rem"></i>
      <span>Settings</span>
    </div>
    <h1><i class="bi bi-gear-fill" style="color:#8b949e;margin-right:.4rem"></i>Settings</h1>
  </div>
</div>

<!-- FLASH -->
<?php if ($flash['msg']): ?>
<div class="flash <?php echo $flash['type']==='ok'?'ok':'err'; ?>">
  <i class="bi <?php echo $flash['type']==='ok'?'bi-check-circle-fill':'bi-exclamation-circle-fill'; ?>"></i>
  <?php echo htmlspecialchars($flash['msg']); ?>
</div>
<?php endif; ?>

<!-- KPI -->
<div class="kpi-row">
  <div class="kpi" style="border-top:3px solid #388bfd">
    <div class="kpi-ico" style="background:rgba(56,139,253,.15);color:#388bfd"><i class="bi bi-building-fill"></i></div>
    <div class="kpi-val"><?php echo $kFac; ?></div>
    <div class="kpi-lbl">Facilities</div>
  </div>
  <div class="kpi" style="border-top:3px solid #3fb950">
    <div class="kpi-ico" style="background:rgba(63,185,80,.15);color:#3fb950"><i class="bi bi-people-fill"></i></div>
    <div class="kpi-val"><?php echo $kUsr; ?></div>
    <div class="kpi-lbl">Total Users</div>
  </div>
  <div class="kpi" style="border-top:3px solid #e3b341">
    <div class="kpi-ico" style="background:rgba(227,179,65,.15);color:#e3b341"><i class="bi bi-person-check-fill"></i></div>
    <div class="kpi-val"><?php echo $kAct; ?></div>
    <div class="kpi-lbl">Active Users</div>
  </div>
  <div class="kpi" style="border-top:3px solid #8b949e">
    <div class="kpi-ico" style="background:rgba(139,148,158,.15);color:#8b949e"><i class="bi bi-journal-text"></i></div>
    <div class="kpi-val"><?php echo $kLogs; ?></div>
    <div class="kpi-lbl">Log Entries</div>
  </div>
</div>

<!-- TABS -->
<div class="tab-strip">
  <?php
  $tabs=['facilities'=>['bi-building','Facilities','#388bfd'],
         'users'     =>['bi-people-fill','Users','#3fb950'],
         'logs'      =>['bi-journal-text','Activity Logs','#8b949e'],
         'security'  =>['bi-shield-lock-fill','Security','#f85149']];
  foreach($tabs as $key=>[$ico,$lbl,$clr]):
    if($key==='facilities' && !$isSA) continue;
  ?>
  <a href="?tab=<?php echo $key;?>" class="tab-lnk <?php echo $activeTab===$key?'on':'';?>"
     style="<?php echo $activeTab===$key?"color:$clr":'';?>"
     onclick="event.preventDefault();switchTab('<?php echo $key;?>')">
    <i class="bi <?php echo $ico;?>"></i><?php echo $lbl;?>
  </a>
  <?php endforeach;?>
</div>

<!-- ══════════════════════════ FACILITIES TAB ══════════════════════════ -->
<?php if($isSA):?>
<div class="sct <?php echo $activeTab==='facilities'?'active':'';?>" id="sct-facilities">
  <div class="sec">
    <div class="sec-hdr">
      <h3><i class="bi bi-building-fill" style="color:#388bfd"></i> Facilities <span class="cnt-b"><?php echo count($facilities);?></span></h3>
      <button class="btn-pr" onclick="openModal('m-fac-create')"><i class="bi bi-plus-lg"></i> Add Facility</button>
    </div>
    <?php if($facilities):?>
    <div class="fac-grid">
      <?php foreach($facilities as $f):
        $used=$f['total_capacity']>0?min(100,round($f['inmate_count']/$f['total_capacity']*100)):0;
        $fillC=$used>=90?'#f85149':($used>=70?'#f39c12':'#3fb950');
        $tc=$facTypeColors[$f['type']]??'#8b949e';
        $sc=$facStatusColors[$f['status']]??'#8b949e';?>
      <div class="fac-card" style="border-top:3px solid <?php echo $tc;?>">
        <div class="fac-card-top">
          <div>
            <div class="fac-card-name"><?php echo htmlspecialchars($f['name']);?></div>
            <div class="fac-card-loc"><i class="bi bi-geo-alt-fill" style="font-size:.7rem"></i><?php echo htmlspecialchars($f['location']);?></div>
          </div>
          <?php echo stbg($f['status'],$facStatusColors);?>
        </div>
        <?php echo stbg($f['type'],$facTypeColors);?>
        <div class="fac-stat-row">
          <div class="fac-stat"><div class="v" style="color:<?php echo $fillC;?>"><?php echo $f['inmate_count'];?></div><div class="l">Inmates</div></div>
          <div class="fac-stat"><div class="v"><?php echo $f['staff_count'];?></div><div class="l">Staff</div></div>
          <div class="fac-stat"><div class="v"><?php echo $f['total_capacity'];?></div><div class="l">Capacity</div></div>
        </div>
        <div class="cap-bar"><div class="cap-fill" style="width:<?php echo $used;?>%;background:<?php echo $fillC;?>"></div></div>
        <div style="font-size:.68rem;color:#484f58;margin-top:.25rem"><?php echo $used;?>% occupied</div>
        <?php if($f['contact_person'] || $f['contact_email']):?>
        <div style="margin-top:.6rem;font-size:.74rem;color:#8b949e;display:flex;flex-direction:column;gap:.15rem">
          <?php if($f['contact_person']):?><span><i class="bi bi-person"></i> <?php echo htmlspecialchars($f['contact_person']);?></span><?php endif;?>
          <?php if($f['contact_email']):?><span><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($f['contact_email']);?></span><?php endif;?>
          <?php if($f['contact_phone']):?><span><i class="bi bi-telephone"></i> <?php echo htmlspecialchars($f['contact_phone']);?></span><?php endif;?>
        </div>
        <?php endif;?>
        <div class="fac-card-acts">
          <button class="btn-xs" onclick='openFacEdit(<?php echo json_encode($f);?>)'><i class="bi bi-pencil"></i> Edit</button>
          <button class="btn-xs danger" onclick="confirmFacDel(<?php echo $f['id'];?>,'<?php echo addslashes($f['name']);?>')"><i class="bi bi-trash3"></i> Delete</button>
        </div>
      </div>
      <?php endforeach;?>
    </div>
    <?php else:?>
    <div style="padding:2rem;text-align:center;color:#8b949e"><i class="bi bi-building" style="font-size:2rem;display:block;opacity:.2;margin-bottom:.5rem"></i>No facilities found</div>
    <?php endif;?>
  </div>
</div>
<?php endif;?>

<!-- ══════════════════════════ USERS TAB ══════════════════════════ -->
<div class="sct <?php echo $activeTab==='users'?'active':'';?>" id="sct-users">
  <div class="sec">
    <div class="sec-hdr">
      <h3><i class="bi bi-people-fill" style="color:#3fb950"></i> Users <span class="cnt-b"><?php echo count($allUsers);?></span></h3>
      <?php if($isSA):?>
      <button class="btn-pr" onclick="openModal('m-usr-create')"><i class="bi bi-person-plus-fill"></i> Add User</button>
      <?php endif;?>
    </div>
    <div class="tbl-toolbar">
      <div class="srch"><i class="bi bi-search"></i><input type="text" placeholder="Search name, username, role…" oninput="filterTable(this,'tbl-users')"></div>
      <span class="rec-lbl" id="cnt-users"><?php echo count($allUsers);?> shown</span>
    </div>
    <?php if($allUsers):?>
    <div class="tbl-wrap">
      <table class="dtab" id="tbl-users">
        <thead><tr>
          <th>#</th><th>User</th><th>Email</th><th>Role</th>
          <th>Facility</th><th>Status</th><th>Last Login</th>
          <?php if($isSA):?><th>Actions</th><?php endif;?>
        </tr></thead>
        <tbody>
        <?php foreach($allUsers as $idx=>$u):
          $rc=$roleColors[$u['role']]??'#8b949e';
          $isMe=$u['id']==$userId;
          $initials=strtoupper(substr($u['first_name'],0,1).substr($u['last_name'],0,1));?>
        <tr>
          <td style="color:#484f58"><?php echo $idx+1;?></td>
          <td>
            <div style="display:flex;align-items:center;gap:.6rem">
              <div class="av" style="background:rgba(<?php
                preg_match('/^#([0-9a-f]{6})$/i',$rc,$m);
                echo $m?hexdec(substr($m[1],0,2)).','.hexdec(substr($m[1],2,2)).','.hexdec(substr($m[1],4,2)):'139,148,158';?>, .2);color:<?php echo $rc;?>"><?php echo $initials;?></div>
              <div>
                <div style="font-weight:600;color:#e6edf3"><?php echo htmlspecialchars($u['first_name'].' '.$u['last_name']);?>
                  <?php if($isMe):?><span style="font-size:.65rem;color:#388bfd;margin-left:.3rem">(you)</span><?php endif;?>
                </div>
                <div style="font-size:.72rem;color:#484f58;font-family:monospace">@<?php echo htmlspecialchars($u['username']);?></div>
              </div>
            </div>
          </td>
          <td style="color:#8b949e;font-size:.77rem"><?php echo htmlspecialchars($u['email']??'—');?></td>
          <td><?php echo stbg($u['role'],$roleColors);?></td>
          <td style="color:#8b949e;font-size:.77rem"><?php echo htmlspecialchars($u['facility_name']??'—');?></td>
          <td>
            <?php if($u['is_active']):?>
            <span style="color:#3fb950;font-size:.77rem;font-weight:600"><i class="bi bi-circle-fill" style="font-size:.45rem;vertical-align:.1em"></i> Active</span>
            <?php else:?>
            <span style="color:#f85149;font-size:.77rem;font-weight:600"><i class="bi bi-circle" style="font-size:.45rem;vertical-align:.1em"></i> Inactive</span>
            <?php endif;?>
          </td>
          <td style="color:#484f58;font-size:.75rem;white-space:nowrap"><?php echo $u['last_login']?date('M j, Y H:i',strtotime($u['last_login'])):'Never';?></td>
          <?php if($isSA):?>
          <td>
            <div style="display:flex;gap:.35rem;flex-wrap:wrap">
              <button class="btn-xs" onclick='openUsrEdit(<?php echo json_encode($u);?>)'><i class="bi bi-pencil"></i> Edit</button>
              <?php if(!$isMe):?>
              <form method="POST" style="display:inline">
                <input type="hidden" name="_action" value="user_toggle">
                <input type="hidden" name="user_id" value="<?php echo $u['id'];?>">
                <input type="hidden" name="is_active" value="<?php echo $u['is_active'];?>">
                <button type="submit" class="btn-xs <?php echo $u['is_active']?'danger':'success';?>"
                  onclick="return confirm('<?php echo $u['is_active']?'Deactivate':'Activate';?> this user?')">
                  <i class="bi <?php echo $u['is_active']?'bi-person-dash':'bi-person-check';?>"></i>
                  <?php echo $u['is_active']?'Deactivate':'Activate';?>
                </button>
              </form>
              <button class="btn-xs danger" onclick="confirmUsrDel(<?php echo $u['id'];?>,'<?php echo addslashes($u['username']);?>')"><i class="bi bi-trash3"></i></button>
              <?php endif;?>
            </div>
          </td>
          <?php endif;?>
        </tr>
        <?php endforeach;?>
        </tbody>
      </table>
    </div>
    <?php else:?>
    <div style="padding:2rem;text-align:center;color:#8b949e"><i class="bi bi-people" style="font-size:2rem;display:block;opacity:.2;margin-bottom:.5rem"></i>No users found</div>
    <?php endif;?>
  </div>
</div>

<!-- ══════════════════════════ LOGS TAB ══════════════════════════ -->
<div class="sct <?php echo $activeTab==='logs'?'active':'';?>" id="sct-logs">
  <!-- Activity Logs -->
  <div class="sec">
    <div class="sec-hdr">
      <h3><i class="bi bi-activity" style="color:#388bfd"></i> Activity Logs <span class="cnt-b"><?php echo count($actLogs);?></span></h3>
      <button class="btn-xs" onclick="exportCSV('tbl-act-logs','activity_logs')"><i class="bi bi-download"></i> CSV</button>
    </div>
    <div class="tbl-toolbar">
      <div class="srch"><i class="bi bi-search"></i><input type="text" placeholder="Search user, action, resource…" oninput="filterTable(this,'tbl-act-logs')"></div>
      <span class="rec-lbl" id="cnt-tbl-act-logs"><?php echo count($actLogs);?> shown</span>
    </div>
    <?php if($actLogs):?>
    <div class="tbl-wrap">
      <table class="dtab" id="tbl-act-logs">
        <thead><tr><th>Time</th><th>User</th><th>Type</th><th>Resource</th><th>IP</th></tr></thead>
        <tbody>
        <?php foreach($actLogs as $l):
          $ac=$actTypeColors[$l['activity_type']]??'#8b949e';
          $m2=[];
          preg_match('/^#([0-9a-f]{6})$/i',$ac,$m2);
          $abg=$m2?'rgba('.hexdec(substr($m2[1],0,2)).','.hexdec(substr($m2[1],2,2)).','.hexdec(substr($m2[1],4,2)).',.15)':'rgba(139,148,158,.15)';
          ?>
        <tr>
          <td style="color:#484f58;white-space:nowrap;font-size:.75rem"><?php echo $l['created_at']?date('M j, Y H:i:s',strtotime($l['created_at'])):'—';?></td>
          <td>
            <?php if($l['username']):?>
            <span style="font-weight:600;color:#c9d1d9"><?php echo htmlspecialchars($l['first_name'].' '.$l['last_name']);?></span>
            <span style="display:block;font-size:.69rem;color:#484f58;font-family:monospace">@<?php echo htmlspecialchars($l['username']);?></span>
            <?php else:?><span style="color:#484f58">System</span><?php endif;?>
          </td>
          <td><span class="log-act" style="color:<?php echo $ac;?>;background:<?php echo $abg;?>"><?php echo htmlspecialchars($l['activity_type']??'—');?></span></td>
          <td style="color:#8b949e;font-size:.76rem;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
              title="<?php echo htmlspecialchars($l['resource']??'');?>"><?php echo htmlspecialchars($l['resource']??'—');?></td>
          <td style="color:#484f58;font-size:.74rem;font-family:monospace"><?php echo htmlspecialchars($l['ip_address']??'—');?></td>
        </tr>
        <?php endforeach;?>
        </tbody>
      </table>
    </div>
    <?php else:?><div style="padding:2rem;text-align:center;color:#8b949e">No activity logs</div><?php endif;?>
  </div>

  <!-- System Logs -->
  <div class="sec">
    <div class="sec-hdr">
      <h3><i class="bi bi-hdd-stack-fill" style="color:#8b949e"></i> System Logs <span class="cnt-b"><?php echo count($sysLogs);?></span></h3>
      <button class="btn-xs" onclick="exportCSV('tbl-sys-logs','system_logs')"><i class="bi bi-download"></i> CSV</button>
    </div>
    <div class="tbl-toolbar">
      <div class="srch"><i class="bi bi-search"></i><input type="text" placeholder="Search action, module, status…" oninput="filterTable(this,'tbl-sys-logs')"></div>
      <span class="rec-lbl" id="cnt-tbl-sys-logs"><?php echo count($sysLogs);?> shown</span>
    </div>
    <?php if($sysLogs):?>
    <div class="tbl-wrap">
      <table class="dtab" id="tbl-sys-logs">
        <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Module</th><th>Entity</th><th>Status</th><th>IP</th></tr></thead>
        <tbody>
        <?php foreach($sysLogs as $l):
          $sc2=$l['status']==='SUCCESS'?'#3fb950':'#f85149';
          $m2=[];
          preg_match('/^#([0-9a-f]{6})$/i',$sc2,$m2);
          $sbg='rgba('.hexdec(substr($m2[1],0,2)).','.hexdec(substr($m2[1],2,2)).','.hexdec(substr($m2[1],4,2)).',.15)';
          ?>
        <tr>
          <td style="color:#484f58;white-space:nowrap;font-size:.75rem"><?php echo $l['created_at']?date('M j, Y H:i:s',strtotime($l['created_at'])):'—';?></td>
          <td style="color:#c9d1d9;font-family:monospace;font-size:.76rem"><?php echo htmlspecialchars($l['username']??'System');?></td>
          <td style="color:#8b949e;font-size:.76rem"><?php echo htmlspecialchars($l['action']??'—');?></td>
          <td style="color:#8b949e;font-size:.76rem"><?php echo htmlspecialchars($l['module']??'—');?></td>
          <td style="color:#484f58;font-size:.75rem"><?php echo htmlspecialchars($l['entity_type']??'—').($l['entity_id']?" #".$l['entity_id']:'');?></td>
          <td><span class="log-act" style="color:<?php echo $sc2;?>;background:<?php echo $sbg;?>"><?php echo htmlspecialchars($l['status']??'—');?></span></td>
          <td style="color:#484f58;font-size:.74rem;font-family:monospace"><?php echo htmlspecialchars($l['ip_address']??'—');?></td>
        </tr>
        <?php endforeach;?>
        </tbody>
      </table>
    </div>
    <?php else:?><div style="padding:2rem;text-align:center;color:#8b949e">No system logs</div><?php endif;?>
  </div>
</div>

<!-- ══════════════════════════ SECURITY TAB ══════════════════════════ -->
<div class="sct <?php echo $activeTab==='security'?'active':'';?>" id="sct-security">
  <div class="sec-2col">

    <!-- Account Info -->
    <div>
      <div class="sec" style="margin-bottom:1.1rem">
        <div class="sec-hdr"><h3><i class="bi bi-person-circle" style="color:#3fb950"></i> Account Info</h3></div>
        <div style="padding:1rem 1.2rem">
          <div class="info-grid">
            <div class="info-row"><span class="info-lbl">Full Name</span><span class="info-val"><?php echo htmlspecialchars(($me['first_name']??'').' '.($me['last_name']??''));?></span></div>
            <div class="info-row"><span class="info-lbl">Username</span><span class="info-val" style="font-family:monospace">@<?php echo htmlspecialchars($me['username']??'');?></span></div>
            <div class="info-row"><span class="info-lbl">Email</span><span class="info-val"><?php echo htmlspecialchars($me['email']??'—');?></span></div>
            <div class="info-row"><span class="info-lbl">Role</span><span class="info-val"><?php echo stbg($me['role']??'', $roleColors);?></span></div>
            <div class="info-row"><span class="info-lbl">Account Status</span><span class="info-val">
              <?php echo $me['is_active']?"<span style='color:#3fb950;font-weight:600'>Active</span>":"<span style='color:#f85149;font-weight:600'>Inactive</span>";?></span></div>
            <div class="info-row"><span class="info-lbl">Member Since</span><span class="info-val"><?php echo $me['created_at']?date('M j, Y',strtotime($me['created_at'])):'—';?></span></div>
            <div class="info-row"><span class="info-lbl">Last Login</span><span class="info-val"><?php echo $me['last_login']?date('M j, Y H:i',strtotime($me['last_login'])):'Never';?></span></div>
            <div class="info-row"><span class="info-lbl">Facility</span><span class="info-val"><?php
              $mf=fetchOne("SELECT name FROM facilities WHERE id=?",[$me['facility_id']??0],'i');
              echo htmlspecialchars($mf['name']??'—');?></span></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Change Password -->
    <div>
      <div class="sec">
        <div class="sec-hdr"><h3><i class="bi bi-key-fill" style="color:#f85149"></i> Change Password</h3></div>
        <div style="padding:1rem 1.2rem">
          <form method="POST" onsubmit="return validatePw()">
            <input type="hidden" name="_action" value="change_password">
            <div class="fld">
              <label>Current Password</label>
              <div class="pw-wrap">
                <input type="password" name="old_password" id="old_pw" placeholder="Your current password" autocomplete="current-password">
                <button type="button" class="pw-eye" onclick="togglePw('old_pw',this)"><i class="bi bi-eye"></i></button>
              </div>
            </div>
            <div class="fld">
              <label>New Password</label>
              <div class="pw-wrap">
                <input type="password" name="new_password" id="new_pw" placeholder="At least 6 characters" autocomplete="new-password">
                <button type="button" class="pw-eye" onclick="togglePw('new_pw',this)"><i class="bi bi-eye"></i></button>
              </div>
            </div>
            <div class="fld">
              <label>Confirm New Password</label>
              <div class="pw-wrap">
                <input type="password" name="confirm_password" id="conf_pw" placeholder="Repeat new password" autocomplete="new-password">
                <button type="button" class="pw-eye" onclick="togglePw('conf_pw',this)"><i class="bi bi-eye"></i></button>
              </div>
            </div>
            <div id="pw-err" style="color:#f85149;font-size:.78rem;margin-bottom:.7rem;display:none"></div>
            <button type="submit" class="btn-pr" style="width:100%;justify-content:center"><i class="bi bi-shield-lock-fill"></i> Update Password</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Session info -->
  <div class="sec">
    <div class="sec-hdr"><h3><i class="bi bi-info-circle-fill" style="color:#8b949e"></i> Session Information</h3></div>
    <div style="padding:1rem 1.2rem">
      <div class="info-grid">
        <div class="info-row"><span class="info-lbl">Session ID</span><span class="info-val" style="font-family:monospace;font-size:.72rem;color:#484f58"><?php echo substr(session_id(),0,20).'…';?></span></div>
        <div class="info-row"><span class="info-lbl">Server Time</span><span class="info-val"><?php echo date('M j, Y H:i:s');?></span></div>
        <div class="info-row"><span class="info-lbl">PHP Version</span><span class="info-val" style="font-family:monospace"><?php echo PHP_VERSION;?></span></div>
        <div class="info-row"><span class="info-lbl">User Agent</span><span class="info-val" style="font-size:.72rem;word-break:break-all"><?php echo htmlspecialchars(substr($_SERVER['HTTP_USER_AGENT']??'Unknown',0,60));?>…</span></div>
      </div>
      <div style="margin-top:1rem">
        <a href="<?php echo APP_URL;?>/api/auth/logout.php" class="btn-ghost" style="color:#f85149;border-color:#f85149">
          <i class="bi bi-box-arrow-right"></i> Sign Out
        </a>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════ MODALS ══════════════════════ -->

<!-- Create Facility -->
<div class="modal-ov" id="m-fac-create">
  <div class="modal-box">
    <div class="modal-hdr">
      <h4><i class="bi bi-building-add" style="color:#388bfd"></i> Add Facility</h4>
      <button class="modal-close" onclick="closeModal('m-fac-create')"><i class="bi bi-x-lg"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="_action" value="facility_create">
      <div class="modal-body">
        <div class="frow">
          <div class="fld"><label>Facility Name*</label><input type="text" name="name" required placeholder="e.g. Eastern Prison"></div>
          <div class="fld"><label>Type*</label>
            <select name="type">
              <option value="PRISON">Prison</option>
              <option value="ADMIN_OFFICE">Admin Office</option>
              <option value="HOLDING_CENTER">Holding Center</option>
            </select>
          </div>
        </div>
        <div class="fld"><label>Location*</label><input type="text" name="location" required placeholder="City / District"></div>
        <div class="frow">
          <div class="fld"><label>Capacity*</label><input type="number" name="total_capacity" min="1" required placeholder="500"></div>
          <div class="fld"><label>Status</label>
            <select name="status">
              <option value="ACTIVE">Active</option>
              <option value="INACTIVE">Inactive</option>
              <option value="UNDER_MAINTENANCE">Under Maintenance</option>
            </select>
          </div>
        </div>
        <div class="frow">
          <div class="fld"><label>Contact Person</label><input type="text" name="contact_person" placeholder="Full name"></div>
          <div class="fld"><label>Contact Email</label><input type="email" name="contact_email" placeholder="email@domain.com"></div>
        </div>
        <div class="fld"><label>Contact Phone</label><input type="text" name="contact_phone" placeholder="555-XXXX"></div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn-cancel" onclick="closeModal('m-fac-create')">Cancel</button>
        <button type="submit" class="btn-sub"><i class="bi bi-plus-lg"></i> Create</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Facility -->
<div class="modal-ov" id="m-fac-edit">
  <div class="modal-box">
    <div class="modal-hdr">
      <h4><i class="bi bi-building-gear" style="color:#f39c12"></i> Edit Facility</h4>
      <button class="modal-close" onclick="closeModal('m-fac-edit')"><i class="bi bi-x-lg"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="_action" value="facility_edit">
      <input type="hidden" name="facility_id" id="ef-id">
      <div class="modal-body">
        <div class="frow">
          <div class="fld"><label>Facility Name*</label><input type="text" name="name" id="ef-name" required></div>
          <div class="fld"><label>Type*</label>
            <select name="type" id="ef-type">
              <option value="PRISON">Prison</option>
              <option value="ADMIN_OFFICE">Admin Office</option>
              <option value="HOLDING_CENTER">Holding Center</option>
            </select>
          </div>
        </div>
        <div class="fld"><label>Location*</label><input type="text" name="location" id="ef-loc" required></div>
        <div class="frow">
          <div class="fld"><label>Capacity*</label><input type="number" name="total_capacity" id="ef-cap" min="1" required></div>
          <div class="fld"><label>Status</label>
            <select name="status" id="ef-status">
              <option value="ACTIVE">Active</option>
              <option value="INACTIVE">Inactive</option>
              <option value="UNDER_MAINTENANCE">Under Maintenance</option>
            </select>
          </div>
        </div>
        <div class="frow">
          <div class="fld"><label>Contact Person</label><input type="text" name="contact_person" id="ef-cp"></div>
          <div class="fld"><label>Contact Email</label><input type="email" name="contact_email" id="ef-ce"></div>
        </div>
        <div class="fld"><label>Contact Phone</label><input type="text" name="contact_phone" id="ef-cph"></div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn-cancel" onclick="closeModal('m-fac-edit')">Cancel</button>
        <button type="submit" class="btn-sub"><i class="bi bi-check-lg"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Facility confirm -->
<div class="modal-ov" id="m-fac-del">
  <div class="modal-box" style="max-width:400px">
    <div class="modal-hdr">
      <h4><i class="bi bi-exclamation-triangle-fill" style="color:#f85149"></i> Delete Facility</h4>
      <button class="modal-close" onclick="closeModal('m-fac-del')"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="modal-body">
      <p style="color:#c9d1d9;font-size:.85rem;margin:0 0 .5rem">Are you sure you want to remove <strong id="del-fac-name" style="color:#f85149"></strong>?</p>
      <p style="color:#8b949e;font-size:.78rem;margin:0">This action cannot be undone. Associated records (staff, inmates) will not be deleted.</p>
    </div>
    <form method="POST">
      <input type="hidden" name="_action" value="facility_delete">
      <input type="hidden" name="facility_id" id="del-fac-id">
      <div class="modal-foot">
        <button type="button" class="btn-cancel" onclick="closeModal('m-fac-del')">Cancel</button>
        <button type="submit" class="btn-sub danger"><i class="bi bi-trash3-fill"></i> Delete</button>
      </div>
    </form>
  </div>
</div>

<!-- Create User -->
<div class="modal-ov" id="m-usr-create">
  <div class="modal-box">
    <div class="modal-hdr">
      <h4><i class="bi bi-person-plus-fill" style="color:#3fb950"></i> Add User</h4>
      <button class="modal-close" onclick="closeModal('m-usr-create')"><i class="bi bi-x-lg"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="_action" value="user_create">
      <div class="modal-body">
        <div class="frow">
          <div class="fld"><label>First Name*</label><input type="text" name="first_name" required placeholder="John"></div>
          <div class="fld"><label>Last Name*</label><input type="text" name="last_name" required placeholder="Doe"></div>
        </div>
        <div class="frow">
          <div class="fld"><label>Username*</label><input type="text" name="username" required placeholder="john_doe"></div>
          <div class="fld"><label>Email*</label><input type="email" name="email" required placeholder="john@prison.gov"></div>
        </div>
        <div class="frow">
          <div class="fld"><label>Role*</label>
            <select name="role">
              <?php foreach($allRoles as $r):?><option value="<?php echo $r;?>"><?php echo $r;?></option><?php endforeach;?>
            </select>
          </div>
          <div class="fld"><label>Facility*</label>
            <select name="facility_id">
              <?php foreach($allFacilities as $f):?><option value="<?php echo $f['id'];?>"><?php echo htmlspecialchars($f['name']);?></option><?php endforeach;?>
            </select>
          </div>
        </div>
        <div class="fld"><label>Password* (min 6 chars)</label>
          <div class="pw-wrap">
            <input type="password" name="password" id="cu-pw" placeholder="Set initial password" autocomplete="new-password">
            <button type="button" class="pw-eye" onclick="togglePw('cu-pw',this)"><i class="bi bi-eye"></i></button>
          </div>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn-cancel" onclick="closeModal('m-usr-create')">Cancel</button>
        <button type="submit" class="btn-sub"><i class="bi bi-person-plus-fill"></i> Create User</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit User -->
<div class="modal-ov" id="m-usr-edit">
  <div class="modal-box">
    <div class="modal-hdr">
      <h4><i class="bi bi-person-gear" style="color:#f39c12"></i> Edit User</h4>
      <button class="modal-close" onclick="closeModal('m-usr-edit')"><i class="bi bi-x-lg"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="_action" value="user_edit">
      <input type="hidden" name="user_id" id="eu-id">
      <div class="modal-body">
        <div class="frow">
          <div class="fld"><label>First Name*</label><input type="text" name="first_name" id="eu-fn" required></div>
          <div class="fld"><label>Last Name*</label><input type="text" name="last_name" id="eu-ln" required></div>
        </div>
        <div class="fld"><label>Email*</label><input type="email" name="email" id="eu-em" required></div>
        <div class="frow">
          <div class="fld"><label>Role*</label>
            <select name="role" id="eu-role">
              <?php foreach($allRoles as $r):?><option value="<?php echo $r;?>"><?php echo $r;?></option><?php endforeach;?>
            </select>
          </div>
          <div class="fld"><label>Facility*</label>
            <select name="facility_id" id="eu-fid">
              <?php foreach($allFacilities as $f):?><option value="<?php echo $f['id'];?>"><?php echo htmlspecialchars($f['name']);?></option><?php endforeach;?>
            </select>
          </div>
        </div>
        <div class="fld"><label>New Password <span style="color:#484f58">(leave blank to keep current)</span></label>
          <div class="pw-wrap">
            <input type="password" name="password" id="eu-pw" placeholder="Leave blank to keep unchanged" autocomplete="new-password">
            <button type="button" class="pw-eye" onclick="togglePw('eu-pw',this)"><i class="bi bi-eye"></i></button>
          </div>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn-cancel" onclick="closeModal('m-usr-edit')">Cancel</button>
        <button type="submit" class="btn-sub"><i class="bi bi-check-lg"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete User confirm -->
<div class="modal-ov" id="m-usr-del">
  <div class="modal-box" style="max-width:400px">
    <div class="modal-hdr">
      <h4><i class="bi bi-exclamation-triangle-fill" style="color:#f85149"></i> Remove User</h4>
      <button class="modal-close" onclick="closeModal('m-usr-del')"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="modal-body">
      <p style="color:#c9d1d9;font-size:.85rem;margin:0 0 .5rem">Remove user <strong id="del-usr-name" style="color:#f85149"></strong>?</p>
      <p style="color:#8b949e;font-size:.78rem;margin:0">This will soft-delete the account. The user will no longer be able to log in.</p>
    </div>
    <form method="POST">
      <input type="hidden" name="_action" value="user_delete">
      <input type="hidden" name="user_id" id="del-usr-id">
      <div class="modal-foot">
        <button type="button" class="btn-cancel" onclick="closeModal('m-usr-del')">Cancel</button>
        <button type="submit" class="btn-sub danger"><i class="bi bi-trash3-fill"></i> Remove</button>
      </div>
    </form>
  </div>
</div>

<script>
/* ── Tab ── */
var activeTab='<?php echo $activeTab;?>';
var tabColors={facilities:'#388bfd',users:'#3fb950',logs:'#8b949e',security:'#f85149'};
function switchTab(tab){
  document.querySelectorAll('.sct').forEach(s=>s.classList.remove('active'));
  document.querySelectorAll('.tab-lnk').forEach(l=>{l.classList.remove('on');l.style.color='';});
  var s=document.getElementById('sct-'+tab);
  if(s) s.classList.add('active');
  document.querySelectorAll('.tab-lnk').forEach(l=>{
    if(l.getAttribute('href')==='?tab='+tab){l.classList.add('on');l.style.color=tabColors[tab]||'';}
  });
  activeTab=tab;
  history.replaceState(null,'','?tab='+tab);
}

/* ── Modal ── */
function openModal(id){document.getElementById(id).classList.add('open');}
function closeModal(id){document.getElementById(id).classList.remove('open');}
document.querySelectorAll('.modal-ov').forEach(function(m){
  m.addEventListener('click',function(e){if(e.target===this)this.classList.remove('open');});
});
document.addEventListener('keydown',function(e){if(e.key==='Escape')document.querySelectorAll('.modal-ov.open').forEach(m=>m.classList.remove('open'));});

/* ── Facility edit populate ── */
function openFacEdit(f){
  document.getElementById('ef-id').value=f.id;
  document.getElementById('ef-name').value=f.name||'';
  document.getElementById('ef-type').value=f.type||'PRISON';
  document.getElementById('ef-loc').value=f.location||'';
  document.getElementById('ef-cap').value=f.total_capacity||'';
  document.getElementById('ef-status').value=f.status||'ACTIVE';
  document.getElementById('ef-cp').value=f.contact_person||'';
  document.getElementById('ef-ce').value=f.contact_email||'';
  document.getElementById('ef-cph').value=f.contact_phone||'';
  openModal('m-fac-edit');
}
function confirmFacDel(id,name){
  document.getElementById('del-fac-id').value=id;
  document.getElementById('del-fac-name').textContent=name;
  openModal('m-fac-del');
}

/* ── User edit populate ── */
function openUsrEdit(u){
  document.getElementById('eu-id').value=u.id;
  document.getElementById('eu-fn').value=u.first_name||'';
  document.getElementById('eu-ln').value=u.last_name||'';
  document.getElementById('eu-em').value=u.email||'';
  document.getElementById('eu-role').value=u.role||'OFFICER';
  document.getElementById('eu-fid').value=u.facility_id||'';
  document.getElementById('eu-pw').value='';
  openModal('m-usr-edit');
}
function confirmUsrDel(id,uname){
  document.getElementById('del-usr-id').value=id;
  document.getElementById('del-usr-name').textContent='@'+uname;
  openModal('m-usr-del');
}

/* ── Table search ── */
function filterTable(inp,tableId){
  var q=inp.value.trim().toLowerCase();
  var tbl=document.getElementById(tableId);
  if(!tbl) return;
  var rows=tbl.querySelectorAll('tbody tr');
  var shown=0;
  rows.forEach(function(row){
    var txt=row.textContent.toLowerCase();
    if(!q||txt.includes(q)){row.classList.remove('hidden-row');shown++;}
    else row.classList.add('hidden-row');
  });
  var cnt=document.getElementById('cnt-'+tableId);
  if(cnt) cnt.textContent=shown+' shown';
}

/* ── CSV export ── */
function exportCSV(tableId,filename){
  var tbl=document.getElementById(tableId);
  if(!tbl) return;
  var rows=Array.from(tbl.querySelectorAll('tr')).filter(r=>!r.classList.contains('hidden-row'));
  var csv=rows.map(function(row){
    return Array.from(row.querySelectorAll('th,td')).map(function(cell){
      return '"'+cell.innerText.replace(/\n/g,' ').replace(/"/g,'""').trim()+'"';
    }).join(',');
  }).join('\n');
  var blob=new Blob([csv],{type:'text/csv;charset=utf-8;'});
  var a=document.createElement('a');
  a.href=URL.createObjectURL(blob);
  a.download=filename+'_<?php echo date('Ymd');?>.csv';
  document.body.appendChild(a);a.click();document.body.removeChild(a);
}

/* ── Password toggle ── */
function togglePw(id,btn){
  var inp=document.getElementById(id);
  var show=inp.type==='password';
  inp.type=show?'text':'password';
  btn.querySelector('i').className='bi '+(show?'bi-eye-slash':'bi-eye');
}

/* ── Validate change password ── */
function validatePw(){
  var np=document.getElementById('new_pw').value;
  var cp=document.getElementById('conf_pw').value;
  var err=document.getElementById('pw-err');
  if(np.length>0&&np.length<6){err.style.display='block';err.textContent='Password must be at least 6 characters.';return false;}
  if(np&&np!==cp){err.style.display='block';err.textContent='Passwords do not match.';return false;}
  err.style.display='none';return true;
}
</script>

<?php require_once '../../includes/footer.php';?>
