<?php
/**
 * System Dashboard – Advanced Analytics View
 */

require_once dirname(__FILE__) . '/config/app.php';
require_once dirname(__FILE__) . '/config/db.php';
require_once dirname(__FILE__) . '/includes/functions.php';

requireAuth();
$pageTitle   = 'Dashboard';
$currentUser = getCurrentUser();

/* ── Facility scope: super admin can filter by a specific facility ── */
$isSA = isSuperAdmin();

// For super admins: honour ?fid= filter; default null = all facilities
$fidFilter = null;
if ($isSA && isset($_GET['fid']) && (int)$_GET['fid'] > 0) {
    $fidFilter = (int)$_GET['fid'];
}

// The "working" facility id used for single-facility functions
$fid = $isSA ? ($fidFilter ?? null) : getCurrentFacility();

$allFacilities = $isSA ? getAccessibleFacilities() : [];
$facility      = $fid ? getFacility($fid) : null;

// Stats & occupancy – system-wide for SA (unless filtering on one facility)
if ($isSA && !$fidFilter) {
    $stats    = getSystemStatistics();
    $occupancy = getSystemOccupancy();
} else {
    $stats    = getFacilityStatistics($fid);
    $occupancy = getFacilityOccupancy($fid);
}

/* ── Chart: Inmate admissions – last 12 months ── */
$inmateMonths  = [];
$inmateCounts  = [];
for ($i = 11; $i >= 0; $i--) {
    $dt = new DateTime("first day of -$i months");
    $inmateMonths[] = $dt->format('M Y');
    $facilityFilter = ($isSA && !$fidFilter) ? '' : 'AND facility_id = ' . (int)$fid;
    $row = fetchOne(
        "SELECT COUNT(*) as cnt FROM inmates
         WHERE YEAR(admission_date)=? AND MONTH(admission_date)=? $facilityFilter AND deleted_at IS NULL",
        [(int)$dt->format('Y'), (int)$dt->format('m')],
        'ii'
    );
    $inmateCounts[] = (int)($row['cnt'] ?? 0);
}

/* ── Chart: Incidents by month – last 6 months ── */
$incidentMonths = [];
$incLow = $incMed = $incHigh = $incCrit = [];
for ($i = 5; $i >= 0; $i--) {
    $dt = new DateTime("first day of -$i months");
    $incidentMonths[] = $dt->format('M Y');
    $y = (int)$dt->format('Y');
    $m = (int)$dt->format('m');
    $ff = ($isSA && !$fidFilter) ? '' : 'AND facility_id = ' . (int)$fid;
    foreach (['LOW'=>&$incLow,'MEDIUM'=>&$incMed,'HIGH'=>&$incHigh,'CRITICAL'=>&$incCrit] as $sev => &$arr) {
        $r = fetchOne(
            "SELECT COUNT(*) as cnt FROM incidents
             WHERE YEAR(incident_date)=? AND MONTH(incident_date)=? AND severity=? $ff AND deleted_at IS NULL",
            [$y, $m, $sev], 'iis'
        );
        $arr[] = (int)($r['cnt'] ?? 0);
    }
    unset($arr);
}

/* ── Chart: Inmate status breakdown ── */
$statusFilter = ($isSA && !$fidFilter)
    ? "deleted_at IS NULL"
    : "facility_id=$fid AND deleted_at IS NULL";
$statusRows = fetchAll("SELECT status, COUNT(*) as cnt FROM inmates WHERE $statusFilter GROUP BY status");
$statusLabels = array_column($statusRows, 'status');
$statusData   = array_map('intval', array_column($statusRows, 'cnt'));

/* ── Chart: Incident severity totals ── */
$sevFilter = ($isSA && !$fidFilter)
    ? "deleted_at IS NULL"
    : "facility_id=$fid AND deleted_at IS NULL";
$sevRows    = fetchAll("SELECT severity, COUNT(*) as cnt FROM incidents WHERE $sevFilter GROUP BY severity");
$sevLabels  = array_column($sevRows, 'severity');
$sevData    = array_map('intval', array_column($sevRows, 'cnt'));

/* ── Recent data ── */
if ($isSA && !$fidFilter) {
    $recentComplaints = getAllPendingComplaints(5);
    $recentIncidents  = getAllRecentIncidents(5);
} else {
    $recentComplaints = getPendingComplaints($fid, 5);
    $recentIncidents  = getFacilityIncidents($fid, null, 5);
}
?>
<?php require_once 'includes/header.php'; ?>

<!-- ═══════════════════════════════════════════════════
     EXTRA INLINE STYLES – smooth feel on top of Bootstrap
     ═══════════════════════════════════════════════════ -->
<style>
/* ---- palette overrides ---- */
:root {
  --bs-body-bg: #0d1117;
  --bs-body-color: #e6edf3;
  --c-surface: #161b22;
  --c-border: #21262d;
  --c-accent: #1f6feb;
  --c-accent2: #388bfd;
  --c-muted: #8b949e;
}

body { background: var(--bs-body-bg); }

/* ---- Stat KPI card ---- */
.kpi-card {
  background: var(--c-surface);
  border: 1px solid var(--c-border);
  border-radius: 16px;
  padding: 24px;
  transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
  position: relative;
  overflow: hidden;
}
.kpi-card::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 3px;
  border-radius: 16px 16px 0 0;
  background: var(--kpi-stripe, #388bfd);
  opacity: .9;
}
.kpi-card:hover { transform: translateY(-4px); box-shadow: 0 12px 32px rgba(0,0,0,.35); border-color: var(--c-accent); }
.kpi-icon-wrap {
  width: 54px; height: 54px;
  border-radius: 14px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.5rem;
}
.kpi-val  { font-size: 2rem; font-weight: 800; line-height: 1; color: var(--bs-body-color); }
.kpi-lbl  { font-size: .78rem; color: var(--c-muted); text-transform: uppercase; letter-spacing: .06em; margin-top: 4px; }
.kpi-trend { font-size: .76rem; margin-top: 8px; }
.kpi-trend .arrow { font-size: .65rem; margin-right: 2px; }

/* ---- Chart card ---- */
.chart-card {
  background: var(--c-surface);
  border: 1px solid var(--c-border);
  border-radius: 16px;
  overflow: hidden;
}
.chart-card .cc-header {
  padding: 16px 20px 12px;
  border-bottom: 1px solid var(--c-border);
  display: flex; align-items: center; justify-content: space-between;
}
.cc-title { font-size: .92rem; font-weight: 700; color: var(--bs-body-color); margin: 0; }
.cc-sub   { font-size: .74rem; color: var(--c-muted); }
.cc-body  { padding: 20px; }

/* ---- Pill chip badges ---- */
.chip {
  display: inline-block;
  padding: 3px 10px;
  border-radius: 20px;
  font-size: .72rem;
  font-weight: 600;
  letter-spacing: .03em;
}
.chip-low             { background: rgba(39,174,96,.22);   color: #2ecc71; }
.chip-medium          { background: rgba(243,156,18,.22);  color: #f1c40f; }
.chip-high            { background: rgba(230,126,34,.25);  color: #e67e22; }
.chip-critical        { background: rgba(231,76,60,.25);   color: #ff6b6b; }
.chip-remand          { background: rgba(52,152,219,.22);  color: #5dade2; }
.chip-convicted       { background: rgba(155,89,182,.22);  color: #bb8fce; }
.chip-released        { background: rgba(39,174,96,.22);   color: #2ecc71; }
.chip-pending         { background: rgba(243,156,18,.22);  color: #f1c40f; }
/* incident statuses */
.chip-reported        { background: rgba(231,76,60,.22);   color: #ff6b6b; }
.chip-investigating   { background: rgba(52,152,219,.22);  color: #5dade2; }
.chip-resolved        { background: rgba(39,174,96,.22);   color: #2ecc71; }

/* ---- Table tweaks ---- */
.tbl-dark-custom {
  color: #e6edf3;
  font-size: .86rem;
  --bs-table-color: #e6edf3;
  --bs-table-bg: transparent;
  --bs-table-border-color: var(--c-border);
}
.tbl-dark-custom thead th {
  background: #0d1117;
  color: var(--c-muted);
  font-size: .72rem;
  text-transform: uppercase;
  letter-spacing: .07em;
  font-weight: 600;
  border-bottom: 1px solid var(--c-border) !important;
  padding: 10px 14px;
}
.tbl-dark-custom tbody td {
  border-color: var(--c-border);
  padding: 10px 14px;
  vertical-align: middle;
  color: #e6edf3;
}
.tbl-dark-custom tbody tr { transition: background .12s; }
.tbl-dark-custom tbody tr:hover td { background: #1c2128; }

/* ---- Progress occupancy ---- */
.occ-bar {
  height: 12px;
  border-radius: 20px;
  background: #21262d;
  overflow: hidden;
}
.occ-bar-fill {
  height: 100%;
  border-radius: 20px;
  transition: width .6s cubic-bezier(.4,0,.2,1);
}

/* ---- Avatar initials ---- */
.avatar-sm {
  width: 32px; height: 32px;
  border-radius: 50%;
  background: var(--c-accent);
  display: inline-flex; align-items: center; justify-content: center;
  font-size: .72rem; font-weight: 700; color: #fff;
  flex-shrink: 0;
}

/* ---- Separator ---- */
.section-title {
  font-size: .72rem; font-weight: 700;
  letter-spacing: .1em; text-transform: uppercase;
  color: var(--c-muted); margin-bottom: 14px;
}

/* ---- Page header gradient text ---- */
.gradient-text {
  background: linear-gradient(90deg, var(--c-accent2), #a371f7);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}
</style>

<!-- ════════════ PAGE HEADER ════════════ -->
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0" style="color:#e6edf3;">
      Overview <span class="gradient-text">Analytics</span>
    </h4>
    <p class="mb-0" style="font-size:.83rem; color:#8b949e;">
      <?php if ($isSA && !$fidFilter): ?>
      <i class="bi bi-globe2 me-1"></i>All Facilities — System-Wide View
      <?php else: ?>
      <i class="bi bi-geo-alt me-1"></i><?php echo htmlspecialchars($facility['name'] ?? 'Facility'); ?>
      <?php endif; ?>
      &nbsp;·&nbsp;<?php echo date('l, F j, Y'); ?>
    </p>
  </div>
  <div class="d-flex gap-2 flex-wrap align-items-center">

    <?php if ($isSA): ?>
    <!-- ── Facility selector (super admin only) ── -->
    <form method="GET" action="" class="d-flex align-items-center gap-2 m-0">
      <select name="fid" onchange="this.form.submit()"
        style="background:#161b22;border:1px solid #30363d;color:#e6edf3;border-radius:8px;padding:6px 10px;font-size:.82rem;cursor:pointer;">
        <option value="">🌐 All Facilities</option>
        <?php foreach ($allFacilities as $af): ?>
        <option value="<?php echo $af['id']; ?>" <?php echo $fidFilter == $af['id'] ? 'selected' : ''; ?>>
          🏢 <?php echo htmlspecialchars($af['name']); ?>
        </option>
        <?php endforeach; ?>
      </select>
    </form>
    <?php endif; ?>

    <?php if (hasPermission('create', 'inmates')): ?>
    <a href="<?php echo APP_URL; ?>/modules/inmates/add.php"
       class="btn btn-sm btn-primary d-flex align-items-center gap-1" style="border-radius:8px;">
      <i class="bi bi-person-plus-fill"></i> Add Inmate
    </a>
    <?php endif; ?>
    <?php if (hasPermission('create', 'incidents')): ?>
    <a href="<?php echo APP_URL; ?>/modules/incidents/add.php"
       class="btn btn-sm btn-outline-danger d-flex align-items-center gap-1" style="border-radius:8px;">
      <i class="bi bi-shield-exclamation"></i> Report Incident
    </a>
    <?php endif; ?>
  </div>
</div>

<!-- ════════════ KPI CARDS ════════════ -->
<div class="row g-3 mb-4">
  <?php
  $kpis = [
    [
      'label'  => 'Total Inmates',
      'value'  => number_format($stats['total_inmates'] ?? 0),
      'icon'   => 'bi-person-badge',
      'bg'     => 'rgba(31,111,235,.15)',
      'color'  => '#388bfd',
      'stripe' => 'linear-gradient(90deg,#1f6feb,#388bfd)',
      'sub'    => 'Active & remand population',
    ],
    [
      'label'  => 'Staff Members',
      'value'  => number_format($stats['total_staff'] ?? 0),
      'icon'   => 'bi-people-fill',
      'bg'     => 'rgba(39,174,96,.15)',
      'color'  => '#2ecc71',
      'stripe' => 'linear-gradient(90deg,#1a7a45,#2ecc71)',
      'sub'    => 'Active personnel',
    ],
    [
      'label'  => 'Incidents This Month',
      'value'  => number_format($stats['incidents_month'] ?? 0),
      'icon'   => 'bi-shield-exclamation',
      'bg'     => 'rgba(231,76,60,.15)',
      'color'  => '#e74c3c',
      'stripe' => 'linear-gradient(90deg,#c0392b,#e74c3c)',
      'sub'    => 'Security incidents',
    ],
    [
      'label'  => 'Pending Transfers',
      'value'  => number_format($stats['pending_transfers'] ?? 0),
      'icon'   => 'bi-arrow-left-right',
      'bg'     => 'rgba(243,156,18,.15)',
      'color'  => '#f39c12',
      'stripe' => 'linear-gradient(90deg,#d35400,#f39c12)',
      'sub'    => 'Awaiting approval',
    ],
    [
      'label'  => 'Occupancy Rate',
      'value'  => round($occupancy['percentage'] ?? 0, 1) . '%',
      'icon'   => 'bi-building-fill',
      'bg'     => ($occupancy['is_overcrowded'] ?? false) ? 'rgba(231,76,60,.15)' : 'rgba(155,89,182,.15)',
      'color'  => ($occupancy['is_overcrowded'] ?? false) ? '#e74c3c' : '#9b59b6',
      'stripe' => ($occupancy['is_overcrowded'] ?? false)
                    ? 'linear-gradient(90deg,#c0392b,#e74c3c)'
                    : 'linear-gradient(90deg,#6c3483,#9b59b6)',
      'sub'    => ($occupancy['current'] ?? 0) . ' / ' . ($occupancy['capacity'] ?? 0) . ' capacity',
    ],
    [
      'label'  => 'Pending Complaints',
      'value'  => number_format(count($recentComplaints)),
      'icon'   => 'bi-chat-left-dots-fill',
      'bg'     => 'rgba(52,152,219,.15)',
      'color'  => '#3498db',
      'stripe' => 'linear-gradient(90deg,#1a5276,#3498db)',
      'sub'    => 'Requires attention',
    ],
  ];

  // Extra cards for Super Admin system-wide view
  if ($isSA && !$fidFilter) {
      $kpis[] = [
        'label'  => 'Total Facilities',
        'value'  => number_format($stats['total_facilities'] ?? 0),
        'icon'   => 'bi-building',
        'bg'     => 'rgba(26,188,156,.15)',
        'color'  => '#1abc9c',
        'stripe' => 'linear-gradient(90deg,#148f77,#1abc9c)',
        'sub'    => 'Active prison facilities',
      ];
      $kpis[] = [
        'label'  => 'System Users',
        'value'  => number_format($stats['total_users'] ?? 0),
        'icon'   => 'bi-person-check-fill',
        'bg'     => 'rgba(142,68,173,.15)',
        'color'  => '#8e44ad',
        'stripe' => 'linear-gradient(90deg,#6c3483,#8e44ad)',
        'sub'    => 'Active user accounts',
      ];
  }

  foreach ($kpis as $k): ?>
  <div class="col-6 col-xl-4 col-xxl-2">
    <div class="kpi-card h-100" style="--kpi-stripe:<?php echo $k['stripe']; ?>">
      <div class="kpi-icon-wrap mb-3" style="background:<?php echo $k['bg']; ?>; color:<?php echo $k['color']; ?>">
        <i class="bi <?php echo $k['icon']; ?>"></i>
      </div>
      <div class="kpi-val"><?php echo $k['value']; ?></div>
      <div class="kpi-lbl"><?php echo $k['label']; ?></div>
      <div style="font-size:.73rem; color:#8b949e; margin-top:4px;"><?php echo $k['sub']; ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<?php if ($isSA && !$fidFilter && !empty($occupancy['facilities'])): ?>
<!-- ════════════ SUPER ADMIN: PER-FACILITY OVERVIEW ════════════ -->
<div class="mb-4">
  <div class="chart-card">
    <div class="cc-header">
      <div>
        <p class="cc-title"><i class="bi bi-building-check me-2" style="color:#1abc9c;"></i>Facility Overview</p>
        <p class="cc-sub">Real-time status across all <?php echo count($occupancy['facilities']); ?> facilities</p>
      </div>
      <a href="<?php echo APP_URL; ?>/modules/facilities/index.php"
         class="btn btn-sm btn-outline-secondary" style="font-size:.75rem; border-radius:8px;">
        Manage Facilities
      </a>
    </div>
    <div class="table-responsive">
      <table class="table tbl-dark-custom mb-0">
        <thead>
          <tr>
            <th>Facility</th>
            <th>Location</th>
            <th>Population</th>
            <th style="min-width:160px;">Occupancy</th>
            <th>Inmates</th>
            <th>Staff</th>
            <th>Incidents</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($occupancy['facilities'] as $frow):
            $pct    = $frow['occupancy_pct'];
            $barClr = $frow['is_overcrowded'] ? '#e74c3c' : ($pct > 80 ? '#f39c12' : '#2ecc71');
            // per-facility quick stats
            $fs = getFacilityStatistics($frow['id']);
          ?>
          <tr>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="avatar-sm" style="background:#1f6feb12;color:#388bfd;font-size:.7rem;">
                  <i class="bi bi-building"></i>
                </div>
                <strong style="font-size:.85rem;"><?php echo htmlspecialchars($frow['name']); ?></strong>
              </div>
            </td>
            <td style="color:#8b949e;font-size:.82rem;"><?php echo htmlspecialchars($frow['location'] ?? '—'); ?></td>
            <td style="font-size:.82rem;">
              <span style="color:#e6edf3;font-weight:600;"><?php echo $frow['current_population']; ?></span>
              <span style="color:#8b949e;"> / <?php echo $frow['total_capacity']; ?></span>
            </td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="occ-bar flex-grow-1" style="height:8px;">
                  <div class="occ-bar-fill" style="width:<?php echo min($pct,100); ?>%;background:<?php echo $barClr; ?>;"></div>
                </div>
                <span style="font-size:.75rem;color:<?php echo $barClr; ?>;font-weight:600;white-space:nowrap;">
                  <?php echo $pct; ?>%
                  <?php if ($frow['is_overcrowded']): ?><i class="bi bi-exclamation-triangle-fill ms-1"></i><?php endif; ?>
                </span>
              </div>
            </td>
            <td style="font-size:.82rem;color:#e6edf3;"><?php echo number_format($fs['total_inmates'] ?? 0); ?></td>
            <td style="font-size:.82rem;color:#e6edf3;"><?php echo number_format($fs['total_staff'] ?? 0); ?></td>
            <td style="font-size:.82rem;">
              <?php $inc = $fs['incidents_month'] ?? 0; ?>
              <span style="color:<?php echo $inc > 0 ? '#e74c3c' : '#2ecc71'; ?>;">
                <?php echo $inc > 0 ? $inc : '<i class="bi bi-check-circle-fill"></i>'; ?>
              </span>
            </td>
            <td>
              <a href="?fid=<?php echo $frow['id']; ?>"
                 class="btn btn-sm" style="font-size:.72rem;border-radius:6px;background:#21262d;color:#e6edf3;border:1px solid #30363d;padding:3px 10px;">
                View
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ════════════ ROW: Inmate Growth + Occupancy ════════════ -->
<div class="row g-3 mb-4">

  <!-- Inmate Admission Trend -->
  <div class="col-lg-8">
    <div class="chart-card h-100">
      <div class="cc-header">
        <div>
          <p class="cc-title"><i class="bi bi-graph-up-arrow me-2" style="color:#388bfd;"></i>Inmate Admission Trend</p>
          <p class="cc-sub">Monthly admissions over the last 12 months</p>
        </div>
        <span class="chip chip-remand">Last 12 Months</span>
      </div>
      <div class="cc-body">
        <canvas id="chartInmateTrend" style="max-height:260px;"></canvas>
      </div>
    </div>
  </div>

  <!-- Occupancy Gauge -->
  <div class="col-lg-4">
    <div class="chart-card h-100">
      <div class="cc-header">
        <div>
          <p class="cc-title"><i class="bi bi-building me-2" style="color:#9b59b6;"></i>
            <?php echo ($isSA && !$fidFilter) ? 'System Occupancy' : 'Facility Occupancy'; ?>
          </p>
          <p class="cc-sub"><?php echo $occupancy['current'] ?? 0; ?> / <?php echo $occupancy['capacity'] ?? 0; ?> inmates</p>
        </div>
      </div>
      <div class="cc-body d-flex flex-column align-items-center justify-content-center gap-3">
        <!-- Doughnut -->
        <div style="position:relative; width:180px; height:180px;">
          <canvas id="chartOccupancy"></canvas>
          <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;">
            <div style="font-size:1.7rem;font-weight:800;color:#e6edf3;"><?php echo round($occupancy['percentage'] ?? 0, 0); ?>%</div>
            <div style="font-size:.7rem;color:#8b949e;">Utilized</div>
          </div>
        </div>
        <!-- Bar breakdown -->
        <div style="width:100%;">
          <div class="d-flex justify-content-between mb-1" style="font-size:.78rem;">
            <span style="color:#8b949e;">Current / Capacity</span>
            <span style="color:#e6edf3;font-weight:600;"><?php echo $occupancy['current'] ?? 0; ?> / <?php echo $occupancy['capacity'] ?? 0; ?></span>
          </div>
          <?php $pct = min($occupancy['percentage'] ?? 0, 100); $barClr = ($occupancy['is_overcrowded']??false) ? '#e74c3c' : ($pct>80?'#f39c12':'#2ecc71'); ?>
          <div class="occ-bar"><div class="occ-bar-fill" style="width:<?php echo $pct; ?>%;background:<?php echo $barClr; ?>;"></div></div>
          <?php if ($occupancy['is_overcrowded'] ?? false): ?>
          <div class="d-flex align-items-center gap-1 mt-2" style="font-size:.75rem;color:#e74c3c;">
            <i class="bi bi-exclamation-triangle-fill"></i> Overcrowded – action required
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ════════════ ROW: Incidents + Status Breakdown ════════════ -->
<div class="row g-3 mb-4">

  <!-- Incidents by Severity (stacked bar) -->
  <div class="col-lg-8">
    <div class="chart-card h-100">
      <div class="cc-header">
        <div>
          <p class="cc-title"><i class="bi bi-bar-chart-fill me-2" style="color:#e74c3c;"></i>Incidents by Severity</p>
          <p class="cc-sub">Monthly breakdown over the last 6 months</p>
        </div>
        <span class="chip chip-high">Security</span>
      </div>
      <div class="cc-body">
        <canvas id="chartIncidents" style="max-height:240px;"></canvas>
      </div>
    </div>
  </div>

  <!-- Inmate Status Doughnut -->
  <div class="col-lg-4">
    <div class="chart-card h-100">
      <div class="cc-header">
        <div>
          <p class="cc-title"><i class="bi bi-pie-chart-fill me-2" style="color:#f39c12;"></i>Inmate Status</p>
          <p class="cc-sub">Current population breakdown</p>
        </div>
      </div>
      <div class="cc-body">
        <canvas id="chartStatus" style="max-height:220px;"></canvas>
        <!-- Legend -->
        <div class="mt-3 d-flex flex-wrap gap-2 justify-content-center" id="statusLegend"></div>
      </div>
    </div>
  </div>
</div>

<!-- ════════════ ROW: Incident Severity Totals + Quick Actions ════════════ -->
<div class="row g-3 mb-4">

  <!-- Severity totals radar -->
  <div class="col-lg-5">
    <div class="chart-card h-100">
      <div class="cc-header">
        <div>
          <p class="cc-title"><i class="bi bi-activity me-2" style="color:#2ecc71;"></i>Incident Severity Totals</p>
          <p class="cc-sub">All-time distribution</p>
        </div>
      </div>
      <div class="cc-body d-flex align-items-center justify-content-center">
        <canvas id="chartSeverity" style="max-height:230px; max-width:340px;"></canvas>
      </div>
    </div>
  </div>

  <!-- Quick Actions + System Status -->
  <div class="col-lg-7">
    <div class="chart-card h-100">
      <div class="cc-header">
        <p class="cc-title"><i class="bi bi-lightning-charge-fill me-2" style="color:#f39c12;"></i>Quick Actions</p>
      </div>
      <div class="cc-body">
        <div class="row g-2">
          <?php
          $actions = [];
          if (hasPermission('create', 'inmates'))   $actions[] = ['Add Inmate',      'bi-person-plus-fill',   'btn-primary',         APP_URL.'/modules/inmates/add.php',      '#388bfd'];
          if (hasPermission('view',   'inmates'))   $actions[] = ['View Inmates',    'bi-person-list',        'btn-outline-primary',  APP_URL.'/modules/inmates/index.php',    '#388bfd'];
          if (hasPermission('create', 'staff'))     $actions[] = ['Add Staff',       'bi-person-badge',       'btn-outline-success',  APP_URL.'/modules/staff/add.php',        '#2ecc71'];
          if (hasPermission('create', 'incidents')) $actions[] = ['Report Incident', 'bi-shield-exclamation', 'btn-outline-danger',   APP_URL.'/modules/incidents/add.php',    '#e74c3c'];
          if (hasPermission('view',   'finance'))   $actions[] = ['Finance',         'bi-cash-coin',          'btn-outline-warning',  APP_URL.'/modules/finance/accounts.php', '#f39c12'];
          if (hasPermission('view',   'reports'))   $actions[] = ['Reports',         'bi-bar-chart-line',     'btn-outline-info',     APP_URL.'/modules/reports/index.php',    '#3498db'];
          if (isSuperAdmin())                       $actions[] = ['Facilities',      'bi-building',           'btn-outline-secondary',APP_URL.'/modules/facilities/index.php', '#9b59b6'];
          foreach ($actions as $a): ?>
          <div class="col-6 col-sm-4">
            <a href="<?php echo $a[3]; ?>"
               class="d-flex align-items-center gap-2 w-100"
               style="border-radius:10px; font-size:.82rem; padding:10px 12px; background:#0d1117; border:1px solid var(--c-border); color:#e6edf3; text-decoration:none; transition:all .15s;"
               onmouseover="this.style.borderColor='#388bfd'; this.style.background='#161b22';"
               onmouseout="this.style.borderColor='var(--c-border)'; this.style.background='#0d1117';">
              <i class="bi <?php echo $a[1]; ?>" style="font-size:1rem; color:<?php echo $a[4]; ?>;"></i>
              <?php echo $a[0]; ?>
            </a>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- System health items -->
        <div class="mt-4">
          <p class="section-title mb-2">System Status</p>
          <div class="d-flex flex-column gap-2">
            <?php
            $systems = [
              ['Database',    'ONLINE',  'success'],
              ['Auth Service','ONLINE',  'success'],
              ['Scheduler',   'RUNNING', 'success'],
            ];
            foreach ($systems as $sys): ?>
            <div class="d-flex align-items-center justify-content-between"
                 style="background:#0d1117; border:1px solid var(--c-border); border-radius:8px; padding:9px 14px;">
              <span style="font-size:.82rem;"><?php echo $sys[0]; ?></span>
              <span class="badge bg-<?php echo $sys[2]; ?> bg-opacity-25 text-<?php echo $sys[2]; ?>" style="font-size:.7rem;">
                <span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:currentColor;margin-right:4px;vertical-align:middle;"></span>
                <?php echo $sys[1]; ?>
              </span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ════════════ ROW: Recent Tables ════════════ -->
<div class="row g-3 mb-2">

  <!-- Recent Complaints -->
  <div class="col-lg-6">
    <div class="chart-card">
      <div class="cc-header">
        <div>
          <p class="cc-title"><i class="bi bi-chat-left-dots me-2" style="color:#3498db;"></i>Pending Complaints</p>
          <p class="cc-sub">Latest <?php echo count($recentComplaints); ?> requiring attention</p>
        </div>
        <a href="<?php echo APP_URL; ?>/modules/complaints/" class="btn btn-sm btn-outline-secondary"
           style="font-size:.75rem; border-radius:8px;">View All</a>
      </div>
      <?php if (empty($recentComplaints)): ?>
      <div class="cc-body text-center py-5" style="color:#8b949e;">
        <i class="bi bi-check-circle fs-3 d-block mb-2 text-success"></i>
        <span style="font-size:.85rem;">No pending complaints</span>
      </div>
      <?php else: ?>
      <div class="table-responsive">
      <table class="table tbl-dark-custom mb-0">
        <thead><tr>
          <th>Inmate</th>
          <?php if ($isSA && !$fidFilter): ?><th>Facility</th><?php endif; ?>
          <th>Type</th><th>Status</th><th>Date</th>
        </tr></thead>
        <tbody>
          <?php foreach ($recentComplaints as $c): ?>
          <tr>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="avatar-sm"><?php echo strtoupper(substr($c['first_name'],0,1).substr($c['last_name'],0,1)); ?></div>
                <span><?php echo htmlspecialchars($c['first_name'].' '.$c['last_name']); ?></span>
              </div>
            </td>
            <?php if ($isSA && !$fidFilter): ?>
            <td style="font-size:.78rem;color:#8b949e;"><?php echo htmlspecialchars($c['facility_name'] ?? '—'); ?></td>
            <?php endif; ?>
            <td><?php echo htmlspecialchars($c['complaint_type']); ?></td>
            <td><span class="chip chip-pending"><?php echo $c['status']; ?></span></td>
            <td style="color:#8b949e;"><?php echo formatDate($c['submission_date'], 'M d'); ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Recent Incidents -->
  <div class="col-lg-6">
    <div class="chart-card">
      <div class="cc-header">
        <div>
          <p class="cc-title"><i class="bi bi-shield-exclamation me-2" style="color:#e74c3c;"></i>Recent Incidents</p>
          <p class="cc-sub">Latest <?php echo count($recentIncidents); ?> security events</p>
        </div>
        <a href="<?php echo APP_URL; ?>/modules/incidents/index.php" class="btn btn-sm btn-outline-secondary"
           style="font-size:.75rem; border-radius:8px;">View All</a>
      </div>
      <?php if (empty($recentIncidents)): ?>
      <div class="cc-body text-center py-5" style="color:#8b949e;">
        <i class="bi bi-check-circle fs-3 d-block mb-2 text-success"></i>
        <span style="font-size:.85rem;">No recent incidents</span>
      </div>
      <?php else: ?>
      <div class="table-responsive">
      <table class="table tbl-dark-custom mb-0">
        <thead><tr>
          <th>Category</th>
          <?php if ($isSA && !$fidFilter): ?><th>Facility</th><?php endif; ?>
          <th>Severity</th><th>Status</th><th>Date</th>
        </tr></thead>
        <tbody>
          <?php foreach ($recentIncidents as $inc):
            $sevMap = ['LOW'=>'chip-low','MEDIUM'=>'chip-medium','HIGH'=>'chip-high','CRITICAL'=>'chip-critical'];
            $sevCls = $sevMap[$inc['severity']] ?? 'chip-low';
            $staMap = ['REPORTED'=>'chip-reported','UNDER_INVESTIGATION'=>'chip-investigating','RESOLVED'=>'chip-resolved'];
            $staCls = $staMap[$inc['status']] ?? 'chip-pending';
            $staLbl = ['REPORTED'=>'Reported','UNDER_INVESTIGATION'=>'Investigating','RESOLVED'=>'Resolved'][$inc['status']] ?? $inc['status'];
          ?>
          <tr>
            <td><?php echo htmlspecialchars($inc['category_name']); ?></td>
            <?php if ($isSA && !$fidFilter): ?>
            <td style="font-size:.78rem;color:#8b949e;"><?php echo htmlspecialchars($inc['facility_name'] ?? '—'); ?></td>
            <?php endif; ?>
            <td><span class="chip <?php echo $sevCls; ?>"><?php echo $inc['severity']; ?></span></td>
            <td><span class="chip <?php echo $staCls; ?>"><?php echo $staLbl; ?></span></td>
            <td style="color:#8b949e;"><?php echo formatDate($inc['incident_date'], 'M d'); ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ════════════ CHART.JS ════════════ -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
/* ── Global Chart.js defaults ── */
Chart.defaults.color          = '#8b949e';
Chart.defaults.borderColor    = '#21262d';
Chart.defaults.font.family    = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
Chart.defaults.font.size      = 11;
Chart.defaults.plugins.legend.labels.usePointStyle = true;
Chart.defaults.plugins.legend.labels.padding       = 16;
Chart.defaults.plugins.tooltip.backgroundColor     = '#1c2128';
Chart.defaults.plugins.tooltip.borderColor         = '#30363d';
Chart.defaults.plugins.tooltip.borderWidth         = 1;
Chart.defaults.plugins.tooltip.padding             = 10;
Chart.defaults.plugins.tooltip.cornerRadius        = 8;
Chart.defaults.plugins.tooltip.titleFont           = { weight: '600' };

/* ── Data from PHP ── */
const inmateLabels  = <?php echo json_encode($inmateMonths); ?>;
const inmateCounts  = <?php echo json_encode($inmateCounts); ?>;
const incLabels     = <?php echo json_encode($incidentMonths); ?>;
const incLow        = <?php echo json_encode($incLow); ?>;
const incMed        = <?php echo json_encode($incMed); ?>;
const incHigh       = <?php echo json_encode($incHigh); ?>;
const incCrit       = <?php echo json_encode($incCrit); ?>;
const statusLabels  = <?php echo json_encode($statusLabels); ?>;
const statusData    = <?php echo json_encode($statusData); ?>;
const sevLabels     = <?php echo json_encode($sevLabels); ?>;
const sevData       = <?php echo json_encode($sevData); ?>;
const occCurr       = <?php echo (int)($occupancy['current'] ?? 0); ?>;
const occCap        = <?php echo (int)($occupancy['capacity'] ?? 1); ?>;
const overcrowded   = <?php echo ($occupancy['is_overcrowded'] ?? false) ? 'true' : 'false'; ?>;

/* ── Gradient helper ── */
function makeGradient(ctx, c1, c2) {
  const g = ctx.createLinearGradient(0, 0, 0, ctx.canvas.offsetHeight || 260);
  g.addColorStop(0, c1);
  g.addColorStop(1, c2);
  return g;
}

/* ── 1. Inmate Admission Trend (Area Line) ── */
const ctx1 = document.getElementById('chartInmateTrend').getContext('2d');
const areaGrad = makeGradient(ctx1, 'rgba(56,139,253,0.35)', 'rgba(56,139,253,0.0)');
new Chart(ctx1, {
  type: 'line',
  data: {
    labels: inmateLabels,
    datasets: [{
      label: 'Admissions',
      data: inmateCounts,
      borderColor: '#388bfd',
      borderWidth: 2.5,
      pointBackgroundColor: '#388bfd',
      pointBorderColor: '#0d1117',
      pointBorderWidth: 2,
      pointRadius: 4,
      pointHoverRadius: 6,
      fill: true,
      backgroundColor: areaGrad,
      tension: 0.45,
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: true,
    interaction: { mode: 'index', intersect: false },
    plugins: { legend: { display: false } },
    scales: {
      x: { grid: { color: '#21262d' }, ticks: { maxTicksLimit: 6 } },
      y: { grid: { color: '#21262d' }, ticks: { stepSize: 1 }, beginAtZero: true }
    }
  }
});

/* ── 2. Occupancy Doughnut ── */
const occColor = overcrowded ? '#e74c3c' : (occCurr/occCap > 0.8 ? '#f39c12' : '#2ecc71');
new Chart(document.getElementById('chartOccupancy'), {
  type: 'doughnut',
  data: {
    datasets: [{
      data: [occCurr, Math.max(occCap - occCurr, 0)],
      backgroundColor: [occColor, '#21262d'],
      borderWidth: 0,
      hoverBorderWidth: 0,
    }]
  },
  options: {
    cutout: '76%',
    responsive: true,
    plugins: { legend: { display: false }, tooltip: { enabled: false } }
  }
});

/* ── 3. Incidents Stacked Bar ── */
new Chart(document.getElementById('chartIncidents'), {
  type: 'bar',
  data: {
    labels: incLabels,
    datasets: [
      { label: 'Critical', data: incCrit, backgroundColor: 'rgba(231,76,60,.85)',  borderRadius: 0, borderSkipped: false },
      { label: 'High',     data: incHigh, backgroundColor: 'rgba(230,126,34,.85)', borderRadius: 0, borderSkipped: false },
      { label: 'Medium',   data: incMed,  backgroundColor: 'rgba(243,156,18,.85)', borderRadius: 0, borderSkipped: false },
      { label: 'Low',      data: incLow,  backgroundColor: 'rgba(39,174,96,.85)',  borderRadius: { topLeft:5, topRight:5 }, borderSkipped: 'bottom' },
    ]
  },
  options: {
    responsive: true,
    maintainAspectRatio: true,
    interaction: { mode: 'index' },
    plugins: { legend: { position: 'bottom' } },
    scales: {
      x: { stacked: true, grid: { display: false } },
      y: { stacked: true, grid: { color: '#21262d' }, beginAtZero: true, ticks: { stepSize: 1 } }
    }
  }
});

/* ── 4. Inmate Status Doughnut ── */
const statusColors = ['#388bfd','#9b59b6','#2ecc71','#e74c3c','#f39c12','#1abc9c'];
new Chart(document.getElementById('chartStatus'), {
  type: 'doughnut',
  data: {
    labels: statusLabels,
    datasets: [{
      data: statusData,
      backgroundColor: statusColors.slice(0, statusLabels.length),
      borderWidth: 2,
      borderColor: '#161b22',
      hoverOffset: 8,
    }]
  },
  options: {
    cutout: '60%',
    responsive: true,
    plugins: {
      legend: { display: false },
    }
  },
  plugins: [{
    id: 'customLegendStatus',
    afterRender(chart) {
      const el = document.getElementById('statusLegend');
      if (el.children.length > 0) return;
      chart.data.labels.forEach((lbl, i) => {
        const chip = document.createElement('span');
        chip.style.cssText = `display:inline-flex;align-items:center;gap:5px;font-size:.72rem;color:#e6edf3;background:#21262d;padding:3px 9px;border-radius:20px;`;
        chip.innerHTML = `<span style="width:8px;height:8px;border-radius:50%;background:${statusColors[i]};display:inline-block;"></span>${lbl}: <strong>${statusData[i]}</strong>`;
        el.appendChild(chip);
      });
    }
  }]
});

/* ── 5. Severity Polar Area ── */
const sevColors = { LOW:'rgba(39,174,96,.75)', MEDIUM:'rgba(243,156,18,.75)', HIGH:'rgba(230,126,34,.75)', CRITICAL:'rgba(231,76,60,.75)' };
const sevBg = sevLabels.map(l => sevColors[l] || 'rgba(100,100,100,.6)');
new Chart(document.getElementById('chartSeverity'), {
  type: 'polarArea',
  data: {
    labels: sevLabels,
    datasets: [{
      data: sevData,
      backgroundColor: sevBg,
      borderWidth: 1,
      borderColor: '#21262d',
    }]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { position: 'bottom' }
    },
    scales: {
      r: {
        grid: { color: '#21262d' },
        ticks: { backdropColor: 'transparent', color: '#8b949e' }
      }
    }
  }
});
</script>

<?php require_once 'includes/footer.php'; ?>
