<?php
include('../includes/session.php');
include('../includes/config.php');
include('samagra_married_divorce_common.php');

$_gpp_dl_name = 'married-divorce';
$_gpp_dl_table = 'samagra_married_divorce_requests';
$_gpp_dl_cols  = 'user_phone, status, pdf_path, application_no';
$_gpp_dl_nameCol = 'application_no';
$_gpp_dl_upload = 'uploads/samagra_married_divorce';
$_gpp_dl_pdfCol = 'pdf_path';
$_gpp_dl_ensureFunc = 'ensureSamagraMarriedDivorceTable';

include __DIR__ . '/_samagra_download_shell.php';
