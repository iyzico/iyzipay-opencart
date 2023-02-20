<?php

class ModelExtensionPaymentIyzico extends Model {

    public function getMethod($address, $total) {

        $payment_iyzico_geo_zone_id = $this->config->get('payment_iyzico_geo_zone_id');
        $payment_iyzico_geo_zone_id = $this->db->escape($payment_iyzico_geo_zone_id);
        $address_country_id         = $this->db->escape($address['country_id']);
        $address_zone_id            = $this->db->escape($address['zone_id']);

        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone_to_geo_zone` WHERE `geo_zone_id` = '" . $payment_iyzico_geo_zone_id . "' AND `country_id` = '" . $address_country_id . "' AND (`zone_id` = '" . $address_zone_id . "' OR `zone_id` = '0')");

        if ($this->config->get('payment_iyzico_total') > $total) {
            $status = false;
        } elseif (!$this->config->get('payment_iyzico_geo_zone_id')) {
            $status = true;
        } elseif ($query->num_rows) {
            $status = true;
        } else {
            $status = false;
        }

        $method_data = array();

        $this->load->language('extension/payment/iyzico');

        if ($status) {
            $method_data = array(
                'code'       => 'iyzico',
                'title'      => $this->iyzicoMultipLangTitle($this->config->get('payment_iyzico_title')) . " ".$this->language->get('iyzico_img_title'),
                'terms'      => '',
                'sort_order' => $this->config->get('payment_iyzico_sort_order')
            );
        }

        return $method_data;
    }

    private function iyzicoMultipLangTitle($title) {

        $this->load->language('extension/payment/iyzico');
        $language = $this->config->get('payment_iyzico_language');
        $str_language = mb_strtolower($language);

        if(empty($str_language) or $str_language == 'null')
        {
            $title_language              = $this->language->get('code');
        }else {
            $title_language              = $str_language;
        }

        if($title) {

            $parser = explode('|',$title);

            if(is_array($parser) && count($parser)) {

                foreach ($parser as $key => $parse) {
                    $result = explode('=',$parse);

                    if($title_language == $result[0]) {
                        $new_title = $result[1];
                        break;
                    }
                }

            }

        }
        if(!isset($new_title)) {
            $new_title = $this->language->get('iyzico');
        }

        return $new_title;

    }

    public function authorizationGenerate($pki,$api_key,$secret_key,$rand_value) {

        $hash_value = $api_key.$rand_value.$secret_key.$pki;
        $hash       = base64_encode(sha1($hash_value,true));

        $authorization  = 'IYZWS '.$api_key.':'.$hash;

        $authorization_data = array(
            'authorization' => $authorization,
            'rand_value'    => $rand_value
        );

        return $authorization_data;

    }


    public function createFormInitializObjectSort($object_data) {

        $form_object = new stdClass();

        $form_object->locale                        = $object_data->locale;
        $form_object->conversationId                = $object_data->conversationId;
        $form_object->price                         = $object_data->price;
        $form_object->basketId                      = $object_data->basketId;
        $form_object->paymentGroup                  = $object_data->paymentGroup;

        $form_object->buyer = new stdClass();
        $form_object->buyer = $object_data->buyer;

        $form_object->shippingAddress = new stdClass();
        $form_object->shippingAddress = $object_data->shippingAddress;

        $form_object->billingAddress = new stdClass();
        $form_object->billingAddress = $object_data->billingAddress;

        foreach ($object_data->basketItems as $key => $item) {

            $form_object->basketItems[$key] = new stdClass();
            $form_object->basketItems[$key] = $item;

        }

        $form_object->callbackUrl           = $object_data->callbackUrl;
        $form_object->paymentSource         = $object_data->paymentSource;
        $form_object->currency              = $object_data->currency;
        $form_object->paidPrice             = $object_data->paidPrice;
        $form_object->forceThreeDS          = $object_data->forceThreeDS;
        $form_object->cardUserKey           = $object_data->cardUserKey;

        return $form_object;
    }

    public function pkiStringGenerate($object_data) {

        $pki_value = "[";
        foreach ($object_data as $key => $data) {
            if(is_object($data)) {
                $name = var_export($key, true);
                $name = str_replace("'", "", $name);
                $pki_value .= $name."=[";
                $end_key = count(get_object_vars($data));
                $count   = 0;
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
                $count   = 0;
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


    public function hashGenerate($pki,$api_key,$secret_key,$random_value) {

        $hash = $api_key . $random_value . $secret_key . $pki;

        return base64_encode(sha1($hash, true));

    }

    public function createFormInitializeDetailRequest($json,$authorization_data) {

        $url = $this->config->get('payment_iyzico_api_url');
        $url = $url.'/payment/iyzipos/checkoutform/auth/ecom/detail';

        return $this->curlPost($json,$authorization_data,$url);

    }


    public function createFormInitializeRequest($json,$authorization_data) {

        $url = $this->config->get('payment_iyzico_api_url');
        $url = $url.'/payment/iyzipos/checkoutform/initialize/auth/ecom';

        return $this->curlPost($json,$authorization_data,$url);
    }


    public function curlPost($json,$authorization_data,$url) {

        $phpVersion = phpversion();

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);

        if ($json) {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 0);
        curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_NONE);
        curl_setopt($curl, CURLOPT_TIMEOUT, 150);

        curl_setopt(
            $curl, CURLOPT_HTTPHEADER, array(
                "Authorization: " .$authorization_data['authorization'],
                "x-iyzi-rnd:".$authorization_data['rand_value'],
                "opencart-php-version:".$phpVersion,
                "Content-Type: application/json",
            )
        );

        $result = json_decode(curl_exec($curl));
        curl_close($curl);



        return $result;
    }

    public function insertCardUserKey($customer_id,$card_user_key,$api_key) {

        $insertCard = $this->db->query("INSERT INTO `" . DB_PREFIX . "iyzico_card` SET
            `customer_id`   = '" . $this->db->escape($customer_id) . "',
            `card_user_key` = '" . $this->db->escape($card_user_key) . "',
            `api_key`       = '" . $this->db->escape($api_key) . "'");

        return $insertCard;
    }

    public function findUserCardKey($customer_id,$api_key) {

        $customer_id = $this->db->escape($customer_id);
        $api_key     = $this->db->escape($api_key);

        $card_user_key = (object) $this->db->query("SELECT card_user_key FROM " . DB_PREFIX . "iyzico_card WHERE customer_id = '" . $customer_id ."' and api_key = '".$api_key."' ORDER BY iyzico_card_id DESC");

        if(count($card_user_key->rows)) {

            return $card_user_key->rows[0]['card_user_key'];
        }

        return '';
    }

    public function insertIyzicoOrder($order) {

        $insertOrder = $this->db->query("INSERT INTO `" . DB_PREFIX . "iyzico_order` SET
            `payment_id` = '" . $this->db->escape($order->payment_id) . "',
            `order_id` = '" . $this->db->escape($order->order_id) . "',
            `total_amount` = '" . $this->db->escape($order->total_amount) . "',
            `status` = '" . $this->db->escape($order->status) . "'");

        return $insertOrder;
    }

    public function orderUpdateByInstallement($order_id,$paidPrice) {

        $order_id        = $this->db->escape($order_id);

        $order_info      = $this->model_checkout_order->getOrder($order_id);

        $this->load->language('extension/payment/iyzico');

        $order_total = (array) $this->db->query("SELECT * FROM " . DB_PREFIX . "order_total WHERE order_id = '" . $order_id . "' AND code = 'total' ");

        $last_sort_value = $order_total['row']['sort_order'] - 1;
        $last_sort_value = $this->db->escape($last_sort_value);

        $exchange_rate = $this->currency->getValue($order_info['currency_code']);

        $new_amount = str_replace(',', '', $paidPrice);
        $old_amount = str_replace(',', '', $order_info['total'] * $order_info['currency_value']);
        $installment_fee_variation = (float) ($new_amount - $old_amount) / $exchange_rate;
        $installment_fee_variation = $this->db->escape($installment_fee_variation);
        $installment_fee_desc = $this->language->get('installement_field_desc');

        $this->db->query("INSERT INTO " . DB_PREFIX . "order_total SET order_id = '" .
            $order_id . "',code = 'iyzico_fee',  title = '".$installment_fee_desc."', `value` = '" .
            $installment_fee_variation . "', sort_order = '" . $last_sort_value . "'");


        $order_total_data = (array) $this->db->query("SELECT * FROM " . DB_PREFIX . "order_total WHERE order_id = '" . $order_id . "' AND code != 'total' ");

        $calculate_total = 0;

        foreach ($order_total_data['rows'] as $row) {
            $calculate_total += $row['value'];
        }

        $calculate_total = $this->db->escape($calculate_total);

        $this->db->query("UPDATE " . DB_PREFIX . "order_total SET  `value` = '" . $calculate_total . "' WHERE order_id = '$order_id' AND code = 'total' ");

        $this->db->query("UPDATE `" . DB_PREFIX . "order` SET total = '" . $calculate_total . "' WHERE order_id = '" . $order_id . "'");

    }

    public function getCategoryName($product_id) {

        $product_id = $this->db->escape($product_id);

        $query = $this->db->query("SELECT category_id FROM " . DB_PREFIX . "product_to_category WHERE product_id = '" . $product_id . "' LIMIT 1");


        if(count($query->rows)) {

            $category_id = $this->db->escape($query->rows[0]['category_id']);

            $category    = $this->db->query("SELECT name FROM " . DB_PREFIX . "category_description WHERE category_id = '" . $category_id . "' LIMIT 1");

            if($category->rows[0]['name']) {
                $category_name = $category->rows[0]['name'];
            } else {
                $category_name = 'NO CATEGORIES';
            }

        } else {
            $category_name = 'NO CATEGORIES';
        }

        return $category_name;
    }


    public function getUserCreateDate($user_id) {

        $user_id = $this->db->escape($user_id);

        $user_create_date = (object) $this->db->query("SELECT date_added FROM " . DB_PREFIX . "user WHERE user_id = '" . $user_id ."'");

        if(count($user_create_date->rows)) {

            return $user_create_date->rows[0]['date_added'];
        }

        return date('Y-m-d H:i:s');
    }



}
