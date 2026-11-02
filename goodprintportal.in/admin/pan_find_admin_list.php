<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');
if(checkAdmin($udata['type']) == false){
    ?>
    <script>
        window.location='index';
    </script>
    <?php
    die();
}
   ?>
<!--start page wrapper -->
<div class="page-wrapper">
    <div class="page-content">
        <div class="card radius-10">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div>
                        <h5 class="mb-0">All Pan No Find Admin List </h5>
                    </div>
                   
                </div>
                <hr>
                <div class="table-responsive">
                    <table id="example2" class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center">SL.</th>
                                 <th class="text-center">Username</th>
                                <th class="text-center">Application No</th>
                                <th class="text-center">Apply date</th>
                                <th class="text-center">Aadhar No</th>
                                <th class="text-center">Pan No</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                           
<?php
$res = mysqli_query($ahk_conn,"SELECT * FROM instantpan_find   ORDER BY id DESC");
if(mysqli_num_rows($res)>0){
    $x=0;
    while($data = mysqli_fetch_assoc($res)){
        $x ++;
        ?>
        <tr>
            <td class="text-center"><?= $x;?></td>
              <td>
                                         <span class="badge rounded-pill bg-light text-dark">
                    <strong><?php echo  $data['username']; ?></strong>
                    </span> </td>
            <td>
                                         <span class="badge rounded-pill bg-light text-dark">
                    <strong><?php echo  $data['application_no']; ?></strong>
                    </span> </td>
            
            <td class="text-center"><?php echo strtoupper($data['date']); ?></td>
           <td>
                                         <span class="badge rounded-pill bg-light text-primary">
                    <strong>Aadhaar No :<?php echo  $data['aadhaar_no']; ?> 
                
                
                    </strong>
                    </span>
                        <br> </br>
                      <span class="badge rounded-pill bg-light text-success">
                    <strong>Pan no :<?php echo  $data['pan_no']; ?></strong>
                    </span></td>
            
            <td class="text-center">
            <strong><?php echo strtoupper($data['pan_no']); ?></strong>
               
            </td>
             <td class="text-center">
        <div class="badge rounded-pill bg-light-success text-success w-100">Success
                        </div>
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