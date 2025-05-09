<?php

	namespace Opencart\Catalog\Model\Extension\paywithiyzico\Payment;

	use Opencart\System\Engine\Model;

	class paywithiyzico extends Model
	{

		public function getMethods($address, array &$total = null): array
		{
			$method_data = array();

			if ($this->cart->hasSubscription()) {
				$status = false;
			} elseif ($this->cart->hasShipping()) {
				$status = true;
			} elseif (!$this->config->get('config_checkout_payment_address')) {
				$status = true;
			} elseif (!$this->config->get('payment_paywithiyzico_geo_zone_id')) {
				$status = true;
			} else {
				$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone_to_geo_zone` WHERE `geo_zone_id` = '" . (int)$this->config->get('payment_paywithiyzico_geo_zone_id') . "' AND `country_id` = '" . (int)$address['country_id'] . "' AND (`zone_id` = '" . (int)$address['zone_id'] . "' OR `zone_id` = '0')");
				if ($query->num_rows) {
					$status = true;
				} else {
					$status = false;
				}
			}


			if ($status) {
				$option_data['paywithiyzico'] = [
					'code' => 'paywithiyzico.paywithiyzico',
					'name' => $this->paywithiyzicoMultipLangTitle("pwi_title"),
				];

				$method_data = [
					'code' => 'paywithiyzico',
					'name' => $this->paywithiyzicoMultipLangTitle("pwi_title"),
					'option' => $option_data,
					'sort_order' => $this->config->get('payment_paywithiyzico_sort_order')
				];
			}

			return $method_data;
		}

		private function paywithiyzicoMultipLangTitle($title)
		{

			$this->load->language('extension/paywithiyzico/payment/paywithiyzico');

			$this->load->language('extension/iyzico/payment/iyzico');
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
				$new_title = $this->language->get('paywithiyzico');
			}

			return $new_title;

		}

		public function insertCardUserKey($customerId, $cardUserKey, $apiKey)
		{
			return $this->db->query("INSERT INTO `" . DB_PREFIX . "paywithiyzico_card` SET
        `customer_id` 	= '" . $this->db->escape($customerId) . "',
        `card_user_key` = '" . $this->db->escape($cardUserKey) . "',
        `api_key` 		= '" . $this->db->escape($apiKey) . "'");
		}

		public function findUserCardKey($customerId, $apiKey): int|string
		{
			$customerId  = $this->db->escape($customerId);
			$apiKey      = $this->db->escape($apiKey);
			$cardUserKey = (object)$this->db->query("SELECT card_user_key FROM " . DB_PREFIX . "paywithiyzico_card WHERE customer_id = '" . $customerId . "' and api_key = '" . $apiKey . "' ORDER BY paywithiyzico_card_id DESC");

			return count($cardUserKey->rows) ? $cardUserKey->rows[0]['card_user_key'] : "";
		}

		public function insertIyzicoOrder($order)
		{
			return $this->db->query("INSERT INTO `" . DB_PREFIX . "paywithiyzico_order` SET
        `payment_id` = '" . $this->db->escape($order->payment_id) . "',
        `order_id` = '" . $this->db->escape($order->order_id) . "',
        `total_amount` = '" . $this->db->escape($order->total_amount) . "',
        `status` = '" . $this->db->escape($order->status) . "'");
		}

		public function orderUpdateByInstallement($orderId, $paidPrice)
		{

			$orderId   = $this->db->escape($orderId);
			$orderInfo = $this->model_checkout_order->getOrder($orderId);

			$this->load->language('extension/iyzico/payment/iyzico');

			$orderTotal    = (array)$this->db->query("SELECT * FROM " . DB_PREFIX . "order_total WHERE order_id = '" . $orderId . "' AND code = 'total' ");
			$lastSortValue = $this->db->escape($orderTotal['row']['sort_order'] - 1);

			$exchange_rate = $this->currency->getValue($orderInfo['currency_code']);

			$new_amount                = str_replace(',', '', $paidPrice);
			$old_amount                = str_replace(',', '', $orderInfo['total'] * $orderInfo['currency_value']);
			$installment_fee_variation = (float)($new_amount - $old_amount) / $exchange_rate;
			$installment_fee_variation = $this->db->escape($installment_fee_variation);
			$installment_fee_desc      = $this->language->get('installement_field_desc');

			$this->db->query("INSERT INTO " . DB_PREFIX . "order_total SET order_id = '" .
				$orderId . "',code = 'paywithiyzico_fee', extension='paywithiyzico',  title = '" . $installment_fee_desc . "', `value` = '" .
				$installment_fee_variation . "', sort_order = '" . $lastSortValue . "'");


			$orderTotalData = (array)$this->db->query("SELECT * FROM " . DB_PREFIX . "order_total WHERE order_id = '" . $orderId . "' AND code != 'total' ");
			$calculateTotal = 0;

			foreach ($orderTotalData['rows'] as $row) {
				$calculateTotal += $row['value'];
			}

			$calculateTotal = $this->db->escape($calculateTotal);

			$this->db->query("UPDATE " . DB_PREFIX . "order_total SET  `value` = '" . $calculateTotal . "' WHERE order_id = '$orderId' AND code = 'total' ");
			$this->db->query("UPDATE `" . DB_PREFIX . "order` SET total = '" . $calculateTotal . "' WHERE order_id = '" . $orderId . "'");

		}

		public function getCategoryName($productId)
		{

			$productId = $this->db->escape($productId);
			$query     = $this->db->query("SELECT category_id FROM " . DB_PREFIX . "product_to_category WHERE product_id = '" . $productId . "' LIMIT 1");


			if (count($query->rows)) {
				$categoryId = $this->db->escape($query->rows[0]['category_id']);
				$category   = $this->db->query("SELECT name FROM " . DB_PREFIX . "category_description WHERE category_id = '" . $categoryId . "' LIMIT 1");
				if ($category->rows[0]['name'])
					$categoryName = $category->rows[0]['name'];
				else
					$categoryName = 'NO CATEGORIES';
			} else {
				$categoryName = 'NO CATEGORIES';
			}

			$categoryName = html_entity_decode($categoryName);
			$categoryName = trim($categoryName);

			return $categoryName;
		}


		public function getUserCreateDate($userId)
		{
			$userId           = $this->db->escape($userId);
			$user_create_date = (object)$this->db->query("SELECT date_added FROM " . DB_PREFIX . "user WHERE user_id = '" . $userId . "'");
			if (count($user_create_date->rows)) {
				return $user_create_date->rows[0]['date_added'];
			}
			return date('Y-m-d H:i:s');
		}


	}
