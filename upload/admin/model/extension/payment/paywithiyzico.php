<?php
class ModelExtensionPaymentPaywithiyzico extends Model {
    private $module_version 	 = VERSION;
    private $module_product_name = 'eleven-1.7';


    public function install() {
        $this->db->query("
			CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "paywithiyzico_order` (
			  `paywithiyzico_order_id` INT(11) NOT NULL AUTO_INCREMENT,
			  `payment_id` INT(11) NOT NULL,
			  `order_id` INT(11) NOT NULL,
			  `total_amount` DECIMAL( 10, 2 ) NOT NULL,
			  `status` VARCHAR(20) NOT NULL,
			  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			  PRIMARY KEY (`paywithiyzico_order_id`)
			) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;");

        $this->db->query("
			CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "paywithiyzico_card` (
			  	`paywithiyzico_card_id` INT(11) NOT NULL AUTO_INCREMENT,
			  	`customer_id` INT(11) NOT NULL,
				`card_user_key` VARCHAR(50),
				`api_key` VARCHAR(50),
			  	`created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			  	PRIMARY KEY (`paywithiyzico_card_id`)
			) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;");
    }

    public function uninstall() {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "paywithiyzico_order`;");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "paywithiyzico_card`;");
    }

    public function pkiStringGenerate($object_data) {

        $pki_value = "[";
        foreach ($object_data as $key => $data) {
            if(is_object($data)) {
                $name = var_export($key, true);
                $name = str_replace("'", "", $name);
                $pki_value .= $name."=[";
                $end_key = count(get_object_vars($data));
                $count 	 = 0;
                foreach ($data as $key => $value) {
                    $count++;
                    $name = var_export($key, true);
                    $name = str_replace("'", "", $name);
                    $pki_value .= $name."="."".$value;
                    if($end_key != $count)
                        $pki_value .= ",";
                }
                $pki_value .= "]";
            } else if(is_array($data)) {
                $name = var_export($key, true);
                $name = str_replace("'", "", $name);
                $pki_value .= $name."=[";
                $end_key = count($data);
                $count 	 = 0;
                foreach ($data as $key => $result) {
                    $count++;
                    $pki_value .= "[";

                    foreach ($result as $key => $item) {
                        $name = var_export($key, true);
                        $name = str_replace("'", "", $name);

                        $pki_value .= $name."="."".$item;
                        if(end($result) != $item) {
                            $pki_value .= ",";
                        }
                        if(end($result) == $item) {
                            if($end_key != $count) {
                                $pki_value .= "], ";

                            } else {
                                $pki_value .= "]";
                            }
                        }
                    }
                }
                if(end($data) == $result)
                    $pki_value .= "]";

            } else {
                $name = var_export($key, true);
                $name = str_replace("'", "", $name);

                $pki_value .= $name."="."".$data."";
            }
            if(end($object_data) != $data)
                $pki_value .= ",";
        }
        $pki_value .= "]";
        return $pki_value;
    }

    public function authorizationGenerate($api_key,$secret_key,$pki) {

        $rand_value	= rand(100000,99999999);
        $hash_value = $api_key.$rand_value.$secret_key.$pki;
        $hash 		= base64_encode(sha1($hash_value,true));

        $authorization 	= 'IYZWS '.$api_key.':'.$hash;

        $authorization_data = array(
            'authorization' => $authorization,
            'rand_value' 	=> $rand_value
        );

        return $authorization_data;
    }

    public function apiConnection($authorization_data,$api_connection_object) {

        $url 		= $this->config->get('payment_paywithiyzico_api_url');
        $url 		= $url.'/payment/bin/check';

        $api_connection_object = json_encode($api_connection_object);

        return $this->curlPost($api_connection_object,$authorization_data,$url);

    }

    public function overlayScript($authorization_data,$overlay_script_object) {

        $url   = "https://iyziup.iyzipay.com/";
        $url   = $url."v1/iyziup/protected/shop/detail/overlay-script";

        $overlay_script_object = json_encode($overlay_script_object);

        return $this->curlPost($overlay_script_object,$authorization_data,$url);

    }

    public function curlPost($json,$authorizationData,$url) {

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        $content_length = 0;
        if ($json) {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 0);
        curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_NONE);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);

        curl_setopt(
            $curl, CURLOPT_HTTPHEADER, array(
                "Authorization: " .$authorizationData['authorization'],
                "x-iyzi-rnd:".$authorizationData['rand_value'],
                "Content-Type: application/json",
            )
        );

        $result = json_decode(curl_exec($curl));
        curl_close($curl);


        return $result;
    }

}
