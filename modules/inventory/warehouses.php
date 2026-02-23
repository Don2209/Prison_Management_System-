<?php
$pageTitle = 'Warehouses';
require_once '../../includes/header.php';
requireAuth();
verifyPermission('view', 'inventory');

$fid  = getCurrentFacility();
$isSA = isSuperAdmin();

/* ══════════════ POST HANDLER ══════════════ */
$flash = '';
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* ── CREATE ── */
    if ($action === 'create') {
        $name     = trim($_POST['name']     ?? '');
        $location = trim($_POST['location'] ?? '');
        $capacity = trim($_POST['capacity'] ?? '');
        $status   = trim($_POST['status']   ?? 'ACTIVE');
        $whFid    = $isSA ? (int)($_POST['facility_id'] ?? $fid) : $fid;

        $errs = [];
        if (!$name) $errs[] = 'Name is required.';
        if ($capacity !== '' && (!ctype_digit($capacity) || (int)$capacity < 1))
            $errs[] = 'Capacity must be a positive number.';

        if (!$errs) {
            $dup = fetchOne("SELECT id FROM warehouses WHERE name=? AND facility_id=? AND deleted_at IS NULL",
                [$name, $whFid], 'si');
            if ($dup) $errs[] = 'A warehouse with that name already exists in this facility.';
        }

        if (!$errs) {
            executeQuery(
                "INSERT INTO warehouses (facility_id, name, location, capacity, current_stock_value, status, created_at, updated_at)
                 VALUES (?,?,?,?,0,?,NOW(),NOW())",
                [$whFid, $name, $location ?: null, $capacity !== '' ? (int)$capacity : null, $status],
                'iisss'
            );
            $flash = 'Warehouse created successfully.';
        } else {
            $flash = implode(' ', $errs); $flashType = 'error';
        }
    }

    /* ── EDIT ── */
    if ($action === 'edit') {
        $id       = (int)($_POST['id']       ?? 0);
        $name     = trim($_POST['name']      ?? '');
        $location = trim($_POST['location']  ?? '');
        $capacity = trim($_POST['capacity']  ?? '');
        $status   = trim($_POST['status']    ?? 'ACTIVE');

        $errs = [];
        if (!$name) $errs[] = 'Name is required.';
        if (!$id)   $errs[] = 'Invalid warehouse.';
        if ($capacity !== '' && (!ctype_digit($capacity) || (int)$capacity < 1))
            $errs[] = 'Capacity must be a positive number.';

        if (!$errs) {
            $row = fetchOne("SELECT facility_id FROM warehouses WHERE id=? AND deleted_at IS NULL", [$id], 'i');
            if (!$row || (!$isSA && $row['facility_id'] != $fid))
                $errs[] = 'Warehouse not found or access denied.';
        }
        if (!$errs) {
            executeQuery(
                "UPDATE warehouses SET name=?, location=?, capacity=?, status=?, updated_at=NOW() WHERE id=?",
                [$name, $location ?: null, $capacity !== '' ? (int)$capacity : null, $status, $id],
                'ssisi'
            );
            $flash = 'Warehouse updated successfully.';
        } else {
            $flash = implode(' ', $errs); $flashType = 'error';
        }
    }

    /* ── DELETE ── */
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $row = $id ? fetchOne("SELECT facility_id FROM warehouses WHERE id=? AND deleted_at IS NULL", [$id], 'i') : null;
        if (!$row || (!$isSA && $row['facility_id'] != $fid)) {
            $flash = 'Warehouse not found or access denied.'; $flashType = 'error';
        } else {
            $hasItems = fetchOne("SELECT COUNT(*) c FROM inventory_items WHERE warehouse_id=? AND deleted_at IS NULL", [$id], 'i')['c'] ?? 0;
            if ($hasItems) {
                $flash = 'Cannot delete: this warehouse still holds inventory items. Reassign or remove them first.'; $flashType = 'error';
            } else {
                executeQuery("UPDATE warehouses SET deleted_at=NOW() WHERE id=?", [$id], 'i');
                $flash = 'Warehouse deleted.';
            }
        }
    }

    if (!$flash || $flashType === 'success') {
        $redirect = '?';
        if ($flash) $redirect .= 'msg='.urlencode($flash).'&';
        header('Location: '.$redirect); exit;
    }
}

if (!$flash && !empty($_GET['msg'])) {
    $flash = $_GET['msg']; $flashType = 'success';
}

/* ══════════════ DATA ══════════════ */
$facClause = $isSA ? "w.deleted_at IS NULL" : "w.deleted_at IS NULL AND w.facility_id = $fid";

$warehouses = fetchAll(
    "SELECT w.*, f.name AS facility_name,
            COUNT(ii.id)                          AS item_count,
            COALESCE(SUM(ii.current_quantity), 0) AS total_qty,
            COALESCE(SUM(ii.unit_cost * ii.current_quantity), 0) AS stock_val,
            SUM(CASE WHEN ii.status IN ('LOW_STOCK','OUT_OF_STOCK') THEN 1 ELSE 0 END) AS alert_count
     FROM warehouses w
     JOIN facilities f ON w.facility_id = f.id
     LEFT JOIN inventory_items ii ON w.id = ii.warehouse_id AND ii.deleted_at IS NULL
     WHERE $facClause
     GROUP BY w.id
     ORDER BY w.status ASC, w.name ASC",
    [], ''
);

$kpis = [
    'total'    => count($warehouses),
    'active'   => count(array_filter($warehouses, fn($w) => $w['status'] === 'ACTIVE')),
    'inactive' => count(array_filter($warehouses, fn($w) => $w['status'] === 'INACTIVE')),
    'items'    => array_sum(array_column($warehouses, 'item_count')),
    'value'    => array_sum(array_column($warehouses, 'stock_val')),
    'alerts'   => array_sum(array_column($warehouses, 'alert_count')),
];

/* Facilities list for super admin create form */
$facilities = $isSA ? fetchAll("SELECT id, name FROM facilities WHERE deleted_at IS NULL ORDER BY name", [], '') : [];

$canCreate = hasPermission('create', 'inventory');
$canEdit   = hasPermission('edit',   'inventory');
?>
<style>
:root{--sur:#161b22;--bdr:#21262d;--bg:#0d1117;--txt:#e6edf3;--mut:#8b949e;--acc:#1f6feb}

.ph{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:.75rem}
.ph h1{font-size:1.35rem;font-weight:700;color:var(--txt);margin:0}
.bc{font-size:.78rem;color:#6e7681;display:flex;align-items:center;gap:.3rem;margin-bottom:.3rem}
.bc a{color:#388bfd;text-decoration:none}.bc a:hover{text-decoration:underline}
.ph-actions{display:flex;align-items:center;gap:.6rem}

/* KPIs */
.kpi-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:1rem;margin-bottom:1.5rem}
@media(max-width:1100px){.kpi-grid{grid-template-columns:repeat(3,1fr)}}
@media(max-width:640px){.kpi-grid{grid-template-columns:repeat(2,1fr)}}
.kpi{background:var(--sur);border:1px solid var(--bdr);border-radius:14px;padding:1.1rem 1.2rem;position:relative;transition:transform .18s,box-shadow .18s}
.kpi:hover{transform:translateY(-2px);box-shadow:0 6px 24px rgba(0,0,0,.35)}
.kpi-ico{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1rem;margin-bottom:.75rem}
.kpi-val{font-size:1.6rem;font-weight:800;color:var(--txt);line-height:1;margin-bottom:.25rem}
.kpi-lbl{font-size:.72rem;font-weight:600;color:var(--mut);text-transform:uppercase;letter-spacing:.05em}

/* Warehouse cards grid */
.wh-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1.25rem}
.wh-card{background:var(--sur);border:1px solid var(--bdr);border-radius:16px;overflow:hidden;transition:transform .18s,box-shadow .18s}
.wh-card:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(0,0,0,.4)}
.wh-card-top{padding:1.1rem 1.25rem .85rem;border-bottom:1px solid var(--bdr);position:relative}
.wh-ribbon{position:absolute;top:0;left:0;right:0;height:3px;border-radius:16px 16px 0 0}
.wh-head{display:flex;align-items:flex-start;justify-content:space-between;gap:.5rem;margin-top:.25rem}
.wh-icon{width:42px;height:42px;background:rgba(56,139,253,.12);border-radius:11px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:1.1rem;color:#388bfd}
.wh-name{font-size:.95rem;font-weight:700;color:var(--txt);line-height:1.3;flex:1;min-width:0}
.wh-loc{font-size:.75rem;color:#8b949e;margin-top:.25rem;display:flex;align-items:center;gap:.3rem}
.wh-status-chip{display:inline-block;padding:2px 9px;border-radius:20px;font-size:.7rem;font-weight:700;flex-shrink:0}
.wh-facility{font-size:.73rem;color:#8b949e;margin-top:.5rem;display:flex;align-items:center;gap:.3rem}

/* Capacity meter */
.cap-section{padding:.85rem 1.25rem;border-bottom:1px solid var(--bdr)}
.cap-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:.4rem}
.cap-label{font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.07em;color:#8b949e}
.cap-pct{font-size:.82rem;font-weight:800}
.cap-bar-bg{height:6px;background:#21262d;border-radius:3px;overflow:hidden}
.cap-bar-fill{height:100%;border-radius:3px;transition:width .4s,background .4s}
.cap-hint{font-size:.7rem;color:#8b949e;margin-top:.35rem}

/* Stats row */
.wh-stats{display:grid;grid-template-columns:repeat(3,1fr);border-bottom:1px solid var(--bdr)}
.wh-stat{padding:.7rem .75rem;text-align:center;border-right:1px solid var(--bdr)}
.wh-stat:last-child{border-right:none}
.wh-stat-val{font-size:1rem;font-weight:800;color:var(--txt)}
.wh-stat-lbl{font-size:.67rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:#8b949e;margin-top:.15rem}

/* Actions footer */
.wh-footer{display:flex;align-items:center;gap:.5rem;padding:.75rem 1.25rem}
.act-btn{display:inline-flex;align-items:center;gap:.35rem;padding:.4rem .85rem;border-radius:8px;font-size:.78rem;font-weight:600;border:1px solid #30363d;background:transparent;color:#8b949e;cursor:pointer;transition:all .18s;text-decoration:none;white-space:nowrap}
.act-btn:hover{border-color:#388bfd;color:#388bfd;background:rgba(56,139,253,.08)}
.act-btn.danger:hover{border-color:#f85149;color:#f85149;background:rgba(248,81,73,.08)}
.act-btn.items-btn:hover{border-color:#3fb950;color:#3fb950;background:rgba(63,185,80,.08)}

/* Alerts badge on card */
.alert-dot{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:20px;font-size:.68rem;font-weight:700;background:rgba(243,156,18,.15);color:#f39c12;margin-left:.4rem}

/* Buttons */
.btn-primary-sm{background:linear-gradient(135deg,#1f6feb,#388bfd);color:#fff;padding:.5rem 1.1rem;border-radius:9px;font-size:.85rem;font-weight:600;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;text-decoration:none;transition:opacity .18s;white-space:nowrap}
.btn-primary-sm:hover{opacity:.87;color:#fff}

/* Flash */
.flash{border-radius:11px;padding:.8rem 1.1rem;margin-bottom:1.25rem;font-size:.875rem;display:flex;align-items:center;gap:.55rem}
.flash.success{background:rgba(63,185,80,.12);border:1px solid rgba(63,185,80,.3);color:#3fb950}
.flash.error{background:rgba(248,81,73,.08);border:1px solid rgba(248,81,73,.3);color:#f85149}

/* Empty */
.empty-state{text-align:center;padding:4rem 1rem;color:#8b949e}
.empty-state i{font-size:2.8rem;display:block;margin-bottom:.75rem;opacity:.3}
.empty-state p{font-size:.9rem;margin:0}

/* MODAL */
.modal-ov{position:fixed;inset:0;background:rgba(1,4,9,.77);z-index:1000;display:flex;align-items:center;justify-content:center;padding:1rem;opacity:0;pointer-events:none;transition:opacity .22s}
.modal-ov.open{opacity:1;pointer-events:auto}
.modal-box{background:#161b22;border:1px solid #30363d;border-radius:18px;width:100%;max-width:520px;max-height:90vh;overflow-y:auto;transform:translateY(20px);transition:transform .22s}
.modal-ov.open .modal-box{transform:translateY(0)}
.modal-hdr{display:flex;align-items:center;justify-content:space-between;padding:1.1rem 1.4rem;border-bottom:1px solid #21262d;position:sticky;top:0;background:#161b22;z-index:1}
.modal-hdr h2{margin:0;font-size:1rem;font-weight:700;color:#e6edf3;display:flex;align-items:center;gap:.5rem}
.modal-close{background:none;border:none;color:#8b949e;font-size:1.1rem;cursor:pointer;width:30px;height:30px;border-radius:7px;display:flex;align-items:center;justify-content:center;transition:all .18s}
.modal-close:hover{background:#21262d;color:#e6edf3}
.modal-body{padding:1.4rem}
.fg{display:flex;flex-direction:column;gap:.35rem;margin-bottom:1rem}
.fg:last-child{margin-bottom:0}
.fg-row2{display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem}
@media(max-width:480px){.fg-row2{grid-template-columns:1fr}}
.fg label{font-size:.8rem;font-weight:600;color:var(--mut)}
.req{color:#f85149}
.fi{background:#0d1117;border:1px solid #30363d;border-radius:9px;color:var(--txt);padding:.55rem .85rem;font-size:.875rem;width:100%;outline:none;transition:border .2s,box-shadow .2s;font-family:inherit;box-sizing:border-box}
.fi::placeholder{color:#484f58}
.fi:focus{border-color:#388bfd;box-shadow:0 0 0 3px rgba(56,139,253,.15)}
select.fi{cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='%238b949e' d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right .75rem center;background-size:11px;padding-right:2rem}
.fi-icon-wrap{position:relative}
.fi-icon-wrap .fi-ico{position:absolute;left:.8rem;top:50%;transform:translateY(-50%);color:#8b949e;font-size:.82rem;pointer-events:none}
.fi-icon-wrap .fi{padding-left:2.2rem}
.modal-footer{display:flex;justify-content:flex-end;gap:.6rem;padding-top:1rem;border-top:1px solid #21262d;margin-top:1.1rem;flex-wrap:wrap}
.btn-ghost{background:transparent;border:1px solid #30363d;color:#8b949e;padding:.55rem 1.1rem;border-radius:9px;font-size:.875rem;font-weight:500;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;transition:all .18s}
.btn-ghost:hover{border-color:#58a6ff;color:#58a6ff}
.btn-danger{background:rgba(248,81,73,.1);border:1px solid rgba(248,81,73,.3);color:#f85149;padding:.55rem 1.1rem;border-radius:9px;font-size:.875rem;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;transition:all .18s}
.btn-danger:hover{background:rgba(248,81,73,.2)}
.note{background:rgba(248,81,73,.07);border:1px solid rgba(248,81,73,.2);border-radius:8px;padding:.6rem .9rem;font-size:.8rem;color:#f85149;display:flex;align-items:flex-start;gap:.45rem;margin-bottom:1rem}
.note i{flex-shrink:0;margin-top:1px}
</style>

<!-- PAGE HEADER -->
<div class="ph">
  <div>
    <div class="bc">
      <a href="<?php echo APP_URL; ?>/dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
      <i class="bi bi-chevron-right" style="font-size:.6rem"></i>
      <a href="<?php echo APP_URL; ?>/modules/inventory/index.php">Inventory</a>
      <i class="bi bi-chevron-right" style="font-size:.6rem"></i>
      <span>Warehouses</span>
    </div>
    <h1><i class="bi bi-building-fill" style="color:#388bfd;margin-right:.45rem"></i>Warehouses</h1>
  </div>
  <div class="ph-actions">
    <a href="<?php echo APP_URL; ?>/modules/inventory/index.php" class="btn-ghost" style="font-size:.82rem;padding:.42rem .9rem">
      <i class="bi bi-boxes"></i> Inventory
    </a>
    <a href="<?php echo APP_URL; ?>/modules/inventory/purchase-orders.php" class="btn-ghost" style="font-size:.82rem;padding:.42rem .9rem">
      <i class="bi bi-cart-check"></i> Purchase Orders
    </a>
    <?php if ($canCreate): ?>
    <button class="btn-primary-sm" onclick="openCreate()">
      <i class="bi bi-plus-lg"></i> Add Warehouse
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
<?php
$kpiDefs = [
    ['Total',          $kpis['total'],                      'bi-building-fill',        '#388bfd', 'rgba(56,139,253,.15)',  '#1f6feb'],
    ['Active',         $kpis['active'],                     'bi-check-circle-fill',    '#3fb950', 'rgba(63,185,80,.15)',   '#3fb950'],
    ['Inactive',       $kpis['inactive'],                   'bi-pause-circle-fill',    '#8b949e', 'rgba(139,148,158,.15)','#8b949e'],
    ['Total Items',    $kpis['items'],                      'bi-box-seam-fill',        '#58a6ff', 'rgba(88,166,255,.15)',  '#58a6ff'],
    ['Stock Value',    '$'.number_format($kpis['value'],2), 'bi-currency-dollar',       '#bb8fce', 'rgba(187,143,206,.15)','#bb8fce'],
    ['Alerts',         $kpis['alerts'],                     'bi-exclamation-triangle-fill','#f39c12','rgba(243,156,18,.15)','#f39c12'],
];
foreach ($kpiDefs as [$lbl,$val,$icon,$clr,$bg,$bar]): ?>
<div class="kpi" style="border-top:3px solid <?php echo $bar; ?>">
  <div class="kpi-ico" style="background:<?php echo $bg; ?>;color:<?php echo $clr; ?>"><i class="bi <?php echo $icon; ?>"></i></div>
  <div class="kpi-val" style="font-size:<?php echo strlen((string)$val)>6?'1.15rem':'1.6rem'; ?>"><?php echo is_numeric($val)?number_format((float)$val):$val; ?></div>
  <div class="kpi-lbl"><?php echo $lbl; ?></div>
</div>
<?php endforeach; ?>
</div>

<!-- WAREHOUSE CARDS -->
<?php if (empty($warehouses)): ?>
<div class="empty-state">
  <i class="bi bi-building-x"></i>
  <p>No warehouses found.<?php echo $canCreate ? ' <a href="#" onclick="openCreate()" style="color:#388bfd">Add the first one</a>.' : ''; ?></p>
</div>
<?php else: ?>
<div class="wh-grid">
<?php foreach ($warehouses as $wh):
    $isActive = $wh['status'] === 'ACTIVE';
    $ribbonClr = $isActive ? '#388bfd' : '#484f58';
    $cap   = (int)$wh['capacity'];
    $qty   = (int)$wh['total_qty'];
    $capPct  = ($cap > 0 && $qty > 0) ? min(100, round($qty / $cap * 100)) : 0;
    $barClr  = $capPct >= 90 ? '#f85149' : ($capPct >= 70 ? '#f39c12' : '#3fb950');
    $alerts  = (int)$wh['alert_count'];
    $val     = (float)$wh['stock_val'];
?>
<div class="wh-card">
  <div class="wh-ribbon" style="background:<?php echo $ribbonClr; ?>"></div>
  <div class="wh-card-top">
    <div class="wh-head">
      <div class="wh-icon"><i class="bi bi-building"></i></div>
      <div style="flex:1;min-width:0;padding:0 .5rem">
        <div class="wh-name">
          <?php echo htmlspecialchars($wh['name']); ?>
          <?php if ($alerts > 0): ?>
          <span class="alert-dot"><i class="bi bi-exclamation-triangle-fill"></i> <?php echo $alerts; ?></span>
          <?php endif; ?>
        </div>
        <?php if ($wh['location']): ?>
        <div class="wh-loc"><i class="bi bi-geo-alt"></i><?php echo htmlspecialchars($wh['location']); ?></div>
        <?php endif; ?>
        <?php if ($isSA): ?>
        <div class="wh-facility"><i class="bi bi-buildings"></i><?php echo htmlspecialchars($wh['facility_name']); ?></div>
        <?php endif; ?>
      </div>
      <span class="wh-status-chip" style="background:<?php echo $isActive?'rgba(63,185,80,.15)':'rgba(139,148,158,.15)'; ?>;color:<?php echo $isActive?'#3fb950':'#8b949e'; ?>">
        <?php echo $isActive ? 'Active' : 'Inactive'; ?>
      </span>
    </div>
  </div>

  <!-- Capacity meter (only if capacity is set) -->
  <?php if ($cap > 0): ?>
  <div class="cap-section">
    <div class="cap-row">
      <span class="cap-label">Capacity Usage</span>
      <span class="cap-pct" style="color:<?php echo $barClr; ?>"><?php echo $capPct; ?>%</span>
    </div>
    <div class="cap-bar-bg">
      <div class="cap-bar-fill" style="width:<?php echo $capPct; ?>%;background:<?php echo $barClr; ?>"></div>
    </div>
    <div class="cap-hint"><?php echo number_format($qty); ?> units stored of <?php echo number_format($cap); ?> capacity</div>
  </div>
  <?php endif; ?>

  <!-- Stats -->
  <div class="wh-stats">
    <div class="wh-stat">
      <div class="wh-stat-val"><?php echo number_format((int)$wh['item_count']); ?></div>
      <div class="wh-stat-lbl">Items</div>
    </div>
    <div class="wh-stat">
      <div class="wh-stat-val"><?php echo number_format($qty); ?></div>
      <div class="wh-stat-lbl">Units</div>
    </div>
    <div class="wh-stat">
      <div class="wh-stat-val" style="font-size:.82rem">$<?php echo number_format($val, 0); ?></div>
      <div class="wh-stat-lbl">Value</div>
    </div>
  </div>

  <!-- Footer actions -->
  <div class="wh-footer">
    <a href="<?php echo APP_URL; ?>/modules/inventory/index.php?wh=<?php echo $wh['id']; ?>"
       class="act-btn items-btn" style="flex:1;justify-content:center">
      <i class="bi bi-boxes"></i> View Items
    </a>
    <?php if ($canEdit): ?>
    <button class="act-btn" title="Edit"
      onclick='openEdit(<?php echo json_encode([
        "id"          => $wh["id"],
        "name"        => $wh["name"],
        "location"    => $wh["location"] ?? "",
        "capacity"    => $cap ?: "",
        "status"      => $wh["status"],
        "facility_id" => $wh["facility_id"],
      ]); ?>)'>
      <i class="bi bi-pencil"></i>
    </button>
    <button class="act-btn danger" title="Delete"
      onclick='openDelete(<?php echo $wh["id"]; ?>, <?php echo json_encode($wh["name"]); ?>, <?php echo (int)$wh["item_count"]; ?>)'>
      <i class="bi bi-trash3"></i>
    </button>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ══════════ CREATE MODAL ══════════ -->
<div class="modal-ov" id="createOv" onclick="if(event.target===this)closeCreate()">
  <div class="modal-box">
    <div class="modal-hdr">
      <h2><i class="bi bi-building-add" style="color:#388bfd"></i> Add Warehouse</h2>
      <button class="modal-close" onclick="closeCreate()"><i class="bi bi-x"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="create">
      <div class="modal-body">
        <?php if ($isSA && !empty($facilities)): ?>
        <div class="fg">
          <label class="fg label">Facility <span class="req">*</span></label>
          <select name="facility_id" class="fi" required>
            <?php foreach ($facilities as $f): ?>
            <option value="<?php echo $f['id']; ?>"><?php echo htmlspecialchars($f['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <div class="fg">
          <label>Name <span class="req">*</span></label>
          <input type="text" name="name" class="fi" placeholder="e.g. Main Warehouse" required autofocus>
        </div>
        <div class="fg">
          <label>Location / Room</label>
          <div class="fi-icon-wrap">
            <i class="bi bi-geo-alt fi-ico"></i>
            <input type="text" name="location" class="fi" placeholder="e.g. Ground Floor, East Wing">
          </div>
        </div>
        <div class="fg-row2">
          <div class="fg" style="margin-bottom:0">
            <label>Capacity (units)</label>
            <input type="number" name="capacity" class="fi" placeholder="e.g. 1000" min="1">
          </div>
          <div class="fg" style="margin-bottom:0">
            <label>Status</label>
            <select name="status" class="fi">
              <option value="ACTIVE">Active</option>
              <option value="INACTIVE">Inactive</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-ghost" onclick="closeCreate()"><i class="bi bi-x"></i> Cancel</button>
          <button type="submit" class="btn-primary-sm"><i class="bi bi-building-add"></i> Create</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- ══════════ EDIT MODAL ══════════ -->
<div class="modal-ov" id="editOv" onclick="if(event.target===this)closeEdit()">
  <div class="modal-box">
    <div class="modal-hdr">
      <h2><i class="bi bi-pencil-fill" style="color:#388bfd"></i> Edit Warehouse</h2>
      <button class="modal-close" onclick="closeEdit()"><i class="bi bi-x"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id"     id="edit-id">
      <div class="modal-body">
        <div class="fg">
          <label>Name <span class="req">*</span></label>
          <input type="text" name="name" id="edit-name" class="fi" required>
        </div>
        <div class="fg">
          <label>Location / Room</label>
          <div class="fi-icon-wrap">
            <i class="bi bi-geo-alt fi-ico"></i>
            <input type="text" name="location" id="edit-location" class="fi" placeholder="e.g. Ground Floor, East Wing">
          </div>
        </div>
        <div class="fg-row2">
          <div class="fg" style="margin-bottom:0">
            <label>Capacity (units)</label>
            <input type="number" name="capacity" id="edit-capacity" class="fi" placeholder="e.g. 1000" min="1">
          </div>
          <div class="fg" style="margin-bottom:0">
            <label>Status</label>
            <select name="status" id="edit-status" class="fi">
              <option value="ACTIVE">Active</option>
              <option value="INACTIVE">Inactive</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-ghost" onclick="closeEdit()"><i class="bi bi-x"></i> Cancel</button>
          <button type="submit" class="btn-primary-sm"><i class="bi bi-check-lg"></i> Save Changes</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- ══════════ DELETE MODAL ══════════ -->
<div class="modal-ov" id="delOv" onclick="if(event.target===this)closeDelete()">
  <div class="modal-box" style="max-width:420px">
    <div class="modal-hdr">
      <h2><i class="bi bi-trash3-fill" style="color:#f85149"></i> Delete Warehouse</h2>
      <button class="modal-close" onclick="closeDelete()"><i class="bi bi-x"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" id="del-id">
      <div class="modal-body">
        <div class="note"><i class="bi bi-exclamation-triangle-fill"></i>
          This action cannot be undone. Warehouses with inventory items cannot be deleted.
        </div>
        <p style="font-size:.9rem;color:#c9d1d9;margin:0 0 1rem">
          Are you sure you want to delete <strong id="del-name" style="color:#e6edf3"></strong>?
        </p>
        <div id="del-has-items" style="display:none;background:rgba(248,81,73,.08);border:1px solid rgba(248,81,73,.3);border-radius:9px;padding:.7rem .9rem;font-size:.82rem;color:#f85149;margin-bottom:1rem">
          <i class="bi bi-box-seam"></i> This warehouse has inventory items. Reassign or remove them before deleting.
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-ghost" onclick="closeDelete()">Cancel</button>
          <button type="submit" id="del-btn" class="btn-danger"><i class="bi bi-trash3"></i> Delete</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
function openCreate() { document.getElementById('createOv').classList.add('open'); document.body.style.overflow='hidden'; }
function closeCreate(){ document.getElementById('createOv').classList.remove('open'); document.body.style.overflow=''; }

function openEdit(r) {
  document.getElementById('edit-id').value       = r.id;
  document.getElementById('edit-name').value     = r.name;
  document.getElementById('edit-location').value = r.location || '';
  document.getElementById('edit-capacity').value = r.capacity || '';
  document.getElementById('edit-status').value   = r.status;
  document.getElementById('editOv').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeEdit(){ document.getElementById('editOv').classList.remove('open'); document.body.style.overflow=''; }

function openDelete(id, name, itemCount) {
  document.getElementById('del-id').value = id;
  document.getElementById('del-name').textContent = name;
  var hasItems = document.getElementById('del-has-items');
  var btn = document.getElementById('del-btn');
  if (itemCount > 0) { hasItems.style.display=''; btn.disabled=true; btn.style.opacity='.4'; }
  else               { hasItems.style.display='none'; btn.disabled=false; btn.style.opacity='1'; }
  document.getElementById('delOv').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeDelete(){ document.getElementById('delOv').classList.remove('open'); document.body.style.overflow=''; }

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') { closeCreate(); closeEdit(); closeDelete(); }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
