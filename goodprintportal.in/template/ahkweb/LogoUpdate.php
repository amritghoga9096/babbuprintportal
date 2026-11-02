<?php
include('header.php');
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
                        <li class="breadcrumb-item active text-muted" aria-current="page">Update Logo</li>
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
				
						<div class="card border-top border-0 border-4 border-primary">
							<div class="card-body p-5">
                                <div>
                               
                                </div>
								<div class="card-title d-flex align-items-center">
									<div><i class="bx bxs-id-card me-1 font-22 text-primary"></i>
									</div>
									<h5 class="mb-0 text-primary">Update New Logo</h5>
									
								</div>
								<hr>
								<form action="" method="POST" class="row g-3"  enctype="multipart/form-data">
									
									
								
									<!--  -->
                                    <div class="col-12 ml-2">
                                    <div class="col-md-3">
										<label for="inputFirstName" class="form-label">Upload Logo</label>
										<input type="file" name="logo"  required class="form-control" >
                                        <input type="hidden" name="lg" value="true">
									</div>
                                    <div class="col-md-3">
										<img src="<?php ahkweb('logo'); ?>" width="150px" alt="">
									</div>
									</div>
                                    <!--  -->
									
								
									<div class="col-12">
										<button type="submit" class="btn btn-primary px-5">Update</button>
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


</html>