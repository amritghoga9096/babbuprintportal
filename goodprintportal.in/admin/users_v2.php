<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('../includes/session.php');
include('../includes/config.php');
if (!checkAdmin($udata['type'])) {
    echo '<script>window.location="index.php";</script>';
    die();
}
function _v2DebugBanner($msg, $type='info') {
    $colors = ['info'=>'#4f7bff','error'=>'#f5576c','warn'=>'#f6a623','ok'=>'#43e97b'];
    $c = $colors[$type] ?? $colors['info'];
    echo '<div style="margin:8px 12px;padding:10px 16px;border-radius:10px;background:'.$c.'22;border:1px solid '.$c.'55;color:#222;font-weight:600;font-size:13px;box-shadow:0 2px 8px '.$c.'22;">
        🔧 DEBUG: '.htmlspecialchars($msg).'</div>';
}
_v2DebugBanner('users_v2.php loaded. PHP '.phpversion().' | Error reporting ON', 'ok');

// ===== SELF-HEAL: Ensure wallet-related tables + columns exist (like trick_buy_common pattern) =====
function _v2EnsureWalletSchema($conn) {
    if (!$conn) return;
    static $done = null;
    if ($done === true) return;
    @mysqli_report(MYSQLI_REPORT_OFF);

    // ---- users table extra columns needed for users_v2.php filters ----
    $uCols = [];
    $rU = @mysqli_query($conn, "SHOW COLUMNS FROM users");
    if ($rU) while ($row = mysqli_fetch_assoc($rU)) $uCols[] = $row['Field'];
    $addU = function($n, $d) use ($conn, $uCols) {
        if (!in_array($n, $uCols, true)) @mysqli_query($conn, "ALTER TABLE users ADD COLUMN {$n} {$d}");
    };
    // Stores the "up to which GAP amount we should ignore this user in fraud list".
    // E.g. user had bal=10, totalLegit=0, gap=10. Admin clicks "Remove from List" => fraud_ignore_gap=10.
    // Next time: if gap stays 10 or less -> hidden. If gap grows to 11+ (user does more fraud) -> reappears automatically.
    $addU('fraud_ignore_gap', "DECIMAL(12,2) NOT NULL DEFAULT 0.00");

    // 1) wallet table
    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `wallet` (
      `id` INT NOT NULL AUTO_INCREMENT,
      `phone` VARCHAR(255) NULL,
      `amount` VARCHAR(255) NULL,
      `txn_id` VARCHAR(255) NULL,
      `date` VARCHAR(255) NULL,
      `txn_date` DATETIME NULL DEFAULT CURRENT_TIMESTAMP(),
      `email` VARCHAR(255) NULL,
      `PAYMENTMODE` VARCHAR(255) NULL,
      `RESPMSG` VARCHAR(255) NULL,
      `BANKTXNID` VARCHAR(255) NULL,
      `status` ENUM('pending','success','rejected') NOT NULL DEFAULT 'pending',
      PRIMARY KEY (`id`), KEY `idx_wallet_txn` (`txn_id`), KEY `idx_wallet_phone` (`phone`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $cols = [];
    $r = @mysqli_query($conn, "SHOW COLUMNS FROM wallet");
    if ($r) while ($row = mysqli_fetch_assoc($r)) $cols[] = $row['Field'];
    $add = function($n, $d) use ($conn, $cols) { if (!in_array($n, $cols, true)) @mysqli_query($conn, "ALTER TABLE wallet ADD COLUMN {$n} {$d}"); };
    $add('txn_date', "DATETIME NULL DEFAULT CURRENT_TIMESTAMP()");
    $add('PAYMENTMODE', "VARCHAR(255) NULL");
    $add('BANKTXNID', "VARCHAR(255) NULL");
    $add('RESPMSG', "VARCHAR(255) NULL");
    $add('email', "VARCHAR(255) NULL");
    $add('status', "ENUM('pending','success','rejected') NOT NULL DEFAULT 'pending'");

    // 2) wallet_transactions table (goodprintportal_good.sql standard)
    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `wallet_transactions` (
      `id` INT NOT NULL AUTO_INCREMENT,
      `order_id` VARCHAR(64) NOT NULL,
      `session_id` VARCHAR(128) NULL,
      `phone` VARCHAR(255) NULL,
      `email` VARCHAR(255) NULL,
      `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      `txn_status` VARCHAR(32) NOT NULL DEFAULT 'PENDING',
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
      PRIMARY KEY (`id`), KEY `idx_wt_order` (`order_id`), KEY `idx_wt_phone` (`phone`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $cols2 = [];
    $r2 = @mysqli_query($conn, "SHOW COLUMNS FROM wallet_transactions");
    if ($r2) while ($row = mysqli_fetch_assoc($r2)) $cols2[] = $row['Field'];
    $add2 = function($n, $d) use ($conn, $cols2) { if (!in_array($n, $cols2, true)) @mysqli_query($conn, "ALTER TABLE wallet_transactions ADD COLUMN {$n} {$d}"); };
    $add2('session_id', "VARCHAR(128) NULL");
    $add2('email', "VARCHAR(255) NULL");

    // 3) wallethistory table (older deployments log all debits here)
    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `wallethistory` (
      `id` INT NOT NULL AUTO_INCREMENT,
      `userid` VARCHAR(255) NULL,
      `amount` VARCHAR(255) NULL,
      `balance` VARCHAR(255) NULL,
      `purpose` VARCHAR(255) NULL,
      `status` VARCHAR(255) NULL,
      `type` VARCHAR(255) NULL,
      `date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(),
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 4) transactions table (older deployments — optional, no fail if missing)
    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `transactions` (
      `id` INT NOT NULL AUTO_INCREMENT,
      `uid` INT NULL,
      `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      `description` TEXT NULL,
      `txn_ref` VARCHAR(128) NULL,
      `date` DATETIME NULL,
      `status` VARCHAR(32) NULL DEFAULT 'success',
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $done = true;
}
_v2EnsureWalletSchema($ahk_conn);

// ====== ACTION HANDLERS ======
$actionMsg = '';
$actionType = '';

// 1. Delete User
if (isset($_GET['deleteuser']) && $_GET['deleteuser'] == 1 && !empty($_GET['id'])) {
    $id = (int)base64_decode(getSafe($_GET['id']));
    if (ahkQuery("DELETE FROM users WHERE id='$id'")) {
        $actionMsg = 'User Deleted Successfully';
        $actionType = 'success';
    } else {
        $actionMsg = 'User Delete Failed: ' . mysqli_error($ahk_conn);
        $actionType = 'error';
    }
}

// 2. Ban / Block User (status=0)
if (isset($_GET['banid']) && !empty($_GET['banid']) && isset($_GET['ban']) && $_GET['ban'] == '1') {
    $banid = (int)base64_decode(getSafe($_GET['banid']));
    if (ahkQuery("UPDATE users SET status='0' WHERE id='$banid'")) {
        $actionMsg = 'User Banned/Blocked Successfully';
        $actionType = 'success';
    } else {
        $actionMsg = 'Ban Failed';
        $actionType = 'error';
    }
}

// 3. Activate / Unblock User (status=1)
if (isset($_GET['apid']) && !empty($_GET['apid']) && isset($_GET['approve']) && $_GET['approve'] == '1') {
    $apid = (int)base64_decode(getSafe($_GET['apid']));
    if (ahkQuery("UPDATE users SET status='1' WHERE id='$apid'")) {
        $actionMsg = 'User Activated/Unblocked Successfully';
        $actionType = 'success';
    } else {
        $actionMsg = 'Activation Failed';
        $actionType = 'error';
    }
}

// 4. Reset Password
if (isset($_GET['reset']) && $_GET['reset'] == 1 && !empty($_GET['id'])) {
    $id = (int)base64_decode(getSafe($_GET['id']));
    $vpass = rand(0, 999999);
    $vpassStr = str_pad($vpass, 6, '0', STR_PAD_LEFT);
    $pass = password_hash($vpassStr, PASSWORD_DEFAULT);
    if (ahkQuery("UPDATE users SET password='$pass' WHERE id='$id'")) {
        $actionMsg = 'Password Reset Done. New Password: ' . $vpassStr . ' (Save it!)';
        $actionType = 'success';
    } else {
        $actionMsg = 'Password Reset Failed';
        $actionType = 'error';
    }
}

// 5. Login As User
if (isset($_GET['login']) && $_GET['login'] == 1 && !empty($_GET['phone'])) {
    $phone = base64_decode(getSafe($_GET['phone']));
    $_SESSION['adminasuser'] = true;
    $_SESSION['adminusername'] = $_SESSION['phone'];
    $_SESSION['phone'] = $phone;
    echo '<script>window.location="../index.php";</script>';
    die();
}

// 7. FRAUD LIST — Remove from List (remember current gap; if grows later user auto-reappears)
if (isset($_GET['ignore_fraud']) && $_GET['ignore_fraud'] == 1 && !empty($_GET['id'])) {
    $ignoreId = (int)base64_decode(getSafe($_GET['id']));
    $u = fetchRow("SELECT id, phone, balance FROM users WHERE id='$ignoreId' LIMIT 1");
    if ($u) {
        $bal = (float)($u['balance'] ?? 0);
        $phn = mysqli_real_escape_string($ahk_conn, $u['phone'] ?? '');
        $idStr = (string)$u['id'];

        $wt = 0; $rw = @mysqli_fetch_assoc(@mysqli_query($ahk_conn,
            "SELECT SUM(amount+0) AS s FROM wallet_transactions WHERE amount>0 AND UPPER(txn_status) IN ('SUCCESS','CREDIT','ADMIN CREDIT') AND phone='$phn'"));
        if ($rw) $wt = (float)($rw['s'] ?? 0);

        $wa = 0; $rw = @mysqli_fetch_assoc(@mysqli_query($ahk_conn,
            "SELECT SUM((amount+0)) AS s FROM wallet WHERE status='success' AND (amount+0)>0 AND phone='$phn'"));
        if ($rw) $wa = (float)($rw['s'] ?? 0);

        $wh = 0; $rw = @mysqli_fetch_assoc(@mysqli_query($ahk_conn,
            "SELECT SUM((amount+0)) AS s FROM wallethistory WHERE UPPER(type)='CREDIT' AND UPPER(status)='1' AND LOWER(COALESCE(purpose,'')) NOT LIKE '%refer%' AND LOWER(COALESCE(purpose,'')) NOT LIKE '%bonus%' AND userid='$idStr'"));
        if ($rw) $wh = (float)($rw['s'] ?? 0);

        $rb = 0; $rw = @mysqli_fetch_assoc(@mysqli_query($ahk_conn,
            "SELECT SUM((referral_balance+0)) AS s FROM referal WHERE UPPER(status)='1' AND referred_by='$phn'"));
        if ($rw) $rb = (float)($rw['s'] ?? 0);

        $deposit    = max($wt, $wa, $wh, 0);
        $totalLegit = $deposit + $rb;
        $gap        = max(0, $bal - $totalLegit);

        $gapStr = number_format($gap, 2, '.', '');
        if (ahkQuery("UPDATE users SET fraud_ignore_gap = '$gapStr' WHERE id='$ignoreId' LIMIT 1")) {
            $actionMsg = '✅ User removed from Fraud list. Will auto-reappear ONLY if new Extra Gap grows beyond ₹' . number_format($gap,2) . '.';
            $actionType = 'success';
        } else {
            $actionMsg = 'Failed to remove from fraud list: ' . mysqli_error($ahk_conn);
            $actionType = 'error';
        }
    } else {
        $actionMsg = 'User not found for ignore';
        $actionType = 'error';
    }
}

// 6. POST: Credit / Debit wallet balance
if (isset($_POST['bd_action']) && !empty($_POST['user_id']) && !empty($_POST['bd_amount'])) {
    try {
        $user_id = (int)getSafe($_POST['user_id']);
        $amount = (float)getSafe($_POST['bd_amount']);
        $note = getSafe($_POST['bd_note'] ?? 'Admin manual');
        $bd_action = getSafe($_POST['bd_action']); // credit/debit

        if ($amount <= 0) {
            $actionMsg = 'Amount must be greater than 0';
            $actionType = 'error';
        } else {
            $user = fetchRow("SELECT id, phone, balance, name, type, email FROM users WHERE id='$user_id'");
            if (!$user) {
                $actionMsg = 'User not found';
                $actionType = 'error';
            } else {
                $phone = $user['phone'];
                $uName = $user['name'];
                $curBal = (float)$user['balance'];
                $ok = true;
                if (function_exists('mysqli_begin_transaction')) {
                    mysqli_begin_transaction($ahk_conn);
                } else {
                    ahkQuery("START TRANSACTION");
                }

                $deltaAbs = abs($amount);
                $txnType = 'Admin Credit';
                $deltaTxn = $deltaAbs;
                if ($bd_action === 'credit') {
                    $ok = $ok && ahkQuery("UPDATE users SET balance = balance + $deltaAbs WHERE id='$user_id'");
                    $newBal = $curBal + $deltaAbs;
                } else {
                    if ($curBal < $deltaAbs) {
                        if (function_exists('mysqli_rollback')) @mysqli_rollback($ahk_conn); else ahkQuery("ROLLBACK");
                        $actionMsg = 'Debit FAILED: Insufficient wallet balance. User has Rs.' . number_format($curBal,2) . ' but requested Rs.' . number_format($deltaAbs,2);
                        $actionType = 'error';
                        $ok = false;
                    } else {
                        $ok = $ok && ahkQuery("UPDATE users SET balance = balance - $deltaAbs WHERE id='$user_id'");
                        $txnType = 'Admin Debit';
                        $deltaTxn = -1 * abs($deltaAbs);
                        $newBal = $curBal - $deltaAbs;
                    }
                }
                $txnRef = 'ADMIN-' . strtoupper(substr(md5(uniqid((string)mt_rand() . $user_id, true)), 0, 10));
                $desc = $txnType . ' Rs.' . number_format($deltaAbs, 2) . ' | By: ' . $udata['name'] . ' | Note: ' . $note . ' | User: ' . $uName . ' (' . $phone . ')';

                // TRY to log in transactions -> wallet_transactions -> wallethistory. ANY success = $logged=true. Never fail op.
                if ($ok) {
                    $logged = false;
                    // 1) transactions (older deployments)
                    $st = @mysqli_prepare($ahk_conn, "INSERT INTO transactions (uid, amount, description, txn_ref, date, status) VALUES (?, ?, ?, ?, NOW(), 'success')");
                    if ($st) { @mysqli_stmt_bind_param($st, 'idss', $user_id, $deltaTxn, $desc, $txnRef); if (@mysqli_stmt_execute($st)) $logged = true; @mysqli_stmt_close($st); }
                    // 2) wallet_transactions (goodprintportal standard)
                    if (!$logged) {
                        $st2 = @mysqli_prepare($ahk_conn, "INSERT INTO wallet_transactions (order_id, session_id, phone, email, amount, txn_status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                        if ($st2) {
                            $sess = session_id() ?: ('adm_' . $txnRef);
                            $em = $user['email'] ?? '';
                            $stat = strtoupper(str_replace('Admin ', '', $txnType));
                            @mysqli_stmt_bind_param($st2, 'ssssds', $txnRef, $sess, $phone, $em, $deltaTxn, $stat);
                            if (@mysqli_stmt_execute($st2)) $logged = true;
                            @mysqli_stmt_close($st2);
                        }
                    }
                    // 3) wallethistory (legacy scripts read debits from here)
                    if (!$logged || true) {
                        $whType = ($bd_action === 'credit') ? 'Credit' : 'Debit';
                        $whAmt = number_format($deltaAbs, 2, '.', '');
                        $whBal = isset($newBal) ? number_format($newBal, 2, '.', '') : $curBal;
                        $whPurpose = $txnType . ' - ' . $note . ' (Ref: ' . $txnRef . ')';
                        $whStatus = '1';
                        $st3 = @mysqli_prepare($ahk_conn, "INSERT INTO wallethistory (userid, amount, balance, purpose, status, type, date) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                        if ($st3) {
                            $uidStr = (string)$user_id;
                            @mysqli_stmt_bind_param($st3, 'ssssss', $uidStr, $whAmt, $whBal, $whPurpose, $whStatus, $whType);
                            @mysqli_stmt_execute($st3);
                            @mysqli_stmt_close($st3);
                            $logged = true;
                        }
                    }
                }

                if ($ok) {
                    if (function_exists('mysqli_commit')) {
                        @mysqli_commit($ahk_conn);
                    } else {
                        ahkQuery("COMMIT");
                    }
                    $actionMsg = $txnType . ' of Rs.' . number_format($deltaAbs, 2) . ' done for user ' . htmlspecialchars($uName) . ' (' . htmlspecialchars($phone) . ')';
                    $actionType = 'success';
                } else {
                    if (function_exists('mysqli_rollback')) {
                        @mysqli_rollback($ahk_conn);
                    } else {
                        ahkQuery("ROLLBACK");
                    }
                    $dbErr = mysqli_error($ahk_conn);
                    $actionMsg = 'Wallet operation failed: ' . ($dbErr ? $dbErr : 'Unknown DB error');
                    $actionType = 'error';
                }
            }
        }
    } catch (Throwable $e) {
        $actionMsg = 'Server Error: ' . $e->getMessage() . ' (File: ' . basename($e->getFile()) . ':' . $e->getLine() . ')';
        $actionType = 'error';
    }
}

// ====== FAST USERS LIST: Search + LIMIT to avoid loading 6500+ users ======
$search_q    = isset($_GET['search_q']) ? trim((string)$_GET['search_q']) : '';
$highBalance = isset($_GET['high_balance']) && $_GET['high_balance'] === '1';
$fraudFilter = isset($_GET['fraud_check']) && $_GET['fraud_check'] === '1';
$page        = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage     = 50;
$offset      = ($page - 1) * $perPage;

// --- Build WHERE clause with safe escaping (project-standard: same as ahkQuery usage) ---
$whereSql = " WHERE LOWER(type) <> 'admin' ";
$searchSql = '';
$balanceSql = '';
if ($search_q !== '') {
    $sq = mysqli_real_escape_string($ahk_conn, $search_q);
    $searchSql = " AND ( phone LIKE '%{$sq}%' OR name LIKE '%{$sq}%' OR email LIKE '%{$sq}%' ) ";
}
if ($highBalance) {
    $balanceSql = " AND (balance + 0) > 120 ";
}
$fullWhere = $whereSql . $searchSql . $balanceSql;

// ===== FRAUD CHECK SUBQUERY: Total credited/deposited per user (from ALL 3 wallet tables) + REFERRAL BONUS =====
// Logic: Total Legit Inflow = (sum of all successful deposits/payment-credits/admin-credits from wallet tables)
//                                 + (sum of Refer & Earn bonus received by user from the `referal` table)
// NOTE: gpGiveReferralBonusIfEligible() only updates users.balance + inserts referal table.
//       It does NOT insert into wallet/wallet_transactions/wallethistory. Hence we MUST add referal sum.
// FRAUD flag: Current balance > TOTAL LEGIT INFLOW (impossible without tampering)
// IGNORE LOGIC: fraud_ignore_gap column stores "admin accepted threshold".
//       If (bal - totalLegit) <= fraud_ignore_gap  =>  hide from list.
//       If (bal - totalLegit) >  fraud_ignore_gap  =>  auto reappears (new fraud detected).
$fraudJoinSQL = '';
$fraudWhereExtra = '';
if ($fraudFilter) {
    $fraudJoinSQL = "
        LEFT JOIN (
            SELECT phone, SUM(amt) AS wt_deposit FROM (
                SELECT phone, (amount+0) AS amt FROM wallet_transactions WHERE amount > 0 AND UPPER(txn_status) IN ('SUCCESS','CREDIT','ADMIN CREDIT')
            ) AS wtg GROUP BY phone
        ) wt ON wt.phone = users.phone
        LEFT JOIN (
            SELECT phone, SUM((amount+0)) AS w_deposit FROM wallet WHERE status='success' AND (amount+0) > 0 GROUP BY phone
        ) wa ON wa.phone = users.phone
        LEFT JOIN (
            SELECT userid, SUM((amount+0)) AS wh_deposit FROM wallethistory
             WHERE UPPER(type)='CREDIT' AND UPPER(status)='1'
               AND LOWER(COALESCE(purpose,'')) NOT LIKE '%refer%'
               AND LOWER(COALESCE(purpose,'')) NOT LIKE '%bonus%'
             GROUP BY userid
        ) wh ON wh.userid = users.id
        LEFT JOIN (
            -- Sum of referral bonus this user has EARNED by referring others (= money ADDED to his wallet)
            SELECT referred_by AS ref_phone, SUM((referral_balance+0)) AS ref_total
              FROM referal WHERE UPPER(status)='1' GROUP BY referred_by
        ) rb ON rb.ref_phone = users.phone
        LEFT JOIN (
            -- Fallback: for old records where referal rows missing, count how many users this phone has referred
            -- and multiply by settings.ref_bal (default 10). This catches old deployments.
            SELECT referred_by AS fallback_phone,
                   COUNT(*) AS ref_cnt
              FROM referal WHERE UPPER(status)='1' AND LENGTH(COALESCE(referred_by,''))>0
             GROUP BY referred_by
        ) rbf ON rbf.fallback_phone = users.phone
        LEFT JOIN (SELECT COALESCE(CAST(ref_bal AS DECIMAL(10,2)),10) AS ref_bal_amt FROM settings WHERE id=1 LIMIT 1) set_rb ON 1=1
    ";
    $biggestDeposit = "GREATEST(COALESCE(wt.wt_deposit,0),COALESCE(wa.w_deposit,0),COALESCE(wh.wh_deposit,0),0)";
    $bonusSum       = "COALESCE(rb.ref_total, 0)";
    // Fallback bonus for old data: if explicit rb.ref_total is 0 but referrals count exists -> estimate
    $bonusSafe      = "IF($bonusSum > 0, $bonusSum, COALESCE(rbf.ref_cnt,0)*COALESCE(set_rb.ref_bal_amt,10))";
    $totalLegitSQL  = "( $biggestDeposit + $bonusSafe )";
    $gapSQL         = "GREATEST(0, (users.balance + 0) - $totalLegitSQL)";

    $fraudWhereExtra = "
        AND ( (users.balance + 0) > $totalLegitSQL )
        AND ( $gapSQL > COALESCE(users.fraud_ignore_gap, 0) )
    ";
    $fullWhere = $fullWhere . $fraudWhereExtra;
    // Save computed values so SELECT can reuse without rewriting tons of SQL
    $GLOBALS['_fraudTotalLegit'] = $totalLegitSQL;
    $GLOBALS['_fraudBonusSafe']  = $bonusSafe;
    $GLOBALS['_fraudGapSQL']     = $gapSQL;
    $GLOBALS['_fraudDepositMax'] = $biggestDeposit;
}

// --- Count total (admin-visible) rows for pagination ---
$totalUsers = 0;
$countSQL = "SELECT COUNT(DISTINCT users.id) AS cnt FROM users " . $fraudJoinSQL . $fullWhere;
$r = ahkQuery($countSQL);
if ($r && ($rw = mysqli_fetch_assoc($r))) $totalUsers = (int)$rw['cnt'];
$totalPages = max(1, (int)ceil($totalUsers / $perPage));
if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $perPage; }

// --- Fetch ONLY current page rows ---
$perPageI = (int)$perPage;
$offsetI  = (int)$offset;
$selectCols = "users.id, users.phone, users.name, users.email, users.type, users.status, users.balance";
if ($fraudFilter) {
    $_dep  = $GLOBALS['_fraudDepositMax'];
    $_bon  = $GLOBALS['_fraudBonusSafe'];
    $_leg  = $GLOBALS['_fraudTotalLegit'];
    $_gap  = $GLOBALS['_fraudGapSQL'];
    $selectCols .= ",
        $_dep AS _total_deposit,
        $_bon AS _referral_bonus,
        $_leg AS _total_legit,
        $_gap AS _extra_gap,
        COALESCE(users.fraud_ignore_gap, 0) AS _ignore_gap";
}
$dataSQL = "SELECT DISTINCT " . $selectCols . " FROM users "
         . $fraudJoinSQL
         . $fullWhere
         . " ORDER BY users.id DESC LIMIT {$perPageI} OFFSET {$offsetI}";
$usersList = [];
$res = ahkQuery($dataSQL);
if ($res && mysqli_num_rows($res) > 0) {
    while ($row = mysqli_fetch_assoc($res)) $usersList[] = $row;
}
$_v2HbStr = $highBalance ? ' [HIGH_BALANCE >120 ON]' : '';
$_v2FcStr = $fraudFilter ? ' [FRAUD_CHECK Bal>Deposit ON]' : '';
_v2DebugBanner("Query OK: totalUsers={$totalUsers}, page={$page}/{$totalPages}, showing=".count($usersList)." perPage={$perPageI}{$_v2HbStr}{$_v2FcStr}", 'ok');

// For pagination query-string building (preserve search + tab):
function _v2BuildQS($overrides = []) {
    $qs = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === null) unset($qs[$k]); else $qs[$k] = $v;
    }
    return http_build_query($qs);
}

function _v2UserStats($ahk_conn) {
    $out = ['total'=>0,'active'=>0,'blocked'=>0];
    $r = @mysqli_query($ahk_conn,
        "SELECT COUNT(*) AS c, SUM(CASE WHEN status='1' THEN 1 ELSE 0 END) AS a FROM users WHERE LOWER(type)<>'admin'"
    );
    if ($r && ($rw = mysqli_fetch_assoc($r))) {
        $out['total']   = (int)($rw['c'] ?? 0);
        $out['active']  = (int)($rw['a'] ?? 0);
        $out['blocked'] = max(0, $out['total'] - $out['active']);
    }
    return $out;
}
$stats = _v2UserStats($ahk_conn);

include('../template/ahkweb/header.php');
if ($actionMsg) {
    showAlert($actionMsg, '', $actionType);
}
?>
<style>
.bd-form { display:flex; gap:6px; align-items:center; justify-content:center; flex-wrap:wrap; }
.bd-form input, .bd-form select { padding:5px 8px; font-size:12px; border-radius:6px; border:1px solid #ddd; min-width:80px; }
.bd-form button { padding:5px 12px; font-size:12px; border-radius:6px; border:0; cursor:pointer; font-weight:600; }
.btn-credit { background:#28a745; color:#fff; }
.btn-debit { background:#dc3545; color:#fff; }
.act-wrap { display:flex; gap:10px; align-items:center; justify-content:center; flex-wrap:wrap; }
.act-wrap a, .act-wrap button { font-size:22px; padding:4px 8px; border-radius:8px; text-decoration:none; }
.badge-wallet { display:inline-block; padding:4px 10px; border-radius:10px; background:#e3f2fd; color:#1565c0; font-weight:700; }
.badge-status { padding:4px 10px; border-radius:10px; font-weight:700; }
.bs-active { background:#d4edda; color:#155724; }
.bs-block { background:#f8d7da; color:#721c24; }

/* ---- FAST SEARCH BAR ---- */
.v2-searchbar {
    display:flex; gap:12px; align-items:center; justify-content:space-between;
    flex-wrap:wrap; margin: 8px 0 16px; padding:18px 22px;
    background: linear-gradient(135deg, #f6f9ff 0%, #eef5ff 100%);
    border-radius: 14px;
    border: 1px solid #dbe7fb;
    box-shadow: 0 2px 8px rgba(100,130,200,0.08), inset 0 1px 0 rgba(255,255,255,0.8);
}
.v2-search-left { display:flex; gap:10px; align-items:center; flex-wrap:wrap; flex:1; min-width: 280px; }
.v2-search-left form { display:flex; gap:8px; align-items:center; flex:1; flex-wrap:wrap; }
.v2-search-left input[type=text] {
    flex:1; min-width: 240px; padding: 11px 16px;
    border-radius: 10px; border: 1.5px solid #c9d9f7;
    font-size: 14px; font-weight: 500; font-family: inherit;
    background: #fff; color:#1a2b4a;
    outline: none; transition: all 0.2s ease;
    box-shadow: inset 0 1px 2px rgba(0,0,0,0.03);
}
.v2-search-left input[type=text]:focus {
    border-color: #4f7bff;
    box-shadow: 0 0 0 3px rgba(79,123,255,0.15), inset 0 1px 2px rgba(0,0,0,0.03);
}
.v2-search-left button[type=submit] {
    padding: 11px 22px;
    background: linear-gradient(135deg, #4f7bff 0%, #6a5cff 100%);
    color: #fff; border: none; border-radius: 10px;
    font-size: 14px; font-weight: 600; cursor: pointer;
    font-family: inherit; letter-spacing: 0.2px;
    box-shadow: 0 4px 14px rgba(79,123,255,0.35), inset 0 1px 0 rgba(255,255,255,0.2);
    transition: all 0.2s ease;
}
.v2-search-left button[type=submit]:hover {
    transform: translateY(-1px);
    box-shadow: 0 7px 20px rgba(79,123,255,0.5), inset 0 1px 0 rgba(255,255,255,0.25);
}
.v2-search-left a.v2-reset {
    padding: 11px 18px; border-radius: 10px;
    background: #fff; color: #4f5b75;
    border: 1.5px solid #d7dfee;
    text-decoration: none; font-size: 14px; font-weight: 600;
    transition: all 0.2s ease;
}
.v2-search-left a.v2-reset:hover { border-color:#4f7bff; color:#4f7bff; }
.v2-stats { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
.v2-stat {
    padding: 7px 14px; border-radius: 10px; background:#fff;
    border: 1px solid #e1e9fb; font-size: 12.5px; font-weight: 600;
    color:#3e4e75; display:flex; align-items:center; gap:6px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}
.v2-stat .dot { width:8px; height:8px; border-radius:999px; display:inline-block; }
.v2-stat-total .dot { background: linear-gradient(135deg,#4f7bff,#6a5cff); }
.v2-stat-active .dot { background: linear-gradient(135deg,#43e97b,#38f9d7); }
.v2-stat-blocked .dot { background: linear-gradient(135deg,#f093fb,#f5576c); }
.v2-stat-showing .dot { background: linear-gradient(135deg,#f6d365,#fda085); }

/* ---- PAGINATION ---- */
.v2-pager {
    display:flex; gap:6px; align-items:center; justify-content:center;
    flex-wrap:wrap; margin-top: 22px;
}
.v2-pager a, .v2-pager span {
    padding: 8px 14px; border-radius: 8px;
    border: 1.5px solid #dbe0ee;
    background: #fff; color:#3b4868;
    text-decoration: none; font-weight: 600; font-size: 13px;
    transition: all 0.18s ease; min-width: 38px; text-align:center;
}
.v2-pager a:hover {
    border-color: #4f7bff; color:#4f7bff;
    box-shadow: 0 4px 12px rgba(79,123,255,0.18);
    transform: translateY(-1px);
}
.v2-pager .curr {
    background: linear-gradient(135deg, #4f7bff 0%, #6a5cff 100%);
    color:#fff; border-color: transparent;
    box-shadow: 0 4px 14px rgba(79,123,255,0.35);
}
.v2-pager .dis { opacity: 0.45; cursor: not-allowed; pointer-events:none; }
.v2-pager-info {
    margin-top: 10px; text-align: center; font-size: 12.5px;
    color: #6b7693; font-weight: 500;
}
.v2-empty {
    padding: 70px 30px; text-align:center; color:#6b7693;
}
.v2-empty .icon { font-size: 64px; opacity: 0.5; margin-bottom: 14px; }
.v2-empty h4 { margin:0 0 6px; color:#3b4868; font-size:19px; }
.v2-empty p { margin:0; font-size:13.5px; }
</style>

<div class="page-wrapper">
    <div class="page-content">
        <div class="card radius-10">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between flex-wrap">
                    <div>
                        <h5 class="mb-0">👥 All Users List (Admin v2)</h5>
                        <p class="text-muted mb-0">Credit / Debit balance, Block / Unblock, Delete users, Reset password, Login as user</p>
                    </div>
                    <a href="adduser.php" class="btn btn-primary btn-sm">+ Add New User</a>
                </div>
                <hr>

                <!-- ===== FAST SEARCH + STATS BAR ===== -->
                <div class="v2-searchbar">
                    <div class="v2-search-left">
                        <form method="GET" autocomplete="off">
                            <input type="text" name="search_q" placeholder="Search by Phone, Name, or Email..." value="<?= htmlspecialchars($search_q) ?>">
                            <button type="submit">🔍 Search</button>
                            <?php if ($search_q !== '' || $highBalance || $fraudFilter) { ?>
                                <a href="users_v2.php" class="v2-reset">✕ Clear All</a>
                            <?php } ?>
                        </form>
                        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-left:4px;">
                            <?php if ($highBalance) { ?>
                                <a href="?<?= _v2BuildQS(['high_balance' => null, 'page' => 1]) ?>" style="padding:10px 14px; border-radius:10px; background:linear-gradient(135deg,#ff9a9e,#fad0c4); border:1px solid #f5576c55; color:#9c1f3d; font-size:13px; font-weight:700; text-decoration:none; box-shadow:0 2px 8px rgba(245,87,108,0.18);">
                                    💰 BAL > ₹120 🔴 ON — click to remove
                                </a>
                            <?php } else { ?>
                                <a href="?<?= _v2BuildQS(['high_balance' => '1', 'page' => 1]) ?>" style="padding:10px 14px; border-radius:10px; background:#fff; border:1.5px dashed #4f7bff88; color:#4f5b75; font-size:13px; font-weight:600; text-decoration:none; transition:all 0.2s ease;" onmouseover="this.style.background='#eef3ff';this.style.borderStyle='solid';" onmouseout="this.style.background='#fff';this.style.borderStyle='dashed';">
                                    💰 Show only Balance &gt; ₹120
                                </a>
                            <?php } ?>
                            <?php if ($fraudFilter) { ?>
                                <a href="?<?= _v2BuildQS(['fraud_check' => null, 'page' => 1]) ?>" style="padding:10px 14px; border-radius:10px; background:linear-gradient(135deg,#1a1a2e,#16213e); border:1px solid #e9456055; color:#f8f9fa; font-size:13px; font-weight:700; text-decoration:none; box-shadow:0 2px 10px rgba(233,69,96,0.35);">
                                    🚩 FRAUD CHECK: Bal &gt; Deposit 🔴 ON — click to remove
                                </a>
                            <?php } else { ?>
                                <a href="?<?= _v2BuildQS(['fraud_check' => '1', 'page' => 1]) ?>" style="padding:10px 14px; border-radius:10px; background:#fff; border:1.5px dashed #e94560; color:#b0223a; font-size:13px; font-weight:700; text-decoration:none; transition:all 0.2s ease;" onmouseover="this.style.background='#fff0f3';this.style.borderStyle='solid';" onmouseout="this.style.background='#fff';this.style.borderStyle='dashed';">
                                    🚩 Fraud Check (Bal &gt; Deposit)
                                </a>
                            <?php } ?>
                        </div>
                    </div>
                    <div class="v2-stats">
                        <div class="v2-stat v2-stat-total"><span class="dot"></span> Total: <strong><?= (int)$stats['total'] ?></strong></div>
                        <div class="v2-stat v2-stat-active"><span class="dot"></span> Active: <strong><?= (int)$stats['active'] ?></strong></div>
                        <div class="v2-stat v2-stat-blocked"><span class="dot"></span> Blocked: <strong><?= (int)$stats['blocked'] ?></strong></div>
                        <?php if ($highBalance) {
                            $hc = @mysqli_query($ahk_conn, "SELECT COUNT(*) AS c FROM users WHERE LOWER(type)<>'admin' AND (balance+0) > 120");
                            $hcv = $hc && ($hr = mysqli_fetch_assoc($hc)) ? (int)$hr['c'] : 0;
                        ?>
                            <div class="v2-stat" style="background:linear-gradient(135deg,#fff5f5,#ffe0e0);border-color:#f5576c55;color:#9c1f3d;"><span class="dot" style="background:linear-gradient(135deg,#f093fb,#f5576c);"></span> Users &gt;₹120: <strong><?= $hcv ?></strong></div>
                        <?php } ?>
                        <?php if ($fraudFilter) { ?>
                            <div class="v2-stat" style="background:linear-gradient(135deg,#1a1a2e,#16213e);border-color:#e9456055;color:#fff;"><span class="dot" style="background:linear-gradient(135deg,#f857a6,#ff5858);"></span> 🚩 Fraud Hits: <strong><?= (int)$totalUsers ?></strong></div>
                        <?php } ?>
                        <div class="v2-stat v2-stat-showing"><span class="dot"></span> Showing: <strong><?= count($usersList) ?></strong> / <?= (int)$totalUsers ?></div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="example2" class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center">SL</th>
                                <th class="text-center">Phone / Username</th>
                                <th class="text-center">Name</th>
                                <th class="text-center">Email</th>
                                <th class="text-center">Type</th>
                                <th class="text-center">Status</th>
                                <?php if ($fraudFilter) { ?>
                                    <th class="text-center">Deposit</th>
                                    <th class="text-center">Referral Bonus</th>
                                    <th class="text-center" style="background:#e8f5e9;">Total Legit (Dep+Ref)</th>
                                    <th class="text-center" style="background:#fff0f3;">Balance (🚩Fraud)</th>
                                    <th class="text-center" style="background:#fff0f3;">Extra (Bal − Legit)</th>
                                <?php } else { ?>
                                    <th class="text-center">Balance</th>
                                <?php } ?>
                                <th class="text-center">Credit / Debit</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        if (!empty($usersList)) {
                            $x = $offset;
                            foreach ($usersList as $data) {
                                $x++;
                                $bal = (float)($data['balance'] ?? 0);
                                $active = ((string)$data['status'] === '1');
                                $tdep = $fraudFilter ? (float)($data['_total_deposit'] ?? 0) : 0;
                                $tref = $fraudFilter ? (float)($data['_referral_bonus'] ?? 0) : 0;
                                $tleg = $fraudFilter ? (float)($data['_total_legit'] ?? 0) : 0;
                                // Use SQL-calculated gap if fraud filter on (guarantees same value as WHERE condition)
                                $gap  = $fraudFilter ? (float)($data['_extra_gap'] ?? 0) : 0;
                                $ignGap = $fraudFilter ? (float)($data['_ignore_gap'] ?? 0) : 0;
                                $wasIgnored = $fraudFilter && $ignGap > 0;
                        ?>
                            <tr<?= $fraudFilter ? ' style="background:linear-gradient(180deg,#fff5f5 0%,#fff 100%);"' : '' ?>>
                                <td class="text-center"><?= $x ?></td>
                                <td class="text-center"><strong><?= htmlspecialchars($data['phone']) ?></strong></td>
                                <td class="text-center"><?= htmlspecialchars($data['name']) ?></td>
                                <td class="text-center"><?= htmlspecialchars($data['email']) ?></td>
                                <td class="text-center"><span class="badge bg-info"><?= strtoupper(htmlspecialchars($data['type'])) ?></span></td>
                                <td class="text-center">
                                    <?php if ($active) { ?>
                                        <span class="badge-status bs-active">ACTIVE</span>
                                    <?php } else { ?>
                                        <span class="badge-status bs-block">BLOCKED</span>
                                    <?php } ?>
                                </td>
                                <?php if ($fraudFilter) { ?>
                                    <td class="text-center"><span class="badge-wallet">₹<?= number_format($tdep, 2) ?></span></td>
                                    <td class="text-center"><span class="badge-wallet" style="background:#f3e5f5;color:#6a1b9a;">🎁 ₹<?= number_format($tref, 2) ?></span></td>
                                    <td class="text-center"><span class="badge-wallet" style="background:#e8f5e9;color:#2e7d32;font-weight:800;">₹<?= number_format($tleg, 2) ?></span></td>
                                    <td class="text-center"><span class="badge-wallet" style="background:#ffebee;color:#c62828;font-weight:800;">₹<?= number_format($bal, 2) ?></span></td>
                                    <td class="text-center"><span style="display:inline-block;padding:4px 10px;border-radius:10px;background:linear-gradient(135deg,#1a1a2e,#16213e);color:#fff;font-weight:800;font-size:12px;box-shadow:0 2px 8px rgba(233,69,96,0.3);">🚩 +₹<?= number_format($gap, 2) ?></span></td>
                                <?php } else { ?>
                                    <td class="text-center"><span class="badge-wallet">₹<?= number_format($bal, 2) ?></span></td>
                                <?php } ?>
                                <td class="text-center">
                                    <form class="bd-form" method="POST">
                                        <input type="hidden" name="user_id" value="<?= (int)$data['id'] ?>">
                                        <input type="number" name="bd_amount" min="1" step="0.01" placeholder="Amount ₹" required>
                                        <input type="text" name="bd_note" placeholder="Note (optional)">
                                        <select name="bd_action" required>
                                            <option value="credit">Credit +</option>
                                            <option value="debit">Debit -</option>
                                        </select>
                                        <button type="submit" class="btn-credit" onclick="if(this.form.bd_action.value==='debit'){this.classList.remove('btn-credit');this.classList.add('btn-debit');}else{this.classList.add('btn-credit');this.classList.remove('btn-debit');}return true;">GO</button>
                                    </form>
                                </td>
                                <td class="text-center">
                                    <div class="act-wrap">
                                        <a class="bg-light-success text-success" title="Login as user" href="?phone=<?= base64_encode($data['phone']) ?>&login=1">👤</a>
                                        <a class="bg-light-success text-success" title="Reset password" href="?id=<?= base64_encode($data['id']) ?>&reset=1">🔄</a>
                                        <a class="bg-light-success text-success" title="Edit user" href="EditUser.php?id=<?= base64_encode($data['id']) ?>">✏️</a>
                                        <a class="bg-light-primary text-primary" title="Balance transfer to this user" href="BalanceTransfer.php?username=<?= urlencode($data['email']) ?>">💸</a>
                                        <?php if (!$active) { ?>
                                            <a class="bg-light-success text-success" title="Activate / Unblock" href="?apid=<?= base64_encode($data['id']) ?>&approve=1">✅</a>
                                        <?php } else { ?>
                                            <a class="bg-light-danger text-danger" title="Block / Ban user" href="?banid=<?= base64_encode($data['id']) ?>&ban=1">🚫</a>
                                        <?php } ?>
                                        <?php if ($fraudFilter) { ?>
                                            <a class="text-white" title="Remove from Fraud list (auto re-appears only if gap grows)"
                                               style="background:linear-gradient(135deg,#ff9966,#ff5e62);font-weight:700;font-size:11px;padding:5px 8px;border-radius:8px;text-decoration:none;box-shadow:0 2px 6px rgba(255,94,98,0.35);"
                                               href="?<?= _v2BuildQS(['ignore_fraud' => '1', 'id' => base64_encode($data['id']), 'page' => $page]) ?>"
                                               onclick="return confirm('Remove this user from Fraud list?\n\nIf Extra Gap stays ≤ ₹<?= number_format($gap,2) ?> — will stay hidden.\nIf user does NEW fraud (gap grows bigger) — automatically comes back to the list.\n\nProceed?');">
                                                👁️‍🗨️ Remove
                                            </a>
                                        <?php } ?>
                                        <a class="bg-light-danger text-danger" title="Delete user permanently" href="?deleteuser=1&id=<?= base64_encode($data['id']) ?>" onclick="return confirm('Permanently DELETE user? This cannot be undone.');">🗑️</a>
                                    </div>
                                    <?php if ($wasIgnored) { ?>
                                        <div style="margin-top:6px;">
                                            <span style="display:inline-block;padding:3px 9px;border-radius:8px;background:linear-gradient(135deg,#f6d365,#fda085);color:#7c2d12;font-size:11px;font-weight:700;">ℹ️ Ignored ≤ ₹<?= number_format($ignGap,2) ?></span>
                                        </div>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php
                            }
                        } else {
                            $cols = $fraudFilter ? 13 : 9;
                        ?>
                            <tr>
                                <td colspan="<?= $cols ?>">
                                    <div class="v2-empty">
                                        <div class="icon"><?= $fraudFilter ? '🛡️' : '🔍' ?></div>
                                        <h4><?= $fraudFilter ? 'No FRAUD users found 🎉' : 'No users found' ?></h4>
                                        <p><?php
                                            if ($fraudFilter && $search_q !== '') {
                                                echo 'No user matching search passed the fraud check (Bal > Total Deposit). System is clean for those users!';
                                            } elseif ($fraudFilter) {
                                                echo 'Great news — No user in the system has wallet balance greater than their total deposits. Click the dark FRAUD CHECK button above to remove filter.';
                                            } elseif ($search_q !== '' && $highBalance) {
                                                echo 'No user matches your search AND has balance > ₹120. Try removing search or balance filter.';
                                            } elseif ($highBalance) {
                                                echo 'No users found with wallet balance greater than ₹120. Click the red BAL>₹120 button above to remove filter.';
                                            } elseif ($search_q !== '') {
                                                echo 'Try a different search keyword or clear the search box.';
                                            } else {
                                                echo 'No users in the database yet.';
                                            }
                                        ?></p>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>

                <!-- ===== PAGINATION ===== -->
                <?php if ($totalPages > 1 || $search_q !== '') {
                    $start = $offset + 1;
                    $end = min($offset + $perPage, $totalUsers);
                ?>
                <div class="v2-pager-info">Showing <strong><?= (int)$start ?></strong> – <strong><?= (int)$end ?></strong> of <strong><?= (int)$totalUsers ?></strong> users</div>
                <div class="v2-pager">
                    <?php if ($page <= 1) { ?>
                        <span class="dis">« First</span>
                        <span class="dis">‹ Prev</span>
                    <?php } else { ?>
                        <a href="?<?= _v2BuildQS(['page' => 1]) ?>">« First</a>
                        <a href="?<?= _v2BuildQS(['page' => $page - 1]) ?>">‹ Prev</a>
                    <?php } ?>

                    <?php
                    $startPage = max(1, $page - 3);
                    $endPage = min($totalPages, $page + 3);
                    if ($startPage > 1) {
                        echo '<a href="?' . _v2BuildQS(['page' => 1]) . '">1</a>';
                        if ($startPage > 2) echo '<span>...</span>';
                    }
                    for ($i = $startPage; $i <= $endPage; $i++) {
                        if ($i == $page) {
                            echo '<span class="curr">' . (int)$i . '</span>';
                        } else {
                            echo '<a href="?' . _v2BuildQS(['page' => $i]) . '">' . (int)$i . '</a>';
                        }
                    }
                    if ($endPage < $totalPages) {
                        if ($endPage < $totalPages - 1) echo '<span>...</span>';
                        echo '<a href="?' . _v2BuildQS(['page' => $totalPages]) . '">' . (int)$totalPages . '</a>';
                    }
                    ?>

                    <?php if ($page >= $totalPages) { ?>
                        <span class="dis">Next ›</span>
                        <span class="dis">Last »</span>
                    <?php } else { ?>
                        <a href="?<?= _v2BuildQS(['page' => $page + 1]) ?>">Next ›</a>
                        <a href="?<?= _v2BuildQS(['page' => $totalPages]) ?>">Last »</a>
                    <?php } ?>
                </div>
                <?php } ?>

            </div>
        </div>
    </div>
</div>

<?php
include('../template/ahkweb/footer.php');
?>
<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<script src="../template/ahkweb/assets/js/jquery.min.js"></script>
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>
<script src="../template/ahkweb/assets/plugins/datatable/js/jquery.dataTables.min.js"></script>
<script src="../template/ahkweb/assets/plugins/datatable/js/dataTables.bootstrap5.min.js"></script>

</body>
</html>
