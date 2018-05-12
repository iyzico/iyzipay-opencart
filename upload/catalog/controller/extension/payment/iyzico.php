<?php 

class ControllerExtensionPaymentIyzico extends Controller {

    private $module_version      = VERSION;   
    private $module_product_name = 'eleven-1.0';  

	public function index() {

        $this->load->language('extension/payment/iyzico');
        $data['form_class']         = $this->config->get('payment_iyzico_design');
        $data['form_type']          = $this->config->get('payment_iyzico_design');

        if($data['form_type'] == 'onepage')
            $data['form_class'] = 'responsive';
            

        $data['user_login_check']   = $this->customer->isLogged();
        
		return $this->load->view('extension/payment/iyzico_form',$data);
	}

	public function getCheckoutFormToken() {

		$this->load->model('checkout/order');
        $this->load->model('setting/setting');
        $this->load->model('extension/payment/iyzico');

        $module_attribute                      = false;
        $order_id                              = (int) $this->session->data['order_id'];
		$customer_id 	                       = (int) isset($this->session->data['customer_id']) ? $this->session->data['customer_id'] : 0;
		$user_id                               = (int) isset($this->session->data['user_id']) ? $this->session->data['user_id'] : 0;
        $order_info 	                       = $this->model_checkout_order->getOrder($order_id);
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
		$iyzico->locale 					  = $this->language->get('code');
		$iyzico->conversationId 			  = $order_id;
        $iyzico->price                        = $this->priceParser($this->itemPriceSubTotal($products) * $order_info['currency_value']);
        $iyzico->paidPrice                    = $this->priceParser($order_info['total'] * $order_info['currency_value']);
        $iyzico->currency                     = $order_info['currency_code'];
        $iyzico->basketId                     = $order_id;
        $iyzico->paymentGroup                 = "PRODUCT";
        $iyzico->forceThreeDS                 = "1";
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
            $iyzico->basketItems[$key] = new stdClass();

            $iyzico->basketItems[$key]->id                = $product['model'];
            $iyzico->basketItems[$key]->price             = $this->priceParser($product['total'] * $order_info['currency_value']);
            $iyzico->basketItems[$key]->name              = $product['name'];
            $iyzico->basketItems[$key]->category1         = $this->model_extension_payment_iyzico->getCategoryName($product['product_id']);
            $iyzico->basketItems[$key]->itemType          = "PHYSICAL";

        }

      $shipping = $this->shippingInfo();     

      if(!empty($shipping) && $shipping['cost']) {
           
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

    public function getCallBack()  {

        try {
            
            $this->load->language('extension/payment/iyzico');

            if(!isset($this->request->post['token']) || empty($this->request->post['token'])) {

                $errorMessage = 'invalid token';
                throw new \Exception($errorMessage);
                
            }

            $this->load->model('checkout/order');
            $this->load->model('extension/payment/iyzico');

            $api_key                               = $this->config->get('payment_iyzico_api_key');
            $secret_key                            = $this->config->get('payment_iyzico_secret_key');
            $conversation_id                       = (int) $this->session->data['conversation_id'];
            $order_id                              = (int) $this->session->data['order_id'];
            $customer_id                           = isset($this->session->data['customer_id']) ? (int) $this->session->data['customer_id'] : 0;
            
            $detail_object = new stdClass();

            $detail_object->locale         = $this->language->get('code');
            $detail_object->conversationId = $conversation_id;
            $detail_object->token          = $this->db->escape($this->request->post['token']);

            $rand_value             = rand(100000,99999999);
            $pki_generate           = $this->model_extension_payment_iyzico->pkiStringGenerate($detail_object);
            $authorization_data     = $this->model_extension_payment_iyzico->authorizationGenerate($pki_generate,$api_key,$secret_key,$rand_value);

            $iyzico_json = json_encode($detail_object);
            $request_response = $this->model_extension_payment_iyzico->createFormInitializeDetailRequest($iyzico_json,$authorization_data);

            $iyzico_local_order = new stdClass;
            $iyzico_local_order->payment_id         = !empty($request_response->paymentId) ? (int) $request_response->paymentId : '';
            $iyzico_local_order->order_id           = (int) $this->session->data['order_id'];
            $iyzico_local_order->total_amount       = !empty($request_response->paidPrice) ? (float) $request_response->paidPrice : '';
            $iyzico_local_order->status             = $request_response->paymentStatus; 

            $iyzico_order_insert  = $this->model_extension_payment_iyzico->insertIyzicoOrder($iyzico_local_order);
            
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

            return $this->response->redirect($this->url->link('extension/payment/iyzico/successpage'));
        
        } catch (Exception $e) {

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
        $this->load->model('tool/upload');

        $order_info = $this->model_account_order->getOrder($order_id);

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

    public function injectOverlayScript($route, &$data = false, &$output) {


        $this->load->model('setting/setting');
  
        $token              = $this->config->get('payment_iyzico_overlay_token');
        $overlay_status     = $this->config->get('payment_iyzico_overlay_status');
        $api_channel        = $this->config->get('payment_iyzico_api_channel');

        if($overlay_status != 'hidden' && $overlay_status != '' || $api_channel == 'sandbox') {

            $hook = '</footer>';
            $js   = "<script> window.iyz = { token: '".$token."', position: '".$overlay_status."', ideaSoft: false};</script>
            <script src='https://static.iyzipay.com/buyer-protection/buyer-protection.js' type='text/javascript'></script></footer>";

            $output = str_replace($hook,$js,$output);
        
        }
    }


    private function getIpAdress() {

          $ip_address = $_SERVER['REMOTE_ADDR'];
        
        return $ip_address;
    }
}
