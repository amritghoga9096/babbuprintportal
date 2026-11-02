<?php
include('header.php');
if(isset($_GET['id']) && $_GET['id']!=NULL){
    $id = base64_decode(getSafe($_GET['id']));
    $data = fetchRow("SELECT * FROM users WHERE id='$id'");

}
if(isset($_POST['name']) && $_POST['phone']){
    $id = getSafe($_POST['id']);
    $name = getSafe($_POST['name']);
    $phone = getSafe($_POST['phone']);
    $email = getSafe($_POST['email']);
    $type = getSafe($_POST['type']);
    if(ahkQuery("UPDATE users SET name='$name', phone='$phone',email='$email', type='$type' WHERE id='$id'")){
        showAlert('User Data Saved!');
        ahkRedirect('',1200);
    }else{
        showAlert('User Not Updated!','','error');
    }
}
?>

		<!--start page wrapper -->
		<div class="page-wrapper">
			<div class="page-content">
				<!--breadcrumb-->
				<div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
					<div class="breadcrumb-title pe-3">Edit User</div>
					<div class="ps-3">
						<nav aria-label="breadcrumb">
							<ol class="breadcrumb mb-0 p-0">
								<li class="breadcrumb-item"><a href="javascript:;"><i class="bx bx-home-alt"></i></a>
								</li>
								<li class="breadcrumb-item active" aria-current="page">Edit user</li>
							</ol>
						</nav>
					</div>
				</div>
				<!--end breadcrumb-->
                
				<div class="row">
					<div class="col-xl-10 mx-auto">
						<h6 class="mb-0 text-uppercase">Edit user data</h6>
						<hr/>
						<div class="card border-top border-0 border-4 border-primary">
							<div class="card-body p-5">
								<div class="card-title d-flex align-items-center">
									<div><i class="bx bxs-user me-1 font-22 text-primary"></i>
									</div>
									<h5 class="mb-0 text-primary">user Details</h5>
								</div>
								<hr>
								<form action="" method="POST" class="row g-3">
									<div class="col-md-3">
										<label for="inputFirstName" class="form-label">Name</label>
										<input name="name"  type="text" value="<?php echo $data['name'] ;?>" placeholder="Name"  class="form-control" id="inputFirstName">
									</div>
									
										<input name="id"  type="hidden" value="<?php echo $data['id'] ;?>" >
									
									<div class="col-md-3">
										<label for="inputFirstName" class="form-label">Phone (It Will be Username)</label>
										<input name="phone"  type="text" value="<?php echo $data['phone'] ;?>"  placeholder="Phone"  class="form-control" id="inputFirstName">
									</div>
									<div class="col-md-3">
										<label for="inputFirstName" class="form-label">Email</label>
										<input name="email"  type="email" value="<?php echo $data['email'] ;?>"  placeholder="Enter Email"  class="form-control" id="inputFirstName">
									</div>
									<div class="col-md-3">
										<label for="inputFirstName" class="form-label">Usertype</label>
										<select class="form-control" name="type" id="">
                                            <option value="">Select User Type</option>
                                            <option <?php if($data['type']=='retailer'){echo 'selected';} ?> value="retailer">Retailer</option>
                                            <option <?php if($data['type']=='distributor'){echo 'selected';} ?> value="distributor">Distributor</option>
                                        </select>
									</div>
									
									<div class="col-12">
										<button type="submit" class="btn btn-primary px-5">Save</button>
									</div>
								</form>
							</div>
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