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
                'expires'  => $expire,
                'path'     => $path,
                'domain'   => $domain,
                'samesite' => 'None',
                'secure'   => $secure,
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
                    $this->setcookieSameSite(
                        $cookieName,
                        $_COOKIE[$cookieName],
                        time() + 86400,
                        "/",
                        $_SERVER['SERVER_NAME'],
                        true,
                        true
                    );
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

        $order_id    = isset($this->session->data['order_id']) ? (int)$this->session->data['order_id'] : 0;
        $customer_id = isset($this->session->data['customer_id']) ? (int)$this->session->data['customer_id'] : 0;
        $user_id     = isset($this->session->data['user_id']) ? (int)$this->session->data['user_id'] : 0;

        $order_info = $this->model_checkout_order->getOrder($order_id);
        $products   = $this->cart->getProducts();

        if (!$order_info || !$order_id) {
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode([
                'status'       => 'failure',
                'errorCode'    => 'INVALID_ORDER',
                'errorMessage' => 'Geçerli sipariş bulunamadı.'
            ]));
            return;
        }

        $payment_source = "OPENCART|" . $this->module_version . "|" . $this->module_product_name . "|" . $this->config->get('payment_iyzico_design');
        $user_create_date = $this->model_extension_payment_iyzico->getUserCreateDate($user_id);

        $this->session->data['conversation_id'] = $order_id;
        $order_info['payment_address']  = trim($order_info['payment_address_1'] . " " . $order_info['payment_address_2']);
        $order_info['shipping_address'] = trim($order_info['shipping_address_1'] . " " . $order_info['shipping_address_2']);

        $request = new \Iyzipay\Request\CreateCheckoutFormInitializeRequest();

        $language = $this->config->get('payment_iyzico_language');
        $str_language = mb_strtolower((string)$language);

        if (empty($str_language) || $str_language == 'null') {
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

        if ((float)$request->getPaidPrice() === 0.0) {
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode([
                'status'       => 'failure',
                'errorCode'    => 'ZERO_AMOUNT',
                'errorMessage' => 'Ödeme tutarı 0 olamaz.'
            ]));
            return;
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
        foreach ($products as $product) {
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

        $shippingInfo = $this->shippingInfo();
        if (!empty($shippingInfo) && isset($shippingInfo['cost']) && $shippingInfo['cost'] && $shippingInfo['cost'] != '0.00') {
            $shippingItem = new \Iyzipay\Model\BasketItem();
            $shippingItem->setId('Kargo');
            $shippingItem->setPrice($this->priceParser($shippingInfo['cost'] * $order_info['currency_value']));
            $shippingItem->setName($shippingInfo['title']);
            $shippingItem->setCategory1("Kargo");
            $shippingItem->setItemType("VIRTUAL");

            $basketItems[] = $shippingItem;
        }

        $request->setBasketItems($basketItems);

        $response = \Iyzipay\Model\CheckoutFormInitialize::create($request, $options);

        if (!$response) {
            $responseArray = [
                'status'       => 'failure',
                'errorCode'    => 'NULL_RESPONSE',
                'errorMessage' => 'Iyzipay boş response döndü.'
            ];
        } else {
            $responseArray = [
                'status'              => $response->getStatus(),
                'errorCode'           => $response->getErrorCode(),
                'errorMessage'        => $response->getErrorMessage(),
                'errorGroup'          => $response->getErrorGroup(),
                'locale'              => $response->getLocale(),
                'systemTime'          => $response->getSystemTime(),
                'conversationId'      => $response->getConversationId(),
                'token'               => $response->getToken(),
                'checkoutFormContent' => $response->getCheckoutFormContent(),
                'tokenExpireTime'     => $response->getTokenExpireTime(),
                'paymentPageUrl'      => $response->getPaymentPageUrl()
            ];
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($responseArray));
    }

    public function getCallBack($webhook = null, $webhookPaymentConversationId = null, $webhookToken = null, $webhookIyziEventType = null)
    {
        $response = null;
        $order_id = null;
        $order_info = null;
        $iyzico_local_order = null;
        $customer_id = 0;

        try {
            $this->load->language('extension/payment/iyzico');
            $this->load->model('checkout/order');
            $this->load->model('extension/payment/iyzico');

            if ($webhook != "webhook" && (!isset($this->request->post['token']) || empty($this->request->post['token']))) {
                throw new \Exception('INVALID_TOKEN');
            }

            $options = new \Iyzipay\Options();
            $options->setApiKey($this->config->get('payment_iyzico_api_key'));
            $options->setSecretKey($this->config->get('payment_iyzico_secret_key'));

            if ($this->config->get('payment_iyzico_api_channel') == 'sandbox') {
                $options->setBaseUrl('https://sandbox-api.iyzipay.com');
            } else {
                $options->setBaseUrl('https://api.iyzipay.com');
            }

            $request = new \Iyzipay\Request\RetrieveCheckoutFormRequest();

            if ($webhook == 'webhook') {
                $conversation_id = $webhookPaymentConversationId;
                $token = $webhookToken;
            } else {
                $conversation_id = isset($this->session->data['conversation_id']) ? $this->session->data['conversation_id'] : null;
                $order_id        = isset($this->session->data['order_id']) ? (int)$this->session->data['order_id'] : null;
                $token           = isset($this->request->post['token']) ? $this->request->post['token'] : null;

                if (!$conversation_id || !$token) {
                    throw new \Exception('Missing session or token');
                }
            }

            $customer_id = isset($this->session->data['customer_id']) ? (int)$this->session->data['customer_id'] : 0;

            $language = $this->config->get('payment_iyzico_language');
            if (empty($language) || $language == 'null') {
                $request->setLocale($this->language->get('code'));
            } elseif ($language == 'TR' || $language == 'tr') {
                $request->setLocale('tr');
            } else {
                $request->setLocale('en');
            }

            $request->setConversationId($conversation_id);
            $request->setToken($this->db->escape($token));

            $response = \Iyzipay\Model\CheckoutForm::retrieve($request, $options);

            if (!$response) {
                error_log('IYZICO NULL RESPONSE | token: ' . $token . ' | conversationId: ' . $conversation_id);

                if ($webhook == 'webhook') {
                    return $this->webhookHttpResponse('Iyzico response null', 404);
                }

                throw new \Exception('Iyzico response null');
            }

            if ($webhook == "webhook" && $webhookIyziEventType != 'CREDIT_PAYMENT_AUTH' && $response->getStatus() == 'failure') {
                return $this->webhookHttpResponse(
                    "errorCode: " . $response->getErrorCode() . " - " . $response->getErrorMessage(),
                    404
                );
            }

            if ($response->getBasketId()) {
                $order_id = (int)$response->getBasketId();
            }

            if (!$order_id) {
                throw new \Exception('BasketId / Order ID bulunamadı.');
            }

            $order_info = $this->model_checkout_order->getOrder($order_id);

            if (!$order_info) {
                throw new \Exception('Order not found');
            }

            if ($webhook == "webhook") {
                if ($webhookIyziEventType == 'CREDIT_PAYMENT_AUTH' && $response->getPaymentStatus() == 'PENDING_CREDIT') {
                    $orderMessage = 'Alışveriş kredisi başvurusu sürecindedir.';
                    $this->model_checkout_order->addOrderHistory($response->getBasketId(), 1, $orderMessage);
                    return $this->webhookHttpResponse("Order Exist - Alışveriş kredisi başvurusu sürecindedir.", 200);
                }

                if ($webhookIyziEventType == 'CREDIT_PAYMENT_AUTH' && $response->getStatus() == 'success') {
                    $orderMessage = 'Alışveriş kredisi işlemi başarıyla tamamlandı.';
                    $this->model_checkout_order->addOrderHistory($response->getBasketId(), 2, $orderMessage);
                    return $this->webhookHttpResponse("Order Exist - Alışveriş kredisi işlemi başarıyla tamamlandı.", 200);
                }

                if ($webhookIyziEventType == 'CREDIT_PAYMENT_INIT' && $response->getStatus() == 'INIT_CREDIT') {
                    $orderMessage = 'Alışveriş kredisi işlemi başlatıldı.';
                    $this->model_checkout_order->addOrderHistory($response->getBasketId(), 1, $orderMessage);
                    return $this->webhookHttpResponse("Order Exist - Alışveriş kredisi işlemi başlatıldı.", 200);
                }

                if ($webhookIyziEventType == 'CREDIT_PAYMENT_AUTH' && $response->getStatus() == 'FAILURE') {
                    $orderMessage = 'Alışveriş kredisi işlemi başarısız sonuçlandı.';
                    $this->model_checkout_order->addOrderHistory($response->getBasketId(), 7, $orderMessage);
                    return $this->webhookHttpResponse("Order Exist - Alışveriş kredisi işlemi başarısız sonuçlandı.", 200);
                }
            }

            if ($webhook == "webhook") {
                if ($order_info && $order_info['order_status_id'] == '5') {
                    return $this->webhookHttpResponse("Order Exist - Sipariş zaten var.", 200);
                }
            }

            $iyzico_local_order = new stdClass();
            $iyzico_local_order->payment_id   = !empty($response->getPaymentId()) ? (int)$response->getPaymentId() : '';
            $iyzico_local_order->order_id     = $order_id;
            $iyzico_local_order->total_amount = !empty($response->getPaidPrice()) ? (float)$response->getPaidPrice() : '';
            $iyzico_local_order->status       = $response->getPaymentStatus();

            $this->model_extension_payment_iyzico->insertIyzicoOrder($iyzico_local_order);

            if ($response->getPaymentStatus() != 'SUCCESS' || $response->getStatus() != 'success' || $order_id != (int)$response->getBasketId()) {
                $errorMessage = $response->getErrorMessage() !== null ? $response->getErrorMessage() : $this->language->get('payment_failed');
                throw new \Exception($errorMessage);
            }

            if ($response->getCardUserKey() !== null && $customer_id) {
                $cardUserKey = $this->model_extension_payment_iyzico->findUserCardKey(
                    $customer_id,
                    $this->config->get('payment_iyzico_api_key')
                );

                if ($response->getCardUserKey() != $cardUserKey) {
                    $this->model_extension_payment_iyzico->insertCardUserKey(
                        $customer_id,
                        $response->getCardUserKey(),
                        $this->config->get('payment_iyzico_api_key')
                    );
                }
            }

            $payment_id = $this->db->escape($response->getPaymentId());
            $payment_field_desc = $this->language->get('payment_field_desc');
            $message = '';

            if (!empty($payment_id)) {
                $message = $payment_field_desc . $payment_id . "\n";
            }

            $installment = $response->getInstallment();

            if ($installment > 1) {
                $installement_field_desc = $this->language->get('installement_field_desc');

                $this->model_extension_payment_iyzico->orderUpdateByInstallement(
                    $iyzico_local_order->order_id,
                    $response->getPaidPrice()
                );

                $this->model_checkout_order->addOrderHistory(
                    $iyzico_local_order->order_id,
                    $this->config->get('payment_iyzico_order_status'),
                    $message
                );

                $messageInstallement = $response->getCardFamily() . ' - ' . $response->getInstallment() . $installement_field_desc;
                $this->model_checkout_order->addOrderHistory(
                    $iyzico_local_order->order_id,
                    $this->config->get('payment_iyzico_order_status'),
                    $messageInstallement
                );
            } else {
                $this->model_checkout_order->addOrderHistory(
                    $iyzico_local_order->order_id,
                    $this->config->get('payment_iyzico_order_status'),
                    $message
                );
            }

            if ($webhook == 'webhook') {
                return $this->webhookHttpResponse("Order Created by Webhook - Sipariş webhook tarafından oluşturuldu.", 200);
            }

            $this->setWebhookText(0);
            return $this->response->redirect($this->url->link('extension/payment/iyzico/successpage', '', true));

        } catch (\Exception $e) {
            error_log('IYZICO ERROR: ' . $e->getMessage());

            if ($response && $response->getPaymentStatus() == 'INIT_BANK_TRANSFER' && $response->getStatus() == 'success') {
                $orderMessage = 'iyzico Banka Havale/EFT ödemesi bekleniyor.';

                if ($iyzico_local_order && !empty($iyzico_local_order->order_id)) {
                    $this->model_checkout_order->addOrderHistory(
                        $iyzico_local_order->order_id,
                        $this->config->get('payment_iyzico_order_status'),
                        $orderMessage
                    );
                }

                $this->setWebhookText(0);
                return $this->response->redirect($this->url->link('extension/payment/iyzico/successpage', '', true));
            }

            if ($webhook != 'webhook' && $response && $response->getPaymentStatus() == 'PENDING_CREDIT' && $response->getStatus() == 'success') {
                $orderMessage = 'Alışveriş kredisi işlemi başlatıldı.';

                if ($iyzico_local_order && !empty($iyzico_local_order->order_id)) {
                    $this->model_checkout_order->addOrderHistory(
                        $iyzico_local_order->order_id,
                        1,
                        $orderMessage
                    );
                }

                $this->setWebhookText(1);
                return $this->response->redirect($this->url->link('extension/payment/iyzico/successpage', '', true));
            }

            $this->setWebhookText(0);

            if ($webhook == 'webhook') {
                $errorCode = $response ? $response->getErrorCode() : 'no_response';
                $errorMessage = $response && $response->getErrorMessage() ? $response->getErrorMessage() : $e->getMessage();

                return $this->webhookHttpResponse(
                    "errorCode: " . $errorCode . " - " . $errorMessage,
                    404
                );
            }

            $errorMessage = ($response && $response->getErrorMessage() !== null)
                ? $response->getErrorMessage()
                : $e->getMessage();

            $this->session->data['iyzico_error_message'] = $errorMessage;

            return $this->response->redirect($this->url->link('extension/payment/iyzico/errorpage', '', true));
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
        $data['error_message']  = isset($this->session->data['iyzico_error_message']) ? $this->session->data['iyzico_error_message'] : '';
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
        $products = $this->model_account_order->getOrderProducts($order_id);

        foreach ($products as $product) {
            $option_data = array();

            $options = $this->model_account_order->getOrderOptions($order_id, $product['order_product_id']);

            foreach ($options as $option) {
                if ($option['type'] != 'file') {
                    $value = $option['value'];
                } else {
                    $upload_info = $this->model_tool_upload->getUploadByCode($option['value']);
                    $value = $upload_info ? $upload_info['name'] : '';
                }

                $option_data[] = array(
                    'name'  => $option['name'],
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
                'name'     => $product['name'],
                'model'    => $product['model'],
                'option'   => $option_data,
                'quantity' => $product['quantity'],
                'price'    => $this->currency->format(
                    $product['price'] + ($this->config->get('config_tax') ? $product['tax'] : 0),
                    $order_info['currency_code'],
                    $order_info['currency_value']
                ),
                'total'    => $this->currency->format(
                    $product['total'] + ($this->config->get('config_tax') ? ($product['tax'] * $product['quantity']) : 0),
                    $order_info['currency_code'],
                    $order_info['currency_value']
                ),
                'reorder'  => $reorder,
                'return'   => $this->url->link('account/return/add', 'order_id=' . $order_info['order_id'] . '&product_id=' . $product['product_id'], true)
            );
        }

        $data['vouchers'] = array();
        $vouchers = $this->model_account_order->getOrderVouchers($order_id);

        foreach ($vouchers as $voucher) {
            $data['vouchers'][] = array(
                'description' => $voucher['description'],
                'amount'      => $this->currency->format($voucher['amount'], $order_info['currency_code'], $order_info['currency_value'])
            );
        }

        $data['totals'] = array();
        $totals = $this->model_account_order->getOrderTotals($order_id);

        foreach ($totals as $total) {
            $data['totals'][] = array(
                'title' => $total['title'],
                'text'  => $this->currency->format($total['value'], $order_info['currency_code'], $order_info['currency_value']),
            );
        }

        $data['comment'] = nl2br($order_info['comment']);
        $data['histories'] = array();

        $results = $this->model_account_order->getOrderHistories($order_id);

        foreach ($results as $result) {
            $data['histories'][] = array(
                'date_added' => date($this->language->get('date_format_short'), strtotime($result['date_added'])),
                'status'     => $result['status'],
                'comment'    => $result['notify'] ? nl2br($result['comment']) : ''
            );
        }

        $this->document->addStyle('catalog/view/javascript/iyzico/iyzico_success.css');

        $language = $this->config->get('payment_iyzico_language');
        $str_language = mb_strtolower((string)$language);

        if (empty($str_language) || $str_language == 'null') {
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

        foreach ($products as $product) {
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
        $price = (string)$price;

        if (strpos($price, ".") === false) {
            return $price . ".0";
        }

        $subStrIndex   = 0;
        $priceReversed = strrev($price);

        for ($i = 0; $i < strlen($priceReversed); $i++) {
            if (strcmp($priceReversed[$i], "0") == 0) {
                $subStrIndex = $i + 1;
            } elseif (strcmp($priceReversed[$i], ".") == 0) {
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
        $token_safe = json_encode($token, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
        $overlay_status_safe = json_encode($overlay_status, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);

        if (($overlay_status != 'hidden' && $overlay_status != '') || $api_channel == 'sandbox') {
            $hook = '</footer>';
            $js = "<style>
                    @media screen and (max-width: 380px) {
                        ._1xrVL7npYN5CKybp32heXk {
                            position: fixed;
                            bottom: 0!important;
                            top: unset;
                            left: 0;
                            width: 100%;
                        }
                    }
                </style><script> window.iyz = { token: " . $token_safe . ", position: " . $overlay_status_safe . ", ideaSoft: false, pwi:true};</script>
        <script src='https://static.iyzipay.com/buyer-protection/buyer-protection.js' type='text/javascript'></script></footer>";

            $output = str_replace($hook, $js, $output);
        }
    }

    private function getIpAdress()
    {
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
    }

    public function setWebhookText($thankyouTextValue)
    {
        return $this->db->query(
            "UPDATE `" . DB_PREFIX . "setting`
             SET `value` = '" . (int)$thankyouTextValue . "', `serialized` = 0
             WHERE `code` = 'payment_iyzico_webhook'
               AND `key` = 'payment_iyzico_webhook_text'
               AND `store_id` = '0'"
        );
    }

    public function webhook()
    {
        if (!isset($this->request->get['key']) || $this->request->get['key'] != $this->config->get('webhook_iyzico_webhook_url_key')) {
            $this->webhookHttpResponse("invalid_key - key geçersiz", 404);
        }

        $post = file_get_contents("php://input");
        $params = json_decode($post, true);

        $headers = function_exists('getallheaders') ? getallheaders() : array();
        if (isset($headers['x-iyz-signature-v3'])) {
            $this->iyziSignature = $headers['x-iyz-signature-v3'];
        } elseif (isset($headers['X-IYZ-SIGNATURE-V3'])) {
            $this->iyziSignature = $headers['X-IYZ-SIGNATURE-V3'];
        }

        if (!isset($params['iyziEventType']) || !isset($params['token']) || !isset($params['paymentConversationId'])) {
            $this->webhookHttpResponse("invalid_parameters - Gönderilen parametreler geçersiz", 404);
        }

        $this->paymentConversationId = $params['paymentConversationId'];
        $this->webhookToken          = $params['token'];
        $this->iyziEventType         = $params['iyziEventType'];
        $this->iyziPaymentId         = isset($params['iyziPaymentId']) ? $params['iyziPaymentId'] : '';
        $status                      = isset($params['status']) ? $params['status'] : '';

        if ($this->iyziSignature) {
            $secretKey = $this->config->get('payment_iyzico_secret_key');
            $key = $secretKey . $this->iyziEventType . $this->iyziPaymentId . $this->webhookToken . $this->paymentConversationId . $status;
            $createIyzicoSignature = bin2hex(hash_hmac('sha256', $key, $secretKey, true));

            if ($this->iyziSignature != $createIyzicoSignature) {
                $this->webhookHttpResponse("signature_not_valid - X-IYZ-SIGNATURE-V3 geçersiz", 404);
            }
        }

        $this->getCallBack(
            'webhook',
            $params['paymentConversationId'],
            $params['token'],
            $params['iyziEventType']
        );
    }

    public function webhookHttpResponse($message, $status)
    {
        $httpMessage = array('message' => $message);
        header('Content-Type: application/json', true, $status);
        echo json_encode($httpMessage);
        exit();
    }
}
