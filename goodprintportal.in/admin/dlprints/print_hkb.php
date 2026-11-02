<?php
$getid = $_GET['id'];
include('../../includes/config.php');
$dl_info = mysqli_fetch_assoc(mysqli_query($ahk_conn, "select * from dllist where id=" . $getid . ""));
extract($dl_info);

?>
<html>

<head>
    <style type="text/css">
        .PhotoSize {
            height: 70px;
            width: 70px;
        }

        .SignatureSize {
            height: 10px;
            width: 80px;
        }

        .HeadSize {
            font-size: 14px;
            font-weight: 800;
            font-family: "Times New Roman";
        }

        .txt11 {
            font-size: 6px;
        }

        .txt1 {
            font-size: 9px;
        }

        .txt2 {
            font-weight: bold;
        }

        .txt3 {
            font-size: 10px;
        }

        .BarcodeSize {
            height: 70px;
            width: 70px;
        }

        .border1 {
            border: 1px solid black;
            border-collapse: collapse;
            font-size: 7px;
        }
        img{
            pointer-events:none;
        }
        * {
            -webkit-print-color-adjust: exact !important;
            color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
    </style>
</head>

<body onload="self.print();">
    <left>
        <div class="row">
            <div class="col-md-12">
                <table>
                    <tbody>
                        <tr style="vertical-align: top;">
                            <td style="background-image: url(dlFront.jpg); background-repeat: no-repeat; background-size: 100%; height: 400px; width: 400px; background-position: center;">
                                <table width="100%" style="margin-top: 80px;">
                                    <tbody>
                                        <tr>
                                            <td width="100%" class="HeadSize" style="text-align: center;">
                                                <center>
                                                    <table width="100%">
                                                        <tbody>
                                                            <tr>
                                                                <td width="80%" style="text-align: center; padding-left: 80px; font-size: 13px;">Indian Union Driving Licence</td>
                                                                <td width="20%"><span id="ContentPlaceHolder1_lblRto" style="margin-left: 42px; display: inline-block; margin-top: 9px; font-size: 11px; color: black;"><?=$state?></span></td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </center>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td width="100%" class="HeadSize" style="margin-top: -10px; text-align: center; display: list-item;">Issued by <span id="ContentPlaceHolder1_lblIssueBy"></span></td>
                                        </tr>
                                        <tr>
                                            <td width="100%" class="HeadSize" style="text-align: center; padding-top: 8px;"><span id="ContentPlaceHolder1_lbldl2" class=""><?=$dlno?></span></td>
                                        </tr>
                                        <tr>
                                            <td width="100%" style="text-align: center;">
                                                <center>
                                                    <table width="100%">
                                                        <tbody>
                                                            <tr>
                                                                <td width="28%"></td>
                                                                <td width="47%">
                                                                    <table width="100%">
                                                                        <tbody>
                                                                            <tr>
                                                                                <td width="33%" class="txt1">Issue Date</td>
                                                                                <td width="33%" class="txt1">Validity (NT)</td>
                                                                                <td width="33%" class="txt1">Validity(TR)#</td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td width="33%" class="txt1 txt2"><span id="ContentPlaceHolder1_lblissuedate" class=""><?=$idate?></span></td>
                                                                                <td width="33%" class="txt1 txt2"><span id="ContentPlaceHolder1_lblvalid" class=""><?=$edate?></span></td>
                                                                                <td width="33%" class="txt1 txt2">------</td>
                                                                            </tr>
                                                                        </tbody>
                                                                    </table>
                                                                </td>
                                                                <td width="25%">
                                                                    <table width="45%">
                                                                        <tbody>
                                                                            <tr>
                                                                                <td><img src="<?=$photo?>" id="ContentPlaceHolder1_imgPhoto" class="PhotoSize" style="margin-top: -24px;" /></td>
                                                                            </tr>
                                                                        </tbody>
                                                                    </table>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </center>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td width="100%" style="text-align: center;">
                                                <center>
                                                    <table width="100%">
                                                        <tbody>
                                                            <tr>
                                                                <td width="28%"></td>
                                                                <td width="47%"></td>
                                                                <td width="25%">
                                                                    <table width="100%">
                                                                        <tbody style="width: 100%;">
                                                                            <tr>
                                                                                <td><span id="ContentPlaceHolder1_Label1" class="txt1" style="position: relative; top: -15px;"> Holder's Signature</span>
                                                                                    <img src="<?=$sign?>" id="ContentPlaceHolder1_imgSignature" class="SignatureSize" style="margin-top: -16px;" />
                                                                                    
                                                                                </td>
                                                                            </tr>
                                                                        </tbody>
                                                                    </table>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </center>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td width="100%" style="text-align: center; position: relative; top: -20px;">
                                                <center>
                                                    <table width="100%">
                                                        <tbody>
                                                            <tr style="height: 14px;">
                                                                <td width="5%"></td>
                                                                <td width="15%" class="txt1" style="vertical-align: top;">Name:</td>
                                                                <td width="15%" class="txt3 txt2" colspan="4" style="vertical-align: top;"><span id="ContentPlaceHolder1_lblname" class=""><?=$name?></span></td>
                                                                <td width="15%" class="txt1" style="vertical-align: top; position: relative; top: 5px;">Gender:<span id="ContentPlaceHolder1_lblGender" class="txt1 txt2"><?=$gender?></span></td>
                                                                <td width="5%" class="txt1"></td>
                                                            </tr>
                                                            <tr style="height: 14px;">
                                                                <td width="5%" class="txt1"></td>
                                                                <td width="15%" class="txt1" style="vertical-align: top;">Date Of Birth:</td>
                                                                <td width="15%" class="txt1 txt2" style="vertical-align: top;"><span id="ContentPlaceHolder1_lbldob" class=""><?=$dob?></span></td>
                                                                <td width="15%" class="txt1" style="vertical-align: top;">Blood Group:</td>
                                                                <td width="15%" class="txt1 txt2" style="vertical-align: top;"><span id="ContentPlaceHolder1_lblgroup" class="txt1 txt2"><?=$bgroup?></span></td>
                                                                <td width="15%" class="txt1" style="vertical-align: top;">Organ Donor:</td>
                                                                <td width="15%" class="txt1 txt2" style="vertical-align: top;"><span id="ContentPlaceHolder1_Label5" class="txt1 txt2">N</span></td>
                                                                <td width="5%"></td>
                                                            </tr>
                                                            <tr style="height: 14px;">
                                                                <td width="5%"></td>
                                                                <td width="15%" colspan="2" class="txt1" style="vertical-align: top;">Son/Daughter/Wife of:</td>
                                                                <td width="15%" class="txt3 txt2" colspan="3" style="vertical-align: top;"><span id="ContentPlaceHolder1_lblfathername" class=""><?=$swd?></span></td>
                                                                <td width="15%"></td>
                                                                <td width="5%" class="txt1"></td>
                                                            </tr>
                                                            <tr style="height: 14px;">
                                                                <td width="5%"></td>
                                                                <td width="15%" class="txt1" style="vertical-align: top;">Address:</td>
                                                                <td width="15%" class="txt3 txt2" colspan="4" style="vertical-align: top;">
                                                                    <span id="ContentPlaceHolder1_lbladdress" class=""><?=$address?></span>
                                                                </td>
                                                                <td width="15%"></td>
                                                                <td width="5%" class="txt1"></td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </center>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </td>
                            <td style="background-image: url(dlBack.jpg); background-repeat: no-repeat; background-size: 100%; height: 400px; width: 400px; background-position: center;">
                                <table width="100%" style="margin-top: 85px; margin-left: 10px;">
                                    <tbody>
                                        <tr>
                                            <td width="100%" class="HeadSize" style="text-align: center;">DL No: <span id="ContentPlaceHolder1_lbldlno" class=""><?=$dlno?></span></td>
                                        </tr>
                                        <tr>
                                            <td width="100%" style="text-align: center; height: 95px; vertical-align: top;">
                                                <center>
                                                    <table width="100%">
                                                        <tbody>
                                                            <tr>
                                                                <td width="25%">
                                                                    <table width="45%">
                                                                        <tbody>
                                                                            <tr>
                                                                                <td style="text-align: center;">
                                                                                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&amp;data=<?=urlencode('https://sarathi.parivahan.gov.in/sarathiservice/rsServices/sarathi/QRService/DLDetails/dlqrresult?dlnum='.base64_encode($dlno).'&dob='.base64_encode($dob))?>" id="ContentPlaceHolder1_imgBarCode" class="BarcodeSize" style="margin-top: 10px; margin-left: 0px;" />
                                                                                </td>
                                                                            </tr>
                                                                        </tbody>
                                                                    </table>
                                                                </td>
                                                                <td width="75%" style="vertical-align: top;">
                                                                    <table width="100%">
                                                                        <tbody>
                                                                            <tr>
                                                                                <td width="100%" class="HeadSize" colspan="2">Invalid Carriage (Regn Numbers)#</td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td width="100%" class="HeadSize txt2" colspan="2"><span id="ContentPlaceHolder1_Label3" class="">---------</span></td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td width="50%" class="HeadSize">Hazardous Validity#</td>
                                                                                <td width="50%" class="HeadSize">Hill Validity#</td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td width="50%" class="HeadSize txt2"><span id="ContentPlaceHolder1_Label2" class="">---------</span></td>
                                                                                <td width="50%" class="HeadSize txt2"><span id="ContentPlaceHolder1_Label6" class="">---------</span></td>
                                                                            </tr>
                                                                        </tbody>
                                                                    </table>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </center>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td width="100%" style="text-align: center; position: relative; top: -5px;">
                                                <table width="93%" class="border1">
                                                    <tbody>
                                                        <tr>
                                                            <td width="14%" class="border1" style="vertical-align: top; text-align: center;">Class of Vechical</td>
                                                            <td width="12%" class="border1" style="vertical-align: top; text-align: center;">Code</td>
                                                            <td width="12%" class="border1" style="vertical-align: top; text-align: center;">Code Issued By</td>
                                                            <td width="12%" class="border1" style="vertical-align: top; text-align: center;">Date of Issue</td>
                                                            <td width="12%" class="border1" style="vertical-align: top; text-align: center;">Vehicle Category</td>
                                                            <td width="12%" class="border1" style="vertical-align: top; text-align: center;">Badge Number#</td>
                                                            <td width="12%" class="border1" style="vertical-align: top; text-align: center;">Badge IssuedDate#</td>
                                                            <td width="14%" class="border1" style="vertical-align: top; text-align: center;">Badge IssuedBy#</td>
                                                        </tr>
                                                        <?php
                                                        $stshor = substr($dlno, 0, 4);
                                                        $tovs = explode(',',$typeofvehicle);
                                                        $count = 1;
                                                        foreach($tovs as $tov)
                                                        {
                                                            echo '
                                                            <tr>
                                                            <td width="14%" class="border1" style="vertical-align: top; text-align: center;"><span id="ContentPlaceHolder1_lblClassOfVech1">'.$tov.'</span></td>
                                                            <td width="12%" class="border1" style="vertical-align: top; text-align: center;"><span id="ContentPlaceHolder1_lblCode1">'.$tov.'</span></td>
                                                            <td width="12%" class="border1" style="vertical-align: top; text-align: center;"><span id="ContentPlaceHolder1_lblCodeIssue1">'.$stshor.'</span></td>
                                                            <td width="12%" class="border1" style="vertical-align: top; text-align: center;"><span id="ContentPlaceHolder1_lblIssueDate1">'.$idate.'</span></td>
                                                            <td width="12%" class="border1" style="vertical-align: top; text-align: center;"><span id="ContentPlaceHolder1_lblVehCat1">NT</span></td>
                                                            <td width="12%" class="border1" style="vertical-align: top; text-align: center;"></td>
                                                            <td width="12%" class="border1" style="vertical-align: top; text-align: center;"></td>
                                                            <td width="14%" class="border1" style="vertical-align: top; text-align: center;"></td>
                                                        </tr>
                                                            ';
                                                            $count++;
                                                        }
                                                        for($count;$count<=5;$count++)
                                                        {
                                                            echo '
                                                            <tr>
                                                            <td width="14%" class="border1" style="vertical-align: top; text-align: center;"><span id="ContentPlaceHolder1_lblClassOfVech3">-</span></td>
                                                            <td width="12%" class="border1" style="vertical-align: top; text-align: center;"><span id="ContentPlaceHolder1_lblCode3"></span></td>
                                                            <td width="12%" class="border1" style="vertical-align: top; text-align: center;"><span id="ContentPlaceHolder1_lblCodeIssue3"></span></td>
                                                            <td width="12%" class="border1" style="vertical-align: top; text-align: center;"><span id="ContentPlaceHolder1_lblIssueDate3"></span></td>
                                                            <td width="12%" class="border1" style="vertical-align: top; text-align: center;"><span id="ContentPlaceHolder1_lblVehCat3"></span></td>
                                                            <td width="12%" class="border1" style="vertical-align: top; text-align: center;"></td>
                                                            <td width="12%" class="border1" style="vertical-align: top; text-align: center;"></td>
                                                            <td width="14%" class="border1" style="vertical-align: top; text-align: center;"></td>
                                                        </tr>
                                                            ';
                                                        }
                                                        ?>
                                                    </tbody>
                                                </table>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td width="100%" style="text-align: center; position: relative; top: -5px;">
                                                <table width="100%">
                                                    <tbody>
                                                        <tr>
                                                            <td width="50%" style="vertical-align: top;">&nbsp;</td>
                                                            <td width="50%" style="vertical-align: top;"></td>
                                                        </tr>
                                                        <tr style="position: relative; top: -20px;">
                                                            <td width="50%" style="vertical-align: top; text-align: center;" class="txt1 txt2"><span id="ContentPlaceHolder1_Label4" class="">Emergancy Contact Number</span></td>
                                                            <td width="50%" style="vertical-align: top; text-align: center;" class="txt1 txt2"><span id="ContentPlaceHolder1_Label7" class="">Licensing Authority</span></td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </left>
</body>

</html>