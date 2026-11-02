<?php include '../database.php';?>
 
<html lang="en">
<head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">

<title>Voter Card Priview</title>
<link href="assamvoter/css" rel="stylesheet">
<link href="assamvoter/aadhar.css" type="text/css" rel="stylesheet">

<?php 
$sid = $_GET['searchid'];
$get = mysqli_fetch_assoc(mysqli_query($conn,"select * from voterimg where id=".$sid.""));
?>

												
<style>
	.firstpage {
    background-image: url(ASSAM.png);
    height: 500px;
    background-size: contain;
    background-repeat: no-repeat;
    margin-right: 30px;
    width: 320px;
    float: none;
    position: relative;
    font-size: 14px;
    margin-bottom: 20px;
        
}
    .secondpage {
    background-image: url(aASSAM.png);
    height: 500px;
    background-size: contain;
    background-repeat: no-repeat;
    margin-right: 30px;
    width: 320px;
    float: none;
    position: relative;
}
	</style>
<style>
    @font-face {
     font-family: mangal;
      src: url(font/MANGAL.TTF);
     }
     
    main.bg {
    font-family: 'arial', mangal;
    font-weight: 600;
    }

     

.secondpage {
    font-size: 9px;
    font-weight: 600;
    box-sizing: border-box;
    padding: 14px;
}
.gender span.label,.dob span.label {
    width: 116px;
    display: inline-block;
}

.imagecontainer {
    position: absolute;
    top: 123px;
    left: 91px;
}

img.picture {
       width: 143px;
    height: 186px;
	margin-left: -6px;
    margin-top: 15px;
}

img.barcode {
   width: 151px;
    height: 22px;
    position: absolute;
    top: 100px;
    left: 150px;
}

.epicnumber {
    position: absolute;
    top: 103px;
    left: 50px;
}

.settable {
        position: absolute;
    top: 355px;
    left: 84px;
    
}

.tablecss 
    font-family:arial;
    font-size:14px;
    font-weight:bold;
  
}

.r_name {
    position: absolute;
    top: 331px;
    left: 25px;
}

.actual_name {
    position: absolute;
    left: 25px;
    top: 363px;
}

.father_name {
    position: absolute;
    left: 25px;
    top: 395px;
}

.father_name_actual {
    position: absolute;
    left: 25px;
    top: 427px;
}

.gender span.value {
    text-transform: capitalize;
}

.secondpage .gender {
    margin-bottom: 4px;
}

.secondpage .dob {
    position: relative;
    margin-bottom: 4px;
}

.secondpage .dob span.value {
    top: -5px;
    position: relative;
}

.address_regional {
    margin-bottom: 5px;
}

.address {
    margin-bottom: 60px;
}

.nirvachan .date {
    width: 100px;
    float: left;
}

.nirvachan .nirvachanofficer {
    float: right;
    width: 160px;
    text-align: right;
    position: relative;
}

img.officersign {
    position: absolute;
    top: -69px;
    left: 20px;
}

.nirvachan:before, .nirvachan:after {
    display: block;
    content: '';
    clear: both;
}

.nirvachan {
    margin-bottom: 10px;
}
.assemballysankhya .regional {
    margin-bottom: 4px;
}
.assemballysankhya {
    margin-bottom: 10px;
}

.bhagsankhya .regional {
    margin-bottom: 4px;
}

@media  print
{
    * {-webkit-print-color-adjust:exact;}
}
@page  { size: auto;  margin: 0mm; }
@media  print {
    a[href]:after {
        content: none !important;
    }
}
@media  print {
.header, .hide { visibility: hidden }
form.bootom-form{display:none;}
}
    </style>
<?php
if(isset($_GET['searchid'])){
//$searchid =$_GET['searchid'];
$searchid = mysqli_real_escape_string($conn,$_GET['searchid']);

mysqli_set_charset($conn,"utf8");
$a = mysqli_query($conn,"SELECT * FROM voterimg Where id='".$searchid."'");
$b = mysqli_fetch_array($a);

}
?>

 <style type="text/css">* {<br>		-webkit-user-select: text !important;<br>		-moz-user-select: text !important;<br>		-ms-user-select: text !important;<br>		 user-select: text !important;<br>	}</style><style type="text/css">* {<br>        -webkit-user-select: text !important;<br>        -moz-user-select: text !important;<br>        -ms-user-select: text !important;<br>         user-select: text !important;<br>    }</style></head>
<body onload="window.print();">
<main class="bg">
<div class="row">
<div class="firstpage">
                <img src="http://bwipjs-api.metafloor.com/?bcid=code128&text=<?php echo $b['epicno'] ?>" class="barcode">
                <div class="epicnumber"><?php echo $b['epicno'] ?></div>
                <div class="imagecontainer">
				<?php if(strpos($b['imagepathoriginal'],'data:image') !== false) { ?>
				<img src="<?php echo $b['imagepathoriginal']?>" class="picture">
				<?php } else { ?>
                    <img src="<?php echo $slct['weburl'].'admin/'.$b['imagepathoriginal']?>" class="picture">
				<?php } ?>
                </div>  
<div class="settable">
<table class="tablecss" style="font-weight:bold;font-size:14px">
<tbody><tr>
<td style="padding-bottom: 15px;"><?php echo $b['namelocal'];?>  </td>
</tr>
<tr>
<td style="padding-bottom: 15px;"> <?php echo $b['votername'];?></td>
</tr>
<tr>
<td style="padding-bottom: 15px;"> <?php echo $b['fathernamelocal']; ?></td>
</tr>
<tr>
   
   <td style="padding-bottom: 15px;"><?php echo ': '.$b['fathername'] ?></td>
</tr>
</tbody></table>
</div>


</div>
<div class="secondpage">
<div class="gender">
 <span class="label"><?php echo $b['sexlocal'] ?>/Sex</span>
                    <span class="value">: <?php echo $b['genderlocal'] ?> / <?php echo $b['gender'] ?></span>
</div>
<div class="dob">
 
  <span class="label"><?php echo $b['dobinlocal'] ?> <br>Date Of Birth/Age</span>
                    <span class="value">: <?php echo $b['dob'] ?></span>
</div>
<div class="address_regional">
  <span class="label"><?php echo $b['pata'] ?> : </span>
                    <span class="value"><?php echo $b['localaddress'] ?></span>
</div>
<div class="address">
<span class="label">Address : </span>
                    <span class="value"><?php echo $b['fulladdress'] ?></span>
</div>
<div class="nirvachan">
<div class="date">
<span class="label">Date:</span>
<span class="value"><?php echo date("d/m/Y"); ?></span>
</div>
<div class="nirvachanofficer">

 <img class="officersign" src="../baap/votersign2.png">
                  
                  <span class="label"><?php echo $b['signlocal'] ?><br>Electoral Registration Officer</span>
                    <span class="value"></span>
</div>
</div>
<div class="assemballysankhya">
<div class="regional">
   <span class="label"><?php echo $b['assconnamenolocal'] ?> : </span>
                    <span class="value"><br><?php echo $b['assconnonmlocal'] ?></span>
</div>
<div class="actual">Assembly Constituency No. &amp; Name : 
  <span class="value"><br><?php echo $b['assconnonm'] ?></span></div>
</div>
<div class="bhagsankhya">
<div class="regional">
      <span class="label"><?php echo $b['partnoandnamelocal'] ?> : </span>
                    <span class="value"><?php echo $b['partno'] ?>  <?php echo $b['partnamelocal'] ?></span>
</div>
<div class="actual">Part No. &amp; Name : 
                    <span class="value"><?php echo $b['partno'] ?>  <?php echo $b['partname'] ?></span>
 </div>
</div>










</div>
</div>
</main>

</body></html>