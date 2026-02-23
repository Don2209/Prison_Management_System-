<?php
$pageTitle = 'Inventory Management';
require_once '../../includes/header.php';
requireAuth();
verifyPermission('view', 'inventory');

$fid  = getCurrentFacility();
$isSA = isSuperAdmin();

/* ═══ FILTERS ═══ */
$fTab  = $_GET['tab'] ?? 'all';
$fQ    = trim($_GET['q']   ?? '');
$fCat  = trim($_GET['cat'] ?? '');
$fWh   = trim($_GET['wh']  ?? '');
$validTabs = ['all','IN_STOCK','LOW_STOCK','OUT_OF_STOCK','OBSOLETE'];
if (!in_array($fTab, $validTabs)) $fTab = 'all';

/* ═══ WHERE ═══ */
$wParts  = ["ii.deleted_at IS NULL"];
$wParams = []; $wTypes = '';

if (!$isSA) { $wParts[] = "ii.facility_id = ?"; $wParams[] = $fid; $wTypes .= 'i'; }
if ($fTab !== 'all') { $wParts[] = "ii.status = ?"; $wParams[] = $fTab; $wTypes .= 's'; }
if ($fCat) { $wParts[] = "ii.category_id = ?"; $wParams[] = (int)$fCat; $wTypes .= 'i'; }
if ($fWh)  { $wParts[] = "ii.warehouse_id = ?"; $wParams[] = (int)$fWh; $wTypes .= 'i'; }
if ($fQ) {
    $s = "%$fQ%";
    $wParts[]  = "(ii.name LIKE ? OR ii.sku LIKE ? OR ic.name LIKE ?)";
    $wParams   = array_merge($wParams, [$s,$s,$s]); $wTypes .= 'sss';
}
$where = implode(' AND ', $wParts);

/* ═══ KPIs (use mr.facility_id directly) ═══ */
$fc   = $isSA ? "1=1" : "facility_id = $fid";
$base = "FROM inventory_items WHERE $fc AND deleted_at IS NULL";
$kpis = [
    'total'     => fetchOne("SELECT COUNT(*) c $base",                                    [], '')['c'] ?? 0,
    'in_stock'  => fetchOne("SELECT COUNT(*) c $base AND status='IN_STOCK'",              [], '')['c'] ?? 0,
    'low'       => fetchOne("SELECT COUNT(*) c $base AND status='LOW_STOCK'",             [], '')['c'] ?? 0,
    'out'       => fetchOne("SELECT COUNT(*) c $base AND status='OUT_OF_STOCK'",          [], '')['c'] ?? 0,
    'obsolete'  => fetchOne("SELECT COUNT(*) c $base AND status='OBSOLETE'",              [], '')['c'] ?? 0,
    'value'     => fetchOne("SELECT COALESCE(SUM(unit_cost*current_quantity),0) v $base", [], '')['v'] ?? 0,
];

/* ═══ LOOKUP DROPDOWNS ═══ */
$categories = fetchAll("SELECT id, name FROM inventory_categories WHERE deleted_at IS NULL ORDER BY name", [], '');
$warehouses = $isSA
    ? fetchAll("SELECT id, name FROM warehouses WHERE deleted_at IS NULL ORDER BY name", [], '')
    : fetchAll("SELECT id, name FROM warehouses WHERE facility_id=? AND deleted_at IS NULL ORDER BY name", [$fid], 'i');

/* ═══ MAIN QUERY ═══ */
$items = fetchAll(
    "SELECT ii.id, ii.name, ii.sku, ii.description, ii.current_quantity, ii.reorder_level,
            ii.reorder_quantity, ii.unit_cost, ii.unit_of_measure, ii.status, ii.last_reorder_date,
            ic.name AS cat_name,
            w.name  AS wh_name
     FROM inventory_items ii
     JOIN inventory_categories ic ON ii.category_id = ic.id
     JOIN warehouses w             ON ii.warehouse_id = w.id
     WHERE $where
     ORDER BY ii.status ASC, ii.name ASC
     LIMIT 300",
    $wParams, $wTypes
);

/* ═══ HELPERS ═══ */
function stockChip($s) {
    $m = [
        'IN_STOCK'    => ['rgba(63,185,80,.15)',    '#3fb950', 'bi-check-circle-fill', 'In Stock'],
        'LOW_STOCK'   => ['rgba(243,156,18,.15)',   '#f39c12', 'bi-exclamation-circle-fill', 'Low Stock'],
        'OUT_OF_STOCK'=> ['rgba(248,81,73,.15)',    '#f85149', 'bi-x-circle-fill',     'Out of Stock'],
        'OBSOLETE'    => ['rgba(139,148,158,.15)',  '#8b949e', 'bi-archive-fill',      'Obsolete'],
    ];
    $c = $m[$s] ?? $m['IN_STOCK'];
    return '<span style="display:inline-flex;align-items:center;gap:4px;padding:2px 10px;border-radius:20px;font-size:.7rem;font-weight:700;background:'.$c[0].';color:'.$c[1].'"><i class="bi '.$c[2].'"></i>'.$c[3].'</span>';
}
function catColor($name) {
    $map = ['Food & Beverage'=>'#3fb950','Toiletries'=>'#bb8fce','Clothing'=>'#58a6ff',
            'Medical Supplies'=>'#f85149','Cleaning Supplies'=>'#1abc9c','Office Supplies'=>'#f39c12',
            'Maintenance'=>'#e67e22','Security Equipment'=>'#e74c3c'];
    return $map[$name] ?? '#8b949e';
}
?>
<style>
:root{--sur:#161b22;--bdr:#21262d;--bg:#0d1117;--txt:#e6edf3;--mut:#8b949e;--acc:#1f6feb}

/* KPIs */
.kpi-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:1rem;margin-bottom:1.5rem}
@media(max-width:1100px){.kpi-grid{grid-template-columns:repeat(3,1fr)}}
@media(max-width:640px){.kpi-grid{grid-template-columns:repeat(2,1fr)}}
.kpi{background:var(--sur);border:1px solid var(--bdr);border-radius:14px;padding:1.1rem 1.2rem;position:relative;transition:transform .18s,box-shadow .18s}
.kpi:hover{transform:translateY(-2px);box-shadow:0 6px 24px rgba(0,0,0,.35)}
.kpi-ico{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1rem;margin-bottom:.75rem}
.kpi-val{font-size:1.6rem;font-weight:800;color:var(--txt);line-height:1;margin-bottom:.25rem}
.kpi-lbl{font-size:.72rem;font-weight:600;color:var(--mut);text-transform:uppercase;letter-spacing:.05em}

/* Tabs */
.tabs{display:flex;gap:.3rem;background:var(--sur);border:1px solid var(--bdr);border-radius:11px;padding:.3rem;margin-bottom:1rem;flex-wrap:wrap}
.tabs a{padding:.4rem .95rem;border-radius:8px;font-size:.82rem;font-weight:600;color:var(--mut);text-decoration:none;display:flex;align-items:center;gap:.35rem;transition:all .18s;white-space:nowrap}
.tabs a:hover,.tabs a.active{background:#21262d;color:var(--txt)}
.tb{background:#21262d;color:#8b949e;border-radius:20px;padding:1px 7px;font-size:.68rem;font-weight:700}
.tabs a.active .tb{background:var(--acc);color:#fff}

/* Toolbar */
.toolbar{display:flex;align-items:center;gap:.6rem;margin-bottom:1rem;flex-wrap:wrap}
.search-wrap{position:relative;flex:1;min-width:200px;max-width:300px}
.search-wrap i{position:absolute;left:.75rem;top:50%;transform:translateY(-50%);color:#8b949e;font-size:.8rem;pointer-events:none}
.fi-search{background:#0d1117;border:1px solid #30363d;border-radius:9px;color:var(--txt);padding:.5rem .85rem .5rem 2.2rem;font-size:.875rem;outline:none;transition:border .2s,box-shadow .2s;width:100%;font-family:inherit}
.fi-search::placeholder{color:#484f58}
.fi-search:focus{border-color:#388bfd;box-shadow:0 0 0 3px rgba(56,139,253,.15)}
.fi-sm{background:#0d1117;border:1px solid #30363d;border-radius:9px;color:var(--txt);padding:.48rem .65rem;font-size:.82rem;outline:none;transition:border .2s;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='%238b949e' d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right .55rem center;background-size:11px;padding-right:1.8rem;cursor:pointer}
.fi-sm:focus{border-color:#388bfd}
.clear-btn{background:none;border:1px solid #30363d;border-radius:8px;color:#8b949e;padding:.4rem .75rem;font-size:.78rem;cursor:pointer;display:flex;align-items:center;gap:.3rem;text-decoration:none;transition:all .18s;white-space:nowrap}
.clear-btn:hover{border-color:#58a6ff;color:#58a6ff}
.btn-primary-sm{background:linear-gradient(135deg,#1f6feb,#388bfd);color:#fff;padding:.5rem 1.1rem;border-radius:9px;font-size:.85rem;font-weight:600;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;text-decoration:none;transition:opacity .18s;white-space:nowrap}
.btn-primary-sm:hover{opacity:.87;color:#fff}
.btn-ghost{background:transparent;border:1px solid #30363d;color:#8b949e;padding:.55rem 1.1rem;border-radius:9px;font-size:.875rem;font-weight:500;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;text-decoration:none;transition:all .18s;white-space:nowrap}
.btn-ghost:hover{border-color:#58a6ff;color:#58a6ff}

/* Table card */
.tcard{background:var(--sur);border:1px solid var(--bdr);border-radius:16px;overflow:hidden}
.tcard-head{display:flex;align-items:center;justify-content:space-between;padding:.85rem 1.25rem;border-bottom:1px solid var(--bdr)}
.tcard-head .ttl{font-size:.87rem;font-weight:700;color:var(--txt)}
.tcard-head .cnt{font-size:.78rem;color:#8b949e}
table.inv-tbl{width:100%;border-collapse:collapse}
table.inv-tbl thead th{padding:.7rem 1rem;text-align:left;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#8b949e;border-bottom:1px solid var(--bdr);white-space:nowrap;background:rgba(255,255,255,.01)}
table.inv-tbl tbody tr{border-bottom:1px solid rgba(33,38,45,.8);transition:background .15s}
table.inv-tbl tbody tr:last-child{border-bottom:none}
table.inv-tbl tbody tr:hover{background:rgba(255,255,255,.03)}
table.inv-tbl td{padding:.75rem 1rem;font-size:.85rem;color:var(--txt);vertical-align:middle}

/* SKU badge */
.sku{display:inline-block;background:#0d1117;border:1px solid #30363d;color:#8b949e;border-radius:6px;padding:2px 8px;font-size:.72rem;font-family:monospace;white-space:nowrap}
/* Item cell */
.item-cell .iname{font-weight:600;font-size:.875rem;color:var(--txt)}
.item-cell .idesc{font-size:.73rem;color:#8b949e;margin-top:2px;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
/* Category chip */
.cat-chip{display:inline-block;padding:2px 9px;border-radius:20px;font-size:.72rem;font-weight:600;white-space:nowrap}
/* Warehouse */
.wh-cell{font-size:.8rem;color:#8b949e;display:flex;align-items:center;gap:.35rem;white-space:nowrap}
/* Qty bar */
.qty-wrap{min-width:90px}
.qty-num{font-size:.9rem;font-weight:700}
.qty-bar-bg{height:4px;background:#21262d;border-radius:2px;overflow:hidden;margin-top:4px;width:80px}
.qty-bar-fill{height:100%;border-radius:2px;transition:width .3s}
.qty-sub{font-size:.68rem;color:#8b949e;margin-top:2px}
/* Cost */
.cost-cell{font-size:.87rem;font-weight:600;white-space:nowrap}
/* Actions */
.act-btn{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:7px;border:1px solid #30363d;background:transparent;color:#8b949e;cursor:pointer;transition:all .18s;text-decoration:none;font-size:.82rem}
.act-btn:hover{border-color:#388bfd;color:#388bfd;background:rgba(56,139,253,.08)}

/* Page header */
.ph{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:.75rem}
.ph h1{font-size:1.35rem;font-weight:700;color:var(--txt);margin:0}
.bc{font-size:.78rem;color:#6e7681;display:flex;align-items:center;gap:.3rem;margin-bottom:.3rem}
.bc a{color:#388bfd;text-decoration:none}.bc a:hover{text-decoration:underline}

/* Empty */
.empty-state{text-align:center;padding:3.5rem 1rem;color:#8b949e}
.empty-state i{font-size:2.5rem;display:block;margin-bottom:.75rem;opacity:.35}
.empty-state p{font-size:.9rem;margin:0}

/* Low stock warning banner */
.warn-banner{background:rgba(243,156,18,.08);border:1px solid rgba(243,156,18,.25);border-radius:11px;padding:.75rem 1.1rem;margin-bottom:1.25rem;font-size:.84rem;color:#f39c12;display:flex;align-items:center;gap:.55rem}

/* MODAL */
.modal-ov{position:fixed;inset:0;background:rgba(1,4,9,.77);z-index:1000;display:flex;align-items:center;justify-content:center;padding:1rem;opacity:0;pointer-events:none;transition:opacity .22s}
.modal-ov.open{opacity:1;pointer-events:auto}
.modal-box{background:#161b22;border:1px solid #30363d;border-radius:18px;width:100%;max-width:620px;max-height:90vh;overflow-y:auto;transform:translateY(20px);transition:transform .22s}
.modal-ov.open .modal-box{transform:translateY(0)}
.modal-hdr{display:flex;align-items:center;justify-content:space-between;padding:1.1rem 1.4rem;border-bottom:1px solid #21262d;position:sticky;top:0;background:#161b22;z-index:1}
.modal-hdr h2{margin:0;font-size:1rem;font-weight:700;color:#e6edf3;display:flex;align-items:center;gap:.5rem}
.modal-close{background:none;border:none;color:#8b949e;font-size:1.1rem;cursor:pointer;width:30px;height:30px;border-radius:7px;display:flex;align-items:center;justify-content:center;transition:all .18s}
.modal-close:hover{background:#21262d;color:#e6edf3}
.modal-body{padding:1.4rem}
.ms-title{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#8b949e;margin-bottom:.65rem;padding-bottom:.35rem;border-bottom:1px solid #21262d}
.m-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:.75rem;margin-bottom:1.1rem}
.m-grid3{display:grid;grid-template-columns:repeat(3,1fr);gap:.75rem;margin-bottom:1.1rem}
@media(max-width:480px){.m-grid,.m-grid3{grid-template-columns:1fr}}
.mf label{font-size:.72rem;color:#8b949e;display:block;margin-bottom:.2rem}
.mf span{font-size:.875rem;color:#e6edf3;font-weight:500}
.stock-meter{background:#0d1117;border:1px solid #21262d;border-radius:10px;padding:.75rem 1rem;margin-bottom:1.1rem}
.sm-bar-bg{height:8px;background:#21262d;border-radius:4px;overflow:hidden;margin:.5rem 0}
.sm-bar-fill{height:100%;border-radius:4px;transition:width .4s,background .4s}
.sm-nums{display:flex;justify-content:space-between;font-size:.75rem;color:#8b949e}
</style>

<!-- PAGE HEADER -->
<div class="ph">
  <div>
    <div class="bc">
      <a href="<?php echo APP_URL; ?>/dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
      <i class="bi bi-chevron-right" style="font-size:.6rem"></i>
      <span>Inventory</span>
    </div>
    <h1><i class="bi bi-boxes" style="color:#388bfd;margin-right:.45rem"></i>Inventory Management</h1>
  </div>
  <div style="display:flex;align-items:center;gap:.6rem">
    <a href="<?php echo APP_URL; ?>/modules/inventory/warehouses.php"
       class="btn-ghost" style="font-size:.82rem;padding:.42rem .9rem">
      <i class="bi bi-building"></i> Warehouses
    </a>
    <a href="<?php echo APP_URL; ?>/modules/inventory/purchase-orders.php"
       class="btn-ghost" style="font-size:.82rem;padding:.42rem .9rem">
      <i class="bi bi-cart-check"></i> Purchase Orders
    </a>
    <?php if (hasPermission('create','inventory')): ?>
    <a href="<?php echo APP_URL; ?>/modules/inventory/add-item.php" class="btn-primary-sm">
      <i class="bi bi-plus-lg"></i> Add Item
    </a>
    <?php endif; ?>
  </div>
</div>

<?php if ($kpis['low'] > 0 || $kpis['out'] > 0): ?>
<div class="warn-banner">
  <i class="bi bi-exclamation-triangle-fill"></i>
  <span>
    <?php if ($kpis['out'] > 0): ?>
      <strong><?php echo $kpis['out']; ?> item<?php echo $kpis['out']!=1?'s are':' is'; ?> out of stock</strong>
      <?php if ($kpis['low'] > 0): echo ' and '; endif; ?>
    <?php endif; ?>
    <?php if ($kpis['low'] > 0): ?>
      <strong><?php echo $kpis['low']; ?> item<?php echo $kpis['low']!=1?'s are':' is'; ?> running low</strong>
    <?php endif; ?>
    — reorder action may be needed.
  </span>
</div>
<?php endif; ?>

<!-- KPI GRID -->
<div class="kpi-grid">
<?php
$kpiDefs = [
    ['Total Items',    $kpis['total'],                          'bi-boxes',                '#388bfd', 'rgba(56,139,253,.15)',  '#1f6feb'],
    ['In Stock',       $kpis['in_stock'],                       'bi-check-circle-fill',    '#3fb950', 'rgba(63,185,80,.15)',   '#3fb950'],
    ['Low Stock',      $kpis['low'],                            'bi-exclamation-circle-fill','#f39c12','rgba(243,156,18,.15)','#f39c12'],
    ['Out of Stock',   $kpis['out'],                            'bi-x-circle-fill',        '#f85149', 'rgba(248,81,73,.15)',   '#f85149'],
    ['Obsolete',       $kpis['obsolete'],                       'bi-archive-fill',         '#8b949e', 'rgba(139,148,158,.15)','#8b949e'],
    ['Total Value',    '$'.number_format($kpis['value'],2),     'bi-currency-dollar',       '#bb8fce', 'rgba(187,143,206,.15)','#bb8fce'],
];
foreach ($kpiDefs as [$lbl,$val,$icon,$clr,$bg,$bar]): ?>
<div class="kpi" style="border-top:3px solid <?php echo $bar; ?>">
  <div class="kpi-ico" style="background:<?php echo $bg; ?>;color:<?php echo $clr; ?>"><i class="bi <?php echo $icon; ?>"></i></div>
  <div class="kpi-val"><?php echo is_numeric($val) ? number_format((float)$val) : $val; ?></div>
  <div class="kpi-lbl"><?php echo $lbl; ?></div>
</div>
<?php endforeach; ?>
</div>

<!-- TABS -->
<div class="tabs">
<?php
$tabDefs = [
    ['all',          'All Items',    'bi-list-ul',                $kpis['total']   ],
    ['IN_STOCK',     'In Stock',     'bi-check-circle',           $kpis['in_stock']],
    ['LOW_STOCK',    'Low Stock',    'bi-exclamation-circle',     $kpis['low']     ],
    ['OUT_OF_STOCK', 'Out of Stock', 'bi-x-circle',               $kpis['out']     ],
    ['OBSOLETE',     'Obsolete',     'bi-archive',                $kpis['obsolete']],
];
foreach ($tabDefs as [$tv,$tl,$ti,$tc]):
    $qp = http_build_query(array_merge($_GET, ['tab'=>$tv])); ?>
<a href="?<?php echo $qp; ?>" class="<?php echo $fTab===$tv?'active':''; ?>">
  <i class="bi <?php echo $ti; ?>"></i> <?php echo $tl; ?>
  <span class="tb"><?php echo $tc; ?></span>
</a>
<?php endforeach; ?>
</div>

<!-- TOOLBAR -->
<form method="GET" action="">
<input type="hidden" name="tab" value="<?php echo htmlspecialchars($fTab); ?>">
<div class="toolbar">
  <div class="search-wrap">
    <i class="bi bi-search"></i>
    <input type="text" name="q" class="fi-search" placeholder="Search name, SKU, category…"
           value="<?php echo htmlspecialchars($fQ); ?>" onchange="this.form.submit()">
  </div>

  <select name="cat" class="fi-sm" onchange="this.form.submit()">
    <option value="">All Categories</option>
    <?php foreach ($categories as $c): ?>
    <option value="<?php echo $c['id']; ?>" <?php echo $fCat==(string)$c['id']?'selected':''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
    <?php endforeach; ?>
  </select>

  <select name="wh" class="fi-sm" onchange="this.form.submit()">
    <option value="">All Warehouses</option>
    <?php foreach ($warehouses as $w): ?>
    <option value="<?php echo $w['id']; ?>" <?php echo $fWh==(string)$w['id']?'selected':''; ?>><?php echo htmlspecialchars($w['name']); ?></option>
    <?php endforeach; ?>
  </select>

  <?php if ($fQ || $fCat || $fWh): ?>
  <a href="?tab=<?php echo urlencode($fTab); ?>" class="clear-btn"><i class="bi bi-x"></i> Clear</a>
  <?php endif; ?>
</div>
</form>

<!-- TABLE -->
<div class="tcard">
  <div class="tcard-head">
    <span class="ttl"><i class="bi bi-table" style="margin-right:.4rem"></i>Inventory Items</span>
    <span class="cnt"><?php echo count($items); ?> result<?php echo count($items)!=1?'s':''; ?></span>
  </div>
  <?php if (empty($items)): ?>
  <div class="empty-state">
    <i class="bi bi-boxes"></i>
    <p>No inventory items found<?php echo ($fQ||$fCat||$fWh) ? ' matching your filters.' : '.'; ?></p>
  </div>
  <?php else: ?>
  <div style="overflow-x:auto">
  <table class="inv-tbl">
    <thead>
      <tr>
        <th>SKU</th>
        <th>Item</th>
        <th>Category</th>
        <th>Warehouse</th>
        <th>Quantity</th>
        <th>Unit Cost</th>
        <th>Status</th>
        <th style="text-align:right">Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($items as $item):
        $qty     = (int)$item['current_quantity'];
        $reorder = (int)$item['reorder_level'];
        $reorderQty = (int)$item['reorder_quantity'];
        $pct     = $reorder > 0 ? min(100, round($qty / ($reorder * 2) * 100)) : ($qty > 0 ? 100 : 0);
        $barClr  = $qty === 0 ? '#f85149' : ($qty <= $reorder ? '#f39c12' : '#3fb950');
        $catClr  = catColor($item['cat_name']);
        $catBg   = 'rgba('.implode(',', array_map('hexdec', str_split(ltrim($catClr,'#'),2))).',.13)';
        $cost    = $item['unit_cost'] !== null ? '$'.number_format((float)$item['unit_cost'], 2) : '—';
    ?>
    <tr>
      <td><span class="sku"><?php echo htmlspecialchars($item['sku']); ?></span></td>
      <td>
        <div class="item-cell">
          <div class="iname"><?php echo htmlspecialchars($item['name']); ?></div>
          <?php if (!empty($item['description'])): ?>
          <div class="idesc"><?php echo htmlspecialchars($item['description']); ?></div>
          <?php endif; ?>
        </div>
      </td>
      <td>
        <span class="cat-chip" style="background:<?php echo $catBg; ?>;color:<?php echo $catClr; ?>">
          <?php echo htmlspecialchars($item['cat_name']); ?>
        </span>
      </td>
      <td><div class="wh-cell"><i class="bi bi-building"></i><?php echo htmlspecialchars($item['wh_name']); ?></div></td>
      <td>
        <div class="qty-wrap">
          <span class="qty-num" style="color:<?php echo $barClr; ?>"><?php echo number_format($qty); ?></span>
          <span style="font-size:.72rem;color:#8b949e"> <?php echo htmlspecialchars($item['unit_of_measure'] ?: 'units'); ?></span>
          <div class="qty-bar-bg"><div class="qty-bar-fill" style="width:<?php echo $pct; ?>%;background:<?php echo $barClr; ?>"></div></div>
          <div class="qty-sub">Reorder at <?php echo $reorder; ?></div>
        </div>
      </td>
      <td><span class="cost-cell"><?php echo $cost; ?></span></td>
      <td><?php echo stockChip($item['status']); ?></td>
      <td style="text-align:right">
        <div style="display:flex;align-items:center;gap:.35rem;justify-content:flex-end">
          <button class="act-btn" title="View Details"
            onclick='openModal(<?php echo json_encode([
              "id"          => $item["id"],
              "name"        => $item["name"],
              "sku"         => $item["sku"],
              "description" => $item["description"] ?? "",
              "category"    => $item["cat_name"],
              "warehouse"   => $item["wh_name"],
              "qty"         => $qty,
              "reorder"     => $reorder,
              "reorder_qty" => $reorderQty,
              "uom"         => $item["unit_of_measure"] ?: "units",
              "unit_cost"   => $item["unit_cost"] !== null ? number_format((float)$item["unit_cost"],2) : null,
              "status"      => $item["status"],
              "last_reorder"=> $item["last_reorder_date"] ?? "",
            ]); ?>)'>
            <i class="bi bi-eye"></i>
          </button>
          <?php if (hasPermission('edit','inventory')): ?>
          <a href="<?php echo APP_URL; ?>/modules/inventory/edit-item.php?id=<?php echo $item['id']; ?>"
             class="act-btn" title="Edit Item"><i class="bi bi-pencil"></i></a>
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

<!-- VIEW MODAL -->
<div class="modal-ov" id="mdOv" onclick="if(event.target===this)closeModal()">
  <div class="modal-box">
    <div class="modal-hdr">
      <h2><i class="bi bi-box-seam" style="color:#388bfd"></i> Item Details</h2>
      <button class="modal-close" onclick="closeModal()"><i class="bi bi-x"></i></button>
    </div>
    <div class="modal-body">

      <!-- Identity -->
      <div class="ms-title">Item Information</div>
      <div style="margin-bottom:.6rem">
        <div style="font-size:1rem;font-weight:700;color:#e6edf3" id="md-name"></div>
        <div style="margin-top:.3rem" id="md-sku-wrap">
          <span style="display:inline-block;background:#0d1117;border:1px solid #30363d;color:#8b949e;border-radius:6px;padding:2px 8px;font-size:.72rem;font-family:monospace" id="md-sku"></span>
        </div>
        <div style="font-size:.82rem;color:#8b949e;margin-top:.5rem;line-height:1.5" id="md-desc"></div>
      </div>
      <div class="m-grid" style="margin-bottom:1.1rem">
        <div class="mf"><label>Category</label><span id="md-cat"></span></div>
        <div class="mf"><label>Warehouse</label><span id="md-wh"></span></div>
        <div class="mf"><label>Unit of Measure</label><span id="md-uom"></span></div>
        <div class="mf"><label>Last Reorder</label><span id="md-last-reorder"></span></div>
      </div>

      <!-- Stock meter -->
      <div class="ms-title">Stock Level</div>
      <div class="stock-meter">
        <div style="display:flex;justify-content:space-between;align-items:center">
          <span style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#8b949e">Current Stock</span>
          <span id="md-status-chip"></span>
        </div>
        <div class="sm-bar-bg"><div class="sm-bar-fill" id="md-bar"></div></div>
        <div class="sm-nums">
          <span id="md-qty-label"></span>
          <span id="md-reorder-label"></span>
        </div>
      </div>
      <div class="m-grid3">
        <div class="mf"><label>Current Qty</label><span id="md-qty" style="font-size:1.1rem;font-weight:800"></span></div>
        <div class="mf"><label>Reorder At</label><span id="md-reorder"></span></div>
        <div class="mf"><label>Reorder Qty</label><span id="md-reorder-qty"></span></div>
      </div>

      <!-- Cost -->
      <div class="ms-title">Pricing</div>
      <div class="m-grid" style="margin-bottom:1.1rem">
        <div class="mf"><label>Unit Cost</label><span id="md-cost" style="font-size:1.1rem;font-weight:700"></span></div>
        <div class="mf"><label>Stock Value</label><span id="md-total-val" style="font-size:1.1rem;font-weight:700;color:#bb8fce"></span></div>
      </div>

      <!-- Footer -->
      <div style="display:flex;gap:.6rem;padding-top:1rem;border-top:1px solid #21262d;flex-wrap:wrap">
        <?php if (hasPermission('edit','inventory')): ?>
        <a id="md-edit-link" href="#" class="btn-primary-sm" style="font-size:.82rem;padding:.45rem .9rem">
          <i class="bi bi-pencil"></i> Edit
        </a>
        <?php endif; ?>
        <button onclick="closeModal()" style="background:transparent;border:1px solid #30363d;color:#8b949e;padding:.45rem .9rem;border-radius:9px;font-size:.82rem;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem">
          Close
        </button>
      </div>
    </div>
  </div>
</div>

<script>
var APP_URL = '<?php echo APP_URL; ?>';
var statusMap = {
  IN_STOCK:    {bg:'rgba(63,185,80,.15)',   clr:'#3fb950', ico:'bi-check-circle-fill',    lbl:'In Stock'},
  LOW_STOCK:   {bg:'rgba(243,156,18,.15)',  clr:'#f39c12', ico:'bi-exclamation-circle-fill',lbl:'Low Stock'},
  OUT_OF_STOCK:{bg:'rgba(248,81,73,.15)',   clr:'#f85149', ico:'bi-x-circle-fill',         lbl:'Out of Stock'},
  OBSOLETE:    {bg:'rgba(139,148,158,.15)', clr:'#8b949e', ico:'bi-archive-fill',          lbl:'Obsolete'},
};

function openModal(r) {
  document.getElementById('md-name').textContent  = r.name;
  document.getElementById('md-sku').textContent   = r.sku;
  document.getElementById('md-desc').textContent  = r.description || '';
  document.getElementById('md-cat').textContent   = r.category;
  document.getElementById('md-wh').textContent    = r.warehouse;
  document.getElementById('md-uom').textContent   = r.uom;
  document.getElementById('md-last-reorder').textContent = r.last_reorder || '—';

  // Status chip
  var sc = statusMap[r.status] || statusMap.IN_STOCK;
  document.getElementById('md-status-chip').innerHTML =
    '<span style="display:inline-flex;align-items:center;gap:4px;padding:2px 10px;border-radius:20px;font-size:.75rem;font-weight:700;background:'+sc.bg+';color:'+sc.clr+'"><i class="bi '+sc.ico+'"></i>'+sc.lbl+'</span>';

  // Stock bar
  var max   = Math.max(r.reorder * 2, r.qty, 1);
  var pct   = Math.min(100, Math.round(r.qty / max * 100));
  var bclr  = r.qty === 0 ? '#f85149' : (r.qty <= r.reorder ? '#f39c12' : '#3fb950');
  document.getElementById('md-bar').style.width      = pct + '%';
  document.getElementById('md-bar').style.background = bclr;
  document.getElementById('md-qty').textContent      = r.qty.toLocaleString();
  document.getElementById('md-qty').style.color      = bclr;
  document.getElementById('md-reorder').textContent  = r.reorder;
  document.getElementById('md-reorder-qty').textContent = r.reorder_qty;
  document.getElementById('md-qty-label').textContent    = r.qty + ' ' + r.uom;
  document.getElementById('md-reorder-label').textContent = 'Reorder point: ' + r.reorder;

  // Cost
  var uc  = r.unit_cost ? '$' + r.unit_cost : '—';
  var tv  = r.unit_cost ? '$' + (parseFloat(r.unit_cost.replace(/,/g,'')) * r.qty).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}) : '—';
  document.getElementById('md-cost').textContent      = uc;
  document.getElementById('md-total-val').textContent = tv;

  // Edit link
  var el = document.getElementById('md-edit-link');
  if (el) el.href = APP_URL + '/modules/inventory/edit-item.php?id=' + r.id;

  document.getElementById('mdOv').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeModal() {
  document.getElementById('mdOv').classList.remove('open');
  document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
</script>

<?php require_once '../../includes/footer.php'; ?>
