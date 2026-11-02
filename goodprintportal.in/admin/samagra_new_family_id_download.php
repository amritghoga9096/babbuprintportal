<?php
include('../includes/session.php');
include('../includes/config.php');
include('samagra_new_family_id_common.php');

$_gpp_dl_name = 'new-family-id';
$_gpp_dl_table = 'samagra_new_family_id_requests';
$_gpp_dl_cols  = 'user_phone, status, pdf_path, application_no';
$_gpp_dl_nameCol = 'application_no';
$_gpp_dl_upload = 'uploads/samagra_new_family_id';
$_gpp_dl_pdfCol = 'pdf_path';
$_gpp_dl_ensureFunc = 'ensureSamagraNewFamilyIdTable';

include __DIR__ . '/_samagra_download_shell.php';
