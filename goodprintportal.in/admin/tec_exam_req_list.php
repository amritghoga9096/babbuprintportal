<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

   ?>
<!--start page wrapper -->
<div class="page-wrapper">
    <div class="page-content">
        <div class="card radius-10">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div>
                        <h5 class="mb-0">ALL TEC REQUEST LIST</h5>
                    </div>
                   
                </div>
                <hr>
                <div class="table-responsive">
                    <table id="example2" class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center"># </th>
                                <th class="text-center">Application No </th>
                                <th class="text-center">Password</th>
                         
                                <th class="text-center">Exam Date </th>
                                <th class="text-center">Fee </th>
                                <th class="text-center">Stutas </th>
                                <th class="text-center">Result </th>
                            </tr>
                        </thead>
                        <tbody>
 
                           
<?php
if(isset($_GET['apid'])&& $_GET['track'] =='true'){
    $apid = base64_decode(getSafe($_GET['apid']));
    $application_no = getSafe($_GET['apid']); 
        $api_key = $webdata['nsdl_api_key']; 

        $url = 'https:///api/v1/services/aadhaar_pdf/track_pdf.php?application_no='.$application_no.'&api_key='.$api_key;
        $curl = curl_init();
        curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        $response;
        $resdata = json_decode($response,true);
        if($resdata['status'] == '1'){
            if(ahkQuery("UPDATE rasan_card SET remark='".$resdata['remark']."', status='success', pdf_link='".$resdata['pdf_link']."'  WHERE application_no='$apid' ")){
                showAlert('Aadhaar PDF generated', $resdata['remark'],'success');
            }else{
                showAlert('Something Went Wrong','','error');
            }
        }else{
            showAlert($resdata['status'], $resdata['message'],'error');
        }
}
$res = mysqli_query($ahk_conn,"SELECT * FROM 	tec_certificate WHERE appliedby='".$udata['phone']."'  ORDER BY id DESC");
if(mysqli_num_rows($res)>0){
    $x=0;
    while($data = mysqli_fetch_assoc($res)){
        $x ++;
        ?>
        <tr>
            <td class="text-center"><?= $x;?></td>
            <td class="text-center">
                <div class="d-flex align-items-center">
                    
                    <div class="ms-2">
                        <h6 class="mb-1 font-14"><?php echo strtoupper($data['name']); ?></h6>
                    </div>
                </div>
            </td>
           
            <td class="text-center"><?php echo strtoupper($data['password']); ?></td>
            <td class="text-center"><?php echo strtoupper($data['date']); ?></td>
            <td class="text-center"><?php echo strtoupper($data['fee']); ?></td>
            <td class="text-center"><?php
            if(!$data['remark'] ==NULL){
                ?>
                <?php echo strtoupper($data['remark']); ?>
                <?php
            }else{
                echo '<span class="badge rounded-pill bg-light-warning text-warning w-100">Pending...</span>';
            }
            ?></td>
            <td class="text-center">
                <?php
                    if($data['status']=="pending"){
                        ?>
                        <div class="badge rounded-pill bg-light-warning text-warning w-100"> 
                        </div>
                        <?php
                    }else if($data['status'] =="success" ){
                            ?>
                            <div class="text-center">
                                <a download="<?php echo $data['success'] ?> " href="<?php echo $data['pdf_link'] ?>" class="btn btn-sm btn-success">View Certificate</a>
                            </div>
                            <?php
                    }
                ?>
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