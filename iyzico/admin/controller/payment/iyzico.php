<?php

namespace Opencart\Admin\Controller\Extension\iyzico\Payment;
use stdClass;
class iyzico extends \Opencart\System\Engine\Controller {
	private $error = array();
	private $iyzico;
	private $module_version      = VERSION;
	private $module_product_name = '1.6';


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
            'validateField' => 'error_language',
            'name'          => 'payment_iyzico_language',
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
						'name'          => 'payment_iyzico_webhook_text',
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
            'name'          => 'webhook_iyzico_webhook_url_key',
        )

    );



    public function index(): void
    {
			$this->load->language('extension/iyzico/payment/iyzico');
        $this->load->model('setting/setting');
        $this->load->model('user/user');
        $this->load->model('extension/iyzico/payment/iyzico');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {

            $request            = $this->requestIyzico($this->request->post,'add','');


            $this->model_setting_setting->editSetting('payment_iyzico',$request);


            $this->response->redirect($this->url->link('extension/iyzico/payment/iyzico', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
        }

        $this->setIyziWebhookUrlKey();

				$this->setIyziWebhookUrlActiveButton();

				$this->setWebhookUpdate();

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
        //	$data['api_status']     = $this->getApiStatus($data['install_status']);

        /* Get Order Status */
        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();


        $data['action']         = $this->url->link('extension/iyzico/payment/iyzico', 'user_token=' . $this->session->data['user_token'], true);
				$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment');
				$data['heading_title']  = $this->language->get('heading_title');
        $data['header']         = $this->load->controller('common/header');
        $data['column_left']    = $this->load->controller('common/column_left');
        $data['footer']         = $this->load->controller('common/footer');
        $data['locale']         = $this->language->get('code');
				$data['iyzico_webhook_url_key'] = $this->config->get('webhook_iyzico_webhook_url_key');
			  $data['iyzico_webhook_url']  = HTTP_CATALOG.'index.php?route=extension/iyzico/payment/iyzico|webhook&key=' .$this->config->get('webhook_iyzico_webhook_url_key');
        $data['module_version'] = $this->module_product_name;
				$data['iyzico_webhook_button'] = $this->config->get('payment_iyzico_webhook_active_button');



		    $this->response->setOutput($this->load->view('extension/iyzico/payment/iyzico', $data));


    }


		public function save(): void {


			$this->load->language('extension/iyzico/payment/iyzico');

			$this->load->model('extension/iyzico/payment/iyzico');

			if (!$this->user->hasPermission('modify', 'extension/iyzico/payment/iyzico')) {
					$this->error['warning'] = $this->language->get('error_warning');
			}

			$this->validate();

			if (!$this->error) {
					$this->load->model('setting/setting');

					$this->model_setting_setting->editSetting('payment_iyzico', $this->request->post);

					$data['success'] = $this->language->get('text_success');
			}

			$data['error'] = $this->error;

			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($data));

	}


    public function install()
    {
        $this->load->model('setting/setting');
        $this->load->model('extension/iyzico/payment/iyzico');
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
			 $this->setWebhookUpdate();

        $this->model_extension_iyzico_payment_iyzico->install();
        $this->model_setting_setting->editSetting('payment_iyzico', $data);
    }

    public function uninstall()
    {
        $this->load->model('setting/setting');
        $this->load->model('extension/iyzico/payment/iyzico');
				$this->db->query("DELETE FROM " . DB_PREFIX . "setting WHERE store_id = '0' AND code = 'payment_iyzico_webhook'");
        $this->model_extension_iyzico_payment_iyzico->uninstall();
        $this->model_setting_setting->deleteSetting('payment_iyzico');
    }



		protected function validate() {

			if (!$this->user->hasPermission('modify', 'extension/iyzico/payment/iyzico')) {
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


	 private function installStatus() {

			 $counter = 0;

			 foreach ($this->fields as $key => $field) {

					 $data[$field['name']] = $this->config->get($field['name']);
					 if(!empty($this->config->get($field['name'])))
							 $counter++;
			 }


			 return $counter;
	 }

	 private function setIyziWebhookUrlActiveButton()
	{
			$webhookActive = $this->config->get('payment_iyzico_webhook_active_button');
			if(empty($webhookActive))
			{
				$this->db->query("INSERT INTO `" . DB_PREFIX . "setting` (`code`, `key`, `value`, `serialized`) VALUES ('payment_iyzico_webhook','payment_iyzico_webhook_active_button', '1' ,'0');");

			}


	}

	private function setWebhookUpdate() {

      $webhookActive = $this->config->get('payment_iyzico_webhook_active_button');
      $api_key = $this->config->get('payment_iyzico_api_key');
      $secret_key = $this->config->get('payment_iyzico_secret_key');

      if(isset($api_key) && isset($secret_key))
      {
        if($webhookActive == 1)
        {
          $webhook_active_post = new stdClass();
          $webhook_active_post->webhookUrl      = HTTP_CATALOG.'index.php?route=extension/payment/iyzico/webhook&key=' .$this->config->get('webhook_iyzico_webhook_url_key');

          $webhook_active_pki        = $this->model_extension_iyzico_payment_iyzico->pkiStringGenerate($webhook_active_post);
          $authorization_data        = $this->model_extension_iyzico_payment_iyzico->authorizationGenerate($api_key,$secret_key,$webhook_active_pki);
          $requestResponseWebhook    = $this->model_extension_iyzico_payment_iyzico->iyzicoPostWebhookUrlKey($authorization_data,$webhook_active_post);


          if($requestResponseWebhook->merchantNotificationUpdateStatus == 'UPDATED' || $requestResponseWebhook->merchantNotificationUpdateStatus == 'CREATED')
          {
            $this->model_setting_setting->editSetting('payment_iyzico_webhook',array(
                "payment_iyzico_webhook_active_button" => 2 ));
          }
          else {
            $this->model_setting_setting->editSetting('payment_iyzico_webhook',array(
                "payment_iyzico_webhook_active_button" => 3 ));
          }

        }
      }
    }



}
