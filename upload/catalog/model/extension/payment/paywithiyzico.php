<?php

class ModelExtensionPaymentPaywithiyzico extends Model {

    public function getMethod($address, $total) {
       $currency_code = $this->session->data['currency'];
       if(!empty($currency_code) and $currency_code == 'TRY' or $currency_code == 'try')
       {
         $payment_paywithiyzico_geo_zone_id = $this->config->get('payment_paywithiyzico_geo_zone_id');
         $payment_paywithiyzico_geo_zone_id = $this->db->escape($payment_paywithiyzico_geo_zone_id);
         $address_country_id 		= $this->db->escape($address['country_id']);
         $address_zone_id 			= $this->db->escape($address['zone_id']);

         $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone_to_geo_zone` WHERE `geo_zone_id` = '" . $payment_paywithiyzico_geo_zone_id . "' AND `country_id` = '" . $address_country_id . "' AND (`zone_id` = '" . $address_zone_id . "' OR `zone_id` = '0')");

         if ($this->config->get('payment_paywithiyzico_total') > $total) {
             $status = false;
         } elseif (!$this->config->get('payment_paywithiyzico_geo_zone_id')) {
             $status = true;
         } elseif ($query->num_rows) {
             $status = true;
         } else {
             $status = false;
         }

         $language = $this->config->get('payment_iyzico_language');
         $str_language = mb_strtolower($language);
         $this->load->language('extension/payment/paywithiyzico');


         if(empty($str_language) or $str_language == 'null')
         {
             $title_language = $this->language->get('pwi_img_title'). " " .$this->language->get('pwi_title');

         }elseif ($str_language == 'tr') {

           $title_language = '<img width="20%" src="admin/view/image/payment/pay-with-iyzico-tr.svg"/>'.''.'iyzico ile Öde';
         }else {

           $title_language = '<img width="20%" src="admin/view/image/payment/pay-with-iyzico.svg"/>'.''.'Pay with iyzico';
         }

         $method_data = array();



         if ($status) {
             $method_data = array(
                 'code'       => 'paywithiyzico',
                 'title' => $title_language,
                 'terms'      => '',
                 'sort_order' => $this->config->get('payment_paywithiyzico_sort_order')
             );
         }

         return $method_data;

       }


    }

    private function paywithiyzicoMultipLangTitle($title) {

        $this->load->language('extension/payment/paywithiyzico');

        $this->load->language('extension/payment/iyzico');
        $language = $this->config->get('payment_iyzico_language');
        $str_language = mb_strtolower($language);

        if(empty($str_language) or $str_language == 'null')
        {
            $title_language 			 = $this->language->get('code');
        }else {
            $title_language 			 = $str_language;
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
            $new_title = $this->language->get('paywithiyzico');
        }

        return $new_title;

    }



    public function insertCardUserKey($customer_id,$card_user_key,$api_key) {

        $insertCard = $this->db->query("INSERT INTO `" . DB_PREFIX . "paywithiyzico_card` SET
			`customer_id` 	= '" . $this->db->escape($customer_id) . "',
			`card_user_key` = '" . $this->db->escape($card_user_key) . "',
			`api_key` 		= '" . $this->db->escape($api_key) . "'");

        return $insertCard;
    }

    public function findUserCardKey($customer_id,$api_key) {

        $customer_id = $this->db->escape($customer_id);
        $api_key 	 = $this->db->escape($api_key);

        $card_user_key = (object) $this->db->query("SELECT card_user_key FROM " . DB_PREFIX . "paywithiyzico_card WHERE customer_id = '" . $customer_id ."' and api_key = '".$api_key."' ORDER BY paywithiyzico_card_id DESC");

        if(count($card_user_key->rows)) {

            return $card_user_key->rows[0]['card_user_key'];
        }

        return '';
    }

    public function insertIyzicoOrder($order) {

        $insertOrder = $this->db->query("INSERT INTO `" . DB_PREFIX . "paywithiyzico_order` SET
			`payment_id` = '" . $this->db->escape($order->payment_id) . "',
			`order_id` = '" . $this->db->escape($order->order_id) . "',
			`total_amount` = '" . $this->db->escape($order->total_amount) . "',
			`status` = '" . $this->db->escape($order->status) . "'");

        return $insertOrder;
    }

    public function orderUpdateByInstallement($order_id,$paidPrice) {

        $order_id 		 = $this->db->escape($order_id);

        $order_info 	 = $this->model_checkout_order->getOrder($order_id);

        $order_total = (array) $this->db->query("SELECT * FROM " . DB_PREFIX . "order_total WHERE order_id = '" . $order_id . "' AND code = 'total' ");

        $last_sort_value = $order_total['row']['sort_order'] - 1;
        $last_sort_value = $this->db->escape($last_sort_value);

        $exchange_rate = $this->currency->getValue($order_info['currency_code']);

        $new_amount = str_replace(',', '', $paidPrice);
        $old_amount = str_replace(',', '', $order_info['total'] * $order_info['currency_value']);
        $installment_fee_variation = (float) ($new_amount - $old_amount) / $exchange_rate;
        $installment_fee_variation = $this->db->escape($installment_fee_variation);

        $this->db->query("INSERT INTO " . DB_PREFIX . "order_total SET order_id = '" .
            $order_id . "',code = 'paywithiyzico_fee',  title = 'Taksit Ücreti', `value` = '" .
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

            $category 	 = $this->db->query("SELECT name FROM " . DB_PREFIX . "category_description WHERE category_id = '" . $category_id . "' LIMIT 1");

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
