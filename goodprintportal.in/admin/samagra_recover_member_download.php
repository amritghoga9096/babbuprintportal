<?php
include('../includes/session.php');
include('../includes/config.php');
include('samagra_recover_member_common.php');

$_gpp_dl_name = 'recover-member';
$_gpp_dl_table = 'samagra_recover_member_requests';
$_gpp_dl_cols  = 'user_phone, status, application_no, member_aadhar_pdf, result_pdf_path';
$_gpp_dl_nameCol = 'application_no';
$_gpp_dl_upload = 'uploads/samagra_recover_member';
$_gpp_dl_pdfCol = 'result_pdf_path'; // default, overridden by selector below
$_gpp_dl_ensureFunc = 'ensureSamagraRecoverMemberTable';

$_gpp_dl_altFileSelector = function ($request, $isAdmin, $userPhone, $errPage) {
    $fileType = $_GET['file'] ?? 'result';
    $relativePath = null;
    $suffix = '';

    if ($fileType === 'userdoc') {
        if (!$isAdmin) {
            $errPage('Access Denied (userdoc)', 'Debug: only admin can download member-aadhar documents | your_type=' . htmlspecialchars((string)($GLOBALS['udata']['type'] ?? '')));
            return null;
        }
        $relativePath = (string)($request['member_aadhar_pdf'] ?? '');
        $suffix = '-member-aadhar';
    } else {
        if ((string)($request['status'] ?? '') !== 'approved') {
            $errPage('Not Approved', 'Debug: status=' . htmlspecialchars((string)($request['status'] ?? 'NULL')));
            return null;
        }
        if (!$isAdmin && ((string)($request['user_phone'] ?? '') !== $userPhone)) {
            $errPage('Access Denied', 'Debug: owner=' . htmlspecialchars((string)($request['user_phone'] ?? '')) . ' | you=' . htmlspecialchars($userPhone));
            return null;
        }
        $relativePath = (string)($request['result_pdf_path'] ?? '');
        $suffix = '-result';
    }

    $GLOBALS['_gpp_dl_suffix'] = $suffix;
    return $relativePath;
};

include __DIR__ . '/_samagra_download_shell.php';
