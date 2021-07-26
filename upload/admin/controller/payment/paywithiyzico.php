<?php

class ControllerPaymentPaywithiyzico extends Controller {

    private $module_version = "1.0.0";
    private $module_product_name = "eleven";

    private $error = array();

    public function install(){
        $this->load->model('payment/paywithiyzico');
        $this->model_payment_paywithiyzico->install();
    }

    public function uninstall(){
        $this->load->model('payment/paywithiyzico');
        $this->model_payment_paywithiyzico->uninstall();
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

        $this->language->load('payment/paywithiyzico');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('payment/paywithiyzico');
        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate($opencartVersion)) {
            $this->model_setting_setting->editSetting('paywithiyzico', $this->request->post);
            if($opencartVersion == "2.3"){
                $this->response->redirect($this->url->link('extension/payment/paywithiyzico', 'token=' . $this->session->data['token'], 'SSL'));
            }
            else{
                $this->response->redirect($this->url->link('payment/paywithiyzico', 'token=' . $this->session->data['token'], 'SSL'));
            }
        }

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if($opencartVersion == "2.3"){
            $data['action'] = $this->url->link('extension/payment/paywithiyzico', 'token=' . $this->session->data['token'], 'SSL');
        }
        else{
            $data['action'] = $this->url->link('payment/paywithiyzico', 'token=' . $this->session->data['token'], 'SSL');
        }

        $data['cancel'] = $this->url->link('extension/payment', 'token=' . $this->session->data['token'], 'SSL');

        $data['heading_title'] = $this->language->get('heading_title');
        $data['text_enabled']  = $this->language->get('text_enabled');
        $data['text_disabled'] = $this->language->get('text_disabled');
        $data['extension_status'] = $this->language->get('extension_status');
        $data['text_paywithiyzico'] = $this->language->get('text_paywithiyzico');
        $data['module_version'] = $this->module_version;

        $data['entry_order_status'] = $this->language->get('entry_order_status');
        $data['order_status_after_payment_tooltip'] = $this->language->get('order_status_after_payment_tooltip');
        $data['order_status_after_cancel_tooltip'] = $this->language->get('order_status_after_cancel_tooltip');
        $data['entry_cancel_order_status'] = $this->language->get('entry_cancel_order_status');
        $data['entry_sort_order'] = $this->language->get('entry_sort_order');

        $error_data_array_key = array(
            'status',
            'order_status_id',
            'cancel_order_status_id',
            'form_sort_order'
        );

        foreach ($error_data_array_key as $key) {
            $data["error_{$key}"] = isset($this->error[$key]) ? $this->error[$key] : '';
        }

        $merchant_keys_name_array = array(
            'paywithiyzico_status',
            'paywithiyzico_order_status_id',
            'paywithiyzico_cancel_order_status_id',
            'paywithiyzico_form_sort_order'
        );

        foreach ($merchant_keys_name_array as $key) {
            $data[$key] = isset($this->request->post[$key]) ? $this->request->post[$key] : $this->config->get($key);
        }

        $this->load->model('localisation/order_status');

        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();


        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view('payment/paywithiyzico.tpl', $data));



    }

    protected function validate($opencartVersion){

        if($opencartVersion == "2.3"){

            if (!$this->user->hasPermission('modify', 'extension/payment/paywithiyzico')) {
                $this->error['warning'] = $this->language->get('error_permission');
            }
        }
        else{
            if (!$this->user->hasPermission('modify', 'payment/paywithiyzico')) {
                $this->error['warning'] = $this->language->get('error_permission');
            }
        }

        $validation_array = array(
            'status',
            'order_status_id',
            'cancel_order_status_id'
        );

        foreach ($validation_array as $key) {
            if (empty($this->request->post["paywithiyzico_{$key}"]) &&  $this->request->post["paywithiyzico_status"] != "0"){
                $this->error[$key] = $this->language->get("error_$key");
            }
        }

        if (!$this->error) {
            return true;
        } else {
            return false;
        }
    }

}