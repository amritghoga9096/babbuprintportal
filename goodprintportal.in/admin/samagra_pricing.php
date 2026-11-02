<?php
include('../includes/session.php');
include('../includes/config.php');
if(checkAdmin($udata['type']) == false){
    ?>
    <script>
        window.location='index.php';
    </script>
    <?php
    die();
}

$samagraServices = [
    'samagra_pdf_fee'              => ['label' => 'Samagra PDF Without OTP', 'default' => '20'],
    'samagra_new_apply_fee'        => ['label' => 'Samagra New Apply',         'default' => '150'],
    'samagra_member_delete_fee'    => ['label' => 'Samagra Member Delete',     'default' => '150'],
    'samagra_member_transfer_fee'  => ['label' => 'Samagra Member Transfer',   'default' => '300'],
    'samagra_married_divorce_fee'  => ['label' => 'Samagra Married to Divorce','default' => '350'],
    'samagra_kyc_fee'              => ['label' => 'Samagra KYC',               'default' => '70'],
    'samagra_new_family_id_fee'    => ['label' => 'Samagra New Family ID',     'default' => '250'],
    'samagra_pravasan_fee'         => ['label' => 'Samagra Pravasan',          'default' => '1000'],
    'samagra_merge_family_id_fee'  => ['label' => 'Samagra Merge Family ID',   'default' => '600'],
    'samagra_recover_member_fee'   => ['label' => 'Samagra Recover Member',    'default' => '199'],
];

foreach ($samagraServices as $svc => $meta) {
    $svcSafe = mysqli_real_escape_string($ahk_conn, $svc);
    $defSafe = mysqli_real_escape_string($ahk_conn, $meta['default']);
    mysqli_query($ahk_conn, "INSERT IGNORE INTO `pricing` (`service_name`, `price`) VALUES ('$svcSafe', '$defSafe')");
}

$success_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['samagra_pricing_submit'])) {
    $update_successful = true;
    foreach ($samagraServices as $svc => $meta) {
        if (isset($_POST[$svc])) {
            $new_price = mysqli_real_escape_string($ahk_conn, $_POST[$svc]);
            $svcSafe   = mysqli_real_escape_string($ahk_conn, $svc);
            if (!mysqli_query($ahk_conn, "UPDATE pricing SET price='$new_price' WHERE service_name='$svcSafe'")) {
                $update_successful = false;
            }
        }
    }
    if ($update_successful) {
        $success_message = "Samagra Prices updated successfully!";
    }
}

$pricing = [];
foreach (array_keys($samagraServices) as $svc) {
    $svcSafe = mysqli_real_escape_string($ahk_conn, $svc);
    $result = mysqli_query($ahk_conn, "SELECT price FROM pricing WHERE service_name='$svcSafe'");
    $row = mysqli_fetch_assoc($result);
    $pricing[$svc] = $row ? $row['price'] : $samagraServices[$svc]['default'];
}

include('../template/ahkweb/header.php');
?>

<div class="page-wrapper">
    <div class="page-content">
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item"><a href="javascript:;"><i class="bx bx-home-alt"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Samagra Pricing</li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-10 mx-auto">
                <div class="card border-top border-0 border-4 border-primary">
                    <div class="card-body p-5">
                        <div class="card-title d-flex align-items-center">
                            <div><i class="bx bxs-cog me-1 font-22 text-primary"></i></div>
                            <h5 class="mb-0 text-primary">Samagra Services Pricing Settings</h5>
                        </div>
                        <hr>
                        <form action="" method="POST" class="row g-3">
                            <input type="hidden" name="samagra_pricing_submit" value="1">
                            <?php foreach ($samagraServices as $svc => $meta):
                                $service_price = isset($pricing[$svc]) ? htmlspecialchars($pricing[$svc]) : htmlspecialchars($meta['default']);
                                $label = htmlspecialchars($meta['label']);
                                $inputId = 'input_' . preg_replace('/[^a-zA-Z0-9]/', '_', $svc);
                            ?>
                                <div class="col-md-3">
                                    <label for="<?php echo $inputId; ?>" class="form-label"><?php echo $label; ?></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fa-solid fa-indian-rupee-sign"></i></span>
                                        <input name="<?php echo htmlspecialchars($svc); ?>"
                                               value="<?php echo $service_price; ?>"
                                               type="number" min="0" step="0.01"
                                               placeholder="<?php echo $label; ?>"
                                               class="form-control"
                                               id="<?php echo $inputId; ?>">
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary px-5">
                                    <i class="bx bx-save me-1"></i> Save Prices
                                </button>
                                <a href="Samagra_card_service.php" class="btn btn-outline-secondary px-5 ms-2">
                                    <i class="bx bx-arrow-back me-1"></i> View Services
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include('footers.php'); ?>

<?php if ($success_message != ''): ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: '<?php echo $success_message; ?>',
            confirmButtonText: 'OK'
        });
    </script>
<?php endif; ?>

<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<script src="../template/ahkweb/assets/js/jquery.min.js"></script>
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>
</body>
</html>
