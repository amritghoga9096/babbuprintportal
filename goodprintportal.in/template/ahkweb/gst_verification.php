<!-- Page Wrapper -->
<div class="page-wrapper">
    <div class="page-content">
        <!-- Breadcrumb with Action Buttons -->
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">Services</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">Gst Verification</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
               
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>
        <!-- End Breadcrumb -->

        <head>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
            <style>
                .hidden {
                    display: none;
                }
                .container-fluid {
                    padding-top: 30px;
                    padding-left: 30px;
                    padding-right: 30px;
                }
                /* Status Styling */
                .status-active {
                    color: green;
                    font-weight: bold;
                }
                .status-cancelled {
                    color: red;
                    font-weight: bold;
                }
                .status-unknown {
                    color: orange;
                    font-weight: bold;
                }
            </style>
        </head>

        <body>

        <?php
        include('header.php');
        if (isset($_POST['keyword'])) {
            $keyword = $_POST['keyword'] ?? null;

            $price = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT * FROM pricing WHERE service_name='gst_verification'"));
            $fee = $price['price'];
            $username = $udata['phone'];
            $wallet = $udata['balance'];

            if ($wallet > $fee) {
                $debit_fee = $wallet - $fee;

                $api_key = urlencode($flexsecure_key);
                $url = "https://" . htmlspecialchars($flexsecureapiurl) . "/api/v1/gstVerification?apiKey=$api_key&keyword=" . urlencode($keyword);

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

            

                if (!$resdata['success']) {
                    ?>
                    <script>
                        $(function(){
                            Swal.fire(
                                 '<?php echo $resdata['message']; ?>',
                                 'Please Try After Sometime',
                                'warning'
                            );
                        });
                        setTimeout(() => {
                            window.location.href = '#';
                        }, 10000);
                    </script>
                    <?php
                } else {
                    // Deduct wallet
                    mysqli_query($ahk_conn, "UPDATE `users` SET balance='$debit_fee' WHERE phone='$username'");
                    date_default_timezone_set('Asia/Kolkata');
                    $timestamp = date("Y-m-d H:i:s");
                    mysqli_query($ahk_conn, "INSERT INTO `wallethistory`(`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES ('$username','$fee','$debit_fee','GST Verification','1','Debit')");
                }
            } else {
                ?>
                <script>
                    $(function(){
                        Swal.fire(
                            'Insufficient Balance',
                            'Please recharge your wallet',
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

        <!-- Main Content -->
        <div class="container-fluid pt-4 px-4">
            <div class="row">
                <!-- Left Side: Form Section -->
                <div class="col-lg-4 col-md-12 mb-4">
                    <div class="card">
                        <div class="card-body">
                            <div class="p-3 mb-4 rounded text-white" style="background: linear-gradient(135deg, #0062E6, #33AEFF);">
                                <h3 class="text-center m-0 text-white">
                                    <i class="bi bi-file-earmark-pdf-fill me-2"></i>
                                    <strong>Gst Verification</strong>
                                </h3>
                            </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="form-group">
                                    <label for="keyword">Search Keyword (Name, GST Number, PAN Number)<span style="color:red;">*</span></label>
                                    <input type="text" name="keyword" class="form-control" placeholder="Enter Name, GST Number, PAN Number" required>
                                </div>
                                </div>
                                    <div class="text-center mb-3">
                                <button type="submit" name="find" class="btn btn-success btn-block"><i class="fa fa-check-circle"></i> Submit</button>
                                 <div class="col-12 ml-2 d-flex justify-content-center">
    <div class="fee-container text-center">
        <h5 class="text-warning">Application Fee: 
            <?php  
                $price = mysqli_fetch_assoc(mysqli_query($ahk_conn,"SELECT price FROM pricing WHERE service_name='gst_verification'")); 
                echo "₹" . $price['price'];
            ?>
        </h5>
    </div>
</div>

<style>
    .fee-container {
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        padding: 10px 15px;
        max-width: 300px;
        width: 100%;
        margin-top: 10px;
        margin-bottom: 10px;
    }

    .fee-container h5 {
        color: #ff9800; /* Bright yellow/orange color for emphasis */
        font-size: 18px;
        font-weight: 600;
        margin: 0;
    }
</style>

                            </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Right Side: GST Verification Results -->
                <div class="col-lg-8 col-md-12">
                    <?php if (isset($resdata) && $resdata['success']) : ?>
                        <div class="card mt-4">
                            <div class="card-header bg-success text-white">
                                <h5>GST Verification Results</h5>
                            </div>
                            <div class="card-body table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Sr No</th>
                                            <th>GSTIN</th>
                                            <th>Name</th>
                                            <th>Trade Name</th>
                                            <th>City</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $sr = 1; foreach ($resdata['results'] as $item): ?>
                                            <tr>
                                                <td><?= $sr++; ?></td>
                                                <td><strong><?= $item['gstin']; ?></strong></td>
                                                <td><?= $item['name']; ?></td>
                                                <td><?= $item['trade_name']; ?></td>
                                                <td><?= $item['city']; ?></td>
                                                <td>
                                                    <?php 
                                                    // Check the status field and apply appropriate styling
                                                    if (isset($item['status'])) {
                                                        if ($item['status'] == "Active") {
                                                            echo '<span class="status-active">Active</span>';
                                                        } elseif ($item['status'] == "Cancelled") {
                                                            echo '<span class="status-cancelled">Cancelled</span>';
                                                        } else {
                                                            echo '<span class="status-unknown">Unknown Status</span>';
                                                        }
                                                    } else {
                                                        echo '<span class="status-unknown">No Status Available</span>';
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
        <script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
        <script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
        <script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
        <script src="../template/ahkweb/assets/js/app.js"></script>
        </body>
        </html>

        <?php 
        include('footer.php');
        ?>
