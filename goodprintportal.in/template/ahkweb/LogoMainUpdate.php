<?php
include('header.php');
?>

		<!--start page wrapper -->
	<div class="page-wrapper">
    <div class="page-content">
        <!-- Breadcrumb with Action Buttons -->
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">Website Setting</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">Main Logo Update</li>
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

                <!-- Info Banner -->
                <div class="alert alert-info mb-4 radius-10 border-start border-info border-4 d-flex align-items-start gap-3" role="alert">
                    <i class="bx bxs-info-circle font-24 text-info"></i>
                    <div>
                        <strong>Note:</strong> Ye <b>Main Logo</b> sirf <strong>2 jagah</strong> ka logo change karega (baaki sab pages ka logo asli wahi rahega):
                        <ul class="mb-0 mt-1 ps-3">
                            <li>✅ Homepage / Landing Page: <code>https://goodprintportal.in/</code> — Header logo + Hero (hero image</li>
                            <li>✅ Login / Auth Page: <code>https://goodprintportal.in/auth</code> — Top logo area</li>
                            <li>❌ Admin panel / Profile / Favicons / Register — unchanged (wo Update Logo wale page se control honge)</li>
                        </ul>
                    </div>
                </div>

						<div class="card border-top border-0 border-4 border-5 border-info">
							<div class="card-body p-5">
								<div class="card-title d-flex align-items-center">
									<div><i class="bx bxs-landscape me-1 font-22 text-info"></i>
									</div>
									<h5 class="mb-0 text-info">Main Logo Update</h5>
								</div>
								<hr>

                                <div class="row mb-4">
                                    <div class="col-12 d-flex align-items-center gap-3 flex-wrap">
                                        <h6 class="mb-0 text-dark fw-bold" style="width:180px;">Current Main Logo:</h6>
                                        <div class="d-flex align-items-center gap-3 flex-wrap">
                                            <div style="padding:14px 20px; border-radius:12px; background:#f8fafc; border:2px dashed #cbd5e1;">
                                                <img src="/logomain.png" alt="Current Main Logo" style="max-width:180px; max-height:60px; object-fit:contain;" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-flex';">
                                                <div style="display:none; align-items:center; justify-content:center; width:180px; height:60px; border-radius:8px; background:linear-gradient(135deg,#2a2469,#4f46e5); color:#fff; font-weight:800; font-family:'Nunito',sans-serif; font-size:20px; letter-spacing:0.5px;">
                                                    MAIN LOGO
                                                </div>
                                            </div>
                                            <span class="text-muted small"><i class="bx bx-link"></i> File path: <code>/logomain.png</code> (public_html root)</span>
                                        </div>
                                    </div>
                                </div>
								<form action="" method="POST" class="row g-3"  enctype="multipart/form-data">

									<div class="col-12 ml-2">
                                    <div class="col-md-3">
										<label for="inputFirstName" class="form-label">Upload Main Logo (JPG / PNG / GIF / WEBP)</label>
										<input type="file" name="mainlogo"  required class="form-control" accept="image/png,image/jpeg,image/jpg,image/gif,image/webp" >
                                        <input type="hidden" name="mlg" value="true">
                                        <div class="form-text text-muted mt-1">Recommended: Transparent PNG recommended. Max 2MB best results</div>
									</div>
									</div>

									<div class="col-12 mt-4">
										<button type="submit" class="btn btn-info px-5 text-white fw-bold" style="background:linear-gradient(135deg,#0ea5e9,#2563eb); border:none;">
                                            <i class="bx bxs-cloud-upload me-2"></i> Upload Main Logo
                                        </button>
									</div>

								</form>
							</div>
						</div>

						<!-- ============================================================
						     OPTION 2 — STATIC LOGO (logo.png) — SECOND CARD
						     Uploads directly to public_html/logo.png  (root)
						     Existing Option 1 above (logomain.png) untouched
						     ============================================================ -->
						<div class="card border-top border-0 border-4 border-5 border-warning mt-5">
							<div class="card-body p-5">
								<div class="card-title d-flex align-items-center">
									<div><i class="bx bxs-palette me-1 font-22 text-warning"></i>
									</div>
									<h5 class="mb-0 text-warning">Static Logo Upload → /logo.png  <span class="badge bg-warning text-dark ms-2">NEW</span></h5>
								</div>
								<hr>

                                <div class="row mb-4">
                                    <div class="col-12 d-flex align-items-center gap-3 flex-wrap">
                                        <h6 class="mb-0 text-dark fw-bold" style="width:180px;">Current /logo.png:</h6>
                                        <div class="d-flex align-items-center gap-3 flex-wrap">
                                            <div style="padding:14px 20px; border-radius:12px; background:#fffbeb; border:2px dashed #fbbf24;">
                                                <img src="/logo.png" alt="Current Static Logo (logo.png)" style="max-width:180px; max-height:60px; object-fit:contain;" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-flex';">
                                                <div style="display:none; align-items:center; justify-content:center; width:180px; height:60px; border-radius:8px; background:linear-gradient(135deg,#d97706,#f59e0b); color:#fff; font-weight:800; font-family:'Nunito',sans-serif; font-size:20px; letter-spacing:0.5px;">
                                                    logo.png
                                                </div>
                                            </div>
                                            <span class="text-muted small"><i class="bx bx-link"></i> File path: <code>/logo.png</code> (public_html root)</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="alert alert-warning mb-4 radius-10 border-start border-warning border-4 d-flex align-items-start gap-3" role="alert">
                                    <i class="bx bxs-error-circle font-24 text-warning"></i>
                                    <div>
                                        <strong>Note:</strong> Yeh <b>alag option</b> hai — isse upload karne par file <b>direct</b> <code>/logo.png</code> (root folder) me save hogi.
                                        <ul class="mb-0 mt-1 ps-3">
                                            <li>✅ Upar wala Option 1 (Main Logo → <code>/logomain.png</code>) bilkul <b>untouched</b> rahega.</li>
                                            <li>✅ Database me koi change nahi — sirf file upload.</li>
                                        </ul>
                                    </div>
                                </div>

								<form action="" method="POST" class="row g-3"  enctype="multipart/form-data">

									<div class="col-12 ml-2">
                                    <div class="col-md-3">
										<label for="inputFirstName" class="form-label">Upload Static Logo (JPG / PNG / GIF / WEBP)</label>
										<input type="file" name="staticlogo"  required class="form-control" accept="image/png,image/jpeg,image/jpg,image/gif,image/webp" >
                                        <input type="hidden" name="lg_static" value="true">
                                        <div class="form-text text-muted mt-1">Direct save hoga as <code>/logo.png</code> — Transparent PNG recommended.</div>
									</div>
									</div>

									<div class="col-12 mt-4">
										<button type="submit" class="btn btn-warning px-5 text-white fw-bold" style="background:linear-gradient(135deg,#d97706,#f59e0b); border:none;">
                                            <i class="bx bxs-cloud-upload me-2"></i> Upload as /logo.png
                                        </button>
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
	<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
	<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
	<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
	<!--app JS-->
	<script src="../template/ahkweb/assets/js/app.js"></script>

	<?php if (!empty($GLOBALS['ahkweb_swal_inline'])): ?>
	<script>
	$(function(){
		<?php echo $GLOBALS['ahkweb_swal_inline']; ?>
	});
	</script>
	<?php endif; ?>
</body>


</html>
