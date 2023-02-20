<?php

class ControllerExtensionPaymentPaywithiyzico extends Controller {

    private $module_version      = '1.7';
    private $module_product_name = 'eleven-1.7';

    private $error = array();

    private $fields = array(
        array(
            'validateField' => 'blank',
            'name'          => 'payment_paywithiyzico_status',
        ),
        array(
            'validateField' => 'error_order_status',
            'name'          => 'payment_paywithiyzico_order_status',
        ),
        array(
            'validateField' => 'error_cancel_order_status',
            'name'          => 'payment_paywithiyzico_order_cancel_status',
        )

    );


    public function install() {

        $this->load->model('extension/payment/paywithiyzico');
        $this->model_extension_payment_paywithiyzico->install();
        $this->model_setting_event->addEvent('pwi_logo_css_for_order_history', 'catalog/controller/common/footer/after', 'extension/payment/paywithiyzico/injectPwiLogoCss');
    }

    public function uninstall() {

        $this->load->model('extension/payment/paywithiyzico');
        $this->model_extension_payment_paywithiyzico->uninstall();
        $this->model_setting_event->deleteEventByCode('pwi_logo_css_for_order_history');
    }

    public function index() {

        $this->load->language('extension/payment/paywithiyzico');
        $this->load->model('setting/setting');
        $this->load->model('user/user');
        $this->load->model('extension/payment/paywithiyzico');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {

            $request            = $this->requestIyzico($this->request->post,'add','');

            $this->model_setting_setting->editSetting('payment_paywithiyzico',$request);



            $this->response->redirect($this->url->link('extension/payment/paywithiyzico', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
        }

        /* Get Order Status */
        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();


        $data['action']         = $this->url->link('extension/payment/paywithiyzico', 'user_token=' . $this->session->data['user_token'], true);
        $data['heading_title']  = $this->language->get('heading_title');
        $data['header']         = $this->load->controller('common/header');
        $data['column_left']    = $this->load->controller('common/column_left');
        $data['footer']         = $this->load->controller('common/footer');
        $data['locale']         = $this->language->get('code');
        $data['version']        = $this->module_version;
        $data['pwi_module_logo']=$this->language->get('pwi_module_setting_logo');


        foreach ($this->fields as $key => $field) {

            if (isset($this->error[$field['validateField']])) {
                $data[$field['validateField']] = $this->error[$field['validateField']];
            } else {
                $data[$field['validateField']] = '';
            }

            if (isset($this->request->post[$field['name']])) {
                $data[$field['name']] = $this->request->post[$field['name']];
            } else {
                $data[$field['name']] = $this->config->get($field['name']);
            }
        }


        $this->response->setOutput($this->load->view('extension/payment/paywithiyzico', $data));
    }

    protected function validate() {

        if (!$this->user->hasPermission('modify', 'extension/payment/paywithiyzico')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        foreach ($this->fields as $key => $field) {

            if($field['validateField'] != 'blank') {

                if (!$this->request->post[$field['name']]){
                    $this->error[$field['validateField']] = $this->language->get($field['validateField']);
                }
            }

        }

        return !$this->error;
    }

    public function requestIyzico($request,$method_type,$extra_request = false) {

        $request_modify = array();

        if ($method_type == 'add') {


            foreach ($this->fields as $key => $field) {

                if(isset($request[$field['name']])) {

                    if($field['name'] == 'payment_paywithiyzico_api_key' || $field['name'] == 'payment_paywithiyzico_secret_key')
                        $request[$field['name']] = str_replace(' ','',$request[$field['name']]);

                    $request_modify[$field['name']] = $request[$field['name']];

                }

            }


        }



        return $request_modify;
    }



}
