<?php
// ============================================================================
// 🔴 ERROR REPORTING ON — WSOD nahi, error dikhenge always
// ============================================================================
@ini_set('display_errors', 1);
@ini_set('display_startup_errors', 1);
@ini_set('log_errors', 1);
@ini_set('error_log', dirname(__DIR__) . '/storage/php-errors.log');
error_reporting(E_ALL);

@ob_start();

include('../includes/session.php');
include('../includes/config.php');

if (!is_dir(dirname(__DIR__) . '/storage')) {
    @mkdir(dirname(__DIR__) . '/storage', 0755, true);
}

// Admin check
if (checkAdmin($udata['type']) == false) {
    ?>
    <script>
    window.location='index.php';
    </script>
    <?php
    die();
}

// ============================================================================
// SAVE NOTICE POST HANDLER
// ============================================================================
$savedMsg = '';
$saveError = '';

if (isset($_POST['save_push_notice'])) {
    try {
        $noticeEnabled = isset($_POST['push_notice_enabled']) ? '1' : '0';
        $noticeText    = trim((string)($_POST['push_notice_text'] ?? ''));
        $walletNoticeEnabled = isset($_POST['push_notice_wallet_enabled']) ? '1' : '0';
        $walletNoticeText    = trim((string)($_POST['push_notice_wallet_text'] ?? ''));

        // 1. Ensure columns exist (idempotent, same pattern as settings.php)
        $colsRes = @mysqli_query($ahk_conn, "SHOW COLUMNS FROM settings");
        $tableCols = [];
        if ($colsRes) {
            while ($r = mysqli_fetch_row($colsRes)) $tableCols[] = $r[0];
        }

        $addCol = function ($colName, $definition) use ($ahk_conn, $tableCols) {
            if (in_array($colName, $tableCols, true)) return;
            $prevReport = function_exists('mysqli_report') ? @mysqli_report(MYSQLI_REPORT_OFF) : null;
            @mysqli_query($ahk_conn, "ALTER TABLE settings ADD COLUMN {$colName} {$definition}");
            if ($prevReport !== null) @mysqli_report($prevReport);
        };

        $afterWn = in_array('whatsapp_number', $tableCols, true) ? 'AFTER whatsapp_number' : '';
        $addCol('push_notice_enabled', "TINYINT(1)  DEFAULT 1      {$afterWn}");
        $addCol('push_notice_text',    "TEXT        DEFAULT NULL AFTER push_notice_enabled");
        $afterPt = in_array('push_notice_text', $tableCols, true) ? 'AFTER push_notice_text' : '';
        $addCol('push_notice_wallet_enabled', "TINYINT(1)  DEFAULT 0      {$afterPt}");
        $addCol('push_notice_wallet_text',    "TEXT        DEFAULT NULL AFTER push_notice_wallet_enabled");

        // 2. UPDATE
        $en_esc  = mysqli_real_escape_string($ahk_conn, $noticeEnabled);
        $txt_esc = mysqli_real_escape_string($ahk_conn, $noticeText);
        $wen_esc  = mysqli_real_escape_string($ahk_conn, $walletNoticeEnabled);
        $wtxt_esc = mysqli_real_escape_string($ahk_conn, $walletNoticeText);

        // Re-fetch columns after possible ALTER
        $colsRes2 = @mysqli_query($ahk_conn, "SHOW COLUMNS FROM settings");
        $existingCols = [];
        if ($colsRes2) {
            while ($r = mysqli_fetch_row($colsRes2)) $existingCols[] = $r[0];
        }
        $setClauses = [];
        if (in_array('push_notice_enabled', $existingCols, true)) $setClauses[] = "`push_notice_enabled`='{$en_esc}'";
        if (in_array('push_notice_text',    $existingCols, true)) $setClauses[] = "`push_notice_text`='{$txt_esc}'";
        if (in_array('push_notice_wallet_enabled', $existingCols, true)) $setClauses[] = "`push_notice_wallet_enabled`='{$wen_esc}'";
        if (in_array('push_notice_wallet_text',    $existingCols, true)) $setClauses[] = "`push_notice_wallet_text`='{$wtxt_esc}'";
        if (!$setClauses) throw new Exception('Settings columns missing — check DB.');

        $sql = "UPDATE settings SET " . implode(', ', $setClauses) . " WHERE id=1 LIMIT 1";
        $update = @mysqli_query($ahk_conn, $sql);
        if ($update === false) {
            throw new Exception('MySQL: ' . mysqli_error($ahk_conn) . ' SQL=' . $sql);
        }
        $savedMsg = '✅ Notice Updated Successfully.';
    } catch (Throwable $e) {
        $saveError = '❌ Save nahi hua: ' . $e->getMessage();
    }
}

// Fetch current notice (after save so latest value shows)
$webres = @mysqli_query($ahk_conn, "SELECT * FROM settings WHERE id=1 LIMIT 1");
$webdata = $webres ? mysqli_fetch_assoc($webres) : [];

$curEnabled = (isset($webdata['push_notice_enabled']) && (int)$webdata['push_notice_enabled'] === 1);
$curText    = (string)($webdata['push_notice_text'] ?? '');
$curWalletEnabled = (isset($webdata['push_notice_wallet_enabled']) && (int)$webdata['push_notice_wallet_enabled'] === 1);
$curWalletText    = (string)($webdata['push_notice_wallet_text'] ?? '');

$defaultText = "Agar koi service ya PDF nikalne me error aa raha hai to thode der baad try kijiye. Kabhi kabhi server down ke wajah se aisa hota hai.";
$defaultWalletText = "Welcome back! Your wallet has sufficient balance. Ab aap koi bhi service without recharge use kar sakte hain. Enjoy premium services!";
if ($curText === '') $curText = $defaultText;
if ($curWalletText === '') $curWalletText = $defaultWalletText;

include(__DIR__ . '/../template/ahkweb/header.php');
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- SUCCESS / ERROR BANNERS -->
<div class="container mt-3">
    <?php if ($savedMsg !== ''): ?>
        <div class="alert alert-success border-0 shadow-sm bg-success bg-opacity-10 text-success mb-3" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?php echo htmlspecialchars($savedMsg); ?>
        </div>
    <?php endif; ?>
    <?php if ($saveError !== ''): ?>
        <div class="alert alert-danger border-0 shadow-sm bg-danger bg-opacity-10 text-danger mb-3" role="alert">
            <i class="bi bi-x-circle-fill me-2"></i><?php echo htmlspecialchars($saveError); ?>
        </div>
    <?php endif; ?>
</div>

<div class="page-wrapper">
    <div class="page-content">
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item"><a href="index.php"><i class="bx bx-home-alt"></i></a></li>
                        <li class="breadcrumb-item active">Push Notice</li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="row g-4">
            <!-- ========== SINGLE COLUMN: BOTH EDIT CARDS INSIDE SAME FORM ========== -->
            <div class="col-xl-8 mx-auto">
                <form method="POST">
                <div class="card border-top border-0 border-4 border-warning shadow-sm mb-4">
                    <div class="card-body p-5">
                        <div class="card-title d-flex align-items-center mb-3">
                            <div><i class="fa-solid fa-bullhorn me-2 font-22 text-warning"></i></div>
                            <h4 class="mb-0 text-warning">Push Notice — Customer Dashboard Banner</h4>
                        </div>
                        <p class="text-muted small mb-4">
                            Ye notice Customer Care section ke upar <b>attractive banner</b> ke roop me user dashboard par dikhega.
                            User jitne baad bhi dashboard open karega, yahi text dikhega jab tak enabled hai.
                        </p>
                        <hr>

                        <div class="row g-4">
                            <!-- ENABLED TOGGLE -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Notice Visible to Users?</label>
                                <div class="d-flex align-items-center gap-3 mt-2">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input"
                                               style="width:3em;height:1.6em;"
                                               type="checkbox"
                                               role="switch"
                                               id="pn_switch"
                                               name="push_notice_enabled"
                                               <?php echo $curEnabled ? 'checked' : ''; ?>>
                                        <label class="form-check-label ms-2 fw-bold" for="pn_switch">
                                            <span id="pn_state_label" class="<?php echo $curEnabled ? 'text-success' : 'text-secondary'; ?>">
                                                <?php echo $curEnabled ? '✅ Notice is ON (showing to users)' : '⛔ Notice is OFF (hidden from users)'; ?>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- STATUS PILL -->
                            <div class="col-md-6 d-flex align-items-end justify-content-end">
                                <span id="pn_status_pill"
                                      class="badge rounded-pill px-4 py-2 fs-6 <?php echo $curEnabled ? 'bg-success' : 'bg-secondary'; ?>">
                                    <?php echo $curEnabled ? '🔴 LIVE' : '⚫ DRAFT'; ?>
                                </span>
                            </div>

                            <!-- NOTICE TEXT -->
                            <div class="col-12">
                                <label class="form-label fw-bold mb-2">
                                    Notice Text
                                    <small class="text-muted d-block mt-1">
                                        Hindi + English dono allowed. Line break ke liye <b>Enter</b> dabayein.
                                    </small>
                                </label>
                                <textarea name="push_notice_text"
                                          id="pn_textarea"
                                          rows="6"
                                          class="form-control form-control-lg shadow-sm"
                                          placeholder="Example: Agar koi service ya PDF nikalne me error aa raha hai to thode der baad try kijiye..."><?php echo htmlspecialchars($curText); ?></textarea>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <small class="text-muted">
                                        Current length: <span id="pn_chars"><?php echo strlen($curText); ?></span> chars
                                    </small>
                                    <button type="button"
                                            class="btn btn-sm btn-outline-secondary rounded-pill"
                                            onclick="document.getElementById('pn_textarea').value = '<?php echo addslashes($defaultText); ?>'; updateCharCount();">
                                        ↩️ Reset Default
                                    </button>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- ===== WALLET BALANCE USERS NOTICE ===== -->
                <div class="card border-top border-0 border-4 border-success shadow-sm">
                    <div class="card-body p-5">
                        <div class="card-title d-flex align-items-center mb-3">
                            <div><i class="fa-solid fa-wallet me-2 font-22 text-success"></i></div>
                            <h4 class="mb-0 text-success">Wallet Balance Users — Special Notice</h4>
                        </div>
                        <p class="text-muted small mb-4">
                            Ye notice <b>sirf unhi users ko dikhega</b> jinke wallet me balance hai (₹0 se zyada).
                            Zero balance users ko ye notice kabhi nahi dikhega — unko upar wala "All Users" notice hi dikhega.
                        </p>
                        <hr>

                        <div class="row g-4">
                            <!-- ENABLED TOGGLE -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Notice Visible to Wallet Users?</label>
                                <div class="d-flex align-items-center gap-3 mt-2">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input"
                                               style="width:3em;height:1.6em;"
                                               type="checkbox"
                                               role="switch"
                                               id="pn_wallet_switch"
                                               name="push_notice_wallet_enabled"
                                               <?php echo $curWalletEnabled ? 'checked' : ''; ?>>
                                        <label class="form-check-label ms-2 fw-bold" for="pn_wallet_switch">
                                            <span id="pn_wallet_state_label" class="<?php echo $curWalletEnabled ? 'text-success' : 'text-secondary'; ?>">
                                                <?php echo $curWalletEnabled ? '✅ Wallet Notice ON (only balance users)' : '⛔ Wallet Notice OFF (hidden)'; ?>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- STATUS PILL -->
                            <div class="col-md-6 d-flex align-items-end justify-content-end">
                                <span id="pn_wallet_status_pill"
                                      class="badge rounded-pill px-4 py-2 fs-6 <?php echo $curWalletEnabled ? 'bg-success' : 'bg-secondary'; ?>">
                                    <?php echo $curWalletEnabled ? '🟢 FOR BALANCE USERS' : '⚫ DISABLED'; ?>
                                </span>
                            </div>

                            <!-- NOTICE TEXT -->
                            <div class="col-12">
                                <label class="form-label fw-bold mb-2">
                                    Wallet Users Notice Text
                                    <small class="text-muted d-block mt-1">
                                        Hindi + English dono allowed. Sirf un users ko dikhega jinke paas wallet balance hai.
                                    </small>
                                </label>
                                <textarea name="push_notice_wallet_text"
                                          id="pn_wallet_textarea"
                                          rows="6"
                                          class="form-control form-control-lg shadow-sm"
                                          placeholder="Example: Welcome back! Your wallet has sufficient balance. Enjoy our premium services..."><?php echo htmlspecialchars($curWalletText); ?></textarea>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <small class="text-muted">
                                        Current length: <span id="pn_wallet_chars"><?php echo strlen($curWalletText); ?></span> chars
                                    </small>
                                    <button type="button"
                                            class="btn btn-sm btn-outline-secondary rounded-pill"
                                            onclick="document.getElementById('pn_wallet_textarea').value = '<?php echo addslashes($defaultWalletText); ?>'; updateWalletCharCount();">
                                        ↩️ Reset Default
                                    </button>
                                </div>
                            </div>

                            <!-- SUBMIT (applies to BOTH notices) -->
                            <div class="col-12 d-grid gap-2 d-md-flex justify-content-md-end mt-4 pt-4 border-top">
                                <a href="index.php" class="btn btn-light border btn-lg px-4 rounded-pill">
                                    <i class="bi bi-arrow-left me-2"></i> Cancel
                                </a>
                                <button type="submit"
                                        name="save_push_notice"
                                        value="1"
                                        class="btn btn-lg px-5 rounded-pill text-white"
                                        style="background:linear-gradient(135deg,#10b981,#059669);box-shadow:0 6px 16px rgba(16,185,129,.35);">
                                    <i class="bi bi-send-check me-2"></i> Save Both Notices
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                </form>
            </div>

            <!-- ========== RIGHT CARD: LIVE PREVIEW ========== -->
            <div class="col-xl-12 mx-auto mt-4">
                <div class="card border shadow-sm">
                    <div class="card-header bg-light">
                        <h6 class="mb-0 fw-bold">
                            <i class="bi bi-eye-fill text-info me-2"></i>
                            LIVE PREVIEW — User Dashboard (Customer Care ke upar dikhega)
                        </h6>
                    </div>
                    <div class="card-body p-4 bg-gray-50" style="background:#f8fafc;">
                        <style>
                            .preview-wrap {
                                max-width: 1100px;
                                margin: 0 auto;
                            }
                            .pn-preview-banner {
                                border-radius: 18px;
                                background: linear-gradient(135deg, #fef3c7 0%, #fed7aa 100%);
                                border: 1.5px solid rgba(245, 158, 11, 0.35);
                                box-shadow: 0 14px 36px -18px rgba(245,158,11,0.55),
                                            0 4px 10px rgba(0,0,0,0.05);
                                padding: 20px 24px;
                                position: relative;
                                overflow: hidden;
                                margin-bottom: 22px;
                            }
                            .pn-preview-banner::before {
                                content: '';
                                position: absolute; top: 0; left: 0; right: 0;
                                height: 3px;
                                background: linear-gradient(90deg, #f59e0b, #ef4444, #f59e0b);
                                background-size: 200% 100%;
                                animation: pn-bar 3.5s linear infinite;
                            }
                            @keyframes pn-bar {
                                0% { background-position: 0% 0; }
                                100% { background-position: 200% 0; }
                            }
                            .pn-preview-icon {
                                width: 54px; height: 54px;
                                border-radius: 16px;
                                display: inline-flex;
                                align-items: center; justify-content: center;
                                background: linear-gradient(135deg, #f59e0b, #ef4444);
                                color: #fff;
                                box-shadow: 0 6px 14px rgba(239,68,68,.35),
                                            inset 0 1px 0 rgba(255,255,255,.25);
                                flex-shrink: 0;
                            }
                            .pn-preview-icon i {
                                font-size: 24px;
                                animation: pn-shake 2.2s ease-in-out infinite;
                            }
                            @keyframes pn-shake {
                                0%,100% { transform: rotate(0); }
                                20%     { transform: rotate(-12deg); }
                                40%     { transform: rotate(10deg); }
                                60%     { transform: rotate(-8deg); }
                                80%     { transform: rotate(6deg); }
                            }
                            .pn-preview-title {
                                font-size: 18px;
                                font-weight: 800;
                                color: #92400e;
                                letter-spacing: 0.02em;
                                margin-bottom: 6px;
                            }
                            .pn-preview-text {
                                font-size: 14.5px;
                                color: #78350f;
                                line-height: 1.65;
                                white-space: pre-wrap;
                                word-break: break-word;
                            }
                            .fake-customer-care {
                                background: #16a34a;
                                border-radius: 18px;
                                padding: 22px 28px;
                                color: #fff;
                                position: relative;
                            }
                            .fake-customer-care h6 {
                                color: #fff;
                                font-weight: 700;
                                margin-bottom: 8px;
                                font-size: 16px;
                            }
                            .fake-customer-care p {
                                color: #dcfce7;
                                font-size: 13px;
                                margin: 0;
                            }
                        </style>

                        <div class="preview-wrap">
                            <!-- NOTICE BANNER -->
                            <div class="pn-preview-banner d-flex gap-4 align-items-start pn-preview-active" id="pn_preview">
                                <div class="pn-preview-icon">
                                    <i class="bi bi-megaphone-fill"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="pn-preview-title">
                                        📢 IMPORTANT NOTICE
                                        <span id="pn_live_badge" class="badge rounded-pill bg-danger ms-2 fs-7 py-1 px-3">LIVE</span>
                                    </div>
                                    <div class="pn-preview-text" id="pn_preview_text"><?php echo nl2br(htmlspecialchars($curText)); ?></div>
                                </div>
                                <button type="button"
                                        style="border:none;background:rgba(0,0,0,0.08);border-radius:10px;color:#78350f;width:36px;height:36px;font-size:18px;cursor:pointer;line-height:1;"
                                        title="Dismiss">&times;</button>
                            </div>

                            <!-- FAKE CUSTOMER CARE -->
                            <div class="fake-customer-care">
                                <h6>📞 Customer Care</h6>
                                <p>Hello Good Print Portal, please use our services on Desktop / Laptop with Chrome or Mozilla.</p>
                                <div class="mt-3 opacity-75 small">
                                    <span>WhatsApp: +91 98XXX XXXXX</span>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- ========== WALLET NOTICE LIVE PREVIEW ========== -->
            <div class="col-xl-12 mx-auto mt-4">
                <div class="card border shadow-sm">
                    <div class="card-header bg-light">
                        <h6 class="mb-0 fw-bold">
                            <i class="bi bi-eye-fill text-success me-2"></i>
                            WALLET NOTICE PREVIEW — Only for users with Wallet Balance (₹0+)
                        </h6>
                    </div>
                    <div class="card-body p-4 bg-gray-50" style="background:#f8fafc;">
                        <style>
                            .wallet-preview-wrap {
                                max-width: 1100px;
                                margin: 0 auto;
                            }
                            .pn-wallet-preview-banner {
                                border-radius: 18px;
                                background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
                                border: 1.5px solid rgba(34, 197, 94, 0.35);
                                box-shadow: 0 14px 36px -18px rgba(34,197,94,0.55),
                                            0 4px 10px rgba(0,0,0,0.05);
                                padding: 20px 24px;
                                position: relative;
                                overflow: hidden;
                                margin-bottom: 22px;
                            }
                            .pn-wallet-preview-banner::before {
                                content: '';
                                position: absolute; top: 0; left: 0; right: 0;
                                height: 3px;
                                background: linear-gradient(90deg, #10b981, #059669, #10b981);
                                background-size: 200% 100%;
                                animation: pn-wallet-bar 3.5s linear infinite;
                            }
                            @keyframes pn-wallet-bar {
                                0% { background-position: 0% 0; }
                                100% { background-position: 200% 0; }
                            }
                            .pn-wallet-preview-icon {
                                width: 54px; height: 54px;
                                border-radius: 16px;
                                display: inline-flex;
                                align-items: center; justify-content: center;
                                background: linear-gradient(135deg, #10b981, #059669);
                                color: #fff;
                                box-shadow: 0 6px 14px rgba(5,150,105,.35),
                                            inset 0 1px 0 rgba(255,255,255,.25);
                                flex-shrink: 0;
                            }
                            .pn-wallet-preview-icon i {
                                font-size: 24px;
                                animation: pn-wallet-bounce 2.2s ease-in-out infinite;
                            }
                            @keyframes pn-wallet-bounce {
                                0%,100% { transform: translateY(0); }
                                50%     { transform: translateY(-6px); }
                            }
                            .pn-wallet-preview-title {
                                font-size: 18px;
                                font-weight: 800;
                                color: #14532d;
                                letter-spacing: 0.02em;
                                margin-bottom: 6px;
                            }
                            .pn-wallet-preview-text {
                                font-size: 14.5px;
                                color: #166534;
                                line-height: 1.65;
                                white-space: pre-wrap;
                                word-break: break-word;
                            }
                        </style>

                        <div class="wallet-preview-wrap">
                            <!-- WALLET NOTICE BANNER -->
                            <div class="pn-wallet-preview-banner d-flex gap-4 align-items-start" id="pn_wallet_preview">
                                <div class="pn-wallet-preview-icon">
                                    <i class="bi bi-wallet2"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="pn-wallet-preview-title">
                                        💰 WALLET BALANCE NOTICE
                                        <span id="pn_wallet_live_badge" class="badge rounded-pill bg-success ms-2 fs-7 py-1 px-3">BALANCE USERS</span>
                                    </div>
                                    <div class="pn-wallet-preview-text" id="pn_wallet_preview_text"><?php echo nl2br(htmlspecialchars($curWalletText)); ?></div>
                                </div>
                                <button type="button"
                                        style="border:none;background:rgba(0,0,0,0.08);border-radius:10px;color:#14532d;width:36px;height:36px;font-size:18px;cursor:pointer;line-height:1;"
                                        title="Dismiss">&times;</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // ---- live toggle UI updates + live preview + char count (ALL USERS NOTICE) ----
    const sw  = document.getElementById('pn_switch');
    const lbl = document.getElementById('pn_state_label');
    const pill = document.getElementById('pn_status_pill');
    const liveBadge = document.getElementById('pn_live_badge');
    const preview = document.getElementById('pn_preview');
    const ta  = document.getElementById('pn_textarea');
    const out = document.getElementById('pn_preview_text');
    const cc  = document.getElementById('pn_chars');

    function updateUI() {
        const on = sw.checked;
        lbl.textContent = on
            ? '✅ Notice is ON (showing to users)'
            : '⛔ Notice is OFF (hidden from users)';
        lbl.className = on ? 'text-success' : 'text-secondary';
        pill.className = 'badge rounded-pill px-4 py-2 fs-6 ' + (on ? 'bg-success' : 'bg-secondary');
        pill.textContent = on ? '🔴 LIVE' : '⚫ DRAFT';
        if (liveBadge) {
            liveBadge.className = 'badge rounded-pill ms-2 fs-7 py-1 px-3 ' + (on ? 'bg-danger' : 'bg-secondary');
            liveBadge.textContent = on ? 'LIVE' : 'HIDDEN';
        }
        if (preview) {
            preview.style.opacity = on ? '1' : '0.45';
            preview.style.filter  = on ? 'none' : 'grayscale(1)';
        }
    }
    function updateCharCount() {
        cc.textContent = ta.value.length;
        out.innerHTML = ta.value
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\n/g, '<br>');
    }
    sw.addEventListener('change', updateUI);
    ta.addEventListener('input', function(){ updateCharCount(); });
    updateUI();

    // ---- live toggle UI updates + live preview + char count (WALLET USERS NOTICE) ----
    const swW  = document.getElementById('pn_wallet_switch');
    const lblW = document.getElementById('pn_wallet_state_label');
    const pillW = document.getElementById('pn_wallet_status_pill');
    const liveBadgeW = document.getElementById('pn_wallet_live_badge');
    const previewW = document.getElementById('pn_wallet_preview');
    const taW  = document.getElementById('pn_wallet_textarea');
    const outW = document.getElementById('pn_wallet_preview_text');
    const ccW  = document.getElementById('pn_wallet_chars');

    function updateWalletUI() {
        const on = swW.checked;
        lblW.textContent = on
            ? '✅ Wallet Notice ON (only balance users)'
            : '⛔ Wallet Notice OFF (hidden)';
        lblW.className = on ? 'text-success' : 'text-secondary';
        pillW.className = 'badge rounded-pill px-4 py-2 fs-6 ' + (on ? 'bg-success' : 'bg-secondary');
        pillW.textContent = on ? '🟢 FOR BALANCE USERS' : '⚫ DISABLED';
        if (liveBadgeW) {
            liveBadgeW.className = 'badge rounded-pill ms-2 fs-7 py-1 px-3 ' + (on ? 'bg-success' : 'bg-secondary');
            liveBadgeW.textContent = on ? 'BALANCE USERS' : 'HIDDEN';
        }
        if (previewW) {
            previewW.style.opacity = on ? '1' : '0.45';
            previewW.style.filter  = on ? 'none' : 'grayscale(1)';
        }
    }
    function updateWalletCharCount() {
        ccW.textContent = taW.value.length;
        outW.innerHTML = taW.value
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\n/g, '<br>');
    }
    swW.addEventListener('change', updateWalletUI);
    taW.addEventListener('input', function(){ updateWalletCharCount(); });
    updateWalletUI();
</script>

<?php include(__DIR__ . '/../template/ahkweb/footer.php'); ?>
