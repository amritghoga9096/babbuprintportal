<?php
// ============================================================================
// LL Exam status (secure) — used by ll_request_list.php "Check" button.
// Calls PariPrint server-side so the API key is never sent to the browser.
//   ?order_id=<tracking_id>               → JSON status (key-free)
//   ?order_id=<tracking_id>&download=1    → result PDF (streamed)
// ============================================================================
include('../includes/session.php');
include('../includes/config.php');
require_once __DIR__ . '/../includes/pp_secure.php';

$isDownload = !empty($_GET['download']);
$jsonOut = function (array $a, int $code = 200) {
    while (ob_get_level() > 0) { @ob_end_clean(); }
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($a, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
};

$phone = isset($udata['phone']) ? (string)$udata['phone'] : '';
if ($phone === '') {
    if ($isDownload) pp_fail_page(401, 'Login required.');
    $jsonOut(['status' => false, 'message' => 'Login required.'], 401);
}

$orderId = trim((string)($_GET['order_id'] ?? ''));
if ($orderId === '' || strlen($orderId) > 64 || !preg_match('/^[A-Za-z0-9_-]+$/', $orderId)) {
    if ($isDownload) pp_fail_page(400, 'Invalid request.');
    $jsonOut(['status' => false, 'message' => 'Invalid order id.'], 400);
}

// Only the user's own application
$safePhone = mysqli_real_escape_string($ahk_conn, $phone);
$safeOrder = mysqli_real_escape_string($ahk_conn, $orderId);
$own = mysqli_query($ahk_conn, "SELECT id FROM ll_exam_list WHERE userid='$safePhone' AND tracking_id='$safeOrder' LIMIT 1");
if (!$own || mysqli_num_rows($own) === 0) {
    if ($isDownload) pp_fail_page(404, 'Record not found.');
    $jsonOut(['status' => false, 'message' => 'Record not found.'], 404);
}

$key = pp_api_key();
$statusUrl = pp_api_base() . '/api-proxy.php?slug=ll-exam-request&api_key=' . rawurlencode($key) . '&order_id=' . rawurlencode($orderId);
$r = pp_http($statusUrl, 'GET', [], 30);
$data = pp_json($r['body']);

if ($isDownload) {
    foreach (is_array($data) ? pp_pick_urls($data) : [] as $u) {
        $f = pp_http(pp_with_key($u), 'GET', [], 90);
        if (pp_is_pdf($f)) pp_send_pdf($f, 'll-exam-result-' . $orderId . '.pdf');
    }
    pp_fail_page(404, 'Result PDF abhi available nahi hai. Thodi der baad try karein.');
}

if (!is_array($data)) {
    // Same as before: page shows its default "pending" message when no JSON comes back.
    $jsonOut(['status' => false, 'message' => 'No update yet. Admin abhi process nahi kiya.'], $r['code'] >= 400 ? $r['code'] : 200);
}

// Remove key-bearing links; give our own download link if a result exists.
$hasDownload = !empty(pp_pick_urls($data));
$clean = pp_scrub($data);
foreach ($clean as $k => $v) {
    if (is_string($v) && pp_is_private_url($v)) unset($clean[$k]);
}
foreach (['status_poll_url', 'poll_url', 'check_url'] as $k) unset($clean[$k]);
if ($hasDownload) {
    $local = 'll_status_check.php?order_id=' . rawurlencode($orderId) . '&download=1';
    $clean['download_url'] = $local;
    $clean['direct_download_url'] = $local;
}
$jsonOut($clean, $r['code'] >= 200 && $r['code'] < 600 && $r['code'] !== 0 ? $r['code'] : 200);
