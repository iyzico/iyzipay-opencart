<?php

	namespace Opencart\Catalog\Controller\Extension\paywithiyzico\Payment;

	use stdClass;

	require_once(DIR_EXTENSION . 'iyzico/system/library/iyzipay-php/IyzipayBootstrap.php');
	\IyzipayBootstrap::init(DIR_EXTENSION . 'iyzico/system/library/iyzipay-php/src');

	class paywithiyzico extends \Opencart\System\Engine\Controller
	{
		private $module_version = VERSION;
		private $module_product_name = '2.6.0';


		private function setcookieSameSite($name, $value, $expire, $path, $domain, $secure, $httponly)
		{
			if (PHP_VERSION_ID < 70300) {
				setcookie($name, $value, $expire, "$path; samesite=None", $domain, $secure, $httponly);
			} else {
				setcookie($name, $value, [
					'expires' => $expire,
					'path' => $path,
					'domain' => $domain,
					'samesite' => 'None',
					'secure' => $secure,
					'httponly' => $httponly,
				]);
			}
		}

		public function index()
		{
			$cookieControl = false;

			if (isset($_COOKIE['PHPSESSID'])) {
				$sessionKey    = "PHPSESSID";
				$sessionValue  = $_COOKIE['PHPSESSID'];
				$cookieControl = true;
			}

			if (isset($_COOKIE['OCSESSID'])) {

				$sessionKey    = "OCSESSID";
				$sessionValue  = $_COOKIE['OCSESSID'];
				$cookieControl = true;
			}

			if ($cookieControl) {
				$setCookie = $this->setcookieSameSite($sessionKey, $sessionValue, time() + 86400, "/", $_SERVER['SERVER_NAME'], true, true);
			}

			$this->load->model('checkout/order');
			$this->load->model('setting/setting');
			$this->load->model('extension/paywithiyzico/payment/paywithiyzico');

			$module_attribute = false;
			$order_id         = (int)$this->session->data['order_id'];
			$customer_id      = (int)isset($this->session->data['customer_id']) ? $this->session->data['customer_id'] : 0;
			$user_id          = (int)isset($this->session->data['user_id']) ? $this->session->data['user_id'] : 0;
			$order_info       = $this->model_checkout_order->getOrder($order_id);
			$products         = $this->cart->getProducts();

			$api_key        = $this->config->get('payment_iyzico_api_key');
			$secret_key     = $this->config->get('payment_iyzico_secret_key');
			$payment_source = "OPENCART|" . $this->module_version . "|" . $this->module_product_name . "|PWI";

			$user_create_date                       = $this->model_extension_paywithiyzico_payment_paywithiyzico->getUserCreateDate($user_id);
			$this->session->data['conversation_id'] = $order_id;
			$order_info['payment_address']          = $order_info['payment_address_1'] . " " . $order_info['payment_address_2'];
			$order_info['shipping_address']         = $order_info['shipping_address_1'] . " " . $order_info['shipping_address_2'];


			/* Order Detail */
			$paywithiyzico = new \Iyzipay\Request\CreatePayWithIyzicoInitializeRequest();

			$language     = $this->config->get('payment_iyzico_language');
			$str_language = mb_strtolower($language);

			if (empty($str_language) or $str_language == 'null') {
				$paywithiyzico->setLocale($this->language->get('code'));
			} else {
				$paywithiyzico->setLocale($str_language);
			}

			$paywithiyzico->setConversationId($order_id);
			$paywithiyzico->setPrice($this->priceParser($this->itemPriceSubTotal($products) * $order_info['currency_value']));
			$paywithiyzico->setPaidPrice($this->priceParser($order_info['total'] * $order_info['currency_value']));
			$paywithiyzico->setCurrency($order_info['currency_code']);
			$paywithiyzico->setBasketId($order_id);
			$paywithiyzico->setPaymentGroup("PRODUCT");
			$paywithiyzico->setCallbackUrl($this->url->link('extension/paywithiyzico/payment/paywithiyzico.getCallBack', '', true));
			$paywithiyzico->setPaymentSource($payment_source);


			if ((float)$paywithiyzico->getPaidPrice() <= 0) {
				return false;
			}

			$buyer = new \Iyzipay\Model\Buyer();
			$buyer->setId($order_info['customer_id']);
			$buyer->setName($this->dataCheck($order_info['firstname']));
			$buyer->setSurname($this->dataCheck($order_info['lastname']));
			$buyer->setIdentityNumber('11111111111');
			$buyer->setEmail($this->dataCheck($order_info['email']));
			$buyer->setGsmNumber($this->dataCheck($order_info['telephone']));
			$buyer->setRegistrationDate($user_create_date);
			$buyer->setLastLoginDate(date('Y-m-d H:i:s'));
			$buyer->setRegistrationAddress($this->dataCheck($order_info['payment_address']));
			$buyer->setCity($this->dataCheck($order_info['payment_zone']));
			$buyer->setCountry($this->dataCheck($order_info['payment_country']));
			$buyer->setZipCode($this->dataCheck($order_info['payment_postcode']));
			$buyer->setIp($this->dataCheck($this->getIpAdress()));
			$paywithiyzico->setBuyer($buyer);

			$shippingAddress = new \Iyzipay\Model\Address();
			$shippingAddress->setAddress($this->dataCheck($order_info['shipping_address']));
			$shippingAddress->setZipCode($this->dataCheck($order_info['shipping_postcode']));
			$shippingAddress->setContactName($this->dataCheck($order_info['shipping_firstname']));
			$shippingAddress->setCity($this->dataCheck($order_info['shipping_zone']));
			$shippingAddress->setCountry($this->dataCheck($order_info['shipping_country']));
			$paywithiyzico->setShippingAddress($shippingAddress);


			$billingAddress = new \Iyzipay\Model\Address();
			$billingAddress->setAddress($this->dataCheck($order_info['payment_address']));
			$billingAddress->setZipCode($this->dataCheck($order_info['payment_postcode']));
			$billingAddress->setContactName($this->dataCheck($order_info['payment_firstname']));
			$billingAddress->setCity($this->dataCheck($order_info['payment_zone']));
			$billingAddress->setCountry($this->dataCheck($order_info['payment_country']));
			$paywithiyzico->setBillingAddress($billingAddress);


			$basketItems = [];
			foreach ($products as $product) {
				$price = $product['total'] * $order_info['currency_value'];

				if ($price) {
					$item = new \Iyzipay\Model\BasketItem();
					$item->setId($product['product_id']);
					$item->setPrice($this->priceParser($price));
					$item->setName($product['name']);
					$item->setCategory1($this->model_extension_paywithiyzico_payment_paywithiyzico->getCategoryName($product['product_id']));
					$item->setItemType("PHYSICAL");
					$basketItems[] = $item;
				}
			}


			$shipping = $this->shippingInfo();
			if (!empty($shipping) && $shipping['cost'] && $shipping['cost'] != '0.00') {


				$basketItem = new \Iyzipay\Model\BasketItem();
				$basketItem->setId('Kargo');
				$basketItem->setPrice($this->priceParser($shipping['cost'] * $order_info['currency_value']));
				$basketItem->setName($shipping['name']);
				$basketItem->setCategory1("Kargo");
				$basketItem->setItemType("VIRTUAL");
				$basketItems[] = $basketItem;
			}
			$paywithiyzico->setBasketItems($basketItems);

			#  iyzico Options
			$options = new \Iyzipay\Options();
			$options->setApiKey($api_key);
			$options->setSecretKey($secret_key);

			if ($this->config->get('payment_iyzico_api_channel') == 'sandbox') {
				$options->setBaseUrl("https://sandbox-api.iyzipay.com");
			} else {
				$options->setBaseUrl("https://api.iyzipay.com");
			}


			$form_response = \Iyzipay\Model\PayWithIyzicoInitialize::create($paywithiyzico, $options);

			if (isset($this->session->data['order_id'])) {
				unset($this->session->data['payment_method']);
				unset($this->session->data['payment_methods']);
			}

			$data['pwi_redirect'] = $form_response->getPayWithIyzicoPageUrl();
			return $this->load->view('extension/paywithiyzico/payment/paywithiyzico_form', $data);
		}


		public function getCallBack()
		{
			try {

				$this->load->language('extension/paywithiyzico/payment/paywithiyzico');
				if (!isset($this->request->post['token']) || empty($this->request->post['token'])) {
					$errorMessage = 'INVALID_TOKEN';
					throw new \Exception($errorMessage);
				}

				$this->load->model('checkout/order');
				$this->load->model('extension/paywithiyzico/payment/paywithiyzico');

				$api_key    = $this->config->get('payment_iyzico_api_key');
				$secret_key = $this->config->get('payment_iyzico_secret_key');

				$conversation_id = (int)$this->session->data['conversation_id'];
				$order_id        = (int)$this->session->data['order_id'];
				$customer_id     = isset($this->session->data['customer_id']) ? (int)$this->session->data['customer_id'] : 0;

				$detail_object = new \Iyzipay\Request\RetrievePayWithIyzicoRequest();

				$detail_object->setLocale($this->language->get('code'));
				$detail_object->setConversationId($conversation_id);
				$detail_object->setToken($this->db->escape($this->request->post['token']));

				$options = new \Iyzipay\Options();
				$options->setApiKey($api_key);
				$options->setSecretKey($secret_key);

				if ($this->config->get('payment_iyzico_api_channel') == 'sandbox') {
					$options->setBaseUrl("https://sandbox-api.iyzipay.com");
				} else {
					$options->setBaseUrl("https://api.iyzipay.com");
				}

				$request_response = \Iyzipay\Model\PayWithIyzico::retrieve($detail_object, $options);

				$paywithiyzico_local_order               = new stdClass;
				$paywithiyzico_local_order->payment_id   = !empty($request_response->getPaymentId()) ? (int)$request_response->getPaymentId() : '';
				$paywithiyzico_local_order->order_id     = (int)$this->session->data['order_id'];
				$paywithiyzico_local_order->total_amount = !empty($request_response->getPaidPrice()) ? (float)$request_response->getPaidPrice() : '';
				$paywithiyzico_local_order->status       = $request_response->getPaymentStatus();

				$paywithiyzico_order_insert = $this->model_extension_paywithiyzico_payment_paywithiyzico->insertIyzicoOrder($paywithiyzico_local_order);
				if ($request_response->getPaymentStatus() == 'PENDING_CREDIT' && $request_response->getStatus() == 'success') {
					$orderMessage = 'Alışveriş kredisi işlemi başlatıldı.';
					$this->model_checkout_order->addHistory($paywithiyzico_local_order->order_id, 1, $orderMessage);
					$this->setWebhookText(1);
					return $this->response->redirect($this->url->link('extension/paywithiyzico/payment/paywithiyzico.successpage'));
				}
				$this->setWebhookText(0);

				if ($request_response->getPaymentStatus() == 'INIT_BANK_TRANSFER' && $request_response->getStatus() == 'success') {
					$orderMessage = 'iyzico Banka Havale/EFT ödemesi bekleniyor.';
					$this->model_checkout_order->addHistory($paywithiyzico_local_order->order_id, 1, $orderMessage);
					$this->setWebhookText(0);
					return $this->response->redirect($this->url->link('extension/paywithiyzico/payment/paywithiyzico.successpage'));
				}


				if ($request_response->getPaymentStatus() != 'SUCCESS' || $request_response->getStatus() != 'success' || $order_id != $request_response->getBasketId()) {
					/* Redirect Error */
					$errorMessage = $request_response->getErrorMessage() ?? $this->language->get('payment_failed');
					throw new \Exception($errorMessage);
				}


				if ($request_response->getCardUserKey() !== null) {
					if ($customer_id) {
						$cardUserKey = $this->model_extension_paywithiyzico_payment_paywithiyzico->findUserCardKey($customer_id, $api_key);
						if ($request_response->getCardUserKey() != $cardUserKey) {
							$this->model_extension_paywithiyzico_payment_paywithiyzico->insertCardUserKey($customer_id, $request_response->getCardUserKey(), $api_key);
						}
					}
				}

				$payment_id         = $this->db->escape($request_response->getPaymentId());
				$payment_field_desc = $this->language->get('payment_field_desc');
				if (!empty($payment_id)) {
					$message = $payment_field_desc . $payment_id . "\n";
				}

				$installment = $request_response->getInstallment();

				if ($installment > 1) {
					$installement_field_desc = $this->language->get('installement_field_desc');
					$this->model_extension_paywithiyzico_payment_paywithiyzico->orderUpdateByInstallement($paywithiyzico_local_order->order_id, $request_response->getPaidPrice());
					$this->model_checkout_order->addHistory($paywithiyzico_local_order->order_id, $this->config->get('payment_paywithiyzico_order_status'), $message);
					$messageInstallement = $request_response->getCardFamily() . ' - ' . $request_response->getInstallment() . $installement_field_desc;
					$this->model_checkout_order->addHistory($paywithiyzico_local_order->order_id, $this->config->get('payment_paywithiyzico_order_status'), $messageInstallement);
				} else {
					$this->model_checkout_order->addHistory($paywithiyzico_local_order->order_id, $this->config->get('payment_paywithiyzico_order_status'), $message);
				}
				$this->setWebhookText(0);

				return $this->response->redirect($this->url->link('extension/paywithiyzico/payment/paywithiyzico.successpage'));

			} catch (Exception $e) {
				$errorMessage                                       = $request_response->getErrorMessage() !== null ? $request_response->getErrorMessage() : $e->getMessage();
				$this->session->data['paywithiyzico_error_message'] = $errorMessage;
				return $this->response->redirect($this->url->link('extension/paywithiyzico/payment/paywithiyzico.errorpage'));
			}
		}

		public function errorPage()
		{
			$data['continue']       = $this->url->link('common/home');
			$data['column_left']    = $this->load->controller('common/column_left');
			$data['column_right']   = $this->load->controller('common/column_right');
			$data['content_top']    = $this->load->controller('common/content_top');
			$data['content_bottom'] = $this->load->controller('common/content_bottom');
			$data['footer']         = $this->load->controller('common/footer');
			$data['header']         = $this->load->controller('common/header');
			$data['error_title']    = 'Ödemeniz Alınamadı.';
			$data['error_message']  = $this->session->data['paywithiyzico_error_message'];
			$data['error_icon']     = 'catalog/view/theme/default/image/payment/paywithiyzico_error_icon.png';

			return $this->response->setOutput($this->load->view('extension/paywithiyzico/payment/paywithiyzico_error', $data));
		}

		public function successPage()
		{
			if (!isset($this->session->data['order_id'])) {
				return $this->response->redirect($this->url->link('common/home'));
			}

			$this->load->language('account/order');

			$order_id = $this->session->data['order_id'];

			if (isset($this->session->data['order_id'])) {
				$this->cart->clear();

				unset($this->session->data['shipping_method']);
				unset($this->session->data['shipping_methods']);
				unset($this->session->data['payment_method']);
				unset($this->session->data['payment_methods']);
				unset($this->session->data['guest']);
				unset($this->session->data['comment']);
				unset($this->session->data['coupon']);
				unset($this->session->data['reward']);
				unset($this->session->data['voucher']);
				unset($this->session->data['vouchers']);
				unset($this->session->data['totals']);
			}

			$this->load->model('account/order');
			$this->load->model('catalog/product');
			$this->load->model('checkout/order');
			$this->load->model('tool/upload');
			$order_info = $this->model_checkout_order->getOrder($order_id);


			$data['products'] = array();
			$products         = $this->model_account_order->getProducts($order_id);
			foreach ($products as $product) {
				$option_data = array();
				$options     = $this->model_account_order->getOptions($order_id, $product['order_product_id']);
				foreach ($options as $option) {
					if ($option['type'] != 'file') {
						$value = $option['value'];
					} else {
						$upload_info = $this->model_tool_upload->getUploadByCode($option['value']);

						if ($upload_info) {
							$value = $upload_info['name'];
						} else {
							$value = '';
						}
					}

					$option_data[] = array(
						'name' => $option['name'],
						'value' => (strlen($value) > 20 ? utf8_substr($value, 0, 20) . '..' : $value)
					);
				}

				$product_info = $this->model_catalog_product->getProduct($product['product_id']);
				if ($product_info) {
					$reorder = $this->url->link('account/order/reorder', 'order_id=' . $order_id . '&order_product_id=' . $product['order_product_id'], true);
				} else {
					$reorder = '';
				}

				$data['products'][] = array(
					'name' => $product['name'],
					'model' => $product['model'],
					'option' => $option_data,
					'quantity' => $product['quantity'],
					'price' => $this->currency->format($product['price'] + ($this->config->get('config_tax') ? $product['tax'] : 0), $order_info['currency_code'], $order_info['currency_value']),
					'total' => $this->currency->format($product['total'] + ($this->config->get('config_tax') ? ($product['tax'] * $product['quantity']) : 0), $order_info['currency_code'], $order_info['currency_value']),
					'reorder' => $reorder,
					'return' => $this->url->link('account/return/add', 'order_id=' . $order_info['order_id'] . '&product_id=' . $product['product_id'], true)
				);
			}


			$data['vouchers'] = array();

			/** This is not supported in OpenCart 4.X
			 * $vouchers = $this->model_account_order->getVouchers($order_id);
			 * foreach ($vouchers as $voucher) {
			 *    $data['vouchers'][] = array(
			 *        'description' => $voucher['description'],
			 *        'amount' => $this->currency->format($voucher['amount'], $order_info['currency_code'], $order_info['currency_value'])
			 *    );
			 * }
			 */

			// Totals
			$data['totals'] = array();

			$totals = $this->model_account_order->getTotals($order_id);

			foreach ($totals as $total) {
				$data['totals'][] = array(
					'title' => $total['title'],
					'text' => $this->currency->format($total['value'], $order_info['currency_code'], $order_info['currency_value']),
				);
			}


			$data['comment']   = nl2br($order_info['comment']);
			$data['histories'] = array();
			$results           = $this->model_account_order->getHistories($order_id);
			foreach ($results as $result) {
				$data['histories'][] = array(
					'date_added' => date($this->language->get('date_format_short'), strtotime($result['date_added'])),
					'status' => $result['status'],
					'comment' => $result['notify'] ? nl2br($result['comment']) : ''
				);
			}

			$this->document->addStyle('view/javascript/paywithiyzico/paywithiyzico_success.css');

			$language     = $this->config->get('payment_iyzico_language');
			$str_language = mb_strtolower($language);

			if (empty($str_language) or $str_language == 'null') {
				$locale = $this->language->get('code');
			} else {
				$locale = $str_language;
			}

			$data['locale']         = $locale;
			$data['credit_pending'] = $this->config->get('payment_iyzico_webhook_text');
			$data['continue']       = $this->url->link('account/order', '', true);
			$data['column_left']    = $this->load->controller('common/column_left');
			$data['column_right']   = $this->load->controller('common/column_right');
			$data['content_top']    = $this->load->controller('common/content_top');
			$data['content_bottom'] = $this->load->controller('common/content_bottom');
			$data['footer']         = $this->load->controller('common/footer');
			$data['header']         = $this->load->controller('common/header');

			/* Remove Order */
			unset($this->session->data['order_id']);

			return $this->response->setOutput($this->load->view('extension/paywithiyzico/payment/paywithiyzico_success', $data));
		}

		private function dataCheck($data)
		{
			if (!$data || $data == ' ') {
				$data = "NOT PROVIDED";
			}

			return $data;
		}

		private function shippingInfo()
		{
			if (isset($this->session->data['shipping_method']) && $this->session->data['shipping_method'] != 'flat.flat') {
				$shipping_info = $this->session->data['shipping_method'];
			} else {
				$shipping_info = false;
			}

			if ($shipping_info != false) {
				if (isset($shipping_info['tax_class_id'])) {
					$shipping_info['tax'] = $this->tax->getRates($shipping_info['cost'], $shipping_info['tax_class_id']);
				} else {
					$shipping_info['tax'] = false;
				}
			}

			return $shipping_info;
		}

		private function itemPriceSubTotal($products)
		{
			$price = 0;
			foreach ($products as $key => $product) {
				$price += (float)$product['total'];
			}

			$shippingInfo = $this->shippingInfo();
			if (is_object($shippingInfo) || is_array($shippingInfo)) {
				$price += (float)$shippingInfo['cost'];
			}

			return $price;
		}

		private function priceParser($price)
		{
			if (strpos($price, ".") === false) {
				return $price . ".0";
			}

			$subStrIndex   = 0;
			$priceReversed = strrev($price);
			for ($i = 0; $i < strlen($priceReversed); $i++) {
				if (strcmp($priceReversed[$i], "0") == 0) {
					$subStrIndex = $i + 1;
				} else if (strcmp($priceReversed[$i], ".") == 0) {
					$priceReversed = "0" . $priceReversed;
					break;
				} else {
					break;
				}
			}

			return strrev(substr($priceReversed, $subStrIndex));
		}


		private function getIpAdress()
		{
			$ip_address = $_SERVER['REMOTE_ADDR'];
			return $ip_address;
		}


		public function setWebhookText($thankyouTextValue)
		{
			$webhookText = $this->config->get('payment_iyzico_webhook_text');
			$query       = $this->db->query("UPDATE `" . DB_PREFIX . "setting` SET `value` = '" . $thankyouTextValue . "' , `serialized` = 0  WHERE `code` = 'payment_iyzico' AND `key` = 'payment_iyzico_webhook_text' AND `store_id` = '0'");
			return $query;
		}


	}
