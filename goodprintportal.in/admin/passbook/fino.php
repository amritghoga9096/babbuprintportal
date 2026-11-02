<?php include('config.php'); error_reporting(0); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/png" sizes="16x16" href="https://hkbprint.in/assets/img/icons/logo-white.png">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<title>FINO DIGITAL PASSBOOK Priview</title>
<link href="https://fonts.googleapis.com/css?family=Hind" rel="stylesheet">
<link href="aadhar.css" type="text/css" rel="stylesheet">
<?php
$b = array('id'=>'','name'=>'','fathername'=>'','account'=>'','cif'=>'','ifsc'=>'','actype'=>'','branch'=>'','occuption'=>'','occupation'=>'','village'=>'','post'=>'','block'=>'','district'=>'','state'=>'','pincode'=>'','mobile'=>'','email'=>'','userid'=>'','date'=>'','create_time'=>'','status'=>'','payment_status'=>'','ret_wp_no'=>'','aadhar'=>'');
if(isset($_GET['searchid'])){
$searchid = mysqli_real_escape_string($ahk_conn,$_GET['searchid']);

mysqli_set_charset($ahk_conn,"utf8");
$a = mysqli_query($ahk_conn,"SELECT * FROM passbook Where id='".$searchid."' LIMIT 1");
if($a && mysqli_num_rows($a)>0){
    $b = mysqli_fetch_array($a, MYSQLI_ASSOC);
    if(!is_array($b)) $b = array();
}
$b = array_merge(array('id'=>'','name'=>'','fathername'=>'','account'=>'','cif'=>'','ifsc'=>'','actype'=>'','branch'=>'','occuption'=>'','occupation'=>'','village'=>'','post'=>'','block'=>'','district'=>'','state'=>'','pincode'=>'','mobile'=>'','email'=>'','userid'=>'','date'=>'','create_time'=>'','status'=>'','payment_status'=>'','ret_wp_no'=>'','aadhar'=>''), $b);
if(!isset($b['occuption']) || $b['occuption']===''){ if(isset($b['occupation'])) $b['occuption']=$b['occupation']; }
foreach($b as $k=>$v){ if($v===null) $b[$k]=''; }
}

$_e = function($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };

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

<div id="p1" style="overflow: hidden; position: relative; background-color: white; width: 909px; height: 1284px;">

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

#t1_1{left:142px;bottom:881px;letter-spacing:0.13px;}
#t2_1{left:144px;bottom:900px;letter-spacing:0.13px;}
#t3_1{left:142px;bottom:861px;letter-spacing:0.13px;}
#t4_1{left:469px;bottom:901px;letter-spacing:0.13px;}
#t5_1{left:144px;bottom:840px;letter-spacing:0.13px;}
#t6_1{left:144px;bottom:802px;letter-spacing:0.13px;}
#t7_1{left:144px;bottom:790px;letter-spacing:0.13px;}
#t8_1{left:144px;bottom:822px;letter-spacing:0.13px;}
#t9_1{left:469px;bottom:883px;letter-spacing:0.13px;}
#ta_1{left:469px;bottom:863px;letter-spacing:0.13px;}
#tb_1{left:142px;bottom:747px;letter-spacing:0.08px;}
#tc_1{left:142px;bottom:734px;letter-spacing:0.08px;}
#td_1{left:379px;bottom:745px;letter-spacing:0.07px;}
#te_1{left:379px;bottom:733px;letter-spacing:0.07px;}
#tf_1{left:379px;bottom:724px;letter-spacing:0.07px;}
#tg_1{left:362px;bottom:921px;letter-spacing:0.27px;}

.s1{font-size:12px;font-family:Courier;color:#000;}
.s2{font-size:9px;font-family:AAAMND-DroidSerif-Regular_1-;color:#BA68C8;}
.s3{font-size:9px;font-family:AAALMF-2BCarlito_21;color:#BA68C8;}
.s4{font-size:21px;font-family:AAAADM-DroidSerif-Bold_23;color:#BDBDBD;}
</style>
<!-- End inline CSS -->

<!-- Begin embedded font definitions -->
<style id="fonts1" type="text/css" >

@font-face {
	font-family: AAAADM-DroidSerif-Bold_23;
	src: url("fonts/AAAADM-DroidSerif-Bold_23.woff") format("woff");
}

@font-face {
	font-family: AAALMF-2BCarlito_21;
	src: url("fonts/AAALMF-2BCarlito_21.woff") format("woff");
}

@font-face {
	font-family: AAAMND-DroidSerif-Regular_1-;
	src: url("fonts/AAAMND-DroidSerif-Regular_1-.woff") format("woff");
}

</style>
<!-- End embedded font definitions -->

<!-- Begin page background -->
<div id="pg1Overlay" style="width:100%; height:100%; position:absolute; z-index:1; background-color:rgba(0,0,0,0); -webkit-user-select: none;"></div>
<div id="pg1" style="-webkit-user-select: none;"><object width="909" height="1284" data="1/1.svg" type="image/svg+xml" id="pdf1" style="width:909px; height:1284px; -moz-transform:scale(1); z-index: 0;"></object></div>
<!-- End page background -->


<!-- Begin text definitions (Positioned/styled in CSS) -->
<div class="text-container"><span id="t1_1" class="t s1">Customer ID : <?php echo $_e($b['cif'])?> </span>
<span id="t2_1" class="t s1">Account No : <?php echo $_e($b['account'])?> </span>
<span id="t3_1" class="t s1">Holder Name : <?php echo $_e($b['name'])?> </span>
<span id="t4_1" class="t s1">IFSC Code : <?php echo $_e($b['ifsc'])?> </span>
<span id="t5_1" class="t s1">Father/Husband : <?php echo $_e($b['fathername'])?> </span>
<span id="t6_1" class="t s1">Address : At- <?php echo $_e($b['village'])?>, Post: <?php echo $_e($b['post'])?>, Ps: <?php echo $_e($b['block'])?> </span>
<span id="t7_1" class="t s1">District: <?php echo $_e($b['district'])?> Pincode: <?php echo $_e($b['pincode'])?> </span>
<span id="t8_1" class="t s1">Product Type : <?php echo $_e($b['actype'])?> </span>
<span id="t9_1" class="t s1">Occuption : <?php echo $_e($b['occuption'])?> </span>
<span id="ta_1" class="t s1">Br Name : <?php echo $_e($b['branch'])?> </span>
<span id="tb_1" class="t s2">For any Queries / enquiry </span>
<span id="tc_1" class="t s2">Toll Free No. of our call Center : 022-6868 1414 </span>
<span id="td_1" class="t s3">Base branch address </span>
<span id="te_1" class="t s3">Mindspace, Plot No - Gen 2/1/F, Tower- 1 8 Floor,TTCIndustrialArea,MIDC </span>
<span id="tf_1" class="t s3">Shirwane Juinagar, Navi Mumbai - 400706 Mumbai, Maharashtra 400706 IN </span>
<span id="tg_1" class="t s4">PASSBOOK </span></div>
<!-- End text definitions -->
 <script>
        window.onload = function() { window.print();  }
    </script> 

</div>
</body>
</html>
