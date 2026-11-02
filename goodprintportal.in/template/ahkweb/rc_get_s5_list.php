<?php include("header.php"); 
$s_phone=$udata['phone'];

@mysqli_report(MYSQLI_REPORT_OFF);
@mysqli_query($ahk_conn, "CREATE TABLE IF NOT EXISTS `rc_vehical_s5` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) DEFAULT NULL,
  `rc_vehical_no` varchar(100) DEFAULT NULL,
  `type` varchar(20) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `pdf` longtext DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `date` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_rc_s5_user` (`username`),
  KEY `idx_rc_s5_rc` (`rc_vehical_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$res = mysqli_query($ahk_conn, "SELECT * FROM `rc_vehical_s5` WHERE username='$s_phone' ORDER BY `id` DESC");

if(isset($_POST['delete']) && $_POST['delete'] == "del"){
    $id = base64_decode($_POST['id']);
    $del = mysqli_query($ahk_conn,"DELETE FROM `rc_vehical_s5` WHERE id='$id'");
    if($del){
        ?>
        <script>
            $(function(){
                Swal.fire(
                    'Success',
                    'Data Deleted Success!',
                    'success'
                )
            });
        </script>
        <?php
    }else{
        ?>
        <script>
            $(function(){
                Swal.fire(
                    'Opps',
                    'Data Not Deleted !',
                    'error'
                )
            });
        </script>
        <?php
    }
} 

$typeLabels = ['1'=>'Chip - 1','2'=>'Without Chip - 2','3'=>'Chip New - 3','4'=>'Without Chip New - 4'];
?>
<script>
    function myFunction() {
        $("#proc_modal").modal('show');
        document.f1.submit();
    }
</script>

<div class="modal fade" id="proc_modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false" aria-labelledby="exampleModalCenterLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <center>
                <img src="assets/images/settings.gif" width="100px" height="100px">
                <h6>Please wait. we are processing your request ...</h6>
            </center>
        </div>
    </div>
</div>

<style>
    .type-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 10px;
        font-size: 11.5px;
        font-weight: 700;
        letter-spacing: 0.2px;
    }
    .type-badge.t1 { background: #dbeafe; color: #1e40af; }
    .type-badge.t2 { background: #dcfce7; color: #166534; }
    .type-badge.t3 { background: #fef3c7; color: #92400e; }
    .type-badge.t4 { background: #f3e8ff; color: #6b21a8; }
</style>

<script>
    $(document).ready(() => {
        $('#default-datatable').DataTable({
            "order": [[ 0, "desc" ]],
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
                        <li class="breadcrumb-item active text-muted" aria-current="page">All Instant Rc Pdf S5 List</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group" role="group" aria-label="actions">
                <a href="rc_get_s5" class="btn btn-primary" style="border-radius:8px;background:linear-gradient(135deg,#2563eb 0%,#3b82f6 100%);border:none;">
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
                            <h3 style="color:#fff;margin:0;"><strong>Instant Rc Pdf S5 List</strong></h3>
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
                                                    <th style="color:white">RC Number</th>
                                                    <th style="color:white">Type</th>
                                                    <th style="color:white">Download Date</th>
                                                    <th style="color:white; text-align:center;">Download PDF</th>
                                                    <th style="color:white; text-align:center;">Delete</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                 <?php
                                                $sql = mysqli_query($ahk_conn, "SELECT * FROM rc_vehical_s5 WHERE username='$s_phone' order by id DESC");
                                                if (mysqli_num_rows($sql) > 0) {
                                                    $slno = 1;
                                                    while ($data = mysqli_fetch_assoc($sql)) {
                                                        $downloadName = !empty($data['file_name']) ? $data['file_name'] : ($data['rc_vehical_no'] . '.pdf');
                                                        $tv = !empty($data['type']) ? (string)$data['type'] : '1';
                                                        $tlabel = isset($typeLabels[$tv]) ? $typeLabels[$tv] : 'Type '.$tv;
                                                ?>
                                                        <tr>
                                                            <td><?php echo $slno; ?></td>
                                                            <td><strong style="font-family:'JetBrains Mono',monospace;letter-spacing:1px;"><?php echo $data['rc_vehical_no']; ?></strong></td>
                                                            <td><span class="type-badge t<?php echo $tv; ?>"><?php echo $tlabel; ?></span></td>
                                                            <td><?php echo $data['date']; ?></td>
                                                            <td align="center" valign="middle">
                                                                <form action="rc_s5_pdf_download.php" method="get" target="_blank" rel="noopener noreferrer" style="margin:0;padding:0;display:inline-block;">
                                                                    <input type="hidden" name="id" value="<?php echo (int)$data['id']; ?>">
                                                                    <button type="submit" style="padding:8px 12px;font-size:14px;font-weight:600;cursor:pointer;border:none;background-color:#3b82f6;color:#fff;border-radius:4px;box-shadow:0 4px 10px rgba(59,130,246,.2);display:inline-flex;align-items:center;gap:6px;"
                                                                            onmouseover="this.style.boxShadow='0 7px 14px rgba(59,130,246,.25)';this.style.transform='translateY(-1px)'"
                                                                            onmouseout="this.style.boxShadow='0 4px 10px rgba(59,130,246,.2)';this.style.transform=''">
                                                                        ⬇️ Download PDF
                                                                    </button>
                                                                </form>
                                                            </td>
                                                             
                                                            <td align="center" valign="middle">
                  											<form action="" method="post" enctype="multipart/form-data" >
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
                                                                    <svg width="56" height="56" fill="none" stroke="#9ca3af" stroke-width="1.5" viewBox="0 0 24 24"><path d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/><path d="M9 12h6"/></svg>
                                                                    <strong style="font-size:15px;">Abhi tak koi record nahi hai.</strong>
                                                                    <span style="font-size:13px;">Pehli baar Instant Rc Pdf S5 generate karein.</span>
                                                                    <a href="rc_get_s5" class="btn btn-primary" style="margin-top:6px;border-radius:8px;background:linear-gradient(135deg,#2563eb 0%,#3b82f6 100%);border:none;">
                                                                        <i class="bi bi-plus-square me-1"></i> Generate New RC S5
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

<script>
    $(document).ready(function() {
        if (window.ClipboardJS) new ClipboardJS('.btn');
    });
</script>
<?php 
include('footer.php');
?>
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
<script src="../template/ahkweb/assets/plugins/jquery-knob/excanvas.js"></script>
<script src="../template/ahkweb/assets/plugins/jquery-knob/jquery.knob.js"></script>
<script>
$(function() {
    if ($.fn.knob) $(".knob").knob();
});
</script>
<script src="../template/ahkweb/assets/js/index.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>
<script src="../template/ahkweb/assets/plugins/datatable/js/jquery.dataTables.min.js"></script>
	<script src="../template/ahkweb/assets/plugins/datatable/js/dataTables.bootstrap5.min.js"></script>
<script>
		$(document).ready(function() {
			if ($('#example').length) $('#example').DataTable();
		  } );
	</script>
	
	<script>
		$(document).ready(function() {
            if (!$('#example2').length) return;
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
