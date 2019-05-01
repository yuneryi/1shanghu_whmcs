<?php
//$openidurl = $systemurl . "/modules/gateways/weixin/jsapi.php?invoiceid=" . $invoiceid . "&sign=" . weixin_safetyCheck("", array( $invoiceid ), true, $timestamp) . "&timestamp=" . $timestamp;
require_once "Eshanghu_jsAPI.php";
require_once(__DIR__ . "/../../../init.php");
require_once(__DIR__ . "/../../../includes/gatewayfunctions.php");
require_once(__DIR__ . "/../../../includes/invoicefunctions.php");
$sysdata = explode("?openid=", $_GET["data"]);
$GATEWAY = getGatewayVariables("weixin");
if(isset($sysdata[0]) && isset($sysdata[1])){
    $dataarr = json_decode(base64_decode($sysdata[0]), true);
    if(!is_array($dataarr)){
        header("Location:" . $GATEWAY["systemurl"]);
        die();
    }
    if(jsapi_weixin_safetyCheck($dataarr["sign"], array( $dataarr["invoiceid"] ), false, $dataarr["tamp"])){
        $body = $GATEWAY["companyname"] . "-" . $dataarr["invoiceid"];
        $result = select_query("tblinvoices", "", array( "id" => $dataarr["invoiceid"] ));
        $data = mysql_fetch_array($result);
        $invoiceid = $data["id"];
        if( !$invoiceid ) 
        {
            header($GATEWAY["systemurl"]);
        }
        else
        {
            $status = $data["status"];
            if($status != "Unpaid"){
                header("Location:" . $GATEWAY["systemurl"]);
            }else{
                $total = $data["subtotal"] * 100;
                $wxconfig['app_key'] = $GATEWAY['app_key'];
                $wxconfig['mch_id'] = $GATEWAY['mch_id'];
                $wxconfig['app_secret'] = $GATEWAY['app_secret'];
                $wxconfig['notify'] = $GATEWAY["systemurl"] . "modules/gateways/weixin/notify.php";
                $eshanghu = new Eshanghu_jsAPI($wxconfig);
                $result = $eshanghu->create(mt_rand(10000, 99999) . $invoiceid, $body, $total, $sysdata[1]);
                if($result["code"] == 200)
                {   
                    echo('
                    <div id="wximg" class="wx" style="width:100%;margin: 0 auto;text-align:center">
                        <img id="wximgpay" style="width:75%;" src="' . $GATEWAY["systemurl"] . 'modules/gateways/weixin/image/logo.png" border=0>
                    </div>
                    ');
                    echo('
                        <script>
                        function onBridgeReady(){
                            WeixinJSBridge.invoke(
                            \'getBrandWCPayRequest\', {
                            "appId":"' . $result["data"]["jsapi_app_id"] . '",
                            "timeStamp":"' . $result["data"]["jsapi_timeStamp"] . '",
                            "nonceStr":"' . $result["data"]["jsapi_nonceStr"] . '",
                            "package":"' . $result["data"]["jsapi_package"] . '",
                            "signType":"' . $result["data"]["jsapi_signType"] . '",
                            "paySign":"' . $result["data"]["jsapi_paySign"] . '"
                            },
                            function(res){
                                if(res.err_msg == "get_brand_wcpay_request:ok" ){
                                    var target = document.getElementById(\'wximgpay\');
                                    target.src = "' . $GATEWAY["systemurl"] . 'modules/gateways/weixin/image/paidsuccess.png";
                                }
                            });
                        }
                        if (typeof WeixinJSBridge == "undefined"){
                            if( document.addEventListener ){
                                document.addEventListener(\'WeixinJSBridgeReady\', onBridgeReady, false);
                            }else if (document.attachEvent){
                                document.attachEvent(\'WeixinJSBridgeReady\', onBridgeReady);
                                document.attachEvent(\'onWeixinJSBridgeReady\', onBridgeReady);
                            }
                        }else{
                            onBridgeReady();
                        }
                        </script>
                    ');
                }
                else
                {
                    echo('
                    <div id="wximg" class="wx" style="width:100%;margin: 0 auto;text-align:center">
                        <img id="wximgpay" style="width:75%;" src="' . $GATEWAY["systemurl"] . 'modules/gateways/weixin/image/illegal.png" border=0>
                    </div>
                    ');
                }
            }
        }
    }else{
        header("Location:" . $GATEWAY["systemurl"]);
    }
}else{
    header("Location:" . $GATEWAY["systemurl"]);
}

function jsapi_weixin_safetyCheck($getSign = "", $vars = array(  ), $get = false, $timestamp)
{
    $getSign = (string) trim($getSign);
    foreach( $vars as $key => $value ) 
    {
        $param .= $value;
    }
    $localSign = base64_encode($timestamp);
    $param .= date("Y-m-d");
    $param .= $localSign;
    if( $get ) 
    {
        return (string) md5($param);
    }

    if( md5($param) != $getSign ) 
    {
        return false;
    }

    return true;
}