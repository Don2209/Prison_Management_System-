<?php
$pageTitle = 'Medical Records';
require_once '../../includes/header.php';
requireAuth();
verifyPermission('view', 'medical_records');

$fid      = getCurrentFacility();
$isSA     = isSuperAdmin();

/* ══════════════ FILTERS ══════════════ */
$fTab     = $_GET['tab']    ?? 'all';
$fSearch  = trim($_GET['q'] ?? '');
$fFrom    = trim($_GET['from'] ?? '');
$fTo      = trim($_GET['to']   ?? '');
$validTabs = ['all','ACTIVE','RESOLVED','CHRONIC'];
if (!in_array($fTab, $validTabs)) $fTab = 'all';

/* ══════════════ WHERE builder ══════════════ */
$wParts  = ["mr.deleted_at IS NULL"];
$wParams = [];
$wTypes  = '';

if (!$isSA) {
    $wParts[]  = "mr.facility_id = ?";
    $wParams[] = $fid;
    $wTypes   .= 'i';
}
if ($fTab !== 'all') {
    $wParts[]  = "mr.status = ?";
    $wParams[] = $fTab;
    $wTypes   .= 's';
}
if ($fSearch) {
    $s          = "%$fSearch%";
    $wParts[]   = "(i.first_name LIKE ? OR i.last_name LIKE ? OR i.inmate_id LIKE ? OR mr.diagnosis LIKE ?)";
    $wParams    = array_merge($wParams, [$s,$s,$s,$s]);
    $wTypes    .= 'ssss';
}
if ($fFrom) {
    $wParts[]  = "DATE(mr.record_date) >= ?";
    $wParams[] = $fFrom;
    $wTypes   .= 's';
}
if ($fTo) {
    $wParts[]  = "DATE(mr.record_date) <= ?";
    $wParams[] = $fTo;
    $wTypes   .= 's';
}
$where = implode(' AND ', $wParts);

/* ══════════════ QUERIES ══════════════ */
$facilityClause = $isSA ? "1=1" : "mr.facility_id = $fid";
$medFacClause   = $isSA ? "1=1" : "mh.facility_id = $fid";

$kpiBase = "FROM medical_records mr WHERE $facilityClause AND mr.deleted_at IS NULL";
$kpis = [
    'total'    => fetchOne("SELECT COUNT(*) c $kpiBase",                                     [],   '')['c'] ?? 0,
    'active'   => fetchOne("SELECT COUNT(*) c $kpiBase AND mr.status='ACTIVE'",               [],   '')['c'] ?? 0,
    'resolved' => fetchOne("SELECT COUNT(*) c $kpiBase AND mr.status='RESOLVED'",             [],   '')['c'] ?? 0,
    'chronic'  => fetchOne("SELECT COUNT(*) c $kpiBase AND mr.status='CHRONIC'",              [],   '')['c'] ?? 0,
    'today'    => fetchOne("SELECT COUNT(*) c $kpiBase AND DATE(mr.record_date)=CURDATE()",   [],   '')['c'] ?? 0,
    'meds'     => fetchOne("SELECT COUNT(*) c FROM medication_history mh WHERE $medFacClause AND mh.deleted_at IS NULL AND (mh.end_date IS NULL OR mh.end_date >= CURDATE())", [], '')['c'] ?? 0,
];

$records = fetchAll(
    "SELECT mr.id, mr.record_date, mr.diagnosis, mr.treatment_plan, mr.status, mr.vital_signs,
            i.id as inmate_db_id, i.inmate_id as inmate_number, i.first_name, i.last_name,
            CONCAT(u.first_name,' ',u.last_name) as doctor_name
     FROM medical_records mr
     JOIN inmates i ON mr.inmate_id = i.id
     LEFT JOIN staff s ON mr.examined_by = s.id
     LEFT JOIN users u ON s.user_id = u.id
     WHERE $where
     ORDER BY mr.record_date DESC
     LIMIT 200",
    $wParams, $wTypes
);

/* ══════════════ STATUS CONFIG ══════════════ */
function medStatusChip($s) {
    $map = [
        'ACTIVE'   => ['bg'=>'rgba(248,81,73,.15)',  'clr'=>'#f85149', 'label'=>'Active'],
        'RESOLVED' => ['bg'=>'rgba(63,185,80,.15)',  'clr'=>'#3fb950', 'label'=>'Resolved'],
        'CHRONIC'  => ['bg'=>'rgba(243,156,18,.15)', 'clr'=>'#f39c12', 'label'=>'Chronic'],
    ];
    $c = $map[$s] ?? ['bg'=>'rgba(139,148,158,.15)','clr'=>'#8b949e','label'=>$s];
    return '<span style="display:inline-block;padding:2px 10px;border-radius:20px;font-size:.7rem;font-weight:700;background:'.$c['bg'].';color:'.$c['clr'].'">'.htmlspecialchars($c['label']).'</span>';
}
function initials($fn,$ln){ return strtoupper(substr($fn,0,1).substr($ln,0,1)); }
function avatarColor($name){
    $colors=['#1f6feb','#388bfd','#3fb950','#f39c12','#f85149','#bb8fce','#1abc9c','#e74c3c','#58a6ff'];
    return $colors[abs(crc32($name))%count($colors)];
}
?>
<!-- ═══════════════════════════════ STYLES ═══════════════════════════════ -->
<style>
:root{--sur:#161b22;--bdr:#21262d;--bg:#0d1117;--txt:#e6edf3;--mut:#8b949e;--acc:#1f6feb;--acc2:#388bfd}

/* KPI grid */
.kpi-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:1rem;margin-bottom:1.5rem}
@media(max-width:1100px){.kpi-grid{grid-template-columns:repeat(3,1fr)}}
@media(max-width:640px) {.kpi-grid{grid-template-columns:repeat(2,1fr)}}
.kpi{background:var(--sur);border:1px solid var(--bdr);border-radius:14px;padding:1.1rem 1.2rem;position:relative;overflow:hidden;transition:transform .18s,box-shadow .18s;cursor:default}
.kpi:hover{transform:translateY(-2px);box-shadow:0 6px 24px rgba(0,0,0,.35)}
.kpi::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;border-radius:14px 14px 0 0}
.kpi-ico{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1rem;margin-bottom:.75rem}
.kpi-val{font-size:1.65rem;font-weight:800;color:var(--txt);line-height:1;margin-bottom:.25rem}
.kpi-lbl{font-size:.73rem;font-weight:600;color:var(--mut);text-transform:uppercase;letter-spacing:.05em}

/* Tabs */
.tabs{display:flex;gap:.3rem;background:var(--sur);border:1px solid var(--bdr);border-radius:11px;padding:.3rem;margin-bottom:1rem;flex-wrap:wrap}
.tabs a{padding:.4rem .95rem;border-radius:8px;font-size:.82rem;font-weight:600;color:var(--mut);text-decoration:none;display:flex;align-items:center;gap:.35rem;transition:all .18s;white-space:nowrap}
.tabs a:hover{color:var(--txt);background:#21262d}
.tabs a.active{background:#21262d;color:var(--txt)}
.tab-badge{background:#21262d;color:#8b949e;border-radius:20px;padding:1px 7px;font-size:.68rem;font-weight:700}
.tabs a.active .tab-badge{background:var(--acc);color:#fff}

/* Toolbar */
.toolbar{display:flex;align-items:center;gap:.6rem;margin-bottom:1rem;flex-wrap:wrap}
.toolbar-left{display:flex;align-items:center;gap:.6rem;flex:1;flex-wrap:wrap}
.fi-search{background:#0d1117;border:1px solid #30363d;border-radius:9px;color:var(--txt);padding:.5rem .85rem .5rem 2.2rem;font-size:.875rem;outline:none;transition:border .2s,box-shadow .2s;flex:1;min-width:180px;font-family:inherit}
.fi-search::placeholder{color:#484f58}
.fi-search:focus{border-color:#388bfd;box-shadow:0 0 0 3px rgba(56,139,253,.15)}
.search-wrap{position:relative;flex:1;min-width:200px;max-width:320px}
.search-wrap i{position:absolute;left:.75rem;top:50%;transform:translateY(-50%);color:#8b949e;font-size:.8rem;pointer-events:none}
.fi-sm{background:#0d1117;border:1px solid #30363d;border-radius:9px;color:var(--txt);padding:.48rem .75rem;font-size:.82rem;outline:none;transition:border .2s}
.fi-sm:focus{border-color:#388bfd}
.fi-sm::placeholder{color:#484f58}
.fi-label{font-size:.75rem;color:#8b949e;white-space:nowrap}
.date-pair{display:flex;align-items:center;gap:.4rem}
.clear-btn{background:none;border:1px solid #30363d;border-radius:8px;color:#8b949e;padding:.4rem .75rem;font-size:.78rem;cursor:pointer;display:flex;align-items:center;gap:.3rem;transition:all .18s}
.clear-btn:hover{border-color:#58a6ff;color:#58a6ff}

/* Add button */
.btn-primary-sm{background:linear-gradient(135deg,#1f6feb,#388bfd);color:#fff;padding:.5rem 1.1rem;border-radius:9px;font-size:.85rem;font-weight:600;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;text-decoration:none;transition:opacity .18s;white-space:nowrap}
.btn-primary-sm:hover{opacity:.87;color:#fff}

/* Table card */
.tcard{background:var(--sur);border:1px solid var(--bdr);border-radius:16px;overflow:hidden}
.tcard-head{display:flex;align-items:center;justify-content:space-between;padding:.85rem 1.25rem;border-bottom:1px solid var(--bdr)}
.tcard-head .ttl{font-size:.87rem;font-weight:700;color:var(--txt)}
.tcard-head .cnt{font-size:.78rem;color:#8b949e}
table.med-tbl{width:100%;border-collapse:collapse}
table.med-tbl thead th{padding:.7rem 1rem;text-align:left;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#8b949e;border-bottom:1px solid var(--bdr);white-space:nowrap;background:rgba(255,255,255,.01)}
table.med-tbl tbody tr{border-bottom:1px solid rgba(33,38,45,.8);transition:background .15s}
table.med-tbl tbody tr:last-child{border-bottom:none}
table.med-tbl tbody tr:hover{background:rgba(255,255,255,.03)}
table.med-tbl td{padding:.7rem 1rem;font-size:.85rem;color:var(--txt);vertical-align:middle}

/* Avatar */
.av{width:34px;height:34px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:.73rem;font-weight:800;flex-shrink:0;color:#fff}
.inmcell{display:flex;align-items:center;gap:.7rem}
.inm-name{font-weight:600;font-size:.875rem;color:var(--txt);line-height:1.2}
.inm-id{font-size:.73rem;color:#8b949e;font-family:monospace}

/* Diag */
.diag-text{max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#c9d1d9;font-size:.82rem}
.doc-name{font-size:.82rem;color:#8b949e;display:flex;align-items:center;gap:.35rem}

/* Actions */
.act-btn{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:7px;border:1px solid #30363d;background:transparent;color:#8b949e;cursor:pointer;transition:all .18s;text-decoration:none;font-size:.82rem}
.act-btn:hover{border-color:#388bfd;color:#388bfd;background:rgba(56,139,253,.08)}
.act-btn.grn:hover{border-color:#3fb950;color:#3fb950;background:rgba(63,185,80,.08)}
.act-btn.pur:hover{border-color:#bb8fce;color:#bb8fce;background:rgba(187,143,206,.08)}

/* Empty */
.empty-state{text-align:center;padding:3.5rem 1rem;color:#8b949e}
.empty-state i{font-size:2.5rem;display:block;margin-bottom:.75rem;opacity:.35}
.empty-state p{font-size:.9rem;margin:0}

/* Page header */
.ph{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:.75rem}
.ph h1{font-size:1.35rem;font-weight:700;color:var(--txt);margin:0}
.bc{font-size:.78rem;color:#6e7681;display:flex;align-items:center;gap:.3rem;margin-bottom:.3rem}
.bc a{color:#388bfd;text-decoration:none}.bc a:hover{text-decoration:underline}

/* modal */
.modal-ov{position:fixed;inset:0;background:rgba(1,4,9,.75);z-index:1000;display:flex;align-items:center;justify-content:center;padding:1rem;opacity:0;pointer-events:none;transition:opacity .22s}
.modal-ov.open{opacity:1;pointer-events:auto}
.modal-box{background:#161b22;border:1px solid #30363d;border-radius:18px;width:100%;max-width:640px;max-height:90vh;overflow-y:auto;transform:translateY(20px);transition:transform .22s}
.modal-ov.open .modal-box{transform:translateY(0)}
.modal-hdr{display:flex;align-items:center;justify-content:space-between;padding:1.1rem 1.4rem;border-bottom:1px solid #21262d}
.modal-hdr h2{margin:0;font-size:1rem;font-weight:700;color:#e6edf3}
.modal-close{background:none;border:none;color:#8b949e;font-size:1.1rem;cursor:pointer;width:30px;height:30px;border-radius:7px;display:flex;align-items:center;justify-content:center;transition:all .18s}
.modal-close:hover{background:#21262d;color:#e6edf3}
.modal-body{padding:1.4rem}
.m-section{margin-bottom:1.25rem}
.m-section-title{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#8b949e;margin-bottom:.65rem;padding-bottom:.4rem;border-bottom:1px solid #21262d}
.m-grid{display:grid;grid-template-columns:1fr 1fr;gap:.75rem}
@media(max-width:480px){.m-grid{grid-template-columns:1fr}}
.m-field label{font-size:.72rem;color:#8b949e;display:block;margin-bottom:.2rem}
.m-field span{font-size:.875rem;color:#e6edf3;font-weight:500}
.m-text-val{font-size:.875rem;color:#c9d1d9;line-height:1.5}
.vital-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:.6rem}
.vital-item{background:#0d1117;border:1px solid #21262d;border-radius:9px;padding:.55rem .75rem;display:flex;flex-direction:column;gap:.15rem}
.vital-item .vl{font-size:.7rem;color:#8b949e}
.vital-item .vv{font-size:.92rem;font-weight:700;color:#e6edf3}
.med-chip{display:inline-block;background:rgba(187,143,206,.13);color:#bb8fce;border-radius:7px;padding:.25rem .65rem;font-size:.78rem;margin:.2rem .2rem 0 0}
</style>

<!-- ════════════════════════ PAGE HEADER ════════════════════════ -->
<div class="ph">
  <div>
    <div class="bc">
      <a href="<?php echo APP_URL; ?>/dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
      <i class="bi bi-chevron-right" style="font-size:.6rem"></i>
      <span>Medical Records</span>
    </div>
    <h1><i class="bi bi-heart-pulse-fill" style="color:#f85149;margin-right:.45rem"></i>Medical Records</h1>
  </div>
  <?php if (hasPermission('create','medical_records')): ?>
  <a href="<?php echo APP_URL; ?>/modules/medical/add-record.php" class="btn-primary-sm">
    <i class="bi bi-plus-lg"></i> Add Record
  </a>
  <?php endif; ?>
</div>

<!-- ════════════════════════ KPI GRID ════════════════════════ -->
<div class="kpi-grid">
<?php
$kpiDefs = [
    ['Total Records',    $kpis['total'],    'bi-clipboard2-pulse-fill', '#388bfd', 'rgba(56,139,253,.15)',  '#1f6feb'],
    ['Active Cases',     $kpis['active'],   'bi-activity',              '#f85149', 'rgba(248,81,73,.15)',   '#f85149'],
    ['Resolved',         $kpis['resolved'], 'bi-check-circle-fill',     '#3fb950', 'rgba(63,185,80,.15)',   '#3fb950'],
    ['Chronic Cases',    $kpis['chronic'],  'bi-clock-history',         '#f39c12', 'rgba(243,156,18,.15)',  '#f39c12'],
    ['Today\'s Records', $kpis['today'],    'bi-calendar-check-fill',   '#58a6ff', 'rgba(88,166,255,.15)',  '#58a6ff'],
    ['On Medication',    $kpis['meds'],     'bi-capsule',               '#bb8fce', 'rgba(187,143,206,.15)', '#bb8fce'],
];
foreach ($kpiDefs as [$lbl,$val,$icon,$clr,$bg,$bar]):
?>
<div class="kpi" style="border-top-color:<?php echo $bar; ?>">
  <div style="position:absolute;top:0;left:0;right:0;height:3px;background:<?php echo $bar; ?>;border-radius:14px 14px 0 0"></div>
  <div class="kpi-ico" style="background:<?php echo $bg; ?>;color:<?php echo $clr; ?>">
    <i class="bi <?php echo $icon; ?>"></i>
  </div>
  <div class="kpi-val"><?php echo number_format($val); ?></div>
  <div class="kpi-lbl"><?php echo $lbl; ?></div>
</div>
<?php endforeach; ?>
</div>

<!-- ════════════════════════ TABS ════════════════════════ -->
<div class="tabs">
<?php
$tabDefs = [
    ['all',      'All Records', 'bi-list-ul',           $kpis['total']   ],
    ['ACTIVE',   'Active',      'bi-activity',           $kpis['active']  ],
    ['RESOLVED', 'Resolved',    'bi-check-circle',       $kpis['resolved']],
    ['CHRONIC',  'Chronic',     'bi-clock-history',      $kpis['chronic'] ],
];
foreach ($tabDefs as [$tv,$tl,$ti,$tc]):
    $qp = http_build_query(array_merge($_GET, ['tab'=>$tv]));
?>
<a href="?<?php echo $qp; ?>" class="<?php echo $fTab===$tv?'active':''; ?>">
  <i class="bi <?php echo $ti; ?>"></i> <?php echo $tl; ?>
  <span class="tab-badge"><?php echo $tc; ?></span>
</a>
<?php endforeach; ?>
</div>

<!-- ════════════════════════ TOOLBAR ════════════════════════ -->
<form method="GET" action="" id="filterForm">
<input type="hidden" name="tab" value="<?php echo htmlspecialchars($fTab); ?>">
<div class="toolbar">
  <div class="toolbar-left">
    <div class="search-wrap">
      <i class="bi bi-search"></i>
      <input type="text" name="q" class="fi-search" placeholder="Search inmate, diagnosis…"
             value="<?php echo htmlspecialchars($fSearch); ?>"
             onchange="this.form.submit()">
    </div>
    <div class="date-pair">
      <span class="fi-label">From</span>
      <input type="date" name="from" class="fi-sm" value="<?php echo htmlspecialchars($fFrom); ?>" onchange="this.form.submit()">
      <span class="fi-label">To</span>
      <input type="date" name="to" class="fi-sm" value="<?php echo htmlspecialchars($fTo); ?>" onchange="this.form.submit()">
    </div>
    <?php if ($fSearch || $fFrom || $fTo): ?>
    <a href="?tab=<?php echo urlencode($fTab); ?>" class="clear-btn">
      <i class="bi bi-x"></i> Clear
    </a>
    <?php endif; ?>
  </div>
</div>
</form>

<!-- ════════════════════════ TABLE ════════════════════════ -->
<div class="tcard">
  <div class="tcard-head">
    <span class="ttl"><i class="bi bi-clipboard2-pulse" style="margin-right:.4rem"></i>Records</span>
    <span class="cnt"><?php echo count($records); ?> result<?php echo count($records)!=1?'s':''; ?></span>
  </div>
  <?php if (empty($records)): ?>
  <div class="empty-state">
    <i class="bi bi-clipboard2-x"></i>
    <p>No medical records found<?php echo ($fSearch||$fFrom||$fTo) ? ' matching your filters.' : '.'; ?></p>
  </div>
  <?php else: ?>
  <div style="overflow-x:auto">
  <table class="med-tbl">
    <thead>
      <tr>
        <th>#</th>
        <th>Inmate</th>
        <th>Date</th>
        <th>Diagnosis</th>
        <th>Doctor</th>
        <th>Status</th>
        <th style="text-align:right">Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($records as $i => $r):
        $fullName = $r['first_name'].' '.$r['last_name'];
        $clr = avatarColor($fullName);
        $ini = initials($r['first_name'], $r['last_name']);
        $dateStr = date('M d, Y', strtotime($r['record_date']));
        $diag = $r['diagnosis'] ?? '—';
        $vitalRaw = $r['vital_signs'];
        $vitals = [];
        if ($vitalRaw) {
            $vd = is_array($vitalRaw) ? $vitalRaw : json_decode($vitalRaw, true);
            if (is_array($vd)) $vitals = $vd;
        }
        $treat = $r['treatment_plan'] ?? '';
    ?>
      <tr>
        <td style="color:#8b949e;font-size:.78rem"><?php echo $i+1; ?></td>
        <td>
          <div class="inmcell">
            <div class="av" style="background:<?php echo $clr; ?>"><?php echo $ini; ?></div>
            <div>
              <div class="inm-name"><?php echo htmlspecialchars($fullName); ?></div>
              <div class="inm-id"><?php echo htmlspecialchars($r['inmate_number']); ?></div>
            </div>
          </div>
        </td>
        <td style="color:#8b949e;font-size:.82rem;white-space:nowrap"><?php echo $dateStr; ?></td>
        <td>
          <div class="diag-text" title="<?php echo htmlspecialchars($diag); ?>">
            <?php echo htmlspecialchars(strlen($diag)>60 ? substr($diag,0,60).'…' : $diag); ?>
          </div>
        </td>
        <td>
          <div class="doc-name">
            <i class="bi bi-person-badge"></i>
            <?php echo htmlspecialchars($r['doctor_name'] ?: '—'); ?>
          </div>
        </td>
        <td><?php echo medStatusChip($r['status']); ?></td>
        <td style="text-align:right">
          <div style="display:flex;align-items:center;gap:.35rem;justify-content:flex-end">
            <button class="act-btn" title="View Details"
              onclick="openModal(<?php echo htmlspecialchars(json_encode([
                'id'          => $r['id'],
                'inmate'      => $fullName,
                'inmate_id'   => $r['inmate_number'],
                'date'        => $dateStr,
                'diagnosis'   => $r['diagnosis'] ?? '',
                'treatment'   => $treat,
                'status'      => $r['status'],
                'doctor'      => $r['doctor_name'] ?: '',
                'vitals'      => $vitals,
              ])); ?>)">
              <i class="bi bi-eye"></i>
            </button>
            <?php if (hasPermission('edit','medical_records')): ?>
            <a href="<?php echo APP_URL; ?>/modules/medical/edit-record.php?id=<?php echo $r['id']; ?>"
               class="act-btn" title="Edit Record">
              <i class="bi bi-pencil"></i>
            </a>
            <a href="<?php echo APP_URL; ?>/modules/medical/medications.php?record_id=<?php echo $r['id']; ?>"
               class="act-btn pur" title="Medications">
              <i class="bi bi-capsule"></i>
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

<!-- ════════════════════════ VIEW MODAL ════════════════════════ -->
<div class="modal-ov" id="mdOv" onclick="if(event.target===this)closeModal()">
  <div class="modal-box">
    <div class="modal-hdr">
      <h2><i class="bi bi-clipboard2-pulse" style="color:#f85149;margin-right:.45rem"></i>Medical Record</h2>
      <button class="modal-close" onclick="closeModal()"><i class="bi bi-x"></i></button>
    </div>
    <div class="modal-body">
      <!-- Patient -->
      <div class="m-section">
        <div class="m-section-title">Patient</div>
        <div class="m-grid">
          <div class="m-field"><label>Name</label><span id="md-inmate"></span></div>
          <div class="m-field"><label>Inmate ID</label><span id="md-inmate-id" style="font-family:monospace"></span></div>
          <div class="m-field"><label>Record Date</label><span id="md-date"></span></div>
          <div class="m-field"><label>Status</label><span id="md-status"></span></div>
        </div>
      </div>
      <!-- Doctor -->
      <div class="m-section">
        <div class="m-section-title">Attending Doctor</div>
        <div class="m-field"><span id="md-doctor" style="display:flex;align-items:center;gap:.4rem"><i class="bi bi-person-badge" style="color:#8b949e"></i></span></div>
      </div>
      <!-- Diagnosis -->
      <div class="m-section">
        <div class="m-section-title">Diagnosis</div>
        <div class="m-text-val" id="md-diag"></div>
      </div>
      <!-- Treatment -->
      <div class="m-section" id="md-treat-sec">
        <div class="m-section-title">Treatment Plan</div>
        <div class="m-text-val" id="md-treat"></div>
      </div>
      <!-- Vitals -->
      <div class="m-section" id="md-vitals-sec">
        <div class="m-section-title">Vital Signs</div>
        <div class="vital-grid" id="md-vitals"></div>
      </div>
      <!-- Footer actions -->
      <div style="display:flex;gap:.6rem;margin-top:1.2rem;padding-top:1rem;border-top:1px solid #21262d;flex-wrap:wrap">
        <?php if (hasPermission('edit','medical_records')): ?>
        <a id="md-edit-link" href="#" class="btn-primary-sm" style="font-size:.82rem;padding:.45rem .9rem">
          <i class="bi bi-pencil"></i> Edit
        </a>
        <a id="md-meds-link" href="#" class="btn-primary-sm" style="font-size:.82rem;padding:.45rem .9rem;background:linear-gradient(135deg,#6f42c1,#9b59b6)">
          <i class="bi bi-capsule"></i> Medications
        </a>
        <?php endif; ?>
        <button onclick="closeModal()" class="btn-primary-sm" style="font-size:.82rem;padding:.45rem .9rem;background:transparent;border:1px solid #30363d;color:#8b949e">
          Close
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ════════════════════════ SCRIPT ════════════════════════ -->
<script>
var APP_URL = '<?php echo APP_URL; ?>';

function openModal(r) {
  document.getElementById('md-inmate').textContent    = r.inmate    || '—';
  document.getElementById('md-inmate-id').textContent = r.inmate_id || '—';
  document.getElementById('md-date').textContent      = r.date      || '—';
  document.getElementById('md-doctor').innerHTML      = '<i class="bi bi-person-badge" style="color:#8b949e"></i>' + (r.doctor || 'Not assigned');
  document.getElementById('md-diag').textContent      = r.diagnosis || '—';

  // Status chip
  var smap = {ACTIVE:'rgba(248,81,73,.15):#f85149:Active',RESOLVED:'rgba(63,185,80,.15):#3fb950:Resolved',CHRONIC:'rgba(243,156,18,.15):#f39c12:Chronic'};
  var sp   = (smap[r.status]||'rgba(139,148,158,.15):#8b949e:'+r.status).split(':');
  document.getElementById('md-status').innerHTML = '<span style="display:inline-block;padding:2px 10px;border-radius:20px;font-size:.75rem;font-weight:700;background:'+sp[0]+';color:'+sp[1]+'">'+sp[2]+'</span>';

  // Treatment
  var ts = document.getElementById('md-treat-sec');
  if (r.treatment) { document.getElementById('md-treat').textContent = r.treatment; ts.style.display=''; }
  else ts.style.display='none';

  // Vitals
  var vs = document.getElementById('md-vitals-sec');
  var vg = document.getElementById('md-vitals');
  vg.innerHTML = '';
  if (r.vitals && Object.keys(r.vitals).length) {
    var icons = {temperature:'bi-thermometer-half',blood_pressure:'bi-heart-pulse',pulse:'bi-activity',weight:'bi-bar-chart',height:'bi-arrows-vertical',oxygen_saturation:'bi-lungs'};
    Object.entries(r.vitals).forEach(([k,v]) => {
      if (!v) return;
      var lbl = k.replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase());
      var ico = icons[k]||'bi-dot';
      vg.innerHTML += '<div class="vital-item"><span class="vl"><i class="bi '+ico+'" style="margin-right:3px"></i>'+lbl+'</span><span class="vv">'+v+'</span></div>';
    });
    vs.style.display = vg.innerHTML ? '' : 'none';
  } else vs.style.display='none';

  // Links
  var el = document.getElementById('md-edit-link');
  var ml = document.getElementById('md-meds-link');
  if (el) el.href = APP_URL+'/modules/medical/edit-record.php?id='+r.id;
  if (ml) ml.href = APP_URL+'/modules/medical/medications.php?record_id='+r.id;

  document.getElementById('mdOv').classList.add('open');
  document.body.style.overflow='hidden';
}
function closeModal(){
  document.getElementById('mdOv').classList.remove('open');
  document.body.style.overflow='';
}
document.addEventListener('keydown',e=>{ if(e.key==='Escape') closeModal(); });
</script>

<?php require_once '../../includes/footer.php'; ?>
