<?php
include('header.php');
$message = '';
$userfound = false;
if(isset($_POST['username'])){
    $username = getSafe($_POST['username']);
    $sql = "SELECT * FROM users WHERE phone='$username' OR email='$username'";
    if(ahkRows($sql)==1){
        $userdata= fetchRow($sql);
        $userfound = true;
        $message = 'User Fetched Successfully! ';
    }else{
        $userfound = false;
        $message = 'No user Found with Associated This Email or Phone';
    }
    if(isset($_POST['username']) && $_POST['txn_type'] && $_POST['purpose'] && $_POST['amount'] && !empty($_POST['amount'])){
            $username = getSafe($_POST['username']);
            $txn_type = getSafe($_POST['txn_type']);
            $purpose = getSafe($_POST['purpose']);
            $amount = getSafe($_POST['amount']);
            if($txn_type == 'credit'){
                $bal = $userdata['balance']+$amount;
                $sql = "UPDATE users SET balance=balance+$amount WHERE phone='$username' OR email='$username'";
                $sql2 = "INSERT INTO `wallethistory`(`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES ('".$userdata['phone']."','$amount','$bal','$purpose','1','$txn_type')";
                if(ahkQuery($sql) && ahkQuery($sql2)){
                    // MANUAL CREDIT by Admin: referral bonus on first deposit (idempotent)
                    if (function_exists('gpGiveReferralBonusIfEligible')) {
                        gpGiveReferralBonusIfEligible($userdata['phone'], $GLOBALS['ahk_conn'] ?? null);
                    }
                    ?>
                    <script>
                        $(function(){
                            Swal.fire(
                                'Great',
                                'Amount Credited Successfully!',
                                'success'
                            )
                        })
                    </script>
                    <?php
                    ahkRedirect('',1200);
                }else{
                    ?>
                    <script>
                        $(function(){
                            Swal.fire(
                                'Huuh',
                                'Transaction Could Not Be Completed!',
                                'error'
                            )
                        })
                    </script>
                    <?php
                    ahkRedirect('',1200);
                }
                
            }else if($txn_type == 'debit'){
                $bal = $userdata['balance']+$amount;
                $sql = "UPDATE users SET balance=balance-$amount WHERE phone='$username' OR email='$username'";
                $sql2 = "INSERT INTO `wallethistory`(`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES ('".$userdata['phone']."','$amount','$bal','$purpose','1','$txn_type')";
                if(ahkQuery($sql) && ahkQuery($sql2)){
                    ?>
                    <script>
                        $(function(){
                            Swal.fire(
                                'Great',
                                'Amount Debited Successfully!',
                                'success'
                            )
                        })
                    </script>
                    <?php
                    ahkRedirect('',1200);
                }else{
                    ?>
                    <script>
                        $(function(){
                            Swal.fire(
                                'Huuh',
                                'Transaction Could Not Be Completed!',
                                'error'
                            )
                        })
                    </script>
                    <?php
                    ahkRedirect('',1200);
                }
            }
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
                        <li class="breadcrumb-item active text-muted" aria-current="page">Balance Transfer</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
               
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

				<!--end breadcrumb-->
                <?php 
                if($userfound === false){
                    ?>
                    <div class="row">
					<div class="col-xl-10 mx-auto">
						<h6 class="mb-0 text-uppercase">Beneficiary Details</h6>
						<hr/>
						<div class="card border-top border-0 border-4 border-primary">
							<div class="card-body p-5">
                            <?php 
                                        if($message != ''){
                                            ?>
                                            <h5 class="mb-0 text-danger"><?php echo $message; ?></h5><br>
                                            <?php 
                                        }
                                    ?>
								<div class="card-title d-flex align-items-center">
									<div><i class="bx bxs-user me-1 font-22 text-primary"></i>
									</div>
                                   
									<h5 class="mb-0 text-primary">Enter Details</h5>
								</div>
								<hr>
								<form action="" method="POST" class="row g-3">
									<div class="col-md-3">
										<label for="inputFirstName" class="form-label"> Email or Phone</label>
										<input name="username"  type="text"  placeholder="Email or Phone"  class="form-control" id="inputFirstName">
									</div>
									<div class="col-12">
										<button type="submit" class="btn btn-primary px-5">Fetch Details</button>
									</div>
								</form>
							</div>
						</div>
					</div>
				</div>
                    <?php 
                }
                


                // Start User Found Details 
                if($userfound === true){
                    ?>
                    <div class="row">
					<div class="col-xl-10 mx-auto">
						<h6 class="mb-0 text-uppercase">Beneficiary Details</h6>
						<hr/>
						<div class="card border-top border-0 border-4 border-primary">
							<div class="card-body p-5">
                            <?php 
                                if($message != ''){
                                    ?>
                                    <h5 class="mb-0 text-success"><?php echo $message; ?></h5><br>
                                    <?php 
                                }
                                ?>
								<div class="card-title d-flex align-items-center">
									<div><i class="bx bxs-user me-1 font-22 text-primary"></i>
									</div>
                                   
									<h5 class="mb-0 text-primary">Check Payment Details</h5>
								</div>
								<hr>
								<form action="" method="POST" class="row g-3">
									<div class="col-md-3">
										<label for="inputFirstName" class="form-label"> Email or Phone</label>
										<input name="username" value="<?php echo $_POST['username'];?>" type="text" readonly  placeholder="Email or Phone"  class="form-control" id="inputFirstName">
									</div>
									<div class="col-md-3">
										<label for="inputFirstName" class="form-label">Customer Name</label>
										<input name="name" value="<?php echo $userdata['name'];?>"  type="text"  readonly placeholder="Customer Name"  class="form-control" id="inputFirstName">
									</div>
									<div class="col-md-3">
										<label for="inputFirstName" class="form-label">Currect Balance</label>
										<input name="balance" value="<?php echo $userdata['balance'];?>"  type="text"  readonly placeholder="Customer balance"  class="form-control" id="inputFirstName">
									</div>
                                    <br>
									<div class="col-md-3">
										<label for="inputFirstName" class="form-label">Select Transaction Type</label>
										<select class="form-control" name="txn_type">
                                            <option value="">Please Select</option>
                                            <option value="credit">Credit</option>
                                            <option value="debit">Debit</option>
                                        </select>
									</div>
									<div class="col-md-3">
										<label for="inputFirstName" class="form-label">Select Payment Purpose</label>
										<select class="form-control" name="purpose">
                                            <option value="">Please Select</option>
                                            <option value="deposit">Deposit</option>
                                            <option value="refund">Refund</option>
                                            <option value="penalty">Penalty (for Debit)</option>
                                            <option value="other">Other</option>
                                        </select>
									</div>
                                    <div class="col-md-3">
										<label for="inputFirstName" class="form-label">Enter Amount</label>
										<input name="amount"  type="text"  placeholder="TXN Amount"  class="form-control" id="inputFirstName">
									</div>

									<div class="col-12">
										<button type="submit" class="btn btn-primary px-5">Submit</button>
									</div>
								</form>
							</div>
						</div>
					</div>
				</div>
                    <?php
                }
                ?>
				
				
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
	// $(document).ready(function() {
	
	// $('#eid').inputmask();
	// $('#date').inputmask();
	// $('#timea').inputmask("hh:mm:ss", {
    //     placeholder: "00:00:00", 
    //     insertMode: false, 
    //     showMaskOnHover: false,
    //     hourFormat: 12
    //   });
	// });
</script>
</html>