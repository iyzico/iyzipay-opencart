<?php

	namespace Opencart\Catalog\Controller\Extension\iyzico\Payment;

	use Opencart\System\Engine\Controller;
	use stdClass;

	require_once(DIR_EXTENSION . 'iyzico/system/library/iyzipay-php/IyzipayBootstrap.php');
	\IyzipayBootstrap::init(DIR_EXTENSION . 'iyzico/system/library/iyzipay-php/src');

	class iyzico extends Controller
	{

		private $moduleVersion = VERSION;
		private $moduleProductName = '2.6.0';
		private $paymentConversationId;
		private $webhookToken;
		private $iyziEventType;
		private $iyziSignature;
		private $iyziPaymentId;

		public function index()
		{
			$this->load->language('extension/iyzico/payment/iyzico');
			return $this->getCheckoutFormToken();
		}

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
					'httponly' => $httponly
				]);
			}
		}

		private function checkAndSetCookieSameSite()
		{
			$checkCookieNames = array('PHPSESSID', 'OCSESSID', 'default', 'PrestaShop-', 'wp_woocommerce_session_');
			foreach ($_COOKIE as $cookieName => $value) {
				foreach ($checkCookieNames as $checkCookieName) {
					if (stripos($cookieName, $checkCookieName) === 0) {
						$this->setcookieSameSite($cookieName, $_COOKIE[$cookieName], time() + 86400, "/", $_SERVER['SERVER_NAME'], true, true);
					}
				}
			}
		}

		public function getCheckoutFormToken()
		{
			$this->checkAndSetCookieSameSite();

			$this->load->model('checkout/order');
			$this->load->model('setting/setting');
			$this->load->model('extension/iyzico/payment/iyzico');

			$order_id    = (int)$this->session->data['order_id'];
			$customer_id = (int)isset($this->session->data['customer_id']) ? $this->session->data['customer_id'] : 0;
			$user_id     = (int)isset($this->session->data['user_id']) ? $this->session->data['user_id'] : 0;
			$order_info  = $this->model_checkout_order->getOrder($order_id);
			$products    = $this->cart->getProducts();

			$api_key        = $this->config->get('payment_iyzico_api_key');
			$secret_key     = $this->config->get('payment_iyzico_secret_key');
			$payment_source = "OPENCART|" . $this->moduleVersion . "|" . $this->moduleProductName;

			$user_create_date = $this->model_extension_iyzico_payment_iyzico->getUserCreateDate($user_id);

			$this->session->data['conversation_id'] = $order_id;

			$order_info['payment_address']  = $order_info['payment_address_1'] . " " . $order_info['payment_address_2'];
			$order_info['shipping_address'] = $order_info['shipping_address_1'] . " " . $order_info['shipping_address_2'];

			$iyzico = new \Iyzipay\Request\CreateCheckoutFormInitializeRequest();

			$iyzico->setLocale($this->language->get('code'));
			$iyzico->setConversationId($order_id);
			$iyzico->setPrice($this->priceParser($this->itemPriceSubTotal($products) * $order_info['currency_value']));
			$iyzico->setPaidPrice($this->priceParser($order_info['total'] * $order_info['currency_value']));
			$iyzico->setCurrency($order_info['currency_code']);
			$iyzico->setBasketId($order_id);
			$iyzico->setPaymentGroup("PRODUCT");
			$iyzico->setForceThreeDS("0");
			$iyzico->setCallbackUrl($this->url->link('extension/iyzico/payment/iyzico.getCallBack', '', true));
			$iyzico->setCardUserKey($this->model_extension_iyzico_payment_iyzico->findUserCardKey($customer_id, $api_key));
			$iyzico->setPaymentSource($payment_source);

			if ($iyzico->getPaidPrice() == 0) {
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
			$iyzico->setBuyer($buyer);

			$shippingAddress = new \Iyzipay\Model\Address();
			$shippingAddress->setAddress($this->dataCheck($order_info['shipping_address']));
			$shippingAddress->setZipCode($this->dataCheck($order_info['shipping_postcode']));
			$shippingAddress->setContactName($this->dataCheck($order_info['shipping_firstname']));
			$shippingAddress->setCity($this->dataCheck($order_info['shipping_zone']));
			$shippingAddress->setCountry($this->dataCheck($order_info['shipping_country']));
			$iyzico->setShippingAddress($shippingAddress);


			$billingAddress = new \Iyzipay\Model\Address();
			$billingAddress->setAddress($this->dataCheck($order_info['payment_address']));
			$billingAddress->setZipCode($this->dataCheck($order_info['payment_postcode']));
			$billingAddress->setContactName($this->dataCheck($order_info['payment_firstname']));
			$billingAddress->setCity($this->dataCheck($order_info['payment_zone']));
			$billingAddress->setCountry($this->dataCheck($order_info['payment_country']));
			$iyzico->setBillingAddress($billingAddress);


			$basketItems = [];
			foreach ($products as $product) {
				$price = $product['total'] * $order_info['currency_value'];

				if ($price) {
					$item = new \Iyzipay\Model\BasketItem();
					$item->setId($product['product_id']);
					$item->setPrice($this->priceParser($price));
					$item->setName($product['name']);
					$item->setCategory1($this->model_extension_iyzico_payment_iyzico->getCategoryName($product['product_id']));
					$item->setItemType("PHYSICAL");
					$basketItems[] = $item;
				}
			}


			$shipping = $this->shippingInfo();
			if (!empty($shipping) && $shipping['cost'] && $shipping['cost'] != '0.00') {
				$item = new \Iyzipay\Model\BasketItem();

				$item->setId("CARGO");
				$item->setPrice($this->priceParser($shipping['cost'] * $order_info['currency_value']));
				$item->setName($shipping['name']);
				$item->setCategory1("CARGO");
				$item->setItemType("VIRTUAL");

				$basketItems[] = $item;
			}
			$iyzico->setBasketItems($basketItems);

			#  iyzico Options
			$options = new \Iyzipay\Options();
			$options->setApiKey($api_key);
			$options->setSecretKey($secret_key);

			if ($this->config->get('payment_iyzico_api_channel') == 'sandbox') {
				$options->setBaseUrl("https://sandbox-api.iyzipay.com");
			} else {
				$options->setBaseUrl("https://api.iyzipay.com");
			}

			$response = \Iyzipay\Model\CheckoutFormInitialize::create($iyzico, $options);

			$data['checkoutFormType']    = $this->config->get('payment_iyzico_design');
			$data['checkoutFormContent'] = $response->getCheckoutFormContent();

			return $this->load->view('extension/iyzico/payment/iyzico_form', $data);
		}


		public function getCallBack($webhook = null, $webhookPaymentConversationId = null, $webhookToken = null, $webhookIyziEventType = null)
		{
			try {
				$this->load->language('extension/iyzico/payment/iyzico');

				if ((!isset($this->request->post['token']) || !isset($this->session->data['order_id']) || empty($this->request->post['token'])) && $webhook != "webhook") {
					$errorMessage = 'INVALID_TOKEN';
					throw new \Exception($errorMessage);
				}

				$this->load->model('checkout/order');
				$this->load->model('extension/iyzico/payment/iyzico');

				$api_key    = $this->config->get('payment_iyzico_api_key');
				$secret_key = $this->config->get('payment_iyzico_secret_key');
				$envoriment = $this->config->get('payment_iyzico_api_channel');

				if ($webhook == 'webhook') {
					$conversation_id = $webhookPaymentConversationId;
					$token           = $webhookToken;
				} else {
					$conversation_id = (int)$this->session->data['conversation_id'];
					$order_id        = (int)$this->session->data['order_id'];
					$token           = $this->request->post['token'];
				}

				$customer_id = isset($this->session->data['customer_id']) ? (int)$this->session->data['customer_id'] : 0;

				$detail_object = new \Iyzipay\Request\RetrieveCheckoutFormRequest();
				$language      = $this->config->get('payment_iyzico_language');
				if (empty($language) or $language == 'null') {
					$detail_object->setLocale($this->language->get('code'));
				} elseif ($language == 'TR' or $language == 'tr') {
					$detail_object->setLocale('tr');
				} else {
					$detail_object->setLocale('en');
				}

				$detail_object->setConversationId($conversation_id);
				$detail_object->setToken($this->db->escape($token));

				# iyzico Options
				$options = new \Iyzipay\Options();
				$options->setApiKey($api_key);
				$options->setSecretKey($secret_key);
				if ($envoriment == 'sandbox') {
					$options->setBaseUrl("https://sandbox-api.iyzipay.com");
				} else {
					$options->setBaseUrl("https://api.iyzipay.com");
				}

				$request_response = \Iyzipay\Model\CheckoutForm::retrieve($detail_object, $options);
				if ($webhook == "webhook" && $webhookIyziEventType != 'CREDIT_PAYMENT_AUTH' && $request_response->getStatus() == 'failure') {
					return $this->webhookHttpResponse("errorCode: " . $request_response->getErrorCode() . " - " . $request_response->getErrorMessage(), 404);
				}


				if ($webhook == "webhook") {
					$order_id = $request_response->getBasketId();
					$this->model_checkout_order->getOrder($order_id);

					if ($webhookIyziEventType == 'CREDIT_PAYMENT_AUTH' && $request_response->getPaymentStatus() == 'PENDING_CREDIT') {
						$orderMessage = 'Alışveriş kredisi başvurusu sürecindedir.';
						$this->model_checkout_order->addHistory($request_response->getBasketId(), 1, $orderMessage);
						return $this->webhookHttpResponse("Order Exist - Alışveriş kredisi başvurusu sürecindedir.", 200);

					}
					if ($webhookIyziEventType == 'CREDIT_PAYMENT_AUTH' && $request_response->getStatus() == 'success') {
						$orderMessage = 'Alışveriş kredisi işlemi başarıyla tamamlandı.';
						$this->model_checkout_order->addHistory($request_response->getBasketId(), 2, $orderMessage);
						return $this->webhookHttpResponse("Order Exist - Alışveriş kredisi işlemi başarıyla tamamlandı.", 200);
					}
					if ($webhookIyziEventType == 'CREDIT_PAYMENT_INIT' && $request_response->getStatus() == 'INIT_CREDIT') {
						$orderMessage = 'Alışveriş kredisi işlemi başlatıldı.';
						$this->model_checkout_order->addHistory($request_response->getBasketId(), 1, $orderMessage);
						return $this->webhookHttpResponse("Order Exist - Alışveriş kredisi işlemi başlatıldı.", 200);
					}

					if ($webhookIyziEventType == 'CREDIT_PAYMENT_AUTH' && $request_response->getStatus() == 'FAILURE') {
						$orderMessage = 'Alışveriş kredisi işlemi başarısız sonuçlandı.';
						$this->model_checkout_order->addHistory($request_response->getBasketId(), 7, $orderMessage);
						return $this->webhookHttpResponse("Order Exist - Alışveriş kredisi işlemi başarısız sonuçlandı.", 200);
					}

					if ($request_response->getPaymentStatus() == 'BANK_TRANSFER_AUTH' && $request_response->getStatus() == 'success') {
						$orderMessage = 'iyzico Banka Havale/EFT ödemesi tamamlandı.';
						$this->setWebhookText(0);
						$this->model_checkout_order->addHistory($request_response->getBasketId(), 5, $orderMessage);
						return $this->response->redirect($this->url->link('extension/iyzico/payment/iyzico.successpage'));
					}

				}


				if ($webhook == "webhook") {
					$order_id   = $request_response->getBasketId();
					$order_info = $this->model_checkout_order->getOrder($order_id);

					if (isset($order_info) & $order_info['order_status_id'] == '5') {
						return $this->webhookHttpResponse("Order Exist - Sipariş zaten var.", 200);
					}
				}

				$iyzico_local_order               = new stdClass;
				$iyzico_local_order->payment_id   = !empty($request_response->getPaymentId()) ? (int)$request_response->getPaymentId() : '';
				$iyzico_local_order->order_id     = $order_id;
				$iyzico_local_order->total_amount = !empty($request_response->getPaidPrice()) ? (float)$request_response->getPaidPrice() : '';
				$iyzico_local_order->status       = $request_response->getPaymentStatus();

				$this->model_extension_iyzico_payment_iyzico->insertIyzicoOrder($iyzico_local_order);

				$this->setWebhookText(0);

				if ($request_response->getPaymentStatus() == 'INIT_BANK_TRANSFER' && $request_response->getStatus() == 'success') {
					$orderMessage = 'iyzico Banka Havale/EFT ödemesi bekleniyor.';
					$this->setWebhookText(0);
					$this->model_checkout_order->addHistory($iyzico_local_order->order_id, 1, $orderMessage);
					return $this->response->redirect($this->url->link('extension/iyzico/payment/iyzico.successpage'));
				}

				if ($webhook != 'webhook' && $request_response->getPaymentStatus() == 'PENDING_CREDIT' && $request_response->getStatus() == 'success') {
					$orderMessage = 'Alışveriş kredisi işlemi başlatıldı.';
					$this->setWebhookText(1);
					$this->model_checkout_order->addHistory($iyzico_local_order->order_id, 1, $orderMessage);
					return $this->response->redirect($this->url->link('extension/iyzico/payment/iyzico.successpage'));
				}
				$this->setWebhookText(0);

				if ($request_response->getPaymentStatus() != 'SUCCESS' || $request_response->getStatus() != 'success' || $order_id != $request_response->getBasketId()) {
					echo '<div class="alert alert-danger alert-dismissible" style="opacity: 0.994559;"><i class="fa-solid fa-circle-exclamation">Ödemeniz alınamadı.Anasayfaya yönlendirliyorsunuz.</div>';
					return $this->response->redirect($this->url->link('checkout/checkout'));
				}

				/* Save Card */
				if ($request_response->getCardUserKey() !== null) {
					if ($customer_id) {
						$cardUserKey = $this->model_extension_iyzico_payment_iyzico->findUserCardKey($customer_id, $api_key);
						if ($request_response->getCardUserKey() != $cardUserKey) {
							$this->model_extension_iyzico_payment_iyzico->insertCardUserKey($customer_id, $request_response->getCardUserKey(), $api_key);
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
					$this->model_extension_iyzico_payment_iyzico->orderUpdateByInstallement($iyzico_local_order->order_id, $request_response->getPaidPrice());
					$messageInstallement = $request_response->getCardFamily() . ' - ' . $request_response->getInstallment() . $installement_field_desc;
					$this->model_checkout_order->addHistory($iyzico_local_order->order_id, $this->config->get('payment_iyzico_order_status'), $messageInstallement);
				} else {
					$this->model_checkout_order->addHistory($iyzico_local_order->order_id, $this->config->get('payment_iyzico_order_status'), $message);
				}

				if ($webhook == 'webhook') {
					return $this->webhookHttpResponse("Order Created by Webhook - Sipariş webhook tarafından oluşturuldu.", 200);
				}

				return $this->response->redirect($this->url->link('extension/iyzico/payment/iyzico.successpage'));

			} catch (\Exception $e) {
				if ($webhook == 'webhook') {
					return $this->webhookHttpResponse("errorCode: " . $request_response->getErrorCode() . " - " . $request_response->getErrorMessage(), 404);
				}

				$errorMessage                                = $request_response->getErrorMessage() !== null ? $request_response->getErrorMessage() : $e->getMessage();
				$this->session->data['iyzico_error_message'] = $errorMessage;

				return $this->response->redirect($this->url->link('extension/iyzico/payment/iyzico.errorpage'));
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
			$data['error_message']  = $this->session->data['iyzico_error_message'];
			$data['error_icon']     = 'catalog/view/theme/default/image/iyzico/payment/iyzico_error_icon.png';

			return $this->response->setOutput($this->load->view('extension/iyzico/payment/iyzico/iyzico_error', $data));
		}

		public function successPage()
		{
			$this->load->language('account/order');

			if (!isset($this->session->data['order_id'])) {
				return $this->response->redirect($this->url->link('common/home'));
			}

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
						'value' => (strlen($value) > 20 ? mb_substr($value, 0, 20) . '..' : $value)
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

			$data['totals'] = array();
			$totals         = $this->model_account_order->getTotals($order_id);
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

			$data['vouchers'] = array();
			/* Voucher functionality is not available in OpenCart 4.x
			$vouchers = $this->model_account_order->getVouchers($order_id);
			foreach ($vouchers as $voucher) {
				$data['vouchers'][] = array(
					'description' => $voucher['description'],
					'amount' => $this->currency->format($voucher['amount'], $order_info['currency_code'], $order_info['currency_value'])
				);
			}
			*/

			$this->document->addStyle('view/javascript/iyzico/iyzico_success.css');
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
			$data['success_icon']   = 'catalog/view/theme/default/image/iyzico/payment/iyzico_success_icon.png';
			unset($this->session->data['order_id']);

			return $this->response->setOutput($this->load->view('extension/iyzico/payment/iyzico_success', $data));
		}

		private function dataCheck($data)
		{
			if (!$data || $data == ' ') {
				$data = "NOT PROVIDED";
			}

			return $data;
		}

		private function shippingInfo(): bool|array
		{
			if (isset($this->session->data['shipping_method']) && $this->session->data['shipping_method'] != 'flat.flat')
				$shipping_info = $this->session->data['shipping_method'];
			else
				$shipping_info = false;

			if ($shipping_info != false)
				if (isset($shipping_info['tax_class_id']))
					$shipping_info['tax'] = $this->tax->getRates($shipping_info['cost'], $shipping_info['tax_class_id']);
				else
					$shipping_info['tax'] = false;

			return $shipping_info;
		}

		private function itemPriceSubTotal($products)
		{
			$price = 0;
			foreach ($products as $key => $product) {
				$price += (float)$product['total'];
			}

			$shippingInfo = $this->shippingInfo();
			if (is_object($shippingInfo) || is_array($shippingInfo))
				$price += (float)$shippingInfo['cost'];

			return $price;
		}

		private function priceParser($price)
		{
			if (strpos($price, ".") === false)
				return $price . ".0";

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

		private function getIpAdress(): string
		{
			return $_SERVER['REMOTE_ADDR'];
		}


		public function setWebhookText($thankyouTextValue)
		{
			return $this->db->query("UPDATE `" . DB_PREFIX . "setting` SET `value` = '" . $thankyouTextValue . "' , `serialized` = 0  WHERE `code` = 'payment_iyzico' AND `key` = 'payment_iyzico_webhook_text' AND `store_id` = '0'");
		}


		public function webhook()
		{
			if (isset($this->request->get['key']) && $this->request->get['key'] == $this->config->get('webhook_iyzico_webhook_url_key')) {
				$post   = file_get_contents("php://input");
				$params = json_decode($post, true);

				if (isset(getallheaders()['x-iyz-signature-v3']))
					$this->iyziSignature = getallheaders()['x-iyz-signature-v3'];

				if (isset($params['iyziEventType']) && isset($params['token']) && isset($params['paymentConversationId'])) {
					$this->paymentConversationId = $params['paymentConversationId'];
					$this->webhookToken          = $params['token'];
					$this->iyziEventType         = $params['iyziEventType'];
					$this->iyziPaymentId         = $params['iyziPaymentId'];
					$status                      = $params['status'];

					if ($this->iyziSignature) {
						$secretKey             = $this->config->get('payment_iyzico_secret_key');
						$key                   = $secretKey . $this->iyziEventType . $this->iyziPaymentId . $this->webhookToken . $this->paymentConversationId . $status;
						$createIyzicoSignature = bin2hex(hash_hmac('sha256', $key, $secretKey, true));

						if ($this->iyziSignature == $createIyzicoSignature) {
							$this->getCallBack('webhook', $params['paymentConversationId'], $params['token'], $params['iyziEventType']);
						} else {
							$this->webhookHttpResponse("INVALID_SIGNATURE", 404);
						}
					} else {
						$this->getCallBack('webhook', $params['paymentConversationId'], $params['token'], $params['iyziEventType']);
					}
				} else {
					$this->webhookHttpResponse("INVALID_PARAMS", 404);
				}
			} else {
				$this->webhookHttpResponse("INVALID_URL", 404);
			}
		}

		public function webhookHttpResponse($message, $status)
		{
			$httpMessage = array('message' => $message);
			header('Content-Type: application/json, Status: ' . $status, true, $status);
			echo json_encode($httpMessage);
			exit();
		}

		public function injectOverlayScript($route, &$data = false, &$output = null)
		{
			$this->load->model('setting/setting');

			$token         = $this->config->get('payment_iyzico_overlay_token');
			$overlayStatus = $this->config->get('payment_iyzico_overlay_status');
			$apiChannel    = $this->config->get('payment_iyzico_api_channel');

			if ($overlayStatus != 'hidden' && $overlayStatus != '' || $apiChannel == 'sandbox') {

				$hook = "</footer>";
				$js   = "<style>
                    @media screen and (max-width: 380px) {
                        ._1xrVL7npYN5CKybp32heXk {
                            position: fixed;
                            bottom: 0!important;
                            top: unset;
                            left: 0;
                            width: 100%;
                        }
                    }
                </style>
                    <script> window.iyz = { token: '" . $token . "', position: '" . $overlayStatus . "', pwi:true};</script>
                    <script src='https://static.iyzipay.com/buyer-protection/buyer-protection.js' type='text/javascript'></script>
                </footer>";

				$output = str_replace($hook, $js, $output);
			}
		}
	}
