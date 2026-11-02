<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

$pdf_base64_raw = '';
$aadhaar = '';
$show_preview = false;
$result_data = [];

if (isset($_POST['find'])) {
    $aadhaar = trim(mysqli_real_escape_string($ahk_conn, $_POST['aadhaar']));

    // Fetch service price
    $priceRow = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT price FROM pricing WHERE service_name='eshram_pdf'"));
    $fee = (float)($priceRow['price'] ?? 0);

    $username = $udata['phone'];
    $wallet_amount = (float)$udata['balance'];
    $debit_fee = $wallet_amount - $fee;

    if ($wallet_amount >= $fee && $fee > 0) {
        $api_key = urlencode($flexsecure_key);
        $url = "https://{$flexsecureapiurl}/api/v1/eshramPDF.php?apiKey={$api_key}&aadhaar={$aadhaar}";

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $response = curl_exec($curl);
        $curl_error = curl_error($curl);
        curl_close($curl);

        if ($curl_error) {
            echo "<script>Swal.fire('API Error', '" . addslashes($curl_error) . "', 'error');</script>";
        } else {
            $result = json_decode($response, true);

            $status = $result['status'] ?? '';
            $MSG = $result['message'] ?? 'Data Not Found In Server';
            $pdf_full = $result['data']['pdf'] ?? '';

            if (!empty($pdf_full) && strpos($pdf_full, 'base64,') !== false) {
                $pdf_base64_raw = explode('base64,', $pdf_full, 2)[1];
            }

            if ($status === "100" && !empty($pdf_base64_raw)) {
                // Debit wallet
                $debit = mysqli_query($ahk_conn, "UPDATE users SET balance = balance - {$fee} WHERE phone = '{$username}'");

                // Log transaction
                $insert_history = mysqli_query($ahk_conn, "INSERT INTO `wallethistory`
                    (`userid`, `amount`, `balance`, `purpose`, `status`, `type`, `date`)
                    VALUES ('{$username}', '{$fee}', '{$debit_fee}', 'Eshram PDF', '1', 'Debit', NOW())");

                if ($debit && $insert_history) {
                    $show_preview = true;
                    $result_data = $result['data'];
                    echo "<script>Swal.fire('" . addslashes($MSG) . "', 'PDF Generated Successfully', 'success');</script>";
                } else {
                    echo "<script>Swal.fire('Database Error', 'Failed to update wallet or transaction history', 'error');</script>";
                }
            } else {
                echo "<script>Swal.fire('" . addslashes($MSG) . "', 'Failed', 'error');</script>";
            }
        }
    } else {
        echo "<script>
            Swal.fire('Oops', 'Wallet Balance Insufficient! Please Recharge', 'error');
            setTimeout(() => window.location.href = 'wallet', 4000);
        </script>";
    }
}
?>

<!-- HTML STARTS -->
	<div class="page-wrapper">
    <div class="page-content">
        <!-- Breadcrumb with Action Buttons -->
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">Services</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">Eshram Card Download</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

        <div class="container-fluid pt-4 px-4">
            <div class="row">
                <!-- Form -->
                <div class="col-lg-5 mb-4">
                    <div class="card">
                        <div class="card-body">
                            <div class="p-3 mb-4 text-white rounded" style="background: linear-gradient(135deg, #0062E6, #33AEFF);">
                                <h3 class="text-center m-0"><i class="bi bi-file-earmark-pdf-fill me-2"></i><strong>Eshram PDF Download</strong></h3>
                            </div>
                            <form method="post">
                                <div class="form-group">
                                    <label for="aadhaar">Enter Aadhaar Number</label>
                                    <input type="text" required class="form-control" name="aadhaar" id="aadhaar"
                                           placeholder="123456789012" maxlength="12" pattern="\d{12}"
                                           title="Enter 12 digit Aadhaar number"
                                           value="<?php echo htmlspecialchars($aadhaar); ?>">
                                </div>
                                <div class="text-center mt-4">
                                    <button type="submit" name="find" class="btn btn-success">
                                        <i class="fa fa-check-circle"></i> Submit
                                    </button>
                                </div>
                                <div class="col-12 d-flex justify-content-center mt-3">
                                    <h5 class="text-warning">
                                        Application Fee: ₹<?php  
                                            $price = mysqli_fetch_assoc(mysqli_query($ahk_conn,"SELECT price FROM pricing WHERE service_name='eshram_pdf'")); 
                                            echo $price['price'];
                                        ?>
                                    </h5>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Result Display -->
                <div class="col-lg-7 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-info text-white">
                            <h5><i class="bi bi-person-lines-fill me-2"></i>Eshram Details</h5>
                        </div>
                        <div class="card-body text-center">
                            <?php if ($show_preview && !empty($pdf_base64_raw)) { ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <tbody>
                                            <tr>
                                                <th scope="row">Aadhaar</th>
                                                <td><?php echo htmlspecialchars($result_data['aadhaar'] ?? $aadhaar); ?></td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Name</th>
                                                <td><?php echo htmlspecialchars($result_data['name'] ?? 'N/A'); ?></td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Father's Name</th>
                                                <td><?php echo htmlspecialchars($result_data['fathername'] ?? 'N/A'); ?></td>
                                            </tr>
                                            <tr>
                                                <th scope="row">UAN</th>
                                                <td><?php echo htmlspecialchars($result_data['uan'] ?? 'N/A'); ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="mt-3">
                                    <a href="data:application/pdf;base64,<?php echo htmlspecialchars($pdf_base64_raw); ?>"
                                       download="Eshram_PDF_<?php echo htmlspecialchars($aadhaar); ?>.pdf"
                                       class="btn btn-success">
                                        <i class="bi bi-download"></i> Download PDF
                                    </a>
                                </div>
                            <?php } else { ?>
                                <p class="text-muted mt-4">No data available. Submit Aadhaar to fetch details.</p>
                            <?php } ?>
                        </div>
                    </div>
                </div>

            </div> 
        </div>
    </div>
</div>




<!-- Styling -->
<style>
    .fee-container {
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        padding: 10px 15px;
        max-width: 300px;
        margin-top: 10px;
    }
    .fee-container h5 {
        color: #ff9800;
        font-weight: 600;
    }
</style>

<!-- JS -->
<script>
function removeSpaces(input) {
    input.value = input.value.replace(/\s/g, "");
}
</script>

<?php include("footer.php");?>
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
    });
</script>
<script>
    $(document).ready(function() {
        var table = $('#example2').DataTable( {
            lengthChange: false,
            buttons: [ 'copy', 'excel', 'pdf', 'print']
        });

        table.buttons().container()
            .appendTo( '#example2_wrapper .col-md-6:eq(0)' );
    });
</script>

</body>
</html>
