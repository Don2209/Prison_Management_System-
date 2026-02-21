<?php
/**
 * Inmates Management – Enhanced UI
 */
$pageTitle = 'Inmates';
require_once '../../includes/header.php';
requireAuth();
verifyPermission('view', 'inmates');

$fid = getCurrentFacility();

/* ── Summary counts for KPI cards ── */
$ff = isSuperAdmin() ? "deleted_at IS NULL" : "facility_id=$fid AND deleted_at IS NULL";
$total      = (int)(fetchOne("SELECT COUNT(*) c FROM inmates WHERE $ff")['c'] ?? 0);
$remand     = (int)(fetchOne("SELECT COUNT(*) c FROM inmates WHERE $ff AND status='REMAND'")['c'] ?? 0);
$convicted  = (int)(fetchOne("SELECT COUNT(*) c FROM inmates WHERE $ff AND status='CONVICTED'")['c'] ?? 0);
$released   = (int)(fetchOne("SELECT COUNT(*) c FROM inmates WHERE $ff AND status='RELEASED'")['c'] ?? 0);
$transferred= (int)(fetchOne("SELECT COUNT(*) c FROM inmates WHERE $ff AND status='TRANSFERRED'")['c'] ?? 0);
$high_risk  = (int)(fetchOne("SELECT COUNT(*) c FROM inmates WHERE $ff AND risk_classification IN ('HIGH','MAXIMUM')")['c'] ?? 0);
$deceased   = (int)(fetchOne("SELECT COUNT(*) c FROM inmates WHERE $ff AND status='DECEASED'")['c'] ?? 0);
?>
<style>
/* ── CSS Custom properties ── */
:root {
  --sur : #161b22;
  --bdr : #21262d;
  --bg  : #0d1117;
  --txt : #e6edf3;
  --mut : #8b949e;
  --acc : #1f6feb;
  --acc2: #388bfd;
}

/* ══════════════════════════════════════
   KPI CARDS
   ══════════════════════════════════════ */
.kpi-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:14px; }
.kpi {
  background: var(--sur);
  border: 1px solid var(--bdr);
  border-radius: 16px;
  padding: 20px 18px 16px;
  position: relative;
  overflow: hidden;
  cursor: default;
  transition: transform .2s, box-shadow .2s, border-color .2s;
}
.kpi::before {
  content: '';
  position: absolute; inset: 0;
  background: var(--kpi-glow, transparent);
  opacity: 0;
  transition: opacity .2s;
  border-radius: 16px;
}
.kpi:hover { transform: translateY(-4px); box-shadow: 0 12px 32px rgba(0,0,0,.4); border-color: var(--kpi-color, #388bfd); }
.kpi:hover::before { opacity: 1; }
.kpi-top-bar {
  position: absolute; top:0; left:0; right:0; height:3px;
  background: var(--kpi-bar, #388bfd); border-radius:16px 16px 0 0;
}
.kpi-icon-wrap {
  width:42px; height:42px; border-radius:12px;
  display:flex; align-items:center; justify-content:center;
  font-size:1.15rem; margin-bottom:14px;
  background: var(--kpi-icon-bg, rgba(56,139,253,.15));
  color: var(--kpi-color, #388bfd);
}
.kpi-val {
  font-size:2rem; font-weight:800; color:var(--txt);
  line-height:1; letter-spacing:-.03em;
}
.kpi-lbl {
  font-size:.72rem; color:var(--mut);
  text-transform:uppercase; letter-spacing:.07em; margin-top:5px;
}
.kpi-sparkline {
  position:absolute; bottom:0; right:0; width:60px; height:36px; opacity:.18;
}

/* ══════════════════════════════════════
   PAGE HEADER
   ══════════════════════════════════════ */
.page-hero {
  display:flex; align-items:flex-start; justify-content:space-between;
  flex-wrap:wrap; gap:14px; margin-bottom:24px;
}
.hero-title {
  font-size:1.5rem; font-weight:800; color:var(--txt);
  letter-spacing:-.02em; margin:0;
  background: linear-gradient(90deg,#e6edf3 60%,#388bfd);
  background-clip:text; -webkit-background-clip:text; -webkit-text-fill-color:transparent;
}
.hero-sub { font-size:.82rem; color:var(--mut); margin-top:4px; }
.view-toggle {
  display:flex; gap:4px; background:var(--bg);
  border:1px solid var(--bdr); border-radius:10px; padding:4px;
}
.vt-btn {
  width:32px; height:32px; border-radius:7px; border:none;
  background:transparent; color:var(--mut);
  display:flex; align-items:center; justify-content:center;
  font-size:.9rem; cursor:pointer; transition:all .15s;
}
.vt-btn.active { background:var(--acc); color:#fff; }
.vt-btn:not(.active):hover { background:#21262d; color:var(--txt); }

/* ══════════════════════════════════════
   TOOLBAR
   ══════════════════════════════════════ */
.toolbar {
  background: var(--sur);
  border: 1px solid var(--bdr);
  border-radius: 14px;
  padding: 14px 16px;
}
.search-wrap { position:relative; }
.search-wrap .search-ico {
  position:absolute; left:12px; top:50%; transform:translateY(-50%);
  color:var(--mut); font-size:.88rem; pointer-events:none;
}
.search-input {
  background:var(--bg); border:1px solid var(--bdr); border-radius:9px;
  color:var(--txt); font-size:.875rem; padding:9px 40px 9px 34px;
  width:100%; transition:border-color .15s, box-shadow .15s;
}
.search-input:focus { outline:none; border-color:var(--acc); box-shadow:0 0 0 3px rgba(31,111,235,.18); }
.search-input::placeholder { color:var(--mut); }
.search-clear {
  position:absolute; right:10px; top:50%; transform:translateY(-50%);
  background:none; border:none; color:var(--mut); cursor:pointer;
  font-size:.82rem; padding:2px 4px; display:none;
}
.search-clear:hover { color:var(--txt); }

.fsel {
  background:var(--bg); border:1px solid var(--bdr); border-radius:9px;
  color:var(--txt); font-size:.82rem; padding:8px 28px 8px 12px;
  cursor:pointer; transition:border-color .15s; width:100%;
  appearance:none; -webkit-appearance:none;
  background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 16 16'%3E%3Cpath fill='%238b949e' d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
  background-repeat:no-repeat; background-position:right 9px center;
}
.fsel:focus    { outline:none; border-color:var(--acc); }
.fsel option   { background:#0d1117; }
.fsel.has-val  { border-color: var(--acc); color:var(--acc2); }

/* ── Active filter chips ── */
.chip-strip { display:flex; flex-wrap:wrap; gap:6px; margin-top:10px; }
.chip-strip:empty { display:none; }
.filter-chip {
  display:inline-flex; align-items:center; gap:5px;
  background:rgba(31,111,235,.15); border:1px solid rgba(56,139,253,.3);
  border-radius:20px; padding:3px 10px 3px 12px;
  font-size:.73rem; color:#388bfd; font-weight:600;
  cursor:pointer; transition:all .12s;
}
.filter-chip:hover { background:rgba(231,76,60,.15); border-color:rgba(231,76,60,.3); color:#ff6b6b; }
.filter-chip .fc-x { font-size:.62rem; }

/* ══════════════════════════════════════
   TABLE WRAPPER
   ══════════════════════════════════════ */
.tbl-wrap {
  background:var(--sur); border:1px solid var(--bdr);
  border-radius:16px; overflow:hidden;
}
.tbl-header {
  padding:14px 18px; border-bottom:1px solid var(--bdr);
  display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px;
}
.tbl-title { font-size:.88rem; font-weight:700; color:var(--txt); margin:0; }

/* ── Table ── */
#inmatesTable { width:100%; border-collapse:collapse; }
#inmatesTable thead th {
  background:var(--bg); color:var(--mut);
  font-size:.68rem; font-weight:700; letter-spacing:.09em;
  text-transform:uppercase; padding:11px 14px;
  border-bottom:1px solid var(--bdr); white-space:nowrap;
  position:sticky; top:0; z-index:2;
}
#inmatesTable thead th.sortable { cursor:pointer; user-select:none; }
#inmatesTable thead th.sortable:hover { color:var(--acc2); }
#inmatesTable thead th .si { font-size:.58rem; margin-left:4px; opacity:.4; transition:opacity .1s; }
#inmatesTable thead th.asc  .si::before  { content:"▲"; opacity:1; color:var(--acc2); }
#inmatesTable thead th.desc .si::before  { content:"▼"; opacity:1; color:var(--acc2); }
#inmatesTable thead th:not(.asc):not(.desc) .si::before { content:"⇅"; }

#inmatesTable tbody tr { transition:background .1s; }
#inmatesTable tbody tr:hover td { background:#1c2128 !important; }
#inmatesTable tbody td {
  padding:11px 14px; border-bottom:1px solid var(--bdr);
  color:var(--txt); font-size:.855rem; vertical-align:middle;
}
#inmatesTable tbody tr:last-child td { border-bottom:none; }
/* Maximum risk row glow */
#inmatesTable tbody tr.risk-max { background:rgba(231,76,60,.04); }
#inmatesTable tbody tr.risk-max td:first-child {
  border-left:2px solid rgba(231,76,60,.5);
}

/* ── Avatar ── */
.avatar {
  width:38px; height:38px; border-radius:50%;
  display:inline-flex; align-items:center; justify-content:center;
  font-size:.78rem; font-weight:800; color:#fff; flex-shrink:0;
  position:relative;
}
.avatar-ring {
  position:absolute; inset:-2px;
  border-radius:50%; border:2px solid transparent;
}
.name-cell { display:flex; align-items:center; gap:10px; }
.name-txt   { font-weight:600; color:var(--txt); line-height:1.2; }
.name-sub   { font-size:.72rem; color:var(--mut); margin-top:1px; }

/* ── Badges ── */
.bp {
  display:inline-flex; align-items:center; gap:4px;
  padding:3px 9px; border-radius:20px;
  font-size:.68rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase;
}
.st-remand     { background:rgba(52,152,219,.18);  color:#5dade2; border:1px solid rgba(52,152,219,.25); }
.st-convicted  { background:rgba(155,89,182,.18);  color:#bb8fce; border:1px solid rgba(155,89,182,.25); }
.st-released   { background:rgba(39,174,96,.18);   color:#2ecc71; border:1px solid rgba(39,174,96,.25); }
.st-transferred{ background:rgba(243,156,18,.18);  color:#f1c40f; border:1px solid rgba(243,156,18,.25); }
.st-deceased   { background:rgba(127,140,141,.18); color:#aab7b8; border:1px solid rgba(127,140,141,.25); }
.rk-low     { background:rgba(39,174,96,.15);   color:#2ecc71; border:1px solid rgba(39,174,96,.25); }
.rk-medium  { background:rgba(243,156,18,.15);  color:#f1c40f; border:1px solid rgba(243,156,18,.25); }
.rk-high    { background:rgba(230,126,34,.15);  color:#e67e22; border:1px solid rgba(230,126,34,.25); }
.rk-maximum { background:rgba(231,76,60,.18);   color:#ff6b6b; border:1px solid rgba(231,76,60,.3);
              box-shadow:0 0 6px rgba(231,76,60,.2); }

/* ── Risk micro-bar ── */
.risk-bar-wrap { display:flex; align-items:center; gap:8px; }
.risk-bar {
  flex:1; max-width:44px; height:3px; border-radius:3px;
  background:#21262d; overflow:hidden;
}
.risk-bar-fill { height:100%; border-radius:3px; }

/* ── Action buttons ── */
.row-actions { display:flex; gap:5px; justify-content:flex-end; }
.row-btn {
  width:30px; height:30px; border-radius:8px;
  border:1px solid var(--bdr); background:var(--bg); color:var(--mut);
  display:inline-flex; align-items:center; justify-content:center;
  font-size:.8rem; transition:all .15s; cursor:pointer; text-decoration:none;
  position:relative;
}
.row-btn:hover { border-color:var(--acc); color:var(--acc2); background:rgba(31,111,235,.1); transform:scale(1.1); }
.row-btn.xfer:hover  { border-color:#f1c40f; color:#f1c40f; background:rgba(243,156,18,.1); }
.row-btn.danger:hover{ border-color:#e74c3c; color:#ff6b6b; background:rgba(231,76,60,.1); }

/* ── Skeleton loader ── */
@keyframes shimmer {
  0%   { background-position:-600px 0; }
  100% { background-position: 600px 0; }
}
.skel {
  background: linear-gradient(90deg,#1c2128 25%,#21262d 37%,#1c2128 63%);
  background-size:600px 100%;
  animation: shimmer 1.4s infinite linear;
  border-radius:6px; display:inline-block;
}

/* ── States ── */
.state-box { text-align:center; padding:60px 20px; }
.state-icon { font-size:2.8rem; margin-bottom:14px; line-height:1; }
.state-box p { color:var(--mut); font-size:.875rem; margin:0; }

/* ── Pagination ── */
.pager { display:flex; align-items:center; gap:5px; flex-wrap:wrap; }
.pager-btn {
  min-width:32px; height:32px; border-radius:8px;
  border:1px solid var(--bdr); background:var(--bg); color:var(--mut);
  font-size:.78rem; display:inline-flex; align-items:center; justify-content:center;
  cursor:pointer; transition:all .15s; padding:0 8px;
}
.pager-btn:hover:not(:disabled) { border-color:var(--acc); color:var(--acc2); }
.pager-btn.active { background:var(--acc); border-color:var(--acc); color:#fff; font-weight:700; }
.pager-btn:disabled { opacity:.3; cursor:not-allowed; }
.pager-info { font-size:.76rem; color:var(--mut); white-space:nowrap; }

/* ── Gender ── */
.gdot { width:7px; height:7px; border-radius:50%; display:inline-block; margin-right:5px; }

/* ══════════════════════════════════════
   CARD VIEW
   ══════════════════════════════════════ */
.card-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:14px; padding:18px; }
.inmate-card {
  background:var(--bg); border:1px solid var(--bdr); border-radius:14px;
  padding:18px; transition:all .18s; cursor:pointer;
  position:relative; overflow:hidden;
}
.inmate-card:hover { border-color:var(--acc); transform:translateY(-2px); box-shadow:0 8px 24px rgba(0,0,0,.3); }
.inmate-card .card-avatar {
  width:52px; height:52px; border-radius:50%; font-size:1rem;
  font-weight:800; display:flex; align-items:center; justify-content:center; color:#fff;
  margin-bottom:12px; position:relative;
}
.inmate-card .card-name  { font-weight:700; color:var(--txt); font-size:.92rem; }
.inmate-card .card-id    { font-size:.74rem; color:var(--mut); margin-top:2px;
                           font-family:monospace; }
.inmate-card .card-meta  { display:flex; gap:6px; flex-wrap:wrap; margin-top:10px; }
.inmate-card .card-footer{
  display:flex; justify-content:space-between; align-items:center;
  margin-top:12px; padding-top:10px; border-top:1px solid var(--bdr);
}
.card-risk-stripe {
  position:absolute; top:0; left:0; right:0; height:3px;
}

/* ── Tooltip ── */
[data-tip] { position:relative; }
[data-tip]:hover::after {
  content: attr(data-tip);
  position:absolute; bottom:calc(100% + 6px); left:50%; transform:translateX(-50%);
  background:#1c2128; border:1px solid #21262d; color:#e6edf3;
  font-size:.72rem; white-space:nowrap; padding:4px 8px; border-radius:6px;
  pointer-events:none; z-index:100;
}

/* ── Responsive ── */
@media(max-width:640px) {
  .kpi-grid { grid-template-columns:repeat(2,1fr); }
  .hero-title { font-size:1.2rem; }
}
</style>

<!-- ══════════════════════════════════════
     PAGE HEADER
     ══════════════════════════════════════ -->
<div class="page-hero">
  <div>
    <h4 class="hero-title">
      <i class="bi bi-person-badge" style="-webkit-text-fill-color:#388bfd;"></i>&ensp;Inmate Population
    </h4>
    <p class="hero-sub">
      <span id="totalBadge" style="color:var(--acc2); font-weight:600;">—</span>
      &nbsp;records &nbsp;·&nbsp; <?php echo isSuperAdmin() ? 'All Facilities' : 'Your Facility'; ?>
    </p>
  </div>
  <div class="d-flex align-items-center gap-2 flex-wrap">
    <!-- View toggle -->
    <div class="view-toggle" id="viewToggle">
      <button class="vt-btn active" id="btnTable" data-tip="Table view" onclick="setView('table')">
        <i class="bi bi-table"></i>
      </button>
      <button class="vt-btn" id="btnCards" data-tip="Card view" onclick="setView('cards')">
        <i class="bi bi-grid-3x3-gap"></i>
      </button>
    </div>
    <?php if (hasPermission('create', 'inmates')): ?>
    <a href="<?php echo APP_URL; ?>/modules/inmates/add.php"
       class="btn btn-primary d-flex align-items-center gap-2"
       style="border-radius:10px; font-size:.84rem; padding:8px 16px;">
      <i class="bi bi-person-plus-fill"></i> Admit Inmate
    </a>
    <?php endif; ?>
    <?php if (hasPermission('view', 'transfers')): ?>
    <a href="<?php echo APP_URL; ?>/modules/inmates/transfers.php"
       class="btn btn-outline-secondary d-flex align-items-center gap-2"
       style="border-radius:10px; font-size:.84rem; padding:8px 14px;">
      <i class="bi bi-arrow-left-right"></i> Transfers
    </a>
    <?php endif; ?>
  </div>
</div>

<!-- ══════════════════════════════════════
     KPI STRIP
     ══════════════════════════════════════ -->
<?php
$kpis = [
  ['Total',       $total,      '#388bfd','rgba(56,139,253,.12)','bi-people-fill',             '#388bfd','linear-gradient(135deg,#1f6feb,#388bfd)'],
  ['Remand',      $remand,     '#5dade2','rgba(52,152,219,.12)','bi-hourglass-split',          '#5dade2','linear-gradient(135deg,#1a5276,#5dade2)'],
  ['Convicted',   $convicted,  '#bb8fce','rgba(155,89,182,.12)','bi-person-lock',              '#bb8fce','linear-gradient(135deg,#6c3483,#bb8fce)'],
  ['Released',    $released,   '#2ecc71','rgba(39,174,96,.12)', 'bi-person-check-fill',        '#2ecc71','linear-gradient(135deg,#1a7a45,#2ecc71)'],
  ['Transferred', $transferred,'#f1c40f','rgba(243,156,18,.12)','bi-arrow-left-right',         '#f1c40f','linear-gradient(135deg,#9a7d0a,#f1c40f)'],
  ['High Risk',   $high_risk,  '#ff6b6b','rgba(231,76,60,.12)', 'bi-exclamation-triangle-fill','#ff6b6b','linear-gradient(135deg,#c0392b,#ff6b6b)'],
];
?>
<div class="kpi-grid mb-4">
  <?php foreach ($kpis as $i => $k): ?>
  <div class="kpi"
       style="--kpi-color:<?php echo $k[2]; ?>;--kpi-icon-bg:<?php echo $k[3]; ?>;--kpi-glow:radial-gradient(ellipse at top left,<?php echo $k[3]; ?>,transparent 70%);--kpi-bar:<?php echo $k[6]; ?>;">
    <div class="kpi-top-bar"></div>
    <div class="kpi-icon-wrap"><i class="bi <?php echo $k[4]; ?>"></i></div>
    <div class="kpi-val" data-target="<?php echo $k[1]; ?>">0</div>
    <div class="kpi-lbl"><?php echo $k[0]; ?></div>
    <!-- micro sparkline SVG -->
    <svg class="kpi-sparkline" viewBox="0 0 60 36" fill="none">
      <polyline points="0,28 10,22 20,26 30,14 40,18 50,8 60,12"
                stroke="<?php echo $k[2]; ?>" stroke-width="2"
                fill="none" stroke-linejoin="round" stroke-linecap="round"/>
    </svg>
  </div>
  <?php endforeach; ?>
</div>

<!-- ══════════════════════════════════════
     TOOLBAR
     ══════════════════════════════════════ -->
<div class="toolbar mb-3">
  <div class="row g-2 align-items-center">
    <div class="col-12 col-md-5">
      <div class="search-wrap">
        <i class="bi bi-search search-ico"></i>
        <input type="text" class="search-input" id="searchInput"
               placeholder="Search name, inmate #, national ID…" autocomplete="off">
        <button class="search-clear" id="searchClear" onclick="clearSearch()">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
    </div>
    <div class="col-6 col-sm-3 col-md-2">
      <select class="fsel" id="filterStatus">
        <option value="">All Statuses</option>
        <option value="REMAND">Remand</option>
        <option value="CONVICTED">Convicted</option>
        <option value="RELEASED">Released</option>
        <option value="TRANSFERRED">Transferred</option>
        <option value="DECEASED">Deceased</option>
      </select>
    </div>
    <div class="col-6 col-sm-3 col-md-2">
      <select class="fsel" id="filterRisk">
        <option value="">All Risk Levels</option>
        <option value="LOW">Low</option>
        <option value="MEDIUM">Medium</option>
        <option value="HIGH">High</option>
        <option value="MAXIMUM">Maximum</option>
      </select>
    </div>
    <div class="col-6 col-sm-3 col-md-2">
      <select class="fsel" id="filterGender">
        <option value="">All Genders</option>
        <option value="MALE">Male</option>
        <option value="FEMALE">Female</option>
      </select>
    </div>
    <div class="col-6 col-sm-3 col-md-1 d-flex gap-1">
      <button class="row-btn" id="btnReset" style="height:38px;flex:1;border-radius:9px;" title="Reset filters">
        <i class="bi bi-funnel"></i>
      </button>
    </div>
  </div>
  <!-- Active filter chips -->
  <div class="chip-strip" id="chipStrip"></div>
</div>

<!-- ══════════════════════════════════════
     TABLE WRAPPER
     ══════════════════════════════════════ -->
<div class="tbl-wrap" id="mainWrap">
  <!-- Table header bar -->
  <div class="tbl-header">
    <span class="tbl-title">
      <i class="bi bi-table me-2" style="color:#388bfd;"></i>Inmate Records
    </span>
    <div class="d-flex align-items-center gap-3">
      <span class="pager-info" id="rangeLabel"></span>
      <select class="fsel" id="perPageSel" style="width:auto;">
        <option value="15">15 / page</option>
        <option value="25" selected>25 / page</option>
        <option value="50">50 / page</option>
        <option value="100">100 / page</option>
      </select>
    </div>
  </div>

  <!-- ── Loading skeleton ── -->
  <div id="loadingState" style="padding:0 0 4px;">
    <?php for($sk=0;$sk<8;$sk++): ?>
    <div style="display:flex;align-items:center;gap:14px;padding:13px 16px;border-bottom:1px solid #21262d;">
      <span class="skel" style="width:36px;height:36px;border-radius:50%;flex-shrink:0;"></span>
      <div style="flex:1;">
        <span class="skel" style="width:<?php echo 100+($sk%4)*30; ?>px;height:13px;display:block;margin-bottom:5px;"></span>
        <span class="skel" style="width:<?php echo 60+($sk%3)*20; ?>px;height:10px;display:block;"></span>
      </div>
      <span class="skel" style="width:60px;height:22px;"></span>
      <span class="skel" style="width:70px;height:22px;"></span>
      <span class="skel" style="width:80px;height:22px;"></span>
    </div>
    <?php endfor; ?>
  </div>

  <!-- ── Empty state ── -->
  <div id="emptyState" class="state-box" style="display:none;">
    <div class="state-icon" style="color:#388bfd;"><i class="bi bi-person-x"></i></div>
    <p class="fw-semibold" style="color:var(--txt); margin-bottom:6px; font-size:.95rem;">No inmates found</p>
    <p>Try adjusting your search or filters</p>
    <button onclick="resetAll()" class="btn btn-outline-primary btn-sm mt-3" style="border-radius:8px;">
      <i class="bi bi-arrow-counterclockwise me-1"></i> Clear filters
    </button>
  </div>

  <!-- ── TABLE view ── -->
  <div id="tableContainer" style="display:none; overflow-x:auto; -webkit-overflow-scrolling:touch;">
    <table id="inmatesTable">
      <thead>
        <tr>
          <th class="sortable" data-field="inmate_id">Inmate # <span class="si"></span></th>
          <th class="sortable" data-field="first_name">Name <span class="si"></span></th>
          <th>Gender</th>
          <th class="sortable" data-field="admission_date">Admitted <span class="si"></span></th>
          <th>Sentence</th>
          <th class="sortable" data-field="status">Status <span class="si"></span></th>
          <th class="sortable" data-field="risk_classification">Risk <span class="si"></span></th>
          <th style="text-align:right; padding-right:18px;">Actions</th>
        </tr>
      </thead>
      <tbody id="inmatesBody"></tbody>
    </table>
  </div>

  <!-- ── CARD view ── -->
  <div id="cardsContainer" style="display:none;">
    <div class="card-grid" id="cardsBody"></div>
  </div>

  <!-- ── Pagination ── -->
  <div id="pagerBar" style="display:none; padding:13px 18px; border-top:1px solid #21262d;">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
      <span class="pager-info" id="pageInfo"></span>
      <div class="pager" id="pager"></div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════
     JAVASCRIPT
     ══════════════════════════════════════ -->
<script>
const API_BASE = '<?php echo APP_URL; ?>/api/inmates';
const IS_SUPER_ADMIN = <?php echo isSuperAdmin() ? 'true' : 'false'; ?>;
const FACILITY = <?php echo isSuperAdmin() ? 'null' : json_encode((string)$fid); ?>;
const CAN_EDIT = <?php echo hasPermission('edit', 'inmates') ? 'true' : 'false'; ?>;
const CAN_XFER = <?php echo hasPermission('view', 'transfers') ? 'true' : 'false'; ?>;
const APP_BASE = '<?php echo APP_URL; ?>';

/* ── State ── */
let state = {
  page:1, perPage:25, search:'', status:'', risk:'', gender:'',
  sortField:'admission_date', sortDir:'desc', loading:false, view:'table',
};

/* ── KPI counter animation ── */
document.querySelectorAll('.kpi-val').forEach(el => {
  const target = parseInt(el.dataset.target, 10);
  let start = null;
  const dur = 900;
  function step(ts) {
    if (!start) start = ts;
    const prog = Math.min((ts - start) / dur, 1);
    const ease = 1 - Math.pow(1 - prog, 3);
    el.textContent = Math.round(ease * target).toLocaleString();
    if (prog < 1) requestAnimationFrame(step);
  }
  setTimeout(() => requestAnimationFrame(step), 80);
});

/* ── View toggle ── */
let currentView = 'table';
function setView(v) {
  currentView = v;
  document.getElementById('btnTable').classList.toggle('active', v === 'table');
  document.getElementById('btnCards').classList.toggle('active', v === 'cards');
  const tc = document.getElementById('tableContainer');
  const cc = document.getElementById('cardsContainer');
  if (v === 'table') { tc.style.display = 'block'; cc.style.display = 'none'; }
  else               { tc.style.display = 'none';  cc.style.display = 'block'; }
}

/* ── Colour helpers ── */
const AVT = ['#1f6feb','#9b59b6','#e74c3c','#27ae60','#f39c12','#16a085','#2980b9','#8e44ad'];
function avatarColor(s) {
  let h = 0;
  for (let i = 0; i < s.length; i++) h = s.charCodeAt(i) + ((h << 5) - h);
  return AVT[Math.abs(h) % AVT.length];
}
function initials(f, l) { return ((f?.[0] ?? '') + (l?.[0] ?? '')).toUpperCase(); }

const RISK_RING = { LOW:'#2ecc71', MEDIUM:'#f1c40f', HIGH:'#e67e22', MAXIMUM:'#ff6b6b' };
const RISK_PCT  = { LOW:25,        MEDIUM:50,         HIGH:75,        MAXIMUM:100 };

/* ── Badges ── */
const STATUS_MAP = {
  REMAND     :['st-remand',     '<i class="bi bi-hourglass-split"></i> Remand'],
  CONVICTED  :['st-convicted',  '<i class="bi bi-person-lock"></i> Convicted'],
  RELEASED   :['st-released',   '<i class="bi bi-person-check-fill"></i> Released'],
  TRANSFERRED:['st-transferred','<i class="bi bi-arrow-left-right"></i> Transferred'],
  DECEASED   :['st-deceased',   '<i class="bi bi-dash-circle"></i> Deceased'],
};
const RISK_MAP = {
  LOW    :['rk-low',    'Low'],
  MEDIUM :['rk-medium', 'Medium'],
  HIGH   :['rk-high',   'High'],
  MAXIMUM:['rk-maximum','Maximum'],
};
function statusBadge(s) {
  const [cls, lbl] = STATUS_MAP[s] ?? ['st-remand', s ?? '—'];
  return `<span class="bp ${cls}">${lbl}</span>`;
}
function riskBadge(r) {
  const [cls, lbl] = RISK_MAP[r] ?? ['rk-medium', r ?? '—'];
  const clr  = RISK_RING[r] ?? '#8b949e';
  const pct  = RISK_PCT[r] ?? 50;
  return `<div class="risk-bar-wrap">
    <span class="bp ${cls}">${lbl}</span>
    <div class="risk-bar"><div class="risk-bar-fill" style="width:${pct}%;background:${clr};"></div></div>
  </div>`;
}

/* ── Gender ── */
function genderCell(g) {
  const clr = g === 'MALE' ? '#5dade2' : g === 'FEMALE' ? '#f48fb1' : '#90caf9';
  const lbl = g ? (g[0] + g.slice(1).toLowerCase()) : '—';
  return `<span style="display:inline-flex;align-items:center;">
    <span class="gdot" style="background:${clr};"></span>${lbl}</span>`;
}

/* ── Date helpers ── */
function fmtDate(d) {
  if (!d) return '—';
  return new Date(d).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'});
}
function relTime(d) {
  if (!d) return '';
  const diff = Date.now() - new Date(d).getTime();
  const days = Math.floor(diff / 86400000);
  if (days < 1)   return 'today';
  if (days < 30)  return `${days}d ago`;
  if (days < 365) return `${Math.floor(days/30)}mo ago`;
  return `${Math.floor(days/365)}y ago`;
}
function fmtSentence(m) {
  if (!m) return '<span style="color:#8b949e;">—</span>';
  if (m < 12) return `<span style="color:var(--txt);">${m}<small style="color:var(--mut);"> mo</small></span>`;
  const y = Math.floor(m/12), r = m%12;
  return `<span style="color:var(--txt);">${y}<small style="color:var(--mut);"> yr</small>${r?` ${r}<small style="color:var(--mut);"> mo</small>`:''}</span>`;
}

/* ── Active filter chips ── */
const CHIP_LABELS = {
  status:{ REMAND:'Remand', CONVICTED:'Convicted', RELEASED:'Released', TRANSFERRED:'Transferred', DECEASED:'Deceased' },
  risk  :{ LOW:'Low Risk', MEDIUM:'Med Risk', HIGH:'High Risk', MAXIMUM:'Maximum Risk' },
  gender:{ MALE:'Male', FEMALE:'Female', OTHER:'Other' },
};
function renderChips() {
  const strip = document.getElementById('chipStrip');
  strip.innerHTML = '';
  const add = (key, val, label) => {
    const c = document.createElement('span');
    c.className = 'filter-chip';
    c.innerHTML = `${label} <i class="bi bi-x fc-x"></i>`;
    c.title = `Remove filter`;
    c.onclick = () => {
      document.getElementById('filter'+key[0].toUpperCase()+key.slice(1)).value = '';
      state[key] = ''; state.page = 1; loadInmates(); renderChips();
    };
    strip.appendChild(c);
  };
  if (state.status) add('status', state.status, CHIP_LABELS.status[state.status] ?? state.status);
  if (state.risk)   add('risk',   state.risk,   CHIP_LABELS.risk[state.risk] ?? state.risk);
  if (state.gender) add('gender', state.gender, CHIP_LABELS.gender[state.gender] ?? state.gender);
  if (state.search) {
    const c = document.createElement('span');
    c.className = 'filter-chip';
    c.innerHTML = `"${state.search.length>14 ? state.search.slice(0,14)+'…' : state.search}" <i class="bi bi-x fc-x"></i>`;
    c.onclick = clearSearch;
    strip.appendChild(c);
  }
  // Colour active selects
  ['filterStatus','filterRisk','filterGender'].forEach(id => {
    document.getElementById(id).classList.toggle('has-val', !!document.getElementById(id).value);
  });
}

function clearSearch() {
  document.getElementById('searchInput').value = '';
  document.getElementById('searchClear').style.display = 'none';
  state.search = ''; state.page = 1; loadInmates(); renderChips();
}
function resetAll() {
  ['searchInput','filterStatus','filterRisk','filterGender'].forEach(id => {
    document.getElementById(id).value = '';
  });
  document.getElementById('searchClear').style.display = 'none';
  Object.assign(state, {search:'',status:'',risk:'',gender:'',page:1});
  renderChips(); loadInmates();
}

/* ── Main fetch ── */
async function loadInmates() {
  if (state.loading) return;
  state.loading = true;
  const ld = document.getElementById('loadingState');
  const em = document.getElementById('emptyState');
  const tc = document.getElementById('tableContainer');
  const cc = document.getElementById('cardsContainer');
  const pb = document.getElementById('pagerBar');

  ld.style.display = 'block';
  em.style.display = 'none';
  tc.style.display = 'none';
  cc.style.display = 'none';
  pb.style.display = 'none';

  const params = new URLSearchParams({ page:state.page, per_page:state.perPage });
  if (FACILITY) params.set('facility_id', FACILITY);
  if (state.status) params.set('status', state.status);

  try {
    const resp = await fetch(`${API_BASE}?${params}`);
    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
    const json = await resp.json();
    if (!json.success) throw new Error(json.message || 'API error');

    let inmates = json.data?.inmates ?? [];
    const pag   = json.data?.pagination ?? {};

    // Client filters
    const q = state.search.trim().toLowerCase();
    if (q) inmates = inmates.filter(i =>
      (`${i.first_name} ${i.last_name}`).toLowerCase().includes(q) ||
      (i.inmate_id ?? '').toLowerCase().includes(q) ||
      (i.national_id ?? '').toLowerCase().includes(q)
    );
    if (state.gender) inmates = inmates.filter(i => i.gender === state.gender);
    if (state.risk)   inmates = inmates.filter(i => i.risk_classification === state.risk);

    // Sort
    inmates.sort((a,b) => {
      const va = a[state.sortField] ?? '', vb = b[state.sortField] ?? '';
      return (va < vb ? -1 : va > vb ? 1 : 0) * (state.sortDir === 'asc' ? 1 : -1);
    });

    const total = pag.total ?? inmates.length;
    document.getElementById('totalBadge').textContent = total.toLocaleString();

    if (inmates.length === 0) {
      ld.style.display = 'none';
      em.style.display = 'block';
      state.loading = false;
      return;
    }

    renderRows(inmates);
    renderCards(inmates);
    renderPager(pag, inmates.length);

    const from = (state.page-1)*state.perPage+1;
    const to   = Math.min(state.page*state.perPage, total);
    document.getElementById('rangeLabel').textContent = `${from}–${to} of ${total.toLocaleString()}`;

    ld.style.display = 'none';
    setView(currentView);  // show correct view

  } catch (err) {
    console.error(err);
    ld.innerHTML = `<div class="state-box">
      <div class="state-icon" style="color:#e74c3c;"><i class="bi bi-exclamation-triangle"></i></div>
      <p class="fw-semibold" style="color:var(--txt);">Failed to load</p>
      <p>${err.message}</p>
      <button onclick="loadInmates()" class="btn btn-outline-primary btn-sm mt-3" style="border-radius:8px;">
        <i class="bi bi-arrow-clockwise me-1"></i> Retry
      </button></div>`;
  } finally {
    state.loading = false;
  }
}

/* ── Render table rows ── */
function renderRows(list) {
  const tbody = document.getElementById('inmatesBody');
  tbody.innerHTML = '';
  list.forEach(inm => {
    const bg   = avatarColor((inm.first_name ?? '') + (inm.last_name ?? ''));
    const init = initials(inm.first_name, inm.last_name) || '?';
    const name = `${inm.first_name ?? ''} ${inm.last_name ?? ''}`.trim() || '—';
    const sub  = inm.national_id ? `NID: ${inm.national_id}` : fmtDate(inm.date_of_birth);
    const ring = RISK_RING[inm.risk_classification] ?? 'transparent';
    const isMax = inm.risk_classification === 'MAXIMUM';

    const actions = `<div class="row-actions">
      <a href="${APP_BASE}/modules/inmates/view.php?id=${inm.id}" class="row-btn" data-tip="View profile">
        <i class="bi bi-eye"></i></a>
      ${CAN_EDIT ? `<a href="${APP_BASE}/modules/inmates/edit.php?id=${inm.id}" class="row-btn" data-tip="Edit">
        <i class="bi bi-pencil"></i></a>` : ''}
      ${CAN_XFER ? `<a href="${APP_BASE}/modules/inmates/transfer.php?id=${inm.id}" class="row-btn xfer" data-tip="Transfer">
        <i class="bi bi-arrow-left-right"></i></a>` : ''}
    </div>`;

    const tr = document.createElement('tr');
    if (isMax) tr.classList.add('risk-max');

    tr.innerHTML = `
      <td>
        <span style="font-family:monospace;font-size:.79rem;color:#8b949e;
                     background:var(--bg);padding:2px 8px;border-radius:6px;
                     border:1px solid var(--bdr);">${inm.inmate_id ?? '—'}</span>
      </td>
      <td>
        <div class="name-cell">
          <div class="avatar" style="background:${bg};">
            ${init}
            <div class="avatar-ring" style="border-color:${ring}; border-width:${isMax?'2.5px':'2px'};"></div>
          </div>
          <div>
            <div class="name-txt">${name}</div>
            <div class="name-sub">${sub}</div>
          </div>
        </div>
      </td>
      <td>${genderCell(inm.gender)}</td>
      <td>
        <div style="color:var(--txt);font-size:.82rem;">${fmtDate(inm.admission_date)}</div>
        <div style="font-size:.7rem;color:var(--mut);">${relTime(inm.admission_date)}</div>
      </td>
      <td>${fmtSentence(inm.sentence_length_months)}</td>
      <td>${statusBadge(inm.status)}</td>
      <td>${riskBadge(inm.risk_classification)}</td>
      <td>${actions}</td>`;
    tbody.appendChild(tr);
  });
}

/* ── Render cards ── */
function renderCards(list) {
  const grid = document.getElementById('cardsBody');
  grid.innerHTML = '';
  list.forEach(inm => {
    const bg   = avatarColor((inm.first_name ?? '') + (inm.last_name ?? ''));
    const init = initials(inm.first_name, inm.last_name) || '?';
    const name = `${inm.first_name ?? ''} ${inm.last_name ?? ''}`.trim() || '—';
    const ring = RISK_RING[inm.risk_classification] ?? '#21262d';
    const [sCls] = STATUS_MAP[inm.status] ?? ['st-remand'];
    const [rCls] = RISK_MAP[inm.risk_classification] ?? ['rk-medium'];

    const d = document.createElement('div');
    d.className = 'inmate-card';
    d.onclick = () => window.location.href = `${APP_BASE}/modules/inmates/view.php?id=${inm.id}`;
    d.innerHTML = `
      <div class="card-risk-stripe" style="background:${ring};opacity:.7;"></div>
      <div class="card-avatar" style="background:${bg};">
        ${init}
        <div style="position:absolute;inset:-3px;border-radius:50%;border:2px solid ${ring};"></div>
      </div>
      <div class="card-name">${name}</div>
      <div class="card-id">${inm.inmate_id ?? '—'}</div>
      <div class="card-meta">
        <span class="bp ${sCls}" style="font-size:.65rem;">${(STATUS_MAP[inm.status]?.[1] ?? inm.status ?? '—').replace(/<[^>]+>/g,'')}</span>
        <span class="bp ${rCls}" style="font-size:.65rem;">${RISK_MAP[inm.risk_classification]?.[1] ?? '—'}</span>
      </div>
      <div class="card-footer">
        <div style="font-size:.73rem;color:var(--mut);">
          <i class="bi bi-calendar3 me-1"></i>${fmtDate(inm.admission_date)}
        </div>
        <div style="font-size:.73rem;color:var(--mut);">
          ${genderCell(inm.gender)}
        </div>
      </div>`;
    grid.appendChild(d);
  });
}

/* ── Render pager ── */
function renderPager(pag, count) {
  const total = pag.total ?? count;
  const pages = pag.last_page ?? (Math.ceil(total / state.perPage) || 1);
  const cur   = state.page;
  document.getElementById('pageInfo').textContent =
    `Page ${cur} of ${pages}  ·  ${total.toLocaleString()} total`;
  const pager = document.getElementById('pager');
  pager.innerHTML = '';
  const mk = (html, page, dis, act) => {
    const b = document.createElement('button');
    b.className = 'pager-btn' + (act ? ' active' : '');
    b.innerHTML = html; b.disabled = dis;
    if (!dis && !act) b.onclick = () => { state.page = page; loadInmates(); };
    return b;
  };
  pager.appendChild(mk('<i class="bi bi-chevron-double-left"></i>', 1,     cur===1,     false));
  pager.appendChild(mk('<i class="bi bi-chevron-left"></i>',        cur-1, cur===1,     false));
  const s = Math.max(1,cur-2), e = Math.min(pages,cur+2);
  if (s>1) pager.appendChild(mk('…',null,true,false));
  for(let p=s;p<=e;p++) pager.appendChild(mk(p, p, false, p===cur));
  if (e<pages) pager.appendChild(mk('…',null,true,false));
  pager.appendChild(mk('<i class="bi bi-chevron-right"></i>',        cur+1, cur===pages, false));
  pager.appendChild(mk('<i class="bi bi-chevron-double-right"></i>', pages, cur===pages, false));
  document.getElementById('pagerBar').style.display = pages > 1 ? 'block' : 'none';
}

/* ── Sort headers ── */
document.querySelectorAll('#inmatesTable thead th.sortable').forEach(th => {
  th.addEventListener('click', () => {
    const f = th.dataset.field;
    state.sortDir = (state.sortField === f && state.sortDir === 'asc') ? 'desc' : 'asc';
    state.sortField = f;
    document.querySelectorAll('.sortable').forEach(h => h.classList.remove('asc','desc'));
    th.classList.add(state.sortDir);
    state.page = 1; loadInmates();
  });
});

/* ── Event listeners ── */
let searchTimer;
document.getElementById('searchInput').addEventListener('input', e => {
  clearTimeout(searchTimer);
  document.getElementById('searchClear').style.display = e.target.value ? 'block' : 'none';
  searchTimer = setTimeout(() => {
    state.search = e.target.value; state.page = 1; loadInmates(); renderChips();
  }, 280);
});
['filterStatus','filterRisk','filterGender'].forEach(id => {
  document.getElementById(id).addEventListener('change', e => {
    const key = {filterStatus:'status',filterRisk:'risk',filterGender:'gender'}[id];
    state[key] = e.target.value; state.page = 1; loadInmates(); renderChips();
  });
});
document.getElementById('perPageSel').addEventListener('change', e => {
  state.perPage = parseInt(e.target.value); state.page = 1; loadInmates();
});
document.getElementById('btnReset').addEventListener('click', resetAll);

/* ── Boot ── */
loadInmates();
</script>
<?php require_once '../../includes/footer.php'; ?>
