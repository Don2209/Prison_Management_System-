<?php
$pageTitle = 'Purchase Orders';
require_once '../../includes/header.php';
requireAuth();
verifyPermission('view', 'inventory');

$fid  = getCurrentFacility();
$isSA = isSuperAdmin();
$uid  = getCurrentUserId();

/* ══════════ HELPERS ══════════ */
function nextPoNumber($fid) {
    $year = date('Y');
    $row  = fetchOne(
        "SELECT po_number FROM purchase_orders WHERE facility_id=? AND po_number LIKE ? ORDER BY id DESC LIMIT 1",
        [$fid, "PO-$year-%"], 'is'
    );
    if ($row) {
        $parts = explode('-', $row['po_number']);
        $seq   = (int)end($parts) + 1;
    } else {
        $seq = 1;
    }
    return "PO-$year-" . str_pad($seq, 3, '0', STR_PAD_LEFT);
}

/* ══════════ POST HANDLER ══════════ */
$flash = ''; $flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* ── CREATE ── */
    if ($action === 'create') {
        $supplierId      = (int)($_POST['supplier_id']      ?? 0);
        $orderDate       = trim($_POST['order_date']        ?? '');
        $expectedDelivery= trim($_POST['expected_delivery'] ?? '');
        $totalAmount     = trim($_POST['total_amount']      ?? '');
        $notes           = trim($_POST['notes']             ?? '');
        $poFid           = $isSA ? (int)($_POST['facility_id'] ?? $fid) : $fid;
        $status          = $_POST['submit_type'] === 'submit' ? 'SUBMITTED' : 'DRAFT';

        $errs = [];
        if (!$supplierId)  $errs[] = 'Supplier is required.';
        if (!$orderDate)   $errs[] = 'Order date is required.';
        if ($totalAmount !== '' && !is_numeric($totalAmount)) $errs[] = 'Total amount must be a number.';

        if (!$errs) {
            $poNumber = nextPoNumber($poFid);
            executeQuery(
                "INSERT INTO purchase_orders
                 (facility_id, supplier_id, po_number, order_date, expected_delivery, total_amount, status, created_by, notes, created_at, updated_at)
                 VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())",
                [$poFid, $supplierId, $poNumber, $orderDate,
                 $expectedDelivery ?: null,
                 $totalAmount !== '' ? (float)$totalAmount : null,
                 $status, $uid, $notes ?: null],
                'iisssdiss'
            );
            $flash = "Purchase order $poNumber created successfully.";
        } else {
            $flash = implode(' ', $errs); $flashType = 'error';
        }
    }

    /* ── EDIT ── */
    if ($action === 'edit') {
        $id              = (int)($_POST['id']               ?? 0);
        $supplierId      = (int)($_POST['supplier_id']      ?? 0);
        $orderDate       = trim($_POST['order_date']        ?? '');
        $expectedDelivery= trim($_POST['expected_delivery'] ?? '');
        $totalAmount     = trim($_POST['total_amount']      ?? '');
        $notes           = trim($_POST['notes']             ?? '');

        $errs = [];
        if (!$id)         $errs[] = 'Invalid order.';
        if (!$supplierId) $errs[] = 'Supplier is required.';
        if (!$orderDate)  $errs[] = 'Order date is required.';
        if ($totalAmount !== '' && !is_numeric($totalAmount)) $errs[] = 'Total amount must be a number.';

        if (!$errs) {
            $po = fetchOne("SELECT facility_id,status FROM purchase_orders WHERE id=? AND deleted_at IS NULL", [$id], 'i');
            if (!$po || (!$isSA && $po['facility_id'] != $fid)) $errs[] = 'Order not found or access denied.';
            elseif ($po['status'] !== 'DRAFT') $errs[] = 'Only DRAFT orders can be edited.';
        }
        if (!$errs) {
            executeQuery(
                "UPDATE purchase_orders SET supplier_id=?,order_date=?,expected_delivery=?,total_amount=?,notes=?,updated_at=NOW() WHERE id=?",
                [$supplierId, $orderDate, $expectedDelivery ?: null, $totalAmount !== '' ? (float)$totalAmount : null, $notes ?: null, $id],
                'issdsi'
            );
            $flash = 'Purchase order updated.';
        } else {
            $flash = implode(' ', $errs); $flashType = 'error';
        }
    }

    /* ── STATUS CHANGE ── */
    if ($action === 'set_status') {
        $id        = (int)($_POST['id']         ?? 0);
        $newStatus = trim($_POST['new_status']  ?? '');
        $actDelivery= trim($_POST['actual_delivery'] ?? '');
        $allowed   = ['SUBMITTED','APPROVED','DELIVERED','CANCELLED'];

        if (!in_array($newStatus, $allowed) || !$id) {
            $flash = 'Invalid status.'; $flashType = 'error';
        } else {
            $po = fetchOne("SELECT facility_id,status FROM purchase_orders WHERE id=? AND deleted_at IS NULL", [$id], 'i');
            if (!$po || (!$isSA && $po['facility_id'] != $fid)) {
                $flash = 'Order not found or access denied.'; $flashType = 'error';
            } else {
                $extra = '';
                $params = [$newStatus, $id]; $types = 'si';
                if ($newStatus === 'APPROVED')  { $extra = ',approved_by=?'; array_splice($params, 1, 0, [$uid]); $types = 'sii'; }
                if ($newStatus === 'DELIVERED') {
                    $del = $actDelivery ?: date('Y-m-d');
                    $extra = ',actual_delivery=?';
                    array_splice($params, 1, 0, [$del]); $types = 'ssi';
                }
                executeQuery("UPDATE purchase_orders SET status=?$extra,updated_at=NOW() WHERE id=?", $params, $types);
                $flash = 'Status updated to ' . $newStatus . '.';
            }
        }
    }

    /* ── DELETE ── */
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $po = $id ? fetchOne("SELECT facility_id,status FROM purchase_orders WHERE id=? AND deleted_at IS NULL", [$id], 'i') : null;
        if (!$po || (!$isSA && $po['facility_id'] != $fid)) {
            $flash = 'Order not found or access denied.'; $flashType = 'error';
        } elseif (!in_array($po['status'], ['DRAFT','CANCELLED'])) {
            $flash = 'Only DRAFT or CANCELLED orders can be deleted.'; $flashType = 'error';
        } else {
            executeQuery("UPDATE purchase_orders SET deleted_at=NOW() WHERE id=?", [$id], 'i');
            $flash = 'Purchase order deleted.';
        }
    }

    if (!$flash || $flashType === 'success') {
        $q = http_build_query(array_filter(['tab' => $_POST['tab'] ?? '', 'msg' => $flash ?: '']));
        header('Location: ?' . $q); exit;
    }
}

if (!$flash && !empty($_GET['msg'])) { $flash = $_GET['msg']; $flashType = 'success'; }

/* ══════════ DATA ══════════ */
// $facClause — for aliased queries using 'po.' prefix
$facClause  = $isSA ? "po.deleted_at IS NULL" : "po.deleted_at IS NULL AND po.facility_id = $fid";
// $kpiClause — for simple single-table queries (no alias)
$kpiClause  = $isSA ? "deleted_at IS NULL" : "deleted_at IS NULL AND facility_id = $fid";

$activeTab  = $_GET['tab'] ?? 'ALL';
$search     = trim($_GET['q'] ?? '');
$statusFilter = '';
if (in_array($activeTab, ['DRAFT','SUBMITTED','APPROVED','DELIVERED','CANCELLED']))
    $statusFilter = " AND po.status = '" . $activeTab . "'";

$searchClause = '';
if ($search) {
    $esc = addslashes($search);
    $searchClause = " AND (po.po_number LIKE '%$esc%' OR s.name LIKE '%$esc%')";
}

$orders = fetchAll(
    "SELECT po.*, s.name supplier_name, s.contact_person, s.email,
            f.name facility_name,
            CONCAT(uc.first_name,' ',uc.last_name) created_name,
            CONCAT(ua.first_name,' ',ua.last_name) approved_name
     FROM purchase_orders po
     JOIN suppliers s      ON po.supplier_id   = s.id
     JOIN facilities f     ON po.facility_id   = f.id
     LEFT JOIN users uc    ON po.created_by    = uc.id
     LEFT JOIN users ua    ON po.approved_by   = ua.id
     WHERE $facClause$statusFilter$searchClause
     ORDER BY po.created_at DESC",
    [], ''
);

/* KPIs */
$allOrders = fetchAll(
    "SELECT status, COUNT(*) cnt, COALESCE(SUM(total_amount),0) val
     FROM purchase_orders WHERE $kpiClause GROUP BY status",
    [], ''
);
$kmap = [];
foreach ($allOrders as $r) $kmap[$r['status']] = ['cnt' => $r['cnt'], 'val' => $r['val']];
$kpis = [
    'total'     => array_sum(array_column($allOrders, 'cnt')),
    'value'     => array_sum(array_column($allOrders, 'val')),
    'draft'     => $kmap['DRAFT']['cnt']     ?? 0,
    'submitted' => $kmap['SUBMITTED']['cnt'] ?? 0,
    'approved'  => $kmap['APPROVED']['cnt']  ?? 0,
    'delivered' => $kmap['DELIVERED']['cnt'] ?? 0,
    'cancelled' => $kmap['CANCELLED']['cnt'] ?? 0,
    'pending_val'=> (($kmap['SUBMITTED']['val'] ?? 0) + ($kmap['APPROVED']['val'] ?? 0)),
];

$suppliers  = fetchAll("SELECT id,name,contact_person,email,phone FROM suppliers WHERE status='ACTIVE' AND deleted_at IS NULL ORDER BY name", [], '');
$facilities = $isSA ? fetchAll("SELECT id,name FROM facilities WHERE deleted_at IS NULL ORDER BY name", [], '') : [];

$canCreate = hasPermission('create', 'inventory');
$canEdit   = hasPermission('edit',   'inventory');

$tabs = [
    'ALL'       => ['label'=>'All',       'count'=>$kpis['total'],     'clr'=>'#8b949e'],
    'DRAFT'     => ['label'=>'Draft',     'count'=>$kpis['draft'],     'clr'=>'#8b949e'],
    'SUBMITTED' => ['label'=>'Submitted', 'count'=>$kpis['submitted'], 'clr'=>'#f39c12'],
    'APPROVED'  => ['label'=>'Approved',  'count'=>$kpis['approved'],  'clr'=>'#58a6ff'],
    'DELIVERED' => ['label'=>'Delivered', 'count'=>$kpis['delivered'], 'clr'=>'#3fb950'],
    'CANCELLED' => ['label'=>'Cancelled', 'count'=>$kpis['cancelled'], 'clr'=>'#f85149'],
];

$statusCfg = [
    'DRAFT'     => ['clr'=>'#8b949e','bg'=>'rgba(139,148,158,.15)','icon'=>'bi-pencil-square'],
    'SUBMITTED' => ['clr'=>'#f39c12','bg'=>'rgba(243,156,18,.15)','icon'=>'bi-send-fill'],
    'APPROVED'  => ['clr'=>'#58a6ff','bg'=>'rgba(88,166,255,.15)','icon'=>'bi-check2-circle'],
    'DELIVERED' => ['clr'=>'#3fb950','bg'=>'rgba(63,185,80,.15)','icon'=>'bi-box-arrow-in-down'],
    'CANCELLED' => ['clr'=>'#f85149','bg'=>'rgba(248,81,73,.15)','icon'=>'bi-x-circle-fill'],
];

$nextStatus = [
    'DRAFT'     => 'SUBMITTED',
    'SUBMITTED' => 'APPROVED',
    'APPROVED'  => 'DELIVERED',
];
$nextLabel = [
    'DRAFT'     => 'Submit',
    'SUBMITTED' => 'Approve',
    'APPROVED'  => 'Mark Delivered',
];
?>
<style>
:root{--sur:#161b22;--bdr:#21262d;--bg:#0d1117;--txt:#e6edf3;--mut:#8b949e;--acc:#1f6feb}
.ph{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:.75rem}
.ph h1{font-size:1.35rem;font-weight:700;color:var(--txt);margin:0}
.bc{font-size:.78rem;color:#6e7681;display:flex;align-items:center;gap:.3rem;margin-bottom:.3rem}
.bc a{color:#388bfd;text-decoration:none}.bc a:hover{text-decoration:underline}
.ph-actions{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap}

/* KPI */
.kpi-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:1rem;margin-bottom:1.5rem}
@media(max-width:1100px){.kpi-grid{grid-template-columns:repeat(3,1fr)}}
@media(max-width:640px){.kpi-grid{grid-template-columns:repeat(2,1fr)}}
.kpi{background:var(--sur);border:1px solid var(--bdr);border-radius:14px;padding:1.1rem 1.2rem;position:relative;transition:transform .18s,box-shadow .18s;cursor:pointer}
.kpi:hover,.kpi.active{transform:translateY(-2px);box-shadow:0 6px 24px rgba(0,0,0,.35)}
.kpi-ico{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1rem;margin-bottom:.75rem}
.kpi-val{font-size:1.6rem;font-weight:800;color:var(--txt);line-height:1;margin-bottom:.25rem}
.kpi-lbl{font-size:.72rem;font-weight:600;color:var(--mut);text-transform:uppercase;letter-spacing:.05em}

/* Tabs */
.tab-strip{display:flex;gap:.4rem;margin-bottom:1.25rem;flex-wrap:wrap;padding:.45rem .5rem;background:var(--sur);border:1px solid var(--bdr);border-radius:12px;width:fit-content}
.tab{padding:.38rem .85rem;border-radius:8px;font-size:.8rem;font-weight:600;cursor:pointer;color:var(--mut);background:transparent;border:none;display:flex;align-items:center;gap:.4rem;transition:all .18s;text-decoration:none;white-space:nowrap}
.tab:hover{background:#21262d;color:var(--txt)}
.tab.active{background:#21262d;color:var(--txt)}
.tab-badge{padding:1px 6px;border-radius:20px;font-size:.68rem;font-weight:700}

/* Toolbar */
.toolbar{display:flex;align-items:center;gap:.6rem;margin-bottom:1.25rem;flex-wrap:wrap}
.search-wrap{position:relative;flex:1;min-width:180px;max-width:340px}
.search-wrap i{position:absolute;left:.75rem;top:50%;transform:translateY(-50%);color:#484f58;font-size:.85rem;pointer-events:none}
.search-wrap input{width:100%;background:#0d1117;border:1px solid #30363d;color:var(--txt);padding:.5rem .85rem .5rem 2.2rem;border-radius:9px;font-size:.875rem;outline:none;transition:border .2s,box-shadow .2s;box-sizing:border-box}
.search-wrap input::placeholder{color:#484f58}
.search-wrap input:focus{border-color:#388bfd;box-shadow:0 0 0 3px rgba(56,139,253,.15)}

/* Table */
.card{background:var(--sur);border:1px solid var(--bdr);border-radius:14px;overflow:hidden}
.tbl-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:.875rem}
th{background:#0d1117;color:var(--mut);font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;padding:.7rem 1rem;text-align:left;white-space:nowrap;border-bottom:1px solid var(--bdr)}
td{padding:.75rem 1rem;border-bottom:1px solid #1c2128;color:var(--txt);vertical-align:middle}
tr:last-child td{border-bottom:none}
tr:hover td{background:rgba(255,255,255,.025)}
.po-num{font-weight:700;color:#58a6ff;font-size:.85rem;white-space:nowrap}
.supplier-cell{display:flex;flex-direction:column;gap:.1rem}
.supplier-name{font-weight:600;font-size:.85rem}
.supplier-sub{font-size:.73rem;color:#8b949e}
.status-chip{display:inline-flex;align-items:center;gap:.3rem;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700}
.amt{font-weight:700;color:var(--txt)}
.date-cell{font-size:.82rem;color:#8b949e;white-space:nowrap}
.overdue{color:#f85149 !important;font-weight:700}
.actions-cell{display:flex;align-items:center;gap:.4rem;white-space:nowrap}
.ic-btn{width:30px;height:30px;border-radius:7px;border:1px solid #30363d;background:transparent;color:#8b949e;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;font-size:.82rem;transition:all .18s}
.ic-btn:hover{border-color:#388bfd;color:#388bfd;background:rgba(56,139,253,.08)}
.ic-btn.confirm:hover{border-color:#3fb950;color:#3fb950;background:rgba(63,185,80,.08)}
.ic-btn.danger:hover{border-color:#f85149;color:#f85149;background:rgba(248,81,73,.08)}
.adv-btn{padding:.3rem .7rem;border-radius:7px;font-size:.75rem;font-weight:700;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:.3rem;transition:all .18s;white-space:nowrap}

/* Buttons */
.btn-primary-sm{background:linear-gradient(135deg,#1f6feb,#388bfd);color:#fff;padding:.5rem 1.1rem;border-radius:9px;font-size:.85rem;font-weight:600;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;text-decoration:none;transition:opacity .18s;white-space:nowrap}
.btn-primary-sm:hover{opacity:.87;color:#fff}
.btn-ghost{background:transparent;border:1px solid #30363d;color:#8b949e;padding:.5rem 1rem;border-radius:9px;font-size:.85rem;font-weight:500;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;text-decoration:none;transition:all .18s;white-space:nowrap}
.btn-ghost:hover{border-color:#58a6ff;color:#58a6ff}

/* Flash */
.flash{border-radius:11px;padding:.8rem 1.1rem;margin-bottom:1.25rem;font-size:.875rem;display:flex;align-items:center;gap:.55rem}
.flash.success{background:rgba(63,185,80,.12);border:1px solid rgba(63,185,80,.3);color:#3fb950}
.flash.error{background:rgba(248,81,73,.08);border:1px solid rgba(248,81,73,.3);color:#f85149}

/* Empty */
.empty-state{text-align:center;padding:4rem 1rem;color:#8b949e}
.empty-state i{font-size:2.8rem;display:block;margin-bottom:.75rem;opacity:.3}

/* MODAL */
.modal-ov{position:fixed;inset:0;background:rgba(1,4,9,.77);z-index:1000;display:flex;align-items:center;justify-content:center;padding:1rem;opacity:0;pointer-events:none;transition:opacity .22s}
.modal-ov.open{opacity:1;pointer-events:auto}
.modal-box{background:#161b22;border:1px solid #30363d;border-radius:18px;width:100%;max-width:560px;max-height:90vh;overflow-y:auto;transform:translateY(20px);transition:transform .22s}
.modal-box.wide{max-width:720px}
.modal-ov.open .modal-box{transform:translateY(0)}
.modal-hdr{display:flex;align-items:center;justify-content:space-between;padding:1.1rem 1.4rem;border-bottom:1px solid #21262d;position:sticky;top:0;background:#161b22;z-index:1}
.modal-hdr h2{margin:0;font-size:1rem;font-weight:700;color:#e6edf3;display:flex;align-items:center;gap:.5rem}
.modal-close{background:none;border:none;color:#8b949e;font-size:1.1rem;cursor:pointer;width:30px;height:30px;border-radius:7px;display:flex;align-items:center;justify-content:center;transition:all .18s}
.modal-close:hover{background:#21262d;color:#e6edf3}
.modal-body{padding:1.4rem}
.fg{display:flex;flex-direction:column;gap:.35rem;margin-bottom:1rem}
.fg-row2{display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem}
@media(max-width:480px){.fg-row2{grid-template-columns:1fr}}
.fg label,.fg-row2 .fg label{font-size:.8rem;font-weight:600;color:var(--mut)}
.req{color:#f85149}
.fi{background:#0d1117;border:1px solid #30363d;border-radius:9px;color:var(--txt);padding:.55rem .85rem;font-size:.875rem;width:100%;outline:none;transition:border .2s,box-shadow .2s;font-family:inherit;box-sizing:border-box}
.fi::placeholder{color:#484f58}
.fi:focus{border-color:#388bfd;box-shadow:0 0 0 3px rgba(56,139,253,.15)}
textarea.fi{resize:vertical;min-height:80px}
select.fi{cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='%238b949e' d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right .75rem center;background-size:11px;padding-right:2rem}
.modal-footer{display:flex;justify-content:flex-end;gap:.6rem;padding-top:1rem;border-top:1px solid #21262d;margin-top:1.1rem;flex-wrap:wrap}
.btn-danger{background:rgba(248,81,73,.1);border:1px solid rgba(248,81,73,.3);color:#f85149;padding:.55rem 1.1rem;border-radius:9px;font-size:.875rem;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;transition:all .18s}
.btn-danger:hover{background:rgba(248,81,73,.2)}
.note{background:rgba(248,81,73,.07);border:1px solid rgba(248,81,73,.2);border-radius:8px;padding:.6rem .9rem;font-size:.8rem;color:#f85149;display:flex;align-items:flex-start;gap:.45rem;margin-bottom:1rem}
.note i{flex-shrink:0;margin-top:1px}

/* View detail modal */
.detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
@media(max-width:520px){.detail-grid{grid-template-columns:1fr}}
.detail-item{background:#0d1117;border-radius:10px;padding:.75rem 1rem}
.detail-item-lbl{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#8b949e;margin-bottom:.25rem}
.detail-item-val{font-size:.9rem;color:#e6edf3;font-weight:600}
.detail-section{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#8b949e;margin:1.1rem 0 .55rem;padding-bottom:.35rem;border-bottom:1px solid #21262d}
.timeline{list-style:none;margin:0;padding:0}
.timeline li{display:flex;align-items:center;gap:.6rem;font-size:.82rem;color:#8b949e;padding:.3rem 0}
.timeline li i{color:#388bfd;width:16px;flex-shrink:0}
</style>

<!-- PAGE HEADER -->
<div class="ph">
  <div>
    <div class="bc">
      <a href="<?php echo APP_URL; ?>/dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
      <i class="bi bi-chevron-right" style="font-size:.6rem"></i>
      <a href="<?php echo APP_URL; ?>/modules/inventory/index.php">Inventory</a>
      <i class="bi bi-chevron-right" style="font-size:.6rem"></i>
      <span>Purchase Orders</span>
    </div>
    <h1><i class="bi bi-cart-check-fill" style="color:#388bfd;margin-right:.45rem"></i>Purchase Orders</h1>
  </div>
  <div class="ph-actions">
    <a href="<?php echo APP_URL; ?>/modules/inventory/index.php" class="btn-ghost">
      <i class="bi bi-boxes"></i> Inventory
    </a>
    <a href="<?php echo APP_URL; ?>/modules/inventory/warehouses.php" class="btn-ghost">
      <i class="bi bi-building"></i> Warehouses
    </a>
    <?php if ($canCreate): ?>
    <button class="btn-primary-sm" onclick="openCreate()">
      <i class="bi bi-plus-lg"></i> New Order
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

<!-- KPIs -->
<div class="kpi-grid">
<?php
$kpiDefs = [
    ['Total Orders',   $kpis['total'],                        'bi-cart4',             '#388bfd','rgba(56,139,253,.15)', '#1f6feb'],
    ['Pending Value',  '$'.number_format($kpis['pending_val'],2),'bi-hourglass-split', '#f39c12','rgba(243,156,18,.15)','#f39c12'],
    ['Draft',          $kpis['draft'],                        'bi-pencil-square',    '#8b949e','rgba(139,148,158,.15)','#8b949e'],
    ['Submitted',      $kpis['submitted'],                    'bi-send-fill',        '#f39c12','rgba(243,156,18,.15)','#f39c12'],
    ['Approved',       $kpis['approved'],                     'bi-check2-circle',    '#58a6ff','rgba(88,166,255,.15)', '#58a6ff'],
    ['Delivered',      $kpis['delivered'],                    'bi-box-arrow-in-down','#3fb950','rgba(63,185,80,.15)',  '#3fb950'],
];
foreach ($kpiDefs as [$lbl,$val,$icon,$clr,$bg,$bar]):
?>
<div class="kpi" style="border-top:3px solid <?php echo $bar; ?>">
  <div class="kpi-ico" style="background:<?php echo $bg; ?>;color:<?php echo $clr; ?>"><i class="bi <?php echo $icon; ?>"></i></div>
  <div class="kpi-val" style="font-size:<?php echo strlen((string)$val)>6?'1.1rem':'1.6rem'; ?>"><?php echo is_numeric($val)?number_format((float)$val):$val; ?></div>
  <div class="kpi-lbl"><?php echo $lbl; ?></div>
</div>
<?php endforeach; ?>
</div>

<!-- TABS + TOOLBAR -->
<div class="tab-strip">
<?php foreach ($tabs as $key => $t): ?>
  <a href="?tab=<?php echo $key; ?><?php echo $search ? '&q='.urlencode($search) : ''; ?>"
     class="tab <?php echo $activeTab===$key?'active':''; ?>">
    <?php echo $t['label']; ?>
    <?php if ($t['count'] > 0): ?>
    <span class="tab-badge" style="background:rgba(139,148,158,.15);color:<?php echo $t['clr']; ?>"><?php echo $t['count']; ?></span>
    <?php endif; ?>
  </a>
<?php endforeach; ?>
</div>

<div class="toolbar">
  <form method="GET" style="display:contents">
    <input type="hidden" name="tab" value="<?php echo htmlspecialchars($activeTab); ?>">
    <div class="search-wrap">
      <i class="bi bi-search"></i>
      <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>"
             placeholder="Search PO number or supplier…" onchange="this.form.submit()">
    </div>
  </form>
  <span style="margin-left:auto;font-size:.8rem;color:#8b949e">
    <?php echo count($orders); ?> order<?php echo count($orders)!==1?'s':''; ?>
  </span>
</div>

<!-- TABLE -->
<div class="card">
  <?php if (empty($orders)): ?>
  <div class="empty-state">
    <i class="bi bi-cart-x"></i>
    <p>No purchase orders found.<?php echo ($canCreate && $activeTab==='ALL') ? ' <a href="#" onclick="openCreate()" style="color:#388bfd">Create the first one</a>.' : ''; ?></p>
  </div>
  <?php else: ?>
  <div class="tbl-wrap">
    <table>
      <thead>
        <tr>
          <th>PO Number</th>
          <th>Supplier</th>
          <?php if ($isSA): ?><th>Facility</th><?php endif; ?>
          <th>Order Date</th>
          <th>Expected</th>
          <th>Amount</th>
          <th>Status</th>
          <th>Created By</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($orders as $po):
          $sc     = $statusCfg[$po['status']];
          $today  = date('Y-m-d');
          $isOverdue = $po['expected_delivery'] && $po['expected_delivery'] < $today
                       && !in_array($po['status'], ['DELIVERED','CANCELLED']);
          $ns = $nextStatus[$po['status']] ?? null;
      ?>
        <tr>
          <td><span class="po-num"><?php echo htmlspecialchars($po['po_number']); ?></span></td>
          <td>
            <div class="supplier-cell">
              <span class="supplier-name"><?php echo htmlspecialchars($po['supplier_name']); ?></span>
              <?php if ($po['contact_person']): ?>
              <span class="supplier-sub"><i class="bi bi-person"></i> <?php echo htmlspecialchars($po['contact_person']); ?></span>
              <?php endif; ?>
            </div>
          </td>
          <?php if ($isSA): ?><td style="font-size:.8rem;color:#8b949e"><?php echo htmlspecialchars($po['facility_name']); ?></td><?php endif; ?>
          <td class="date-cell"><?php echo $po['order_date'] ? date('M j, Y', strtotime($po['order_date'])) : '—'; ?></td>
          <td class="date-cell <?php echo $isOverdue ? 'overdue' : ''; ?>">
            <?php if ($po['status'] === 'DELIVERED' && $po['actual_delivery']): ?>
              <span style="color:#3fb950"><i class="bi bi-check-circle"></i> <?php echo date('M j, Y', strtotime($po['actual_delivery'])); ?></span>
            <?php elseif ($po['expected_delivery']): ?>
              <?php echo $isOverdue ? '<i class="bi bi-exclamation-triangle-fill"></i> ' : ''; ?>
              <?php echo date('M j, Y', strtotime($po['expected_delivery'])); ?>
            <?php else: echo '—'; endif; ?>
          </td>
          <td class="amt"><?php echo $po['total_amount'] !== null ? '$'.number_format((float)$po['total_amount'], 2) : '<span style="color:#8b949e">—</span>'; ?></td>
          <td>
            <span class="status-chip" style="background:<?php echo $sc['bg']; ?>;color:<?php echo $sc['clr']; ?>">
              <i class="bi <?php echo $sc['icon']; ?>"></i>
              <?php echo ucfirst(strtolower($po['status'])); ?>
            </span>
          </td>
          <td style="font-size:.8rem;color:#8b949e"><?php echo $po['created_name'] ? trim($po['created_name']) : '—'; ?></td>
          <td>
            <div class="actions-cell">
              <!-- View -->
              <button class="ic-btn" title="View details"
                onclick='openView(<?php echo json_encode([
                  "po_number"       => $po["po_number"],
                  "supplier"        => $po["supplier_name"],
                  "contact_person"  => $po["contact_person"] ?? "",
                  "email"           => $po["email"] ?? "",
                  "facility"        => $po["facility_name"],
                  "order_date"      => $po["order_date"] ?? "",
                  "expected"        => $po["expected_delivery"] ?? "",
                  "actual"          => $po["actual_delivery"] ?? "",
                  "amount"          => $po["total_amount"],
                  "status"          => $po["status"],
                  "created_name"    => trim($po["created_name"] ?? ""),
                  "approved_name"   => trim($po["approved_name"] ?? ""),
                  "notes"           => $po["notes"] ?? "",
                  "created_at"      => $po["created_at"] ?? "",
                ]); ?>)'>
                <i class="bi bi-eye"></i>
              </button>

              <?php if ($canEdit && $po['status'] === 'DRAFT'): ?>
              <!-- Edit (DRAFT only) -->
              <button class="ic-btn" title="Edit"
                onclick='openEdit(<?php echo json_encode([
                  "id"              => $po["id"],
                  "supplier_id"     => $po["supplier_id"],
                  "order_date"      => $po["order_date"] ?? "",
                  "expected_delivery"=> $po["expected_delivery"] ?? "",
                  "total_amount"    => $po["total_amount"],
                  "notes"           => $po["notes"] ?? "",
                ]); ?>)'>
                <i class="bi bi-pencil"></i>
              </button>
              <?php endif; ?>

              <?php if ($canEdit && $ns): ?>
              <!-- Advance status -->
              <button class="ic-btn confirm" title="<?php echo $nextLabel[$po['status']]; ?>"
                onclick='advanceStatus(<?php echo $po["id"]; ?>, <?php echo json_encode($po["po_number"]); ?>, <?php echo json_encode($ns); ?>, <?php echo json_encode($nextLabel[$po["status"]]); ?>)'>
                <i class="bi bi-arrow-up-circle"></i>
              </button>
              <?php endif; ?>

              <?php if ($canEdit && !in_array($po['status'], ['DELIVERED','CANCELLED'])): ?>
              <!-- Cancel -->
              <button class="ic-btn danger" title="Cancel order"
                onclick='cancelOrder(<?php echo $po["id"]; ?>, <?php echo json_encode($po["po_number"]); ?>)'>
                <i class="bi bi-x-circle"></i>
              </button>
              <?php endif; ?>

              <?php if ($canEdit && in_array($po['status'], ['DRAFT','CANCELLED'])): ?>
              <!-- Delete -->
              <button class="ic-btn danger" title="Delete"
                onclick='deleteOrder(<?php echo $po["id"]; ?>, <?php echo json_encode($po["po_number"]); ?>)'>
                <i class="bi bi-trash3"></i>
              </button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- ══════════ CREATE MODAL ══════════ -->
<div class="modal-ov" id="createOv" onclick="if(event.target===this)closeCreate()">
  <div class="modal-box">
    <div class="modal-hdr">
      <h2><i class="bi bi-cart-plus-fill" style="color:#388bfd"></i> New Purchase Order</h2>
      <button class="modal-close" onclick="closeCreate()"><i class="bi bi-x"></i></button>
    </div>
    <form method="POST" id="createForm">
      <input type="hidden" name="action" value="create">
      <input type="hidden" name="tab" value="<?php echo htmlspecialchars($activeTab); ?>">
      <input type="hidden" name="submit_type" id="create-submit-type" value="draft">
      <div class="modal-body">
        <?php if ($isSA && !empty($facilities)): ?>
        <div class="fg">
          <label>Facility <span class="req">*</span></label>
          <select name="facility_id" class="fi" required>
            <?php foreach ($facilities as $f): ?>
            <option value="<?php echo $f['id']; ?>"><?php echo htmlspecialchars($f['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <div class="fg">
          <label>Supplier <span class="req">*</span></label>
          <select name="supplier_id" class="fi" required>
            <option value="">— Select supplier —</option>
            <?php foreach ($suppliers as $s): ?>
            <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?><?php echo $s['contact_person'] ? ' ('.$s['contact_person'].')' : ''; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="fg-row2">
          <div class="fg" style="margin-bottom:0">
            <label>Order Date <span class="req">*</span></label>
            <input type="date" name="order_date" class="fi" value="<?php echo date('Y-m-d'); ?>" required>
          </div>
          <div class="fg" style="margin-bottom:0">
            <label>Expected Delivery</label>
            <input type="date" name="expected_delivery" class="fi">
          </div>
        </div>
        <div class="fg" style="margin-top:1rem">
          <label>Total Amount ($)</label>
          <input type="number" name="total_amount" class="fi" placeholder="0.00" step="0.01" min="0">
        </div>
        <div class="fg">
          <label>Notes</label>
          <textarea name="notes" class="fi" placeholder="Optional notes or order description…"></textarea>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-ghost" onclick="closeCreate()"><i class="bi bi-x"></i> Cancel</button>
          <button type="button" class="btn-ghost" onclick="submitCreate('draft')" style="color:#8b949e;border-color:#30363d">
            <i class="bi bi-pencil-square"></i> Save as Draft
          </button>
          <button type="button" class="btn-primary-sm" onclick="submitCreate('submit')">
            <i class="bi bi-send-fill"></i> Submit Order
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- ══════════ EDIT MODAL ══════════ -->
<div class="modal-ov" id="editOv" onclick="if(event.target===this)closeEdit()">
  <div class="modal-box">
    <div class="modal-hdr">
      <h2><i class="bi bi-pencil-fill" style="color:#388bfd"></i> Edit Purchase Order</h2>
      <button class="modal-close" onclick="closeEdit()"><i class="bi bi-x"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="tab" value="<?php echo htmlspecialchars($activeTab); ?>">
      <input type="hidden" name="id" id="edit-id">
      <div class="modal-body">
        <div class="fg">
          <label>Supplier <span class="req">*</span></label>
          <select name="supplier_id" id="edit-supplier" class="fi" required>
            <?php foreach ($suppliers as $s): ?>
            <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?><?php echo $s['contact_person'] ? ' ('.$s['contact_person'].')' : ''; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="fg-row2">
          <div class="fg" style="margin-bottom:0">
            <label>Order Date <span class="req">*</span></label>
            <input type="date" name="order_date" id="edit-order-date" class="fi" required>
          </div>
          <div class="fg" style="margin-bottom:0">
            <label>Expected Delivery</label>
            <input type="date" name="expected_delivery" id="edit-expected" class="fi">
          </div>
        </div>
        <div class="fg" style="margin-top:1rem">
          <label>Total Amount ($)</label>
          <input type="number" name="total_amount" id="edit-amount" class="fi" placeholder="0.00" step="0.01" min="0">
        </div>
        <div class="fg">
          <label>Notes</label>
          <textarea name="notes" id="edit-notes" class="fi"></textarea>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-ghost" onclick="closeEdit()"><i class="bi bi-x"></i> Cancel</button>
          <button type="submit" class="btn-primary-sm"><i class="bi bi-check-lg"></i> Save Changes</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- ══════════ VIEW MODAL ══════════ -->
<div class="modal-ov" id="viewOv" onclick="if(event.target===this)closeView()">
  <div class="modal-box wide">
    <div class="modal-hdr">
      <h2><i class="bi bi-file-text-fill" style="color:#388bfd"></i> <span id="view-po-num">Purchase Order</span></h2>
      <button class="modal-close" onclick="closeView()"><i class="bi bi-x"></i></button>
    </div>
    <div class="modal-body" id="view-body"></div>
  </div>
</div>

<!-- ══════════ ADVANCE STATUS MODAL ══════════ -->
<div class="modal-ov" id="advOv" onclick="if(event.target===this)closeAdv()">
  <div class="modal-box" style="max-width:420px">
    <div class="modal-hdr">
      <h2><i class="bi bi-arrow-up-circle-fill" style="color:#3fb950"></i> <span id="adv-title">Confirm Action</span></h2>
      <button class="modal-close" onclick="closeAdv()"><i class="bi bi-x"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="set_status">
      <input type="hidden" name="tab" value="<?php echo htmlspecialchars($activeTab); ?>">
      <input type="hidden" name="id" id="adv-id">
      <input type="hidden" name="new_status" id="adv-new-status">
      <div class="modal-body">
        <p id="adv-msg" style="font-size:.9rem;color:#c9d1d9;margin:0 0 1rem"></p>
        <div class="fg" id="adv-delivery-wrap" style="display:none">
          <label>Actual Delivery Date</label>
          <input type="date" name="actual_delivery" id="adv-delivery" class="fi" value="<?php echo date('Y-m-d'); ?>">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-ghost" onclick="closeAdv()">Cancel</button>
          <button type="submit" class="btn-primary-sm" id="adv-btn"><i class="bi bi-check-lg"></i> Confirm</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- ══════════ CANCEL / DELETE MODALS ══════════ -->
<div class="modal-ov" id="cancelOv" onclick="if(event.target===this)closeCancelModal()">
  <div class="modal-box" style="max-width:400px">
    <div class="modal-hdr">
      <h2><i class="bi bi-x-circle-fill" style="color:#f85149"></i> Cancel Order</h2>
      <button class="modal-close" onclick="closeCancelModal()"><i class="bi bi-x"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="set_status">
      <input type="hidden" name="tab" value="<?php echo htmlspecialchars($activeTab); ?>">
      <input type="hidden" name="new_status" value="CANCELLED">
      <input type="hidden" name="id" id="cancel-id">
      <div class="modal-body">
        <div class="note"><i class="bi bi-info-circle-fill"></i> This will set the order status to Cancelled. You can still view it in the Cancelled tab.</div>
        <p style="font-size:.9rem;color:#c9d1d9;margin:0 0 1rem">Cancel order <strong id="cancel-num" style="color:#e6edf3"></strong>?</p>
        <div class="modal-footer">
          <button type="button" class="btn-ghost" onclick="closeCancelModal()">Back</button>
          <button type="submit" class="btn-danger"><i class="bi bi-x-circle"></i> Cancel Order</button>
        </div>
      </div>
    </form>
  </div>
</div>

<div class="modal-ov" id="delOv" onclick="if(event.target===this)closeDelModal()">
  <div class="modal-box" style="max-width:400px">
    <div class="modal-hdr">
      <h2><i class="bi bi-trash3-fill" style="color:#f85149"></i> Delete Order</h2>
      <button class="modal-close" onclick="closeDelModal()"><i class="bi bi-x"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="tab" value="<?php echo htmlspecialchars($activeTab); ?>">
      <input type="hidden" name="id" id="del-id">
      <div class="modal-body">
        <div class="note"><i class="bi bi-exclamation-triangle-fill"></i> This action cannot be undone.</div>
        <p style="font-size:.9rem;color:#c9d1d9;margin:0 0 1rem">Permanently delete <strong id="del-num" style="color:#e6edf3"></strong>?</p>
        <div class="modal-footer">
          <button type="button" class="btn-ghost" onclick="closeDelModal()">Cancel</button>
          <button type="submit" class="btn-danger"><i class="bi bi-trash3"></i> Delete</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
/* ── Modal open/close helpers ── */
function openCreate(){ document.getElementById('createOv').classList.add('open'); document.body.style.overflow='hidden'; }
function closeCreate(){ document.getElementById('createOv').classList.remove('open'); document.body.style.overflow=''; }

function openEdit(r) {
  document.getElementById('edit-id').value        = r.id;
  document.getElementById('edit-supplier').value  = r.supplier_id;
  document.getElementById('edit-order-date').value= r.order_date;
  document.getElementById('edit-expected').value  = r.expected_delivery || '';
  document.getElementById('edit-amount').value    = r.total_amount !== null ? r.total_amount : '';
  document.getElementById('edit-notes').value     = r.notes || '';
  document.getElementById('editOv').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeEdit(){ document.getElementById('editOv').classList.remove('open'); document.body.style.overflow=''; }

function submitCreate(type) {
  document.getElementById('create-submit-type').value = type;
  document.getElementById('createForm').submit();
}

/* ── View modal ── */
const statusCfg = <?php echo json_encode($statusCfg); ?>;
function fmtDate(d) {
  if (!d) return '—';
  const dt = new Date(d + 'T00:00:00');
  return dt.toLocaleDateString('en-US', {year:'numeric',month:'short',day:'numeric'});
}
function openView(r) {
  document.getElementById('view-po-num').textContent = r.po_number;
  const sc = statusCfg[r.status] || {clr:'#8b949e',bg:'rgba(139,148,158,.15)',icon:'bi-circle'};
  const chip = `<span class="status-chip" style="background:${sc.bg};color:${sc.clr}"><i class="bi ${sc.icon}"></i> ${r.status.charAt(0)+r.status.slice(1).toLowerCase()}</span>`;
  document.getElementById('view-body').innerHTML = `
    <div class="detail-section">Order Information</div>
    <div class="detail-grid">
      <div class="detail-item"><div class="detail-item-lbl">PO Number</div><div class="detail-item-val" style="color:#58a6ff">${r.po_number}</div></div>
      <div class="detail-item"><div class="detail-item-lbl">Status</div><div class="detail-item-val">${chip}</div></div>
      <div class="detail-item"><div class="detail-item-lbl">Facility</div><div class="detail-item-val">${r.facility||'—'}</div></div>
      <div class="detail-item"><div class="detail-item-lbl">Total Amount</div><div class="detail-item-val">${r.amount !== null ? '$'+parseFloat(r.amount).toLocaleString('en-US',{minimumFractionDigits:2}) : '—'}</div></div>
      <div class="detail-item"><div class="detail-item-lbl">Order Date</div><div class="detail-item-val">${fmtDate(r.order_date)}</div></div>
      <div class="detail-item"><div class="detail-item-lbl">Expected Delivery</div><div class="detail-item-val">${fmtDate(r.expected)}</div></div>
      ${r.actual ? `<div class="detail-item"><div class="detail-item-lbl">Actual Delivery</div><div class="detail-item-val" style="color:#3fb950">${fmtDate(r.actual)}</div></div>` : ''}
    </div>
    <div class="detail-section">Supplier</div>
    <div class="detail-grid">
      <div class="detail-item"><div class="detail-item-lbl">Name</div><div class="detail-item-val">${r.supplier||'—'}</div></div>
      <div class="detail-item"><div class="detail-item-lbl">Contact</div><div class="detail-item-val">${r.contact_person||'—'}</div></div>
      <div class="detail-item" style="grid-column:span 2"><div class="detail-item-lbl">Email</div><div class="detail-item-val">${r.email||'—'}</div></div>
    </div>
    <div class="detail-section">Workflow</div>
    <ul class="timeline">
      <li><i class="bi bi-person-fill"></i> Created by: <span style="color:#e6edf3;margin-left:.25rem">${r.created_name||'—'}</span></li>
      ${r.approved_name ? `<li><i class="bi bi-check-circle-fill" style="color:#3fb950"></i> Approved by: <span style="color:#e6edf3;margin-left:.25rem">${r.approved_name}</span></li>` : ''}
      ${r.created_at ? `<li><i class="bi bi-clock"></i> Created: <span style="color:#e6edf3;margin-left:.25rem">${r.created_at}</span></li>` : ''}
    </ul>
    ${r.notes ? `<div class="detail-section">Notes</div><p style="font-size:.875rem;color:#c9d1d9;background:#0d1117;border-radius:9px;padding:.75rem 1rem;margin:0">${r.notes}</p>` : ''}
  `;
  document.getElementById('viewOv').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeView(){ document.getElementById('viewOv').classList.remove('open'); document.body.style.overflow=''; }

/* ── Advance status ── */
function advanceStatus(id, poNum, newStatus, label) {
  document.getElementById('adv-id').value         = id;
  document.getElementById('adv-new-status').value = newStatus;
  document.getElementById('adv-title').textContent= label + ': ' + poNum;
  document.getElementById('adv-msg').textContent  = 'Change status of ' + poNum + ' to ' + newStatus + '?';
  const delivWrap = document.getElementById('adv-delivery-wrap');
  delivWrap.style.display = newStatus === 'DELIVERED' ? '' : 'none';
  document.getElementById('advOv').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeAdv(){ document.getElementById('advOv').classList.remove('open'); document.body.style.overflow=''; }

/* ── Cancel ── */
function cancelOrder(id, poNum) {
  document.getElementById('cancel-id').value          = id;
  document.getElementById('cancel-num').textContent   = poNum;
  document.getElementById('cancelOv').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeCancelModal(){ document.getElementById('cancelOv').classList.remove('open'); document.body.style.overflow=''; }

/* ── Delete ── */
function deleteOrder(id, poNum) {
  document.getElementById('del-id').value  = id;
  document.getElementById('del-num').textContent = poNum;
  document.getElementById('delOv').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeDelModal(){ document.getElementById('delOv').classList.remove('open'); document.body.style.overflow=''; }

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    closeCreate(); closeEdit(); closeView();
    closeAdv(); closeCancelModal(); closeDelModal();
  }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
