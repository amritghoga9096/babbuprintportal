<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

// Check if the user is an admin
if (!checkAdmin($udata['type'])) {
    echo "<script>window.location='index.php';</script>";
    exit;
}

include('../template/ahkweb/tec_exam_req_admin');

// Alert and redirect helper
function handleAlertRedirect($message, $redirect = 'tec_exam_req_admin') {
    echo "<script>alert(" . json_encode($message) . "); window.location='$redirect';</script>";
    exit;
}

// Delete logic
if (isset($_GET['deleteid']) && !empty($_GET['deleteid'])) {
    $deleteid = base64_decode(mysqli_real_escape_string($ahk_conn, $_GET['deleteid']));
    if (mysqli_query($ahk_conn, "DELETE FROM tec_certificate WHERE id='$deleteid'")) {
        handleAlertRedirect('Data Deleted Successfully');
    } else {
        handleAlertRedirect('Delete failed: ' . mysqli_error($ahk_conn));
    }
}

// Update logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['status']) && !empty($_POST['remark']) && !empty($_POST['id'])) {
    $status = mysqli_real_escape_string($ahk_conn, $_POST['status']);
    $remark = mysqli_real_escape_string($ahk_conn, $_POST['remark']);
    $id = intval($_POST['id']);

    $row = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT * FROM tec_certificate WHERE id='$id'"));
    if (!$row) {
        handleAlertRedirect("Record not found.");
    }

    // Handle refund
    if ($status === 'refunded') {
        $amount = floatval($row['fee']);
        $phone = mysqli_real_escape_string($ahk_conn, $row['appliedby']);
        mysqli_begin_transaction($ahk_conn);

        $updateUser = mysqli_query($ahk_conn, "UPDATE users SET balance = balance + $amount WHERE phone = '$phone'");
        $updateStatus = mysqli_query($ahk_conn, "UPDATE tec_certificate SET status='refunded', remark='$remark' WHERE id='$id'");

        if ($updateUser && $updateStatus) {
            mysqli_commit($ahk_conn);
            handleAlertRedirect("Refund applied successfully!");
        } else {
            mysqli_rollback($ahk_conn);
            handleAlertRedirect("Refund failed.");
        }

    } elseif ($status === 'success') {
        $allowedTypes = ["application/pdf", "image/jpeg", "image/png", "image/jpg"];
        $type = $_FILES['aadhaar_pdf']['type'];

        if (in_array($type, $allowedTypes)) {
            $ext = rand(000000, 999999) . $_FILES['aadhaar_pdf']['name'];
            $link = "https://" . $host . "/admin/uploads/" . $ext; // Ensure $host is defined
            $uploadDir = "uploads/";
            
            if (move_uploaded_file($_FILES['aadhaar_pdf']['tmp_name'], $uploadDir . $ext)) {
                $sql = mysqli_query($ahk_conn, "UPDATE tec_certificate SET status='$status', pdf_link='$link', remark='$remark' WHERE id='$id'");
                if ($sql) {
                    showAlert('Tec Updated Successfully!');
                    ahkRedirect('tec_exam_req_admin', 1200);
                }
            } else {
                echo "PDF upload issue";
            }
        } else {
            echo "Only PDF, JPEG, PNG files are allowed.";
        }
    }
}
?>



<!--start page wrapper -->
<div class="page-wrapper">
    <div class="page-content">
        <div class="card radius-10">
            <div class="card-body">
                <h5 class="mb-0">ALL TEC EXAM REQUEST ADMIN LIST</h5>
                <hr>
                <div class="table-responsive">
                    <table id="example2" class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center">#</th>
                                <th class="text-center">Phone</th>
                                <th class="text-center">Application No</th>
                                <th class="text-center">Password</th>
                                <th class="text-center">Fee</th>
                                <th class="text-center">Attachments</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $res = mysqli_query($ahk_conn, "SELECT * FROM tec_certificate ORDER BY id DESC");
                            if (mysqli_num_rows($res) > 0) {
                                $x = 0;
                                while ($data = mysqli_fetch_assoc($res)) {
                                    $x++;
                                    ?>
                                    <tr>
                                        <td class="text-center"><?= $x; ?></td>
                                        <td class="text-center"><?= htmlspecialchars($data['appliedby']); ?></td>
                                        <td class="text-center"><?= htmlspecialchars(strtoupper($data['name'])); ?></td>
                                        <td class="text-center"><?= htmlspecialchars($data['password']); ?></td>
                                        <td class="text-center"><?= htmlspecialchars($data['fee']); ?></td>
                                        <td class="text-center">
                                            <a download="<?= $data['name'] . "_" . $data['mobile'] ?>Aadhaar"
                                               href="uploads/<?= htmlspecialchars($data['aadhaar_pdf']); ?>"
                                               class="btn btn-danger">Aadhaar <i class="fa fa-cloud-download"></i></a>
                                            <a download="<?= $data['name'] . "_" . $data['mobile'] ?>Photo"
                                               href="uploads/<?= htmlspecialchars($data['declaration_form']); ?>"
                                               class="btn btn-danger">Photo <i class="fa fa-cloud-download"></i></a>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($data['status'] == "pending") { ?>
                                                <form method="POST" action="" enctype="multipart/form-data" style="width:250px;">
                                                    <select class="form-control mb-2" name="status" required>
                                                        <option value="">Select Status</option>
                                                        <option value="success">Success</option>
                                                        <option value="refunded">Point Refund</option>
                                                    </select>
                                                    <input class="form-control mb-2" type="text" name="remark" required placeholder="Remark">
                                                    <input type="file" name="aadhaar_pdf" accept=".pdf,image/jpeg,image/png" class="form-control mb-2">
                                                    <input type="hidden" name="id" value="<?= $data['id'] ?>">
                                                    <button class="btn btn-success">Update</button>
                                                    <a href="?deleteid=<?= base64_encode($data['id']); ?>" class="btn btn-danger">Delete</a>
                                                </form>
                                            <?php } elseif ($data['status'] == "success") { ?>
                                                <div class="text-success text-center"><?= htmlspecialchars($data['remark']); ?><br>Already Updated</div>
                                                <a href="?deleteid=<?= base64_encode($data['id']); ?>" class="btn btn-danger">Delete</a>
                                            <?php } elseif ($data['status'] == "refunded") { ?>
                                                <div class="text-info text-center"><?= htmlspecialchars($data['remark']); ?><br>Refunded</div>
                                                <a href="?deleteid=<?= base64_encode($data['id']); ?>" class="btn btn-danger">Delete</a>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                <?php }
                            } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

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
            $('#aadh').inputmask();
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