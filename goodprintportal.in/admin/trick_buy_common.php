<?php
// ============================================================================
// TRICK BUY SERVICE — Common Includes + Helpers
// Services: Voter trick (without OTP e-sign), LL PASS, LL App Relation Name
// Flow exactly like Samagra KYC / Married to Divorce service pattern
// ============================================================================

if (!defined('ABSPATH_GP')) define('ABSPATH_GP', __DIR__ . '/..');

// --- Service definitions (admin can edit price individually via settings) ---
function trick_get_services() {
    return [
        'voter_trick_no_otp'     => ['label' => 'LL PASS', 'fee_key' => 'trick_fee_voter',    'default_fee' => 799],
        'dl_mobile_link'         => ['label' => 'LL PASS',                  'fee_key' => 'trick_fee_dl',       'default_fee' => 799],
        'll_relation_name'       => ['label' => 'LL Pass',     'fee_key' => 'trick_fee_ll',       'default_fee' => 799],
    ];
}
function trick_service_label($key){ $s = trick_get_services(); return $s[$key]['label'] ?? $key; }
function trick_service_fee($key, $webdata = null) {
    $svc = trick_get_services();
    if (!isset($svc[$key])) return 0;
    $feeKey = $svc[$key]['fee_key'];
    global $webdata;
    $wd = $webdata;
    if (is_array($wd) && isset($wd[$feeKey]) && is_numeric($wd[$feeKey]) && (float)$wd[$feeKey] > 0) {
        return (float)$wd[$feeKey];
    }
    return (float)$svc[$key]['default_fee'];
}

// --- Ensure DB tables + settings columns exist (run on every page include, safe) ---
function trickEnsureSchema($conn) {
    if (!$conn) return false;
    $ok = true;

    // 1) Ensure master settings columns for service ON/OFF + per-service prices
    static $colsChecked = null;
    if ($colsChecked === null) {
        $cols = [];
        $r = @mysqli_query($conn, "SHOW COLUMNS FROM settings");
        if ($r) while ($row = mysqli_fetch_assoc($r)) $cols[] = $row['Field'];

        $addCol = function($name, $def) use ($conn, $cols) {
            if (in_array($name, $cols, true)) return;
            @mysqli_report(MYSQLI_REPORT_OFF);
            @mysqli_query($conn, "ALTER TABLE settings ADD COLUMN {$name} {$def}");
        };
        // Global on/off
        $addCol('trick_service_enabled', "TINYINT(1) NOT NULL DEFAULT 1");
        // Default fees (editable in Admin Settings)
        $addCol('trick_fee_voter', "DECIMAL(10,2) NOT NULL DEFAULT 799.00");
        $addCol('trick_fee_dl',    "DECIMAL(10,2) NOT NULL DEFAULT 799.00");
        $addCol('trick_fee_ll',    "DECIMAL(10,2) NOT NULL DEFAULT 799.00");
        $colsChecked = true;
    }

    // 2) Create trick_orders table if missing
    $sql = "CREATE TABLE IF NOT EXISTS `trick_orders` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `application_no` VARCHAR(32) NOT NULL,
        `user_id` INT NULL,
        `user_phone` VARCHAR(20) NOT NULL,
        `user_name` VARCHAR(150) NULL,
        `service_key` VARCHAR(50) NOT NULL,
        `service_label` VARCHAR(150) NOT NULL,
        `whatsapp_number` VARCHAR(20) NOT NULL,
        `anydesk_id` VARCHAR(30) NOT NULL,
        `customer_note` VARCHAR(500) NULL,
        `amount` DECIMAL(10,2) NOT NULL DEFAULT 799.00,
        `status` ENUM('pending','processing','completed','rejected','cancelled','refunded') NOT NULL DEFAULT 'pending',
        `admin_note` TEXT NULL,
        `rejection_reason` TEXT NULL,
        `attachment_path` VARCHAR(255) NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_trick_orders_app_no` (`application_no`),
        KEY `idx_trick_orders_user_phone` (`user_phone`),
        KEY `idx_trick_orders_user_id` (`user_id`),
        KEY `idx_trick_orders_service` (`service_key`),
        KEY `idx_trick_orders_status` (`status`),
        KEY `idx_trick_orders_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    @mysqli_report(MYSQLI_REPORT_OFF);
    $ok = @mysqli_query($conn, $sql);

    // 3) Trick attachments dir (cache dir_exists check via static var - filesystem stat is slow)
    static $dirChecked = null;
    if ($dirChecked === null) {
        $dir = __DIR__ . '/uploads/trick_orders';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        if (!is_dir($dir . '/results')) @mkdir($dir . '/results', 0755, true);
        if (!file_exists($dir . '/index.html')) @file_put_contents($dir . '/index.html', '');
        $dirChecked = true;
    }

    return $ok;
}

function trickAppNumber() { return 'TRK' . date('ymdHis') . random_int(100, 999); }

function trickAlert($title, $text, $icon, $redirect = '') {
    $payload = json_encode([
        'title' => $title, 'text' => $text, 'icon' => $icon, 'redirect' => $redirect
    ], JSON_UNESCAPED_UNICODE);
    echo "<script>
        document.addEventListener('DOMContentLoaded', function () {
            var data = {$payload};
            if (typeof Swal !== 'undefined') {
                Swal.fire({title:data.title, text:data.text, icon:data.icon}).then(function () {
                    if (data.redirect) window.location = data.redirect;
                });
            } else {
                alert(data.title + '\\n' + data.text);
                if (data.redirect) window.location = data.redirect;
            }
        });
    </script>";
}

// --- Pending Trick count (admin notification badge) ---
function trick_pending_count($conn) {
    static $cache = null;
    if ($cache !== null) return $cache;
    $r = @mysqli_query($conn, "SELECT COUNT(*) AS c FROM trick_orders WHERE status IN ('pending','processing')");
    $cache = 0;
    if ($r && ($row = mysqli_fetch_assoc($r))) $cache = (int)$row['c'];
    return $cache;
}

// Global init
if (isset($ahk_conn)) {
    trickEnsureSchema($ahk_conn);
    if (empty($webdata)) {
        $wd = @mysqli_query($ahk_conn, "SELECT * FROM settings LIMIT 1");
        if ($wd) $webdata = @mysqli_fetch_assoc($wd) ?: [];
    }
    $GLOBALS['webdata'] = $webdata ?? [];
}
