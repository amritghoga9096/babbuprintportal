<?php
include('config.php');
error_reporting(0);

if(isset($_GET['searchid'])){
//$searchid =$_GET['searchid'];
$searchid = mysqli_real_escape_string($ahk_conn,$_GET['searchid']);

mysqli_set_charset($ahk_conn,"utf8");
$a = mysqli_query($ahk_conn,"SELECT * FROM aadharauto Where aadharautoid='".$searchid."'");
$b = mysqli_fetch_array($a);


if($searchid == '24160')
  {
    /*
    echo '<pre>';
     
      var_dump($b);
      
    echo '</pre>';

    exit;
    */
  }


if(strtoupper($b['locallanguage']) == 'KN')
     {
          header("location:aadhar-kna.php?searchid=".$searchid);
          exit;
     }
else if(strtoupper($b['locallanguage']) == 'PA')
     {
          header("location:aadhar-paa.php?searchid=".$searchid);
          exit;
     }   
  
else if(strtoupper($b['locallanguage']) == 'GU')
     {
          header("location:aadhar-gua.php?searchid=".$searchid);
          exit;
     } 

 else if(strtoupper($b['locallanguage']) == 'MR')
     {
        //  header("location:aadhar-mr.php?searchid=".$searchid);
         // exit;
     }

else if(strtoupper($b['locallanguage']) == 'TA')
     {
          header("location:aadhar-taa.php?searchid=".$searchid);
          exit;
     } 

else if(strtoupper($b['locallanguage']) == 'BN')
     {
          header("location:aadhar-bna.php?searchid=".$searchid);
          exit;
     }
else if(strtoupper($b['locallanguage']) == 'TE')
     {
          header("location:aadhar-tea.php?searchid=".$searchid);
          exit;
     } 
else if(strtoupper($b['locallanguage']) == 'OR')
     {
          header("location:aadhar-tea.php?searchid=".$searchid);
          exit;
     }                           




}
?>
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
<style>
            .btopthird img {
                top: 2px !important;
            }

            .btopthird1 img {
                width: 80px;
                height: 80px;
                position: relative;
                left: 241px !important;
                top: 5px !important;
            }
        </style>
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
<style>@font-face {
    font-family: myFirstFont;
    src: url(../images/aadhar/AscenderSans.ttf);
  }</style>

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


   <body><div style="position:absolute;top:0.000000px;left:0.000000px">
    <img height="1123.000000" width="794.000000" src="/admin/aadharpvc/aadharfpbc.jpg" />
</div>
 <div class="pdf">
            <div class="left">
                <div class="one">
                    <img src="images/image--001.jpg">
                    <p> नामांकन क्रम / Enrollment No: 1879/38851/4850<?php echo$b['srno']?></p>
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
            style="position:absolute;top:535px;left:246.5px">
            <img src="https://api.qrserver.com/v1/create-qr-code/?color=000000&amp;bgcolor=FFFFFF&amp;data=4985864481267021933052401766487076827585276568209929844243372133
7960382028539908621911172406313877455782601879022892090618356287
9063601887098233576149730870985876910128653369584165797623925400
9586848462872647461397115000874465630265174131361649969641191503
6116060187392514584368901174504603565728672096097501166553900028
5463351753625031856864847296016120983489120372270210317107013367
5536221797215410209182400925775188420618272829578755844441339465
0940858988166237160775944612074793079676572655082915627020824868
4174401309247948481122129952926175230068795304940026718962094703
3825749353715325365656775868350626032277495264646854237839897113
7409620993623502826099573419190475289583097918938692900514384782
8375232997474540957522849593392647533965385773492425551096860497
4374531287518385302597810506008728063455889622683716775505037277
8402364517137794227875970653811661145926306821494022415751794085
4450896801901037870288384364044559929843506485271520592309723110
9088569312970542922414805612728138910589450959127681191488975796
4795316360306936700593754473446436297312417490751073167239369245
1440207084228783645152698333448398138696482488463791941479753934
9193604722089825016901875520437012783311078153788613097019092282
9252000774609203141233345289414119643470011559278098935114081884
9456547559527170219579613641475506123997475243707895727078581637
9098571756401603822805014226859572590160145852664897358861364156
0522421489412923886956141501854976892103916811119513263498943082
3071184410622908044177891878049185709193877699281849573028644302
4683269960548490573570201375185586607330406063298051256357931576
4703401755975306325335667862103290199336865229454085791877779742
3032402408683785551857586600282917285729188199347030260812171901
6401141124603119518151541681498663084756294260769757744119244458
8321645544470049473774722892230908689872959254850280762695782943
7473054810337131991930606990434784964035250949972116949774543028
3223524595484655107111734913089447477626074532977557814603962193
9559678470260517015105922783773085342795502095343270606484420927
0955174346605573546553801187150645518545811911897067227489666264
9043932090444629627728977619665933174082011801272275388394099684
0889755977410562867937379522354679438921116119606392342065812367
2930863428513156049558065380187583088422946937503834786492530051
0276569276761953228012865711472292880219496068281191924995997092
1262795674960395270161952127099268614120751261308273985231373793
0739410350529508988368707467926715616785633074098569910620529339
0358049103146552665836027604096668283421198978818439813786331769
61383715491392759504847871025639700101246398472853036689880672
0891186413210884035089300282888684782228985303805358021963557024
6394842751696176921850995615470343297566941988389566979630285690
9137931031754920942992737877583248555279025012036169901037401379
1697975613829307771946978461319303563197591671722274080682181594
7108891227560539973668935045114228238996443986673548864856763332
3403091353410993603117918234676573846897160932188256498255974195
566175861586268992941762929819648&amp;qzone=1&amp;margin=0&amp;size=400x400&amp;ecc=L" width="126px" height="126px" style="">
        </span></span></p>

<p><span style="font-family:myFirstFont;font-size:18.5px;font-style:normal;font-weight:normal;color:#000000;"><span
            style="position:absolute;top:687.383301px;left:148.727509px">
            <?php $aadhar=$row['aadhar']; echo wordwrap("$aadhar" , 4 , ' ' , true )?> 
        </span><span style="font-size:10.2px;position:absolute;top:707.393372px;left:154.114288px">
            <?php if ($row['vid']=='') { ?> VID : 9174 2897 2641 2386<?php }else{?>VID : <?php $vid=$row['vid']; echo wordwrap("$vid" , 4 , ' ' , true );}?>
        </span></span></p>
<p><span style="font-family:myFirstFont;font-size:7.4px;font-weight:bold;color:#000000;"><span
            style="position:absolute;top:857px;left:368px;transform: rotate(270deg)">
            Details as on: <?php echo htmlentities ($row['date']);?> 
        </span></span></p>
<p><span style="font-family:myFirstFont;font-size:7.4px;font-weight:bold;color:#000000;"><span
            style="position:absolute;top:853px;left:16px;transform: rotate(270deg)">
            Aadhaar no. issued: <?php echo htmlentities ($row['date']);?> 
        </span></span></p>
<p><span style="font-family:myFirstFont;font-size:8.5px;font-style:normal;font-weight:normal;color:#000000;"><span
            style="position:absolute;top:806px;left:158.423706px">
            <?php echo htmlentities ($row['namel']);?>
        </span></span></p>
<p><span style="font-family:myFirstFont;font-size:9.2px;font-style:normal;font-weight:normal;line-height:1.2;color:#000000;"><span
            style="position:absolute;top:818.651855px;left:158.423706px">
            <?php echo htmlentities ($row['name']);?><br><?php echo htmlentities ($row['dobl']);?>/DOB: <?php echo htmlentities ($row['dob']);?><br><?php echo htmlentities ($row['genderl']);?> / <?php echo htmlentities ($row['gender']);?>
        </span></span></p>
        <p><span ><span
            style="position:absolute;top:810px;left:85px">
            <img src="../images/aadhar/<?php echo htmlentities ($row['image']);?>" width="60px" height="85px" style="">
        </span></span></p>
<p><span style="font-family:myFirstFont;font-size:16px;font-weight:;color:#000000;"><span
            style="position:absolute;top:944.957275px;left:154.832535px">
            <?php $aadhar=$row['aadhar']; echo wordwrap("$aadhar" , 4 , ' ' , true )?>  
        </span></span></p>
<p style="font-family:myFirstFont;font-size:8.5px;font-style:normal;font-weight:normal;color:#000000;position:absolute;top:792px;left:415.397614px;width:190px;line-height: 1.2;">
            <?php echo htmlentities ($row['patal']);?>:<BR><?php echo $row['relation']."&nbsp;".$row['adrsl'];?> <br><br><span style="font-size:9px;">Address:<br> <?php echo htmlentities ($row['relation']);?> <?php echo htmlentities ($row['fathername']);?>,<?php if($house===''){}else{echo "$house,";}?><?php if($street===''){}else{echo "$street,";}?><?php if($loc===''){}else{echo "$loc,";}?><?php if($land===''){}else{echo "$land,";}?><?php if($po===''){}else{echo "PO: $po,";}?><?php if($dist===''){}else{echo "District: $dist,";}?><?php if($state===''){}else{echo "<br>$state - $pincode";}?> </span>
        </p>

<!-- <p><span style="font-family:myFirstFont;font-size:7.002805px;font-style:normal;font-weight:normal;color:#000000;"><span
            style="position:absolute;left:413.397614px;width:191px;">
            Address:<br> S/O Nisar Hussain, 212, nawabpura, haji neb ki masjid, Moradabad, PO: Moradabad, DIST: Moradabad, Uttar Pradesh - 244001 
        </span></span></p> -->

<p><span style="font-family:myFirstFont;font-size:15.7px;font-weight:;color:#000000;"><span
            style="position:absolute;top:934.183838px;left:513.950745px">
            <?php $aadhar=$row['aadhar']; echo wordwrap("$aadhar" , 4 , ' ' , true )?>  
        </span><span style="position:absolute;top:952.312073px;left:513.232544px;font-size:10.2px">
             <?php if ($row['vid']=='') { ?> VID : 9174 2897 2641 2386<?php }else{?>VID : <?php $vid=$row['vid']; echo wordwrap("$vid" , 4 , ' ' , true );}?>
        </span></span></p> <p><span ><span
            style="position:absolute;top:804px;left:608.1px">
            <img src="https://api.qrserver.com/v1/create-qr-code/?color=000000&amp;bgcolor=FFFFFF&amp;data=4985864481267021933052401766487076827585276568209929844243372133
7960382028539908621911172406313877455782601879022892090618356287
9063601887098233576149730870985876910128653369584165797623925400
9586848462872647461397115000874465630265174131361649969641191503
6116060187392514584368901174504603565728672096097501166553900028
5463351753625031856864847296016120983489120372270210317107013367
5536221797215410209182400925775188420618272829578755844441339465
0940858988166237160775944612074793079676572655082915627020824868
4174401309247948481122129952926175230068795304940026718962094703
3825749353715325365656775868350626032277495264646854237839897113
7409620993623502826099573419190475289583097918938692900514384782
8375232997474540957522849593392647533965385773492425551096860497
4374531287518385302597810506008728063455889622683716775505037277
8402364517137794227875970653811661145926306821494022415751794085
4450896801901037870288384364044559929843506485271520592309723110
9088569312970542922414805612728138910589450959127681191488975796
4795316360306936700593754473446436297312417490751073167239369245
1440207084228783645152698333448398138696482488463791941479753934
9193604722089825016901875520437012783311078153788613097019092282
9252000774609203141233345289414119643470011559278098935114081884
9456547559527170219579613641475506123997475243707895727078581637
909857175640160382280501422685957259016014585266489738861364156
0522421489412923886956141501854976892103916811119513263498943082
3071184410622908044177891878049185709193877699281849573028644302
4683269960548490573570201375185586607330406063298051256357931576
4703401755975306325335667862103290199336865229454085791877779742
3032402408683785551857586600282917285729188199347030260812171901
6401141124603119518151541681498663084756294260769757744119244458
8321645544470049473774722892230908689872959254850280762695782943
7473054810337131991930606990434784964035250949972116949774543028
3223524595484655107111734913089447477626074532977557814603962193
955967847026051701510592278377308534279550209534327060484420927
0955174346605573546553801187150645518545811911897067227489666264
9043932090444629627728977619665933174082011801272275388394099684
0889755977410562867937379522354679438921116119606392342065812367
2930863428513156049558065380187583088422946937503834786492530051
0276569276761953228012865711472292880219496068281191924995997092
1262795674960395270161952127099268614120751261308273985231373793
0739410350529508988368707467926715616785633074098569910620529339
0358049103146552665836027604096668283421198978818439813786331769
6138371549139275950484787102563970010124639847285303668988065772
0891186413210884035089300282888684782228985303805358021963557024
6394842751696176921850995615470343297566941988389566979630285690
9137931031754920942992737877583248555279025012036169901037401379
1697975613829307771946978461319303563197591671722274080682181594
7108891227560539973668935045114228238996443986673548864856763332
3403091353410993603117918234676573846897160932188256498255974195
566175861586268992941762929819648&amp;qzone=1&amp;margin=0&amp;size=400x400&amp;ecc=L" width="126px" height="126px" style="">
        </span></span></p></body> <script>

    </body>
</html>