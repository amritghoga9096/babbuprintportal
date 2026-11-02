<?php
// 🔴 ALWAYS Error display ON — hosting me problem/debug karne ke liye hamesha errors dikhe
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ✅ ALWAYS use __DIR__ for header/footer - works whether included from admin/ OR called directly
include(__DIR__ . '/header.php');

// Fetch settings (if already loaded from admin controller, reuse; else direct fetch)
global $webdata, $ahk_conn, $showSavedBanner;
if (!isset($webdata) || !$webdata) {
    $webres = mysqli_query($ahk_conn, "SELECT * FROM settings WHERE id=1 LIMIT 1");
    $webdata = $webres ? mysqli_fetch_assoc($webres) : [];
}
if (!isset($showSavedBanner)) $showSavedBanner = false;

// Success banner (if ?saved=1 or passed from controller
if ($showSavedBanner):
?>
<div class="container mt-3">
    <div class="alert alert-success alert-dismissible fade show border-0 bg-success bg-opacity-10 text-success mb-4" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>
        <b>Settings Updated Successfully.</b> All changes saved to database.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
</div>
<?php endif;

// Show curl/fopen warnings
$hasCurl  = function_exists('curl_init');
$hasFopen  = (bool)ini_get('allow_url_fopen');
$canReachable = ($hasCurl || $hasFopen);
if (!$canReachable):
?>
<div class="container mt-3">
    <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <b>Hosting Issue:</b> Your server has BOTH <b>curl</b> extension disabled AND <b>allow_url_fopen</b>=Off in php.ini.
        Central Manager buttons (Register / Test Connection) will FAIL. Contact hosting support and ask to enable either curl (recommended) OR set <code>allow_url_fopen=On</code> in MultiPHP INI Editor.
    </div>
</div>
<?php elseif (!$hasCurl && $hasFopen): ?>
<div class="container mt-3">
    <div class="alert alert-warning border-0 bg-warning bg-opacity-10 mb-4" role="alert">
        <i class="bi bi-info-circle me-2"></i>
        <b>Note:</b> curl extension not installed, using <code>file_get_contents</code> (allow_url_fopen) for Central Manager. Works, but curl is faster. Ask host to install curl for best performance.
    </div>
</div>
<?php endif;

// Safe check - does pariprint_api_key column exist?
$hasPariprintCol = false;
if ($webdata && is_array($webdata) && array_key_exists('pariprint_api_key', $webdata)) {
    $hasPariprintCol = true;
} else {
    // Fallback - try column check (one time)
    $colCheck = @mysqli_query($ahk_conn, "SHOW COLUMNS FROM settings LIKE 'pariprint_api_key'");
    if ($colCheck && mysqli_num_rows($colCheck) > 0) {
        $hasPariprintCol = true;
    }
}
$default_pariprint_key = 'phk_55e219135b215ac76104c0b0e9eacb7ef7dd14fd';
?>

<!-- Debug inline banner (remove after fix) -->
<div style="background:#e7f4ff;border:1px solid #48e;padding:6px 12px;margin:4px;color:#246;font-family:monospace;font-size:11px;">
<b>Template Debug (ahkweb/settings.php):</b> ✅ header OK | webdata loaded = <?php echo ($webdata?'YES':'NO'); ?> | pariprint column = <?php echo ($hasPariprintCol?'EXISTS':'NOT DETECTED (will skip field)'); ?> | __FILE__ = <?php echo htmlspecialchars(__FILE__); ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>

<!--start page wrapper -->
<div class="page-wrapper">
    <div class="page-content">
        <!--breadcrumb-->
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item"><a href="javascript:;"><i class="bx bx-home-alt"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Website Settings</li>
                    </ol>
                </nav>
            </div>
        </div>
        <!--end breadcrumb-->

        <div class="row">
            <div class="col-xl-12 mx-auto">
                <div class="card border-top border-0 border-4 border-primary shadow-sm">
                    <div class="card-body p-5">
                        <div class="card-title d-flex align-items-center">
                            <div><i class="bx bxs-cog me-2 font-22 text-primary"></i></div>
                            <h4 class="mb-0 text-primary">Website Settings (Website Details)</h4>
                        </div>
                        <hr>

                        <form action="" method="POST" class="row g-4">
                            <div class="col-12">
                                <div class="row g-3">
                                    <!-- Website Basic Info -->
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Website Name</label>
                                        <input name="webname" value="<?php ahkweb('webname'); ?>" type="text" class="form-control" placeholder="Website Name">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Phone</label>
                                        <input name="phone" value="<?php ahkweb('phone'); ?>" type="text" class="form-control" placeholder="Phone Number">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">WhatsApp Support Number
                                            <small class="text-muted">(with country code, e.g. 919876543210)</small>
                                        </label>
                                        <input
                                            name="whatsapp_number"
                                            value="<?php
                                                $wa_val = $webdata['whatsapp_number'] ?? '';
                                                echo htmlspecialchars($wa_val);
                                            ?>"
                                            type="text"
                                            class="form-control"
                                            placeholder="919876543210">
                                        <small class="text-muted">
                                            Ye number floating WhatsApp button par sab jagah (Homepage, Auth, Admin) dikhega.
                                        </small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Email</label>
                                        <input name="email" value="<?php ahkweb('email'); ?>" type="email" class="form-control" placeholder="Email Address">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Address</label>
                                        <input name="address" value="<?php ahkweb('address'); ?>" type="text" class="form-control" placeholder="Full Address">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Retailer Registration Fee</label>
                                        <input name="ret_reg_fee" value="<?php ahkweb('ret_reg_fee'); ?>" type="text" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Distributor Registration Fee</label>
                                        <input name="dist_reg_fee" value="<?php ahkweb('dist_reg_fee'); ?>" type="text" class="form-control">
                                    </div>

                                    <!-- API Configuration -->
                                    <div class="col-12 mt-4">
                                        <h6 class="text-primary border-bottom pb-2">API Configuration</h6>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">API URL
                                            <a id="authUrlLink" target="_blank" class="text-danger fw-bold">&#128279; API URL Here</a>
                                        </label>
                                        <input name="auth_url"
                                               value="<?php ahkweb('auth_url'); ?>"
                                               type="text"
                                               placeholder="https://apiadda.in/"
                                               class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">API Key</label>
                                        <input name="api_key" value="<?php ahkweb('api_key'); ?>" type="text" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Secret Key</label>
                                        <input name="secret_key" value="<?php ahkweb('secret_key'); ?>" type="text" class="form-control">
                                    </div>
                                    <?php if ($hasPariprintCol): ?>
                                    <div class="col-md-6">
                                        <label class="form-label">PariPrint API Key</label>
                                        <input
                                            name="pariprint_api_key"
                                            value="<?php echo htmlspecialchars($webdata['pariprint_api_key'] ?? $default_pariprint_key); ?>"
                                            type="text"
                                            class="form-control"
                                            placeholder="Enter PariPrint API Key">
                                        <small class="text-muted">
                                            Ye ek hi key sab PariPrint reseller services par apply hogi: Instant Pan Find, Instant Number Link With Voter, Instant RC Pdf, Instant DL Pdf, Voter PDF, LL Exam Pass, PAN to GST, GST to Details, Instant Farmer PDF, Instant Challan Details, Instant Ration PDF, Mobile Number Details, Balance Check.
                                        </small>
                                    </div>
                                    <?php else: ?>
                                    <div class="col-md-6">
                                        <label class="form-label text-danger">PariPrint API Key (Column Missing!)</label>
                                        <div class="alert alert-danger py-1 px-2 mb-0 small">
                                            <b>pariprint_api_key</b> column settings table me nahi hai.
                                            Save button par click karo - auto create ho jayega. Phir page refresh karke key dalo.
                                        </div>
                                        <input name="pariprint_api_key"
                                               value="<?php echo htmlspecialchars($default_pariprint_key); ?>"
                                               type="text"
                                               class="form-control"
                                               placeholder="Default key - Save karke column create karo">
                                    </div>
                                    <?php endif; ?>

                                    <!-- Payment Configuration -->
                                    <div class="col-12 mt-4">
                                        <h6 class="text-primary border-bottom pb-2">Payment Configuration</h6>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">PG Type</label>
                                        <input name="pgtype" value="<?php ahkweb('pgtype'); ?>" type="text" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Merchant ID</label>
                                        <input name="mid" value="<?php ahkweb('mid'); ?>" type="text" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Merchant Key</label>
                                        <input name="mkey" value="<?php ahkweb('mkey'); ?>" type="text" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Callback URL</label>
                                        <input name="callback_url" value="<?php ahkweb('callback_url'); ?>" type="text" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">PayU Merchant Key</label>
                                        <input name="payu_mkey" value="<?php ahkweb('payu_mkey'); ?>" type="text" class="form-control" placeholder="Telegram Username">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">PayU Salt</label>
                                        <input name="salt" value="<?php ahkweb('salt'); ?>" type="text" class="form-control" placeholder="Telegram Group Link">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">UPI ID</label>
                                        <input name="upi_id" value="<?php ahkweb('upi_id'); ?>" type="text" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">UPI Key</label>
                                        <input name="upi_key" value="<?php ahkweb('upi_key'); ?>" type="text" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">PayTM Active</label>
                                        <input name="paytm" value="<?php ahkweb('paytm'); ?>" type="text" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">PayU Active</label>
                                        <input name="payu" value="<?php ahkweb('payu'); ?>" type="text" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">UPI Active</label>
                                        <input name="upi" value="<?php ahkweb('upi'); ?>" type="text" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">WH Key</label>
                                        <input name="whkey" value="<?php ahkweb('whkey'); ?>" type="text" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Home Template Name</label>
                                        <input name="HomeTemplatename" value="<?php ahkweb('HomeTemplatename'); ?>" type="text" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Mobile Recharge Active</label>
                                        <input name="mobile_recharge_active" value="<?php ahkweb('mobile_recharge_active'); ?>" type="text" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">QR Enable</label>
                                        <select name="qr" class="form-control">
                                            <option value="1" <?php $val = function_exists('ahkweb') ? '' : ''; echo (($webdata['qr'] ?? '1') == '1' ? 'selected' : ''); ?>>Enabled</option>
                                            <option value="0" <?php echo (($webdata['qr'] ?? '1') == '0' ? 'selected' : ''); ?>>Disabled</option>
                                        </select>
                                    </div>

                                    <!-- Recharge API Configs -->
                                    <div class="col-12 mt-4">
                                        <h6 class="text-primary border-bottom pb-2">Recharge Provider API Keys</h6>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Airtel</label>
                                        <input name="airtal" value="<?php ahkweb('airtal'); ?>" type="text" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">VI</label>
                                        <input name="vi" value="<?php ahkweb('vi'); ?>" type="text" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Jio</label>
                                        <input name="jio" value="<?php ahkweb('jio'); ?>" type="text" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">BSNL</label>
                                        <input name="bsnl" value="<?php ahkweb('bsnl'); ?>" type="text" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Airtel Digital TV DTH</label>
                                        <input name="airtel_digital_tv_dth" value="<?php ahkweb('airtel_digital_tv_dth'); ?>" type="text" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Tata Sky DTH</label>
                                        <input name="tata_sky_dth" value="<?php ahkweb('tata_sky_dth'); ?>" type="text" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">DishTV DTH</label>
                                        <input name="dishtv_dth" value="<?php ahkweb('dishtv_dth'); ?>" type="text" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Videocon DTH</label>
                                        <input name="Videocon_dth" value="<?php ahkweb('Videocon_dth'); ?>" type="text" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Sun Direct DTH</label>
                                        <input name="Sun_Direct_dth" value="<?php ahkweb('Sun_Direct_dth'); ?>" type="text" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Google Play</label>
                                        <input name="google_play" value="<?php ahkweb('google_play'); ?>" type="text" class="form-control">
                                    </div>

                                    <!-- ============================================================ -->
                                    <!-- CENTRAL API DOMAIN MANAGER SECTION                          -->
                                    <!-- ============================================================ -->
                                    <div class="col-12 mt-5 border-3 border-primary border-top pt-4">
                                        <h5 class="mb-3 text-primary">
                                            <i class="bi bi-diagram-3 me-2"></i>Central API Domain Manager
                                            <span class="badge bg-info text-dark ms-2">1 config = all sites sync</span>
                                        </h5>
                                        <p class="text-muted small mb-4">
                                            When you have 10+ sites, set up ONE Central Manager. Change domains once there, all sites auto-update instantly.
                                            Works for <b>API Domain</b> (Pariprint) + <b>Gateway Domain</b> (Wallet recharge, drpay).
                                        </p>

                                        <?php
                                        // Live diagnostic (resolved domains)
                                        $liveCfg  = function_exists('central_debug_info') ? central_debug_info() : ['resolved'=>['source'=>'client-missing','api_domain'=>'https://pariprint.in','gateway_domain'=>'https://gateway.pdfgods.in']];
                                        $resolved = $liveCfg['resolved'] ?? [];
                                        $srcBadge = 'secondary';
                                        if (($resolved['source'] ?? '') === 'remote') $srcBadge = 'success';
                                        elseif (($resolved['source'] ?? '') === 'cache') $srcBadge = 'primary';
                                        elseif (($resolved['source'] ?? '') === 'cache-stale') $srcBadge = 'warning';
                                        elseif (($resolved['source'] ?? '') === 'default-fallback') $srcBadge = 'danger';
                                        ?>
                                        <div class="alert alert-<?= $srcBadge ?> bg-opacity-10 border border-<?= $srcBadge ?> border-opacity-25 mb-4">
                                            <div class="row g-2 align-items-center small">
                                                <div class="col-sm-6">
                                                    <span class="fw-bold text-uppercase">Live API Domain</span><br>
                                                    <a target="_blank" class="font-monospace text-break" href="<?= htmlspecialchars($resolved['api_domain'] ?? 'https://pariprint.in') ?>">
                                                        <?= htmlspecialchars($resolved['api_domain'] ?? 'https://pariprint.in') ?>
                                                    </a>
                                                </div>
                                                <div class="col-sm-6">
                                                    <span class="fw-bold text-uppercase">Live Gateway Domain</span><br>
                                                    <a target="_blank" class="font-monospace text-break" href="<?= htmlspecialchars($resolved['gateway_domain'] ?? 'https://gateway.pdfgods.in') ?>">
                                                        <?= htmlspecialchars($resolved['gateway_domain'] ?? 'https://gateway.pdfgods.in') ?>
                                                    </a>
                                                </div>
                                                <div class="col-12 mt-1">
                                                    <span class="badge bg-<?= $srcBadge ?>">
                                                        Source: <?= strtoupper($resolved['source'] ?? 'unknown') ?>
                                                    </span>
                                                    <?php if (!empty($resolved['version']) && (int)$resolved['version'] > 0): ?>
                                                        <span class="badge bg-secondary ms-1">Version v<?= (int)$resolved['version'] ?></span>
                                                    <?php endif; ?>
                                                    <?php if (isset($resolved['age_sec'])): ?>
                                                        <span class="badge bg-light text-dark ms-1">Age: <?= round(($resolved['age_sec']/60),1) ?> min</span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($liveCfg['cache_path'])): ?>
                                                        <span class="badge bg-light text-dark ms-1" title="Cache file">
                                                            <i class="bi bi-hdd"></i> cache: <?= htmlspecialchars(basename($liveCfg['cache_path'])) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-8">
                                        <label class="form-label fw-bold">Central Manager URL
                                            <small class="text-muted">(paste from your central panel Dashboard)</small>
                                        </label>
                                        <div class="input-group input-group-lg">
                                            <span class="input-group-text"><i class="bi bi-globe2"></i></span>
                                            <input
                                                id="central_manager_url"
                                                name="central_manager_url"
                                                type="url"
                                                value="<?php ahkweb('central_manager_url'); ?>"
                                                class="form-control form-control-lg"
                                                placeholder="https://manager.yourdomain.com/"
                                                pattern="https?://.*">
                                            <button type="button" class="btn btn-outline-info" onclick="registerSite()">
                                                <i class="bi bi-plug"></i> Register &amp; Get Key
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Cache Refresh (minutes)</label>
                                        <input
                                            name="central_cache_min"
                                            type="number"
                                            min="1"
                                            max="1440"
                                            value="<?php
                                                $v = isset($webdata['central_cache_min']) ? (int)$webdata['central_cache_min'] : 60;
                                                if ($v <= 0) $v = 60; if ($v > 1440) $v = 1440;
                                                echo $v; ?>"
                                            class="form-control form-control-lg">
                                        <div class="form-text">How often to check central for new domains.</div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-bold">Access Key
                                            <small class="text-muted">(auto-generated after Register)</small>
                                        </label>
                                        <div class="input-group input-group-lg">
                                            <span class="input-group-text"><i class="bi bi-key-fill"></i></span>
                                            <input
                                                id="central_access_key"
                                                name="central_access_key"
                                                type="text"
                                                value="<?php ahkweb('central_access_key'); ?>"
                                                class="form-control form-control-lg font-monospace"
                                                placeholder="Click 'Register &amp; Get Key' → access key auto-filled here">
                                            <button type="button" class="btn btn-outline-success" onclick="testConnection()">
                                                <i class="bi bi-lightning-charge"></i> Test Connection
                                            </button>
                                            <button type="button" class="btn btn-outline-primary" onclick="forceRefreshCache()">
                                                <i class="bi bi-arrow-repeat"></i> Force Refresh
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Quick actions result area -->
                                    <div id="central_result" class="col-12 d-none">
                                        <div class="card border-primary shadow-sm mt-3 bg-white">
                                            <div class="card-body" id="central_result_body"></div>
                                        </div>
                                    </div>

                                </div>
                            </div>

                                <!-- PUSH NOTICE SETTINGS -->
                                <div class="col-12 mt-5" id="push-notice-settings">
                                    <div class="card border-0 shadow-sm">
                                        <div class="card-header bg-white border-bottom">
                                            <h6 class="mb-0 fw-bold"><i class="bi bi-bullhorn me-2 text-warning"></i> Push Notice Settings</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="row g-3 mb-4">
                                                <div class="col-md-12">
                                                    <div class="alert alert-info py-2 px-3 mb-3 small">
                                                        <i class="bi bi-info-circle me-2"></i>
                                                        <b>Note:</b> Push Notice ka full editor aur live preview ke liye <a href="push_notice" class="fw-bold text-decoration-none">Push Notice page</a> open karein.
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" role="switch" id="push_notice_enabled"
                                                               name="push_notice_enabled" value="1"
                                                               <?= isset($webdata['push_notice_enabled']) && (int)$webdata['push_notice_enabled'] === 1 ? 'checked' : '' ?>>
                                                        <label class="form-check-label fw-semibold" for="push_notice_enabled">
                                                            📢 Notice ON for All Users
                                                        </label>
                                                    </div>
                                                    <div class="form-text">Ye notice sab users ko dikhega (zero balance + wallet balance dono).</div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" role="switch" id="push_notice_wallet_enabled"
                                                               name="push_notice_wallet_enabled" value="1"
                                                               <?= isset($webdata['push_notice_wallet_enabled']) && (int)$webdata['push_notice_wallet_enabled'] === 1 ? 'checked' : '' ?>>
                                                        <label class="form-check-label fw-semibold" for="push_notice_wallet_enabled">
                                                            💰 Wallet Balance Users Notice ON
                                                        </label>
                                                    </div>
                                                    <div class="form-text">Sirf un users ko dikhega jinke wallet me ₹0 se zyada balance hai.</div>
                                                </div>
                                            </div>
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label fw-semibold">All Users Notice Text</label>
                                                    <textarea name="push_notice_text" rows="4" class="form-control"
                                                              placeholder="Notice for all users..."><?= htmlspecialchars($webdata['push_notice_text'] ?? '') ?></textarea>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label fw-semibold">Wallet Users Notice Text</label>
                                                    <textarea name="push_notice_wallet_text" rows="4" class="form-control"
                                                              placeholder="Notice only for wallet balance users..."><?= htmlspecialchars($webdata['push_notice_wallet_text'] ?? '') ?></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- TRICK BUY SERVICE (Admin on/off + prices) -->
                                <div class="col-12 mt-5" id="trick-buy-settings">
                                    <div class="card border-0 shadow-sm">
                                        <div class="card-header bg-white border-bottom">
                                            <h6 class="mb-0 fw-bold"><i class="bi bi-magic me-2 text-indigo"></i> TRICK Buy Service Settings</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="row g-3 align-items-center mb-3">
                                                <div class="col-md-6">
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" role="switch" id="trick_service_enabled"
                                                               name="trick_service_enabled" value="1"
                                                               <?= isset($webdata['trick_service_enabled']) && (int)$webdata['trick_service_enabled'] === 1 ? 'checked' : '' ?>>
                                                        <label class="form-check-label fw-semibold" for="trick_service_enabled">
                                                            TRICK Service Enabled (ON for users, OFF = hidden on frontend)
                                                        </label>
                                                    </div>
                                                    <div class="form-text">Users ko trick_buy_card dikhna hai ya band karna hai.</div>
                                                </div>
                                            </div>
                                            <div class="row g-3">
                                                <div class="col-md-4">
                                                    <label class="form-label fw-semibold">Voter Trick (₹)</label>
                                                    <input type="number" step="0.01" min="0" class="form-control"
                                                           name="trick_fee_voter"
                                                           value="<?= htmlspecialchars($webdata['trick_fee_voter'] ?? '799.00') ?>">
                                                    <div class="form-text">LL PASS</div>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label fw-semibold">LL PASS (₹)</label>
                                                    <input type="number" step="0.01" min="0" class="form-control"
                                                           name="trick_fee_dl"
                                                           value="<?= htmlspecialchars($webdata['trick_fee_dl'] ?? '799.00') ?>">
                                                    <div class="form-text">LL PASS service</div>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label fw-semibold">LL Pass (₹)</label>
                                                    <input type="number" step="0.01" min="0" class="form-control"
                                                           name="trick_fee_ll"
                                                           value="<?= htmlspecialchars($webdata['trick_fee_ll'] ?? '799.00') ?>">
                                                    <div class="form-text">LL LL PassE</div>
                                                </div>
                                            </div>
                                            <div class="mt-3">
                                                <a class="btn btn-sm btn-outline-primary" target="_blank" href="trick_buy_admin"><i class="bi bi-grid-3x3-gap me-1"></i> View TRICK Orders Admin</a>
                                                <a class="btn btn-sm btn-outline-secondary ms-1" target="_blank" href="trick_buy_card"><i class="bi bi-box-arrow-up-right me-1"></i> Preview Trick Buy Card (User UI)</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- REFERRAL BONUS AMOUNT -->
                                <div class="col-12 mt-5" id="referral-bonus-settings">
                                    <div class="card border-0 shadow-sm">
                                        <div class="card-header bg-white border-bottom">
                                            <h6 class="mb-0 fw-bold"><i class="fa-solid fa-gift me-2" style="color:#a855f7;"></i> Refer & Earn — Bonus Settings</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="row g-3 align-items-center">
                                                <div class="col-md-6">
                                                    <label class="form-label fw-semibold">Referral Bonus Per User (₹)</label>
                                                    <input type="number" step="0.01" min="0" class="form-control"
                                                           name="ref_bal"
                                                           value="<?= htmlspecialchars($webdata['ref_bal'] ?? '10') ?>"
                                                           placeholder="Default: 10">
                                                    <div class="form-text">
                                                        Jab koi naya user apne refer link se register karke <b>first time deposit</b> karega, to referer ko itna ₹ bonus auto credit hoga.
                                                        <br>0 rakhne pe referral bonus system OFF ho jayega.
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="alert alert-light border mb-0" style="background:#faf5ff;">
                                                        <p class="mb-1 small">
                                                            <i class="fa-solid fa-circle-info me-1 text-purple"></i>
                                                            <b>Note:</b> Bonus sirf <b>ek baar</b> har naye user ke liye milega — sirf register karne se nahi,
                                                            <b>deposit hona zaroori hai</b>.
                                                        </p>
                                                        <a href="referral_settings.php" class="btn btn-sm btn-outline-primary mt-2">
                                                            <i class="fa-solid fa-arrow-up-right-from-square me-1"></i>
                                                            Dedicated Referral Bonus Page Open Karein
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            <!-- Note -->
                            <div class="col-12 mt-4">
                                <div class="alert alert-info border-0 bg-light">
                                    <strong>Note:</strong> PARI PRINT settings alag rahengi aur PariPrint API key alag se save hogi.<br>
                                    PariPrint key save karte hi ye services ek saath update ho jayengi:
                                    <span class="text-primary fw-bold">Instant Pan Find, Instant Number Link With Voter, Instant RC Pdf, Instant DL Pdf, Voter PDF Download, LL Exam Pass, Balance Check</span><br>
                                    &#128187; Powered by <span class="text-primary">PARI PRINT + PARIPRINT.IN</span><br>
                                    &#128640; Developed by <span class="text-primary">LALA WEB DEVELOPER</span>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="col-12 text-center mt-4">
                                <button type="submit" class="btn btn-primary px-5 py-2 fw-bold">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!--end page wrapper -->

<?php
// ✅ Safe footer include
include(__DIR__ . '/footer.php');
?>

<!-- JavaScripts -->
<!-- IMPORTANT: All custom Central Manager code is now in EXTERNAL .js file to AVOID permanent classes of bugs:
     1) PHP mis-parsing dollar-openCurly pairs inside JS code as PHP variable syntax
     2) PHP short_open_tag mis-fire on LESS-THAN QUESTION pairs in JS code or comments
     3) HTML parser seeing close-tag script words inside literal text or comments and prematurely ending block
        (causes remaining JS text to be RENDERED as page HTML, breaking sidebar / metismenu / menu links / buttons)
     External file = HTML/PHP parser NEVER scans inside its content. Zero chance of these bugs ever again.
-->
<script src="../template/ahkweb/assets/js/central-manager.js?v=20260731-110000"></script>
<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>

<?php
// userFooter optional include (safe)
if (file_exists(__DIR__ . '/userFooter.php')) {
    include(__DIR__ . '/userFooter.php');
}
?>
</body>
</html>
