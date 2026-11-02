<?php
include('header.php');

if (isset($_POST['name'])) {
    $name = trim($_POST['name']);
    $account = trim($_POST['account']);
    $cif = trim($_POST['cif']);
    $ifsc = trim($_POST['ifsc']);
    $refrence = "6895 5486 5804 " . rand(0000, 9999);
    $userid = $_SESSION['userid'];
    $mobile = $rw['mobileno'];
    $email = $rw['emailid'];
    date_default_timezone_set("Asia/Kolkata");
    $timestamp = date('d/m/Y g:i:s');
    $price = mysqli_fetch_assoc(mysqli_query($ahk_conn,"SELECT * FROM pricing WHERE service_name='Ippb_pass_fee' "));
    $fee = $price['price'];
    $appliedby = $udata['phone'];
    if($udata['balance']>=$price['price'])

  

   $fee = $price['price'];
        $debit = mysqli_query($ahk_conn,"UPDATE users SET balance=balance-$fee WHERE phone='$appliedby'");
        if($debit){
        $q = "INSERT INTO `postpass`(`name`,`account`, `cif`, `ifsc`, `refrence`, `userid`,`mobile`,`email`, `date`, `status`, `payment_status`) VALUES ('$name','$account','$cif','$ifsc','$refrence','$userid','$mobile','$email','$timestamp','Generated',1)";
        $summary = mysqli_query($ahk_conn, "INSERT INTO `card_summary`(`name`, `number`, `status`, `fee`,`type`,`old_balance`,`new_balance`, `date`, `userid`) VALUES ('$name','$account','IPPB Passbook P','$fixed_amount_to_deduct','D','$balance','$debit_fee','$timestamp','$userid')");

        $res = mysqli_query($ahk_conn, $q);

        if ($res) { 
            $msgno = 1;
            $msg = "Application Submitted Successfully!";
            echo "<script> alert('$msg') </script>";
            echo "<script> setTimeout(() => { window.location.href='Ippb_pass_list.php'; }, 200); </script>";
        } else {
            $msg = 0;
            echo "<script> alert('Record Rejected.') </script>";
        }
    } else {
        echo "Error updating balance: " . mysqli_error($ahk_conn);
    }
}
?>

		<!--start page wrapper -->
	<div class="page-wrapper">
    <div class="page-content">
        <!-- Breadcrumb with Action Buttons -->
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">Services</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">IPPB Passbook</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="Ippb_pass_list" class="btn btn-success">
                    <i class="bi bi-list-ul me-1"></i>Print List
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>
			
				<div class="row">
					<div class="col-xl-10 mx-auto">
						<h6 class="mb-0 text-uppercase">India Post Payment Bank Passbook !!!</h6>
						<hr/>
						<div class="card border-top border-0 border-4 border-primary">
							<div class="card-body p-5">
								<div class="card-title d-flex align-items-center">
									<div><i class="bx bxs-id-card me-1 font-22 text-primary"></i>
									</div>
					
								 

						<!-- /# row -->
						<section id="main-content">
                       <?php if($msgno==1){ ?><div style="width=100%"  class="row cvmsgok"><?php echo $msg; ?></div>
                       <script>
                       
                       setTimeout(()=>{
                            window.location.href="Ippb_pass_list.php";
                       },200);</script>
                       <?php } ?>
							<div class="row  dgnform">
                           
                           <div class="col-sm-12">
                           <form action="" method="post">  
                           <div class="row">
                         
                                                    <div class="col-sm-3">
                                                        <label>Full Name</label>
                                                        <div class="form-group">
                                                            <input class="form-control " value="" id="name" placeholder="Full Name" autocomplete="off" name="name" type="text" maxlength="40" required="">
                                                            <input type="hidden" name="photo" id="photo" required>
                                                            <span id="erroraadharno" class="error"></span>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-3">
                                                        <label>Account No </label>
                                                        <div class="form-group">
                                                            <input class="form-control" name="account" id="account" placeholder="Account No " value="" type="number" maxlength="12" required>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-3">
                                                        <label>IFSC Code</label>
                                                        <div class="form-group">
                                                            <input class="form-control" name="ifsc" id="ifsc"  value="IPOS0000001" type="text" required>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-3">
                                                        <label>Customer ID</label>
                                                        <div class="form-group">
                                                            <input class="form-control" name="cif" id="cif" placeholder="CIF NUMBER" value="" type="number" maxlength="10"required>
                                                        </div>
                                                    </div>
                                                            <div class="col-sm-2">
                                                                <label> </label>
                                                               <div class="form-group">
                                                                   <button class="form-control btn btn-success  ">Submit</button>
                                                               </div>
                                                               	<div class="col-12 ml-2">
									<h5 class="text-warning ">Application Fee: <?php  
										$price = mysqli_fetch_assoc(mysqli_query($ahk_conn,"SELECT price FROM pricing WHERE service_name='Ippb_pass_fee'")); 
										echo "₹" .$price['price'];
										?></h5>
										<input type="hidden" name="fee" value="<?php echo $price['price'];  ?>">
                                                            </div>
												         </div>
                                                      </div>
                                                 </form>
							                  </div>
                            </section>
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
	<!-- <script src="../template/ahkweb/assets/js/jquery.min.js"></script> -->
	<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
	<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
	<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
	<!--app JS-->
	<script src="../template/ahkweb/assets/js/app.js"></script>
</body>

<script>
	$(document).ready(function() {
	
	$('#eid').inputmask();
	$('#date').inputmask();
	$('#pan_no').inputmask();
	$('#timea').inputmask("hh:mm:ss", {
        placeholder: "00:00:00", 
        insertMode: false, 
        showMaskOnHover: false,
        hourFormat: 12
      });
	});
</script>
</html>