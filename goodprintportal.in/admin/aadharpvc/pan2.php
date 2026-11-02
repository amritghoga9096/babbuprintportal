<?php include('config.php'); error_reporting(0); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<title>Pan Card Priview</title>
<link href="https://fonts.googleapis.com/css?family=Hind" rel="stylesheet">
<link href="aadhar.css" type="text/css" rel="stylesheet">
<?php
if(isset($_GET['searchid'])){
//$searchid =$_GET['searchid'];
$searchid = mysqli_real_escape_string($ahk_conn,$_GET['searchid']);

mysqli_set_charset($ahk_conn,"utf8");
$a = mysqli_query($ahk_conn,"SELECT * FROM panauto Where id='".$searchid."'");
$b = mysqli_fetch_array($a);

}
?>
<?php 
  $sid = $_GET['searchid'];
  $get = mysqli_fetch_assoc(mysqli_query($ahk_conn,"select * from panauto where id=".$sid.""));
  
  $setsql = "select weburl from setting limit 1";
  $setq = mysqli_query($ahk_conn,$setsql);
  $slctweb = mysqli_fetch_assoc($setq);
  $weburl = isset($slctweb['weburl']) ? rtrim($slctweb['weburl'],'/') : '';

  function encodeImgUrlPan2($path) {
      if(empty($path) || substr($path,0,5)=='data:' || substr($path,0,4)=='http') return $path;
      $parts = explode('/', $path);
      $encoded = [];
      foreach($parts as $p) {
          $encoded[] = rawurlencode($p);
      }
      return implode('/', $encoded);
  }

  function resolveImgPan2($path, $weburl, $fallback='images/q.jpg') {
      if(empty($path)) return $fallback;
      if(substr($path,0,10)=='data:image') return $path;
      if(substr($path,0,7)=='http://' || substr($path,0,8)=='https://') return $path;
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
              return encodeImgUrlPan2($lp);
          }
      }
      if(substr($path,0,6)=='admin/') {
          if(!empty($weburl)) return encodeImgUrlPan2($weburl.'/'.$path);
          return encodeImgUrlPan2('../'.substr($path,6));
      }
      if(!empty($weburl)) {
          if(substr($path,0,7)=='uploads') return encodeImgUrlPan2($weburl.'/admin/'.$path);
          return encodeImgUrlPan2($weburl.'/'.$path);
      }
      return encodeImgUrlPan2('../uploads/'.$bn);
  }

  $_imgdb2     = (isset($b['image'])?$b['image']:(isset($get['image'])?$get['image']:''));
  $_signimgdb2 = (isset($b['signimage'])?$b['signimage']:(isset($get['signimage'])?$get['signimage']:''));
  $_img2     = resolveImgPan2($_imgdb2, $weburl);
  $_signimg2 = resolveImgPan2($_signimgdb2, $weburl);
  $_onerror2 = "this.onerror=null;this.removeAttribute('src');this.style.background='#eee';this.alt='Not found';";
  ?>
<!--DEBUG_PAN2_IMG
P2_PHOTO_DB: <?php echo htmlspecialchars($_imgdb2); ?> 
P2_PHOTO_RESOLVED: <?php echo htmlspecialchars($_img2); ?>
P2_SIGN_DB: <?php echo htmlspecialchars($_signimgdb2); ?> 
P2_SIGN_RESOLVED: <?php echo htmlspecialchars($_signimg2); ?>
P2_CWD: <?php echo getcwd(); ?>
P2_WEBURL: <?php echo htmlspecialchars($weburl); ?>
-->
<?php
if($get['userid'] == 2) 
{
	?>
	<style type="text/css">
.bg {
    background: url('2pana.jpg') no-repeat;
    width: 800px;
    height: 986px;
    overflow: visible;
    display: block;
    background-size: 100% auto;
    
}

</style>
	<?php 
}
else 
{
?>
<style type="text/css">
.bg {
    background: url('2pana.jpg') no-repeat;
    width: 800px;
    height: 986px;
    overflow: visible;
    display: block;
    background-size: 100% auto;
}

</style>
<?php } ?>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  </head>
 
    <main class="bg">
    
   
     
<?php 

   /// qr code xml string start
  /* libxml_use_internal_errors(true);
   $simplexml= new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><books/>');
   $book1= $simplexml->addChild('book',"<PrintLetterBarcodeData uid=&quot;".$b['panno']."&quot; name=&quot;".$b['name']."&quot; dob=&quot;".$b['dob']."&quot; co=&quot;".$b['fathername']."&quot;/>");
   $str='<?xml version="1.0" encoding="UTF-8"?>'.$book1;
   $codeContents = $str; 
  // echo $codeContents;
  // echo $book1;*/
?>

  
<style>
img.mrmins.mrninbigs {
    
    top: 59px;
    position: absolute;
    left: 247px;

}

.cimg 
{
	 top: 85px;
    position: absolute;
    left: 27px;
}
.mainno
{
    top: 102px;
    position: absolute;
    left: 142px;
    font-weight: 800;
    font-size: 11px;
    font-family: Arial;
    letter-spacing: 0.3px;
    font-weight: 600;
    font-size: 13px;
    color: #30353d;
}

.name 
{
	        top: 151px;
    position: absolute;
    left: 33px;
    font-weight: 800;
    font-size: 9px;
    font-family: Arial;
    letter-spacing: 0.1px;
    font-weight: 550;
    font-size: 9px;
    color: #30353d;
}

.fathername 
{
	    top: 181px;
    position: absolute;
    left: 34px;
    font-weight: 800;
    font-size: 9px;
    font-family: Arial;
    letter-spacing: 0.1px;
    font-weight: 550;
    font-size: 9px;
    color: #30353d;
}
.bod 
{
	    top: 218px;
    position: absolute;
    left: 33px;
    font-weight: 800;
    font-size: 9px;
    font-family: Arial;
    letter-spacing: 0.1px;
    font-weight: 550;
    font-size: 9px;
    color: #30353d;
    
}

.sign 
{
	top: 205px;
    position: absolute;
    left: 148px;
}
</style>

<!------------------------------ # connection ------------------------------->
						<?php
												error_reporting(0);
												include("config.php");

												
												$sqla="select * from setting";
												$updt = mysqli_query($ahk_conn,$sqla) ;
												$slct = mysqli_fetch_array($updt);
												//$slct = mysqli_fetch_assoc($r);
												//$slct['aadharpoint'];

												?>
												
						<!------------------------------ # connection ------------------------------->


</p>


    <!-- <img class="mrmins mrninbigs" src='https://chart.googleapis.com/chart?chs=110x110&cht=qr&chl=<?php echo $codeContents; ?>&chld=L|0&chf=bg,s,FFFFFF00' > -->
	 
	
	 <?php 
	 $_rawimg = isset($b['image']) ? $b['image'] : '';
	 if(substr($_rawimg,0,10)=='data:image') {?>
	 <img class="cimg" src="<?php echo $_rawimg;?>" onerror="<?php echo $_onerror2;?>" width="60px" height="60px"/>
	 <?php } else if(substr($_rawimg,0,7)=='http://' || substr($_rawimg,0,8)=='https://') {?>
	 <img class="cimg" src="<?php echo $_rawimg;?>" onerror="<?php echo $_onerror2;?>" width="60px" height="60px"/>
	 <?php } else { ?>
     <img class="cimg" src="<?php echo $_img2;?>" onerror="<?php echo $_onerror2;?>" width="60px" height="60px"/>
	 <?php } ?>
	 <p class="mainno"><B><?php echo $b['panno'];?></B></p>
     <p class="name"><?php echo strtoupper($b['name']);?></p>
	 <p class="fathername"><?php echo strtoupper($b['fathername']);?></p>
	 <p class="bod"><?php echo $b['dob'];?></p>
	 <?php
	 $_rawsign = isset($b['signimage']) ? $b['signimage'] : '';
	 if(substr($_rawsign,0,10)=='data:image') {?>
	 <img class="sign" src="<?php echo $_rawsign;?>" onerror="<?php echo $_onerror2;?>" width="65px" height="25px"/>
	 <?php } else if(substr($_rawsign,0,7)=='http://' || substr($_rawsign,0,8)=='https://') {?>
	 <img class="sign" src="<?php echo $_rawsign;?>" onerror="<?php echo $_onerror2;?>" width="65px" height="25px"/>
	 <?php } else { ?>
	 <img class="sign" src="<?php echo $_signimg2;?>" onerror="<?php echo $_onerror2;?>" width="65px" height="25px"/>
	 <?php } ?>
  </main>
  </body>
</html>