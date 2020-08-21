<?php 

    class Transaction {

        public $transaction = array();

        private $const = array();

        private $payload = array();

        private $transactionGenerated = array();
        private $transactionStatus = array();

        private $urlTransaction = "https://banipay.me:8443/api/payments/transaction";
        private $urlTransactionInfo = "https://banipay.me:8443/api/payments/info";


        // array keys required
        private $keys = array("withInvoice", "externalCode", "paymentDescription", "details");
        
        // public function __construct($data, $params){
        public function __construct(){
            /*
            $this->transaction = $data;
            $this->const = array(
                "affiliateCode"   => $params['affiliateCode'],
                "expireMinutes"   => $params['expireMinutes'],
                "failedUrl"       => $params['failedUrl'],
                "notificationUrl" => $params['notificationUrl']
            );
            */
        }

        public function register ($data, $params){
            $this->transaction = $data;
            $this->const = array(
                "affiliateCode"   => $params['affiliateCode'],
                "expireMinutes"   => $params['expireMinutes'],
                "failedUrl"       => $params['failedUrl'],
                "successUrl"      => $params['successUrl'],
                "notificationUrl" => $params['notificationUrl']
            );
            if( self::verify() ) {
                $data = self::toComplete();
                return self::send();
            } else {
                return "Incorrectly formatted data";
            }
        }

        private function verify() {
            if (is_array($this->transaction)) {
                foreach ($this->keys as $key => $value) {
                    if ( !array_key_exists($value, $this->transaction) ) return false;
                }
                return true;
            } else {
                return false;
            } 
        }

        private function toComplete(){
            $this->payload = $this->const + $this->transaction;
            return $this->payload;
        }

        public function getPayload(){
            return json_encode($this->payload);
        }

        private function send(){
            $ch = curl_init($this->urlTransaction);
            $payload = self::getPayload();

            // Attach encoded JSON string to the POST fields
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

            // Set the content type to application/json
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));

            // Return response instead of outputting
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            // Execute the POST request
            $this->transactionGenerated = json_decode(curl_exec($ch));

            // Close cURL resource
            curl_close($ch);

            return $this->transactionGenerated;
        }

        public function getTransaction ($paymentId, $transactionGenerated){

            $ch = curl_init("{$this->urlTransactionInfo}/{$paymentId}/{$transactionGenerated}");

            // Return response instead of outputting
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            // Execute the POST request
            $this->transactionStatus = json_decode(curl_exec($ch));
            // error_log(print_r($this->transactionStatus, true));

            // Close cURL resource
            curl_close($ch);

            return $this->transactionStatus;
        }

    }
   
?>