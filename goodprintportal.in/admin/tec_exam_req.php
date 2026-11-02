<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');
if(isset($_POST['name']) && !empty($_POST['name']) && !empty($_POST['password']) ){
    $name = mysqli_real_escape_string($ahk_conn,$_POST['name']);
    $password = mysqli_real_escape_string($ahk_conn,$_POST['password']);
 
    $application_no = "TEC". rand(00000000,99999999);
    $appliedby = $udata['phone'];
    $date = date("jS \of F Y h:i:s A");
    $price = mysqli_fetch_assoc(mysqli_query($ahk_conn,"SELECT * FROM pricing WHERE service_name='Tec_fee' "));
    if($udata['balance']>=$price['price']){
        // Upload Files
        $type = $_FILES['aadhaar']['type'];
        $type2 = $_FILES['declaration']['type'];
 
        // printR($_FILES);
          if($type == "application/pdf" || $type =="image/jpeg" || $type == "image/png" || $type == "image/jpg"&& 
         $type2 == "application/pdf" || $type2 =="image/jpeg" || $type2 == "image/png" || $type2 == "image/jpg"){
             
         $ext = rand(000000,999999).$_FILES['aadhaar']['name'];
         $ext2 = rand(000000,999999).$_FILES['declaration']['name'];
     
         $link = "https://" .$host.$dir."/uploads". "/".$ext;
         $link2 = "https://" .$host.$dir."/uploads". "/".$ext2;
    
        $upload = "uploads/";
        if(move_uploaded_file($_FILES['aadhaar']['tmp_name'],$upload.$ext) && move_uploaded_file($_FILES['declaration']['tmp_name'],$upload.$ext2)){
            $fee = $price['price'];
            $nbal = $udata['balance']-$price['price'];
            $debit = mysqli_query($ahk_conn,"UPDATE users SET balance=balance-$fee WHERE phone='$appliedby'");
            $updatehistory = mysqli_query($ahk_conn,"INSERT INTO `wallethistory`(`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES ('$appliedby','$fee','$nbal','Tec Exam Application','1','Debit')");
            if($debit && $updatehistory ){
            $submit = mysqli_query($ahk_conn,"INSERT INTO `tec_certificate` (`appliedby`, `application_no`, `name`, `password`, `status`, `fee`,`date`,`aadhaar_pdf`,`declaration_form`) VALUES('$appliedby','$application_no','$name','$password','pending','$fee','$date','$ext','$ext2')");
            if($submit == true){
               showAlert('Application Submitted','');
            }else{
                showAlert('Opps','Something Went Wrong','error');
            }
                }else{
                    showAlert('Opps','Something Went Wrong2','error');
                }
        }else{
         ?>
             <script>
                 $(function(){
                     Swal.fire(
                         'Error While Uploading Files!',
                         '',
                         'error'
                     );
                 });
             </script>
             <?php
        }
     }else{
         ?>
             <script>
                 $(function(){
                     Swal.fire(
                         'Wrong File Type!',
                         'PDF,JPEG,PNG Allowed only',
                         'error'
                     );
                 });
             </script>
             <?php
        }
        // Upload End
    }else{
        showAlert('Wallet Balance is Low!','Please Recharge Now!','error');
       }
    


}
?>

		<!--start page wrapper -->
		<div class="page-wrapper">
			<div class="page-content">
				<!--breadcrumb-->
				<div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
					<div class="ps-3">
						<nav aria-label="breadcrumb">
							<ol class="breadcrumb mb-0 p-0">
							
							</ol>
						</nav>
					</div>
					<div class="ms-auto">
						<div class="btn-group">
						
						
							<div class="dropdown-menu dropdown-menu-right dropdown-menu-lg-end">	<a class="dropdown-item" href="javascript:;">Action</a>
								<a class="dropdown-item" href="javascript:;">Another action</a>
								<a class="dropdown-item" href="javascript:;">Something else here</a>
								<div class="dropdown-divider"></div>	<a class="dropdown-item" href="javascript:;">Separated link</a>
							</div>
						</div>
					</div>
				</div>
				
					<!--end breadcrumb-->
					<div class="col-18">
                                <div class="bg-info p-2 mb-3 font-weight-bold">
                                    <div class="row">
                                        <h3><strong>TEC CERTIFICATE TAST EXAM </strong></h3>
                                        <h5>DELIVERY TIME - 02-48 HOUR</h5>
                                       <h5>Disclaimer :- CHARGE - <?php $price = mysqli_fetch_assoc(mysqli_query($ahk_conn,"SELECT * FROM pricing WHERE service_name='Tec_fee' ")); 
                                                                      echo $price['price']; ?>, Just</h5>
                                    </div>
                                </div>
                            </div>
						<div class="card border-top border-0 border-4 border-primary">
							<div class="card-body p-5">
								<div class="card-title d-flex align-items-center">
									<div><i class="fa-solid fa--id-card me-1 font-22 text-primary"></i>
									</div>
								</div>
                           <!--<h4> Note - Before applying the data, apply the data only after completing the assessment complaint. </h4>-->
								<hr>
								<form action="" method="POST" class="row g-3"  enctype="multipart/form-data">
									<div class="col-md-3">
										<label for="inputFirstName" class="form-label">Tec Application No <span style='color:red'>*</span></label>
										<input name="name" type="text"  placeholder="Application No " required class="form-control" >
									</div>
								 
									<!--Password-->
									
										<div class="col-md-3">
										<label for="inputFirstName" class="form-label">Enter Password <span style='color:red'>*</span></label>
										<input name="password" type="text"  placeholder="Enter Password " required class="form-control" >
									</div>
									
									<!--Upload Ducoments-->
                                
                                    <div class="col-md-3">
										<label for="inputFirstName" class="form-label">UPLOAD PHOTO <span style='color:red'>*</span></label>
										<input type="file" name="declaration" accept="Photo_pdf" required class="form-control" >
									</div>
									
									<div class="col-md-3">
										<label for="inputPassword" class="form-label">UPLOAD AADHAAR <span style='color:red'>*</span></label>
										<input  type="file" name="aadhaar" accept="aadhaar_pdf" required  class="form-control" >
								 
                                    <!--  -->
									
									</div>
										<div class="col-12">
										 <button class="btn btn-success" type="submit" onclick="return confirm('Are You Sure ?');">Submit</button>
										 	<!--<button class="btn btn-danger font-weight-bold" type="button">Service Down</button>-->
								<!--start list page -->
 		 
                          
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