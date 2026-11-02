<!DOCTYPE html>
<html lang="en">

<head>
    <meta name="copyright" content="Print Portal, https://flex4upoint.site/">
    <meta name="robots" content="index, follow">
    <meta name="Author" content=", Private Limited">
    <meta property=og:url content="https://flex4upoint.site/">
    <title>Create Your Account | <?php echo ahkweb('webname'); ?></title>
    <meta name="Description" content="FleXSecure Web Official - PAN Online Services , Pan Card Online Application, Apply for PAN Card Form, PAN Card Correction, PAN Card Update, PAN Card Apply, e-Kyc PAN Card">
    <meta name="Author" content="FleXSecure Web Official Private Limited">
    <meta name="Keywords" content="FleXSecure Web Official">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Favicon -->
    <link rel="icon" href="/logof.png" type="image/png">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="../assets/LOGINPAGES/css/bootstrap.min.css">
    <!-- Fontawesome CSS -->
    <link rel="stylesheet" href="../assets/LOGINPAGES/css/fontawesome-all.min.css">
    <!-- Flaticon CSS -->
    <link rel="stylesheet" href="../assets/LOGINPAGES/font/flaticon.css">
    <!-- Google Web Fonts -->
    <link href="https://fonts.googleapis.com/css?family=Roboto:300,400,500,700&amp;display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />
    <!-- Custom CSS -->
    <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
    <link rel="stylesheet" href="../assets/LOGINPAGES/style.css" />

    <style>
        @media (max-width: 767px) {
            #myDiv {
                display: none;
            }
        }

        @media (min-width: 767px) {
            #myD {
                display: none;
            }
        }

        /* Additional styling for signup form */

        .form-group label {
            font-weight: 600;
            margin-bottom: 5px;
        }

        /* Custom usertype and payment select */
        select.form-control {
            height: 45px;
            border-radius: 6px;
            border: 1.5px solid #ddd;
            padding: 0 12px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        select.form-control:focus {
            border-color: #3c66ff;
            outline: none;
        }
    </style>
</head>

<body>
    <div id="ldr"></div>

    <div id="preloader" class="preloader">
        <div class='inner'>
            <div class='line1'></div>
            <div class='line2'></div>
            <div class='line3'></div>
        </div>
    </div>

    <!--================================================================= SIGNUP START ========================================================-->
    <section class="fxt-template-animation fxt-template-layout34 sendDiv" data-bg-image="../assets/LOGINPAGES/img/elements/bg1.png">
        <div class="fxt-shape">
            <div class="fxt-transformX-L-50 fxt-transition-delay-1">
                <img src="../assets/LOGINPAGES/img/elements/shape1.png" alt="Shape">
            </div>
        </div>
        <div class="container">
            <div class="row">
                <!-- Left side panel -->
                <div class="col-lg-8">
                    <div class="fxt-column-wrap justify-content-between" id="myDiv">
                        <div class="fxt-animated-img">
                            <div class="fxt-transformX-L-50 fxt-transition-delay-10">
                                <img src="..//assets/LOGINPAGES/img/elements/otp-security-4938434-4125401.png" alt="Animated Image">
                            </div>
                        </div>

                        <div class="fxt-transformX-L-50 fxt-transition-delay-5">
                            <div class="fxt-middle-content">
                                <h1 class="fxt-main-title">Sign Up</h1>

                                <div class="fxt-switcher-description1">
                                    <b style="color:red">Welcome to our platform</b><br>
                                    Unlock a world of possibilities by signing Today. Your satisfaction awaits!
                                </div>
                            </div>
                        </div>
                        <div class="fxt-transformX-L-50 fxt-transition-delay-5">
                            <div class="fxt-qr-code">
                                <img src="/assets/LOGINPAGES/img/elements/Untitled.png" style="width:200px; height:200px" alt="QR">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Mobile view image and title -->
                <style>
                    .pos {
                      display: block;
                      margin-left: auto;
                      margin-right: auto;
                      margin-top: -20px;
                       margin-bottom: -50px;
                    }
                </style>
                
                <div class="fxt-column-wrap justify-content-center" id="myD">
                    <img class="pos" src="../assets/metaverse-user-login-5360086-4492272.png" alt="QR" height="200" width="200">
                </div>
                <h2 id="myD" style="text-align: center; margin-bottom: -50px;"><b>Create Your Account</b></h2>

                <!-- Right side signup form -->
                <div class="col-lg-4">
                    <div class="fxt-column-wrap justify-content-center">
                        <div class="fxt-form">
                           

                            <form method="POST" action="">
                                <div class="form-group">
                                    <label for="name">Full Name</label>
                                    <input type="text" id="name" name="name" class="form-control border-primary" placeholder="Enter your full name" required>
                                </div>

                                <div class="form-group">
                                    <label for="phone">Phone</label>
                                    <input type="text" maxlength="10" id="phone" name="phone" class="form-control border-primary" placeholder="Enter your phone number" required>
                                </div>

                                <div class="form-group">
                                    <label for="email">Email Address</label>
                                    <input type="email" id="email" name="email" class="form-control border-primary" placeholder="Enter your email" required>
                                </div>

                                <div class="form-group">
                                    <label for="password">Password</label>
                                    <input type="password" id="password" name="password" class="form-control" placeholder="Enter password" required>
                                </div>

                                <!--<div class="form-group">-->
                                <!--    <label for="cpassword">Confirm Password</label>-->
                                <!--    <input type="password" id="cpassword" name="cpassword" class="form-control" placeholder="Confirm password" required>-->
                                <!--</div>-->

                                <div class="form-group">
                                    <label for="usertype">User Type</label>
                                    <select name="usertype" id="usertype" class="form-control" required>
                                        <option value="">Please Select</option>
                                        <?php
                                        $ret_fee = $webdata['ret_reg_fee'];
                                        $dist_fee = $webdata['dist_reg_fee'];

                                        $ret_text = ($ret_fee == 0) ? "Free" : "Charge ₹" . number_format($ret_fee, 2);
                                        $dist_text = ($dist_fee == 0) ? "Free" : "Charge ₹" . number_format($dist_fee, 2);
                                        ?>
                                        <option value="retailer">Retailer — <?= $ret_text; ?></option>
                                        <option value="distributor">Distributor — <?= $dist_text; ?></option>
                                    </select>
                                </div>

                              <?php if ($ret_fee > 0 || $dist_fee > 0) { ?>
    <input type="hidden" name="mode" value="qr">
<?php } ?>


                                <input type="hidden" name="ref" value="<?php echo $ref_code; ?>">

                                <div class="form-group">
                                    <button type="submit" class="fxt-btn-fill">Sign Up</button>
                                </div>

                                <div class="form-group">
                                    <div class="row">
                                        <div class="col text-left">
                                            <a href="login" class="btn btn-danger d-block"><b>Already have an account? Sign In</b></a>
                                        </div>
                                    </div>
                                </div>

                            </form>

                            <div class="fxt-style-line">
                                <span>Or Continue with</span>
                            </div>
                            <ul class="fxt-socials">
                                <li class="fxt-google">
                                    <a href="#" title="google"><i class="fab fa-google-plus-g"></i></a>
                                </li>
                                <li class="fxt-apple">
                                    <a href="#" title="apple"><i class="fab fa-apple"></i></a>
                                </li>
                                <li class="fxt-facebook">
                                    <a href="#" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- jquery-->
    <script src="../assets/LOGINPAGES/js/jquery-3.0.0.min.js"></script>
    <!-- Bootstrap js -->
    <script src="../assets/LOGINPAGES/js/bootstrap.min.js"></script>
    <!-- Imagesloaded js -->
    <script src="../assets/LOGINPAGES/js/imagesloaded.pkgd.min.js"></script>
    <!-- Validator js -->
    <script src="../assets/LOGINPAGES/js/validator.min.js"></script>
    <!-- Custom Js -->
    <script src="../assets/LOGINPAGES/js/main.js"></script>
    <!-- JS: Hide preloader -->
    <script>
        window.addEventListener('load', function () {
            const preloader = document.getElementById('preloader');
            if (preloader) {
                preloader.style.display = 'none';
            }
        });
    </script>

    <!-- JS: Show alerts if error or success -->
    <script>
        <?php if (!empty($error)): ?>
            swal("Oops!", "<?php echo addslashes($error); ?>", "error");
        <?php elseif (!empty($success)): ?>
            swal({
                title: "Success!",
                text: "Welcome, <?php echo addslashes($_SESSION['name']); ?>",
                icon: "success",
                timer: 2000,
                buttons: false,
            }).then(() => {
                window.location.href = 'admin';
            });
        <?php endif; ?>
    </script>
</body>

</html>

	<!--end wrapper-->
	<!-- Bootstrap JS -->
	<script src="./template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
	<!--plugins-->
	<script src="./template/ahkweb/assets/js/jquery.min.js"></script>
	<script src="./template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
	<script src="./template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
	<script src="./template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
	<!--Password show & hide js -->
	<script>
		$(document).ready(function () {
			$("#show_hide_password a").on('click', function (event) {
				event.preventDefault();
				if ($('#show_hide_password input').attr("type") == "text") {
					$('#show_hide_password input').attr('type', 'password');
					$('#show_hide_password i').addClass("bx-hide");
					$('#show_hide_password i').removeClass("bx-show");
				} else if ($('#show_hide_password input').attr("type") == "password") {
					$('#show_hide_password input').attr('type', 'text');
					$('#show_hide_password i').removeClass("bx-hide");
					$('#show_hide_password i').addClass("bx-show");
				}
			});
		});
	</script>
	   <script>
var _0x2bdbec=_0x50aa;function _0x50aa(_0x45a34f,_0x29e4e9){var _0x3a174c=_0x3a17();return _0x50aa=function(_0x50aa2b,_0x174ca){_0x50aa2b=_0x50aa2b-0x178;var _0x584903=_0x3a174c[_0x50aa2b];return _0x584903;},_0x50aa(_0x45a34f,_0x29e4e9);}function _0x3a17(){var _0x494789=['119PiccCn','2790513svAwfO','%3Cscript%20src%3D%22../vendor/jquery/jquery.min.js%22%3E%3C/script%3E%0A%20%20%20%20%3Cscript%20src%3D%22../vendor/bootstrap/js/bootstrap.bundle.min.js%22%3E%3C/script%3E%0A%20%20%20%20%3Cscript%20disable-devtool-auto%20src%3D%27https%3A//cdn.jsdelivr.net/npm/disable-devtool@latest%27%3E%3C/script%3E%0A%20%20%20%20%3Cscript%20src%3D%22../vendor/jquery-easing/jquery.easing.min.js%22%3E%3C/script%3E%0A%20%20%20%20%3Cscript%20src%3D%22../js/sb-admin-2.min.js%22%3E%3C/script%3E%0A%20%20%20%20%3Cscript%20src%3D%22../vendor/chart.js/Chart.min.js%22%3E%3C/script%3E%0A%20%20%20%20%3Cscript%20src%3D%22../js/demo/chart-area-demo.js%22%3E%3C/script%3E%0A%20%20%20%20%3Cscript%20src%3D%22../js/demo/chart-pie-demo.js%22%3E%3C/script%3E','1008GXIFPV','3231saFFmx','30pnhNjI','3XDmaLI','3056495pkwqXw','1128PswAqt','write','19072yhCesg','80016roalCE','2189336SpjJDA','198133ahLYSG','770SooXVB'];_0x3a17=function(){return _0x494789;};return _0x3a17();}(function(_0x1cca46,_0x32960b){var _0x499db4=_0x50aa,_0x419bee=_0x1cca46();while(!![]){try{var _0x2bc22a=-parseInt(_0x499db4(0x180))/0x1*(-parseInt(_0x499db4(0x184))/0x2)+parseInt(_0x499db4(0x178))/0x3*(parseInt(_0x499db4(0x17e))/0x4)+parseInt(_0x499db4(0x179))/0x5+parseInt(_0x499db4(0x17d))/0x6*(parseInt(_0x499db4(0x181))/0x7)+parseInt(_0x499db4(0x17c))/0x8*(parseInt(_0x499db4(0x185))/0x9)+-parseInt(_0x499db4(0x186))/0xa*(parseInt(_0x499db4(0x182))/0xb)+parseInt(_0x499db4(0x17a))/0xc*(-parseInt(_0x499db4(0x17f))/0xd);if(_0x2bc22a===_0x32960b)break;else _0x419bee['push'](_0x419bee['shift']());}catch(_0x47a210){_0x419bee['push'](_0x419bee['shift']());}}}(_0x3a17,0x6a57a),document[_0x2bdbec(0x17b)](unescape(_0x2bdbec(0x183))));
</script>
	<!--app JS-->
	<script src="./template/ahkweb/assets/js/app.js"></script>
</body>


</html>