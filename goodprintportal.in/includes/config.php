<?php
// 1. Session Start sabse upar hona chahiye
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug Mode: Development ke liye temporarily on (production me comment ya off karna)
// ini_set('display_errors', 0); // ← production me 0 rakho
// error_reporting(0);           // ← production me 0 rakho

// ====== PHP 7.4 Compatibility Polyfills (PHP 8.0+ functions) ======
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        if ($needle === '') {
            return true;
        }
        return strpos($haystack, $needle) !== false;
    }
}
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        if ($needle === '') {
            return true;
        }
        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
        if ($needle === '') {
            return true;
        }
        $lenNeedle = strlen($needle);
        return substr($haystack, -$lenNeedle) === $needle;
    }
}
if (!function_exists('array_is_list')) {
    function array_is_list(array $array) {
        if ($array === []) {
            return true;
        }
        return array_keys($array) === range(0, count($array) - 1);
    }
}
// ==================================================================

require_once('database.php');
require_once('links.php');
require_once(__DIR__ . '/central-domain-config.php');

// Server Timezone Set
date_default_timezone_set("Asia/Kolkata");

// ============================================================================
// SITE URL + DOMAIN (Referral link + asset URLs ke liye)
// Fix: $host / $dir undefined the, isliye referral link me domain gayab ho jata tha
// Fix: /admin/register.php BANTA tha (wrong!) — register.php ROOT me hota hai /admin/ ke BAHAR
// ============================================================================
if (!isset($host) || trim((string)$host) === '') {
    // Protocol detect
    $_gpIsHttps = false;
    if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') $_gpIsHttps = true;
    if (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) $_gpIsHttps = true;
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') $_gpIsHttps = true;
    $_gpProto = $_gpIsHttps ? 'https://' : 'http://';

    // Host/Domain detect (3-level fallback: HTTP_HOST → SERVER_NAME → webdata)
    $_gpHostname = '';
    if (!empty($_SERVER['HTTP_HOST'])) $_gpHostname = trim((string)$_SERVER['HTTP_HOST']);
    if ($_gpHostname === '' && !empty($_SERVER['SERVER_NAME'])) $_gpHostname = trim((string)$_SERVER['SERVER_NAME']);

    // ULTIMATE FALLBACK: settings table se koi base URL parse karo (agar HTTP_HOST empty ho — rare CLI/cron)
    if ($_gpHostname === '' && isset($webdata) && is_array($webdata)) {
        foreach (['callback_url', 'auth_url', 'central_manager_url'] as $_gpK) {
            if (!empty($webdata[$_gpK]) && is_string($webdata[$_gpK])) {
                $_gpPu = @parse_url($webdata[$_gpK]);
                if (!empty($_gpPu['host'])) { $_gpHostname = $_gpPu['host']; break; }
            }
        }
    }
    if ($_gpHostname === '') $_gpHostname = 'localhost';
    $_gpHostname = trim($_gpHostname, "/ \t\n\r\0\x0B");

    $host = $_gpHostname;
    $GLOBALS['host'] = $host;
    $GLOBALS['gp_proto'] = $_gpProto;

    // Directory / Path detect (script URL: /admin/xyz.php → dir = /admin/ OR /)
    $_gpDir = '/';
    if (!empty($_SERVER['SCRIPT_NAME'])) {
        $_gpSn = str_replace('\\', '/', (string)$_SERVER['SCRIPT_NAME']);
        $_gpLastSlash = strrpos($_gpSn, '/');
        if ($_gpLastSlash !== false) {
            $_gpDir = substr($_gpSn, 0, $_gpLastSlash + 1);
        }
    }
    // ROOT dir nikalna: /admin/ /template/ /includes/ etc. subfolders ko REMOVE kar do
    // Safe way: explode → filter known non-root folders → implode
    $_gpSegs = array_values(array_filter(explode('/', $_gpDir), 'strlen'));
    $_gpBadFolders = ['admin','template','templates','includes','inc','drpay','paytmUPI','paytm','upi','assets','js','css','images','uploads','public','wp-admin','wp-content','wp-includes','vendor'];
    $_gpRootSegs = [];
    foreach ($_gpSegs as $_s) {
        if (!in_array(strtolower($_s), $_gpBadFolders, true)) $_gpRootSegs[] = $_s;
    }
    $_gpRootDir = '/' . implode('/', $_gpRootSegs);
    if (!str_ends_with($_gpRootDir, '/')) $_gpRootDir .= '/';
    $_gpRootDir = preg_replace('#/{2,}#', '/', $_gpRootDir);
    if ($_gpRootDir === '' || !str_starts_with($_gpRootDir, '/')) $_gpRootDir = '/';
    $dir = $_gpRootDir;
    $GLOBALS['dir'] = $dir;

    // Site URL (general purpose)
    $_gpSitePath = trim(implode('/', $_gpRootSegs), '/');
    $gpSiteUrl = $_gpProto . $host . ($_gpSitePath !== '' ? '/' . $_gpSitePath : '') . '/';
    $gpSiteUrl = preg_replace('#(?<!:)/{2,}#', '/', $gpSiteUrl);
    $gpSiteUrl = str_replace(['http:/', 'https:/'], ['http://', 'https://'], $gpSiteUrl);
    $GLOBALS['gpSiteUrl'] = $gpSiteUrl;

    // ================================================================
    // 🔥 REGISTER URL ALWAYS ROOT ME HAI — /admin/ etc ke BAHAR
    //    isliye $dir / subfolder ko register URL ME NAHI LENGE
    //    Final: https://domain.com/register.php
    // ================================================================
    $gpRegisterFullUrl = $_gpProto . $host . '/register.php';
    $gpRegisterFullUrl = preg_replace('#(?<!:)/{2,}#', '/', $gpRegisterFullUrl);
    $gpRegisterFullUrl = str_replace(['http:/', 'https:/'], ['http://', 'https://'], $gpRegisterFullUrl);
    // Final safety net: agar /admin/register.php ya kisi bhi galat subfolder me register.php ban gaya ho → ROOT kar do
    $gpRegisterFullUrl = preg_replace('#/(admin|template|templates|includes|inc|drpay|paytmUPI|paytm|upi|assets|wp-admin|wp-content|uploads|public|vendor)(/[^/]+)*/register\.php#i', '/register.php', $gpRegisterFullUrl);
    $GLOBALS['gpRegisterFullUrl'] = $gpRegisterFullUrl;

    unset($_gpIsHttps, $_gpProto, $_gpHostname, $_gpDir, $_gpSegs, $_gpBadFolders, $_gpRootSegs, $_gpRootDir, $_gpSitePath);
}

// Helper function: kisi bhi page se refer link generate karne ke liye
if (!function_exists('gp_get_referral_link')) {
    function gp_get_referral_link($referrerPhone = null, $conn = null) {
        global $gpRegisterFullUrl, $gpSiteUrl, $host, $dir, $udata;
        $base = '';
        if (!empty($gpRegisterFullUrl) && is_string($gpRegisterFullUrl)) $base = $gpRegisterFullUrl;
        // Rebuild if empty (sanity)
        if ($base === '' || strpos($base, 'register.php') === false) {
            $p = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ? 'https://' : 'http://';
            $h = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : (!empty($host) ? $host : 'localhost');
            $h = trim((string)$h, "/ \t\n\r\0\x0B");
            $base = $p . $h . '/register.php';
            // Sanitize
            $base = preg_replace('#(?<!:)/{2,}#', '/', $base);
            $base = str_replace(['http:/', 'https:/'], ['http://', 'https://'], $base);
        }
        // FINAL SAFETY NET: kabhi bhi /admin/register.php ya kisi subfolder me register.php na rahe
        $base = preg_replace('#/(admin|template|templates|includes|inc|drpay|paytmUPI|paytm|upi|assets|wp-admin|wp-content|uploads|public|vendor)(/[^/]+)*/register\.php#i', '/register.php', $base);
        if (strpos($base, 'register.php') === false) {
            // ensure it ends with /register.php
            $base = rtrim($base, '/') . '/register.php';
        }
        if ($referrerPhone === null && isset($udata['phone'])) $referrerPhone = $udata['phone'];
        $referrerPhone = trim((string)$referrerPhone);
        if ($referrerPhone === '') return rtrim($base, '?') . '?ref=MA';
        // Standard base64 (NOT URL-safe) — backward compat: register.php old code se bina change decode ho jaye
        return rtrim($base, '?') . '?ref=' . base64_encode($referrerPhone);
    }
}

// ====== MySQL Native Driver (mysqlnd) Compatibility Helpers ======
// mysqli_stmt_get_result() aur related functions bina mysqlnd ke kaam nahi karte
// PHP 7.4 ke kuch cPanel setups mein mysqlnd disabled rehta hai
// Yeh helpers bind_result + metadata ka use karke same behavior dete hain

if (!function_exists('db_stmt_fetch_assoc')) {
function db_stmt_fetch_assoc($stmt) {
    if (function_exists('mysqli_stmt_get_result')) {
        $result = mysqli_stmt_get_result($stmt);
        if (!$result) return null;
        return mysqli_fetch_assoc($result);
    }
    // Fallback: bina mysqlnd ke bind_result ka use karo
    $meta = mysqli_stmt_result_metadata($stmt);
    if (!$meta) return null;
    $fields = mysqli_fetch_fields($meta);
    $row = array();
    $refs = array();
    foreach ($fields as $field) {
        $refs[$field->name] = &$row[$field->name];
    }
    mysqli_free_result($meta);
    call_user_func_array(array($stmt, 'bind_result'), $refs);
    if (!mysqli_stmt_fetch($stmt)) {
        return null;
    }
    $out = array();
    foreach ($row as $k => $v) {
        $out[$k] = $v;
    }
    return $out;
}
}

if (!function_exists('db_stmt_fetch_all_assoc')) {
function db_stmt_fetch_all_assoc($stmt) {
    if (function_exists('mysqli_stmt_get_result')) {
        $result = mysqli_stmt_get_result($stmt);
        if (!$result) return array();
        $all = array();
        while ($row = mysqli_fetch_assoc($result)) {
            $all[] = $row;
        }
        return $all;
    }
    // Fallback: bina mysqlnd ke bind_result ka use karke saare rows collect karo
    $meta = mysqli_stmt_result_metadata($stmt);
    if (!$meta) return array();
    $fields = mysqli_fetch_fields($meta);
    $row = array();
    $refs = array();
    foreach ($fields as $field) {
        $refs[$field->name] = &$row[$field->name];
    }
    mysqli_free_result($meta);
    call_user_func_array(array($stmt, 'bind_result'), $refs);
    $all = array();
    while (mysqli_stmt_fetch($stmt)) {
        $out = array();
        foreach ($row as $k => $v) {
            $out[$k] = $v;
        }
        $all[] = $out;
    }
    return $all;
}
}
// ==================================================================

// ============================================================
// MARK: PERFORMANCE GUARD - prevent double-execution of all
// non-function code (global queries / counts / session fetch)
// when config.php is included more than once per request.
// ============================================================
if (empty($GLOBALS['_config_globals_loaded'])) {
    $GLOBALS['_config_globals_loaded'] = true;

// Global Variable Initialization
$nutype = ''; // Initialize globally taaki functions use kar sakein
$udata = array(); // Initialize to avoid warnings
// Default safety keys - agar DB fetch fail ho jaye bhi to undefined key warnings na aayein
$udata += [
    'id'          => 0,
    'phone'       => '',
    'email'       => '',
    'type'        => 'user',
    'name'        => '',
    'status'      => '0',
    'balance'     => 0,
    'password'    => '',
    'address'     => '',
    'parent'      => '',
    'referred_by' => '',
    'date'        => '',
    'created_at'  => '',
    'last_login'  => '',
    'login_ip'    => '',
    'order_id'    => '',
    'gender'      => '',
    'dob'         => '',
    'pincode'     => '',
    'state'       => '',
    'city'        => '',
];

// ---- DYNAMIC DOMAINS (fetched from Central Manager, with caching + fallback) ----
// Koi bhi future change sirf Central Manager me karo - 100+ websites auto-update
$CENTRAL_API_BASE     = function_exists('get_central_api_domain')     ? rtrim(get_central_api_domain(), '/')     : 'https://pariprint.in';
$CENTRAL_GATEWAY_BASE = function_exists('get_central_gateway_domain') ? rtrim(get_central_gateway_domain(), '/') : 'https://gateway.pdfgods.in';

$pariprint_instant_voter_endpoint = $CENTRAL_API_BASE . '/api-proxy.php';
$pariprint_instant_pan_endpoint   = $CENTRAL_API_BASE . '/api-proxy.php';
$pariprint_instant_dl_endpoint    = $CENTRAL_API_BASE . '/api-proxy.php';
$pariprint_instant_rc_endpoint    = $CENTRAL_API_BASE . '/api-proxy.php';

// Fetch Website Settings Data
$webres = mysqli_query($ahk_conn, "SELECT * FROM settings WHERE id=1 AND status=1 LIMIT 1");
$webdata = $webres ? mysqli_fetch_assoc($webres) : [];
if (!$webdata) { $webdata = []; }

// ====================== PARI PRINT CONFIGURATION ======================
// API Configuration
$auth_url          = $webdata['auth_url']            ?? 'https://apiadda.in/';
$api_key           = $webdata['api_key']             ?? '';
$secret_key        = $webdata['secret_key']          ?? '';
$pariprint_api_key = $webdata['pariprint_api_key']   ?? 'phk_55e219135b215ac76104c0b0e9eacb7ef7dd14fd';

// Extra endpoints / global vars (safe defaults - used in header / index templates)
$flexsecureapiurl  = $webdata['flexsecureapiurl']    ?? 'goodprintportal.in';
$displayName       = $webdata['displayName']         ?? ($webdata['webname'] ?? 'Good Print Portal');
$allusersforadmin  = 0;  // Will be overridden below with actual count
$totalOrdersCountTrick = 0; // fallback for trick badges

// Count total users (for dashboard "Total Users" card), excluding admin accounts for display (admin accounts not counted in "users" figures usually)
if (!empty($ahk_conn)) {
    $cntQ = @mysqli_query($ahk_conn, "SELECT COUNT(*) AS c FROM users WHERE LOWER(type) != 'admin'");
    if ($cntQ && ($r = mysqli_fetch_assoc($cntQ))) {
        $allusersforadmin = (int)$r['c'];
    }
    // Fallback if above returns 0 / fails - count ALL (including admins) just so dashboard shows correct numbers
    if ($allusersforadmin < 1) {
        $cntQ2 = @mysqli_query($ahk_conn, "SELECT COUNT(*) AS c FROM users");
        if ($cntQ2 && ($r2 = mysqli_fetch_assoc($cntQ2))) $allusersforadmin = (int)$r2['c'];
    }
}

// Count unread messages / notifications for header badges
$query_noforadmin  = 0; // admin notification badge count
$query_foruser     = 0; // user notification badge count
$query_noticadmin  = 0; // legacy fallback (referenced in header)
if (!empty($ahk_conn)) {
    $cntAdmin = @mysqli_query($ahk_conn, "SELECT COUNT(*) AS c FROM contact WHERE reply IS NULL OR reply = ''");
    if ($cntAdmin && ($r = mysqli_fetch_assoc($cntAdmin))) {
        $query_noforadmin = (int)$r['c'];
    }
    if (isset($udata['email']) && $udata['email']) {
        $safeEmail = mysqli_real_escape_string($ahk_conn, $udata['email']);
        $cntUser = @mysqli_query($ahk_conn, "SELECT COUNT(*) AS c FROM contact WHERE email='" . $safeEmail . "' AND (reply IS NOT NULL AND reply != '')");
        if ($cntUser && ($r2 = mysqli_fetch_assoc($cntUser))) {
            $query_foruser = (int)$r2['c'];
        }
    }
}
$query_noticadmin = $query_noforadmin; // legacy alias fallback

// ============================================================
// MARK: Users Table Schema Self-Heal
// Ensures ALL expected columns exist before we SELECT * FROM users.
// Runs max once per request (single-request guard; NOT session cache).
// ============================================================
function _gpEnsureUsersSchema($conn) {
    if (!$conn) return;
    if (!empty($GLOBALS['_gp_users_cols_done'])) return;
    $GLOBALS['_gp_users_cols_done'] = true;
    @mysqli_report(MYSQLI_REPORT_OFF);

    $cols = [];
    $r = @mysqli_query($conn, "SHOW COLUMNS FROM users");
    if (!$r) return;
    while ($row = mysqli_fetch_assoc($r)) $cols[] = $row['Field'];

    $add = function($n, $d) use ($conn, $cols) {
        if (!in_array($n, $cols, true)) {
            @mysqli_query($conn, "ALTER TABLE users ADD COLUMN {$n} {$d}");
        }
    };

    $add('password',    "VARCHAR(255) NULL DEFAULT NULL");
    $add('email',       "VARCHAR(255) NULL DEFAULT NULL");
    $add('address',     "TEXT NULL DEFAULT NULL");
    $add('parent',      "VARCHAR(255) NULL DEFAULT NULL");
    $add('referred_by', "VARCHAR(255) NULL DEFAULT NULL");
    $add('date',        "VARCHAR(50) NULL DEFAULT NULL");
    $add('created_at',  "DATETIME NULL DEFAULT NULL");
    $add('last_login',  "DATETIME NULL DEFAULT NULL");
    $add('login_ip',    "VARCHAR(64) NULL DEFAULT NULL");
    $add('dob',         "VARCHAR(32) NULL DEFAULT NULL");
    $add('gender',      "VARCHAR(16) NULL DEFAULT NULL");
    $add('pincode',     "VARCHAR(16) NULL DEFAULT NULL");
    $add('state',       "VARCHAR(128) NULL DEFAULT NULL");
    $add('city',        "VARCHAR(128) NULL DEFAULT NULL");
    // Popup login nonce: har login pe unique random token, kabhi same nahi hoga
    $add('popup_login_nonce', "VARCHAR(64) NULL DEFAULT NULL");
    // Popup event id: 100% asan login-linking — har login pe NAYA event id, session flag compare
    $add('last_popup_event_id', "BIGINT(20) NULL DEFAULT NULL");
}

// ============================================================
// MARK: login_popup_events table (1 row = 1 login, 1 popup dikhna hai uske liye)
// Har login me INSERT karo, id users.last_popup_event_id me save karo.
// Session me same ID "seen" mark nahi hai → popup dikhao.
// ============================================================
function _gpEnsurePopupEventsTable($conn) {
    if (!$conn) return;
    if (!empty($GLOBALS['_gp_popup_events_done'])) return;
    $GLOBALS['_gp_popup_events_done'] = true;
    @mysqli_report(MYSQLI_REPORT_OFF);
    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS login_popup_events (
        id BIGINT(20) PRIMARY KEY AUTO_INCREMENT,
        user_id INT(11) NOT NULL,
        login_ip VARCHAR(64) NULL DEFAULT NULL,
        created_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
if (!empty($ahk_conn)) _gpEnsurePopupEventsTable($ahk_conn);
if (!empty($ahk_conn)) _gpEnsureUsersSchema($ahk_conn);

// 2. Check User Session and Fetch Data
if (isset($_SESSION['phone']) && !empty($_SESSION['phone'])) {
    $safe_phone = mysqli_real_escape_string($ahk_conn, $_SESSION['phone']);
    $uquery = mysqli_query($ahk_conn, "SELECT * FROM users WHERE phone='" . $safe_phone . "' LIMIT 1");

    if ($uquery) {
        $udata = mysqli_fetch_assoc($uquery);
        $nutype = $udata['type'] ?? 'user'; // Set global user type

        // ============================================================
        // 🟢 LOGIN POPUP: "HAR LOGIN PE NEW" — Nonce based detection
        // popup_login_nonce (random unique string) ALWAYS unique per login.
        // Session me stored nonce != DB nonce → PURANE popup flags RESET!
        // ============================================================
        if (!empty($udata['id'])) {
            $__uid = (int)$udata['id'];
            $__dbNonce = (string)($udata['popup_login_nonce'] ?? '');
            $__sessKey = 'gp_popup_nonce_' . $__uid;
            $__sessNonce = (string)($_SESSION[$__sessKey] ?? '');
            if ($__dbNonce !== '' && $__sessNonce !== $__dbNonce) {
                unset($_SESSION['gp_login_popup_shown_' . $__uid]);
                unset($_SESSION['gp_login_popup_ver_'   . $__uid]);
                unset($_SESSION['gp_last_login_ts_'     . $__uid]);
                unset($_SESSION['gp_seen_nonce_'        . $__uid]);
                unset($_SESSION['gp_popup_shown_once_login_' . $__uid]);
                // 🟢🟢 New once-key (final 3-layer system)
                unset($_SESSION['gp_once_per_login_'    . $__uid]);
                unset($_SESSION['gp_popup_show_1']);
                $_SESSION[$__sessKey] = $__dbNonce;
            }

            // ============================================================
            // 🔥 NEW DB EVENT ID SYSTEM (100% asan: jitne baar login utne baar popup)
            // users.last_popup_event_id != session stored id → UNSET shown flag → next render show hoga
            // Is block se purane session keys clean ho jayenge, har login ke baad guaranteed mismatch
            // ============================================================
            $__dbEvId = (int)($udata['last_popup_event_id'] ?? 0);
            $__sessEvKey = 'gp_popup_event_shown_' . $__uid;
            $__sessEvVal = (int)($_SESSION[$__sessEvKey] ?? -1);
            if ($__dbEvId > 0 && $__sessEvVal !== $__dbEvId) {
                unset($_SESSION[$__sessEvKey]); // shown flag HATAO → header.php mismatch pe show karega
            }
        }
    }
}

// END of _config_globals_loaded guard
} // <-- closes the global-load guard at top

/*
=====================================================================================
=============================== ALL FUNCTIONS HERE ==================================
=====================================================================================
*/

// Check Session Function
if (!function_exists('checkSession')) {
function checkSession()
{
    if (!isset($_SESSION['phone']) || empty($_SESSION['phone'])) {
        header('Location: ../login.php');
        die();
    }
}
}

// Check Admin (Pass user type as argument - accepts 'admin', 'Administrator', 'superadmin' etc)
// NOTE: Call sites use BOTH checkAdmin (Capital A) and checkadmin (lowercase).
// PHP functions are case-insensitive in global symbol table, but DEFINE BOTH names EXPLICITLY
// to eliminate ANY edge-case opcode cache / symbol-table bugs:
if (!function_exists('checkadmin')) {
function checkadmin($usertype)
{
    $t = strtolower((string)($usertype ?? ''));
    return ($t === 'admin' || $t === 'administrator' || $t === 'superadmin');
}
}

// Exact capital-A spelling - 99% of callers use checkAdmin()
if (!function_exists('checkAdmin')) {
    function checkAdmin($usertype) { return checkadmin($usertype); }
}

// Get API Balance Function (Pariprint / Dynamic API Base)
if (!function_exists('getAPIBalance')) {
function getAPIBalance()
{
    global $pariprint_api_key;

    $apiBase = (function_exists('get_central_api_domain') ? rtrim(get_central_api_domain(), '/') : 'https://pariprint.in');
    $url = $apiBase . "/api-balance.php?" . http_build_query([
        'api_key' => $pariprint_api_key
    ]);

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_USERAGENT => 'goodprintportal.in-getAPIBalance',
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        return "Curl Error: " . $err;
    }

    $data = json_decode($response, true);

    if (isset($data['status']) && $data['status'] === 'success' && isset($data['balance'])) {
        return number_format((float) $data['balance'], 2);
    } elseif (isset($data['message'])) {
        return $data['message'];
    } else {
        return "Invalid Response";
    }
}
}

// If Admin Check (Using Global Nutype)
if (!function_exists('ifadmin')) {
function ifadmin()
{
    global $nutype;
    return ($nutype == 'admin');
}
}

// Query Execute
if (!function_exists('ahkQuery')) {
function ahkQuery($sql)
{
    global $ahk_conn;
    $res = mysqli_query($ahk_conn, $sql);
    return $res;
}
}

// Count Row Number
if (!function_exists('ahkRows')) {
function ahkRows($sql)
{
    $query = ahkQuery($sql);
    if ($query === false) {
        error_log("MySQL Query Failed: " . mysqli_error($GLOBALS['ahk_conn']) . " | SQL: $sql");
        return 0;
    }
    return mysqli_num_rows($query);
}
}

// Fetch Row Data 
if (!function_exists('fetchRow')) {
function fetchRow($sql)
{
    $res = ahkQuery($sql);
    if ($res) {
        return mysqli_fetch_assoc($res);
    }
    return null;
}
}

// Get Website data 
if (!function_exists('ahkweb')) {
function ahkweb($columnName)
{
    global $webdata, $ahk_conn;
    // ===== MARK: ahkweb() OPTIMIZATION - prefer global $webdata to avoid re-querying settings every call =====
    // Header mein logo, webname etc. ke liye 3-5 baar call hota hai — har baar SELECT * mat chalao
    if (is_array($webdata) && isset($webdata[$columnName]) && $webdata[$columnName] !== null && $webdata[$columnName] !== '') {
        echo $webdata[$columnName];
        return;
    }
    // Fallback only if column not in global $webdata
    $res = fetchRow("SELECT " . (isset($ahk_conn) ? mysqli_real_escape_string($ahk_conn, $columnName) : $columnName) . " FROM settings WHERE id=1 LIMIT 1");
    if ($res && isset($res[$columnName])) {
        echo $res[$columnName];
    } else {
        echo "";
    }
}
}

// Get Particular Column Name of table
if (!function_exists('getColumn')) {
function getColumn($tableName, $columnName, $Identity)
{
    $sql = "SELECT * FROM " . $tableName . " WHERE " . $Identity;
    if (ahkRows($sql) > 0) {
        $res = fetchRow($sql);
        if (isset($res[$columnName])) {
            echo $res[$columnName];
        } else {
            echo "Column not found";
        }
    } else {
        echo "Data not Found";
    }
}
}

// Get Safe Value 
if (!function_exists('getSafe')) {
function getSafe($value)
{
    global $ahk_conn;
    return mysqli_real_escape_string($ahk_conn, $value);
}
}

// print Array Function 
if (!function_exists('printR')) {
function printR($data)
{
    echo "<pre>";
    print_r($data);
    echo "</pre>";
}
}

// Redirect Function (required params first)
if (!function_exists('ahkRedirect')) {
function ahkRedirect($time = 0, $url = '')
{
    // For backward compat: support both new order ahkRedirect($time, $url) AND old order ahkRedirect($url, $time).
    // Detect by type of first arg.
    if (is_string($time) || filter_var($time, FILTER_VALIDATE_URL) || (is_string($time) && $time !== '' && (string)(int)$time !== (string)$time && !is_numeric($time))) {
        // Old signature ahkRedirect($url='...', $time=5) was called
        $_realUrl = (string)$time;
        $_realTime = (int)$url;
    } else {
        $_realTime = (int)$time;
        $_realUrl = (string)$url;
    }
    echo "<script>
        setTimeout(() => {
            window.location = '" . $_realUrl . "';
        }, " . $_realTime . ");
    </script>";
}
}

// Alert Function
if (!function_exists('showAlert')) {
function showAlert($fmsg = '', $smsg = '', $type = 'success')
{
    echo "<script>
        $(function () {
            Swal.fire(
                '" . $fmsg . "',
                '" . $smsg . "',
                '" . $type . "'
            )
        })
    </script>";
}
}

// Login as Admin Logic
if (!function_exists('loginAsadmin')) {
function loginAsadmin()
{
    if (isset($_SESSION['adminasuser']) && $_SESSION['adminasuser'] == true && isset($_SESSION['adminusername'])) {
        $_SESSION['phone'] = $_SESSION['adminusername'];
        unset($_SESSION['adminasuser']);
        unset($_SESSION['adminusername']);
        ahkRedirect('index.php', 1200);
    }
}
}

// ============================================================
// MARK: Referral Bonus on First Deposit — Idempotent Helper
// Sirf EK BAAR per referred user deta hai — pehli successful deposit/wallet payment pe.
// Safe to call multiple times: second call onwards kuchh nahi hoga.
// ============================================================
if (!function_exists('_gpEnsureReferralTable')) {
function _gpEnsureReferralTable($conn) {
    if (!$conn) return;
    static $done = null;
    if ($done === true) return;
    @mysqli_report(MYSQLI_REPORT_OFF);
    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS referal (
        id BIGINT(20) PRIMARY KEY AUTO_INCREMENT,
        phone VARCHAR(50) NOT NULL,
        referred_by VARCHAR(50) NOT NULL,
        txn_no VARCHAR(50) NULL,
        referral_balance VARCHAR(20) DEFAULT '0',
        status VARCHAR(10) DEFAULT '0',
        refer_date VARCHAR(50) NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $cols = [];
    $r = @mysqli_query($conn, "SHOW COLUMNS FROM referal");
    if ($r) while ($row = mysqli_fetch_assoc($r)) $cols[] = $row['Field'];
    $add = function($n, $d) use ($conn, $cols) {
        if (!in_array($n, $cols, true)) @mysqli_query($conn, "ALTER TABLE referal ADD COLUMN {$n} {$d}");
    };
    $add('phone', "VARCHAR(50) NOT NULL");
    $add('referred_by', "VARCHAR(50) NOT NULL");
    $add('txn_no', "VARCHAR(50) NULL");
    $add('referral_balance', "VARCHAR(20) DEFAULT '0'");
    $add('status', "VARCHAR(10) DEFAULT '0'");
    $add('refer_date', "VARCHAR(50) NULL");
    $done = true;
}
}
if (!function_exists('_gpEnsureSettingsRefBal')) {
function _gpEnsureSettingsRefBal($conn) {
    if (!$conn) return;
    static $done = null;
    if ($done === true) return;
    @mysqli_report(MYSQLI_REPORT_OFF);
    $cols = [];
    $r = @mysqli_query($conn, "SHOW COLUMNS FROM settings");
    if ($r) while ($row = mysqli_fetch_assoc($r)) $cols[] = $row['Field'];
    if (!in_array('ref_bal', $cols, true)) {
        @mysqli_query($conn, "ALTER TABLE settings ADD COLUMN ref_bal VARCHAR(20) DEFAULT '10'");
    }
    if (!in_array('ref_enabled', $cols, true)) {
        $afterPos = in_array('ref_bal', $cols, true) ? 'AFTER ref_bal' : '';
        @mysqli_query($conn, "ALTER TABLE settings ADD COLUMN ref_enabled TINYINT(1) NOT NULL DEFAULT 1 {$afterPos}");
    }
    $done = true;
}
}
if (!function_exists('gpGiveReferralBonusIfEligible')) {
function gpGiveReferralBonusIfEligible($userPhone, $conn = null) {
    if ($conn === null) { global $ahk_conn; $conn = $ahk_conn; }
    if (!$conn || trim((string)$userPhone) === '') return false;
    $userPhone = trim((string)$userPhone);

    _gpEnsureReferralTable($conn);
    _gpEnsureSettingsRefBal($conn);

    // HARD OFF CHECK: If admin set ref_enabled = 0, NO bonus AT ALL (return immediately)
    global $webdata;
    $_sysRefEnabled = true;
    if (is_array($webdata) && array_key_exists('ref_enabled', $webdata)) {
        $_sysRefEnabled = ((int)($webdata['ref_enabled'] ?? 1) === 1);
    } else {
        // fallback: direct query
        static $_gpCacheEnabled = null;
        if ($_gpCacheEnabled === null) {
            $rr = @mysqli_query($conn, "SHOW COLUMNS FROM settings LIKE 'ref_enabled'");
            if ($rr && mysqli_num_rows($rr) > 0) {
                $rw = @mysqli_query($conn, "SELECT ref_enabled FROM settings WHERE id=1 LIMIT 1");
                if ($rw && ($rx = mysqli_fetch_assoc($rw))) $_gpCacheEnabled = ((int)($rx['ref_enabled'] ?? 1) === 1);
                else $_gpCacheEnabled = true;
            } else {
                $_gpCacheEnabled = true;
            }
        }
        $_sysRefEnabled = $_gpCacheEnabled;
    }
    if (!$_sysRefEnabled) return false;

    // 1. Check user has a referrer
    $stmt = @mysqli_prepare($conn, "SELECT referred_by FROM users WHERE phone=? LIMIT 1");
    if (!$stmt) return false;
    @mysqli_stmt_bind_param($stmt, "s", $userPhone);
    @mysqli_stmt_execute($stmt);
    $row = db_stmt_fetch_assoc($stmt);
    @mysqli_stmt_close($stmt);
    if (!$row || empty($row['referred_by'])) return false;
    $referredBy = trim((string)$row['referred_by']);
    if ($referredBy === '' || $referredBy === $userPhone) return false;

    // 2. One-time guard: referal table me is phone ke liye koi row already hai? (regardless of status)
    $chkStmt = @mysqli_prepare($conn, "SELECT id FROM referal WHERE phone=? LIMIT 1");
    if (!$chkStmt) return false;
    @mysqli_stmt_bind_param($chkStmt, "s", $userPhone);
    @mysqli_stmt_execute($chkStmt);
    $chkRow = db_stmt_fetch_assoc($chkStmt);
    @mysqli_stmt_close($chkStmt);
    if ($chkRow) return false;

    // 3. Get referral bonus amount from settings (default = 10)
    global $webdata;
    $bonus = 10;
    if (is_array($webdata) && isset($webdata['ref_bal']) && $webdata['ref_bal'] !== null && $webdata['ref_bal'] !== '' && is_numeric($webdata['ref_bal'])) {
        $bonus = (float)$webdata['ref_bal'];
    } else {
        $ws = @mysqli_query($conn, "SELECT ref_bal FROM settings WHERE id=1 LIMIT 1");
        if ($ws && ($wr = mysqli_fetch_assoc($ws))) {
            if (isset($wr['ref_bal']) && $wr['ref_bal'] !== null && $wr['ref_bal'] !== '' && is_numeric($wr['ref_bal'])) {
                $bonus = (float)$wr['ref_bal'];
            }
        }
    }
    if ($bonus <= 0) return false;

    // 4. Insert referal record
    $bonusStr = (string)$bonus;
    $txnNo = "REF" . date("YmdHis") . rand(10000, 99999);
    $refDate = date("Y-m-d");
    $insStmt = @mysqli_prepare($conn, "INSERT INTO referal (phone, referred_by, txn_no, referral_balance, status, refer_date) VALUES (?, ?, ?, ?, '1', ?)");
    if (!$insStmt) return false;
    @mysqli_stmt_bind_param($insStmt, "sssss", $userPhone, $referredBy, $txnNo, $bonusStr, $refDate);
    $ok = @mysqli_stmt_execute($insStmt);
    @mysqli_stmt_close($insStmt);
    if (!$ok) return false;

    // 5. Also credit referrer's wallet balance (actual balance me add)
    $updStmt = @mysqli_prepare($conn, "UPDATE users SET balance = CAST(COALESCE(balance,0) AS DECIMAL(15,2)) + ? WHERE phone=? LIMIT 1");
    if ($updStmt) {
        @mysqli_stmt_bind_param($updStmt, "ds", $bonus, $referredBy);
        @mysqli_stmt_execute($updStmt);
        @mysqli_stmt_close($updStmt);
    }

    return true;
}
}
?>
