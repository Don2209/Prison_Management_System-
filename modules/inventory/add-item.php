<?php
$pageTitle = 'Add Inventory Item';
require_once '../../includes/header.php';
requireAuth();
verifyPermission('create', 'inventory');

$fid  = getCurrentFacility();
$isSA = isSuperAdmin();

/* ══════════ DROPDOWN DATA ══════════ */
$categories = fetchAll("SELECT id, name, description FROM inventory_categories WHERE deleted_at IS NULL ORDER BY name", [], '');
$warehouses = $isSA
    ? fetchAll("SELECT w.id, w.name, f.name facility_name FROM warehouses w JOIN facilities f ON w.facility_id=f.id WHERE w.deleted_at IS NULL AND w.status='ACTIVE' ORDER BY w.name", [], '')
    : fetchAll("SELECT id, name FROM warehouses WHERE deleted_at IS NULL AND status='ACTIVE' AND facility_id=? ORDER BY name", [$fid], 'i');
$suppliers  = fetchAll("SELECT id, name FROM suppliers WHERE status='ACTIVE' AND deleted_at IS NULL ORDER BY name", [], '');
$facilities = $isSA ? fetchAll("SELECT id, name FROM facilities WHERE deleted_at IS NULL ORDER BY name", [], '') : [];

/* ══════════ POST HANDLER ══════════ */
$errors  = [];
$success = false;
$old     = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST;

    $itemFid     = $isSA ? (int)($_POST['facility_id']    ?? 0) : $fid;
    $catId       = (int)($_POST['category_id']    ?? 0);
    $whId        = (int)($_POST['warehouse_id']   ?? 0);
    $name        = trim($_POST['name']            ?? '');
    $desc        = trim($_POST['description']     ?? '');
    $sku         = trim($_POST['sku']             ?? '');
    $uom         = trim($_POST['unit_of_measure'] ?? '');
    $qty         = trim($_POST['current_quantity']?? '0');
    $reorderLvl  = trim($_POST['reorder_level']   ?? '10');
    $reorderQty  = trim($_POST['reorder_quantity']?? '100');
    $unitCost    = trim($_POST['unit_cost']        ?? '');
    $supplierId  = (int)($_POST['supplier_id']    ?? 0);
    $lastReorder = trim($_POST['last_reorder_date']?? '');
    $status      = trim($_POST['status']          ?? 'IN_STOCK');

    /* Validation */
    if (!$itemFid)  $errors[] = 'Facility is required.';
    if (!$catId)    $errors[] = 'Category is required.';
    if (!$whId)     $errors[] = 'Warehouse is required.';
    if (!$name)     $errors[] = 'Item name is required.';
    if (!$sku)      $errors[] = 'SKU is required.';
    if (!ctype_digit($qty) || (int)$qty < 0)       $errors[] = 'Quantity must be a non-negative number.';
    if (!ctype_digit($reorderLvl) || (int)$reorderLvl < 0) $errors[] = 'Reorder level must be a non-negative number.';
    if (!ctype_digit($reorderQty) || (int)$reorderQty < 0) $errors[] = 'Reorder quantity must be a non-negative number.';
    if ($unitCost !== '' && (!is_numeric($unitCost) || (float)$unitCost < 0)) $errors[] = 'Unit cost must be a positive number.';
    if (!in_array($status, ['IN_STOCK','LOW_STOCK','OUT_OF_STOCK','OBSOLETE'])) $status = 'IN_STOCK';

    /* SKU uniqueness */
    if (!$errors && $sku) {
        $dup = fetchOne("SELECT id FROM inventory_items WHERE sku=? AND deleted_at IS NULL", [$sku], 's');
        if ($dup) $errors[] = "SKU '$sku' is already in use. Please use a unique SKU.";
    }

    if (!$errors) {
        /* Auto-status based on qty vs reorder level */
        if ((int)$qty === 0) $status = 'OUT_OF_STOCK';
        elseif ((int)$qty <= (int)$reorderLvl) $status = 'LOW_STOCK';
        elseif ($status === 'OUT_OF_STOCK' || $status === 'LOW_STOCK') $status = 'IN_STOCK';

        executeQuery(
            "INSERT INTO inventory_items
             (facility_id, category_id, warehouse_id, name, description, sku, unit_of_measure,
              current_quantity, reorder_level, reorder_quantity, unit_cost, supplier_id,
              last_reorder_date, status, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())",
            [$itemFid, $catId, $whId, $name, $desc ?: null, $sku, $uom ?: null,
             (int)$qty, (int)$reorderLvl, (int)$reorderQty,
             $unitCost !== '' ? (float)$unitCost : null,
             $supplierId ?: null,
             $lastReorder ?: null, $status],
            'iiissssiiidiss'
        );
        $success = true;
        $old = []; // clear for fresh form
    }
}

/* Category map for JS preview */
$catMap = [];
foreach ($categories as $c) $catMap[$c['id']] = $c['name'];

$statusCfg = [
    'IN_STOCK'     => ['clr'=>'#3fb950','bg'=>'rgba(63,185,80,.15)'],
    'LOW_STOCK'    => ['clr'=>'#f39c12','bg'=>'rgba(243,156,18,.15)'],
    'OUT_OF_STOCK' => ['clr'=>'#f85149','bg'=>'rgba(248,81,73,.15)'],
    'OBSOLETE'     => ['clr'=>'#8b949e','bg'=>'rgba(139,148,158,.15)'],
];
?>
<style>
:root{--sur:#161b22;--bdr:#21262d;--bg:#0d1117;--txt:#e6edf3;--mut:#8b949e;--acc:#1f6feb}
.ph{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:.75rem}
.ph h1{font-size:1.35rem;font-weight:700;color:var(--txt);margin:0}
.bc{font-size:.78rem;color:#6e7681;display:flex;align-items:center;gap:.3rem;margin-bottom:.3rem}
.bc a{color:#388bfd;text-decoration:none}.bc a:hover{text-decoration:underline}

/* Layout */
.layout{display:grid;grid-template-columns:1fr 340px;gap:1.5rem;align-items:start}
@media(max-width:960px){.layout{grid-template-columns:1fr}}

/* Card */
.card{background:var(--sur);border:1px solid var(--bdr);border-radius:16px;overflow:hidden}
.card-hdr{padding:.9rem 1.3rem;border-bottom:1px solid var(--bdr);display:flex;align-items:center;gap:.55rem}
.card-hdr h2{margin:0;font-size:.9rem;font-weight:700;color:var(--txt)}
.card-hdr i{color:#388bfd;font-size:1rem}
.card-body{padding:1.3rem}

/* Section label */
.section-lbl{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#8b949e;margin:1.3rem 0 .75rem;padding-bottom:.35rem;border-bottom:1px solid #21262d;display:flex;align-items:center;gap:.4rem}
.section-lbl:first-child{margin-top:0}

/* Form fields */
.fg{display:flex;flex-direction:column;gap:.35rem;margin-bottom:1rem}
.fg:last-child{margin-bottom:0}
.fg-row2{display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem}
.fg-row3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;margin-bottom:1rem}
@media(max-width:600px){.fg-row2,.fg-row3{grid-template-columns:1fr}}
.fg label,.fg-row2 .fg label,.fg-row3 .fg label{font-size:.8rem;font-weight:600;color:var(--mut)}
.req{color:#f85149}
.fi{background:#0d1117;border:1px solid #30363d;border-radius:9px;color:var(--txt);padding:.55rem .85rem;font-size:.875rem;width:100%;outline:none;transition:border .2s,box-shadow .2s;font-family:inherit;box-sizing:border-box}
.fi::placeholder{color:#484f58}
.fi:focus{border-color:#388bfd;box-shadow:0 0 0 3px rgba(56,139,253,.15)}
.fi.is-error{border-color:#f85149;box-shadow:0 0 0 3px rgba(248,81,73,.12)}
textarea.fi{resize:vertical;min-height:80px}
select.fi{cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='%238b949e' d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right .75rem center;background-size:11px;padding-right:2rem}
.fi-icon-wrap{position:relative}
.fi-icon-wrap .fi-ico{position:absolute;left:.8rem;top:50%;transform:translateY(-50%);color:#8b949e;font-size:.82rem;pointer-events:none}
.fi-icon-wrap .fi{padding-left:2.2rem}
.hint{font-size:.73rem;color:#8b949e;margin-top:.2rem}
.char-count{font-size:.7rem;color:#8b949e;text-align:right;margin-top:.15rem}

/* Error / success */
.alert{border-radius:11px;padding:.85rem 1.1rem;margin-bottom:1.25rem;font-size:.875rem}
.alert.error{background:rgba(248,81,73,.08);border:1px solid rgba(248,81,73,.3);color:#f85149}
.alert.success{background:rgba(63,185,80,.12);border:1px solid rgba(63,185,80,.3);color:#3fb950}
.alert ul{margin:.4rem 0 0 1.2rem;padding:0}
.alert li{margin:.2rem 0}
.alert-hdr{display:flex;align-items:center;gap:.5rem;font-weight:700;margin-bottom:.25rem}

/* Category tiles */
.cat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:.55rem;margin-bottom:1rem}
@media(max-width:640px){.cat-grid{grid-template-columns:repeat(2,1fr)}}
.cat-tile{background:#0d1117;border:2px solid #30363d;border-radius:11px;padding:.6rem .5rem;cursor:pointer;text-align:center;transition:all .18s}
.cat-tile:hover{border-color:#388bfd;background:rgba(56,139,253,.06)}
.cat-tile.selected{border-color:#388bfd;background:rgba(56,139,253,.12)}
.cat-tile i{font-size:1.15rem;display:block;margin-bottom:.3rem;color:#8b949e;transition:color .18s}
.cat-tile.selected i{color:#388bfd}
.cat-tile span{font-size:.68rem;font-weight:600;color:#8b949e;line-height:1.2;display:block;transition:color .18s}
.cat-tile.selected span{color:#e6edf3}

/* Buttons */
.btn-primary{background:linear-gradient(135deg,#1f6feb,#388bfd);color:#fff;padding:.65rem 1.4rem;border-radius:10px;font-size:.9rem;font-weight:600;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:.45rem;transition:opacity .18s;white-space:nowrap}
.btn-primary:hover{opacity:.87}
.btn-ghost{background:transparent;border:1px solid #30363d;color:#8b949e;padding:.65rem 1.2rem;border-radius:10px;font-size:.9rem;font-weight:500;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;text-decoration:none;transition:all .18s;white-space:nowrap}
.btn-ghost:hover{border-color:#58a6ff;color:#58a6ff}
.form-actions{display:flex;align-items:center;gap:.75rem;margin-top:1.5rem;padding-top:1.25rem;border-top:1px solid var(--bdr);flex-wrap:wrap}

/* Preview card */
.preview-card{background:var(--sur);border:1px solid var(--bdr);border-radius:16px;overflow:hidden;position:sticky;top:80px}
.preview-hdr{padding:.9rem 1.2rem;border-bottom:1px solid var(--bdr);font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#8b949e;display:flex;align-items:center;gap:.4rem}
.preview-body{padding:1.2rem}
.prev-sku{font-size:.72rem;font-weight:700;color:#388bfd;background:rgba(56,139,253,.12);border:1px solid rgba(56,139,253,.2);border-radius:6px;padding:2px 8px;display:inline-block;margin-bottom:.55rem;font-family:monospace;letter-spacing:.04em}
.prev-name{font-size:1rem;font-weight:700;color:#e6edf3;margin-bottom:.25rem;line-height:1.3;min-height:1.3em}
.prev-desc{font-size:.8rem;color:#8b949e;margin-bottom:.9rem;min-height:1em;line-height:1.5}
.prev-cat{display:inline-flex;align-items:center;gap:.3rem;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700;background:rgba(56,139,253,.12);color:#388bfd;margin-bottom:.75rem}
.prev-row{display:flex;justify-content:space-between;align-items:center;padding:.45rem 0;border-bottom:1px solid #1c2128;font-size:.82rem}
.prev-row:last-child{border-bottom:none}
.prev-row-lbl{color:#8b949e}
.prev-row-val{font-weight:600;color:#e6edf3;text-align:right}
.prev-status{display:inline-flex;align-items:center;gap:.3rem;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700}
.prev-qty-bar{margin-top:.75rem}
.prev-qty-lbl{display:flex;justify-content:space-between;font-size:.73rem;color:#8b949e;margin-bottom:.3rem}
.prev-bar-bg{height:7px;background:#21262d;border-radius:4px;overflow:hidden}
.prev-bar-fill{height:100%;border-radius:4px;transition:width .35s,background .35s}
.empty-prev{text-align:center;padding:1.5rem .5rem;color:#484f58}
.empty-prev i{font-size:1.8rem;display:block;margin-bottom:.5rem;opacity:.35}
.empty-prev p{font-size:.78rem;margin:0}
</style>

<!-- PAGE HEADER -->
<div class="ph">
  <div>
    <div class="bc">
      <a href="<?php echo APP_URL; ?>/dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
      <i class="bi bi-chevron-right" style="font-size:.6rem"></i>
      <a href="<?php echo APP_URL; ?>/modules/inventory/index.php">Inventory</a>
      <i class="bi bi-chevron-right" style="font-size:.6rem"></i>
      <span>Add Item</span>
    </div>
    <h1><i class="bi bi-box-seam-fill" style="color:#388bfd;margin-right:.45rem"></i>Add Inventory Item</h1>
  </div>
  <a href="<?php echo APP_URL; ?>/modules/inventory/index.php" class="btn-ghost">
    <i class="bi bi-arrow-left"></i> Back to Inventory
  </a>
</div>

<?php if ($success): ?>
<div class="alert success">
  <div class="alert-hdr"><i class="bi bi-check-circle-fill"></i> Item added successfully!</div>
  <div style="font-size:.85rem;margin-top:.3rem">
    <a href="<?php echo APP_URL; ?>/modules/inventory/index.php" style="color:#3fb950">← Back to inventory list</a>
    &nbsp;·&nbsp;
    <a href="<?php echo APP_URL; ?>/modules/inventory/add-item.php" style="color:#3fb950">Add another item</a>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="alert error">
  <div class="alert-hdr"><i class="bi bi-exclamation-triangle-fill"></i> Please fix the following:</div>
  <ul><?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<form method="POST" id="itemForm" novalidate>
<div class="layout">

  <!-- LEFT: FORM -->
  <div>

    <!-- CARD 1: Basic Info -->
    <div class="card" style="margin-bottom:1.25rem">
      <div class="card-hdr">
        <i class="bi bi-info-circle-fill"></i>
        <h2>Basic Information</h2>
      </div>
      <div class="card-body">

        <?php if ($isSA): ?>
        <div class="section-lbl"><i class="bi bi-buildings"></i> Facility</div>
        <div class="fg">
          <label>Facility <span class="req">*</span></label>
          <select name="facility_id" id="f-facility" class="fi" required onchange="updateWarehouses(this.value)">
            <option value="">— Select facility —</option>
            <?php foreach ($facilities as $f): ?>
            <option value="<?php echo $f['id']; ?>" <?php echo ($old['facility_id']??'')==$f['id']?'selected':''; ?>>
              <?php echo htmlspecialchars($f['name']); ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>

        <div class="section-lbl"><i class="bi bi-tag-fill"></i> Category</div>
        <!-- Category tiles -->
        <input type="hidden" name="category_id" id="f-category" value="<?php echo (int)($old['category_id']??0); ?>">
        <?php
        $catIcons = [
            'Food & Beverage'   => 'bi-cup-hot-fill',
            'Toiletries'        => 'bi-droplet-fill',
            'Clothing'          => 'bi-person-fill',
            'Medical Supplies'  => 'bi-capsule',
            'Cleaning Supplies' => 'bi-bucket-fill',
            'Office Supplies'   => 'bi-briefcase-fill',
            'Maintenance'       => 'bi-tools',
            'Security Equipment'=> 'bi-shield-lock-fill',
        ];
        ?>
        <div class="cat-grid">
        <?php foreach ($categories as $c):
            $icon = $catIcons[$c['name']] ?? 'bi-box-seam';
            $selCat = (int)($old['category_id'] ?? 0);
        ?>
          <div class="cat-tile <?php echo $selCat===$c['id']?'selected':''; ?>"
               onclick="selectCat(<?php echo $c['id']; ?>, this)"
               title="<?php echo htmlspecialchars($c['description'] ?? ''); ?>">
            <i class="bi <?php echo $icon; ?>"></i>
            <span><?php echo htmlspecialchars($c['name']); ?></span>
          </div>
        <?php endforeach; ?>
        </div>

        <div class="section-lbl"><i class="bi bi-card-text"></i> Item Details</div>
        <div class="fg">
          <label>Item Name <span class="req">*</span></label>
          <input type="text" name="name" id="f-name" class="fi <?php echo in_array('Item name is required.', $errors)?'is-error':''; ?>"
                 placeholder="e.g. Rice (50lb bag)" maxlength="255"
                 value="<?php echo htmlspecialchars($old['name']??''); ?>"
                 oninput="updatePreview()" required autofocus>
        </div>
        <div class="fg">
          <label>Description</label>
          <textarea name="description" id="f-desc" class="fi" placeholder="Optional: brief description of the item…"
                    maxlength="500" oninput="updatePreview()"><?php echo htmlspecialchars($old['description']??''); ?></textarea>
          <div class="char-count"><span id="desc-count">0</span> / 500</div>
        </div>
        <div class="fg-row2">
          <div class="fg" style="margin-bottom:0">
            <label>SKU <span class="req">*</span></label>
            <input type="text" name="sku" id="f-sku" class="fi <?php echo array_filter($errors, fn($e)=>str_contains($e,'SKU'))?'is-error':''; ?>"
                   placeholder="e.g. FOOD-001" maxlength="100"
                   value="<?php echo htmlspecialchars($old['sku']??''); ?>"
                   oninput="updatePreview()" required>
            <div class="hint">Unique identifier for this item.</div>
          </div>
          <div class="fg" style="margin-bottom:0">
            <label>Unit of Measure</label>
            <input type="text" name="unit_of_measure" id="f-uom" class="fi"
                   placeholder="e.g. bags, liters, units" maxlength="50"
                   value="<?php echo htmlspecialchars($old['unit_of_measure']??''); ?>"
                   oninput="updatePreview()">
          </div>
        </div>
      </div>
    </div>

    <!-- CARD 2: Stock & Reorder -->
    <div class="card" style="margin-bottom:1.25rem">
      <div class="card-hdr">
        <i class="bi bi-bar-chart-fill"></i>
        <h2>Stock & Reorder Settings</h2>
      </div>
      <div class="card-body">
        <div class="fg-row3">
          <div class="fg" style="margin-bottom:0">
            <label>Current Quantity <span class="req">*</span></label>
            <input type="number" name="current_quantity" id="f-qty" class="fi"
                   min="0" placeholder="0"
                   value="<?php echo htmlspecialchars($old['current_quantity']??'0'); ?>"
                   oninput="updatePreview()" required>
          </div>
          <div class="fg" style="margin-bottom:0">
            <label>Reorder Level</label>
            <input type="number" name="reorder_level" id="f-rl" class="fi"
                   min="0" placeholder="10"
                   value="<?php echo htmlspecialchars($old['reorder_level']??'10'); ?>"
                   oninput="updatePreview()">
            <div class="hint">Alert threshold.</div>
          </div>
          <div class="fg" style="margin-bottom:0">
            <label>Reorder Quantity</label>
            <input type="number" name="reorder_quantity" id="f-rq" class="fi"
                   min="0" placeholder="100"
                   value="<?php echo htmlspecialchars($old['reorder_quantity']??'100'); ?>">
            <div class="hint">Typical order qty.</div>
          </div>
        </div>
        <div class="section-lbl" style="margin-top:1.1rem"><i class="bi bi-circle-fill" style="font-size:.5rem"></i> Initial Status</div>
        <div class="fg-row2">
          <div class="fg" style="margin-bottom:0">
            <label>Status <span class="req">*</span></label>
            <select name="status" id="f-status" class="fi" onchange="updatePreview()">
              <?php foreach (['IN_STOCK','LOW_STOCK','OUT_OF_STOCK','OBSOLETE'] as $s): ?>
              <option value="<?php echo $s; ?>" <?php echo ($old['status']??'IN_STOCK')===$s?'selected':''; ?>>
                <?php echo ucwords(strtolower(str_replace('_',' ',$s))); ?>
              </option>
              <?php endforeach; ?>
            </select>
            <div class="hint">Auto-adjusted based on qty vs reorder level.</div>
          </div>
          <div class="fg" style="margin-bottom:0">
            <label>Last Reorder Date</label>
            <input type="date" name="last_reorder_date" class="fi"
                   value="<?php echo htmlspecialchars($old['last_reorder_date']??''); ?>">
          </div>
        </div>
      </div>
    </div>

    <!-- CARD 3: Pricing & Supply -->
    <div class="card" style="margin-bottom:1.25rem">
      <div class="card-hdr">
        <i class="bi bi-currency-dollar"></i>
        <h2>Pricing & Supply</h2>
      </div>
      <div class="card-body">
        <div class="fg-row2">
          <div class="fg" style="margin-bottom:0">
            <label>Unit Cost ($)</label>
            <div class="fi-icon-wrap">
              <i class="bi bi-currency-dollar fi-ico"></i>
              <input type="number" name="unit_cost" id="f-cost" class="fi"
                     placeholder="0.00" step="0.01" min="0"
                     value="<?php echo htmlspecialchars($old['unit_cost']??''); ?>"
                     oninput="updatePreview()">
            </div>
          </div>
          <div class="fg" style="margin-bottom:0">
            <label>Supplier</label>
            <select name="supplier_id" class="fi">
              <option value="">— None / Select later —</option>
              <?php foreach ($suppliers as $s): ?>
              <option value="<?php echo $s['id']; ?>" <?php echo ($old['supplier_id']??'')==$s['id']?'selected':''; ?>>
                <?php echo htmlspecialchars($s['name']); ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
    </div>

    <!-- CARD 4: Warehouse -->
    <div class="card" style="margin-bottom:1.25rem">
      <div class="card-hdr">
        <i class="bi bi-building-fill"></i>
        <h2>Warehouse Assignment</h2>
      </div>
      <div class="card-body">
        <div class="fg">
          <label>Warehouse <span class="req">*</span></label>
          <select name="warehouse_id" id="f-warehouse" class="fi" required onchange="updatePreview()">
            <option value="">— Select warehouse —</option>
            <?php foreach ($warehouses as $w): ?>
            <option value="<?php echo $w['id']; ?>" <?php echo ($old['warehouse_id']??'')==$w['id']?'selected':''; ?>>
              <?php echo htmlspecialchars($w['name']); ?>
              <?php if ($isSA && !empty($w['facility_name'])): ?> (<?php echo htmlspecialchars($w['facility_name']); ?>)<?php endif; ?>
            </option>
            <?php endforeach; ?>
          </select>
          <div class="hint">Where this item will be physically stored.</div>
        </div>
      </div>
    </div>

    <!-- ACTIONS -->
    <div class="form-actions">
      <button type="submit" class="btn-primary">
        <i class="bi bi-plus-circle-fill"></i> Add Item to Inventory
      </button>
      <a href="<?php echo APP_URL; ?>/modules/inventory/index.php" class="btn-ghost">
        <i class="bi bi-x"></i> Cancel
      </a>
    </div>

  </div><!-- /left -->

  <!-- RIGHT: PREVIEW -->
  <div>
    <div class="preview-card">
      <div class="preview-hdr"><i class="bi bi-eye-fill"></i> Live Preview</div>
      <div class="preview-body" id="preview-body">
        <div class="empty-prev">
          <i class="bi bi-box-seam"></i>
          <p>Fill in the form to see a preview of your item.</p>
        </div>
      </div>
    </div>

    <!-- Tips card -->
    <div class="card" style="margin-top:1rem">
      <div class="card-hdr"><i class="bi bi-lightbulb-fill" style="color:#f39c12"></i><h2>Tips</h2></div>
      <div class="card-body" style="padding:1rem 1.2rem">
        <ul style="margin:0;padding:0 0 0 1.1rem;list-style:disc;font-size:.8rem;color:#8b949e;line-height:1.9">
          <li>SKU must be unique across all inventory.</li>
          <li>Status is auto-adjusted: if qty ≤ reorder level → <span style="color:#f39c12">Low Stock</span>; qty = 0 → <span style="color:#f85149">Out of Stock</span>.</li>
          <li>Leave <em>Unit Cost</em> blank if unknown — it can be updated later.</li>
          <li>Reorder level triggers alerts on the inventory dashboard.</li>
        </ul>
      </div>
    </div>
  </div>

</div><!-- /layout -->
</form>

<script>
const catMap     = <?php echo json_encode($catMap); ?>;
const statusCfg  = <?php echo json_encode($statusCfg); ?>;
<?php if ($isSA): ?>
const allWarehouses = <?php echo json_encode($warehouses); ?>;
<?php endif; ?>

/* Category tile selection */
function selectCat(id, el) {
  document.querySelectorAll('.cat-tile').forEach(t => t.classList.remove('selected'));
  el.classList.add('selected');
  document.getElementById('f-category').value = id;
  updatePreview();
}

/* Char counter */
const descTa = document.getElementById('f-desc');
const descCount = document.getElementById('desc-count');
if (descTa) {
  descTa.addEventListener('input', () => { descCount.textContent = descTa.value.length; });
  descCount.textContent = descTa.value.length;
}

/* Warehouse filter by facility (SA only) */
<?php if ($isSA): ?>
function updateWarehouses(facilityId) {
  const sel = document.getElementById('f-warehouse');
  const prev = sel.value;
  sel.innerHTML = '<option value="">— Select warehouse —</option>';
  allWarehouses.forEach(w => {
    if (!facilityId || w.facility_id == facilityId) {
      const o = document.createElement('option');
      o.value = w.id;
      o.textContent = w.name + (w.facility_name ? ' (' + w.facility_name + ')' : '');
      if (w.id == prev) o.selected = true;
      sel.appendChild(o);
    }
  });
  updatePreview();
}
<?php endif; ?>

/* Live preview */
function updatePreview() {
  const name  = document.getElementById('f-name').value.trim();
  const sku   = document.getElementById('f-sku').value.trim();
  const desc  = document.getElementById('f-desc').value.trim();
  const uom   = document.getElementById('f-uom').value.trim();
  const qty   = parseInt(document.getElementById('f-qty').value) || 0;
  const rl    = parseInt(document.getElementById('f-rl').value)  || 10;
  const cost  = parseFloat(document.getElementById('f-cost').value) || 0;
  const catId = parseInt(document.getElementById('f-category').value) || 0;
  const whSel = document.getElementById('f-warehouse');
  const whName= whSel.options[whSel.selectedIndex]?.text || '';

  const pBody = document.getElementById('preview-body');

  if (!name && !sku) {
    pBody.innerHTML = '<div class="empty-prev"><i class="bi bi-box-seam"></i><p>Fill in the form to see a preview.</p></div>';
    return;
  }

  // auto status
  let status = document.getElementById('f-status').value;
  if (qty === 0) status = 'OUT_OF_STOCK';
  else if (qty <= rl) status = 'LOW_STOCK';
  else if (status === 'OUT_OF_STOCK' || status === 'LOW_STOCK') status = 'IN_STOCK';
  const sc = statusCfg[status] || {clr:'#8b949e',bg:'rgba(139,148,158,.15)'};
  const statusLabel = status.replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase());
  const catName = catId && catMap[catId] ? catMap[catId] : null;

  // capacity bar (vs reorder level × 3 as rough max)
  const barMax = Math.max(rl * 3, qty, 1);
  const barPct = Math.min(100, Math.round(qty / barMax * 100));
  const barClr = qty === 0 ? '#f85149' : (qty <= rl ? '#f39c12' : '#3fb950');

  pBody.innerHTML = `
    ${sku ? `<div class="prev-sku">${sku}</div>` : ''}
    <div class="prev-name">${name || '<span style="color:#484f58">Item name…</span>'}</div>
    ${desc ? `<div class="prev-desc">${desc}</div>` : '<div class="prev-desc" style="color:#484f58">No description</div>'}
    ${catName ? `<div class="prev-cat"><i class="bi bi-tag-fill"></i>${catName}</div>` : ''}
    <div style="margin-bottom:.75rem">
      <span class="prev-status" style="background:${sc.bg};color:${sc.clr}">${statusLabel}</span>
    </div>
    <div class="prev-row"><span class="prev-row-lbl">Quantity</span><span class="prev-row-val">${qty.toLocaleString()}${uom?' '+uom:''}</span></div>
    <div class="prev-row"><span class="prev-row-lbl">Reorder At</span><span class="prev-row-val">${rl.toLocaleString()}${uom?' '+uom:''}</span></div>
    ${cost > 0 ? `<div class="prev-row"><span class="prev-row-lbl">Unit Cost</span><span class="prev-row-val">$${cost.toFixed(2)}</span></div>` : ''}
    ${cost > 0 ? `<div class="prev-row"><span class="prev-row-lbl">Total Value</span><span class="prev-row-val" style="color:#bb8fce">$${(cost*qty).toFixed(2)}</span></div>` : ''}
    ${whName && whName !== '— Select warehouse —' ? `<div class="prev-row"><span class="prev-row-lbl">Warehouse</span><span class="prev-row-val" style="font-size:.78rem">${whName}</span></div>` : ''}
    <div class="prev-qty-bar">
      <div class="prev-qty-lbl"><span>Stock level</span><span>${barPct}%</span></div>
      <div class="prev-bar-bg"><div class="prev-bar-fill" style="width:${barPct}%;background:${barClr}"></div></div>
    </div>
  `;
}

/* Init */
document.addEventListener('DOMContentLoaded', () => {
  updatePreview();
  // restore char count
  if (descTa) descCount.textContent = descTa.value.length;
});
</script>

<?php require_once '../../includes/footer.php'; ?>
