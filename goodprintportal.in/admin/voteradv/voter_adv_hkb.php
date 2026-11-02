<?php
$searchid = $_GET['searchid'];
include('../aadharpvc/config.php');
$searchid = mysqli_fetch_assoc(mysqli_query($ahk_conn, "select * from voterauto1 where voterautoid=" . $searchid . ""));
extract($searchid);

?>
<html lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<title>Voter Card Priview</title>
<link href="https://fonts.googleapis.com/css?family=Hind" rel="stylesheet">
<link href="aadhar.css" type="text/css" rel="stylesheet">
   <head>
   
   <!------------------------------ # connection ------------------------------->
						<?php
												error_reporting(0);
												include("../aadharpvc/config.php");

												
												$sqla="select * from setting";
												$updt = mysqli_query($ahk_conn,$sqla) ;
												$slct = mysqli_fetch_array($updt);
												//$slct = mysqli_fetch_assoc($r);
												//$slct['aadharpoint'];

												?>
												
						<!------------------------------ # connection ------------------------------->
   
  


<?php
if(isset($_GET['searchid'])){
//$searchid =$_GET['searchid'];
$searchid = mysqli_real_escape_string($ahk_conn,$_GET['searchid']);

mysqli_set_charset($ahk_conn,"utf8");
$a = mysqli_query($ahk_conn,"SELECT * FROM voterauto1 Where voterautoid='".$searchid."'");
$b = mysqli_fetch_array($a);
if ($b['locallanguage']==HI) { ?>
    <style>
	.firstpage { 
    background-image: url(baap/hindi.jpg);
    height: 1400px;
    background-size: contain;
    background-repeat: no-repeat;
    margin-right: 30px;
    width: 1000px;
    float: none;
    position: relative;
    font-size: 12px;
    margin-bottom: 20px;
        
}
	</style>
	<?php } elseif($b['locallanguage']==PA) { ?>
	
	 <style>
	.firstpage { 
    background-image: url(baap/pu.jpg);
    height: 1400px;
    background-size: contain;
    background-repeat: no-repeat;
    margin-right: 30px;
    width: 1000px;
    float: none;
    position: relative;
    font-size: 14px;
    margin-bottom: 20px;
        
}
	</style>
		<?php } elseif($b['locallanguage']==GU) { ?>
	
	 <style>
	.firstpage { 
    background-image: url(baap/gujarat.jpg);
    height: 1400px;
    background-size: contain;
    background-repeat: no-repeat;
    margin-right: 30px;
    width: 1000px;
    float: none;
    position: relative;
    font-size: 14px;
    margin-bottom: 20px;
        
}
	</style>	<?php } elseif($b['locallanguage']==MR) { ?>
	
	 <style>
	.firstpage { 
    background-image: url(baap/marathi.jpg);
    height: 1400px;
    background-size: contain;
    background-repeat: no-repeat;
    margin-right: 30px;
    width: 1000px;
    float: none;
    position: relative;
    font-size: 14px;
    margin-bottom: 20px;
        
}
	</style>	<?php } elseif($b['locallanguage']==TA) { ?>
	
	 <style>
	.firstpage { 
    background-image: url(baap/tamild.jpg);
    height: 1400px;
    background-size: contain;
    background-repeat: no-repeat;
    margin-right: 30px;
    width: 1000px;
    float: none;
    position: relative;
    font-size: 14px;
    margin-bottom: 20px;
        
}
	</style>	<?php } elseif($b['locallanguage']==KN) { ?>
	
	 <style>
	.firstpage { 
    background-image: url(baap/kannada.jpg);
    height: 1400px;
    background-size: contain;
    background-repeat: no-repeat;
    margin-right: 30px;
    width: 1000px;
    float: none;
    position: relative;
    font-size: 14px;
    margin-bottom: 20px;
        
}
	</style>	<?php } elseif($b['locallanguage']==BN) { ?>
	
	 <style>
	.firstpage { 
    background-image: url(baap/bangla.jpg);
    height: 1400px;
    background-size: contain;
    background-repeat: no-repeat;
    margin-right: 30px;
    width: 1000px;
    float: none;
    position: relative;
    font-size: 14px;
    margin-bottom: 20px;
        
}
	</style>	
	<?php } elseif($b['locallanguage']==TE) { ?>
	
	 <style>
	.firstpage { 
    background-image: url(baap/telegu.jpg);
    height: 1400px;
    background-size: contain;
    background-repeat: no-repeat;
    margin-right: 30px;
    width: 1000px;
    float: none;
    position: relative;
    font-size: 14px;
    margin-bottom: 20px;
        
}
	</style>
		<?php } elseif($b['locallanguage']==SD) { ?>
	
	 <style>
	.firstpage { 
    background-image: url(baap/sindhi.jpg);
    height: 1400px;
    background-size: contain;
    background-repeat: no-repeat;
    margin-right: 30px;
    width: 1000px;
    float: none;
    position: relative;
    font-size: 14px;
    margin-bottom: 20px;
        
}
	</style>
		<?php }
		elseif($b['locallanguage']=="OR") { ?>
	
	 <style>
	.firstpage { 
    background-image: url(baap/odia.jpg);
    height: 1400px;
    background-size: contain;
    background-repeat: no-repeat;
    margin-right: 30px;
    width: 1000px;
    float: none;
    position: relative;
    font-size: 14px;
    margin-bottom: 20px;
        
}

	</style>
	<?php } } ?>

<style>
    @font-face {
     font-family: mangal;
      src: url(baap/NotoSans-SemiBold_11);
     }
     
    main.bg {
    font-family: 'arial', mangal;
    font-weight: 600;
    }

     

.secondpage {
   font-size: 10px;
    font-weight: 600;
    box-sizing: border-box;
    padding: 14px;
    position: absolute;
    top: 112px;
    width: 275px;
    margin-left: 322px;
    left: 353px;
}
.gender span.label,.dob span.label {
    width: 16px;
    display: inline-block;
}

.imagecontainer {
       position: absolute;
    top: 268px;
    left: 59px;
}
.imagecontainers {
       position: absolute;
    top: 240px;
    left: 395px;
}

img.barcode {
    width: 108px;
    height: 108px;
    position: relative;
    top: 128px;
    right: 123px;

}

img.qrcode {
    width: 208px;
    height: 208px;
    position: relative;
    top: 258px;
    left: 20px;

}
img.picture {
    width: 98px;
    height: 132px;
    border: ridge;
    border-radius: 3px;
    border-color: #bfc1c6;
}

img.pictures {
    opacity: 0.7;
    width: 30px;
    height: 40px;
    border: ridge;
    border-radius: 0px;
    border-color: #bfc1c6;
}

.epicnumberback {
    position: absolute;
    top: 423px;
    right: 296px;
    font-size:11.5px;
}

.epicnumber {
    position: absolute;
    top: 258px;
    right: 296px;
    font-size:11.5px;
}
.epicnumberfront {
    position: absolute;
    top: 20px;
    left: 20px;
    font-size:6px;
  transform: rotate(-90deg);
}
.epicnumberphoto {
    position: absolute;
    top: 10px;
    left: -328px;
    font-size:12px;
}
.settable {
    position: absolute;
    top: 268px;
    left: 170px;
    
}

.tablecss 
    font-family:arial;
    font-size:10px;
    font-weight:bold;
  
}

.r_name {
    position: absolute;
    top: 321px;
    left: 25px;
}

.actual_name {
    position: absolute;
    left: 25px;
    top: 363px;
}
.actual {
    position: absolute;
    left: 25px;
    top: 603px;
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
    margin-top:-50px;
}

.address {
    margin-bottom: 48px;
}

.nirvachan .date {
    width: 250px;
    margin-bottom: 60px;
    float: left;
}

.dates {
    width: 250px;
    margin-top: -270px;
    margin-left: -98px;
    float: left;
}

.qrdate {
    width: 250px;
    margin-top: 135px;
    float: left;
    margin-left: -718px;
}

.nirvachan .nirvachanofficer {
    float: left;
    width: 245px;
    margin-right: 10px;
    text-align: left;
    position: relative;
}
.nirvachanofficers {
    float: left;
    width: 245px;
    margin-left: -40px;
    bottom:38px;
    text-align: left;
    position: relative;
}
img.officersign {
    position: absolute;
    height: 40px;
    width: 40px;
    top: -65px;
    left: 173px;
}

.nirvachan:before, .nirvachan:after {
    display: block;
    content: '';
    clear: both;
}

.nirvachan {
    margin-bottom: 140px;
    
}
.assemballysankhya .dates {
    margin-top: 30px;
    margin-left: -10px;
    font-size:14px;
    font-weight:bold;
}
.assemballysankhya {
    margin-bottom: 10px;
}

.bhagsankhya .regional {
    margin-bottom: 15px;
}

.bhagsankhyas .regionals {
    margin-bottom: 15px;
}
.img {
  -webkit-filter: grayscale(100%); /* Safari 6.0 - 9.0 */
  filter: grayscale(100%);
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
$searchid = mysqli_real_escape_string($ahk_conn,$_GET['searchid']);

mysqli_set_charset($ahk_conn,"utf8");
$a = mysqli_query($ahk_conn,"SELECT * FROM voterauto1 Where voterautoid='".$searchid."'");
$b = mysqli_fetch_array($a);

}
?>

   </head>
  
   <body>
        <!--<body onload="window.print();">-->
      <main class="bg">
         <div class="row">
             <div class="firstpage">
                
                 <div class="imagecontainer">
				<?php if(strpos($b['imagepathoriginal'],'data:image') !== false) { ?>
				<img src="<?php echo $b['imagepathoriginal']?>" class="picture">
				<?php } else { ?>
                    <img src="<?php echo $slct['weburl'].'admin/'.$b['imagepathoriginal']?>" class="picture">
				<?php } ?>
			
                </div>  
                
                 <div class="imagecontainers img">
                <div class="epicnumberfront font-bold"><?php echo $b['epicno'] ?></div>
                <div class="epicnumberphoto font-bold"><?php echo $b['epicno'] ?></div>
				<?php if(strpos($b['imagepathoriginal'],'data:image') !== false) { ?>
                    <img class="officersign" src="baap/Sign_new_.png">
				<img src="<?php echo $b['imagepathoriginal']?>" class="pictures">
				<?php } else { ?>
                    <img src="<?php echo $slct['weburl'].'admin/'.$b['imagepathoriginal']?>" class="pictures">
				<?php } ?>
			
                </div>  
                <?php
                $iparr = explode (" ", $b['kaname']); 
                $aaaa =  $iparr[1];
                  ?>
                  <div class="settable">
                       <table class="tablecss" style=" line-height: 1.2; font-weight:bold;font-size:12px">
                           <tr>
                               <td><?php echo $aaaa ?>: <?php echo $b['namelocal'] ?></td>
                               <td></td>
                           </tr>
                           <tr>
                               <td><?php echo 'Name' ?> <?php echo ': '.$b['votername'] ?></td>
                               <td></td>
                           </tr>
                           <tr>
                               <td><?php echo $b['spousenamelocal'].' '.$b['kaname'] ?> <?php echo ': '.$b['fathernamelocal'] ?></td>
                               <td></td>
                           </tr>
                           <tr>
                               <td><?php echo $b['spousename'].' Name'?> <?php echo ': '.$b['fathername'] ?></td>
                               <td></td>
                           </tr>
                           <tr>
                               <td><?php echo $b['sexlocal'] ?>/Gender : <?php echo $b['genderlocal'] ?> / <?php echo $b['gender'] ?></td>
                               <td></td>
                           </tr>
                           <tr>
                               <td><?php echo $b['dobinlocal'] ?> <br>Date Of Birth/Age <?php echo ': '.$b['dob'] ?></td>
                               <td> <br></td>
                           </tr>
                       </table>
                  </div>
                  <div class="imagecontainer">
                 <img src="baap/qrcode_yd656_h978c_hdg6.jpeg" class="qrcode">
               </div>

                                <!-- kannada start -->
                                <!-- kannada end -->
                <!-- tamil start -->
                                <!-- tamil end -->
                <!-- marathi start -->
                                <!-- marathi end -->
                <!-- language punjabi start -->
                                <!-- language punjabi end -->
                <!-- gujrati start -->
                                <!-- gujrati end -->
             </div>

             <div class="secondpage">
                 <img src="baap/qrcode_yd656_h978c_hdg6.jpeg" class="barcode">
                <div class="epicnumber font-bold"><?php echo $b['epicno'] ?></div>
                    

                <div class="address_regional">
                    <span class="label"><?php echo $b['pata'] ?> : </span>
                    <span class="value"><?php echo $b['localaddress'] ?></span>
                </div>


                <div class="address">
                    <span class="label">Address : </span>
                    <span class="value"><?php echo $b['fulladdress'] ?></span>
                </div>
                

                <div class="imagecontainer">
                    <div class="nirvachanofficers">
                    <span class="label"><?php echo $b['signlocal'] ?> : <?php echo $b['assconnonmlocal'] ?> <br>Electoral Registration Officer : <?php echo $b['assconnonm'] ?> </span>
                    <span class="value"></span>
                   </div>

                <div class="imagecontainer">
                  <div class="dates">
                    <span class="label"></span>
                    <span class="value">Download Date-: <?php echo date("d/m/Y"); ?></span>
                   </div>
                </div>
                <div class="assemballysankhya">
                  <div class="dates">
                <div class="regional font-bold" style="margin-top:90px;">: <?php echo $b['epicno'] ?></div>
                    
                   </div>

                <div class="assemballysankhya">
                  <div class="dates">
                   <span class="label" >: NA  </span>
                    <span class="value"><br></span>
                   </div>

                <div class="assemballysankhya">
                  <div class="dates">
                    <span class="label">: <?php echo $b['assconnonmlocal'] ?></span>
                    <span class="value"><br></span>
                   </div>

                <div class="assemballysankhya">
                   <div class="dates" >: <?php echo $b['assconnonm'] ?></span>
                    <span class="value"><br></span>
                   </div>
                </div> 

                <div class="assemballysankhya">
                  <div class="dates">
                    <span class="label">: <?php echo $b['partno'] ?>  <?php echo $b['partnamelocal'] ?></span>
                    <span class="value">
                   </div>
                      <div class="dates">
                   <div class="label">: <?php echo $b['partno'] ?>  <?php echo $b['partname'] ?></span>
                    <span class="value">
                   </div>
                </div>  
                
                 <div class="bhagsankhyas">
                  <div class="dates">
                    <span class="label" style="font-size:14px">: <?php echo $b['partnamelocal'] ?></span>
                    <span class="value">
                   </div>
                <div class="assemballysankhya">
                      <div class="dates">
                   <div class="label">: <?php echo $b['partname'] ?></span>
                    <span class="value">
                   </div>
                </div>  
                <div class="imagecontainer">
                  <div class="qrdate">
                    <span class="label"></span>
                    <span class="value"  style="font-size:15px">Download Date-: <?php echo date("d/m/Y"); ?></span>
                   </div>
                </div>
               <!-- kannda start -->
                              <!-- kannda end -->

               <!-- tamil start -->
                              <!-- tamil end -->

               <!-- marathi start -->
                              <!-- marathi end -->
               <!-- gujrati start -->
                               <!-- gujrati end -->
                <!-- punjabi start -->
                                <!-- punjabi end -->
             </div>   
         </div>   
      </main>  
   </body> 
</html>
<script>window.print();
</script>