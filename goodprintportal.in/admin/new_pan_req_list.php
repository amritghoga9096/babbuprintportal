<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');
date_default_timezone_set('Asia/Kolkata');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_id'], $_POST['aadhaarNumber'])) {
    $id = (int)$_POST['check_id'];
    $aadhaar = mysqli_real_escape_string($ahk_conn, $_POST['aadhaarNumber']);
    $username = $udata['phone'];

    $rowQ = mysqli_query($ahk_conn, "SELECT * FROM new_pan_request WHERE id='$id' AND username='$username'");
    if (!$rowQ || mysqli_num_rows($rowQ) === 0) {
        echo "<script>Swal.fire('Error', 'Record not found.', 'error');</script>";
        exit;
    }

    $row = mysqli_fetch_assoc($rowQ);
    $fee = floatval($row['fee']);
    $old_balance = floatval($row['old_balance']);

    $apiUrl = "https://" . htmlspecialchars($flexsecureapiurl) .
              "/api/v2/NewPanRequest/CheckStatus.php?apiKey=" . urlencode($flexsecure_key) .
              "&aadhaarNumber=" . urlencode($aadhaar);

    $response = file_get_contents($apiUrl);
    $res = json_decode($response, true);

    if (empty($res['status'])) {
        echo "<script>Swal.fire('Error', 'Invalid API response.', 'error');</script>";
        exit;
    }

    $status = strtolower(trim($res['status']));
    $message = mysqli_real_escape_string($ahk_conn, $res['message'] ?? '');
    $error = mysqli_real_escape_string($ahk_conn, $res['error'] ?? '');
    $escapedMessage = htmlspecialchars($res['message'] ?? '', ENT_QUOTES, 'UTF-8');
    $escapedError = htmlspecialchars($res['error'] ?? 'Verification Failed', ENT_QUOTES, 'UTF-8');

    if (in_array($status, ['pending', 'process', 'success'])) {
        mysqli_query($ahk_conn, "
            UPDATE new_pan_request SET 
                status='$status',
                message='$message'
            WHERE id='$id'
        ");
        echo "<script>Swal.fire('Info', '$escapedMessage', 'info');</script>";

    } elseif ($status === 'completed') {
        $statusMsg = mysqli_real_escape_string($ahk_conn, $res['statusMessage'] ?? '');
        mysqli_query($ahk_conn, "
            UPDATE new_pan_request SET 
                status='completed',
                message='$message',
                status_code='200',
                statusMessage='$statusMsg'
            WHERE id='$id'
        ");
        echo "<script>Swal.fire('Success', 'Pan Application completed and saved.', 'success');</script>";

    } elseif ($status === 'failed') {
        // Check if already refunded
        if ((int)$row['is_refunded'] === 1) {
            echo "<script>Swal.fire('Info', 'Refund already processed for this Aadhaar.', 'warning');</script>";
        } else {
            // Refund logic
            mysqli_query($ahk_conn, "UPDATE users SET balance = balance + $fee WHERE phone = '$username'");

            // Get updated balance
            $balQ = mysqli_query($ahk_conn, "SELECT balance FROM users WHERE phone = '$username'");
            $new_balance = 0;
            if ($balQ && mysqli_num_rows($balQ) > 0) {
                $new_balance = floatval(mysqli_fetch_assoc($balQ)['balance']);
            }

            // Add to wallet history
            mysqli_query($ahk_conn, "INSERT INTO wallethistory(userid, amount, balance, purpose, status, type)
                VALUES ('$username', $fee, $new_balance, 'Pan Request Refund', '1', 'Credit')");

            // Update pan request
            mysqli_query($ahk_conn, "
                UPDATE new_pan_request SET 
                    status='failed',
                    message='$error',
                    new_balance=$new_balance,
                    is_refunded=1
                WHERE id='$id'
            ");

            echo "<script>Swal.fire('Refunded', '$escapedError', 'warning');</script>";
        }
    } else {
        echo "<script>Swal.fire('Error', 'Unknown API status: {$status}', 'error');</script>";
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
                        <li class="breadcrumb-item active text-muted" aria-current="page">All Pan List</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="new_pan_req" class="btn btn-success">
                    <i class="bi bi-list-ul me-1"></i> UTI Online PAN
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>
        <div class="card radius-10">
            <div class="card-body">
                <h5 class="mb-3">New PAN Request Records</h5><hr>
                <div class="table-responsive">
                    <table class="table align-middle table-bordered text-center">
                        <thead class="table-light">
                            <tr>
                                <th>SL</th>
                                <th>Application No</th>
                                <th>Date</th>
                                <th>Aadhaar</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                       <!-- UI table -->
<?php
$result = mysqli_query($ahk_conn, "SELECT * FROM new_pan_request WHERE username='{$udata['phone']}' ORDER BY id DESC");
if (mysqli_num_rows($result) > 0): 
    $i = 1;
    while ($row = mysqli_fetch_assoc($result)):
        echo "<tr>
                <td>{$i}</td>
                <td><span class='badge bg-dark'>{$row['application_no']}</span></td>
                <td>{$row['created_at']}</td>
                <td>{$row['aadhaarNumber']}</td>
                <td>{$row['status']}</td>
                <td>";

        // Show Check Status only if not completed or refunded
       if (in_array($row['status'], ['pending', 'process', 'success'])) {
    echo "<form method='POST' style='display:inline;'>
            <input type='hidden' name='check_id' value='{$row['id']}'>
            <input type='hidden' name='aadhaarNumber' value='{$row['aadhaarNumber']}'>
            <button type='submit' class='btn btn-sm btn-primary'>Check Status</button>
          </form>";
} elseif ($row['status'] === 'completed') {
    echo "<button class='btn btn-sm btn-success' data-bs-toggle='modal' data-bs-target='#detailsModal{$row['id']}'>Show Details</button>";
} elseif ($row['is_refunded'] == 1) {
    echo "<span class='text-danger'>Refunded</span>";
} else {
    echo "-";
}
        echo "</td></tr>";

        // Modal for completed entries
        if ($row['status'] === 'completed') {
            echo "
            <div class='modal fade' id='detailsModal{$row['id']}' tabindex='-1'>
              <div class='modal-dialog'>
                <div class='modal-content'>
                  <div class='modal-header'>
                    <h5 class='modal-title'>PAN Application Details</h5>
                    <button type='button' class='btn-close' data-bs-dismiss='modal'></button>
                  </div>
                  <div class='modal-body text-start'>
                    <p><strong>Aadhaar:</strong> {$row['aadhaarNumber']}</p>
                    <p><strong>API Status Message:</strong><br><pre style='white-space: pre-wrap;'>{$row['statusMessage']}</pre></p>
                  </div>
                  <div class='modal-footer'>
                    <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Close</button>
                  </div>
                </div>
              </div>
            </div>";
        }

        $i++;
    endwhile;
else:
    echo "<tr><td colspan='6'>No records found.</td></tr>";
endif;
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