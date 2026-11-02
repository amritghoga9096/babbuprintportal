<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');
date_default_timezone_set('Asia/Kolkata');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_id'], $_POST['pan'])) {
    $id = (int) $_POST['check_id'];
    $pan = mysqli_real_escape_string($ahk_conn, $_POST['pan']);
    $username = $udata['phone'];

    $rowQ = mysqli_query($ahk_conn, "SELECT * FROM pan_to_uid_find WHERE id='$id' AND username='$username'");
    if ($rowQ && mysqli_num_rows($rowQ) > 0) {
        $row = mysqli_fetch_assoc($rowQ);
        $fee = floatval($row['fee']);
        $old_balance = floatval($row['old_balance']);

        // API Call using cURL instead of file_get_contents
        $apiUrl = "https://" . htmlspecialchars($flexsecureapiurl) . "/api/v2/Pan2Uid/CheckStatus.php?apiKey=" . urlencode($flexsecure_key) . "&panNumber=" . urlencode($pan);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // disable SSL verify if needed
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            echo "<script>Swal.fire('Error', 'API Request Failed: " . addslashes($error_msg) . "', 'error');</script>";
            curl_close($ch);
            exit;
        }
        curl_close($ch);

        $res = json_decode($response, true);

        if (isset($res['status']) && $res['status'] == "100") {
            // Success case
            $name = mysqli_real_escape_string($ahk_conn, $res['name']);
            $dob = mysqli_real_escape_string($ahk_conn, $res['dob']);
            $gender = mysqli_real_escape_string($ahk_conn, $res['gender']);
            $uid = mysqli_real_escape_string($ahk_conn, $res['uid']);
            $msg = mysqli_real_escape_string($ahk_conn, $res['statusMessage']);

            mysqli_query($ahk_conn, "UPDATE pan_to_uid_find SET
                name='$name', dob='$dob', gender='$gender', uid='$uid',
                status='Success', status_code='100', message='$msg'
                WHERE id='$id'");

            echo "<script>Swal.fire('Success', 'Aadhaar details updated!', 'success');</script>";

        } elseif (isset($res['status']) && $res['status'] === "error" && strpos(strtolower($res['error']), 'please wait') !== false) {
            // Don't change status, just update message
            $msg = mysqli_real_escape_string($ahk_conn, $res['error']);
            mysqli_query($ahk_conn, "UPDATE pan_to_uid_find SET
                message='$msg'
                WHERE id='$id'");

            echo "<script>Swal.fire('Processing', 'Data is being processed. Please Wait 10 Minutes try again later.', 'info');</script>";

        } elseif (isset($res['statusMessage']) && strtolower($res['statusMessage']) == "refunded") {
            // Check if already refunded
            $checkRefund = mysqli_query($ahk_conn, "SELECT COUNT(*) AS total FROM pan_to_uid_find 
                WHERE panNumber='$pan' AND username='$username' AND status='Refunded'");
            $refundedCount = mysqli_fetch_assoc($checkRefund)['total'];

            if ($refundedCount > 0) {
                echo "<script>Swal.fire('Already Refunded', 'This PAN was already refunded before.', 'warning');</script>";
            } else {
                $statusMsg = mysqli_real_escape_string($ahk_conn, $res['statusMessage'] ?? '');
                $error = mysqli_real_escape_string($ahk_conn, $res['error'] ?? 'Verification Failed');

                mysqli_query($ahk_conn, "UPDATE users SET balance = balance + $fee WHERE phone = '$username'");
                mysqli_query($ahk_conn, "INSERT INTO wallethistory (userid, amount, balance, purpose, status, type)
                    VALUES ('$username', '$fee', '$old_balance', 'PAN Aadhaar Refund', '1', 'Credit')");

                mysqli_query($ahk_conn, "UPDATE pan_to_uid_find SET
                    status='Refunded',
                    message=CONCAT('$statusMsg ', '$error')
                    WHERE id='$id'");

                echo "<script>Swal.fire('Refunded', 'Verification failed. Amount refunded.', 'warning');</script>";
            }

        } else {
            echo "<script>Swal.fire('Error', 'Unexpected API response.', 'error');</script>";
        }
    }
}
?>

<!-- UI -->
<div class="page-wrapper">
    <div class="page-content">
        <!-- Breadcrumb with Action Buttons -->
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">History</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">All Pan To Aadhaar List</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="pan-2-aadhaar" class="btn btn-success">
                    <i class="bi bi-list-ul me-1"></i> Pan To Aadhaar
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>
        <div class="card radius-10">
            <div class="card-body">
                <h5 class="mb-3">Instant PAN Find Records</h5>
                <hr>
                <div class="table-responsive">
                    <table class="table align-middle table-bordered text-center">
                        <thead class="table-light">
                            <tr>
                                <th>SL</th>
                                <th>Application No</th>
                                <th>Date</th>
                                <th>PAN</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $result = mysqli_query($ahk_conn, "SELECT * FROM pan_to_uid_find WHERE username='{$udata['phone']}' ORDER BY id DESC");
                            if (mysqli_num_rows($result) > 0) {
                                $i = 1;
                                while ($row = mysqli_fetch_assoc($result)) {
                                    echo "<tr>
                                    <td>{$i}</td>
                                    <td><span class='badge bg-dark'>{$row['application_no']}</span></td>
                                    <td>{$row['created_at']}</td>
                                    <td>{$row['panNumber']}</td>
                                    <td>{$row['status']}</td>
                                    <td>";

                                    // Button Logic
                                    if ($row['status'] === 'Requested') {
                                        echo "<form method='POST' style='display:inline;'>
                                            <input type='hidden' name='check_id' value='{$row['id']}'>
                                            <input type='hidden' name='pan' value='{$row['panNumber']}'>
                                            <button type='submit' class='btn btn-sm btn-primary'>Check Status</button>
                                        </form>";
                                    } elseif ($row['status'] === 'Success') {
                                        echo "<button class='btn btn-sm btn-success' data-bs-toggle='modal' data-bs-target='#detailsModal{$row['id']}'>Show Details</button>";
                                    } elseif ($row['status'] === 'Refunded') {
                                        echo "<span class='text-danger'>Refunded</span>";
                                    } else {
                                        echo "-";
                                    }

                                    echo "</td></tr>";

                                    // Success Modal
                                    if ($row['status'] === 'Success') {
                                        echo "
                                    <div class='modal fade' id='detailsModal{$row['id']}' tabindex='-1'>
                                        <div class='modal-dialog'>
                                            <div class='modal-content'>
                                                <div class='modal-header'>
                                                    <h5 class='modal-title'>Aadhaar Details</h5>
                                                    <button type='button' class='btn-close' data-bs-dismiss='modal'></button>
                                                </div>
                                                <div class='modal-body text-start'>
                                                    <p><strong>PAN:</strong> {$row['panNumber']}</p>
                                                    <p><strong>Aadhaar:</strong> {$row['uid']}</p>
                                                    <p><strong>Name:</strong> {$row['name']}</p>
                                                    <p><strong>DOB:</strong> {$row['dob']}</p>
                                                    <p><strong>Gender:</strong> {$row['gender']}</p>
                                                    <p><strong>Status Message:</strong> {$row['message']}</p>
                                                </div>
                                                <div class='modal-footer'>
                                                    <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Close</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>";
                                    }
                                    $i++;
                                }
                            } else {
                                echo "<tr><td colspan='6'>No records found.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SweetAlert2 + Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>


<!--end page wrapper -->
<?php
include('footer.php');
?>
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
    $(function () {
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
    $(document).ready(function () {
        $('#example').DataTable();
    });
</script>

<script>
    $(document).ready(function () {
        var table = $('#example2').DataTable({
            lengthChange: false,
            buttons: ['copy', 'excel', 'pdf', 'print']
        });

        table.buttons().container()
            .appendTo('#example2_wrapper .col-md-6:eq(0)');
    });
</script>

</body>



</html>