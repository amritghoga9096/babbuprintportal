<?php
// ============================================================
// 🔥 UNIVERSAL SAMAGRA DOWNLOAD SHELL (ULTRA-SAFE PHP 7.4+)
//  NOTE: Caller file includes SHELL this file AFTER setting:
//    $_gpp_dl_name, $_gpp_dl_table, $_gpp_dl_cols,
//    $_gpp_dl_nameCol, $_gpp_dl_upload, $_gpp_dl_pdfCol,
//    $_gpp_dl_ensureFunc, [optional $_gpp_dl_altFileSelector]
//
//  BECAUSE this shell ITSELF is included AFTER caller sets vars,
//  we need error_handling to run HERE. But wait — caller includes shell
//  AFTER its OWN includes. So we start by SETTING the ultra-safe
//  handlers as the FIRST thing in THIS file (which runs BEFORE shell code here)
// ============================================================

// 🔥 FORCE ERROR DISPLAY ON (handlers + php.ini override)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('html_errors', 0);
error_reporting(E_ALL);

if (!isset($GLOBALS['_dl_debug'])) {
    $GLOBALS['_dl_debug'] = [
        'errors'     => [],
        'page_title' => isset($_gpp_dl_name) ? $_gpp_dl_name : 'Samagra Download'
    ];
}

if (!function_exists('_dl_err_handler')) {
    function _dl_err_handler($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) return false;
        $map = [
            E_WARNING => 'E_WARNING', E_NOTICE => 'E_NOTICE',
            E_STRICT => 'E_STRICT', E_DEPRECATED => 'E_DEPRECATED',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_USER_ERROR => 'E_USER_ERROR', E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE', E_USER_DEPRECATED => 'E_USER_DEPRECATED'
        ];
        $typeName = isset($map[$severity]) ? $map[$severity] : 'E_#'.$severity;
        $GLOBALS['_dl_debug']['errors'][] = "[$typeName] $message in $file line $line";
        $title = isset($GLOBALS['_dl_debug']['page_title']) ? $GLOBALS['_dl_debug']['page_title'] : 'DL';
        @error_log("DL_WARN [$title] [$typeName] $message in $file:$line");
        return true;
    }

    function _dl_render_swal($title, $body, $isHtml = false, $footer = null) {
        while (ob_get_level()) { @ob_end_clean(); }
        if (!headers_sent()) { @header('Content-Type: text/html; charset=utf-8'); }
        $safeTitle = htmlspecialchars((string)$title, ENT_QUOTES);
        $bodyStr = $isHtml ? (string)$body : htmlspecialchars((string)$body, ENT_QUOTES);
        $plainBody = $isHtml ? trim(preg_replace('/\s+/', ' ', @strip_tags((string)$body))) : (string)$body;
        $safeBodyAttr = htmlspecialchars($plainBody, ENT_QUOTES);
        $warnings = '';
        if (!empty($GLOBALS['_dl_debug']['errors'])) {
            foreach ($GLOBALS['_dl_debug']['errors'] as $w) { $warnings .= htmlspecialchars($w, ENT_QUOTES)."\n"; }
        }
        echo '<!doctype html><html><head><meta charset="utf-8"><title>Error - ', $safeTitle, '</title>';
        echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<style>
            body{font-family:system-ui,Arial,sans-serif;background:#1a1a1a;color:#fff;margin:0;padding:18px}
            .ebox{max-width:720px;margin:24px auto;background:#2a2a2a;border:1px solid #444;border-radius:12px;padding:18px 20px}
            .etitle{font-size:20px;font-weight:700;color:#ff6b6b;margin-bottom:10px}
            .ebody{background:#111;padding:12px;border-radius:8px;white-space:pre-wrap;word-break:break-word;margin:10px 0;color:#ffd966;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:13px;line-height:1.5;max-height:260px;overflow:auto}
            .ebtn{display:inline-block;margin-top:10px;padding:9px 16px;background:#4f46e5;color:#fff;border:0;border-radius:8px;cursor:pointer;font-weight:600}
            .efoot{color:#aaa;font-size:12px;margin-top:12px}
            details{margin-top:10px}summary{cursor:pointer;color:#60a5fa}
        </style></head><body>';
        echo '<div class="ebox">';
        echo '<div class="etitle">🚨 ', $safeTitle, '</div>';
        echo '<div class="ebody">', $bodyStr, '</div>';
        if ($footer) echo '<div class="efoot">', htmlspecialchars((string)$footer), '</div>';
        if (!empty($GLOBALS['_dl_debug']['errors'])) {
            $cnt = count($GLOBALS['_dl_debug']['errors']);
            echo '<details><summary>Show PHP Warnings (', $cnt, ')</summary><div class="ebody" style="color:#a78bfa;margin-top:6px">', htmlspecialchars($warnings), '</div></details>';
        }
        echo '<br><button class="ebtn" onclick="window.history.back();">← Back (Try Again)</button>';
        echo ' &nbsp; <button class="ebtn" style="background:#10b981" onclick="window.location.href=\'index.php\'">🏠 Dashboard</button>';
        echo '</div>';
        echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';
        echo '<script>
            (function(){
                var t = ', ($isHtml ? 'true' : 'false'), ';
                var opts = {icon:"error",showConfirmButton:true,confirmButtonText:"OK",title:', json_encode($safeTitle), ',backdrop:true};
                if (t) opts.html = ', json_encode((string)$body), '; else opts.text = ', json_encode($plainBody), ';
                var fired = false;
                function fb(){ if(fired)return; fired=true; alert("'.$safeTitleAttr.'\n\n'.$safeBodyAttr.'"); if(window.history.length>1) window.history.back(); }
                function fs(){ if(fired)return; fired=true; if(typeof Swal!=="undefined"){ Swal.fire(opts).then(function(){ if(window.history.length>1) window.history.back(); else window.location.href="index.php";});} else { fb(); } }
                try{ fs(); }catch(e){ fb(); }
                setTimeout(function(){ try{ fs(); }catch(e){ fb(); } }, 1500);
            })();
        </script>';
        if (!empty($GLOBALS['_dl_debug']['errors'])) {
            echo '<script>try{console.log(' . json_encode($GLOBALS['_dl_debug']['errors'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ');}catch(e){}</script>';
        }
        echo '</body></html>';
        exit;
    }

    function _dl_exception_handler($e) {
        $msg = $e->getMessage() . "\nFile: " . $e->getFile() . "\nLine: " . $e->getLine();
        $title = isset($GLOBALS['_dl_debug']['page_title']) ? $GLOBALS['_dl_debug']['page_title'] : 'DL';
        @error_log("DL_EXCEPTION [$title] " . $e->getMessage() . " " . $e->getFile() . ":" . $e->getLine());
        _dl_render_swal('PHP Exception ('.$title.')', $msg, false, 'Admin ko yeh screenshot bhejo');
    }

    function _dl_shutdown_handler() {
        $err = error_get_last();
        if (!$err) return;
        $fatals = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR);
        if (!in_array($err['type'], $fatals, true)) return;
        $msg = $err['message'] . "\nFile: " . $err['file'] . "\nLine: " . $err['line'];
        $title = isset($GLOBALS['_dl_debug']['page_title']) ? $GLOBALS['_dl_debug']['page_title'] : 'DL';
        @error_log("DL_FATAL [$title] " . $err['message'] . " " . $err['file'] . ":" . $err['line']);
        _dl_render_swal('PHP Fatal Error ('.$title.')', $msg, false, 'Check PHP error_log + share screenshot with admin');
    }

    set_error_handler('_dl_err_handler');
    set_exception_handler('_dl_exception_handler');
    register_shutdown_function('_dl_shutdown_handler');
}

// ---- VALIDATE CALLER CONFIG ----
$_missing = array();
foreach (array('_gpp_dl_name','_gpp_dl_table','_gpp_dl_cols','_gpp_dl_nameCol','_gpp_dl_upload','_gpp_dl_pdfCol','_gpp_dl_ensureFunc') as $_k) {
    if (!isset($$_k)) $_missing[] = $_k;
}
if (!empty($_missing)) {
    _dl_render_swal('Shell Config Missing', "Debug: caller ne ye variables set nahi kiye:\n - " . implode("\n - ", $_missing));
}
$GLOBALS['_dl_debug']['page_title'] = $_gpp_dl_name;

// ---- INCLUDES (handlers already set - isliye agar includes mein error aaye to pakad me aayega ----
// NOTE: includes already done by CALLER before including shell
// (shell is included AFTER caller sets vars

checkSessionWrapper:
checkSession();
if (function_exists($_gpp_dl_ensureFunc)) {
    call_user_func($_gpp_dl_ensureFunc, $ahk_conn);
}

$userPhone = (string)(isset($udata['phone']) ? $udata['phone'] : '');
$userType  = (string)(isset($udata['type'])  ? $udata['type']  : '');
$id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);

if ($id <= 0) {
    _dl_render_swal('Invalid Request', "Debug: Missing/Invalid id. id=$id | page=$_gpp_dl_name", false, 'Check URL ya list page wapas se try karo');
}
if ($userPhone === '') {
    _dl_render_swal('Session Expired', "Debug: user_phone empty. Session khatam ho gayi.\nPlease login phir se karo.");
}

$sql = "SELECT {$_gpp_dl_cols} FROM {$_gpp_dl_table} WHERE id=? LIMIT 1";
$stmt = mysqli_prepare($ahk_conn, $sql);
if (!$stmt) {
    _dl_render_swal('DB Prepare Failed', "Debug: mysqli_prepare FALSE.\nMySQL: " . mysqli_error($ahk_conn) . "\nSQL: $sql");
}
mysqli_stmt_bind_param($stmt, 'i', $id);
if (!mysqli_stmt_execute($stmt)) {
    $err = mysqli_stmt_error($stmt);
    @mysqli_stmt_close($stmt);
    _dl_render_swal('DB Execute Failed', "Debug: mysqli_stmt_execute failed.\nMySQL Error: $err\nID=$id\nService: $_gpp_dl_name");
}
$request = db_stmt_fetch_assoc($stmt);
@mysqli_stmt_close($stmt);

$isAdmin = function_exists('checkAdmin') ? checkAdmin($userType) : ($userType === 'admin');

if (!$request || !is_array($request)) {
    _dl_render_swal('Request Not Found', "Debug: {$_gpp_dl_table} me id=$id record nahi mila.\ndb_stmt_fetch_assoc = ".gettype($request)."\nService: $_gpp_dl_name");
}

// ---- Caller alt selector ----
$relativePath = null;
$_suffix = isset($_gpp_dl_suffix) ? (string)$_gpp_dl_suffix : '';
if (isset($_gpp_dl_altFileSelector) && is_callable($_gpp_dl_altFileSelector)) {
    $relativePath = call_user_func($_gpp_dl_altFileSelector, $request, $isAdmin, $userPhone, '_dl_render_swal');
    if ($relativePath === null) exit; // selector already rendered error
    if (isset($GLOBALS['_gpp_dl_suffix'])) $_suffix = (string)$GLOBALS['_gpp_dl_suffix'];
} else {
    // standard flow
    $reqStatus = (string)(isset($request['status']) ? $request['status'] : '');
    $reqOwner  = (string)(isset($request['user_phone']) ? $request['user_phone'] : '');
    if ($reqStatus !== 'approved') {
        $_reqNo = isset($request[$_gpp_dl_nameCol]) ? $request[$_gpp_dl_nameCol] : "id=$id";
        _dl_render_swal('Not Approved', "Debug: status='$reqStatus' (expected 'approved').\nRequest: $_reqNo\nAdmin abhi approve nahi kiya hai.");
    }
    if (!$isAdmin && $reqOwner !== $userPhone) {
        _dl_render_swal('Access Denied', "Debug: kisi aur ki request.\nOwner: $reqOwner\nYou: $userPhone\nType: $userType\nisAdmin=".($isAdmin?'true':'false'));
    }
    $relativePath = (string)(isset($request[$_gpp_dl_pdfCol]) ? $request[$_gpp_dl_pdfCol] : '');
}

if ($relativePath === '') {
    $_reqNo = isset($request[$_gpp_dl_nameCol]) ? $request[$_gpp_dl_nameCol] : "id=$id";
    _dl_render_swal('PDF Not Uploaded Yet', "Debug: {$_gpp_dl_pdfCol} column EMPTY for $_reqNo.\nStatus=approved lekin admin ne PDF upload nahi kiya.");
}

$uploadDir = __DIR__ . '/' . trim($_gpp_dl_upload, '/');
$base = @realpath($uploadDir);
$fullPath = @realpath(__DIR__ . '/' . $relativePath);

if (!$base) {
    _dl_render_swal('Upload Folder Missing', "Debug: {$_gpp_dl_upload} folder server par nahi mila!\nExpected: " . htmlspecialchars($uploadDir) . "\nrealpath() = FALSE\n\nSolution: FTP/cPanel se folder create karo / permissions do.", true);
}

$baseCheck = $base . DIRECTORY_SEPARATOR;
$pathOk = ($fullPath && strpos($fullPath, $baseCheck) === 0 && @is_file($fullPath));

if (!$pathOk) {
    $dbg = array(
        "stored (db) path = $relativePath",
        "upload dir (php)  = $uploadDir",
        "upload (realpath) = " . ($base ? $base : '(MISSING directory)'),
        "file (realpath)   = " . ($fullPath ? $fullPath : '(NOT FOUND - file missing)'),
        "is regular file?  = " . ($fullPath ? (@is_file($fullPath) ? 'YES' : 'NO: not a file (maybe dir/link)') : 'n/a'),
        "inside base dir?  = " . ($fullPath ? (strpos($fullPath, $baseCheck)===0 ? 'YES' : 'NO: outside/security blocked') : 'n/a'),
    );
    _dl_render_swal('PDF Not Found on Server', implode("\n", $dbg), false, 'Admin ko screenshot bhejo - file missing ya path galat');
}

$fsize = @filesize($fullPath);
if ($fsize === false || $fsize <= 0) {
    _dl_render_swal('PDF Empty/Corrupt', "Debug: size=" . ($fsize===false?'FALSE (unreadable)':(int)$fsize) . " bytes\nPath: $fullPath");
}

// ==== FINAL: CLEAN BUFFER + SEND PDF ====
while (ob_get_level()) { @ob_end_clean(); }

if (headers_sent($hf, $hl)) {
    _dl_render_swal('Headers Already Sent', "Debug: PHP ne PDF se pehle hi output bhej diya.\nStarted at file: $hf\nline: $hl\n\nReasons: warnings, BOM byte, whitespace before <?php, etc.", false, 'Developer ko exact file+line bhejo');
}

$reqNo = (string)(isset($request[$_gpp_dl_nameCol]) ? $request[$_gpp_dl_nameCol] : ('samagra-'.$_gpp_dl_name.'-'.$id));
$safeName = preg_replace('/[^A-Za-z0-9_-]/', '', $reqNo) . $_suffix;
if (trim($safeName,'.pdf') === '') { $safeName = 'samagra-'.$_gpp_dl_name.'-'.$id.$_suffix; }
if (stripos($safeName,'.pdf')===false) { $safeName .= '.pdf'; }

header('Content-Type: application/pdf');
header('Content-Transfer-Encoding: binary');
header('Content-Disposition: attachment; filename="' . $safeName . '"');
header('Content-Length: ' . $fsize);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
$mtime = @filemtime($fullPath);
if ($mtime) header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
header('Accept-Ranges: bytes');

readfile($fullPath);
exit;
