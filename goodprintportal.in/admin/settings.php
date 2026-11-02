<?php
// ============================================================================
// ⚠️ ERROR REPORTING ON — ab error dikhega, blank page nahi (WSOD fix)
//   — BUT FOR AJAX JSON REQUESTS: we disable display_errors at handler level.
// ============================================================================
if (function_exists('ini_set')) {
    @ini_set('display_errors', 1);
    @ini_set('display_startup_errors', 1);
    @ini_set('log_errors', 1);
    @ini_set('error_log', dirname(__DIR__) . '/storage/php-errors.log');
    @ini_set('zlib.output_compression', 'Off');
}
error_reporting(E_ALL);
// Register shutdown handler for FATAL errors (WSOD catcher)
register_shutdown_function(function () {
    // If we are inside AJAX handler (Content-Type JSON) — already exited already; skip this.
    $ct = null;
    foreach (headers_list() as $h) {
        if (stripos($h, 'Content-Type:') === 0) { $ct = trim(substr($h,13)); break; }
    }
    if ($ct && stripos($ct, 'application/json') === 0) return; // let our own JSON shutdown handler (if any) used.
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_RECOVERABLE_ERROR], true)) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
        }
        $msg = $err['message'] . ' in <b>' . $err['file'] . '</b> line <b>' . $err['line'] . '</b>';
        echo '<!doctype html><html><head><title>Fatal Error</title><meta charset="utf-8"><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><div class="container mt-5"><div class="card border-0 shadow"><div class="card-body p-4"><h3 class="text-danger mb-2"><i class="bi bi-exclamation-triangle"></i> PHP Fatal Error</h3><pre class="bg-light p-3 rounded overflow-auto">'.htmlspecialchars($msg).'</pre><p class="text-muted mb-0 small">Agar ye error settings save karne par aa raha hai, neeche "Actual SQL Error" aur "Posted Values" check karo. Fix karne ke baad reload karo.</p></div></div></div></body></html>';
    }
});

// ============================================================================
// 🔴 GLOBAL OUTPUT BUFFER (CRITICAL for AJAX JSON safety:
//    auto_prepend_file / framework helpers / earlier includes AKSAR
//    <script src=...sweetalert...>  etc inject kar dete hain BEFORE we exit.
//    OB woh sab BUFFER me rahenge, _cdm_ajax_json() me CLEAN + exit.
// ============================================================================
@ob_start();
// Silence any zlib / ob_gzhandler confusion with ajax:
if (function_exists('ini_set')) @ini_set('zlib.output_compression', 'Off');

include('../includes/session.php');
include('../includes/config.php');

// Ensure storage dir exists for error log
if (!is_dir(dirname(__DIR__) . '/storage')) {
    @mkdir(dirname(__DIR__) . '/storage', 0755, true);
}

// Admin check - exact same pattern as LogoUpdate / pricing.php
if(checkAdmin($udata['type']) == false){
    ?>
    <script>
    window.location='index.php';
    </script>
    <?php
    die();
}

// ============================================================================
// AJAX HANDLERS for Central API Domain Manager (early return JSON)
// ============================================================================
function _cdm_ajax_json($payload, $code = 200) {
    // ====================================================================
    // CRITICAL: NUCLEAR output buffer purge.
    // GoodPrintPortal hosting / framework / auto_prepend_file OFTEN injects
    // <script src="sweetalert2..."></script> BEFORE our code even runs here.
    // → We FLUSH + CLEAN all output buffers so ONLY JSON goes to client.
    // ====================================================================
    if (function_exists('ini_set')) {
        @ini_set('display_errors', 0);
        @ini_set('display_startup_errors', 0);
        @ini_set('zlib.output_compression', 'Off');
    }
    // Wipe every level of OB — 100x iterations = safe.
    $max_levels = 200;
    $wiped = 0;
    while (ob_get_level() > 0 && $max_levels-- > 0) {
        // End & clean buffer. Some OB handlers call ob_gzhandler etc — @ suppress.
        @ob_end_clean();
        $wiped++;
    }
    // Also discard any implicit flush leftovers using fresh OB + drop
    @ob_start();
    @ob_end_clean();

    http_response_code((int)$code);
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Content-Encoding: identity');
        header('X-Content-Type-Options: nosniff');
    }
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

// AJAX 1) Register & Get Access Key — calls central_register_site()
if (isset($_POST['central_ajax_register'])) {
    $managerUrl = rtrim(trim($_POST['central_manager_url'] ?? ''), '/');
    if ($managerUrl === '' || !preg_match('#^https?://#i', $managerUrl)) {
        _cdm_ajax_json(['status'=>false,'message'=>'Central Manager URL invalid. Must start with http:// or https://. Paste from Central Dashboard.']);
    }
    if (!function_exists('central_register_site')) {
        _cdm_ajax_json(['status'=>false,'message'=>'Client library central_register_site() missing. Upload includes/central-domain-config.php.']);
    }

    $label = isset($webdata['webname']) ? trim((string)$webdata['webname']) : '';
    if ($label === '') $label = (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'GoodPrintPortal Site');
    $site_domain = $_SERVER['HTTP_HOST'] ?? '';
    if (isset($webdata['phone']) && $webdata['phone']) {
        $contact = $webdata['phone'];
    } elseif (isset($udata['phone']) && $udata['phone']) {
        $contact = $udata['phone'];
    } else {
        $contact = '';
    }
    $admin_email = (isset($webdata['email']) && $webdata['email']) ? trim($webdata['email']) : '';
    if ($admin_email === '' && isset($udata['email']) && $udata['email']) $admin_email = trim($udata['email']);

    // NOTE: central_register_site($managerUrl, $siteDomain, $adminEmail, $label, $contactNo) — ORDER MATTERS
    try {
        $result = central_register_site($managerUrl, $site_domain, $admin_email, $label, $contact);
    } catch (Throwable $e) {
        _cdm_ajax_json([
            'status'  => false,
            'message' => 'PHP Exception during register: ' . $e->getMessage(),
            'debug'   => ['file'=>$e->getFile(),'line'=>$e->getLine()]
        ], 500);
    }
    if ($result['status'] === true) {
        _cdm_ajax_json([
            'status' => true,
            'access_key'           => $result['access_key'],
            'manager_url'          => $managerUrl,
            'site_label'           => $label,
            'manager_instructions' => (isset($result['instructions']) ? $result['instructions'] : ''),
            'note' => 'Access Key shown above AND auto-filled below. Now click "Save Changes" to permanently save to DB.'
        ]);
    } else {
        _cdm_ajax_json([
            'status'=>false,
            'message'=>$result['message'] ?? 'Registration failed.',
            'debug'  => $result['debug'] ?? null
        ]);
    }
}

// AJAX 2) Test Connection — shows current live domains / source
if (isset($_POST['central_ajax_test'])) {
    $managerUrl = rtrim(trim($_POST['central_manager_url'] ?? ''), '/');
    $accessKey  = trim($_POST['central_access_key'] ?? '');

    if (!function_exists('central_debug_info')) {
        _cdm_ajax_json(['status'=>false,'message'=>'Client library central_debug_info() missing. Upload includes/central-domain-config.php.']);
    }

    // Override what's already in DB with whatever user typed into UI (if any)
    $cfgOverride = [];
    if ($managerUrl !== '') $cfgOverride['central_manager_url'] = $managerUrl;
    if ($accessKey  !== '') $cfgOverride['central_access_key']  = $accessKey;

    try {
        $debug = central_debug_info(false, $cfgOverride);
    } catch (Throwable $e) {
        _cdm_ajax_json([
            'status'  => false,
            'message' => 'PHP Exception: ' . $e->getMessage(),
            'debug'   => ['file'=>$e->getFile(),'line'=>$e->getLine()]
        ], 500);
    }
    _cdm_ajax_json([
        'status'     => true,
        'manager_url'=> $debug['config']['manager_url'] ?? '-',
        'has_key'    => !empty($debug['config']['access_key']),
        'cache_path' => $debug['cache_path'] ?? null,
        'resolved'   => $debug['resolved'] ?? null,
        'env'        => $debug['env'] ?? [
            'curl'        => function_exists('curl_init'),
            'allow_url_fopen' => ini_get('allow_url_fopen'),
        ],
        'last_fetch_error' => $debug['last_fetch_error'] ?? null,
        'last_fetch_debug' => $debug['last_fetch_debug'] ?? null,
    ]);
}

// AJAX 3) Force Refresh Cache — bypass disk cache, fetch fresh
if (isset($_POST['central_ajax_refresh'])) {
    $managerUrl = rtrim(trim($_POST['central_manager_url'] ?? ''), '/');
    $accessKey  = trim($_POST['central_access_key'] ?? '');

    if (!function_exists('central_force_refresh') || !function_exists('central_debug_info')) {
        _cdm_ajax_json(['status'=>false,'message'=>'Client library functions missing. Upload includes/central-domain-config.php.']);
    }

    $cfgOverride = [];
    if ($managerUrl !== '') $cfgOverride['central_manager_url'] = $managerUrl;
    if ($accessKey  !== '') $cfgOverride['central_access_key']  = $accessKey;

    try {
        $refresh = central_force_refresh($cfgOverride);
        if ($refresh['status'] !== true) {
            _cdm_ajax_json([
                'status'  => false,
                'message' => $refresh['message'] ?? 'Force refresh failed.',
                'debug'   => $refresh['debug'] ?? null
            ]);
        }
        $debug = central_debug_info(false, $cfgOverride);
    } catch (Throwable $e) {
        _cdm_ajax_json([
            'status'  => false,
            'message' => 'PHP Exception: ' . $e->getMessage(),
            'debug'   => ['file'=>$e->getFile(),'line'=>$e->getLine()]
        ], 500);
    }
    _cdm_ajax_json([
        'status'   => true,
        'resolved' => $debug['resolved'] ?? null,
        'cache_path' => $debug['cache_path'] ?? null
    ]);
}

// ============================================================================
// Handle form POST (BEFORE template include - like pricing controller pattern)
// ============================================================================
if(isset($_POST['webname'])){
    try {
        // 1. Collect ALL possible inputs with safe defaults
        $fields = [
            'webname'                   => trim((string)($_POST['webname'] ?? '')),
            'phone'                     => trim((string)($_POST['phone'] ?? '')),
            'mobile_recharge_active'    => trim((string)($_POST['mobile_recharge_active'] ?? '')),
            'email'                     => trim((string)($_POST['email'] ?? '')),
            'address'                   => trim((string)($_POST['address'] ?? '')),
            'pgtype'                    => trim((string)($_POST['pgtype'] ?? '')),
            'whkey'                     => trim((string)($_POST['whkey'] ?? '')),
            'mid'                       => trim((string)($_POST['mid'] ?? '')),
            'mkey'                      => trim((string)($_POST['mkey'] ?? '')),
            'callback_url'              => trim((string)($_POST['callback_url'] ?? '')),
            'paytm'                     => trim((string)($_POST['paytm'] ?? '')),
            'payu'                      => trim((string)($_POST['payu'] ?? '')),
            'upi'                       => trim((string)($_POST['upi'] ?? '')),
            'payu_mkey'                 => trim((string)($_POST['payu_mkey'] ?? '')),
            'salt'                      => trim((string)($_POST['salt'] ?? '')),
            'upi_key'                   => trim((string)($_POST['upi_key'] ?? '')),
            'qr'                        => trim((string)($_POST['qr'] ?? '1')),
            'upi_id'                    => trim((string)($_POST['upi_id'] ?? '')),
            'nsdl_api_key'              => trim((string)($_POST['api_key'] ?? '')),
            'pariprint_api_key'         => trim((string)($_POST['pariprint_api_key'] ?? '')),
            'auth_url'                  => trim((string)($_POST['auth_url'] ?? '')),
            'api_key'                   => trim((string)($_POST['api_key'] ?? '')),
            'secret_key'                => trim((string)($_POST['secret_key'] ?? '')),
            'HomeTemplatename'          => trim((string)($_POST['HomeTemplatename'] ?? '')),
            'ret_reg_fee'               => trim((string)($_POST['ret_reg_fee'] ?? '')),
            'dist_reg_fee'              => trim((string)($_POST['dist_reg_fee'] ?? '')),
            'airtal'                    => trim((string)($_POST['airtal'] ?? '')),
            'vi'                        => trim((string)($_POST['vi'] ?? '')),
            'jio'                       => trim((string)($_POST['jio'] ?? '')),
            'bsnl'                      => trim((string)($_POST['bsnl'] ?? '')),
            'airtel_digital_tv_dth'     => trim((string)($_POST['airtel_digital_tv_dth'] ?? '')),
            'tata_sky_dth'              => trim((string)($_POST['tata_sky_dth'] ?? '')),
            'dishtv_dth'                => trim((string)($_POST['dishtv_dth'] ?? '')),
            'Videocon_dth'              => trim((string)($_POST['Videocon_dth'] ?? '')),
            'Sun_Direct_dth'            => trim((string)($_POST['Sun_Direct_dth'] ?? '')),
            'google_play'               => trim((string)($_POST['google_play'] ?? '')),
            'central_manager_url'       => rtrim(trim((string)($_POST['central_manager_url'] ?? '')), '/ '),
            'central_access_key'        => trim((string)($_POST['central_access_key'] ?? '')),
            'central_cache_min'         => trim((string)($_POST['central_cache_min'] ?? '60')),
            'trick_service_enabled'     => isset($_POST['trick_service_enabled']) ? '1' : '0',
            'trick_fee_voter'           => trim((string)($_POST['trick_fee_voter'] ?? '799.00')),
            'trick_fee_dl'              => trim((string)($_POST['trick_fee_dl']    ?? '799.00')),
            'trick_fee_ll'              => trim((string)($_POST['trick_fee_ll']    ?? '799.00')),
            'whatsapp_number'           => trim((string)($_POST['whatsapp_number'] ?? '')),
            'push_notice_enabled'       => isset($_POST['push_notice_enabled']) ? '1' : '0',
            'push_notice_text'          => trim((string)($_POST['push_notice_text'] ?? '')),
            'push_notice_wallet_enabled' => isset($_POST['push_notice_wallet_enabled']) ? '1' : '0',
            'push_notice_wallet_text'    => trim((string)($_POST['push_notice_wallet_text'] ?? '')),
            'ref_bal'                   => trim((string)($_POST['ref_bal'] ?? '10')),
            'ref_enabled'               => isset($_POST['ref_enabled']) ? '1' : '0',
        ];
        if ($fields['central_cache_min'] === '' || (int)$fields['central_cache_min'] <= 0) $fields['central_cache_min'] = '60';
        if ((int)$fields['central_cache_min'] > 1440) $fields['central_cache_min'] = '1440';
        // Normalize trick prices to decimal strings (empty default 799.00)
        foreach (['trick_fee_voter','trick_fee_dl','trick_fee_ll'] as $_tfk) {
            if ($fields[$_tfk] === '' || !is_numeric($fields[$_tfk])) {
                $fields[$_tfk] = '799.00';
            } else {
                $fields[$_tfk] = number_format((float)$fields[$_tfk], 2, '.', '');
            }
        }

        // 2. Ensure new columns exist — check FIRST with SHOW COLUMNS, then ADD only if missing.
        //    (MySQL STRICT_TRANS_TABLES mode converts ALTER TABLE warnings into Throwable
        //     even with @ — so avoid ALTER entirely when column already exists!)
        $colsResCheck = @mysqli_query($ahk_conn, "SHOW COLUMNS FROM settings");
        $tableCols = [];
        if ($colsResCheck) {
            while ($r = mysqli_fetch_row($colsResCheck)) $tableCols[] = $r[0];
        }

        // Helper: add column ONLY if not already present
        $addCol = function ($colName, $definition) use ($ahk_conn, $tableCols) {
            if (in_array($colName, $tableCols, true)) return;
            // Temporarily disable mysqli strict reporting for this idempotent schema-change
            $prevReport = function_exists('mysqli_report') ? @mysqli_report(MYSQLI_REPORT_OFF) : null;
            @mysqli_query($ahk_conn, "ALTER TABLE settings ADD COLUMN {$colName} {$definition}");
            if ($prevReport !== null) @mysqli_report($prevReport);
        };

        // Determine placement order (use existing columns as anchors)
        $afterSecret  = in_array('secret_key', $tableCols, true) ? 'AFTER secret_key' : '';
        $afterPp      = in_array('pariprint_api_key', $tableCols, true) ? 'AFTER pariprint_api_key' : $afterSecret;
        $afterMgr     = in_array('central_manager_url', $tableCols, true) ? 'AFTER central_manager_url' : $afterPp;
        $afterAk      = in_array('central_access_key', $tableCols, true) ? 'AFTER central_access_key' : $afterMgr;
        $afterCcm     = in_array('central_cache_min', $tableCols, true)  ? 'AFTER central_cache_min'  : $afterAk;
        $afterTse     = in_array('trick_service_enabled', $tableCols, true) ? 'AFTER trick_service_enabled' : 'afterCcm';
        $afterTfv     = in_array('trick_fee_voter',         $tableCols, true) ? 'AFTER trick_fee_voter'         : 'afterTse';
        $afterTfd     = in_array('trick_fee_dl',            $tableCols, true) ? 'AFTER trick_fee_dl'            : 'afterTfv';
        $afterTfl     = in_array('trick_fee_ll',            $tableCols, true) ? 'AFTER trick_fee_ll'            : 'afterTfd';
        $afterWn      = in_array('whatsapp_number',        $tableCols, true) ? 'AFTER whatsapp_number'        : 'afterTfl';

        $addCol('pariprint_api_key',   "VARCHAR(255) DEFAULT NULL {$afterSecret}");
        $addCol('central_manager_url', "VARCHAR(500) DEFAULT NULL {$afterPp}");
        $addCol('central_access_key',  "VARCHAR(255) DEFAULT NULL {$afterMgr}");
        $addCol('central_cache_min',   "VARCHAR(10)  DEFAULT '60' {$afterAk}");
        $addCol('trick_service_enabled', "TINYINT(1)   DEFAULT 1      {$afterCcm}");
        $addCol('trick_fee_voter',       "DECIMAL(10,2) DEFAULT 799.00 {$afterTse}");
        $addCol('trick_fee_dl',          "DECIMAL(10,2) DEFAULT 799.00 {$afterTfv}");
        $addCol('trick_fee_ll',          "DECIMAL(10,2) DEFAULT 799.00 {$afterTfd}");
        $afterWn = in_array('whatsapp_number',        $tableCols, true) ? 'AFTER whatsapp_number'        : 'afterTfl';
        $afterPt = in_array('push_notice_text',       $tableCols, true) ? 'AFTER push_notice_text'       : '';
        $addCol('whatsapp_number',       "VARCHAR(30)  DEFAULT NULL {$afterTfl}");
        $addCol('push_notice_enabled',   "TINYINT(1)   DEFAULT 1      {$afterWn}");
        $addCol('push_notice_text',      "TEXT         DEFAULT NULL AFTER push_notice_enabled");
        $addCol('push_notice_wallet_enabled', "TINYINT(1)   DEFAULT 0      {$afterPt}");
        $addCol('push_notice_wallet_text',    "TEXT         DEFAULT NULL AFTER push_notice_wallet_enabled");
        $afterPnwt = in_array('push_notice_wallet_text', $tableCols, true) ? 'AFTER push_notice_wallet_text' : '';
        $addCol('ref_bal', "VARCHAR(20)  DEFAULT '10' {$afterPnwt}");
        $afterRefBal = in_array('ref_bal', $tableCols, true) ? 'AFTER ref_bal' : $afterPnwt;
        $addCol('ref_enabled', "TINYINT(1)   NOT NULL DEFAULT 1 {$afterRefBal}");

        // 3. Fetch EXISTING columns from the table → only UPDATE columns that exist (no "unknown column" error)
        $colsRes = @mysqli_query($ahk_conn, "SHOW COLUMNS FROM settings");
        $existingCols = [];
        if ($colsRes) {
            while ($r = mysqli_fetch_row($colsRes)) $existingCols[] = $r[0];
        }
        $setClauses = [];
        foreach ($fields as $col => $rawVal) {
            if (!in_array($col, $existingCols, true)) continue; // skip non-existent columns = no WSOD
            $esc = mysqli_real_escape_string($ahk_conn, $rawVal);
            $setClauses[] = "`{$col}`='{$esc}'";
        }
        if (!$setClauses) {
            throw new Exception('Settings table me koi valid column nahi mila. SHOW COLUMNS check karo.');
        }
        $sql = "UPDATE settings SET " . implode(', ', $setClauses) . " WHERE id=1 LIMIT 1";
        $update = @mysqli_query($ahk_conn, $sql);

        if ($update === false) {
            $err = mysqli_error($ahk_conn);
            // Show SQL + error and then redirect after user sees
            echo '<!doctype html><html><head><meta charset="utf-8"><title>Settings Save Failed</title>';
            echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">';
            echo '</head><body class="bg-light"><div class="container mt-5"><div class="card border-0 shadow"><div class="card-body p-4">';
            echo '<h3 class="text-danger mb-3"><i class="bi bi-x-circle"></i> Settings Save Nahi Hua</h3>';
            echo '<div class="mb-3"><b>MySQL Error:</b><pre class="bg-light border p-3 rounded">' . htmlspecialchars($err) . '</pre></div>';
            echo '<div class="mb-3"><b>SQL Query:</b><pre class="bg-light border p-3 rounded small">' . htmlspecialchars($sql) . '</pre></div>';
            echo '<a href="settings" class="btn btn-primary">← Wapas Settings Page Pe Jao</a>';
            echo '</div></div></div></body></html>';
            exit;
        }

        // 4. SUCCESS — clean redirect (NO template include!)
        if (!headers_sent()) {
            header('Location: settings?saved=1');
            exit;
        } else {
            echo '<script>window.location="settings?saved=1";</script>';
            exit;
        }
    } catch (Throwable $e) {
        echo '<!doctype html><html><head><meta charset="utf-8"><title>Settings Error</title>';
        echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">';
        echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">';
        echo '</head><body class="bg-light"><div class="container mt-5"><div class="card border-0 shadow"><div class="card-body p-4">';
        echo '<h3 class="text-danger mb-3"><i class="bi bi-exclamation-triangle"></i> PHP Error Aaya</h3>';
        echo '<pre class="bg-light border p-3 rounded overflow-auto">' . htmlspecialchars($e->getMessage()) . '</pre>';
        echo '<div class="text-muted small mb-3">File: <code>' . htmlspecialchars($e->getFile()) . '</code> &nbsp; Line: <b>' . (int)$e->getLine() . '</b></div>';
        echo '<a href="settings" class="btn btn-primary">← Wapas Settings Pe Jao</a>';
        echo '</div></div></div></body></html>';
        exit;
    }
}

// ---- GET request (not POST): show success banner if ?saved=1 ----
$showSavedBanner = isset($_GET['saved']) && (string)$_GET['saved'] === '1';

// ============================================================================
// Finally include VIEW TEMPLATE (exact same as LogoUpdate / pricing pattern!)
// ============================================================================
include('../template/ahkweb/settings.php');
