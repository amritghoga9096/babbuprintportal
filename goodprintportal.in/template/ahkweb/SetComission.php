<?php
include('header.php');
?>

		<!--start page wrapper -->
		<div class="page-wrapper">
			<div class="page-content">
				<!--breadcrumb-->
				<div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
					<div class="breadcrumb-title pe-3">Set Comission</div>
					<div class="ps-3">
						<nav aria-label="breadcrumb">
							<ol class="breadcrumb mb-0 p-0">
								<li class="breadcrumb-item"><a href="javascript:;"><i class="bx bx-home-alt"></i></a>
								</li>
								<li class="breadcrumb-item active" aria-current="page">Comission Setting</li>
							</ol>
						</nav>
					</div>
					
				</div>
				<!--end breadcrumb-->
               
				<div class="row">
					<div class="col-xl-10 mx-auto">
						<h6 class="mb-0 text-uppercase">Set Comission</h6>
						<hr/>
						<div class="card border-top border-0 border-4 border-primary">
							<div class="card-body p-5">
								<div class="card-title d-flex align-items-center">
									<div><i class="bx bxs-cog me-1 font-22 text-primary"></i>
									</div>
									<h5 class="mb-0 text-primary">Update Comission Details</h5>
								</div>
								<hr>
								<form action="" method="POST" class="row g-3">
									<div class="col-md-3">
										<label for="inputFirstName" class="form-label">Airtel Comission  ( LIKE 3%) </label>
										<input name="airtel_com" value="<?php  ahkweb('airtel_com'); ?>" type="text"  placeholder="3.5"  class="form-control" id="inputFirstName">
									</div>
									<div class="col-md-3">
										<label for="inputFirstName" class="form-label">Jio Comission ( LIKE 3%)</label>
										<input name="jio_com" value="<?php  ahkweb('jio_com');?>" type="text"  placeholder="3.5"  class="form-control" id="inputFirstName">
									</div>
									<div class="col-md-3">
										<label for="inputFirstName" class="form-label">BSNL Comission ( LIKE 3%)</label>
										<input name="bsnl_com" value="<?php  ahkweb('bsnl_com'); ?>" type="text"  placeholder="3.5"  class="form-control" id="inputFirstName">
									</div>
									<div class="col-md-3">
										<label for="inputFirstName" class="form-label">Vodafone Comission ( LIKE 3%)</label>
										<input name="vi_com" value="<?php ahkweb('vi_com');   ?>" type="text"  placeholder="3.0"  class="form-control" id="inputFirstName">
									</div>
									<div class="col-md-3">
										<label for="inputFirstName" class="form-label">Set Distributor Comission From Retailer Comission ( LIKE 30%)</label>
										<input name="dist_com" value="<?php  ahkweb('dist_com'); ?>" type="text"  placeholder="20"  class="form-control" id="inputFirstName">
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