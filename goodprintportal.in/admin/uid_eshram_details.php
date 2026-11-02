<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

error_reporting(E_ALL);
ini_set('display_errors', 1);

$resdata = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aadhaar'])) {
    $aadhaar = trim(mysqli_real_escape_string($ahk_conn, $_POST['aadhaar']));

    $priceQuery = $ahk_conn->prepare("SELECT price FROM pricing WHERE service_name = ?");
    $serviceName = 'ehsram_details_find';
    $priceQuery->bind_param("s", $serviceName);
    $priceQuery->execute();
    $result = $priceQuery->get_result();
    $priceRow = $result->fetch_assoc();
    $fee = $priceRow['price'] ?? 0;

    $username = $udata['phone'];
    $wallet = $udata['balance'];

    if ($wallet >= $fee) {
        $apiKey = urlencode($flexsecure_key);
        $apiUrl = "https://" . htmlspecialchars($flexsecureapiurl) . "/api/v1/eshramVerification.php?aadhaar=$aadhaar&apiKey=$apiKey";

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $response = curl_exec($curl);
        curl_close($curl);

        $resdata = json_decode($response, true);

        if (isset($resdata['error']) && $resdata['error']) {
            echo "<script>
                $(function(){
                    Swal.fire('{$resdata['error']}', 'Please try with correct details.', 'warning');
                });
            </script>";
        } elseif (isset($resdata['status']) && $resdata['status'] == "100") {
            $newBalance = $wallet - $fee;

            $updateUser = $ahk_conn->prepare("UPDATE users SET balance = ? WHERE phone = ?");
            $updateUser->bind_param("ds", $newBalance, $username);
            $updateUser->execute();

            date_default_timezone_set('Asia/Kolkata');
            $timestamp = date("Y-m-d H:i:s");
            $purpose = 'Aadhaar Details Find';
            $status = 1;
            $type = 'Debit';

            $insertHistory = $ahk_conn->prepare("INSERT INTO wallethistory (userid, amount, balance, purpose, status, type) VALUES (?, ?, ?, ?, ?, ?)");
            $insertHistory->bind_param("sdssis", $username, $fee, $newBalance, $purpose, $status, $type);
            $insertHistory->execute();

            echo "<script>
                $(function(){
                    Swal.fire('Success!', 'Status: {$resdata['message']}', 'success');
                });
            </script>";
        } else {
            echo "<script>
                $(function(){
                    Swal.fire('Error', 'Unexpected response from server.', 'error');
                });
            </script>";
        }
    } else {
        echo "<script>
            $(function(){
                Swal.fire('Insufficient Balance', 'Wallet Balance Insufficient! Please Recharge.', 'error');
            });
            setTimeout(() => { window.location.href = 'wallet.php'; }, 10000);
        </script>";
    }
}
?>


    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<style>
  /* Flex layout on medium+ screens */
  @media (min-width: 768px) {
    .custom-flex {
      display: flex;
      gap: 20px;
    }
    .left-panel {
      flex: 0 0 40%;
      max-width: 40%;
    }
    .right-panel {
      flex: 0 0 60%;
      max-width: 60%;
    }
  }
</style>

<div class="page-wrapper">
    <div class="page-content">
        <!-- Breadcrumb with Action Buttons -->
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">Services</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">Aadhaar eShram Details</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>
    <!-- End breadcrumb -->

    <div class="container-fluid pt-4 px-4">
      <div class="custom-flex flex-wrap">

        <!-- Left Side: Form (40%) -->
        <div class="card mb-4 left-panel">
          <div class="card-body">
            <h3 class="card-title text-dark text-center"><strong>Aadhaar Details Find</strong></h3>
            <a href="#" class="btn btn-dark w-100 mb-3">Data will come only when e-Shram is created</a>

            <form action="" method="post">
              <div class="form-group mb-3">
                <label for="aadhaar">Aadhaar Number <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="aadhaar" required>
              </div>

              <div class="text-warning mb-3">
                <h5>Application Fee:
                  ₹<?php
                  $priceResult = mysqli_query($ahk_conn, "SELECT price FROM pricing WHERE service_name='ehsram_details_find'");
                  if ($priceResult && mysqli_num_rows($priceResult) > 0) {
                    $price = mysqli_fetch_assoc($priceResult);
                    echo htmlspecialchars($price['price']);
                  } else {
                    echo "N/A";
                  }
                  ?>
                </h5>
              </div>

              <button type="submit" class="btn btn-primary w-100"><i class="fa fa-check-circle"></i> Submit</button>
            </form>
          </div>
        </div>

        <!-- Right Side: Response (60%) -->
        <div class="right-panel mb-4">
          <?php if (!empty($resdata) && $resdata['status'] == "100" && isset($resdata['data'])): ?>
            <div class="card print-area" id="printSection">
              <div class="card-body">
                <h4 class="text-success text-center mb-4">Details Found</h4>

                <?php if (!empty($resdata['data']['image'])): ?>
                  <div class="text-center mb-4">
                    <img src="<?= htmlspecialchars($resdata['data']['image']) ?>" alt="User Image" class="img-thumbnail" style="width: 180px; height: auto;">
                  </div>
                <?php endif; ?>

                <div class="table-responsive">
                  <table class="table table-bordered">
                    <tbody>
                      <?php
                      $fields = [
                        'aadhaar' => 'Aadhaar Number',
                        'name' => 'Name',
                        'co' => 'Care of',
                        'dob' => 'Date of Birth',
                        'gender' => 'Gender',
                        'mobile' => 'Mobile',
                        'occupation' => 'Occupation',
                        'occupation_family' => 'Occupation (Family)',
                        'address' => 'Address'
                      ];

                      foreach ($fields as $key => $label):
                        if (!empty($resdata['data'][$key])): ?>
                          <tr>
                            <th><?= $label ?></th>
                            <td><?= htmlspecialchars($resdata['data'][$key]) ?></td>
                          </tr>
                      <?php
                        endif;
                      endforeach;
                      ?>
                    </tbody>
                  </table>
                </div>

                <div class="text-center no-print mt-3">
                  <button class="btn btn-info" onclick="printDiv('printSection')">🖨️ Print</button>
                </div>
              </div>
            </div>
          <?php elseif (isset($resdata['status']) && $resdata['status'] != "100"): ?>
            <div class="alert alert-danger text-center">No data found for this Aadhaar Number.</div>
          <?php endif; ?>
        </div>

      </div>
    </div>
  </div>
</div>

<script>
function printDiv(divId) {
    const printContents = document.getElementById(divId).innerHTML;
    const originalContents = document.body.innerHTML;

    const newWindow = window.open('', '', 'height=600,width=800');
    newWindow.document.write('<html><head><title>Print</title>');
    newWindow.document.write('<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">');
    newWindow.document.write('<style>body{font-family:sans-serif;padding:20px;} table{width:100%;}</style>');
    newWindow.document.write('</head><body>');
    newWindow.document.write(printContents);
    newWindow.document.write('</body></html>');

    newWindow.document.close();
    newWindow.focus();

    newWindow.print();

    // Automatically close after print
    newWindow.onafterprint = function () {
        newWindow.close();
    };
}
</script>


<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>

<?php include('../template/ahkweb/footer.php'); ?>
</body>
</html>
