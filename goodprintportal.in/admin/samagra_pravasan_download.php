<?php
include('../includes/session.php');
include('../includes/config.php');
include('samagra_pravasan_common.php');

$_gpp_dl_name = 'pravasan';
$_gpp_dl_table = 'samagra_pravasan_requests';
$_gpp_dl_cols  = 'user_phone, status, pdf_path, application_no';
$_gpp_dl_nameCol = 'application_no';
$_gpp_dl_upload = 'uploads/samagra_pravasan';
$_gpp_dl_pdfCol = 'pdf_path';
$_gpp_dl_ensureFunc = 'ensureSamagraPravasanTable';

include __DIR__ . '/_samagra_download_shell.php';
