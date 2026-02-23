<?php
$pageTitle = 'Inmate Accounts';
require_once '../../includes/header.php';
requireAuth();
verifyPermission('view', 'finance');

$fid  = getCurrentFacility();
$isSA = isSuperAdmin();
$uid  = getCurrentUserId();

/* ══════════ FACILITY CLAUSE ══════════ */
$facCl  = $isSA ? "ia.deleted_at IS NULL" : "ia.deleted_at IS NULL AND ia.facility_id=$fid";
$facClT = $isSA ? "it.deleted_at IS NULL" : "it.deleted_at IS NULL AND it.facility_id=$fid";
$facClPlain = $isSA ? "deleted_at IS NULL" : "deleted_at IS NULL AND facility_id=$fid";

/* ══════════ POST HANDLER ══════════ */
$flash = ''; $flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';

    /* ── DEPOSIT ── */
    if ($action === 'deposit') {
        $accId  = (int)($_POST['account_id'] ?? 0);
        $amount = trim($_POST['amount']      ?? '');
        $desc   = trim($_POST['description'] ?? '');

        $errs = [];
        $acc  = null;
        if (!$accId)  $errs[] = 'Account required.';
        if (!is_numeric($amount) || (float)$amount <= 0) $errs[] = 'Amount must be a positive number.';

        if (!$errs) {
            $acc = fetchOne("SELECT id,account_balance,account_status,facility_id FROM inmate_accounts WHERE id=? AND deleted_at IS NULL", [$accId], 'i');
            if (!$acc || (!$isSA && $acc['facility_id'] != $fid)) $errs[] = 'Account not found.';
            elseif ($acc['account_status'] !== 'ACTIVE') $errs[] = 'Can only deposit to ACTIVE accounts.';
        }
        if (!$errs) {
            $before = (float)$acc['account_balance'];
            $amt    = (float)$amount;
            $after  = $before + $amt;
            executeQuery("UPDATE inmate_accounts SET account_balance=?, updated_at=NOW() WHERE id=?", [$after, $accId], 'di');
            executeQuery(
                "INSERT INTO inmate_transactions (facility_id,account_id,transaction_type,amount,balance_before,balance_after,description,processed_by,created_at,updated_at)
                 VALUES (?,?,'DEPOSIT',?,?,?,?,?,NOW(),NOW())",
                [$isSA ? $acc['facility_id'] : $fid, $accId, $amt, $before, $after, $desc ?: null, $uid],
                'iiddds' . ($uid ? 'i' : 's')
            );
            $flash = 'Deposit of $'.number_format($amt,2).' processed successfully.';
        } else { $flash = implode(' ', $errs); $flashType = 'error'; }
    }

    /* ── WITHDRAWAL ── */
    if ($action === 'withdrawal') {
        $accId  = (int)($_POST['account_id'] ?? 0);
        $amount = trim($_POST['amount']      ?? '');
        $desc   = trim($_POST['description'] ?? '');

        $errs = [];
        $acc  = null;
        if (!$accId)  $errs[] = 'Account required.';
        if (!is_numeric($amount) || (float)$amount <= 0) $errs[] = 'Amount must be a positive number.';

        if (!$errs) {
            $acc = fetchOne("SELECT id,account_balance,account_status,facility_id FROM inmate_accounts WHERE id=? AND deleted_at IS NULL", [$accId], 'i');
            if (!$acc || (!$isSA && $acc['facility_id'] != $fid)) $errs[] = 'Account not found.';
            elseif ($acc['account_status'] !== 'ACTIVE') $errs[] = 'Can only withdraw from ACTIVE accounts.';
            elseif ((float)$acc['account_balance'] < (float)$amount) $errs[] = 'Insufficient balance.';
        }
        if (!$errs) {
            $before = (float)$acc['account_balance'];
            $amt    = (float)$amount;
            $after  = $before - $amt;
            executeQuery("UPDATE inmate_accounts SET account_balance=?, updated_at=NOW() WHERE id=?", [$after, $accId], 'di');
            executeQuery(
                "INSERT INTO inmate_transactions (facility_id,account_id,transaction_type,amount,balance_before,balance_after,description,processed_by,created_at,updated_at)
                 VALUES (?,?,'WITHDRAWAL',?,?,?,?,?,NOW(),NOW())",
                [$isSA ? $acc['facility_id'] : $fid, $accId, $amt, $before, $after, $desc ?: null, $uid],
                'iiddds' . ($uid ? 'i' : 's')
            );
            $flash = 'Withdrawal of $'.number_format($amt,2).' processed successfully.';
        } else { $flash = implode(' ', $errs); $flashType = 'error'; }
    }

    /* ── SET STATUS ── */
    if ($action === 'set_status') {
        $accId  = (int)($_POST['account_id'] ?? 0);
        $status = trim($_POST['new_status']  ?? '');
        $allowed = ['ACTIVE','SUSPENDED','CLOSED'];
        $errs = [];
        if (!in_array($status, $allowed) || !$accId) $errs[] = 'Invalid request.';
        if (!$errs) {
            $acc = fetchOne("SELECT facility_id FROM inmate_accounts WHERE id=? AND deleted_at IS NULL", [$accId], 'i');
            if (!$acc || (!$isSA && $acc['facility_id'] != $fid)) $errs[] = 'Account not found.';
        }
        if (!$errs) {
            executeQuery("UPDATE inmate_accounts SET account_status=?,updated_at=NOW() WHERE id=?", [$status,$accId], 'si');
            $flash = 'Account status updated to '.strtolower($status).'.';
        } else { $flash = implode(' ', $errs); $flashType = 'error'; }
    }

    if (!$flash || $flashType === 'success') {
        $redir = '?';
        if ($_POST['tab'] ?? '') $redir .= 'tab='.urlencode($_POST['tab']).'&';
        if ($flash) $redir .= 'msg='.urlencode($flash).'&';
        header('Location: '.$redir); exit;
    }
}

if (!$flash && !empty($_GET['msg'])) { $flash = $_GET['msg']; $flashType = 'success'; }

/* ══════════ DATA ══════════ */
$activeTab = $_GET['tab'] ?? 'ALL';
$search    = trim($_GET['q'] ?? '');
$tabCl     = in_array($activeTab, ['ACTIVE','SUSPENDED','CLOSED']) ? " AND ia.account_status='$activeTab'" : '';
$srchCl    = '';
if ($search) {
    $esc = addslashes($search);
    $srchCl = " AND (i.first_name LIKE '%$esc%' OR i.last_name LIKE '%$esc%' OR i.inmate_id LIKE '%$esc%')";
}

$accounts = fetchAll(
    "SELECT ia.*, i.first_name, i.last_name, i.inmate_id AS inmate_number,
            f.name facility_name,
            (SELECT it2.transaction_type FROM inmate_transactions it2
             WHERE it2.account_id=ia.id AND it2.deleted_at IS NULL
             ORDER BY it2.created_at DESC LIMIT 1) last_tx_type,
            (SELECT it2.amount FROM inmate_transactions it2
             WHERE it2.account_id=ia.id AND it2.deleted_at IS NULL
             ORDER BY it2.created_at DESC LIMIT 1) last_tx_amt,
            (SELECT it2.created_at FROM inmate_transactions it2
             WHERE it2.account_id=ia.id AND it2.deleted_at IS NULL
             ORDER BY it2.created_at DESC LIMIT 1) last_tx_at
     FROM inmate_accounts ia
     JOIN inmates i  ON ia.inmate_id=i.id
     JOIN facilities f ON ia.facility_id=f.id
     WHERE $facCl$tabCl$srchCl
     ORDER BY ia.account_balance DESC",
    [], ''
);

/* KPIs */
$kpiRows = fetchAll(
    "SELECT account_status,COUNT(*) cnt,COALESCE(SUM(account_balance),0) bal
     FROM inmate_accounts WHERE $facClPlain GROUP BY account_status",
    [], ''
);
$km = [];
foreach ($kpiRows as $r) $km[$r['account_status']] = $r;
$txStats = fetchAll(
    "SELECT transaction_type,COUNT(*) cnt,COALESCE(SUM(amount),0) total
     FROM inmate_transactions WHERE $facClPlain GROUP BY transaction_type",
    [], ''
);
$txm = [];
foreach ($txStats as $r) $txm[$r['transaction_type']] = $r;

$kpis = [
    'total'     => array_sum(array_column($kpiRows,'cnt')),
    'active'    => $km['ACTIVE']['cnt']    ?? 0,
    'suspended' => $km['SUSPENDED']['cnt'] ?? 0,
    'closed'    => $km['CLOSED']['cnt']    ?? 0,
    'bal'       => ($km['ACTIVE']['bal'] ?? 0) + ($km['SUSPENDED']['bal'] ?? 0),
    'deposits'  => $txm['DEPOSIT']['total']    ?? 0,
    'withdrawals'=> $txm['WITHDRAWAL']['total'] ?? 0,
];

$canEdit = hasPermission('edit', 'finance') || hasPermission('create', 'finance');

$statusCfg = [
    'ACTIVE'    => ['clr'=>'#3fb950','bg'=>'rgba(63,185,80,.15)','icon'=>'bi-check-circle-fill'],
    'SUSPENDED' => ['clr'=>'#f39c12','bg'=>'rgba(243,156,18,.15)','icon'=>'bi-pause-circle-fill'],
    'CLOSED'    => ['clr'=>'#f85149','bg'=>'rgba(248,81,73,.15)','icon'=>'bi-x-circle-fill'],
];
$txCfg = [
    'DEPOSIT'    => ['clr'=>'#3fb950','icon'=>'bi-arrow-down-circle-fill','sign'=>'+'],
    'WITHDRAWAL' => ['clr'=>'#f85149','icon'=>'bi-arrow-up-circle-fill','sign'=>'-'],
    'TRANSFER'   => ['clr'=>'#58a6ff','icon'=>'bi-arrow-left-right','sign'=>''],
    'CANTEEN'    => ['clr'=>'#bb8fce','icon'=>'bi-bag-fill','sign'=>'-'],
];

$tabs = [
    'ALL'       => ['label'=>'All',       'cnt'=>$kpis['total'],     'clr'=>'#8b949e'],
    'ACTIVE'    => ['label'=>'Active',    'cnt'=>$kpis['active'],    'clr'=>'#3fb950'],
    'SUSPENDED' => ['label'=>'Suspended', 'cnt'=>$kpis['suspended'], 'clr'=>'#f39c12'],
    'CLOSED'    => ['label'=>'Closed',    'cnt'=>$kpis['closed'],    'clr'=>'#f85149'],
];
?>
<style>
:root{--sur:#161b22;--bdr:#21262d;--bg:#0d1117;--txt:#e6edf3;--mut:#8b949e;--acc:#1f6feb}
.ph{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:.75rem}
.ph h1{font-size:1.35rem;font-weight:700;color:var(--txt);margin:0}
.bc{font-size:.78rem;color:#6e7681;display:flex;align-items:center;gap:.3rem;margin-bottom:.3rem}
.bc a{color:#388bfd;text-decoration:none}.bc a:hover{text-decoration:underline}
.ph-actions{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap}

/* KPI */
.kpi-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:1rem;margin-bottom:1.5rem}
@media(max-width:1100px){.kpi-grid{grid-template-columns:repeat(3,1fr)}}
@media(max-width:640px){.kpi-grid{grid-template-columns:repeat(2,1fr)}}
.kpi{background:var(--sur);border:1px solid var(--bdr);border-radius:14px;padding:1.1rem 1.2rem;transition:transform .18s,box-shadow .18s}
.kpi:hover{transform:translateY(-2px);box-shadow:0 6px 24px rgba(0,0,0,.35)}
.kpi-ico{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1rem;margin-bottom:.75rem}
.kpi-val{font-size:1.6rem;font-weight:800;color:var(--txt);line-height:1;margin-bottom:.25rem}
.kpi-lbl{font-size:.72rem;font-weight:600;color:var(--mut);text-transform:uppercase;letter-spacing:.05em}

/* Tabs */
.tab-strip{display:flex;gap:.4rem;margin-bottom:1.25rem;flex-wrap:wrap;padding:.45rem .5rem;background:var(--sur);border:1px solid var(--bdr);border-radius:12px;width:fit-content}
.tab{padding:.38rem .85rem;border-radius:8px;font-size:.8rem;font-weight:600;cursor:pointer;color:var(--mut);background:transparent;border:none;display:flex;align-items:center;gap:.4rem;transition:all .18s;text-decoration:none;white-space:nowrap}
.tab:hover,.tab.active{background:#21262d;color:var(--txt)}
.tab-badge{padding:1px 6px;border-radius:20px;font-size:.68rem;font-weight:700}

/* Toolbar */
.toolbar{display:flex;align-items:center;gap:.6rem;margin-bottom:1.25rem;flex-wrap:wrap}
.search-wrap{position:relative;flex:1;min-width:180px;max-width:340px}
.search-wrap i{position:absolute;left:.75rem;top:50%;transform:translateY(-50%);color:#484f58;font-size:.85rem;pointer-events:none}
.search-wrap input{width:100%;background:#0d1117;border:1px solid #30363d;color:var(--txt);padding:.5rem .85rem .5rem 2.2rem;border-radius:9px;font-size:.875rem;outline:none;transition:border .2s,box-shadow .2s;box-sizing:border-box}
.search-wrap input::placeholder{color:#484f58}
.search-wrap input:focus{border-color:#388bfd;box-shadow:0 0 0 3px rgba(56,139,253,.15)}

/* Table */
.card{background:var(--sur);border:1px solid var(--bdr);border-radius:14px;overflow:hidden}
.tbl-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:.875rem}
th{background:#0d1117;color:var(--mut);font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;padding:.7rem 1rem;text-align:left;white-space:nowrap;border-bottom:1px solid var(--bdr)}
td{padding:.75rem 1rem;border-bottom:1px solid #1c2128;color:var(--txt);vertical-align:middle}
tr:last-child td{border-bottom:none}
tr:hover td{background:rgba(255,255,255,.025)}

/* Inmate avatar */
.inmate-cell{display:flex;align-items:center;gap:.7rem}
.avatar{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;flex-shrink:0;background:linear-gradient(135deg,#1f6feb,#388bfd);color:#fff}
.inmate-name{font-weight:600;font-size:.875rem;color:var(--txt)}
.inmate-id{font-size:.73rem;color:#8b949e;font-family:monospace}

/* Balance */
.balance-big{font-size:1rem;font-weight:800;color:#3fb950}
.balance-zero{color:#8b949e}
.balance-neg{color:#f85149}

/* Status chip */
.status-chip{display:inline-flex;align-items:center;gap:.3rem;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700}

/* Last tx */
.tx-pill{display:inline-flex;align-items:center;gap:.3rem;font-size:.75rem;font-weight:600}

/* Action buttons */
.actions-cell{display:flex;align-items:center;gap:.35rem;flex-wrap:wrap}
.ic-btn{width:32px;height:32px;border-radius:8px;border:1px solid #30363d;background:transparent;color:#8b949e;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;transition:all .18s}
.ic-btn.dep:hover{border-color:#3fb950;color:#3fb950;background:rgba(63,185,80,.08)}
.ic-btn.wd:hover{border-color:#f85149;color:#f85149;background:rgba(248,81,73,.08)}
.ic-btn.hist:hover{border-color:#388bfd;color:#388bfd;background:rgba(56,139,253,.08)}
.ic-btn.mgmt:hover{border-color:#f39c12;color:#f39c12;background:rgba(243,156,18,.08)}

/* Buttons */
.btn-primary-sm{background:linear-gradient(135deg,#1f6feb,#388bfd);color:#fff;padding:.5rem 1.1rem;border-radius:9px;font-size:.85rem;font-weight:600;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;text-decoration:none;transition:opacity .18s;white-space:nowrap}
.btn-primary-sm:hover{opacity:.87;color:#fff}
.btn-ghost{background:transparent;border:1px solid #30363d;color:#8b949e;padding:.5rem 1rem;border-radius:9px;font-size:.85rem;font-weight:500;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;text-decoration:none;transition:all .18s;white-space:nowrap}
.btn-ghost:hover{border-color:#58a6ff;color:#58a6ff}

/* Flash */
.flash{border-radius:11px;padding:.8rem 1.1rem;margin-bottom:1.25rem;font-size:.875rem;display:flex;align-items:center;gap:.55rem}
.flash.success{background:rgba(63,185,80,.12);border:1px solid rgba(63,185,80,.3);color:#3fb950}
.flash.error{background:rgba(248,81,73,.08);border:1px solid rgba(248,81,73,.3);color:#f85149}

/* Empty */
.empty-state{text-align:center;padding:4rem 1rem;color:#8b949e}
.empty-state i{font-size:2.8rem;display:block;margin-bottom:.75rem;opacity:.3}

/* MODAL */
.modal-ov{position:fixed;inset:0;background:rgba(1,4,9,.77);z-index:1000;display:flex;align-items:center;justify-content:center;padding:1rem;opacity:0;pointer-events:none;transition:opacity .22s}
.modal-ov.open{opacity:1;pointer-events:auto}
.modal-box{background:#161b22;border:1px solid #30363d;border-radius:18px;width:100%;max-width:480px;max-height:90vh;overflow-y:auto;transform:translateY(20px);transition:transform .22s}
.modal-box.wide{max-width:640px}
.modal-ov.open .modal-box{transform:translateY(0)}
.modal-hdr{display:flex;align-items:center;justify-content:space-between;padding:1.1rem 1.4rem;border-bottom:1px solid #21262d;position:sticky;top:0;background:#161b22;z-index:1}
.modal-hdr h2{margin:0;font-size:1rem;font-weight:700;color:#e6edf3;display:flex;align-items:center;gap:.5rem}
.modal-close{background:none;border:none;color:#8b949e;font-size:1.1rem;cursor:pointer;width:30px;height:30px;border-radius:7px;display:flex;align-items:center;justify-content:center;transition:all .18s}
.modal-close:hover{background:#21262d;color:#e6edf3}
.modal-body{padding:1.4rem}
.fg{display:flex;flex-direction:column;gap:.35rem;margin-bottom:1rem}
.fg:last-child{margin-bottom:0}
.fg label{font-size:.8rem;font-weight:600;color:var(--mut)}
.req{color:#f85149}
.fi{background:#0d1117;border:1px solid #30363d;border-radius:9px;color:var(--txt);padding:.55rem .85rem;font-size:.875rem;width:100%;outline:none;transition:border .2s,box-shadow .2s;font-family:inherit;box-sizing:border-box}
.fi::placeholder{color:#484f58}
.fi:focus{border-color:#388bfd;box-shadow:0 0 0 3px rgba(56,139,253,.15)}
.fi-icon-wrap{position:relative}
.fi-icon-wrap .fi-ico{position:absolute;left:.8rem;top:50%;transform:translateY(-50%);color:#8b949e;pointer-events:none}
.fi-icon-wrap .fi{padding-left:2rem}
.modal-footer{display:flex;justify-content:flex-end;gap:.6rem;padding-top:1rem;border-top:1px solid #21262d;margin-top:1.1rem;flex-wrap:wrap}
.btn-success{background:linear-gradient(135deg,#238636,#3fb950);color:#fff;padding:.55rem 1.1rem;border-radius:9px;font-size:.875rem;font-weight:600;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;transition:opacity .18s}
.btn-success:hover{opacity:.87}
.btn-danger{background:rgba(248,81,73,.1);border:1px solid rgba(248,81,73,.3);color:#f85149;padding:.55rem 1.1rem;border-radius:9px;font-size:.875rem;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;transition:all .18s}
.btn-danger:hover{background:rgba(248,81,73,.2)}

/* Account info banner in modal */
.acc-banner{background:#0d1117;border:1px solid #30363d;border-radius:11px;padding:.9rem 1rem;display:flex;align-items:center;gap:.8rem;margin-bottom:1.2rem}
.acc-banner-avatar{width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,#1f6feb,#388bfd);display:flex;align-items:center;justify-content:center;font-size:.9rem;font-weight:800;color:#fff;flex-shrink:0}
.acc-banner-name{font-weight:700;font-size:.9rem;color:#e6edf3}
.acc-banner-id{font-size:.75rem;color:#8b949e;font-family:monospace}
.acc-banner-bal{margin-left:auto;text-align:right}
.acc-banner-bal .label{font-size:.68rem;color:#8b949e;font-weight:600;text-transform:uppercase;letter-spacing:.05em}
.acc-banner-bal .val{font-size:1.1rem;font-weight:800;color:#3fb950}

/* Transaction history */
.tx-list{display:flex;flex-direction:column;gap:.5rem}
.tx-item{background:#0d1117;border:1px solid #21262d;border-radius:10px;padding:.7rem 1rem;display:flex;align-items:center;gap:.75rem}
.tx-icon{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.85rem;flex-shrink:0}
.tx-info{flex:1;min-width:0}
.tx-type{font-size:.82rem;font-weight:700;color:#e6edf3}
.tx-desc{font-size:.73rem;color:#8b949e;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.tx-date{font-size:.7rem;color:#8b949e}
.tx-amount{font-weight:800;font-size:.95rem;white-space:nowrap}
.tx-balance{font-size:.7rem;color:#8b949e;text-align:right}

/* Status management */
.status-opts{display:grid;grid-template-columns:repeat(3,1fr);gap:.6rem;margin-bottom:1rem}
.status-opt{background:#0d1117;border:2px solid #30363d;border-radius:11px;padding:.7rem .5rem;cursor:pointer;text-align:center;transition:all .18s}
.status-opt:hover{border-color:#388bfd}
.status-opt.sel-active{border-color:#3fb950;background:rgba(63,185,80,.08)}
.status-opt.sel-suspended{border-color:#f39c12;background:rgba(243,156,18,.08)}
.status-opt.sel-closed{border-color:#f85149;background:rgba(248,81,73,.08)}
.status-opt i{font-size:1.1rem;display:block;margin-bottom:.3rem}
.status-opt span{font-size:.72rem;font-weight:700;display:block}
</style>

<!-- PAGE HEADER -->
<div class="ph">
  <div>
    <div class="bc">
      <a href="<?php echo APP_URL; ?>/dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
      <i class="bi bi-chevron-right" style="font-size:.6rem"></i>
      <a href="<?php echo APP_URL; ?>/modules/finance/index.php">Finance</a>
      <i class="bi bi-chevron-right" style="font-size:.6rem"></i>
      <span>Inmate Accounts</span>
    </div>
    <h1><i class="bi bi-wallet2" style="color:#388bfd;margin-right:.45rem"></i>Inmate Accounts</h1>
  </div>
  <div class="ph-actions">
    <a href="<?php echo APP_URL; ?>/modules/finance/index.php" class="btn-ghost">
      <i class="bi bi-graph-up"></i> Finance Overview
    </a>
    <?php if ($canEdit): ?>
    <button class="btn-primary-sm" onclick="openDeposit(null)" style="background:linear-gradient(135deg,#238636,#3fb950)">
      <i class="bi bi-arrow-down-circle-fill"></i> Deposit
    </button>
    <button class="btn-primary-sm" onclick="openWithdraw(null)" style="background:linear-gradient(135deg,#b91c1c,#f85149)">
      <i class="bi bi-arrow-up-circle-fill"></i> Withdraw
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

<!-- KPI GRID -->
<div class="kpi-grid">
<?php
$kpiDefs = [
    ['Total Accounts', $kpis['total'],      'bi-people-fill',           '#388bfd','rgba(56,139,253,.15)', '#1f6feb'],
    ['Active',         $kpis['active'],     'bi-check-circle-fill',     '#3fb950','rgba(63,185,80,.15)',  '#3fb950'],
    ['Suspended',      $kpis['suspended'],  'bi-pause-circle-fill',     '#f39c12','rgba(243,156,18,.15)','#f39c12'],
    ['Closed',         $kpis['closed'],     'bi-x-circle-fill',         '#f85149','rgba(248,81,73,.15)', '#f85149'],
    ['Total Balance',  '$'.number_format($kpis['bal'],2), 'bi-cash-coin','#bb8fce','rgba(187,143,206,.15)','#bb8fce'],
    ['Total Deposits', '$'.number_format($kpis['deposits'],2),'bi-arrow-down-circle-fill','#3fb950','rgba(63,185,80,.15)','#3fb950'],
];
foreach ($kpiDefs as [$lbl,$val,$icon,$clr,$bg,$bar]):
?>
<div class="kpi" style="border-top:3px solid <?php echo $bar; ?>">
  <div class="kpi-ico" style="background:<?php echo $bg; ?>;color:<?php echo $clr; ?>"><i class="bi <?php echo $icon; ?>"></i></div>
  <div class="kpi-val" style="font-size:<?php echo strlen((string)$val)>6?'1.1rem':'1.6rem'; ?>"><?php echo is_numeric($val)?number_format((float)$val):$val; ?></div>
  <div class="kpi-lbl"><?php echo $lbl; ?></div>
</div>
<?php endforeach; ?>
</div>

<!-- TABS -->
<div class="tab-strip">
<?php foreach ($tabs as $key => $t): ?>
  <a href="?tab=<?php echo $key; ?><?php echo $search ? '&q='.urlencode($search) : ''; ?>"
     class="tab <?php echo $activeTab===$key?'active':''; ?>">
    <?php echo $t['label']; ?>
    <?php if ($t['cnt'] > 0): ?>
    <span class="tab-badge" style="background:rgba(139,148,158,.15);color:<?php echo $t['clr']; ?>"><?php echo $t['cnt']; ?></span>
    <?php endif; ?>
  </a>
<?php endforeach; ?>
</div>

<!-- TOOLBAR -->
<div class="toolbar">
  <form method="GET" style="display:contents">
    <input type="hidden" name="tab" value="<?php echo htmlspecialchars($activeTab); ?>">
    <div class="search-wrap">
      <i class="bi bi-search"></i>
      <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>"
             placeholder="Search by name or inmate ID…" onchange="this.form.submit()">
    </div>
  </form>
  <span style="margin-left:auto;font-size:.8rem;color:#8b949e"><?php echo count($accounts); ?> account<?php echo count($accounts)!==1?'s':''; ?></span>
</div>

<!-- TABLE -->
<div class="card">
  <?php if (empty($accounts)): ?>
  <div class="empty-state">
    <i class="bi bi-wallet"></i>
    <p>No accounts found<?php echo $search ? ' for your search' : ''; ?>.</p>
  </div>
  <?php else: ?>
  <div class="tbl-wrap">
    <table>
      <thead>
        <tr>
          <th>Inmate</th>
          <?php if ($isSA): ?><th>Facility</th><?php endif; ?>
          <th>Balance</th>
          <th>Status</th>
          <th>Last Transaction</th>
          <th>Since</th>
          <?php if ($canEdit): ?><th></th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($accounts as $acc):
          $sc      = $statusCfg[$acc['account_status']];
          $bal     = (float)$acc['account_balance'];
          $initials= strtoupper(substr($acc['first_name'],0,1).substr($acc['last_name'],0,1));
      ?>
        <tr>
          <td>
            <div class="inmate-cell">
              <div class="avatar"><?php echo $initials; ?></div>
              <div>
                <div class="inmate-name"><?php echo htmlspecialchars($acc['first_name'].' '.$acc['last_name']); ?></div>
                <div class="inmate-id"><?php echo htmlspecialchars($acc['inmate_number']); ?></div>
              </div>
            </div>
          </td>
          <?php if ($isSA): ?><td style="font-size:.8rem;color:#8b949e"><?php echo htmlspecialchars($acc['facility_name']); ?></td><?php endif; ?>
          <td>
            <span class="balance-big <?php echo $bal<=0?'balance-zero':''; ?>" style="<?php echo $bal>0?'color:#3fb950':''; ?>">
              $<?php echo number_format($bal, 2); ?>
            </span>
          </td>
          <td>
            <span class="status-chip" style="background:<?php echo $sc['bg']; ?>;color:<?php echo $sc['clr']; ?>">
              <i class="bi <?php echo $sc['icon']; ?>"></i>
              <?php echo ucfirst(strtolower($acc['account_status'])); ?>
            </span>
          </td>
          <td>
            <?php if ($acc['last_tx_type']): $tc = $txCfg[$acc['last_tx_type']] ?? ['clr'=>'#8b949e','icon'=>'bi-circle','sign'=>'']; ?>
            <div class="tx-pill" style="color:<?php echo $tc['clr']; ?>">
              <i class="bi <?php echo $tc['icon']; ?>"></i>
              <?php echo $tc['sign']; ?>$<?php echo number_format((float)$acc['last_tx_amt'],2); ?>
            </div>
            <div style="font-size:.7rem;color:#8b949e;margin-top:.1rem"><?php echo date('M j, Y', strtotime($acc['last_tx_at'])); ?></div>
            <?php else: ?>
            <span style="color:#484f58;font-size:.8rem">No transactions</span>
            <?php endif; ?>
          </td>
          <td style="font-size:.8rem;color:#8b949e"><?php echo $acc['created_at'] ? date('M Y', strtotime($acc['created_at'])) : '—'; ?></td>
          <?php if ($canEdit): ?>
          <td>
            <div class="actions-cell">
              <?php if ($acc['account_status'] === 'ACTIVE'): ?>
              <button class="ic-btn dep" title="Deposit"
                onclick='openDeposit(<?php echo json_encode(["id"=>$acc["id"],"name"=>$acc["first_name"]." ".$acc["last_name"],"inmate_id"=>$acc["inmate_number"],"balance"=>$acc["account_balance"]]); ?>)'>
                <i class="bi bi-arrow-down-circle-fill"></i>
              </button>
              <button class="ic-btn wd" title="Withdraw"
                onclick='openWithdraw(<?php echo json_encode(["id"=>$acc["id"],"name"=>$acc["first_name"]." ".$acc["last_name"],"inmate_id"=>$acc["inmate_number"],"balance"=>$acc["account_balance"]]); ?>)'>
                <i class="bi bi-arrow-up-circle-fill"></i>
              </button>
              <?php endif; ?>
              <button class="ic-btn hist" title="Transaction history"
                onclick='openHistory(<?php echo $acc["id"]; ?>, <?php echo json_encode($acc["first_name"]." ".$acc["last_name"]); ?>, <?php echo json_encode($acc["inmate_number"]); ?>)'>
                <i class="bi bi-clock-history"></i>
              </button>
              <button class="ic-btn mgmt" title="Manage account status"
                onclick='openManage(<?php echo json_encode(["id"=>$acc["id"],"name"=>$acc["first_name"]." ".$acc["last_name"],"inmate_id"=>$acc["inmate_number"],"balance"=>$acc["account_balance"],"status"=>$acc["account_status"]]); ?>)'>
                <i class="bi bi-gear-fill"></i>
              </button>
            </div>
          </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- ══════════ DEPOSIT MODAL ══════════ -->
<div class="modal-ov" id="depositOv" onclick="if(event.target===this)closeDeposit()">
  <div class="modal-box">
    <div class="modal-hdr">
      <h2><i class="bi bi-arrow-down-circle-fill" style="color:#3fb950"></i> Deposit Funds</h2>
      <button class="modal-close" onclick="closeDeposit()"><i class="bi bi-x"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="deposit">
      <input type="hidden" name="tab" value="<?php echo htmlspecialchars($activeTab); ?>">
      <div class="modal-body">
        <div id="dep-banner" class="acc-banner" style="display:none">
          <div class="acc-banner-avatar" id="dep-av"></div>
          <div><div class="acc-banner-name" id="dep-name"></div><div class="acc-banner-id" id="dep-id-lbl"></div></div>
          <div class="acc-banner-bal"><div class="label">Balance</div><div class="val" id="dep-bal"></div></div>
        </div>
        <!-- Account selector (shown when opened without a specific account) -->
        <div class="fg" id="dep-acc-wrap">
          <label>Account <span class="req">*</span></label>
          <select name="account_id" id="dep-account" class="fi" required>
            <option value="">— Select inmate account —</option>
            <?php foreach ($accounts as $acc): if ($acc['account_status']!=='ACTIVE') continue; ?>
            <option value="<?php echo $acc['id']; ?>"
                    data-name="<?php echo htmlspecialchars($acc['first_name'].' '.$acc['last_name']); ?>"
                    data-iid="<?php echo htmlspecialchars($acc['inmate_number']); ?>"
                    data-bal="<?php echo $acc['account_balance']; ?>">
              <?php echo htmlspecialchars($acc['first_name'].' '.$acc['last_name']); ?> — <?php echo htmlspecialchars($acc['inmate_number']); ?>
              ($<?php echo number_format((float)$acc['account_balance'],2); ?>)
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="fg">
          <label>Amount ($) <span class="req">*</span></label>
          <div class="fi-icon-wrap">
            <i class="bi bi-currency-dollar fi-ico"></i>
            <input type="number" name="amount" class="fi" placeholder="0.00" step="0.01" min="0.01" required autofocus>
          </div>
        </div>
        <div class="fg">
          <label>Description / Reference</label>
          <input type="text" name="description" class="fi" placeholder="e.g. Family deposit, Work credit…" maxlength="255">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-ghost" onclick="closeDeposit()"><i class="bi bi-x"></i> Cancel</button>
          <button type="submit" class="btn-success"><i class="bi bi-check-lg"></i> Confirm Deposit</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- ══════════ WITHDRAW MODAL ══════════ -->
<div class="modal-ov" id="withdrawOv" onclick="if(event.target===this)closeWithdraw()">
  <div class="modal-box">
    <div class="modal-hdr">
      <h2><i class="bi bi-arrow-up-circle-fill" style="color:#f85149"></i> Withdraw Funds</h2>
      <button class="modal-close" onclick="closeWithdraw()"><i class="bi bi-x"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="withdrawal">
      <input type="hidden" name="tab" value="<?php echo htmlspecialchars($activeTab); ?>">
      <div class="modal-body">
        <div id="wd-banner" class="acc-banner" style="display:none">
          <div class="acc-banner-avatar" id="wd-av"></div>
          <div><div class="acc-banner-name" id="wd-name"></div><div class="acc-banner-id" id="wd-id-lbl"></div></div>
          <div class="acc-banner-bal"><div class="label">Available</div><div class="val" id="wd-bal"></div></div>
        </div>
        <div class="fg" id="wd-acc-wrap">
          <label>Account <span class="req">*</span></label>
          <select name="account_id" id="wd-account" class="fi" required>
            <option value="">— Select inmate account —</option>
            <?php foreach ($accounts as $acc): if ($acc['account_status']!=='ACTIVE') continue; ?>
            <option value="<?php echo $acc['id']; ?>"
                    data-name="<?php echo htmlspecialchars($acc['first_name'].' '.$acc['last_name']); ?>"
                    data-iid="<?php echo htmlspecialchars($acc['inmate_number']); ?>"
                    data-bal="<?php echo $acc['account_balance']; ?>">
              <?php echo htmlspecialchars($acc['first_name'].' '.$acc['last_name']); ?> — <?php echo htmlspecialchars($acc['inmate_number']); ?>
              ($<?php echo number_format((float)$acc['account_balance'],2); ?>)
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="fg">
          <label>Amount ($) <span class="req">*</span></label>
          <div class="fi-icon-wrap">
            <i class="bi bi-currency-dollar fi-ico"></i>
            <input type="number" name="amount" id="wd-amount" class="fi" placeholder="0.00" step="0.01" min="0.01" required>
          </div>
          <div id="wd-balance-hint" style="font-size:.73rem;color:#8b949e;margin-top:.2rem"></div>
        </div>
        <div class="fg">
          <label>Description / Reference</label>
          <input type="text" name="description" class="fi" placeholder="e.g. Canteen purchase, Fine deduction…" maxlength="255">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-ghost" onclick="closeWithdraw()"><i class="bi bi-x"></i> Cancel</button>
          <button type="submit" class="btn-danger"><i class="bi bi-check-lg"></i> Confirm Withdrawal</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- ══════════ HISTORY MODAL ══════════ -->
<div class="modal-ov" id="historyOv" onclick="if(event.target===this)closeHistory()">
  <div class="modal-box wide">
    <div class="modal-hdr">
      <h2><i class="bi bi-clock-history" style="color:#388bfd"></i> <span id="hist-title">Transaction History</span></h2>
      <button class="modal-close" onclick="closeHistory()"><i class="bi bi-x"></i></button>
    </div>
    <div class="modal-body">
      <div id="hist-loading" style="text-align:center;padding:2rem;color:#8b949e"><i class="bi bi-hourglass-split"></i> Loading…</div>
      <div id="hist-content" class="tx-list"></div>
    </div>
  </div>
</div>

<!-- ══════════ MANAGE MODAL ══════════ -->
<div class="modal-ov" id="manageOv" onclick="if(event.target===this)closeManage()">
  <div class="modal-box">
    <div class="modal-hdr">
      <h2><i class="bi bi-gear-fill" style="color:#f39c12"></i> Manage Account</h2>
      <button class="modal-close" onclick="closeManage()"><i class="bi bi-x"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="set_status">
      <input type="hidden" name="tab" value="<?php echo htmlspecialchars($activeTab); ?>">
      <input type="hidden" name="account_id" id="mgmt-id">
      <input type="hidden" name="new_status" id="mgmt-status-val">
      <div class="modal-body">
        <div id="mgmt-banner" class="acc-banner">
          <div class="acc-banner-avatar" id="mgmt-av"></div>
          <div><div class="acc-banner-name" id="mgmt-name"></div><div class="acc-banner-id" id="mgmt-id-lbl"></div></div>
          <div class="acc-banner-bal"><div class="label">Balance</div><div class="val" id="mgmt-bal"></div></div>
        </div>
        <p style="font-size:.8rem;color:#8b949e;margin:0 0 .75rem">Change account status:</p>
        <div class="status-opts">
          <div class="status-opt" id="opt-active" onclick="selectStatus('ACTIVE')">
            <i class="bi bi-check-circle-fill" style="color:#3fb950"></i>
            <span style="color:#3fb950">Active</span>
          </div>
          <div class="status-opt" id="opt-suspended" onclick="selectStatus('SUSPENDED')">
            <i class="bi bi-pause-circle-fill" style="color:#f39c12"></i>
            <span style="color:#f39c12">Suspended</span>
          </div>
          <div class="status-opt" id="opt-closed" onclick="selectStatus('CLOSED')">
            <i class="bi bi-x-circle-fill" style="color:#f85149"></i>
            <span style="color:#f85149">Closed</span>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-ghost" onclick="closeManage()"><i class="bi bi-x"></i> Cancel</button>
          <button type="submit" class="btn-primary-sm"><i class="bi bi-check-lg"></i> Update Status</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
const txCfg = <?php echo json_encode($txCfg); ?>;

/* ── DEPOSIT ── */
function openDeposit(r) {
  const ov  = document.getElementById('depositOv');
  const wrap= document.getElementById('dep-acc-wrap');
  const sel = document.getElementById('dep-account');
  const ban = document.getElementById('dep-banner');
  if (r) {
    wrap.style.display = 'none'; ban.style.display = '';
    sel.innerHTML = `<option value="${r.id}" selected></option>`;
    sel.querySelector('option').value = r.id;
    document.getElementById('dep-av').textContent   = r.name.split(' ').map(w=>w[0]).join('').toUpperCase().slice(0,2);
    document.getElementById('dep-name').textContent = r.name;
    document.getElementById('dep-id-lbl').textContent = r.inmate_id;
    document.getElementById('dep-bal').textContent  = '$' + parseFloat(r.balance).toFixed(2);
    // set account_id hidden
    sel.name = ''; // remove from form temporarily
    const hid = document.createElement('input');
    hid.type='hidden'; hid.name='account_id'; hid.value=r.id; hid.id='dep-hid';
    ov.querySelector('form').appendChild(hid);
  } else {
    wrap.style.display = ''; ban.style.display = 'none';
    sel.name = 'account_id';
    const old = document.getElementById('dep-hid'); if(old) old.remove();
  }
  ov.classList.add('open'); document.body.style.overflow='hidden';
}
function closeDeposit(){ document.getElementById('depositOv').classList.remove('open'); document.body.style.overflow=''; }

/* ── WITHDRAW ── */
let wdMaxBal = 0;
function openWithdraw(r) {
  const ov   = document.getElementById('withdrawOv');
  const wrap = document.getElementById('wd-acc-wrap');
  const sel  = document.getElementById('wd-account');
  const ban  = document.getElementById('wd-banner');
  if (r) {
    wrap.style.display='none'; ban.style.display='';
    document.getElementById('wd-av').textContent   = r.name.split(' ').map(w=>w[0]).join('').toUpperCase().slice(0,2);
    document.getElementById('wd-name').textContent = r.name;
    document.getElementById('wd-id-lbl').textContent = r.inmate_id;
    document.getElementById('wd-bal').textContent  = '$' + parseFloat(r.balance).toFixed(2);
    wdMaxBal = parseFloat(r.balance);
    document.getElementById('wd-amount').max = wdMaxBal;
    document.getElementById('wd-balance-hint').textContent = 'Available: $' + wdMaxBal.toFixed(2);
    sel.name = '';
    const hid = document.createElement('input');
    hid.type='hidden'; hid.name='account_id'; hid.value=r.id; hid.id='wd-hid';
    ov.querySelector('form').appendChild(hid);
  } else {
    wrap.style.display=''; ban.style.display='none';
    sel.name='account_id';
    const old = document.getElementById('wd-hid'); if(old) old.remove();
    // update hint on change
    sel.onchange = () => {
      const opt = sel.options[sel.selectedIndex];
      const bal = parseFloat(opt.dataset.bal) || 0;
      document.getElementById('wd-balance-hint').textContent = sel.value ? 'Available: $' + bal.toFixed(2) : '';
      wdMaxBal = bal;
      document.getElementById('wd-amount').max = bal;
    };
  }
  ov.classList.add('open'); document.body.style.overflow='hidden';
}
function closeWithdraw(){ document.getElementById('withdrawOv').classList.remove('open'); document.body.style.overflow=''; }

/* ── HISTORY ── */
function openHistory(accountId, name, inmateId) {
  document.getElementById('hist-title').textContent = name + ' (' + inmateId + ')';
  document.getElementById('hist-loading').style.display = '';
  document.getElementById('hist-content').innerHTML = '';
  document.getElementById('historyOv').classList.add('open');
  document.body.style.overflow='hidden';

  fetch('<?php echo APP_URL; ?>/api/finance/index.php?action=statement&account_id=' + accountId, {credentials:'same-origin'})
    .then(r => r.json())
    .then(data => {
      document.getElementById('hist-loading').style.display='none';
      const txs = data.data?.transactions || data.transactions || [];
      if (!txs.length) {
        document.getElementById('hist-content').innerHTML = '<div style="text-align:center;padding:2rem;color:#8b949e;font-size:.875rem"><i class="bi bi-inbox" style="font-size:2rem;display:block;margin-bottom:.5rem;opacity:.3"></i>No transactions yet.</div>';
        return;
      }
      const html = txs.map(tx => {
        const tc = txCfg[tx.transaction_type] || {clr:'#8b949e',icon:'bi-circle',sign:''};
        const dt = new Date(tx.created_at).toLocaleDateString('en-US',{year:'numeric',month:'short',day:'numeric'});
        const isDebit = ['WITHDRAWAL','CANTEEN'].includes(tx.transaction_type);
        return `<div class="tx-item">
          <div class="tx-icon" style="background:${tc.bg||'rgba(139,148,158,.12)'};color:${tc.clr}"><i class="bi ${tc.icon}"></i></div>
          <div class="tx-info">
            <div class="tx-type">${tx.transaction_type.charAt(0)+tx.transaction_type.slice(1).toLowerCase()}</div>
            ${tx.description ? `<div class="tx-desc">${tx.description}</div>` : ''}
            <div class="tx-date">${dt}</div>
          </div>
          <div style="text-align:right">
            <div class="tx-amount" style="color:${tc.clr}">${tc.sign}$${parseFloat(tx.amount).toFixed(2)}</div>
            ${tx.balance_after !== null ? `<div class="tx-balance">Bal: $${parseFloat(tx.balance_after).toFixed(2)}</div>` : ''}
          </div>
        </div>`;
      }).join('');
      document.getElementById('hist-content').innerHTML = html;
    })
    .catch(() => {
      document.getElementById('hist-loading').style.display='none';
      document.getElementById('hist-content').innerHTML = '<div style="color:#f85149;font-size:.875rem;text-align:center;padding:1.5rem"><i class="bi bi-exclamation-triangle-fill"></i> Failed to load transactions.</div>';
    });
}
function closeHistory(){ document.getElementById('historyOv').classList.remove('open'); document.body.style.overflow=''; }

/* ── MANAGE ── */
function openManage(r) {
  document.getElementById('mgmt-id').value       = r.id;
  document.getElementById('mgmt-av').textContent  = r.name.split(' ').map(w=>w[0]).join('').toUpperCase().slice(0,2);
  document.getElementById('mgmt-name').textContent= r.name;
  document.getElementById('mgmt-id-lbl').textContent = r.inmate_id;
  document.getElementById('mgmt-bal').textContent = '$' + parseFloat(r.balance).toFixed(2);
  selectStatus(r.status);
  document.getElementById('manageOv').classList.add('open');
  document.body.style.overflow='hidden';
}
function selectStatus(s) {
  document.getElementById('mgmt-status-val').value = s;
  ['active','suspended','closed'].forEach(k => {
    const el = document.getElementById('opt-'+k);
    el.className = 'status-opt';
    if (k === s.toLowerCase()) el.className += ' sel-'+k;
  });
}
function closeManage(){ document.getElementById('manageOv').classList.remove('open'); document.body.style.overflow=''; }

document.addEventListener('keydown', e => {
  if (e.key==='Escape') { closeDeposit(); closeWithdraw(); closeHistory(); closeManage(); }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
