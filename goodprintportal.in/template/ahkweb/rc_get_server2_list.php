

<?php include("header.php"); 
$s_phone=$udata['phone'];
$res = mysqli_query($ahk_conn, "SELECT * FROM `rc_vehical_server2` WHERE username='$s_phone' ORDER BY `id` DESC");
if(isset($_POST['delete']) && $_POST['delete'] == "del"){
    $id = base64_decode($_POST['id']);
    $del = mysqli_query($ahk_conn,"DELETE FROM `rc_vehical_server2` WHERE id='$id'");
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
</style>

<script></script>
<script>
    $(document).ready(() => {
        $('#default-datatable').DataTable();
    });
</script>

<div class="page-wrapper">
    <div class="page-content">
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">History</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">All Instant RC PDF Server 2 List</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group" role="group" aria-label="actions">
                <a href="rc_get_server2" class="btn btn-primary" style="border-radius:8px;">
                    <i class="bi bi-plus-square me-1"></i> + Generate New
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-primary" style="border-radius:8px;">
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
                    <div class="card-header bg-success">
                        <div class="card-title">
                            <h3><strong>RC PDF Server 2 List</strong></h3>
                        <h3><strong>Instant RC PDF Server 2 Print List</strong></h3>
                    </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table id="default-datatable" class="table table-bordered" width="100%">
                                            <thead style="color:white;background:linear-gradient(-62deg, #065F46, #10b981);">
                                                <tr>
                                                    <th style="color:white; text-align:center;">#</th>
                                                    <th style="color:white">RC Number</th>
                                                    <th style="color:white">Download Date</th>
                                                    <th style="color:white; text-align:center;">Download PDF</th>
                                                    <th style="color:white; text-align:center;">Delete</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                 <?php
                                                $sql = mysqli_query($ahk_conn, "SELECT * FROM rc_vehical_server2 WHERE username='$s_phone' order by id DESC");
                                                if (mysqli_num_rows($sql) > 0) {
                                                    $slno = 1;
                                                    while ($data = mysqli_fetch_assoc($sql)) {
                                                        $downloadName = !empty($data['file_name']) ? $data['file_name'] : ($data['rc_vehical_no'] . '.pdf');
                                                ?>
                                                        <tr>
                                                            <td><?php echo $slno; ?></td>
                                                            <td><?php echo $data['rc_vehical_no']; ?></td>
                                                            <td><?php echo $data['date']; ?></td>
                                                            <td align="center" valign="middle">
                                                                <form action="rc_server2_pdf_download.php" method="get" target="_blank" rel="noopener noreferrer" style="margin:0;padding:0;display:inline-block;">
                                                                    <input type="hidden" name="id" value="<?php echo (int)$data['id']; ?>">
                                                                    <button type="submit" style="padding:8px 12px;font-size:14px;font-weight:600;cursor:pointer;border:none;background-color:#10b981;color:#fff;border-radius:4px;box-shadow:0 4px 10px rgba(16,185,129,.2);display:inline-flex;align-items:center;gap:6px;"
                                                                            onmouseover="this.style.boxShadow='0 7px 14px rgba(16,185,129,.25)';this.style.transform='translateY(-1px)'"
                                                                            onmouseout="this.style.boxShadow='0 4px 10px rgba(16,185,129,.2)';this.style.transform=''">
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
                                                            <td colspan="5" align="center" style="padding:30px;color:#6b7280;">
                                                                <strong>Abhi tak koi record nahi hai. Pehli baar RC PDF Server 2 generate karein.</strong>
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
        new ClipboardJS('.btn');
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
    $(".knob").knob();
});
</script>
<script src="../template/ahkweb/assets/js/index.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>
<script src="../template/ahkweb/assets/plugins/datatable/js/jquery.dataTables.min.js"></script>
	<script src="../template/ahkweb/assets/plugins/datatable/js/dataTables.bootstrap5.min.js"></script>
<script>
		$(document).ready(function() {
			$('#example').DataTable();
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
