<?php 
include('config.php'); 
error_reporting(0); 
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<title>School ID Card Preview</title>
<link href="https://fonts.googleapis.com/css?family=Hind" rel="stylesheet">
<!-- Add this in the <head> -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">

<?php
if(isset($_GET['searchid'])){
    $searchid = mysqli_real_escape_string($ahk_conn,$_GET['searchid']);
    mysqli_set_charset($ahk_conn,"utf8");
    $a = mysqli_query($ahk_conn,"SELECT * FROM school_id WHERE id='".$searchid."'");
    $b = mysqli_fetch_array($a);
}
?>

<style type="text/css">
.bg {
    background: url('<?php echo "https://" . $_SERVER['HTTP_HOST'] . "/admin/aadharpvc/schoolpng.jpg"; ?>') no-repeat;
    width: 615px;
    height: 986px;
    background-size: 100% auto;
    position: relative;
    font-family: Arial, sans-serif;
}

/* Student Photo */
.cimg {
    position: absolute;
    top: 239px;
    left: 166px;
    width: 255px;
    height: 244px;

    /* Diamond (clip path) */
    clip-path: polygon(50% 0%, 100% 50%, 50% 100%, 0% 50%);

    border: 12px solid #00bfff;  /* border color */
    box-shadow: 0 4px 12px rgba(0,0,0,0.25);
    overflow: hidden;           /* hide outside area */
    border-radius: 12px;        /* optional soft corners */
}

/* Image inside stays upright */
.cimg img {
    width: 100%;
    height: 100%;
    object-fit: cover;   /* fills diamond nicely */
}


/* Class */
.class {
    position: absolute;
    top: 460px;
    left: 44px;
    font-size: 26px;
    font-weight: bold;
    color: #b00020;
}

/* Student Name */
.name {
    position: absolute;
    top: 505px;
    left: 132px;
    font-size: 30px;
    font-weight: bold;
    color: #006600;
    text-transform: uppercase;
}

/* Father Name */
.father {
    position: absolute;
    top: 585px;
    left: 251px;
    
    color: #000066;
    font-weight: bold;
    font-size: 24px;
    text-transform: capitalize;
    letter-spacing: 0.5px;
    font-family: 'Segoe UI', sans-serif;
    margin-top: 8px;
}

/* Mobile No */
.mobile {
    position: absolute;
    top: 644px;
    left: 252px;
    font-size: 22px;
    color: #b00020;
    font-weight: 700; /* Bold */
    font-family: 'Poppins', sans-serif;
}


/* Address */
.address {
    position: absolute;
    top: 718px;
    left: 251px;
    font-size: 20px;
    width: 296px;
    line-height: 28px;
    color: #000066;
     font-weight: 700; /* Bold */
    font-family: 'Poppins', sans-serif;
}


</style>
</head>

<body>
<main class="bg">

    <!-- Student Photo -->
    <?php if(!empty($b['image'])) { ?>
        <img class="cimg" src="<?php echo $slct['weburl'].'/admin/'.$b['image'];?>" />
    <?php } ?>

    <!-- Class -->
    <p class="class"><?php echo $b['panno']; ?></p>

    <!-- Name -->
    <p class="name"><?php echo strtoupper($b['name']); ?></p>

    <!-- Father Name -->
    <p class="father"><?php echo $b['fathername']; ?></p>

    <!-- Mobile No -->
    <p class="mobile"><?php echo $b['dob']; ?></p>

    <!-- Address -->
    <p class="address"><?php echo $b['gender']; ?></p>

  

</main>
</body>
</html>
