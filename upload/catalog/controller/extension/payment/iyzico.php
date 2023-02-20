<?php
class ControllerExtensionPaymentIyzico extends Controller {

    private $module_version      = VERSION;
    private $module_product_name = 'eleven-2.5';

    private $paymentConversationId;
    private $webhookToken;
    private $iyziEventType;
    private $iyziSignature;




    public function index() {

        $this->load->language('extension/payment/iyzico');
        $data['form_class']         = $this->config->get('payment_iyzico_design');
        $data['form_type']          = $this->config->get('payment_iyzico_design');
        $data['config_theme']       = $this->config->get('config_theme');
        $data['onepage_desc']       = $this->language->get('iyzico_onepage_desc');

        if($data['form_type'] == 'onepage')
            $data['form_class'] = 'responsive';


        $data['user_login_check']   = $this->customer->isLogged();

        return $this->load->view('extension/payment/iyzico_form',$data);
    }

    private function setcookieSameSite($name, $value, $expire, $path, $domain, $secure, $httponly) {

        if (PHP_VERSION_ID < 70300) {

            setcookie($name, $value, $expire, "$path; samesite=None", $domain, $secure, $httponly);
        }
        else {
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

    private function checkAndSetCookieSameSite(){

        $checkCookieNames = array('PHPSESSID','OCSESSID','default','PrestaShop-','wp_woocommerce_session_');

        foreach ($_COOKIE as $cookieName => $value) {
            foreach ($checkCookieNames as $checkCookieName){
                if (stripos($cookieName,$checkCookieName) === 0) {
                    $this->setcookieSameSite($cookieName,$_COOKIE[$cookieName], time() + 86400, "/", $_SERVER['SERVER_NAME'],true, true);
                }
            }
        }
    }

    public function getCheckoutFormToken() {

        $this->checkAndSetCookieSameSite();

        $this->load->model('checkout/order');
        $this->load->model('setting/setting');
        $this->load->model('extension/payment/iyzico');

        $module_attribute                      = false;
        $order_id                              = (int) $this->session->data['order_id'];
        $customer_id                           = (int) isset($this->session->data['customer_id']) ? $this->session->data['customer_id'] : 0;
        $user_id                               = (int) isset($this->session->data['user_id']) ? $this->session->data['user_id'] : 0;
        $order_info                            = $this->model_checkout_order->getOrder($order_id);
        $products                              = $this->cart->getProducts();

        $api_key                               = $this->config->get('payment_iyzico_api_key');
        $secret_key                            = $this->config->get('payment_iyzico_secret_key');
        $payment_source                        = "OPENCART-".$this->module_version."|".$this->module_product_name."|".$this->config->get('payment_iyzico_design');

        $user_create_date                      = $this->model_extension_payment_iyzico->getUserCreateDate($user_id);

        $this->session->data['conversation_id'] = $order_id;


        $order_info['payment_address']         = $order_info['payment_address_1']." ".$order_info['payment_address_2'];
        $order_info['shipping_address']        = $order_info['shipping_address_1']." ".$order_info['shipping_address_2'];


        /* Order Detail */
        $iyzico = new stdClass;

        $language = $this->config->get('payment_iyzico_language');
        $str_language = mb_strtolower($language);

        if(empty($str_language) or $str_language == 'null')
        {
            $iyzico->locale              = $this->language->get('code');
        }else {
            $iyzico->locale              = $str_language;
        }
        $iyzico->conversationId                     = $order_id;
        $iyzico->price                        = $this->priceParser($this->itemPriceSubTotal($products) * $order_info['currency_value']);
        $iyzico->paidPrice                    = $this->priceParser($order_info['total'] * $order_info['currency_value']);
        $iyzico->currency                     = $order_info['currency_code'];
        $iyzico->basketId                     = $order_id;
        $iyzico->paymentGroup                 = "PRODUCT";
        $iyzico->forceThreeDS                 = "0";
        $iyzico->callbackUrl                  = $this->url->link('extension/payment/iyzico/getcallback', '', true);
        $iyzico->cardUserKey                  = $this->model_extension_payment_iyzico->findUserCardKey($customer_id,$api_key);
        $iyzico->paymentSource                = $payment_source;

        if ($iyzico->paidPrice === 0) {
            return false;
        }

        $iyzico->buyer = new stdClass;
        $iyzico->buyer->id                          = $order_info['customer_id'];
        $iyzico->buyer->name                        = $this->dataCheck($order_info['firstname']);
        $iyzico->buyer->surname                     = $this->dataCheck($order_info['lastname']);
        $iyzico->buyer->identityNumber              = '11111111111';
        $iyzico->buyer->email                       = $this->dataCheck($order_info['email']);
        $iyzico->buyer->gsmNumber                   = $this->dataCheck($order_info['telephone']);
        $iyzico->buyer->registrationDate            = $user_create_date;
        $iyzico->buyer->lastLoginDate               = date('Y-m-d H:i:s');
        $iyzico->buyer->registrationAddress         = $this->dataCheck($order_info['payment_address']);
        $iyzico->buyer->city                        = $this->dataCheck($order_info['payment_zone']);
        $iyzico->buyer->country                     = $this->dataCheck($order_info['payment_country']);
        $iyzico->buyer->zipCode                     = $this->dataCheck($order_info['payment_postcode']);
        $iyzico->buyer->ip                          = $this->dataCheck($this->getIpAdress());

        $iyzico->shippingAddress = new stdClass;
        $iyzico->shippingAddress->address          = $this->dataCheck($order_info['shipping_address']);
        $iyzico->shippingAddress->zipCode          = $this->dataCheck($order_info['shipping_postcode']);
        $iyzico->shippingAddress->contactName      = $this->dataCheck($order_info['shipping_firstname']);
        $iyzico->shippingAddress->city             = $this->dataCheck($order_info['shipping_zone']);
        $iyzico->shippingAddress->country          = $this->dataCheck($order_info['shipping_country']);


        $iyzico->billingAddress = new stdClass;
        $iyzico->billingAddress->address          = $this->dataCheck($order_info['payment_address']);
        $iyzico->billingAddress->zipCode          = $this->dataCheck($order_info['payment_postcode']);
        $iyzico->billingAddress->contactName      = $this->dataCheck($order_info['payment_firstname']);
        $iyzico->billingAddress->city             = $this->dataCheck($order_info['payment_zone']);
        $iyzico->billingAddress->country          = $this->dataCheck($order_info['payment_country']);

        foreach ($products as $key => $product) {
            $price = $product['total'] * $order_info['currency_value'];

            if($price) {
                $iyzico->basketItems[$key] = new stdClass();

                $iyzico->basketItems[$key]->id                = $product['model'];
                $iyzico->basketItems[$key]->price             = $this->priceParser($price);
                $iyzico->basketItems[$key]->name              = $product['name'];
                $iyzico->basketItems[$key]->category1         = $this->model_extension_payment_iyzico->getCategoryName($product['product_id']);
                $iyzico->basketItems[$key]->itemType          = "PHYSICAL";
            }
        }

        $shipping = $this->shippingInfo();

        if(!empty($shipping) && $shipping['cost'] && $shipping['cost'] != '0.00') {

            $shippigKey = count($iyzico->basketItems);

            $iyzico->basketItems[$shippigKey] = new stdClass();

            $iyzico->basketItems[$shippigKey]->id            = 'Kargo';
            $iyzico->basketItems[$shippigKey]->price         = $this->priceParser($shipping['cost'] * $order_info['currency_value']);
            $iyzico->basketItems[$shippigKey]->name          = $shipping['title'];
            $iyzico->basketItems[$shippigKey]->category1     = "Kargo";
            $iyzico->basketItems[$shippigKey]->itemType      = "VIRTUAL";
        }


        $rand_value             = rand(100000,99999999);
        $order_object           = $this->model_extension_payment_iyzico->createFormInitializObjectSort($iyzico);
        $pki_generate           = $this->model_extension_payment_iyzico->pkiStringGenerate($order_object);
        $authorization_data     = $this->model_extension_payment_iyzico->authorizationGenerate($pki_generate,$api_key,$secret_key,$rand_value);

        $iyzico_json = json_encode($iyzico,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);

        $form_response = $this->model_extension_payment_iyzico->createFormInitializeRequest($iyzico_json,$authorization_data);

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($form_response));
    }

    public function getCallBack($webhook = null, $webhookPaymentConversationId = null ,$webhookToken = null ,$webhookIyziEventType = null)  {

        try {

            $this->load->language('extension/payment/iyzico');

            if((!isset($this->request->post['token']) || empty($this->request->post['token'])) && $webhook != "webhook") {

                $errorMessage = 'invalid token';
                throw new \Exception($errorMessage);

            }


            $this->load->model('checkout/order');
            $this->load->model('extension/payment/iyzico');

            $api_key                               = $this->config->get('payment_iyzico_api_key');
            $secret_key                            = $this->config->get('payment_iyzico_secret_key');

            if ($webhook == 'webhook'){
                $conversation_id                       = $webhookPaymentConversationId;
                $token                                 = $webhookToken;
            }
            else{
                $conversation_id                       = (int) $this->session->data['conversation_id'];
                $order_id                              = (int) $this->session->data['order_id'];
                $token                                 = $this->request->post['token'];
            }

            $customer_id                           = isset($this->session->data['customer_id']) ? (int) $this->session->data['customer_id'] : 0;

            $detail_object = new stdClass();
            $language = $this->config->get('payment_iyzico_language');
            if(empty($language) or $language == 'null')
            {
                $detail_object->locale                   = $this->language->get('code');
            }elseif ($language == 'TR' or $language == 'tr') {
                $detail_object->locale                   = 'tr';
            }else {
                $detail_object->locale                   = 'en';
            }

            $detail_object->conversationId = $conversation_id;
            $detail_object->token          = $this->db->escape($token);

            $rand_value             = rand(100000,99999999);
            $pki_generate           = $this->model_extension_payment_iyzico->pkiStringGenerate($detail_object);
            $authorization_data     = $this->model_extension_payment_iyzico->authorizationGenerate($pki_generate,$api_key,$secret_key,$rand_value);

            $iyzico_json = json_encode($detail_object);
            $request_response = $this->model_extension_payment_iyzico->createFormInitializeDetailRequest($iyzico_json,$authorization_data);

            if ($webhook == "webhook" &&  $webhookIyziEventType != 'CREDIT_PAYMENT_AUTH' && $request_response->status == 'failure'){
                return $this->webhookHttpResponse("errorCode: ".$request_response->errorCode ." - " . $request_response->errorMessage, 404);
            }


            if($webhook == "webhook" )
            {

              $order_id = $request_response->basketId;
              $order_info            = $this->model_checkout_order->getOrder($order_id);

              if($webhookIyziEventType == 'CREDIT_PAYMENT_AUTH' && $request_response->paymentStatus == 'PENDING_CREDIT')
                 {
                   $orderMessage = 'Alışveriş kredisi başvurusu sürecindedir.';
                   $this->model_checkout_order->addOrderHistory($request_response->basketId, 1, $orderMessage);
                   return $this->webhookHttpResponse("Order Exist - Alışveriş kredisi başvurusu sürecindedir.", 200);

                 }
              if($webhookIyziEventType == 'CREDIT_PAYMENT_AUTH' && $request_response->status == 'success')
                 {
                    $orderMessage = 'Alışveriş kredisi işlemi başarıyla tamamlandı.';
                    $this->model_checkout_order->addOrderHistory($request_response->basketId, 2 , $orderMessage);
                    return $this->webhookHttpResponse("Order Exist - Alışveriş kredisi işlemi başarıyla tamamlandı.", 200);
                   }
              if($webhookIyziEventType =='CREDIT_PAYMENT_INIT' && $request_response->status == 'INIT_CREDIT')
                 {
                    $orderMessage = 'Alışveriş kredisi işlemi başlatıldı.';
                    $this->model_checkout_order->addOrderHistory($request_response->basketId, 1 , $orderMessage);
                    return $this->webhookHttpResponse("Order Exist - Alışveriş kredisi işlemi başlatıldı.", 200);
                    }

               if($webhookIyziEventType == 'CREDIT_PAYMENT_AUTH' && $request_response->status == 'FAILURE')
                 {
                    $orderMessage = 'Alışveriş kredisi işlemi başarısız sonuçlandı.';
                    $this->model_checkout_order->addOrderHistory($request_response->basketId, 7, $orderMessage);
                    return $this->webhookHttpResponse("Order Exist - Alışveriş kredisi işlemi başarısız sonuçlandı.", 200);
                 }

            }


            if ($webhook == "webhook"){
                $order_id = $request_response->basketId;
                $order_info              = $this->model_checkout_order->getOrder($order_id);

                if ($order_info & $order_info['order_status_id'] == '5'){
                    return $this->webhookHttpResponse("Order Exist - Sipariş zaten var.", 200);

                }
            }


            $iyzico_local_order = new stdClass;
            $iyzico_local_order->payment_id         = !empty($request_response->paymentId) ? (int) $request_response->paymentId : '';
            $iyzico_local_order->order_id           = $order_id;
            $iyzico_local_order->total_amount       = !empty($request_response->paidPrice) ? (float) $request_response->paidPrice : '';
            $iyzico_local_order->status             = $request_response->paymentStatus;

            $this->model_extension_payment_iyzico->insertIyzicoOrder($iyzico_local_order);

            if($request_response->paymentStatus != 'SUCCESS' || $request_response->status != 'success' || $order_id != $request_response->basketId ) {

                /* Redirect Error */
                $errorMessage = isset($request_response->errorMessage) ? $request_response->errorMessage : $this->language->get('payment_failed');
                throw new \Exception($errorMessage);
            }

            /* Save Card */
            if(isset($request_response->cardUserKey)) {

                if($customer_id) {

                    $cardUserKey = $this->model_extension_payment_iyzico->findUserCardKey($customer_id,$api_key);

                    if($request_response->cardUserKey != $cardUserKey) {

                        $this->model_extension_payment_iyzico->insertCardUserKey($customer_id,$request_response->cardUserKey,$api_key);

                    }
                }

            }

            $payment_id            = $this->db->escape($request_response->paymentId);
            $payment_field_desc    = $this->language->get('payment_field_desc');
            if (!empty($payment_id)) {
                $message = $payment_field_desc.$payment_id . "\n";
            }


            $installment = $request_response->installment;

            if ($installment > 1) {
                $installement_field_desc = $this->language->get('installement_field_desc');
                $this->model_extension_payment_iyzico->orderUpdateByInstallement($iyzico_local_order->order_id,$request_response->paidPrice);
                $this->model_checkout_order->addOrderHistory($iyzico_local_order->order_id, $this->config->get('payment_iyzico_order_status'), $message);
                $messageInstallement = $request_response->cardFamily . ' - ' . $request_response->installment .$installement_field_desc;
                $this->model_checkout_order->addOrderHistory($iyzico_local_order->order_id, $this->config->get('payment_iyzico_order_status'), $messageInstallement);
            } else {
                $this->model_checkout_order->addOrderHistory($iyzico_local_order->order_id, $this->config->get('payment_iyzico_order_status'), $message);
            }

            if ($webhook == 'webhook'){
                return $this->webhookHttpResponse("Order Created by Webhook - Sipariş webhook tarafından oluşturuldu.", 200);
            }
            $this->setWebhookText(0);
            return $this->response->redirect($this->url->link('extension/payment/iyzico/successpage'));

        } catch (Exception $e) {

          if($request_response->paymentStatus == 'INIT_BANK_TRANSFER' && $request_response->status == 'success'){
              $orderMessage = 'iyzico Banka Havale/EFT ödemesi bekleniyor.';
              $this->model_checkout_order->addOrderHistory($iyzico_local_order->order_id, $this->config->get('payment_iyzico_order_status'), $orderMessage);
              $this->setWebhookText(0);
              return $this->response->redirect($this->url->link('extension/payment/iyzico/successpage'));
          }

          if($webhook != 'webhook' && $request_response->paymentStatus == 'PENDING_CREDIT' && $request_response->status == 'success')
          {
            $orderMessage = 'Alışveriş kredisi işlemi başlatıldı.';
            $this->model_checkout_order->addOrderHistory($iyzico_local_order->order_id, 1,$orderMessage);
            $this->setWebhookText(1);
            return $this->response->redirect($this->url->link('extension/payment/iyzico/successpage'));
          }
          $this->setWebhookText(0);

            if ($webhook == 'webhook'){
                return $this->webhookHttpResponse("errorCode: ".$request_response->errorCode ." - " . $request_response->errorMessage, 404);
            }

            $errorMessage = isset($request_response->errorMessage) ? $request_response->errorMessage : $e->getMessage();

            $this->session->data['iyzico_error_message'] = $errorMessage;

            return $this->response->redirect($this->url->link('extension/payment/iyzico/errorpage'));

        }


    }

    public function errorPage() {

        $data['continue'] = $this->url->link('common/home');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');
        $data['error_title']    = 'Ödemeniz Alınamadı.';
        $data['error_message']  = $this->session->data['iyzico_error_message'];
        $data['error_icon']     = 'catalog/view/theme/default/image/payment/iyzico_error_icon.png';

        return $this->response->setOutput($this->load->view('extension/payment/iyzico_error', $data));

    }

    public function successPage() {

        if(!isset($this->session->data['order_id'])) {
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

        // Products
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

                    if ($upload_info) {
                        $value = $upload_info['name'];
                    } else {
                        $value = '';
                    }
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
                'price'    => $this->currency->format($product['price'] + ($this->config->get('config_tax') ? $product['tax'] : 0), $order_info['currency_code'], $order_info['currency_value']),
                'total'    => $this->currency->format($product['total'] + ($this->config->get('config_tax') ? ($product['tax'] * $product['quantity']) : 0), $order_info['currency_code'], $order_info['currency_value']),
                'reorder'  => $reorder,
                'return'   => $this->url->link('account/return/add', 'order_id=' . $order_info['order_id'] . '&product_id=' . $product['product_id'], true)
            );
        }

        // Voucher
        $data['vouchers'] = array();

        $vouchers = $this->model_account_order->getOrderVouchers($order_id);

        foreach ($vouchers as $voucher) {
            $data['vouchers'][] = array(
                'description' => $voucher['description'],
                'amount'      => $this->currency->format($voucher['amount'], $order_info['currency_code'], $order_info['currency_value'])
            );
        }

        // Totals
        $data['totals'] = array();

        $totals = $this->model_account_order->getOrderTotals($order_id);

        foreach ($totals as $total) {
            $data['totals'][] = array(
                'title' => $total['title'],
                'text'  => $this->currency->format($total['value'], $order_info['currency_code'], $order_info['currency_value']),
            );
        }

        $data['comment'] = nl2br($order_info['comment']);

        // History
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
        $str_language = mb_strtolower($language);

        if(empty($str_language) or $str_language == 'null')
        {
            $locale              = $this->language->get('code');
        }else {
            $locale              = $str_language;
        }

        $data['locale'] = $locale;
        $thankyouText = $this->config->get('payment_iyzico_webhook_text');
        $data['credit_pending'] = $thankyouText;

        $data['continue'] = $this->url->link('account/order', '', true);

        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');
        $data['success_icon']     = 'catalog/view/theme/default/image/payment/iyzico_success_icon.png';

        /* Remove Order */
        unset($this->session->data['order_id']);

        return $this->response->setOutput($this->load->view('extension/payment/iyzico_success', $data));
    }

    private function dataCheck($data) {

        if(!$data || $data == ' ') {

            $data = "NOT PROVIDED";
        }

        return $data;

    }

    private function shippingInfo() {

        if(isset($this->session->data['shipping_method'])) {

            $shipping_info      = $this->session->data['shipping_method'];

        } else {

            $shipping_info = false;
        }

        if($shipping_info) {

            if ($shipping_info['tax_class_id']) {

                $shipping_info['tax'] = $this->tax->getRates($shipping_info['cost'], $shipping_info['tax_class_id']);

            } else {

                $shipping_info['tax'] = false;
            }

        }

        return $shipping_info;
    }

    private function itemPriceSubTotal($products) {

        $price = 0;

        foreach ($products as $key => $product) {

            $price+= (float) $product['total'];
        }


        $shippingInfo = $this->shippingInfo();

        if(is_object($shippingInfo) || is_array($shippingInfo)) {

            $price+= (float) $shippingInfo['cost'];

        }

        return $price;

    }

    private function priceParser($price) {

        if (strpos($price, ".") === false) {
            return $price . ".0";
        }
        $subStrIndex = 0;
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

    public function injectOverlayScript($route, &$data = false, &$output=null) {


        $this->load->model('setting/setting');

        $token              = $this->config->get('payment_iyzico_overlay_token');
        $overlay_status     = $this->config->get('payment_iyzico_overlay_status');
        $api_channel        = $this->config->get('payment_iyzico_api_channel');

        if($overlay_status != 'hidden' && $overlay_status != '' || $api_channel == 'sandbox') {

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
                </style><script> window.iyz = { token: '".$token."', position: '".$overlay_status."', ideaSoft: false, pwi:true};</script>
        <script src='https://static.iyzipay.com/buyer-protection/buyer-protection.js' type='text/javascript'></script></footer>";

            $output = str_replace($hook,$js,$output);

        }
    }


    private function getIpAdress() {

        $ip_address = $_SERVER['REMOTE_ADDR'];

        return $ip_address;
    }

    public function setWebhookText($thankyouTextValue) {

      $webhookText = $this->config->get('payment_iyzico_webhook_text');
      //$query = $this->db->query("UPDATE `" . DB_PREFIX . "setting` SET value = 1  WHERE  `key` = payment_iyzico_webhook_text ");
      $query = $this->db->query("UPDATE `" . DB_PREFIX . "setting` SET `value` = '".$thankyouTextValue."' , `serialized` = 0  WHERE `code` = 'payment_iyzico_webhook' AND `key` = 'payment_iyzico_webhook_text' AND `store_id` = '0'");
      return $query;
    }





    public function webhook(){

        if (isset($this->request->get['key']) && $this->request->get['key'] == $this->config->get('webhook_iyzico_webhook_url_key')) {

            $post = file_get_contents("php://input");
            $params = json_decode($post, true);

            if (isset(getallheaders()['x-iyz-signature'])){
                $this->iyziSignature = getallheaders()['x-iyz-signature'];
            }

            if (isset($params['iyziEventType']) && isset($params['token']) && isset($params['paymentConversationId'])){
                $this->paymentConversationId = $params['paymentConversationId'];
                $this->webhookToken = $params['token'];
                $this->iyziEventType = $params['iyziEventType'];

                if ($this->iyziSignature){
                    $secretKey = $this->config->get('payment_iyzico_secret_key');
                    $createIyzicoSignature = base64_encode(sha1($secretKey . $this->iyziEventType . $this->webhookToken, true));

                    if ($this->iyziSignature == $createIyzicoSignature){
                        $this->getCallBack('webhook', $params['paymentConversationId'], $params['token'] , $params['iyziEventType']);
                    }
                    else{
                        $this->webhookHttpResponse("signature_not_valid - X-IYZ-SIGNATURE geçersiz", 404);
                    }
                }
                else{
                    $this->getCallBack('webhook', $params['paymentConversationId'], $params['token'], $params['iyziEventType']);
                }
            }
            else{
                $this->webhookHttpResponse("invalid_parameters - Gönderilen parametreler geçersiz", 404);
            }
        }
        else{
            $this->webhookHttpResponse("invalid_key - key geçersiz", 404);
        }
    }

    public function webhookHttpResponse($message,$status){
        $httpMessage = array('message' => $message);
        header('Content-Type: application/json, Status: '. $status, true, $status);
        echo json_encode($httpMessage);
        exit();
    }
}
