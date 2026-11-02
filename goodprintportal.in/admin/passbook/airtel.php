<?php include('config.php'); error_reporting(0); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=Edge" />
    <title>Airtel Passbook Printing</title>
    <link rel="icon" type="image/png" sizes="16x16" href="https://panel.sss00.xyz/admin/uploads/service_icom/support-icon-removebg-preview%20(1).png">
<link href="https://fonts.googleapis.com/css2?family=Space+Mono&display=swap" rel="stylesheet">
    <link href="aadhar.css" rel="stylesheet" type="text/css">

    <style>
        body {
            margin: 0;
            padding: 0;
        }

        #a4-container {
            position: relative;
            width: 794px; /* A4 width at 96 DPI */
            height: 1123px; /* A4 height at 96 DPI */
            background-image: url('1/airtel/Airtel Passbook.jpg');
            background-size: cover;
            background-repeat: no-repeat;
            font-family: 'Hind', sans-serif;
        }

        .text {
            position: absolute;
            font-size: 12px;
            color: #000;
        }

        .label {
            font-weight: bold;
        }

        #cif { top: 410px; left: 85px; font-weight: 600; /* Extra bold */ }
        #account { top: 424px; left: 536px; font-weight: 600; /* Extra bold */ font-size: 14px;   /* Increased from default 14px */ }
        #ifsc { top: 559px; left: 180px; }
        #aadhar { top: 574px; left: 180px; }
        #name { top: 425px; left: 153px; font-weight: 600; /* Extra bold */ }
        #father { top: 529px; left: 180px; }
        #father2 { top: 544px; left: 180px; }
        #address {
    top: 445px;
    left: 98px;
    width: 600px;
    line-height: 1.4;
    font-size: 14px;
    word-break: break-word;
}

        #branch { top: 588px; left: 180px; }
        #contact { bottom: 50px; left: 150px; font-size: 12px; }

        @media print {
            body, html {
                margin: 0;
                padding: 0;
                width: 794px;
                height: 1123px;
            }
        }
    </style>

    <script>
        document.addEventListener('contextmenu', event => event.preventDefault());
        window.onload = function () {
            window.print();
        };
    </script>

</head>
<body>
<?php
if (isset($_GET['searchid'])) {
    $searchid = mysqli_real_escape_string($ahk_conn, $_GET['searchid']);
    mysqli_set_charset($ahk_conn, "utf8");
    $result = mysqli_query($ahk_conn, "SELECT * FROM airtelpass WHERE id='$searchid'");
    $data = mysqli_fetch_assoc($result);
}
?>

<div id="a4-container">
    <div class="text" id="cif"><span class="label"></span> <?php echo isset($data['cif']) ? htmlspecialchars($data['cif']) : ''; ?></div>
    <div class="text" id="account"><span class="label"></span> <?php echo isset($data['account']) ? htmlspecialchars($data['account']) : ''; ?></div>
    <div class="text" id="ifsc"><span class="label"></span> <?php echo isset($data['ifsc']) ? htmlspecialchars($data['ifsc']) : ''; ?></div>
   <div class="text" id="aadhar">
    <span class="label"></span>
    <?php
        $aadhaar = isset($data['aadhar']) ? (string)$data['aadhar'] : '';
        if (strlen($aadhaar) >= 12) {
            echo substr($aadhaar, 0, 4) . ' ' . substr($aadhaar, 4, 4) . ' ' . substr($aadhaar, 8, 4);
        } else {
            echo htmlspecialchars($aadhaar);
        }
    ?>
</div>

    <div class="text" id="name"><span class="label"></span> <?php echo isset($data['name']) ? htmlspecialchars($data['name']) : ''; ?></div>
    <div class="text" id="father"><span class="label"></span> <?php echo isset($data['fathername']) ? htmlspecialchars($data['fathername']) : ''; ?></div>
     <div class="text" id="father2"><span class="label"></span> <?php echo isset($data['fathername']) ? htmlspecialchars($data['fathername']) : ''; ?></div>
  <div class="text" id="address">
    <b></b>
    Vill - <?php echo isset($data['village']) ? htmlspecialchars($data['village']) : ''; ?>,<br>
    Post - <?php echo isset($data['post']) ? htmlspecialchars($data['post']) : ''; ?> <?php echo isset($data['state']) ? htmlspecialchars($data['state']) : ''; ?> <?php echo isset($data['pincode']) ? htmlspecialchars($data['pincode']) : ''; ?>
</div>


    <div class="text" id="branch"><span class="label"></span> <?php echo isset($data['branch']) ? htmlspecialchars($data['branch']) : ''; ?></div>
   
</div>

</body>
</html>
