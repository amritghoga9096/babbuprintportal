 <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
  <title>DL All List | Recharge Pay Web Official</title>

<?php 
	include('header.php');
   

$s_phone=$udata['phone'];
$res = mysqli_query($ahk_conn, "SELECT * FROM `dlprint2` WHERE username='$s_phone' ORDER BY `id` DESC");
if(isset($_POST['delete']) && $_POST['delete'] == "del"){
    $id = base64_decode($_POST['id']);
    $del = mysqli_query($ahk_conn,"DELETE FROM `dlprint2` WHERE id='$id'");
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
<!-- Modal for processing -->
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


<!-- Styling -->
<style>
    /* Your styles go here */
</style>

<!-- Datatable initialization -->
<script>
    $(document).ready(() => {
        $('#default-datatable').DataTable();
    });
</script>
<!--start page wrapper -->
<div class="page-wrapper">
    <div class="page-content">
        <!-- Breadcrumb with Action Buttons -->
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">History</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">All Blue Driving Licence List</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="DL_Instant_flx_blue" class="btn btn-success">
                    <i class="bi bi-list-ul me-1"></i>Blue Driving Licence
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>
        <div class="card radius-10">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div>
                        <h5 class="mb-0">All DL INSTANT LIST</h5>
                    </div>
                   
                </div>
                <hr>
                                    <div class="table-responsive">
                                        <table id="default-datatable" class="table table-bordered" width="100%">
                                            <!-- Table headers -->
                                            <thead style="color:white;background:black;">
                                                <tr>
                                                    <th style="color:white">#</th>
                                                    <th style="color:white">Application No</th>
                                                    <th style="color:white">Dl No</th>
                                                    <th style="color:white">DOB / Time</th>
                                                   
                                                    <th style="color:white">Download Pdf</th>
                                                    <?php if(checkAdmin($udata['type']) == false){?>
                                                    <th style="color:white">Delete</th>
                                                    <?php } ?>
                                                </tr>
                                            </thead>
                                            <!-- Table data -->
                                            <tbody>
                                                 <?php
                                                if($_SESSION['phone']==1)
								    
								                 $sql = mysqli_query($ahk_conn, "SELECT * FROM dlprint2 order by id desc");
							                 	else
                                                $sql = mysqli_query($ahk_conn, "SELECT * FROM dlprint2 WHERE username='$s_phone' order by id DESC");
                                                if (mysqli_num_rows($sql) > 0) {
                                                    $slno = 1;
                                                    while ($data = mysqli_fetch_assoc($sql)) {
                                                ?>
                                                        <tr>
                                                            <td><?php echo $slno; ?></td>
                                                            <td><?php echo $data['application_no']; ?></td>
                                                            <td><?php echo $data['dl_no']; ?></td>
                                                            <td>DOB: <?php echo $data['dob']; ?><br> Download Date: <?php echo $data['created_at']; ?></td>
                                                          
                                                            <td align="center" valign="middle">
                                                                <a href="<?php echo $data['pdf']; ?>" download="<?php echo $data['dl']; ?>.pdf"style="text-decoration: none;">
                                                                <button style="padding: 8px 12px; font-size: 14px; cursor: pointer; border: none; background-color: #007BFF; color: white; border-radius: 4px;">
                                                                    ⬇️ Download PDF
                                                                </button></a>
                                                            </td >
                                                          <?php if(checkAdmin($udata['type']) == false){?>
                                                            <td align="center" valign="middle">
                  											<form action="action_DL_Instant_pdf.php" method="post" enctype="multipart/form-data" >
                  												<input name="id" type="hidden" value="<?=$data['id']?>" />
                  												<input style="padding-left:15px;" class="btn btn-danger" type="submit" value="Remove" />
                  											</form>
								                		</td>
								                		<?php } ?>
                                                        </tr>
                                                <?php
                                                        $slno++;
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
                </section>
            </div>
        </div>
    </div>
</div>

<!-- ClipboardJS initialization -->
<script>
    $(document).ready(function() {
        new ClipboardJS('.btn');
    });
</script>

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