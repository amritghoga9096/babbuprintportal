<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/School_id_card.php');

date_default_timezone_set('Asia/Kolkata');

// Helper: respond with JS alert and optional redirect
function js_alert_and_redirect($msg, $redirect = null) {
    echo '<script>';
    echo 'alert("' . addslashes($msg) . '");';
    if ($redirect) {
        echo 'window.location.href = "' . addslashes($redirect) . '";';
    }
    echo '</script>';
    exit;
}

// Ensure user data available
if (!isset($udata) || !isset($_SESSION['phone'])) {
    js_alert_and_redirect("Session expired. Please login again.", "../admin/index.php");
}

// Low balance check
if (!isset($udata['balance']) || floatval($udata['balance']) < 10) {
    js_alert_and_redirect("Dear user your Wallet Balance is Low Please Recharge Now", "../admin/index.php");
}

// Prepare upload directories
$uploads_dir = __DIR__ . "/uploads/";
$web_uploads_dir = "uploads/";
if (!is_dir($uploads_dir)) {
    @mkdir($uploads_dir, 0755, true);
}

// Allowed MIME types/extensions
$allowed_mimes = ['image/jpeg', 'image/pjpeg', 'image/jpg', 'image/png', 'image/webp'];
$allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
$finfo = new finfo(FILEINFO_MIME_TYPE);

// Helper to process a file input and return web path or empty string
function handle_file_upload($file_input_name, $finfo, $allowed_mimes, $allowed_exts, $uploads_dir, $web_uploads_dir) {
    if (!isset($_FILES[$file_input_name]) || $_FILES[$file_input_name]['error'] !== UPLOAD_ERR_OK) {
        return '';
    }
    $tmp_name = $_FILES[$file_input_name]['tmp_name'];
    $orig_name = $_FILES[$file_input_name]['name'];
    $mime = $finfo->file($tmp_name);
    $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
    if (!in_array($mime, $allowed_mimes) || !in_array($ext, $allowed_exts)) {
        return ''; // invalid file, caller may handle error if required
    }
    $uniq = time() . '_' . bin2hex(random_bytes(6));
    $filename = $uniq . '_' . $file_input_name . '.' . $ext;
    $target_path = rtrim($uploads_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
    if (move_uploaded_file($tmp_name, $target_path)) {
        return rtrim($web_uploads_dir, '/') . '/' . $filename;
    }
    return '';
}

/**
 * Ensure tbltrans exists. If it doesn't, create a minimal table used by the app.
 * Returns true on success (exists or created), false on failure.
 */
function ensure_tbltrans_exists($mysqli_conn) {
    // Determine DB name from connection
    $db_name = '';
    if (isset($mysqli_conn->connect_errno)) {
        // procedural style: $mysqli_conn is a mysqli resource
        // try to use default DB from config via SELECT DATABASE()
        $res = mysqli_query($mysqli_conn, "SELECT DATABASE() as db");
        if ($res) {
            $row = mysqli_fetch_assoc($res);
            $db_name = $row['db'];
            mysqli_free_result($res);
        }
    } else {
        // object style (unlikely here) — fallback
        $res = mysqli_query($mysqli_conn, "SELECT DATABASE() as db");
        if ($res) {
            $row = mysqli_fetch_assoc($res);
            $db_name = $row['db'];
            mysqli_free_result($res);
        }
    }

    if (empty($db_name)) {
        // try to use information_schema without specifying db
        $check_sql = "SELECT 1 FROM information_schema.TABLES WHERE TABLE_NAME = 'tbltrans' LIMIT 1";
    } else {
        $db_esc = mysqli_real_escape_string($mysqli_conn, $db_name);
        $check_sql = "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = '$db_esc' AND TABLE_NAME = 'tbltrans' LIMIT 1";
    }

    $chk_res = mysqli_query($mysqli_conn, $check_sql);
    if ($chk_res && mysqli_num_rows($chk_res) > 0) {
        return true; // table exists
    }

    // Table doesn't exist: create it with a minimal safe structure
    $create_sql = "
    CREATE TABLE IF NOT EXISTS `tbltrans` (
      `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
      `userid` VARCHAR(100) NOT NULL,
      `username` VARCHAR(150) DEFAULT NULL,
      `transdate` DATETIME DEFAULT CURRENT_TIMESTAMP,
      `transqty` INT DEFAULT 0,
      `transtype` VARCHAR(50) DEFAULT NULL,
      `touserid` VARCHAR(100) DEFAULT NULL,
      `tousername` VARCHAR(150) DEFAULT NULL,
      `remark` TEXT,
      `loginid` VARCHAR(100) DEFAULT NULL,
      `logdate` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $create_res = mysqli_query($mysqli_conn, $create_sql);
    return ($create_res !== false);
}

// Process form submission
if (isset($_POST['savedata'])) {
    // fetch current user row (to get existing aadharpoint etc.)
    $user_phone = mysqli_real_escape_string($ahk_conn, $_SESSION['phone']);
    $q = "SELECT * FROM users WHERE phone='$user_phone' LIMIT 1";
    $r = mysqli_query($ahk_conn, $q);
    $rw = $r ? mysqli_fetch_assoc($r) : null;

    // Inputs (trim)
    $aadharno   = isset($_POST['normaltext']) ? trim($_POST['normaltext']) : '';
    $name       = isset($_POST['name']) ? trim($_POST['name']) : '';
    $fathername = isset($_POST['fathername']) ? trim($_POST['fathername']) : '';
    $dobadhar   = isset($_POST['mobile']) ? trim($_POST['mobile']) : '';
    $gender     = isset($_POST['address']) ? trim($_POST['address']) : '';

    // Basic validations
    if ($aadharno === '') js_alert_and_redirect('Please Enter Pan Card No .... ', '#');
    if ($name === '') js_alert_and_redirect('Please Enter Name  .... ', '#');
    if ($fathername === '') js_alert_and_redirect('Please Enter Father Name  .... ', '#');
    if ($dobadhar === '') js_alert_and_redirect('Please Enter Date of Birth  .... ', '#');
    if ($gender === '') js_alert_and_redirect('Please Enter Gender  .... ', '#');

    // Check balance vs aadharpoint (if those columns exist)
    if ($rw && isset($rw['aadharpoint']) && isset($rw['balance']) && floatval($rw['aadharpoint']) > floatval($rw['balance'])) {
        js_alert_and_redirect("Sorry, Your Balance is Low .... Please Recharge Soon", 'panmanual.php');
    }

    // Start DB transaction
    if (function_exists('mysqli_begin_transaction')) {
        mysqli_begin_transaction($ahk_conn);
        $in_transaction = true;
    } else {
        $in_transaction = false;
    }

    try {
        // Process uploads (if provided). We do NOT delete any old records or files.
        $new_image_path = handle_file_upload('imagefile', $finfo, $allowed_mimes, $allowed_exts, $uploads_dir, $web_uploads_dir);
        $new_sign_path  = handle_file_upload('signfile',  $finfo, $allowed_mimes, $allowed_exts, $uploads_dir, $web_uploads_dir);

        // Insert new row (always insert - do NOT remove old records)
        $timestamp = date("Y-m-d H:i:s");
        $insert_sql = "INSERT INTO school_id (`userid`,`panno`,`name`,`fathername`,`dob`,`gender`,`image`,`signimage`,`create_time`)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $ins_stmt = mysqli_prepare($ahk_conn, $insert_sql);
        if ($ins_stmt === false) {
            $err = mysqli_error($ahk_conn);
            throw new Exception("Prepare insert failed: " . $err);
        }
        $userid = $_SESSION['phone'];
        mysqli_stmt_bind_param($ins_stmt, "sssssssss", $userid, $aadharno, $name, $fathername, $dobadhar, $gender, $new_image_path, $new_sign_path, $timestamp);
        $exec = mysqli_stmt_execute($ins_stmt);
        if ($exec === false) {
            $err_no = mysqli_errno($ahk_conn);
            $err_msg = mysqli_error($ahk_conn);
            mysqli_stmt_close($ins_stmt);
            // If duplicate key error occurs (errno 1062) it's likely due to UNIQUE constraint on panno.
            if ($err_no == 1062) {
                if ($in_transaction) mysqli_rollback($ahk_conn);
                js_alert_and_redirect('Insert failed: duplicate PAN detected by database. To allow multiple rows with the same PAN remove the UNIQUE constraint on school_id.panno, or contact your DBA. Detailed error: ' . addslashes($err_msg), '#');
            }
            throw new Exception("DB Insert Error: " . $err_msg);
        }
        mysqli_stmt_close($ins_stmt);

        // Deduct fee if pricing exists and > 0
        $fee = 0;
        $price_q = mysqli_query($ahk_conn, "SELECT * FROM pricing WHERE service_name='student_card_fee' LIMIT 1");
        if ($price_q && mysqli_num_rows($price_q) > 0) {
            $price = mysqli_fetch_assoc($price_q);
            $fee = floatval($price['price']);
        }

        if ($fee > 0) {
            $appliedby = mysqli_real_escape_string($ahk_conn, $udata['phone']);
            $update_balance_q = mysqli_query($ahk_conn, "UPDATE users SET balance = balance - $fee WHERE phone = '$appliedby'");
            if (!$update_balance_q) {
                throw new Exception("Failed to deduct fee: " . mysqli_error($ahk_conn));
            }
        }

        // Ensure tbltrans exists. If creation fails, we will skip logging but not fail the whole submission.
        $tbltrans_ok = ensure_tbltrans_exists($ahk_conn);
        if (!$tbltrans_ok) {
            // If we can't create/check the table, proceed but record a server log (if possible)
            error_log("Warning: unable to ensure tbltrans exists. Transaction log will be skipped.");
        } else {
            // Log transaction (tbltrans)
            // Get aadharpoint safely
            $getpoint_row = mysqli_query($ahk_conn, "SELECT * FROM users WHERE phone = '" . intval($_SESSION['phone']) . "' LIMIT 1");
            $getpoint = $getpoint_row ? mysqli_fetch_assoc($getpoint_row) : null;
            $aadharpoint = isset($getpoint['aadharpoint']) ? intval($getpoint['aadharpoint']) : 0;
            $remark = 'Pan No : ' . $aadharno . ' Pan Name : ' . $name;

            $qu = "INSERT INTO `tbltrans`(`userid`, `username`, `transdate`, `transqty`, `transtype`, `touserid`, `tousername`, `remark`, `loginid`, `logdate`)
                   VALUES (?, ?, now(), ?, 'Dr', '0', 'Pan Create', ?, ?, now())";
            $tr_stmt = mysqli_prepare($ahk_conn, $qu);
            if ($tr_stmt === false) {
                // something wrong with prepare; don't fail the whole user action
                error_log("Warning: failed to prepare tbltrans insert: " . mysqli_error($ahk_conn));
            } else {
                $userid_sess = isset($_SESSION['phone']) ? $_SESSION['phone'] : '';
                $username_sess = isset($_SESSION['username']) ? $_SESSION['username'] : '';
                // types: userid(s), username(s), transqty(i), remark(s), loginid(s) => "ssiss"
                mysqli_stmt_bind_param($tr_stmt, "ssiss", $userid_sess, $username_sess, $aadharpoint, $remark, $userid_sess);
                $tr_exec = mysqli_stmt_execute($tr_stmt);
                if ($tr_exec === false) {
                    error_log("Warning: tbltrans insert failed: " . mysqli_error($ahk_conn));
                }
                mysqli_stmt_close($tr_stmt);
            }
        }

        // Commit transaction
        if ($in_transaction) {
            mysqli_commit($ahk_conn);
        }

        // Success feedback and redirect
        echo '<script>
            $(function(){
                Swal.fire(
                    "Pan inserted successfully. Redirecting to list...",
                    "",
                    "success"
                );
            });
            setTimeout(function () {
                window.location = "School_id_card_list.php";
            }, 1200);
        </script>';
        exit;

    } catch (Exception $e) {
        // rollback if in transaction
        if ($in_transaction) {
            mysqli_rollback($ahk_conn);
        }
        $err_msg = $e->getMessage();
        js_alert_and_redirect('Error: ' . $err_msg, '#');
    }
}
?>
