<?php
$pageTitle = 'Finance Overview';
require_once '../../includes/header.php';
requireAuth();
verifyPermission('view', 'finance');

$fid  = getCurrentFacility();
$isSA = isSuperAdmin();
$uid  = getCurrentUserId();

$facCl      = $isSA ? "ia.deleted_at IS NULL" : "ia.deleted_at IS NULL AND ia.facility_id=$fid";
$facClPlain = $isSA ? "deleted_at IS NULL" : "deleted_at IS NULL AND facility_id=$fid";
$facClTx    = $isSA ? "it.deleted_at IS NULL" : "it.deleted_at IS NULL AND it.facility_id=$fid";

/* POST: quick deposit / withdrawal */
$flash = ''; $flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (in_array($action, ['deposit','withdrawal'])) {
        $accId  = (int)($_POST['account_id'] ?? 0);
        $amount = trim($_POST['amount'] ?? '');
        $desc   = trim($_POST['description'] ?? '');
        $errs   = [];
        $acc    = null;

        if (!$accId) $errs[] = 'Account required.';
        if (!is_numeric($amount) || (float)$amount <= 0) $errs[] = 'Amount must be a positive number.';

        if (!$errs) {
            $acc = fetchOne("SELECT id,account_balance,account_status,facility_id FROM inmate_accounts WHERE id=? AND deleted_at IS NULL", [$accId], 'i');
            if (!$acc || (!$isSA && $acc['facility_id'] != $fid)) $errs[] = 'Account not found.';
            elseif ($acc['account_status'] !== 'ACTIVE') $errs[] = 'Account is not active.';
            elseif ($action === 'withdrawal' && (float)$acc['account_balance'] < (float)$amount) $errs[] = 'Insufficient balance.';
        }

        if (!$errs) {
            $before = (float)$acc['account_balance'];
            $amt    = (float)$amount;
            $after  = $action === 'deposit' ? $before + $amt : $before - $amt;
            $type   = strtoupper($action);
            executeQuery("UPDATE inmate_accounts SET account_balance=?,updated_at=NOW() WHERE id=?", [$after,$accId], 'di');
            executeQuery(
                "INSERT INTO inmate_transactions (facility_id,account_id,transaction_type,amount,balance_before,balance_after,description,processed_by,created_at,updated_at)
                 VALUES (?,?,'$type',?,?,?,?,?,NOW(),NOW())",
                [$isSA ? $acc['facility_id'] : $fid, $accId, $amt, $before, $after, $desc ?: null, $uid],
                'iiddds'.($uid?'i':'s')
            );
            $flash = ucfirst($action).' of $'.number_format($amt,2).' processed.';
        } else { $flash = implode(' ',$errs); $flashType='error'; }
    }

    if (!$flash || $flashType==='success') {
        $redir = '?';
        if ($flash) $redir .= 'msg='.urlencode($flash).'&';
        header('Location: '.$redir); exit;
    }
}

if (!$flash && !empty($_GET['msg'])) { $flash = $_GET['msg']; $flashType='success'; }

/* KPIs */
$kpiRows = fetchAll("SELECT account_status,COUNT(*) cnt,COALESCE(SUM(account_balance),0) bal FROM inmate_accounts WHERE $facClPlain GROUP BY account_status", [], '');
$km = [];
foreach ($kpiRows as $r) $km[$r['account_status']] = $r;

$txStats = fetchAll("SELECT transaction_type,COUNT(*) cnt,COALESCE(SUM(amount),0) total FROM inmate_transactions WHERE $facClPlain GROUP BY transaction_type", [], '');
$txm = [];
foreach ($txStats as $r) $txm[$r['transaction_type']] = $r;

$todayTx = fetchOne("SELECT COUNT(*) cnt,COALESCE(SUM(amount),0) vol FROM inmate_transactions WHERE $facClPlain AND DATE(created_at)=CURDATE()", [], '');

$kpis = [
    'total'      => array_sum(array_column($kpiRows,'cnt')),
    'active'     => $km['ACTIVE']['cnt']    ?? 0,
    'suspended'  => $km['SUSPENDED']['cnt'] ?? 0,
    'closed'     => $km['CLOSED']['cnt']    ?? 0,
    'bal'        => ($km['ACTIVE']['bal'] ?? 0) + ($km['SUSPENDED']['bal'] ?? 0),
    'deposits'   => $txm['DEPOSIT']['total']    ?? 0,
    'withdrawals'=> $txm['WITHDRAWAL']['total'] ?? 0,
    'tx_today'   => $todayTx['cnt'] ?? 0,
    'vol_today'  => $todayTx['vol'] ?? 0,
];

/* Accounts list */
$search = trim($_GET['q'] ?? '');
$srchCl = '';
if ($search) {
    $esc = addslashes($search);
    $srchCl = " AND (i.first_name LIKE '%$esc%' OR i.last_name LIKE '%$esc%' OR i.inmate_id LIKE '%$esc%')";
}
$accounts = fetchAll(
    "SELECT ia.id, ia.account_balance, ia.account_status, ia.facility_id,
            i.first_name, i.last_name, i.inmate_id AS inmate_number,
            f.name facility_name
     FROM inmate_accounts ia
     JOIN inmates i ON ia.inmate_id=i.id
     JOIN facilities f ON ia.facility_id=f.id
     WHERE $facCl$srchCl
     ORDER BY ia.account_balance DESC LIMIT 50",
    [], ''
);

/* Recent transactions */
$recentTx = fetchAll(
    "SELECT it.transaction_type, it.amount, it.balance_after, it.description, it.created_at,
            i.first_name, i.last_name, i.inmate_id AS inmate_number
     FROM inmate_transactions it
     JOIN inmate_accounts ia ON it.account_id=ia.id
     JOIN inmates i ON ia.inmate_id=i.id
     WHERE $facClTx
     ORDER BY it.created_at DESC LIMIT 10",
    [], ''
);

$canEdit = hasPermission('edit','finance') || hasPermission('create','finance');
?>
<style>
:root{--sur:#161b22;--bdr:#21262d;--bg:#0d1117;--txt:#e6edf3;--mut:#8b949e;--acc:#1f6feb}
.ph{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:.75rem}
.ph h1{font-size:1.35rem;font-weight:700;color:var(--txt);margin:0}
.bc{font-size:.78rem;color:#6e7681;display:flex;align-items:center;gap:.3rem;margin-bottom:.3rem}
.bc a{color:#388bfd;text-decoration:none}.bc a:hover{text-decoration:underline}
.ph-actions{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap}
.btn-ghost{display:inline-flex;align-items:center;gap:.4rem;padding:.42rem .85rem;border-radius:9px;border:1px solid #30363d;background:transparent;color:#c9d1d9;font-size:.82rem;font-weight:600;cursor:pointer;text-decoration:none;transition:all .18s}
.btn-ghost:hover{background:#21262d;border-color:#388bfd;color:#388bfd}
.btn-primary-sm{display:inline-flex;align-items:center;gap:.4rem;padding:.42rem .9rem;border-radius:9px;border:none;background:#1f6feb;color:#fff;font-size:.82rem;font-weight:600;cursor:pointer;transition:all .18s}
.btn-primary-sm:hover{background:#388bfd;transform:translateY(-1px)}
.kpi-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem}
@media(max-width:1000px){.kpi-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:560px){.kpi-grid{grid-template-columns:1fr 1fr}}
.kpi{background:var(--sur);border:1px solid var(--bdr);border-radius:14px;padding:1.1rem 1.2rem;transition:transform .18s,box-shadow .18s}
.kpi:hover{transform:translateY(-2px);box-shadow:0 6px 24px rgba(0,0,0,.35)}
.kpi-ico{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1rem;margin-bottom:.75rem}
.kpi-val{font-size:1.55rem;font-weight:800;color:var(--txt);line-height:1;margin-bottom:.25rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.kpi-lbl{font-size:.72rem;font-weight:600;color:var(--mut);text-transform:uppercase;letter-spacing:.05em}
.kpi-sub{font-size:.7rem;color:#8b949e;margin-top:.2rem}
.two-col{display:grid;grid-template-columns:1fr 360px;gap:1.25rem;align-items:start}
@media(max-width:960px){.two-col{grid-template-columns:1fr}}
.card{background:var(--sur);border:1px solid var(--bdr);border-radius:14px;overflow:hidden}
.card-head{display:flex;align-items:center;justify-content:space-between;padding:.9rem 1.1rem;border-bottom:1px solid var(--bdr)}
.card-title{font-size:.88rem;font-weight:700;color:var(--txt);display:flex;align-items:center;gap:.45rem}
.card-head a{font-size:.75rem;color:#388bfd;text-decoration:none;font-weight:600}
.card-head a:hover{text-decoration:underline}
.tbl-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:.85rem}
th{background:#0d1117;color:var(--mut);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;padding:.65rem 1rem;text-align:left;white-space:nowrap;border-bottom:1px solid var(--bdr)}
td{padding:.7rem 1rem;border-bottom:1px solid #1c2128;color:var(--txt);vertical-align:middle}
tr:last-child td{border-bottom:none}
tr:hover td{background:rgba(255,255,255,.025)}
.inmate-cell{display:flex;align-items:center;gap:.6rem}
.avatar{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:700;flex-shrink:0;color:#fff}
.inmate-name{font-weight:600;font-size:.84rem;color:var(--txt);line-height:1.2}
.inmate-id{font-size:.7rem;color:#8b949e;font-family:monospace}
.bal{font-weight:800;font-size:.92rem}
.bal.pos{color:#3fb950}
.bal.zero{color:#8b949e}
.chip{display:inline-flex;align-items:center;gap:.28rem;padding:2px 9px;border-radius:20px;font-size:.68rem;font-weight:700}
.actions-cell{display:flex;gap:.3rem}
.ic-btn{width:30px;height:30px;border-radius:7px;border:1px solid #30363d;background:transparent;color:#8b949e;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;font-size:.8rem;transition:all .18s;text-decoration:none}
.ic-btn.dep:hover{border-color:#3fb950;color:#3fb950;background:rgba(63,185,80,.08)}
.ic-btn.wd:hover{border-color:#f85149;color:#f85149;background:rgba(248,81,73,.08)}
.ic-btn.view:hover{border-color:#388bfd;color:#388bfd;background:rgba(56,139,253,.08)}
.toolbar{display:flex;align-items:center;gap:.6rem;padding:.75rem 1rem;border-bottom:1px solid var(--bdr);background:#0d1117}
.search-wrap{position:relative;flex:1;min-width:0}
.search-wrap i{position:absolute;left:.7rem;top:50%;transform:translateY(-50%);color:#484f58;font-size:.8rem;pointer-events:none}
.search-wrap input{width:100%;background:#161b22;border:1px solid #30363d;color:var(--txt);padding:.45rem .8rem .45rem 2rem;border-radius:8px;font-size:.82rem;outline:none;transition:border .2s;box-sizing:border-box}
.search-wrap input::placeholder{color:#484f58}
.search-wrap input:focus{border-color:#388bfd;box-shadow:0 0 0 3px rgba(56,139,253,.12)}
.count-lbl{font-size:.75rem;color:#8b949e;white-space:nowrap}
.tx-list{padding:.5rem .8rem;display:flex;flex-direction:column;gap:.45rem;max-height:480px;overflow-y:auto}
.tx-item{display:flex;align-items:center;gap:.7rem;padding:.65rem .75rem;border-radius:10px;background:#0d1117;border:1px solid #1c2128;transition:background .14s}
.tx-item:hover{background:#161b22}
.tx-ico{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.82rem;flex-shrink:0}
.tx-info{flex:1;min-width:0}
.tx-name{font-size:.8rem;font-weight:700;color:#e6edf3;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.tx-meta{font-size:.7rem;color:#8b949e}
.tx-amt{font-weight:800;font-size:.88rem;white-space:nowrap}
.tx-bal{font-size:.68rem;color:#8b949e;text-align:right}
.empty-state{text-align:center;padding:2.5rem 1rem;color:#8b949e}
.empty-state i{font-size:2.2rem;display:block;margin-bottom:.75rem;opacity:.4}
.empty-state p{font-size:.85rem;margin:0}
.flash{display:flex;align-items:center;gap:.6rem;padding:.75rem 1.1rem;border-radius:10px;margin-bottom:1.25rem;font-size:.875rem;font-weight:500}
.flash.success{background:rgba(63,185,80,.12);border:1px solid rgba(63,185,80,.3);color:#3fb950}
.flash.error{background:rgba(248,81,73,.12);border:1px solid rgba(248,81,73,.3);color:#f85149}

.modal-ov{opacity:0;pointer-events:none;transition:opacity .22s}
.modal-ov.open{opacity:1;pointer-events:auto}
.modal-box{background:#161b22;border:1px solid #30363d;border-radius:16px;width:100%;max-width:420px;box-shadow:0 20px 60px rgba(0,0,0,.5)}

.modal-hdr{display:flex;align-items:center;justify-content:space-between;padding:1.1rem 1.25rem;border-bottom:1px solid #21262d}
.modal-head h3{font-size:1rem;font-weight:700;color:#e6edf3;margin:0;display:flex;align-items:center;gap:.5rem}
.modal-close{width:30px;height:30px;border-radius:7px;border:1px solid #30363d;background:transparent;color:#8b949e;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.85rem;transition:all .18s}
.modal-close:hover{background:#21262d;color:#e6edf3}
.modal-body{padding:1.25rem}
.form-group{margin-bottom:1rem}
.form-group label{display:block;font-size:.78rem;font-weight:600;color:#8b949e;margin-bottom:.4rem;text-transform:uppercase;letter-spacing:.04em}
.form-group input,.form-group select{width:100%;background:#0d1117;border:1px solid #30363d;color:#e6edf3;padding:.55rem .8rem;border-radius:9px;font-size:.875rem;outline:none;transition:border .2s,box-shadow .2s;box-sizing:border-box}
.form-group input:focus,.form-group select:focus{border-color:#388bfd;box-shadow:0 0 0 3px rgba(56,139,253,.12)}
.form-group input::placeholder{color:#484f58}
.modal-footer{display:flex;gap:.6rem;justify-content:flex-end;padding:1rem 1.25rem;border-top:1px solid #21262d}
.acc-info-bar{background:#0d1117;border:1px solid #21262d;border-radius:10px;padding:.75rem 1rem;margin-bottom:1rem;display:flex;align-items:center;gap:.75rem}
.acc-info-bar .aname{font-weight:700;color:#e6edf3;font-size:.88rem}
.acc-info-bar .bal-lbl{font-size:.7rem;color:#8b949e;margin-top:.1rem}
.acc-info-bar .bal-val{font-size:1rem;font-weight:800;color:#3fb950;margin-left:auto}
.mini-grid{display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:.75rem}
</style>

<!-- PAGE HEADER -->
<div class="ph">
  <div>
    <div class="bc">
      <a href="<?php echo APP_URL; ?>/dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
      <i class="bi bi-chevron-right" style="font-size:.6rem"></i>
      <span>Finance</span>
    </div>
    <h1><i class="bi bi-bank" style="color:#388bfd;margin-right:.45rem"></i>Finance Overview</h1>
  </div>
  <div class="ph-actions">
    <a href="<?php echo APP_URL; ?>/modules/finance/accounts.php" class="btn-ghost">
      <i class="bi bi-wallet2"></i> Manage Accounts
    </a>
    <a href="<?php echo APP_URL; ?>/modules/finance/transactions.php" class="btn-ghost">
      <i class="bi bi-arrow-left-right"></i> Transactions
    </a>
    <?php if ($canEdit): ?>
    <button class="btn-primary-sm" onclick="openDeposit()" style="background:linear-gradient(135deg,#238636,#3fb950)">
      <i class="bi bi-arrow-down-circle-fill"></i> Quick Deposit
    </button>
    <button class="btn-primary-sm" onclick="openWithdraw()" style="background:linear-gradient(135deg,#b91c1c,#f85149)">
      <i class="bi bi-arrow-up-circle-fill"></i> Quick Withdraw
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

<!-- KPI ROW -->
<div class="kpi-grid">
  <div class="kpi" style="border-top:3px solid #388bfd">
    <div class="kpi-ico" style="background:rgba(56,139,253,.15);color:#388bfd"><i class="bi bi-people-fill"></i></div>
    <div class="kpi-val"><?php echo $kpis['total']; ?></div>
    <div class="kpi-lbl">Total Accounts</div>
    <div class="kpi-sub"><?php echo $kpis['active']; ?> active &middot; <?php echo $kpis['suspended']; ?> suspended</div>
  </div>
  <div class="kpi" style="border-top:3px solid #bb8fce">
    <div class="kpi-ico" style="background:rgba(187,143,206,.15);color:#bb8fce"><i class="bi bi-cash-coin"></i></div>
    <div class="kpi-val" style="font-size:1.15rem">$<?php echo number_format($kpis['bal'],2); ?></div>
    <div class="kpi-lbl">Total Balance</div>
    <div class="kpi-sub">Active + suspended accounts</div>
  </div>
  <div class="kpi" style="border-top:3px solid #3fb950">
    <div class="kpi-ico" style="background:rgba(63,185,80,.15);color:#3fb950"><i class="bi bi-arrow-down-circle-fill"></i></div>
    <div class="kpi-val" style="font-size:1.15rem">$<?php echo number_format($kpis['deposits'],2); ?></div>
    <div class="kpi-lbl">Total Deposits</div>
    <div class="kpi-sub"><?php echo $txm['DEPOSIT']['cnt'] ?? 0; ?> transaction<?php echo ($txm['DEPOSIT']['cnt']??0)!=1?'s':''; ?></div>
  </div>
  <div class="kpi" style="border-top:3px solid #f85149">
    <div class="kpi-ico" style="background:rgba(248,81,73,.15);color:#f85149"><i class="bi bi-arrow-up-circle-fill"></i></div>
    <div class="kpi-val" style="font-size:1.15rem">$<?php echo number_format($kpis['withdrawals'],2); ?></div>
    <div class="kpi-lbl">Total Withdrawals</div>
    <div class="kpi-sub"><?php echo $txm['WITHDRAWAL']['cnt'] ?? 0; ?> transaction<?php echo ($txm['WITHDRAWAL']['cnt']??0)!=1?'s':''; ?></div>
  </div>
</div>

<!-- TWO COLUMN -->
<div class="two-col">

  <!-- LEFT: accounts table -->
  <div class="card">
    <div class="card-head">
      <div class="card-title"><i class="bi bi-wallet2" style="color:#388bfd"></i> Inmate Accounts</div>
      <a href="<?php echo APP_URL; ?>/modules/finance/accounts.php">View all &rarr;</a>
    </div>
    <div class="toolbar">
      <form method="GET" style="display:contents">
        <div class="search-wrap">
          <i class="bi bi-search"></i>
          <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>"
                 placeholder="Search name or inmate ID…" onchange="this.form.submit()">
        </div>
      </form>
      <span class="count-lbl"><?php echo count($accounts); ?> shown</span>
    </div>
    <?php if (empty($accounts)): ?>
    <div class="empty-state"><i class="bi bi-wallet"></i><p>No accounts found<?php echo $search?' for your search':''; ?>.</p></div>
    <?php else: ?>
    <div class="tbl-wrap">
      <table>
        <thead>
          <tr>
            <th>Inmate</th>
            <?php if ($isSA): ?><th>Facility</th><?php endif; ?>
            <th>Balance</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($accounts as $acc):
          $initials = strtoupper(substr($acc['first_name'],0,1).substr($acc['last_name'],0,1));
          $hue = crc32($acc['first_name'].$acc['last_name']) % 360;
          $bal = (float)$acc['account_balance'];
          $sCfg = [
            'ACTIVE'    => ['#3fb950','rgba(63,185,80,.15)','check-circle-fill'],
            'SUSPENDED' => ['#f39c12','rgba(243,156,18,.15)','pause-circle-fill'],
            'CLOSED'    => ['#f85149','rgba(248,81,73,.15)','x-circle-fill'],
          ];
          [$sc,$sbg,$si] = $sCfg[$acc['account_status']] ?? ['#8b949e','rgba(139,148,158,.15)','circle'];
        ?>
          <tr>
            <td>
              <div class="inmate-cell">
                <div class="avatar" style="background:hsl(<?php echo $hue; ?>,55%,38%)"><?php echo $initials; ?></div>
                <div>
                  <div class="inmate-name"><?php echo htmlspecialchars($acc['first_name'].' '.$acc['last_name']); ?></div>
                  <div class="inmate-id"><?php echo htmlspecialchars($acc['inmate_number']); ?></div>
                </div>
              </div>
            </td>
            <?php if ($isSA): ?><td style="font-size:.78rem;color:#8b949e"><?php echo htmlspecialchars($acc['facility_name']); ?></td><?php endif; ?>
            <td><span class="bal <?php echo $bal>0?'pos':'zero'; ?>">$<?php echo number_format($bal,2); ?></span></td>
            <td>
              <span class="chip" style="color:<?php echo $sc; ?>;background:<?php echo $sbg; ?>">
                <i class="bi bi-<?php echo $si; ?>"></i><?php echo $acc['account_status']; ?>
              </span>
            </td>
            <td>
              <div class="actions-cell">
                <a href="<?php echo APP_URL; ?>/modules/finance/accounts.php?q=<?php echo urlencode($acc['inmate_number']); ?>" class="ic-btn view" title="View"><i class="bi bi-eye"></i></a>
                <?php if ($canEdit && $acc['account_status']==='ACTIVE'): ?>
                <button class="ic-btn dep" title="Deposit"
                  onclick="openDeposit(<?php echo $acc['id']; ?>,'<?php echo htmlspecialchars(addslashes($acc['first_name'].' '.$acc['last_name'])); ?>','<?php echo number_format($bal,2); ?>')">
                  <i class="bi bi-arrow-down-circle"></i>
                </button>
                <button class="ic-btn wd" title="Withdraw"
                  onclick="openWithdraw(<?php echo $acc['id']; ?>,'<?php echo htmlspecialchars(addslashes($acc['first_name'].' '.$acc['last_name'])); ?>','<?php echo number_format($bal,2); ?>')">
                  <i class="bi bi-arrow-up-circle"></i>
                </button>
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

  <!-- RIGHT: mini stats + recent tx -->
  <div>
    <div class="mini-grid">
      <div class="kpi" style="border-top:3px solid #388bfd;padding:.85rem 1rem">
        <div style="font-size:.68rem;font-weight:700;color:#8b949e;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.3rem">Active</div>
        <div style="font-size:1.45rem;font-weight:800;color:#e6edf3"><?php echo $kpis['active']; ?></div>
      </div>
      <div class="kpi" style="border-top:3px solid <?php echo $kpis['suspended']>0?'#f39c12':'#30363d'; ?>;padding:.85rem 1rem">
        <div style="font-size:.68rem;font-weight:700;color:#8b949e;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.3rem">Suspended</div>
        <div style="font-size:1.45rem;font-weight:800;color:<?php echo $kpis['suspended']>0?'#f39c12':'#8b949e'; ?>"><?php echo $kpis['suspended']; ?></div>
      </div>
      <div class="kpi" style="border-top:3px solid #3fb950;padding:.85rem 1rem">
        <div style="font-size:.68rem;font-weight:700;color:#8b949e;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.3rem">Today's Tx</div>
        <div style="font-size:1.45rem;font-weight:800;color:#3fb950"><?php echo $kpis['tx_today']; ?></div>
      </div>
      <div class="kpi" style="border-top:3px solid #bb8fce;padding:.85rem 1rem">
        <div style="font-size:.68rem;font-weight:700;color:#8b949e;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.3rem">Today Vol</div>
        <div style="font-size:1rem;font-weight:800;color:#bb8fce">$<?php echo number_format($kpis['vol_today'],2); ?></div>
      </div>
    </div>

    <div class="card">
      <div class="card-head">
        <div class="card-title"><i class="bi bi-clock-history" style="color:#f39c12"></i> Recent Transactions</div>
        <a href="<?php echo APP_URL; ?>/modules/finance/accounts.php">Accounts &rarr;</a>
      </div>
      <?php if (empty($recentTx)): ?>
      <div class="empty-state" style="padding:1.5rem 1rem"><i class="bi bi-inbox"></i><p>No transactions yet.</p></div>
      <?php else: ?>
      <div class="tx-list">
        <?php
        $txCfg = [
          'DEPOSIT'    => ['#3fb950','rgba(63,185,80,.15)','bi-arrow-down-circle-fill','+'],
          'WITHDRAWAL' => ['#f85149','rgba(248,81,73,.15)','bi-arrow-up-circle-fill','-'],
          'TRANSFER'   => ['#388bfd','rgba(56,139,253,.15)','bi-arrow-left-right',''],
          'CANTEEN'    => ['#bb8fce','rgba(187,143,206,.15)','bi-bag-fill','-'],
        ];
        foreach ($recentTx as $tx):
          [$tc,$tbg,$ti,$sign] = $txCfg[$tx['transaction_type']] ?? ['#8b949e','rgba(139,148,158,.15)','bi-circle',''];
          $diff = time() - strtotime($tx['created_at']);
          if ($diff < 60) $ago = 'just now';
          elseif ($diff < 3600) $ago = floor($diff/60).'m ago';
          elseif ($diff < 86400) $ago = floor($diff/3600).'h ago';
          else $ago = date('M j', strtotime($tx['created_at']));
        ?>
        <div class="tx-item">
          <div class="tx-ico" style="background:<?php echo $tbg; ?>;color:<?php echo $tc; ?>"><i class="bi <?php echo $ti; ?>"></i></div>
          <div class="tx-info">
            <div class="tx-name"><?php echo htmlspecialchars($tx['first_name'].' '.$tx['last_name']); ?></div>
            <div class="tx-meta"><?php echo $tx['transaction_type']; ?> &middot; <?php echo htmlspecialchars($tx['inmate_number']); ?> &middot; <?php echo $ago; ?></div>
          </div>
          <div style="text-align:right;flex-shrink:0">
            <div class="tx-amt" style="color:<?php echo $tc; ?>"><?php echo $sign; ?>$<?php echo number_format($tx['amount'],2); ?></div>
            <div class="tx-bal">Bal $<?php echo number_format($tx['balance_after'],2); ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

</div>

<?php if ($canEdit): ?>
<!-- DEPOSIT MODAL -->
<div class="modal-ov" id="depositModal">
  <div class="modal-box">
    <div class="modal-hdr">
      <h3><i class="bi bi-arrow-down-circle-fill" style="color:#3fb950"></i> Deposit Funds</h3>
      <button class="modal-close" onclick="closeModal('depositModal')"><i class="bi bi-x-lg"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="deposit">
      <input type="hidden" name="account_id" id="dep_hidden_id">
      <div class="modal-body">
        <div class="acc-info-bar" id="dep_info_bar" style="display:none">
          <div><div class="aname" id="dep_info_name"></div><div class="bal-lbl">Current balance</div></div>
          <div class="bal-val" id="dep_info_bal"></div>
        </div>
        <div class="form-group" id="dep_sel_wrap">
          <label>Select Account</label>
          <select id="dep_sel" name="account_id">
            <option value="">— choose active account —</option>
            <?php foreach ($accounts as $a): if ($a['account_status']==='ACTIVE'): ?>
            <option value="<?php echo $a['id']; ?>"
                    data-name="<?php echo htmlspecialchars($a['first_name'].' '.$a['last_name']); ?>"
                    data-bal="<?php echo number_format((float)$a['account_balance'],2); ?>">
              <?php echo htmlspecialchars($a['inmate_number'].' — '.$a['first_name'].' '.$a['last_name'].' ($'.number_format((float)$a['account_balance'],2).')'); ?>
            </option>
            <?php endif; endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Amount ($)</label>
          <input type="number" name="amount" min="0.01" step="0.01" placeholder="0.00" required>
        </div>
        <div class="form-group">
          <label>Description (optional)</label>
          <input type="text" name="description" placeholder="e.g. Family transfer" maxlength="255">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-ghost" onclick="closeModal('depositModal')">Cancel</button>
        <button type="submit" class="btn-primary-sm" style="background:linear-gradient(135deg,#238636,#3fb950)"><i class="bi bi-arrow-down-circle-fill"></i> Deposit</button>
      </div>
    </form>
  </div>
</div>

<!-- WITHDRAW MODAL -->
<div class="modal-ov" id="withdrawModal">
  <div class="modal-box">
    <div class="modal-hdr">
      <h3><i class="bi bi-arrow-up-circle-fill" style="color:#f85149"></i> Withdraw Funds</h3>
      <button class="modal-close" onclick="closeModal('withdrawModal')"><i class="bi bi-x-lg"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="withdrawal">
      <input type="hidden" name="account_id" id="wd_hidden_id">
      <div class="modal-body">
        <div class="acc-info-bar" id="wd_info_bar" style="display:none">
          <div><div class="aname" id="wd_info_name"></div><div class="bal-lbl">Current balance</div></div>
          <div class="bal-val" id="wd_info_bal"></div>
        </div>
        <div class="form-group" id="wd_sel_wrap">
          <label>Select Account</label>
          <select id="wd_sel" name="account_id">
            <option value="">— choose active account —</option>
            <?php foreach ($accounts as $a): if ($a['account_status']==='ACTIVE'): ?>
            <option value="<?php echo $a['id']; ?>"
                    data-name="<?php echo htmlspecialchars($a['first_name'].' '.$a['last_name']); ?>"
                    data-bal="<?php echo number_format((float)$a['account_balance'],2); ?>">
              <?php echo htmlspecialchars($a['inmate_number'].' — '.$a['first_name'].' '.$a['last_name'].' ($'.number_format((float)$a['account_balance'],2).')'); ?>
            </option>
            <?php endif; endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Amount ($)</label>
          <input type="number" name="amount" min="0.01" step="0.01" placeholder="0.00" required>
        </div>
        <div class="form-group">
          <label>Description (optional)</label>
          <input type="text" name="description" placeholder="e.g. Legal fee" maxlength="255">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-ghost" onclick="closeModal('withdrawModal')">Cancel</button>
        <button type="submit" class="btn-primary-sm" style="background:linear-gradient(135deg,#b91c1c,#f85149)"><i class="bi bi-arrow-up-circle-fill"></i> Withdraw</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
function openDeposit(id, name, bal) {
  const m       = document.getElementById('depositModal');
  const selWrap = document.getElementById('dep_sel_wrap');
  const infoBar = document.getElementById('dep_info_bar');
  const hidden  = document.getElementById('dep_hidden_id');
  const sel     = document.getElementById('dep_sel');
  if (id) {
    hidden.value  = id; sel.name = '_x';
    selWrap.style.display = 'none';
    document.getElementById('dep_info_name').textContent = name;
    document.getElementById('dep_info_bal').textContent  = '$'+bal;
    infoBar.style.display = 'flex';
  } else {
    hidden.value = ''; sel.name = 'account_id';
    selWrap.style.display = ''; infoBar.style.display = 'none'; sel.value = '';
  }
  m.classList.add('open');
}
function openWithdraw(id, name, bal) {
  const m       = document.getElementById('withdrawModal');
  const selWrap = document.getElementById('wd_sel_wrap');
  const infoBar = document.getElementById('wd_info_bar');
  const hidden  = document.getElementById('wd_hidden_id');
  const sel     = document.getElementById('wd_sel');
  if (id) {
    hidden.value = id; sel.name = '_x';
    selWrap.style.display = 'none';
    document.getElementById('wd_info_name').textContent = name;
    document.getElementById('wd_info_bal').textContent  = '$'+bal;
    infoBar.style.display = 'flex';
  } else {
    hidden.value = ''; sel.name = 'account_id';
    selWrap.style.display = ''; infoBar.style.display = 'none'; sel.value = '';
  }
  m.classList.add('open');
}
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-ov').forEach(m => m.addEventListener('click', e => { if(e.target===m) m.classList.remove('open'); }));

/* Show account info when dropdown changes */
['dep','wd'].forEach(pfx => {
  const sel = document.getElementById(pfx+'_sel');
  if (!sel) return;
  sel.addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    if (opt.value) {
      document.getElementById(pfx+'_info_name').textContent = opt.dataset.name;
      document.getElementById(pfx+'_info_bal').textContent  = '$'+opt.dataset.bal;
      document.getElementById(pfx+'_info_bar').style.display = 'flex';
    } else {
      document.getElementById(pfx+'_info_bar').style.display = 'none';
    }
  });
});
</script>

<?php require_once '../../includes/footer.php'; ?>
