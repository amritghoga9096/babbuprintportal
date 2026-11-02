<?php
// Download (or inline view) of the PDF / card image of one pp_service request. Owner or admin only.
include('../includes/session.php');
include('../includes/config.php');
require_once __DIR__ . '/../includes/pp_extra_services.php';

if (empty($udata['phone'])) {
    http_response_code(403);
    exit('Login required.');
}
$id = (int) ($_GET['id'] ?? 0);
$isAdmin = function_exists('checkAdmin') && checkAdmin($udata['type'] ?? '');
$phone = (string) $udata['phone'];
$row = pp_extra_get_request($ahk_conn, $id, $isAdmin ? null : $phone, null, true);

$data = $row ? json_decode((string) $row['result_json'], true) : null;
$file = is_array($data) ? pp_extra_file($data) : null;
if (!$file) {
    http_response_code(404);
    exit('File not found.');
}
$types = ['pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg'];
$name = preg_replace('/[^A-Za-z0-9_-]+/', '-', $row['slug'] . '-' . $row['application_no']) . '.' . $file['ext'];
while (ob_get_level() > 0) ob_end_clean();
header('Content-Type: ' . $types[$file['ext']]);
header('Content-Disposition: ' . (!empty($_GET['inline']) && $file['ext'] !== 'pdf' ? 'inline' : 'attachment') . '; filename="' . $name . '"');
header('Content-Length: ' . strlen($file['bin']));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
echo $file['bin'];
exit;
