<?php
include('../includes/session.php');
include('../includes/config.php');
include('samagra_merge_family_id_common.php');

$_gpp_dl_name = 'merge-family-id';
$_gpp_dl_table = 'samagra_merge_family_id_requests';
$_gpp_dl_cols  = 'user_phone, status, application_no, mukhiya_pdf_1, mukhiya_pdf_2, result_pdf_path';
$_gpp_dl_nameCol = 'application_no';
$_gpp_dl_upload = 'uploads/samagra_merge_family_id';
$_gpp_dl_pdfCol = 'result_pdf_path'; // default, overridden by selector below
$_gpp_dl_ensureFunc = 'ensureSamagraMergeFamilyIdTable';

$_gpp_dl_altFileSelector = function ($request, $isAdmin, $userPhone, $errPage) {
    $fileType = $_GET['file'] ?? 'result';
    $relativePath = null;
    $suffix = '';

    if ($fileType === 'mukhiya1') {
        if (!$isAdmin) {
            $errPage('Access Denied (mukhiya1)', 'Debug: only admin can download mukhiya documents | your_type=' . htmlspecialchars((string)($GLOBALS['udata']['type'] ?? '')));
            return null;
        }
        $relativePath = (string)($request['mukhiya_pdf_1'] ?? '');
        $suffix = '-mukhiya-1';
    } elseif ($fileType === 'mukhiya2') {
        if (!$isAdmin) {
            $errPage('Access Denied (mukhiya2)', 'Debug: only admin can download mukhiya documents | your_type=' . htmlspecialchars((string)($GLOBALS['udata']['type'] ?? '')));
            return null;
        }
        $relativePath = (string)($request['mukhiya_pdf_2'] ?? '');
        $suffix = '-mukhiya-2';
    } else {
        // result mode
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
