<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

$price = mysqli_fetch_assoc(mysqli_query($ahk_conn,"SELECT * FROM pricing WHERE service_name='v_mobile_link' "));
$fee = $price['price'] ?? '100';
$appliedby = $udata['phone'];

$apiKey = $flexsecure_key;           // api key paste here
$ApiLinkAxxen = "https://" . htmlspecialchars($flexsecureapiurl) . "";                   //  Api Url Flex 

if ($_POST['epicNo'] && $_POST['mobileNumber']) {
    $epicNo = $_POST['epicNo'];
    $mobileNumber = $_POST['mobileNumber'];
    $debit_fee = $udata['balance'] - $fee;

    if ($udata['balance'] >= $fee) {

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "$ApiLinkAxxen/api/v2/VoterMobile/Apply.php?apiKey=$apiKey&epicNumber=$epicNo&mobileNumber=$mobileNumber",
            CURLOPT_RETURNTRANSFER => true
        ));
        $response = curl_exec($curl);
        curl_close($curl);

        $resdata = json_decode($response, true);
        $errore = $resdata['message'] ?? $resdata['error'] ?? '';

        if ($resdata['status'] == "100") {
            date_default_timezone_set('Asia/Kolkata');
            $timedate = date("Y-m-d H:i:s");
            $epicNumber = $resdata['epicNumber'] ?? '';
            $message = $resdata['message'] ?? $resdata['error'] ?? 'Server Down';

            $debit = mysqli_query($ahk_conn, "UPDATE users SET balance = balance - $fee WHERE phone = '$appliedby'");
            mysqli_query($ahk_conn, "INSERT INTO wallethistory(userid, amount, balance, purpose, status, type) VALUES ('$appliedby','$fee','$debit_fee','VCM Request','1','Debit')");

            $exists = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT id FROM vcm_Req WHERE epicNo='$epicNumber' AND mobileNumber='$mobileNumber' AND status='pending'"));
            if (!$exists) {
                $insert = mysqli_query($ahk_conn, "INSERT INTO vcm_Req(epicNo, mobileNumber, status, username, date) VALUES ('$epicNumber', '$mobileNumber', 'pending', '$appliedby','$timedate')");
                if ($insert) {
                    echo "<script>Swal.fire('$message', 'Successfully', 'success'); setTimeout(() => { window.location = ''; }, 5000);</script>";
                } else {
                    echo "<script>Swal.fire('Insert Failed', 'Error saving record.', 'error');setTimeout(() => { window.location = ''; }, 5000);</script>";
                }
            } else {
                echo "<script>Swal.fire('Already Exists', 'Pending request already exists.', 'info');setTimeout(() => { window.location = ''; }, 5000);</script>";
            }
        } else {
            echo "<script>Swal.fire('$errore', 'API Error', 'error');setTimeout(() => { window.location = ''; }, 5000);</script>";
        }
    } else {
        echo "<script>Swal.fire('Wallet Low', 'Please recharge your wallet.', 'error'); setTimeout(() => { window.location = 'wallet.php'; }, 1500);</script>";
    }
}

// CHECK STATUS & HANDLE REFUND
if (isset($_POST['check_status_id'])) {
    $rowId = intval($_POST['check_status_id']);
    $rowData = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT * FROM vcm_Req WHERE id='$rowId' LIMIT 1"));

    if (!$rowData) {
        echo "<script>Swal.fire('Error', 'Record not found.', 'error');</script>";
    } else {
        $checkEpic     = $rowData['epicNo'];
        $checkmobile   = $rowData['mobileNumber'];
        $username      = $rowData['username'];
        $currentStatus = $rowData['status'];

        if ($currentStatus === 'refunded') {
            echo "<script>Swal.fire('Already Refunded', 'This record has already been refunded.', 'info');</script>";
        } else {
            $checkUrl = "$ApiLinkAxxen/api/v2/VoterMobile/CheckStatus.php?apiKey=$apiKey&epicNumber=$checkEpic&mobileNumber=$checkmobile";
            $curl = curl_init();
            curl_setopt_array($curl, array(CURLOPT_URL => $checkUrl, CURLOPT_RETURNTRANSFER => true));
            $response = curl_exec($curl);
            curl_close($curl);
            $data = json_decode($response, true);

            $statusMessage = strtolower($data['statusMessage'] ?? '');
            $status = $data['status'] ?? '';
            $name = $data['name'] ?? '';

            if ($statusMessage === 'refunded') {
                $refund = mysqli_query($ahk_conn, "UPDATE users SET balance = balance + $fee WHERE phone = '$username'");
                $newBalance = $udata['balance'] + $fee;
                $log = mysqli_query($ahk_conn, "INSERT INTO wallethistory(userid, amount, balance, purpose, status, type) VALUES ('$username', '$fee', '$newBalance', 'VCM Refund', '1', 'Credit')");
                $updateStatus = mysqli_query($ahk_conn, "UPDATE vcm_Req SET status='refunded' WHERE id='$rowId'");

                if ($refund && $log && $updateStatus) {
                    echo "<script>Swal.fire('Refunded', 'Amount refunded successfully.', 'success'); setTimeout(() => { window.location = ''; }, 1500);</script>";
                } else {
                    echo "<script>Swal.fire('Error', 'Refund failed.', 'error'); setTimeout(() => { window.location = ''; }, 5000);</script>";
                }

            } elseif ($status == '100') {
                $update = mysqli_query($ahk_conn, "UPDATE vcm_Req SET name='$name', status='completed' WHERE id='$rowId'");
                if ($update) {
                    echo "<script>Swal.fire('Success', 'Details updated.', 'success'); setTimeout(() => { window.location = ''; }, 1500);</script>";
                } else {
                    echo "<script>Swal.fire('Update Failed', 'Could not update Database.', 'error'); setTimeout(() => { window.location = ''; }, 5000);</script>";
                }
            } else {
                $error = $data['error'] ?? $data['message'] ?? 'Unknown error';
                echo "<script>Swal.fire('$error', 'Api Error', 'error'); setTimeout(() => { window.location = ''; }, 5000);</script>";
            }
        }
    }
}
$resultData = mysqli_query($ahk_conn, "SELECT * FROM vcm_Req WHERE username='$appliedby' ORDER BY id ASC");
?>
<!-- Page Wrapper -->
<div class="page-wrapper">
    <div class="page-content">
        <!-- Breadcrumb with Action Buttons -->
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">Services</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">Voter Mobile Link Withot Otp</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-xl-4 mx-auto">
                <h6 class="mb-0 text-uppercase">Voter Request</h6>
                <div class="card border-top border-0 border-4 border-primary">
                    <div class="card-body p-4">
                        <form method="POST">
                            <div class="form-group">
                                <label>Voter Number</label>
                                <input type="text" name="epicNo" id="epicNo" class="form-control" placeholder="Enter Epic Number" maxlength="20" required />
                            </div>
                            <br>
                            <div class="form-group">
                                <label>Mobile Number</label>
                                <input type="text" name="mobileNumber" id="mobileNumber" class="form-control" placeholder="Enter Mobile Number" minlength="10" maxlength="10" required />
                            </div>
                            <br>
                            <button type="submit" class="btn btn-primary">Submit</button>
                        </form>
                        <p><strong>Note:</strong> Wallet ₹<?php echo $fee; ?> will be deducted per request.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- HISTORY SECTION -->
        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-body">
                       <div class="table-responsive mt-3">
                       <table id="historyTable" class="table table-bordered table-striped display nowrap" style="width:100%">
                           <thead>
                               <tr>
                                   <th>#</th>
                                   <th>Data</th>
                                   <th>Mobile</th>
                                   <th>Name</th>
                                   <th>Status</th>
                                   <th>Date</th>
                                   <th>Action</th>
                               </tr>
                           </thead>
                           <tbody>
                <?php 
                $count = 1;
                if (mysqli_num_rows($resultData) > 0) {
                    while ($row = mysqli_fetch_assoc($resultData)) {
                     $epic = $row['epicNo'];
                     $masked = substr($epic, 0, 3) . str_repeat('*', strlen($epic) - 6) . substr($epic, -3);
                    ?>
                    <tr>
                        <td><?php echo $count; ?></td>
                        <td><?php echo $masked; ?></td>
                        <td><?php echo '******' . substr($row['mobileNumber'], -4); ?></td>
                        <td><?php echo $row['name']; ?></td>
                        <td><?php echo strtoupper($row['status']); ?></td>
                        <td><?php echo $row['date']; ?></td>
                        <td>
                            <?php if ($row['status'] == 'pending') { ?>
                                <form method="POST" style="display:inline-block;">
                                    <input type="hidden" name="check_status_id" value="<?php echo $row['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-info">Check Status</button>
                                </form>
                            <?php } elseif ($row['status'] == 'refunded') { ?>
                                <span class="badge bg-danger"><?php echo strtoupper($row['status']); ?></span>
                            <?php } else { ?>
                                <span class="badge bg-success"><?php echo strtoupper($row['status']); ?></span>
                            <?php } ?>
                        </td>
                    </tr>
                    <?php
                    $count++;
                }
            } else {
                // echo "<tr><td colspan='9' class='text-center'>No history found.</td></tr>";
            }
            ?>
        </tbody>
    </table>
</div>
</div>
</div>
</div>
</div>
</div>
</div>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.68/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.68/vfs_fonts.js"></script>
<script>
$(document).ready(function() {
    $('#historyTable').DataTable({
        responsive: true,
        dom: 'Bfrtip', // Buttons + Search + Pagination
        buttons: [
            'copyHtml5', 'excelHtml5', 'csvHtml5', 'pdfHtml5', 'print'
        ],
        pageLength: 10,
        lengthMenu: [5, 10, 25, 50, 100],
        order: [[ 0, "desc" ]]
    });
});
</script>
<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>
<?php include('../template/ahkweb/footer.php'); ?>