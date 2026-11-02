<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

// fixed include filename
include('../template/ahkweb/ll_request_admin.php');

if (checkAdmin($udata['type']) == false) {
    ?>
    <script>window.location='index.php';</script>
    <?php
    die();
}

/**
 * Helper: show JS SweetAlert and exit
 */
function swal_and_exit($title, $text = '', $icon = 'info', $redirect = 'll_request_admin.php') {
    $title_js = addslashes($title);
    $text_js  = addslashes($text);
    $redirect_js = addslashes($redirect);
    echo "<script>
            $(function(){ Swal.fire('{$title_js}','{$text_js}','{$icon}');});
            setTimeout(()=>{ window.location = '{$redirect_js}'; }, 1500);
          </script>";
    exit;
}

/* ----------------------------
   Self-heal: larning_exam only — add passed_by_admin column for Pass Mark feature
   ---------------------------- */
@mysqli_report(MYSQLI_REPORT_OFF);
@mysqli_query($ahk_conn, "ALTER TABLE larning_exam ADD COLUMN passed_by_admin TINYINT(1) NOT NULL DEFAULT 0");
@mysqli_query($ahk_conn, "ALTER TABLE larning_exam ADD COLUMN pass_marked_at DATETIME DEFAULT NULL");

/* ----------------------------
   Handle Pass Mark (mark larning_exam entry as SUCCESS / PASSED)
   Works for: pending, 0, open, new, cancelled, refunded — admin override
   ---------------------------- */
if (isset($_POST['pass_id']) && !empty($_POST['pass_id'])) {
    $pass_id  = mysqli_real_escape_string($ahk_conn, $_POST['pass_id']);
    $srcTable = isset($_POST['_source']) ? $_POST['_source'] : '';
    $ok = false;

    if ($srcTable === 'larning_exam') {
        $ok = @mysqli_query($ahk_conn,
            "UPDATE larning_exam SET status='success', passed_by_admin=1, pass_marked_at=NOW() WHERE id='{$pass_id}' LIMIT 1"
        );
    }

    if ($ok && mysqli_affected_rows($ahk_conn) > 0) {
        swal_and_exit('✅ Marked as PASS!', 'LL Request #'.$pass_id.' has been marked as PASSED (status = success) successfully.', 'success', 'll_request_admin.php');
    } elseif ($ok) {
        swal_and_exit('No Change', 'Record was not updated (already passed or not found).', 'info', 'll_request_admin.php');
    } else {
        $err = mysqli_error($ahk_conn);
        swal_and_exit('Database Error!', $err, 'error', 'll_request_admin.php');
    }
}

/* ----------------------------
   Handle Aadhaar upload (existing behaviour, larning_exam only; ll_exam_list = exam results, no upload needed)
   ---------------------------- */
if (isset($_POST['aadhaar_no']) && !empty($_POST['aadhaar_no']) && empty($_POST['refund_id'])) {
    $aadhaar_no = mysqli_real_escape_string($ahk_conn, $_POST['aadhaar_no']);
    $id = mysqli_real_escape_string($ahk_conn, $_POST['id']);
    $srcTable = isset($_POST['_source']) ? $_POST['_source'] : 'larning_exam';

    if ($srcTable !== 'larning_exam') {
        ?>
        <script>
            $(function () { Swal.fire('Upload Not Applicable', 'This LL Exam List record is an exam result; no Aadhaar upload action is required. For any issue, use Cancel & Refund.', 'info'); });
            setTimeout(() => { window.location = 'll_request_admin.php'; }, 1400);
        </script>
        <?php
        exit;
    }

    $type = $_FILES['aadhaar_pdf']['type'] ?? '';
    if ($type == "application/pdf" || $type == "image/jpeg" || $type == "image/png" || $type == "image/jpg") {
        $ext = rand(000000, 999999) . '_' . basename($_FILES['aadhaar_pdf']['name']);
        $link = "https://" . $host . $dir . "/uploads" . "/" . $ext;
        $upload = "uploads/";
        if (move_uploaded_file($_FILES['aadhaar_pdf']['tmp_name'], $upload . $ext)) {
            $sql = mysqli_query($ahk_conn, "UPDATE larning_exam SET pdf_link='" . mysqli_real_escape_string($ahk_conn, $link) . "', aadhaar_no='" . mysqli_real_escape_string($ahk_conn, $aadhaar_no) . "', status='success' WHERE id='" . mysqli_real_escape_string($ahk_conn, $id) . "'");
            if ($sql) {
                ?>
                <script>
                    $(function () { Swal.fire('Pending Data Uploaded Successfully!', '', 'success'); });
                    setTimeout(() => { window.location = 'll_request_admin.php'; }, 1200);
                </script>
                <?php
            } else {
                $err = mysqli_error($ahk_conn);
                error_log("Aadhaar Upload DB Update Error: " . $err);
                ?>
                <script>
                    $(function () { Swal.fire('Database Error!', '<?php echo addslashes($err); ?>', 'error'); });
                </script>
                <?php
            }
        } else {
            ?>
            <script>
                $(function () { Swal.fire('Error While Uploading Files!', '', 'error'); });
            </script>
            <?php
        }
    } else {
        ?>
        <script>
            $(function () { Swal.fire('Wrong File Type!', 'PDF,JPEG,PNG Allowed only', 'error'); });
        </script>
        <?php
    }
}

/* ----------------------------
   Refund / Cancel logic (works for pending & success, supports BOTH larning_exam + ll_exam_list tables)
   Robust logging fallback to avoid previous "service_name null" issue
   ---------------------------- */
if (isset($_POST['refund_id']) && !empty($_POST['refund_id'])) {
    $refund_id = mysqli_real_escape_string($ahk_conn, $_POST['refund_id']);
    $srcTable = isset($_POST['_source']) ? $_POST['_source'] : '';

    // Prepare constant variables used by all logging attempts
    $serviceName = 'll_request_fee_refund';
    $status = 'credit';
    $adminId = isset($udata['id']) ? intval($udata['id']) : 0;

    // Begin transaction
    if (function_exists('mysqli_begin_transaction')) {
        mysqli_begin_transaction($ahk_conn);
    } else {
        mysqli_query($ahk_conn, "START TRANSACTION");
    }

    try {
        $app = null;
        $appTable = '';
        $appliedbyCol = '';
        $feeCol = '';

        // --- Auto-detect / explicit source: try larning_exam first, then ll_exam_list ---
        if ($srcTable === '' || $srcTable === 'larning_exam') {
            $appRes = mysqli_query($ahk_conn, "SELECT * FROM larning_exam WHERE id = '$refund_id' FOR UPDATE");
            if ($appRes && mysqli_num_rows($appRes) > 0) {
                $app = mysqli_fetch_assoc($appRes);
                $appTable = 'larning_exam';
                $appliedbyCol = 'appliedby';
                $feeCol = 'fee';
            }
        }
        if ($app === null && ($srcTable === '' || $srcTable === 'll_exam_list')) {
            $appRes2 = mysqli_query($ahk_conn, "SELECT * FROM ll_exam_list WHERE id = '$refund_id' FOR UPDATE");
            if ($appRes2 && mysqli_num_rows($appRes2) > 0) {
                $app = mysqli_fetch_assoc($appRes2);
                $appTable = 'll_exam_list';
                $appliedbyCol = 'userid';
                $feeCol = 'amount';
            }
        }
        if ($app === null) {
            throw new Exception('Application not found in either LL table.');
        }

        // Determine userid from the correct column (appliedby or userid / numeric id or phone)
        $appliedby = $app[$appliedbyCol] ?? '';
        $userid = null;

        if (is_numeric($appliedby)) {
            // Could be user.id directly (if stored as id instead of phone)
            $checkUser = mysqli_query($ahk_conn, "SELECT id FROM users WHERE id = '" . intval($appliedby) . "' LIMIT 1");
            if ($checkUser && mysqli_num_rows($checkUser) > 0) {
                $r = mysqli_fetch_assoc($checkUser);
                $userid = $r['id'];
            }
        }

        if ($userid === null) {
            $appliedby_esc = mysqli_real_escape_string($ahk_conn, $appliedby);
            $checkUser2 = mysqli_query($ahk_conn, "SELECT id FROM users WHERE phone = '$appliedby_esc' LIMIT 1");
            if ($checkUser2 && mysqli_num_rows($checkUser2) > 0) {
                $r2 = mysqli_fetch_assoc($checkUser2);
                $userid = $r2['id'];
            }
        }

        if ($userid === null) {
            throw new Exception('User not found for this application (phone=' . htmlspecialchars($appliedby) . ')');
        }

        // Determine refund amount (fee for larning_exam, amount for ll_exam_list)
        $refundAmount = 0;
        if (!empty($app[$feeCol]) && is_numeric($app[$feeCol])) {
            $refundAmount = floatval($app[$feeCol]);
        } else {
            $priceResult = mysqli_query($ahk_conn, "SELECT price FROM pricing WHERE service_name = 'll_request_fee' LIMIT 1");
            $refundAmount = ($priceResult && mysqli_num_rows($priceResult) > 0) ? floatval(mysqli_fetch_assoc($priceResult)['price']) : 1000000;
        }

        // Lock user's balance row and update balance
        $balRes = mysqli_query($ahk_conn, "SELECT balance FROM users WHERE id = '$userid' FOR UPDATE");
        if (!$balRes || mysqli_num_rows($balRes) == 0) {
            throw new Exception('User balance record not found');
        }
        $balRow = mysqli_fetch_assoc($balRes);
        $oldBalance = floatval($balRow['balance']);
        $newBalance = $oldBalance + $refundAmount;

        $upd = mysqli_query($ahk_conn, "UPDATE users SET balance = '" . mysqli_real_escape_string($ahk_conn, $newBalance) . "' WHERE id = '" . mysqli_real_escape_string($ahk_conn, $userid) . "'");
        if (!$upd) {
            throw new Exception('Failed to update user balance: ' . mysqli_error($ahk_conn));
        }

        // Update application status (per-table logic)
        $refund_id_esc = mysqli_real_escape_string($ahk_conn, $refund_id);
        if ($appTable === 'larning_exam') {
            $updApp = mysqli_query($ahk_conn, "UPDATE larning_exam SET status = 'cancelled', pdf_link = '', aadhaar_no = '' WHERE id = '{$refund_id_esc}'");
        } else {
            // ll_exam_list: status enum now includes cancelled/refunded (self-heal at top of file)
            $updApp = mysqli_query($ahk_conn, "UPDATE ll_exam_list SET status = 'cancelled' WHERE id = '{$refund_id_esc}'");
        }
        if (!$updApp) {
            throw new Exception('Failed to update application status ('.$appTable.'): ' . mysqli_error($ahk_conn));
        }

        // Now try to log into service_summary robustly.
        $logged = false;
        $lastError = '';

        // Attempt 1: prepared insert with created_at column
        $stmt1 = mysqli_prepare($ahk_conn, "INSERT INTO service_summary (user_id, service, amount, status, created_at) VALUES (?, ?, ?, ?, NOW())");
        if ($stmt1) {
            mysqli_stmt_bind_param($stmt1, "isds", $userid, $serviceName, $refundAmount, $status);
            $exec1 = mysqli_stmt_execute($stmt1);
            if ($exec1) {
                $logged = true;
            } else {
                $lastError = mysqli_error($ahk_conn);
            }
            mysqli_stmt_close($stmt1);
        } else {
            $lastError = mysqli_error($ahk_conn);
        }

        // Attempt 2: if attempt 1 failed, try insert without created_at (some schemas lack it)
        if (!$logged) {
            $stmt2 = mysqli_prepare($ahk_conn, "INSERT INTO service_summary (user_id, service, amount, status) VALUES (?, ?, ?, ?)");
            if ($stmt2) {
                mysqli_stmt_bind_param($stmt2, "isds", $userid, $serviceName, $refundAmount, $status);
                $exec2 = mysqli_stmt_execute($stmt2);
                if ($exec2) {
                    $logged = true;
                } else {
                    $lastError = mysqli_error($ahk_conn);
                }
                mysqli_stmt_close($stmt2);
            } else {
                $lastError = mysqli_error($ahk_conn);
            }
        }

        // Attempt 3: fallback table if still not logged
        if (!$logged) {
            $createFallback = "CREATE TABLE IF NOT EXISTS refund_fallback (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                application_id INT NULL,
                service_name VARCHAR(100) NOT NULL,
                amount DECIMAL(18,2) NOT NULL,
                status VARCHAR(20) NOT NULL,
                admin_id INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            $okCreate = mysqli_query($ahk_conn, $createFallback);
            if (!$okCreate) {
                $lastError = "Failed to create fallback table: " . mysqli_error($ahk_conn);
                throw new Exception($lastError);
            }

            $stmt3 = mysqli_prepare($ahk_conn, "INSERT INTO refund_fallback (user_id, application_id, service_name, amount, status, admin_id) VALUES (?, ?, ?, ?, ?, ?)");
            if (!$stmt3) {
                $lastError = "Failed to prepare fallback insert: " . mysqli_error($ahk_conn);
                throw new Exception($lastError);
            }

            // Bind parameters: i i s d s i  -> user_id, application_id, service_name, amount, status, admin_id
            mysqli_stmt_bind_param($stmt3, "iisdsi", $userid, $refund_id, $serviceName, $refundAmount, $status, $adminId);
            $exec3 = mysqli_stmt_execute($stmt3);
            if (!$exec3) {
                $lastError = "Failed to write to fallback table: " . mysqli_error($ahk_conn);
                mysqli_stmt_close($stmt3);
                throw new Exception($lastError);
            }
            mysqli_stmt_close($stmt3);

            $logged = true;
            error_log("Refund logged to fallback table for user_id $userid, application $refund_id, amount $refundAmount");
        }

        // commit transaction
        mysqli_commit($ahk_conn);

        error_log("Refund successful: user_id=$userid, application_id=$refund_id, amount=$refundAmount, admin=" . ($udata['id'] ?? 'unknown'));

        ?>
        <script>
            $(function () {
                Swal.fire('Refund processed successfully!', 'User credited: ₹<?php echo number_format($refundAmount, 2); ?>', 'success');
            });
            setTimeout(() => { window.location = 'll_request_admin.php'; }, 1400);
        </script>
        <?php
        exit;

    } catch (Exception $e) {
        mysqli_rollback($ahk_conn);
        $errMsg = $e->getMessage();
        error_log("Refund failed for application_id $refund_id: " . $errMsg);
        swal_and_exit('Refund failed!', $errMsg, 'error');
    }
}
?>

<!--start page wrapper -->
<div class="page-wrapper">
    <div class="page-content">
        <!-- Breadcrumb with Action Buttons -->
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">History</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">Learning Licence List</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="ll_request" class="btn btn-success">
                    <i class="bi bi-list-ul me-1"></i> New Apply
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>
        <div class="card radius-10">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div>
                        <h5 class="mb-0">LL TEST EXAM PENDING REQUEST ADMIN LIST</h5>
                    </div>
                </div>
                <hr>
                <div class="table-responsive">
                    <table id="example2" class="table align-middle mb-0">
                        <thead style="color:white;background: linear-gradient(180deg, #c82c2c, #12f0cf);">
                        <tr>
                            <th class="text-center">#</th>
                            <th class="text-center">Phone</th>
                            <th class="text-center">Application No</th>
                            <th class="text-center">Date of Birth</th>
                            <th class="text-center">Password</th>
                            <th class="text-center">State</th>
                            <th class="text-center">Fee</th>
                            <th class="text-center">Exam Date</th>
                            <th class="text-center">Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php
                        // ============================================================
                        // ONLY larning_exam (learner_license_apply.php) records.
                        // PENDING rows FIRST (newest pending on top) then others DESC.
                        // NO CONNECTION with ll_request / ll_exam_list at all.
                        // ============================================================
                        $res = mysqli_query($ahk_conn,
                            "SELECT * FROM larning_exam ORDER BY
                                (CASE WHEN LOWER(TRIM(COALESCE(status,'pending'))) IN ('pending','0','open','new','') THEN 0 ELSE 1 END) ASC,
                                id DESC"
                        );
                        $rowsFound = ($res && mysqli_num_rows($res) > 0);
                        if ($rowsFound) {
                            $x = 0;
                            while ($data = mysqli_fetch_assoc($res)) {
                                $x++;
                                $st = strtolower(trim((string)($data['status'] ?? 'pending')));
                                $rowId = (int)$data['id'];
                                $rowBgPending = in_array($st, ['pending','0','open','new',''])
                                    ? 'background:linear-gradient(90deg,#fff3cd 0%,#ffffff 35%);'
                                    : '';
                                ?>
                                <tr style="<?= $rowBgPending; ?>">
                                    <td class="text-center">
                                        <?= $x; ?><br>
                                        <?php if (in_array($st, ['pending','0','open','new',''])) { ?>
                                            <span class="badge bg-danger" style="font-size:10px;">NEW</span>
                                        <?php } else if ($st === "success" && !empty($data['passed_by_admin'])) { ?>
                                            <span class="badge bg-success" style="font-size:10px;">✅ PASS</span>
                                        <?php } ?>
                                    </td>
                                    <td class="text-center"><?= htmlspecialchars($data['appliedby']); ?></td>
                                    <td class="text-center">
                                        <div class="ms-2">
                                            <h6 class="mb-1 font-14"><?= strtoupper(htmlspecialchars($data['name'] ?? '')); ?></h6>
                                        </div>
                                    </td>
                                    <td class="text-center"><?= strtoupper(htmlspecialchars($data['rasan_no'] ?? '')); ?></td>
                                    <td class="text-center"><?= strtoupper(htmlspecialchars($data['date'] ?? '')); ?></td>
                                    <td class="text-center"><?= strtoupper(htmlspecialchars($data['state_fee'] ?? '')); ?></td>
                                    <td class="text-center"><?= is_numeric($data['fee']) ? '₹' . htmlspecialchars(number_format($data['fee'], 2)) : htmlspecialchars($data['fee']); ?></td>
                                    <td class="text-center">
                                        <?php
                                        $d = $data['apply_date'] ?? '';
                                        if ($d && $d !== '0000-00-00 00:00:00') {
                                            echo htmlspecialchars(strtoupper($d));
                                        } else {
                                            echo '<span class="text-muted">-</span>';
                                        }
                                        ?>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                        // ============== larning_exam = original workflow ==============
                                        if ($st == "pending") {
                                            ?>
                                            <div style="width:250px;">
                                                <!-- Upload form -->
                                                <form method="POST" action="" enctype="multipart/form-data" style="margin-bottom:6px;">
                                                    <input class="form-control mb-2" type="text" id="aadh_<?= $rowId; ?>" data-inputmask="'mask': '9999 9999 9999'" name="aadhaar_no" required maxlength="12" placeholder="Enter Aadhaar No">
                                                    <input type="hidden" name="id" value="<?= $rowId; ?>">
                                                    <input type="hidden" name="_source" value="larning_exam">
                                                    <input class="form-control mb-2" type="file" name="aadhaar_pdf" required>
                                                    <button class="btn px-6 btn-success">Update</button>
                                                </form>
                                                <!-- Pass Mark form -->
                                                <form method="POST" style="margin-bottom:6px;" onsubmit="return confirm('Mark this LL Request as PASSED (Success) and approve?');">
                                                    <input type="hidden" name="pass_id" value="<?= $rowId; ?>">
                                                    <input type="hidden" name="_source" value="larning_exam">
                                                    <button type="submit" class="btn btn-sm" style="background:linear-gradient(135deg,#10b981 0%,#059669 100%);color:#fff;font-weight:700;width:100%;">✅ Mark Pass</button>
                                                </form>
                                                <!-- Refund form -->
                                                <form method="POST" onsubmit="return confirm('Are you sure you want to cancel this application and refund the user?');">
                                                    <input type="hidden" name="refund_id" value="<?= $rowId; ?>">
                                                    <input type="hidden" name="_source" value="larning_exam">
                                                    <button type="submit" class="btn btn-sm btn-danger">Cancel & Refund</button>
                                                </form>
                                            </div>
                                            <?php
                                        } else if ($st == "success") {
                                            ?>
                                            <div class="text-center">
                                                <div class="text-success" style="margin-bottom:6px;">
                                                    <?= htmlspecialchars($data['aadhaar_no'] ?? ''); ?><br>
                                                    Already Uploaded
                                                    <?php if (!empty($data['pdf_link'])) { ?>
                                                        <a target="_blank" href="<?= htmlspecialchars($data['pdf_link']); ?>" class="btn btn-sm btn-info">See</a>
                                                    <?php } ?>
                                                    <?php if (!empty($data['passed_by_admin'])) { ?>
                                                        <br><small class="text-success fw-bold">✅ Marked Pass by Admin
                                                            <?php if (!empty($data['pass_marked_at'])) { ?>
                                                                @ <?= htmlspecialchars($data['pass_marked_at']); ?>
                                                            <?php } ?>
                                                        </small>
                                                    <?php } ?>
                                                </div>
                                                <!-- Pass Mark form (re-mark / admin re-approval) -->
                                                <form method="POST" style="display:inline-block;margin-right:4px;margin-bottom:4px;" onsubmit="return confirm('Re-approve / mark this record as PASSED?');">
                                                    <input type="hidden" name="pass_id" value="<?= $rowId; ?>">
                                                    <input type="hidden" name="_source" value="larning_exam">
                                                    <button type="submit" class="btn btn-sm" style="background:linear-gradient(135deg,#10b981 0%,#059669 100%);color:#fff;font-weight:700;">✅ Mark Pass</button>
                                                </form>
                                                <form method="POST" style="display:inline-block;margin-left:4px;" onsubmit="return confirm('Are you sure you want to cancel this application and refund the user?');">
                                                    <input type="hidden" name="refund_id" value="<?= $rowId; ?>">
                                                    <input type="hidden" name="_source" value="larning_exam">
                                                    <button type="submit" class="btn btn-sm btn-danger">Cancel & Refund</button>
                                                </form>
                                            </div>
                                            <?php
                                        } else if ($st == "cancelled" || $st == "refunded") {
                                            ?>
                                            <div class="text-center">
                                                <div class="text-muted"><?= strtoupper(htmlspecialchars($data['status'])); ?></div>
                                                <!-- Pass Mark form (recover cancelled/refunded → back to success) -->
                                                <form method="POST" style="margin-top:6px;" onsubmit="return confirm('Recover this cancelled/refunded record and mark as PASSED?');">
                                                    <input type="hidden" name="pass_id" value="<?= $rowId; ?>">
                                                    <input type="hidden" name="_source" value="larning_exam">
                                                    <button type="submit" class="btn btn-sm" style="background:linear-gradient(135deg,#10b981 0%,#059669 100%);color:#fff;font-weight:700;">✅ Mark Pass (Recover)</button>
                                                </form>
                                            </div>
                                            <?php
                                        } else {
                                            ?>
                                            <div class="text-center"><?= htmlspecialchars($data['status']); ?></div>
                                            <!-- Pass Mark form -->
                                            <form method="POST" style="margin-top:6px;margin-bottom:4px;" onsubmit="return confirm('Mark this LL Request as PASSED?');">
                                                <input type="hidden" name="pass_id" value="<?= $rowId; ?>">
                                                <input type="hidden" name="_source" value="larning_exam">
                                                <button type="submit" class="btn btn-sm" style="background:linear-gradient(135deg,#10b981 0%,#059669 100%);color:#fff;font-weight:700;">✅ Mark Pass</button>
                                            </form>
                                            <form method="POST" style="margin-top:2px;" onsubmit="return confirm('Are you sure you want to cancel this application and refund the user?');">
                                                <input type="hidden" name="refund_id" value="<?= $rowId; ?>">
                                                <input type="hidden" name="_source" value="larning_exam">
                                                <button type="submit" class="btn btn-sm btn-danger">Cancel & Refund</button>
                                            </form>
                                            <?php
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <?php
                            }
                            @mysqli_free_result($res);
                        } else {
                            ?>
                            <tr><td colspan="9" class="text-center">No records found.</td></tr>
                            <?php
                        }
                        ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>
<!--end page wrapper -->
<?php include('footer.php'); ?>

<!-- Bootstrap JS -->
<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<!--plugins-->
<script src="../template/ahkweb/assets/js/jquery.min.js"></script>
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="../template/ahkweb/assets/plugins/chartjs/chart.min.js"></script>
<script src="../template/ahkweb/assets/plugins/vectormap/jquery-jvectormap-2.0.2.min.js"></script>
<script src="../template/ahkweb/assets/plugins/vectormap/jquery-jvectormap-world-mill-en.js"></script>
<script src="../template/ahkweb/assets/plugins/jquery.easy-pie-chart/jquery.easypiechart.min.js"></script>
<script src="../template/ahkweb/assets/plugins/sparkline-charts/jquery.sparkline.min.js"></script>
<script src="../template/ahkweb/assets/plugins/jquery-knob/excanvas.js"></script>
<script src="../template/ahkweb/assets/plugins/jquery-knob/jquery.knob.js"></script>
<script>
$(function() {
    $(".knob").knob();
});
</script>
<script src="../template/ahkweb/assets/js/index.js"></script>
<!--app JS-->
<script src="../template/ahkweb/assets/js/app.js"></script>
<!-- datatable -->
<script src="../template/ahkweb/assets/plugins/datatable/js/jquery.dataTables.min.js"></script>
	<script src="../template/ahkweb/assets/plugins/datatable/js/dataTables.bootstrap5.min.js"></script>
<script>
		$(document).ready(function() {
			$('#example').DataTable();
            $('#aadh').inputmask();
		  } );
	</script>
	
	<script>
		$(document).ready(function() {
			var table = $('#example2').DataTable( {
				lengthChange: false,
				buttons: [ 'copy', 'excel', 'pdf', 'print']
			} );
		 
			table.buttons().container()
				.appendTo( '#example2_wrapper .col-md-6:eq(0)' );
		} );


	</script>
	
</body>



</html>