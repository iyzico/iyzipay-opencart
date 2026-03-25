<?php

	require_once(DIR_SYSTEM . 'library/iyzipay-php/IyzipayBootstrap.php');
	IyzipayBootstrap::init(DIR_SYSTEM . 'library/iyzipay-php/src');

	class ControllerExtensionPaymentIyzico extends Controller
	{

		private $module_version = VERSION;
		private $module_product_name = '2.1.4';
		private $paymentConversationId;
		private $webhookToken;
		private $iyziEventType;
		private $iyziPaymentId;
		private $iyziSignature;

		public function index()
		{
			$this->load->language('extension/payment/iyzico');

			$data['form_class']       = $this->config->get('payment_iyzico_design');
			$data['form_type']        = $this->config->get('payment_iyzico_design');
			$data['config_theme']     = $this->config->get('config_theme');
			$data['onepage_desc']     = $this->language->get('iyzico_onepage_desc');
			$data['user_login_check'] = $this->customer->isLogged();

			if ($data['form_type'] == 'onepage') {
				$data['form_class'] = 'responsive';
			}

			return $this->load->view('extension/payment/iyzico_form', $data);
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
			$this->load->model('extension/payment/iyzico');

			$options = new \Iyzipay\Options();
			$options->setApiKey($this->config->get('payment_iyzico_api_key'));
			$options->setSecretKey($this->config->get('payment_iyzico_secret_key'));
			if ($this->config->get('payment_iyzico_api_channel') == 'sandbox') {
				$options->setBaseUrl('https://sandbox-api.iyzipay.com');
			} else {
				$options->setBaseUrl('https://api.iyzipay.com');
			}

			$module_attribute = false;
			$order_id         = (int)$this->session->data['order_id'];
			$customer_id      = (int)isset($this->session->data['customer_id']) ? $this->session->data['customer_id'] : 0;
			$user_id          = (int)isset($this->session->data['user_id']) ? $this->session->data['user_id'] : 0;
			$order_info       = $this->model_checkout_order->getOrder($order_id);
			$products         = $this->cart->getProducts();

			$payment_source                         = "OPENCART|" . $this->module_version . "|" . $this->module_product_name . "|" . $this->config->get('payment_iyzico_design');
			$user_create_date                       = $this->model_extension_payment_iyzico->getUserCreateDate($user_id);
			$this->session->data['conversation_id'] = $order_id;
			$order_info['payment_address']          = $order_info['payment_address_1'] . " " . $order_info['payment_address_2'];
			$order_info['shipping_address']         = $order_info['shipping_address_1'] . " " . $order_info['shipping_address_2'];

			$request = new \Iyzipay\Request\CreateCheckoutFormInitializeRequest();

			$language     = $this->config->get('payment_iyzico_language');
			$str_language = mb_strtolower($language);
			if (empty($str_language) or $str_language == 'null') {
				$request->setLocale($this->language->get('code'));
			} else {
				$request->setLocale($str_language);
			}

			$request->setConversationId($order_id);
			$request->setPrice($this->priceParser($this->itemPriceSubTotal($products) * $order_info['currency_value']));
			$request->setPaidPrice($this->priceParser($order_info['total'] * $order_info['currency_value']));
			$request->setCurrency($order_info['currency_code']);
			$request->setBasketId($order_id);
			$request->setPaymentGroup("PRODUCT");
			$request->setForceThreeDS("0");
			$request->setCallbackUrl($this->url->link('extension/payment/iyzico/getcallback', '', true));
			$request->setCardUserKey($this->model_extension_payment_iyzico->findUserCardKey($customer_id, $this->config->get('payment_iyzico_api_key')));
			$request->setPaymentSource($payment_source);

			if ($request->getPaidPrice() === 0) {
				return false;
			}

			$buyer = new \Iyzipay\Model\Buyer();
			$buyer->setId($order_info['customer_id']);
			$buyer->setName($this->dataCheck($order_info['firstname']));
			$buyer->setSurname($this->dataCheck($order_info['lastname']));
			$buyer->setIdentityNumber("11111111111");
			$buyer->setEmail($this->dataCheck($order_info['email']));
			$buyer->setGsmNumber($this->dataCheck($order_info['telephone']));
			$buyer->setRegistrationDate($user_create_date);
			$buyer->setLastLoginDate(date('Y-m-d H:i:s'));
			$buyer->setRegistrationAddress($this->dataCheck($order_info['payment_address']));
			$buyer->setCity($this->dataCheck($order_info['payment_zone']));
			$buyer->setCountry($this->dataCheck($order_info['payment_country']));
			$buyer->setZipCode($this->dataCheck($order_info['payment_postcode']));
			$buyer->setIp($this->dataCheck($this->getIpAdress()));
			$request->setBuyer($buyer);

			$shipping = new \Iyzipay\Model\Address();
			$shipping->setAddress($this->dataCheck($order_info['shipping_address']));
			$shipping->setZipCode($this->dataCheck($order_info['shipping_postcode']));
			$shipping->setContactName($this->dataCheck($order_info['shipping_firstname']));
			$shipping->setCity($this->dataCheck($order_info['shipping_zone']));
			$shipping->setCountry($this->dataCheck($order_info['shipping_country']));
			$request->setShippingAddress($shipping);

			$billing = new \Iyzipay\Model\Address();
			$billing->setAddress($this->dataCheck($order_info['payment_address']));
			$billing->setZipCode($this->dataCheck($order_info['payment_postcode']));
			$billing->setContactName($this->dataCheck($order_info['payment_firstname']));
			$billing->setCity($this->dataCheck($order_info['payment_zone']));
			$billing->setCountry($this->dataCheck($order_info['payment_country']));
			$request->setBillingAddress($billing);

			$basketItems = [];
			foreach ($products as $key => $product) {
				$price = $product['total'] * $order_info['currency_value'];

				if ($price) {
					$basketItem = new \Iyzipay\Model\BasketItem();
					$basketItem->setId($product['model']);
					$basketItem->setPrice($this->priceParser($price));
					$basketItem->setName($product['name']);
					$basketItem->setCategory1($this->model_extension_payment_iyzico->getCategoryName($product['product_id']));
					$basketItem->setItemType("PHYSICAL");

					$basketItems[] = $basketItem;
				}
			}

			$shipping = $this->shippingInfo();
			if (!empty($shipping) && $shipping['cost'] && $shipping['cost'] != '0.00') {
				$shippingItem = new \Iyzipay\Model\BasketItem();
				$shippingItem->setId('Kargo');
				$shippingItem->setPrice($this->priceParser($shipping['cost'] * $order_info['currency_value']));
				$shippingItem->setName($shipping['title']);
				$shippingItem->setCategory1("Kargo");
				$shippingItem->setItemType("VIRTUAL");

				$basketItems[] = $shippingItem;
			}
			$request->setBasketItems($basketItems);

			$response = \Iyzipay\Model\CheckoutFormInitialize::create($request, $options);

			$responseArray = [
				'status' => $response->getStatus(),
				'errorCode' => $response->getErrorCode(),
				'errorMessage' => $response->getErrorMessage(),
				'errorGroup' => $response->getErrorGroup(),
				'locale' => $response->getLocale(),
				'systemTime' => $response->getSystemTime(),
				'conversationId' => $response->getConversationId(),
				'token' => $response->getToken(),
				'checkoutFormContent' => $response->getCheckoutFormContent(),
				'tokenExpireTime' => $response->getTokenExpireTime(),
				'paymentPageUrl' => $response->getPaymentPageUrl()
			];

			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($responseArray));
		}

		public function getCallBack($webhook = null, $webhookPaymentConversationId = null, $webhookToken = null, $webhookIyziEventType = null)
		{
		    $response = null;
		    $order_id = null;
		    $iyzico_local_order = null;
		
		    try {
		        $this->load->language('extension/payment/iyzico');
		        $this->load->model('checkout/order');
		        $this->load->model('extension/payment/iyzico');
		
		        if ((!isset($this->request->post['token']) || empty($this->request->post['token'])) && $webhook != "webhook") {
		            throw new \Exception('INVALID_TOKEN');
		        }
		
		        $options = new \Iyzipay\Options();
		        $options->setApiKey($this->config->get('payment_iyzico_api_key'));
		        $options->setSecretKey($this->config->get('payment_iyzico_secret_key'));
		        $options->setBaseUrl(
		            $this->config->get('payment_iyzico_api_channel') == 'sandbox'
		                ? 'https://sandbox-api.iyzipay.com'
		                : 'https://api.iyzipay.com'
		        );
		
		        $request = new \Iyzipay\Request\RetrieveCheckoutFormRequest();
		
		        if ($webhook == 'webhook') {
		            $conversation_id = $webhookPaymentConversationId;
		            $token = $webhookToken;
		        } else {
		            $conversation_id = $this->session->data['conversation_id'] ?? null;
		            $order_id = $this->session->data['order_id'] ?? null;
		            $token = $this->request->post['token'] ?? null;
		
		            if (!$conversation_id || !$token) {
		                throw new \Exception('Missing session or token');
		            }
		        }
		
		        $request->setLocale('tr');
		        $request->setConversationId($conversation_id);
		        $request->setToken($this->db->escape($token));
		
		        $response = \Iyzipay\Model\CheckoutForm::retrieve($request, $options);
		
		        if (!$response) {
		            error_log("IYZICO NULL RESPONSE | token: $token | conversation: $conversation_id");
		
		            if ($webhook == 'webhook') {
		                return $this->webhookHttpResponse("null response", 404);
		            }
		
		            throw new \Exception('Iyzico response null');
		        }
		
		        // WEBHOOK FAIL FAST
		        if ($webhook == "webhook" && $response->getStatus() == 'failure') {
		            return $this->webhookHttpResponse(
		                $response->getErrorCode() . " - " . $response->getErrorMessage(),
		                404
		            );
		        }
		
		        $order_id = $response->getBasketId();
		        $order_info = $this->model_checkout_order->getOrder($order_id);
		
		        if (!$order_info) {
		            throw new \Exception('Order not found');
		        }
		
		        // ZATEN İŞLENMİŞ
		        if ($webhook == "webhook" && $order_info && $order_info['order_status_id'] == '5') {
		            return $this->webhookHttpResponse("Order already processed", 200);
		        }
		
		        // ORDER SAVE
		        $iyzico_local_order = new stdClass;
		        $iyzico_local_order->payment_id = $response->getPaymentId();
		        $iyzico_local_order->order_id = $order_id;
		        $iyzico_local_order->total_amount = $response->getPaidPrice();
		        $iyzico_local_order->status = $response->getPaymentStatus();
		
		        $this->model_extension_payment_iyzico->insertIyzicoOrder($iyzico_local_order);
		
		        // SUCCESS CHECK
		        if (
		            $response->getPaymentStatus() != 'SUCCESS' ||
		            $response->getStatus() != 'success'
		        ) {
		            throw new \Exception(
		                $response->getErrorMessage() ?: 'Payment failed'
		            );
		        }
		
		        // ORDER UPDATE
		        $message = "Payment ID: " . $response->getPaymentId();
		
		        $this->model_checkout_order->addOrderHistory(
		            $order_id,
		            $this->config->get('payment_iyzico_order_status'),
		            $message
		        );
		
		        if ($webhook == 'webhook') {
		            return $this->webhookHttpResponse("OK", 200);
		        }
		
		        return $this->response->redirect(
		            $this->url->link('extension/payment/iyzico/successpage', '', true)
		        );
		
		    } catch (Exception $e) {
		
		        error_log("IYZICO ERROR: " . $e->getMessage());
		
		        // SAFE CHECK
		        if ($response && $response->getPaymentStatus() == 'INIT_BANK_TRANSFER') {
		            $this->model_checkout_order->addOrderHistory(
		                $order_id,
		                $this->config->get('payment_iyzico_order_status'),
		                'Banka transfer bekleniyor'
		            );
		
		            return $this->response->redirect(
		                $this->url->link('extension/payment/iyzico/successpage', '', true)
		            );
		        }
		
		        if ($webhook == 'webhook') {
		            $errorCode = $response ? $response->getErrorCode() : 'no_response';
		            $errorMessage = $response ? $response->getErrorMessage() : $e->getMessage();
		
		            return $this->webhookHttpResponse(
		                "$errorCode - $errorMessage",
		                404
		            );
		        }
		
		        $this->session->data['iyzico_error_message'] =
		            $response ? $response->getErrorMessage() : $e->getMessage();
		
		        return $this->response->redirect(
		            $this->url->link('extension/payment/iyzico/errorpage', '', true)
		        );
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
			$data['error_icon']     = 'catalog/view/theme/default/image/payment/iyzico_error_icon.png';

			return $this->response->setOutput($this->load->view('extension/payment/iyzico_error', $data));
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
			$products         = $this->model_account_order->getOrderProducts($order_id);
			foreach ($products as $product) {
				$option_data = array();

				$options = $this->model_account_order->getOrderOptions($order_id, $product['order_product_id']);
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
						'value' => (utf8_strlen($value) > 20 ? utf8_substr($value, 0, 20) . '..' : $value)
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
			$vouchers         = $this->model_account_order->getOrderVouchers($order_id);
			foreach ($vouchers as $voucher) {
				$data['vouchers'][] = array(
					'description' => $voucher['description'],
					'amount' => $this->currency->format($voucher['amount'], $order_info['currency_code'], $order_info['currency_value'])
				);
			}

			$data['totals'] = array();
			$totals         = $this->model_account_order->getOrderTotals($order_id);
			foreach ($totals as $total) {
				$data['totals'][] = array(
					'title' => $total['title'],
					'text' => $this->currency->format($total['value'], $order_info['currency_code'], $order_info['currency_value']),
				);
			}

			$data['comment']   = nl2br($order_info['comment']);
			$data['histories'] = array();

			$results = $this->model_account_order->getOrderHistories($order_id);
			foreach ($results as $result) {
				$data['histories'][] = array(
					'date_added' => date($this->language->get('date_format_short'), strtotime($result['date_added'])),
					'status' => $result['status'],
					'comment' => $result['notify'] ? nl2br($result['comment']) : ''
				);
			}

			$this->document->addStyle('catalog/view/javascript/iyzico/iyzico_success.css');
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
			$data['success_icon']   = 'catalog/view/theme/default/image/payment/iyzico_success_icon.png';
			unset($this->session->data['order_id']);

			return $this->response->setOutput($this->load->view('extension/payment/iyzico_success', $data));
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
			if (isset($this->session->data['shipping_method'])) {
				$shipping_info = $this->session->data['shipping_method'];
			} else {
				$shipping_info = false;
			}

			if ($shipping_info) {
				if ($shipping_info['tax_class_id']) {
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
			if (isset($shippingInfo['cost'])) {
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

		public function injectOverlayScript($route, &$data = false, &$output = null)
		{
			$this->load->model('setting/setting');

			$token          = $this->config->get('payment_iyzico_overlay_token');
			$overlay_status = $this->config->get('payment_iyzico_overlay_status');
			$api_channel    = $this->config->get('payment_iyzico_api_channel');

			if ($overlay_status != 'hidden' && $overlay_status != '' || $api_channel == 'sandbox') {

				$hook = '</footer>';
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
                </style><script> window.iyz = { token: '" . $token . "', position: '" . $overlay_status . "', ideaSoft: false, pwi:true};</script>
        <script src='https://static.iyzipay.com/buyer-protection/buyer-protection.js' type='text/javascript'></script></footer>";

				$output = str_replace($hook, $js, $output);
			}
		}

		private function getIpAdress()
		{
			return $_SERVER['REMOTE_ADDR'];
		}

		public function setWebhookText($thankyouTextValue)
		{
			return $this->db->query("UPDATE `" . DB_PREFIX . "setting` SET `value` = '" . $thankyouTextValue . "' , `serialized` = 0  WHERE `code` = 'payment_iyzico_webhook' AND `key` = 'payment_iyzico_webhook_text' AND `store_id` = '0'");
		}

		public function webhook()
		{
			if (isset($this->request->get['key']) && $this->request->get['key'] == $this->config->get('webhook_iyzico_webhook_url_key')) {
				$post   = file_get_contents("php://input");
				$params = json_decode($post, true);

				if (isset(getallheaders()['x-iyz-signature-v3'])) {
					$this->iyziSignature = getallheaders()['x-iyz-signature-v3'];
				}

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
							$this->webhookHttpResponse("signature_not_valid - X-IYZ-SIGNATURE-V3 geçersiz", 404);
						}
					} else {
						$this->getCallBack('webhook', $params['paymentConversationId'], $params['token'], $params['iyziEventType']);
					}
				} else {
					$this->webhookHttpResponse("invalid_parameters - Gönderilen parametreler geçersiz", 404);
				}
			} else {
				$this->webhookHttpResponse("invalid_key - key geçersiz", 404);
			}
		}

		public function webhookHttpResponse($message, $status)
		{
			$httpMessage = array('message' => $message);
			header('Content-Type: application/json, Status: ' . $status, true, $status);
			echo json_encode($httpMessage);
			exit();
		}
	}
