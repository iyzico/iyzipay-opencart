<?php
error_reporting(0);

//Opencart2.3v and 2.0v are using Payment
class ControllerExtensionPaymentIyzico extends Controller {
    private $module_version = "2.0.0";
    private $module_product_name = "FLAP";

    public function index()
    {
        $this->checkAndSetCookieSameSite();

        $this->load->language('payment/iyzico');
        $this->load->model('checkout/order');

        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        $cart_total_amount = round($order_info['total'] * $order_info['currency_value'], 2);
        $data['cart_total'] = $cart_total_amount;
        $data['form_class'] = $this->config->get('iyzico_form_class');
        $data['text_wait'] = $this->language->get('text_wait');
        $data['button_confirm'] = $this->language->get('button_confirm');
        $data['continue'] = $this->url->link('checkout/success');
        $data['error_page'] = $this->url->link('checkout/error');


        if (VERSION >= '2.2.0.0'){
            $template_url = 'payment/iyzico_form.tpl';
        }
        else{
            $template_url = 'default/template/payment/iyzico_form.tpl';
        }

        return $this->load->view($template_url,$data);

    }

    public function getCheckoutFormToken()
    {
        $this->load->model('checkout/order');
        $this->load->model('setting/setting');
        $this->load->model('payment/iyzico');

        $order_id                              = (int) $this->session->data['order_id'];
        $customer_id 	                       = (int) isset($this->session->data['customer_id']) ? $this->session->data['customer_id'] : 0;
        $user_id                               = (int) isset($this->session->data['user_id']) ? $this->session->data['user_id'] : 0;
        $order_info                            = $this->model_checkout_order->getOrder($order_id);
        $products                              = $this->cart->getProducts();
        $api_key                               = $this->config->get('iyzico_checkout_form_api_id_live');
        $secret_key                            = $this->config->get('iyzico_checkout_form_secret_key_live');
        $payment_source                        = "OPENCART-".VERSION.'|'.$this->module_version."-".$this->module_product_name;

        $user_create_date                      = $this->model_payment_iyzico->getUserCreateDate($user_id);

        $this->session->data['conversation_id'] = $order_id;

        $order_info['payment_address']         = $order_info['payment_address_1']." ".$order_info['payment_address_2'];
        $order_info['shipping_address']        = $order_info['shipping_address_1']." ".$order_info['shipping_address_2'];

        /* Order Detail */
        $iyzico = new stdClass;
        $iyzico->locale 					  = $this->language->get('code');
        $iyzico->conversationId 			  = $order_id;
        $iyzico->price                        = $this->priceParser($this->itemPriceSubTotal($products) * $order_info['currency_value']);
        $iyzico->paidPrice                    = $this->priceParser($order_info['total'] * $order_info['currency_value']);
        $iyzico->currency                     = $order_info['currency_code'];
        $iyzico->basketId                     = $order_id;
        $iyzico->paymentGroup                 = "PRODUCT";
        $iyzico->forceThreeDS                 = "0";
        $iyzico->callbackUrl                  = $this->url->link('extension/payment/iyzico/getcallback', '', true);
        $iyzico->cardUserKey                  = $this->model_payment_iyzico->findUserCardKey($customer_id,$api_key);
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

        $count = 0;
        foreach ($products as $product) {
            $price = $product['total'] * $order_info['currency_value'];
            if($price) {
                $iyzico->basketItems[$count] = new stdClass;

                $iyzico->basketItems[$count]->id                = $product['model'];
                $iyzico->basketItems[$count]->price             = $this->priceParser($price);
                $iyzico->basketItems[$count]->name              = $product['name'];
                $iyzico->basketItems[$count]->category1         = $this->model_payment_iyzico->getCategoryName($product['product_id']);
                $iyzico->basketItems[$count]->itemType          = "PHYSICAL";

                $count++;
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
        $order_object           = $this->model_payment_iyzico->createFormInitializObjectSort($iyzico);
        $pki_generate           = $this->model_payment_iyzico->pkiStringGenerate($order_object);
        $authorization_data     = $this->model_payment_iyzico->authorizationGenerate($pki_generate,$api_key,$secret_key,$rand_value);

        $iyzico_json = json_encode($iyzico,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);

        $form_response = $this->model_payment_iyzico->createFormInitializeRequest($iyzico_json,$authorization_data);

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($form_response));

    }

    public function getCallBack($webhook = null, $webhookPaymentConversationId = null ,$webhookToken = null)
    {

        try {

            $this->load->language('payment/iyzico');

            if((!isset($this->request->post['token']) || empty($this->request->post['token'])) && $webhook != "webhook") {
                $errorMessage = $this->language->get('invalid_token');
                throw new \Exception($errorMessage);
            }

            $this->load->model('checkout/order');
            $this->load->model('payment/iyzico');

            $api_key                               = $this->config->get('iyzico_checkout_form_api_id_live');
            $secret_key                            = $this->config->get('iyzico_checkout_form_secret_key_live');

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

            $detail_object->locale         = $this->language->get('code');
            $detail_object->conversationId = $conversation_id;
            $detail_object->token          = $this->db->escape($token);

            $rand_value             = rand(100000,99999999);
            $pki_generate           = $this->model_payment_iyzico->pkiStringGenerate($detail_object);
            $authorization_data     = $this->model_payment_iyzico->authorizationGenerate($pki_generate,$api_key,$secret_key,$rand_value);

            $iyzico_json = json_encode($detail_object);
            $request_response = $this->model_payment_iyzico->createFormInitializeDetailRequest($iyzico_json,$authorization_data);

            if ($webhook == "webhook" && $request_response->status == 'failure'){
                return $this->webhookHttpResponse("errorCode: ".$request_response->errorCode ." - " . $request_response->errorMessage, 404);
            }

            if ($webhook == "webhook"){
                $order_id = $request_response->basketId;
                $order_info = $this->model_checkout_order->getOrder($order_id);

                if (!$order_info){
                    return $this->webhookHttpResponse("Order Not Found - Sipariş yok.", 404);
                }

                if ($order_info && $order_info['order_status_id'] == $this->config->get('iyzico_order_status_id')){
                    return $this->webhookHttpResponse("Order Exist - Sipariş zaten var.", 200);
                }
            }

            $iyzico_local_order = new stdClass;
            $iyzico_local_order->payment_id         = !empty($request_response->paymentId) ? (int) $request_response->paymentId : '';
            $iyzico_local_order->order_id           = $order_id;
            $iyzico_local_order->total_amount       = !empty($request_response->paidPrice) ? (float) $request_response->paidPrice : '';
            $iyzico_local_order->status             = $request_response->paymentStatus;

            $iyzico_order_insert  = $this->model_payment_iyzico->insertIyzicoOrder($iyzico_local_order);

            if($request_response->paymentStatus != 'SUCCESS' || $request_response->status != 'success' || $order_id != $request_response->basketId ) {

                /* Redirect Error */
                $errorMessage = isset($request_response->errorMessage) ? $request_response->errorMessage : $this->language->get('payment_failed');
                throw new \Exception($errorMessage);
            }

            /* Save Card */
            if(isset($request_response->cardUserKey)) {

                if($customer_id) {

                    $cardUserKey = $this->model_payment_iyzico->findUserCardKey($customer_id,$api_key);

                    if($request_response->cardUserKey != $cardUserKey) {

                        $this->model_payment_iyzico->insertCardUserKey($customer_id,$request_response->cardUserKey,$api_key);

                    }
                }
            }



            $payment_id            = $this->db->escape($request_response->paymentId);

            $payment_field_desc    = $this->language->get('payment_field_desc');
            if (!empty($payment_id)) {
                $message = $payment_field_desc. ": ".$payment_id . "\n";
            }

            $installment = $request_response->installment;
            $paymentReceivedMessage = $this->language->get('payment_received_message');

            if ($installment > 1) {
                $installement_count = $this->language->get('installement_count');
                $this->model_checkout_order->addOrderHistory($iyzico_local_order->order_id, $this->config->get('iyzico_order_status_id'), $message, false);
                $messageInstallement = $request_response->cardFamily . ' - ' . $request_response->installment . $installement_count;
                $this->model_checkout_order->addOrderHistory($iyzico_local_order->order_id, $this->config->get('iyzico_order_status_id'), $messageInstallement, false);
                $this->model_payment_iyzico->addCustomerMessageToOrderHistory($iyzico_local_order->order_id, $this->config->get('iyzico_order_status_id'), $paymentReceivedMessage, true);
                $this->model_payment_iyzico->orderUpdateByInstallement($iyzico_local_order->order_id,$request_response->paidPrice);
            } else {
                $this->model_checkout_order->addOrderHistory($iyzico_local_order->order_id, $this->config->get('iyzico_order_status_id'), $message, false);
                $this->model_payment_iyzico->addCustomerMessageToOrderHistory($iyzico_local_order->order_id, $this->config->get('iyzico_order_status_id'), $paymentReceivedMessage, true);
            }

            if ($webhook == 'webhook'){
                return $this->webhookHttpResponse("Order Created by Webhook - Sipariş webhook tarafından oluşturuldu.", 200);
            }

            return $this->response->redirect($this->url->link('checkout/success', '', 'SSL'));

        }
        catch (Exception $e){

            if ($webhook == 'webhook'){
                return $this->webhookHttpResponse("errorCode: ".$request_response->errorCode ." - " . $request_response->errorMessage, 404);
            }

            $errorMessage = isset($request_response->errorMessage) ? $request_response->errorMessage : $e->getMessage();

            $this->session->data['iyzico_error_message'] = $errorMessage;

            return $this->response->redirect($this->url->link('extension/payment/iyzico/errorpage'));
        }

    }

    public function errorPage()
    {

        $this->load->language('payment/iyzico');

        $data['continue'] = $this->url->link('common/home');
        $data['homepage_button_text'] = $this->language->get('homepage_button_text');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');
        $data['error_title']    = $this->language->get('error_message_sender_head');
        $data['error_message']  = $this->session->data['iyzico_error_message'];
        $data['error_icon']     = 'catalog/view/theme/default/image/payment/iyzico_error_icon.png';

        if (VERSION >= '2.2.0.0'){
            return $this->response->setOutput($this->load->view('payment/iyzico_error', $data));
        }
        else{
            return $this->response->setOutput($this->load->view('default/template/payment/iyzico_error.tpl', $data));
        }
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

    private function getIpAdress() {

        $ip_address = $_SERVER['REMOTE_ADDR'];

        return $ip_address;
    }

    private function dataCheck($data) {

        if(!$data || $data == ' ') {

            $data = "NOT PROVIDED";
        }

        return $data;

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

    public function injectOverlayScript($route, $args, &$output) {

        $this->load->model('setting/setting');

        $token              = $this->config->get('iyzico_overlay_token');
        $overlay_status     = $this->config->get('iyzico_overlay_status');
        $api_channel        = $this->config->get('iyzico_api_channel');

        if($overlay_status != 'closed' && $overlay_status != '' && $api_channel != 'sandbox') {

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
	            </style><script> window.iyz = { token: '" . $token . "', position: '" . $overlay_status . "', ideaSoft: false, pwi:true};</script>
        <script src='https://static.iyzipay.com/buyer-protection/buyer-protection.js' type='text/javascript'></script></footer>";

            $output = str_replace($hook, $js, $output);
        }

    }

    public function webhook()
    {
        if (isset($this->request->get['key']) && $this->request->get['key'] == $this->config->get('iyzico_webhook_url_key')) {
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
                    $secretKey = $this->config->get('iyzico_checkout_form_secret_key_live');
                    $createIyzicoSignature = base64_encode(sha1($secretKey . $this->iyziEventType . $this->webhookToken, true));

                    if ($this->iyziSignature == $createIyzicoSignature){
                        $this->getCallBack('webhook', $params['paymentConversationId'], $params['token']);
                    }
                    else{
                        $this->webhookHttpResponse("signature_not_valid - X-IYZ-SIGNATURE geçersiz", 404);
                    }
                }
                else{
                    $this->getCallBack('webhook', $params['paymentConversationId'], $params['token']);
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