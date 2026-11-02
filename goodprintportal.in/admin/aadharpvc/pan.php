<?php include('../../includes/database.php'); error_reporting(0); ?>
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xml:lang=""><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>Pan Card Priview</title>
<link href="https://fonts.googleapis.com/css?family=Hind" rel="stylesheet">
<link href="aadhar.css" type="text/css" rel="stylesheet">
<style type="text/css">
.bg {
    background: url('.jpg') no-repeat;
    width: 800px;
    height: 986px;
    overflow: visible;
    display: block;
    background-size: 100% auto;
}
</style>
<?php
if(isset($_GET['searchid'])){
$searchid = mysqli_real_escape_string($ahk_conn,$_GET['searchid']);
mysqli_set_charset($ahk_conn,"utf8");
$a = mysqli_query($ahk_conn,"SELECT * FROM panauto Where id='".$searchid."'");
$b = mysqli_fetch_array($a);
}
$sid = isset($_GET['searchid']) ? $_GET['searchid'] : 0;
$get = mysqli_fetch_assoc(mysqli_query($ahk_conn,"select * from panauto where id=".$sid.""));
$setsql = "select weburl from setting limit 1";
$setq = mysqli_query($ahk_conn,$setsql);
$slctweb = mysqli_fetch_assoc($setq);
$weburl = isset($slctweb['weburl']) ? rtrim($slctweb['weburl'],'/') : '';

function encodeImgUrl($path) {
    if(empty($path) || substr($path,0,5)=='data:' || substr($path,0,4)=='http') return $path;
    $parts = explode('/', $path);
    $encoded = [];
    foreach($parts as $p) {
        $encoded[] = rawurlencode($p);
    }
    return implode('/', $encoded);
}

function resolveImg($path, $weburl, $fallback='images/q.jpg') {
    if(empty($path)) return $fallback;
    if(substr($path,0,10)=='data:image') return $path;
    if(substr($path,0,7)=='http://' || substr($path,0,8)=='https://') return $path;
    $orig = $path;
    $path = ltrim($path,'/');
    $bn = basename($path);

    $try_local = [
        $path,
        '../'.$path,
        '../../admin/'.$path,
        'uploads/'.$bn,
        '../uploads/'.$bn,
        '../../admin/uploads/'.$bn,
    ];
    foreach($try_local as $lp) {
        if(file_exists($lp)) {
            return encodeImgUrl($lp);
        }
    }
    if(substr($path,0,6)=='admin/') {
        if(!empty($weburl)) return encodeImgUrl($weburl.'/'.$path);
        return encodeImgUrl('../'.substr($path,6));
    }
    if(!empty($weburl)) {
        if(substr($path,0,7)=='uploads') return encodeImgUrl($weburl.'/admin/'.$path);
        return encodeImgUrl($weburl.'/'.$path);
    }
    return encodeImgUrl('../uploads/'.$bn);
}
$_imgdb     = (isset($b['image'])?$b['image']:(isset($get['image'])?$get['image']:''));
$_signimgdb = (isset($b['signimage'])?$b['signimage']:(isset($get['signimage'])?$get['signimage']:''));
$_img     = resolveImg($_imgdb, $weburl);
$_signimg = resolveImg($_signimgdb, $weburl);
?>
<!--DEBUG_PAN_IMG
PHOTO_DB: <?php echo htmlspecialchars($_imgdb); ?> 
PHOTO_RESOLVED: <?php echo htmlspecialchars($_img); ?>
PHOTO_FE: <?php $t=''; foreach([$_imgdb,'../'.$_imgdb,'../../admin/'.$_imgdb,'../uploads/'.basename($_imgdb)] as $p){ if(file_exists($p)){$t=$p.' YES'; break;} else {$t.=$p.' NO,';} } echo $t; ?>
SIGN_DB: <?php echo htmlspecialchars($_signimgdb); ?> 
SIGN_RESOLVED: <?php echo htmlspecialchars($_signimg); ?>
SIGN_FE: <?php $t=''; foreach([$_signimgdb,'../'.$_signimgdb,'../../admin/'.$_signimgdb,'../uploads/'.basename($_signimgdb)] as $p){ if(file_exists($p)){$t=$p.' YES'; break;} else {$t.=$p.' NO,';} } echo $t; ?>
CWD: <?php echo getcwd(); ?>
WEBURL: <?php echo htmlspecialchars($weburl); ?>
-->

<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<style type="text/css">
<!--
	p {margin: 0; padding: 0;}	.ft00{font-size:22px;font-family:Helvetica;color:#000000;}
	.ft01{font-size:13px;font-family:Helvetica;color:#000000;}
	.ft02{font-size:9px;font-family:Helvetica;color:#000000;}
	.ft03{font-size:11px;font-family:Helvetica;color:#000000;}
	.ft04{font-size:14px;font-family:Helvetica;color:#000000;}
	.ft05{font-size:11px;line-height:11px;font-family:Helvetica;color:#000000;}
-->
</style>
<?php
$_panno = isset($b['panno']) ? $b['panno'] : '';
$_name  = isset($b['name']) ? strtoupper($b['name']) : '';
$_dob   = isset($b['dob']) ? $b['dob'] : '';
$_gend  = isset($b['gender']) ? $b['gender'] : '';
$_qrdata = urlencode('<?xml version="1.0" encoding="UTF-8"?><PrintLetterBarcodeData uid="'.$_panno.'" name="'.$_name.'" dob="'.$_dob.'" gender="'.$_gend.'" co="'.$_name.'"/>');
$first = 'https://api.qrserver.com/v1/create-qr-code/?size=50x100&data='.$_qrdata.'&chld=L|0&chf=bg,s,FFFFFF00';
$second ='https://api.qrserver.com/v1/create-qr-code/?size=100x100&data='.$_qrdata.'&chld=L|0&chf=bg,s,FFFFFF00';
$_onerror = "this.onerror=null;this.removeAttribute('src');this.style.background='#eee';this.alt='Image not found';";
?>
<div id="page1-div" style="position:relative;width:892px;height:1263px;">
<img width="892" height="1263" src="/admin/aadharpvc/NSDL.jpg" alt="background image">
<p style="position:absolute;top:227px;left:368px;white-space:nowrap" class="ft00"><b><?php echo strtoupper($b['panno']); ?></b></p>
<p style="position:absolute;top:278px;left:278px;white-space:nowrap" class="ft01"><b><?php echo strtoupper($b['name']);?></b></p>
<p style="position:absolute;top:335px;left:278px;white-space:nowrap" class="ft01"><b><?php echo strtoupper($b['fathername']);?></b></p>
<p style="position:absolute;top:384px;left:278px;white-space:nowrap" class="ft01"><b><?php echo str_replace('-','/',$b['dob']); ?></b></p>
<p style="position:absolute;top:432px;left:278px;white-space:nowrap" class="ft01"><b><?php echo $b['gender'];?></b></p>
<p style="position:absolute;top:485px;left:278px;white-space:nowrap" class="ft01"><b>	 <img class="cimgn" src="<?php echo $_signimg;?>" onerror="<?php echo $_onerror;?>" width="165px" height="75px">
	 </b></p>

<p style="position:absolute;top:497px;left:74px;white-space:nowrap" class="ft01"><b>	 <img class="cimg" src="<?php echo $_img;?>" onerror="<?php echo $_onerror;?>" width="103px" height="100px">
	 </b></p>



<p style="position:absolute;top:1167px;left:185px;white-space:nowrap" class="ft01"><b>	 <img class="cimgn" src="<?php echo $_signimg;?>" onerror="<?php echo $_onerror;?>" width="95px" height="37px">
	 </b></p>
	 
<p style="position:absolute;top:1079px;left:165px;white-space:nowrap" class="ft01"><b><?php echo strtoupper($b['panno']); ?></b></p>

<p style="position:absolute;top:1046px;left:70px;white-space:nowrap"  class="ft01"><img src="<?php echo $_img;?>" onerror="<?php echo $_onerror;?>" width="62px" height="62px"></p>


<p style="position:absolute;top:1127px;left:70px;white-space:nowrap" class="ft02"><b><?php echo strtoupper($b['name']);?></b></p>
<p style="position:absolute;top:1158px;left:70px;white-space:nowrap" class="ft02"><b><?php echo strtoupper($b['fathername']);?></b></p>
<p style="position:absolute;top:1198px;left:70px;white-space:nowrap" class="ft02"><b><?php echo str_replace('-','/',$b['dob']); ?></b></p>
<p style="position:absolute;top:523px;left:696px;white-space:nowrap" class="ft05"><b>Digitally Signed by : Income<br>Tax Department</b></p>
<p style="position:absolute;top:557px;left:696px;white-space:nowrap" class="ft05"><b>Date : <?php echo strtoupper($b['create_time']);?><br>Reason : Document Signer<br>Location : India</b></p>
<p style="position:absolute;top:490px;left:696px;white-space:nowrap" class="ft04"><b>Signature Verified</b></p>
</div>


<script>


    window.print();


</script></body>
</html>
