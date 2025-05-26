<?php

namespace Opencart\Admin\Controller\Extension\iyzico\Payment;

use Opencart\System\Engine\Controller;
use stdClass;

class iyzico extends Controller
{
    private $error = array();
    private $iyzico;
    private $module_version = VERSION;
    private $module_product_name = '2.2';
    private $fields = array(
        array(
            'validateField' => 'error_api_channel',
            'name' => 'payment_iyzico_api_channel',
        ),
        array(
            'validateField' => 'blank',
            'name' => 'payment_iyzico_api_url',
        ),
        array(
            'validateField' => 'error_api_key',
            'name' => 'payment_iyzico_api_key',
        ),
        array(
            'validateField' => 'error_secret_key',
            'name' => 'payment_iyzico_secret_key',
        ),
        array(
            'validateField' => 'error_design',
            'name' => 'payment_iyzico_design',
        ),
        array(
            'validateField' => 'error_language',
            'name' => 'payment_iyzico_language',
        ),
        array(
            'validateField' => 'error_order_status',
            'name' => 'payment_iyzico_order_status',
        ),
        array(
            'validateField' => 'error_cancel_order_status',
            'name' => 'payment_iyzico_order_cancel_status',
        ),
        array(
            'validateField' => 'blank',
            'name' => 'payment_iyzico_status',
        ),
        array(
            'validateField' => 'blank',
            'name' => 'payment_iyzico_sort_order',
        ),
        array(
            'validateField' => 'error_title',
            'name' => 'payment_iyzico_title',
        ),
        array(
            'validateField' => 'blank',
            'name' => 'payment_iyzico_order_status_id',
        ),
        array(
            'validateField' => 'blank',
            'name' => 'payment_iyzico_webhook_text',
        ),
        array(
            'validateField' => 'blank',
            'name' => 'payment_iyzico_overlay_token',
        ),
        array(
            'validateField' => 'blank',
            'name' => 'payment_iyzico_overlay_status',
        ),
        array(
            'validateField' => 'blank',
            'name' => 'webhook_iyzico_webhook_url_key',
        )
    );


    /**
     * iyzico extension: index methods
     * 
     * @return void
     */
    public function index(): void
    {
        # Load Language
        $this->load->language('extension/iyzico/payment/iyzico');

        # Load Settings Model
        $this->load->model('setting/setting');

        # Load User Model
        $this->load->model('user/user');

        # Load Order Status Model
        $this->load->model('localisation/order_status');

        # Load Model
        $this->load->model('extension/iyzico/payment/iyzico');

        # Set Webhook Url
        $this->setWebookUrl();

        # Set Webhook Button
        $this->setWebookButton();

        # Set Webhook Update
        $this->setWebhookUpdate();

        foreach ($this->fields as $key => $field) {
            if (isset($this->error[$field['validateField']]))
                $data[$field['validateField']] = $this->error[$field['validateField']];
            else
                $data[$field['validateField']] = '';

            if (isset($this->request->post[$field['name']]))
                $data[$field['name']] = $this->request->post[$field['name']];
            else
                $data[$field['name']] = $this->config->get($field['name']);

        }

        # Get Title
        $title = $this->language->get('heading_title');

        # Set Title
        $this->document->setTitle($title);

        # Install Status
        $data['install_status'] = $this->installStatus();

        # Set Order Statues
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        # Button Links
        $data['action'] = $this->url->link('extension/iyzico/payment/iyzico.save', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment');

        # Admin Page Options
        $data['heading_title'] = $title;
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $data['locale'] = $this->language->get('code');
        $data['breadcrumbs'] = $this->createBreadcrumbs();

        # iyzico Options
        $data['iyzico_webhook_url'] = HTTP_CATALOG . 'index.php?route=extension/iyzico/payment/iyzico.webhook&key=' . $this->config->get('webhook_iyzico_webhook_url_key');
        $data['module_version'] = $this->module_product_name;
        $data['copy_clipboard_text'] = $this->language->get('copy_clipboard_text');

        $this->response->setOutput($this->load->view('extension/iyzico/payment/iyzico', $data));
    }

    /**
     * iyzico extension: save methods
     * 
     * @return void
     */
    public function save(): void
    {
        # Load Language
        $this->load->language('extension/iyzico/payment/iyzico');

        # Load Model
        $this->load->model('extension/iyzico/payment/iyzico');

        # Check Permission
        if (!$this->user->hasPermission('modify', 'extension/iyzico/payment/iyzico'))
            $this->error['warning'] = $this->language->get('error_permission');

        # Validate
        $this->validate();

        $formRequest = $this->request->post;

        # Check payment_iyzico_api_channel
        if($formRequest['payment_iyzico_api_channel'] == 'sandbox')
            $formRequest['payment_iyzico_api_url'] = 'https://sandbox-api.iyzipay.com';
        else
            $formRequest['payment_iyzico_api_url'] = 'https://api.iyzipay.com';

        $json = [];
        if (!$this->error) {
            $this->load->model('setting/setting');
            $this->model_setting_setting->editSetting('payment_iyzico', $formRequest);
            $json['success'] = $this->language->get('text_success');
        } else {
            $json['error'] = $this->error;
        }

        $data['test_Error'] = "test hata";

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }


    /**
     * iyzico extension: install methods
     * 
     * @return void
     */
    public function install(): void
    {
        # Load Model
        $this->load->model('setting/setting');

        # Load Model
        $this->load->model('extension/iyzico/payment/iyzico');


        foreach ($this->fields as $key => $field) {
            if (isset($this->error[$field['validateField']]))
                $data[$field['validateField']] = $this->error[$field['validateField']];
            else
                $data[$field['validateField']] = '';

            if (isset($this->request->post[$field['name']]))
                $data[$field['name']] = $this->request->post[$field['name']];
            else
                $data[$field['name']] = $this->config->get($field['name']);
        }

        # Set Webhook Url
        $this->setWebhookUpdate();

        # Install 
        $this->model_extension_iyzico_payment_iyzico->install();

        # Install Events
        $this->__registerEvents();

        # Set Settings
        $this->model_setting_setting->editSetting('payment_iyzico', $data);
    }

    /**
     * iyzico extension: uninstall methods
     * 
     * @return void
     */
    public function uninstall(): void
    {
        # Load Model
        $this->load->model('setting/setting');

        # Load Model
        $this->load->model('extension/iyzico/payment/iyzico');

        # Delete Settings
        $this->db->query("DELETE FROM " . DB_PREFIX . "setting WHERE store_id = '0' AND code = 'payment_iyzico_webhook'");

        # Uninstall
        $this->model_extension_iyzico_payment_iyzico->uninstall();

        # Delete Settings
        $this->model_setting_setting->deleteSetting('payment_iyzico');
    }

    /**
     * iyzico extension: validate methods
     * 
     * @return bool
     */
    protected function validate()
    {
        foreach ($this->fields as $field) {
            if ($field['validateField'] != 'blank') {
                if (!$this->request->post[$field['name']]) {
                    $this->error[$field['validateField']] = $this->language->get($field['validateField']);
                }
            }
        }

        return !$this->error;
    }


    /**
     * iyzico extension: setWebookUrl methods
     * 
     * @return bool
     */
    private function setWebookUrl(): bool
    {

        $getWebhookUrlKey = $this->config->get('webhook_iyzico_webhook_url_key');
        $generateUrlId = substr(base64_encode(time() . mt_rand()), 15, 6);

        if (!$getWebhookUrlKey)
            $this->model_setting_setting->editSetting('webhook_iyzico', array("webhook_iyzico_webhook_url_key" => $generateUrlId));

        return true;
    }

    /**
     * iyzico extension: installStatus methods
     * 
     * @return int
     */
    private function installStatus(): int
    {
        $counter = 0;
        foreach ($this->fields as $key => $field) {
            $data[$field['name']] = $this->config->get($field['name']);
            if (!empty($this->config->get($field['name'])))
                $counter++;
        }
        return $counter;
    }

    /**
     * iyzico extension: setWebookButton methods
     * 
     * @return void
     */
    private function setWebookButton(): void
    {
        $webhookActive = $this->config->get('payment_iyzico_webhook_active_button');
        if (empty($webhookActive))
            $this->db->query("INSERT INTO `" . DB_PREFIX . "setting` (`code`, `key`, `value`, `serialized`) VALUES ('payment_iyzico_webhook','payment_iyzico_webhook_active_button', '1' ,'0');");
    }

    /**
     * iyzico extension: setWebhookUpdate methods
     * 
     * @return void
     */
    private function setWebhookUpdate(): void
    {

        $configWebhookStatus = $this->config->get('payment_iyzico_webhook_active_button');
        $configApikey = $this->config->get('payment_iyzico_api_key');
        $configSecretKey = $this->config->get('payment_iyzico_secret_key');

        if (isset($configApikey) && isset($configSecretKey)) {
            if ($configWebhookStatus == 1) {
                $webhookPost = new stdClass();
                $webhookPost->webhookUrl = HTTP_CATALOG . 'index.php?route=extension/payment/iyzico.webhook&key=' . $this->config->get('webhook_iyzico_webhook_url_key');

                $webhookPki = $this->model_extension_iyzico_payment_iyzico->pkiStringGenerate($webhookPost);
                $authorizationData = $this->model_extension_iyzico_payment_iyzico->authorizationGenerate($configApikey, $configSecretKey, $webhookPki);
                $requestResponseWebhook = $this->model_extension_iyzico_payment_iyzico->iyzicoPostWebhookUrlKey($authorizationData, $webhookPost);

                if(isset($requestResponseWebhook->merchantNotificationUpdateStatus)){
                    if ($requestResponseWebhook->merchantNotificationUpdateStatus == 'UPDATED' || $requestResponseWebhook->merchantNotificationUpdateStatus == 'CREATED')
                        $this->model_setting_setting->editSetting('payment_iyzico_webhook', array("payment_iyzico_webhook_active_button" => 2));
                    else
                        $this->model_setting_setting->editSetting('payment_iyzico_webhook', array("payment_iyzico_webhook_active_button" => 3));
                }
            }
        }
    }

    /**
     * iyzico extension: createBreadcrumbs methods
     * 
     * @return array
     */
    protected function createBreadcrumbs(): array
    {
        return array(
            array(
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
            ),
            array(
                'text' => $this->language->get('text_extension'),
                'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
            ),
            array(
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link('extension/iyzico/payment/iyzico', 'user_token=' . $this->session->data['user_token'], true)
            )
        );
    }

    /**
     * iyzico extension: __registerEvents methods
     * 
     * @return void
     */
    protected function __registerEvents(): void
    {

        // events array
        $events = array();

        $events[] = array(
            'code' => "overlay_script",
            'trigger' => "catalog/controller/common/footer/after",
            'action' => "extension/iyzico/payment/iyzico.injectOverlayScript",
            'description' => "Injecting overlay script",
            'status' => 1,
            'sort_order' => 1,
        );

        $events[] = array(
            'code' => "module_notification",
            'trigger' => "admin/controller/common/footer/after",
            'action' => "extension/iyzico/payment/iyzico.injectModuleNotification",
            'description' => "Injecting module notification",
            'status' => 1,
            'sort_order' => 1,
        );

        $this->load->model('setting/event');
        foreach ($events as $event) {
            $this->model_setting_event->addEvent($event);
        }
    }
}
