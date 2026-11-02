<?php include("header.php");
$s_phone = mysqli_real_escape_string($ahk_conn, (string)$udata['phone']);

@mysqli_report(MYSQLI_REPORT_OFF);
@mysqli_query($ahk_conn, "CREATE TABLE IF NOT EXISTS `dl_pdf_s3` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) DEFAULT NULL,
  `dl_no` varchar(100) DEFAULT NULL,
  `dob` varchar(20) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `pdf` longtext DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `date` varchar(100) DEFAULT NULL,
  `request_id` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_dl_s3_user` (`username`),
  KEY `idx_dl_s3_dl` (`dl_no`),
  KEY `idx_dl_s3_reqid` (`request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if (isset($_POST['delete']) && $_POST['delete'] == "del") {
    $id = (int)base64_decode((string)($_POST['id'] ?? ''));
    $del = $id > 0 ? mysqli_query($ahk_conn, "DELETE FROM `dl_pdf_s3` WHERE id=$id AND username='$s_phone'") : false;
    if ($del && mysqli_affected_rows($ahk_conn) > 0) {
        ?>
        <script>
            $(function(){
                Swal.fire('Success', 'Data Deleted Success!', 'success');
            });
        </script>
        <?php
    } else {
        ?>
        <script>
            $(function(){
                Swal.fire('Opps', 'Data Not Deleted !', 'error');
            });
        </script>
        <?php
    }
}

if (isset($_GET['dup']) && $_GET['dup'] == '1') {
    ?>
    <script>
        $(function(){
            Swal.fire('Already Generated', 'Ye DL PDF pehle se aapki list me hai. Yahin se download kijiye (dobara charge nahi hua).', 'info');
        });
    </script>
    <?php
}
?>

<script>
    $(document).ready(() => {
        $('#default-datatable').DataTable({
            "order": [[ 0, "asc" ]],
            "pageLength": 10
        });
    });
</script>

<div class="page-wrapper">
    <div class="page-content">
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">History</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">All Instant DL Pdf Server 3 List</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group" role="group" aria-label="actions">
                <a href="dl_pdf_s3" class="btn btn-primary" style="border-radius:8px;background:linear-gradient(135deg,#2563eb 0%,#3b82f6 100%);border:none;">
                    <i class="bi bi-plus-square me-1"></i> + Generate New
                </a>
                <a href="vahicle_card_service" class="btn btn-outline-primary" style="border-radius:8px;">
                    <i class="bi bi-grid me-1"></i> All Services
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-secondary" style="border-radius:8px;">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>
        <div class="card radius-10">
<div class="content-wrap">
    <div class="main">
        <div class="col-md-12">
            <div class="main-content">
                <section class="section">
                    <div class="card-header" style="background:linear-gradient(135deg,#2563eb 0%,#3b82f6 50%,#60a5fa 100%);">
                        <div class="card-title">
                            <h3 style="color:#fff;margin:0;"><strong>Instant DL Pdf Server 3 List</strong></h3>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table id="default-datatable" class="table table-bordered" width="100%">
                                            <thead style="color:white;background:linear-gradient(-62deg, #1e3a8a, #3b82f6);">
                                                <tr>
                                                    <th style="color:white; text-align:center;">#</th>
                                                    <th style="color:white">DL Number</th>
                                                    <th style="color:white">Date of Birth</th>
                                                    <th style="color:white">Download Date</th>
                                                    <th style="color:white; text-align:center;">Download PDF</th>
                                                    <th style="color:white; text-align:center;">Delete</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $sql = mysqli_query($ahk_conn, "SELECT id, dl_no, dob, date FROM dl_pdf_s3 WHERE username='$s_phone' ORDER BY id DESC");
                                                if ($sql && mysqli_num_rows($sql) > 0) {
                                                    $slno = 1;
                                                    while ($data = mysqli_fetch_assoc($sql)) {
                                                ?>
                                                        <tr>
                                                            <td><?php echo $slno; ?></td>
                                                            <td><strong style="font-family:'JetBrains Mono',monospace;letter-spacing:1px;"><?php echo htmlspecialchars((string)$data['dl_no']); ?></strong></td>
                                                            <td><?php echo htmlspecialchars((string)$data['dob']); ?></td>
                                                            <td><?php echo htmlspecialchars((string)$data['date']); ?></td>
                                                            <td align="center" valign="middle">
                                                                <form action="dl_pdf_s3_download.php" method="get" target="_blank" rel="noopener noreferrer" style="margin:0;padding:0;display:inline-block;">
                                                                    <input type="hidden" name="id" value="<?php echo (int)$data['id']; ?>">
                                                                    <button type="submit" style="padding:8px 12px;font-size:14px;font-weight:600;cursor:pointer;border:none;background-color:#3b82f6;color:#fff;border-radius:4px;box-shadow:0 4px 10px rgba(59,130,246,.2);display:inline-flex;align-items:center;gap:6px;"
                                                                            onmouseover="this.style.boxShadow='0 7px 14px rgba(59,130,246,.25)';this.style.transform='translateY(-1px)'"
                                                                            onmouseout="this.style.boxShadow='0 4px 10px rgba(59,130,246,.2)';this.style.transform=''">
                                                                        ⬇️ Download PDF
                                                                    </button>
                                                                </form>
                                                            </td>
                                                            <td align="center" valign="middle">
                                                                <form action="" method="post" enctype="multipart/form-data">
                                                                    <input name="id" type="hidden" value="<?php echo base64_encode((string)$data['id']); ?>" />
                                                                    <input name="delete" type="hidden" value="del" />
                                                                    <input style="padding-left:15px;" class="btn btn-danger" type="submit" value="Remove" onclick="return confirm('Delete this record?');"/>
                                                                </form>
                                                            </td>
                                                        </tr>
                                                <?php
                                                        $slno++;
                                                    }
                                                } else {
                                                ?>
                                                        <tr>
                                                            <td colspan="6" align="center" style="padding:40px 20px;color:#6b7280;">
                                                                <div style="display:flex;flex-direction:column;align-items:center;gap:12px;">
                                                                    <strong style="font-size:15px;">Abhi tak koi record nahi hai.</strong>
                                                                    <span style="font-size:13px;">Pehli baar Instant DL Pdf Server 3 generate karein.</span>
                                                                    <a href="dl_pdf_s3" class="btn btn-primary" style="margin-top:6px;border-radius:8px;background:linear-gradient(135deg,#2563eb 0%,#3b82f6 100%);border:none;">
                                                                        <i class="bi bi-plus-square me-1"></i> Generate New DL PDF
                                                                    </a>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                <?php
                                                }
                                                ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</div>
        </div>
    </div>
</div>

<?php
include('footer.php');
?>
<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<script src="../template/ahkweb/assets/js/jquery.min.js"></script>
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>
<script src="../template/ahkweb/assets/plugins/datatable/js/jquery.dataTables.min.js"></script>
<script src="../template/ahkweb/assets/plugins/datatable/js/dataTables.bootstrap5.min.js"></script>
</body>
</html>
