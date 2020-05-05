<?php
    namespace Twyple\Cashfree;

    use Twyple\Utils\Curl;

    class Payments {

        private $appId;
        private $appSecret;
        public $orderHash;
        public $token;
        public $orderData;
        public $cfOrder;
        private $appURLs = array(
            "billpay" => "https://www.cashfree.com",
            "payments" => "https://payments.cashfree.com/pgbillpayuiapi",
            "apiV1" => "https://api.cashfree.com/api/v1"
        );

        function __construct($appId, $appSecret, $env = null) {
            $this->appId = $appId;
            $this->appSecret = $appSecret;
            if($env == "TEST"){
                $this->appURLs = array(
                    "billpay" => "https://test.cashfree.com/billpay",
                    "payments" => "https://payments-test.cashfree.com/pgbillpayuiapi",
                    "apiV1" => "https://test.cashfree.com/api/v1"
                );
            }
        }

        public function setEnv($env){
            if($env == "TEST"){
                $this->appURLs = array(
                    "billpay" => "https://test.cashfree.com/billpay",
                    "payments" => "https://payments-test.cashfree.com/pgbillpayuiapi",
                    "apiV1" => "https://test.cashfree.com/api/v1"
                );
            }
        }

        public function getOrderDetails($orderId){
            $payload = array(
                "appId" => $this->appId,
                "secretKey" => $this->appSecret,
                "orderId" => $orderId,
            );

            $res = Curl::formPost($this->appURLs["apiV1"] . "/order/info/link", $payload);

            return explode("#", $res["paymentLink"])[1];
        }
        public function getOrderHash($orderData){


            $this->getToken($orderData);
            $this->orderData["source"] = "twyple";
            $res = Curl::jsonPost($this->appURLs["billpay"] . "/checkout/post/submit-js-v1", $this->orderData);

            $orderHash = null;
            if($res["status"] == "OK"){
                $orderHash = explode("#", $res["paymentLink"])[1];
                $this->orderHash = $orderHash;
            } else {
                $orderHash = $this->getOrderDetails($this->orderData["orderId"]);
                $this->orderHash = $orderHash;
            }

            return $orderHash;
        }

        public function getToken($orderData){
            ksort($orderData);
            $orderData["appId"] = $this->appId;
            $signatureData = "";

            $tokenData = "appId=".$this->appId."&orderId=".$orderData["orderId"]."&orderAmount=".$orderData["orderAmount"]."&returnUrl=".$orderData["returnUrl"]."&paymentModes=".$orderData["paymentModes"];
            //echo json_encode($orderData);
            $signature = hash_hmac('sha256', $tokenData, $this->appSecret, true);

            $signature = base64_encode($signature);
            $orderData["paymentToken"] = $signature;
            $this->token = $signature;
            $this->orderData= $orderData;


        }

        public function CreateOrder($orderData){
            $this->getOrderHash($orderData);
            $url = $this->appURLs["payments"] . "/order/config/" . $this->orderHash;

            $cfOrder = Curl::jsonGet($url);

            $this->cfOrder = $cfOrder;
            if($cfOrder["status"] == "SUCCESS"){
                return array(
                    "token" => $this->token,
                    "cfOrder" => isset($this->cfOrder["message"]) ? $this->cfOrder["message"] : $this->cfOrder,
                    "orderHash" => $this->orderHash
                );
            } else {
                return array(
                    "token" => $this->token,
                    "error" =>$this->cfOrder["message"],
                    "orderHash" => $this->orderHash
                );
            }

        }

        public function GetStatus($orderId){
            $payload = array(
                "appId" => $this->appId,
                "secretKey" => $this->appSecret,
                "orderId" => $orderId,
            );

            $res = Curl::formPost($this->appURLs["apiV1"] . "/order/info/status", $payload);
            return $res;
        }

        public function CreateUPITransaction($orderHash, $upiHandle){
            $req = array();
            $upiHandle = trim($upiHandle);
            if(is_numeric($upiHandle) && strlen($upiHandle) == 10){
                $req["upiVpa"] = "+91" . $upiHandle;
                $req["upiProvider"] = "gpay";
            } else {
                $req["upiVpa"] = $upiHandle;
                $req["upiProvider"] = "vpa";
            }
            $req["orderHash"] = $orderHash;

            $res = Curl::jsonPost($this->appURLs["payments"] . "/legacy/upi/create", $req);

            return $res;
        }

        public function Handler($inputJSON){
            $this->setEnv($inputJSON["env"]);
            if($inputJSON["action"] == "init"){
                $payload = $inputJSON["payload"];
                $orderId = $payload["orderId"];
                $orderAmount = $payload["orderAmount"];
                $returnUrl = $payload["returnUrl"];
                $orderCurrency = $payload["orderCurrency"];
                $customerName = $payload["customerName"];
                $orderNote = $payload["orderNote"];
                $customerEmail = $payload["customerEmail"];
                $customerPhone = $payload["customerPhone"];
                $notifyUrl = $payload["notifyUrl"];

                $paymentModes =  $payload["paymentModes"];
                $postData = array(
                    "orderId" => $orderId,
                    "orderAmount" => $orderAmount,
                    "orderCurrency" => $orderCurrency,
                    "orderNote" => $orderNote,
                    "customerName" => $customerName,
                    "customerPhone" => $customerPhone,
                    "customerEmail" => $customerEmail,
                    "paymentModes" => $paymentModes,
                    "returnUrl" => $returnUrl,
                    "notifyUrl" => $notifyUrl,
                );


                $token = $this->CreateOrder($postData);

                return $token ;

            } else if($inputJSON["action"] == "status"){
                $payload = $inputJSON["payload"];
                $orderId = $payload["orderId"];
                $res = $this->GetStatus($orderId);
                return $res ;
            } else if($inputJSON["action"] == "create_upi"){
                $payload = $inputJSON["payload"];
                $vpa = $payload["vpa"];
                $orderHash = $payload["orderHash"];
                $res = $this->CreateUPITransaction($orderHash, $vpa);
                return $res ;
            }

        }
    }
?>
