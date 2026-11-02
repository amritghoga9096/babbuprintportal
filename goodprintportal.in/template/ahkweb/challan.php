<?php
include('header.php');

if (isset($_POST['bikeNumber'])) {
    $bikeNumber = $_POST['bikeNumber'] ?? null;

    $price = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT * FROM pricing WHERE service_name='challan_fee'"));
    $fee = $price['price'];
    $username = $udata['phone'];
    $wallet = $udata['balance'];

    if ($wallet >= $fee) {
        $debit_fee = $wallet - $fee;

        $api_key = urlencode($flexsecure_key);
        $url = "https://" . htmlspecialchars($flexsecureapiurl) . "/serviceApi/FlexSecureV1/challan/challan_link.php?bikeNumber=$bikeNumber&apiKey=$api_key";

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ]);

        $response = curl_exec($curl);
        curl_close($curl);

        $resdata = json_decode($response, true);

        if (isset($resdata['statusMessage']) && $resdata['statusMessage'] === 'Success' && $resdata['status'] === '100') {
            // API call succeeded
            mysqli_query($ahk_conn, "UPDATE `users` SET balance='$debit_fee' WHERE phone='$username'");
            date_default_timezone_set('Asia/Kolkata');
            $timestamp = date("Y-m-d H:i:s");
            mysqli_query($ahk_conn, "INSERT INTO `wallethistory`(`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES ('$username','$fee','$debit_fee','Challan Details Find','1','Debit')");

            ?>
            <script>
                $(function(){
                    Swal.fire(
                        'Challan Data Fetched',
                        'Status: <?= $resdata['statusMessage']; ?>',
                        'success'
                    )
                })
                setTimeout(() => {
                    window.location = '#';
                }, 12000);
            </script>
            <?php
        } else {
            // API failure
            $errorMsg = $resdata['message'] ?? 'Unable to fetch challan data.';
            ?>
            <script>
                $(function(){
                    Swal.fire(
                        'Request Failed',
                        '<?= $errorMsg ?>',
                        'warning'
                    );
                });
                setTimeout(() => {
                    window.location.href = '#';
                }, 10000);
            </script>
            <?php
        }
    } else {
        ?>
        <script>
            $(function(){
                Swal.fire(
                    'Insufficient Balance',
                    'Please recharge your wallet to continue.',
                    'error'
                );
            });
            setTimeout(() => {
                window.location.href = 'wallet.php';
            }, 10000);
        </script>
        <?php
    }
}
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
                        <li class="breadcrumb-item active text-muted" aria-current="page">Challan Find</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
               
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>
            <div class="ms-auto">
                <div class="btn-group">
                  
                </div>
            </div>
        </div>
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
                               <h3 class="card-title text-dark text-center"><strong>Challan Find Instant</strong></h3>
                            </div>
                            <div class="card-body">
                                <a href="rc_get_two" class="btn btn-dark w-100 mb-3">RC PDF Download Here</a>
                                <form name="" action="" method="post" id="rasan_print">
                                    <div class="card-body">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label class="card-title" for="bikeNumber">Vehical Number <span class="required-mark text-red" style="color:red;">*</span></label>
                                                <input type="text" class="form-control" name="bikeNumber" id="bikeNumber" placeholder="Enter Vehical Number">
                                            </div>
                                        </div>
                                            <div class="col-12 ml-2">
                                                <h5 class="text-warning">Application Fee: 
                                                    <?php  
                                                    $price = mysqli_fetch_assoc(mysqli_query($ahk_conn,"SELECT price FROM pricing WHERE service_name='challan_fee'")); 
                                                    echo "₹" .$price['price'];
                                                    ?>
                                                </h5>
                                            </div>
                                        <div class="row row-sm mg-t-20">
                                            <div class="col">
                                                <button type="submit" class="btn btn-primary w-100"><i class="fa fa-check-circle"></i> Submit</button>
                                            </div>
                                        </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                    </div>
                </div>
                    <div class="col-md-12">
                      <?php if (isset($resdata) && strtolower($resdata['statusMessage']) == "success") : ?>
    <div class="table-responsive mt-4">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th class="text-center">Vehicle No</th>
                    <th class="text-center">Owner Name</th>
                    <th class="text-center">Model</th>
                    <th class="text-center">Company</th>
                    <th class="text-center">Type</th>
                    <th class="text-center">Fuel</th>
                    <th class="text-center">Challan Info</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $vehicle = $resdata['vehicle'] ?? [];
                $challans = $resdata['challans'] ?? [];

                // In case of no challans
                if (empty($challans)) :
                ?>
                    <tr>
                        <td><?= htmlspecialchars($vehicle['registration_number'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($vehicle['owner_name'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($vehicle['model_name'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($vehicle['vehicle_name'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($vehicle['vehicle_type_v2'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($vehicle['fuel_type'] ?? '-') ?></td>
                        <td><button class="btn btn-success"><strong>CHALLAN NOT FOUND</strong></button></td>
                    </tr>
                <?php
                else:
                    foreach ($challans as $challan):
                ?>
                    <tr>
                        <td><?= htmlspecialchars($vehicle['registration_number'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($challan['owner_name'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($vehicle['model_name'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($vehicle['vehicle_name'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($vehicle['vehicle_type_v2'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($vehicle['fuel_type'] ?? '-') ?></td>
                        <td>
                            <div class="text-center">
                                <p><strong>Challan No:</strong> <?= htmlspecialchars($challan['challan_number'] ?? '-') ?></p>
                                <p><strong>Amount:</strong> ₹<?= htmlspecialchars($challan['amount'] ?? '0') ?></p>
                                <p><strong>Penalty:</strong> ₹<?= htmlspecialchars($challan['penalty'] ?? '0') ?></p>
                                <p><strong>Status:</strong>
                                    <?php if ($challan['challan_status'] == 'PAID'): ?>
                                        <span class="badge bg-success">PAID</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">UNPAID</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </td>
                    </tr>
                <?php
                    endforeach;
                endif;
                ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

                    </div>
    </div>
	<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
	<!--plugins-->
	<!-- <script src="../template/ahkweb/assets/js/jquery.min.js"></script> -->
	<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
	<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
	<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
	<!--app JS-->
	<script src="../template/ahkweb/assets/js/app.js"></script>
</body>
</html>


		<?php 
		include('footer.php');
		?>