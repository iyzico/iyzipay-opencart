<?php

namespace Tests;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use stdClass;

class SampleAdminTest extends OpenCartTest
{
 
    public function testPkiGenerate() {

        $this->load->model('extension/payment/iyzico');

        $api_con_object = new stdClass();
        $api_con_object->locale           = $this->language->get('code');
        $api_con_object->conversationId   = rand(100000,99999999);
        $api_con_object->binNumber        = '454671';

        $result_pki     = $this->model_extension_payment_iyzico->pkiStringGenerate($api_con_object);
        $result_pki     = (string) $result_pki;

        $default_pki    = "[locale=en,conversationId=".$api_con_object->conversationId.",binNumber=454671]";


        $this->assertEquals($result_pki,$default_pki);

    }

    public function testAuthorizationGenerate() {

        $this->load->model('extension/payment/iyzico');

        $api_key                = 'xxxx';
        $secret_key             = 'xxxx';
        $default_pki_string     =  "[locale=en,conversationId=21763770,binNumber=454671]";

        $authorization          = $this->model_extension_payment_iyzico->authorizationGenerate($api_key,$secret_key,$default_pki_string);

        $default_hash           = $api_key.$authorization['rand_value'].$secret_key.$default_pki_string;
        $default_hash           = base64_encode(sha1($default_hash,true));

        $default_authorization  = "IYZWS ".$api_key.":".$default_hash;

        $this->assertEquals($authorization['authorization'],$default_authorization);
    }


    public function testOverlayScript() {

        $authorization_data = array(
            'authorization' => 'test',
            'rand_value'    => '123456'
        );
        $overlay_script_object  = '[locale=en,conversationId=21763770,binNumber=454671]';

        $this->load->model('extension/payment/iyzico');
        $result  = $this->model_extension_payment_iyzico->overlayScript($authorization_data,$overlay_script_object);

        $this->assertEquals($result->status,'failure');
      
    }


    public function testCurlPost() {

        $this->load->model('extension/payment/iyzico');

        $json =  '{"test": "test", "test": "test"}';

        $authorization_data = array(
            'authorization' => 'test',
            'rand_value'    => '123456'
        );
        $url = 'https://sandbox-api.iyzipay.com';

        $result  = $this->model_extension_payment_iyzico->curlPost($json,$authorization_data,$url);

        $this->assertEquals($result->status,'failure');

    }
    /*
    public function testInstallIyzicoExtensionPayment() {

        /*
       $client = new Client();
       $url = "http://localhost/opencart/opencart-test-suite/www/admin/index.php";

   
       $request = $client->get($url,[ 'query' =>
                            [
                            'route' => 'extension/extension/payment/install',
                            'user_token' => 'Tok0c3ykLFbxWVEqjcxqAOsChyOs0CvM',
                            'extension' => 'iyzico'
                            ] 
                    ]);
     

        $request = $client->post($url."?route=common/login", 
                                        ['body' => 
                                            [
                                                'username' => 'int',
                                                'password' => 'aA070849',
                                                'redirect' =>  '',
                                            ]
                                        ]);

       var_dump($request->getBody()->getContents());
       exit;

        $data = json_decode($response->getBody(), true);
        echo $data;

        exit;
  

        //'route=extension/extension/payment/install&user_token=U1UfypL8FtGZhMzDSh3djz7NfnfWndGr&extension=iyzico'

  
        $result = $this->load->controller('extension/payment/iyzico/install');

        $this->assertEquals(NULL, $result);
        
    }

    public function testIndexIyzicoExtensionPayment() {


        $result = $this->load->controller('extension/payment/iyzico/index');

        $this->assertEquals(NULL, $result);


    }
    */

}

