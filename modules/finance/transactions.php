<?php
$pageTitle = 'Transactions';
require_once '../../includes/header.php';
requireAuth();
verifyPermission('view', 'finance');

$fid  = getCurrentFacility();
$isSA = isSuperAdmin();

/* ── facility clauses ── */
$facPl = $isSA ? "it.deleted_at IS NULL"
               : "it.deleted_at IS NULL AND it.facility_id=$fid";
$facKpi = $isSA ? "deleted_at IS NULL"
                : "deleted_at IS NULL AND facility_id=$fid";

/* ── filters ── */
$activeTab = $_GET['tab']   ?? 'ALL';
$search    = trim($_GET['q'] ?? '');
$dateFrom  = trim($_GET['from'] ?? '');
$dateTo    = trim($_GET['to']   ?? '');

$allowed = ['ALL','DEPOSIT','WITHDRAWAL','TRANSFER','CANTEEN'];
if (!in_array($activeTab, $allowed)) $activeTab = 'ALL';

$typeCl = $activeTab !== 'ALL' ? " AND it.transaction_type='$activeTab'" : '';
$srchCl = '';
if ($search) {
    $esc = addslashes($search);
    $srchCl = " AND (i.first_name LIKE '%$esc%' OR i.last_name LIKE '%$esc%' OR i.inmate_id LIKE '%$esc%')";
}
$dateCl = '';
if ($dateFrom) $dateCl .= " AND DATE(it.created_at) >= '".addslashes($dateFrom)."'";
if ($dateTo)   $dateCl .= " AND DATE(it.created_at) <= '".addslashes($dateTo)."'";

/* ── data ── */
$transactions = fetchAll(
    "SELECT it.id, it.transaction_type, it.amount, it.balance_before, it.balance_after,
            it.description, it.created_at, it.facility_id,
            i.first_name, i.last_name, i.inmate_id AS inmate_number,
            f.name facility_name,
            u.username processed_by_name
     FROM inmate_transactions it
     JOIN inmate_accounts ia ON it.account_id = ia.id
     JOIN inmates i          ON ia.inmate_id  = i.id
     JOIN facilities f       ON it.facility_id = f.id
     LEFT JOIN users u       ON it.processed_by = u.id
     WHERE $facPl$typeCl$srchCl$dateCl
     ORDER BY it.created_at DESC
     LIMIT 200",
    [], ''
);

/* ── KPIs (unfiltered for the page) ── */
$kpiRows = fetchAll(
    "SELECT transaction_type, COUNT(*) cnt, COALESCE(SUM(amount),0) vol
     FROM inmate_transactions WHERE $facKpi GROUP BY transaction_type",
    [], ''
);
$km = [];
foreach ($kpiRows as $r) $km[$r['transaction_type']] = $r;
$totalTx  = array_sum(array_column($kpiRows,'cnt'));
$deposits = $km['DEPOSIT']['vol']    ?? 0;
$withdrawals = $km['WITHDRAWAL']['vol'] ?? 0;
$canteen  = $km['CANTEEN']['vol']    ?? 0;
$transfers= $km['TRANSFER']['vol']   ?? 0;
$netFlow  = $deposits - $withdrawals - $canteen;

/* ── tab counts (with filters applied) ── */
$tabCounts = [];
foreach ($allowed as $t) {
    $tCl = $t !== 'ALL' ? " AND it.transaction_type='$t'" : '';
    $r = fetchOne(
        "SELECT COUNT(*) cnt FROM inmate_transactions it
         JOIN inmate_accounts ia ON it.account_id=ia.id
         JOIN inmates i ON ia.inmate_id=i.id
         WHERE $facPl$tCl$srchCl$dateCl",
        [], ''
    );
    $tabCounts[$t] = $r['cnt'] ?? 0;
}
?>
<style>
:root{--sur:#161b22;--bdr:#21262d;--bg:#0d1117;--txt:#e6edf3;--mut:#8b949e}
.ph{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:.75rem}
.ph h1{font-size:1.35rem;font-weight:700;color:var(--txt);margin:0}
.bc{font-size:.78rem;color:#6e7681;display:flex;align-items:center;gap:.3rem;margin-bottom:.3rem}
.bc a{color:#388bfd;text-decoration:none}.bc a:hover{text-decoration:underline}
.ph-actions{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap}
.btn-ghost{display:inline-flex;align-items:center;gap:.4rem;padding:.42rem .85rem;border-radius:9px;border:1px solid #30363d;background:transparent;color:#c9d1d9;font-size:.82rem;font-weight:600;cursor:pointer;text-decoration:none;transition:all .18s}
.btn-ghost:hover{background:#21262d;border-color:#388bfd;color:#388bfd}

/* KPIs */
.kpi-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:1rem;margin-bottom:1.5rem}
@media(max-width:1100px){.kpi-grid{grid-template-columns:repeat(3,1fr)}}
@media(max-width:640px){.kpi-grid{grid-template-columns:repeat(2,1fr)}}
.kpi{background:var(--sur);border:1px solid var(--bdr);border-radius:14px;padding:1rem 1.1rem;transition:transform .18s,box-shadow .18s}
.kpi:hover{transform:translateY(-2px);box-shadow:0 6px 24px rgba(0,0,0,.35)}
.kpi-ico{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:.9rem;margin-bottom:.65rem}
.kpi-val{font-size:1.45rem;font-weight:800;color:var(--txt);line-height:1;margin-bottom:.2rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.kpi-lbl{font-size:.7rem;font-weight:600;color:var(--mut);text-transform:uppercase;letter-spacing:.05em}
.kpi-sub{font-size:.68rem;color:#8b949e;margin-top:.15rem}

/* Tab strip */
.tab-strip{display:flex;gap:.4rem;margin-bottom:1.25rem;flex-wrap:wrap;padding:.4rem .45rem;background:var(--sur);border:1px solid var(--bdr);border-radius:12px;width:fit-content}
.tab{padding:.36rem .8rem;border-radius:8px;font-size:.78rem;font-weight:600;cursor:pointer;color:var(--mut);background:transparent;border:none;display:flex;align-items:center;gap:.38rem;transition:all .18s;text-decoration:none;white-space:nowrap}
.tab:hover,.tab.active{background:#21262d;color:var(--txt)}
.tbadge{padding:1px 6px;border-radius:20px;font-size:.66rem;font-weight:700;background:rgba(139,148,158,.15)}

/* Toolbar */
.toolbar{display:flex;align-items:center;gap:.6rem;margin-bottom:1.1rem;flex-wrap:wrap}
.search-wrap{position:relative;min-width:180px;max-width:280px;flex:1}
.search-wrap i{position:absolute;left:.7rem;top:50%;transform:translateY(-50%);color:#484f58;font-size:.8rem;pointer-events:none}
.search-wrap input{width:100%;background:#161b22;border:1px solid #30363d;color:var(--txt);padding:.45rem .8rem .45rem 2rem;border-radius:8px;font-size:.82rem;outline:none;transition:border .2s;box-sizing:border-box}
.search-wrap input::placeholder{color:#484f58}
.search-wrap input:focus{border-color:#388bfd;box-shadow:0 0 0 3px rgba(56,139,253,.12)}
.date-input{background:#161b22;border:1px solid #30363d;color:var(--txt);padding:.44rem .75rem;border-radius:8px;font-size:.82rem;outline:none;transition:border .2s;cursor:pointer}
.date-input:focus{border-color:#388bfd}
.date-label{font-size:.76rem;color:#8b949e;white-space:nowrap}
.count-lbl{font-size:.78rem;color:#8b949e;margin-left:auto;white-space:nowrap}

/* Card + table */
.card{background:var(--sur);border:1px solid var(--bdr);border-radius:14px;overflow:hidden}
.tbl-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:.855rem}
th{background:#0d1117;color:var(--mut);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;padding:.65rem 1rem;text-align:left;white-space:nowrap;border-bottom:1px solid var(--bdr)}
td{padding:.72rem 1rem;border-bottom:1px solid #1c2128;color:var(--txt);vertical-align:middle}
tr:last-child td{border-bottom:none}
tr:hover td{background:rgba(255,255,255,.025);cursor:pointer}

/* Inmate cell */
.inmate-cell{display:flex;align-items:center;gap:.6rem}
.avatar{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:700;flex-shrink:0;color:#fff}
.inmate-name{font-weight:600;font-size:.84rem;color:var(--txt)}
.inmate-id{font-size:.7rem;color:#8b949e;font-family:monospace}

/* Type badge */
.type-badge{display:inline-flex;align-items:center;gap:.3rem;padding:3px 10px;border-radius:20px;font-size:.71rem;font-weight:700}

/* Amount */
.amt{font-weight:800;font-size:.92rem}
.amt.dep{color:#3fb950}
.amt.wd{color:#f85149}
.amt.tr{color:#388bfd}
.amt.ct{color:#bb8fce}

/* Balance */
.bal-cell{font-size:.82rem;color:#8b949e;font-family:monospace}
.bal-arrow{color:#484f58;margin:0 .2rem;font-size:.7rem}

/* Meta */
.meta-cell{font-size:.78rem;color:#8b949e}
.desc-cell{font-size:.8rem;color:#c9d1d9;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

/* Date */
.date-cell{font-size:.78rem;color:#8b949e;white-space:nowrap}

/* Empty */
.empty-state{text-align:center;padding:3.5rem 1rem;color:#8b949e}
.empty-state i{font-size:2.5rem;display:block;margin-bottom:.75rem;opacity:.35}
.empty-state p{font-size:.875rem;margin:0}

/* Detail modal */
.modal-ov{position:fixed;inset:0;background:rgba(1,4,9,.78);z-index:1000;display:flex;align-items:center;justify-content:center;padding:1rem;opacity:0;pointer-events:none;transition:opacity .22s}
.modal-ov.open{opacity:1;pointer-events:auto}
.modal-box{background:#161b22;border:1px solid #30363d;border-radius:18px;width:100%;max-width:480px;max-height:90vh;overflow-y:auto;transform:translateY(20px);transition:transform .22s}
.modal-ov.open .modal-box{transform:translateY(0)}
.modal-hdr{display:flex;align-items:center;justify-content:space-between;padding:1.1rem 1.4rem;border-bottom:1px solid #21262d;position:sticky;top:0;background:#161b22;z-index:1}
.modal-hdr h2{margin:0;font-size:1rem;font-weight:700;color:#e6edf3;display:flex;align-items:center;gap:.5rem}
.modal-close{background:none;border:none;color:#8b949e;font-size:1.1rem;cursor:pointer;width:30px;height:30px;border-radius:7px;display:flex;align-items:center;justify-content:center;transition:all .18s}
.modal-close:hover{background:#21262d;color:#e6edf3}
.modal-body{padding:1.4rem}
.detail-row{display:flex;align-items:center;justify-content:space-between;padding:.6rem 0;border-bottom:1px solid #21262d}
.detail-row:last-child{border-bottom:none}
.detail-lbl{font-size:.75rem;font-weight:600;color:#8b949e;text-transform:uppercase;letter-spacing:.04em}
.detail-val{font-size:.875rem;font-weight:600;color:#e6edf3;text-align:right;max-width:270px}
.amount-display{font-size:2rem;font-weight:800;text-align:center;margin:1.2rem 0;padding:1rem;background:#0d1117;border-radius:12px;border:1px solid #21262d}
.balance-flow{display:flex;align-items:center;justify-content:center;gap:.6rem;font-size:.85rem;margin-bottom:1.2rem}
.balance-flow .bb{color:#8b949e}
.balance-flow .ba{font-weight:700;color:#e6edf3}
.balance-flow .arrow{color:#484f58}
</style>

<!-- PAGE HEADER -->
<div class="ph">
  <div>
    <div class="bc">
      <a href="<?php echo APP_URL; ?>/dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
      <i class="bi bi-chevron-right" style="font-size:.6rem"></i>
      <a href="<?php echo APP_URL; ?>/modules/finance/index.php">Finance</a>
      <i class="bi bi-chevron-right" style="font-size:.6rem"></i>
      <span>Transactions</span>
    </div>
    <h1><i class="bi bi-arrow-left-right" style="color:#388bfd;margin-right:.45rem"></i>Transaction Ledger</h1>
  </div>
  <div class="ph-actions">
    <a href="<?php echo APP_URL; ?>/modules/finance/index.php" class="btn-ghost">
      <i class="bi bi-bank"></i> Finance Overview
    </a>
    <a href="<?php echo APP_URL; ?>/modules/finance/accounts.php" class="btn-ghost">
      <i class="bi bi-wallet2"></i> Accounts
    </a>
  </div>
</div>

<!-- KPI GRID -->
<div class="kpi-grid">
  <div class="kpi" style="border-top:3px solid #388bfd">
    <div class="kpi-ico" style="background:rgba(56,139,253,.15);color:#388bfd"><i class="bi bi-receipt"></i></div>
    <div class="kpi-val"><?php echo number_format($totalTx); ?></div>
    <div class="kpi-lbl">Total Transactions</div>
    <div class="kpi-sub">All time</div>
  </div>
  <div class="kpi" style="border-top:3px solid #3fb950">
    <div class="kpi-ico" style="background:rgba(63,185,80,.15);color:#3fb950"><i class="bi bi-arrow-down-circle-fill"></i></div>
    <div class="kpi-val" style="font-size:<?php echo strlen(number_format($deposits,2))>8?'1rem':'1.45rem'; ?>">$<?php echo number_format($deposits,2); ?></div>
    <div class="kpi-lbl">Deposits</div>
    <div class="kpi-sub"><?php echo $km['DEPOSIT']['cnt'] ?? 0; ?> transaction<?php echo ($km['DEPOSIT']['cnt']??0)!=1?'s':''; ?></div>
  </div>
  <div class="kpi" style="border-top:3px solid #f85149">
    <div class="kpi-ico" style="background:rgba(248,81,73,.15);color:#f85149"><i class="bi bi-arrow-up-circle-fill"></i></div>
    <div class="kpi-val" style="font-size:<?php echo strlen(number_format($withdrawals,2))>8?'1rem':'1.45rem'; ?>">$<?php echo number_format($withdrawals,2); ?></div>
    <div class="kpi-lbl">Withdrawals</div>
    <div class="kpi-sub"><?php echo $km['WITHDRAWAL']['cnt'] ?? 0; ?> transaction<?php echo ($km['WITHDRAWAL']['cnt']??0)!=1?'s':''; ?></div>
  </div>
  <div class="kpi" style="border-top:3px solid #bb8fce">
    <div class="kpi-ico" style="background:rgba(187,143,206,.15);color:#bb8fce"><i class="bi bi-bag-fill"></i></div>
    <div class="kpi-val" style="font-size:<?php echo strlen(number_format($canteen,2))>8?'1rem':'1.45rem'; ?>">$<?php echo number_format($canteen,2); ?></div>
    <div class="kpi-lbl">Canteen</div>
    <div class="kpi-sub"><?php echo $km['CANTEEN']['cnt'] ?? 0; ?> transaction<?php echo ($km['CANTEEN']['cnt']??0)!=1?'s':''; ?></div>
  </div>
  <div class="kpi" style="border-top:3px solid #58a6ff">
    <div class="kpi-ico" style="background:rgba(88,166,255,.15);color:#58a6ff"><i class="bi bi-arrow-left-right"></i></div>
    <div class="kpi-val" style="font-size:<?php echo strlen(number_format($transfers,2))>8?'1rem':'1.45rem'; ?>">$<?php echo number_format($transfers,2); ?></div>
    <div class="kpi-lbl">Transfers</div>
    <div class="kpi-sub"><?php echo $km['TRANSFER']['cnt'] ?? 0; ?> transaction<?php echo ($km['TRANSFER']['cnt']??0)!=1?'s':''; ?></div>
  </div>
  <div class="kpi" style="border-top:3px solid <?php echo $netFlow>=0?'#3fb950':'#f85149'; ?>">
    <div class="kpi-ico" style="background:rgba(<?php echo $netFlow>=0?'63,185,80':'248,81,73'; ?>,.15);color:<?php echo $netFlow>=0?'#3fb950':'#f85149'; ?>"><i class="bi bi-graph-up-arrow"></i></div>
    <div class="kpi-val" style="color:<?php echo $netFlow>=0?'#3fb950':'#f85149'; ?>;font-size:<?php echo strlen(number_format(abs($netFlow),2))>8?'1rem':'1.45rem'; ?>"><?php echo $netFlow>=0?'+':'-'; ?>$<?php echo number_format(abs($netFlow),2); ?></div>
    <div class="kpi-lbl">Net Flow</div>
    <div class="kpi-sub">Deposits minus outflows</div>
  </div>
</div>

<!-- TABS -->
<?php
$tabDefs = [
    'ALL'        => ['All',        '#8b949e'],
    'DEPOSIT'    => ['Deposits',   '#3fb950'],
    'WITHDRAWAL' => ['Withdrawals','#f85149'],
    'TRANSFER'   => ['Transfers',  '#58a6ff'],
    'CANTEEN'    => ['Canteen',    '#bb8fce'],
];
$qStr = ($search ? '&q='.urlencode($search) : '').($dateFrom ? '&from='.urlencode($dateFrom) : '').($dateTo ? '&to='.urlencode($dateTo) : '');
?>
<div class="tab-strip">
<?php foreach ($tabDefs as $key => [$label,$clr]): ?>
  <a href="?tab=<?php echo $key.$qStr; ?>"
     class="tab <?php echo $activeTab===$key?'active':''; ?>">
    <?php echo $label; ?>
    <?php if ($tabCounts[$key] > 0): ?>
    <span class="tbadge" style="color:<?php echo $clr; ?>"><?php echo $tabCounts[$key]; ?></span>
    <?php endif; ?>
  </a>
<?php endforeach; ?>
</div>

<!-- TOOLBAR -->
<form method="GET">
  <input type="hidden" name="tab" value="<?php echo htmlspecialchars($activeTab); ?>">
  <div class="toolbar">
    <div class="search-wrap">
      <i class="bi bi-search"></i>
      <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>"
             placeholder="Search inmate name or ID…" onchange="this.form.submit()">
    </div>
    <span class="date-label">From</span>
    <input type="date" name="from" class="date-input" value="<?php echo htmlspecialchars($dateFrom); ?>" onchange="this.form.submit()">
    <span class="date-label">To</span>
    <input type="date" name="to" class="date-input" value="<?php echo htmlspecialchars($dateTo); ?>" onchange="this.form.submit()">
    <?php if ($search || $dateFrom || $dateTo): ?>
    <a href="?tab=<?php echo urlencode($activeTab); ?>" class="btn-ghost" style="padding:.38rem .7rem">
      <i class="bi bi-x-lg"></i> Clear
    </a>
    <?php endif; ?>
    <span class="count-lbl"><?php echo count($transactions); ?> result<?php echo count($transactions)!=1?'s':''; ?></span>
  </div>
</form>

<!-- TABLE -->
<div class="card">
<?php if (empty($transactions)): ?>
  <div class="empty-state">
    <i class="bi bi-inbox"></i>
    <p>No transactions found<?php echo ($search||$dateFrom||$dateTo) ? ' for the selected filters' : ''; ?>.</p>
  </div>
<?php else: ?>
<div class="tbl-wrap">
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Inmate</th>
        <?php if ($isSA): ?><th>Facility</th><?php endif; ?>
        <th>Type</th>
        <th>Amount</th>
        <th>Balance Change</th>
        <th>Description</th>
        <th>Processed By</th>
        <th>Date</th>
      </tr>
    </thead>
    <tbody>
    <?php
    $typeCfg = [
      'DEPOSIT'    => ['#3fb950','rgba(63,185,80,.15)','bi-arrow-down-circle-fill','dep'],
      'WITHDRAWAL' => ['#f85149','rgba(248,81,73,.15)','bi-arrow-up-circle-fill','wd'],
      'TRANSFER'   => ['#58a6ff','rgba(88,166,255,.15)','bi-arrow-left-right','tr'],
      'CANTEEN'    => ['#bb8fce','rgba(187,143,206,.15)','bi-bag-fill','ct'],
    ];
    $signs = ['DEPOSIT'=>'+','WITHDRAWAL'=>'-','TRANSFER'=>'','CANTEEN'=>'-'];
    foreach ($transactions as $tx):
      [$tc,$tbg,$ti,$tcls] = $typeCfg[$tx['transaction_type']] ?? ['#8b949e','rgba(139,148,158,.15)','bi-circle',''];
      $sign = $signs[$tx['transaction_type']] ?? '';
      $initials = strtoupper(substr($tx['first_name'],0,1).substr($tx['last_name'],0,1));
      $hue = crc32($tx['first_name'].$tx['last_name']) % 360;
      $dtDisp = $tx['created_at'] ? date('M j, Y H:i', strtotime($tx['created_at'])) : '—';
      $bb = $tx['balance_before'] !== null ? '$'.number_format($tx['balance_before'],2) : '—';
      $ba = $tx['balance_after']  !== null ? '$'.number_format($tx['balance_after'],2)  : '—';
      $txJson = htmlspecialchars(json_encode([
        'id'   => $tx['id'],
        'type' => $tx['transaction_type'],
        'amount' => number_format($tx['amount'],2),
        'bb'   => number_format((float)($tx['balance_before']??0),2),
        'ba'   => number_format((float)($tx['balance_after']??0),2),
        'desc' => $tx['description'] ?? '',
        'inmate' => $tx['first_name'].' '.$tx['last_name'],
        'inmateId' => $tx['inmate_number'],
        'by'   => $tx['processed_by_name'] ?? 'System',
        'facility' => $tx['facility_name'] ?? '',
        'date' => $dtDisp,
        'clr'  => $tc,
        'icon' => $ti,
        'sign' => $sign,
      ]), ENT_QUOTES);
    ?>
      <tr onclick='openDetail(<?php echo $txJson; ?>)'>
        <td style="color:#8b949e;font-family:monospace;font-size:.75rem">#<?php echo $tx['id']; ?></td>
        <td>
          <div class="inmate-cell">
            <div class="avatar" style="background:hsl(<?php echo $hue; ?>,55%,38%)"><?php echo $initials; ?></div>
            <div>
              <div class="inmate-name"><?php echo htmlspecialchars($tx['first_name'].' '.$tx['last_name']); ?></div>
              <div class="inmate-id"><?php echo htmlspecialchars($tx['inmate_number']); ?></div>
            </div>
          </div>
        </td>
        <?php if ($isSA): ?>
        <td style="font-size:.78rem;color:#8b949e"><?php echo htmlspecialchars($tx['facility_name']); ?></td>
        <?php endif; ?>
        <td>
          <span class="type-badge" style="color:<?php echo $tc; ?>;background:<?php echo $tbg; ?>">
            <i class="bi <?php echo $ti; ?>"></i><?php echo $tx['transaction_type']; ?>
          </span>
        </td>
        <td><span class="amt <?php echo $tcls; ?>"><?php echo $sign; ?>$<?php echo number_format($tx['amount'],2); ?></span></td>
        <td class="bal-cell">
          <?php echo $bb; ?>
          <span class="bal-arrow">&#8594;</span>
          <span style="color:#e6edf3;font-weight:700"><?php echo $ba; ?></span>
        </td>
        <td class="desc-cell" title="<?php echo htmlspecialchars($tx['description'] ?? ''); ?>">
          <?php echo $tx['description'] ? htmlspecialchars($tx['description']) : '<span style="color:#484f58">—</span>'; ?>
        </td>
        <td class="meta-cell"><?php echo htmlspecialchars($tx['processed_by_name'] ?? 'System'); ?></td>
        <td class="date-cell"><?php echo $dtDisp; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
</div>

<!-- DETAIL MODAL -->
<div class="modal-ov" id="detailModal" onclick="if(event.target===this)closeDetail()">
  <div class="modal-box">
    <div class="modal-hdr">
      <h2 id="det_title"><i class="bi" id="det_icon"></i> Transaction Detail</h2>
      <button class="modal-close" onclick="closeDetail()"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="modal-body">
      <div class="amount-display" id="det_amount_block">
        <div id="det_amount" style="font-size:2rem;font-weight:800"></div>
        <div id="det_type_lbl" style="font-size:.78rem;font-weight:600;color:#8b949e;margin-top:.3rem;text-transform:uppercase;letter-spacing:.05em"></div>
      </div>
      <div class="balance-flow">
        <span><span style="font-size:.7rem;color:#8b949e">Before</span><br><span class="bb" id="det_bb"></span></span>
        <span class="arrow" style="font-size:1.2rem">&#8594;</span>
        <span><span style="font-size:.7rem;color:#8b949e">After</span><br><span class="ba" id="det_ba" style="font-weight:700;color:#e6edf3"></span></span>
      </div>
      <div id="det_rows"></div>
    </div>
  </div>
</div>

<script>
const typeIcons = {
  DEPOSIT:'bi-arrow-down-circle-fill',
  WITHDRAWAL:'bi-arrow-up-circle-fill',
  TRANSFER:'bi-arrow-left-right',
  CANTEEN:'bi-bag-fill'
};
function openDetail(tx) {
  document.getElementById('det_icon').className = 'bi ' + tx.icon;
  document.getElementById('det_icon').style.color = tx.clr;
  document.getElementById('det_title').innerHTML =
    `<i class="bi ${tx.icon}" style="color:${tx.clr}"></i> Transaction #${tx.id}`;
  document.getElementById('det_amount').textContent = tx.sign + '$' + tx.amount;
  document.getElementById('det_amount').style.color = tx.clr;
  document.getElementById('det_type_lbl').textContent = tx.type;
  document.getElementById('det_bb').textContent = '$' + tx.bb;
  document.getElementById('det_ba').textContent  = '$' + tx.ba;

  const rows = [
    ['Inmate',       tx.inmate],
    ['Inmate ID',    tx.inmateId],
    ['Type',         tx.type],
    ['Description',  tx.desc || '—'],
    ['Processed By', tx.by],
    <?php if ($isSA): ?>
    ['Facility',     tx.facility],
    <?php endif; ?>
    ['Date & Time',  tx.date],
  ];
  document.getElementById('det_rows').innerHTML = rows.map(([l,v]) =>
    `<div class="detail-row">
      <span class="detail-lbl">${l}</span>
      <span class="detail-val">${v}</span>
    </div>`
  ).join('');
  document.getElementById('detailModal').classList.add('open');
}
function closeDetail() {
  document.getElementById('detailModal').classList.remove('open');
}
</script>

<?php require_once '../../includes/footer.php'; ?>
