<?php
$pageTitle = 'Reports & Analytics';
require_once '../../includes/header.php';
requireAuth();
verifyPermission('view','reports');

$fid  = getCurrentFacility();
$isSA = isSuperAdmin();

$facCl    = $isSA ? "deleted_at IS NULL" : "deleted_at IS NULL AND facility_id=$fid";
$facClI   = $isSA ? "i.deleted_at IS NULL"   : "i.deleted_at IS NULL AND i.facility_id=$fid";
$facClInc = $isSA ? "inc.deleted_at IS NULL" : "inc.deleted_at IS NULL AND inc.facility_id=$fid";
$facClMr  = $isSA ? "mr.deleted_at IS NULL"  : "mr.deleted_at IS NULL AND mr.facility_id=$fid";
$facClIa  = $isSA ? "ia.deleted_at IS NULL"  : "ia.deleted_at IS NULL AND ia.facility_id=$fid";
$facClRp  = $isSA ? "rp.deleted_at IS NULL"  : "rp.deleted_at IS NULL AND rp.facility_id=$fid";

/* ══ KPI ══ */
$totalInmates  = fetchOne("SELECT COUNT(*) cnt FROM inmates WHERE $facCl AND status IN ('REMAND','CONVICTED')", [], '')['cnt'] ?? 0;
$totalAll      = fetchOne("SELECT COUNT(*) cnt FROM inmates WHERE $facCl", [], '')['cnt'] ?? 0;
$totalStaff    = fetchOne("SELECT COUNT(*) cnt FROM staff WHERE $facCl AND employment_status='ACTIVE'", [], '')['cnt'] ?? 0;
$incidentsMo   = fetchOne("SELECT COUNT(*) cnt FROM incidents WHERE $facCl AND MONTH(incident_date)=MONTH(NOW()) AND YEAR(incident_date)=YEAR(NOW())", [], '')['cnt'] ?? 0;
$incidentsTot  = fetchOne("SELECT COUNT(*) cnt FROM incidents WHERE $facCl", [], '')['cnt'] ?? 0;
$pendingTrans  = fetchOne("SELECT COUNT(*) cnt FROM inmate_transfers WHERE ".($isSA?"deleted_at IS NULL":"facility_to=$fid AND deleted_at IS NULL")." AND approval_status='PENDING'", [], '')['cnt'] ?? 0;
$medRecords    = fetchOne("SELECT COUNT(*) cnt FROM medical_records WHERE $facCl", [], '')['cnt'] ?? 0;
$totalPrograms = fetchOne("SELECT COUNT(*) cnt FROM rehabilitation_programs WHERE $facCl AND status='ACTIVE'", [], '')['cnt'] ?? 0;
$totalEnrolled = fetchOne("SELECT COUNT(DISTINCT pe.inmate_id) cnt FROM program_enrollment pe JOIN rehabilitation_programs rp ON pe.program_id=rp.id WHERE pe.deleted_at IS NULL AND pe.status IN ('ENROLLED','ACTIVE') AND $facClRp", [], '')['cnt'] ?? 0;

/* ══ POPULATION ══ */
$inmateByStatus = fetchAll("SELECT status, COUNT(*) cnt FROM inmates WHERE $facCl GROUP BY status ORDER BY cnt DESC", [], '');
$inmateByRisk   = fetchAll("SELECT risk_classification, COUNT(*) cnt FROM inmates WHERE $facCl GROUP BY risk_classification ORDER BY cnt DESC", [], '');
$inmateByGender = fetchAll("SELECT gender, COUNT(*) cnt FROM inmates WHERE $facCl GROUP BY gender ORDER BY cnt DESC", [], '');
$inmateByRelig  = fetchAll("SELECT COALESCE(NULLIF(religion,''),'Unknown') religion, COUNT(*) cnt FROM inmates WHERE $facCl GROUP BY religion ORDER BY cnt DESC LIMIT 8", [], '');
$admTrend       = fetchAll("SELECT DATE_FORMAT(admission_date,'%b %Y') lbl, COUNT(*) cnt FROM inmates WHERE $facCl GROUP BY DATE_FORMAT(admission_date,'%Y-%m') ORDER BY MIN(admission_date) LIMIT 12", [], '');
$inmateRoster   = fetchAll(
    "SELECT i.inmate_id, i.first_name, i.last_name, i.status, i.risk_classification, i.gender,
            i.admission_date, i.release_date, i.sentence_length_months, f.name facility_name
     FROM inmates i JOIN facilities f ON i.facility_id=f.id
     WHERE $facClI ORDER BY i.last_name, i.first_name LIMIT 500",
    [], ''
);

/* ══ INCIDENTS ══ */
$incidentsBySev    = fetchAll("SELECT severity, COUNT(*) cnt FROM incidents WHERE $facCl GROUP BY severity ORDER BY FIELD(severity,'CRITICAL','HIGH','MEDIUM','LOW')", [], '');
$incidentsByStatus = fetchAll("SELECT status, COUNT(*) cnt FROM incidents WHERE $facCl GROUP BY status ORDER BY cnt DESC", [], '');
$criticalCount     = fetchOne("SELECT COUNT(*) cnt FROM incidents WHERE $facCl AND severity IN ('CRITICAL','HIGH')", [], '')['cnt'] ?? 0;
$openCount         = fetchOne("SELECT COUNT(*) cnt FROM incidents WHERE $facCl AND status='OPEN'", [], '')['cnt'] ?? 0;
$recentIncidents   = fetchAll(
    "SELECT inc.id, inc.incident_date, inc.severity, inc.status, inc.description, inc.location,
            i.first_name, i.last_name, i.inmate_id
     FROM incidents inc
     LEFT JOIN inmates i ON inc.inmate_id=i.id
     WHERE $facClInc
     ORDER BY inc.incident_date DESC LIMIT 100",
    [], ''
);

/* ══ STAFF ══ */
$staffByType   = fetchAll("SELECT staff_type, COUNT(*) cnt FROM staff WHERE $facCl GROUP BY staff_type ORDER BY cnt DESC", [], '');
$staffByStatus = fetchAll("SELECT employment_status, COUNT(*) cnt FROM staff WHERE $facCl GROUP BY employment_status ORDER BY cnt DESC", [], '');
$staffByGender = fetchAll("SELECT gender, COUNT(*) cnt FROM staff WHERE $facCl GROUP BY gender ORDER BY cnt DESC", [], '');
$staffRoster   = fetchAll(
    "SELECT s.staff_id_number, u.username, s.staff_type, s.position, s.employment_status, s.hire_date, s.gender, f.name facility_name
     FROM staff s JOIN facilities f ON s.facility_id=f.id JOIN users u ON s.user_id=u.id
     WHERE s.deleted_at IS NULL ".($isSA?'':'AND s.facility_id='.$fid)."
     ORDER BY s.employment_status='ACTIVE' DESC, u.username LIMIT 500",
    [], ''
);

/* ══ MEDICAL ══ */
$medByStatus   = fetchAll("SELECT COALESCE(NULLIF(status,''),'UNKNOWN') status, COUNT(*) cnt FROM medical_records WHERE $facCl GROUP BY status ORDER BY cnt DESC", [], '');
$activeConditions = fetchOne("SELECT COUNT(*) cnt FROM medical_records WHERE $facCl AND status='ACTIVE'", [], '')['cnt'] ?? 0;
$recentMed     = fetchAll(
    "SELECT mr.id, mr.record_date, mr.diagnosis, mr.treatment_plan, mr.status, mr.examined_by,
            i.first_name, i.last_name, i.inmate_id
     FROM medical_records mr
     LEFT JOIN inmates i ON mr.inmate_id=i.id
     WHERE $facClMr
     ORDER BY mr.record_date DESC LIMIT 100",
    [], ''
);

/* ══ FINANCE ══ */
$finByStatus   = fetchAll("SELECT account_status, COUNT(*) cnt, COALESCE(SUM(account_balance),0) total FROM inmate_accounts WHERE $facCl GROUP BY account_status ORDER BY cnt DESC", [], '');
$totalBalance  = (float)array_sum(array_column($finByStatus,'total'));
$txSummary     = fetchAll("SELECT transaction_type, COUNT(*) cnt, COALESCE(SUM(amount),0) total FROM inmate_transactions WHERE deleted_at IS NULL GROUP BY transaction_type ORDER BY total DESC", [], '');
$finTopAccts   = fetchAll(
    "SELECT ia.id, i.first_name, i.last_name, i.inmate_id, i.status inmate_status, ia.account_status, ia.account_balance, ia.updated_at
     FROM inmate_accounts ia JOIN inmates i ON ia.inmate_id=i.id
     WHERE $facClIa
     ORDER BY ia.account_balance DESC LIMIT 100",
    [], ''
);

/* ══ PROGRAMS ══ */
$progByType    = fetchAll("SELECT program_type, COUNT(*) cnt FROM rehabilitation_programs WHERE $facCl GROUP BY program_type ORDER BY cnt DESC", [], '');
$progByStatus  = fetchAll("SELECT status, COUNT(*) cnt FROM rehabilitation_programs WHERE $facCl GROUP BY status ORDER BY cnt DESC", [], '');
$completionRate = 0;
$totalProgsAll  = fetchOne("SELECT COUNT(*) cnt FROM rehabilitation_programs WHERE $facCl", [], '')['cnt'] ?? 0;
$completedProgs = fetchOne("SELECT COUNT(*) cnt FROM rehabilitation_programs WHERE $facCl AND status='COMPLETED'", [], '')['cnt'] ?? 0;
if ($totalProgsAll > 0) $completionRate = round($completedProgs / $totalProgsAll * 100);
$programsList  = fetchAll(
    "SELECT rp.name, rp.program_type, rp.status, rp.start_date, rp.end_date, rp.capacity, rp.instructor_id,
            COUNT(pe.id) enrolled
     FROM rehabilitation_programs rp
     LEFT JOIN program_enrollment pe ON rp.id=pe.program_id AND pe.status IN ('ENROLLED','ACTIVE') AND pe.deleted_at IS NULL
     WHERE $facClRp
     GROUP BY rp.id ORDER BY rp.status='ACTIVE' DESC, rp.start_date DESC LIMIT 200",
    [], ''
);

/* ══ CONFIG ══ */
$activeTab = $_GET['tab'] ?? 'population';
$validTabs = ['population','incidents','staff','medical','finance','programs'];
if (!in_array($activeTab,$validTabs)) $activeTab = 'population';

$sevCfg = ['CRITICAL'=>'#f85149','HIGH'=>'#f39c12','MEDIUM'=>'#e3b341','LOW'=>'#3fb950'];
$statusColors = [
    'ACTIVE'=>'#3fb950','INACTIVE'=>'#8b949e','COMPLETED'=>'#388bfd',
    'OPEN'=>'#f39c12','CLOSED'=>'#3fb950','INVESTIGATING'=>'#bb8fce',
    'CONVICTED'=>'#388bfd','REMAND'=>'#e3b341','RELEASED'=>'#3fb950','TRANSFERRED'=>'#8b949e','DECEASED'=>'#f85149',
    'PENDING'=>'#e3b341','APPROVED'=>'#3fb950','REJECTED'=>'#f85149','SUSPENDED'=>'#f39c12',
    'RESOLVED'=>'#3fb950','MONITORING'=>'#e3b341','CHRONIC'=>'#bb8fce','UNKNOWN'=>'#8b949e',
];
$typeCfg = [
    'EDUCATION'       => ['#388bfd','bi-book-fill'],
    'VOCATIONAL'      => ['#f39c12','bi-tools'],
    'COUNSELING'      => ['#bb8fce','bi-chat-heart-fill'],
    'SKILLS_TRAINING' => ['#3fb950','bi-lightning-fill'],
    'SPORTS'          => ['#f85149','bi-trophy-fill'],
];
$genderColors = ['MALE'=>'#388bfd','FEMALE'=>'#bb8fce','OTHER'=>'#8b949e'];
$riskColors   = ['LOW'=>'#3fb950','MEDIUM'=>'#e3b341','HIGH'=>'#f39c12','MAXIMUM'=>'#f85149'];

function chip($val,$map,$def='#8b949e'){
    $c=$map[$val]??$def;
    preg_match('/^#([0-9a-f]{6})$/i',$c,$m);
    $bg=$m?'rgba('.hexdec(substr($m[1],0,2)).','.hexdec(substr($m[1],2,2)).','.hexdec(substr($m[1],4,2)).',0.15)':'rgba(139,148,158,.15)';
    return "<span style='display:inline-flex;align-items:center;padding:2px 9px;border-radius:20px;font-size:.67rem;font-weight:700;color:$c;background:$bg'>".htmlspecialchars($val)."</span>";
}

/* SVG donut helper — returns raw SVG string */
function svgDonut(array $slices, int $size=120, int $thickness=22): string {
    $total = array_sum(array_column($slices,'v'));
    if (!$total) return "<svg width='$size' height='$size'><circle cx='".($size/2)."' cy='".($size/2)."' r='".($size/2-$thickness/2)."' fill='none' stroke='#21262d' stroke-width='$thickness'/></svg>";
    $r   = ($size/2) - ($thickness/2);
    $cx  = $cy = $size/2;
    $circ= 2*M_PI*$r;
    $off = 0;
    $arcs= '';
    foreach ($slices as $s) {
        $dash = ($s['v']/$total)*$circ;
        $gap  = $circ - $dash;
        $arcs .= "<circle cx='$cx' cy='$cy' r='$r' fill='none'
            stroke='{$s['c']}' stroke-width='$thickness'
            stroke-dasharray='$dash $gap'
            stroke-dashoffset='-$off'
            transform='rotate(-90 $cx $cy)'/>";
        $off += $dash;
    }
    return "<svg width='$size' height='$size' viewBox='0 0 $size $size'>$arcs</svg>";
}

/* Bar chart (vertical) — pure SVG */
function svgBar(array $rows, int $w=320, int $h=100, string $color='#388bfd'): string {
    if (!$rows) return '';
    $max = max(array_column($rows,'v'));
    if (!$max) return '';
    $n   = count($rows);
    $bw  = max(8, floor(($w - ($n+1)*4) / $n));
    $out = "<svg width='100%' viewBox='0 0 $w $h' style='overflow:visible'>";
    foreach ($rows as $i => $row) {
        $bh  = round(($row['v']/$max)*($h-18));
        $x   = 4 + $i*($bw+4);
        $y   = $h - 18 - $bh;
        $lbl = htmlspecialchars(mb_strimwidth($row['l'],0,6,'..'));
        $out .= "<rect x='$x' y='$y' width='$bw' height='$bh' rx='3' fill='$color' opacity='.75'>
            <title>{$row['l']}: {$row['v']}</title></rect>";
        $out .= "<text x='".($x+$bw/2)."' y='".($h-2)."' font-size='7' text-anchor='middle' fill='#8b949e'>$lbl</text>";
    }
    $out .= '</svg>';
    return $out;
}
?>
<style>
:root{--sur:#161b22;--bdr:#21262d;--bg:#0d1117;--txt:#e6edf3;--mut:#8b949e}
*{box-sizing:border-box}
.ph{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:.75rem}
.ph h1{font-size:1.35rem;font-weight:700;color:var(--txt);margin:0}
.bc{font-size:.78rem;color:#6e7681;display:flex;align-items:center;gap:.3rem;margin-bottom:.3rem}
.bc a{color:#388bfd;text-decoration:none}.bc a:hover{text-decoration:underline}
.ph-act{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap}
.btn-ghost{display:inline-flex;align-items:center;gap:.4rem;padding:.4rem .85rem;border-radius:9px;border:1px solid #30363d;background:transparent;color:#c9d1d9;font-size:.8rem;font-weight:600;cursor:pointer;text-decoration:none;transition:all .18s}
.btn-ghost:hover{background:#21262d;border-color:#388bfd;color:#388bfd}
.btn-xs{display:inline-flex;align-items:center;gap:.3rem;padding:.28rem .65rem;border-radius:7px;border:1px solid #30363d;background:transparent;color:#8b949e;font-size:.72rem;font-weight:600;cursor:pointer;transition:all .18s;white-space:nowrap}
.btn-xs:hover{background:#21262d;color:#e6edf3;border-color:#388bfd}

/* KPI row */
.kpi-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:1rem;margin-bottom:1.4rem}
@media(max-width:1100px){.kpi-grid{grid-template-columns:repeat(3,1fr)}}
@media(max-width:580px){.kpi-grid{grid-template-columns:repeat(2,1fr)}}
.kpi{background:var(--sur);border:1px solid var(--bdr);border-radius:14px;padding:1rem 1.1rem;transition:transform .18s,box-shadow .18s;cursor:pointer;position:relative;overflow:hidden}
.kpi:hover{transform:translateY(-2px);box-shadow:0 6px 24px rgba(0,0,0,.4)}
.kpi-ico{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:.9rem;margin-bottom:.6rem}
.kpi-val{font-size:1.55rem;font-weight:800;color:var(--txt);line-height:1;margin-bottom:.18rem}
.kpi-lbl{font-size:.69rem;font-weight:600;color:var(--mut);text-transform:uppercase;letter-spacing:.05em}
.kpi-sub{font-size:.7rem;color:#484f58;margin-top:.2rem}

/* Tabs */
.tab-strip{display:flex;gap:.3rem;margin-bottom:1.25rem;flex-wrap:wrap;padding:.38rem .42rem;background:var(--sur);border:1px solid var(--bdr);border-radius:12px;width:fit-content}
.tab-lnk{display:flex;align-items:center;gap:.35rem;padding:.36rem .82rem;border-radius:7px;font-size:.79rem;font-weight:600;cursor:pointer;color:var(--mut);background:transparent;border:none;text-decoration:none;transition:all .18s;white-space:nowrap}
.tab-lnk:hover,.tab-lnk.on{background:#21262d;color:var(--txt)}

/* Layout */
.rep-layout{display:grid;grid-template-columns:1fr 1fr;gap:1.2rem}
@media(max-width:860px){.rep-layout{grid-template-columns:1fr}}
.rep-full{grid-column:1/-1}
.rep-3col{display:grid;grid-template-columns:1fr 1fr 1fr;gap:1.2rem}
@media(max-width:900px){.rep-3col{grid-template-columns:1fr 1fr}}
@media(max-width:580px){.rep-3col{grid-template-columns:1fr}}

/* Section card */
.sec{background:var(--sur);border:1px solid var(--bdr);border-radius:14px;overflow:hidden}
.sec-hdr{display:flex;align-items:center;justify-content:space-between;padding:.75rem 1rem;border-bottom:1px solid #1c2128}
.sec-hdr h3{margin:0;font-size:.88rem;font-weight:700;color:var(--txt);display:flex;align-items:center;gap:.42rem}
.cnt-badge{font-size:.7rem;color:#8b949e;background:#21262d;padding:1px 8px;border-radius:20px;font-weight:600}

/* Bar rows */
.bar-row{display:flex;align-items:center;gap:.7rem;padding:.5rem 1rem;border-bottom:1px solid #0d1117}
.bar-row:last-child{border-bottom:none}
.bar-lbl{font-size:.79rem;color:#c9d1d9;min-width:115px;font-weight:500;white-space:nowrap}
.bar-track{flex:1;height:7px;background:#21262d;border-radius:4px;overflow:hidden}
.bar-fill{height:100%;border-radius:4px;transition:width .6s ease}
.bar-num{font-size:.77rem;font-weight:700;color:var(--txt);min-width:28px;text-align:right}
.bar-pct{font-size:.68rem;color:#8b949e;min-width:34px;text-align:right}

/* Donut card */
.donut-card{display:flex;align-items:center;gap:1.1rem;padding:1rem 1.1rem;flex-wrap:wrap}
.donut-legend{display:flex;flex-direction:column;gap:.38rem;flex:1;min-width:100px}
.legend-item{display:flex;align-items:center;gap:.45rem;font-size:.76rem;color:#c9d1d9}
.legend-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}
.legend-val{margin-left:auto;font-weight:700;color:#e6edf3}
.legend-pct{font-size:.68rem;color:#8b949e;min-width:32px;text-align:right}

/* Stat-row (inline quick stats) */
.stat-row{display:flex;gap:0;border-top:1px solid #1c2128}
.stat-box{flex:1;padding:.7rem .9rem;text-align:center;border-right:1px solid #1c2128}
.stat-box:last-child{border-right:none}
.stat-box .sv{font-size:1.25rem;font-weight:800;color:var(--txt)}
.stat-box .sl{font-size:.67rem;font-weight:600;color:#8b949e;text-transform:uppercase;letter-spacing:.04em;margin-top:.15rem}

/* Trend chart row */
.chart-wrap{padding:.7rem 1rem 0;overflow:hidden}

/* Table */
.dtab{width:100%;border-collapse:collapse;font-size:.795rem}
.dtab th{padding:.58rem .9rem;text-align:left;font-size:.68rem;font-weight:600;color:#8b949e;text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid #1c2128;white-space:nowrap;position:sticky;top:0;background:#161b22;z-index:1}
.dtab td{padding:.55rem .9rem;border-bottom:1px solid #0d1117;color:#c9d1d9;vertical-align:middle}
.dtab tr:last-child td{border-bottom:none}
.dtab tbody tr:hover{background:rgba(56,139,253,.04)}
.tbl-wrap{overflow-x:auto;max-height:420px;overflow-y:auto}
.tbl-toolbar{display:flex;align-items:center;gap:.6rem;padding:.65rem 1rem;border-bottom:1px solid #1c2128;flex-wrap:wrap}
.srch{flex:1;min-width:140px;max-width:260px;position:relative}
.srch i{position:absolute;left:.65rem;top:50%;transform:translateY(-50%);color:#484f58;font-size:.75rem;pointer-events:none}
.srch input{width:100%;background:#0d1117;border:1px solid #30363d;color:#e6edf3;padding:.38rem .7rem .38rem 1.9rem;border-radius:8px;font-size:.79rem;outline:none;transition:border .18s}
.srch input:focus{border-color:#388bfd;box-shadow:0 0 0 3px rgba(56,139,253,.1)}
.srch input::placeholder{color:#484f58}
.rec-lbl{font-size:.74rem;color:#8b949e;margin-left:auto;white-space:nowrap}
.hidden-row{display:none}
.empty-msg{padding:2.4rem 1rem;text-align:center;color:#8b949e;font-size:.84rem}
.empty-msg i{display:block;font-size:2rem;opacity:.2;margin-bottom:.55rem}

/* Tx type badge */
.tx-dep{color:#3fb950;background:rgba(63,185,80,.12)}
.tx-wit{color:#f85149;background:rgba(248,81,73,.12)}
.tx-tra{color:#388bfd;background:rgba(56,139,253,.12)}

/* Print */
@media print{
    .ph-act,.tab-strip,.no-print,.tbl-toolbar{display:none!important}
    .rep-section{display:block!important;margin-bottom:1.5rem}
    .tbl-wrap{max-height:none;overflow-y:visible}
}
.rep-section{display:none}
.rep-section.active{display:block}
</style>

<!-- HEADER -->
<div class="ph">
  <div>
    <div class="bc">
      <a href="<?php echo APP_URL; ?>/dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
      <i class="bi bi-chevron-right" style="font-size:.6rem"></i>
      <span>Reports</span>
    </div>
    <h1><i class="bi bi-bar-chart-fill" style="color:#388bfd;margin-right:.4rem"></i>Reports &amp; Analytics</h1>
  </div>
  <div class="ph-act no-print">
    <span style="font-size:.74rem;color:#8b949e"><i class="bi bi-clock"></i> <?php echo date('M j, Y g:i A'); ?></span>
    <button class="btn-ghost" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
  </div>
</div>

<!-- KPI CARDS -->
<div class="kpi-grid">
  <div class="kpi" style="border-top:3px solid #388bfd" onclick="switchTab('population')">
    <div class="kpi-ico" style="background:rgba(56,139,253,.15);color:#388bfd"><i class="bi bi-people-fill"></i></div>
    <div class="kpi-val"><?php echo $totalInmates; ?></div>
    <div class="kpi-lbl">Active Inmates</div>
    <div class="kpi-sub"><?php echo $totalAll; ?> total incl. released</div>
  </div>
  <div class="kpi" style="border-top:3px solid #3fb950" onclick="switchTab('staff')">
    <div class="kpi-ico" style="background:rgba(63,185,80,.15);color:#3fb950"><i class="bi bi-person-badge-fill"></i></div>
    <div class="kpi-val"><?php echo $totalStaff; ?></div>
    <div class="kpi-lbl">Active Staff</div>
    <div class="kpi-sub"><?php echo array_sum(array_column($staffByType,'cnt')); ?> total on record</div>
  </div>
  <div class="kpi" style="border-top:3px solid #f85149" onclick="switchTab('incidents')">
    <div class="kpi-ico" style="background:rgba(248,81,73,.15);color:#f85149"><i class="bi bi-exclamation-triangle-fill"></i></div>
    <div class="kpi-val"><?php echo $incidentsMo; ?></div>
    <div class="kpi-lbl">Incidents This Month</div>
    <div class="kpi-sub"><?php echo $incidentsTot; ?> total · <?php echo $openCount; ?> open</div>
  </div>
  <div class="kpi" style="border-top:3px solid #e3b341" onclick="switchTab('population')">
    <div class="kpi-ico" style="background:rgba(227,179,65,.15);color:#e3b341"><i class="bi bi-arrow-left-right"></i></div>
    <div class="kpi-val"><?php echo $pendingTrans; ?></div>
    <div class="kpi-lbl">Pending Transfers</div>
    <div class="kpi-sub">Awaiting approval</div>
  </div>
  <div class="kpi" style="border-top:3px solid #bb8fce" onclick="switchTab('medical')">
    <div class="kpi-ico" style="background:rgba(187,143,206,.15);color:#bb8fce"><i class="bi bi-heart-pulse-fill"></i></div>
    <div class="kpi-val"><?php echo $medRecords; ?></div>
    <div class="kpi-lbl">Medical Records</div>
    <div class="kpi-sub"><?php echo $activeConditions; ?> active conditions</div>
  </div>
  <div class="kpi" style="border-top:3px solid #58a6ff" onclick="switchTab('programs')">
    <div class="kpi-ico" style="background:rgba(88,166,255,.15);color:#58a6ff"><i class="bi bi-mortarboard-fill"></i></div>
    <div class="kpi-val"><?php echo $totalPrograms; ?></div>
    <div class="kpi-lbl">Active Programs</div>
    <div class="kpi-sub"><?php echo $totalEnrolled; ?> inmates enrolled</div>
  </div>
</div>

<!-- TABS -->
<div class="tab-strip no-print">
<?php
$tabs=['population'=>['bi-people-fill','Population','#388bfd'],
       'incidents' =>['bi-exclamation-triangle-fill','Incidents','#f85149'],
       'staff'     =>['bi-person-badge-fill','Staff','#3fb950'],
       'medical'   =>['bi-heart-pulse-fill','Medical','#bb8fce'],
       'finance'   =>['bi-cash-stack','Finance','#f39c12'],
       'programs'  =>['bi-mortarboard-fill','Programs','#58a6ff']];
foreach($tabs as $key=>[$ico,$label,$clr]):?>
  <a href="?tab=<?php echo $key;?>" class="tab-lnk <?php echo $activeTab===$key?'on':'';?>"
     style="<?php echo $activeTab===$key?"color:$clr":'';?>"
     onclick="event.preventDefault();switchTab('<?php echo $key;?>')">
    <i class="bi <?php echo $ico;?>"></i><?php echo $label;?>
  </a>
<?php endforeach;?>
</div>

<!-- ═══════════════════════ POPULATION ═══════════════════════ -->
<div class="rep-section <?php echo $activeTab==='population'?'active':'';?>" id="sec-population">

  <div class="rep-3col" style="margin-bottom:1.2rem">
    <!-- Donut: by status -->
    <div class="sec">
      <div class="sec-hdr"><h3><i class="bi bi-circle-half" style="color:#388bfd"></i> By Status</h3><span class="cnt-badge"><?php echo $totalAll;?></span></div>
      <?php
      $slices=[];$stot=array_sum(array_column($inmateByStatus,'cnt'));
      foreach($inmateByStatus as $r) $slices[]=['v'=>(int)$r['cnt'],'c'=>$statusColors[$r['status']]??'#8b949e'];
      ?>
      <div class="donut-card">
        <?php echo svgDonut($slices); ?>
        <div class="donut-legend">
          <?php foreach($inmateByStatus as $r):
            $c=$statusColors[$r['status']]??'#8b949e';
            $pct=$stot?round($r['cnt']/$stot*100):0;?>
          <div class="legend-item">
            <span class="legend-dot" style="background:<?php echo $c;?>"></span>
            <span><?php echo htmlspecialchars($r['status']);?></span>
            <span class="legend-val"><?php echo $r['cnt'];?></span>
            <span class="legend-pct"><?php echo $pct;?>%</span>
          </div>
          <?php endforeach;?>
        </div>
      </div>
    </div>

    <!-- Donut: by gender -->
    <div class="sec">
      <div class="sec-hdr"><h3><i class="bi bi-gender-ambiguous" style="color:#bb8fce"></i> By Gender</h3><span class="cnt-badge"><?php echo array_sum(array_column($inmateByGender,'cnt'));?></span></div>
      <?php
      $slices=[];$gtot=array_sum(array_column($inmateByGender,'cnt'));
      foreach($inmateByGender as $r) $slices[]=['v'=>(int)$r['cnt'],'c'=>$genderColors[$r['gender']]??'#8b949e'];
      ?>
      <div class="donut-card">
        <?php echo svgDonut($slices);?>
        <div class="donut-legend">
          <?php foreach($inmateByGender as $r):
            $c=$genderColors[$r['gender']]??'#8b949e';
            $pct=$gtot?round($r['cnt']/$gtot*100):0;?>
          <div class="legend-item">
            <span class="legend-dot" style="background:<?php echo $c;?>"></span>
            <span><?php echo htmlspecialchars($r['gender']??'Unknown');?></span>
            <span class="legend-val"><?php echo $r['cnt'];?></span>
            <span class="legend-pct"><?php echo $pct;?>%</span>
          </div>
          <?php endforeach;?>
        </div>
      </div>
    </div>

    <!-- Donut: by risk -->
    <div class="sec">
      <div class="sec-hdr"><h3><i class="bi bi-shield-exclamation" style="color:#f39c12"></i> By Risk Level</h3><span class="cnt-badge"><?php echo array_sum(array_column($inmateByRisk,'cnt'));?></span></div>
      <?php
      $slices=[];$rtot=array_sum(array_column($inmateByRisk,'cnt'));
      foreach($inmateByRisk as $r) $slices[]=['v'=>(int)$r['cnt'],'c'=>$riskColors[$r['risk_classification']]??'#8b949e'];
      ?>
      <div class="donut-card">
        <?php echo svgDonut($slices);?>
        <div class="donut-legend">
          <?php foreach($inmateByRisk as $r):
            $c=$riskColors[$r['risk_classification']]??'#8b949e';
            $pct=$rtot?round($r['cnt']/$rtot*100):0;?>
          <div class="legend-item">
            <span class="legend-dot" style="background:<?php echo $c;?>"></span>
            <span><?php echo htmlspecialchars($r['risk_classification']??'Unknown');?></span>
            <span class="legend-val"><?php echo $r['cnt'];?></span>
            <span class="legend-pct"><?php echo $pct;?>%</span>
          </div>
          <?php endforeach;?>
        </div>
      </div>
    </div>
  </div>

  <div class="rep-layout" style="margin-bottom:1.2rem">
    <!-- Admission trend -->
    <div class="sec">
      <div class="sec-hdr"><h3><i class="bi bi-graph-up" style="color:#388bfd"></i> Admission Trend</h3></div>
      <?php if($admTrend):
        $rows=array_map(fn($r)=>['l'=>$r['lbl'],'v'=>(int)$r['cnt']],$admTrend);?>
      <div class="chart-wrap" style="padding:.9rem 1rem"><?php echo svgBar($rows,340,90,'#388bfd');?></div>
      <?php else:?><div class="empty-msg"><i class="bi bi-graph-up"></i>No admission data</div><?php endif;?>
      <div class="stat-row">
        <div class="stat-box"><div class="sv"><?php echo $totalInmates;?></div><div class="sl">Active</div></div>
        <div class="stat-box"><div class="sv" style="color:#3fb950"><?php echo ($inmateByStatus[0]['status']==='RELEASED'?$inmateByStatus[0]['cnt']:fetchOne("SELECT COUNT(*) cnt FROM inmates WHERE $facCl AND status='RELEASED'",[],'')['cnt']??0);?></div><div class="sl">Released</div></div>
        <div class="stat-box"><div class="sv" style="color:#8b949e"><?php echo $totalAll;?></div><div class="sl">Total Ever</div></div>
      </div>
    </div>

    <!-- Religion distribution -->
    <div class="sec">
      <div class="sec-hdr"><h3><i class="bi bi-globe" style="color:#58a6ff"></i> Religion Distribution</h3></div>
      <?php if($inmateByRelig):
        $rmax=max(array_column($inmateByRelig,'cnt'));
        $rcolors=['#388bfd','#3fb950','#f39c12','#bb8fce','#f85149','#58a6ff','#e3b341','#8b949e'];
        foreach($inmateByRelig as $ri=>$r):
          $c=$rcolors[$ri%count($rcolors)];
          $pct=$rmax?round($r['cnt']/$rmax*100):0;
          $tot=array_sum(array_column($inmateByRelig,'cnt'));
          $rpct=$tot?round($r['cnt']/$tot*100):0;?>
      <div class="bar-row">
        <span class="bar-lbl"><?php echo htmlspecialchars($r['religion']);?></span>
        <div class="bar-track"><div class="bar-fill" style="width:<?php echo $pct;?>%;background:<?php echo $c;?>"></div></div>
        <span class="bar-num" style="color:<?php echo $c;?>"><?php echo $r['cnt'];?></span>
        <span class="bar-pct"><?php echo $rpct;?>%</span>
      </div>
      <?php endforeach;else:?><div class="empty-msg"><i class="bi bi-globe"></i>No data</div><?php endif;?>
    </div>
  </div>

  <!-- Inmate Roster -->
  <div class="sec rep-full">
    <div class="sec-hdr">
      <h3><i class="bi bi-list-ul" style="color:#388bfd"></i> Inmate Roster</h3>
      <div style="display:flex;gap:.5rem;align-items:center">
        <span class="cnt-badge"><?php echo count($inmateRoster);?> records</span>
        <button class="btn-xs no-print" onclick="exportCSV('tbl-inmates','inmates_roster')"><i class="bi bi-download"></i> CSV</button>
      </div>
    </div>
    <div class="tbl-toolbar no-print">
      <div class="srch"><i class="bi bi-search"></i><input type="text" placeholder="Search name, ID, status…" oninput="filterTable(this,'tbl-inmates')"></div>
      <span class="rec-lbl" id="cnt-inmates"><?php echo count($inmateRoster);?> shown</span>
    </div>
    <?php if($inmateRoster):?>
    <div class="tbl-wrap">
      <table class="dtab" id="tbl-inmates">
        <thead><tr>
          <th>#</th><th>Inmate ID</th><th>Name</th><th>Gender</th>
          <th>Status</th><th>Risk</th><th>Sentence (mo)</th><th>Admission</th><th>Release</th>
          <?php if($isSA):?><th>Facility</th><?php endif;?>
        </tr></thead>
        <tbody>
        <?php foreach($inmateRoster as $idx=>$r):
          $rc=$riskColors[$r['risk_classification']]??'#8b949e';?>
        <tr>
          <td style="color:#484f58"><?php echo $idx+1;?></td>
          <td style="font-family:monospace;color:#8b949e;font-size:.75rem"><?php echo htmlspecialchars($r['inmate_id']);?></td>
          <td style="font-weight:600;color:#e6edf3"><?php echo htmlspecialchars($r['first_name'].' '.$r['last_name']);?></td>
          <td><?php echo htmlspecialchars($r['gender']??'—');?></td>
          <td><?php echo chip($r['status'],$statusColors);?></td>
          <td><span style="font-size:.72rem;font-weight:700;color:<?php echo $rc;?>"><?php echo htmlspecialchars($r['risk_classification']??'—');?></span></td>
          <td style="text-align:center;color:#8b949e"><?php echo $r['sentence_length_months']??'—';?></td>
          <td style="color:#8b949e;white-space:nowrap"><?php echo $r['admission_date']?date('M j, Y',strtotime($r['admission_date'])):'—';?></td>
          <td style="color:#8b949e;white-space:nowrap"><?php echo $r['release_date']?date('M j, Y',strtotime($r['release_date'])):'—';?></td>
          <?php if($isSA):?><td style="color:#484f58;font-size:.75rem"><?php echo htmlspecialchars($r['facility_name']);?></td><?php endif;?>
        </tr>
        <?php endforeach;?>
        </tbody>
      </table>
    </div>
    <?php else:?><div class="empty-msg"><i class="bi bi-people"></i>No inmates found</div><?php endif;?>
  </div>
</div>

<!-- ═══════════════════════ INCIDENTS ═══════════════════════ -->
<div class="rep-section <?php echo $activeTab==='incidents'?'active':'';?>" id="sec-incidents">

  <div class="rep-layout" style="margin-bottom:1.2rem">
    <!-- Severity donut -->
    <div class="sec">
      <div class="sec-hdr"><h3><i class="bi bi-shield-fill-exclamation" style="color:#f85149"></i> By Severity</h3><span class="cnt-badge"><?php echo $incidentsTot;?> total</span></div>
      <?php
      $slices=[];$sevtot=array_sum(array_column($incidentsBySev,'cnt'));
      foreach($incidentsBySev as $r) $slices[]=['v'=>(int)$r['cnt'],'c'=>$sevCfg[$r['severity']]??'#8b949e'];
      ?>
      <div class="donut-card">
        <?php echo svgDonut($slices,110,20);?>
        <div class="donut-legend">
          <?php foreach($incidentsBySev as $r):
            $c=$sevCfg[$r['severity']]??'#8b949e';
            $pct=$sevtot?round($r['cnt']/$sevtot*100):0;?>
          <div class="legend-item">
            <span class="legend-dot" style="background:<?php echo $c;?>"></span>
            <span><?php echo htmlspecialchars($r['severity']);?></span>
            <span class="legend-val"><?php echo $r['cnt'];?></span>
            <span class="legend-pct"><?php echo $pct;?>%</span>
          </div>
          <?php endforeach;?>
        </div>
      </div>
      <div class="stat-row">
        <div class="stat-box"><div class="sv" style="color:#f85149"><?php echo $criticalCount;?></div><div class="sl">Critical/High</div></div>
        <div class="stat-box"><div class="sv" style="color:#f39c12"><?php echo $openCount;?></div><div class="sl">Open</div></div>
        <div class="stat-box"><div class="sv"><?php echo $incidentsTot;?></div><div class="sl">Total</div></div>
      </div>
    </div>

    <!-- By status bars -->
    <div class="sec">
      <div class="sec-hdr"><h3><i class="bi bi-clipboard2-data-fill" style="color:#f39c12"></i> By Status</h3></div>
      <?php if($incidentsByStatus):
        $max=max(array_column($incidentsByStatus,'cnt'));
        $tot2=array_sum(array_column($incidentsByStatus,'cnt'));
        foreach($incidentsByStatus as $r):
          $c=$statusColors[$r['status']]??'#8b949e';
          $pct=$max?round($r['cnt']/$max*100):0;
          $rpct=$tot2?round($r['cnt']/$tot2*100):0;?>
      <div class="bar-row">
        <span class="bar-lbl"><?php echo htmlspecialchars($r['status']);?></span>
        <div class="bar-track"><div class="bar-fill" style="width:<?php echo $pct;?>%;background:<?php echo $c;?>"></div></div>
        <span class="bar-num" style="color:<?php echo $c;?>"><?php echo $r['cnt'];?></span>
        <span class="bar-pct"><?php echo $rpct;?>%</span>
      </div>
      <?php endforeach;else:?><div class="empty-msg"><i class="bi bi-bar-chart"></i>No data</div><?php endif;?>
    </div>
  </div>

  <!-- Incident Log -->
  <div class="sec rep-full">
    <div class="sec-hdr">
      <h3><i class="bi bi-list-ul" style="color:#f85149"></i> Incident Log</h3>
      <div style="display:flex;gap:.5rem;align-items:center">
        <span class="cnt-badge"><?php echo count($recentIncidents);?> records</span>
        <button class="btn-xs no-print" onclick="exportCSV('tbl-incidents','incident_log')"><i class="bi bi-download"></i> CSV</button>
      </div>
    </div>
    <div class="tbl-toolbar no-print">
      <div class="srch"><i class="bi bi-search"></i><input type="text" placeholder="Search inmate, severity, status…" oninput="filterTable(this,'tbl-incidents')"></div>
      <span class="rec-lbl" id="cnt-incidents"><?php echo count($recentIncidents);?> shown</span>
    </div>
    <?php if($recentIncidents):?>
    <div class="tbl-wrap">
      <table class="dtab" id="tbl-incidents">
        <thead><tr><th>Date</th><th>Inmate</th><th>Severity</th><th>Status</th><th>Location</th><th>Description</th></tr></thead>
        <tbody>
        <?php foreach($recentIncidents as $r):?>
        <tr>
          <td style="color:#8b949e;white-space:nowrap"><?php echo date('M j, Y',strtotime($r['incident_date']));?></td>
          <td>
            <?php if($r['first_name']):?>
            <span style="font-weight:600;color:#e6edf3"><?php echo htmlspecialchars($r['first_name'].' '.$r['last_name']);?></span>
            <span style="display:block;font-size:.69rem;color:#484f58;font-family:monospace"><?php echo htmlspecialchars($r['inmate_id']);?></span>
            <?php else:?><span style="color:#484f58">—</span><?php endif;?>
          </td>
          <td><?php echo chip($r['severity'],$sevCfg);?></td>
          <td><?php echo chip($r['status'],$statusColors);?></td>
          <td style="color:#8b949e;font-size:.76rem;white-space:nowrap"><?php echo htmlspecialchars($r['location']??'—');?></td>
          <td style="color:#8b949e;font-size:.76rem;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
              title="<?php echo htmlspecialchars($r['description']??'');?>"><?php echo htmlspecialchars(mb_strimwidth($r['description']??'—',0,80,'…'));?></td>
        </tr>
        <?php endforeach;?>
        </tbody>
      </table>
    </div>
    <?php else:?><div class="empty-msg"><i class="bi bi-exclamation-triangle"></i>No incidents found</div><?php endif;?>
  </div>
</div>

<!-- ═══════════════════════ STAFF ═══════════════════════ -->
<div class="rep-section <?php echo $activeTab==='staff'?'active':'';?>" id="sec-staff">

  <div class="rep-3col" style="margin-bottom:1.2rem">
    <!-- Type donut -->
    <div class="sec">
      <div class="sec-hdr"><h3><i class="bi bi-person-gear" style="color:#3fb950"></i> By Type</h3></div>
      <?php
      $tcolors=['OFFICER'=>'#388bfd','NURSE'=>'#bb8fce','ADMIN'=>'#f39c12','SOCIAL_WORKER'=>'#3fb950'];
      $slices=[];$stot2=array_sum(array_column($staffByType,'cnt'));
      foreach($staffByType as $r) $slices[]=['v'=>(int)$r['cnt'],'c'=>$tcolors[$r['staff_type']]??'#8b949e'];
      ?>
      <div class="donut-card">
        <?php echo svgDonut($slices,100,18);?>
        <div class="donut-legend">
          <?php foreach($staffByType as $r):
            $c=$tcolors[$r['staff_type']]??'#8b949e';
            $pct=$stot2?round($r['cnt']/$stot2*100):0;?>
          <div class="legend-item">
            <span class="legend-dot" style="background:<?php echo $c;?>"></span>
            <span><?php echo htmlspecialchars($r['staff_type']);?></span>
            <span class="legend-val"><?php echo $r['cnt'];?></span>
            <span class="legend-pct"><?php echo $pct;?>%</span>
          </div>
          <?php endforeach;?>
        </div>
      </div>
    </div>

    <!-- Status bars -->
    <div class="sec">
      <div class="sec-hdr"><h3><i class="bi bi-clipboard-check-fill" style="color:#3fb950"></i> Employment Status</h3></div>
      <?php if($staffByStatus):
        $max=max(array_column($staffByStatus,'cnt'));
        $tot3=array_sum(array_column($staffByStatus,'cnt'));
        foreach($staffByStatus as $r):
          $c=$statusColors[$r['employment_status']]??'#8b949e';
          $pct=$max?round($r['cnt']/$max*100):0;
          $rpct=$tot3?round($r['cnt']/$tot3*100):0;?>
      <div class="bar-row">
        <span class="bar-lbl"><?php echo htmlspecialchars($r['employment_status']);?></span>
        <div class="bar-track"><div class="bar-fill" style="width:<?php echo $pct;?>%;background:<?php echo $c;?>"></div></div>
        <span class="bar-num" style="color:<?php echo $c;?>"><?php echo $r['cnt'];?></span>
        <span class="bar-pct"><?php echo $rpct;?>%</span>
      </div>
      <?php endforeach;else:?><div class="empty-msg">No data</div><?php endif;?>
    </div>

    <!-- Gender donut -->
    <div class="sec">
      <div class="sec-hdr"><h3><i class="bi bi-gender-ambiguous" style="color:#bb8fce"></i> By Gender</h3></div>
      <?php
      $slices=[];$gtot2=array_sum(array_column($staffByGender,'cnt'));
      foreach($staffByGender as $r) $slices[]=['v'=>(int)$r['cnt'],'c'=>$genderColors[$r['gender']]??'#8b949e'];
      ?>
      <div class="donut-card">
        <?php echo svgDonut($slices,100,18);?>
        <div class="donut-legend">
          <?php foreach($staffByGender as $r):
            $c=$genderColors[$r['gender']]??'#8b949e';
            $pct=$gtot2?round($r['cnt']/$gtot2*100):0;?>
          <div class="legend-item">
            <span class="legend-dot" style="background:<?php echo $c;?>"></span>
            <span><?php echo htmlspecialchars($r['gender']??'Unknown');?></span>
            <span class="legend-val"><?php echo $r['cnt'];?></span>
            <span class="legend-pct"><?php echo $pct;?>%</span>
          </div>
          <?php endforeach;?>
        </div>
      </div>
    </div>
  </div>

  <!-- Staff Roster -->
  <div class="sec rep-full">
    <div class="sec-hdr">
      <h3><i class="bi bi-list-ul" style="color:#3fb950"></i> Staff Roster</h3>
      <div style="display:flex;gap:.5rem;align-items:center">
        <span class="cnt-badge"><?php echo count($staffRoster);?> records</span>
        <button class="btn-xs no-print" onclick="exportCSV('tbl-staff','staff_roster')"><i class="bi bi-download"></i> CSV</button>
      </div>
    </div>
    <div class="tbl-toolbar no-print">
      <div class="srch"><i class="bi bi-search"></i><input type="text" placeholder="Search name, type, position…" oninput="filterTable(this,'tbl-staff')"></div>
      <span class="rec-lbl" id="cnt-staff"><?php echo count($staffRoster);?> shown</span>
    </div>
    <?php if($staffRoster):?>
    <div class="tbl-wrap">
      <table class="dtab" id="tbl-staff">
        <thead><tr>
          <th>#</th><th>Staff ID</th><th>Username</th><th>Type</th>
          <th>Position</th><th>Gender</th><th>Status</th><th>Hire Date</th>
          <?php if($isSA):?><th>Facility</th><?php endif;?>
        </tr></thead>
        <tbody>
        <?php foreach($staffRoster as $idx=>$r):
          $tc=$tcolors[$r['staff_type']]??'#8b949e';?>
        <tr>
          <td style="color:#484f58"><?php echo $idx+1;?></td>
          <td style="font-family:monospace;color:#8b949e;font-size:.75rem"><?php echo htmlspecialchars($r['staff_id_number']??'—');?></td>
          <td style="font-weight:600;color:#e6edf3"><?php echo htmlspecialchars($r['username']);?></td>
          <td><span style="font-size:.72rem;font-weight:700;color:<?php echo $tc;?>"><?php echo htmlspecialchars($r['staff_type']);?></span></td>
          <td style="color:#8b949e"><?php echo htmlspecialchars($r['position']??'—');?></td>
          <td style="color:#8b949e"><?php echo htmlspecialchars($r['gender']??'—');?></td>
          <td><?php echo chip($r['employment_status'],$statusColors);?></td>
          <td style="color:#8b949e;white-space:nowrap"><?php echo $r['hire_date']?date('M j, Y',strtotime($r['hire_date'])):'—';?></td>
          <?php if($isSA):?><td style="color:#484f58;font-size:.75rem"><?php echo htmlspecialchars($r['facility_name']);?></td><?php endif;?>
        </tr>
        <?php endforeach;?>
        </tbody>
      </table>
    </div>
    <?php else:?><div class="empty-msg"><i class="bi bi-person-badge"></i>No staff found</div><?php endif;?>
  </div>
</div>

<!-- ═══════════════════════ MEDICAL ═══════════════════════ -->
<div class="rep-section <?php echo $activeTab==='medical'?'active':'';?>" id="sec-medical">

  <div class="rep-layout" style="margin-bottom:1.2rem">
    <!-- Donut by status -->
    <div class="sec">
      <div class="sec-hdr"><h3><i class="bi bi-heart-pulse-fill" style="color:#bb8fce"></i> By Status</h3><span class="cnt-badge"><?php echo $medRecords;?></span></div>
      <?php
      $mcolors=['ACTIVE'=>'#f85149','RESOLVED'=>'#3fb950','MONITORING'=>'#e3b341','CHRONIC'=>'#bb8fce','UNKNOWN'=>'#8b949e'];
      $slices=[];$mtot=array_sum(array_column($medByStatus,'cnt'));
      foreach($medByStatus as $r) $slices[]=['v'=>(int)$r['cnt'],'c'=>$mcolors[$r['status']]??'#8b949e'];
      ?>
      <div class="donut-card">
        <?php echo svgDonut($slices,110,20);?>
        <div class="donut-legend">
          <?php foreach($medByStatus as $r):
            $c=$mcolors[$r['status']]??'#8b949e';
            $pct=$mtot?round($r['cnt']/$mtot*100):0;?>
          <div class="legend-item">
            <span class="legend-dot" style="background:<?php echo $c;?>"></span>
            <span><?php echo htmlspecialchars($r['status']);?></span>
            <span class="legend-val"><?php echo $r['cnt'];?></span>
            <span class="legend-pct"><?php echo $pct;?>%</span>
          </div>
          <?php endforeach;?>
        </div>
      </div>
      <div class="stat-row">
        <div class="stat-box"><div class="sv"><?php echo $medRecords;?></div><div class="sl">Total Records</div></div>
        <div class="stat-box"><div class="sv" style="color:#f85149"><?php echo $activeConditions;?></div><div class="sl">Active Conditions</div></div>
        <div class="stat-box"><div class="sv" style="color:#3fb950"><?php echo $mtot?($medByStatus[count($medByStatus)-1]['status']==='RESOLVED'?$medByStatus[count($medByStatus)-1]['cnt']:fetchOne("SELECT COUNT(*) cnt FROM medical_records WHERE $facCl AND status='RESOLVED'",[],'')['cnt']??0):0;?></div><div class="sl">Resolved</div></div>
      </div>
    </div>

    <!-- Bar breakdown -->
    <div class="sec">
      <div class="sec-hdr"><h3><i class="bi bi-bar-chart-fill" style="color:#bb8fce"></i> Condition Breakdown</h3></div>
      <?php if($medByStatus):
        $max=max(array_column($medByStatus,'cnt'));
        foreach($medByStatus as $r):
          $c=$mcolors[$r['status']]??'#8b949e';
          $pct=$max?round($r['cnt']/$max*100):0;
          $rpct=$mtot?round($r['cnt']/$mtot*100):0;?>
      <div class="bar-row">
        <span class="bar-lbl"><?php echo htmlspecialchars($r['status']);?></span>
        <div class="bar-track"><div class="bar-fill" style="width:<?php echo $pct;?>%;background:<?php echo $c;?>"></div></div>
        <span class="bar-num" style="color:<?php echo $c;?>"><?php echo $r['cnt'];?></span>
        <span class="bar-pct"><?php echo $rpct;?>%</span>
      </div>
      <?php endforeach;else:?><div class="empty-msg">No data</div><?php endif;?>
    </div>
  </div>

  <!-- Medical Log -->
  <div class="sec rep-full">
    <div class="sec-hdr">
      <h3><i class="bi bi-clipboard2-pulse-fill" style="color:#bb8fce"></i> Medical Records Log</h3>
      <div style="display:flex;gap:.5rem;align-items:center">
        <span class="cnt-badge"><?php echo count($recentMed);?> records</span>
        <button class="btn-xs no-print" onclick="exportCSV('tbl-medical','medical_records')"><i class="bi bi-download"></i> CSV</button>
      </div>
    </div>
    <div class="tbl-toolbar no-print">
      <div class="srch"><i class="bi bi-search"></i><input type="text" placeholder="Search inmate, diagnosis…" oninput="filterTable(this,'tbl-medical')"></div>
      <span class="rec-lbl" id="cnt-medical"><?php echo count($recentMed);?> shown</span>
    </div>
    <?php if($recentMed):?>
    <div class="tbl-wrap">
      <table class="dtab" id="tbl-medical">
        <thead><tr><th>Date</th><th>Inmate</th><th>Diagnosis</th><th>Treatment Plan</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach($recentMed as $r):?>
        <tr>
          <td style="color:#8b949e;white-space:nowrap"><?php echo $r['record_date']?date('M j, Y',strtotime($r['record_date'])):'—';?></td>
          <td>
            <?php if($r['first_name']):?>
            <span style="font-weight:600;color:#e6edf3"><?php echo htmlspecialchars($r['first_name'].' '.$r['last_name']);?></span>
            <span style="display:block;font-size:.69rem;color:#484f58;font-family:monospace"><?php echo htmlspecialchars($r['inmate_id']);?></span>
            <?php else:?><span style="color:#484f58">—</span><?php endif;?>
          </td>
          <td title="<?php echo htmlspecialchars($r['diagnosis']??'');?>"><?php echo htmlspecialchars(mb_strimwidth($r['diagnosis']??'—',0,60,'…'));?></td>
          <td style="color:#8b949e" title="<?php echo htmlspecialchars($r['treatment_plan']??'');?>"><?php echo htmlspecialchars(mb_strimwidth($r['treatment_plan']??'—',0,60,'…'));?></td>
          <td><?php echo chip($r['status']??'UNKNOWN',$mcolors);?></td>
        </tr>
        <?php endforeach;?>
        </tbody>
      </table>
    </div>
    <?php else:?><div class="empty-msg"><i class="bi bi-heart-pulse"></i>No medical records found</div><?php endif;?>
  </div>
</div>

<!-- ═══════════════════════ FINANCE ═══════════════════════ -->
<div class="rep-section <?php echo $activeTab==='finance'?'active':'';?>" id="sec-finance">

  <div class="rep-layout" style="margin-bottom:1.2rem">
    <!-- Account status donut -->
    <div class="sec">
      <div class="sec-hdr"><h3><i class="bi bi-credit-card-fill" style="color:#f39c12"></i> Account Status</h3><span class="cnt-badge"><?php echo array_sum(array_column($finByStatus,'cnt'));?> accounts</span></div>
      <?php
      $acolors=['ACTIVE'=>'#3fb950','SUSPENDED'=>'#f39c12','CLOSED'=>'#8b949e'];
      $slices=[];$atot=array_sum(array_column($finByStatus,'cnt'));
      foreach($finByStatus as $r) $slices[]=['v'=>(int)$r['cnt'],'c'=>$acolors[$r['account_status']]??'#8b949e'];
      ?>
      <div class="donut-card">
        <?php echo svgDonut($slices,110,20);?>
        <div class="donut-legend">
          <?php foreach($finByStatus as $r):
            $c=$acolors[$r['account_status']]??'#8b949e';
            $pct=$atot?round($r['cnt']/$atot*100):0;?>
          <div class="legend-item">
            <span class="legend-dot" style="background:<?php echo $c;?>"></span>
            <span><?php echo htmlspecialchars($r['account_status']);?></span>
            <span class="legend-val"><?php echo $r['cnt'];?></span>
            <span class="legend-pct"><?php echo $pct;?>%</span>
          </div>
          <?php endforeach;?>
        </div>
      </div>
      <div class="stat-row">
        <div class="stat-box"><div class="sv" style="color:#f39c12">$<?php echo number_format($totalBalance,0);?></div><div class="sl">Total Funds</div></div>
        <div class="stat-box"><div class="sv"><?php echo $atot;?></div><div class="sl">Accounts</div></div>
        <div class="stat-box"><div class="sv" style="color:#8b949e">$<?php echo $atot?number_format($totalBalance/$atot,2):'0.00';?></div><div class="sl">Avg Balance</div></div>
      </div>
    </div>

    <!-- Transaction summary -->
    <div class="sec">
      <div class="sec-hdr"><h3><i class="bi bi-arrow-left-right" style="color:#f39c12"></i> Transaction Summary</h3></div>
      <?php if($txSummary):
        $txColors=['DEPOSIT'=>'#3fb950','WITHDRAWAL'=>'#f85149','TRANSFER'=>'#388bfd','CANTEEN'=>'#bb8fce'];
        $tmax2=max(array_column($txSummary,'total'));
        foreach($txSummary as $tx):
          $c=$txColors[$tx['transaction_type']]??'#8b949e';
          $pct=$tmax2?round((float)$tx['total']/$tmax2*100):0;?>
      <div class="bar-row" style="flex-direction:column;align-items:flex-start;gap:.3rem;padding:.7rem 1rem">
        <div style="display:flex;align-items:center;justify-content:space-between;width:100%">
          <span style="font-size:.79rem;font-weight:600;color:#c9d1d9"><?php echo htmlspecialchars($tx['transaction_type']);?></span>
          <span style="font-size:.78rem;font-weight:700;color:<?php echo $c;?>">$<?php echo number_format((float)$tx['total'],2);?></span>
        </div>
        <div class="bar-track" style="width:100%;height:5px"><div class="bar-fill" style="width:<?php echo $pct;?>%;background:<?php echo $c;?>"></div></div>
        <span style="font-size:.7rem;color:#8b949e"><?php echo $tx['cnt'];?> transaction<?php echo $tx['cnt']!=1?'s':'';?></span>
      </div>
      <?php endforeach;else:?><div class="empty-msg"><i class="bi bi-arrow-left-right"></i>No transactions</div><?php endif;?>
    </div>
  </div>

  <!-- Account Balances Table -->
  <div class="sec rep-full">
    <div class="sec-hdr">
      <h3><i class="bi bi-list-ol" style="color:#f39c12"></i> Account Balances</h3>
      <div style="display:flex;gap:.5rem;align-items:center">
        <span class="cnt-badge"><?php echo count($finTopAccts);?> accounts</span>
        <button class="btn-xs no-print" onclick="exportCSV('tbl-finance','account_balances')"><i class="bi bi-download"></i> CSV</button>
      </div>
    </div>
    <div class="tbl-toolbar no-print">
      <div class="srch"><i class="bi bi-search"></i><input type="text" placeholder="Search inmate name or ID…" oninput="filterTable(this,'tbl-finance')"></div>
      <span class="rec-lbl" id="cnt-finance"><?php echo count($finTopAccts);?> shown</span>
    </div>
    <?php if($finTopAccts):?>
    <div class="tbl-wrap">
      <table class="dtab" id="tbl-finance">
        <thead><tr>
          <th>#</th><th>Inmate ID</th><th>Name</th><th>Inmate Status</th>
          <th>Account</th><th style="text-align:right">Balance</th><th>Updated</th>
        </tr></thead>
        <tbody>
        <?php foreach($finTopAccts as $idx=>$r):?>
        <tr>
          <td style="color:#484f58"><?php echo $idx+1;?></td>
          <td style="font-family:monospace;color:#8b949e;font-size:.75rem"><?php echo htmlspecialchars($r['inmate_id']);?></td>
          <td style="font-weight:600;color:#e6edf3"><?php echo htmlspecialchars($r['first_name'].' '.$r['last_name']);?></td>
          <td><?php echo chip($r['inmate_status'],$statusColors);?></td>
          <td><?php echo chip($r['account_status'],$acolors);?></td>
          <td style="text-align:right;font-weight:700;font-family:monospace;color:<?php echo (float)$r['account_balance']>0?'#3fb950':'#f85149';?>">
            $<?php echo number_format((float)$r['account_balance'],2);?>
          </td>
          <td style="color:#8b949e;font-size:.75rem;white-space:nowrap"><?php echo $r['updated_at']?date('M j, Y',strtotime($r['updated_at'])):'—';?></td>
        </tr>
        <?php endforeach;?>
        </tbody>
      </table>
    </div>
    <?php else:?><div class="empty-msg"><i class="bi bi-cash-stack"></i>No accounts found</div><?php endif;?>
  </div>
</div>

<!-- ═══════════════════════ PROGRAMS ═══════════════════════ -->
<div class="rep-section <?php echo $activeTab==='programs'?'active':'';?>" id="sec-programs">

  <div class="rep-layout" style="margin-bottom:1.2rem">
    <!-- Type donut -->
    <div class="sec">
      <div class="sec-hdr"><h3><i class="bi bi-grid-fill" style="color:#58a6ff"></i> By Type</h3><span class="cnt-badge"><?php echo $totalProgsAll;?></span></div>
      <?php
      $slices=[];$ptot=array_sum(array_column($progByType,'cnt'));
      foreach($progByType as $r) $slices[]=['v'=>(int)$r['cnt'],'c'=>$typeCfg[$r['program_type']][0]??'#8b949e'];
      ?>
      <div class="donut-card">
        <?php echo svgDonut($slices,110,20);?>
        <div class="donut-legend">
          <?php foreach($progByType as $r):
            [$tc]=$typeCfg[$r['program_type']]??['#8b949e'];
            $pct=$ptot?round($r['cnt']/$ptot*100):0;?>
          <div class="legend-item">
            <span class="legend-dot" style="background:<?php echo $tc;?>"></span>
            <span><?php echo htmlspecialchars($r['program_type']);?></span>
            <span class="legend-val"><?php echo $r['cnt'];?></span>
            <span class="legend-pct"><?php echo $pct;?>%</span>
          </div>
          <?php endforeach;?>
        </div>
      </div>
      <div class="stat-row">
        <div class="stat-box"><div class="sv"><?php echo $totalProgsAll;?></div><div class="sl">Total</div></div>
        <div class="stat-box"><div class="sv" style="color:#3fb950"><?php echo $totalPrograms;?></div><div class="sl">Active</div></div>
        <div class="stat-box"><div class="sv" style="color:#388bfd"><?php echo $completionRate;?>%</div><div class="sl">Completion</div></div>
      </div>
    </div>

    <!-- Status bars -->
    <div class="sec">
      <div class="sec-hdr"><h3><i class="bi bi-clipboard2-check-fill" style="color:#58a6ff"></i> By Status &amp; Enrollment</h3></div>
      <?php if($progByStatus):
        $max=max(array_column($progByStatus,'cnt'));
        $tot4=array_sum(array_column($progByStatus,'cnt'));
        foreach($progByStatus as $r):
          $c=$statusColors[$r['status']]??'#8b949e';
          $pct=$max?round($r['cnt']/$max*100):0;
          $rpct=$tot4?round($r['cnt']/$tot4*100):0;?>
      <div class="bar-row">
        <span class="bar-lbl"><?php echo htmlspecialchars($r['status']);?></span>
        <div class="bar-track"><div class="bar-fill" style="width:<?php echo $pct;?>%;background:<?php echo $c;?>"></div></div>
        <span class="bar-num" style="color:<?php echo $c;?>"><?php echo $r['cnt'];?></span>
        <span class="bar-pct"><?php echo $rpct;?>%</span>
      </div>
      <?php endforeach;else:?><div class="empty-msg">No data</div><?php endif;?>
      <div class="stat-row">
        <div class="stat-box"><div class="sv" style="color:#bb8fce"><?php echo $totalEnrolled;?></div><div class="sl">Enrolled Inmates</div></div>
        <div class="stat-box"><div class="sv"><?php echo $completedProgs;?></div><div class="sl">Completed Progs</div></div>
      </div>
    </div>
  </div>

  <!-- Programs list -->
  <div class="sec rep-full">
    <div class="sec-hdr">
      <h3><i class="bi bi-list-ul" style="color:#58a6ff"></i> Programs List</h3>
      <div style="display:flex;gap:.5rem;align-items:center">
        <span class="cnt-badge"><?php echo count($programsList);?> programs</span>
        <button class="btn-xs no-print" onclick="exportCSV('tbl-programs','programs_list')"><i class="bi bi-download"></i> CSV</button>
      </div>
    </div>
    <div class="tbl-toolbar no-print">
      <div class="srch"><i class="bi bi-search"></i><input type="text" placeholder="Search name, type, status…" oninput="filterTable(this,'tbl-programs')"></div>
      <span class="rec-lbl" id="cnt-programs"><?php echo count($programsList);?> shown</span>
    </div>
    <?php if($programsList):?>
    <div class="tbl-wrap">
      <table class="dtab" id="tbl-programs">
        <thead><tr>
          <th>#</th><th>Name</th><th>Type</th><th>Status</th>
          <th>Start</th><th>End</th><th style="text-align:center">Enrolled/Cap</th>
        </tr></thead>
        <tbody>
        <?php foreach($programsList as $idx=>$r):
          [$tc,$ti]=$typeCfg[$r['program_type']]??['#8b949e','bi-circle'];
          $cap=(int)($r['capacity']??0);
          $enrolled=(int)$r['enrolled'];
          $fillPct=$cap>0?min(100,round($enrolled/$cap*100)):0;
          $fillClr=$fillPct>=100?'#f85149':($fillPct>=80?'#f39c12':'#3fb950');?>
        <tr>
          <td style="color:#484f58"><?php echo $idx+1;?></td>
          <td style="font-weight:600;color:#e6edf3"><?php echo htmlspecialchars($r['name']);?></td>
          <td><span style="font-size:.72rem;font-weight:700;color:<?php echo $tc;?>"><i class="bi <?php echo $ti;?>"></i> <?php echo htmlspecialchars($r['program_type']);?></span></td>
          <td><?php echo chip($r['status'],$statusColors);?></td>
          <td style="color:#8b949e;white-space:nowrap"><?php echo $r['start_date']?date('M j, Y',strtotime($r['start_date'])):'—';?></td>
          <td style="color:#8b949e;white-space:nowrap"><?php echo $r['end_date']?date('M j, Y',strtotime($r['end_date'])):'Ongoing';?></td>
          <td style="text-align:center">
            <?php if($cap>0):?>
            <div style="display:flex;align-items:center;gap:.5rem;justify-content:center">
              <span style="font-size:.77rem;font-weight:700;color:<?php echo $fillClr;?>"><?php echo $enrolled;?>/<?php echo $cap;?></span>
              <div style="width:56px;height:6px;background:#21262d;border-radius:3px;overflow:hidden">
                <div style="height:100%;width:<?php echo $fillPct;?>%;background:<?php echo $fillClr;?>;border-radius:3px"></div>
              </div>
            </div>
            <?php else:?>
            <span style="color:#8b949e;font-size:.77rem"><?php echo $enrolled;?> / ∞</span>
            <?php endif;?>
          </td>
        </tr>
        <?php endforeach;?>
        </tbody>
      </table>
    </div>
    <?php else:?><div class="empty-msg"><i class="bi bi-mortarboard"></i>No programs found</div><?php endif;?>
  </div>
</div>

<script>
/* ── Tab switching ── */
var activeTab='<?php echo $activeTab;?>';
function switchTab(tab){
  document.querySelectorAll('.rep-section').forEach(s=>s.classList.remove('active'));
  document.querySelectorAll('.tab-lnk').forEach(l=>{l.classList.remove('on');l.style.color='';});
  var sec=document.getElementById('sec-'+tab);
  if(sec) sec.classList.add('active');
  var colors={population:'#388bfd',incidents:'#f85149',staff:'#3fb950',medical:'#bb8fce',finance:'#f39c12',programs:'#58a6ff'};
  document.querySelectorAll('.tab-lnk').forEach(l=>{
    if(l.getAttribute('href')==='?tab='+tab){l.classList.add('on');l.style.color=colors[tab]||'';}
  });
  activeTab=tab;
  history.replaceState(null,'','?tab='+tab);
}

/* ── Table search filter ── */
function filterTable(inp, tableId){
  var q=inp.value.trim().toLowerCase();
  var tbl=document.getElementById(tableId);
  if(!tbl) return;
  var rows=tbl.querySelectorAll('tbody tr');
  var shown=0;
  rows.forEach(function(row){
    var txt=row.textContent.toLowerCase();
    if(!q||txt.includes(q)){row.classList.remove('hidden-row');shown++;}
    else row.classList.add('hidden-row');
  });
  var lbl=document.getElementById('cnt-'+tableId.replace('tbl-',''));
  if(lbl) lbl.textContent=shown+' shown';
}

/* ── CSV export ── */
function exportCSV(tableId, filename){
  var tbl=document.getElementById(tableId);
  if(!tbl) return;
  var rows=Array.from(tbl.querySelectorAll('tr')).filter(r=>!r.classList.contains('hidden-row'));
  var csv=rows.map(function(row){
    return Array.from(row.querySelectorAll('th,td')).map(function(cell){
      var t=cell.innerText.replace(/\n/g,' ').replace(/"/g,'""');
      return '"'+t.trim()+'"';
    }).join(',');
  }).join('\n');
  var blob=new Blob([csv],{type:'text/csv;charset=utf-8;'});
  var url=URL.createObjectURL(blob);
  var a=document.createElement('a');
  a.href=url; a.download=filename+'_<?php echo date('Ymd');?>.csv';
  document.body.appendChild(a); a.click();
  document.body.removeChild(a); URL.revokeObjectURL(url);
}
</script>

<?php require_once '../../includes/footer.php';?>
