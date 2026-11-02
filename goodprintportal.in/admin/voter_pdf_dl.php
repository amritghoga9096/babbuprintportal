<?php
// ============================================================================
// Voter PDF download (secure)
// Browser asks THIS file; it checks the record belongs to the logged-in user,
// then fetches the PDF from PariPrint server-side (API key never leaves server).
//   ?local_id=<voter_pdf_list.id>   or   ?rid=<PariPrint request id>
//   &m=standalone | internal        (optional: which PariPrint route to try first)
// ============================================================================
include('../includes/session.php');
include('../includes/config.php');
require_once __DIR__ . '/../includes/pp_secure.php';

$phoneRaw = isset($udata['phone']) ? (string)$udata['phone'] : '';
$phoneNorm = pp_norm_phone($phoneRaw);
if ($phoneRaw === '' || $phoneNorm === '') pp_fail_page(401, 'Login required.');

$localId = (int)($_GET['local_id'] ?? 0);
$rid = trim((string)($_GET['rid'] ?? $_GET['order_id'] ?? ''));
if ($rid !== '' && !preg_match('/^[0-9]+$/', $rid)) $rid = '';
$mode = strtolower(trim((string)($_GET['m'] ?? '')));

// ---- Find the user's own record ----
$escRaw = mysqli_real_escape_string($ahk_conn, $phoneRaw);
$escNorm = mysqli_real_escape_string($ahk_conn, $phoneNorm);
$where = "(userid='$escRaw' OR userid LIKE '%$escNorm')";
if ($localId > 0) {
    $res = mysqli_query($ahk_conn, "SELECT * FROM voter_pdf_list WHERE id=$localId AND $where LIMIT 5");
} elseif ($rid !== '') {
    $res = mysqli_query($ahk_conn, "SELECT * FROM voter_pdf_list WHERE $where ORDER BY id DESC");
} else {
    pp_fail_page(400, 'Invalid request.');
}

$oid = '';
while ($res && ($row = mysqli_fetch_assoc($res))) {
    if (pp_norm_phone((string)($row['userid'] ?? '')) !== $phoneNorm) continue;
    $pl = json_decode((string)($row['response'] ?? ''), true);
    $o = is_array($pl) ? trim((string)($pl['request_id'] ?? $pl['requestId'] ?? $pl['id'] ?? '')) : '';
    if ($o === '' && is_numeric(trim((string)($row['order_id'] ?? '')))) $o = trim((string)$row['order_id']);
    if ($localId > 0 || $o === $rid || trim((string)($row['order_id'] ?? '')) === $rid) {
        $oid = $o !== '' ? $o : $rid;
        break;
    }
}
if ($oid === '' || !preg_match('/^[0-9]+$/', $oid)) pp_fail_page(404, 'PDF record not found.');

$key = pp_api_key();
if ($key === '') pp_fail_page(500, 'PariPrint API key Settings me save nahi hai.');
$base = pp_api_base();

// ---- Try PariPrint routes (server side) ----
$standalone = $base . '/voter-pdf-api-download.php?order_id=' . urlencode($oid) . '&api_key=' . urlencode($key);
$internal = $base . '/voter-pdf-api-internal.php?action=download&order_id=' . urlencode($oid) . '&api_key=' . urlencode($key) . '&secret=' . urlencode(PP_VPDF_INTERNAL_SECRET);
$routes = $mode === 'internal' ? [$internal, $standalone] : [$standalone, $internal];

$fileName = 'voter-pdf-' . $oid . '.pdf';
foreach ($routes as $u) {
    $r = pp_http($u);
    if (pp_is_pdf($r)) pp_send_pdf($r, $fileName);
}

// Status JSON may carry another download link (e.g. stored file) — fetch it here too.
foreach (['instant-voter-pdf', 'instant-number-link-with-voter'] as $slug) {
    $s = pp_http($base . '/api-proxy.php', 'POST', ['slug' => $slug, 'order_id' => $oid, 'api_key' => $key], 30);
    $j = pp_json($s['body']);
    if (!$j) continue;
    foreach (pp_pick_urls($j) as $u) {
        $r = pp_http(pp_with_key($u));
        if (pp_is_pdf($r)) pp_send_pdf($r, $fileName);
    }
    $msg = trim((string)($j['message'] ?? $j['msg'] ?? ''));
    if ($msg !== '') pp_fail_page(404, pp_scrub($msg));
}

pp_fail_page(404, 'PDF abhi ready nahi hai. Thodi der baad "Check Status" karke dobara try karein.');
