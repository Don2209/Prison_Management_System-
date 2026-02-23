<?php
/**
 * Inmate Transfers – Full management page
 */
$pageTitle = 'Inmate Transfers';
require_once '../../includes/header.php';
requireAuth();
verifyPermission('view', 'inmates');

$isSA    = isSuperAdmin();
$fid     = getCurrentFacility();
$uid     = getCurrentUserId();
$canEdit = hasPermission('create', 'inmates') || $isSA
        || in_array(getCurrentUserRole(), ['FACILITY_ADMIN','SUPER_ADMIN','RECORDS_OFFICER']);
$canApprove = $isSA || in_array(getCurrentUserRole(), ['FACILITY_ADMIN','SUPER_ADMIN']);

/* ── Flash message ── */
$flash = ['type' => '', 'msg' => ''];

/* ══════════════════════════════════════════════════════════════
   POST ACTION HANDLERS
   ══════════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');

    /* ── Create transfer request ── */
    if ($action === 'create' && $canEdit) {
        $inmate_id   = (int)($_POST['inmate_id']   ?? 0);
        $facility_to = (int)($_POST['facility_to'] ?? 0);
        $reason      = trim($_POST['reason']       ?? '');
        $tdate       = trim($_POST['transfer_date'] ?? date('Y-m-d'));
        $notes       = trim($_POST['notes']        ?? '');

        // Determine from-facility: use inmate's current facility
        $inmate_row = fetchOne("SELECT facility_id, first_name, last_name FROM inmates WHERE id=? AND deleted_at IS NULL", [$inmate_id], 'i');
        $facility_from = $inmate_row ? (int)$inmate_row['facility_id'] : ($isSA ? 0 : $fid);

        if ($inmate_row && $facility_to && $facility_to !== $facility_from && $reason) {
            try {
                executeQuery(
                    "INSERT INTO inmate_transfers (facility_from, facility_to, inmate_id, reason, transfer_date, approval_status, notes, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, 'PENDING', ?, NOW(), NOW())",
                    [$facility_from, $facility_to, $inmate_id, $reason, $tdate, $notes],
                    'iiisss'
                );
                $flash = ['type' => 'success', 'msg' => 'Transfer request submitted successfully.'];
            } catch (Exception $e) {
                $flash = ['type' => 'error', 'msg' => 'Failed to create transfer: ' . $e->getMessage()];
            }
        } else {
            $flash = ['type' => 'error', 'msg' => 'Invalid transfer request. Check inmate, destination facility, and reason.'];
        }

    /* ── Approve transfer ── */
    } elseif ($action === 'approve' && $canApprove) {
        $tid = (int)($_POST['transfer_id'] ?? 0);
        $tr = fetchOne("SELECT * FROM inmate_transfers WHERE id=? AND deleted_at IS NULL", [$tid], 'i');
        if ($tr && $tr['approval_status'] === 'PENDING') {
            try {
                executeQuery(
                    "UPDATE inmate_transfers SET approval_status='APPROVED', approved_by=?, approved_date=NOW(), updated_at=NOW() WHERE id=?",
                    [$uid, $tid], 'ii'
                );
                // Update the inmate's facility
                executeQuery(
                    "UPDATE inmates SET facility_id=?, status='TRANSFERRED', updated_at=NOW() WHERE id=?",
                    [$tr['facility_to'], $tr['inmate_id']], 'ii'
                );
                $flash = ['type' => 'success', 'msg' => 'Transfer approved and inmate record updated.'];
            } catch (Exception $e) {
                $flash = ['type' => 'error', 'msg' => 'Approval failed: ' . $e->getMessage()];
            }
        } else {
            $flash = ['type' => 'error', 'msg' => 'Transfer not found or already processed.'];
        }

    /* ── Reject transfer ── */
    } elseif ($action === 'reject' && $canApprove) {
        $tid         = (int)($_POST['transfer_id']   ?? 0);
        $reject_note = trim($_POST['reject_notes']   ?? 'Rejected by administrator.');
        $tr = fetchOne("SELECT * FROM inmate_transfers WHERE id=? AND deleted_at IS NULL", [$tid], 'i');
        if ($tr && $tr['approval_status'] === 'PENDING') {
            try {
                executeQuery(
                    "UPDATE inmate_transfers SET approval_status='REJECTED', approved_by=?, approved_date=NOW(), notes=CONCAT(COALESCE(notes,''),' | Rejected: ', ?), updated_at=NOW() WHERE id=?",
                    [$uid, $reject_note, $tid], 'isi'
                );
                $flash = ['type' => 'success', 'msg' => 'Transfer request rejected.'];
            } catch (Exception $e) {
                $flash = ['type' => 'error', 'msg' => 'Rejection failed: ' . $e->getMessage()];
            }
        } else {
            $flash = ['type' => 'error', 'msg' => 'Transfer not found or already processed.'];
        }

    /* ── Delete / cancel ── */
    } elseif ($action === 'delete' && $canEdit) {
        $tid = (int)($_POST['transfer_id'] ?? 0);
        $tr  = fetchOne("SELECT * FROM inmate_transfers WHERE id=? AND deleted_at IS NULL", [$tid], 'i');
        if ($tr && $tr['approval_status'] === 'PENDING') {
            try {
                executeQuery("UPDATE inmate_transfers SET deleted_at=NOW() WHERE id=?", [$tid], 'i');
                $flash = ['type' => 'success', 'msg' => 'Transfer request cancelled.'];
            } catch (Exception $e) {
                $flash = ['type' => 'error', 'msg' => 'Could not cancel transfer.'];
            }
        }
    }
}

/* ══════════════════════════════════════════════════════════════
   DATA FETCHING
   ══════════════════════════════════════════════════════════════ */

/* ── Filters ── */
$filterStatus  = $_GET['status']  ?? '';
$filterFac     = (int)($_GET['fid']    ?? 0);
$filterSearch  = trim($_GET['q']      ?? '');
$filterFrom    = $_GET['date_from']   ?? '';
$filterTo      = $_GET['date_to']     ?? '';

/* ── Base WHERE ── */
$whereParts = ["t.deleted_at IS NULL"];
$params     = [];
$types      = '';

if (!$isSA) {
    $whereParts[] = "(t.facility_from=? OR t.facility_to=?)";
    $params[]     = $fid; $params[] = $fid;
    $types       .= 'ii';
} elseif ($filterFac) {
    $whereParts[] = "(t.facility_from=? OR t.facility_to=?)";
    $params[]     = $filterFac; $params[] = $filterFac;
    $types       .= 'ii';
}
if ($filterStatus) {
    $whereParts[] = "t.approval_status=?"; $params[] = $filterStatus; $types .= 's';
}
if ($filterSearch) {
    $whereParts[] = "(i.first_name LIKE ? OR i.last_name LIKE ? OR i.inmate_id LIKE ?)";
    $s = "%$filterSearch%";
    $params[] = $s; $params[] = $s; $params[] = $s;
    $types   .= 'sss';
}
if ($filterFrom) { $whereParts[] = "t.transfer_date >= ?"; $params[] = $filterFrom; $types .= 's'; }
if ($filterTo)   { $whereParts[] = "t.transfer_date <= ?"; $params[] = $filterTo;   $types .= 's'; }

$where = implode(' AND ', $whereParts);

/* ── Transfers ── */
$transfers = fetchAll(
    "SELECT t.*,
            i.first_name, i.last_name, i.inmate_id AS inmate_num,
            i.risk_classification,
            ff.name AS from_name, ft.name AS to_name,
            CONCAT(u.first_name,' ',u.last_name) AS approver_name
     FROM inmate_transfers t
     JOIN inmates i    ON t.inmate_id    = i.id
     JOIN facilities ff ON t.facility_from = ff.id
     JOIN facilities ft ON t.facility_to   = ft.id
     LEFT JOIN users u  ON t.approved_by   = u.id
     WHERE $where
     ORDER BY t.created_at DESC",
    $params, $types
);

/* ── KPI counts ── */
$kpiBase  = $isSA ? "t.deleted_at IS NULL" : "(t.facility_from=$fid OR t.facility_to=$fid) AND t.deleted_at IS NULL";
$kpiTotal   = (int)(fetchOne("SELECT COUNT(*) c FROM inmate_transfers t WHERE $kpiBase")['c'] ?? 0);
$kpiPending = (int)(fetchOne("SELECT COUNT(*) c FROM inmate_transfers t WHERE $kpiBase AND approval_status='PENDING'")['c'] ?? 0);
$kpiApproved= (int)(fetchOne("SELECT COUNT(*) c FROM inmate_transfers t WHERE $kpiBase AND approval_status='APPROVED'")['c'] ?? 0);
$kpiRejected= (int)(fetchOne("SELECT COUNT(*) c FROM inmate_transfers t WHERE $kpiBase AND approval_status='REJECTED'")['c'] ?? 0);
$kpiMonth   = (int)(fetchOne("SELECT COUNT(*) c FROM inmate_transfers t WHERE $kpiBase AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")['c'] ?? 0);

/* ── Facility list for selects ── */
$allFacilities = getAccessibleFacilities();

/* ── Inmates for the create-transfer dropdown (active only) ── */
if ($isSA) {
    $inmateList = fetchAll(
        "SELECT id, inmate_id, first_name, last_name, facility_id,
                (SELECT name FROM facilities WHERE id=inmates.facility_id) AS facility_name
         FROM inmates WHERE status IN ('REMAND','CONVICTED') AND deleted_at IS NULL ORDER BY first_name LIMIT 500"
    );
} else {
    $inmateList = fetchAll(
        "SELECT id, inmate_id, first_name, last_name, facility_id,
                (SELECT name FROM facilities WHERE id=inmates.facility_id) AS facility_name
         FROM inmates WHERE facility_id=? AND status IN ('REMAND','CONVICTED') AND deleted_at IS NULL ORDER BY first_name",
        [$fid], 'i'
    );
}
?>
<!-- ══════════════════════════════════════════════════════════════
     STYLES
     ══════════════════════════════════════════════════════════════ -->
<style>
:root{--sur:#161b22;--bdr:#21262d;--bg:#0d1117;--txt:#e6edf3;--mut:#8b949e;--acc:#1f6feb;--acc2:#388bfd}

/* KPI grid */
.kpi-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:14px;margin-bottom:1.5rem}
.kpi{background:var(--sur);border:1px solid var(--bdr);border-radius:14px;padding:18px 16px 14px;position:relative;overflow:hidden;transition:transform .2s,box-shadow .2s,border-color .2s}
.kpi::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--kpi-bar,#388bfd);border-radius:14px 14px 0 0}
.kpi:hover{transform:translateY(-3px);box-shadow:0 10px 28px rgba(0,0,0,.4);border-color:var(--kpi-clr,#388bfd)}
.kpi-ico{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;margin-bottom:10px}
.kpi-val{font-size:1.75rem;font-weight:800;color:var(--txt);line-height:1}
.kpi-lbl{font-size:.72rem;color:var(--mut);text-transform:uppercase;letter-spacing:.07em;margin-top:3px}

/* Filter bar */
.filter-bar{background:var(--sur);border:1px solid var(--bdr);border-radius:12px;padding:14px 16px;margin-bottom:1.25rem;display:flex;flex-wrap:wrap;gap:.6rem;align-items:center}
.filter-bar .fi{background:#0d1117;border:1px solid #30363d;border-radius:8px;color:var(--txt);padding:.4rem .75rem;font-size:.82rem;outline:none}
.filter-bar .fi:focus{border-color:var(--acc2)}
.search-wrap{position:relative;flex:1;min-width:180px}
.search-wrap i{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--mut);font-size:.8rem;pointer-events:none}
.search-wrap input{padding-left:28px;width:100%}

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
.chip-pending  {background:rgba(243,156,18,.18); color:#f39c12}
.chip-approved {background:rgba(63,185,80,.18);  color:#3fb950}
.chip-rejected {background:rgba(248,81,73,.18);  color:#f85149}
.chip-low      {background:rgba(63,185,80,.15);  color:#3fb950}
.chip-medium   {background:rgba(210,153,34,.15); color:#d29922}
.chip-high     {background:rgba(248,81,73,.15);  color:#f85149}
.chip-maximum  {background:rgba(185,28,28,.2);   color:#ff6b6b}

/* Avatar */
.av{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#1f6feb,#388bfd);display:inline-flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:700;color:#fff;flex-shrink:0}

/* Buttons */
.btn-act{display:inline-flex;align-items:center;gap:.3rem;padding:.3rem .7rem;border-radius:7px;font-size:.76rem;font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:all .18s;white-space:nowrap}
.btn-view   {background:#21262d;color:#8b949e}   .btn-view:hover   {background:#30363d;color:#e6edf3}
.btn-approve{background:rgba(63,185,80,.15);color:#3fb950}.btn-approve:hover{background:rgba(63,185,80,.28)}
.btn-reject {background:rgba(248,81,73,.12);color:#f85149}.btn-reject:hover {background:rgba(248,81,73,.25)}
.btn-delete {background:rgba(248,81,73,.08);color:#8b949e}.btn-delete:hover {color:#f85149}
.btn-primary-sm{background:linear-gradient(135deg,#1f6feb,#388bfd);color:#fff;padding:.45rem 1rem;border-radius:8px;font-size:.85rem;font-weight:600;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;transition:opacity .18s}
.btn-primary-sm:hover{opacity:.88}

/* Flash banner */
.flash{display:flex;align-items:center;gap:.6rem;padding:.75rem 1rem;border-radius:10px;font-size:.85rem;margin-bottom:1rem;animation:fIn .3s ease}
.flash-success{background:rgba(63,185,80,.12);border:1px solid rgba(63,185,80,.3);color:#3fb950}
.flash-error  {background:rgba(248,81,73,.12);border:1px solid rgba(248,81,73,.3);color:#f85149}
@keyframes fIn{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}

/* Modals */
.modal-backdrop-custom{position:fixed;inset:0;background:rgba(0,0,0,.7);backdrop-filter:blur(4px);z-index:1050;display:none;align-items:center;justify-content:center;padding:1rem}
.modal-backdrop-custom.open{display:flex}
.modal-box{background:#161b22;border:1px solid #30363d;border-radius:14px;width:100%;max-width:560px;max-height:92vh;overflow-y:auto;animation:mIn .22s ease}
.modal-box.wide{max-width:700px}
@keyframes mIn{from{opacity:0;transform:scale(.96)}to{opacity:1;transform:scale(1)}}
.modal-head{display:flex;align-items:center;justify-content:space-between;padding:1rem 1.25rem;border-bottom:1px solid #21262d;flex-shrink:0}
.modal-head h3{margin:0;font-size:1rem;font-weight:700;color:#e6edf3;display:flex;align-items:center;gap:.5rem}
.modal-body{padding:1.25rem}
.modal-foot{display:flex;justify-content:flex-end;gap:.5rem;padding:1rem 1.25rem;border-top:1px solid #21262d}
.btn-close-modal{background:none;border:none;color:#8b949e;font-size:1.1rem;cursor:pointer;padding:.2rem;line-height:1}
.btn-close-modal:hover{color:#e6edf3}

/* Form elements inside modal */
.f-row{display:grid;gap:1rem;margin-bottom:1rem}
.f-row.two{grid-template-columns:1fr 1fr}
@media(max-width:500px){.f-row.two{grid-template-columns:1fr}}
.f-group{display:flex;flex-direction:column;gap:.3rem}
.f-label{font-size:.78rem;font-weight:600;color:#8b949e;letter-spacing:.02em}
.f-label .req{color:#f85149}
.fi{background:#0d1117;border:1px solid #30363d;border-radius:8px;color:#e6edf3;padding:.55rem .85rem;font-size:.875rem;width:100%;outline:none;transition:border-color .2s,box-shadow .2s;font-family:inherit}
.fi::placeholder{color:#484f58}
.fi:focus{border-color:#388bfd;box-shadow:0 0 0 3px rgba(56,139,253,.15)}
select.fi{cursor:pointer;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='%238b949e' d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right .75rem center;background-size:12px;padding-right:2.25rem;appearance:none}
.fi-area{resize:vertical;min-height:80px}

/* Detail view card */
.detail-row{display:flex;justify-content:space-between;align-items:flex-start;padding:.55rem 0;border-bottom:1px solid #21262d;font-size:.85rem}
.detail-row:last-child{border-bottom:none}
.detail-key{color:#8b949e;font-size:.78rem;min-width:130px}
.detail-val{color:#e6edf3;text-align:right;word-break:break-word;max-width:300px}

/* Transfer route arrow */
.route-box{display:flex;align-items:center;gap:.6rem;background:#0d1117;border:1px solid #21262d;border-radius:10px;padding:.75rem 1rem;margin:.75rem 0}
.route-fac{flex:1;text-align:center}
.route-fac .rf-label{font-size:.65rem;text-transform:uppercase;letter-spacing:.06em;color:#8b949e}
.route-fac .rf-name{font-size:.88rem;font-weight:700;color:#e6edf3;margin-top:2px}
.route-arrow{font-size:1.25rem;color:#388bfd;flex-shrink:0}

/* Empty state */
.empty-state{text-align:center;padding:4rem 2rem;color:#8b949e}
.empty-state i{font-size:2.5rem;display:block;margin-bottom:.75rem;opacity:.4}

/* Page header */
.ph{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;flex-wrap:wrap;gap:.75rem}
.ph h1{font-size:1.35rem;font-weight:700;color:#e6edf3;margin:0}
.bc{font-size:.78rem;color:#6e7681;display:flex;align-items:center;gap:.3rem;margin-bottom:.3rem}
.bc a{color:#388bfd;text-decoration:none}.bc a:hover{text-decoration:underline}
</style>

<!-- ══════════════════════════════════════════════════════════════
     PAGE HEADER
     ══════════════════════════════════════════════════════════════ -->
<div class="ph">
  <div>
    <div class="bc">
      <a href="<?php echo APP_URL; ?>/modules/inmates/index.php"><i class="bi bi-person-badge"></i> Inmates</a>
      <i class="bi bi-chevron-right" style="font-size:.6rem"></i>
      <span>Transfers</span>
    </div>
    <h1><i class="bi bi-arrow-left-right" style="color:#388bfd;margin-right:.4rem"></i>Inmate Transfers</h1>
  </div>
  <?php if ($canEdit): ?>
  <button class="btn-primary-sm" onclick="openModal('createModal')">
    <i class="bi bi-plus-lg"></i> New Transfer Request
  </button>
  <?php endif; ?>
</div>

<!-- Flash message -->
<?php if ($flash['msg']): ?>
<div class="flash flash-<?php echo $flash['type']; ?>">
  <i class="bi <?php echo $flash['type']==='success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'; ?>"></i>
  <?php echo htmlspecialchars($flash['msg']); ?>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════════════
     KPI CARDS
     ══════════════════════════════════════════════════════════════ -->
<div class="kpi-grid">
  <?php
  $kpis = [
    ['Total',        $kpiTotal,    'bi-arrow-left-right',     '#388bfd', 'rgba(31,111,235,.15)',  '#388bfd'],
    ['Pending',      $kpiPending,  'bi-hourglass-split',      '#f39c12', 'rgba(243,156,18,.15)',  '#f39c12'],
    ['Approved',     $kpiApproved, 'bi-check-circle-fill',    '#3fb950', 'rgba(63,185,80,.15)',   '#3fb950'],
    ['Rejected',     $kpiRejected, 'bi-x-circle-fill',        '#f85149', 'rgba(248,81,73,.15)',   '#f85149'],
    ['This Month',   $kpiMonth,    'bi-calendar3',            '#9b59b6', 'rgba(155,89,182,.15)',  '#9b59b6'],
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

<!-- ══════════════════════════════════════════════════════════════
     FILTER BAR
     ══════════════════════════════════════════════════════════════ -->
<form method="GET" class="filter-bar" id="filterForm">
  <!-- Search -->
  <div class="search-wrap">
    <i class="bi bi-search"></i>
    <input type="text" name="q" class="fi" placeholder="Search inmate name or ID…"
           value="<?php echo htmlspecialchars($filterSearch); ?>">
  </div>

  <!-- Status -->
  <select name="status" class="fi" onchange="this.form.submit()" style="min-width:140px">
    <option value="">All Statuses</option>
    <?php foreach (['PENDING','APPROVED','REJECTED'] as $s): ?>
    <option value="<?php echo $s; ?>" <?php echo $filterStatus===$s?'selected':''; ?>><?php echo ucfirst(strtolower($s)); ?></option>
    <?php endforeach; ?>
  </select>

  <!-- Facility (super admin only) -->
  <?php if ($isSA): ?>
  <select name="fid" class="fi" onchange="this.form.submit()" style="min-width:160px">
    <option value="">All Facilities</option>
    <?php foreach ($allFacilities as $af): ?>
    <option value="<?php echo $af['id']; ?>" <?php echo $filterFac==$af['id']?'selected':''; ?>>
      <?php echo htmlspecialchars($af['name']); ?>
    </option>
    <?php endforeach; ?>
  </select>
  <?php endif; ?>

  <!-- Date range -->
  <input type="date" name="date_from" class="fi" value="<?php echo htmlspecialchars($filterFrom); ?>"
         title="From date" style="min-width:130px" onchange="this.form.submit()">
  <input type="date" name="date_to"   class="fi" value="<?php echo htmlspecialchars($filterTo); ?>"
         title="To date" style="min-width:130px" onchange="this.form.submit()">

  <button type="submit" class="btn-act btn-approve" style="padding:.42rem .9rem;">
    <i class="bi bi-funnel-fill"></i> Filter
  </button>
  <?php if ($filterSearch || $filterStatus || $filterFac || $filterFrom || $filterTo): ?>
  <a href="transfers.php" class="btn-act btn-view"><i class="bi bi-x"></i> Clear</a>
  <?php endif; ?>
</form>

<!-- ══════════════════════════════════════════════════════════════
     TRANSFERS TABLE
     ══════════════════════════════════════════════════════════════ -->
<div class="tbl-wrap">
  <div class="tbl-header">
    <div>
      <div class="tbl-title"><i class="bi bi-table me-2" style="color:#388bfd"></i>Transfer Records</div>
      <div class="tbl-sub"><?php echo count($transfers); ?> record<?php echo count($transfers)!==1?'s':''; ?> found</div>
    </div>
  </div>

  <?php if (empty($transfers)): ?>
  <div class="empty-state">
    <i class="bi bi-arrow-left-right"></i>
    <div style="font-size:.95rem;font-weight:600;color:#e6edf3;margin-bottom:.3rem">No transfers found</div>
    <div style="font-size:.82rem">Try adjusting your filters or create a new transfer request.</div>
  </div>
  <?php else: ?>
  <div class="table-responsive">
  <table class="t">
    <thead>
      <tr>
        <th>#</th>
        <th>Inmate</th>
        <th>From Facility</th>
        <th>To Facility</th>
        <th>Transfer Date</th>
        <th>Reason</th>
        <th>Status</th>
        <th>Requested</th>
        <?php if ($canApprove): ?><th>Approved By</th><?php endif; ?>
        <th style="text-align:right">Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($transfers as $t):
      $riskClr = ['LOW'=>'chip-low','MEDIUM'=>'chip-medium','HIGH'=>'chip-high','MAXIMUM'=>'chip-maximum'][$t['risk_classification']??'LOW'] ?? 'chip-low';
      $stClr   = ['PENDING'=>'chip-pending','APPROVED'=>'chip-approved','REJECTED'=>'chip-rejected'][$t['approval_status']??'PENDING'];
    ?>
    <tr>
      <td style="color:#8b949e;font-size:.78rem;"><?php echo $t['id']; ?></td>
      <td>
        <div style="display:flex;align-items:center;gap:8px">
          <div class="av"><?php echo strtoupper(substr($t['first_name'],0,1).substr($t['last_name'],0,1)); ?></div>
          <div>
            <div style="font-weight:600;font-size:.85rem;"><?php echo htmlspecialchars($t['first_name'].' '.$t['last_name']); ?></div>
            <div style="font-size:.72rem;color:#8b949e;"><?php echo htmlspecialchars($t['inmate_num']); ?></div>
          </div>
        </div>
      </td>
      <td style="font-size:.82rem;">
        <i class="bi bi-geo-alt me-1" style="color:#8b949e"></i><?php echo htmlspecialchars($t['from_name']); ?>
      </td>
      <td style="font-size:.82rem;">
        <i class="bi bi-geo-alt-fill me-1" style="color:#388bfd"></i><?php echo htmlspecialchars($t['to_name']); ?>
      </td>
      <td style="font-size:.82rem;color:#8b949e;white-space:nowrap">
        <?php echo $t['transfer_date'] ? date('M d, Y', strtotime($t['transfer_date'])) : '—'; ?>
      </td>
      <td style="font-size:.82rem;max-width:160px">
        <span title="<?php echo htmlspecialchars($t['reason']??''); ?>" style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:140px">
          <?php echo htmlspecialchars($t['reason'] ?? '—'); ?>
        </span>
      </td>
      <td><span class="chip <?php echo $stClr; ?>"><?php echo ucfirst(strtolower($t['approval_status'])); ?></span></td>
      <td style="font-size:.78rem;color:#8b949e;white-space:nowrap">
        <?php echo $t['created_at'] ? date('M d, Y', strtotime($t['created_at'])) : '—'; ?>
      </td>
      <?php if ($canApprove): ?>
      <td style="font-size:.78rem;color:#8b949e">
        <?php echo $t['approver_name'] ? htmlspecialchars($t['approver_name']) : '—'; ?>
      </td>
      <?php endif; ?>
      <td style="text-align:right;white-space:nowrap">
        <button class="btn-act btn-view" onclick='viewTransfer(<?php echo json_encode($t); ?>)'>
          <i class="bi bi-eye"></i>
        </button>
        <?php if ($t['approval_status'] === 'PENDING'): ?>
          <?php if ($canApprove): ?>
          <button class="btn-act btn-approve" onclick="quickApprove(<?php echo $t['id']; ?>)">
            <i class="bi bi-check-lg"></i> Approve
          </button>
          <button class="btn-act btn-reject" onclick="openReject(<?php echo $t['id']; ?>)">
            <i class="bi bi-x-lg"></i> Reject
          </button>
          <?php endif; ?>
          <?php if ($canEdit): ?>
          <button class="btn-act btn-delete" onclick="confirmDelete(<?php echo $t['id']; ?>)"
                  title="Cancel request"><i class="bi bi-trash"></i></button>
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

<!-- ══════════════════════════════════════════════════════════════
     MODAL: CREATE TRANSFER
     ══════════════════════════════════════════════════════════════ -->
<div class="modal-backdrop-custom" id="createModal">
  <div class="modal-box">
    <div class="modal-head">
      <h3><i class="bi bi-arrow-left-right" style="color:#388bfd"></i> New Transfer Request</h3>
      <button class="btn-close-modal" onclick="closeModal('createModal')"><i class="bi bi-x-lg"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="create">
      <div class="modal-body">

        <div class="f-row">
          <div class="f-group">
            <label class="f-label">Inmate <span class="req">*</span></label>
            <select name="inmate_id" class="fi" required id="inmateSelect" onchange="updateFromFac(this)">
              <option value="">— Select inmate —</option>
              <?php foreach ($inmateList as $im): ?>
              <option value="<?php echo $im['id']; ?>"
                      data-fid="<?php echo $im['facility_id']; ?>"
                      data-fname="<?php echo htmlspecialchars($im['facility_name']??''); ?>">
                <?php echo htmlspecialchars($im['first_name'].' '.$im['last_name']); ?>
                (<?php echo htmlspecialchars($im['inmate_id']); ?>)
              </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Route visual -->
        <div class="route-box" id="routeBox" style="display:none">
          <div class="route-fac">
            <div class="rf-label">From</div>
            <div class="rf-name" id="routeFrom">—</div>
          </div>
          <div class="route-arrow"><i class="bi bi-arrow-right-circle-fill"></i></div>
          <div class="route-fac">
            <div class="rf-label">To</div>
            <div class="rf-name" id="routeTo">—</div>
          </div>
        </div>

        <div class="f-row two">
          <div class="f-group">
            <label class="f-label">Destination Facility <span class="req">*</span></label>
            <select name="facility_to" class="fi" required id="facilityToSelect" onchange="updateRoute()">
              <option value="">— Select facility —</option>
              <?php foreach ($allFacilities as $af): ?>
              <option value="<?php echo $af['id']; ?>"><?php echo htmlspecialchars($af['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="f-group">
            <label class="f-label">Transfer Date <span class="req">*</span></label>
            <input type="date" name="transfer_date" class="fi" required
                   value="<?php echo date('Y-m-d'); ?>" min="<?php echo date('Y-m-d'); ?>">
          </div>
        </div>

        <div class="f-row">
          <div class="f-group">
            <label class="f-label">Reason / Justification <span class="req">*</span></label>
            <input type="text" name="reason" class="fi" placeholder="e.g. Security concern, Medical transfer, Sentence requirement…" required maxlength="255">
          </div>
        </div>

        <div class="f-row">
          <div class="f-group">
            <label class="f-label">Additional Notes</label>
            <textarea name="notes" class="fi fi-area" placeholder="Any additional information…" rows="3"></textarea>
          </div>
        </div>

        <div style="background:rgba(56,139,253,.08);border:1px solid rgba(56,139,253,.2);border-radius:8px;padding:.7rem 1rem;font-size:.79rem;color:#58a6ff;display:flex;align-items:flex-start;gap:.5rem">
          <i class="bi bi-info-circle-fill" style="margin-top:1px;flex-shrink:0"></i>
          Transfer requests are submitted as <strong>PENDING</strong> and require approval from a Facility Administrator or Super Admin before the inmate record is updated.
        </div>

      </div>
      <div class="modal-foot">
        <button type="button" class="btn-act btn-view" onclick="closeModal('createModal')">Cancel</button>
        <button type="submit" class="btn-primary-sm"><i class="bi bi-send"></i> Submit Request</button>
      </div>
    </form>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     MODAL: VIEW TRANSFER DETAILS
     ══════════════════════════════════════════════════════════════ -->
<div class="modal-backdrop-custom" id="viewModal">
  <div class="modal-box wide">
    <div class="modal-head">
      <h3><i class="bi bi-info-circle" style="color:#388bfd"></i> Transfer Details</h3>
      <button class="btn-close-modal" onclick="closeModal('viewModal')"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="modal-body" id="viewModalBody">
      <!-- populated by JS -->
    </div>
    <div class="modal-foot" id="viewModalFoot">
      <button type="button" class="btn-act btn-view" onclick="closeModal('viewModal')">Close</button>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     MODAL: REJECT
     ══════════════════════════════════════════════════════════════ -->
<div class="modal-backdrop-custom" id="rejectModal">
  <div class="modal-box" style="max-width:420px">
    <div class="modal-head">
      <h3><i class="bi bi-x-circle" style="color:#f85149"></i> Reject Transfer</h3>
      <button class="btn-close-modal" onclick="closeModal('rejectModal')"><i class="bi bi-x-lg"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="reject">
      <input type="hidden" name="transfer_id" id="rejectTid">
      <div class="modal-body">
        <div class="f-group">
          <label class="f-label">Rejection Reason <span class="req">*</span></label>
          <textarea name="reject_notes" class="fi fi-area" required
                    placeholder="Explain why this transfer is being rejected…" rows="4"></textarea>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn-act btn-view" onclick="closeModal('rejectModal')">Cancel</button>
        <button type="submit" class="btn-act btn-reject" style="padding:.5rem 1rem;font-size:.85rem">
          <i class="bi bi-x-lg"></i> Confirm Rejection
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Hidden approve form -->
<form method="POST" id="approveForm" style="display:none">
  <input type="hidden" name="action" value="approve">
  <input type="hidden" name="transfer_id" id="approveTid">
</form>

<!-- Hidden delete form -->
<form method="POST" id="deleteForm" style="display:none">
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="transfer_id" id="deleteTid">
</form>

<!-- ══════════════════════════════════════════════════════════════
     JAVASCRIPT
     ══════════════════════════════════════════════════════════════ -->
<script>
/* ── Facility lookup for route preview ── */
const facilities = <?php echo json_encode(array_column($allFacilities, null, 'id')); ?>;

function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

/* Close on backdrop click */
document.querySelectorAll('.modal-backdrop-custom').forEach(function(m){
  m.addEventListener('click', function(e){ if(e.target===this) closeModal(this.id); });
});

/* Escape key */
document.addEventListener('keydown', function(e){
  if(e.key==='Escape') document.querySelectorAll('.modal-backdrop-custom.open').forEach(function(m){m.classList.remove('open');});
});

/* ── Route preview ── */
var currentFromFacId = null, currentFromFacName = '—';

function updateFromFac(sel){
  var opt = sel.options[sel.selectedIndex];
  currentFromFacId   = opt.dataset.fid;
  currentFromFacName = opt.dataset.fname || '—';
  document.getElementById('routeFrom').textContent = currentFromFacName;
  if(sel.value) { document.getElementById('routeBox').style.display='flex'; }
  else          { document.getElementById('routeBox').style.display='none'; }
  updateRoute();
}

function updateRoute(){
  var facSel  = document.getElementById('facilityToSelect');
  var opt     = facSel.options[facSel.selectedIndex];
  var toName  = facSel.value ? (opt.textContent.trim()) : '—';
  document.getElementById('routeTo').textContent = toName;
}

/* ── View modal ── */
function viewTransfer(t){
  var statusClr = {PENDING:'#f39c12', APPROVED:'#3fb950', REJECTED:'#f85149'};
  var clr = statusClr[t.approval_status] || '#8b949e';

  var html = '<div class="route-box">'
    + '<div class="route-fac"><div class="rf-label">From</div><div class="rf-name">'+esc(t.from_name)+'</div></div>'
    + '<div class="route-arrow"><i class="bi bi-arrow-right-circle-fill"></i></div>'
    + '<div class="route-fac"><div class="rf-label">To</div><div class="rf-name">'+esc(t.to_name)+'</div></div>'
    + '</div>';

  html += '<div>';
  var rows = [
    ['Inmate',         '<strong>'+esc(t.first_name+' '+t.last_name)+'</strong> <span style="color:#8b949e;font-size:.8rem;">('+esc(t.inmate_num)+')</span>'],
    ['Transfer Date',  t.transfer_date ? fmtDate(t.transfer_date) : '—'],
    ['Status',         '<span style="color:'+clr+';font-weight:700">'+esc(t.approval_status)+'</span>'],
    ['Reason',         esc(t.reason||'—')],
    ['Notes',          esc(t.notes||'—')],
    ['Approved By',    t.approver_name ? esc(t.approver_name) : '—'],
    ['Approved Date',  t.approved_date ? fmtDate(t.approved_date) : '—'],
    ['Requested On',   t.created_at    ? fmtDate(t.created_at)    : '—'],
  ];
  rows.forEach(function(r){
    html += '<div class="detail-row"><span class="detail-key">'+r[0]+'</span><span class="detail-val">'+r[1]+'</span></div>';
  });
  html += '</div>';

  document.getElementById('viewModalBody').innerHTML = html;
  openModal('viewModal');
}

function esc(s){ var d=document.createElement('div'); d.appendChild(document.createTextNode(s??'')); return d.innerHTML; }
function fmtDate(s){ try{ return new Date(s).toLocaleDateString('en-GB',{day:'numeric',month:'short',year:'numeric'}); }catch(e){return s;} }

/* ── Approve ── */
function quickApprove(tid){
  if(!confirm('Approve this transfer? The inmate record will be updated immediately.')) return;
  document.getElementById('approveTid').value = tid;
  document.getElementById('approveForm').submit();
}

/* ── Reject ── */
function openReject(tid){
  document.getElementById('rejectTid').value = tid;
  openModal('rejectModal');
}

/* ── Delete/cancel ── */
function confirmDelete(tid){
  if(!confirm('Cancel this transfer request? This action cannot be undone.')) return;
  document.getElementById('deleteTid').value = tid;
  document.getElementById('deleteForm').submit();
}
</script>

<?php require_once '../../includes/footer.php'; ?>
