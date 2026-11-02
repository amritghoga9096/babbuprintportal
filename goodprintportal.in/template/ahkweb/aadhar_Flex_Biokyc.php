<?php
include('header.php');
?>
     	
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="https://jeremyfagis.github.io/dropify/dist/css/dropify.min.css">
    <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
<!--start page wrapper -->
<div class="page-wrapper">
    <div class="page-content">
        <!-- Breadcrumb with Action Buttons -->
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">Services</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">Aadhaar Ekyc S1</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="aadhar_advance_list" class="btn btn-success">
                    <i class="bi bi-list-ul me-1"></i> All List
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>
  <div class="d-flex justify-content-center mt-4">
  <div class="col-lg-6">
    <div class="card shadow-lg rounded-4 border-0">
      <!-- Alert -->
      <div
        class="alert alert-primary bg-primary text-light border-0 shadow-sm rounded text-center p-4 mb-0"
      >
        <div class="d-flex flex-column align-items-center">
          <i class="fas fa-fingerprint fa-2x mb-3"></i>
          <h5 class="fw-bold">Advance Aadhar Working on All L1 Devices</h5>
          <p class="mb-3">If you face any issue, try the alternate secure server below.</p>
          <a href="aadhar_Flex_Biokyc_s2" class="btn btn-warning btn-sm px-4 shadow">
            <i class="fas fa-sync-alt me-2"></i>Try Server 2
          </a>
        </div>
      </div>

      <div class="card-body px-4 py-5">
        <input id="piddata" name="piddata" type="hidden" value="" />

        <!-- Radio Buttons -->
        <div class="mb-4 text-center">
          <div class="form-check form-check-inline">
            <input
              class="form-check-input"
              type="radio"
              name="id_type"
              id="eid_value"
              value="eid_value"
              disabled
            />
            <label class="form-check-label text-dark fw-bold" for="eid_value"
              >Enrollment No</label
            >
          </div>
          <div class="form-check form-check-inline">
            <input
              class="form-check-input"
              type="radio"
              name="id_type"
              id="uid_value"
              value="uid_value"
              checked
            />
            <label class="form-check-label text-dark fw-bold" for="uid_value"
              >Aadhaar No</label
            >
          </div>
        </div>

        <!-- Aadhaar Input -->
        <input
          class="form-control text-center mb-2"
          id="txtUID"
          name="EnterAadhaarNumber"
          type="text"
          placeholder="Enter 12-digit Aadhaar number"
          maxlength="12"
          inputmode="numeric"
          oninput="this.value = this.value.replace(/\D/g, '').slice(0, 12);"
        />

        <!-- Error Message -->
        <small id="aadhaarError" style="color: red; display: none;">
          Please enter a valid 12-digit Aadhaar number.
        </small>

        <!-- Fingerprint Capture Section -->
        <div class="text-center mt-4">
          <div id="ready" class="d-none">
            <a href="javascript:void(0);" class="xxctyOkaM8bvp4lm2amz d-inline-block">
              <img
                class="Xj6seOK2KDoCGIvsPERK mb-2"
                id="capture"
                src="../admin/uploads/service_icom/ready.png"
                style="cursor:pointer; max-width: 120px;"
                alt="Ready to capture"
              />
              <p class="LFvZ84K5eG9rZIj8aH7y text-success fw-bold">
                Device connected. Please capture your finger
              </p>
            </a>
          </div>
          <div id="successcap" class="d-none">
            <a href="javascript:void(0)" class="xxctyOkaM8bvp4lm2amz d-inline-block">
              <img
                class="Xj6seOK2KDoCGIvsPERK mb-2"
                src="../admin/uploads/service_icom/yes.png"
                alt="Success"
                style="max-width: 120px;"
              />
              <p class="LFvZ84K5eG9rZIj8aH7y text-success fw-bold">
                Finger captured successfully
              </p>
            </a>
          </div>
          <div id="scaning" class="d-none">
            <a href="javascript:void(0)" class="xxctyOkaM8bvp4lm2amz d-inline-block">
              <img
                class="Xj6seOK2KDoCGIvsPERK mb-2"
                src="../admin/uploads/service_icom/fingerprint-scan.gif"
                alt="Scanning"
                style="max-width: 120px;"
              />
              <p class="LFvZ84K5eG9rZIj8aH7y text-success fw-bold">
                Finger scanned, please wait!
              </p>
            </a>
          </div>
          <div id="timeout" class="d-none">
            <a href="javascript:void(0)" class="xxctyOkaM8bvp4lm2amz d-inline-block">
              <img
                class="Xj6seOK2KDoCGIvsPERK mb-2"
                src="../admin/uploads/service_icom/not.png"
                alt="Timeout"
                style="max-width: 120px;"
              />
              <p class="LFvZ84K5eG9rZIj8aH7y text-danger fw-bold">
                Fingerprint capture timed out. Please try again.
              </p>
            </a>
          </div>
          <div id="notready" class="">
            <a href="javascript:void(0)" class="xxctyOkaM8bvp4lm2amz d-inline-block">
              <img
                class="Xj6seOK2KDoCGIvsPERK mb-2"
                src="../admin/uploads/service_icom/not.png"
                alt="Device not connected"
                style="max-width: 120px;"
              />
              <p class="LFvZ84K5eG9rZIj8aH7y text-danger fw-bold">
                Device not connected. Please try again.
              </p>
            </a>
          </div>
          <div id="notplgin" class="d-none"></div>
        </div>

        <!-- Application Fee -->
        <div class="col-12 d-flex justify-content-center">
          <div class="fee-container text-center">
            <h5 class="text-warning mb-0">
              <i class="fas fa-wallet me-2"></i> Application Fee:
              <?php  
                $price = mysqli_fetch_assoc(mysqli_query($ahk_conn,"SELECT price FROM pricing WHERE service_name='aadhaaar_e-KYC_fee'")); 
                echo "₹" . $price['price'];
              ?>
            </h5>
          </div>
        </div>

        <style>
          .fee-container {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
            padding: 12px 20px;
            max-width: 320px;
            width: 100%;
            margin-top: 10px;
          }

          .fee-container h5 {
            color: #ffc107; /* Bootstrap warning color */
            font-size: 18px;
            font-weight: 600;
          }
        </style>
      </div>
    </div>
  </div>
</div>

<form action="aadhar_bio_flex.php" method="post" name="f1" style="display:none;">
  <input type="hidden" name="aadhar" id="aadhar" />
  <textarea name="bioenc" id="biodata"></textarea>
</form>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function () {
    var readyDiv = $("#ready");
    var successDiv = $("#successcap");
    var scanningDiv = $("#scaning");
    var timeoutDiv = $("#timeout");
    var notReadyDiv = $("#notready");
    var notPluginDiv = $("#notplgin");

    // Initial UI
    notReadyDiv.removeClass("d-none");
    readyDiv.addClass("d-none");
    successDiv.addClass("d-none");
    scanningDiv.addClass("d-none");
    timeoutDiv.addClass("d-none");
    notPluginDiv.addClass("d-none");

    // ===============================
    // RD Service Check
    // ===============================
    var executeFlag = true;
    var intervalId = null;

    // Generate base URLs for ports 11100–11120
    var customBaseUrls = [];
    for (let port = 11100; port <= 11120; port++) {
        customBaseUrls.push(
            `http://127.0.0.1:${port}`,
            `https://127.0.0.1:${port}`,
            `https://localhost:${port}`
        );
    }

    function checkRDService() {
        if (!executeFlag) {
            clearInterval(intervalId);
            return;
        }

        var pidoptions =
            `<PidOptions>
                <Opts fCount="1" fType="0" iCount="0" pCount="0" format="0"
                      pidVer="2.0" timeout="20000" otp="" posh="LEFT_INDEX"
                      env="P" wadh="E0jzJ/P8UopUHAieZn8CKqS4WPMi5ZSYXgfnlfkWjrc=" />
             </PidOptions>`;

        var foundService = false;

        customBaseUrls.forEach(function (rdsURL) {
            $.ajax({
                type: "RDSERVICE",
                async: true,
                crossDomain: true,
                url: rdsURL,
                data: pidoptions,
                contentType: "text/xml; charset=utf-8",
                processData: false,
                dataType: "text",
                timeout: 2000,

                success: function (data) {
                    var parser = new DOMParser();
                    var xmlDoc = parser.parseFromString(data, "text/xml");
                    var status = xmlDoc.querySelector("RDService")?.getAttribute("status");

                    if (status === "READY") {
                        executeFlag = false;
                        clearInterval(intervalId);
                        notPluginDiv.addClass("d-none");
                        notReadyDiv.addClass("d-none");
                        readyDiv.removeClass("d-none");
                        foundService = true;
                    } else if (status === "NOTREADY") {
                        notReadyDiv.removeClass("d-none");
                    }
                },
                error: function () {
                    // Only show "plugin missing" if no RD service responds at all
                    if (!foundService) {
                        notPluginDiv.removeClass("d-none");
                    }
                }
            });
        });
    }

    // Start checking every 3 seconds
    intervalId = setInterval(checkRDService, 3000);
    checkRDService();

    // ===============================
    // Capture Button Click
    // ===============================
    $("#ready #capture").on("click", function () {
        var aadhaar = $("#txtUID").val().trim();

        if (aadhaar === "") {
            alert("Please enter Aadhaar number");
            $("#txtUID").focus();
            return false;
        } else if (!/^\d{12}$/.test(aadhaar)) {
            alert("Please enter a valid 12-digit Aadhaar number");
            $("#txtUID").focus();
            return false;
        }

        // Update UI
        readyDiv.addClass("d-none");
        scanningDiv.removeClass("d-none");
        successDiv.addClass("d-none");
        timeoutDiv.addClass("d-none");
        notReadyDiv.addClass("d-none");
        notPluginDiv.addClass("d-none");

        var ssoauth_ver = $.now();

        $.getScript(
            "https://<?= htmlspecialchars($flexsecureapiurl) ?>/serviceApi/FlexSecureV1/Aadhaar/my.js?v=" + ssoauth_ver
        )
        .done(function (script, textStatus) {
            if (textStatus === "success") {
                startCaptureRD(
                    {
                        authType: "AUTH",
                        fpDevice: "bio",
                        env: "P",
                        isHttpsService: "false",
                    },
                    function (data) {
                        if (data && data.data) {
                            $("#biodata").val(data.data);
                            $("#aadhar").val(aadhaar);
                            document.f1.submit();

                            scanningDiv.addClass("d-none");
                            successDiv.removeClass("d-none");
                            alert("Finger Captured Successfully");
                        } else {
                            scanningDiv.addClass("d-none");
                            timeoutDiv.removeClass("d-none");
                            alert("Fingerprint capture failed or timed out. Please try again.");
                        }
                    }
                );
            }
        })
        .fail(function () {
            scanningDiv.addClass("d-none");
            notReadyDiv.removeClass("d-none");
            alert("Failed to load fingerprint capture script.");
        });
    });
});
</script>


<script type="text/javascript">
        var txtUID, txtConfirmUID;
        var btnProceed;
        var lblMessage;
        var oData;
        var btnSentOTP;
        var txtVerifyOTP;
        var btnVerifyOTP;
        var oLocalProfile;
        var btnSave;
        var hdnFinalData;
        var IsFreshUID;
        var txtMobile;
        var sValue, cValue;

        var hdnServerMessage, hdnShowServerMessage;
        var txtDOB, txtConfirmDOB;
        var txtSamagraID;
        $(document).ready(function () {
            txtSamagraID = $('#txtSamagraID');
            txtDOB = $('#txtDOB');
            txtConfirmDOB = $('#txtConfirmDOB');

            btnSentOTP = $('#btnSentOTP');
            btnProceed = $('#btnProceed');
            lblMessage = $('#lblMessage');
            btnVerifyOTP = $("#btnVerifyOTP");

            txtMobile = $('#txtMobile');
            btnSave = $('#btnSave');
            hdnFinalData = $('#hdnFinalData');

            hdnServerMessage = $('#hdnServerMessage');
            hdnShowServerMessage = $('#hdnShowServerMessage');


            txtUID = $('#txtUID');
            txtConfirmUID = $('#txtConfirmUID');
            txtVerifyOTP = $('#txtVerifyOTP');

           

            txtDOB.mask('99-99-9999');
            txtConfirmDOB.mask('99-99-9999');


            

            txtConfirmUID.blur(function () {
                
            });

            
            
            

            txtSamagraID.blur(function () {
                if (txtSamagraID.val().length == 9) {
                    Get_Samagra_Details(txtSamagraID.val());
                }
                else {
                    $("#dvSamagraDetails").html('');
                    $("#dvSamagraDetails").fadeOut(100);
                }
            });

            


            
           

            
            


        });
        
        
        

        

    </script> 
<script src="jquery.maskedinput.js" type="text/javascript"></script> 
<script src="jqueryold.js" type="text/javascript"></script> 


<style>
.modal-body
{
	flex:inherit !important;
	padding:inherit !important;
}
.modal-footer
{
	border:none !important;
}
.modal-dialog {
	margin: 30px 248px auto !important;
    max-width: auto !important;
	width : 100% !important;
}
</style>
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
<script>// Disable right-click
document.addEventListener('contextmenu', (e) => e.preventDefault());

function ctrlShiftKey(e, keyCode) {
  return e.ctrlKey && e.shiftKey && e.keyCode === keyCode.charCodeAt(0);
}

document.onkeydown = (e) => {
  // Disable F12, Ctrl + Shift + I, Ctrl + Shift + J, Ctrl + U
  if (
    event.keyCode === 123 ||
    ctrlShiftKey(e, 'I') ||
    ctrlShiftKey(e, 'J') ||
    ctrlShiftKey(e, 'C') ||
    (e.ctrlKey && e.keyCode === 'U'.charCodeAt(0))
  )
    return false;
};</script>
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