<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

// Check if the user is an admin
if (!checkAdmin($udata['type'])) {
    echo "<script>window.location='index';</script>";
    die();
}

include('../template/ahkweb/puc_admin');

// Handle deletion of a record
if (isset($_GET['deleteid']) && !empty($_GET['deleteid'])) {
    $deleteid = base64_decode(mysqli_real_escape_string($ahk_conn, $_GET['deleteid']));
    if (mysqli_query($ahk_conn, "DELETE FROM New_Pucc WHERE id='$deleteid'")) {
        showAlert('Data Deleted Successfully');
        ahkRedirect('puc_admin', 1200);
    }
}

// Handle updating the status and remark
if (isset($_POST['status']) && !empty($_POST['status']) && !empty($_POST['remark']) && !empty($_POST['id'])) {
    $status = mysqli_real_escape_string($ahk_conn, $_POST['status']);
    $remark = mysqli_real_escape_string($ahk_conn, $_POST['remark']);
    $id = mysqli_real_escape_string($ahk_conn, $_POST['id']);

    if ($status === 'refunded') {
        $sl = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT * FROM New_Pucc WHERE id='$id' LIMIT 1"));
        $amount = $sl['fee'];
        $updt = mysqli_query($ahk_conn, "UPDATE users SET balance=balance+$amount WHERE phone='".$sl['appliedby']."'");
        $sql = mysqli_query($ahk_conn, "UPDATE New_Pucc SET status='refunded', remark='$remark' WHERE id='$id'");
        if ($updt && $sql) {
            showAlert('Refund Successful');
            ahkRedirect('puc_admin', 1200);
        }
    } elseif ($status === 'success') {
        $allowedTypes = ["application/pdf", "image/jpeg", "image/png", "image/jpg"];
        $type = $_FILES['aadhaar_pdf']['type'] ?? '';

        if (in_array($type, $allowedTypes)) {
            $ext = rand(100000, 999999) . $_FILES['aadhaar_pdf']['name'];
            $uploadDir = "uploads/";
            $link = (isset($host) ? "https://$host/admin/$uploadDir$ext" : $uploadDir . $ext);

            if (move_uploaded_file($_FILES['aadhaar_pdf']['tmp_name'], $uploadDir . $ext)) {
                $sql = mysqli_query($ahk_conn, "UPDATE New_Pucc SET status='success', pdf_link='$link', remark='$remark' WHERE id='$id'");
                if ($sql) {
                    showAlert('PUC Updated Successfully!');
                    ahkRedirect('puc_admin', 1200);
                }
            } else {
                echo "<script>alert('File upload failed.');</script>";
            }
        } else {
            echo "<script>alert('Invalid file type. Only PDF, JPG, JPEG, PNG allowed.');</script>";
        }
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
                        <li class="breadcrumb-item active text-muted" aria-current="page">All Puc Apply List</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="puc_apply" class="btn btn-success">
                    <i class="bi bi-list-ul me-1"></i> Puc Apply
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>
        <div class="card radius-10">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <h5 class="mb-0">NEW PUCC CERTIFICATE REQUEST ADMIN</h5>
                </div>
                <hr>

                <div class="table-responsive">
                    <table id="example2" class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center">#</th>
                                <th class="text-center">Phone</th>
                                <th class="text-center">Application No</th>
                                <th class="text-center">Gadi No</th>
                                <th class="text-center">Mobile No</th>
                                <th class="text-center">Gadi Type</th>
                                <th class="text-center">Fee</th>
                                <th class="text-center">Attachments</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
<?php
$res = mysqli_query($ahk_conn, "SELECT * FROM New_Pucc ORDER BY id DESC");
if (mysqli_num_rows($res) > 0) {
    $x = 0;
    while ($data = mysqli_fetch_assoc($res)) {
        $x++;
?>
        <tr>
            <td class="text-center"><?= $x; ?></td>
            <td class="text-center"><?= htmlspecialchars($data['appliedby']); ?></td>
            <td class="text-center"><?= strtoupper($data['application_no']); ?></td>
            <td class="text-center"><?= strtoupper($data['name']); ?></td>
            <td class="text-center"><?= strtoupper($data['mobile']); ?></td>
            <td class="text-center"><?= strtoupper($data['gadi']); ?></td>
            <td class="text-center">₹<?= $data['fee']; ?></td>
            <td class="text-center">
                <a download="<?= $data['name'] . '_' . $data['mobile'] ?>_Aadhaar"
                   href="uploads/<?= $data['aadhaar_pdf']; ?>"
                   class="btn btn-danger">Docs <i class="fa fa-cloud-download"></i></a>
            </td>
            <td class="text-center">
<?php if ($data['status'] === "pending"): ?>
    <form method="POST" action="" enctype="multipart/form-data" style="width:250px;">
        <select class="form-control mb-2" name="status" required>
            <option value="">Select Status</option>
            <option value="success">Success</option>
            <option value="refunded">Refund</option>
        </select>
        <input class="form-control mb-2" type="text" name="remark" placeholder="Remark" required>
        <input type="file" name="aadhaar_pdf" accept=".pdf,.jpg,.jpeg,.png">
        <input type="hidden" name="id" value="<?= $data['id'] ?>">
        <button class="btn btn-success">Update</button>
        <a href="?deleteid=<?= base64_encode($data['id']); ?>" class="btn btn-danger">Delete</a>
    </form>
<?php elseif ($data['status'] === "success"): ?>
    <div class="text-success text-center">
        <?= strtoupper($data['remark']); ?><br>
        Already Updated
    </div>
    <a href="?deleteid=<?= base64_encode($data['id']); ?>" class="btn btn-danger">Delete</a>
<?php elseif ($data['status'] === "refunded"): ?>
    <div class="text-info text-center">
        <?= $data['remark']; ?><br>
        Refunded
    </div>
    <a href="?deleteid=<?= base64_encode($data['id']); ?>" class="btn btn-danger">Delete</a>
<?php endif; ?>
            </td>
        </tr>
<?php
    }
}
?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>

<!-- JS Includes -->
<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<script src="../template/ahkweb/assets/js/jquery.min.js"></script>
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="../template/ahkweb/assets/plugins/chartjs/chart.min.js"></script>
<script src="../template/ahkweb/assets/plugins/vectormap/jquery-jvectormap-2.0.2.min.js"></script>
<script src="../template/ahkweb/assets/plugins/vectormap/jquery-jvectormap-world-mill-en.js"></script>
<script src="../template/ahkweb/assets/plugins/jquery.easy-pie-chart/jquery.easypiechart.min.js"></script>
<script src="../template/ahkweb/assets/plugins/sparkline-charts/jquery.sparkline.min.js"></script>
<script src="../template/ahkweb/assets/plugins/jquery-knob/jquery.knob.js"></script>
<script>
    $(function () {
        $(".knob").knob();
    });
</script>
<script src="../template/ahkweb/assets/js/index.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>
<script src="../template/ahkweb/assets/plugins/datatable/js/jquery.dataTables.min.js"></script>
<script src="../template/ahkweb/assets/plugins/datatable/js/dataTables.bootstrap5.min.js"></script>
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
