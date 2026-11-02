<?php include('config.php'); error_reporting(0); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/png" sizes="16x16" href="https://print.mtgcreation.in/assets/img/icons/logo-white.png">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<title>INDIA POST PASSBOOK Priview</title>
<link href="https://fonts.googleapis.com/css?family=Hind" rel="stylesheet">
<link href="aadhar.css" type="text/css" rel="stylesheet">
<?php
if(isset($_GET['searchid'])){
//$searchid =$_GET['searchid'];
$searchid = mysqli_real_escape_string($ahk_conn,$_GET['searchid']);

mysqli_set_charset($ahk_conn,"utf8");
$a = mysqli_query($ahk_conn,"SELECT * FROM postpass Where id='".$searchid."'");
$b = mysqli_fetch_array($a);

$searchid = $_GET['searchid'];
include('config.php');
$searchid = mysqli_fetch_assoc(mysqli_query($ahk_conn, "select * from postpass where id=" . $searchid . ""));
extract($searchid);


}
?>
<?php
function disable_right_click()
{
echo '  <script type="text/javascript">
   
var isNS = (navigator.appName == "Netscape") ? 1 : 0;
   
if(navigator.appName == "Netscape") document.captureEvents(Event.MOUSEDOWN||Event.MOUSEUP);
   
function mischandler(){
   
return false;
   
}
   
function mousehandler(e){
   
var myevent = (isNS) ? e : event;
   
var eventbutton = (isNS) ? myevent.which : myevent.button;
   
if((eventbutton==2)||(eventbutton==3)) return false;
   
}
   
document.oncontextmenu = mischandler;
   
document.onmousedown = mousehandler;
   
document.onmouseup = mousehandler;
   
</script>';
}
disable_right_click();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta http-equiv="X-UA-Compatible" content="IE=Edge" />
<meta charset="utf-8" />
</head>

<body style="margin: 0;">

<div id="p1" style="overflow: hidden; position: relative; background-color: white; width: 854px; height: 328px;">

<!-- Begin shared CSS values -->
<style class="shared-css" type="text/css" >
.t {
	transform-origin: bottom left;
	z-index: 2;
	position: absolute;
	white-space: pre;
	overflow: visible;
	line-height: 1.5;
}
.text-container {
	white-space: pre;
}
@supports (-webkit-touch-callout: none) {
	.text-container {
		white-space: normal;
	}
}
</style>
<!-- End shared CSS values -->


<!-- Begin inline CSS -->
<style type="text/css" >

#t1_1{left:580px;bottom:179px;letter-spacing:-0.2px;}
#t2_1{left:521px;bottom:156px;}
#t3_1{left:519px;bottom:88px;}
#t4_1{left:545px;bottom:132px;}
#t5_1{left:568px;bottom:132px;}
#t6_1{left:593px;bottom:131px;}
#t7_1{left:616px;bottom:131px;}
#t8_1{left:643px;bottom:131px;}
#t9_1{left:666px;bottom:131px;}
#ta_1{left:691px;bottom:131px;}
#tb_1{left:715px;bottom:131px;}
#tc_1{left:739px;bottom:131px;}
#td_1{left:762px;bottom:131px;}
#te_1{left:785px;bottom:131px;}
#tf_1{left:571px;bottom:90px;}
#tg_1{left:594px;bottom:109px;}
#th_1{left:616px;bottom:109px;}
#ti_1{left:642px;bottom:109px;}
#tj_1{left:666px;bottom:109px;}
#tk_1{left:689px;bottom:109px;}
#tl_1{left:715px;bottom:109px;}
#tm_1{left:738px;bottom:109px;}
#tn_1{left:761px;bottom:109px;}
#to_1{left:784px;bottom:109px;}
#tp_1{left:474px;bottom:71px;letter-spacing:-0.13px;}
#tq_1{left:490px;bottom:71px;letter-spacing:-0.21px;}
#tr_1{left:590px;bottom:69px;letter-spacing:-0.13px;}
#ts_1{left:606px;bottom:69px;letter-spacing:-0.21px;}
#tt_1{left:713px;bottom:71px;letter-spacing:-0.13px;}
#tu_1{left:730px;bottom:71px;letter-spacing:-0.21px;}
#tv_1{left:438px;bottom:50px;letter-spacing:-0.18px;}
#tw_1{left:720px;bottom:263px;letter-spacing:-0.21px;}

.s1{font-size:11px;font-family:AAAMLC-DejaVuSans-Bold_2g;color:#212121;}
.s2{font-size:14px;font-family:AAAMLC-DejaVuSans-Bold_2g;color:#212121;}
.s3{font-size:8px;font-family:AAAOYC-Lato-Bold_2i;color:#BDBDBD;}
</style>
<!-- End inline CSS -->

<!-- Begin embedded font definitions -->
<style id="fonts1" type="text/css" >

@font-face {
	font-family: AAAMLC-DejaVuSans-Bold_2g;
	src: url("fonts/AAAMLC-DejaVuSans-Bold_2g.woff") format("woff");
}

@font-face {
	font-family: AAAOYC-Lato-Bold_2i;
	src: url("fonts/AAAOYC-Lato-Bold_2i.woff") format("woff");
}

</style>
<!-- End embedded font definitions -->
<style>
h2 {
  letter-spacing: 14px;
}

h4 {
  letter-spacing: 17px;
}
</style>
<!-- Begin page background -->
<div id="pg1Overlay" style="width:100%; height:100%; position:absolute; z-index:1; background-color:rgba(0,0,0,0); -webkit-user-select: none;"></div>
<div id="pg1" style="-webkit-user-select: none;"><object width="854" height="328" data="1/post/1.svg" type="image/svg+xml" id="pdf1" style="width:854px; height:328px; -moz-transform:scale(1); z-index: 0;"></object></div>
<!-- End page background -->


<!-- Begin text definitions (Positioned/styled in CSS) -->
        <div class="text-container">
            <span id="t1_1" class="t s1"><B><?php echo $b['refrence'] ?></B></span>
            <span id="t2_1" class="t s2" style="color:#140f54"><?php echo $b['name'] ?> </span>
            <span id="t3_1" class="t s2"><h2><?php echo $b['account'] ?></h2> </span>
            <span id="tf_1" class="t s2"><h4><?php echo $b['cif'] ?></h4></span>
            <span id="tp_1" class="t s3">Dial </span><span id="tq_1" class="t s3">8424054994 </span>
            <span id="tr_1" class="t s3">Dial </span><span id="ts_1" class="t s3">8424046556 </span>
            <span id="tt_1" class="t s3">Dial </span><span id="tu_1" class="t s3">8424026886 </span>
            <span id="tv_1" class="t s3">Customer Care - 155229 /1800 180 7980 </span>
            <span id="tw_1" class="t s3">IPOS0000001 </span>
        </div>
        <!-- End text definitions -->

 <script>
        window.onload = function() { window.print();  }
    </script> 

</div>
</body>
</html>

