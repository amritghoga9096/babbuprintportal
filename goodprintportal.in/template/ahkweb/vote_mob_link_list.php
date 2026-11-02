
       

<?php include("header.php"); 
$s_phone=$udata['phone'];
$res = mysqli_query($ahk_conn, "SELECT * FROM `m_link` WHERE userid='$s_phone' ORDER BY `id` DESC");
?>
<!-- Modal for processing -->
<script>
    function myFunction() {
        $("#proc_modal").modal('show');
        document.f1.submit();
    }
</script>
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
                        <li class="breadcrumb-item active text-muted" aria-current="page">Voter Mobile Link List</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="vote_mob_link" class="btn btn-success">
                    <i class="bi bi-list-ul me-1"></i> Voter Mobile Link
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>
        <div class="card radius-10">
            <div class="card-body">
                <div class="d-flex align-items-center">
                  
                   
                </div>
                <hr><!-- Content section -->
<div class="content-wrap">
    <div class="main">
        <div class="col-md-12">
            <div class="main-content">
                <section class="section">
                    <div class="card-header bg-dark">
                        <div class="card-title">
                            <h3 style="color:white"><strong>Mobile Link List </strong></h3>
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
                                                    <th style="color:white">#</th>
                                                    <th style="color:white; text-align:center;">Epic No</th>
                                                    <th style="color:white; text-align:center;">Link Mobile</th>
                                                    <th style="color:white; text-align:center;">Status</th>
                                                    <th style="color:white; text-align:center;">Date</th>
                                                    <th style="color:white; text-align:center;">Delete</th>
                                                </tr>
                                            </thead>
                                            <!-- Table data -->
                                            <tbody>
                                                 <?php
                                                $sql = mysqli_query($ahk_conn, "SELECT * FROM m_link WHERE userid='$s_phone' order by id DESC");
                                                if (mysqli_num_rows($sql) > 0) {
                                                    $slno = 1;
                                                    while ($data = mysqli_fetch_assoc($sql)) {
                                                ?>
                                                        <tr>
                                                            <td><?php echo $slno; ?></td>
                                                            <td style="text-align:center;"><?php echo $data['epic_no']; ?></td>
                                                            <td style="text-align:center;"><?php echo $data['mobile_no']; ?></td>
                                                            <td style="text-align:center;"><?php echo $data['response']; ?></td>
                                                            <td style="text-align:center;"><?php echo $data['date']; ?></td>
                                                            <td align="center" valign="middle">
                  											<form action="action_m_link.php" method="post" enctype="multipart/form-data" >
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