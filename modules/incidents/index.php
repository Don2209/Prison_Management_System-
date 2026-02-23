<?php
$pageTitle = 'Incidents Management';
require_once '../../includes/header.php';
requireAuth();
verifyPermission('view', 'incidents');

$fid  = getCurrentFacility();
$isSA = isSuperAdmin();

/* ═══ FILTERS ═══ */
$fTab      = $_GET['tab']      ?? 'all';
$fSearch   = trim($_GET['q']   ?? '');
$fSeverity = trim($_GET['sev'] ?? '');
$fCat      = trim($_GET['cat'] ?? '');
$fFrom     = trim($_GET['from'] ?? '');
$fTo       = trim($_GET['to']   ?? '');
$validTabs = ['all','REPORTED','UNDER_INVESTIGATION','RESOLVED'];
if (!in_array($fTab, $validTabs)) $fTab = 'all';

/* ═══ WHERE ═══ */
$wParts  = ["inc.deleted_at IS NULL"];
$wParams = []; $wTypes = '';

if (!$isSA) { $wParts[] = "inc.facility_id = ?"; $wParams[] = $fid; $wTypes .= 'i'; }
if ($fTab !== 'all')  { $wParts[] = "inc.status = ?";   $wParams[] = $fTab;      $wTypes .= 's'; }
if ($fSeverity)       { $wParts[] = "inc.severity = ?"; $wParams[] = $fSeverity; $wTypes .= 's'; }
if ($fCat)            { $wParts[] = "inc.incident_category_id = ?"; $wParams[] = (int)$fCat; $wTypes .= 'i'; }
if ($fSearch) {
    $s = "%$fSearch%";
    $wParts[]  = "(ic.name LIKE ? OR inc.location LIKE ? OR inc.description LIKE ? OR i.first_name LIKE ? OR i.last_name LIKE ?)";
    $wParams   = array_merge($wParams, [$s,$s,$s,$s,$s]); $wTypes .= 'sssss';
}
if ($fFrom) { $wParts[] = "DATE(inc.incident_date) >= ?"; $wParams[] = $fFrom; $wTypes .= 's'; }
if ($fTo)   { $wParts[] = "DATE(inc.incident_date) <= ?"; $wParams[] = $fTo;   $wTypes .= 's'; }
$where = implode(' AND ', $wParts);

/* ═══ KPIs ═══ */
$fc   = $isSA ? "1=1" : "facility_id = $fid";
$base = "FROM incidents WHERE $fc AND deleted_at IS NULL";
$kpis = [
    'total'    => fetchOne("SELECT COUNT(*) c $base",                                  [],  '')['c'] ?? 0,
    'reported' => fetchOne("SELECT COUNT(*) c $base AND status='REPORTED'",            [],  '')['c'] ?? 0,
    'invest'   => fetchOne("SELECT COUNT(*) c $base AND status='UNDER_INVESTIGATION'", [],  '')['c'] ?? 0,
    'resolved' => fetchOne("SELECT COUNT(*) c $base AND status='RESOLVED'",            [],  '')['c'] ?? 0,
    'critical' => fetchOne("SELECT COUNT(*) c $base AND severity='CRITICAL'",          [],  '')['c'] ?? 0,
    'high'     => fetchOne("SELECT COUNT(*) c $base AND severity='HIGH'",              [],  '')['c'] ?? 0,
];

/* ═══ CATEGORIES for filter dropdown ═══ */
$categories = fetchAll("SELECT id, name FROM incident_categories WHERE deleted_at IS NULL ORDER BY name", [], '');

/* ═══ MAIN QUERY ═══ */
$records = fetchAll(
    "SELECT inc.id, inc.incident_date, inc.severity, inc.status, inc.location, inc.description,
            ic.name as category_name,
            i.inmate_id as inmate_number, i.first_name, i.last_name,
            CONCAT(ru.first_name,' ',ru.last_name) as reporter_name
     FROM incidents inc
     JOIN incident_categories ic ON inc.incident_category_id = ic.id
     LEFT JOIN inmates i ON inc.inmate_id = i.id
     LEFT JOIN users ru ON inc.reported_by = ru.id
     WHERE $where
     ORDER BY inc.incident_date DESC
     LIMIT 300",
    $wParams, $wTypes
);

/* ═══ HELPERS ═══ */
function sevChip($s) {
    $m = ['LOW'=>['rgba(63,185,80,.15)','#3fb950'],'MEDIUM'=>['rgba(243,156,18,.15)','#f39c12'],
          'HIGH'=>['rgba(248,81,73,.15)','#f85149'],'CRITICAL'=>['rgba(139,0,0,.35)','#ff6b6b']];
    $c = $m[$s] ?? ['rgba(139,148,158,.15)','#8b949e'];
    $ico = ['LOW'=>'bi-arrow-down','MEDIUM'=>'bi-dash','HIGH'=>'bi-arrow-up','CRITICAL'=>'bi-exclamation-triangle-fill'][$s] ?? 'bi-dot';
    return '<span style="display:inline-flex;align-items:center;gap:4px;padding:2px 9px;border-radius:20px;font-size:.7rem;font-weight:700;background:'.$c[0].';color:'.$c[1].'"><i class="bi '.$ico.'"></i>'.htmlspecialchars($s).'</span>';
}
function statusChip($s) {
    $m = ['REPORTED'=>['rgba(56,139,253,.15)','#58a6ff','Reported'],
          'UNDER_INVESTIGATION'=>['rgba(243,156,18,.15)','#f39c12','Investigating'],
          'RESOLVED'=>['rgba(63,185,80,.15)','#3fb950','Resolved']];
    $c = $m[$s] ?? ['rgba(139,148,158,.15)','#8b949e',$s];
    return '<span style="display:inline-block;padding:2px 10px;border-radius:20px;font-size:.7rem;font-weight:700;background:'.$c[0].';color:'.$c[1].'">'.htmlspecialchars($c[2]).'</span>';
}
function avatarColor($n){$cols=['#1f6feb','#3fb950','#f39c12','#f85149','#bb8fce','#1abc9c','#58a6ff'];return $cols[abs(crc32($n))%count($cols)];}
function initials($fn,$ln){return strtoupper(substr($fn,0,1).substr($ln,0,1));}
?>
<style>
:root{--sur:#161b22;--bdr:#21262d;--bg:#0d1117;--txt:#e6edf3;--mut:#8b949e;--acc:#1f6feb}

/* KPIs */
.kpi-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:1rem;margin-bottom:1.5rem}
@media(max-width:1100px){.kpi-grid{grid-template-columns:repeat(3,1fr)}}
@media(max-width:640px){.kpi-grid{grid-template-columns:repeat(2,1fr)}}
.kpi{background:var(--sur);border:1px solid var(--bdr);border-radius:14px;padding:1.1rem 1.2rem;position:relative;overflow:hidden;transition:transform .18s,box-shadow .18s}
.kpi:hover{transform:translateY(-2px);box-shadow:0 6px 24px rgba(0,0,0,.35)}
.kpi-ico{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1rem;margin-bottom:.75rem}
.kpi-val{font-size:1.65rem;font-weight:800;color:var(--txt);line-height:1;margin-bottom:.25rem}
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
.fi-date{background:#0d1117;border:1px solid #30363d;border-radius:9px;color:var(--txt);padding:.48rem .65rem;font-size:.82rem;outline:none;transition:border .2s;font-family:inherit}
.fi-date:focus{border-color:#388bfd}
.fi-lbl{font-size:.74rem;color:#8b949e;white-space:nowrap}
.clear-btn{background:none;border:1px solid #30363d;border-radius:8px;color:#8b949e;padding:.4rem .75rem;font-size:.78rem;cursor:pointer;display:flex;align-items:center;gap:.3rem;transition:all .18s;text-decoration:none;white-space:nowrap}
.clear-btn:hover{border-color:#58a6ff;color:#58a6ff}
.btn-primary-sm{background:linear-gradient(135deg,#1f6feb,#388bfd);color:#fff;padding:.5rem 1.1rem;border-radius:9px;font-size:.85rem;font-weight:600;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;text-decoration:none;transition:opacity .18s;white-space:nowrap}
.btn-primary-sm:hover{opacity:.87;color:#fff}

/* Table */
.tcard{background:var(--sur);border:1px solid var(--bdr);border-radius:16px;overflow:hidden}
.tcard-head{display:flex;align-items:center;justify-content:space-between;padding:.85rem 1.25rem;border-bottom:1px solid var(--bdr)}
.tcard-head .ttl{font-size:.87rem;font-weight:700;color:var(--txt)}
.tcard-head .cnt{font-size:.78rem;color:#8b949e}
table.inc-tbl{width:100%;border-collapse:collapse}
table.inc-tbl thead th{padding:.7rem 1rem;text-align:left;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#8b949e;border-bottom:1px solid var(--bdr);white-space:nowrap;background:rgba(255,255,255,.01)}
table.inc-tbl tbody tr{border-bottom:1px solid rgba(33,38,45,.8);transition:background .15s}
table.inc-tbl tbody tr:last-child{border-bottom:none}
table.inc-tbl tbody tr:hover{background:rgba(255,255,255,.03)}
table.inc-tbl td{padding:.75rem 1rem;font-size:.85rem;color:var(--txt);vertical-align:middle}

/* Cat chip */
.cat-chip{display:inline-block;padding:2px 10px;border-radius:7px;font-size:.73rem;font-weight:600;background:rgba(56,139,253,.1);color:#58a6ff;white-space:nowrap}

/* Inmate cell */
.inmcell{display:flex;align-items:center;gap:.65rem}
.av{width:32px;height:32px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:800;flex-shrink:0;color:#fff}
.inm-name{font-weight:600;font-size:.84rem;color:var(--txt);line-height:1.2}
.inm-id{font-size:.7rem;color:#8b949e;font-family:monospace}

/* Loc */
.loc-cell{font-size:.82rem;color:#8b949e;display:flex;align-items:center;gap:.3rem;max-width:130px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.desc-cell{font-size:.8rem;color:#8b949e;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

/* Actions */
.act-btn{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:7px;border:1px solid #30363d;background:transparent;color:#8b949e;cursor:pointer;transition:all .18s;text-decoration:none;font-size:.82rem}
.act-btn:hover{border-color:#388bfd;color:#388bfd;background:rgba(56,139,253,.08)}
.act-btn.amber:hover{border-color:#f39c12;color:#f39c12;background:rgba(243,156,18,.08)}

/* Page header */
.ph{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:.75rem}
.ph h1{font-size:1.35rem;font-weight:700;color:var(--txt);margin:0}
.bc{font-size:.78rem;color:#6e7681;display:flex;align-items:center;gap:.3rem;margin-bottom:.3rem}
.bc a{color:#388bfd;text-decoration:none}.bc a:hover{text-decoration:underline}

/* Empty */
.empty-state{text-align:center;padding:3.5rem 1rem;color:#8b949e}
.empty-state i{font-size:2.5rem;display:block;margin-bottom:.75rem;opacity:.35}
.empty-state p{font-size:.9rem;margin:0}

/* MODAL */
.modal-ov{position:fixed;inset:0;background:rgba(1,4,9,.75);z-index:1000;display:flex;align-items:center;justify-content:center;padding:1rem;opacity:0;pointer-events:none;transition:opacity .22s}
.modal-ov.open{opacity:1;pointer-events:auto}
.modal-box{background:#161b22;border:1px solid #30363d;border-radius:18px;width:100%;max-width:660px;max-height:90vh;overflow-y:auto;transform:translateY(20px);transition:transform .22s}
.modal-ov.open .modal-box{transform:translateY(0)}
.modal-hdr{display:flex;align-items:center;justify-content:space-between;padding:1.1rem 1.4rem;border-bottom:1px solid #21262d;position:sticky;top:0;background:#161b22;z-index:1}
.modal-hdr h2{margin:0;font-size:1rem;font-weight:700;color:#e6edf3;display:flex;align-items:center;gap:.5rem}
.modal-close{background:none;border:none;color:#8b949e;font-size:1.1rem;cursor:pointer;width:30px;height:30px;border-radius:7px;display:flex;align-items:center;justify-content:center;transition:all .18s}
.modal-close:hover{background:#21262d;color:#e6edf3}
.modal-body{padding:1.4rem}
.ms-title{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#8b949e;margin-bottom:.65rem;padding-bottom:.35rem;border-bottom:1px solid #21262d}
.m2{display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:1.1rem}
.m3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:.75rem;margin-bottom:1.1rem}
@media(max-width:480px){.m2,.m3{grid-template-columns:1fr}}
.mf label{font-size:.72rem;color:#8b949e;display:block;margin-bottom:.2rem}
.mf span{font-size:.875rem;color:#e6edf3;font-weight:500}
.mf-text{font-size:.875rem;color:#c9d1d9;line-height:1.6;margin-bottom:1.1rem}
.sev-bar{height:6px;border-radius:3px;margin-top:.5rem}
</style>

<?php if (!empty($_GET['created'])): ?>
<div style="background:rgba(63,185,80,.12);border:1px solid rgba(63,185,80,.3);border-radius:11px;padding:.8rem 1.1rem;margin-bottom:1.25rem;font-size:.875rem;color:#3fb950;display:flex;align-items:center;gap:.55rem">
  <i class="bi bi-check-circle-fill"></i>
  <span>Incident reported successfully.</span>
</div>
<?php endif; ?>

<!-- PAGE HEADER -->
<div class="ph">
  <div>
    <div class="bc">
      <a href="<?php echo APP_URL; ?>/dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
      <i class="bi bi-chevron-right" style="font-size:.6rem"></i>
      <span>Incidents</span>
    </div>
    <h1><i class="bi bi-exclamation-triangle-fill" style="color:#f39c12;margin-right:.45rem"></i>Incidents Management</h1>
  </div>
  <?php if (hasPermission('create','incidents')): ?>
  <a href="<?php echo APP_URL; ?>/modules/incidents/add.php" class="btn-primary-sm">
    <i class="bi bi-plus-lg"></i> Report Incident
  </a>
  <?php endif; ?>
</div>

<!-- KPI GRID -->
<div class="kpi-grid">
<?php
$kpiDefs = [
  ['Total Incidents',  $kpis['total'],    'bi-exclamation-circle-fill',   '#388bfd', 'rgba(56,139,253,.15)',  '#1f6feb'],
  ['Reported',         $kpis['reported'], 'bi-flag-fill',                  '#58a6ff', 'rgba(88,166,255,.15)',  '#58a6ff'],
  ['Investigating',    $kpis['invest'],   'bi-search',                     '#f39c12', 'rgba(243,156,18,.15)',  '#f39c12'],
  ['Resolved',         $kpis['resolved'], 'bi-check-circle-fill',          '#3fb950', 'rgba(63,185,80,.15)',   '#3fb950'],
  ['Critical',         $kpis['critical'], 'bi-shield-exclamation',         '#ff6b6b', 'rgba(139,0,0,.3)',      '#ff6b6b'],
  ['High Severity',    $kpis['high'],     'bi-arrow-up-circle-fill',       '#f85149', 'rgba(248,81,73,.15)',   '#f85149'],
];
foreach ($kpiDefs as [$lbl,$val,$icon,$clr,$bg,$bar]): ?>
<div class="kpi" style="border-top:3px solid <?php echo $bar; ?>">
  <div class="kpi-ico" style="background:<?php echo $bg; ?>;color:<?php echo $clr; ?>"><i class="bi <?php echo $icon; ?>"></i></div>
  <div class="kpi-val"><?php echo number_format($val); ?></div>
  <div class="kpi-lbl"><?php echo $lbl; ?></div>
</div>
<?php endforeach; ?>
</div>

<!-- TABS -->
<div class="tabs">
<?php
$tabDefs = [
  ['all',                'All',           'bi-list-ul',                $kpis['total']   ],
  ['REPORTED',           'Reported',      'bi-flag',                   $kpis['reported']],
  ['UNDER_INVESTIGATION','Investigating', 'bi-search',                 $kpis['invest']  ],
  ['RESOLVED',           'Resolved',      'bi-check-circle',           $kpis['resolved']],
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
    <input type="text" name="q" class="fi-search" placeholder="Search category, location, inmate…"
           value="<?php echo htmlspecialchars($fSearch); ?>" onchange="this.form.submit()">
  </div>

  <select name="sev" class="fi-sm" onchange="this.form.submit()">
    <option value="">All Severities</option>
    <?php foreach (['LOW','MEDIUM','HIGH','CRITICAL'] as $sv): ?>
    <option value="<?php echo $sv; ?>" <?php echo $fSeverity===$sv?'selected':''; ?>><?php echo ucfirst(strtolower($sv)); ?></option>
    <?php endforeach; ?>
  </select>

  <select name="cat" class="fi-sm" onchange="this.form.submit()">
    <option value="">All Categories</option>
    <?php foreach ($categories as $cat): ?>
    <option value="<?php echo $cat['id']; ?>" <?php echo $fCat==(string)$cat['id']?'selected':''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
    <?php endforeach; ?>
  </select>

  <span class="fi-lbl">From</span>
  <input type="date" name="from" class="fi-date" value="<?php echo htmlspecialchars($fFrom); ?>" onchange="this.form.submit()">
  <span class="fi-lbl">To</span>
  <input type="date" name="to" class="fi-date" value="<?php echo htmlspecialchars($fTo); ?>" onchange="this.form.submit()">

  <?php if ($fSearch||$fSeverity||$fCat||$fFrom||$fTo): ?>
  <a href="?tab=<?php echo urlencode($fTab); ?>" class="clear-btn"><i class="bi bi-x"></i> Clear</a>
  <?php endif; ?>
</div>
</form>

<!-- TABLE -->
<div class="tcard">
  <div class="tcard-head">
    <span class="ttl"><i class="bi bi-exclamation-circle" style="margin-right:.4rem"></i>Incident Reports</span>
    <span class="cnt"><?php echo count($records); ?> result<?php echo count($records)!=1?'s':''; ?></span>
  </div>
  <?php if (empty($records)): ?>
  <div class="empty-state">
    <i class="bi bi-exclamation-triangle"></i>
    <p>No incidents found<?php echo ($fSearch||$fSeverity||$fCat||$fFrom||$fTo) ? ' matching your filters.' : '.'; ?></p>
  </div>
  <?php else: ?>
  <div style="overflow-x:auto">
  <table class="inc-tbl">
    <thead>
      <tr>
        <th>#</th>
        <th>Category</th>
        <th>Inmate Involved</th>
        <th>Location</th>
        <th>Date &amp; Time</th>
        <th>Severity</th>
        <th>Status</th>
        <th style="text-align:right">Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($records as $idx => $r):
      $hasInmate = !empty($r['first_name']);
      $fullName  = $hasInmate ? $r['first_name'].' '.$r['last_name'] : '';
      $clr       = $hasInmate ? avatarColor($fullName) : '#484f58';
      $ini       = $hasInmate ? initials($r['first_name'],$r['last_name']) : '?';
      $dt        = date('M d, Y · H:i', strtotime($r['incident_date']));
    ?>
    <tr>
      <td style="color:#8b949e;font-size:.75rem"><?php echo $idx+1; ?></td>
      <td><span class="cat-chip"><?php echo htmlspecialchars($r['category_name']); ?></span></td>
      <td>
        <?php if ($hasInmate): ?>
        <div class="inmcell">
          <div class="av" style="background:<?php echo $clr; ?>"><?php echo $ini; ?></div>
          <div>
            <div class="inm-name"><?php echo htmlspecialchars($fullName); ?></div>
            <div class="inm-id"><?php echo htmlspecialchars($r['inmate_number'] ?? ''); ?></div>
          </div>
        </div>
        <?php else: ?>
          <span style="color:#484f58;font-size:.8rem">None</span>
        <?php endif; ?>
      </td>
      <td>
        <div class="loc-cell" title="<?php echo htmlspecialchars($r['location'] ?? ''); ?>">
          <i class="bi bi-geo-alt" style="flex-shrink:0"></i>
          <?php echo htmlspecialchars($r['location'] ?: '—'); ?>
        </div>
      </td>
      <td style="white-space:nowrap;font-size:.8rem;color:#8b949e"><?php echo $dt; ?></td>
      <td><?php echo sevChip($r['severity']); ?></td>
      <td><?php echo statusChip($r['status']); ?></td>
      <td style="text-align:right">
        <div style="display:flex;align-items:center;gap:.35rem;justify-content:flex-end">
          <button class="act-btn" title="View Details"
            onclick='openModal(<?php echo json_encode([
              "id"          => $r["id"],
              "category"    => $r["category_name"],
              "date"        => $dt,
              "location"    => $r["location"] ?? "",
              "severity"    => $r["severity"],
              "status"      => $r["status"],
              "description" => $r["description"] ?? "",
              "inmate"      => $fullName,
              "inmate_id"   => $r["inmate_number"] ?? "",
              "reporter"    => $r["reporter_name"] ?? "",
            ]); ?>)'>
            <i class="bi bi-eye"></i>
          </button>
          <?php if (hasPermission('edit','incidents')): ?>
          <a href="<?php echo APP_URL; ?>/modules/incidents/investigate.php?id=<?php echo $r['id']; ?>"
             class="act-btn amber" title="Investigate">
            <i class="bi bi-search"></i>
          </a>
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
      <h2><i class="bi bi-exclamation-triangle-fill" style="color:#f39c12"></i> Incident Details</h2>
      <button class="modal-close" onclick="closeModal()"><i class="bi bi-x"></i></button>
    </div>
    <div class="modal-body">

      <!-- Severity banner -->
      <div id="md-sev-banner" style="border-radius:10px;padding:.65rem 1rem;margin-bottom:1.25rem;display:flex;align-items:center;gap:.6rem;font-size:.875rem;font-weight:700"></div>

      <!-- Overview -->
      <div class="ms-title">Incident Overview</div>
      <div class="m3" style="margin-bottom:1.1rem">
        <div class="mf"><label>Category</label><span id="md-cat"></span></div>
        <div class="mf"><label>Date &amp; Time</label><span id="md-date" style="font-size:.82rem"></span></div>
        <div class="mf"><label>Location</label><span id="md-loc"></span></div>
      </div>
      <div class="m2" style="margin-bottom:1.25rem">
        <div class="mf"><label>Status</label><span id="md-status"></span></div>
        <div class="mf"><label>Reported By</label><span id="md-reporter"></span></div>
      </div>

      <!-- Inmate -->
      <div id="md-inmate-sec">
        <div class="ms-title">Inmate Involved</div>
        <div class="m2" style="margin-bottom:1.25rem">
          <div class="mf"><label>Name</label><span id="md-inmate"></span></div>
          <div class="mf"><label>Inmate ID</label><span id="md-inmate-id" style="font-family:monospace"></span></div>
        </div>
      </div>

      <!-- Description -->
      <div class="ms-title">Description</div>
      <div class="mf-text" id="md-desc"></div>

      <!-- Footer -->
      <div style="display:flex;gap:.6rem;padding-top:1rem;border-top:1px solid #21262d;flex-wrap:wrap">
        <?php if (hasPermission('edit','incidents')): ?>
        <a id="md-inv-link" href="#" class="btn-primary-sm" style="font-size:.82rem;padding:.45rem .9rem;background:linear-gradient(135deg,#f39c12,#e67e22)">
          <i class="bi bi-search"></i> Investigate
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
var sevMap    = {LOW:{bg:'rgba(63,185,80,.12)',clr:'#3fb950',ico:'bi-arrow-down'},MEDIUM:{bg:'rgba(243,156,18,.12)',clr:'#f39c12',ico:'bi-dash'},HIGH:{bg:'rgba(248,81,73,.12)',clr:'#f85149',ico:'bi-arrow-up'},CRITICAL:{bg:'rgba(139,0,0,.25)',clr:'#ff6b6b',ico:'bi-exclamation-triangle-fill'}};
var statusMap = {REPORTED:{bg:'rgba(56,139,253,.15)',clr:'#58a6ff',lbl:'Reported'},UNDER_INVESTIGATION:{bg:'rgba(243,156,18,.15)',clr:'#f39c12',lbl:'Investigating'},RESOLVED:{bg:'rgba(63,185,80,.15)',clr:'#3fb950',lbl:'Resolved'}};

function openModal(r) {
  // Severity banner
  var sv = sevMap[r.severity] || {bg:'rgba(139,148,158,.15)',clr:'#8b949e',ico:'bi-dot'};
  var bn = document.getElementById('md-sev-banner');
  bn.style.background = sv.bg; bn.style.color = sv.clr;
  bn.innerHTML = '<i class="bi '+sv.ico+'"></i> '+r.severity+' SEVERITY';

  // Fields
  document.getElementById('md-cat').textContent      = r.category;
  document.getElementById('md-date').textContent     = r.date;
  document.getElementById('md-loc').textContent      = r.location || '—';
  document.getElementById('md-reporter').textContent = r.reporter || '—';
  document.getElementById('md-desc').textContent     = r.description || '—';

  // Status chip
  var sc = statusMap[r.status] || {bg:'rgba(139,148,158,.15)',clr:'#8b949e',lbl:r.status};
  document.getElementById('md-status').innerHTML = '<span style="display:inline-block;padding:2px 10px;border-radius:20px;font-size:.75rem;font-weight:700;background:'+sc.bg+';color:'+sc.clr+'">'+sc.lbl+'</span>';

  // Inmate
  var is = document.getElementById('md-inmate-sec');
  if (r.inmate) {
    document.getElementById('md-inmate').textContent    = r.inmate;
    document.getElementById('md-inmate-id').textContent = r.inmate_id || '—';
    is.style.display = '';
  } else { is.style.display = 'none'; }

  // Investigate link
  var il = document.getElementById('md-inv-link');
  if (il) il.href = APP_URL+'/modules/incidents/investigate.php?id='+r.id;

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
