<?php
class ControllerExtensionPaymentIyzico extends Controller {
    private $module_version      = '2.0';

    private $error = array();

    private $fields = array(
        array(
            'validateField' => 'error_api_channel',
            'name'          => 'payment_iyzico_api_channel',
        ),
        array(
            'validateField' => 'blank',
            'name'          => 'payment_iyzico_api_url',
        ),
        array(
            'validateField' => 'error_api_key',
            'name'          => 'payment_iyzico_api_key',
        ),
        array(
            'validateField' => 'error_secret_key',
            'name'          => 'payment_iyzico_secret_key',
        ),
        array(
            'validateField' => 'error_design',
            'name'          => 'payment_iyzico_design',
        ),
        array(
            'validateField' => 'error_order_status',
            'name'          => 'payment_iyzico_order_status',
        ),
        array(
            'validateField' => 'error_cancel_order_status',
            'name'          => 'payment_iyzico_order_cancel_status',
        ),
        array(
            'validateField' => 'blank',
            'name'          => 'payment_iyzico_status',
        ),
        array(
            'validateField' => 'blank',
            'name'          => 'payment_iyzico_sort_order',
        ),
        array(
            'validateField' => 'error_title',
            'name'          => 'payment_iyzico_title',
        ),
        array(
            'validateField' => 'blank',
            'name'          => 'payment_iyzico_order_status_id',
        ),
        array(
            'validateField' => 'blank',
            'name'          => 'payment_iyzico_overlay_token',
        ),
        array(
            'validateField' => 'blank',
            'name'          => 'payment_iyzico_overlay_position',
        ),
        array(
            'validateField' => 'blank',
            'name'          => 'payment_iyzico_overlay_status',
        ),
        array(
            'validateField' => 'blank',
            'name'          => 'webhook_iyzico_webhook_url_key',
        )

    );

    public function index() {

        $this->load->language('extension/payment/iyzico');
        $this->load->model('setting/setting');
        $this->load->model('user/user');
        $this->load->model('extension/payment/iyzico');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {

            $request            = $this->requestIyzico($this->request->post,'add','');

            $overlay_result     = $this->getOverlayScript($request['payment_iyzico_overlay_status'],
                $request['payment_iyzico_api_key'],
                $request['payment_iyzico_secret_key']);


            $request_overlay    = $this->requestIyzico($request,'edit',$overlay_result);

            $request            = array_merge($request,$request_overlay);

            $this->model_setting_setting->editSetting('payment_iyzico',$request);

            $this->getApiConnection($request['payment_iyzico_api_key'],$request['payment_iyzico_secret_key']);


            $this->response->redirect($this->url->link('extension/payment/iyzico', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
        }

        $this->setIyziWebhookUrlKey();

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


        $this->document->setTitle($this->language->get('heading_title'));
        $this->document->addStyle('view/stylesheet/iyzico/iyzico.css');
        $this->document->addScript('view/javascript/iyzico/accordion_iyzico.js','footer');



        /* Extension Install Completed Status */
        $data['install_status']  = $this->installStatus();

        /* User Info Get*/
        $user_info              = $this->model_user_user->getUser($this->user->getId());
        $data['firstname']      = $user_info['firstname'];
        $data['lastname']       = $user_info['lastname'];

        /* Get Api Status */
        $data['api_status']     = $this->getApiStatus($data['install_status']);

        /* Get Order Status */
        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();


        $data['action']         = $this->url->link('extension/payment/iyzico', 'user_token=' . $this->session->data['user_token'], true);
        $data['heading_title']  = $this->language->get('heading_title');
        $data['header']         = $this->load->controller('common/header');
        $data['column_left']    = $this->load->controller('common/column_left');
        $data['footer']         = $this->load->controller('common/footer');
        $data['locale']         = $this->language->get('code');
        $data['iyzico_webhook_url_key'] = $this->config->get('webhook_iyzico_webhook_url_key');
        $data['iyzico_webhook_url']  = HTTPS_CATALOG.'index.php?route=extension/payment/iyzico/webhook&key=' .$this->config->get('webhook_iyzico_webhook_url_key');
        $data['module_version'] = $this->module_version;

        $this->response->setOutput($this->load->view('extension/payment/iyzico', $data));
    }

    private function getApiConnection($api_key,$secret_key) {

        $api_con_object = new stdClass();
        $api_con_object->locale           = $this->language->get('code');
        $api_con_object->conversationId   = rand(100000,99999999);
        $api_con_object->binNumber        = '454671';

        $api_con_pki         = $this->model_extension_payment_iyzico->pkiStringGenerate($api_con_object);
        $authorization_data  = $this->model_extension_payment_iyzico->authorizationGenerate($api_key,$secret_key,$api_con_pki);
        $test_api_con        = $this->model_extension_payment_iyzico->apiConnection($authorization_data,$api_con_object);

        if(isset($test_api_con->status) && $test_api_con->status == 'success') {
            $api_status  = true;

        } else {

            $api_status  = false;
        }

        $this->session->data['api_status'] = $api_status;

        return $api_status;
    }

    private function getOverlayScript($position,$api_key,$secret_key) {

        $overlay_script_object = new stdClass();
        $overlay_script_object->locale          = $this->language->get('code');
        $overlay_script_object->conversationId  = rand(100000,99999999);
        $overlay_script_object->position        = $position;

        $overlay_pki         = $this->model_extension_payment_iyzico->pkiStringGenerate($overlay_script_object);
        $authorization_data  = $this->model_extension_payment_iyzico->authorizationGenerate($api_key,$secret_key,$overlay_pki);
        $overlay_script      = $this->model_extension_payment_iyzico->overlayScript($authorization_data,$overlay_script_object);

        return $overlay_script;
    }

    private function getApiStatus($install_status) {

        $api_status = false;

        if($install_status >= 6 ) {

            if(isset($this->session->data['api_status']) && !empty($this->session->data['api_status'])) {

                $api_status    = $this->session->data['api_status'];

            } else {
                $api_key    = $this->config->get('payment_iyzico_api_key');
                $secret_key = $this->config->get('payment_iyzico_secret_key');

                return $this->getApiConnection($api_key,$secret_key);
            }

        } else {

            $api_status     = false;
        }


        return $api_status;

    }

    private function installStatus() {

        $counter = 0;

        foreach ($this->fields as $key => $field) {

            $data[$field['name']] = $this->config->get($field['name']);
            if(!empty($this->config->get($field['name'])))
                $counter++;
        }


        return $counter;
    }


    public function install() {

        $this->load->model('extension/payment/iyzico');
        $this->model_extension_payment_iyzico->install();
        $this->model_setting_event->addEvent('overlay_script', 'catalog/controller/common/footer/after', 'extension/payment/iyzico/injectOverlayScript');
        $this->model_setting_event->addEvent('module_notification', 'admin/controller/common/footer/after', 'extension/payment/iyzico/injectModuleNotification');
    }

    public function uninstall() {

        $this->load->model('extension/payment/iyzico');
        $this->model_extension_payment_iyzico->uninstall();
        $this->model_setting_event->deleteEventByCode('overlay_script');
        $this->model_setting_event->deleteEventByCode('module_notification');
    }

    protected function validate() {

        if (!$this->user->hasPermission('modify', 'extension/payment/iyzico')) {
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

                    if($field['name'] == 'payment_iyzico_api_key' || $field['name'] == 'payment_iyzico_secret_key')
                        $request[$field['name']] = str_replace(' ','',$request[$field['name']]);

                    $request_modify[$field['name']] = $request[$field['name']];
                }

            }

            if($request_modify['payment_iyzico_api_channel'] == 'live') {

                $request_modify['payment_iyzico_api_url'] = 'https://api.iyzipay.com';

            } else if($request_modify['payment_iyzico_api_channel'] == 'sandbox') {

                $request_modify['payment_iyzico_api_url'] = 'https://sandbox-api.iyzipay.com';
                $request_modify['payment_iyzico_overlay_status'] = 'hidden';

            }


            if(!$request_modify['payment_iyzico_overlay_status']) {


                $request_modify['payment_iyzico_overlay_status'] = 'bottomLeft';
            }

        }

        if ($method_type == 'edit') {

            if(isset($extra_request->status)) {

                if($extra_request->status == 'success') {

                    $request_modify['payment_iyzico_overlay_token']     = $extra_request->protectedShopId;
                }
            }
        }

        return $request_modify;
    }

    /**
     * @return bool
     */
    private function setIyziWebhookUrlKey()
    {

        $webhookUrl = $this->config->get('webhook_iyzico_webhook_url_key');

        $uniqueUrlId = substr(base64_encode(time() . mt_rand()),15,6);

        if (!$webhookUrl) {
            $this->model_setting_setting->editSetting('webhook_iyzico',array(
                "webhook_iyzico_webhook_url_key" => $uniqueUrlId
            ));
        }

        return true;
    }

}
