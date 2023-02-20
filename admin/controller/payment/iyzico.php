<?php

class ControllerPaymentIyzico extends Controller {

    private $module_version = "2.1.0";
    private $module_product_name = "FLAP";

    private $error = array();

    public function install(){
        $this->load->model('payment/iyzico');
        $this->model_payment_iyzico->install();
        $this->load->model('extension/event');
        $this->model_extension_event->addEvent('iyzico', 'catalog/controller/common/footer/after', 'extension/payment/iyzico/injectOverlayScript');
    }

    public function uninstall(){
        $this->load->model('payment/iyzico');
        $this->model_payment_iyzico->uninstall();
        $this->db->query("DELETE FROM " . DB_PREFIX . "setting WHERE store_id = '0' AND code = 'payment_iyzico_pwi_status'");
        $this->load->model('extension/event');
        $this->model_extension_event->deleteEvent('iyzico');

    }

    public function index()
    {
        //2.3 or 2.0 or 2.1
        $opencartVersion = substr(VERSION,0,3);

        //Call install, uninstall for Opencart 2.3v
        $request = get_object_vars($this->request);
        $requestRoute = $request['get']['route'];
        if (($this->request->server['REQUEST_METHOD'] == 'GET') && $opencartVersion == "2.3" && strpos($requestRoute, "uninstall")){
            $this->uninstall();
        }
        elseif($this->request->server['REQUEST_METHOD'] == 'GET' && $opencartVersion == "2.3" && strpos($requestRoute, "install")){
            $this->install();
        }

        $this->language->load('payment/iyzico');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('payment/iyzico');
        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate($opencartVersion)) {
            $this->model_setting_setting->editSetting('iyzico', $this->request->post);
            $this->setIyzicoApiUrl();
            if($opencartVersion == "2.3"){
                $this->response->redirect($this->url->link('extension/payment/iyzico', 'token=' . $this->session->data['token'], 'SSL'));
            }
            else{
                $this->response->redirect($this->url->link('payment/iyzico', 'token=' . $this->session->data['token'], 'SSL'));
            }
        }

        $this->setIyziWebhookUrlKey();
        $this->setIyzicoApiUrl();
        $this->getOverlayScript($this->config->get('iyzico_overlay_status'),
            $this->config->get('iyzico_checkout_form_api_id_live'),$this->config->get('iyzico_checkout_form_secret_key_live'));


        $data['api_status'] = $this->getApiConnection() ? "success" : "fail";

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if($opencartVersion == "2.3"){
            $data['action'] = $this->url->link('extension/payment/iyzico', 'token=' . $this->session->data['token'], 'SSL');
        }
        else{
            $data['action'] = $this->url->link('payment/iyzico', 'token=' . $this->session->data['token'], 'SSL');
        }

        $data['cancel'] = $this->url->link('extension/payment', 'token=' . $this->session->data['token'], 'SSL');

        $data['opencart_version'] = $opencartVersion;
        $data['heading_title'] = $this->language->get('heading_title');
        $data['text_enabled']  = $this->language->get('text_enabled');
        $data['text_disabled'] = $this->language->get('text_disabled');
        $data['extension_status'] = $this->language->get('extension_status');
        $data['text_iyzico'] = $this->language->get('text_iyzico');
        $data['module_version'] = $this->module_version;
        $data['general_select'] = $this->language->get('general_select');
        $data['iyzico_webhook'] = $this->language->get('iyzico_webhook');
        $data['iyzico_webhook_url_key_error'] = $this->language->get('iyzico_webhook_url_key_error');
        $data['iyzico_settings'] = $this->language->get('iyzico_settings');
        $data['api_connection_text'] = $this->language->get('api_connection_text');
        $data['api_connection_success'] = $this->language->get('api_connection_success');
        $data['api_connection_failed'] = $this->language->get('api_connection_failed');


        $data['entry_api_channel'] = $this->language->get('entry_api_channel');
        $data['entry_api_live'] = $this->language->get('entry_api_live');
        $data['entry_api_sandbox'] = $this->language->get('entry_api_sandbox');

        $data['entry_api_id_live'] = $this->language->get('entry_api_id_live');
        $data['entry_secret_key_live'] = $this->language->get('entry_secret_key_live');

        $data['entry_class'] = $this->language->get('entry_class');
        $data['entry_class_responsive'] = $this->language->get('entry_class_responsive');
        $data['entry_class_popup'] = $this->language->get('entry_class_popup');

        $data['entry_buyer_protection'] = $this->language->get('entry_buyer_protection');
        $data['entry_overlay_bottom_left'] = $this->language->get('entry_overlay_bottom_left');
        $data['entry_overlay_bottom_right'] = $this->language->get('entry_overlay_bottom_right');
        $data['entry_overlay_closed'] = $this->language->get('entry_overlay_closed');


        $data['entry_order_status'] = $this->language->get('entry_order_status');
        $data['entry_cancel_order_status'] = $this->language->get('entry_cancel_order_status');

        $data['entry_sort_order'] = $this->language->get('entry_sort_order');

        $data['api_channel_tooltip'] = $this->language->get('api_channel_tooltip');
        $data['buyer_protection_tooltip'] = $this->language->get('buyer_protection_tooltip');
        $data['order_status_after_payment_tooltip'] = $this->language->get('order_status_after_payment_tooltip');
        $data['order_status_after_cancel_tooltip'] = $this->language->get('order_status_after_cancel_tooltip');

        $error_data_array_key = array(
            'api_id_live',
            'secret_key_live',
        );

        foreach ($error_data_array_key as $key) {
            $data["error_{$key}"] = isset($this->error[$key]) ? $this->error[$key] : '';
        }

        $merchant_keys_name_array = array(
            'iyzico_status',
            'iyzico_api_channel',
            'iyzico_checkout_form_api_id_live',
            'iyzico_checkout_form_secret_key_live',
            'iyzico_form_class',
            'iyzico_overlay_status',
            'iyzico_order_status_id',
            'iyzico_cancel_order_status_id',
            'iyzico_form_sort_order',
        );

        foreach ($merchant_keys_name_array as $key) {
            $data[$key] = isset($this->request->post[$key]) ? $this->request->post[$key] : $this->config->get($key);
        }

        $this->load->model('localisation/order_status');

        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();


        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $data['locale'] = $this->language->get('code');
        $data['iyzico_webhook_url_key'] = $this->config->get('iyzico_webhook_url_key');
        $data['iyzico_webhook_url_description'] = $this->language->get('iyzico_webhook_url_description');
        $data['iyzico_webhook_url']  = HTTPS_CATALOG.'index.php?route=extension/payment/iyzico/webhook&key=' . $data['iyzico_webhook_url_key'];

        $pwi_status    = $this->config->get('paywithiyzico_status');
        $pwi_status_after_enabled_pwi = $this->config->get('payment_iyzico_pwi_first_enabled_status');

        $data_pwi_load_check['heading_title']  = $data['heading_title'];
        $data_pwi_load_check['pwi_status_error']  = $this->language->get('pwi_status_error');
        $data_pwi_load_check['pwi_status_error_detail']  = $this->language->get('pwi_status_error_detail');
        $data_pwi_load_check['dev_iyzipay_opencart_link']  = $this->language->get('dev_iyzipay_opencart_link');
        $data_pwi_load_check['dev_iyzipay_detail']  = $this->language->get('dev_iyzipay_detail');
        $data_pwi_load_check['header']         = $this->load->controller('common/header');
        $data_pwi_load_check['column_left']    = $this->load->controller('common/column_left');

        //if pwi disabled and pwi first enabled status 0, set output pwi load page
        if ($pwi_status == 0 && $pwi_status_after_enabled_pwi != 1){
            $this->response->setOutput($this->load->view('payment/iyzico_pwi_load_control.tpl', $data_pwi_load_check));
        }
        else{
            $this->setPWIModuleFirstStatus($pwi_status_after_enabled_pwi);
            $this->response->setOutput($this->load->view('payment/iyzico.tpl', $data));
        }
    }

    protected function validate($opencartVersion){

        if($opencartVersion == "2.3"){

            if (!$this->user->hasPermission('modify', 'extension/payment/iyzico')) {
                $this->error['warning'] = $this->language->get('error_permission');
            }
        }
        else{
            if (!$this->user->hasPermission('modify', 'payment/iyzico')) {
                $this->error['warning'] = $this->language->get('error_permission');
            }
        }

        $validation_array = array(
            'api_id_live',
            'secret_key_live'
        );

        foreach ($validation_array as $key) {
            if (empty($this->request->post["iyzico_checkout_form_{$key}"])){
                $this->error[$key] = $this->language->get("error_$key");
            }
        }

        if (!$this->error) {
            return true;
        } else {
            return false;
        }
    }

    private function getOverlayScript($position,$api_key,$secret_key) {

        $overlay_script_object = new stdClass();
        $overlay_script_object->locale          = $this->language->get('code');
        $overlay_script_object->conversationId  = rand(100000,99999999);
        $overlay_script_object->position        = $position;

        $overlay_pki         = $this->model_payment_iyzico->pkiStringGenerate($overlay_script_object);
        $authorization_data  = $this->model_payment_iyzico->authorizationGenerate($api_key,$secret_key,$overlay_pki);
        $overlay_script      = $this->model_payment_iyzico->overlayScript($authorization_data,$overlay_script_object);

        if (isset($overlay_script->protectedShopId)){
            $this->model_setting_setting->editSetting('iyzico_overlay',array(
                "iyzico_overlay_token" => $overlay_script->protectedShopId
            ));
        }
        else{
            $this->model_setting_setting->editSetting('iyzico_overlay',array(
                "iyzico_overlay_token" => ''
            ));
        }
    }

    private function getApiConnection() {

        $api_key = $this->config->get('iyzico_checkout_form_api_id_live');
        $secret_key = $this->config->get('iyzico_checkout_form_secret_key_live');

        $api_con_object = new stdClass();
        $api_con_object->locale           = $this->language->get('code');
        $api_con_object->conversationId   = rand(100000,99999999);
        $api_con_object->binNumber        = '454671';

        $api_con_pki         = $this->model_payment_iyzico->pkiStringGenerate($api_con_object);
        $authorization_data  = $this->model_payment_iyzico->authorizationGenerate($api_key,$secret_key,$api_con_pki);
        $test_api_con        = $this->model_payment_iyzico->apiConnection($authorization_data,$api_con_object);

        if(isset($test_api_con->status) && $test_api_con->status == 'success') {
            $api_status  = true;

        } else {

            $api_status  = false;
        }

        return $api_status;
    }

    /**
     * @return bool
     */
    private function setIyziWebhookUrlKey()
    {

        $webhookUrl = $this->config->get('iyzico_webhook_url_key');
        $uniqueUrlId = substr(base64_encode(time() . mt_rand()),15,6);

        if (!$webhookUrl) {
            $this->model_setting_setting->editSetting('iyzico_webhook',array(
                "iyzico_webhook_url_key" => $uniqueUrlId
            ));
        }

        return true;
    }

    private function setIyzicoApiUrl(){

        if (isset($this->request->post['iyzico_api_channel']) && $this->request->post['iyzico_api_channel'] == 'live'){
            $this->model_setting_setting->editSetting('iyzico_api',array(
                "iyzico_api_url" => 'https://api.iyzipay.com'
            ));
        }
        elseif (isset($this->request->post['iyzico_api_channel']) && $this->request->post['iyzico_api_channel'] == 'sandbox'){
            $this->model_setting_setting->editSetting('iyzico_api',array(
                "iyzico_api_url" => 'https://sandbox-api.iyzipay.com'
            ));
        }
        elseif ($this->config->get('iyzico_api_channel') == 'live'){
            $this->model_setting_setting->editSetting('iyzico_api',array(
                "iyzico_api_url" => 'https://api.iyzipay.com'
            ));
        }
        else {
            $this->model_setting_setting->editSetting('iyzico_api',array(
                "iyzico_api_url" => 'https://sandbox-api.iyzipay.com'
            ));
        }

        return true;
    }

    //if pwi enabled, set pwi_status key in setting table
    private function setPWIModuleFirstStatus($pwiStatus)
    {
        if (!isset($pwiStatus)){
            $this->db->query("INSERT INTO `" . DB_PREFIX . "setting` (`code`, `key`, `value`, `serialized`) VALUES ('payment_iyzico_pwi_status', 'payment_iyzico_pwi_first_enabled_status', '1', '0');");
        }
    }
}
