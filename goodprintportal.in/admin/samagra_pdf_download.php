<?php
// ============================================================
// 🔥 FIRST THING: Error display FORCE ON + Debug handlers
//  YEH SABSE UPAR HONA CHAHIYE - includes se PEHLE
//  Includes mein hi agar koi error aaye to wo bhi pakad me aaye
// ============================================================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('html_errors', 0);
error_reporting(E_ALL);

$GLOBALS['_dl_debug'] = [
    'errors' => [],
    'page_title' => 'Samagra PDF'
];

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
    error_log("DL_WARN [{$GLOBALS['_dl_debug']['page_title']}] [$typeName] $message in $file:$line");
    return true;
}

function _dl_render_swal($title, $body, $isHtml = false, $footer = null) {
    while (ob_get_level()) { @ob_end_clean(); }
    if (!headers_sent()) { header('Content-Type: text/html; charset=utf-8'); }
    $safeTitle = htmlspecialchars((string)$title, ENT_QUOTES);
    $safeBody  = $isHtml ? (string)$body : htmlspecialchars((string)$body, ENT_QUOTES);
    $safeBodyAttr = htmlspecialchars($isHtml ? strip_tags($body) : $body, ENT_QUOTES);
    $footerAttr = $footer ? htmlspecialchars((string)$footer, ENT_QUOTES) : '';
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
        details{margin-top:10px}
        summary{cursor:pointer;color:#60a5fa}
    </style></head><body>';
    echo '<div class="ebox">';
    echo '<div class="etitle">🚨 ', $safeTitle, '</div>';
    echo '<div class="ebody">', $safeBody, '</div>';
    if ($footer) echo '<div class="efoot">', htmlspecialchars($footer), '</div>';
    if (!empty($GLOBALS['_dl_debug']['errors'])) {
        echo '<details><summary>Show PHP Warnings (', count($GLOBALS['_dl_debug']['errors']), ')</summary><div class="ebody" style="color:#a78bfa;margin-top:6px">', htmlspecialchars($warnings), '</div></details>';
    }
    echo '<br><button class="ebtn" onclick="window.history.back();">← Back (Try Again)</button>';
    echo ' &nbsp; <button class="ebtn" style="background:#10b981" onclick="window.location.href=\'index.php\'">🏠 Dashboard</button>';
    echo '</div>';

    // Swal.fire IF CDN loads, else plain alert (fallback chain)
    echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';
    echo '<script>
        (function(){
            var t = ', ($isHtml ? 'true' : 'false'), ';
            var opts = {icon:"error",showConfirmButton:true,confirmButtonText:"OK",title:', json_encode($safeTitle), ', backdrop:true};
            if (t) opts.html = ', json_encode($body), '; else opts.text = ', json_encode(strip_tags($body)), ';
            if (', ($footer ? json_encode($footer) : 'null'), ') opts.footer = ', ($footer ? json_encode($footer) : 'null'), ';
            var fired = false;
            function fireFallback(){ if(fired)return; fired=true; alert("'.$safeTitleAttr.'\n\n'.$safeBodyAttr.'"); if(window.history.length>1) window.history.back(); }
            function fireSwal(){ if(fired)return; fired=true; if(typeof Swal!=="undefined"){ Swal.fire(opts).then(function(){ if(window.history.length>1) window.history.back(); else window.location.href="index.php"; }); } else { fireFallback(); } }
            try { fireSwal(); } catch(e) { fireFallback(); }
            setTimeout(function(){ try{ fireSwal(); }catch(e){ fireFallback(); } }, 1500);
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
    error_log("DL_EXCEPTION [{$GLOBALS['_dl_debug']['page_title']}] " . $e->getMessage() . " " . $e->getFile() . ":" . $e->getLine());
    _dl_render_swal('PHP Exception ('.$GLOBALS['_dl_debug']['page_title'].')', $msg, false, 'Admin ko yeh screenshot bhejo');
}

function _dl_shutdown_handler() {
    $err = error_get_last();
    if (!$err) return;
    $fatals = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($err['type'], $fatals)) return;
    $msg = $err['message'] . "\nFile: " . $err['file'] . "\nLine: " . $err['line'];
    error_log("DL_FATAL [{$GLOBALS['_dl_debug']['page_title']}] " . $err['message'] . " " . $err['file'] . ":" . $err['line']);
    _dl_render_swal('PHP Fatal Error ('.$GLOBALS['_dl_debug']['page_title'].')', $msg, false, 'Check PHP error_log + share screenshot with admin');
}

set_error_handler('_dl_err_handler');
set_exception_handler('_dl_exception_handler');
register_shutdown_function('_dl_shutdown_handler');

// ------ NOW INCLUDES (handlers already active, agar yahan error aaye to pakad me aayega) ------
include('../includes/session.php');
include('../includes/config.php');
include('samagra_pdf_common.php');

checkSession();
ensureSamagraPdfTable($ahk_conn);

$userPhone = (string)(isset($udata['phone']) ? $udata['phone'] : '');
$userType  = (string)(isset($udata['type'])  ? $udata['type']  : '');
$id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);

if ($id <= 0) {
    _dl_render_swal('Invalid Request', "Debug: Missing / invalid id parameter. id=$id", false, 'Check URL ya list page wapas se try karo');
}
if ($userPhone === '') {
    _dl_render_swal('Session Expired', "Debug: user_phone empty. Session khatam ho gayi.\nPlease login phir se karo.", false, 'Login karo phir se');
}

$sql = "SELECT user_phone, status, pdf_path, request_no FROM samagra_pdf_requests WHERE id=? LIMIT 1";
$stmt = mysqli_prepare($ahk_conn, $sql);
if (!$stmt) {
    _dl_render_swal('DB Prepare Failed', "Debug: mysqli_prepare() returned FALSE.\nMySQL Error: " . mysqli_error($ahk_conn) . "\nSQL: $sql");
}
mysqli_stmt_bind_param($stmt, 'i', $id);
if (!mysqli_stmt_execute($stmt)) {
    $err = mysqli_stmt_error($stmt);
    @mysqli_stmt_close($stmt);
    _dl_render_swal('DB Execute Failed', "Debug: mysqli_stmt_execute() failed.\nMySQL Error: $err\nID=$id");
}
$request = db_stmt_fetch_assoc($stmt);
@mysqli_stmt_close($stmt);

$isAdmin = function_exists('checkAdmin') ? checkAdmin($userType) : ($userType === 'admin');

if (!$request || !is_array($request)) {
    _dl_render_swal('Request Not Found', "Debug: samagra_pdf_requests me id=$id record nahi mila.\ndb_stmt_fetch_assoc returned " . gettype($request));
}

$reqStatus = (string)(isset($request['status']) ? $request['status'] : '');
$reqOwner  = (string)(isset($request['user_phone']) ? $request['user_phone'] : '');
$reqNo     = (string)(isset($request['request_no']) ? $request['request_no'] : 'samagra-'.$id);
$reqPdf    = (string)(isset($request['pdf_path']) ? $request['pdf_path'] : '');

if ($reqStatus !== 'approved') {
    _dl_render_swal('Request Not Approved', "Debug: status='$reqStatus' (expected 'approved').\nAdmin abhi is request ko approve nahi ki hai.\nRequest No: $reqNo");
}
if (!$isAdmin && $reqOwner !== $userPhone) {
    _dl_render_swal('Access Denied', "Debug: yeh request kisi aur user ki hai.\nRequest Owner Phone: $reqOwner\nYour Phone: $userPhone\nYour Type: $userType\n(isAdmin=".($isAdmin?'true':'false').")");
}
if ($reqPdf === '') {
    _dl_render_swal('PDF Not Uploaded Yet', "Debug: pdf_path column EMPTY for id=$id.\nStatus=$reqStatus, but admin ne abhi PDF file upload nahi ki hai.\nRequest No: $reqNo");
}

$uploadDir = __DIR__ . '/uploads/samagra_pdf';
$base      = @realpath($uploadDir);
$fullPath  = @realpath(__DIR__ . '/' . $reqPdf);

if (!$base) {
    _dl_render_swal('Upload Folder Missing', "Debug: uploads/samagra_pdf directory server par nahi mila!\nExpected path: " . htmlspecialchars($uploadDir) . "\nrealpath returned FALSE. Folder create karo ya upload karo PDF.", true);
}

$baseCheck = $base . DIRECTORY_SEPARATOR;
$pathOk = ($fullPath && strpos($fullPath, $baseCheck) === 0 && is_file($fullPath));

if (!$pathOk) {
    $dbgLines = [
        "stored_path in DB = $reqPdf",
        "upload directory  = $uploadDir",
        "upload realpath   = " . ($base ? $base : '(missing dir)'),
        "file realpath     = " . ($fullPath ? $fullPath : '(NOT FOUND - file does not exist)'),
        "is regular file?  = " . ($fullPath ? (is_file($fullPath) ? 'YES' : 'NO: exists but not a regular file (dir/link?)') : 'n/a'),
        "path in base dir? = " . ($fullPath ? (strpos($fullPath, $baseCheck)===0 ? 'YES' : 'NO: path traversal / outside upload dir!') : 'n/a'),
    ];
    _dl_render_swal('PDF Not Found on Server', implode("\n", $dbgLines), false, 'Admin ko yeh screenshot bhejo - file upload nahi hui hai ya path galat hai');
}

$fsize = @filesize($fullPath);
if ($fsize === false || $fsize <= 0) {
    _dl_render_swal('PDF File Empty / Corrupt', "Debug: filesize() = " . ($fsize===false ? 'FALSE (unreadable?)' : (int)$fsize) . " bytes\nFull path: $fullPath", false, 'Admin ne 0-byte file upload ki hai ya file corrupt hai.');
}

// ---- HEADERS + OUTPUT ----
while (ob_get_level()) { @ob_end_clean(); }

if (headers_sent($hf, $hl)) {
    _dl_render_swal('Headers Already Sent', "Debug: PHP ne PDF headers se PEHLE hi output bhej diya hai (e.g. warning, whitespace, echo, BOM byte).\n\nOutput started at:\nFile: $hf\nLine: $hl\n\nIske liye common reasons:\n1. koi PHP warning/notice output (console me warnings dekh sakte ho)\n2. <?php tag se pehle space/BOM byte\n3. include ki gayi file me ?> ke baad newline\n\nFix: above handlers warnings ko suppress karte hain, lekin agar warning include ke pehle aayi ho to console me dekhna padega.", false, 'Developer se share karo exact file + line');
}

$safeName = preg_replace('/[^A-Za-z0-9_-]/', '', $reqNo);
if ($safeName === '') { $safeName = 'samagra-pdf-' . $id; }
$safeName .= '.pdf';

header('Content-Type: application/pdf');
header('Content-Transfer-Encoding: binary');
header('Content-Disposition: attachment; filename="' . $safeName . '"');
header('Content-Length: ' . $fsize);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', @filemtime($fullPath)) . ' GMT');
header('Accept-Ranges: bytes');

readfile($fullPath);
exit;
