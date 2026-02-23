<?php
$pageTitle = 'Facilities Management';
require_once '../../includes/header.php';
requireAuth();

if (!isSuperAdmin()) {
    header('Location: ' . APP_URL . '/dashboard.php');
    exit;
}

/* ══════════════ FILTERS ══════════════ */
$fSearch = trim($_GET['q']      ?? '');
$fType   = trim($_GET['type']   ?? '');
$fStatus = trim($_GET['status'] ?? '');

/* ══════════════ WHERE ══════════════ */
$wParts  = ["f.deleted_at IS NULL"];
$wParams = []; $wTypes = '';
if ($fSearch) {
    $s = "%$fSearch%";
    $wParts[]  = "(f.name LIKE ? OR f.location LIKE ? OR f.contact_person LIKE ?)";
    $wParams[] = $s; $wParams[] = $s; $wParams[] = $s; $wTypes .= 'sss';
}
if ($fType)   { $wParts[] = "f.type = ?";   $wParams[] = $fType;   $wTypes .= 's'; }
if ($fStatus) { $wParts[] = "f.status = ?"; $wParams[] = $fStatus; $wTypes .= 's'; }
$where = implode(' AND ', $wParts);

/* ══════════════ MAIN QUERY with aggregates ══════════════ */
$facilities = fetchAll(
    "SELECT f.*,
            COALESCE(sc.cnt, 0)  AS staff_count,
            COALESCE(ic.cnt, 0)  AS inmate_count,
            COALESCE(inc.cnt, 0) AS incidents_month
     FROM facilities f
     LEFT JOIN (SELECT facility_id, COUNT(*) cnt FROM staff   WHERE deleted_at IS NULL GROUP BY facility_id) sc  ON sc.facility_id  = f.id
     LEFT JOIN (SELECT facility_id, COUNT(*) cnt FROM inmates WHERE deleted_at IS NULL GROUP BY facility_id) ic  ON ic.facility_id  = f.id
     LEFT JOIN (SELECT facility_id, COUNT(*) cnt FROM incidents WHERE MONTH(incident_date)=MONTH(NOW()) AND deleted_at IS NULL GROUP BY facility_id) inc ON inc.facility_id = f.id
     WHERE $where
     ORDER BY f.name",
    $wParams, $wTypes
);

/* ══════════════ KPI ══════════════ */
$kTotal    = (int)fetchOne("SELECT COUNT(*) c FROM facilities WHERE deleted_at IS NULL")['c'];
$kActive   = (int)fetchOne("SELECT COUNT(*) c FROM facilities WHERE deleted_at IS NULL AND status='ACTIVE'")['c'];
$kMaint    = (int)fetchOne("SELECT COUNT(*) c FROM facilities WHERE deleted_at IS NULL AND status='UNDER_MAINTENANCE'")['c'];
$kCapacity = (int)fetchOne("SELECT COALESCE(SUM(total_capacity),0) c FROM facilities WHERE deleted_at IS NULL")['c'];
$kPop      = (int)fetchOne("SELECT COALESCE(SUM(current_population),0) c FROM facilities WHERE deleted_at IS NULL")['c'];
$kStaff    = (int)fetchOne("SELECT COUNT(*) c FROM staff WHERE deleted_at IS NULL")['c'];
?>
<!-- ════════════════════════════════════ STYLES ════════════════════════════════════ -->
<style>
:root{--sur:#161b22;--bdr:#21262d;--bg:#0d1117;--txt:#e6edf3;--mut:#8b949e;--acc:#1f6feb;--acc2:#388bfd}

/* Page header */
.ph{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:.75rem}
.ph h1{font-size:1.35rem;font-weight:700;color:var(--txt);margin:0}
.bc{font-size:.78rem;color:#6e7681;display:flex;align-items:center;gap:.3rem;margin-bottom:.3rem}
.bc a{color:#388bfd;text-decoration:none}.bc a:hover{text-decoration:underline}

/* KPI */
.kpi-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(155px,1fr));gap:14px;margin-bottom:1.5rem}
.kpi{background:var(--sur);border:1px solid var(--bdr);border-radius:14px;padding:18px 16px 14px;position:relative;overflow:hidden;transition:transform .2s,box-shadow .2s,border-color .2s;cursor:default}
.kpi::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--kpi-bar,#388bfd);border-radius:14px 14px 0 0}
.kpi:hover{transform:translateY(-3px);box-shadow:0 10px 28px rgba(0,0,0,.4);border-color:var(--kpi-clr,#388bfd)}
.kpi-ico{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;margin-bottom:10px}
.kpi-val{font-size:1.75rem;font-weight:800;color:var(--txt);line-height:1}
.kpi-lbl{font-size:.72rem;color:var(--mut);text-transform:uppercase;letter-spacing:.07em;margin-top:3px}

/* Toolbar */
.toolbar{background:var(--sur);border:1px solid var(--bdr);border-radius:12px;padding:12px 16px;margin-bottom:1.25rem;display:flex;flex-wrap:wrap;gap:.6rem;align-items:center}
.toolbar .fi{background:#0d1117;border:1px solid #30363d;border-radius:8px;color:var(--txt);padding:.4rem .75rem;font-size:.82rem;outline:none;transition:border-color .2s}
.toolbar .fi:focus{border-color:#388bfd}
.search-wrap{position:relative;flex:1;min-width:200px}
.search-wrap i{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--mut);font-size:.8rem;pointer-events:none}
.search-wrap input{padding-left:30px;width:100%}
.btn-act{display:inline-flex;align-items:center;gap:.3rem;padding:.38rem .85rem;border-radius:8px;font-size:.8rem;font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:all .18s;white-space:nowrap}
.btn-filter{background:rgba(56,139,253,.15);color:#58a6ff}.btn-filter:hover{background:rgba(56,139,253,.28)}
.btn-ghost {background:#21262d;color:#8b949e}.btn-ghost:hover{background:#30363d;color:#e6edf3}
.btn-primary-sm{background:linear-gradient(135deg,#1f6feb,#388bfd);color:#fff;padding:.45rem 1rem;border-radius:8px;font-size:.85rem;font-weight:600;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;transition:opacity .18s;text-decoration:none}
.btn-primary-sm:hover{opacity:.88}
.view-toggle{display:flex;gap:2px;background:#0d1117;border:1px solid #30363d;border-radius:8px;padding:3px}
.vt-btn{background:transparent;border:none;color:#8b949e;padding:.3rem .55rem;border-radius:6px;cursor:pointer;font-size:.85rem;transition:all .18s}
.vt-btn.active{background:#21262d;color:#e6edf3}

/* ── CARD GRID ── */
.fac-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:18px}
.fac-card{background:var(--sur);border:1px solid var(--bdr);border-radius:16px;overflow:hidden;transition:transform .22s,box-shadow .22s,border-color .22s;display:flex;flex-direction:column}
.fac-card:hover{transform:translateY(-4px);box-shadow:0 14px 36px rgba(0,0,0,.45);border-color:#30363d}

/* Card top ribbon */
.fc-ribbon{height:5px;width:100%}

/* Card header */
.fc-head{padding:1.1rem 1.25rem .75rem;display:flex;align-items:flex-start;justify-content:space-between;gap:.5rem}
.fc-ico{width:44px;height:44px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:1.15rem;flex-shrink:0}
.fc-name{font-size:.95rem;font-weight:700;color:var(--txt);line-height:1.25;margin-bottom:3px}
.fc-loc{font-size:.75rem;color:var(--mut);display:flex;align-items:center;gap:.3rem}

/* Chips */
.chip{display:inline-block;padding:3px 10px;border-radius:20px;font-size:.7rem;font-weight:700;letter-spacing:.03em;white-space:nowrap}
.chip-active     {background:rgba(63,185,80,.18);   color:#3fb950}
.chip-inactive   {background:rgba(139,148,158,.18); color:#8b949e}
.chip-maintenance{background:rgba(243,156,18,.18);  color:#f39c12}
.chip-prison  {background:rgba(248,81,73,.12);  color:#f85149}
.chip-admin   {background:rgba(56,139,253,.12); color:#58a6ff}
.chip-holding {background:rgba(155,89,182,.12); color:#bb8fce}

/* Occupancy bar */
.occ-wrap{padding:0 1.25rem .75rem}
.occ-label{display:flex;justify-content:space-between;font-size:.72rem;color:var(--mut);margin-bottom:.35rem}
.occ-val{font-size:.78rem;font-weight:700}
.occ-bar{height:6px;background:#21262d;border-radius:4px;overflow:hidden}
.occ-fill{height:100%;border-radius:4px;transition:width .6s cubic-bezier(.4,0,.2,1)}

/* Stats row */
.fc-stats{display:grid;grid-template-columns:1fr 1fr 1fr;gap:0;border-top:1px solid var(--bdr);border-bottom:1px solid var(--bdr)}
.fc-stat{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:.65rem .4rem;gap:.1rem;position:relative}
.fc-stat+.fc-stat::before{content:'';position:absolute;left:0;top:20%;height:60%;width:1px;background:var(--bdr)}
.fc-stat-val{font-size:1.05rem;font-weight:800;color:var(--txt);line-height:1}
.fc-stat-lbl{font-size:.66rem;color:var(--mut);text-transform:uppercase;letter-spacing:.05em;text-align:center}

/* Contact */
.fc-contact{padding:.75rem 1.25rem;font-size:.78rem;color:var(--mut);display:flex;flex-direction:column;gap:.25rem;flex:1}
.fc-contact-row{display:flex;align-items:center;gap:.4rem}
.fc-contact-row i{font-size:.8rem;color:#484f58;width:12px;flex-shrink:0}
.fc-contact-row span{color:#8b949e;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

/* Card actions */
.fc-actions{padding:.75rem 1.25rem;border-top:1px solid var(--bdr);display:flex;gap:.5rem;justify-content:flex-end}

/* ── LIST VIEW ── */
.fac-list{background:var(--sur);border:1px solid var(--bdr);border-radius:14px;overflow:hidden}
.fl-head{display:grid;grid-template-columns:2fr 1fr 1fr minmax(120px,1fr) 100px 1fr 120px;gap:0;background:#0d1117;padding:0}
.fl-th{font-size:.72rem;color:var(--mut);text-transform:uppercase;letter-spacing:.07em;font-weight:600;padding:10px 14px;border-bottom:1px solid var(--bdr)}
.fl-row{display:grid;grid-template-columns:2fr 1fr 1fr minmax(120px,1fr) 100px 1fr 120px;align-items:center;border-bottom:1px solid var(--bdr);transition:background .15s;cursor:pointer}
.fl-row:last-child{border-bottom:none}
.fl-row:hover{background:#1c2128}
.fl-td{padding:11px 14px;font-size:.83rem;color:var(--txt)}
.fl-td.muted{color:var(--mut);font-size:.78rem}

/* Modal */
.mbk{position:fixed;inset:0;background:rgba(0,0,0,.72);backdrop-filter:blur(4px);z-index:1050;display:none;align-items:center;justify-content:center;padding:1rem}
.mbk.open{display:flex}
.mbox{background:#161b22;border:1px solid #30363d;border-radius:16px;width:100%;max-width:620px;max-height:92vh;overflow-y:auto;animation:mIn .22s ease}
@keyframes mIn{from{opacity:0;transform:scale(.96)}to{opacity:1;transform:scale(1)}}
.mhead{display:flex;align-items:center;justify-content:space-between;padding:1rem 1.5rem;border-bottom:1px solid #21262d;position:sticky;top:0;background:#161b22;z-index:2}
.mhead h3{margin:0;font-size:1.05rem;font-weight:700;color:#e6edf3;display:flex;align-items:center;gap:.5rem}
.mbody{padding:1.5rem}
.mfoot{display:flex;justify-content:flex-end;gap:.6rem;padding:1rem 1.5rem;border-top:1px solid #21262d;position:sticky;bottom:0;background:#161b22}
.btn-x{background:none;border:none;color:#8b949e;font-size:1rem;cursor:pointer;padding:.2rem;line-height:1;transition:color .18s}
.btn-x:hover{color:#e6edf3}
.dr{display:flex;justify-content:space-between;align-items:flex-start;padding:.55rem 0;border-bottom:1px solid #21262d;font-size:.84rem;gap:.5rem}
.dr:last-child{border-bottom:none}
.dk{color:#8b949e;font-size:.76rem;min-width:150px;flex-shrink:0}
.dv{color:#e6edf3;text-align:right;word-break:break-word;max-width:330px}

/* Big meter in modal */
.occ-big{background:#0d1117;border:1px solid #21262d;border-radius:10px;padding:1rem;margin-bottom:1.25rem}
.occ-big-bar{height:10px;background:#21262d;border-radius:5px;overflow:hidden;margin:.5rem 0}
.occ-big-fill{height:100%;border-radius:5px;transition:width .7s}
.occ-nums{display:flex;justify-content:space-between;font-size:.78rem}

/* Stats in modal */
.m-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:1.25rem}
.m-stat{background:#0d1117;border:1px solid #21262d;border-radius:10px;padding:.8rem;text-align:center}
.m-stat-val{font-size:1.3rem;font-weight:800;color:var(--txt);line-height:1}
.m-stat-lbl{font-size:.68rem;color:var(--mut);text-transform:uppercase;letter-spacing:.06em;margin-top:3px}

/* empty */
.empty{text-align:center;padding:4rem 2rem;color:#8b949e}
.empty i{font-size:2.5rem;display:block;margin-bottom:.75rem;opacity:.35}

/* fi */
.fi{background:#0d1117;border:1px solid #30363d;border-radius:8px;color:#e6edf3;padding:.4rem .75rem;font-size:.82rem;outline:none;transition:border-color .2s;font-family:inherit}
.fi:focus{border-color:#388bfd}
select.fi{cursor:pointer;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='%238b949e' d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right .65rem center;background-size:11px;padding-right:2rem;appearance:none}
</style>

<?php if (!empty($_GET['created'])): ?>
<div style="background:rgba(63,185,80,.12);border:1px solid rgba(63,185,80,.3);border-radius:11px;padding:.8rem 1.1rem;margin-bottom:1.25rem;font-size:.875rem;color:#3fb950;display:flex;align-items:center;gap:.55rem">
  <i class="bi bi-check-circle-fill"></i>
  <span>Facility created successfully.</span>
</div>
<?php endif; ?>

<!-- ════════════════════════ PAGE HEADER ════════════════════════ -->
<div class="ph">
  <div>
    <div class="bc">
      <a href="<?php echo APP_URL; ?>/dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
      <i class="bi bi-chevron-right" style="font-size:.6rem"></i>
      <span>Facilities</span>
    </div>
    <h1>
      <i class="bi bi-building-fill" style="color:#388bfd;margin-right:.4rem"></i>
      Facilities Management
    </h1>
  </div>
  <a href="<?php echo APP_URL; ?>/modules/facilities/add.php" class="btn-primary-sm">
    <i class="bi bi-plus-lg"></i> Add Facility
  </a>
</div>

<!-- ════════════════════════ KPI ════════════════════════ -->
<div class="kpi-grid">
<?php
$kpis = [
  ['Total Facilities', $kTotal,    'bi-building',          '#388bfd','rgba(31,111,235,.15)','#388bfd'],
  ['Active',           $kActive,   'bi-check-circle-fill', '#3fb950','rgba(63,185,80,.15)' ,'#3fb950'],
  ['Maintenance',      $kMaint,    'bi-tools',             '#f39c12','rgba(243,156,18,.15)','#f39c12'],
  ['Total Capacity',   $kCapacity, 'bi-grid-3x3-gap-fill', '#58a6ff','rgba(56,139,253,.15)','#58a6ff'],
  ['Total Population', $kPop,      'bi-person-fill',       '#1abc9c','rgba(26,188,156,.15)','#1abc9c'],
  ['Total Staff',      $kStaff,    'bi-people-fill',       '#bb8fce','rgba(155,89,182,.15)','#bb8fce'],
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

<!-- ════════════════════════ TOOLBAR ════════════════════════ -->
<form method="GET" class="toolbar" id="filterForm">
  <div class="search-wrap">
    <i class="bi bi-search"></i>
    <input type="text" name="q" class="fi" placeholder="Search name, location, contact…"
           value="<?php echo htmlspecialchars($fSearch); ?>">
  </div>

  <select name="type" class="fi" onchange="this.form.submit()" style="min-width:160px">
    <option value="">All Types</option>
    <?php foreach (['PRISON'=>'Prison','ADMIN_OFFICE'=>'Admin Office','HOLDING_CENTER'=>'Holding Center'] as $tv => $tl): ?>
    <option value="<?php echo $tv; ?>" <?php echo $fType===$tv?'selected':''; ?>><?php echo $tl; ?></option>
    <?php endforeach; ?>
  </select>

  <select name="status" class="fi" onchange="this.form.submit()" style="min-width:160px">
    <option value="">All Statuses</option>
    <?php foreach (['ACTIVE'=>'Active','INACTIVE'=>'Inactive','UNDER_MAINTENANCE'=>'Under Maintenance'] as $sv => $sl): ?>
    <option value="<?php echo $sv; ?>" <?php echo $fStatus===$sv?'selected':''; ?>><?php echo $sl; ?></option>
    <?php endforeach; ?>
  </select>

  <button type="submit" class="btn-act btn-filter"><i class="bi bi-funnel-fill"></i> Filter</button>
  <?php if ($fSearch || $fType || $fStatus): ?>
  <a href="?" class="btn-act btn-ghost"><i class="bi bi-x"></i> Clear</a>
  <?php endif; ?>

  <!-- View toggle -->
  <div style="margin-left:auto;display:flex;align-items:center;gap:.5rem">
    <span style="font-size:.75rem;color:#8b949e"><?php echo count($facilities); ?> facilit<?php echo count($facilities)!==1?'ies':'y'; ?></span>
    <div class="view-toggle">
      <button type="button" class="vt-btn active" id="btnCards" onclick="setView('cards')" title="Card view"><i class="bi bi-grid-3x3-gap"></i></button>
      <button type="button" class="vt-btn" id="btnList"  onclick="setView('list')"  title="List view"><i class="bi bi-list-ul"></i></button>
    </div>
  </div>
</form>

<?php if (empty($facilities)): ?>
<div class="empty">
  <i class="bi bi-building-x"></i>
  <div style="font-size:.95rem;font-weight:600;color:#e6edf3;margin-bottom:.35rem">No facilities found</div>
  <div style="font-size:.82rem">Adjust your filters or add a new facility.</div>
</div>
<?php else: ?>

<!-- ════════════════════════ CARD VIEW ════════════════════════ -->
<div class="fac-grid" id="viewCards">
<?php foreach ($facilities as $f):
  $pop     = (int)$f['current_population'];
  $cap     = max(1, (int)$f['total_capacity']);
  $pct     = min(100, round($pop / $cap * 100));
  $occClr  = $pct >= 90 ? '#f85149' : ($pct >= 70 ? '#f39c12' : '#3fb950');
  $stChip  = match($f['status']) {
    'ACTIVE'            => 'chip-active',
    'INACTIVE'          => 'chip-inactive',
    'UNDER_MAINTENANCE' => 'chip-maintenance',
    default             => 'chip-inactive'
  };
  $tyChip  = match($f['type']) {
    'PRISON'         => 'chip-prison',
    'ADMIN_OFFICE'   => 'chip-admin',
    'HOLDING_CENTER' => 'chip-holding',
    default          => 'chip-admin'
  };
  $tyIcon  = match($f['type']) {
    'PRISON'         => 'bi-building-lock',
    'ADMIN_OFFICE'   => 'bi-building-gear',
    'HOLDING_CENTER' => 'bi-shield-lock',
    default          => 'bi-building'
  };
  $tyBg    = match($f['type']) {
    'PRISON'         => ['ico'=>'rgba(248,81,73,.15)',  'clr'=>'#f85149', 'bar'=>'#f85149'],
    'ADMIN_OFFICE'   => ['ico'=>'rgba(56,139,253,.15)', 'clr'=>'#58a6ff', 'bar'=>'#388bfd'],
    'HOLDING_CENTER' => ['ico'=>'rgba(155,89,182,.15)', 'clr'=>'#bb8fce', 'bar'=>'#a371f7'],
    default          => ['ico'=>'rgba(56,139,253,.15)', 'clr'=>'#58a6ff', 'bar'=>'#388bfd'],
  };
?>
<div class="fac-card" onclick='viewFacility(<?php echo json_encode($f); ?>)' style="cursor:pointer">
  <div class="fc-ribbon" style="background:<?php echo $tyBg['bar']; ?>"></div>
  <div class="fc-head">
    <div style="display:flex;align-items:flex-start;gap:.75rem;flex:1;min-width:0">
      <div class="fc-ico" style="background:<?php echo $tyBg['ico']; ?>;color:<?php echo $tyBg['clr']; ?>">
        <i class="bi <?php echo $tyIcon; ?>"></i>
      </div>
      <div style="min-width:0">
        <div class="fc-name"><?php echo htmlspecialchars($f['name']); ?></div>
        <div class="fc-loc"><i class="bi bi-geo-alt"></i><?php echo htmlspecialchars($f['location'] ?: 'No location'); ?></div>
      </div>
    </div>
    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;flex-shrink:0">
      <span class="chip <?php echo $stChip; ?>"><?php echo ucfirst(strtolower(str_replace('_',' ',$f['status']))); ?></span>
      <span class="chip <?php echo $tyChip; ?>"><?php echo ucfirst(strtolower(str_replace('_',' ',$f['type']))); ?></span>
    </div>
  </div>

  <!-- Occupancy bar -->
  <div class="occ-wrap">
    <div class="occ-label">
      <span>Occupancy</span>
      <span class="occ-val" style="color:<?php echo $occClr; ?>"><?php echo $pct; ?>%</span>
    </div>
    <div class="occ-bar">
      <div class="occ-fill" style="width:<?php echo $pct; ?>%;background:<?php echo $occClr; ?>"></div>
    </div>
  </div>

  <!-- Stats -->
  <div class="fc-stats">
    <div class="fc-stat">
      <div class="fc-stat-val"><?php echo number_format($pop); ?></div>
      <div class="fc-stat-lbl">Inmates</div>
    </div>
    <div class="fc-stat">
      <div class="fc-stat-val"><?php echo number_format($f['staff_count']); ?></div>
      <div class="fc-stat-lbl">Staff</div>
    </div>
    <div class="fc-stat">
      <div class="fc-stat-val" style="color:<?php echo $f['incidents_month']>0?'#f39c12':'var(--txt)'; ?>">
        <?php echo number_format($f['incidents_month']); ?>
      </div>
      <div class="fc-stat-lbl">Incidents</div>
    </div>
  </div>

  <!-- Contact -->
  <div class="fc-contact">
    <?php if ($f['contact_person']): ?>
    <div class="fc-contact-row"><i class="bi bi-person"></i><span><?php echo htmlspecialchars($f['contact_person']); ?></span></div>
    <?php endif; ?>
    <?php if ($f['contact_phone']): ?>
    <div class="fc-contact-row"><i class="bi bi-telephone"></i><span><?php echo htmlspecialchars($f['contact_phone']); ?></span></div>
    <?php endif; ?>
    <?php if ($f['contact_email']): ?>
    <div class="fc-contact-row"><i class="bi bi-envelope"></i><span><?php echo htmlspecialchars($f['contact_email']); ?></span></div>
    <?php endif; ?>
  </div>

  <!-- Actions -->
  <div class="fc-actions" onclick="event.stopPropagation()">
    <button class="btn-act btn-ghost" onclick='viewFacility(<?php echo json_encode($f); ?>)'>
      <i class="bi bi-eye"></i> View
    </button>
    <a href="<?php echo APP_URL; ?>/modules/facilities/edit.php?id=<?php echo $f['id']; ?>" class="btn-act btn-filter">
      <i class="bi bi-pencil"></i> Edit
    </a>
  </div>
</div>
<?php endforeach; ?>
</div>

<!-- ════════════════════════ LIST VIEW ════════════════════════ -->
<div class="fac-list" id="viewList" style="display:none">
  <div class="fl-head">
    <div class="fl-th">Facility</div>
    <div class="fl-th">Type</div>
    <div class="fl-th">Location</div>
    <div class="fl-th">Occupancy</div>
    <div class="fl-th">Staff</div>
    <div class="fl-th">Status</div>
    <div class="fl-th" style="text-align:right">Actions</div>
  </div>
  <?php foreach ($facilities as $f):
    $pop    = (int)$f['current_population'];
    $cap    = max(1, (int)$f['total_capacity']);
    $pct    = min(100, round($pop/$cap*100));
    $occClr = $pct>=90?'#f85149':($pct>=70?'#f39c12':'#3fb950');
    $stChip = match($f['status']){
      'ACTIVE'=>'chip-active','INACTIVE'=>'chip-inactive',
      'UNDER_MAINTENANCE'=>'chip-maintenance',default=>'chip-inactive'};
    $tyChip = match($f['type']){
      'PRISON'=>'chip-prison','ADMIN_OFFICE'=>'chip-admin',
      'HOLDING_CENTER'=>'chip-holding',default=>'chip-admin'};
  ?>
  <div class="fl-row" onclick='viewFacility(<?php echo json_encode($f); ?>)'>
    <div class="fl-td">
      <div style="font-weight:600"><?php echo htmlspecialchars($f['name']); ?></div>
      <?php if ($f['contact_person']): ?><div style="font-size:.72rem;color:#8b949e"><?php echo htmlspecialchars($f['contact_person']); ?></div><?php endif; ?>
    </div>
    <div class="fl-td"><span class="chip <?php echo $tyChip; ?>"><?php echo ucfirst(strtolower(str_replace('_',' ',$f['type']))); ?></span></div>
    <div class="fl-td muted"><?php echo htmlspecialchars($f['location'] ?: '—'); ?></div>
    <div class="fl-td">
      <div style="display:flex;align-items:center;gap:.5rem">
        <div style="flex:1;height:5px;background:#21262d;border-radius:3px;overflow:hidden;min-width:60px">
          <div style="height:100%;width:<?php echo $pct; ?>%;background:<?php echo $occClr; ?>;border-radius:3px"></div>
        </div>
        <span style="font-size:.75rem;font-weight:600;color:<?php echo $occClr; ?>;min-width:32px"><?php echo $pct; ?>%</span>
      </div>
      <div style="font-size:.7rem;color:#8b949e;margin-top:2px"><?php echo $pop; ?> / <?php echo $f['total_capacity']; ?></div>
    </div>
    <div class="fl-td muted"><?php echo number_format($f['staff_count']); ?></div>
    <div class="fl-td"><span class="chip <?php echo $stChip; ?>"><?php echo ucfirst(strtolower(str_replace('_',' ',$f['status']))); ?></span></div>
    <div class="fl-td" style="text-align:right" onclick="event.stopPropagation()">
      <button class="btn-act btn-ghost" onclick='viewFacility(<?php echo json_encode($f); ?>)'><i class="bi bi-eye"></i></button>
      <a class="btn-act btn-filter" href="<?php echo APP_URL; ?>/modules/facilities/edit.php?id=<?php echo $f['id']; ?>"><i class="bi bi-pencil"></i></a>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ════════════════════════════════════
     MODAL: FACILITY DETAILS
     ════════════════════════════════════ -->
<div class="mbk" id="facModal">
  <div class="mbox">
    <div class="mhead">
      <h3 id="mTitle"><i class="bi bi-building" style="color:#388bfd"></i> Facility Details</h3>
      <button class="btn-x" onclick="closeModal()"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="mbody" id="mBody"></div>
    <div class="mfoot" id="mFoot"></div>
  </div>
</div>

<!-- ════════════════════════ JAVASCRIPT ════════════════════════ -->
<script>
/* ── View toggle ── */
var savedView = localStorage.getItem('facView') || 'cards';
setView(savedView, true);

function setView(v, silent) {
  document.getElementById('viewCards').style.display = v==='cards' ? '' : 'none';
  document.getElementById('viewList').style.display  = v==='list'  ? '' : 'none';
  document.getElementById('btnCards').classList.toggle('active', v==='cards');
  document.getElementById('btnList').classList.toggle('active',  v==='list');
  if(!silent) localStorage.setItem('facView', v);
}

/* ── Modal ── */
function closeModal(){ document.getElementById('facModal').classList.remove('open'); }
document.getElementById('facModal').addEventListener('click', function(e){ if(e.target===this) closeModal(); });
document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeModal(); });

var typeLabel = {PRISON:'Prison',ADMIN_OFFICE:'Admin Office',HOLDING_CENTER:'Holding Center'};
var typeClr   = {PRISON:'#f85149',ADMIN_OFFICE:'#58a6ff',HOLDING_CENTER:'#bb8fce'};
var stLabel   = {ACTIVE:'Active',INACTIVE:'Inactive',UNDER_MAINTENANCE:'Under Maintenance'};
var stClr     = {ACTIVE:'#3fb950',INACTIVE:'#8b949e',UNDER_MAINTENANCE:'#f39c12'};

function esc(s){
  var d=document.createElement('div');
  d.appendChild(document.createTextNode(String(s??'')));
  return d.innerHTML;
}

function viewFacility(f) {
  var pop = parseInt(f.current_population)||0;
  var cap = parseInt(f.total_capacity)||1;
  var pct = Math.min(100, Math.round(pop/cap*100));
  var occClr = pct>=90?'#f85149':(pct>=70?'#f39c12':'#3fb950');
  var tc = typeClr[f.type]||'#8b949e';
  var sc = stClr[f.status]||'#8b949e';

  document.getElementById('mTitle').innerHTML =
    '<i class="bi bi-building" style="color:#388bfd"></i> '+esc(f.name);

  var html = '';

  // Occupancy big
  html += '<div class="occ-big">'
        + '<div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#8b949e;margin-bottom:.25rem">Occupancy</div>'
        + '<div class="occ-big-bar"><div class="occ-big-fill" style="width:'+pct+'%;background:'+occClr+'"></div></div>'
        + '<div class="occ-nums"><span style="color:'+occClr+';font-weight:700;font-size:.9rem">'+pct+'%</span>'
        + '<span style="color:#8b949e;font-size:.8rem">'+pop+' of '+cap+' capacity</span>'
        + '</div></div>';

  // Mini stats
  html += '<div class="m-stats">'
        + '<div class="m-stat"><div class="m-stat-val">'+esc(f.inmate_count||0)+'</div><div class="m-stat-lbl">Inmates</div></div>'
        + '<div class="m-stat"><div class="m-stat-val">'+esc(f.staff_count||0)+'</div><div class="m-stat-lbl">Staff</div></div>'
        + '<div class="m-stat"><div class="m-stat-val" style="color:'+(parseInt(f.incidents_month)>0?'#f39c12':'var(--txt)')+'">'+esc(f.incidents_month||0)+'</div><div class="m-stat-lbl">Incidents</div></div>'
        + '</div>';

  var rows = [
    ['Type',           '<span style="color:'+tc+';font-weight:700">'+(typeLabel[f.type]||f.type)+'</span>'],
    ['Status',         '<span style="color:'+sc+';font-weight:700">'+(stLabel[f.status]||f.status)+'</span>'],
    ['Location',       esc(f.location||'—')],
    ['Contact Person', esc(f.contact_person||'—')],
    ['Contact Email',  esc(f.contact_email||'—')],
    ['Contact Phone',  esc(f.contact_phone||'—')],
    ['Added On',       f.created_at ? new Date(f.created_at).toLocaleDateString('en-GB',{day:'numeric',month:'short',year:'numeric'}) : '—'],
  ];
  html += '<div>';
  rows.forEach(function(r){
    html += '<div class="dr"><span class="dk">'+r[0]+'</span><span class="dv">'+r[1]+'</span></div>';
  });
  html += '</div>';

  document.getElementById('mBody').innerHTML = html;
  document.getElementById('mFoot').innerHTML =
    '<button class="btn-act btn-ghost" onclick="closeModal()">Close</button>'
    +'<a class="btn-act btn-filter" href="<?php echo APP_URL; ?>/modules/facilities/edit.php?id='+f.id+'"><i class="bi bi-pencil"></i> Edit</a>';

  document.getElementById('facModal').classList.add('open');
}
</script>

<?php require_once '../../includes/footer.php'; ?>
