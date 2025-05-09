<?php

	class ModelExtensionPaymentIyzico extends Model
	{

		public function getMethod($address, $total)
		{
			$this->load->language('extension/payment/iyzico');

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
			if ($status) {
				$method_data = array(
					'code' => 'iyzico',
					'title' => $this->iyzicoMultipLangTitle($this->config->get('payment_iyzico_title')) . " " . $this->language->get('iyzico_img_title'),
					'terms' => '',
					'sort_order' => $this->config->get('payment_iyzico_sort_order')
				);
			}

			return $method_data;
		}

		private function iyzicoMultipLangTitle($title)
		{
			$this->load->language('extension/payment/iyzico');

			$language     = $this->config->get('payment_iyzico_language');
			$str_language = mb_strtolower($language);
			if (empty($str_language) or $str_language == 'null') {
				$title_language = $this->language->get('code');
			} else {
				$title_language = $str_language;
			}

			if ($title) {
				$parser = explode('|', $title);
				if (is_array($parser) && count($parser)) {
					foreach ($parser as $key => $parse) {
						$result = explode('=', $parse);
						if ($title_language == $result[0]) {
							$new_title = $result[1];
							break;
						}
					}
				}
			}
			if (!isset($new_title)) {
				$new_title = $this->language->get('iyzico');
			}

			return $new_title;
		}

		public function insertCardUserKey($customer_id, $card_user_key, $api_key)
		{
			$insertCard = $this->db->query("INSERT INTO `" . DB_PREFIX . "iyzico_card` SET
            `customer_id`   = '" . $this->db->escape($customer_id) . "',
            `card_user_key` = '" . $this->db->escape($card_user_key) . "',
            `api_key`       = '" . $this->db->escape($api_key) . "'");

			return $insertCard;
		}

		public function findUserCardKey($customer_id, $api_key)
		{
			$customer_id = $this->db->escape($customer_id);
			$api_key     = $this->db->escape($api_key);

			$card_user_key = (object)$this->db->query("SELECT card_user_key FROM " . DB_PREFIX . "iyzico_card WHERE customer_id = '" . $customer_id . "' and api_key = '" . $api_key . "' ORDER BY iyzico_card_id DESC");
			if (count($card_user_key->rows)) {
				return $card_user_key->rows[0]['card_user_key'];
			}

			return '';
		}

		public function insertIyzicoOrder($order)
		{
			$insertOrder = $this->db->query("INSERT INTO `" . DB_PREFIX . "iyzico_order` SET
            `payment_id` = '" . $this->db->escape($order->payment_id) . "',
            `order_id` = '" . $this->db->escape($order->order_id) . "',
            `total_amount` = '" . $this->db->escape($order->total_amount) . "',
            `status` = '" . $this->db->escape($order->status) . "'");

			return $insertOrder;
		}

		public function orderUpdateByInstallement($order_id, $paidPrice)
		{
			$this->load->language('extension/payment/iyzico');

			$order_id                  = $this->db->escape($order_id);
			$order_info                = $this->model_checkout_order->getOrder($order_id);
			$order_total               = (array)$this->db->query("SELECT * FROM " . DB_PREFIX . "order_total WHERE order_id = '" . $order_id . "' AND code = 'total' ");
			$last_sort_value           = $order_total['row']['sort_order'] - 1;
			$last_sort_value           = $this->db->escape($last_sort_value);
			$exchange_rate             = $this->currency->getValue($order_info['currency_code']);
			$new_amount                = str_replace(',', '', $paidPrice);
			$old_amount                = str_replace(',', '', $order_info['total'] * $order_info['currency_value']);
			$installment_fee_variation = (float)($new_amount - $old_amount) / $exchange_rate;
			$installment_fee_variation = $this->db->escape($installment_fee_variation);
			$installment_fee_desc      = $this->language->get('installement_field_desc');
			$this->db->query("INSERT INTO " . DB_PREFIX . "order_total SET order_id = '" .
				$order_id . "',code = 'iyzico_fee',  title = '" . $installment_fee_desc . "', `value` = '" .
				$installment_fee_variation . "', sort_order = '" . $last_sort_value . "'");
			$order_total_data = (array)$this->db->query("SELECT * FROM " . DB_PREFIX . "order_total WHERE order_id = '" . $order_id . "' AND code != 'total' ");
			$calculate_total  = 0;
			foreach ($order_total_data['rows'] as $row) {
				$calculate_total += $row['value'];
			}
			$calculate_total = $this->db->escape($calculate_total);
			$this->db->query("UPDATE " . DB_PREFIX . "order_total SET  `value` = '" . $calculate_total . "' WHERE order_id = '$order_id' AND code = 'total' ");
			$this->db->query("UPDATE `" . DB_PREFIX . "order` SET total = '" . $calculate_total . "' WHERE order_id = '" . $order_id . "'");
		}

		public function getCategoryName($product_id)
		{
			$product_id = $this->db->escape($product_id);
			$query      = $this->db->query("SELECT category_id FROM " . DB_PREFIX . "product_to_category WHERE product_id = '" . $product_id . "' LIMIT 1");
			if (count($query->rows)) {
				$category_id = $this->db->escape($query->rows[0]['category_id']);
				$category    = $this->db->query("SELECT name FROM " . DB_PREFIX . "category_description WHERE category_id = '" . $category_id . "' LIMIT 1");
				if ($category->rows[0]['name']) {
					$category_name = $category->rows[0]['name'];
				} else {
					$category_name = 'NO CATEGORIES';
				}
			} else {
				$category_name = 'NO CATEGORIES';
			}

			return $category_name;
		}

		public function getUserCreateDate($user_id)
		{
			$user_id          = $this->db->escape($user_id);
			$user_create_date = (object)$this->db->query("SELECT date_added FROM " . DB_PREFIX . "user WHERE user_id = '" . $user_id . "'");
			if (count($user_create_date->rows)) {
				return $user_create_date->rows[0]['date_added'];
			}

			return date('Y-m-d H:i:s');
		}
	}
