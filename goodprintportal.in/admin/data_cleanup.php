<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
@ob_start();
@set_time_limit(0);
@ini_set('memory_limit', '1024M');

include('../includes/session.php');
include('../includes/config.php');

if (checkAdmin($udata['type']) == false) {
    ?>
    <script> window.location='index.php'; </script>
    <?php die();
}

$PROTECTED_TABLES = [
    'users', 'settings', 'wallet', 'wallethistory', 'pricing', 'referral',
];

$STATIC_ICON_FOLDERS = [
    'Services',
    'service_icom',
];

$KEEP_FILE_NAMES = [
    'index.php',
    'index.html',
    '.htaccess',
    '.user.ini',
    'web.config',
];

$res = @mysqli_query($ahk_conn, "SHOW TABLES");
$ALL_TABLES = [];
$DATE_BASED_TABLES = [];
if ($res) {
    while ($r = mysqli_fetch_row($res)) {
        $tbl = $r[0];
        $ALL_TABLES[] = $tbl;
        if (!in_array(strtolower($tbl), array_map('strtolower', $PROTECTED_TABLES), true)) {
            $DATE_BASED_TABLES[] = $tbl;
        }
    }
}

function humanFilesize($bytes, $dec = 2) {
    $size   = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
    $factor = floor((strlen($bytes) - 1) / 3);
    if ($factor == 0) $dec = 0;
    return sprintf("%.{$dec}f ", $bytes / pow(1024, $factor)) . @$size[$factor];
}

function tblSizeInfo($conn, $dbname, $tbl) {
    $q = @mysqli_query($conn, "SELECT
        ROUND(((DATA_LENGTH + INDEX_LENGTH)), 0) AS size_bytes,
        TABLE_ROWS
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA='".mysqli_real_escape_string($conn,$dbname)."'
        AND TABLE_NAME='".mysqli_real_escape_string($conn,$tbl)."' LIMIT 1");
    if ($q && ($rw = mysqli_fetch_assoc($q))) {
        return ['bytes'=>(int)$rw['size_bytes'], 'rows'=>(int)$rw['TABLE_ROWS']];
    }
    return ['bytes'=>0, 'rows'=>0];
}

function dbTotalSize($conn, $dbname) {
    $q = @mysqli_query($conn, "SELECT
        ROUND(SUM(DATA_LENGTH + INDEX_LENGTH),0) AS total_bytes
        FROM information_schema.TABLES WHERE TABLE_SCHEMA='".mysqli_real_escape_string($conn,$dbname)."'");
    if ($q && ($r = mysqli_fetch_row($q))) return (int)$r[0];
    return 0;
}

function dirTotalSize($dir) {
    $size = 0;
    if (!is_dir($dir)) return 0;
    $items = @scandir($dir);
    if (!$items) return 0;
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_file($path)) {
            $size += @filesize($path);
        } elseif (is_dir($path)) {
            $size += dirTotalSize($path);
        }
    }
    return $size;
}

function deleteUserUploads($baseDir, $staticFolders, $keepNames, &$filesDeleted, &$bytesFreed) {
    if (!is_dir($baseDir)) return;
    $items = @scandir($baseDir);
    if (!$items) return;
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $baseDir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            if (in_array($item, $staticFolders, true)) continue;
            deleteUserUploads($path, [], $keepNames, $filesDeleted, $bytesFreed);
        } elseif (is_file($path)) {
            if (in_array(basename($item), $keepNames, true)) continue;
            $sz = @filesize($path);
            if (@unlink($path)) {
                $filesDeleted++;
                $bytesFreed += $sz;
            }
        }
    }
}

function walkAndCollect($baseDir, $excludeDirNames, $keepBasenames, &$collect) {
    if (!is_dir($baseDir)) return;
    $items = @scandir($baseDir);
    if (!$items) return;
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $baseDir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            if (is_array($excludeDirNames) && in_array($item, $excludeDirNames, true)) continue;
            walkAndCollect($path, [], $keepBasenames, $collect);
        } elseif (is_file($path)) {
            if (is_array($keepBasenames) && in_array(basename($item), $keepBasenames, true)) continue;
            $collect[] = $path;
        }
    }
}

function collectFileUrlsFromTables($conn, $tables) {
    $urls = [];
    foreach ((array)$tables as $tbl) {
        $tblEsc = mysqli_real_escape_string($conn, $tbl);
        $colsRes = @mysqli_query($conn, "SHOW COLUMNS FROM `{$tblEsc}`");
        if (!$colsRes) continue;
        $candidates = [];
        while ($c = mysqli_fetch_row($colsRes)) {
            $cn = $c[0];
            $ct = strtolower($c[1]);
            $low = strtolower($cn);
            if (strpos($low, 'file') !== false || strpos($low, 'pdf') !== false
                || strpos($low, 'image') !== false || strpos($low, 'img') !== false
                || strpos($low, 'photo') !== false || strpos($low, 'link') !== false
                || strpos($low, 'url') !== false || strpos($low, 'path') !== false
                || strpos($low, 'document') !== false || strpos($low, 'upload') !== false) {
                $candidates[] = $cn;
            }
        }
        mysqli_free_result($colsRes);
        if (empty($candidates)) continue;
        $selCols = [];
        foreach ($candidates as $cc) $selCols[] = "`".mysqli_real_escape_string($conn,$cc)."`";
        $selSql = implode(',', $selCols);
        $q = @mysqli_query($conn, "SELECT {$selSql} FROM `{$tblEsc}`");
        if (!$q) continue;
        while ($rw = mysqli_fetch_row($q)) {
            foreach ($rw as $val) {
                if ($val === null || $val === '') continue;
                $s = trim((string)$val);
                if ($s === '') continue;
                if (strlen($s) > 8 && (stripos($s, 'http') === 0 || stripos($s, '/') !== false || preg_match('/\.(pdf|jpg|jpeg|png|gif|webp|bmp|doc|docx|txt|zip)$/i', $s))) {
                    $urls[] = $s;
                }
            }
        }
        mysqli_free_result($q);
    }
    return array_values(array_unique($urls));
}

function deleteFilesByUrls($urls, $uploadDir, $adminDir, &$filesDeleted, &$bytesFreed) {
    $count = 0;
    $bytes = 0;
    foreach ((array)$urls as $raw) {
        $raw = trim((string)$raw);
        if ($raw === '') continue;
        $candidates = [];
        if (filter_var($raw, FILTER_VALIDATE_URL)) {
            $path = parse_url($raw, PHP_URL_PATH);
            if (is_string($path) && $path !== '') {
                $path = ltrim($path, '/');
                $candidates[] = $adminDir . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . $path;
                $candidates[] = $adminDir . DIRECTORY_SEPARATOR . $path;
                $basename = basename($path);
                if ($basename !== '' && $basename !== '.') {
                    $candidates[] = $uploadDir . DIRECTORY_SEPARATOR . $basename;
                }
            }
        } else {
            $candidates[] = $raw;
            $candidates[] = $adminDir . DIRECTORY_SEPARATOR . ltrim($raw, '/');
            $candidates[] = $adminDir . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . ltrim($raw, '/');
            $basename = basename($raw);
            if ($basename !== '' && $basename !== '.') {
                $candidates[] = $uploadDir . DIRECTORY_SEPARATOR . $basename;
            }
        }
        $done = false;
        foreach ($candidates as $cand) {
            $cand = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $cand);
            if (!is_file($cand)) continue;
            $sz = @filesize($cand);
            if (@unlink($cand)) {
                $count++;
                if ($sz > 0) $bytes += $sz;
                $done = true;
                break;
            }
        }
    }
    $filesDeleted += $count;
    $bytesFreed += $bytes;
    return ['files' => $count, 'bytes' => $bytes];
}

$projectRoot = realpath(__DIR__ . '/..');
$auditRows = [];
$auditTopFiles = [];

function auditBucket($label, $paths, $excludeDirs, $keepFiles, $note = '') {
    global $projectRoot;
    $total = 0; $count = 0; $list = [];
    foreach ((array)$paths as $pd) {
        $resolved = null;
        if (is_array($pd) && isset($pd['type']) && $pd['type'] === 'glob') {
            $patt = $pd['pattern'];
            if (strpos($patt, 'ROOT/') === 0) $patt = $projectRoot . str_replace('ROOT/', DIRECTORY_SEPARATOR, substr($patt, 4));
            if (strpos($patt, 'ADMIN/') === 0) $patt = __DIR__ . str_replace('ADMIN/', DIRECTORY_SEPARATOR, substr($patt, 5));
            $matched = @glob($patt);
            if (!$matched) continue;
            foreach ($matched as $m) {
                if (!is_file($m)) continue;
                if (is_array($keepFiles) && in_array(basename($m), $keepFiles, true)) continue;
                $sz = @filesize($m); if ($sz > 0) { $count++; $total += $sz; $list[] = [$m, $sz]; }
            }
            continue;
        }
        if (is_string($pd)) $resolved = $pd;
        if ($resolved === null) continue;
        if (strpos($pd, 'ROOT/') === 0) $resolved = $projectRoot . str_replace('ROOT/', DIRECTORY_SEPARATOR, substr($pd, 4));
        if (strpos($pd, 'ADMIN/') === 0) $resolved = __DIR__ . str_replace('ADMIN/', DIRECTORY_SEPARATOR, substr($pd, 5));
        if (is_file($resolved)) {
            $sz = @filesize($resolved); if ($sz > 0) { $count++; $total += $sz; $list[] = [$resolved, $sz]; }
        } elseif (is_dir($resolved)) {
            $col = [];
            walkAndCollect($resolved, $excludeDirs, $keepFiles, $col);
            foreach ($col as $cf) { $sz = @filesize($cf); if ($sz > 0) { $count++; $total += $sz; $list[] = [$cf, $sz]; } }
        }
    }
    usort($list, function($a,$b){ return $b[1] - $a[1]; });
    return ['label'=>$label, 'count'=>$count, 'bytes'=>$total, 'note'=>$note, 'top'=>array_slice($list,0,5)];
}

$auditBackup = auditBucket(
    'BACKUP / DUMPS (sql, zip, bak)',
    [
        'ROOT/goodprintportal_good.sql',
        'ROOT/goodprintportal_good.sql.gz',
        'ROOT/goodprintportal_backup.sql',
        'ROOT/database_backup.sql',
        'ROOT/db_backup.sql',
        'ADMIN/admin.zip',
        'ROOT/admin.zip',
        ['type'=>'glob','pattern'=>'ROOT/*.sql'],
        ['type'=>'glob','pattern'=>'ROOT/*.sql.gz'],
        ['type'=>'glob','pattern'=>'ROOT/*.zip'],
        ['type'=>'glob','pattern'=>'ROOT/*.bak'],
        ['type'=>'glob','pattern'=>'ROOT/backup_*.sql*'],
        ['type'=>'glob','pattern'=>'ADMIN/*.zip'],
    ], [], ['index.php','index.html'],
    'Ye 3 biggest sabse pehle: goodprintportal_good.sql (90 MB) + admin.zip (99 MB). Kabhi bhi actual code nahi hota, sirf backup/dump. Delete karna 100% safe.'
);
$auditErrorLogs = auditBucket(
    'ERROR_LOG files (PHP errors / warnings)',
    ['ADMIN/error_log', 'ROOT/error_log', 'ADMIN/aadharpvc/error_log', 'ADMIN/passbook/error_log', 'ADMIN/voteradv/error_log'],
    [], ['index.php','index.html'],
    'Isse server kabhi kuchh fix nahi hota — sirf bade hote rehte hain (admin/error_log alone 19 MB)'
);
$auditDebugLogs = auditBucket(
    '/logs/ — RC debug TXT files (?rcnc_debug=1)',
    ['ROOT/logs'],
    [], ['index.php','index.html','.htaccess'],
    'RC no-chip debug karne waqt har request ki alag file banti hai — 158+ files = ~95 MB'
);
$auditUserUploads = auditBucket(
    'admin/uploads/ — User uploaded PDFs / photos',
    ['ADMIN/uploads'],
    ['Services','service_icom'],
    ['index.php','index.html','.htaccess'],
    'User ke uploaded documents (DL temp PDFs, aadhar photos, WhatsApp screenshots). Icons/Service folders SAFE (exclude).'
);
$auditAadharManual = auditBucket(
    'imgmanualaadhaar/ — Aadhar Manual request photos',
    ['ADMIN/aadharpvc/imgmanualaadhaar'],
    [], ['index.php','index.html','.htaccess'],
    'Aadhar manual form submit karte waqt user ka photo save ho jata hai — ~124 photos = 21 MB'
);

$auditAll = [$auditBackup, $auditErrorLogs, $auditDebugLogs, $auditUserUploads, $auditAadharManual];
$auditTotalBytes = 0; $auditTotalFiles = 0;
foreach ($auditAll as $a) { $auditTotalBytes += $a['bytes']; $auditTotalFiles += $a['count']; }

$topList = [];
foreach ($auditAll as $a) { foreach ($a['top'] as $t) { $topList[] = ['path'=>$t[0],'bytes'=>$t[1],'bucket'=>$a['label']]; } }
usort($topList, function($a,$b){ return $b['bytes'] - $a['bytes']; });
$topList = array_slice($topList, 0, 12);

function nicePath($abs) {
    global $projectRoot;
    $s = str_replace('\\','/',$abs);
    $pr = str_replace('\\','/',$projectRoot);
    if (strpos($s, $pr) === 0) return ltrim(substr($s, strlen($pr)), '/');
    return $s;
}

$dbname = $database;
$totalBytesBefore = dbTotalSize($ahk_conn, $dbname);
$uploadDir = __DIR__ . '/uploads';
$uploadSizeBefore = dirTotalSize($uploadDir);

$cleanLog = [];
$fileCleanLog = [];
$totalCleanedBytes = 0;
$totalDeletedRows = 0;
$totalFilesDeleted = 0;
$totalFileBytesFreed = 0;
$runDone = false;
$zipRunDone = false;
$errorMsg = '';

$INSTANT_NOW = (isset($_GET['instant']) && $_GET['instant'] == '1')
    || (isset($_POST['instant_run']) && $_POST['instant_run'] == '1');

$ZIP_NOW = (isset($_GET['zip_now']) && $_GET['zip_now'] == '1')
    || (isset($_POST['zip_only_run']) && $_POST['zip_only_run'] == '1');

$VOTER_PDF_SQL_TABLES = ['voter_pdf_list', 'voter_pdf_history'];
$LL_REQUEST_SQL_TABLES = ['ll_exam_list', 'larning_exam'];
$COMPLAINT_SQL_TABLES = ['complaints', 'contact'];
$VOTER_PDF_FILE_TABLES = ['voter_pdf_list', 'voter_pdf_history'];
$LL_REQUEST_FILE_TABLES = ['ll_exam_list', 'larning_exam'];
$COMPLAINT_FILE_TABLES = [];

if ($INSTANT_NOW) {
    $_POST['run_cleanup'] = '1';
    $_POST['days_older'] = 1;
    $_POST['do_optimize'] = '1';
    $_POST['clean_complaints_solved'] = '1';
    $_POST['do_full_truncate'] = '1';
    $_POST['do_delete_uploads'] = '1';
    $_POST['do_delete_backups'] = '1';
    // Unchecked checkboxes are omitted from POST. Treat omission as an explicit
    // skip so the instant controls cannot accidentally delete protected services.
    if (!isset($_POST['do_sql_voter_pdf'])) $_POST['do_sql_voter_pdf'] = (isset($_POST['instant_sql_voter_pdf']) ? '1' : '0');
    if (!isset($_POST['do_sql_ll_request'])) $_POST['do_sql_ll_request'] = (isset($_POST['instant_sql_ll_request']) ? '1' : '0');
    if (!isset($_POST['do_sql_complaints'])) $_POST['do_sql_complaints'] = (isset($_POST['instant_sql_complaints']) ? '1' : '0');
    if (!isset($_POST['do_files_voter_pdf'])) $_POST['do_files_voter_pdf'] = $_POST['do_sql_voter_pdf'];
    if (!isset($_POST['do_files_ll_request'])) $_POST['do_files_ll_request'] = $_POST['do_sql_ll_request'];
    if (!isset($_POST['do_files_complaints'])) $_POST['do_files_complaints'] = $_POST['do_sql_complaints'];
}

$clean_sql_voter_pdf = false;
$clean_sql_ll_request = false;
$clean_sql_complaints = false;
$clean_files_voter_pdf = false;
$clean_files_ll_request = false;
$clean_files_complaints = false;
if (isset($_POST['run_cleanup']) || $ZIP_NOW) {
    if ($ZIP_NOW) {
        $clean_files_voter_pdf = isset($_POST['zip_files_voter_pdf']) && $_POST['zip_files_voter_pdf'] == '1';
        $clean_files_ll_request = isset($_POST['zip_files_ll_request']) && $_POST['zip_files_ll_request'] == '1';
        $clean_files_complaints = isset($_POST['zip_files_complaints']) && $_POST['zip_files_complaints'] == '1';
    } else {
        $clean_sql_voter_pdf = isset($_POST['do_sql_voter_pdf']) && $_POST['do_sql_voter_pdf'] == '1';
        $clean_sql_ll_request = isset($_POST['do_sql_ll_request']) && $_POST['do_sql_ll_request'] == '1';
        $clean_sql_complaints = isset($_POST['do_sql_complaints']) && $_POST['do_sql_complaints'] == '1';
        $clean_files_voter_pdf = isset($_POST['do_files_voter_pdf']) && $_POST['do_files_voter_pdf'] == '1';
        $clean_files_ll_request = isset($_POST['do_files_ll_request']) && $_POST['do_files_ll_request'] == '1';
        $clean_files_complaints = isset($_POST['do_files_complaints']) && $_POST['do_files_complaints'] == '1';
    }
}

$EXTRA_PROTECTED_TABLES = [];
if (!$clean_sql_voter_pdf) $EXTRA_PROTECTED_TABLES = array_merge($EXTRA_PROTECTED_TABLES, $VOTER_PDF_SQL_TABLES);
if (!$clean_sql_ll_request) $EXTRA_PROTECTED_TABLES = array_merge($EXTRA_PROTECTED_TABLES, $LL_REQUEST_SQL_TABLES);
if (!$clean_sql_complaints) $EXTRA_PROTECTED_TABLES = array_merge($EXTRA_PROTECTED_TABLES, $COMPLAINT_SQL_TABLES);

function zipOnlyRunFileCleanup($uploadDir, $STATIC_ICON_FOLDERS, $KEEP_FILE_NAMES, &$fileCleanLog, &$totalFilesDeleted, &$totalFileBytesFreed, $cleanFilesVoterPdf = true, $cleanFilesLlRequest = true, $cleanFilesComplaints = true, $ahk_conn = null, $adminDir = __DIR__) {
    $doDeleteUploads = true;
    $doDeleteBackups = true;
    if ($doDeleteUploads) {
        $filesBefore = $totalFilesDeleted;
        $bytesBefore = $totalFileBytesFreed;
        deleteUserUploads($uploadDir, $STATIC_ICON_FOLDERS, $KEEP_FILE_NAMES, $totalFilesDeleted, $totalFileBytesFreed);
        $fileCleanLog[] = [
            "area" => "admin/uploads/",
            "desc" => "User-uploaded PDFs, JPGs, PNGs (static icon folders Services, service_icom kept safe)",
            "files" => $totalFilesDeleted - $filesBefore,
            "bytes" => humanFilesize($totalFileBytesFreed - $bytesBefore),
        ];

        $tempDirs = [
            __DIR__ . '/dlprints',
            __DIR__ . '/sample',
            __DIR__ . '/passbook/1/img',
            __DIR__ . '/passbook/1/airtel/img',
            __DIR__ . '/passbook/1/post/img',
            __DIR__ . '/voteradv/sign',
            __DIR__ . '/voteradv/voterimage',
            __DIR__ . '/voteradv/voterbg',
        ];
        foreach ($tempDirs as $tdir) {
            if (!is_dir($tdir)) continue;
            $fb = $totalFilesDeleted;
            $bb = $totalFileBytesFreed;
            deleteUserUploads($tdir, [], ['index.php', 'index.html', '.htaccess', '1.svg', 'ASSAM.png', 'aASSAM.png', '1.png', '2.png', 'dlBack.jpg', 'dlFront.jpg', 'print_hkb.php', 'print_n.php', 'Sign_new_.png', 'votersign2.png', 'votersign222.png', 'votersign.png', 'votersign 1.png', 'back-new1.jpeg', 'front-new1.jpeg', 'votersign2.jpg', 'voterhindiback12.jpg', 'voterhindiback2.jpg', 'voterhindiback4.jpg', 'voterhindifront1.jpg', 'config.php', '1.jpg', '2.jpg', '3.png', 'AadhaarSample.pdf', 'english_dec.pdf', 'self_declaration.pdf'], $totalFilesDeleted, $totalFileBytesFreed);
            if (($totalFilesDeleted - $fb) > 0) {
                $fileCleanLog[] = [
                    "area" => str_replace(__DIR__, 'admin', $tdir),
                    "desc" => "Temp/preview stored assets",
                    "files" => $totalFilesDeleted - $fb,
                    "bytes" => humanFilesize($totalFileBytesFreed - $bb),
                ];
            }
        }

        $dynamicJunkDirs = [
            __DIR__ . '/aadharpvc/imgmanualaadhaar' => ['index.php', 'index.html', '.htaccess'],
            __DIR__ . '/../logs' => ['index.php', 'index.html', '.htaccess'],
        ];
        foreach ($dynamicJunkDirs as $jd => $keep) {
            if (!is_dir($jd)) continue;
            $fb = $totalFilesDeleted;
            $bb = $totalFileBytesFreed;
            deleteUserUploads($jd, [], $keep, $totalFilesDeleted, $totalFileBytesFreed);
            if (($totalFilesDeleted - $fb) > 0) {
                $niceName = str_replace([__DIR__ . '/', __DIR__ . '/../'], ['admin/', ''], $jd);
                $desc = '';
                if (stripos($jd, 'imgmanualaadhaar') !== false) $desc = 'Aadhar manual process karte waqt bani upload hui photos (har request ki saved copy)';
                if (stripos($jd, 'logs') !== false) $desc = 'RC download debugging ke txt logs (?rcnc_debug=1 se generate hote)';
                $fileCleanLog[] = [
                    "area" => $niceName,
                    "desc" => $desc,
                    "files" => $totalFilesDeleted - $fb,
                    "bytes" => humanFilesize($totalFileBytesFreed - $bb),
                ];
            }
        }
    }

    if ($doDeleteBackups) {
        $backupCandidates = [
            __DIR__ . '/../goodprintportal_good.sql',
            __DIR__ . '/../goodprintportal_good.sql.gz',
            __DIR__ . '/../goodprintportal_backup.sql',
            __DIR__ . '/../database_backup.sql',
            __DIR__ . '/../db_backup.sql',
            __DIR__ . '/admin.zip',
            __DIR__ . '/../admin.zip',
            __DIR__ . '/error_log',
            __DIR__ . '/../error_log',
            __DIR__ . '/aadharpvc/error_log',
            __DIR__ . '/passbook/error_log',
            __DIR__ . '/voteradv/error_log',
        ];
        $globPatterns = [
            __DIR__ . '/../*.sql',
            __DIR__ . '/../*.sql.gz',
            __DIR__ . '/../*.zip',
            __DIR__ . '/*.zip',
            __DIR__ . '/../backup_*.sql*',
            __DIR__ . '/../*.bak',
        ];
        $candsFromGlob = [];
        foreach ($globPatterns as $gp) {
            $matched = @glob($gp);
            if ($matched) $candsFromGlob = array_merge($candsFromGlob, $matched);
        }
        $backupCandidates = array_merge($backupCandidates, $candsFromGlob);
        $backupCandidates = array_unique($backupCandidates);

        foreach ($backupCandidates as $bk) {
            if (!is_file($bk)) continue;
            $sz = @filesize($bk);
            if (@unlink($bk)) {
                $totalFilesDeleted++;
                $totalFileBytesFreed += $sz;
                $fileCleanLog[] = [
                    "area" => str_replace([__DIR__ . '/../', __DIR__ . '/'], '', $bk),
                    "desc" => "Backup / dump / log file",
                    "files" => 1,
                    "bytes" => humanFilesize($sz),
                ];
            }
        }
    }

    global $VOTER_PDF_FILE_TABLES, $LL_REQUEST_FILE_TABLES;
    if ($ahk_conn) {
        if ($cleanFilesVoterPdf && !empty($VOTER_PDF_FILE_TABLES)) {
            $urls = collectFileUrlsFromTables($ahk_conn, $VOTER_PDF_FILE_TABLES);
            $res = deleteFilesByUrls($urls, $uploadDir, $adminDir, $totalFilesDeleted, $totalFileBytesFreed);
            if ($res['files'] > 0) {
                $fileCleanLog[] = [
                    "area" => "admin/uploads/ + voter_pdf_list records",
                    "desc" => "Voter PDF service ke DB records mein stored uploaded files (PDF, JPG, PNG links)",
                    "files" => $res['files'],
                    "bytes" => humanFilesize($res['bytes']),
                ];
            }
        }
        if ($cleanFilesLlRequest && !empty($LL_REQUEST_FILE_TABLES)) {
            $urls = collectFileUrlsFromTables($ahk_conn, $LL_REQUEST_FILE_TABLES);
            $res = deleteFilesByUrls($urls, $uploadDir, $adminDir, $totalFilesDeleted, $totalFileBytesFreed);
            if ($res['files'] > 0) {
                $fileCleanLog[] = [
                    "area" => "admin/uploads/ + ll_exam_list / larning_exam records",
                    "desc" => "LL Request / LL Exam service ke DB records mein stored uploaded Aadhaar PDFs / photos",
                    "files" => $res['files'],
                    "bytes" => humanFilesize($res['bytes']),
                ];
            }
        }
    }
}

if ($ZIP_NOW) {
    zipOnlyRunFileCleanup($uploadDir, $STATIC_ICON_FOLDERS, $KEEP_FILE_NAMES, $fileCleanLog, $totalFilesDeleted, $totalFileBytesFreed, $clean_files_voter_pdf, $clean_files_ll_request, $clean_files_complaints, $ahk_conn, __DIR__);
    $zipRunDone = true;
    $runDone = true;
    $totalCleanedBytes = 0;
    $totalDeletedRows = 0;
    $totalBytesAfter = dbTotalSize($ahk_conn, $dbname);
    $uploadSizeAfter = dirTotalSize($uploadDir);
}
elseif (isset($_POST['run_cleanup']) && $_POST['run_cleanup'] == '1') {
    $days = isset($_POST['days_older']) ? max(1, (int)$_POST['days_older']) : 90;
    $doOptimize = isset($_POST['do_optimize']) && $_POST['do_optimize'] == '1';
    $cleanComplaints = isset($_POST['clean_complaints_solved']) && $_POST['clean_complaints_solved'] == '1';
    $doFullTruncate = isset($_POST['do_full_truncate']) && $_POST['do_full_truncate'] == '1';
    $doDeleteUploads = isset($_POST['do_delete_uploads']) && $_POST['do_delete_uploads'] == '1';
    $doDeleteBackups = isset($_POST['do_delete_backups']) && $_POST['do_delete_backups'] == '1';
    $dateCutoff = date('Y-m-d H:i:s', time() - ($days * 86400));

    @mysqli_query($ahk_conn, "SET foreign_key_checks = 0");

    foreach ($ALL_TABLES as $tbl) {
        $isProtectedCore = in_array(strtolower($tbl), array_map('strtolower', $PROTECTED_TABLES), true);
        $isProtectedExtra = in_array(strtolower($tbl), array_map('strtolower', $EXTRA_PROTECTED_TABLES), true);
        if ($isProtectedCore || $isProtectedExtra) {
            if ($doOptimize) {
                @mysqli_query($ahk_conn, "ALTER TABLE `".mysqli_real_escape_string($ahk_conn,$tbl)."` FORCE");
                @mysqli_query($ahk_conn, "OPTIMIZE TABLE `".mysqli_real_escape_string($ahk_conn,$tbl)."`");
            }
            $protLabel = $isProtectedCore ? "PROTECTED (Users/Wallet/Transactions/Settings - NO rows deleted)" : "SKIPPED (Checkbox unchecked - " . htmlspecialchars($tbl) . " kept safe)";
            $cleanLog[] = ["table"=>$tbl, "action"=>$protLabel . ($doOptimize ? " + REBUILT (FORCE+OPTIMIZE)" : ""), "rows"=>0, "bytes_saved"=>"0 B"];
            continue;
        }

        $infoBefore = tblSizeInfo($ahk_conn, $dbname, $tbl);
        $deletedThisTable = 0;

        if ($doFullTruncate) {
            $tr = @mysqli_query($ahk_conn, "TRUNCATE TABLE `".mysqli_real_escape_string($ahk_conn,$tbl)."`");
            if ($tr) {
                $deletedThisTable += (int)$infoBefore['rows'];
            } else {
                $td = @mysqli_query($ahk_conn, "DELETE FROM `".mysqli_real_escape_string($ahk_conn,$tbl)."`");
                if ($td) $deletedThisTable += (int)mysqli_affected_rows($ahk_conn);
                @mysqli_query($ahk_conn, "ALTER TABLE `".mysqli_real_escape_string($ahk_conn,$tbl)."` AUTO_INCREMENT = 1");
            }
        } else {
            if ($tbl === 'contact' && $cleanComplaints) {
                $d = @mysqli_query($ahk_conn, "DELETE FROM `".mysqli_real_escape_string($ahk_conn,$tbl)."`
                    WHERE (reply IS NOT NULL AND reply != '' AND reply != 'Not Solve Yet')
                    OR (status IS NOT NULL AND (LOWER(status)='solved' OR LOWER(status)='closed'))");
                if ($d) $deletedThisTable += (int)mysqli_affected_rows($ahk_conn);
            }

            if (in_array($tbl, $DATE_BASED_TABLES, true)) {
                $cols = @mysqli_query($ahk_conn, "SHOW COLUMNS FROM `".mysqli_real_escape_string($ahk_conn,$tbl)."`");
                $dateCols = [];
                if ($cols) {
                    while ($c = mysqli_fetch_row($cols)) {
                        $cn = $c[0];
                        $ct = strtolower($c[1]);
                        if (strpos($ct, 'date') !== false || strpos($ct, 'time') !== false
                            || in_array(strtolower($cn),['created_at','updated_at','date','datetime','created','timestamp','txn_date'],true)) {
                            $dateCols[] = $cn;
                        }
                    }
                }
                if (!empty($dateCols)) {
                    $whereParts = [];
                    foreach ($dateCols as $dc) {
                        $whereParts[] = "(`{$dc}` IS NULL OR `{$dc}` = '' OR `{$dc}` = '0000-00-00' OR `{$dc}` = '0000-00-00 00:00:00' OR `{$dc}` < '".mysqli_real_escape_string($ahk_conn,$dateCutoff)."')";
                    }
                    $whereSql = implode(' OR ', $whereParts);
                    $dSql = "DELETE FROM `".mysqli_real_escape_string($ahk_conn,$tbl)."` WHERE ({$whereSql})";
                    $d2 = @mysqli_query($ahk_conn, $dSql);
                    if ($d2) $deletedThisTable += (int)mysqli_affected_rows($ahk_conn);
                }
            }
        }

        if ($doOptimize) {
            @mysqli_query($ahk_conn, "ALTER TABLE `".mysqli_real_escape_string($ahk_conn,$tbl)."` FORCE");
            @mysqli_query($ahk_conn, "OPTIMIZE TABLE `".mysqli_real_escape_string($ahk_conn,$tbl)."`");
        }

        $infoAfter = tblSizeInfo($ahk_conn, $dbname, $tbl);
        $savedBytes = max(0, $infoBefore['bytes'] - $infoAfter['bytes']);
        $totalCleanedBytes += $savedBytes;
        $totalDeletedRows += $deletedThisTable;

        $cleanLog[] = [
            "table"  => $tbl,
            "action" => ($doFullTruncate ? "FULL TRUNCATE ✅" : "Cleaned old records (> {$days} days)")
                . ((!$doFullTruncate && $tbl === 'contact' && $cleanComplaints) ? " + Solved complaints" : "")
                . ($doOptimize ? " + REBUILT (FORCE+OPTIMIZE)" : ""),
            "rows"   => $deletedThisTable,
            "bytes_saved" => humanFilesize($savedBytes),
        ];
    }

    @mysqli_query($ahk_conn, "SET foreign_key_checks = 1");

    if ($doDeleteUploads) {
        $filesBefore = $totalFilesDeleted;
        $bytesBefore = $totalFileBytesFreed;
        deleteUserUploads($uploadDir, $STATIC_ICON_FOLDERS, $KEEP_FILE_NAMES, $totalFilesDeleted, $totalFileBytesFreed);
        $fileCleanLog[] = [
            "area" => "admin/uploads/",
            "desc" => "User-uploaded PDFs, JPGs, PNGs (static icon folders Services, service_icom kept safe)",
            "files" => $totalFilesDeleted - $filesBefore,
            "bytes" => humanFilesize($totalFileBytesFreed - $bytesBefore),
        ];

        $tempDirs = [
            __DIR__ . '/dlprints',
            __DIR__ . '/sample',
            __DIR__ . '/passbook/1/img',
            __DIR__ . '/passbook/1/airtel/img',
            __DIR__ . '/passbook/1/post/img',
            __DIR__ . '/voteradv/sign',
            __DIR__ . '/voteradv/voterimage',
            __DIR__ . '/voteradv/voterbg',
        ];
        foreach ($tempDirs as $tdir) {
            if (!is_dir($tdir)) continue;
            $fb = $totalFilesDeleted;
            $bb = $totalFileBytesFreed;
            deleteUserUploads($tdir, [], ['index.php', 'index.html', '.htaccess', '1.svg', 'ASSAM.png', 'aASSAM.png', '1.png', '2.png', 'dlBack.jpg', 'dlFront.jpg', 'print_hkb.php', 'print_n.php', 'Sign_new_.png', 'votersign2.png', 'votersign222.png', 'votersign.png', 'votersign 1.png', 'back-new1.jpeg', 'front-new1.jpeg', 'votersign2.jpg', 'voterhindiback12.jpg', 'voterhindiback2.jpg', 'voterhindiback4.jpg', 'voterhindifront1.jpg', 'config.php', '1.jpg', '2.jpg', '3.png', 'AadhaarSample.pdf', 'english_dec.pdf', 'self_declaration.pdf'], $totalFilesDeleted, $totalFileBytesFreed);
            if (($totalFilesDeleted - $fb) > 0) {
                $fileCleanLog[] = [
                    "area" => str_replace(__DIR__, 'admin', $tdir),
                    "desc" => "Temp/preview stored assets",
                    "files" => $totalFilesDeleted - $fb,
                    "bytes" => humanFilesize($totalFileBytesFreed - $bb),
                ];
            }
        }

        $dynamicJunkDirs = [
            __DIR__ . '/aadharpvc/imgmanualaadhaar' => ['index.php', 'index.html', '.htaccess'],
            __DIR__ . '/../logs' => ['index.php', 'index.html', '.htaccess'],
        ];
        foreach ($dynamicJunkDirs as $jd => $keep) {
            if (!is_dir($jd)) continue;
            $fb = $totalFilesDeleted;
            $bb = $totalFileBytesFreed;
            deleteUserUploads($jd, [], $keep, $totalFilesDeleted, $totalFileBytesFreed);
            if (($totalFilesDeleted - $fb) > 0) {
                $niceName = str_replace([__DIR__ . '/', __DIR__ . '/../'], ['admin/', ''], $jd);
                $desc = '';
                if (stripos($jd, 'imgmanualaadhaar') !== false) $desc = 'Aadhar manual process karte waqt bani upload hui photos (har request ki saved copy)';
                if (stripos($jd, 'logs') !== false) $desc = 'RC download debugging ke 95 MB+ txt logs (?rcnc_debug=1 se generate hote)';
                $fileCleanLog[] = [
                    "area" => $niceName,
                    "desc" => $desc,
                    "files" => $totalFilesDeleted - $fb,
                    "bytes" => humanFilesize($totalFileBytesFreed - $bb),
                ];
            }
        }

        if ($clean_files_voter_pdf && !empty($VOTER_PDF_FILE_TABLES)) {
            $urls = collectFileUrlsFromTables($ahk_conn, $VOTER_PDF_FILE_TABLES);
            $res = deleteFilesByUrls($urls, $uploadDir, __DIR__, $totalFilesDeleted, $totalFileBytesFreed);
            if ($res['files'] > 0) {
                $fileCleanLog[] = [
                    "area" => "admin/uploads/ + voter_pdf_list records",
                    "desc" => "Voter PDF service ke DB records mein stored uploaded files (PDF, JPG, PNG links)",
                    "files" => $res['files'],
                    "bytes" => humanFilesize($res['bytes']),
                ];
            }
        }
        if ($clean_files_ll_request && !empty($LL_REQUEST_FILE_TABLES)) {
            $urls = collectFileUrlsFromTables($ahk_conn, $LL_REQUEST_FILE_TABLES);
            $res = deleteFilesByUrls($urls, $uploadDir, __DIR__, $totalFilesDeleted, $totalFileBytesFreed);
            if ($res['files'] > 0) {
                $fileCleanLog[] = [
                    "area" => "admin/uploads/ + ll_exam_list / larning_exam records",
                    "desc" => "LL Request / LL Exam service ke DB records mein stored uploaded Aadhaar PDFs / photos",
                    "files" => $res['files'],
                    "bytes" => humanFilesize($res['bytes']),
                ];
            }
        }
    }

    if ($doDeleteBackups) {
        $backupCandidates = [
            __DIR__ . '/../goodprintportal_good.sql',
            __DIR__ . '/../goodprintportal_good.sql.gz',
            __DIR__ . '/../goodprintportal_backup.sql',
            __DIR__ . '/../database_backup.sql',
            __DIR__ . '/../db_backup.sql',
            __DIR__ . '/admin.zip',
            __DIR__ . '/../admin.zip',
            __DIR__ . '/error_log',
            __DIR__ . '/../error_log',
            __DIR__ . '/aadharpvc/error_log',
            __DIR__ . '/passbook/error_log',
            __DIR__ . '/voteradv/error_log',
        ];
        $globPatterns = [
            __DIR__ . '/../*.sql',
            __DIR__ . '/../*.sql.gz',
            __DIR__ . '/../*.zip',
            __DIR__ . '/*.zip',
            __DIR__ . '/../backup_*.sql*',
            __DIR__ . '/../*.bak',
        ];
        $candsFromGlob = [];
        foreach ($globPatterns as $gp) {
            $matched = @glob($gp);
            if ($matched) $candsFromGlob = array_merge($candsFromGlob, $matched);
        }
        $backupCandidates = array_merge($backupCandidates, $candsFromGlob);
        $backupCandidates = array_unique($backupCandidates);

        foreach ($backupCandidates as $bk) {
            if (!is_file($bk)) continue;
            $sz = @filesize($bk);
            if (@unlink($bk)) {
                $totalFilesDeleted++;
                $totalFileBytesFreed += $sz;
                $fileCleanLog[] = [
                    "area" => str_replace([__DIR__ . '/../', __DIR__ . '/'], '', $bk),
                    "desc" => "Backup / dump / log file",
                    "files" => 1,
                    "bytes" => humanFilesize($sz),
                ];
            }
        }
    }

    $runDone = true;
    $totalBytesAfter = dbTotalSize($ahk_conn, $dbname);
    $uploadSizeAfter = dirTotalSize($uploadDir);
}

include(__DIR__ . '/../template/ahkweb/header.php');
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>

<div class="page-wrapper">
    <div class="page-content">
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item"><a href="index.php"><i class="bx bx-home-alt"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Data Cleanup &amp; Database Maintenance</li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-2">
                <div class="card border-top border-0 border-4 border-primary shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="me-2"><i class="fa-solid fa-database fs-3 text-primary"></i></div>
                            <div>
                                <h6 class="mb-0 text-muted small">Database</h6>
                                <h5 class="mb-0 text-primary"><?php echo humanFilesize($totalBytesBefore); ?></h5>
                                <small class="text-muted"><?php echo count($ALL_TABLES); ?> tables</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card border-top border-0 border-4 border-info shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="me-2"><i class="fa-solid fa-file-arrow-up fs-3 text-info"></i></div>
                            <div>
                                <h6 class="mb-0 text-muted small">Uploads</h6>
                                <h5 class="mb-0 text-info"><?php echo humanFilesize($uploadSizeBefore); ?></h5>
                                <small class="text-muted">admin/uploads/</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card border-top border-0 border-4 border-dark shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="me-2"><i class="fa-solid fa-dumpster-fire fs-3 text-dark"></i></div>
                            <div>
                                <h6 class="mb-0 text-muted small">Backups + Logs</h6>
                                <h5 class="mb-0 text-dark"><?php echo humanFilesize($auditBackup['bytes'] + $auditErrorLogs['bytes'] + $auditDebugLogs['bytes']); ?></h5>
                                <small class="text-muted">sql/zip + error + debug logs</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card border-top border-0 border-4 border-warning shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="me-2"><i class="fa-solid fa-camera-retro fs-3 text-warning"></i></div>
                            <div>
                                <h6 class="mb-0 text-muted small">Aadhar Manual</h6>
                                <h5 class="mb-0 text-warning"><?php echo humanFilesize($auditAadharManual['bytes']); ?></h5>
                                <small class="text-muted"><?php echo number_format($auditAadharManual['count']); ?> saved photos</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card border-top border-0 border-4 border-danger shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="me-2"><i class="fa-solid fa-trash-can-arrow-up fs-3 text-danger"></i></div>
                            <div>
                                <h6 class="mb-0 text-muted small">🔥 TOTAL JUNK</h6>
                                <h5 class="mb-0 text-danger"><?php echo humanFilesize($auditTotalBytes); ?></h5>
                                <small class="text-muted"><?php echo number_format($auditTotalFiles); ?> files deletable</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card border-top border-0 border-4 border-success shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="me-2"><i class="fa-solid fa-shield-halved fs-3 text-success"></i></div>
                            <div>
                                <h6 class="mb-0 text-muted small">Protected</h6>
                                <h5 class="mb-0 text-success">✅ SAFE</h5>
                                <small class="text-muted">users wallet wallethistory +</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!$runDone): ?>
        <div class="row mb-4">
            <div class="col-xl-12 mx-auto">
                <div class="card border-top border-0 border-4 border-secondary shadow-sm">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h5 class="mb-0 text-secondary">
                                <i class="fa-solid fa-magnifying-glass-chart me-2"></i>
                                📊 BIG FILES AUDIT — Zip size kyo itna bada hai?
                            </h5>
                            <span class="badge bg-dark">Auto-scanned live</span>
                        </div>
                        <div class="table-responsive mb-4">
                            <table class="table table-sm table-hover align-middle">
                                <thead class="table-dark">
                                    <tr>
                                        <th>#</th>
                                        <th>Category</th>
                                        <th class="text-end">Files</th>
                                        <th class="text-end">Size</th>
                                        <th>Note</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $colors = ['primary','dark','warning','info','purple'];
                                    $ci = 0;
                                    foreach ($auditAll as $row):
                                    ?>
                                    <tr>
                                        <td class="fw-bold"><?php echo ++$ci; ?></td>
                                        <td class="fw-semibold text-<?php echo $colors[$ci-1] ?? 'dark'; ?>">
                                            <?php echo htmlspecialchars($row['label']); ?>
                                        </td>
                                        <td class="text-end"><?php echo number_format($row['count']); ?></td>
                                        <td class="text-end fw-bold"><?php echo humanFilesize($row['bytes']); ?></td>
                                        <td class="small text-muted"><?php echo htmlspecialchars($row['note']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-danger fw-bold">
                                    <tr>
                                        <td colspan="2" class="text-end">TOTAL DELETABLE JUNK FILES</td>
                                        <td class="text-end"><?php echo number_format($auditTotalFiles); ?></td>
                                        <td class="text-end"><?php echo humanFilesize($auditTotalBytes); ?></td>
                                        <td class="small text-danger">In sabko hatane ke baad ZIP size itna kam ho jayega</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <h6 class="mb-2 text-dark">🔥 TOP 12 SINGLE LARGEST FILES (individual culprits):</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-warning">
                                    <tr>
                                        <th>#</th>
                                        <th>File (relative to project root)</th>
                                        <th>Category</th>
                                        <th class="text-end">Size</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $i=0; foreach ($topList as $t): $i++; ?>
                                    <tr>
                                        <td class="fw-bold"><?php echo $i; ?></td>
                                        <td class="small text-break"><code><?php echo htmlspecialchars(nicePath($t['path'])); ?></code></td>
                                        <td class="small text-muted"><?php echo htmlspecialchars($t['bucket']); ?></td>
                                        <td class="text-end fw-bold text-danger"><?php echo humanFilesize($t['bytes']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (count($topList) === 0): ?>
                                    <tr><td colspan="4" class="text-center text-success"><i class="fa-solid fa-check-circle me-1"></i> Abhi koi junk nahi hai — sab clean hai!</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4 pt-3 border-top border-secondary border-opacity-25">
                            <div class="d-flex flex-column gap-3">
                                <div class="small">
                                    <i class="fa-solid fa-circle-info me-1 text-info"></i>
                                    Is button se <b>sirf files delete hongi</b> (junk audit wali sabhi) —
                                    <span class="text-success fw-bold">DB ek row bhi touch nahi hoga.</span>
                                    Website ka koi bhi service / user / wallet — sab as-it-is rahega.
                                </div>
                                <div class="row g-3 bg-white p-3 rounded border border-info border-opacity-25">
                                    <div class="col-md-6">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="zip_files_voter_pdf" name="zip_files_voter_pdf" value="1" checked form="zipOnlyForm">
                                            <label class="form-check-label fw-semibold text-info" for="zip_files_voter_pdf">
                                                🗳️ Delete Voter PDF uploaded files
                                            </label>
                                            <div class="form-text">voter_pdf_list / voter_pdf_history records ke stored file links se actual uploads delete hongi.</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="zip_files_ll_request" name="zip_files_ll_request" value="1" checked form="zipOnlyForm">
                                            <label class="form-check-label fw-semibold text-info" for="zip_files_ll_request">
                                                📝 Delete LL Request / LL Exam uploaded files
                                            </label>
                                            <div class="form-text">ll_exam_list aur larning_exam tables ke stored Aadhaar PDFs / photos delete hongi.</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-lg-end justify-content-center">
                                    <form method="POST" id="zipOnlyForm" class="d-inline">
                                        <input type="hidden" name="zip_only_run" value="1">
                                    </form>
                                    <button type="button" id="zipCleanBtn"
                                            class="btn btn-lg btn-outline-info px-5 py-3 fw-bold shadow-sm"
                                            style="background:linear-gradient(135deg,#0d6efd 0%,#6610f2 100%);color:#fff;border:none;">
                                        <i class="fa-solid fa-file-zipper me-2"></i>
                                        🧹 CLEANUP ZIP NOW (Delete <?php echo humanFilesize($auditTotalBytes); ?> · <?php echo number_format($auditTotalFiles); ?> files)
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($runDone): ?>
        <div class="container mb-0 px-0">
            <?php if ($zipRunDone): ?>
            <div class="alert alert-info alert-dismissible fade show border-0 bg-info bg-opacity-10 text-info mb-4" role="alert">
                <i class="bi bi-file-zip-fill me-2"></i>
                <b>🧹 ZIP Cleanup Complete! (Sirf files delete, DATABASE 100% UNTOUCHED)</b><br>
                ✅ Website poora as-it-is kaam karega — koi bhi table, user balance, transaction, settings — sab bilkul same rahenge.
                <br>📁 Files deleted: <b><?php echo number_format($totalFilesDeleted); ?> files</b>,
                Space freed: <b><?php echo humanFilesize($totalFileBytesFreed); ?></b>
                (Uploads new size: <b><?php echo isset($uploadSizeAfter) ? humanFilesize($uploadSizeAfter) : '—'; ?></b>)
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php else: ?>
            <div class="alert alert-success alert-dismissible fade show border-0 bg-success bg-opacity-10 text-success mb-4" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <b>Cleanup Complete!</b><br>
                🗄️ Database: Deleted <b><?php echo number_format($totalDeletedRows); ?> rows</b>,
                reclaimed <b><?php echo humanFilesize($totalCleanedBytes); ?></b>
                <?php if (isset($totalBytesAfter)): ?>
                    — New DB size: <b><?php echo humanFilesize($totalBytesAfter); ?></b>
                    (reduced by <b><?php echo humanFilesize(max(0,$totalBytesBefore - $totalBytesAfter)); ?></b>)
                <?php endif; ?>
                <?php if ($totalFilesDeleted > 0): ?>
                    <br>📁 Files: Deleted <b><?php echo number_format($totalFilesDeleted); ?> files</b>,
                    freed <b><?php echo humanFilesize($totalFileBytesFreed); ?></b>
                    (Uploads new size: <b><?php echo isset($uploadSizeAfter) ? humanFilesize($uploadSizeAfter) : '—'; ?></b>)
                <?php endif; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (!$runDone): ?>
        <div class="row mb-4">
            <div class="col-xl-12 mx-auto">
                <div class="card border-top border-0 border-5 border-danger shadow-lg">
                    <div class="card-body p-4" style="background:linear-gradient(135deg,#fff5f5 0%,#fff8e6 100%);">
                        <div class="d-flex flex-column gap-3">
                            <div class="d-flex flex-column flex-lg-row align-items-start align-items-lg-center justify-content-between gap-3">
                                <div>
                                    <h4 class="mb-1 text-danger fw-bold">
                                        <i class="fa-solid fa-bolt me-2"></i>
                                        ⚡ INSTANT CLEAN NOW
                                    </h4>
                                    <div class="text-muted small mb-0">
                                        Sabse aggressive mode — ek click mein:
                                        <b class="text-danger">
                                            FULL TRUNCATE + Delete Uploads + Delete SQL/Zip/Logs + Rebuild Tables
                                        </b>
                                        <br>
                                        (users, wallet, wallethistory, settings, pricing, referral — 100% safe)
                                    </div>
                                </div>
                            </div>
                            <div class="row g-3 bg-white p-3 rounded border border-danger border-opacity-25">
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="instant_sql_voter_pdf" name="instant_sql_voter_pdf" value="1" checked form="instantForm">
                                        <label class="form-check-label fw-semibold text-danger" for="instant_sql_voter_pdf">
                                            🗳️ Delete Voter PDF SQL records (voter_pdf_list / voter_pdf_history)
                                        </label>
                                        <div class="form-text">Checkbox OFF rakhne se ye tables TRUNCATE nahi honge — 100% safe rahenge.</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="instant_sql_ll_request" name="instant_sql_ll_request" value="1" checked form="instantForm">
                                        <label class="form-check-label fw-semibold text-danger" for="instant_sql_ll_request">
                                            📝 Delete LL Request / LL Exam SQL records
                                        </label>
                                        <div class="form-text">Checkbox OFF rakhne se ll_exam_list aur larning_exam tables safe rahenge.</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="instant_sql_complaints" name="instant_sql_complaints" value="1" checked form="instantForm">
                                        <label class="form-check-label fw-semibold text-danger" for="instant_sql_complaints">
                                            📢 Delete Complaint / Contact SQL records (complaints / contact)
                                        </label>
                                        <div class="form-text">Checkbox OFF rakhne se complaint_request_list_admin aur contact form ki history safe rahegi.</div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-lg-end justify-content-center">
                                <form method="POST" id="instantForm" class="d-inline">
                                    <input type="hidden" name="instant_run" value="1">
                                </form>
                                <button type="button" id="instantBtn"
                                        class="btn btn-danger btn-lg px-4 py-3 fw-bold shadow-sm"
                                        style="background:linear-gradient(135deg,#dc3545 0%,#ff6b35 100%);border:none;">
                                    <i class="fa-solid fa-bolt me-2"></i> ⚡ INSTANT CLEAN NOW
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-xl-12 mx-auto">
                <div class="card border-top border-0 border-4 border-warning shadow-sm">
                    <div class="card-body p-5">
                        <div class="card-title d-flex align-items-center mb-3">
                            <div><i class="fa-solid fa-broom me-2 font-22 text-warning"></i></div>
                            <h4 class="mb-0 text-warning">Data Cleanup Wizard — Aggressive Mode</h4>
                        </div>
                        <div class="alert alert-danger border-0 bg-danger bg-opacity-10 mb-3">
                            <i class="bi bi-exclamation-octagon-fill me-2"></i>
                            <b>Niche ke options enable karne se — poora service data (Aadhar/PAN/DL/Voter/Ration/Samagra/PDF/Images) delete ho jayega.</b>
                        </div>
                        <div class="alert alert-warning border-0 bg-warning bg-opacity-10 mb-4">
                            <i class="bi bi-shield-fill-check me-2"></i>
                            <b>Safe:</b> <code>users</code>, <code>settings</code>, <code>wallet</code> (balance),
                            <code>wallethistory</code> (transactions), <code>pricing</code>, <code>referral</code>
                            — inme se <u>koi bhi row delete nahi hoga</u>. Sirf OPTIMIZE/FORCE rebuild hoga (space reclaim).
                        </div>

                        <form method="POST" id="cleanupForm" class="row g-4">
                            <input type="hidden" name="run_cleanup" value="1">

                            <div class="col-md-6">
                                <label class="form-label fw-bold">Records older than (days) — Old mode (skip if Full Truncate ON):</label>
                                <input name="days_older" type="number" min="7" max="3650" value="90" class="form-control form-control-lg">
                                <div class="form-text">Full Truncate OFF hone par sirf itne se zyada purane records delete hote hain.</div>
                            </div>

                            <div class="col-md-6 pt-2">
                                <div class="form-check form-switch mt-3">
                                    <input class="form-check-input" type="checkbox" id="do_optimize" name="do_optimize" value="1" checked>
                                    <label class="form-check-label fw-semibold" for="do_optimize">
                                        🔁 REBUILD ALL Tables (ALTER FORCE + OPTIMIZE)
                                        <span class="badge bg-success ms-1">MUST — Real space reclaim</span>
                                    </label>
                                    <div class="form-text">Deleted rows ke baad InnoDB ko actual mein shrink karne ke liye FORCE + OPTIMIZE dono chalega.</div>
                                </div>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" id="clean_complaints_solved" name="clean_complaints_solved" value="1" checked>
                                    <label class="form-check-label fw-semibold" for="clean_complaints_solved">
                                        Clean Solved / Closed Complaints
                                    </label>
                                </div>
                                <hr>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" id="do_full_truncate" name="do_full_truncate" value="1" checked>
                                    <label class="form-check-label fw-semibold text-danger" for="do_full_truncate">
                                        🔥 FULL WIPE — Truncate ALL service tables (RECOMMENDED)
                                        <span class="badge bg-danger ms-1">300 MB → 40 MB</span>
                                    </label>
                                    <div class="form-text text-danger">Protected tables (users/wallet/transactions) ko chhod kar har table ko EMPTY kar dega. DB 300MB se 40MB tak aa jayega.</div>
                                </div>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" id="do_delete_uploads" name="do_delete_uploads" value="1" checked>
                                    <label class="form-check-label fw-semibold text-danger" for="do_delete_uploads">
                                        🗑️ Delete ALL uploaded files (PDF/JPG/PNG/temp)
                                    </label>
                                    <div class="form-text text-danger">admin/uploads ke saare user uploads delete honge. Static icon folders (Services, service_icom) safe rahenge.</div>
                                </div>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" id="do_delete_backups" name="do_delete_backups" value="1" checked>
                                    <label class="form-check-label fw-semibold text-danger" for="do_delete_backups">
                                        🧹 Delete SQL/Zip/Log backup files + error_log
                                    </label>
                                    <div class="form-text text-danger">goodprintportal_good.sql, admin.zip, error_log — sab delete honge (zip size aur bhi kam hoga).</div>
                                </div>
                                <hr>
                                <h6 class="mb-2 text-primary fw-bold">
                                    <i class="fa-solid fa-filter me-1"></i>
                                    Service-Specific Controls (Included by default — uncheck to SKIP)
                                </h6>
                                <div class="row g-2 mt-2">
                                    <div class="col-md-6">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="do_sql_voter_pdf" name="do_sql_voter_pdf" value="1" checked>
                                            <label class="form-check-label fw-semibold text-danger" for="do_sql_voter_pdf">
                                                🗳️ DB: Delete Voter PDF records (voter_pdf_list / history)
                                            </label>
                                            <div class="form-text">OFF = ye tables truncate/clean nahi honge — rows bilkul safe.</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="do_sql_ll_request" name="do_sql_ll_request" value="1" checked>
                                            <label class="form-check-label fw-semibold text-danger" for="do_sql_ll_request">
                                                📝 DB: Delete LL Request / LL Exam records
                                            </label>
                                            <div class="form-text">OFF = ll_exam_list aur larning_exam tables safe rahenge.</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="do_sql_complaints" name="do_sql_complaints" value="1" checked>
                                            <label class="form-check-label fw-semibold text-danger" for="do_sql_complaints">
                                                📢 DB: Delete Complaint / Contact records (complaints / contact)
                                            </label>
                                            <div class="form-text">OFF = complaint_request_list_admin aur contact form ki history safe rahegi.</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="do_files_voter_pdf" name="do_files_voter_pdf" value="1" checked>
                                            <label class="form-check-label fw-semibold text-info" for="do_files_voter_pdf">
                                                🗳️ Files: Delete Voter PDF uploaded files
                                            </label>
                                            <div class="form-text">OFF = Voter PDF records mein stored file links delete nahi honge.</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="do_files_ll_request" name="do_files_ll_request" value="1" checked>
                                            <label class="form-check-label fw-semibold text-info" for="do_files_ll_request">
                                                📝 Files: Delete LL Request / LL Exam uploaded files
                                            </label>
                                            <div class="form-text">OFF = LL Exam ke DB records ke Aadhaar PDFs/photos safe rahenge.</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="do_files_complaints" name="do_files_complaints" value="1" checked>
                                            <label class="form-check-label fw-semibold text-info" for="do_files_complaints">
                                                📢 Files: Delete Complaint related uploaded files
                                            </label>
                                            <div class="form-text">OFF = Complaint/Contact ke uploaded files (agar hai) safe rahenge.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="card border border-secondary border-opacity-25">
                                    <div class="card-body bg-light p-3">
                                        <h6 class="mb-2 text-primary"><i class="fa-solid fa-list-check me-1"></i> Summary of tables:</h6>
                                        <div class="row small">
                                            <?php
                                            $perCol = max(1, (int)ceil(count($ALL_TABLES)/3));
                                            $chunks = array_chunk($ALL_TABLES, $perCol);
                                            $checkboxControlledTables = array_merge($VOTER_PDF_SQL_TABLES, $LL_REQUEST_SQL_TABLES, $COMPLAINT_SQL_TABLES);
                                            foreach ($chunks as $chunk):
                                            ?>
                                            <div class="col-md-4">
                                                <ul class="mb-0 ps-3">
                                                    <?php foreach ($chunk as $t):
                                                        $prot = in_array(strtolower($t), array_map('strtolower', $PROTECTED_TABLES), true);
                                                        $checkboxCtrl = in_array(strtolower($t), array_map('strtolower', $checkboxControlledTables), true);
                                                        $info = tblSizeInfo($ahk_conn, $dbname, $t);
                                                    ?>
                                                    <li>
                                                        <span class="<?php echo $prot ? 'text-danger fw-semibold' : ($checkboxCtrl ? 'text-primary fw-semibold' : 'text-dark'); ?>">
                                                            <?php echo htmlspecialchars($t); ?>
                                                        </span>
                                                        <small class="text-muted">
                                                            (<?php echo number_format($info['rows']); ?> · <?php echo humanFilesize($info['bytes']); ?>)
                                                        </small>
                                                        <?php if ($prot): ?>
                                                            <span class="badge bg-danger">🔒 PROTECTED</span>
                                                        <?php elseif ($checkboxCtrl): ?>
                                                            <span class="badge bg-primary">🎛️ Checkbox Controlled</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-info">🧹 cleanable</span>
                                                        <?php endif; ?>
                                                    </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 text-center mt-3">
                                <button type="button" id="confirmBtn" class="btn btn-lg btn-danger px-5 py-2 fw-bold">
                                    <i class="fa-solid fa-fire me-2"></i> Run Cleanup Now
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($runDone && (!empty($cleanLog) || !empty($fileCleanLog))): ?>
        <div class="row mt-4">
            <?php if (!$zipRunDone && !empty($cleanLog)): ?>
            <div class="col-xl-12 mx-auto mb-4">
                <div class="card border-top border-0 border-4 border-success shadow-sm">
                    <div class="card-body p-4">
                        <h5 class="mb-3 text-success"><i class="fa-solid fa-database me-2"></i> Database Cleanup Results</h5>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Table</th>
                                        <th>Action</th>
                                        <th class="text-end">Rows Deleted</th>
                                        <th class="text-end">Space Reclaimed</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cleanLog as $log): ?>
                                    <tr>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($log['table']); ?></td>
                                        <td class="small"><?php echo htmlspecialchars($log['action']); ?></td>
                                        <td class="text-end"><?php echo number_format($log['rows']); ?></td>
                                        <td class="text-end text-success fw-semibold"><?php echo htmlspecialchars($log['bytes_saved']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-success fw-semibold">
                                    <tr>
                                        <td colspan="2" class="text-end">TOTAL DB</td>
                                        <td class="text-end"><?php echo number_format($totalDeletedRows); ?> rows</td>
                                        <td class="text-end"><?php echo humanFilesize($totalCleanedBytes); ?> reclaimed</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($fileCleanLog)): ?>
            <div class="col-xl-12 mx-auto">
                <div class="card border-top border-0 border-4 border-info shadow-sm">
                    <div class="card-body p-4">
                        <h5 class="mb-3 text-info"><i class="fa-solid fa-folder-tree me-2"></i> Files Deleted Summary</h5>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Area / File</th>
                                        <th>Description</th>
                                        <th class="text-end">Files Deleted</th>
                                        <th class="text-end">Space Freed</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($fileCleanLog as $flog): ?>
                                    <tr>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($flog['area']); ?></td>
                                        <td class="small"><?php echo htmlspecialchars($flog['desc']); ?></td>
                                        <td class="text-end"><?php echo number_format($flog['files']); ?></td>
                                        <td class="text-end text-info fw-semibold"><?php echo htmlspecialchars($flog['bytes']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-info fw-semibold">
                                    <tr>
                                        <td colspan="2" class="text-end">TOTAL FILES</td>
                                        <td class="text-end"><?php echo number_format($totalFilesDeleted); ?> files</td>
                                        <td class="text-end"><?php echo humanFilesize($totalFileBytesFreed); ?> freed</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>
</div>

<script>
document.getElementById('zipCleanBtn').addEventListener('click', function() {
    const btn = this;
    const zv = document.getElementById('zip_files_voter_pdf').checked;
    const zl = document.getElementById('zip_files_ll_request').checked;
    const zc = true;
    Swal.fire({
        title: '🧹 ZIP Cleanup — Sirf files, DB Safe',
        html: `
            <div class="text-start">
                <div class="alert alert-info border-0 bg-info bg-opacity-10 p-3 mb-2">
                    <b>✅ Website ka koi bhi feature (RC/PAN/Aadhar/User/Wallet/Transactions) — bilkul AS-IT-IS rahega.</b>
                </div>
                <p class="mb-1">🗑️ <b>admin/uploads/</b> — user PDFs/photos (icons safe)</p>
                <p class="mb-1">🧹 <b>sql / zip / bak</b> backups (goodprintportal_good.sql, admin.zip etc.)</p>
                <p class="mb-1">📄 <b>error_log</b> files — PHP warnings ka store (kaam ka nahi)</p>
                <p class="mb-1">🧾 <b>/logs/</b> — 158 RC debug TXT files</p>
                <p class="mb-1">🖼️ <b>imgmanualaadhaar/</b> — aadhar manual ki saved 123 photos</p>
                <p class="mb-1">📁 <b>temp dirs</b> — dlprints, sample, passbook img, voteradv temp</p>
                <hr>
                <p class="mb-1 ${zv ? 'text-danger fw-semibold' : 'text-success fw-semibold'}">${zv ? '🗑️ DELETE Voter PDF uploaded files (from voter_pdf_list records links)' : '🛡️ SKIP Voter PDF uploaded files (checkbox OFF — safe)'}</p>
                <p class="mb-1 ${zl ? 'text-danger fw-semibold' : 'text-success fw-semibold'}">${zl ? '📝 DELETE LL Request / Exam uploaded files (from ll_exam_list / larning_exam records)' : '🛡️ SKIP LL Request / Exam uploaded files (checkbox OFF — safe)'}</p>
                <hr>
                <p class="mb-0"><span class="text-success fw-bold">✅ DATABASE (koi bhi row / table / transaction / balance) — ZERO touch.</span></p>
            </div>
        `,
        icon: 'info',
        showCancelButton: true,
        confirmButtonColor: '#6610f2',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Haan, ZIP saaf kar do',
        cancelButtonText: 'Cancel',
        reverseButtons: true
    }).then((res) => {
        if (res.isConfirmed) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i> ZIP Cleanup Running...';
            document.getElementById('zipOnlyForm').submit();
        }
    });
});

document.getElementById('instantBtn').addEventListener('click', function() {
    const btn = this;
    const sv = document.getElementById('instant_sql_voter_pdf').checked;
    const sl = document.getElementById('instant_sql_ll_request').checked;
    const sc = document.getElementById('instant_sql_complaints').checked;
    Swal.fire({
        title: '⚡ INSTANT CLEAN — Last Confirmation',
        html: `
            <div class="text-start">
                <div class="alert alert-danger border-0 bg-danger bg-opacity-10 p-3 mb-2">
                    <b>⚠️ Ye sab 1-click mein hoga:</b>
                </div>
                <p class="mb-1">🔥 <b>FULL WIPE:</b> users/wallet/transactions ko chhod kar har service table ko TRUNCATE (0 rows).</p>
                <p class="mb-1">🗑️ <b>Uploads Delete:</b> admin/uploads ke saare PDF/JPG/PNG hata de (sirf static icons safe).</p>
                <p class="mb-1">🧹 <b>Backup/Logs Delete:</b> .sql, .zip, error_log — sab delete.</p>
                <p class="mb-1">🔁 <b>Rebuild:</b> FORCE + OPTIMIZE — poora DB shrink karega.</p>
                <hr>
                <p class="mb-1 ${sv ? 'text-danger fw-semibold' : 'text-success fw-semibold'}">${sv ? '🗑️ DELETE Voter PDF SQL: voter_pdf_list + voter_pdf_history tables' : '🛡️ SKIP Voter PDF SQL tables (checkbox OFF — 100% safe, sirf rebuild)'}</p>
                <p class="mb-1 ${sl ? 'text-danger fw-semibold' : 'text-success fw-semibold'}">${sl ? '📝 DELETE LL SQL: ll_exam_list + larning_exam tables' : '🛡️ SKIP LL Request / Exam SQL tables (checkbox OFF — 100% safe, sirf rebuild)'}</p>
                <p class="mb-0 ${sc ? 'text-danger fw-semibold' : 'text-success fw-semibold'}">${sc ? '📢 DELETE Complaint SQL: complaints + contact tables' : '🛡️ SKIP Complaint/Contact SQL tables (checkbox OFF — 100% safe, sirf rebuild)'}</p>
                <hr>
                <p class="mb-0"><span class="text-success fw-bold">✅ Safe rahenge:</span> users, wallet, wallethistory, settings, pricing, referral.</p>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Haan, CHALAO — No Confirmations',
        cancelButtonText: 'Cancel',
        reverseButtons: true
    }).then((res) => {
        if (res.isConfirmed) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i> INSTANT Cleanup Running...';
            document.getElementById('instantForm').submit();
        }
    });
});

document.getElementById('confirmBtn').addEventListener('click', function() {
    const fullWipe = document.getElementById('do_full_truncate').checked;
    const delUp = document.getElementById('do_delete_uploads').checked;
    const delBk = document.getElementById('do_delete_backups').checked;
    const opt = document.getElementById('do_optimize').checked;
    const sqlV = document.getElementById('do_sql_voter_pdf').checked;
    const sqlL = document.getElementById('do_sql_ll_request').checked;
    const sqlC = document.getElementById('do_sql_complaints').checked;
    const fV = document.getElementById('do_files_voter_pdf').checked;
    const fL = document.getElementById('do_files_ll_request').checked;
    const fC = document.getElementById('do_files_complaints').checked;

    let warnings = [];
    if (fullWipe) warnings.push('<p class="mb-1"><span class="text-danger">🔥 FULL WIPE:</span> <b>Har service table</b> (Aadhar/PAN/DL/Samagra etc.) ko 0 rows tak truncate karega.</p>');
    if (delUp) warnings.push('<p class="mb-1"><span class="text-danger">🗑️ Uploads:</span> admin/uploads ke saare user PDF/JPG delete honge (icons safe).</p>');
    if (delBk) warnings.push('<p class="mb-1"><span class="text-danger">🧹 Backups:</span> .sql / .zip / error_log dump files delete honge.</p>');
    if (opt) warnings.push('<p class="mb-1"><span class="text-primary">🔁 Rebuild:</span> FORCE + OPTIMIZE — InnoDB actually shrink karega.</p>');

    warnings.push('<hr class="my-2">');
    warnings.push(sqlV
        ? '<p class="mb-1"><span class="text-danger">🗳️ DB:</span> Voter PDF tables (voter_pdf_list / history) delete honge.</p>'
        : '<p class="mb-1"><span class="text-success fw-bold">🛡️ DB SAFE:</span> Voter PDF tables (checkbox OFF) — SKIP + rebuild only.</p>');
    warnings.push(sqlL
        ? '<p class="mb-1"><span class="text-danger">📝 DB:</span> LL Request/Exam tables (ll_exam_list / larning_exam) delete honge.</p>'
        : '<p class="mb-1"><span class="text-success fw-bold">🛡️ DB SAFE:</span> LL tables (checkbox OFF) — SKIP + rebuild only.</p>');
    warnings.push(sqlC
        ? '<p class="mb-1"><span class="text-danger">📢 DB:</span> Complaint/Contact tables (complaints / contact) delete honge.</p>'
        : '<p class="mb-1"><span class="text-success fw-bold">🛡️ DB SAFE:</span> Complaint tables (checkbox OFF) — SKIP + rebuild only.</p>');
    warnings.push(fV
        ? '<p class="mb-1"><span class="text-danger">🗳️ Files:</span> Voter PDF DB records ke stored uploaded files delete honge.</p>'
        : '<p class="mb-1"><span class="text-success fw-bold">🛡️ Files SAFE:</span> Voter PDF uploaded files (checkbox OFF) — SKIP.</p>');
    warnings.push(fL
        ? '<p class="mb-1"><span class="text-danger">📝 Files:</span> LL Request/Exam records ke Aadhaar PDFs/photos delete honge.</p>'
        : '<p class="mb-1"><span class="text-success fw-bold">🛡️ Files SAFE:</span> LL Request/Exam uploaded files (checkbox OFF) — SKIP.</p>');
    warnings.push(fC
        ? '<p class="mb-1"><span class="text-danger">📢 Files:</span> Complaint/Contact ke related uploaded files delete honge.</p>'
        : '<p class="mb-1"><span class="text-success fw-bold">🛡️ Files SAFE:</span> Complaint related files (checkbox OFF) — SKIP.</p>');

    Swal.fire({
        title: 'Final Confirmation — Permanent Action',
        html: `
            <div class="text-start">
                ${warnings.length ? warnings.join('') : '<p>Gentle mode — only old records based on days.</p>'}
                <hr>
                <p class="mb-1"><span class="text-success">✅ 100% Safe:</span> <b>users</b>, <b>wallet</b> (balance), <b>wallethistory</b> (transactions), <b>settings</b>, <b>pricing</b>, <b>referral</b> — inme se kuch bhi <u>nahi</u> delete hoga. Bas rebuild hoga agar optimize ON hai.</p>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Haan, sab clean kar do',
        cancelButtonText: 'Cancel',
        reverseButtons: true
    }).then((res) => {
        if (res.isConfirmed) {
            let btn = document.getElementById('confirmBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i> Running cleanup...';
            document.getElementById('cleanupForm').submit();
        }
    });
});
</script>

<?php
include(__DIR__ . '/../template/ahkweb/footer.php');
?>

<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>

<?php
if (file_exists(__DIR__ . '/../template/ahkweb/userFooter.php')) {
    include(__DIR__ . '/../template/ahkweb/userFooter.php');
}
?>
</body>
</html>
