<?php
include('header.php');
include('../../includes/config.php');
?>
<?php if (isset($_POST['bioenc'])) {

  $bio = $_POST['bioenc'];
  $aadhar = $_POST['aadhar'];
  $order_id = '123';
  $server = $_SERVER['SERVER_NAME'];
  $price = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT * FROM pricing WHERE service_name='aadhaaar_e-KYC_fee'"));
  $fee = $price['price'];
  $username = $udata['phone'];
  $wallet_amount = $udata['balance'];
  // Check wallet balance
  if ($wallet_amount > $fee) {
    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => "https://" . htmlspecialchars($flexsecureapiurl) . "/serviceApi/FlexSecureV1/Aadhaar/my.php",
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => array(
        'bioenc' => $bio,
        'order_id' => $order_id,
        'aadhar' => $aadhar,
        'apiKey' => urlencode($flexsecure_key),

      ),
    ));

    $response = curl_exec($curl);

    curl_close($curl);
    $json = json_decode($response, true);
    $status = $json['status'] ?? '';
    $error_api = $json['message'] ?? '';
    //print_r($response);
    // Check response status and update wallet if successful
    if ($status == true) {

      $appliedby = $udata['phone'];
      $nbal = $wallet_amount - $fee;
      $sql = "update users SET balance= balance - $fee where phone='" . $udata['phone'] . "'";
      $abs = mysqli_query($ahk_conn, $sql);
      $updatehistory = mysqli_query($ahk_conn, "INSERT INTO `wallethistory`(`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES ('$appliedby','$fee','$nbal','Aadhar Ekyc S1','1','Debit')");
      // If the status is 'N', the balance will not be deducted
    } else {
      echo "<script>Swal.fire('Error', '$error_api', 'error');</script>";
    }

    curl_close($curl);
  } else {
    ?>
    <script>
      $(function () {
        Swal.fire(
          'Insufficient Balance',
          'Your wallet balance is too low to process this request',
          'error'
        );
      });
      setTimeout(() => {
        window.location.href = 'wallet.php';
      }, 2000);
    </script>
  <?php
  }


  $enrollment = $json['eid'];
  $issueDate = $json['issue'];
  $img = $json['image'];
  $aadharno = $json['UID'];
  $name = $json['name'];
  $father = $json['fname'];
  $dob = $json['dobadhar'];
  $gender = $json['gender'];
  $txtadd = $json['address'];
  $dist = $json['dist'];
  $pincode = $json['pincode'];
  $state = $json['state'];

}


if (isset($_POST['savedata'])) {


  $aadharno = trim($_POST['aadharno']);

  $name = trim($_POST['name']);

  // echo $name;
  $fathername = trim($_POST['fathername']);

  $dobadhar = trim($_POST['dobadhar']);

  $birthtithilocal = trim($_POST['birthtithilocal']);

  $gender = strtoupper(trim($_POST['gender']));

  $genderlocal = trim($_POST['genderlocal']);

  $address = trim($_POST['address']);

  $language = trim($_POST['language']);

  $namelocal = trim($_POST['namelocal']);

  $localaddress = trim($_POST['addresslocal']);

  $patalocal = trim($_POST['patalocal']);

  $houseno = trim($_POST['houseno']);

  $street = trim($_POST['street']);

  $pinco = trim($_POST['pincode']);

  $vtcandpost = trim($_POST['vtcandpost']);

  $dist = trim($_POST['dist']);

  $statename = trim($_POST['statename']);


  $idata = $_POST['imgdata'];

  $eno = trim($_POST['eno']);
  $idate = date('Y-m-d', strtotime('-7 day', strtotime($_POST['ddate'])));

  $ddate = trim($_POST['ddate']);


  $s = date($fetch['joindate']);

  $dt = new DateTime($s);


  $date = $dt->format('Y-m-d');


  date_default_timezone_set('Asia/Kolkata');
  $date_raw = date("Y-m-d H:i:s");
  $time = date("H:i:s");
  $round = date('Y-m-d', strtotime('-15 day', strtotime($date_raw))) . ' ' . $time;

  if ($aadharno == "") {

    $msgno = 'Please Enter Aadhar Card No .... ';

  } else if ($fetch['aadharpoint'] > $fetch['balance']) {

    $msgno = "Sorry, Your Balance is Low .... Please Recharge Soon";

    ?>
      <script>

        setTimeout(function () {

          window.location.href = 'ainfo.php';

        }, 2000);

      </script>
    <?php

  } elseif ($name == "") {

    $msgno = 'Please Enter Name  .... ';



  } elseif ($dobadhar == "") {

    $msgno = 'Please Enter Date of Birth  .... ';

  } elseif ($gender == "") {

    $msgno = 'Please Enter Gender  .... ';

  } elseif ($address == "") {

    $msgno = 'Please Enter Address  .... ';

  } elseif ($language == "") {

    $msgno = 'Please Enter Local Language  .... ';

  } elseif ($namelocal == "") {

    $msgno = 'Please Enter Name in Local Language  .... ';

  } elseif ($localaddress == "") {

    $msgno = 'Please Enter Address in Local Language  .... ';

  } else {

    $a = mysqli_query($ahk_conn, "SELECT aadharno FROM aadharauto Where aadharno='" . $aadharno . "'");

    $b = mysqli_fetch_array($a);

    if ($b['aadharno'] == $aadharno) {

      $msgno = 'This Aadhar Card No Already Exist .... ';

    } else {

      $st = '';

      $nd = '';

      $rd = '';

      $adhrno = '';

      $st = substr($aadharno, 0, 4);

      $nd = substr($aadharno, 4, 4);

      $rd = substr($aadharno, 8, 4);

      $adhrno = $st . ' ' . $nd . ' ' . $rd;

      //echo $imgfpath;

      $sex = '';

      if ($gender == 'Male') {

        $sex = 'M';

      } else {

        $sex = 'F';

      }

      /// insert value


      $resultm = mysqli_query($ahk_conn, "SELECT srno FROM aadharauto ORDER BY srno DESC LIMIT 1");
      $num_rows = mysqli_fetch_array($resultm);
      $srno = $num_rows['srno'] + 1;
      date_default_timezone_set('Asia/Kolkata');
      $timestamp = date("Y-m-d H:i:s");

      // Step 1: Generate a 6-digit random number
      $aadharautoid = mt_rand(100000, 999999);

      // Optional: Ensure uniqueness by checking the database (recommended in production)
      $checkQuery = "SELECT 1 FROM `aadharauto` WHERE `aadharautoid` = '$aadharautoid'";
      $checkResult = mysqli_query($ahk_conn, $checkQuery);
      while (mysqli_num_rows($checkResult) > 0) {
        $aadharautoid = mt_rand(100000, 999999);
        $checkQuery = "SELECT 1 FROM `aadharauto` WHERE `aadharautoid` = '$aadharautoid'";
        $checkResult = mysqli_query($ahk_conn, $checkQuery);
      }

      // Step 2: Insert the record with `aadharautoid`
      $query = "INSERT INTO `aadharauto`
(`aadharautoid`, `aadharno`, `originalaadharno`, `aadharname`, `fathername`, `dob`, `gender`, `sexinlocal`, `fulladdress`,
 `locallanguage`, `localname`, `localaddress`, `imagepathoriginal`, `dobinlocal`, `pata`, `houseno`, `street`, `vtcandpost`,
 `dist`, `statename`, `pincode`, `srno`, `userid`, `createdatetime`) 
VALUES (
'$aadharautoid',
'" . trim($aadharno) . "',
'" . trim($adhrno) . "',
'" . $name . "',
'" . $fathername . "',
'" . $dobadhar . "',
'" . $gender . "',
N'" . $genderlocal . "',
'" . $address . "',
'" . $language . "',
N'" . $namelocal . "',
N'" . $localaddress . "',
'" . $idata . "',
N'" . $birthtithilocal . "',
N'" . $patalocal . "',
'" . $houseno . "',
'" . $street . "',
'" . $vtcandpost . "',
'" . $dist . "',
'" . $statename . "',
'" . $pinco . "',
'" . $srno . "',
'" . $_SESSION['phone'] . "',
'" . $timestamp . "')";

      $result = mysqli_query($ahk_conn, $query);

      $msg = "Please Wait Aadhar Preview just a second...";
      $_SESSION["IMGPATH"] = '';
      $_SESSION["AADHAARNO"] = trim($aadharno);


      if ($result) {
        ?>
          <script>
            $(function () {
              Swal.fire(
                'Application Submitted Success',
                '',
                'success'
              )
            })
            setTimeout(() => {
              window.location = 'aadhar_advance_list.php';
            }, 1500);
          </script>
        <?php
      } else {
        ?>
          <script>
            $(function () {
              Swal.fire(
                'Opps',
                'Try Again',
                'error'
              )
            })
            setTimeout(() => {
              window.location = '';
            }, 1500);
          </script>
        <?php
      }
    }

  }
}

?>
<!--start page wrapper -->
<div class="page-wrapper">
  <div class="page-content">
    <!--breadcrumb-->
    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
      <div class="breadcrumb-title pe-3">Home </div>
      <div class="ps-3">
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb mb-0 p-0">
            <li class="breadcrumb-item"><a href="javascript:;"><i class="bx bx-home-alt"></i></a>
            </li>
            <li class="breadcrumb-item active" aria-current="page">New APPLY</li>
          </ol>
        </nav>
      </div>
      <div class="ms-auto">
        <div class="btn-group">
          <button type="button" class="btn btn-primary">Settings</button>
          <button type="button" class="btn btn-primary split-bg-primary dropdown-toggle dropdown-toggle-split"
            data-bs-toggle="dropdown"> <span class="visually-hidden">Toggle Dropdown</span>
          </button>
          <div class="dropdown-menu dropdown-menu-right dropdown-menu-lg-end"> <a class="dropdown-item"
              href="javascript:;">Action</a>
            <a class="dropdown-item" href="javascript:;">Another action</a>
            <a class="dropdown-item" href="javascript:;">Something else here</a>
            <div class="dropdown-divider"></div> <a class="dropdown-item" href="javascript:;">Separated link</a>
          </div>
        </div>
      </div>
    </div>
    <!--end breadcrumb-->
    <div class="row">
      <div class="col-xl-10 mx-auto">
        <h6 class="mb-0 text-uppercase">Aadhar Advance Fingerprint </h6>
        <hr />
        <div class="card border-top border-0 border-4 border-primary">
          <div class="card-body p-5">
            <div class="card-title d-flex align-items-center">
              <div><i class="bx bxs-id-card me-1 font-22 text-primary"></i>
              </div>
              <h5 class="mb-0 text-primary">Show Aadhar Details</h5>
            </div>
            <hr>
            <?php if ($msg != '') { ?>
              <div style="width=100%" class="alert alert-success"><?php echo $msg; ?></div>
            <?php } elseif ($msgno != '') { ?>
              <div style="width=100%" class="alert alert-danger"><?php echo $msgno; ?></div>
            <?php } ?>
            <form method="post">
              <div class="card-body form-aadhar">
                <div class="row justify-content-center">
                  <div class="col-md-2 text-center"> <img class="rounded-circle" src="<?php echo $img; ?>" width="150"
                      height="150" />
                    <input type="hidden" value="<?php echo $img; ?>" name="imgdata" />
                  </div>
                </div>
                <div class="row">
                  <div class="col-md-4">
                    <div class="form-group">
                      <label class="mb-0">EnrollMent No.</label>
                      <input class=" form-control" maxlength="17" id="eno" name="eno" type="text"
                        value="xxxx/xxxxx/<?php echo rand(0000, 9999) ?>">
                    </div>
                  </div>
                  <?php

                  $timestamp = date("Y-m-d");

                  ?>
                  <div class="col-md-4">
                    <div class="form-group">
                      <label class="mb-0">Donwload Date</label>
                      <input type="date" class="form-control" name="ddate" id="exampleFormControlInput1"
                        placeholder="07/07/2020" value="<?php echo $timestamp; ?>" required>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group">
                      <label class="mb-0">Issue Date</label>
                      <input type="text" class="form-control" name="idate" id="exampleFormControlInput1"
                        placeholder="12/10/2014" value="<?php echo date('d/m/Y'); ?>" required>
                      <input class="form-control" name="fathername" type="text" value="<?php echo $fname; ?>"
                        style="display:none;">
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group">
                      <label class="mb-0">Aadhar Card No. </label>
                      <input class="  form-control " maxlength="12" id="aadharno" name="aadharno" type="text"
                        value="<?php echo $aadharno; ?>" required>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group">
                      <label class="mb-0">Name</label>
                      <input type="text" class="form-control" name="name" id="name" placeholder="Enter Name"
                        value="<?php echo $name; ?>" required>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group">
                      <label class="mb-0">Name(Local Language)</label>
                      <input type="text" class="form-control" id="name_regional" name="namelocal" required
                        placeholder="Enter Name" required>
                    </div>
                  </div>
                </div>
                <div class="row">
                  <div class="col-md-3">
                    <div class="form-group">
                      <label class="mb-0">Date of Birth</label>
                      <input class="form-control  " name="dobadhar" type="text" value="<?php echo $dob; ?>" required>
                      <input class="form-control  " name="houseno" type="hidden" value="<?php echo $houseno; ?>">
                      <input class="form-control " name="street" type="hidden" value="<?php echo $street ?>">
                      <input class="form-control " name="pincode" type="hidden" value="<?php echo $pincode; ?>">
                      <input class="form-control " name="vtcandpost" type="hidden" value="<?php echo $vtc; ?>">
                      <input class="form-control " name="dist" type="hidden" value="<?php echo $dist; ?>">
                      <input class="form-control " name="statename" type="hidden" value="<?php echo $state; ?>">
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="form-group">
                      <label class="mb-0">Date of Birth Local</label>
                      <input class=" mng_cp form-control " id="birthtithilocal" name="birthtithilocal" type="text"
                        value="">
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="form-group">
                      <label class="mb-0">Gender</label>
                      <input type="text" class="form-control" name="gender" id="gender" value="<?php echo $gender; ?> "
                        placeholder="Male">
                      <input class="form-control " id="birthtithi" name="birthtithi" readonly="readonly" type="hidden"
                        value="Birth Tithi">
                      <input class="form-control " id="pata" name="pata" readonly="readonly" type="hidden"
                        value="address">
                      <input class="form-control " id="patalocal" name="patalocal" readonly="readonly" type="hidden"
                        value="">
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="form-group">
                      <label class="mb-0">Gender Local</label>
                      <input type="text" class="form-control" name="genderlocal" id="genderlocal">
                    </div>
                  </div>
                </div>
                <div class="row">
                  <div class="col-md-4">
                    <div class="form-group">
                      <label class="mb-0">Select Language</label>
                      <select autofocus class="form-control" name="language" id="language" required>
                        <option value="">SELECT</option>
                        <option value="HI">Hindi</option>
                        <option value="PA">Punjabi</option>
                        <option value="GU">Gujarati</option>
                        <option value="MR">Marathi</option>
                        <option value="TA">Tamil</option>
                        <option value="KN">Kannada</option>
                        <option value="BN">Bengali</option>
                        <option value="TE">Telugu</option>
                        <option value="OR">Oriya</option>
                        <option value="SD">Sindhi</option>
                      </select>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group">
                      <label class="mb-0">Address</label>
                      <textarea class="form-control" id="txtSource" name="address" rows="3"><?php echo $txtadd; ?>
</textarea>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group">
                      <label class="mb-0">Address (Local Language)</label>
                      <textarea class="form-control" id="txtTarget" rows="3" name="addresslocal"></textarea>

                    </div>
                  </div>
                </div>
                <div class="row justify-content-center">
                  <div class="col-md-4 text-center">
                    <button type="submit" name="savedata" class="btn btn-success btn-lg btn-subit"
                      style="background-image: linear-gradient(to bottom,#8d42ff,#563d7c);">SUBMIT</button>
                  </div>
                </div>
              </div>
            </form>
          </div>

          <script type="text/javascript">



            $('#language').on('change', function () {



              if ($(this).val() != '' && $(this).val() == 'OR') {

                var langs = $(this).val();

                var lang = langs.toLowerCase();

                var name = $("#name").val();

                var address = $("#txtSource").val();



                $.post("<?php echo "http://" . $_SERVER['SERVER_NAME'] . '/admin/'; ?>test", { lang: lang, name: name, address: address }).done(function (data) {

                  //alert(data);

                  var json = JSON.parse(data);

                  //alert(json.data);

                  $("[name='namelocal']").val(json.name.replace(/"/g, ''));

                  $("[name='addresslocal']").val(json.address.replace(/"/g, ''));

                })



                var dob = $("#birthtithi").val();
                if ($(this).val() == 'HI' && $("#gender").val() == 'Male') {
                  var gender = $("#gender").val('पुरुष');
                }
                else {
                  var gender = $("#gender").val();
                }



                $.post("<?php echo "http://" . $_SERVER['SERVER_NAME'] . '/admin/'; ?>test", { lang: lang, name: dob, address: gender }).done(function (data) {

                  //alert(data);

                  var json = JSON.parse(data);

                  //alert(json.data);

                  $("[name='birthtithilocal']").val(json.name.replace(/"/g, ''));

                  $("[name='genderlocal']").val(json.address.replace(/"/g, ''));

                })









              }

              else {

                changelang();

              }



            });



          </script>
          <script type="text/javascript">

            //English to hindi translate code

            function changelang() {

              var lang = document.getElementById("language").value;

              //alert(lang);

              var url =

                "https://translate.googleapis.com/translate_a/single?client=gtx";

              url += "&sl=" + 'EN';

              url += "&tl=" + lang;

              url += "&dt=t&q=" + escape($("#txtSource").val());

              //alert(url);

              $.get(url, function (data, status) {

                var result = '';

                for (var i = 0; i <= 500; i++) {

                  result += data[0][i][0];

                  //alert(result);

                  $("#txtTarget").val(result);



                }

              });



              url =

                "https://translate.googleapis.com/translate_a/single?client=gtx";

              url += "&sl=" + 'EN';

              url += "&tl=" + lang;

              url += "&dt=t&q=" + escape($("#name").val());

              //alert(url);

              $.get(url, function (data, status) {

                var result = '';

                for (var i = 0; i <= 500; i++) {

                  result += data[0][i][0];

                  // alert(result);

                  $("#name_regional").val(result);



                }

              });






              var gen = $("#gender").val();

              url =

                "https://translate.googleapis.com/translate_a/single?client=gtx";

              url += "&sl=" + 'EN';

              url += "&tl=" + lang;

              url += "&dt=t&q=" + escape(gen.toLowerCase());

              //alert(url);

              $.get(url, function (data, status) {

                var result = '';

                for (var i = 0; i <= 500; i++) {

                  result += data[0][i][0];

                  // alert(result);


                  if (result == 'नर') {
                    result = 'पुरुष';
                  }
                  $("#genderlocal").val(result);



                }

              });



              url =

                "https://translate.googleapis.com/translate_a/single?client=gtx";

              url += "&sl=" + 'EN';

              url += "&tl=" + lang;

              url += "&dt=t&q=" + escape($("#birthtithi").val());

              //alert(url);

              $.get(url, function (data, status) {

                var result = '';

                for (var i = 0; i <= 500; i++) {

                  result += data[0][i][0];

                  //alert(result);

                  $("#birthtithilocal").val(result);



                }

              });





              url =

                "https://translate.googleapis.com/translate_a/single?client=gtx";

              url += "&sl=" + 'EN';

              url += "&tl=" + lang;

              url += "&dt=t&q=" + escape($("#pata").val());

              //alert(url);

              $.get(url, function (data, status) {

                var result = '';

                for (var i = 0; i <= 500; i++) {

                  result += data[0][i][0];

                  // alert(result);

                  $("#patalocal").val(result);



                }

              });



            };

            //Words and Characters Count	

          </script>
          <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.maskedinput/1.4.1/jquery.maskedinput.js"
            integrity="sha256-fvFKHgcKai7J/0TM9ekjyypGDFhho9uKmuHiFVfScCA=" crossorigin="anonymous"></script>
          <script>


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
  < !--Bootstrap JS-- >


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
          </body>

          </html>