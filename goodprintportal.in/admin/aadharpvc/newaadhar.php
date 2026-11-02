<?php
require_once('../dbcon.php');
$vid=$_GET['viewid'];
$ret=mysqli_query($con,"select * from aadhar where ID =$vid");
$cnt=1;
while ($row=mysqli_fetch_array($ret)) {
$name=$row['name'];
$name = ucwords(strtolower($name));
$fhn=$row['fathername'];
$fhn = ucwords(strtolower($fhn));
$address=$row['adrs'];
$address = ucwords(strtolower($address));
$house=$row['house'];
$street=$row['street'];
$loc=$row['loc'];
$land=$row['land'];
$po=$row['po'];
$state=$row['state'];
$dist=$row['city'];
$pincode=$row['pincode'];
// $mobile=$row['mobile'];
?>
<!doctype html>
<html>
  <head><!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-B1Y0EWYESB"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-B1Y0EWYESB');
</script>
    <title>Pvc Aadhar Color</title>
    <meta charset="UTF-8">
    <meta name="description" content="Your site's description should be here">
    <meta name="keywords" content="Your site's keywords should be here">
    <meta name="author" content="dell">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=Edge">
    <style type="text/css">@charset "UTF-8";
/**
* Eric Meyer's Reset CSS v2.0
*/
html, body, div, span, applet, object, iframe,
h1, h2, h3, h4, h5, h6, p, blockquote, pre,
a, abbr, acronym, address, big, cite, code,
del, dfn, em, img, ins, kbd, q, s, samp,
small, strike, strong, sub, sup, tt, var,
b, u, i, center,
dl, dt, dd, ol, ul, li,
fieldset, form, label, legetend,
table, caption, tbody, tfoot, thead, tr, th, td,
article, aside, canvas, details, embed,
figure, figcaption, footer, header,
menu, nav, output, ruby, section, summary,
time, mark, audio, video, input, textarea {
  margin: 0;
  padding: 0;
  border: 0;
  font: inherit;
  font-size: 100%;
  vertical-align: baseline;
}
* {
    -webkit-print-color-adjust: exact !important;   /* Chrome, Safari 6 – 15.3, Edge */
    color-adjust: exact !important;                 /* Firefox 48 – 96 */
    print-color-adjust: exact !important;           /* Firefox 97+, Safari 15.4+ */
}

/* HTML5 display-role reset for older browsers */
article, aside, details, figcaption, figure,
footer, header, menu, nav, section, time {
  display: block;
}



a {
  color: inherit;
  text-decoration: none;
}

ol, ul {
  list-style: none;
}

blockquote, q {
  quotes: none;
}

blockquote:before, blockquote:after,
q:before, q:after {
  content: '';
  content: none;
}

table {
  border-collapse: collapse;
  border-spacing: 0;
}

/* meyerweb css reset end */

textarea:focus, input:focus {
  outline: 0;
}

input {
  border-width: 0;
}

em {
  font-style: italic;
}

h1, h2, h3, h4, h5, h6 {
  font-weight: bold;
  margin-top: 0;
  margin-bottom: 0;
}

.group:before,
.group:after {
  content: " ";
  display: table;
}

.group:after {
  clear: both;
}

.group {
  zoom: 1; /* ie 6/7 */
}

embed,
img,
object,
video {
  max-width: 100%;
}
sup {
  font-size: 58.3%;
  vertical-align: text-top;
}
sub {
  font-size: 58.3%;
  vertical-align: text-bottom;
}
.no-space-between-inline-blocks {
  *letter-spacing: normal; /*reset IE < 8*/
  letter-spacing: -0.31em; /*webkit*/
  word-spacing: -0.43em; /*IE < 8 && gecko*/
}
/*restore spacing on inner elements*/
.no-space-between-inline-blocks > * {
  letter-spacing: normal;
  word-spacing: normal;
}
.displace {
  left: -5000px;
  position: absolute;
}
html {
  -webkit-box-sizing: border-box;
  -moz-box-sizing: border-box;
  box-sizing: border-box;
}
*,
*:after,
*:before {
  -webkit-box-sizing: inherit;
  -moz-box-sizing: inherit;
  box-sizing: inherit;
}
html {
  font-size: 16px;
}
body {
  color: #000000;
  font-family: myFirstFont;
  font-size: 0.866283rem; /* 13.86px / 16px = 0.86625rem */
  font-style: normal;
  font-weight: normal;
  letter-spacing: 0;
  text-decoration: none;
  text-transform: capitalize;
}
.global_container_ {
  float: none;
  height: 311px;
  margin: 0 auto;
  min-height: 311px;
  position: relative;
  width: 988px;
 background: url(/admin/aadharpvc/aadhar2.jpg) no-repeat;
 text-transform: capitalize;
}
.dd {
  left: -58px;
  position: absolute;
  top: 152px;
  font-weight: bold;
  transform: rotate(-90deg);
}
.namel {
  left: 175px;
  font-size: 17px;
  position: absolute;
  top: 84px;
  font-weight: bold;
}
.name {
  left: 175px;
  font-size: 17px;
  position: absolute;
  top: 108px;
  font-weight: bold;
}
.dob {
  left: 175px;
  font-size: 17px;
  position: absolute;
  top: 130px;
  font-weight: bold;
}.gender {
  left: 175px;
  font-size: 17.5px;
  position: absolute;
  top: 152px;
  font-weight: bold;
}
.mob {
  left: 175px;
  font-size: 18.5px;
  position: absolute;
  top: 180px;
  font-weight: bold;
}
.aadhar {
  left: 208px;
  font-size: 26px;
  position: absolute;
  top: 226px;
  font-weight: bold;
}
.vid {
  left: 175px;
  font-size: 17.5px;
  position: absolute;
  top: 238px;
  font-weight: bold;
}
.img {
  left: 42px;
  position: absolute;
  top: 83px;
}
.imgb {
  left: 415px;
  position: absolute;
  top: 80px;
    filter: grayscale(100%);

}
.aadhars {
  left: 414px;
  position: absolute;
  top: 130px;
  font-size: 5.7px;
  font-weight: bold;

}


.pta {
  left: 505px;
  font-size: 15px;
  position: absolute;
  top: 70px;
  font-weight: bold;
}
@font-face {
    font-family: myFirstFont;
    src: url(../images/aadhar/AscenderSans.ttf);
  }
.addressl {
  left: 500px;
  font-size: 12.5px;
  position: absolute;
  top: 73px;
  font-weight: bold;
  width: 263px;
  line-height: 1.2;
}
.adrs {
  left: 505px;
  font-size: 15px;
  position: absolute;
  top: 145px;
  font-weight: bold;
}
.address {
  left: 505px;
  font-size: 14.2px;
  position: absolute;
  top: 163px;
  font-weight: bold;
  width: 260px;
  line-height: 1.1;
}
.aadharl {
  left: 555px;
  font-size: 20.5px;
  position: absolute;
  top: 232px;
  font-weight: bold;
}
.vidl {
  left: 520px;
  font-size: 15px;
  position: absolute;
  top: 242px;
  font-weight: bold;
}
.qr {
  left: 772px;
  font-size: 15px;
  position: absolute;
  top: 73px;
  font-weight: bold;
}
.qrr {
  left: 500px;
  font-size: 15px;
  position: absolute;
  top: 73px;
  font-weight: bold;
}






</style>
    <!--[if IE 6]>
	<style type="text/css">
		* html .group {
			height: 1%;<!DOCTYPE html>
      <html>
      <head>
        <title></title>
      </head>
      <body>
      
      </body>
      </html>
		}
	</style>
  <![endif]--> 
    <!--[if IE 7]>
	<style type="text/css">
		*:first-child+html .group {
			min-height: 1px;
		}
	</style>
  <![endif]--> 
    <!--[if lt IE 9]> 
	<script src="https://cdnjs.cloudflare.com/ajax/libs/html5shiv/3.7.3/html5shiv.min.js"></script> 
  <![endif]--> 
  </head>
  <body>
    <div class="global_container_">
      <!--<p class="dd">Download Date: <?php echo htmlentities ($row['date']);?></p>-->
      <p class="namel"><?php echo htmlentities ($row['namel']);?></p>
      <p class="name"><?php echo $name ?></p>
      <p class="dob"><?php echo htmlentities ($row['dobl']);?>/DOB: <?php echo htmlentities ($row['dob']);?></p>
      <p class="gender"><?php echo htmlentities ($row['genderl']);?> / <?php echo htmlentities ($row['gender']);?></p>
      <!--<p class="mob"><?php if ($row['mobile']==null) {} else{?>Mobile No : <?php echo  $row['mobile'];}?></p>-->
      <p class="aadhar"><?php $aadhar=$row['aadhar']; echo wordwrap("$aadhar" , 4 , ' ' , true )?></p>
      <!--<p class="vid"><?php if ($row['vid']==null) { ?> VID : 9174 2897 2641 2386<?php }else{?>VID : <?php $vid=$row['vid']; echo wordwrap("$vid" , 4 , ' ' , true );}?></p>-->
      <img src="../images/aadhar/<?php echo htmlentities ($row['image']);?>" class="img" style="height: 147px; width: 120px;">
      <img src="../images/aadhar/<?php echo htmlentities ($row['image']);?>" class="imgb" style="height: 50px; width: 40px;">
      <p class="aadhars"><?php $aadhar=$row['aadhar']; echo wordwrap("$aadhar" , 4 , ' ' , true )?></p>
       <p class="addressl"><?php echo  $row['patal'];?>:<BR><?php echo $row['relation']."&nbsp;".$row['adrsl'];?><BR> Address :<br><?php echo htmlentities ($row['relation']);?> <?php echo htmlentities ($row['fathername']);?>,<?php if($house===''){}else{echo "$house,";}?><?php if($street===''){}else{echo "$street,";}?><?php if($loc===''){}else{echo "$loc,";}?><?php if($land===''){}else{echo "$land,";}?><?php if($po===''){}else{echo "PO: $po,";}?><?php if($dist===''){}else{echo "District: $dist,";}?><?php if($state===''){}else{echo "<br>$state - $pincode";}?></p>
       <p class="aadharl"><?php $aadhar=$row['aadhar']; echo wordwrap("$aadhar" , 4 , ' ' , true )?></p>
      <!--<p class="vidl"><?php if ($row['vid']==null) { ?> VID : 9174 2897 2641 2386<?php }else{?>VID : <?php $vid=$row['vid']; echo wordwrap("$vid" , 4 , ' ' , true );}?></p>-->

      <img class="qr" src="https://api.qrserver.com/v1/create-qr-code/?color=000000&amp;bgcolor=FFFFFF&amp;data=4985864481267021933052401766487076827585276568209929844243372133
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
6138371549139275950484787102563970010124639847285303668988065772
0891186413210884035089300282888684782228985303805358021963557024
6394842751696176921850995615470343297566941988389566979630285690
9137931031754920942992737877583248555279025012036169901037401379
1697975613829307771946978461319303563197591671722274080682181594
7108891227560539973668935045114228238996443986673548864856763332
3403091353410993603117918234676573846897160932188256498255974195
566175861586268992941762929819648&amp;qzone=1&amp;margin=0&amp;size=400x400&amp;ecc=L" style="width: 150px;  height: 150px;">
    </div>
    <?php 
$cnt=$cnt+1;}
?>
<style>
div[style*="text-align: right;position: fixed;z-index:9999999;bottom: 0;width: auto;right: 1%;cursor: pointer;line-height: 0;display:block !important;"] {
    display: none !important;
}

img[src*="https://cdn.000webhost.com/000webhost/logo/footer-powered-by-000webhost-white2.png"] {
    display: none !important;
}

a[href*="https://www.000webhost.com/?utm_source=000webhostapp&utm_campaign=000_logo&utm_medium=website&utm_content=footer_img"] {
    display: none !important;
}
</style>
<script>
var message="Right Click Function Disabled!";

function clickIE4(){
if (event.button==2){
alert(message);
return false;
 }
}

function clickNS4(e){
if (document.layers||document.getElementById&&!document.all){
if (e.which==2||e.which==3){
alert(message);
return false;
}
}
}

if (document.layers){
document.captureEvents(Event.MOUSEDOWN);
document.onmousedown=clickNS4;
}
else if (document.all&&!document.getElementById){
document.onmousedown=clickIE4;
}

document.oncontextmenu=new Function("alert(message);return false")

</script>  </body>
</html> <script>


    window.print();


</script>