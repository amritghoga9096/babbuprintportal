<?php
checkSession();
// TRICK BUY SERVICE: ensure schema/helpers loaded (includes CREATE TABLE IF NOT EXISTS trick_orders + settings columns + pending count helper).
// Safe to include on every page; no-op if already loaded via trick_buy pages.
@include_once __DIR__ . '/../../admin/trick_buy_common.php';

// ===== LOGIN POPUP SETTINGS: Auto-create table + load settings =====
$GLOBALS['gpPopupSettings'] = [
    'enabled' => 0,
    'show_once_per_session' => 1,
    'title' => '🎉 Welcome — Important Updates',
    'cards' => [
        ['enabled' => 1, 'icon' => '✅', 'title' => 'Voter PDF (Without OTP)', 'desc' => 'Unlimited Data लगाओ और Super Fast PDF पाओ!', 'bg' => 'linear-gradient(135deg,#16a34a 0%,#15803d 100%)', 'tx' => '#ffffff', 'border' => '#86efac'],
        ['enabled' => 1, 'icon' => '🚀', 'title' => 'LL Exam Data', 'desc' => 'Unlimited Data उपलब्ध है, एकदम Fast Result!', 'bg' => 'linear-gradient(135deg,#f97316 0%,#ea580c 100%)', 'tx' => '#ffffff', 'border' => '#fdba74'],
        ['enabled' => 1, 'icon' => '🎧', 'title' => 'All Services Working', 'desc' => 'सभी सर्विसेज काम कर रही हैं। कोई Issue हो तो तुरंत SMS/WhatsApp करें।', 'bg' => 'linear-gradient(135deg,#7e22ce 0%,#6b21a8 100%)', 'tx' => '#ffffff', 'border' => '#d8b4fe'],
        ['enabled' => 1, 'icon' => '💳', 'title' => 'Auto Refund System', 'desc' => 'अगर कोई सर्विस नहीं मिली, तो 24 Hours इंतज़ार करें, पैसा Auto-Refund हो जाएगा!', 'bg' => 'linear-gradient(135deg,#be185d 0%,#9d174d 100%)', 'tx' => '#ffffff', 'border' => '#f9a8d4'],
        ['enabled' => 1, 'icon' => '⚠️', 'title' => 'NOTE', 'desc' => 'हमारे पोर्टल के साथ बेफ़िक्र होकर अपनी कमाई बढ़ाओ! धन्यवाद 🙏', 'bg' => 'linear-gradient(135deg,#fef3c7 0%,#fde68a 100%)', 'tx' => '#92400e', 'border' => '#f59e0b'],
    ],
    'updated_at' => null,
];
function _gpPopupSelfHeal($conn) {
    if (!$conn) return;
    static $done = null;
    if ($done === true) return;
    @mysqli_report(MYSQLI_REPORT_OFF);
    // 1. Create table if missing
    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS login_popup_settings (
        id INT PRIMARY KEY AUTO_INCREMENT,
        enabled TINYINT(1) NOT NULL DEFAULT 0,
        show_once_per_session TINYINT(1) NOT NULL DEFAULT 1,
        title VARCHAR(255) NOT NULL DEFAULT '🎉 Important Updates',
        cards_json LONGTEXT NOT NULL,
        updated_at DATETIME NULL,
        updated_by INT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // 2. Ensure columns
    $cols = [];
    $r = @mysqli_query($conn, "SHOW COLUMNS FROM login_popup_settings");
    if ($r) while ($row = mysqli_fetch_assoc($r)) $cols[] = $row['Field'];
    $add = function($n, $d) use ($conn, $cols) {
        if (!in_array($n, $cols, true)) @mysqli_query($conn, "ALTER TABLE login_popup_settings ADD COLUMN {$n} {$d}");
    };
    $add('enabled', "TINYINT(1) NOT NULL DEFAULT 0");
    $add('show_once_per_session', "TINYINT(1) NOT NULL DEFAULT 1");
    $add('title', "VARCHAR(255) NOT NULL DEFAULT '🎉 Important Updates'");
    $add('cards_json', "LONGTEXT NOT NULL");
    $add('updated_at', "DATETIME NULL");
    $add('updated_by', "INT NULL");
    // 3. Ensure at least 1 row exists (id=1)
    $chk = @mysqli_query($conn, "SELECT id FROM login_popup_settings ORDER BY id ASC LIMIT 1");
    if (!$chk || mysqli_num_rows($chk) === 0) {
        $defaults = $GLOBALS['gpPopupSettings'];
        $json = mysqli_real_escape_string($conn, json_encode($defaults['cards'], JSON_UNESCAPED_UNICODE));
        $titleE = mysqli_real_escape_string($conn, $defaults['title']);
        @mysqli_query($conn, "INSERT INTO login_popup_settings (id,enabled,show_once_per_session,title,cards_json) VALUES (1,0,1,'{$titleE}','{$json}')");
    }
    // 4. Load into global + ensure every card has 'enabled' key
    $row = null;
    $res = @mysqli_query($conn, "SELECT * FROM login_popup_settings ORDER BY id ASC LIMIT 1");
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        $cards = json_decode($row['cards_json'] ?? '', true);
        if (!is_array($cards) || count($cards) === 0) $cards = $GLOBALS['gpPopupSettings']['cards'];
        // Normalize: pad/truncate to 5 + add enabled key (default ON)
        $normalized = [];
        for ($i = 0; $i < 5; $i++) {
            $c = $cards[$i] ?? [];
            $defC = $GLOBALS['gpPopupSettings']['cards'][$i];
            $normalized[] = [
                'enabled' => (int)($c['enabled'] ?? $defC['enabled'] ?? 1),
                'icon'   => (string)($c['icon'] ?? $defC['icon']),
                'title'  => (string)($c['title'] ?? $defC['title']),
                'desc'   => (string)($c['desc'] ?? $defC['desc']),
                'bg'     => (string)($c['bg'] ?? $defC['bg']),
                'tx'     => (string)($c['tx'] ?? $defC['tx']),
                'border' => (string)($c['border'] ?? $defC['border']),
            ];
        }
        $GLOBALS['gpPopupSettings'] = [
            'enabled' => (int)($row['enabled'] ?? 0),
            'show_once_per_session' => (int)($row['show_once_per_session'] ?? 1),
            'title' => (string)($row['title'] ?? $GLOBALS['gpPopupSettings']['title']),
            'cards' => $normalized,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }
    $done = true;
}
if (!empty($GLOBALS['ahk_conn'])) _gpPopupSelfHeal($GLOBALS['ahk_conn']);
elseif (!empty($ahk_conn)) {
    // Fallback: local scope $ahk_conn (database.php defines it locally) — copy to GLOBALS + heal
    $GLOBALS['ahk_conn'] = $ahk_conn;
    _gpPopupSelfHeal($GLOBALS['ahk_conn']);
}

// ===== COMPLAINT REMARK NOTIFICATION SELF-HEAL + UNREAD COUNT FOR LOGGED-IN USER =====
$__userUnreadComplaintCount = 0;
$__userUnreadComplaints = [];
function _cmpSelfHealHeader($conn) {
    if (!$conn) return;
    static $done = null;
    if ($done === true) return;
    @mysqli_report(MYSQLI_REPORT_OFF);
    $cols = [];
    $r = @mysqli_query($conn, "SHOW COLUMNS FROM complaints");
    if ($r) while ($row = mysqli_fetch_assoc($r)) $cols[] = $row['Field'];
    $add = function($n, $d) use ($conn, $cols) {
        if (!in_array($n, $cols, true)) @mysqli_query($conn, "ALTER TABLE complaints ADD COLUMN {$n} {$d}");
    };
    $add('remark_updated_at', "DATETIME NULL DEFAULT NULL");
    $add('remark_seen', "TINYINT(1) NOT NULL DEFAULT 0");
    $done = true;
}
if (!empty($GLOBALS['ahk_conn']) && !empty($udata) && !empty($udata['id'])) {
    _cmpSelfHealHeader($GLOBALS['ahk_conn']);
    $__cUid = (int)$udata['id'];
    $__cRes = @mysqli_query($GLOBALS['ahk_conn'],
        "SELECT COUNT(*) AS c FROM complaints
         WHERE user_id = {$__cUid}
           AND remark IS NOT NULL AND TRIM(remark) <> ''
           AND (remark_seen IS NULL OR remark_seen = 0)"
    );
    if ($__cRes && ($__cRow = mysqli_fetch_assoc($__cRes))) {
        $__userUnreadComplaintCount = (int)$__cRow['c'];
    }
    if ($__userUnreadComplaintCount > 0) {
        $__cRes2 = @mysqli_query($GLOBALS['ahk_conn'],
            "SELECT complaint_id, complaint_type, status, remark,
                    remark_updated_at, date_time
             FROM complaints
             WHERE user_id = {$__cUid}
               AND remark IS NOT NULL AND TRIM(remark) <> ''
               AND (remark_seen IS NULL OR remark_seen = 0)
             ORDER BY COALESCE(remark_updated_at, date_time) DESC
             LIMIT 5"
        );
        if ($__cRes2) while ($__cr = mysqli_fetch_assoc($__cRes2)) $__userUnreadComplaints[] = $__cr;
    }
}

// ===== Unread Complaint Count (no admin remark yet) =====
$pending_complaint_count = 0;
// ===== Samagra Services Pending Counts =====
$sm_pdf = 0; $sm_newapply = 0; $sm_newfam = 0; $sm_pravasan = 0;
$sm_merge = 0; $sm_recover = 0; $sm_memdel = 0; $sm_memxfer = 0;
$sm_divorce = 0; $sm_kyc = 0;
// ===== TEC Exam + LL Exam Pending Request Counts (sidebar badges same as Samagra) =====
$pending_tec_count = 0;
$pending_ll_count  = 0;
if (!empty($GLOBALS['ahk_conn']) && function_exists('checkAdmin') && checkAdmin($udata['type'] ?? '')) {
    $_cRes = @mysqli_query($GLOBALS['ahk_conn'],
        "SELECT COUNT(*) AS cnt FROM complaints WHERE remark IS NULL OR TRIM(remark) = ''"
    );
    if ($_cRes && ($_cRow = mysqli_fetch_assoc($_cRes))) {
        $pending_complaint_count = (int)($_cRow['cnt'] ?? 0);
    }

    $_sm_tables = [
        'sm_pdf'      => ['samagra_pdf_requests',               'sm_pdf'],
        'sm_newapply' => ['samagra_new_apply_requests',         'sm_newapply'],
        'sm_newfam'   => ['samagra_new_family_id_requests',     'sm_newfam'],
        'sm_pravasan' => ['samagra_pravasan_requests',          'sm_pravasan'],
        'sm_merge'    => ['samagra_merge_family_id_requests',   'sm_merge'],
        'sm_recover'  => ['samagra_recover_member_requests',    'sm_recover'],
        'sm_memdel'   => ['samagra_member_delete_requests',     'sm_memdel'],
        'sm_memxfer'  => ['samagra_member_transfer_requests',   'sm_memxfer'],
        'sm_divorce'  => ['samagra_married_divorce_requests',   'sm_divorce'],
        'sm_kyc'      => ['samagra_kyc_requests',               'sm_kyc'],
    ];
    foreach ($_sm_tables as $_key => $_t) {
        $_r = @mysqli_query($GLOBALS['ahk_conn'],
            "SELECT COUNT(*) AS cnt FROM `{$_t[0]}` WHERE status = 'pending'"
        );
        if ($_r && ($_row = mysqli_fetch_assoc($_r))) {
            $$_key = (int)($_row['cnt'] ?? 0);
        }
    }
    unset($_sm_tables, $_key, $_t, $_r, $_row);

    // ===== TEC: tec_certificate status=pending =====
    $_tecR = @mysqli_query($GLOBALS['ahk_conn'],
        "SELECT COUNT(*) AS cnt FROM tec_certificate WHERE LOWER(TRIM(COALESCE(status,'pending'))) = 'pending'"
    );
    if ($_tecR && ($_tecRow = mysqli_fetch_assoc($_tecR))) {
        $pending_tec_count = (int)($_tecRow['cnt'] ?? 0);
    }
    unset($_tecR, $_tecRow);

    // ===== LL: larning_exam (primary) + ll_exam_list (fallback, status=pending) =====
    $_llA = 0; $_llB = 0;
    $_llR1 = @mysqli_query($GLOBALS['ahk_conn'],
        "SELECT COUNT(*) AS cnt FROM larning_exam WHERE LOWER(TRIM(COALESCE(status,'pending'))) IN ('pending','0')"
    );
    if ($_llR1 && ($_llRow1 = mysqli_fetch_assoc($_llR1))) { $_llA = (int)($_llRow1['cnt'] ?? 0); }
    $_llR2 = @mysqli_query($GLOBALS['ahk_conn'],
        "SELECT COUNT(*) AS cnt FROM ll_exam_list WHERE LOWER(TRIM(COALESCE(status,'pending'))) = 'pending'"
    );
    if ($_llR2 && ($_llRow2 = mysqli_fetch_assoc($_llR2))) { $_llB = (int)($_llRow2['cnt'] ?? 0); }
    $pending_ll_count = $_llA + $_llB;
    unset($_llR1, $_llRow1, $_llR2, $_llRow2, $_llA, $_llB);
}
function _smFormatBadgeCount($n) {
    return $n > 99 ? '99+' : (int)$n;
}

// ===== HEADER: Daily API Hit Count (Debit Transactions) =====
$__dailyApiHitCount = 0;
if (!empty($GLOBALS['ahk_conn']) && !empty($udata)) {
    $__dUid = isset($udata['id']) ? (int)$udata['id'] : 0;
    $__dPhone = isset($udata['phone']) ? trim((string)$udata['phone']) : '';
    $__dToday = date('Y-m-d');
    $__dWhere = array();
    if ($__dUid > 0) $__dWhere[] = "userid='" . mysqli_real_escape_string($GLOBALS['ahk_conn'], (string)$__dUid) . "'";
    if ($__dPhone !== '') $__dWhere[] = "userid='" . mysqli_real_escape_string($GLOBALS['ahk_conn'], $__dPhone) . "'";
    if (!empty($__dWhere)) {
        $__dQ = "SELECT COUNT(*) AS c FROM wallethistory
                 WHERE (" . implode(' OR ', $__dWhere) . ")
                   AND UPPER(TRIM(COALESCE(type,''))) = 'DEBIT'
                   AND LOWER(TRIM(COALESCE(status,''))) IN ('1','success')
                   AND DATE(COALESCE(date, NOW())) = '{$__dToday}'
                 LIMIT 1";
        $__dR = @mysqli_query($GLOBALS['ahk_conn'], $__dQ);
        if ($__dR && ($__dRow = mysqli_fetch_assoc($__dR))) {
            $__dailyApiHitCount = isset($__dRow['c']) ? (int)$__dRow['c'] : 0;
        }
        @mysqli_free_result($__dR);
    }
}

// ===== HEADER: Today's Total Spend (Sum of Debit Amounts) =====
$__todaySpend = 0;
if (!empty($GLOBALS['ahk_conn']) && !empty($udata)) {
    $__tsUid = isset($udata['id']) ? (int)$udata['id'] : 0;
    $__tsPhone = isset($udata['phone']) ? trim((string)$udata['phone']) : '';
    $__tsToday = date('Y-m-d');
    $__tsWhere = array();
    if ($__tsUid > 0) $__tsWhere[] = "userid='" . mysqli_real_escape_string($GLOBALS['ahk_conn'], (string)$__tsUid) . "'";
    if ($__tsPhone !== '') $__tsWhere[] = "userid='" . mysqli_real_escape_string($GLOBALS['ahk_conn'], $__tsPhone) . "'";
    if (!empty($__tsWhere)) {
        $__tsQ = "SELECT COALESCE(SUM(amount),0) AS total FROM wallethistory
                  WHERE (" . implode(' OR ', $__tsWhere) . ")
                    AND UPPER(TRIM(COALESCE(type,''))) = 'DEBIT'
                    AND LOWER(TRIM(COALESCE(status,''))) IN ('1','success')
                    AND DATE(COALESCE(date, NOW())) = '{$__tsToday}'
                  LIMIT 1";
        $__tsR = @mysqli_query($GLOBALS['ahk_conn'], $__tsQ);
        if ($__tsR && ($__tsRow = mysqli_fetch_assoc($__tsR))) {
            $__todaySpend = isset($__tsRow['total']) ? (float)$__tsRow['total'] : 0;
        }
        @mysqli_free_result($__tsR);
    }
}

// ===== HEADER: Low Balance Alert (balance < 50) =====
$__currentBalance = (float)(isset($udata['balance']) ? $udata['balance'] : 0);
$__isLowBalance = ($__currentBalance < 50);

// ===== HEADER: Precompute display values (safe for inline HTML) =====
$__walletClassExtra = '';
$__walletTitle = 'Current Wallet Balance';
$__lowBadgeHtml = '';
if ($__isLowBalance) {
    $__walletClassExtra = ' gp-wallet-low';
    $__walletTitle = 'LOW BALANCE! Please recharge wallet';
    $__lowBadgeHtml = '<span class="gp-low-badge">!</span>';
}
$__balanceFormatted = number_format($__currentBalance, 2);
$__hitsFormatted = (int)$__dailyApiHitCount;
$__spendFormatted = number_format($__todaySpend, 2);

// ===== HEADER: WhatsApp Number (from settings) =====
$__whatsappNumber = '';
$__whatsappDisplay = '';
if (!empty($webdata) && isset($webdata['whatsapp_number'])) {
    $__rawW = trim((string)$webdata['whatsapp_number']);
    if ($__rawW !== '') {
        $__digitsW = preg_replace('/[^0-9]/', '', $__rawW);
        if (strlen($__digitsW) >= 10) {
            if (strlen($__digitsW) === 10) {
                $__digitsW = '91' . $__digitsW;
            }
            $__whatsappNumber = $__digitsW;
        }
        $__whatsappDisplay = $__rawW;
    }
}
?>
<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- 🔥 PERMANENT WHITE HARD RESET — Remove any leftover dark attrs + clear old gp_theme localStorage (for existing users) -->
    <script>
    (function(){
        try {
            // Clear any old saved dark preference so new page loads always default original white
            try { localStorage.removeItem('gp_theme'); } catch(_){}
            var root = document.documentElement;
            try { root.removeAttribute('data-theme'); root.removeAttribute('data-gp-icon'); } catch(_){}
            try { root.removeAttribute('style'); } catch(_){}
        } catch(e){}
    })();
    </script>
    <!--favicon-->
<link rel="shortcut icon" type="image/x-icon" href="<?php echo ahkweb('logo');  ?>">
    <!--plugins-->
    <link href="../template/ahkweb/assets/plugins/vectormap/jquery-jvectormap-2.0.2.css" rel="stylesheet" />
    <link href="../template/ahkweb/assets/plugins/simplebar/css/simplebar.css" rel="stylesheet" />
    <link href="../template/ahkweb/assets/plugins/perfect-scrollbar/css/perfect-scrollbar.css" rel="stylesheet" />
    <link href="../template/ahkweb/assets/plugins/metismenu/css/metisMenu.min.css" rel="stylesheet" />
    <!-- loader-->
    <link href="../template/ahkweb/assets/css/pace.min.css" rel="stylesheet" />
    <script src="../template/ahkweb/assets/js/pace.min.js"></script>
    <!-- Bootstrap CSS -->
    <link href="../template/ahkweb/assets/plugins/datatable/css/dataTables.bootstrap5.min.css" rel="stylesheet" />
    <link href="../template/ahkweb/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../template/ahkweb/assets/css/bootstrap-extended.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&amp;display=swap" rel="stylesheet">
    <link href="../template/ahkweb/assets/css/app.css" rel="stylesheet">
    <link href="../template/ahkweb/assets/css/icons.css" rel="stylesheet">
    <!-- Theme Style CSS -->
	<link href="../assets/css/app.css" rel="stylesheet">
    <link rel="stylesheet" href="../template/ahkweb/assets/css/dark-theme.css" />
    <link rel="stylesheet" href="../template/ahkweb/assets/css/semi-dark.css" />
    <link rel="stylesheet" href="../template/ahkweb/assets/css/header-colors.css" />
    <title>Dashboard | <?php echo ahkweb('webname'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
      /* ===== Complaint Remark Notification (A2Z attention-grabbing) ===== */
      .gp-bell-red { color: #e11d48 !important; }
      .gp-bell-wrap { position: relative; }
      .gp-bell-wrap .gp-bell-red {
          animation: gpBellShake 1.2s ease-in-out infinite;
      }
      @keyframes gpBellShake {
          0%,100% { transform: rotate(0) scale(1); }
          15% { transform: rotate(-14deg) scale(1.12); }
          30% { transform: rotate(12deg) scale(1.08); }
          45% { transform: rotate(-10deg) scale(1.08); }
          60% { transform: rotate(8deg) scale(1.04); }
          75% { transform: rotate(-4deg) scale(1.02); }
      }
      .gp-bell-wrap::after {
          content: '';
          position: absolute;
          top: 2px; right: 2px;
          width: 14px; height: 14px;
          border-radius: 50%;
          background: #e11d48;
          box-shadow: 0 0 0 0 rgba(225,29,72,.7);
          opacity: 0;
          pointer-events: none;
      }
      .gp-bell-wrap.gp-has-unread::after {
          opacity: 1;
          animation: gpPulseRed 1.5s ease-out infinite;
      }
      @keyframes gpPulseRed {
          0%   { box-shadow: 0 0 0 0 rgba(225,29,72,.7); }
          70%  { box-shadow: 0 0 0 14px rgba(225,29,72,0); }
          100% { box-shadow: 0 0 0 0 rgba(225,29,72,0); }
      }
      .gp-bell-badge {
          background: linear-gradient(135deg,#e11d48,#b91c1c) !important;
          color: #fff !important;
          font-weight: 800 !important;
      }
      .gp-cmp-notify-icon { box-shadow: 0 0 0 3px rgba(220,53,69,.08); }
      #gpCmpNotifyWrap .dropdown-menu { border-top: 3px solid #e11d48 !important; }
      .gp-sticky-cmp-banner {
          position: fixed;
          right: 18px;
          bottom: 18px;
          z-index: 9998;
          width: 360px;
          max-width: calc(100vw - 36px);
          background: linear-gradient(135deg,#dc2626 0%, #e11d48 55%, #9f1239 100%);
          color: #fff;
          border-radius: 16px;
          padding: 14px 16px 14px 16px;
          box-shadow: 0 18px 40px rgba(220,38,38,.35), 0 0 0 1px rgba(255,255,255,.12) inset;
          animation: gpBannerIn .5s cubic-bezier(.2,.9,.3,1.2) both, gpBannerGlow 2.2s ease-in-out infinite 1s;
      }
      @keyframes gpBannerIn { from { transform: translateY(60px) scale(.92); opacity:0; } to { transform:none; opacity:1; } }
      @keyframes gpBannerGlow {
          0%,100% { box-shadow: 0 18px 40px rgba(220,38,38,.35), 0 0 0 1px rgba(255,255,255,.12) inset, 0 0 0 0 rgba(225,29,72,.45); }
          50%     { box-shadow: 0 18px 40px rgba(220,38,38,.35), 0 0 0 1px rgba(255,255,255,.12) inset, 0 0 26px 6px rgba(225,29,72,.5); }
      }
      .gp-sticky-cmp-banner h6 { margin: 0 0 4px 0; font-weight: 800; font-size: 15px; letter-spacing:.2px; }
      .gp-sticky-cmp-banner p { margin: 0 0 10px 0; font-size: 13px; opacity:.97; line-height:1.45; }
      .gp-sticky-cmp-banner .gp-cmp-btns { display:flex; gap:8px; }
      .gp-sticky-cmp-banner .gp-cmp-btn {
          flex: 1; padding: 7px 10px; border-radius: 10px;
          font-weight: 700; font-size: 12.5px; border: none; cursor: pointer;
          transition: transform .1s ease;
      }
      .gp-sticky-cmp-banner .gp-cmp-btn:hover { transform: translateY(-1px); }
      .gp-cmp-btn-primary { background: #fff; color: #9f1239; }
      .gp-cmp-btn-ghost   { background: rgba(255,255,255,.12); color:#fff; border:1px solid rgba(255,255,255,.25) !important; }
      .gp-cmp-row-unread {
          background: linear-gradient(90deg, rgba(254,226,226,.85) 0%, rgba(255,237,213,.75) 100%) !important;
          box-shadow: inset 3px 0 0 #ef4444;
      }
      .gp-cmp-row-unread td { background: transparent !important; }
      .gp-cmp-row-unread td:first-child::before {
          content: ''; display:inline-block; width:10px; height:10px; border-radius:50%;
          background: radial-gradient(circle at 30% 30%, #fca5a5, #dc2626);
          box-shadow: 0 0 0 2px #fff, 0 0 12px 2px rgba(220,38,38,.5);
          margin-right: 6px;
          animation: gpPulseRed 1.6s ease-out infinite;
      }
      @media (max-width: 480px) {
          .gp-sticky-cmp-banner { width: calc(100vw - 24px); right: 12px; bottom: 12px; }
      }

      /* ===========================================================
         PREMIUM COLORFUL SIDEBAR THEME
         =========================================================== */

      /* Sidebar Wrapper - Premium Gradient Background */
      .sidebar-wrapper {
          background:
              radial-gradient(circle at 100% 0%, rgba(139,92,246,0.08) 0%, transparent 40%),
              radial-gradient(circle at 0% 100%, rgba(236,72,153,0.06) 0%, transparent 40%),
              linear-gradient(180deg, #ffffff 0%, #fbfaff 40%, #f8f5ff 100%);
          border-right: 1px solid rgba(139,92,246,0.12);
          box-shadow:
              2px 0 20px rgba(99,102,241,0.06),
              4px 0 40px rgba(139,92,246,0.04) !important;
      }

      /* Sidebar Header - Premium Gradient */
      .sidebar-header {
          background:
              radial-gradient(800px 200px at 0% 0%, rgba(255,255,255,0.25) 0%, transparent 55%),
              linear-gradient(135deg, #6366f1 0%, #8b5cf6 40%, #ec4899 75%, #f59e0b 100%);
          border-bottom: none;
          box-shadow:
              0 4px 20px rgba(139,92,246,0.3),
              inset 0 1px 0 rgba(255,255,255,0.3);
      }
      .sidebar-header .logo-text {
          color: #fff;
          text-shadow: 0 1px 3px rgba(0,0,0,0.15);
          font-weight: 800;
          letter-spacing: 0.5px;
      }
      .sidebar-header .toggle-icon {
          color: rgba(255,255,255,0.9);
      }
      .sidebar-header .toggle-icon:hover {
          color: #fff;
      }

      /* Metismenu Container - extra padding for premium feel */
      .sidebar-wrapper .metismenu {
          padding: 8px 10px 16px 10px;
          background: transparent;
      }

      /* -------- Menu Item Base -------- */
      .sidebar-wrapper .metismenu > li:not(.menu-label) > a {
          position: relative;
          border-radius: 10px;
          padding: 9px 14px;
          font-weight: 600;
          font-size: 14px;
          color: #374151;
          letter-spacing: 0.2px;
          border: 1px solid transparent;
          transition: all 0.3s cubic-bezier(.2,.8,.2,1);
          overflow: visible;
      }

      /* Colorful Left Accent Bar (appears on hover/active) */
      .sidebar-wrapper .metismenu > li:not(.menu-label) > a::before {
          content: "";
          position: absolute;
          left: 0;
          top: 50%;
          transform: translateY(-50%);
          width: 3px;
          height: 0;
          border-radius: 0 3px 3px 0;
          background: linear-gradient(180deg, #6366f1, #ec4899);
          transition: height 0.3s ease;
          opacity: 0;
      }

      /* Parent Icon - Premium Colorful Background Ring */
      .sidebar-wrapper .metismenu > li:not(.menu-label) > a .parent-icon {
          display: inline-flex;
          align-items: center;
          justify-content: center;
          width: 34px;
          height: 34px;
          border-radius: 9px;
          font-size: 18px;
          background: linear-gradient(135deg, rgba(99,102,241,0.10), rgba(236,72,153,0.10));
          border: 1px solid rgba(139,92,246,0.18);
          margin-right: 0;
          box-shadow: inset 0 1px 2px rgba(255,255,255,0.6);
          transition: all 0.3s cubic-bezier(.2,.8,.2,1);
      }
      .sidebar-wrapper .metismenu > li:not(.menu-label) > a .parent-icon img {
          width: 22px !important;
          height: 22px !important;
          border-radius: 5px !important;
          object-fit: contain;
      }
      .sidebar-wrapper .metismenu > li:not(.menu-label) > a .parent-icon i {
          font-size: 16px;
      }

      /* Menu Title spacing */
      .sidebar-wrapper .metismenu a .menu-title {
          margin-left: 11px;
          font-size: 14px;
      }

      /* -------- Hover + Active States -------- */
      .sidebar-wrapper .metismenu > li:not(.menu-label) > a:hover,
      .sidebar-wrapper .metismenu > li:not(.menu-label) > a:active,
      .sidebar-wrapper .metismenu > li:not(.menu-label) > a:focus,
      .sidebar-wrapper .metismenu > li:not(.menu-label).mm-active > a {
          color: #ffffff;
          background:
              radial-gradient(600px 100px at 100% 0%, rgba(255,255,255,0.25) 0%, transparent 55%),
              linear-gradient(135deg, #6366f1 0%, #8b5cf6 45%, #ec4899 100%);
          border-color: rgba(255,255,255,0.4);
          box-shadow:
              0 6px 18px rgba(139,92,246,0.35),
              0 2px 6px rgba(99,102,241,0.18),
              inset 0 1px 0 rgba(255,255,255,0.35);
          transform: translateX(3px);
      }

      .sidebar-wrapper .metismenu > li:not(.menu-label) > a:hover::before,
      .sidebar-wrapper .metismenu > li:not(.menu-label).mm-active > a::before {
          height: 60%;
          opacity: 1;
          background: #fff;
          box-shadow: 0 0 8px rgba(255,255,255,0.6);
      }

      /* Hover: parent-icon becomes light/white ring */
      .sidebar-wrapper .metismenu > li:not(.menu-label) > a:hover .parent-icon,
      .sidebar-wrapper .metismenu > li:not(.menu-label).mm-active > a .parent-icon {
          background: rgba(255,255,255,0.2);
          border-color: rgba(255,255,255,0.5);
          box-shadow: 0 2px 10px rgba(0,0,0,0.08);
      }

      /* -------- Submenu (nested ul) Premium Look -------- */
      .sidebar-wrapper .metismenu ul {
          border: 1px solid rgba(139,92,246,0.15);
          background:
              linear-gradient(180deg, #fdfaff 0%, #f7f3ff 100%);
          border-radius: 10px;
          padding: 5px 5px;
          margin: 6px 2px !important;
          box-shadow: 0 4px 14px rgba(139,92,246,0.08);
      }
      .sidebar-wrapper .metismenu ul a {
          position: relative;
          border-radius: 7px;
          padding: 7px 12px !important;
          font-size: 13px !important;
          color: #4b5563;
          font-weight: 500;
          transition: all 0.25s ease;
      }
      .sidebar-wrapper .metismenu ul a i {
          color: #8b5cf6 !important;
          margin-right: 8px !important;
          font-size: 13px;
      }
      .sidebar-wrapper .metismenu ul a:hover,
      .sidebar-wrapper .metismenu ul a.mm-active {
          background: linear-gradient(90deg, rgba(99,102,241,0.12), rgba(236,72,153,0.10));
          color: #6d28d9;
          transform: translateX(3px);
          box-shadow: inset 2px 0 0 #8b5cf6;
      }

      /* -------- Menu Label (Section Headers) - Simple Clean Heading -------- */
      .sidebar-wrapper .metismenu li.menu-label {
          position: relative;
          display: flex;
          align-items: center;
          margin: 20px 4px 8px 4px;
          padding: 0 0 0 12px;
          font-size: 10.5px;
          font-weight: 800;
          letter-spacing: 1.6px;
          color: #6b7280;
          text-transform: uppercase;
          background: transparent;
          border: none;
          box-shadow: none;
          text-shadow: none;
      }
      /* Left accent bar indicator (simple thin line) */
      .sidebar-wrapper .metismenu li.menu-label::after {
          content: "";
          position: absolute;
          left: 0;
          top: 50%;
          transform: translateY(-50%);
          width: 3px;
          height: 14px;
          border-radius: 2px;
          background: #9ca3af;
      }
      .sidebar-wrapper .metismenu li.menu-label::before {
          display: none;
      }
      .sidebar-wrapper .metismenu li.menu-label {
          background-image: none !important;
      }

      /* -------- Separator Lines between menu items -------- */
      .sidebar-wrapper .metismenu > li:not(.menu-label):not(:last-child)::after {
          content: "";
          display: block;
          height: 1px;
          margin: 3px 10px 0 10px;
          background: linear-gradient(90deg,
              transparent 0%,
              rgba(139,92,246,0.10) 20%,
              rgba(236,72,153,0.12) 50%,
              rgba(139,92,246,0.10) 80%,
              transparent 100%);
      }
      /* No separator right before a menu-label */
      .sidebar-wrapper .metismenu > li:not(.menu-label):has(+ li.menu-label)::after {
          display: none;
      }
      /* No separator right after a menu-label */
      .sidebar-wrapper .metismenu > li.menu-label + li:not(.menu-label)::before {
          display: none;
      }

      /* -------- Badge (Instant / NEW) polish -------- */
      .badge-instant {
          background: linear-gradient(135deg, #10b981, #059669) !important;
          box-shadow: 0 2px 6px rgba(16,185,129,0.35);
          font-size: 9.5px !important;
          padding: 2px 7px !important;
          font-weight: 800 !important;
      }
      .new_blink {
          filter: drop-shadow(0 2px 4px rgba(239,68,68,0.35));
      }

      /* -------- Notification Badges on Sidebar Icons -------- */
      .notif-badge {
          position: absolute;
          top: -4px;
          right: -6px;
          background: linear-gradient(135deg, #ef4444, #dc2626);
          color: #fff;
          font-size: 9px;
          font-weight: 800;
          padding: 1px 5px;
          border-radius: 10px;
          box-shadow: 0 2px 6px rgba(239,68,68,0.45);
          border: 1.5px solid #fff;
          min-width: 16px;
          text-align: center;
      }
      .notif-badge-pill {
          background: linear-gradient(135deg, #f59e0b, #d97706);
          color: #fff;
          font-size: 10px;
          font-weight: 800;
          padding: 1px 7px;
          border-radius: 10px;
          box-shadow: 0 2px 6px rgba(245,158,11,0.4);
          border: 1px solid rgba(255,255,255,0.6);
      }

      /* -------- Sidebar Scrollbar (colorful) -------- */
      .sidebar-wrapper .simplebar-scrollbar::before {
          background: linear-gradient(180deg, #6366f1, #ec4899) !important;
          opacity: 0.5;
          border-radius: 6px;
          width: 5px;
      }
      .sidebar-wrapper .simplebar-scrollbar.simplebar-visible::before {
          opacity: 0.7;
      }

      /* -------- Collapsed (toggled) Sidebar - keep it clean -------- */
      @media screen and (min-width:1025px) {
          .wrapper.toggled:not(.sidebar-hovered) .sidebar-wrapper .metismenu > li:not(.menu-label) > a {
              padding: 9px 0;
              justify-content: center;
          }
          .wrapper.toggled:not(.sidebar-hovered) .sidebar-wrapper .metismenu > li:not(.menu-label) > a::before {
              left: 50%;
              top: auto;
              bottom: -2px;
              transform: translateX(-50%);
              width: 0;
              height: 2px;
              border-radius: 2px;
          }
          .wrapper.toggled:not(.sidebar-hovered) .sidebar-wrapper .metismenu > li:not(.menu-label) > a:hover::before {
              width: 60%;
              height: 2px;
          }
          .wrapper.toggled:not(.sidebar-hovered) .sidebar-wrapper .metismenu li.menu-label {
              display: none !important;
          }
          .wrapper.toggled:not(.sidebar-hovered) .sidebar-wrapper .metismenu li.menu-label::after,
          .wrapper.toggled:not(.sidebar-hovered) .sidebar-wrapper .metismenu > li:not(.menu-label):not(:last-child)::after {
              display: none;
          }
      }

      /* Mobile: sidebar slide-in should still look premium */
      @media (max-width: 1024px) {
          .sidebar-wrapper {
              z-index: 999999;
          }
      }

/* ============================================================
   ☀️ / 🌙  LIGHT & DARK THEME (gp-toggle theme controller)
   RULE: Sab dark styles `html[data-theme="dark"]` selectors se apply.
   LIGHT MODE = koi attribute nahi → original (sab default white)
   body.gp-light-mode-active = explicit light override (double backup for edge cases)
   ============================================================ */
:root {
    --gp-light-bg: #f8fafc;
    --gp-light-text: #0f172a;
    --gp-light-text-muted: #64748b;
    --gp-light-card: #ffffff;
    --gp-light-border: #e2e8f0;
    --gp-light-border-2: #cbd5e1;
    --gp-light-surface: #ffffff;
    --gp-light-surface-2: #f1f5f9;
    --gp-dark-bg: #0b1020;
    --gp-dark-bg-2: #0f172a;
    --gp-dark-surface: #111827;
    --gp-dark-surface-2: #1e293b;
    --gp-dark-surface-3: #273449;
    --gp-dark-border: #334155;
    --gp-dark-border-2: #1f2a44;
    --gp-dark-text: #e2e8f0;
    --gp-dark-text-muted: #94a3b8;
    --gp-dark-primary: #6366f1;
    --gp-dark-accent: #a855f7;
}

/* ==================== ☀️ LIGHT MODE — body.gp-light-mode-active (EXPLICIT OVERRIDE — ensures white FORCE backup for each style that may leak) */
body.gp-light-mode-active,
body.gp-light-mode-active {
    background: #ffffff !important;
    color: #0f172a !important;
}
body.gp-light-mode-active .page-wrapper,
body.gp-light-mode-active .page-content {
    background: transparent !important;
    color: #0f172a !important;
}
body.gp-light-mode-active .topbar,
body.gp-light-mode-active header .navbar {
    background: none !important;
    border-bottom: none !important;
    color: #0f172a !important;
}
body.gp-light-mode-active .topbar .breadcrumb-title,
body.gp-light-mode-active .navbar .ms-auto,
body.gp-light-mode-active .topbar a,
body.gp-light-mode-active .user-info .name,
body.gp-light-mode-active .user-name {
    color: #0f172a !important;
}
body.gp-light-mode-active .card,
body.gp-light-mode-active .card-header,
body.gp-light-mode-active .card-body,
body.gp-light-mode-active .card-footer {
    background: #ffffff !important;
    color: #0f172a !important;
}
body.gp-light-mode-active .sidebar-wrapper,
body.gp-light-mode-active .sidebar {
    background: none !important;
    color: #0f172a !important;
}

/* ============================================================
   🌙 DARK THEME (data-theme="dark")
   ============================================================ */
html[data-theme="dark"] body,
body[data-theme="dark"] {
    background: radial-gradient(ellipse at top, #0e1427 0%, #0b1020 55%, #070b15 100%);
    color: var(--gp-dark-text);
    transition: background .3s ease, color .3s ease;
}
html[data-theme="dark"] .page-wrapper,
body[data-theme="dark"] .page-wrapper { background: transparent; }
html[data-theme="dark"] .page-content,
body[data-theme="dark"] .page-content {
    background: transparent !important;
    color: var(--gp-dark-text);
}
/* Top navigation (header) */
html[data-theme="dark"] .topbar,
body[data-theme="dark"] .topbar,
html[data-theme="dark"] header .navbar,
body[data-theme="dark"] header .navbar {
    background: linear-gradient(180deg, #111827 0%, #0f172a 100%) !important;
    border-bottom: 1px solid var(--gp-dark-border-2) !important;
    color: var(--gp-dark-text);
}
html[data-theme="dark"] .topbar .breadcrumb-title,
body[data-theme="dark"] .topbar .breadcrumb-title,
html[data-theme="dark"] .navbar .ms-auto,
body[data-theme="dark"] .navbar .ms-auto,
html[data-theme="dark"] .topbar a,
body[data-theme="dark"] .topbar a,
html[data-theme="dark"] .user-info .name,
body[data-theme="dark"] .user-info .name,
html[data-theme="dark"] .user-name,
body[data-theme="dark"] .user-name {
    color: var(--gp-dark-text) !important;
}
html[data-theme="dark"] .user-info .designation,
body[data-theme="dark"] .user-info .designation {
    color: var(--gp-dark-text-muted) !important;
}
html[data-theme="dark"] .gp-header-date,
body[data-theme="dark"] .gp-header-date {
    background: rgba(99, 102, 241, 0.18) !important;
    color: #c7d2fe !important;
    border: 1px solid rgba(99,102,241,0.35) !important;
}
html[data-theme="dark"] .gp-wallet-chip,
body[data-theme="dark"] .gp-wallet-chip {
    background: linear-gradient(135deg, #1e3a8a 0%, #312e81 100%) !important;
    color: #dbeafe !important;
    border-color: rgba(99,102,241,0.5) !important;
}
html[data-theme="dark"] .gp-daily-hit-chip,
body[data-theme="dark"] .gp-daily-hit-chip {
    background: linear-gradient(135deg, #065f46 0%, #047857 100%) !important;
    color: #a7f3d0 !important;
    border-color: rgba(16,185,129,0.5) !important;
}
html[data-theme="dark"] .gp-addbalance-btn,
body[data-theme="dark"] .gp-addbalance-btn {
    background: linear-gradient(135deg, #064e3b 0%, #065f46 20%, #047857 40%, #059669 58%, #b45309 82%, #92400e 100%) !important;
    box-shadow:
        0 0 0 1.5px rgba(255,255,255,0.35),
        0 0 0 3px rgba(16,185,129,0.2),
        0 4px 18px rgba(16,185,129,0.5),
        0 0 22px rgba(251,191,36,0.2),
        inset 0 1px 0 rgba(255,255,255,0.45),
        inset 0 -1px 0 rgba(4,120,87,0.5) !important;
}
html[data-theme="dark"] .gp-addbalance-btn span,
body[data-theme="dark"] .gp-addbalance-btn span {
    color: #fff !important;
    -webkit-text-fill-color: #fff !important;
    background: none !important;
    text-shadow: 0 1px 0 rgba(0,0,0,0.45), 0 2px 4px rgba(4,120,87,0.6) !important;
}
html[data-theme="dark"] .gp-addbalance-btn span::before,
body[data-theme="dark"] .gp-addbalance-btn span::before {
    color: #fde68a !important;
    text-shadow: 0 0 6px rgba(253,230,138,0.7), 0 1px 2px rgba(4,120,87,0.6) !important;
}
html[data-theme="dark"] .gp-today-spend-chip,
body[data-theme="dark"] .gp-today-spend-chip {
    background: linear-gradient(135deg, #7c2d12 0%, #9a3412 100%) !important;
    color: #fed7aa !important;
    border-color: rgba(249,115,22,0.5) !important;
}
html[data-theme="dark"] .gp-today-spend-chip b {
    color: #fff !important;
}
html[data-theme="dark"] .gp-wallet-chip.gp-wallet-low,
body[data-theme="dark"] .gp-wallet-chip.gp-wallet-low {
    background: linear-gradient(135deg, #7f1d1d 0%, #991b1b 50%, #7f1d1d 100%) !important;
    border-color: #f87171 !important;
}
html[data-theme="dark"] .gp-top-icon-btn,
body[data-theme="dark"] .gp-top-icon-btn {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%) !important;
    color: var(--gp-dark-text) !important;
    border: 1px solid var(--gp-dark-border) !important;
}
html[data-theme="dark"] .gp-top-icon-btn:hover,
body[data-theme="dark"] .gp-top-icon-btn:hover {
    background: linear-gradient(135deg, #334155 0%, #1e293b 100%) !important;
    color: #fff !important;
    border-color: #475569 !important;
}
html[data-theme="dark"] .gp-refer-btn,
body[data-theme="dark"] .gp-refer-btn {
    background: linear-gradient(135deg, #7c3aed 0%, #4338ca 100%) !important;
    border-color: rgba(124,58,237,0.5) !important;
}
html[data-theme="dark"] .gp-whatsapp-btn,
body[data-theme="dark"] .gp-whatsapp-btn {
    background: linear-gradient(135deg, #047857 0%, #065f46 100%) !important;
}
html[data-theme="dark"] .gp-txn-btn,
body[data-theme="dark"] .gp-txn-btn {
    background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%) !important;
}
html[data-theme="dark"] .gp-complaint-btn,
body[data-theme="dark"] .gp-complaint-btn {
    background: linear-gradient(135deg, #be123c 0%, #9f1239 100%) !important;
}
/* Sidebar */
html[data-theme="dark"] .sidebar-wrapper,
body[data-theme="dark"] .sidebar-wrapper,
html[data-theme="dark"] .sidebar,
body[data-theme="dark"] .sidebar {
    background: linear-gradient(180deg, #0f172a 0%, #0b1020 100%) !important;
    border-right: 1px solid var(--gp-dark-border-2) !important;
    color: var(--gp-dark-text) !important;
}
html[data-theme="dark"] .sidebar .metismenu .mm-active > a,
html[data-theme="dark"] .sidebar .metismenu a:active,
html[data-theme="dark"] .sidebar .metismenu a:focus,
body[data-theme="dark"] .sidebar .metismenu .mm-active > a,
body[data-theme="dark"] .sidebar .metismenu a:active,
body[data-theme="dark"] .sidebar .metismenu a:focus {
    background: rgba(99, 102, 241, 0.18) !important;
    color: #c7d2fe !important;
}
html[data-theme="dark"] .sidebar .metismenu a,
body[data-theme="dark"] .sidebar .metismenu a {
    color: var(--gp-dark-text) !important;
    border-bottom-color: rgba(51,65,85,0.5) !important;
}
html[data-theme="dark"] .sidebar .metismenu a:hover,
body[data-theme="dark"] .sidebar .metismenu a:hover {
    background: rgba(51, 65, 85, 0.45) !important;
    color: #fff !important;
}
html[data-theme="dark"] .sidebar .mm-collapse li a,
body[data-theme="dark"] .sidebar .mm-collapse li a {
    color: var(--gp-dark-text-muted) !important;
}
html[data-theme="dark"] .sidebar-label,
body[data-theme="dark"] .sidebar-label,
html[data-theme="dark"] .menu-label,
body[data-theme="dark"] .menu-label {
    color: var(--gp-dark-text-muted) !important;
    background: transparent !important;
}
html[data-theme="dark"] .logo-icon,
body[data-theme="dark"] .logo-icon,
html[data-theme="dark"] .logo-title,
body[data-theme="dark"] .logo-title {
    color: var(--gp-dark-text) !important;
}
/* Cards & Panels */
html[data-theme="dark"] .card,
body[data-theme="dark"] .card {
    background: linear-gradient(180deg, #111827 0%, #0f172a 100%) !important;
    border: 1px solid var(--gp-dark-border) !important;
    color: var(--gp-dark-text) !important;
    box-shadow: 0 6px 20px rgba(0,0,0,0.45) !important;
}
html[data-theme="dark"] .card-header,
body[data-theme="dark"] .card-header {
    background: rgba(15, 23, 42, 0.6) !important;
    border-bottom: 1px solid var(--gp-dark-border) !important;
    color: var(--gp-dark-text) !important;
}
html[data-theme="dark"] .card-body,
body[data-theme="dark"] .card-body,
html[data-theme="dark"] .card-footer,
body[data-theme="dark"] .card-footer {
    color: var(--gp-dark-text);
}
html[data-theme="dark"] .card-title,
body[data-theme="dark"] .card-title,
html[data-theme="dark"] .display-5,
body[data-theme="dark"] .display-5,
html[data-theme="dark"] h1, html[data-theme="dark"] h2, html[data-theme="dark"] h3,
html[data-theme="dark"] h4, html[data-theme="dark"] h5, html[data-theme="dark"] h6,
body[data-theme="dark"] h1, body[data-theme="dark"] h2, body[data-theme="dark"] h3,
body[data-theme="dark"] h4, body[data-theme="dark"] h5, body[data-theme="dark"] h6 {
    color: #f1f5f9 !important;
}
html[data-theme="dark"] p,
body[data-theme="dark"] p,
html[data-theme="dark"] div:not(.badge):not(.btn),
body[data-theme="dark"] div:not(.badge):not(.btn) { color: inherit; }
html[data-theme="dark"] .text-dark, body[data-theme="dark"] .text-dark { color: #f1f5f9 !important; }
html[data-theme="dark"] .text-muted, body[data-theme="dark"] .text-muted,
html[data-theme="dark"] .form-text, body[data-theme="dark"] .form-text,
html[data-theme="dark"] small, body[data-theme="dark"] small {
    color: var(--gp-dark-text-muted) !important;
}
html[data-theme="dark"] .bg-white, body[data-theme="dark"] .bg-white {
    background: #0f172a !important;
}
html[data-theme="dark"] .bg-light, body[data-theme="dark"] .bg-light {
    background: #111827 !important;
}
html[data-theme="dark"] hr, body[data-theme="dark"] hr {
    border-color: var(--gp-dark-border) !important;
}
/* Tables */
html[data-theme="dark"] .table,
body[data-theme="dark"] .table { color: var(--gp-dark-text) !important; }
html[data-theme="dark"] .table thead th,
body[data-theme="dark"] .table thead th,
html[data-theme="dark"] .table-light,
body[data-theme="dark"] .table-light {
    background: #1e293b !important;
    color: #cbd5e1 !important;
    border-color: var(--gp-dark-border) !important;
}
html[data-theme="dark"] .table td,
body[data-theme="dark"] .table td,
html[data-theme="dark"] .table th,
body[data-theme="dark"] .table th {
    border-color: var(--gp-dark-border) !important;
}
html[data-theme="dark"] .table-striped tbody tr:nth-of-type(odd),
body[data-theme="dark"] .table-striped tbody tr:nth-of-type(odd) {
    background-color: rgba(30, 41, 59, 0.55) !important;
}
html[data-theme="dark"] .table-hover tbody tr:hover,
body[data-theme="dark"] .table-hover tbody tr:hover {
    background-color: rgba(51, 65, 85, 0.6) !important;
    color: #fff !important;
}
html[data-theme="dark"] div.dataTables_wrapper div.dataTables_length label,
html[data-theme="dark"] div.dataTables_wrapper div.dataTables_filter label,
html[data-theme="dark"] div.dataTables_wrapper div.dataTables_info,
body[data-theme="dark"] div.dataTables_wrapper div.dataTables_length label,
body[data-theme="dark"] div.dataTables_wrapper div.dataTables_filter label,
body[data-theme="dark"] div.dataTables_wrapper div.dataTables_info {
    color: var(--gp-dark-text-muted) !important;
}
html[data-theme="dark"] .pagination .page-link,
body[data-theme="dark"] .pagination .page-link {
    background: #1e293b;
    color: var(--gp-dark-text);
    border-color: var(--gp-dark-border);
}
html[data-theme="dark"] .pagination .page-item.disabled .page-link,
body[data-theme="dark"] .pagination .page-item.disabled .page-link {
    background: #0f172a;
    color: #475569;
}
html[data-theme="dark"] .pagination .page-item.active .page-link,
body[data-theme="dark"] .pagination .page-item.active .page-link {
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
    border-color: #4f46e5;
    color: #fff;
}
/* Forms & Inputs */
html[data-theme="dark"] .form-control,
body[data-theme="dark"] .form-control,
html[data-theme="dark"] .form-select,
body[data-theme="dark"] .form-select,
html[data-theme="dark"] textarea, body[data-theme="dark"] textarea,
html[data-theme="dark"] select, body[data-theme="dark"] select,
html[data-theme="dark"] input:not([type="radio"]):not([type="checkbox"]):not([type="submit"]):not([type="button"]):not([type="reset"]),
body[data-theme="dark"] input:not([type="radio"]):not([type="checkbox"]):not([type="submit"]):not([type="button"]):not([type="reset"]) {
    background: #0f172a !important;
    color: #e2e8f0 !important;
    border: 1px solid var(--gp-dark-border) !important;
}
html[data-theme="dark"] .form-control:focus,
body[data-theme="dark"] .form-control:focus,
html[data-theme="dark"] .form-select:focus,
body[data-theme="dark"] .form-select:focus,
html[data-theme="dark"] textarea:focus,
body[data-theme="dark"] textarea:focus,
html[data-theme="dark"] input:focus,
body[data-theme="dark"] input:focus {
    background: #0b1020 !important;
    color: #fff !important;
    border-color: #6366f1 !important;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.25) !important;
}
html[data-theme="dark"] .form-control::placeholder,
body[data-theme="dark"] .form-control::placeholder,
html[data-theme="dark"] input::placeholder,
body[data-theme="dark"] input::placeholder {
    color: #64748b !important;
    opacity: 1;
}
html[data-theme="dark"] .input-group-text,
body[data-theme="dark"] .input-group-text {
    background: #1e293b !important;
    color: #cbd5e1 !important;
    border-color: var(--gp-dark-border) !important;
}
html[data-theme="dark"] .form-label,
body[data-theme="dark"] .form-label {
    color: #cbd5e1 !important;
}
/* Buttons */
html[data-theme="dark"] .btn,
body[data-theme="dark"] .btn {
    border-color: transparent;
}
html[data-theme="dark"] .btn-outline-primary,
body[data-theme="dark"] .btn-outline-primary {
    color: #a5b4fc;
    border-color: rgba(99,102,241,0.5);
    background: transparent;
}
html[data-theme="dark"] .btn-outline-primary:hover,
body[data-theme="dark"] .btn-outline-primary:hover {
    background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
    color: #fff;
    border-color: transparent;
}
html[data-theme="dark"] .btn-outline-secondary,
body[data-theme="dark"] .btn-outline-secondary {
    color: #cbd5e1;
    border-color: rgba(148,163,184,0.4);
}
html[data-theme="dark"] .btn-outline-secondary:hover,
body[data-theme="dark"] .btn-outline-secondary:hover {
    background: #334155;
    color: #fff;
    border-color: transparent;
}
/* Breadcrumbs / Page Header */
html[data-theme="dark"] .page-breadcrumb,
body[data-theme="dark"] .page-breadcrumb {
    background: rgba(17, 24, 39, 0.6) !important;
    color: var(--gp-dark-text) !important;
    border: 1px solid var(--gp-dark-border) !important;
    box-shadow: 0 3px 10px rgba(0,0,0,0.3) !important;
}
html[data-theme="dark"] .breadcrumb-item + .breadcrumb-item::before,
body[data-theme="dark"] .breadcrumb-item + .breadcrumb-item::before {
    color: var(--gp-dark-text-muted) !important;
}
html[data-theme="dark"] .breadcrumb a,
body[data-theme="dark"] .breadcrumb a {
    color: #a5b4fc !important;
}
html[data-theme="dark"] .breadcrumb-item.active,
body[data-theme="dark"] .breadcrumb-item.active {
    color: var(--gp-dark-text-muted) !important;
}
/* Alerts */
html[data-theme="dark"] .alert-info,
body[data-theme="dark"] .alert-info {
    background: rgba(56, 189, 248, 0.12) !important;
    color: #bae6fd !important;
    border-color: rgba(56,189,248,0.35) !important;
}
html[data-theme="dark"] .alert-success,
body[data-theme="dark"] .alert-success {
    background: rgba(52, 211, 153, 0.12) !important;
    color: #a7f3d0 !important;
    border-color: rgba(52,211,153,0.35) !important;
}
html[data-theme="dark"] .alert-warning,
body[data-theme="dark"] .alert-warning {
    background: rgba(251, 191, 36, 0.12) !important;
    color: #fde68a !important;
    border-color: rgba(251,191,36,0.35) !important;
}
html[data-theme="dark"] .alert-danger,
body[data-theme="dark"] .alert-danger {
    background: rgba(248, 113, 113, 0.12) !important;
    color: #fecaca !important;
    border-color: rgba(248,113,113,0.35) !important;
}
html[data-theme="dark"] .alert-light,
body[data-theme="dark"] .alert-light {
    background: #1e293b !important;
    color: #cbd5e1 !important;
    border-color: var(--gp-dark-border) !important;
}
/* Dropdowns / Modals / Offcanvas */
html[data-theme="dark"] .dropdown-menu,
body[data-theme="dark"] .dropdown-menu {
    background: #111827 !important;
    color: var(--gp-dark-text) !important;
    border: 1px solid var(--gp-dark-border) !important;
    box-shadow: 0 12px 30px rgba(0,0,0,0.6) !important;
}
html[data-theme="dark"] .dropdown-menu .dropdown-item,
body[data-theme="dark"] .dropdown-menu .dropdown-item {
    color: var(--gp-dark-text) !important;
}
html[data-theme="dark"] .dropdown-menu .dropdown-item:hover,
body[data-theme="dark"] .dropdown-menu .dropdown-item:hover,
html[data-theme="dark"] .dropdown-menu .dropdown-item:focus,
body[data-theme="dark"] .dropdown-menu .dropdown-item:focus {
    background: #1e293b !important;
    color: #fff !important;
}
html[data-theme="dark"] .dropdown-divider,
body[data-theme="dark"] .dropdown-divider {
    border-top: 1px solid var(--gp-dark-border) !important;
}
html[data-theme="dark"] .modal-content,
body[data-theme="dark"] .modal-content,
html[data-theme="dark"] .offcanvas,
body[data-theme="dark"] .offcanvas {
    background: linear-gradient(180deg, #111827 0%, #0f172a 100%) !important;
    color: var(--gp-dark-text) !important;
    border: 1px solid var(--gp-dark-border) !important;
}
html[data-theme="dark"] .modal-header,
body[data-theme="dark"] .modal-header,
html[data-theme="dark"] .offcanvas-header,
body[data-theme="dark"] .offcanvas-header,
html[data-theme="dark"] .modal-footer,
body[data-theme="dark"] .modal-footer {
    border-color: var(--gp-dark-border) !important;
    color: #f1f5f9 !important;
}
/* Navs / Tabs */
html[data-theme="dark"] .nav-tabs,
body[data-theme="dark"] .nav-tabs {
    border-bottom: 1px solid var(--gp-dark-border) !important;
}
html[data-theme="dark"] .nav-tabs .nav-link.active,
body[data-theme="dark"] .nav-tabs .nav-link.active,
html[data-theme="dark"] .nav-pills .nav-link.active,
body[data-theme="dark"] .nav-pills .nav-link.active {
    background: linear-gradient(135deg, #6366f1, #4f46e5) !important;
    color: #fff !important;
    border-color: #4f46e5 !important;
}
html[data-theme="dark"] .nav-tabs .nav-link,
body[data-theme="dark"] .nav-tabs .nav-link,
html[data-theme="dark"] .nav-pills .nav-link,
body[data-theme="dark"] .nav-pills .nav-link {
    color: var(--gp-dark-text-muted) !important;
}
html[data-theme="dark"] .nav-tabs .nav-link:hover,
body[data-theme="dark"] .nav-tabs .nav-link:hover {
    border-color: var(--gp-dark-border);
    color: var(--gp-dark-text) !important;
}
/* Badges */
html[data-theme="dark"] .badge.bg-danger,
body[data-theme="dark"] .badge.bg-danger { background: #be123c !important; color: #fff !important; }
html[data-theme="dark"] .badge.bg-success,
body[data-theme="dark"] .badge.bg-success { background: #047857 !important; color: #fff !important; }
html[data-theme="dark"] .badge.bg-warning,
body[data-theme="dark"] .badge.bg-warning { background: #b45309 !important; color: #fff !important; }
html[data-theme="dark"] .badge.bg-info,
body[data-theme="dark"] .badge.bg-info { background: #0369a1 !important; color: #fff !important; }
html[data-theme="dark"] .badge.bg-dark,
body[data-theme="dark"] .badge.bg-dark { background: #020617 !important; }
html[data-theme="dark"] .badge.bg-primary,
body[data-theme="dark"] .badge.bg-primary { background: linear-gradient(135deg, #4f46e5, #4338ca) !important; color: #fff !important; }
html[data-theme="dark"] .badge.bg-secondary,
body[data-theme="dark"] .badge.bg-secondary { background: #334155 !important; color: #fff !important; }
/* Swal2 Dark */
html[data-theme="dark"] .swal2-popup,
body[data-theme="dark"] .swal2-popup {
    background: linear-gradient(180deg, #111827 0%, #0f172a 100%) !important;
    color: #e2e8f0 !important;
    border: 1px solid var(--gp-dark-border) !important;
}
html[data-theme="dark"] .swal2-title, body[data-theme="dark"] .swal2-title { color: #f1f5f9 !important; }
html[data-theme="dark"] .swal2-html-container, body[data-theme="dark"] .swal2-html-container { color: var(--gp-dark-text) !important; }
/* User Avatar / Profile Circle */
html[data-theme="dark"] .user-img,
body[data-theme="dark"] .user-img {
    border: 2px solid #4f46e5 !important;
}
/* Referral page info card dark tweak */
html[data-theme="dark"] .radius-10,
body[data-theme="dark"] .radius-10 {
    background: #111827 !important;
    border: 1px solid var(--gp-dark-border) !important;
}
/* Footer */
html[data-theme="dark"] footer,
body[data-theme="dark"] footer,
html[data-theme="dark"] .footer,
body[data-theme="dark"] .footer {
    background: #0b1020 !important;
    color: var(--gp-dark-text-muted) !important;
    border-top: 1px solid var(--gp-dark-border-2) !important;
}
/* Theme Toggle Button Styles — HIGHEST CLICKABILITY */
/* Parent <li> ko bhi banao clickable, z-index max, no overlap */
.top-menu .navbar-nav > li.nav-item:has(.gp-theme-toggle) {
    position: relative !important;
    z-index: 999999 !important;
    pointer-events: auto !important;
}
.gp-theme-toggle {
    position: relative;
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 42px;
    border-radius: 12px !important;
    margin: 0 3px !important;
    padding: 0 !important;
    border: 2px solid #e5e7eb;
    cursor: pointer !important;
    pointer-events: auto !important;
    z-index: 999999 !important;
    transition: all .2s ease;
    user-select: none;
    -webkit-tap-highlight-color: transparent;
    outline: 2px dashed transparent !important;
    text-decoration: none !important;
    background: linear-gradient(135deg, #fde68a 0%, #fbbf24 100%);
    color: #78350f !important;
    box-shadow: 0 4px 10px rgba(251,191,36,0.35);
}
html[data-theme="dark"] .gp-theme-toggle,
body[data-theme="dark"] .gp-theme-toggle {
    background: linear-gradient(135deg, #312e81 0%, #6d28d9 100%) !important;
    color: #ede9fe !important;
    border-color: rgba(129,140,248,0.7) !important;
    box-shadow: 0 4px 12px rgba(129,140,248,0.5);
}
.gp-theme-toggle:hover {
    transform: translateY(-1px) scale(1.05);
    filter: brightness(1.08);
    text-decoration: none !important;
}
.gp-theme-toggle:active { transform: scale(0.96); }
.gp-theme-toggle:focus { outline: 2px dashed #6366f1 !important; outline-offset: 2px; }
.gp-theme-toggle i {
    font-size: 20px !important;
    line-height: 1 !important;
    pointer-events: none !important;  /* Important: i element kabhi click capture na kare */
}
@media (max-width: 480px) {
    .gp-theme-toggle { width: 40px; height: 40px; margin: 0 2px !important; }
    .gp-theme-toggle i { font-size: 18px !important; }
}
@media (prefers-color-scheme: dark) {
    html:not([data-theme="light"]) body:not([data-theme="light"]) {
        /* reserved; script data-theme always wins */
    }
}
    </style>
</head>

<body>
    <!-- [DISABLED PERMANENTLY] Body Theme Re-Apply backup — user ne off karne ko kaha, keep original white forever.
    <script>
    (function(){
        try {
            var DARK = false;
            try { DARK = (localStorage.getItem('gp_theme') === 'dark'); } catch(e){ DARK = false; }
            var root = document.documentElement;
            var body = document.body;
            if (DARK) {
                try { root.setAttribute('data-theme','dark'); } catch(_){}
                try { body.setAttribute('data-theme','dark'); } catch(_){}
                try { root.setAttribute('data-gp-icon','moon'); } catch(_){}
                try { body.classList.add('gp-dark-mode-active'); body.classList.remove('gp-light-mode-active'); } catch(_){}
            } else {
                // ☀️ Light/default → SAB HATAO (original CSS 100%)
                try { root.removeAttribute('data-theme'); } catch(_){}
                try { body.removeAttribute('data-theme'); } catch(_){}
                try { root.removeAttribute('data-gp-icon'); } catch(_){}
                try { body.classList.remove('gp-dark-mode-active'); body.classList.add('gp-light-mode-active'); } catch(_){}
            }
        } catch(e){}
    })();
    </script>
    -->
    <!-- 🔥 BODY PE BHI HARD WHITE RESET (after body tag exists) — remove dark attrs, storage clear, inline style wipe -->
    <script>
    (function(){
        try {
            try { localStorage.removeItem('gp_theme'); } catch(_){}
            var root = document.documentElement;
            var body = document.body;
            try { root.removeAttribute('data-theme'); root.removeAttribute('data-gp-icon'); } catch(_){}
            try { body.removeAttribute('data-theme'); } catch(_){}
            try { root.removeAttribute('style'); body.removeAttribute('style'); } catch(_){}
            try {
                body.classList.remove('gp-dark-mode-active');
                body.classList.remove('gp-light-mode-active');
            } catch(_){}
        } catch(e){}
    })();
    </script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.inputmask/3.1.62/jquery.inputmask.bundle.js"></script>
    <!-- [DISABLED PERMANENTLY] THEME TOGGLE SCRIPT (Full IIFE — applyTheme, wireBtn, sync, keyboard shortcut) — user ne off karne ko kaha, keep original white forever.
    <script>
    (function(){
        var STORAGE_KEY = 'gp_theme';
        var sunIcon  = 'fa-solid fa-sun';
        var moonIcon = 'fa-solid fa-moon';

        // --- URL ESCAPE: ?gp_theme_reset=1 dal ke page kholo to localStorage clear + reset light ---
        try {
            if (window.location.search.indexOf('gp_theme_reset=1') !== -1) {
                localStorage.removeItem(STORAGE_KEY);
            }
        } catch(e){}

        // ---- HASHED THEME (localStorage) ----
        // 'dark' string hamesha dark
        // null ya 'light' ya kuch bhi ORIGINAL WHITE (data-theme ATTRIBUTE HATA DIYA JAYEGA — zero dark apply)
        window.gpIsDarkStored = function(){
            try { return localStorage.getItem(STORAGE_KEY) === 'dark'; }
            catch(e){ return false; }
        };

        window.gpApplyTheme = function(theme, persist){
            try {
                var DARK = (theme === 'dark');

                // ==================================================
                // ☀️ LIGHT: data-theme + body attributes SAB HATA DO (original white CSS 100% control)
                //   + inline style cache (if any dark stuck) → reset to ''
                // ==================================================
                var root = document.documentElement;
                var body = document.body;
                try {
                    if (!DARK) {
                        root.removeAttribute('data-theme');
                        if (body) body.removeAttribute('data-theme');
                        root.removeAttribute('data-gp-icon');
                        try { root.removeAttribute('style'); } catch(_){}
                        if (body) {
                            try { body.removeAttribute('style'); } catch(_){}
                            body.classList.remove('gp-dark-mode-active');
                            body.classList.add('gp-light-mode-active');
                        }
                    } else {
                        // 🌙 DARK: data-theme='dark' LAGA DO (sab dark CSS selectors `html[data-theme=dark]` se apply)
                        root.setAttribute('data-theme', 'dark');
                        if (body) body.setAttribute('data-theme', 'dark');
                        root.setAttribute('data-gp-icon', 'moon');
                        if (body) {
                            body.classList.remove('gp-light-mode-active');
                            body.classList.add('gp-dark-mode-active');
                        }
                    }
                } catch(attrErr){}

                // ---- BUTTON ICON + TITLE ----
                var btn = document.getElementById('gpThemeToggle');
                var ico = document.getElementById('gpThemeIcon');
                if (ico) ico.className = DARK ? moonIcon : sunIcon;
                if (btn) {
                    btn.setAttribute('title', DARK ? 'Light Mode me jaayein ☀️' : 'Dark Mode me jaayein 🌙');
                    btn.setAttribute('aria-label', DARK ? 'Switch to Light Mode' : 'Switch to Dark Mode');
                }

                // ---- PERSIST (localStorage) ----
                if (persist) {
                    try {
                        if (DARK) localStorage.setItem(STORAGE_KEY, 'dark');
                        else localStorage.removeItem(STORAGE_KEY);
                    } catch(e){}
                }
                if (window.console && typeof console.log === 'function') {
                    console.log('[ThemeToggle] Applied:', DARK ? 'DARK' : 'LIGHT (original)', '| persist:', persist);
                }
                return true;
            } catch(e){
                if (window.console && typeof console.error === 'function') console.error('[ThemeToggle] Error:', e);
                return false;
            }
        };
        window.gpToggleTheme = function(){
            var DARK_NOW = false;
            try {
                var r = document.documentElement;
                var attr = null;
                try { attr = r.getAttribute('data-theme'); } catch(_){}
                DARK_NOW = (attr === 'dark') || window.gpIsDarkStored();
            } catch(_){}
            return window.gpApplyTheme(DARK_NOW ? 'light' : 'dark', true);
        };

        // ---- INITIAL APPLY (page load pe 1 baar) + ICON SYNC + setInterval sync (100ms baad icon correct hoke rahega) ----
        window.gpApplyTheme(window.gpIsDarkStored() ? 'dark' : 'light', false);

        function syncIconNow(){
            try {
                var r = document.documentElement;
                var dark = false;
                try { dark = (r.getAttribute('data-theme') === 'dark'); } catch(_){}
                var ico = document.getElementById('gpThemeIcon');
                if (ico) ico.className = dark ? moonIcon : sunIcon;
                var b = document.getElementById('gpThemeToggle');
                if (b) b.setAttribute('title', dark ? 'Light me jaayein ☀️' : 'Dark me jaayein 🌙');
            } catch(_){}
        }

        // ---- DOUBLE WIRE: addEventListener (failsafe, inline onclick already hai to koi farak nahi) ----
        function wireBtn(){
            try {
                var b = document.getElementById('gpThemeToggle');
                if (!b) return;
                if (b.getAttribute('data-gp-wired') !== '1') {
                    b.setAttribute('data-gp-wired', '1');
                    b.addEventListener('click', function(e){
                        try { e.preventDefault(); } catch(_){}
                        window.gpToggleTheme();
                        if (e && e.stopPropagation) e.stopPropagation();
                        return false;
                    });
                }
                syncIconNow();
                window.gpApplyTheme(window.gpIsDarkStored() ? 'dark' : 'light', false);
                syncIconNow();
            } catch(e){}
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', wireBtn);
        } else {
            wireBtn();
        }
        if (window.jQuery) jQuery(wireBtn);
        setTimeout(wireBtn, 150);
        setTimeout(syncIconNow, 300);
        setTimeout(syncIconNow, 800);

        // ============== KEYBOARD ESCAPE ==============
        // Ctrl + Alt + L = Light Mode (ORIGINAL WHITE)
        // Ctrl + Alt + D = Dark Mode
        // Ctrl + Alt + T = Toggle
        try {
            document.addEventListener('keydown', function(ev){
                try {
                    if (!ev || !ev.ctrlKey || !ev.altKey) return;
                    var k = String(ev.key || '').toLowerCase();
                    if (k === 'l') { ev.preventDefault(); window.gpApplyTheme('light', true); setTimeout(syncIconNow,20); }
                    else if (k === 'd') { ev.preventDefault(); window.gpApplyTheme('dark', true); setTimeout(syncIconNow,20); }
                    else if (k === 't') { ev.preventDefault(); window.gpToggleTheme(); setTimeout(syncIconNow,20); }
                } catch(e){}
            });
        } catch(e){}
    })();
    </script>
    -->
    <!-- 🔥 FINAL HARD WHITE RESET (after jQuery load) — ensure NO dark attrs/styles remain -->
    <script>
    try {
        try { localStorage.removeItem('gp_theme'); } catch(_){}
        try {
            document.documentElement.removeAttribute('data-theme');
            document.documentElement.removeAttribute('data-gp-icon');
            document.documentElement.removeAttribute('style');
        } catch(_){}
        try {
            document.body.removeAttribute('data-theme');
            document.body.removeAttribute('style');
            document.body.classList.remove('gp-dark-mode-active');
            document.body.classList.remove('gp-light-mode-active');
        } catch(_){}
    } catch(e){}
    </script>
    <!--wrapper-->
    <div class="wrapper">
        <!--sidebar wrapper -->
        <div class="sidebar-wrapper" data-simplebar="true">
            <div class="sidebar-header">
                <div>
                    <img src="<?php echo ahkweb('logo'); ?>" class="logo-icon" alt="logo icon">
                </div>
                <div>
                    <h4 class="logo-text">
                        <?php
                        ob_start();
                        ahkweb('webname');
                        $webname = ob_get_clean();

                        $words = explode(' ', trim($webname));
                        echo isset($words[1]) ? $words[0] . ' ' . $words[1] : $words[0];
                        ?>
                    </h4>

                </div>
                <div class="toggle-icon ms-auto"><i class=></i>
                </div>
            </div>
            <!--navigation-->

            <ul class="metismenu" id="menu">
                <li>
                    <a href="index.php">
                       <div class="parent-icon"> <i class="text-danger fa-sharp fa-solid fa-laptop"></i>
                        </div>
                        <div class="menu-title">Dashboard</div>
                    </a>
                </li>


                <!-- User management -->

                <?php if (checkAdmin($udata['type']) == true) { ?>
                    <li>
                        <a href="javascript:;" class="has-arrow">
                            <div class="parent-icon"><img
                                    src="../assets/img/care.jpg" width="30ox"
                                    style="border-radius:4px;">
                            </div>
                            <div class="menu-title">User Management </div>
                        </a>
                        <ul>
                            <li> <a href="users_v2.php"><i class="bx bx-right-arrow-alt"></i>All Users</a>
                            </li>


                            <li> <a href="adduser.php"><i class="bx bx-right-arrow-alt"></i>Add New</a>
                            <li> <a href="qrpay_pending_v2.php"><i class="bx bx-right-arrow-alt"></i>QR pending <sup
                                        style="color:red;"></sup></a>
                            <li> <a href="PendingManualUsers_v2.php"><i class="bx bx-right-arrow-alt"></i>Manual user pending <sup
                                        style="color:red;"></sup></a>
                            <li> <a href="BalanceTransfer.php"><i class="bx bx-right-arrow-alt"></i>Balance Transfer <sup
                                        style="color:red;"></sup></a>
                            </li>


                        </ul>
                    </li>
                <?php } ?>
                <!-- User Management END -->
                <!-- User management -->
                <?php if ($udata['type'] == "distributor" || $udata['type'] == "super_dist") { ?>
                    <li>
                        <a href="javascript:void(0)" style="; !important;"=""="">
                            <div class="parent-icon"><i class="bx bx-user"></i>
                            </div>
                            <div class="menu-title">User Management</div>
                        </a>
                        <ul>
                            <li> <a href="adduser.php"><i class="bx bx-right-arrow-alt"></i>Add New</a></li>
                            <li> <a href="myusers.php"><i class="bx bx-right-arrow-alt"></i>My User List</a></li>
                        </ul>
                    </li>
                <?php } ?>
                
                 <style>
                            .badge-instant {
                                background-color: #28a745;
                                /* Bootstrap success green */
                                color: white;
                                font-size: 10px;
                                padding: 2px 6px;
                                border-radius: 8px;
                                margin-left: 6px;
                                vertical-align: middle;
                                font-weight: bold;
                            }
                        </style>
                <!-- User Management END -->
                <!-- User Management END -->
                <li>
                    <a href="wallet.php" class="">
                        <div class="parent-icon"><img
                                src="../admin/uploads/Services/wallet.png" width="30ox"
                                style="border-radius:4px;">
                        </div>
                        <div class="menu-title">
                            Add Wallet
                            <span class="badge-instant">Instant</span>
                        </div>

                       
                    </a>
                </li>

                <!-- User Apna Wallet Record Dekhne Ke Liye (All Roles) -->
                <li>
                    <a href="my_wallet_record.php" class="">
                        <div class="parent-icon"><i class="text-primary fa-solid fa-receipt"></i>
                        </div>
                        <div class="menu-title">
                            My Wallet Record A2Z
                        </div>
                    </a>
                </li>


            <li class="menu-label">Services</li>
		<li> 
		 <li>  
			<a href="Uid_Flx-Services.php">
				<div class="parent-icon">
					<img src="../images/aadhaar_icon.jpg" width="30" style="border-radius: 3px;">
				</div>
				<div class="menu-title">Aadhaar Services</div>
				<img class="new_blink d-none" style="position: relative; height: 30px; width: 30px; left: 3px;" src="../images/new.png">
			</a>
		</li>
		<li> 
		
		  <li>  
			<a href="pan_card_service.php">
				<div class="parent-icon">
					<img src="../admin/uploads/Services/panservices.png" width="30" style="border-radius: 3px;">
				</div>
				<div class="menu-title">Pan Card Services</div>
				<img class="new_blink d-none" style="position: relative; height: 30px; width: 30px; left: 3px;" src="../images/new.png">
			</a>
		</li>
		<li> 




            <li>  
			<a href="vahicle_card_service.php">
				<div class="parent-icon">
					<img src="../admin/uploads/Services/Yamaha-Bike-PNG.png" width="30" style="border-radius: 3px;">
				</div>
				<div class="menu-title">Vehicle Services</div>
				<img class="new_blink d-none" style="position: relative; height: 30px; width: 30px; left: 3px;" src="../images/new.png">
			</a>
		</li>
		<li> 
		
		 <li>  
			<a href="Voter_card_service.php">
				<div class="parent-icon">
					<img src="../admin/uploads/Services/voter.png" width="30" style="border-radius: 3px;">
				</div>
				<div class="menu-title">Voter Services</div>
				<img class="new_blink d-none" style="position: relative; height: 30px; width: 30px; left: 3px;" src="../images/new.png">
			</a>
		</li>
		<li> 
		
		 <li>  
			<a href="Ration_card_service.php">
				<div class="parent-icon">
					<img src="../admin/uploads/Services/ration1.png" width="30" style="border-radius: 3px;">
				</div>
				<div class="menu-title">Ration Services</div>
				<img class="new_blink d-none" style="position: relative; height: 30px; width: 30px; left: 3px;" src="../images/new.png">
			</a>
		</li>
		<li> 
		
		<li>  
			<a href="ll_request">
				<div class="parent-icon">
					<img src="https://goodprintportal.in/ll.png" width="30" style="border-radius: 3px;">
				</div>
				<div class="menu-title">LL Exam</div>
				<img class="new_blink d-none" style="position: relative; height: 30px; width: 30px; left: 3px;" src="../images/new.png">
			</a>
		</li>
		<li>
		 <li>  
			<a href="Samagra_card_service.php">
				<div class="parent-icon">
					<img src="https://goodprintportal.in/smg.png" width="30" style="border-radius: 3px;">
				</div>
				<div class="menu-title">Samagra Services</div>
				<img class="new_blink d-none" style="position: relative; height: 30px; width: 30px; left: 3px;" src="../images/new.png">
			</a>
			</li>
		<li>
		 <li>  
			<a href="vahicle_card_service">
				<div class="parent-icon">
					<img src="https://goodprintportal.in/dl.png" width="30" style="border-radius: 3px;">
				</div>
				<div class="menu-title">DL Services</div>
				<img class="new_blink d-none" style="position: relative; height: 30px; width: 30px; left: 3px;" src="../images/new.png">
			</a>
		</li>
		<li>
		
	    	<li>
			<a href="vahicle_card_service">
				<div class="parent-icon">
					<img src="https://goodprintportal.in/s2.png" width="30" style="border-radius: 3px;">
				</div>
				<div class="menu-title">Rc Pdf Services</div>
				<img class="new_blink d-none" style="position: relative; height: 30px; width: 30px; left: 3px;" src="../images/new.png">
			</a>
		</li>
		<?php
		// TRICK Buy sidebar link — HIDE if service disabled AND user is NOT admin. Admin can always see it to manage.
		$_trickShow = true;
		if (empty($webdata) || (isset($webdata['trick_service_enabled']) && (int)$webdata['trick_service_enabled'] !== 1)) {
		    $_trickShow = false;
		    if (!empty($udata)) {
		        $_role = strtolower(trim((string)($udata['role'] ?? $udata['type'] ?? '')));
		        if ($_role === 'admin' || $_role === 'super admin' || $_role === 'super_admin' || !empty($udata['is_admin'])) {
		            $_trickShow = true;
		        }
		    }
		}
		if ($_trickShow):
		    $_trickBadge = '';
		    if (function_exists('trick_pending_count')) {
		        global $ahk_conn;
		        if ($ahk_conn && !empty($udata)) {
		            $_role = strtolower(trim((string)($udata['role'] ?? $udata['type'] ?? '')));
		            $_isAdm = ($_role === 'admin' || $_role === 'super admin' || $_role === 'super_admin' || !empty($udata['is_admin']));
		            if ($_isAdm) {
		                $_cnt = trick_pending_count($ahk_conn);
		                if ($_cnt > 0) $_trickBadge = '<span class="badge bg-warning text-dark ms-1" style="font-size:10px;">'.$_cnt.'</span>';
		            }
		        }
		    }
		?>
		<li>
			<a href="trick_buy_card.php">
				<div class="parent-icon">
					<img src="https://goodprintportal.in/trick.png" alt="TRICK Buy Services" style="width:30px;height:30px;border-radius:6px;object-fit:contain;">
				</div>
				<div class="menu-title">TRICK Buy Services</div>
				<?= $_trickBadge ?>
				<img class="new_blink" style="position: relative; height: 30px; width: 30px; left: 3px;" src="../images/new.png">
			</a>
		</li>
		<?php unset($_trickShow, $_trickBadge, $_role, $_isAdm, $_cnt); endif; ?>
		<li>
		 <li>  
			<a href="ElectriBill_card_service.php">
				<div class="parent-icon">
					<img src="../admin/uploads/Services/electricBill.png" width="30" style="border-radius: 3px;">
				</div>
				<div class="menu-title">ElectriBill Services</div>
				<img class="new_blink d-none" style="position: relative; height: 30px; width: 30px; left: 3px;" src="../images/new.png">
			</a>
		</li>
		<li> 
		
			 <li>  
			<a href="FarmerStackService.php">
				<div class="parent-icon">
					<img src="../admin/uploads/Services/farmerLogo0.png" width="30" style="border-radius: 3px;">
				</div>
				<div class="menu-title">Farmer Services</div>
				<img class="new_blink d-none" style="position: relative; height: 30px; width: 30px; left: 3px;" src="../images/new.png">
			</a>
		</li>
		<li> 
		
			 <li>  
			<a href="Passbook_card_service.php">
				<div class="parent-icon">
					<img src="../admin/uploads/Services/1771198.png" width="30" style="border-radius: 3px;">
				</div>
				<div class="menu-title">Passbook Services</div>
				<img class="new_blink d-none" style="position: relative; height: 30px; width: 30px; left: 3px;" src="../images/new.png">
			</a>
		</li>
		<li> 

 <li>  
			<a href="Other_card_service.php">
				<div class="parent-icon">
					<img src="../admin/uploads/Services/dlpng.png" width="30" style="border-radius: 3px;">
				</div>
				<div class="menu-title">Other Services</div>
				<img class="new_blink d-none" style="position: relative; height: 30px; width: 30px; left: 3px;" src="../images/new.png">
			</a>
		</li>
		<li> 





        
<li class="menu-label">HELP & STATEMENT </li>

            <!-- Wallet management START -->

            <li>
                <a href="wallet_history.php" class="">
                    <div class="parent-icon"><i class="text-success fa fa-wallet"></i>
                    </div>
                    <div class="menu-title">
                        Wallet Record

                    </div>


                </a>
            </li>
            
             <li>
                <a href="complaint_request.php" class="">
                    <div class="parent-icon"><i class="text-success fa fa-exclamation-circle"></i>
                    </div>
                    <div class="menu-title">
                        Raise Complaint

                    </div>


                </a>
            </li>
            
             <li>
                <a href="complaint_request_list.php" class="">
                    <div class="parent-icon"><i class="fas fa-regular fa-headset"></i>
                    </div>
                    <div class="menu-title">
                        Complaint List

                    </div>


                </a>
            </li>
            
            <li>
			<a href="pricing_list.php">
				<div class="parent-icon"><i class="fa-solid fa-indian-rupee-sign"></i>
				</div>
				<div class="menu-title">&nbsp Pricing</div>
			</a>
		</li>
               <?php
                    if (checkAdmin($udata['type']) == true) {
                        ?>
            
<li class="menu-label">Admin Control </li>

            <!-- Gateway Mode (Admin Only) -->
            <li>
                <a href="gateway_mode.php" class="">
                    <div class="parent-icon"><i class="text-warning fa-solid fa-toggle-on"></i>
                    </div>
                    <div class="menu-title">
                        Gateway Mode
                    </div>
                </a>
            </li>

            <!-- User Wallet Transaction Record (Admin Only) -->
            <li>
                <a href="wallet_user_record.php" class="">
                    <div class="parent-icon"><i class="text-danger fa-solid fa-file-invoice-dollar"></i>
                    </div>
                    <div class="menu-title">
                        User Wallet Record A2Z
                    </div>
                </a>
            </li>

       
                
            <li>
             <a href="tec_exam_req_admin" class="">
               <div class="parent-icon" style="position:relative;">
                 <i class="text-success fa fa-credit-card"></i>
                 <?php if ($pending_tec_count > 0) { ?>
                    <span class="notif-badge nb-tec"><?php echo _smFormatBadgeCount($pending_tec_count); ?></span>
                 <?php } ?>
                </div>
                   <div class="menu-title" style="display:flex;align-items:center;gap:8px;">
                       Tec Admin List
                       <?php if ($pending_tec_count > 0) { ?>
                           <span class="notif-badge-pill nb-tec"><?php echo _smFormatBadgeCount($pending_tec_count); ?></span>
                       <?php } ?>

               </div


         </a>
            </li>
                
            <li>
                <a href="ll_request_admin.php" class="">
                    <div class="parent-icon" style="position:relative;">
                        <i class="text-success fa-solid fa-address-card"></i>
                        <?php if ($pending_ll_count > 0) { ?>
                            <span class="notif-badge nb-ll"><?php echo _smFormatBadgeCount($pending_ll_count); ?></span>
                        <?php } ?>
                    </div>
                    <div class="menu-title" style="display:flex;align-items:center;gap:8px;">
                        LL Admin List
                        <?php if ($pending_ll_count > 0) { ?>
                            <span class="notif-badge-pill nb-ll"><?php echo _smFormatBadgeCount($pending_ll_count); ?></span>
                        <?php } ?>

                    </div>


                </a>
            </li>

            <li>
                <a href="samagra_pdf_admin.php">
                    <div class="parent-icon" style="position:relative;">
                        <i class="text-success fa-solid fa-file-pdf"></i>
                        <?php if ($sm_pdf > 0) { ?>
                            <span class="notif-badge nb-pdf"><?php echo _smFormatBadgeCount($sm_pdf); ?></span>
                        <?php } ?>
                    </div>
                    <div class="menu-title" style="display:flex;align-items:center;gap:8px;">
                        Samagra PDF Requests
                        <?php if ($sm_pdf > 0) { ?>
                            <span class="notif-badge-pill nb-pdf"><?php echo _smFormatBadgeCount($sm_pdf); ?></span>
                        <?php } ?>
                    </div>
                </a>
            </li>

            <li>
                <a href="samagra_new_apply_admin.php">
                    <div class="parent-icon" style="position:relative;">
                        <i class="text-success fa-solid fa-user-plus"></i>
                        <?php if ($sm_newapply > 0) { ?>
                            <span class="notif-badge nb-newapply"><?php echo _smFormatBadgeCount($sm_newapply); ?></span>
                        <?php } ?>
                    </div>
                    <div class="menu-title" style="display:flex;align-items:center;gap:8px;">
                        Samagra New Apply
                        <?php if ($sm_newapply > 0) { ?>
                            <span class="notif-badge-pill nb-newapply"><?php echo _smFormatBadgeCount($sm_newapply); ?></span>
                        <?php } ?>
                    </div>
                </a>
            </li>

            <li>
                <a href="samagra_new_family_id_admin.php">
                    <div class="parent-icon" style="position:relative;">
                        <i class="text-success fa-solid fa-people-roof"></i>
                        <?php if ($sm_newfam > 0) { ?>
                            <span class="notif-badge nb-newfam"><?php echo _smFormatBadgeCount($sm_newfam); ?></span>
                        <?php } ?>
                    </div>
                    <div class="menu-title" style="display:flex;align-items:center;gap:8px;">
                        Samagra New Family ID
                        <?php if ($sm_newfam > 0) { ?>
                            <span class="notif-badge-pill nb-newfam"><?php echo _smFormatBadgeCount($sm_newfam); ?></span>
                        <?php } ?>
                    </div>
                </a>
            </li>

            <li>
                <a href="samagra_pravasan_admin.php">
                    <div class="parent-icon" style="position:relative;">
                        <i class="text-success fa-solid fa-person-walking-luggage"></i>
                        <?php if ($sm_pravasan > 0) { ?>
                            <span class="notif-badge nb-pravasan"><?php echo _smFormatBadgeCount($sm_pravasan); ?></span>
                        <?php } ?>
                    </div>
                    <div class="menu-title" style="display:flex;align-items:center;gap:8px;">
                        Samagra Pravasan
                        <?php if ($sm_pravasan > 0) { ?>
                            <span class="notif-badge-pill nb-pravasan"><?php echo _smFormatBadgeCount($sm_pravasan); ?></span>
                        <?php } ?>
                    </div>
                </a>
            </li>

            <li>
                <a href="samagra_merge_family_id_admin.php">
                    <div class="parent-icon" style="position:relative;">
                        <i class="text-success fa-solid fa-code-merge"></i>
                        <?php if ($sm_merge > 0) { ?>
                            <span class="notif-badge nb-merge"><?php echo _smFormatBadgeCount($sm_merge); ?></span>
                        <?php } ?>
                    </div>
                    <div class="menu-title" style="display:flex;align-items:center;gap:8px;">
                        Merge Family ID
                        <?php if ($sm_merge > 0) { ?>
                            <span class="notif-badge-pill nb-merge"><?php echo _smFormatBadgeCount($sm_merge); ?></span>
                        <?php } ?>
                    </div>
                </a>
            </li>

            <li>
                <a href="samagra_recover_member_admin.php">
                    <div class="parent-icon" style="position:relative;">
                        <i class="text-success fa-solid fa-user-check"></i>
                        <?php if ($sm_recover > 0) { ?>
                            <span class="notif-badge nb-recover"><?php echo _smFormatBadgeCount($sm_recover); ?></span>
                        <?php } ?>
                    </div>
                    <div class="menu-title" style="display:flex;align-items:center;gap:8px;">
                        Recover Member
                        <?php if ($sm_recover > 0) { ?>
                            <span class="notif-badge-pill nb-recover"><?php echo _smFormatBadgeCount($sm_recover); ?></span>
                        <?php } ?>
                    </div>
                </a>
            </li>

            <li>
                <a href="samagra_member_delete_admin.php">
                    <div class="parent-icon" style="position:relative;">
                        <i class="text-success fa-solid fa-user-minus"></i>
                        <?php if ($sm_memdel > 0) { ?>
                            <span class="notif-badge nb-memdel"><?php echo _smFormatBadgeCount($sm_memdel); ?></span>
                        <?php } ?>
                    </div>
                    <div class="menu-title" style="display:flex;align-items:center;gap:8px;">
                        Samagra Member Delete
                        <?php if ($sm_memdel > 0) { ?>
                            <span class="notif-badge-pill nb-memdel"><?php echo _smFormatBadgeCount($sm_memdel); ?></span>
                        <?php } ?>
                    </div>
                </a>
            </li>

            <li>
                <a href="samagra_member_transfer_admin.php">
                    <div class="parent-icon" style="position:relative;">
                        <i class="text-success fa-solid fa-right-left"></i>
                        <?php if ($sm_memxfer > 0) { ?>
                            <span class="notif-badge nb-memxfer"><?php echo _smFormatBadgeCount($sm_memxfer); ?></span>
                        <?php } ?>
                    </div>
                    <div class="menu-title" style="display:flex;align-items:center;gap:8px;">
                        Samagra Member Transfer
                        <?php if ($sm_memxfer > 0) { ?>
                            <span class="notif-badge-pill nb-memxfer"><?php echo _smFormatBadgeCount($sm_memxfer); ?></span>
                        <?php } ?>
                    </div>
                </a>
            </li>

            <li>
                <a href="samagra_married_divorce_admin.php">
                    <div class="parent-icon" style="position:relative;">
                        <i class="text-success fa-solid fa-heart-crack"></i>
                        <?php if ($sm_divorce > 0) { ?>
                            <span class="notif-badge nb-divorce"><?php echo _smFormatBadgeCount($sm_divorce); ?></span>
                        <?php } ?>
                    </div>
                    <div class="menu-title" style="display:flex;align-items:center;gap:8px;">
                        Samagra Married to Divorce
                        <?php if ($sm_divorce > 0) { ?>
                            <span class="notif-badge-pill nb-divorce"><?php echo _smFormatBadgeCount($sm_divorce); ?></span>
                        <?php } ?>
                    </div>
                </a>
            </li>

            <li>
                <a href="samagra_kyc_admin.php">
                    <div class="parent-icon" style="position:relative;">
                        <i class="text-success fa-solid fa-id-card"></i>
                        <?php if ($sm_kyc > 0) { ?>
                            <span class="notif-badge nb-kyc"><?php echo _smFormatBadgeCount($sm_kyc); ?></span>
                        <?php } ?>
                    </div>
                    <div class="menu-title" style="display:flex;align-items:center;gap:8px;">
                        Samagra KYC Requests
                        <?php if ($sm_kyc > 0) { ?>
                            <span class="notif-badge-pill nb-kyc"><?php echo _smFormatBadgeCount($sm_kyc); ?></span>
                        <?php } ?>
                    </div>
                </a>
            </li>

            <li>
                <a href="samagra_pricing.php">
                    <div class="parent-icon"><i class="text-warning fa-solid fa-tags"></i></div>
                    <div class="menu-title">Samagra Pricing</div>
                </a>
            </li>
            
              <li>
                <a href="complaint_request_list_admin.php" class="">
                    <div class="parent-icon" style="position:relative;">
                        <i class="fas fa-regular fa-headset"></i>
                        <?php if ($pending_complaint_count > 0) { ?>
                            <span style="position:absolute;top:-6px;right:-8px;min-width:20px;height:20px;padding:0 5px;background:linear-gradient(135deg,#ff416c 0%,#ff4b2b 100%);color:#fff;border-radius:999px;font-size:10px;font-weight:800;display:flex;align-items:center;justify-content:center;box-shadow:0 3px 8px rgba(255,75,43,0.5),inset 0 1px 0 rgba(255,255,255,0.25);border:1.5px solid #fff;letter-spacing:0.1px;line-height:1;animation:complaintBadgePulse 2s ease-in-out infinite;">
                                <?php echo $pending_complaint_count > 99 ? '99+' : (int)$pending_complaint_count; ?>
                            </span>
                        <?php } ?>
                    </div>
                    <div class="menu-title" style="display:flex;align-items:center;gap:8px;">
                        Complaint Admin List
                        <?php if ($pending_complaint_count > 0) { ?>
                            <span style="padding:2px 9px;background:linear-gradient(135deg,#ff416c 0%,#ff4b2b 100%);color:#fff;border-radius:999px;font-size:11px;font-weight:700;box-shadow:0 3px 8px rgba(255,75,43,0.4);letter-spacing:0.2px;line-height:1.4;animation:complaintBadgePulse 2s ease-in-out infinite;">
                                <?php echo $pending_complaint_count > 99 ? '99+' : (int)$pending_complaint_count; ?>
                            </span>
                        <?php } ?>
                    </div>
                </a>
            </li>
            
            <li>
                <a href="push_notice.php">
                    <div class="parent-icon"><i class="fa-solid fa-bullhorn text-warning" style="font-size:18px;"></i></div>
                    <div class="menu-title">Push Notice</div>
                </a>
            </li>

            <li>
                <a href="popup_setup.php">
                    <div class="parent-icon"><i class="fa-solid fa-wand-magic-sparkles" style="font-size:18px;color:#ec4899;"></i></div>
                    <div class="menu-title" style="display:flex;align-items:center;gap:8px;">
                        Popup Setup
                        <?php $__popupSt = $GLOBALS['gpPopupSettings']['enabled'] ?? 0; ?>
                        <span style="padding:2px 8px;border-radius:999px;font-size:10px;font-weight:800;letter-spacing:.3px;color:#fff;<?= $__popupSt ? 'background:linear-gradient(135deg,#16a34a 0%,#15803d 100%);box-shadow:0 2px 6px rgba(22,163,74,.4);' : 'background:linear-gradient(135deg,#6b7280 0%,#4b5563 100%);box-shadow:0 2px 6px rgba(107,114,128,.35);' ?>">
                            <?= $__popupSt ? 'ON' : 'OFF' ?>
                        </span>
                    </div>
                </a>
            </li>

                   <li>
                    <a href="javascript:;" class="has-arrow">
                        <div class="parent-icon"><img
                                src="https://img.icons8.com/?size=100&id=h9udvJ8YzhgG&format=png&color=000000" width="30ox"
                                style="border-radius:4px;">
                        </div>
                        <div class="menu-title">Website Setting</div>
                    </a>
                    <ul>
                        <li> <a href="popup_setup.php"><i class="fa-solid fa-wand-magic-sparkles" style="font-size:12px;margin-right:6px;"></i>Popup Setup</a>
                        </li>
                        <li> <a href="pricing.php"><i class="bx bx-right-arrow-alt"></i>Pricing</a>
                        </li>
                        <li> <a href="samagra_pricing.php"><i class="bx bx-right-arrow-alt"></i>Samagra Pricing</a>
                        </li>
                        <li> <a href="LogoUpdate.php"><i class="bx bx-right-arrow-alt"></i>Update Logo</a>
                        </li>
                        <li> <a href="LogoMainUpdate.php"><i class="bx bx-home-heart"></i>Main Logo (Home + Login)</a>
                        </li>
                        <li> <a href="settings.php"><i class="bx bx-right-arrow-alt"></i>Website Details</a>
                        </li>
                        <li> <a href="referral_settings.php"><i class="fa-solid fa-gift" style="font-size:12px;margin-right:6px;color:#a855f7;"></i>Referral Bonus Set</a>
                        </li>
                    </ul>
                </li>

                   <li>
                    <a href="data_cleanup.php">
                        <div class="parent-icon"><i class="text-danger fa-solid fa-database" style="font-size:20px;"></i>
                        </div>
                        <div class="menu-title">Data Cleanup
                            <span class="badge bg-danger ms-1" style="font-size:10px;">DB Size</span>
                        </div>
                    </a>
                </li>

                   <li>
                    <a href="referral_settings.php">
                        <div class="parent-icon"><i class="fa-solid fa-gift" style="font-size:20px;color:#a855f7;"></i>
                        </div>
                        <div class="menu-title">Referral Bonus
                            <span class="badge ms-1" style="font-size:10px;background: linear-gradient(135deg,#8b5cf6,#6366f1);color:#fff;">SET</span>
                        </div>
                    </a>
                </li>


<?php
                    }
                    ?>
          
          
  
            <!-- Settings -->
            <!-- Refer and Earn (ONLY when ref_enabled = 1) -->
<?php
$_gpRefEnabled = true;
if (is_array($webdata ?? null) && array_key_exists('ref_enabled', $webdata)) {
    $_gpRefEnabled = ((int)($webdata['ref_enabled'] ?? 1) === 1);
} else {
    // Column exists nahi? DB check kar lo (rare case)
    global $ahk_conn;
    if ($ahk_conn) {
        static $gpRefEnCache = null;
        if ($gpRefEnCache === null) {
            $cr = @mysqli_query($ahk_conn, "SHOW COLUMNS FROM settings LIKE 'ref_enabled'");
            if ($cr && mysqli_num_rows($cr) > 0) {
                $rr = @mysqli_query($ahk_conn, "SELECT ref_enabled FROM settings WHERE id=1 LIMIT 1");
                if ($rr && ($rw = mysqli_fetch_assoc($rr))) $gpRefEnCache = ((int)($rw['ref_enabled'] ?? 1) === 1);
                else $gpRefEnCache = true;
            } else {
                $gpRefEnCache = true;
            }
        }
        $_gpRefEnabled = $gpRefEnCache;
    }
}
?>
<?php if ($_gpRefEnabled): ?>
            <li>
                <a href="referral.php" class="">
                    <div class="parent-icon"><img
                            src="https://img.icons8.com/?size=100&id=9kseaedOXUkb&format=png&color=000000" width="30ox"
                            style="border-radius:4px;">
                    </div>
                    <div class="menu-title">Refer & Earn</div>
                </a>

            </li>
<?php endif; ?>
            <li>
                <a href="../includes/logout.php" class="">
                    <div class="parent-icon"><img
                            src="https://img.icons8.com/?size=100&id=fmOzBzMvUPQR&format=png&color=000000" width="30ox"
                            style="border-radius:4px;">
                    </div>
                    <div class="menu-title">Logout</div>
                </a>
            </li>
            <!-- Refer and Earn -->
            <!-- Content Shifted to sidebarextra -->
        </div>
        <!--end sidebar wrapper -->
        <!--start header -->



        <header>
            <div class="topbar d-flex align-items-center">
                <nav class="navbar navbar-expand">
                    <div class="mobile-toggle-menu"><i class='bx bx-menu'></i>
                    </div>
                    <?php
                    if (isset($_GET['gettoAdmin']) && $_GET['session'] == 'true') {
                        loginAsAdmin();
                    }
                    if (isset($_SESSION['adminasuser']) == true && $_SESSION['adminusername'] != NULL) {
                        ?>
                        <div class="search-bar flex-grow-1">
                            <div class="position-relative search-bar-box">
                                <a href="?gettoAdmin=1&session=true" type="submit" class="btn btn-success">Go Back to Admin Panel</a>
                            </div>
                        </div>
                    <?php } else { ?>
                        <div class="flex-grow-1 d-none d-md-block"></div>
                    <?php } ?>

                    <style>
                        .gp-header-date {
                            font-size: 12.5px;
                            font-weight: 600;
                            color: #374151;
                            background: #f3f4f6;
                            padding: 5px 11px;
                            border-radius: 7px;
                            letter-spacing: 0.2px;
                            border: 1px solid #e5e7eb;
                            display: inline-flex;
                            align-items: center;
                            gap: 6px;
                            margin-right: 10px;
                            white-space: nowrap;
                        }
                        .gp-header-date i {
                            color: #6366f1;
                            font-size: 13px;
                        }
                        .gp-wallet-chip {
                            display: inline-flex;
                            align-items: center;
                            gap: 6px;
                            padding: 5px 12px;
                            border-radius: 8px;
                            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #3730a3 100%);
                            color: #fff;
                            font-weight: 700;
                            font-size: 13px;
                            letter-spacing: 0.2px;
                            box-shadow: 0 3px 8px rgba(30,58,138,0.35);
                            text-decoration: none;
                            margin-right: 14px;
                            white-space: nowrap;
                            border: 1px solid rgba(255,255,255,0.1);
                            transition: all .2s ease;
                        }
                        .gp-wallet-chip:hover {
                            color: #fff;
                            transform: translateY(-1px);
                            box-shadow: 0 5px 12px rgba(30,58,138,0.45);
                        }
                        .gp-wallet-chip i {
                            font-size: 14px;
                            opacity: .95;
                        }
                        .gp-top-icon-btn {
                            position: relative;
                            display: inline-flex !important;
                            align-items: center;
                            justify-content: center;
                            width: 38px;
                            height: 38px;
                            border-radius: 9px !important;
                            background: #f3f4f6 !important;
                            border: 1px solid #e5e7eb;
                            margin: 0 3px !important;
                            padding: 0 !important;
                            color: #4b5563 !important;
                            transition: all .2s ease;
                        }
                        .gp-top-icon-btn:hover {
                            background: #e0e7ff !important;
                            color: #4338ca !important;
                            border-color: #c7d2fe;
                        }
                        .gp-top-icon-btn i {
                            font-size: 18px !important;
                        }
                        .gp-top-icon-btn .alert-count {
                            position: absolute !important;
                            top: -2px !important;
                            right: -2px !important;
                            min-width: 18px !important;
                            height: 18px !important;
                            padding: 0 4px !important;
                            font-size: 9.5px !important;
                            font-weight: 800 !important;
                            border-radius: 999px !important;
                            display: inline-flex !important;
                            align-items: center;
                            justify-content: center;
                            color: #fff !important;
                            background: linear-gradient(135deg,#ef4444 0%,#dc2626 100%) !important;
                            border: 1.5px solid #fff;
                            line-height: 1 !important;
                        }
                        @media (max-width: 992px) {
                            .gp-header-date { display: none !important; }
                            .gp-wallet-chip { margin-right: 6px; padding: 5px 9px; font-size: 12px; }
                            html body .gp-daily-hit-chip { display: none !important; }
                            html body .gp-complaint-btn { display: none !important; }
                            html body .gp-txn-btn { display: none !important; }
                            html body .gp-whatsapp-btn { display: none !important; }
                            html body .gp-refer-btn { display: none !important; }
                            html body .gp-addbalance-btn { display: none !important; }
                            html body .gp-today-spend-chip { display: none !important; }
                        }
                        @media (max-width: 480px) {
                            .gp-top-icon-btn { width: 34px; height: 34px; margin: 0 2px !important; }
                        }

                        /* ====== Daily API Hit Count (Green Pill) ====== */
                        .gp-daily-hit-chip {
                            display: inline-flex;
                            align-items: center;
                            gap: 5px;
                            padding: 5px 11px;
                            border-radius: 8px;
                            background: linear-gradient(135deg, #10b981 0%, #059669 50%, #047857 100%);
                            color: #fff;
                            font-weight: 700;
                            font-size: 12px;
                            letter-spacing: 0.2px;
                            box-shadow: 0 3px 8px rgba(16,185,129,0.35);
                            text-decoration: none;
                            margin-right: 10px;
                            white-space: nowrap;
                            border: 1px solid rgba(255,255,255,0.1);
                            transition: all .2s ease;
                        }
                        .gp-daily-hit-chip:hover {
                            color: #fff;
                            transform: translateY(-1px);
                            box-shadow: 0 5px 12px rgba(16,185,129,0.45);
                        }
                        .gp-daily-hit-chip i { font-size: 12px; opacity: .95; }

                        /* ====== Low Balance Alert on Wallet Chip ====== */
                        .gp-wallet-chip.gp-wallet-low {
                            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 50%, #991b1b 100%) !important;
                            animation: gpWalletPulse 1.6s ease-in-out infinite;
                            border: 1.5px solid #fca5a5 !important;
                            box-shadow: 0 0 0 0 rgba(220,38,38,.7), 0 3px 10px rgba(220,38,38,0.45);
                        }
                        @keyframes gpWalletPulse {
                            0%,100% { box-shadow: 0 0 0 0 rgba(220,38,38,.7), 0 3px 10px rgba(220,38,38,0.45); }
                            50%     { box-shadow: 0 0 0 10px rgba(220,38,38,0), 0 5px 14px rgba(220,38,38,0.55); }
                        }
                        .gp-low-badge {
                            position: absolute;
                            top: -6px;
                            right: -6px;
                            min-width: 18px;
                            height: 18px;
                            padding: 0 5px;
                            border-radius: 999px;
                            background: linear-gradient(135deg, #fde047 0%, #facc15 100%);
                            color: #854d0e;
                            font-size: 11px;
                            font-weight: 900;
                            display: inline-flex;
                            align-items: center;
                            justify-content: center;
                            border: 1.5px solid #fff;
                            line-height: 1;
                            animation: gpBadgeShake .7s ease-in-out infinite;
                            z-index: 5;
                        }
                        @keyframes gpBadgeShake {
                            0%,100% { transform: rotate(0) scale(1); }
                            20% { transform: rotate(-12deg) scale(1.05); }
                            40% { transform: rotate(10deg) scale(1.08); }
                            60% { transform: rotate(-6deg) scale(1.05); }
                            80% { transform: rotate(4deg) scale(1.02); }
                        }
                        .gp-wallet-chip {
                            position: relative;
                        }

                        /* ====== ADD BALANCE BUTTON - IRRESISTIBLE MONEY CTA ====== */
                        .gp-addbalance-btn {
                            display: inline-flex;
                            align-items: center;
                            justify-content: center;
                            padding: 5px 14px;
                            border-radius: 9px;
                            background: linear-gradient(135deg, #047857 0%, #059669 20%, #10b981 40%, #34d399 58%, #fbbf24 82%, #f59e0b 100%);
                            color: #fff;
                            font-weight: 900;
                            font-size: 12.5px;
                            letter-spacing: 0.7px;
                            text-transform: uppercase;
                            box-shadow:
                                0 0 0 1.5px rgba(255,255,255,0.5),
                                0 0 0 3px rgba(16,185,129,0.25),
                                0 4px 18px rgba(16,185,129,0.55),
                                0 0 22px rgba(251,191,36,0.25),
                                inset 0 1px 0 rgba(255,255,255,0.75),
                                inset 0 -1px 0 rgba(4,120,87,0.35);
                            text-decoration: none;
                            margin-right: 8px;
                            white-space: nowrap;
                            border: none;
                            position: relative;
                            overflow: hidden;
                            cursor: pointer;
                            transition: transform .18s ease, box-shadow .18s ease, filter .18s ease;
                            z-index: 1;
                            animation: gpAddBalanceGlow 2.6s ease-in-out infinite;
                        }
                        .gp-addbalance-btn::before {
                            content: '';
                            position: absolute;
                            top: 0;
                            left: -120%;
                            width: 70px;
                            height: 100%;
                            background: linear-gradient(
                                120deg,
                                transparent 0%,
                                rgba(255,255,255,0.78) 42%,
                                rgba(255,255,255,1) 50%,
                                rgba(255,255,255,0.78) 58%,
                                transparent 100%
                            );
                            transform: skewX(-20deg);
                            animation: gpAddBalanceShine 3.2s ease-in-out infinite;
                            z-index: 2;
                        }
                        .gp-addbalance-btn::after {
                            content: '';
                            position: absolute;
                            inset: 2px;
                            border-radius: 7px;
                            background: linear-gradient(180deg, rgba(255,255,255,0.22) 0%, transparent 45%);
                            pointer-events: none;
                            z-index: 2;
                        }
                        .gp-addbalance-btn:hover {
                            color: #fff;
                            transform: translateY(-2px) scale(1.045);
                            box-shadow:
                                0 0 0 1.5px rgba(255,255,255,0.75),
                                0 0 0 4px rgba(16,185,129,0.45),
                                0 7px 28px rgba(16,185,129,0.75),
                                0 0 32px rgba(251,191,36,0.55),
                                inset 0 1px 0 rgba(255,255,255,0.9),
                                inset 0 -1px 0 rgba(4,120,87,0.4);
                            filter: brightness(1.1) saturate(1.15) contrast(1.03);
                        }
                        .gp-addbalance-btn:active {
                            transform: translateY(0) scale(0.975);
                        }
                        .gp-addbalance-btn span {
                            position: relative;
                            z-index: 3;
                            color: #fff;
                            text-shadow:
                                0 1px 0 rgba(0,0,0,0.35),
                                0 2px 4px rgba(4,120,87,0.45);
                            font-weight: 900;
                            -webkit-text-fill-color: initial;
                            background: none;
                        }
                        .gp-addbalance-btn span::before {
                            content: '₹ ';
                            font-weight: 900;
                            opacity: 1;
                            color: #fde68a;
                            text-shadow: 0 0 6px rgba(253,230,138,0.8), 0 1px 2px rgba(4,120,87,0.5);
                        }

                        @keyframes gpAddBalanceGlow {
                            0%,100% {
                                box-shadow:
                                    0 0 0 1.5px rgba(255,255,255,0.5),
                                    0 0 0 3px rgba(16,185,129,0.25),
                                    0 4px 18px rgba(16,185,129,0.55),
                                    0 0 22px rgba(251,191,36,0.25),
                                    inset 0 1px 0 rgba(255,255,255,0.75),
                                    inset 0 -1px 0 rgba(4,120,87,0.35);
                            }
                            50% {
                                box-shadow:
                                    0 0 0 1.5px rgba(255,255,255,0.8),
                                    0 0 0 5px rgba(16,185,129,0.15),
                                    0 5px 22px rgba(16,185,129,0.7),
                                    0 0 30px rgba(251,191,36,0.55),
                                    0 0 42px rgba(52,211,153,0.35),
                                    inset 0 1px 0 rgba(255,255,255,0.9),
                                    inset 0 -1px 0 rgba(4,120,87,0.4);
                                filter: brightness(1.07) saturate(1.08);
                            }
                        }
                        @keyframes gpAddBalanceShine {
                            0%   { left: -120%; }
                            42%  { left: -120%; }
                            60%  { left: 130%; }
                            100% { left: 130%; }
                        }

                        /* ====== Today's Spend Chip (Compact) ====== */
                        .gp-today-spend-chip {
                            display: inline-flex;
                            align-items: center;
                            gap: 5px;
                            padding: 4px 8px;
                            border-radius: 7px;
                            background: linear-gradient(135deg, #f97316 0%, #ea580c 50%, #c2410c 100%);
                            color: #fff;
                            font-weight: 700;
                            font-size: 11px;
                            box-shadow: 0 2px 6px rgba(249,115,22,0.35);
                            text-decoration: none;
                            margin-right: 6px;
                            white-space: nowrap;
                            border: 1px solid rgba(255,255,255,0.1);
                            transition: all .2s ease;
                        }
                        .gp-today-spend-chip:hover {
                            color: #fff;
                            transform: translateY(-1px);
                            box-shadow: 0 4px 10px rgba(249,115,22,0.45);
                        }
                        .gp-today-spend-chip b {
                            color: #fff;
                            font-weight: 800;
                            font-size: 11px;
                        }
                        .gp-today-spend-chip i {
                            font-size: 11px;
                            opacity: .95;
                        }

                        /* ====== Raise Complaint Icon (Laptop: Green Headset, Mobile: Normal) ====== */
                        .gp-complaint-btn {
                            position: relative;
                            display: inline-flex !important;
                            align-items: center;
                            justify-content: center;
                            width: 38px;
                            height: 38px;
                            border-radius: 9px !important;
                            background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
                            border: 1px solid rgba(16,185,129,0.4);
                            margin: 0 3px !important;
                            padding: 0 !important;
                            color: #ffffff !important;
                            transition: all .2s ease;
                            text-decoration: none !important;
                            box-shadow: 0 3px 8px rgba(16,185,129,0.35);
                        }
                        .gp-complaint-btn:hover {
                            transform: translateY(-1px);
                            box-shadow: 0 5px 14px rgba(16,185,129,0.5);
                            color: #fff !important;
                        }
                        .gp-complaint-btn i { font-size: 18px !important; }

                        /* ====== My Transactions Icon (Laptop: Green, Mobile: Hidden) ====== */
                        .gp-txn-btn {
                            position: relative;
                            display: inline-flex !important;
                            align-items: center;
                            justify-content: center;
                            width: 38px;
                            height: 38px;
                            border-radius: 9px !important;
                            background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
                            border: 1px solid rgba(16,185,129,0.4);
                            margin: 0 3px !important;
                            padding: 0 !important;
                            color: #ffffff !important;
                            transition: all .2s ease;
                            text-decoration: none !important;
                            box-shadow: 0 3px 8px rgba(16,185,129,0.35);
                        }
                        .gp-txn-btn:hover {
                            transform: translateY(-1px);
                            box-shadow: 0 5px 14px rgba(16,185,129,0.5);
                            color: #fff !important;
                        }
                        .gp-txn-btn i { font-size: 18px !important; }

                        /* ====== Refer & Earn Icon (Laptop: Purple Gift, Mobile: Hidden) ====== */
                        .gp-refer-btn {
                            position: relative;
                            display: inline-flex !important;
                            align-items: center;
                            justify-content: center;
                            width: 38px;
                            height: 38px;
                            border-radius: 9px !important;
                            background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 100%) !important;
                            border: 1px solid rgba(139,92,246,0.4);
                            margin: 0 3px !important;
                            padding: 0 !important;
                            color: #ffffff !important;
                            transition: all .2s ease;
                            text-decoration: none !important;
                            box-shadow: 0 3px 8px rgba(139,92,246,0.35);
                        }
                        .gp-refer-btn:hover {
                            transform: translateY(-1px);
                            box-shadow: 0 5px 14px rgba(139,92,246,0.5);
                            color: #fff !important;
                        }
                        .gp-refer-btn i { font-size: 18px !important; }

                        /* ====== WhatsApp Icon ====== */
                        .gp-whatsapp-btn {
                            position: relative;
                            display: inline-flex !important;
                            align-items: center;
                            justify-content: center;
                            width: 38px;
                            height: 38px;
                            border-radius: 9px !important;
                            background: linear-gradient(135deg, #25D366 0%, #128C7E 100%) !important;
                            border: 1px solid rgba(37,211,102,0.4);
                            margin: 0 3px !important;
                            padding: 0 !important;
                            color: #ffffff !important;
                            transition: all .2s ease;
                            text-decoration: none !important;
                            box-shadow: 0 3px 8px rgba(37,211,102,0.35);
                        }
                        .gp-whatsapp-btn:hover {
                            transform: translateY(-1px);
                            box-shadow: 0 5px 14px rgba(37,211,102,0.5);
                            color: #fff !important;
                        }
                        .gp-whatsapp-btn i { font-size: 19px !important; }
                        @media (max-width: 480px) {
                            .gp-whatsapp-btn { width: 34px; height: 34px; margin: 0 2px !important; }
                            .gp-whatsapp-btn i { font-size: 17px !important; }
                        }

                        /* ====== Complaint Notification Badge ====== */
                        @keyframes complaintBadgePulse {
                            0%, 100% { transform: scale(1); box-shadow: 0 3px 8px rgba(255,75,43,0.5),inset 0 1px 0 rgba(255,255,255,0.25); }
                            50% { transform: scale(1.12); box-shadow: 0 5px 14px rgba(255,75,43,0.65),inset 0 1px 0 rgba(255,255,255,0.3); }
                        }
                        .notif-badge {
                            position: absolute;
                            top: -6px; right: -8px;
                            min-width: 20px; height: 20px;
                            padding: 0 5px;
                            color: #fff;
                            border-radius: 999px;
                            font-size: 10px;
                            font-weight: 800;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            border: 1.5px solid #fff;
                            letter-spacing: 0.1px;
                            line-height: 1;
                            animation: complaintBadgePulse 2s ease-in-out infinite;
                            z-index: 5;
                        }
                        .notif-badge-pill {
                            padding: 2px 9px;
                            color: #fff;
                            border-radius: 999px;
                            font-size: 11px;
                            font-weight: 700;
                            letter-spacing: 0.2px;
                            line-height: 1.4;
                            animation: complaintBadgePulse 2s ease-in-out infinite;
                            flex-shrink: 0;
                        }
                        .nb-pdf      { background: linear-gradient(135deg,#43e97b 0%,#38f9d7 100%); box-shadow: 0 3px 10px rgba(67,233,123,0.5),inset 0 1px 0 rgba(255,255,255,0.25); }
                        .nb-newapply { background: linear-gradient(135deg,#30cfd0 0%,#330867 100%); box-shadow: 0 3px 10px rgba(48,207,208,0.5),inset 0 1px 0 rgba(255,255,255,0.25); }
                        .nb-newfam   { background: linear-gradient(135deg,#667eea 0%,#764ba2 100%); box-shadow: 0 3px 10px rgba(102,126,234,0.5),inset 0 1px 0 rgba(255,255,255,0.25); }
                        .nb-pravasan { background: linear-gradient(135deg,#f6d365 0%,#fda085 100%); box-shadow: 0 3px 10px rgba(253,160,133,0.5),inset 0 1px 0 rgba(255,255,255,0.25); }
                        .nb-merge    { background: linear-gradient(135deg,#f093fb 0%,#f5576c 100%); box-shadow: 0 3px 10px rgba(245,87,108,0.5),inset 0 1px 0 rgba(255,255,255,0.25); }
                        .nb-recover  { background: linear-gradient(135deg,#ff9a9e 0%,#fecfef 100%); box-shadow: 0 3px 10px rgba(255,154,158,0.5),inset 0 1px 0 rgba(255,255,255,0.25); color:#a61e4a !important; }
                        .nb-memdel   { background: linear-gradient(135deg,#eb3349 0%,#f45c43 100%); box-shadow: 0 3px 10px rgba(235,51,73,0.5),inset 0 1px 0 rgba(255,255,255,0.25); }
                        .nb-memxfer  { background: linear-gradient(135deg,#00c6ff 0%,#0072ff 100%); box-shadow: 0 3px 10px rgba(0,114,255,0.5),inset 0 1px 0 rgba(255,255,255,0.25); }
                        .nb-divorce  { background: linear-gradient(135deg,#fa709a 0%,#fee140 100%); box-shadow: 0 3px 10px rgba(250,112,154,0.5),inset 0 1px 0 rgba(255,255,255,0.25); }
                        .nb-kyc      { background: linear-gradient(135deg,#a8edea 0%,#fed6e3 100%); box-shadow: 0 3px 10px rgba(168,237,234,0.5),inset 0 1px 0 rgba(255,255,255,0.25); color:#335 !important; }
                        .nb-tec      { background: linear-gradient(135deg,#ff6a00 0%,#ee0979 100%); box-shadow: 0 3px 10px rgba(238,9,121,0.5),inset 0 1px 0 rgba(255,255,255,0.25); }
                        .nb-ll       { background: linear-gradient(135deg,#0ba360 0%,#3cba92 100%); box-shadow: 0 3px 10px rgba(12,163,96,0.5),inset 0 1px 0 rgba(255,255,255,0.25); }
                    </style>

                    <span class="gp-header-date d-none d-sm-inline-flex">
                        <i class="fas fa-calendar-day"></i>
                        <span id="time"></span>
                    </span>
                    <script>
                        var timeDisplay = document.getElementById("time");
                        function refreshTime() {
                            let now = new Date();
                            let day = String(now.getDate()).padStart(2, '0');
                            let month = String(now.getMonth() + 1).padStart(2, '0');
                            let year = now.getFullYear();
                            let hours = now.getHours();
                            let minutes = String(now.getMinutes()).padStart(2, '0');
                            let seconds = String(now.getSeconds()).padStart(2, '0');
                            let ampm = hours >= 12 ? 'PM' : 'AM';
                            hours = hours % 12;
                            hours = hours ? hours : 12;
                            timeDisplay.innerHTML = `${day}-${month}-${year}  ${hours}:${minutes}:${seconds} ${ampm}`;
                        }
                        refreshTime();
                        setInterval(refreshTime, 1000);
                    </script>

                    <a href="wallet.php" class="gp-wallet-chip<?php echo $__walletClassExtra; ?>" title="<?php echo htmlspecialchars($__walletTitle); ?>">
                        <i class="fas fa-wallet"></i>
                        ₹<?php echo $__balanceFormatted; ?>
                        <?php echo $__lowBadgeHtml; ?>
                    </a>

                    <!-- Add Balance Button (Laptop Only - Most Attractive) -->
                    <a href="https://goodprintportal.in/admin/wallet.php" class="gp-addbalance-btn d-none d-lg-inline-flex" title="Click to Add Money to Wallet">
                        <span>ADD BALANCE</span>
                    </a>

                    <a href="my_wallet_record.php" class="gp-daily-hit-chip" title="Aaj ke successful API hits (Debit Transactions)">
                        <i class="fas fa-bolt"></i>
                        <?php echo $__hitsFormatted; ?> Hits
                    </a>

                    <!-- Today's Spend Chip (Laptop Only) -->
                    <a href="my_wallet_record.php" class="gp-today-spend-chip d-none d-lg-inline-flex" title="Aaj ka total spend (successful debits)">
                        Today's Spend <b>₹<?php echo number_format($__todaySpend, 0); ?></b>
                    </a>

                    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">


                    <div class="top-menu ms-auto">
                        <ul class="navbar-nav align-items-center">

                            <!-- [DISABLED PERMANENTLY] Theme Toggle → user ne off karne ko kaha, keep original white forever.
                            <li class="nav-item d-flex" style="position:relative !important; z-index:999999 !important; pointer-events:auto !important;">
                                <button type="button"
                                        id="gpThemeToggle"
                                        class="gp-theme-toggle"
                                        style="pointer-events:auto !important; z-index:999999 !important; display:inline-flex !important;"
                                        title="Click karo → Dark/Light toggle"
                                        onclick='(function(btn){
                                            try {
                                                var root = document.documentElement;
                                                var body = document.body;
                                                var ico = document.getElementById ? document.getElementById("gpThemeIcon") : (btn ? btn.querySelector("i") : null);
                                                var NOW_DARK = false;
                                                try { NOW_DARK = (root.getAttribute("data-theme") === "dark"); } catch(e){}

                                                if (NOW_DARK) {
                                                    // ☀️ LIGHT — attribute HATAO → original white CSS 100%
                                                    try { root.removeAttribute("data-theme"); } catch(_){}
                                                    try { body.removeAttribute("data-theme"); } catch(_){}
                                                    try { root.removeAttribute("data-gp-icon"); } catch(_){}
                                                    try { root.removeAttribute("style"); } catch(_){}
                                                    try { body.removeAttribute("style"); } catch(_){}
                                                    try {
                                                        body.classList.remove("gp-dark-mode-active");
                                                        body.classList.add("gp-light-mode-active");
                                                    } catch(_){}
                                                    try { localStorage.removeItem("gp_theme"); } catch(_){}
                                                    if (ico) ico.className = "fa-solid fa-sun";
                                                    if (btn) btn.setAttribute("title", "Dark mode me jaayein 🌙");
                                                    if (window.console) console.log("[THEME] → LIGHT (original white)");
                                                } else {
                                                    // 🌙 DARK — lagao data-theme=dark
                                                    try { root.setAttribute("data-theme","dark"); } catch(_){}
                                                    try { body.setAttribute("data-theme","dark"); } catch(_){}
                                                    try { root.setAttribute("data-gp-icon","moon"); } catch(_){}
                                                    try {
                                                        body.classList.remove("gp-light-mode-active");
                                                        body.classList.add("gp-dark-mode-active");
                                                    } catch(_){}
                                                    try { localStorage.setItem("gp_theme","dark"); } catch(_){}
                                                    if (ico) ico.className = "fa-solid fa-moon";
                                                    if (btn) btn.setAttribute("title", "Light mode me jaayein ☀️");
                                                    if (window.console) console.log("[THEME] → DARK");
                                                }
                                                // Click ho gaya — 20ms baad force icon sync (extra backup)
                                                setTimeout(function(){
                                                    try {
                                                        var r = document.documentElement;
                                                        var i2 = document.getElementById("gpThemeIcon");
                                                        var dark = false;
                                                        try { dark = (r.getAttribute("data-theme") === "dark"); } catch(_){}
                                                        if (i2) i2.className = dark ? "fa-solid fa-moon" : "fa-solid fa-sun";
                                                        var b2 = document.getElementById("gpThemeToggle");
                                                        if (b2) b2.setAttribute("title", dark ? "Light mode me jaayein ☀️" : "Dark mode me jaayein 🌙");
                                                    } catch(_){}
                                                }, 20);
                                            } catch (bigErr) {
                                                alert("Theme toggle error: " + (bigErr && bigErr.message ? bigErr.message : bigErr));
                                            }
                                        })(this);
                                        return true;'>
                                    <i class="fa-solid fa-sun" id="gpThemeIcon" style="pointer-events:none !important;"></i>
                                </button>
                            </li>
                            -->

<?php if ($_gpRefEnabled): ?>
                            <li class="nav-item d-none d-md-flex">
                                <a class="gp-refer-btn" href="referral.php" title="Refer & Earn - Bonus on Deposit">
                                    <i class="fa-solid fa-gift"></i>
                                </a>
                            </li>
<?php endif; ?>

<?php if (!empty($__whatsappNumber)): ?>
                            <li class="nav-item">
                                <a class="gp-whatsapp-btn" href="https://wa.me/<?= htmlspecialchars($__whatsappNumber) ?>?text=<?= urlencode('Regarding goodprintportal') ?>" target="_blank" rel="noopener noreferrer" title="WhatsApp Support (<?= htmlspecialchars($__whatsappDisplay) ?>)">
                                    <i class="fab fa-whatsapp"></i>
                                </a>
                            </li>
<?php endif; ?>

                            <li class="nav-item">
                                <a class="gp-txn-btn" href="my_wallet_record.php" title="My Wallet / Transaction History">
                                    <i class="fas fa-receipt"></i>
                                </a>
                            </li>

                            <li class="nav-item">
                                <a class="gp-complaint-btn" href="complaint_request.php" title="Raise Complaint / Customer Care">
                                    <i class="fas fa-headset"></i>
                                </a>
                            </li>

<?php
// Calculate REAL pending notification count:
$__totalPendingNotify = 0;
$__trickPending = 0;
$__userComplaintBadge = $__userUnreadComplaintCount > 0 ? $__userUnreadComplaintCount : 0;
if (function_exists('trick_pending_count') && isset($ahk_conn) && $ahk_conn) {
    global $ahk_conn, $udata;
    $role = isset($udata) ? strtolower(trim((string)($udata['role'] ?? $udata['type'] ?? ''))) : '';
    $__isAdmin = ($role === 'admin' || $role === 'super admin' || $role === 'super_admin' || !empty($udata['is_admin']));
    if ($__isAdmin) {
        $__trickPending = trick_pending_count($ahk_conn);
        $__totalPendingNotify += $__trickPending;
    }
}
// Complaint remark updates ALWAYS added to bell badge (for logged-in user)
$__totalPendingNotify += $__userComplaintBadge;
$__badgeCount = $__totalPendingNotify > 0 ? $__totalPendingNotify : '';
?>
                            <li class="nav-item dropdown dropdown-large" id="gpCmpNotifyWrap">
                                <a class="nav-link dropdown-toggle dropdown-toggle-nocaret position-relative gp-bell-wrap gp-top-icon-btn" href="#"
                                    role="button" data-bs-toggle="dropdown" aria-expanded="false" id="gpCmpBellBtn">
                                    <?php if ($__badgeCount !== ''): ?><span class="alert-count gp-bell-badge" id="gpBellBadge"><?= (int)$__badgeCount ?></span><?php endif; ?>
                                    <i class='bx bx-bell<?= $__userComplaintBadge > 0 ? ' gp-bell-red' : '' ?>'></i>
                                </a>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a href="javascript:;">
                                        <div class="msg-header">
                                            <p class="msg-header-title">Notifications</p>
                                            <p class="msg-header-clear ms-auto" id="gpCmpClearAll" style="cursor:pointer;<?= $__userComplaintBadge > 0 ? 'color:#d63384;font-weight:700;' : '' ?>">
                                                Marks all as read
                                            </p>
                                        </div>
                                    </a>
                                    <div class="header-notifications-list" id="gpNotifyList">
                                        <?php foreach ($__userUnreadComplaints as $__uc): ?>
                                        <a class="dropdown-item" href="complaint_request_list.php" data-cmpid="<?= (int)$__uc['complaint_id'] ?>">
                                            <div class="d-flex align-items-center">
                                                <div class="notify bg-light-danger text-danger gp-cmp-notify-icon">
                                                    <i class='bx bx-message-square-x'></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="msg-name">
                                                        📢 Complaint: <?= htmlspecialchars($__uc['complaint_type']) ?>
                                                        <span class="msg-time float-end">
                                                            <?php
                                                                $__dt = $__uc['remark_updated_at'] ? $__uc['remark_updated_at'] : ($__uc['date_time'] ?? '');
                                                                if ($__dt) echo date('h:i A', strtotime($__dt));
                                                            ?>
                                                        </span>
                                                    </h6>
                                                    <p class="msg-info">
                                                        <b>Admin Reply:</b> <?= htmlspecialchars(mb_substr($__uc['remark'],0,120)) ?><?= strlen($__uc['remark'])>120?'…':'' ?>
                                                    </p>
                                                    <small class="text-danger fw-bold"><i class='bx bx-dot-rounded'></i> Unread — Click to view</small>
                                                </div>
                                            </div>
                                        </a>
                                        <?php endforeach; ?>
                                        <?php if ($__trickPending > 0): ?>
                                        <a class="dropdown-item" href="trick_buy_admin.php">
                                            <div class="d-flex align-items-center">
                                                <div class="notify bg-light-warning text-warning"><i
                                                        class="bx bx-magic-wand"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="msg-name">TRICK Orders Pending <span
                                                            class="msg-time float-end">Just now</span></h6>
                                                    <p class="msg-info">You have <b><?= (int)$__trickPending ?></b> new TRICK Buy request(s) waiting admin approval.</p>
                                                </div>
                                            </div>
                                        </a>
                                        <?php endif; ?>
                                        <a class="dropdown-item" href="javascript:;">
                                            <div class="d-flex align-items-center">
                                                <div class="notify bg-light-primary text-primary"><i
                                                        class="bx bx-group"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="msg-name">New Customers<span
                                                            class="msg-time float-end">14 Sec
                                                            ago</span></h6>
                                                    <p class="msg-info">5 new user registered</p>
                                                </div>
                                            </div>
                                        </a>
                                        <a class="dropdown-item" href="javascript:;">
                                            <div class="d-flex align-items-center">
                                                <div class="notify bg-light-danger text-danger"><i
                                                        class="bx bx-cart-alt"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="msg-name">New Orders <span class="msg-time float-end">2
                                                            min
                                                            ago</span></h6>
                                                    <p class="msg-info">You have recived new orders</p>
                                                </div>
                                            </div>
                                        </a>
                                        <a class="dropdown-item" href="javascript:;">
                                            <div class="d-flex align-items-center">
                                                <div class="notify bg-light-success text-success"><i
                                                        class="bx bx-file"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="msg-name">24 PDF File<span class="msg-time float-end">19
                                                            min
                                                            ago</span></h6>
                                                    <p class="msg-info">The pdf files generated</p>
                                                </div>
                                            </div>
                                        </a>
                                        <a class="dropdown-item" href="javascript:;">
                                            <div class="d-flex align-items-center">
                                                <div class="notify bg-light-warning text-warning"><i
                                                        class="bx bx-send"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="msg-name">Time Response <span
                                                            class="msg-time float-end">28 min
                                                            ago</span></h6>
                                                    <p class="msg-info">5.1 min avarage time response</p>
                                                </div>
                                            </div>
                                        </a>
                                        <a class="dropdown-item" href="javascript:;">
                                            <div class="d-flex align-items-center">
                                                <div class="notify bg-light-info text-info"><i
                                                        class="bx bx-home-circle"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="msg-name">New Product Approved <span
                                                            class="msg-time float-end">2 hrs ago</span></h6>
                                                    <p class="msg-info">Your new product has approved</p>
                                                </div>
                                            </div>
                                        </a>
                                        <a class="dropdown-item" href="javascript:;">
                                            <div class="d-flex align-items-center">
                                                <div class="notify bg-light-danger text-danger"><i
                                                        class="bx bx-message-detail"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="msg-name">New Comments <span class="msg-time float-end">4
                                                            hrs
                                                            ago</span></h6>
                                                    <p class="msg-info">New customer comments recived</p>
                                                </div>
                                            </div>
                                        </a>
                                        <a class="dropdown-item" href="javascript:;">
                                            <div class="d-flex align-items-center">
                                                <div class="notify bg-light-success text-success"><i
                                                        class='bx bx-check-square'></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="msg-name">Your item is shipped <span
                                                            class="msg-time float-end">5 hrs
                                                            ago</span></h6>
                                                    <p class="msg-info">Successfully shipped your item</p>
                                                </div>
                                            </div>
                                        </a>
                                        <a class="dropdown-item" href="javascript:;">
                                            <div class="d-flex align-items-center">
                                                <div class="notify bg-light-primary text-primary"><i
                                                        class='bx bx-user-pin'></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="msg-name">New 24 authors<span
                                                            class="msg-time float-end">1 day
                                                            ago</span></h6>
                                                    <p class="msg-info">24 new authors joined last week</p>
                                                </div>
                                            </div>
                                        </a>
                                        <a class="dropdown-item" href="javascript:;">
                                            <div class="d-flex align-items-center">
                                                <div class="notify bg-light-warning text-warning"><i
                                                        class='bx bx-door-open'></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="msg-name">Defense Alerts <span
                                                            class="msg-time float-end">2 weeks
                                                            ago</span></h6>
                                                    <p class="msg-info">45% less alerts last 4 weeks</p>
                                                </div>
                                            </div>
                                        </a>
                                    </div>
                                    <a href="<?= $__userComplaintBadge > 0 ? 'complaint_request_list.php' : 'javascript:;' ?>"<?= $__userComplaintBadge > 0 ? ' id="gpViewAllCmp"' : '' ?>>
                                        <div class="text-center msg-footer gp-msg-footer">
                                            <?php if ($__userComplaintBadge > 0): ?>
                                                <span style="color:#dc3545;font-weight:700;">📢 View <?= (int)$__userComplaintBadge ?> Complaint Replies</span>
                                            <?php else: ?>
                                                View All Notifications
                                            <?php endif; ?>
                                        </div>
                                    </a>
                                </div>
                            </li>

                            <li class="nav-item dropdown dropdown-large">
                                <?php
                                if (checkAdmin($udata['type']) == true) {
                                    ?>
                                    <a class="nav-link dropdown-toggle dropdown-toggle-nocaret position-relative gp-top-icon-btn" href="#"
                                        role="button" data-bs-toggle="dropdown" aria-expanded="false"> <span
                                            class="alert-count"><?= (isset($query_noforadmin) ? (int)$query_noforadmin : 0); ?></span>
                                        <i class='bx bx-comment'></i>
                                    </a>

                                    <?php
                                } else {
                                    ?>
                                    <a class="nav-link dropdown-toggle dropdown-toggle-nocaret position-relative gp-top-icon-btn" href="#"
                                        role="button" data-bs-toggle="dropdown" aria-expanded="false"> <span
                                            class="alert-count"><?= (isset($query_foruser) ? (int)$query_foruser : 0); ?></span>
                                        <i class='bx bx-comment'></i>
                                    </a>
                                    <?php
                                }
                                ?>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a href="javascript:;">
                                        <div class="msg-header">
                                            <p class="msg-header-title">Messages</p>
                                            <p class="msg-header-clear ms-auto">Marks all as read</p>
                                        </div>
                                    </a>
                                    <div class="header-message-list">
                                        <?php
                                        $res = mysqli_query($ahk_conn, "SELECT * FROM contact  WHERE email='" . $udata['email'] . "'");
                                        if (mysqli_num_rows($res) > 0) {
                                            $x = 0;
                                            while ($mdata = mysqli_fetch_assoc($res)) {
                                                $x++;
                                                ?>
                                                <div class="dropdown-item" href="javascript:;">
                                                    <div class="d-flex align-items-center">
                                                        <p class="msg-info"><?= $mdata['reply']; ?></p>
                                                        <div class="flex-grow-1">
                                                            <h6 class="msg-name" <?php $mdata['mess']; ?> <span
                                                                class="msg-time float-end"><?php $mdata['date']; ?> </span></h6>
                                                            <p class="msg-info"><?= $mdata['reply']; ?></p>
                                                        </div>
                                                    </div>
                                                </div>

                                                <?php

                                            }
                                        }
                                        ?>


                                    </div>
                                    <a href="javascript:;">
                                        <div class="text-center msg-footer">View All Messages</div>
                                    </a>
                                </div>
                            </li>
                        </ul>
                    </div>
                    <?php
                    // ============== Premium User Avatar (Initials + Gradient) ==============
                    $__userName  = isset($udata['name']) ? trim((string)$udata['name']) : 'User';
                    $__userPhone = isset($udata['phone']) ? trim((string)$udata['phone']) : '';
                    $__userRole  = isset($udata['type']) ? trim((string)$udata['type']) : 'user';
                    $__initials  = '';
                    $__nameParts = preg_split('/\s+/', $__userName, -1, PREG_SPLIT_NO_EMPTY);
                    if (!empty($__nameParts)) {
                        $__initials .= mb_strtoupper(mb_substr($__nameParts[0], 0, 1));
                        if (count($__nameParts) > 1) {
                            $__initials .= mb_strtoupper(mb_substr($__nameParts[count($__nameParts)-1], 0, 1));
                        }
                    }
                    if ($__initials === '') $__initials = mb_strtoupper(mb_substr(($__userPhone ?: 'U'), 0, 1));
                    // Hash-based gradient pick — consistent color per user
                    $__palettes = [
                        ['#6366f1','#8b5cf6'], ['#ec4899','#f43f5e'], ['#0ea5e9','#6366f1'],
                        ['#10b981','#059669'], ['#f59e0b','#ef4444'], ['#06b6d4','#0891b2'],
                        ['#a855f7','#ec4899'], ['#84cc16','#16a34a'], ['#3b82f6','#1d4ed8'],
                        ['#f97316','#dc2626'], ['#14b8a6','#0ea5e9'], ['#e11d48','#9333ea'],
                    ];
                    $__seed = abs(crc32(mb_strtolower($__userName . $__userPhone)));
                    $__pal  = $__palettes[$__seed % count($__palettes)];
                    $__avatarImgPath = __DIR__ . '/../../admin/uploads/Profile-Male-PNG.png';
                    $__avatarImgExists = file_exists($__avatarImgPath);
                    $__roleDisp = ucwords(str_replace('_', ' ', $__userRole));
                    ?>
                    <div class="user-box dropdown">
                        <a class="d-flex align-items-center nav-link dropdown-toggle dropdown-toggle-nocaret" href="#"
                            role="button" data-bs-toggle="dropdown" aria-expanded="false" style="gap:10px;">
                            <?php if ($__avatarImgExists): ?>
                                <img src="../admin/uploads/Profile-Male-PNG.png" class="user-img gp-user-img-fallback"
                                     style="width:44px;height:44px;border-radius:50%;object-fit:cover;border:2px solid #e5e7eb;box-shadow:0 2px 6px rgba(0,0,0,.10);"
                                     alt="<?= htmlspecialchars($__userName) ?>"
                                     onerror="this.style.display='none';var next=this.nextElementSibling;if(next)next.style.display='inline-flex';">
                            <?php endif; ?>
                            <span class="gp-premium-avatar" aria-hidden="true" style="<?= $__avatarImgExists ? 'display:none;' : '' ?>
                                display:inline-flex;align-items:center;justify-content:center;
                                width:44px;height:44px;border-radius:50%;flex-shrink:0;
                                background: linear-gradient(135deg, <?= $__pal[0] ?> 0%, <?= $__pal[1] ?> 100%);
                                color:#fff;font-family:'Inter','Nunito',sans-serif;font-weight:800;
                                font-size:15px;letter-spacing:.5px;
                                box-shadow:0 4px 10px rgba(0,0,0,.14), 0 0 0 2px #ffffff inset, 0 0 0 3px <?= $__pal[0] ?>33;">
                                <?= htmlspecialchars($__initials) ?>
                            </span>
                            <div class="user-info ps-2">
                                <p class="user-name mb-0" style="font-weight:700;line-height:1.2;color:#111827;">
                                    <?php echo htmlspecialchars($__userName); ?>
                                </p>
                                <p class="designattion mb-0" style="font-size:11.5px;color:#6b7280;font-weight:600;letter-spacing:.2px;line-height:1.3;">
                                    <span style="display:inline-flex;align-items:center;gap:4px;">
                                        <span style="display:inline-block;width:6px;height:6px;border-radius:50%;
                                              background: linear-gradient(135deg,<?= $__pal[0] ?>,<?= $__pal[1] ?>);
                                              box-shadow:0 0 0 2px <?= $__pal[0] ?>22;"></span>
                                        <?= htmlspecialchars($__roleDisp) ?>
                                    </span>
                                </p>
                            </div>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i
                                        class="bx bx-user"></i><span>Profile</span></a>
                            </li>

                            <li>
                                <div class="dropdown-divider mb-0"></div>
                            </li>
                            <li><a class="dropdown-item" href="../includes/logout.php"><i
                                        class='bx bx-log-out-circle'></i><span>LOGOUT</span></a>
                            </li>
                        </ul>
                    </div>
                </nav>
            </div>
        </header>

        <?php
        // ============================================================
        // 🔥 LOGIN POPUP — EXACT OLD SWAL PATTERN (jo screenshot me dikha rahe ho)
        // Purana Swal (screenshot) : localStorage[alertShown] nahi hai → show + set
        // Hum naya popup            : localStorage[gp_login_popup_seen_{NONCE}] nahi hai → show + set
        // {NONCE} = har login pe unique random token (auth.php DB me dalte)
        // Isliye HAR LOGIN pe KEY hamesha NEW hoti hai → HAR LOGIN pe popup 100% dikhega.
        // ============================================================
        // ============================================================
        // 🔥🔥🔥 FINAL 3-LAYER TRIGGER (KOI EK BHI CHAL JAYEGA, 100% SURE):
        // LAYER 1: URL PARAM ?welcome=1 — attached directly by auth.php / register.php in JS redirect URL.
        //          KABHI FAIL NAHI HOTA. HTTP standard. No header/cookie/session dependency. PRIMARY.
        // LAYER 2: SESSION gp_popup_show_1 = true.
        // LAYER 3: COOKIE  gp_popup_show_1 = '1'.
        // RULES:
        //   - Sirf admin/index.php (MARK 1, baaki pages bilkul off)
        //   - Koi ek bhi layer true → POPUP DIKHAO + SAB 3 LAYERS ko turant unset/delete kar do.
        //   - Session me "once per login" flag set kar do (welcome param dubara paste na ho page refresh me na aa jaye baar baar)
        // ============================================================
        $gpPopup         = $GLOBALS['gpPopupSettings'] ?? null;
        $gpShowPopup     = false;
        $gpCardsFiltered = [];

        $gpScriptName = basename($_SERVER['SCRIPT_NAME'] ?? '');
        $gpIsDashboardPage = ($gpScriptName === 'index.php');
        $__uidP = (int)(($udata['id'] ?? 0));

        // Once-per-login: is login session me popup EK BAAR already dikh chuka hai?
        // (logout = session_destroy, so auto reset on new login)
        $gpOnceKey = 'gp_once_per_login_' . (int)$__uidP;
        $gpAlreadyShownOnce = !empty($_SESSION[$gpOnceKey]);

        // 3 LAYERS (welcome=1 IGNORES already-shown-once gate only if it's a NEW login. else welcome paste in URL baar baar → skip using once-key)
        $gpWelcome = (isset($_GET['welcome']) && $_GET['welcome'] === '1');
        $gpCookie  = (isset($_COOKIE['gp_popup_show_1'])  && $_COOKIE['gp_popup_show_1']  === '1');
        $gpSession = (isset($_SESSION['gp_popup_show_1']) && $_SESSION['gp_popup_show_1'] === true);

        $gpTriggerOk = ($gpWelcome || $gpCookie || $gpSession) && !$gpAlreadyShownOnce;

        if ($gpIsDashboardPage && $gpTriggerOk && $gpPopup && !empty($udata) && $__uidP > 0) {
            $masterEnabled = (int)($gpPopup['enabled'] ?? 0);
            $hasUpdatedAt  = !empty($gpPopup['updated_at']);
            if (!$hasUpdatedAt && $masterEnabled === 0) $masterEnabled = 1;

            if ($masterEnabled) {
                $gpCards = $gpPopup['cards'];
                foreach ($gpCards as $__c) if (!empty($__c['enabled'])) $gpCardsFiltered[] = $__c;
                if (count($gpCardsFiltered) > 0) {
                    $gpShowPopup = true;

                    // 🟢 CLEAR ALL 3 LAYERS (taaki refresh/page nav dubara na aaye)
                    // (A) URL welcome param strip karo via JS pushState (no refresh, URL clean)
                    // (B) Session flag unset
                    unset($_SESSION['gp_popup_show_1']);
                    // (C) Cookie unset + expire
                    unset($_COOKIE['gp_popup_show_1']);
                    @setcookie('gp_popup_show_1', '', time() - 86400, '/');

                    // 🟢 LOCK: is login me ab KABHI popup nahi aayega (welcome URL me paste bhi karoge to bhi nahi)
                    $_SESSION[$gpOnceKey] = true;

                    // Saath hi purane conflict keys bhi clean kar do
                    unset($_SESSION['gp_popup_shown_once_login_' . $__uidP]);
                    unset($_SESSION['gp_popup_force_show_next']);
                    unset($_SESSION['gp_seen_nonce_'            . $__uidP]);
                    unset($_SESSION['gp_popup_event_shown_'     . $__uidP]);
                }
            }
        }
        if ($gpShowPopup):
        ?>

        <!-- ================= PREMIUM LOGIN POPUP (single render, no duplicates) ================= -->
        <style>
            #gpLoginPopupOverlay {
                position: fixed !important; inset: 0 !important;
                z-index: 2147483646 !important; /* max int-1 — highest possible except 2147483647 */
                background: rgba(0,0,0,.58) !important;
                backdrop-filter: blur(6px);
                -webkit-backdrop-filter: blur(6px);
                display: none;
                align-items: center !important;
                justify-content: center !important;
                padding: 20px 14px !important;
                animation: gpPopFadeIn .28s ease both;
            }
            @keyframes gpPopFadeIn { from { opacity: 0; } to { opacity: 1; } }
            #gpLoginPopupModal {
                background: #ffffff;
                border-radius: 26px;
                width: 100%;
                max-width: 460px;
                max-height: 88vh;
                overflow-y: auto;
                overflow-x: hidden;
                padding: 26px 22px 24px;
                box-shadow:
                    0 40px 100px rgba(0,0,0,.42),
                    0 0 0 1px rgba(255,255,255,.6),
                    inset 0 1px 0 rgba(255,255,255,.9);
                animation: gpPopRise .5s cubic-bezier(.2,.9,.3,1.25) both;
                position: relative;
            }
            @keyframes gpPopRise {
                0%   { transform: translateY(50px) scale(.92); opacity: 0; }
                100% { transform: none; opacity: 1; }
            }
            .gp-popup-close {
                position: absolute; top: 12px; right: 14px;
                width: 34px; height: 34px;
                background: rgba(0,0,0,.06);
                border: none;
                border-radius: 50%;
                font-size: 22px;
                font-weight: 700;
                cursor: pointer;
                color: #6b7280;
                transition: all .18s ease;
                display: flex; align-items: center; justify-content: center;
                line-height: 1;
                z-index: 10;
                box-shadow: 0 2px 6px rgba(0,0,0,.08);
            }
            .gp-popup-close:hover {
                background: linear-gradient(135deg,#ef4444,#dc2626);
                color: #fff;
                transform: rotate(90deg) scale(1.1);
                box-shadow: 0 6px 16px rgba(239,68,68,.4);
            }
            .gp-popup-title {
                font-size: 22px;
                font-weight: 800;
                text-align: center;
                padding: 0 30px 18px;
                margin: 0 0 20px;
                background: linear-gradient(135deg,#4338ca 0%,#7e22ce 45%,#be185d 100%);
                -webkit-background-clip: text;
                background-clip: text;
                color: transparent;
                letter-spacing: .3px;
                line-height: 1.25;
                border-bottom: 2.5px dashed #e5e7eb;
            }
            .gp-popup-cards { display: flex; flex-direction: column; gap: 16px; }
            .gp-popup-card {
                padding: 18px 20px 16px;
                border-radius: 18px;
                border: 3px dashed;
                box-shadow: 0 8px 24px rgba(0,0,0,.1);
                transition: all .25s ease;
                position: relative;
                overflow: hidden;
            }
            .gp-popup-card::before {
                content: "";
                position: absolute;
                inset: 0;
                background: linear-gradient(135deg, rgba(255,255,255,.25) 0%, transparent 50%);
                pointer-events: none;
            }
            .gp-popup-card:hover { transform: translateX(5px) scale(1.01); }
            .gp-popup-card-head {
                display: flex; align-items: center; gap: 12px; margin-bottom: 8px;
                position: relative; z-index: 1;
            }
            .gp-popup-card-head span {
                font-size: 28px;
                filter: drop-shadow(0 2px 4px rgba(0,0,0,.18));
            }
            .gp-popup-card-head strong {
                font-size: 19px;
                font-weight: 800;
                line-height: 1.25;
                letter-spacing: .2px;
            }
            .gp-popup-card p {
                margin: 0; font-size: 15px; font-weight: 500; line-height: 1.5;
                position: relative; z-index: 1;
            }
            .gp-popup-gotit {
                margin-top: 22px; text-align: center;
                position: relative; z-index: 1;
            }
            .gp-popup-gotit button {
                background: linear-gradient(135deg,#6366f1 0%,#8b5cf6 50%,#ec4899 100%);
                color: #fff; border: none; cursor: pointer;
                padding: 11px 44px; border-radius: 999px;
                font-weight: 800; letter-spacing: .5px; font-size: 15px;
                box-shadow: 0 8px 24px rgba(139,92,246,.45);
                transition: all .18s ease;
            }
            .gp-popup-gotit button:hover { transform: translateY(-2px); box-shadow: 0 12px 30px rgba(139,92,246,.58); }
            .gp-popup-gotit button:active { transform: translateY(0) scale(.98); }
            #gpLoginPopupModal::-webkit-scrollbar { width: 7px; }
            #gpLoginPopupModal::-webkit-scrollbar-thumb {
                background: linear-gradient(180deg,#c7d2fe,#f9a8d4);
                border-radius: 10px;
            }
            @media (max-width: 575px) {
                #gpLoginPopupModal { padding: 22px 16px 20px; border-radius: 20px; }
                .gp-popup-title { font-size: 19px; padding: 0 26px 14px; }
                .gp-popup-card { padding: 16px 16px 14px; border-radius: 16px; }
                .gp-popup-card-head strong { font-size: 17px; }
                .gp-popup-card-head span { font-size: 24px; }
                .gp-popup-card p { font-size: 14px; }
                .gp-popup-gotit button { padding: 10px 38px; font-size: 14px; }
            }
        </style>

        <div id="gpLoginPopupOverlay">
            <div id="gpLoginPopupModal" role="dialog" aria-modal="true" aria-label="Login Updates">
                <button type="button" class="gp-popup-close" id="gpLoginCloseBtn" aria-label="Close">&times;</button>
                <h3 class="gp-popup-title"><?= htmlspecialchars($gpPopup['title']) ?></h3>
                <div class="gp-popup-cards">
                    <?php foreach ($gpCardsFiltered as $__c): ?>
                    <div class="gp-popup-card"
                         style="background:<?= htmlspecialchars($__c['bg']) ?>;color:<?= htmlspecialchars($__c['tx']) ?>;border-color:<?= htmlspecialchars($__c['border']) ?>;">
                        <div class="gp-popup-card-head">
                            <span aria-hidden="true"><?= htmlspecialchars($__c['icon']) ?></span>
                            <strong><?= htmlspecialchars($__c['title']) ?></strong>
                        </div>
                        <p><?= nl2br(htmlspecialchars($__c['desc'])) ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="gp-popup-gotit">
                    <button type="button" id="gpLoginGotItBtn">Got it ✅ Start Exploring</button>
                </div>
            </div>
        </div>

        <script>
        // 🔥 POPUP JS: PHP ne pehle hi decide kar liya hai SHOW ya NO-SHOW (DB event + session check)
        // Agar yaha code aa raha hai → popup HTML render ho gaya → BAS OPEN kar do, KOI CHECK NAHI
        // Close karne par sirf DOM hide kar do, session/php already "shown" mark kar chuka hai
        (function() {
            try { localStorage.removeItem('alertShown'); } catch (e) {}

            var ovl = document.getElementById('gpLoginPopupOverlay');
            if (!ovl) return;

            function closePop() {
                try { ovl.style.animation = 'gpPopFadeIn .2s ease reverse both'; } catch(e){}
                setTimeout(function() {
                    try { ovl.style.setProperty('display', 'none', 'important'); } catch(e){ ovl.style.display='none'; }
                    document.body.style.overflow = '';
                }, 180);
            }
            function openPop() {
                try {
                    ovl.style.setProperty('display', 'flex', 'important');
                    ovl.style.setProperty('visibility', 'visible', 'important');
                    ovl.style.setProperty('opacity', '1', 'important');
                } catch(e) {
                    ovl.style.display = 'flex'; ovl.style.visibility = 'visible'; ovl.style.opacity = '1';
                }
                document.body.style.overflow = 'hidden';
                try { ovl.scrollTop = 0; } catch(e){}
            }

            try { document.getElementById('gpLoginCloseBtn').addEventListener('click', closePop); } catch(e){}
            try { document.getElementById('gpLoginGotItBtn').addEventListener('click', closePop); } catch(e){}
            try { ovl.addEventListener('click', function(e) { if (e.target === ovl) closePop(); }); } catch(e){}
            try {
                document.addEventListener('keydown', function(e) {
                    if (e && (e.key === 'Escape' || e.keyCode === 27)) closePop();
                });
            } catch(e){}

            // 🎯 PHP ne already confirm kiya hai show karna hai (render kiya hai HTML) — 100% OPEN kar do
            // 🛡️ SINGLE-OPEN GUARD: Do baar (DOMContentLoaded + load) fire hone par bhi EK HI BAAR khulega.
            var __gpPopupOpened = false;
            function doOpen() {
                if (__gpPopupOpened) return;  // already khula hai → skip
                __gpPopupOpened = true;
                openPop();
                // Clean URL: remove ?welcome=1 from address bar (no page reload) taaki refresh me dubara trigger na ho
                try {
                    if (window.history && window.history.replaceState) {
                        var u = new URL(window.location.href);
                        if (u.searchParams.get('welcome') === '1') {
                            u.searchParams.delete('welcome');
                            window.history.replaceState({}, document.title, u.toString());
                        }
                    }
                } catch(e){}
            }
            if (document.readyState === 'complete' || document.readyState === 'interactive') {
                setTimeout(doOpen, 350);
            } else {
                // Dono events pe try karo (koi pehle fire ho jaye wahi chalega, doosra guard se skip)
                window.addEventListener('DOMContentLoaded', function() { setTimeout(doOpen, 350); });
                window.addEventListener('load',            function() { setTimeout(doOpen, 250); });
            }
        })();
        </script>

        <?php endif; /* $gpShowPopup */ ?>

        <style>
            html,
            body {
                margin: 0;
                padding: 0;
            }

            #page-loader {
                position: fixed;
                top: 0;
                left: 0;
                width: 100vw;
                height: 100vh;
                background: #ffffff;
                z-index: 99999;
                display: none;
                align-items: center;
                justify-content: center;
            }

            #page-loader .spinner {
                border: 6px solid red;
                /* Red base */
                border-top: 6px solid blue;
                /* Blue rotating part */
                border-radius: 50%;
                width: 50px;
                height: 50px;
                animation: spin 1s linear infinite;
            }

            @keyframes spin {
                0% {
                    transform: rotate(0deg);
                }

                100% {
                    transform: rotate(360deg);
                }
            }
        </style>
        </head>

        <body>
            <div id="page-loader"
                style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0, 0, 0, 0.5); z-index: 99999; 
         display: flex; flex-direction: column; justify-content: center; align-items: center; color: #fff; font-family: Arial, sans-serif; font-size: 18px;">
                <div class="spinner" style="margin-bottom: 15px;"></div>
                <div id="loading-text">Loading Please Wait</div>
                <button id="cancel-loader-btn"
                    style="display:none; margin-top: 20px; padding: 8px 16px; background-color: red; color: white; border: none; border-radius: 5px; cursor: pointer;">
                    Cancel Loader
                </button>
            </div>

            <script>
                // Hide loader after full page load
                window.addEventListener("load", function () {
                    const loader = document.getElementById("page-loader");
                    if (loader) loader.style.display = "none";
                });

                // Show loader on form submit
                document.addEventListener("DOMContentLoaded", function () {
                    const cancelBtn = document.getElementById("cancel-loader-btn");
                    const loader = document.getElementById("page-loader");

                    // Show cancel button after 20 seconds if loader is still visible
                    setTimeout(function () {
                        if (loader && loader.style.display !== "none") {
                            cancelBtn.style.display = "block";
                        }
                    }, 20000); // 20 seconds

                    if (cancelBtn) {
                        cancelBtn.addEventListener("click", function () {
                            if (loader) loader.style.display = "none";
                        });
                    }

                    // Show loader on form submit
                    document.querySelectorAll("form").forEach(function (form) {
                        form.addEventListener("submit", function () {
                            if (loader) {
                                loader.style.display = "flex";
                                if (cancelBtn) cancelBtn.style.display = "none"; // Reset
                                // Re-enable cancel after 20 sec
                                setTimeout(function () {
                                    if (loader && loader.style.display !== "none") {
                                        cancelBtn.style.display = "block";
                                    }
                                }, 20000);
                            }
                        });
                    });
                });
            </script>
            <script>
                document.addEventListener("DOMContentLoaded", function () {
                    const loadingText = document.getElementById("loading-text");

                    if (loadingText) {
                        let dotCount = 0;
                        setInterval(function () {
                            dotCount = (dotCount + 1) % 4; // cycle through 0–3 dots
                            loadingText.textContent = "Loading Please Wait" + ".".repeat(dotCount);
                        }, 500); // Update every 0.5 seconds
                    }
                });
            </script>

            <!-- ===== Complaint Remark Notifications (A2Z) ===== -->
            <?php if ($__userUnreadComplaintCount > 0): ?>
              <?php
                $__prev = $__userUnreadComplaints[0] ?? null;
                $__prevRemark = $__prev ? (string)($__prev['remark'] ?? '') : '';
                $__prevType   = $__prev ? (string)($__prev['complaint_type'] ?? '') : '';
                $__prevCount  = (int)$__userUnreadComplaintCount;
                $__prevRemShort = mb_strlen($__prevRemark) > 130 ? (mb_substr($__prevRemark,0,130) . '…') : $__prevRemark;
              ?>
              <div class="gp-sticky-cmp-banner" id="gpCmpBanner" role="alert">
                <h6>
                  <i class='bx bx-message-rounded-error'></i>
                  📢 Complaint Reply Received
                  <?php if ($__prevCount > 1): ?>
                    <span style="font-size:11px;padding:2px 8px;border-radius:10px;background:rgba(255,255,255,.2);margin-left:4px;">
                      <?= $__prevCount ?> new
                    </span>
                  <?php endif; ?>
                </h6>
                <p>
                  <b><?= htmlspecialchars($__prevType) ?>:</b>
                  <?= htmlspecialchars($__prevRemShort) ?>
                </p>
                <div class="gp-cmp-btns">
                  <button class="gp-cmp-btn gp-cmp-btn-primary" id="gpCmpViewBtn">
                    <i class='bx bx-show'></i> View Complaint
                  </button>
                  <button class="gp-cmp-btn gp-cmp-btn-ghost" id="gpCmpDismissBtn">
                    <i class='bx bx-check-shield'></i> Mark as Read
                  </button>
                </div>
              </div>
            <?php endif; ?>

            <script>
            (function(){
                var GPCMP_AJAX_URL = "complaint_remark_ajax.php";
                var hasUnread = <?= (int)$__userUnreadComplaintCount ?> > 0;

                function gpCmpSetBell(state){
                    var wrap = document.getElementById('gpCmpNotifyWrap');
                    var bell = document.querySelector('#gpCmpNotifyWrap .gp-bell-wrap i');
                    if (wrap) {
                        if (state) wrap.classList.add('gp-has-unread'); else wrap.classList.remove('gp-has-unread');
                    }
                    if (bell) {
                        if (state) { bell.classList.add('gp-bell-red'); }
                        else { bell.classList.remove('gp-bell-red'); }
                    }
                }
                function gpCmpSetBadge(n){
                    var b = document.getElementById('gpBellBadge');
                    if (!b) return;
                    if (n > 0) { b.textContent = String(n); b.style.display = ''; }
                    else { b.style.display = 'none'; }
                }
                function gpCmpHideBanner(){
                    var el = document.getElementById('gpCmpBanner');
                    if (el) { el.style.transition = 'opacity .25s ease, transform .25s ease'; el.style.opacity='0'; el.style.transform='translateY(30px) scale(.95)';
                        setTimeout(function(){ if(el && el.parentNode) el.parentNode.removeChild(el); }, 260); }
                }
                function gpCmpMarkAll(cb){
                    try {
                        var x = new XMLHttpRequest();
                        x.open('POST', GPCMP_AJAX_URL, true);
                        x.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
                        x.onload = function(){ try { var d = JSON.parse(x.responseText); cb && cb(d); } catch(e){ cb && cb({ok:false}); } };
                        x.onerror = function(){ cb && cb({ok:false}); };
                        x.send('action=mark_seen_all');
                    } catch(e) { cb && cb({ok:false}); }
                }
                function gpCmpClearVisuals(){
                    gpCmpSetBell(false);
                    gpCmpSetBadge(0);
                    gpCmpHideBanner();
                    var dots = document.querySelectorAll('.gp-cmp-row-unread');
                    for (var i=0;i<dots.length;i++) dots[i].classList.remove('gp-cmp-row-unread');
                }

                document.addEventListener('DOMContentLoaded', function(){
                    gpCmpSetBell(hasUnread);

                    var clearAll = document.getElementById('gpCmpClearAll');
                    if (clearAll) { clearAll.addEventListener('click', function(e){
                        e.preventDefault();
                        gpCmpMarkAll(function(d){ if(d && d.ok) gpCmpClearVisuals(); });
                    });}

                    var dismissBtn = document.getElementById('gpCmpDismissBtn');
                    if (dismissBtn) { dismissBtn.addEventListener('click', function(){
                        gpCmpMarkAll(function(d){ if(d && d.ok) gpCmpClearVisuals(); else gpCmpClearVisuals(); });
                    });}

                    var viewBtn = document.getElementById('gpCmpViewBtn');
                    if (viewBtn) { viewBtn.addEventListener('click', function(){
                        document.location.href = 'complaint_request_list.php';
                    });}

                    var va = document.getElementById('gpViewAllCmp');
                    if (va) { va.addEventListener('click', function(e){
                        // Do nothing extra — href already navigates
                    });}

                    // If user opens the dropdown AND has unread — mark all as read after short delay
                    var bellBtn = document.getElementById('gpCmpBellBtn');
                    if (bellBtn && hasUnread) {
                        var gpAutoMarked = false;
                        bellBtn.addEventListener('click', function(){
                            if (gpAutoMarked) return;
                            setTimeout(function(){
                                if (!gpAutoMarked) { /* don't mark automatically — user must click mark read */ }
                            }, 600);
                        });
                    }
                });
            })();
            </script>

<?php
/* ==========================================================================
   9) ADMIN — Floating Unresolved Complaint Reminder (PERSISTENT until action)
   Visible ONLY for admins, ONLY when pending_complaint_count > 0.
   Admin can MINIMIZE to a small pulsing badge, but CANNOT dismiss permanently
   until all complaints are responded/deleted.
   ========================================================================== */
if (!empty($udata)
    && function_exists('checkAdmin')
    && checkAdmin($udata['type'] ?? '')
    && !empty($pending_complaint_count)
    && (int)$pending_complaint_count > 0
) {
    $cmpCount = (int)$pending_complaint_count;
    $cListLink = 'complaint_request_list_admin.php';
    ?>
    <!-- ===== Admin Floating Complaint Popup ===== -->
    <style>
        #gpAdminCmpFloat {
            position: fixed;
            right: 24px;
            bottom: 24px;
            z-index: 9990;
            max-width: 340px;
            width: calc(100% - 32px);
            font-family: 'Roboto', 'Nunito', system-ui, -apple-system, sans-serif;
            animation: gpCmpFloatPop .45s cubic-bezier(.2,.9,.2,1.2);
        }
        @keyframes gpCmpFloatPop {
            0%   { opacity: 0; transform: translateY(40px) scale(.9); }
            100% { opacity: 1; transform: translateY(0)    scale(1);  }
        }
        @keyframes gpCmpRingPulse {
            0%,100% { box-shadow: 0 0 0 0 rgba(220,53,69,.7), 0 10px 30px rgba(0,0,0,.22); }
            50%     { box-shadow: 0 0 0 14px rgba(220,53,69,0), 0 10px 30px rgba(0,0,0,.22); }
        }
        .gp-acf-card {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 50%, #a71d2a 100%);
            color: #fff;
            border-radius: 16px;
            padding: 16px 16px 14px 16px;
            border: 2px solid #ffe066;
            animation: gpCmpRingPulse 2.2s ease-in-out infinite;
        }
        .gp-acf-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 8px;
        }
        .gp-acf-title {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .gp-acf-icon-bubble {
            width: 40px; height: 40px;
            border-radius: 50%;
            background: rgba(255,255,255,.2);
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }
        .gp-acf-title-text { line-height: 1.2; }
        .gp-acf-title-text strong { font-size: 15px; font-weight: 800; letter-spacing: .2px; }
        .gp-acf-title-text small { opacity: .92; font-size: 11px; display: block; margin-top: 2px; }
        .gp-acf-count-badge {
            background: #fff;
            color: #dc3545;
            font-weight: 900;
            font-size: 14px;
            min-width: 30px; height: 30px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 8px;
            box-shadow: 0 3px 8px rgba(0,0,0,.2);
            flex-shrink: 0;
        }
        .gp-acf-close-min {
            background: rgba(255,255,255,.18);
            color: #fff;
            border: none;
            width: 26px; height: 26px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 14px;
            line-height: 1;
            display: flex; align-items: center; justify-content: center;
            transition: background .2s;
        }
        .gp-acf-close-min:hover { background: rgba(255,255,255,.32); }
        .gp-acf-body { font-size: 13px; line-height: 1.45; margin-bottom: 12px; opacity: .97; }
        .gp-acf-actions { display: flex; gap: 8px; }
        .gp-acf-btn {
            flex: 1;
            border-radius: 10px;
            padding: 9px 10px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            border: none;
            transition: transform .15s, background .2s, color .2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .gp-acf-btn-primary {
            background: #fff;
            color: #dc3545;
        }
        .gp-acf-btn-primary:hover { transform: translateY(-1px); background: #fff9e6; }
        .gp-acf-btn-mini {
            background: rgba(255,255,255,.18);
            color: #fff;
            flex: 0 0 auto;
            padding: 9px 12px;
        }
        .gp-acf-btn-mini:hover { background: rgba(255,255,255,.3); }
        /* ---- Minimized small badge ---- */
        #gpAdminCmpFloat.minimized { max-width: none; width: auto; }
        #gpAdminCmpFloat.minimized .gp-acf-expand-wrap { display: none; }
        #gpAdminCmpFloat .gp-acf-mini-badge { display: none; }
        #gpAdminCmpFloat.minimized .gp-acf-mini-badge { display: flex; }
        .gp-acf-mini-badge {
            width: 58px; height: 58px;
            border-radius: 50%;
            background: linear-gradient(135deg, #dc3545, #a71d2a);
            color: #fff;
            border: 3px solid #ffe066;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            animation: gpCmpRingPulse 2.2s ease-in-out infinite;
            cursor: pointer;
            position: relative;
            font-weight: 900;
        }
        .gp-acf-mini-badge .count { font-size: 16px; line-height: 1; }
        .gp-acf-mini-badge .label { font-size: 9px; letter-spacing: .3px; margin-top: 2px; font-weight: 700; }
        @media (max-width: 480px) {
            #gpAdminCmpFloat { right: 12px; bottom: 12px; }
        }
    </style>

    <div id="gpAdminCmpFloat">
        <!-- Expanded card -->
        <div class="gp-acf-expand-wrap">
            <div class="gp-acf-card">
                <div class="gp-acf-head">
                    <div class="gp-acf-title">
                        <div class="gp-acf-icon-bubble">📢</div>
                        <div class="gp-acf-title-text">
                            <strong>New Complaint Pending</strong>
                            <small>Aapke paas jawab ka intezaar kar rahe hai</small>
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <span class="gp-acf-count-badge"><?= $cmpCount; ?></span>
                        <button type="button" class="gp-acf-close-min" id="gpAcfMinBtn" title="Minimize">−</button>
                    </div>
                </div>
                <div class="gp-acf-body">
                    ⚠️ <strong><?= $cmpCount; ?></strong> unresolved complaint<?= $cmpCount === 1 ? '' : 's'; ?> hai.
                    Abhi tak inka response nahi diya gaya.
                    Jaldi se check karke jawab do ya delete karo, warna ye popup har page pe dikhta rahega.
                </div>
                <div class="gp-acf-actions">
                    <a class="gp-acf-btn gp-acf-btn-primary" href="<?= htmlspecialchars($cListLink); ?>">
                        📋 Sab Complaints Dekho
                    </a>
                    <button type="button" class="gp-acf-btn gp-acf-btn-mini" id="gpAcfMinBtn2" title="Minimize">
                        🔽
                    </button>
                </div>
            </div>
        </div>
        <!-- Minimized pulsing badge -->
        <div class="gp-acf-mini-badge" id="gpAcfMiniBadge" title="Click to expand — <?= $cmpCount; ?> pending complaints">
            <span class="count"><?= $cmpCount > 99 ? '99+' : $cmpCount; ?></span>
            <span class="label">COMPLAINT<?= $cmpCount === 1 ? '' : 'S'; ?></span>
        </div>
    </div>

    <script>
    (function(){
        var wrap   = document.getElementById('gpAdminCmpFloat');
        var mini   = document.getElementById('gpAcfMiniBadge');
        var btn1   = document.getElementById('gpAcfMinBtn');
        var btn2   = document.getElementById('gpAcfMinBtn2');
        if (!wrap) return;
        var STORAGE_KEY = 'gp_acf_minimized_v1';

        function applyMin(state) {
            if (!wrap) return;
            if (state) wrap.classList.add('minimized'); else wrap.classList.remove('minimized');
            try { localStorage.setItem(STORAGE_KEY, state ? '1' : '0'); } catch(e) {}
        }
        function readMin(){ try { return localStorage.getItem(STORAGE_KEY) === '1'; } catch(e) { return false; } }
        // Apply saved preference on page load
        if (readMin()) applyMin(true);

        if (btn1) btn1.addEventListener('click', function(){ applyMin(true); });
        if (btn2) btn2.addEventListener('click', function(){ applyMin(true); });
        if (mini) mini.addEventListener('click', function(){ applyMin(false); });
    })();
    </script>
    <?php
}
?>

            <!--end header -->
