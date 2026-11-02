<?php 
include('../../includes/session.php');
include('config.php');
checkSession();
if(isset($_POST['number']) && $_POST['opr_code']  && !empty($_POST['amount'])){
    $number = mysqli_real_escape_string($ahk_conn,$_POST['number']);
    $opr_code = mysqli_real_escape_string($ahk_conn,$_POST['opr_code']);
    $amount = mysqli_real_escape_string($ahk_conn,$_POST['amount']);
    // echo $_SESSION['phone'];
    if($udata['balance']>$amount){
        $nbal = $udata['balance']-$amount;
        if($opr_code =="600"){
            $operatorname = "AIRTEL PREPAID";
            $comission = $amount /100 * $webdata['airtel_com'];
        }else if($opr_code =="601"){
            $operatorname = "JIO PREPAID";
            $comission = $amount /100 * $webdata['jio_com'];
        }else if($opr_code == "6"){
            $operatorname = "Vodafone";
            $comission = $amount /100 * $webdata['vi_com'];
        }else if($opr_code == "2"){
            $operatorname = "BSNL";
            $comission = $amount /100 * $webdata['bsnl_com'];
        }

        $updatebal = mysqli_query($ahk_conn,"UPDATE `users` SET balance='$nbal' WHERE id='".$udata['id']."'");
        $updatewallethistory = mysqli_query($ahk_conn,"INSERT INTO `wallethistory` (`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES('".$udata['id']."','$amount','$nbal','Mobile Recharge','Success','DEBIT') ");
        if($updatebal && $updatewallethistory){
            $txn_no = rand(00000000,99999999);
            $inrecharge = mysqli_query($ahk_conn,"INSERT INTO `mobilerecharge` (`userid`, `txn_no`, `number`, `operator`, `amount`, `remark`, `status`) VALUES('".$udata['id']."','$txn_no','$number','$operatorname','$amount','Processing','Processing') ");
            if($inrecharge){
                // Call API here 
                $curl = curl_init();
                curl_setopt_array($curl, array(
                CURLOPT_URL => "https://apizone.in/api/v1/services/recharge/recharge",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => array('api_key' => $webdata["nsdl_api_key"],'opr_code' => $opr_code,'number' => $number,'amount' => $amount,'txn_id' => $txn_no),
                ));
                $response = curl_exec($curl);
                curl_close($curl);
                  $response;

                  $data = json_decode($response,true);
                // echo "<pre>";
                // echo $data['data'];
                if(!isset($data) ==''){
                    if($data['status']==1){
                        $updaterecharge = mysqli_query($ahk_conn,"UPDATE mobilerecharge SET ref_id='".$data['data']['opr_txn_id']."', status='Success',order_id='".$data['data']['order_id']."', remark='".$data['data']['remark']."' WHERE txn_no='".$data['data']['reference_id']."' ");
                        // Comission Update  for parent user
                        $getparent = mysqli_fetch_assoc(mysqli_query($ahk_conn,"SELECT * FROM users WHERE phone='".$udata['parent']."' LIMIT 1"));
                        $parentcomission = $comission/100*$webdata['dist_com'];
                        $cnbal = $getparent['balance'] +$parentcomission;
                        mysqli_query($ahk_conn,"UPDATE users SET balance=balance+$parentcomission WHERE phone='".$udata['parent']."'");

                        mysqli_query($ahk_conn,"INSERT INTO `wallethistory` (`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES('".$getparent['phone']."','$parentcomission','$cnbal','Mobile Recharge Comission','Success','CREDIT') ");

                        // Comission Update  for Retailer 
                        $ret_comission = $comission -$parentcomission;
                        $cretnbal = $udata['balance'] +$ret_comission;
                        mysqli_query($ahk_conn,"UPDATE users SET balance=balance+$ret_comission WHERE phone='".$udata['phone']."'");
                        mysqli_query($ahk_conn,"INSERT INTO `wallethistory` (`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES('".$udata['phone']."','$ret_comission','$cretnbal','Mobile Recharge Comission','Success','CREDIT') ");

                        if($updaterecharge){
                           echo json_encode(array("status" => true,"msg" => "Recharge Successful...", 'number' => $number , 'txn_no' => $txn_no,'operator' => $operatorname, 'amount'=>$amount, 'r_status' => 'Success','opr_txn_id' => $data['data']['opr_txn_id'], 'order_id' => $data['data']['order_id'] ));
                        }else{
                           echo json_encode(array("status" => true,"msg" => "Recharge Success"));
                        }
                    }else{
                        $json = json_encode(array("status" => false,"msg" => $data['message']));
                        echo $json;
                        // Refund if Server maintenance
                        $obal = mysqli_fetch_assoc(mysqli_query($ahk_conn,"SELECT balance FROM users WHERE id='".$udata['id']."'"));
                        $nbal = $obal['balance']+$amount;
                        $updatebal = mysqli_query($ahk_conn,"UPDATE users SET balance='$nbal' WHERE id='".$udata['id']."'");
                        // Update History
                        $updatewallethistory = mysqli_query($ahk_conn,"INSERT INTO `wallethistory` (`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES('".$udata['id']."','$amount','$nbal','Mobile Recharge Refund','Success','CREDIT') ");
                    }
                }
                // End API call Here 
            }else{
                $json = json_encode(array("status" => false,"msg" => "Insert Error"));
                echo $json;
            }
        }else{
            $json = json_encode(array("status" => false,"msg" => "Problem While Deduction Balance"));
            echo $json;
        }
    }else{
        $json = json_encode(array("status" => false,"msg" => "Wallet balance Low, Please Recharge"));
        echo $json;
    }
}else{
    $json = json_encode(array("status" => false,"msg" => "Fill All Required Data"));
    echo $json;
}
// Hello
?>