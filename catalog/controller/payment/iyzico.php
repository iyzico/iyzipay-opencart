<?php
error_reporting(0);

//Opencart2.0v Only use for index function
class ControllerPaymentIyzico extends Controller {
    private $module_version = "2.1.0";
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


}
