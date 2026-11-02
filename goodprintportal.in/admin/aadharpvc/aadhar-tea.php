<?php
include('config.php');
error_reporting(0);

if(isset($_GET['searchid'])){
//$searchid =$_GET['searchid'];
$searchid = mysqli_real_escape_string($ahk_conn,$_GET['searchid']);

mysqli_set_charset($ahk_conn,"utf8");
$a = mysqli_query($ahk_conn,"SELECT * FROM aadharauto Where aadharautoid='".$searchid."'");

if(isset($_GET['type']) && $_GET['type'] == 'dbt')
{
  $a = mysqli_query($ahk_conn,"SELECT * FROM aadharautodbt Where aadharautoid='".$searchid."'");
}

if(isset($_GET['type']) && $_GET['type'] == 'manual')
{
 $a = mysqli_query($ahk_conn,"SELECT * FROM aadharmanual Where aadharmanualid='".$searchid."'");
}


$b = mysqli_fetch_array($a);



/*
if(strtoupper($b['locallanguage']) == 'KN')
     {
          header("location:aadhar-kn.php?searchid=".$searchid);
          exit;
     }
else if(strtoupper($b['locallanguage']) == 'PA')
     {
          header("location:aadhar-pa.php?searchid=".$searchid);
          exit;
     }   
 
 /* 
else if(strtoupper($b['locallanguage']) == 'GU')
     {
          header("location:aadhar-gu.php?searchid=".$searchid);
          exit;
     } 
    

 else if(strtoupper($b['locallanguage']) == 'MR')
     {
          header("location:aadhar-mr.php?searchid=".$searchid);
          exit;
     }

else if(strtoupper($b['locallanguage']) == 'TA')
     {
          header("location:aadhar-ta.php?searchid=".$searchid);
          exit;
     } 


else if(strtoupper($b['locallanguage']) == 'BN')
     {
          header("location:aadhar-bn.php?searchid=".$searchid);
          exit;
     }
     
else if(strtoupper($b['locallanguage']) == 'TE')
     {
          header("location:aadhar-te.php?searchid=".$searchid);
          exit;
     } 
else if(strtoupper($b['locallanguage']) == 'OR')
     {
          header("location:aadhar-or.php?searchid=".$searchid);
          exit;
     }                           

*/


}
?>
<!DOCTYPE html>
<html lang="hi">
    <head>
        <title>PDF</title>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link href="https://fonts.googleapis.com/css?family=Roboto" rel="stylesheet"> 
        <link href="https://fonts.googleapis.com/css?family=Open+Sans" rel="stylesheet">
        <script>
        window.onload = function() { window.print();  }
    </script> 
        
    </head>

    <style type="text/css">
           body{
      font-family: 'Roboto', sans-serif;
    }
    h1, h2, h3, h4{
      font-family: 'Open Sans', sans-serif;
    }
    .clr{
      clear: both;
    }
    .pdf{
      /*width: 650px;*/
      width: 680px;
      height: 845px;
      border: solid 2px;
      margin: 10% auto;
    }
    .left{
      width: 48.7%;
      float: left;
      border-right: solid 2px;
      height: 100%;
    }
    .right{
      width: 48.8%;
      float: left;
      border-left: solid 2px;
      height: 100%;
    }
    .cntr{
      float: left;
      
    }
    img{
      width: 100%;
    }
    .img4{
      /*
      border-bottom: solid #e70000;
      */
    }
    p{
      font-size: 12px;
      text-align: center;
      margin: 0;
    }
    .rtt{
      float: left;
      width: 15%;
    }
    .rtt_rt{
      float: left;
        width: 146%;
        padding-left: 18%;
        margin-top: -10%;
    }
    .rtt p, .brcd p{
      transform: rotate(90deg);
      margin-top: 25px;
      font-size: 9px;
      font-weight: bold;
    }
    ul li{
      font-size: 10px;
      list-style: none;
      line-height: 11px;
    }
    ul{
      padding: 0;
      margin-top: 5px;
    }
    .vld{
      float: left;
      width: 52%;
      text-align: right;
    }
    .brcd{
      float: left;
      width: 26%;
      background: url("images/q.jpg") no-repeat;
      background-size: 50px;
      margin-top: 100px;
      padding-left: 15px;
      background-position-x: center;
    }
    .brcd.print {
        display: list-item;
        list-style-image: url("images/q.jpg");
        list-style-position: inside;
    }
    .prnt{
        position: absolute;
        margin-top: -60px;
    }
    .vld img{
      width: 153px;
    padding-top: 32px;
    }
    h4 span, h2 span{
      color: #e70000;
    }
    h4{
      font-size: 14px;
      text-align: center;
      padding-top: 20px;
      margin: 0;
    }
    h3{
      margin: 0 0 4px;
      text-align: center;
    }
    h3 span{
      border-bottom: solid 0;
    }
    h2{
      text-align: center;
      font-size: 18px;
      letter-spacing: 1px;
      font-weight: 500;
      margin: 0;
      border-bottom: solid #e70000;
    }
    .img2{
     /*
      border-top: dashed 1px;
      */
      margin-top: 5px;
      padding: 5px 0;
    }

    img.cut-008 {
    height: 8px;
}

    .a_lft{
    width: 20%;
    float: left;
    padding: 0 15px;
    padding-left: 24px;
    }
    .a_rgt{
      width: 68%;
      float: left;
    }
    .a_rgt ul{
      margin-top: 0;
      margin-bottom: 0;
    }
    .a_rgt img{
        width: 75px;
        float: right;
        position: absolute;
        margin: 25px 140px 0;
    }
    .adhr h2{
      font-size: 14px;
      border-top: solid #e70000 2px;
      border-bottom: 0;
    }
    .adhr p{
      font-size: 10px;
    }
    .adhr h3{
      font-size: 16px;
    }
    .img6{
      margin-top: 0px;
    height: 23px;
    }
    .adhr .brcd {
      width: 5%;
    }
    .adhr .brcd p {
        font-size: 6px;
        margin-top: 10px;
    }
    .b_rgt{
      width: 36%;
      float: right;
    }
    .b_lft{
      width: 64%;
      float: left;
    max-height: 112px;
    margin-top: 0%;
    }
    .adhr2 ul li{
      font-size: 10px;
      line-height: 10px;
    }
    .adhr2 ul{
      padding: 0;
      margin: 0;
      margin-bottom: 8px;
      margin-left: 10px;
    }
    .adhr2 ul li span{
      font-weight: bold;
    }
    .blank{
      min-height: 124px;
    }
    .adhr h3 span{
      border-bottom: solid 0px;
    }
    .cut{
      width: 12px;
      position: absolute;
      padding: 2px 0px 0px 5px;
    }
    .cut2{
      position: absolute;
      width: 8px;
      margin: 8px 0 0 -4px;
    }
    .brcd h5{
      margin: 0;
      font-weight: normal;
      font-size: 12px;
    }
    .brcd ul li{
      font-size: 7px;
      line-height: 8px;
    }
    .one{
        height: 500px;
    }
    .two{
        height: 110px;
    }
     .three{
        height: 178px;
    position: relative;
    margin-top: -11px;
    }
    .rtt_rt ul{
        width: 45%;
    }
    .info h4{
        color:#f60000;
        letter-spacing: .5px;
        text-transform: uppercase;
        font-size: 12px;
        padding-top: 10px;
    }
    .info ul li, .info2 ul li{
        font-size: 11px;
        line-height: 16px;
    }
    .info ul li span, .info2 ul li span{
        color:#f60000;
    }
    .info2 ul li{
        padding: 4px 0;
    }
    .info2, .info{
        padding: 0 20px;
    }
    .info li::before, .info2 li::before {
      content: "■";
      color: #D60F26;
      font-size: 12px;
      padding-right: 5px;
    }
    .info2 ul{
        border: solid 1px #666;
        padding: 5px;
    }
    .info2{
        padding: 0 15px;
    }
    .info2 ul li .brk, .info ul li .brk{
        color: #333;
        padding-left: 12px;
    }
    .img7{
        border-top: solid #e70000 2px;
        height: 18px;
        padding-top: 2px;
    }

    img.img2.img--013 {
    padding: 0;
    width: 332px;
    padding-left: 1px;
   }
   img.img6.image--010 {
    border: 0px;
    padding: 0px;
}
.right img.cut-008 {
    margin-left: 1px;
}

img.cut-verti {
    height: 841px;
    width: 10px;
}

p.download-date {
    font-weight: bold;
    transform: rotate(90deg);
    font-size: 7px;
    position: absolute;
    top: 97px;
    left: -31px;
}

p.download-date.issue {
    right: -314px;
}
    </style>

    <body>
        <div class="pdf">
            <div class="left">
                <div class="one">
                    <img src="images/te/img38.jpg">
                    <p> నమోదు / Enrollment No: 0475/11596/0045<?php echo $b['srno']?> </p>
                    <div class="rtt">
                        <p>Download&nbsp;Date:&nbsp;<?php echo date('d/m/Y')?></p>
                        <div class="clr"></div>
                    </div>
                    <div class="rtt_rt">
                        <ul>
                            <li>To</li>
                            <li><?php echo $b['localname']?></li>
                            <li><?php echo $b['aadharname']?></li>
                            <li><?php echo $b['fulladdress']?></li>
                        </ul>
                        <div class="clr"></div>
                    </div>

                    <div class="rtt">
                        <p style="margin-top: 77px;">Issue&nbsp;Date:&nbsp;<?php echo date('d/m/Y',strtotime('-30 days'))?></p>
                        <div class="clr"></div>
                    </div>
                    <div class="brcd print">
                        <div class="prnt">
                            <h5>Signature Valid</h5>
                            <ul>
                                <li>Digitally signed by DS</li>
                                <li>UNIQUE IDENTIFICATION</li>
                                <li>AUTHORITY OF INDIA 03</li>
                                <li>Date: 2019.05.23 03:02:17</li>
                                <li>IST</li>
                            </ul>
                        </div>
                        <div class="clr"></div>
                    </div>
                    <div class="vld">
<?php
if ($b['gender']=='MALE'){
  $sex='M';
} 
else {
  $sex='F';
}
   /// qr code xml string start
   libxml_use_internal_errors(true);
   $simplexml= new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><books/>');
   $book1= $simplexml->addChild('book',"<PrintLetterBarcodeData uid=&quot;".$b['aadharno']."&quot; name=&quot;".$b['aadharname']."&quot; gender=&quot;".$sex."&quot; dob=&quot;".$b['dob']."&quot; co=&quot;".$b['fathername']."&quot; po=&quot;".$b['vtcandpost']."&quot; address=&quot;".$b['fulladdress']."&quot; pc=&quot;".$b['pincode']."&quot;/>");
   $str='<?xml version="1.0" encoding="UTF-8"?>'.$book1;
   $codeContents = $str;

  
   ?>                   
                        <img src='https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo $codeContents;?>' title="" />
                        
                      
                        
                        <div class="clr"></div>
                    </div>

                    <div class="clr"></div>

                </div> <!--one-->

                <div class="two">
                    <img src="images/te/img34.jpg" class="aapka-aadhar">

                    <h3><span><?php echo $b['originalaadharno']?></span></h3>
                    
                    <img src="images/te/img36.jpg" class="meri-image">

                </div>  <!--two-->

                <div class="adhr">
                    <img src="images/image--008.png" class="cut-008">
                    <div class="three">
                        <p class="download-date">Download&nbsp;Date:&nbsp;<?php echo date('d/m/Y')?></p>
                        <img src="images/te/img28.jpg" class="img2">
                        <div class="a_lft">
        <?php if ($b['filetype']=="SSO"){ ?>
                        <img src="data:image/jpeg;base64,<?php echo $b['imgcode']?>" />
        <?php } elseif ($b['filetype']=="dbt"){ ?>
                        <img src="<?php echo $b['imgcode']?>" />
        
        <?php } else {?>
                         <img src="<?php echo $b['imagepathoriginal']?>" />
        <?php } ?>
                        </div>
                        <div class="a_rgt">
                            <ul>
                                <li><?php echo $b['localname']?></li>
                                <li><?php echo $b['aadharname']?></li>
                                <li><?php echo $b['dobinlocal'].' / '.'DOB : '?><?php echo $b['dob']?></li>
                                <li><?php echo $b['sexinlocal'].' / '?><?php echo $b['gender']?></li>
                            </ul>
                            
                            <?php /*
                            <img src='https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo $codeContents; ?>' >
                            */ ?>
                            <p class="download-date issue">Issue&nbsp;Date:&nbsp;<?php echo date('d/m/Y',strtotime('-30 days'))?></p>
                        </div>
                    </div>  <!--three-->
                    <h3><span><?php echo $b['originalaadharno']?></span></h3>
                    <!-- <h2>मेरा <span>आधार</span>, मेरी पहचान</h2> -->
                    <img src="images/te/img30.jpg" class="img6 image--010">
                </div>

                <div class="clr"></div>
            </div>
            <div class="cntr">
                <img src="images/image--004.png" class="cut-verti">
                <div class="clr"></div>
            </div>
            <div class="right">
                <div class="one">
                    <img src="images/te/img29.jpg" class="img4">
                    <?php /*
                    <div class="info">
                        <h4>सूचना</h4>
                        <ul>
                            <li><span>आधार </span> पहचान का प्रमाण है, नागरिकता का नहीं।</li>
                            <li>पहचान का प्रमाण ऑनलाइन ऑथेन्टिकेशन द्वारा प्राप्त करें |</li>
                            <li>यह एक इलेक्ट्रॉनिक प्रक्रिया द्वारा बना हुआ पत्र है |</li>
                        </ul>
                        <h4>Information</h4>
                        <ul>
                            <li><span><b>Aadhaar</b></span> is a proof of identity, not of citizenship.</li>
                            <li>To establish identity, authenticate online.</li>
                            <li>This is electronically generated letter.</li>
                        </ul>
                    </div>
                    <div class="info2">
                        <ul>
                            <li><span>आधार</span> देश भर में मान्य है |</li>
                            <li><span>आधार</span> भविष्य में सरकारी और गैर-सरकारी सेवाओं  <br><span class="brk"> का लाभ उठाने में उपयोगी होगा ।</span></li>
                            <li><span>Aadhaar</span> is valid throughout the country.</li>
                            <li><span>Aadhaar</span> will be helpfull in availing Government <br><span class="brk">and Non-Government services in future.</span></li>
                        </ul>
                    </div>*/
                    ?>
                    <img src="images/te/img31.jpg" class="img4">
  
                </div> <!--one-->
                <div class="two"></div>

                <div class="adhr adhr2">
                    <img src="images/image--012.png" class="cut-008">
                    <div class="three">
                        <img src="images/te/img32.jpg" class="img2 img--013">
                        <div class="b_lft">
                            <ul>
                                <li><span>చిరునామా:</span></li>
                                <li><?php echo $b['localaddress']?></li>
                            </ul>
                            <ul>
                                <li><span>Address:</span></li>
                                <li><?php echo $b['fulladdress']?></li>
                            </ul>
                        </div>
                        <div class="b_rgt">
                            
                         
                            <img src='https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo $codeContents; ?>' >
                           
                              
                             
                        </div>

                        <div class="clr"></div>
                    </div>  <!--three-->
                    <h3><span><?php echo $b['originalaadharno']?></span></h3>
                    <img src="images/te/img35.jpg" class="img6">
                </div>

                <div class="clr"></div>
            </div>
            <div class="clr"></div>
        </div>
    </body>
</html>