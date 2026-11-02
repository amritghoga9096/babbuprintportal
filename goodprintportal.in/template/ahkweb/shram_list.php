<?php include("header.php"); 
$s_phone=$udata['phone'];
$res = mysqli_query($ahk_conn, "SELECT * FROM `shram_ax` WHERE username='$s_phone' ORDER BY `id` DESC");
if(isset($_POST['delete']) && $_POST['delete'] == "del"){
    $id = base64_decode($_POST['id']);
    $del = mysqli_query($ahk_conn,"DELETE FROM `shram_ax` WHERE id='$id'");
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
        <div class="card radius-10">
<!-- Content section -->
<div class="content-wrap">
    <div class="main">
        <div class="col-md-12">
            <div class="main-content">
                <section class="section">
                    <div class="card-header bg-danger">
                        <div class="card-title">
                            <h3><strong>e-Sharm Print List </strong></h3>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table id="default-datatable" class="table table-bordered" width="100%">
                                            <!-- Table headers -->
                                            <thead style="color:white;background:linear-gradient(-62deg, #3a3a53, #03a9f4);">
                                                <tr>
                                                    <th style="color:white; text-align:center;">#</th>
                                                    <th style="color:white">Card No</th>
                                                    <th style="color:white">Download Date</th>
                                                    <th style="color:white; text-align:center;">Download PDF</th>
                                                    <th style="color:white; text-align:center;">Delete</th>
                                                </tr>
                                            </thead>
                                            <!-- Table data -->
                                            <tbody>
                                                 <?php
                                                $sql = mysqli_query($ahk_conn, "SELECT * FROM shram_ax WHERE username='$s_phone' order by id DESC");
                                                if (mysqli_num_rows($sql) > 0) {
                                                    $slno = 1;
                                                    while ($data = mysqli_fetch_assoc($sql)) {
                                                ?>
                                                        <tr>
                                                            <td><?php echo $slno; ?></td>
                                                            <td><?php echo $data['aadhaar_no']; ?></td>
                                                            <td><?php echo $data['date']; ?></td>
                                                            <td align="center" valign="middle">
                                                                <a href="<?php echo $data['pdf']; ?>" download="<?php echo $data['aadhaar_no']; ?>.pdf"><img src="../template/ahkweb/pdf_doc.jpg" width="50" height="50" /></a>
                                                            </td>
                                                             
                                                            <td align="center" valign="middle">
                  											<form action="action_sharm.php" method="post" enctype="multipart/form-data" >
                  												<input name="id" type="hidden" value="<?=$data['id']?>" />
                  												<input style="padding-left:15px;" class="btn btn-danger" type="submit" value="Remove" />
                  											</form>
								                		</td>
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