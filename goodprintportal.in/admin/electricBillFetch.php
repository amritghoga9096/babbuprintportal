<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

// Fetch electric bill operator list
$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL => "https://" . htmlspecialchars($flexsecureapiurl) . "/api/v1/electricBill_Operator",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "GET",
));
$Operatorfetch = curl_exec($curl);
curl_close($curl);
$operatorList = json_decode($Operatorfetch, true) ?? '';

// fetch fee from databasee 
$price = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT * FROM pricing WHERE service_name='electricBill_details_fee' "));
$fee = $price['price'];
    
if ($_POST['caNumber']) {
    $caNumber = $_POST['caNumber'];
    $opid = $_POST['opid'];
    $appliedby = $udata['phone'];
    $debit_fee =  $udata['balance'] - $fee;

    if ($udata['balance'] >= $fee) {
        
        $api_key =   urlencode($flexsecure_key); // Replace with actual API key
        
        $url = "https://" . htmlspecialchars($flexsecureapiurl) . "/api/v1/electricBillFetch.php?apiKey=$api_key&bill_number=$caNumber&operator_code=$opid";
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        $resdata = json_decode($response, true) ?? '';

        $caNumber = $resdata['caNumber'] ?? '';
        $name = $resdata['name'] ?? '';
        $dueDate = $resdata['dueDate'] ?? '';
        $billGeneratedDate = $resdata['billGeneratedDate'] ?? '';
        $billAmount = $resdata['billAmount'] ?? '';
        $totalAmount = $resdata['totalAmount'] ?? '';
        $billPeriod = $resdata['billPeriod'] ?? '';
        $status = $resdata['status'] ?? '';
        $errore = $resdata['error'] ?? '';

        if ($errore) {
            echo "<script>$(function(){ Swal.fire('$errore', 'Someting Wrong.', 'error') }); setTimeout(() => { window.location=''; }, 5000);</script>";
        } elseif ($resdata['status'] === "100") {
            $debit = mysqli_query($ahk_conn, "UPDATE users SET balance=balance-$fee WHERE phone='$appliedby'");
            $updatehistory = mysqli_query($ahk_conn, "INSERT INTO `wallethistory`(`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES ('$appliedby','$fee','$debit_fee','Electric Bill Details','1','Debit')");
        } 
    } else {
        echo "<script>$(function(){ Swal.fire('Wallet Balance is Low!', 'Please Recharge Now!', 'error') }); setTimeout(() => { window.location='wallet.php'; }, 1200);</script>";
    }
}
?>
<!-- Page Wrapper -->
<div class="page-wrapper">
    <div class="page-content">
        <!-- Breadcrumb with Action Buttons -->
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">History</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">Electric Service</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
              
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

        <!-- Breadcrumb -->
      
        <!-- End Breadcrumb -->

        <head>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
            <style>
                .hidden {
                    display: none;
                }
            </style>
        </head>

        <body>
 <div class="container-fluid pt-4 px-4">
                <div class="row">
                    <!-- Left Side: Form Section -->
                    <div class="col-lg-5 mb-4">
                        <div class="card">
                            <div class="card-body">
                               <h3 class="card-title text-dark text-center"><strong>Electric Bill DETAIL</strong></h3>
                            </div>
                            <div class="card-body">
                                <a href="#" class="btn w-100 mb-3" style="
  background: linear-gradient(to right, #FF9933 33%, #FFFFFF 33%, #FFFFFF 66%, #138808 66%);
  color: #000;
  border: 1px solid #000;
  font-weight: bold;
">
  ALL INDIA
</a>

                                                    <form action="" method="POST" class="row g-3">
                                                        <div class="card-body">
                                                            <div class="col-md-12">
                                                                <div class="form-group">
                                                                    <label for="operator">Select Operator</label>
                                                                    <select name="operator" id="operator" class="form-control select2" required>
                                                                        <option value="">-- Select Operator --</option>
                                                                        <?php
                                                                        if ($operatorList['status'] == 'success') {
                                                                            foreach ($operatorList['operators'] as $op) {
                                                                                 echo "<option value='" . $op['op'] . "' data-opid='" . $op['op'] . "'>" . $op['name'] . "</option>";
                                                                            }
                                                                        }
                                                                        ?>
                                                                    </select>
                                                                    <br>
                                                                <hr>
                                                                    <label for="caNumber">Enter CA Number</label>
                                                                    <input name="caNumber" type="text" id="caNumber" placeholder="Enter 9 or 12 Digit CA Number" class="form-control" required>
                                                                    <input name="opid" type="hidden" id="opid" class="form-control" required>
                                                                    <input type="hidden" name="check" value="aadhaar">
                                                                </div>
                                                                <hr>
                                                                <div class="row mt-3">
                                                                  <div class="col-md-6">
                                                                       <input class="form-control" value="Fee ₹ <?php echo $price['price']; ?>" readonly>
                                                                   </div>
                                                                   <div class="col-md-6 text-end">
                                                                       <button class="btn btn-success" name="submit" id="submit"><i class="fa fa-check-circle"></i> Submit</button>
                                                                   </div>
                                                               </div>
                                                            </div>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
         <?php if ($resdata['status'] === "100") { ?>
    <div class="col-lg-8 col-md-6 col-sm-6">
        <div class="card" style="background-color: #FCF3CF;" id="printSection">
            <div class="card-body">
                <h5 class="mb-3">Electricity Bill Details</h5>
                <table class="table table-bordered table-striped">
                    <tbody>
                        <tr>
                            <th>CA Number</th>
                            <td><?php echo htmlspecialchars($caNumber); ?></td>
                        </tr>
                        <tr>
                            <th>Consumer Name</th>
                            <td><?php echo htmlspecialchars($resdata['name']); ?></td>
                        </tr>
                        <tr>
                            <th>Consumer Number</th>
                            <td><?php echo htmlspecialchars($resdata['caNumber']); ?></td>
                        </tr>
                        <tr>
                            <th>Bill Period</th>
                            <td><?php echo htmlspecialchars($resdata['billPeriod']); ?></td>
                        </tr>
                        <tr>
                            <th>Bill Generated Date</th>
                            <td><?php echo htmlspecialchars($resdata['billGeneratedDate']); ?></td>
                        </tr>
                        <tr>
                            <th>Due Date</th>
                            <td><?php echo htmlspecialchars($resdata['dueDate']); ?></td>
                        </tr>
                        <tr>
                            <th>Bill Amount</th>
                            <td><?php echo htmlspecialchars($resdata['billAmount']); ?></td>
                        </tr>
                        <tr>
                            <th>Total Amount</th>
                            <td>₹<?php echo htmlspecialchars($resdata['totalAmount']); ?></td>
                        </tr>
                    </tbody>
                </table>

                <div class="text-end mt-3">
                    <button class="btn btn-primary" onclick="printBill()">🖨️ Print</button>
                </div>
            </div>
        </div>
    </div>
<?php } ?>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <br><br><br><br>
            </div>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>

<!-- Required JS -->
<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>

<!-- Select2 JS & CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
function printBill() {
    var content = document.getElementById("printSection").innerHTML;

    var printWindow = window.open('', '_blank', 'width=800,height=600');
    printWindow.document.write(`
        <html>
            <head>
                <title>Electricity Bill</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 20px; }
                    table { width: 100%; border-collapse: collapse; }
                    th, td { border: 1px solid #000; padding: 8px; text-align: left; }
                    th { background-color: #f2f2f2; }
                    h5 { margin-bottom: 20px; }
                </style>
            </head>
            <body>
                ${content}
                <script>
                    window.onload = function() {
                        window.print();
                    };
                <\/script>
            </body>
        </html>
    `);
    printWindow.document.close();
}
</script>

<!-- Init Select2 -->
<script>
$(document).ready(function () {
    $('#operator').on('change', function () {
        var selectedOpId = $(this).find(':selected').data('opid');
        $('#opid').val(selectedOpId);
    });

    // Initialize Select2 (already present from earlier)
    $('#operator').select2({
        placeholder: "-- Select Operator --",
        allowClear: true,
        width: '100%'
    });
});
</script>

</html>
