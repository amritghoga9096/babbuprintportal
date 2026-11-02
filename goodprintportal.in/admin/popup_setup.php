<?php
@ob_start();
include('../includes/session.php');
include('../includes/config.php');

// Admin check
if (checkAdmin($udata['type']) == false) {
    ?><script>window.location='index.php';</script><?php die();
}

// ============================================================================
// DB self-heal: ensure login_popup_settings table exists (same as header.php logic)
// ============================================================================
function _popupSetupEnsureTable($conn) {
    static $done = null; if ($done === true) return;
    @mysqli_report(MYSQLI_REPORT_OFF);
    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS login_popup_settings (
        id INT PRIMARY KEY AUTO_INCREMENT,
        enabled TINYINT(1) NOT NULL DEFAULT 0,
        show_once_per_session TINYINT(1) NOT NULL DEFAULT 1,
        title VARCHAR(255) NOT NULL DEFAULT '🎉 Important Updates',
        cards_json LONGTEXT NOT NULL,
        updated_at DATETIME NULL,
        updated_by INT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
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
    // Ensure 1 row
    $chk = @mysqli_query($conn, "SELECT id FROM login_popup_settings ORDER BY id ASC LIMIT 1");
    if (!$chk || mysqli_num_rows($chk) === 0) {
        $defaultCards = [
            ['enabled'=>1,'icon'=>'✅','title'=>'Voter PDF (Without OTP)','desc'=>'Unlimited Data लगाओ और Super Fast PDF पाओ!','bg'=>'linear-gradient(135deg,#16a34a 0%,#15803d 100%)','tx'=>'#ffffff','border'=>'#86efac'],
            ['enabled'=>1,'icon'=>'🚀','title'=>'LL Exam Data','desc'=>'Unlimited Data उपलब्ध है, एकदम Fast Result!','bg'=>'linear-gradient(135deg,#f97316 0%,#ea580c 100%)','tx'=>'#ffffff','border'=>'#fdba74'],
            ['enabled'=>1,'icon'=>'🎧','title'=>'All Services Working','desc'=>'सभी सर्विसेज काम कर रही हैं। कोई Issue हो तो तुरंत SMS/WhatsApp करें।','bg'=>'linear-gradient(135deg,#7e22ce 0%,#6b21a8 100%)','tx'=>'#ffffff','border'=>'#d8b4fe'],
            ['enabled'=>1,'icon'=>'💳','title'=>'Auto Refund System','desc'=>'अगर कोई सर्विस नहीं मिली, तो 24 Hours इंतज़ार करें, पैसा Auto-Refund हो जाएगा!','bg'=>'linear-gradient(135deg,#be185d 0%,#9d174d 100%)','tx'=>'#ffffff','border'=>'#f9a8d4'],
            ['enabled'=>1,'icon'=>'⚠️','title'=>'NOTE','desc'=>'हमारे पोर्टल के साथ बेफ़िक्र होकर अपनी कमाई बढ़ाओ! धन्यवाद 🙏','bg'=>'linear-gradient(135deg,#fef3c7 0%,#fde68a 100%)','tx'=>'#92400e','border'=>'#f59e0b'],
        ];
        $json = mysqli_real_escape_string($conn, json_encode($defaultCards, JSON_UNESCAPED_UNICODE));
        @mysqli_query($conn, "INSERT INTO login_popup_settings (id,enabled,show_once_per_session,title,cards_json) VALUES (1,1,1,'🎉 Welcome — Important Updates','{$json}')");
    }
    $done = true;
}
_popupSetupEnsureTable($ahk_conn);

// ============================================================================
// SAVE HANDLER
// ============================================================================
$savedMsg = '';
$saveError = '';

if (isset($_POST['save_popup'])) {
    $masterEnabled = isset($_POST['popup_enabled']) ? 1 : 0;
    $showOnce = isset($_POST['popup_show_once']) ? 1 : 1; // default 1 if missing
    if (isset($_POST['popup_show_once'])) $showOnce = $_POST['popup_show_once'] === 'on' || $_POST['popup_show_once'] === '1' ? 1 : 0;
    $title = trim((string)($_POST['popup_title'] ?? '🎉 Welcome — Important Updates'));
    if ($title === '') $title = '🎉 Important Updates';

    // Sanitize 5 cards
    $cards = [];
    for ($i = 0; $i < 5; $i++) {
        $cardEnabled = isset($_POST['card_enabled_'.$i]) ? 1 : 0;
        $icon   = trim((string)($_POST['card_icon_'.$i]   ?? ''));
        $cTitle = trim((string)($_POST['card_title_'.$i]  ?? ''));
        $desc   = trim((string)($_POST['card_desc_'.$i]   ?? ''));
        $bg     = trim((string)($_POST['card_bg_'.$i]     ?? ''));
        $tx     = trim((string)($_POST['card_tx_'.$i]     ?? ''));
        $border = trim((string)($_POST['card_border_'.$i] ?? ''));
        // Defaults fallback
        $defaults = [
            ['enabled'=>1,'icon'=>'✅','title'=>'Card '.($i+1),'desc'=>'','bg'=>'linear-gradient(135deg,#6366f1 0%,#8b5cf6 100%)','tx'=>'#fff','border'=>'#c4b5fd'],
            ['enabled'=>1,'icon'=>'🚀','title'=>'Card '.($i+1),'desc'=>'','bg'=>'linear-gradient(135deg,#f97316 0%,#ea580c 100%)','tx'=>'#fff','border'=>'#fdba74'],
            ['enabled'=>1,'icon'=>'🎧','title'=>'Card '.($i+1),'desc'=>'','bg'=>'linear-gradient(135deg,#7e22ce 0%,#6b21a8 100%)','tx'=>'#fff','border'=>'#d8b4fe'],
            ['enabled'=>1,'icon'=>'💳','title'=>'Card '.($i+1),'desc'=>'','bg'=>'linear-gradient(135deg,#be185d 0%,#9d174d 100%)','tx'=>'#fff','border'=>'#f9a8d4'],
            ['enabled'=>1,'icon'=>'⚠️','title'=>'NOTE','desc'=>'','bg'=>'linear-gradient(135deg,#fef3c7 0%,#fde68a 100%)','tx'=>'#92400e','border'=>'#f59e0b'],
        ];
        $d = $defaults[$i];
        $cards[] = [
            'enabled' => $cardEnabled,
            'icon'   => $icon   !== '' ? $icon   : $d['icon'],
            'title'  => $cTitle !== '' ? $cTitle : $d['title'],
            'desc'   => $desc,
            'bg'     => $bg     !== '' ? $bg     : $d['bg'],
            'tx'     => $tx     !== '' ? $tx     : $d['tx'],
            'border' => $border !== '' ? $border : $d['border'],
        ];
    }

    $titleE = mysqli_real_escape_string($ahk_conn, $title);
    $jsonE  = mysqli_real_escape_string($ahk_conn, json_encode($cards, JSON_UNESCAPED_UNICODE));
    $uidE   = (int)($udata['id'] ?? 0);

    $upd = @mysqli_query($ahk_conn, "UPDATE login_popup_settings SET
        enabled = {$masterEnabled},
        show_once_per_session = {$showOnce},
        title = '{$titleE}',
        cards_json = '{$jsonE}',
        updated_at = NOW(),
        updated_by = {$uidE}
        ORDER BY id ASC LIMIT 1");

    if ($upd) {
        $savedMsg = "✅ Popup settings saved successfully!";
    } else {
        $saveError = "❌ Save failed: " . mysqli_error($ahk_conn);
    }
}

// ============================================================================
// LOAD SETTINGS FOR FORM
// ============================================================================
$settings = [
    'enabled' => 0,
    'show_once_per_session' => 1,
    'title' => '🎉 Welcome — Important Updates',
    'cards' => [
        ['enabled'=>1,'icon'=>'✅','title'=>'Voter PDF (Without OTP)','desc'=>'Unlimited Data लगाओ और Super Fast PDF पाओ!','bg'=>'linear-gradient(135deg,#16a34a 0%,#15803d 100%)','tx'=>'#ffffff','border'=>'#86efac'],
        ['enabled'=>1,'icon'=>'🚀','title'=>'LL Exam Data','desc'=>'Unlimited Data उपलब्ध है, एकदम Fast Result!','bg'=>'linear-gradient(135deg,#f97316 0%,#ea580c 100%)','tx'=>'#ffffff','border'=>'#fdba74'],
        ['enabled'=>1,'icon'=>'🎧','title'=>'All Services Working','desc'=>'सभी सर्विसेज काम कर रही हैं। कोई Issue हो तो तुरंत SMS/WhatsApp करें।','bg'=>'linear-gradient(135deg,#7e22ce 0%,#6b21a8 100%)','tx'=>'#ffffff','border'=>'#d8b4fe'],
        ['enabled'=>1,'icon'=>'💳','title'=>'Auto Refund System','desc'=>'अगर कोई सर्विस नहीं मिली, तो 24 Hours इंतज़ार करें, पैसा Auto-Refund हो जाएगा!','bg'=>'linear-gradient(135deg,#be185d 0%,#9d174d 100%)','tx'=>'#ffffff','border'=>'#f9a8d4'],
        ['enabled'=>1,'icon'=>'⚠️','title'=>'NOTE','desc'=>'हमारे पोर्टल के साथ बेफ़िक्र होकर अपनी कमाई बढ़ाओ! धन्यवाद 🙏','bg'=>'linear-gradient(135deg,#fef3c7 0%,#fde68a 100%)','tx'=>'#92400e','border'=>'#f59e0b'],
    ],
    'updated_at' => null,
];
$res = @mysqli_query($ahk_conn, "SELECT * FROM login_popup_settings ORDER BY id ASC LIMIT 1");
if ($res && ($row = mysqli_fetch_assoc($res))) {
    $cards = json_decode($row['cards_json'] ?? '', true);
    if (!is_array($cards)) $cards = [];
    $normalized = [];
    for ($i = 0; $i < 5; $i++) {
        $c = $cards[$i] ?? [];
        $d = $settings['cards'][$i];
        $normalized[] = [
            'enabled' => (int)($c['enabled'] ?? $d['enabled'] ?? 1),
            'icon'   => (string)($c['icon']   ?? $d['icon']),
            'title'  => (string)($c['title']  ?? $d['title']),
            'desc'   => (string)($c['desc']   ?? $d['desc']),
            'bg'     => (string)($c['bg']     ?? $d['bg']),
            'tx'     => (string)($c['tx']     ?? $d['tx']),
            'border' => (string)($c['border'] ?? $d['border']),
        ];
    }
    $settings = [
        'enabled' => (int)($row['enabled'] ?? 0),
        'show_once_per_session' => (int)($row['show_once_per_session'] ?? 1),
        'title' => (string)($row['title'] ?? $settings['title']),
        'cards' => $normalized,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

// ============================================================================
// RENDER PAGE
// ============================================================================
$pageTitle = 'Popup Setup';

// ==========================================
// INCLUDE PROPER TEMPLATE HEADER (push_notice.php ke style me)
// ==========================================
include(__DIR__ . '/../template/ahkweb/header.php');
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="page-wrapper">
<div class="page-content">
    <div class="row">
        <div class="col-12 col-xl-10 mx-auto">

            <!-- Header banner -->
            <div class="card radius-15 overflow-hidden mb-4">
                <div class="card-body" style="background:linear-gradient(135deg,#6366f1 0%,#8b5cf6 45%,#ec4899 100%);color:#fff;padding:22px 26px;">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div>
                            <h2 class="mb-1" style="font-weight:800;letter-spacing:.3px;text-shadow:0 1px 3px rgba(0,0,0,.15);">
                                <i class="fa-solid fa-wand-magic-sparkles me-2"></i>Login Popup Setup
                            </h2>
                            <p class="mb-0" style="opacity:.92;font-weight:500;">
                                Customize the popup shown to every user after login. Turn it ON/OFF anytime.
                                <?php if (!empty($settings['updated_at'])): ?>
                                    <span class="ms-2" style="background:rgba(255,255,255,.15);padding:3px 10px;border-radius:999px;font-size:12px;">
                                        Last updated: <?= date('d M, h:i A', strtotime($settings['updated_at'])) ?>
                                    </span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <span style="display:inline-flex;align-items:center;gap:7px;padding:5px 14px;border-radius:999px;font-weight:800;font-size:13px;letter-spacing:.4px;background:<?= $settings['enabled'] ? '#16a34a' : '#6b7280' ?>;color:#fff;box-shadow:0 4px 12px rgba(0,0,0,.2);">
                                <span style="width:8px;height:8px;border-radius:50%;background:#fff;animation:popupPulse 2s ease-in-out infinite;"></span>
                                Popup: <?= $settings['enabled'] ? 'ACTIVE' : 'OFF' ?>
                            </span>
                            <a href="javascript:;" id="previewPopupBtn" class="btn btn-light btn-sm" style="font-weight:700;border-radius:999px;padding:6px 18px;">
                                <i class="fa-solid fa-eye me-1"></i>Preview Popup
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($savedMsg !== ''): ?>
            <div class="alert alert-success border-0 mb-3" style="background:linear-gradient(135deg,#dcfce7,#bbf7d0);color:#166534;font-weight:600;border-radius:12px;">
                <?= $savedMsg ?>
            </div>
            <?php endif; ?>
            <?php if ($saveError !== ''): ?>
            <div class="alert alert-danger border-0 mb-3" style="background:linear-gradient(135deg,#fee2e2,#fecaca);color:#991b1b;font-weight:600;border-radius:12px;">
                <?= $saveError ?>
            </div>
            <?php endif; ?>

            <form method="POST" id="popupForm">
                <!-- Master toggles card -->
                <div class="card radius-15 mb-4 border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h5 class="mb-3" style="font-weight:700;color:#1f2937;">
                            <i class="fa-solid fa-sliders me-2" style="color:#8b5cf6;"></i>Master Settings
                        </h5>
                        <div class="row g-4 align-items-center">
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark mb-0">Enable Login Popup</label>
                                <small class="text-muted d-block mb-2">Turn ON to show popup after every user login.</small>
                            </div>
                            <div class="col-md-2 d-flex align-items-center">
                                <div class="form-check form-switch" style="transform:scale(1.25);transform-origin:left;">
                                    <input class="form-check-input" type="checkbox" role="switch" id="popup_enabled" name="popup_enabled"
                                        style="width:3.2rem;height:1.6rem;cursor:pointer;" <?= $settings['enabled'] ? 'checked' : '' ?>>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold text-dark mb-0">Show once per session</label>
                                <small class="text-muted d-block mb-2">Show popup once on login (not on every page).</small>
                            </div>
                            <div class="col-md-3 d-flex align-items-center">
                                <div class="form-check form-switch" style="transform:scale(1.25);transform-origin:left;">
                                    <input class="form-check-input" type="checkbox" role="switch" id="popup_show_once" name="popup_show_once"
                                        style="width:3.2rem;height:1.6rem;cursor:pointer;" <?= $settings['show_once_per_session'] ? 'checked' : '' ?>>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold text-dark">Popup Header Title</label>
                                <input type="text" class="form-control" name="popup_title" id="popup_title"
                                    value="<?= htmlspecialchars($settings['title']) ?>"
                                    style="border-radius:10px;border:1.5px solid #e5e7eb;font-weight:600;padding:10px 14px;"
                                    placeholder="e.g. 🎉 Welcome! — Important Updates">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cards editor -->
                <div class="card radius-15 mb-4 border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
                            <h5 class="mb-0" style="font-weight:700;color:#1f2937;">
                                <i class="fa-solid fa-layer-group me-2" style="color:#ec4899;"></i>Popup Cards (5 Cards)
                            </h5>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="applyPresetBtn" style="border-radius:999px;font-weight:600;">
                                    <i class="fa-solid fa-palette me-1"></i>Apply Color Presets
                                </button>
                            </div>
                        </div>
                        <div class="row g-4">
                        <?php for ($i = 0; $i < 5; $i++):
                            $c = $settings['cards'][$i]; ?>
                            <div class="col-xl-6 col-12">
                                <div class="card border-0 h-100" style="background:linear-gradient(135deg,#fafbff 0%,#f5f3ff 100%);border-radius:14px;border:1.5px dashed #c7d2fe;">
                                    <div class="card-body p-4">
                                        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
                                            <div class="d-flex align-items-center gap-3">
                                                <h6 class="mb-0" style="font-weight:800;color:#4338ca;">
                                                    Card <?= $i + 1 ?>
                                                </h6>
                                                <span class="badge rounded-pill" style="background:<?= !empty($c['enabled']) ? 'linear-gradient(135deg,#16a34a,#15803d)' : 'linear-gradient(135deg,#6b7280,#9ca3af)' ?>;color:#fff;font-size:11px;font-weight:800;padding:4px 12px;letter-spacing:.3px;">
                                                    <?= !empty($c['enabled']) ? '● ON' : '○ OFF' ?>
                                                </span>
                                            </div>
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="form-check form-switch mb-0" style="transform:scale(1.15);transform-origin:right;">
                                                    <input class="form-check-input card-enable-switch" type="checkbox" role="switch"
                                                        data-card="<?= $i ?>" name="card_enabled_<?= $i ?>"
                                                        style="width:2.6rem;height:1.3rem;cursor:pointer;"
                                                        <?= !empty($c['enabled']) ? 'checked' : '' ?>>
                                                </div>
                                                <div style="width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:22px;background:<?= htmlspecialchars($c['bg']) ?>;color:<?= htmlspecialchars($c['tx']) ?>;border:2px dashed <?= htmlspecialchars($c['border']) ?>;" class="card-preview-<?= $i ?>">
                                                    <?= htmlspecialchars($c['icon']) ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-disabled-dim" data-card-dim="<?= $i ?>" style="<?= !empty($c['enabled']) ? '' : 'pointer-events:none;opacity:.45;filter:grayscale(.3);' ?>">
                                        <div class="row g-3">
                                            <div class="col-sm-4">
                                                <label class="form-label fw-bold text-dark small">Icon (Emoji)</label>
                                                <input type="text" class="form-control card-field-<?= $i ?>" data-card="<?= $i ?>" data-field="icon"
                                                    name="card_icon_<?= $i ?>" value="<?= htmlspecialchars($c['icon']) ?>"
                                                    style="border-radius:8px;border:1.5px solid #e5e7eb;font-weight:700;text-align:center;font-size:16px;">
                                            </div>
                                            <div class="col-sm-8">
                                                <label class="form-label fw-bold text-dark small">Card Title</label>
                                                <input type="text" class="form-control card-field-<?= $i ?>" data-card="<?= $i ?>" data-field="title"
                                                    name="card_title_<?= $i ?>" value="<?= htmlspecialchars($c['title']) ?>"
                                                    style="border-radius:8px;border:1.5px solid #e5e7eb;font-weight:700;">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label fw-bold text-dark small">Description Text</label>
                                                <textarea class="form-control card-field-<?= $i ?>" data-card="<?= $i ?>" data-field="desc"
                                                    name="card_desc_<?= $i ?>" rows="2"
                                                    style="border-radius:8px;border:1.5px solid #e5e7eb;resize:vertical;font-weight:500;"><?= htmlspecialchars($c['desc']) ?></textarea>
                                            </div>
                                            <div class="col-sm-4">
                                                <label class="form-label fw-bold text-dark small">BG Gradient</label>
                                                <input type="text" class="form-control card-bg-input card-field-<?= $i ?>" data-card="<?= $i ?>" data-field="bg"
                                                    name="card_bg_<?= $i ?>" value="<?= htmlspecialchars($c['bg']) ?>"
                                                    style="border-radius:8px;border:1.5px solid #e5e7eb;font-size:11px;font-family:monospace;">
                                            </div>
                                            <div class="col-sm-4">
                                                <label class="form-label fw-bold text-dark small">Text Color</label>
                                                <div class="input-group">
                                                    <input type="color" class="form-control form-control-color card-color-input card-field-<?= $i ?>" data-card="<?= $i ?>" data-field="tx"
                                                        style="width:42px;padding:0;border-radius:8px 0 0 8px;" value="<?= preg_match('/#([0-9a-fA-F]{6}|[0-9a-fA-F]{3})/', $c['tx'], $m) ? $m[0] : '#ffffff' ?>">
                                                    <input type="text" class="form-control card-field-<?= $i ?>" data-card="<?= $i ?>" data-field="tx-text"
                                                        name="card_tx_<?= $i ?>" value="<?= htmlspecialchars($c['tx']) ?>"
                                                        style="border-radius:0 8px 8px 0;border:1.5px solid #e5e7eb;font-family:monospace;font-size:11px;font-weight:600;">
                                                </div>
                                            </div>
                                            <div class="col-sm-4">
                                                <label class="form-label fw-bold text-dark small">Border Color</label>
                                                <div class="input-group">
                                                    <input type="color" class="form-control form-control-color card-color-input card-field-<?= $i ?>" data-card="<?= $i ?>" data-field="border"
                                                        style="width:42px;padding:0;border-radius:8px 0 0 8px;" value="<?= preg_match('/#([0-9a-fA-F]{6}|[0-9a-fA-F]{3})/', $c['border'], $m2) ? $m2[0] : '#86efac' ?>">
                                                    <input type="text" class="form-control card-field-<?= $i ?>" data-card="<?= $i ?>" data-field="border-text"
                                                        name="card_border_<?= $i ?>" value="<?= htmlspecialchars($c['border']) ?>"
                                                        style="border-radius:0 8px 8px 0;border:1.5px solid #e5e7eb;font-family:monospace;font-size:11px;font-weight:600;">
                                                </div>
                                            </div>
                                        </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endfor; ?>
                        </div>
                    </div>
                </div>

                <!-- Save buttons -->
                <div class="card radius-15 border-0 shadow-sm mb-4">
                    <div class="card-body p-4 d-flex flex-wrap justify-content-between gap-3 align-items-center">
                        <p class="mb-0 text-muted small">
                            <i class="fa-solid fa-circle-info me-1"></i>
                            Saved changes apply to every user immediately.
                        </p>
                        <div class="d-flex gap-2">
                            <button type="reset" class="btn btn-outline-secondary" style="border-radius:999px;padding:8px 24px;font-weight:700;">
                                <i class="fa-solid fa-rotate-left me-1"></i>Reset
                            </button>
                            <button type="submit" name="save_popup" class="btn btn-primary" style="background:linear-gradient(135deg,#6366f1 0%,#8b5cf6 50%,#ec4899 100%);border:none;border-radius:999px;padding:8px 32px;font-weight:800;letter-spacing:.3px;box-shadow:0 6px 16px rgba(99,102,241,.4);">
                                <i class="fa-solid fa-floppy-disk me-1"></i>Save Popup Settings
                            </button>
                        </div>
                    </div>
                </div>
            </form>

        </div>
    </div>
</div>

<style>
    @keyframes popupPulse {
        0%,100% { transform:scale(1); opacity:1; }
        50%     { transform:scale(1.4); opacity:.7; }
    }
    .form-switch .form-check-input:checked {
        background: linear-gradient(135deg,#16a34a 0%,#15803d 100%) !important;
        border-color: #15803d !important;
    }
    .form-switch .form-check-input:focus { box-shadow: 0 0 0 .2rem rgba(99,102,241,.25); }
    /* PREVIEW POPUP (same CSS as actual popup in header) */
    #gpPreviewPopupOverlay {
        position: fixed; inset: 0; z-index: 999999;
        background: rgba(0,0,0,.55);
        backdrop-filter: blur(4px);
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px 14px;
        animation: gpPopFadeIn .25s ease;
    }
    @keyframes gpPopFadeIn { from { opacity: 0; } to { opacity: 1; } }
    #gpPreviewPopupModal {
        background: #fff;
        border-radius: 24px;
        width: 100%;
        max-width: 440px;
        max-height: 86vh;
        overflow-y: auto;
        padding: 24px 20px 22px;
        box-shadow: 0 30px 80px rgba(0,0,0,.35), inset 0 1px 0 rgba(255,255,255,.6);
        animation: gpPopRise .4s cubic-bezier(.2,.9,.3,1.2);
        position: relative;
    }
    @keyframes gpPopRise {
        from { transform: translateY(40px) scale(.94); opacity: 0; }
        to   { transform: none; opacity: 1; }
    }
    .gp-popup-close {
        position: absolute; top: 10px; right: 12px;
        width: 34px; height: 34px; border-radius: 50%;
        display: inline-flex; align-items: center; justify-content: center;
        border: none; cursor: pointer;
        background: #f3f4f6; color: #4b5563; font-size: 17px;
        transition: all .18s ease;
    }
    .gp-popup-close:hover { background: #ef4444; color: #fff; transform: rotate(90deg); }
    .gp-popup-title {
        text-align: center;
        font-weight: 800; font-size: 18px;
        margin: 0 0 18px; padding: 0 26px 14px;
        color: #1f2937; letter-spacing: .2px;
        border-bottom: 2px dashed #e5e7eb;
    }
    .gp-popup-cards { display: flex; flex-direction: column; gap: 14px; }
    .gp-popup-card {
        border-radius: 18px;
        padding: 18px 18px 16px;
        position: relative;
        border: 3px dashed;
        box-shadow: 0 6px 18px rgba(0,0,0,.10), inset 0 1px 0 rgba(255,255,255,.35);
        transition: transform .2s ease;
    }
    .gp-popup-card:hover { transform: translateX(4px); }
    .gp-popup-card-head {
        display: flex; align-items: flex-start; gap: 12px;
        font-size: 29px; line-height: 1; margin-bottom: 8px;
    }
    .gp-popup-card-head strong {
        font-size: 20px; font-weight: 800; line-height: 1.2; display: block; margin-top: 4px;
    }
    .gp-popup-card p {
        margin: 0; font-size: 15px; font-weight: 500; line-height: 1.5;
    }
    .gp-popup-gotit {
        margin-top: 18px; text-align: center;
    }
    .gp-popup-gotit button {
        background: linear-gradient(135deg,#6366f1 0%,#8b5cf6 45%,#ec4899 100%);
        color: #fff; border: none; cursor: pointer;
        padding: 10px 36px; border-radius: 999px;
        font-weight: 800; letter-spacing: .5px; font-size: 14px;
        box-shadow: 0 6px 18px rgba(139,92,246,.45);
        transition: all .18s ease;
    }
    .gp-popup-gotit button:hover { transform: translateY(-2px); box-shadow: 0 10px 24px rgba(139,92,246,.55); }
    ::-webkit-scrollbar { width: 8px; }
    ::-webkit-scrollbar-thumb { background: #c7d2fe; border-radius: 10px; }
</style>

<!-- Preview Popup DOM (dynamically filled) -->
<div id="gpPreviewPopupOverlay">
    <div id="gpPreviewPopupModal">
        <button type="button" class="gp-popup-close" id="gpPreviewClose">&times;</button>
        <h3 class="gp-popup-title" id="gpPreviewTitle"></h3>
        <div class="gp-popup-cards" id="gpPreviewCards"></div>
        <div class="gp-popup-gotit">
            <button type="button" id="gpPreviewGotIt">Got it ✅</button>
        </div>
    </div>
</div>

<script>
// ===== Card ON/OFF switches: live toggle badge + dim fields =====
document.querySelectorAll('.card-enable-switch').forEach(function(sw) {
    var cardIdx = sw.dataset.card;
    var badge = sw.closest('.card-body').querySelector('.badge.rounded-pill');
    var dimWrap = document.querySelector('[data-card-dim="' + cardIdx + '"]');

    function applyState() {
        var on = sw.checked;
        if (badge) {
            badge.textContent = on ? '● ON' : '○ OFF';
            badge.style.background = on
                ? 'linear-gradient(135deg,#16a34a,#15803d)'
                : 'linear-gradient(135deg,#6b7280,#9ca3af)';
        }
        if (dimWrap) {
            if (on) {
                dimWrap.style.pointerEvents = '';
                dimWrap.style.opacity = '';
                dimWrap.style.filter = '';
            } else {
                dimWrap.style.pointerEvents = 'none';
                dimWrap.style.opacity = '.45';
                dimWrap.style.filter = 'grayscale(.3)';
            }
        }
    }
    sw.addEventListener('change', applyState);
    applyState();
});

// ===== Live color sync: color picker -> text field =====
document.querySelectorAll('.card-color-input').forEach(function(inp) {
    inp.addEventListener('input', function() {
        var card  = this.dataset.card;
        var field = this.dataset.field;
        var textF = document.querySelector('input[name="card_' + field + '_' + card + '"]');
        if (textF) textF.value = this.value;
    });
});

// ===== Preset colors for all 5 cards =====
document.getElementById('applyPresetBtn').addEventListener('click', function() {
    var presets = [
        {bg:'linear-gradient(135deg,#16a34a 0%,#15803d 100%)',tx:'#ffffff',border:'#86efac'},
        {bg:'linear-gradient(135deg,#f97316 0%,#ea580c 100%)',tx:'#ffffff',border:'#fdba74'},
        {bg:'linear-gradient(135deg,#7e22ce 0%,#6b21a8 100%)',tx:'#ffffff',border:'#d8b4fe'},
        {bg:'linear-gradient(135deg,#be185d 0%,#9d174d 100%)',tx:'#ffffff',border:'#f9a8d4'},
        {bg:'linear-gradient(135deg,#fef3c7 0%,#fde68a 100%)',tx:'#92400e',border:'#f59e0b'},
    ];
    for (var i = 0; i < 5; i++) {
        var p = presets[i];
        var bgI = document.querySelector('input[name="card_bg_' + i + '"]');
        var txT = document.querySelector('input[name="card_tx_' + i + '"]');
        var brT = document.querySelector('input[name="card_border_' + i + '"]');
        if (bgI) bgI.value = p.bg;
        if (txT) txT.value = p.tx;
        if (brT) brT.value = p.border;
        var txC = document.querySelector('input[data-card="' + i + '"][data-field="tx"]');
        var brC = document.querySelector('input[data-card="' + i + '"][data-field="border"]');
        if (txC && txC.type === 'color') txC.value = p.tx;
        if (brC && brC.type === 'color') brC.value = p.border;
    }
    this.animate([{transform:'scale(.95)'},{transform:'scale(1)'}],{duration:200});
});

// ===== Preview popup =====
function readSettingsForPreview() {
    var cards = [];
    for (var i = 0; i < 5; i++) {
        var enSw = document.querySelector('input[name="card_enabled_' + i + '"]');
        cards.push({
            enabled: enSw ? enSw.checked : true,
            icon:   (document.querySelector('input[name="card_icon_' + i + '"]')   || {}).value || '✅',
            title:  (document.querySelector('input[name="card_title_' + i + '"]')  || {}).value || 'Card '+(i+1),
            desc:   (document.querySelector('textarea[name="card_desc_' + i + '"]')|| {}).value || '',
            bg:     (document.querySelector('input[name="card_bg_' + i + '"]')     || {}).value || 'linear-gradient(135deg,#6366f1,#8b5cf6)',
            tx:     (document.querySelector('input[name="card_tx_' + i + '"]')     || {}).value || '#fff',
            border: (document.querySelector('input[name="card_border_' + i + '"]') || {}).value || '#c4b5fd'
        });
    }
    return {
        title: (document.getElementById('popup_title') || {}).value || '🎉 Updates',
        cards: cards
    };
}
function renderPreviewPopup() {
    var s = readSettingsForPreview();
    document.getElementById('gpPreviewTitle').textContent = s.title;
    var wrap = document.getElementById('gpPreviewCards');
    wrap.innerHTML = '';
    s.cards.forEach(function(c) {
        if (!c.enabled) return;  // sirf enabled cards hi preview me
        var el = document.createElement('div');
        el.className = 'gp-popup-card';
        el.style.background = c.bg;
        el.style.color = c.tx;
        el.style.borderColor = c.border;
        el.innerHTML =
            '<div class="gp-popup-card-head">' +
                '<span>' + (c.icon||'').replace(/</g,'&lt;') + '</span>' +
                '<strong>' + (c.title||'').replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</strong>' +
            '</div>' +
            '<p>' + (c.desc||'').replace(/\n/g,'<br>').replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</p>';
        wrap.appendChild(el);
    });
}
document.getElementById('previewPopupBtn').addEventListener('click', function() {
    renderPreviewPopup();
    document.getElementById('gpPreviewPopupOverlay').style.display = 'flex';
});
function closePreview() {
    document.getElementById('gpPreviewPopupOverlay').style.display = 'none';
}
document.getElementById('gpPreviewClose').addEventListener('click', closePreview);
document.getElementById('gpPreviewGotIt').addEventListener('click', closePreview);
document.getElementById('gpPreviewPopupOverlay').addEventListener('click', function(e) {
    if (e.target === this) closePreview();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closePreview();
});
</script>

</div>
</div>

<?php include(__DIR__ . '/../template/ahkweb/footer.php'); ?>
