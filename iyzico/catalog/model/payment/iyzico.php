<?php
namespace Opencart\Catalog\Model\Extension\iyzico\Payment;

use Opencart\System\Engine\Model;
use stdClass;

class iyzico extends Model
{

    public function getMethods(array $address = []): array
    {
        $this->load->language('extension/iyzico/payment/iyzico');

        if ($this->cart->hasSubscription()) {
            $status = false;
        } elseif ($this->cart->hasShipping()) {
            $status = true;
        } elseif (!$this->config->get('config_checkout_payment_address')) {
            $status = true;
        } elseif (!$this->config->get('payment_iyzico_geo_zone_id')) {
            $status = true;
        } else {
            $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone_to_geo_zone` WHERE `geo_zone_id` = '" . (int) $this->config->get('payment_iyzico_geo_zone_id') . "' AND `country_id` = '" . (int) $address['country_id'] . "' AND (`zone_id` = '" . (int) $address['zone_id'] . "' OR `zone_id` = '0')");
            if ($query->num_rows) {
                $status = true;
            } else {
                $status = false;
            }
        }

        $method_data = [];

        if ($status) {
            $option_data['iyzico'] = [
                'code' => 'iyzico.iyzico',
                'name' => $this->iyzicoMultipLangTitle($this->config->get('payment_iyzico_title')),
            ];

            $method_data = [
                'code' => 'iyzico',
                'name' => $this->iyzicoMultipLangTitle($this->config->get('payment_iyzico_title')),
                'option' => $option_data,
                'sort_order' => $this->config->get('payment_iyzico_sort_order')
            ];
        }

        return $method_data;
    }

    private function iyzicoMultipLangTitle($title)
    {

        $this->load->language('extension/iyzico/payment/iyzico');
        $language = $this->config->get('payment_iyzico_language');
        $str_language = mb_strtolower($language);

        if (empty($str_language) or $str_language == 'null')
            $title_language = $this->language->get('code');
        else
            $title_language = $str_language;

        if ($title) {
            $parser = explode('|', $title);
            if (is_array($parser) && count($parser)) {
                foreach ($parser as $key => $parse) {
                    $result = explode('=', $parse);
                    if ($title_language == $result[0]) {
                        $new_title = $result[1];
                        break;
                    }
                }
            }
        }

        if (!isset($new_title)) {
            $new_title = $this->language->get('iyzico');
        }

        return $new_title;
    }

    public function authorizationGenerate($pkiString, $apiKey, $secretKey, $randValue): array
    {
        $hashValue = $apiKey . $randValue . $secretKey . $pkiString;
        $hashed = base64_encode(sha1($hashValue, true));

        $authorization = 'IYZWS ' . $apiKey . ':' . $hashed;

        return array(
            'authorization' => $authorization,
            'rand_value' => $randValue
        );
    }

    public function createFormInitializObjectSort($data)
    {

        $form = new stdClass();

        $form->locale = $data->locale;
        $form->conversationId = $data->conversationId;
        $form->price = $data->price;
        $form->basketId = $data->basketId;
        $form->paymentGroup = $data->paymentGroup;

        $form->buyer = new stdClass();
        $form->buyer = $data->buyer;

        $form->shippingAddress = new stdClass();
        $form->shippingAddress = $data->shippingAddress;

        $form->billingAddress = new stdClass();
        $form->billingAddress = $data->billingAddress;

        foreach ($data->basketItems as $key => $item) {
            $form->basketItems[$key] = new stdClass();
            $form->basketItems[$key] = $item;
        }

        $form->callbackUrl = $data->callbackUrl;
        $form->paymentSource = $data->paymentSource;
        $form->currency = $data->currency;
        $form->paidPrice = $data->paidPrice;
        $form->forceThreeDS = $data->forceThreeDS;
        $form->cardUserKey = $data->cardUserKey;

        return $form;
    }

    public function pkiStringGenerate($objectData)
    {
        $pki_value = "[";
        foreach ($objectData as $key => $data) {
            if (is_object($data)) {
                $name = var_export($key, true);
                $name = str_replace("'", "", $name);
                $pki_value .= $name . "=[";
                $end_key = count(get_object_vars($data));
                $count = 0;
                foreach ($data as $key => $value) {
                    $count++;
                    $name = var_export($key, true);
                    $name = str_replace("'", "", $name);
                    $pki_value .= $name . "=" . "" . $value;
                    if ($end_key != $count)
                        $pki_value .= ",";
                }
                $pki_value .= "]";
            } else if (is_array($data)) {
                $name = var_export($key, true);
                $name = str_replace("'", "", $name);
                $pki_value .= $name . "=[";
                $end_key = count($data);
                $count = 0;
                foreach ($data as $key => $result) {
                    $count++;
                    $pki_value .= "[";

                    foreach ($result as $key => $item) {
                        $name = var_export($key, true);
                        $name = str_replace("'", "", $name);

                        $pki_value .= $name . "=" . "" . $item;
                        $reResult = (array) $result;
                        $newResult = $reResult[array_key_last($reResult)];

                        if ($newResult != $item) {
                            $pki_value .= ",";
                        }

                        if ($newResult == $item) {

                            if ($end_key != $count) {
                                $pki_value .= "], ";
                            } else {
                                $pki_value .= "]";
                            }
                        }
                    }
                }

                $reData = (array) $data;
                $newData = $reData[array_key_last($reData)];
                if ($newData == $result)
                    $pki_value .= "]";
            } else {
                $name = var_export($key, true);
                $name = str_replace("'", "", $name);

                $pki_value .= $name . "=" . "" . $data . "";
            }

            $reObjectData = (array) $objectData;
            $newobjectData = $reObjectData[array_key_last($reObjectData)];

            if ($newobjectData != $data)
                $pki_value .= ",";
        }
        $pki_value .= "]";
        return $pki_value;
    }


    public function hashGenerate($pkiString, $apiKey, $secretKey, $randValue)
    {
        $hash = $apiKey . $randValue . $secretKey . $pkiString;
        return base64_encode(sha1($hash, true));

    }

    public function createFormInitializeDetailRequest($json, $authorization_data)
    {
        $url = $this->config->get('payment_iyzico_api_url');
        $url = $url . '/payment/iyzipos/checkoutform/auth/ecom/detail';

        return $this->curlPost($json, $authorization_data, $url);
    }


    public function createFormInitializeRequest($json, $authorizationData)
    {
        $url = $this->config->get('payment_iyzico_api_url');
        $url = $url . '/payment/iyzipos/checkoutform/initialize/auth/ecom';

        return $this->curlPost($json, $authorizationData, $url);
    }


    public function curlPost($json, $authorization_data, $url)
    {

        $phpVersion = phpversion();

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);

        if ($json) {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 0);
        curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_NONE);
        curl_setopt($curl, CURLOPT_TIMEOUT, 150);

        curl_setopt(
            $curl,
            CURLOPT_HTTPHEADER,
            array(
                "Authorization: " . $authorization_data['authorization'],
                "x-iyzi-rnd:" . $authorization_data['rand_value'],
                "opencart-php-version:" . $phpVersion,
                "Content-Type: application/json",
            )
        );

        $result = json_decode(curl_exec($curl));
        curl_close($curl);



        return $result;
    }

    public function insertCardUserKey($customerId, $cardUserKey, $apiKey)
    {
        return $this->db->query("INSERT INTO `" . DB_PREFIX . "iyzico_card` SET
        `customer_id` 	= '" . $this->db->escape($customerId) . "',
        `card_user_key` = '" . $this->db->escape($cardUserKey) . "',
        `api_key` 		= '" . $this->db->escape($apiKey) . "'");
    }

    public function findUserCardKey($customerId, $apiKey): int|string
    {
        $customerId = $this->db->escape($customerId);
        $apiKey = $this->db->escape($apiKey);
        $cardUserKey = (object) $this->db->query("SELECT card_user_key FROM " . DB_PREFIX . "iyzico_card WHERE customer_id = '" . $customerId . "' and api_key = '" . $apiKey . "' ORDER BY iyzico_card_id DESC");

        return count($cardUserKey->rows) ? $cardUserKey->rows[0]['card_user_key'] : "";
    }

    public function insertIyzicoOrder($order)
    {
        return $this->db->query("INSERT INTO `" . DB_PREFIX . "iyzico_order` SET
        `payment_id` = '" . $this->db->escape($order->payment_id) . "',
        `order_id` = '" . $this->db->escape($order->order_id) . "',
        `total_amount` = '" . $this->db->escape($order->total_amount) . "',
        `status` = '" . $this->db->escape($order->status) . "'");
    }

    public function orderUpdateByInstallement($orderId, $paidPrice)
    {

        $orderId = $this->db->escape($orderId);
        $orderInfo = $this->model_checkout_order->getOrder($orderId);

        $this->load->language('extension/iyzico/payment/iyzico');

        $orderTotal = (array) $this->db->query("SELECT * FROM " . DB_PREFIX . "order_total WHERE order_id = '" . $orderId . "' AND code = 'total' ");
        $lastSortValue = $this->db->escape($orderTotal['row']['sort_order'] - 1);

        $exchange_rate = $this->currency->getValue($orderInfo['currency_code']);

        $new_amount = str_replace(',', '', $paidPrice);
        $old_amount = str_replace(',', '', $orderInfo['total'] * $orderInfo['currency_value']);
        $installment_fee_variation = (float) ($new_amount - $old_amount) / $exchange_rate;
        $installment_fee_variation = $this->db->escape($installment_fee_variation);
        $installment_fee_desc = $this->language->get('installement_field_desc');

        $this->db->query("INSERT INTO " . DB_PREFIX . "order_total SET order_id = '" .
            $orderId . "',code = 'iyzico_fee', extension='iyzico',  title = '" . $installment_fee_desc . "', `value` = '" .
            $installment_fee_variation . "', sort_order = '" . $lastSortValue . "'");


        $orderTotalData = (array) $this->db->query("SELECT * FROM " . DB_PREFIX . "order_total WHERE order_id = '" . $orderId . "' AND code != 'total' ");
        $calculateTotal = 0;

        foreach ($orderTotalData['rows'] as $row) {
            $calculateTotal += $row['value'];
        }

        $calculateTotal = $this->db->escape($calculateTotal);

        $this->db->query("UPDATE " . DB_PREFIX . "order_total SET  `value` = '" . $calculateTotal . "' WHERE order_id = '$orderId' AND code = 'total' ");
        $this->db->query("UPDATE `" . DB_PREFIX . "order` SET total = '" . $calculateTotal . "' WHERE order_id = '" . $orderId . "'");

    }

    public function getCategoryName($productId)
    {

        $productId = $this->db->escape($productId);
        $query = $this->db->query("SELECT category_id FROM " . DB_PREFIX . "product_to_category WHERE product_id = '" . $productId . "' LIMIT 1");


        if (count($query->rows)) {
            $categoryId = $this->db->escape($query->rows[0]['category_id']);
            $category = $this->db->query("SELECT name FROM " . DB_PREFIX . "category_description WHERE category_id = '" . $categoryId . "' LIMIT 1");
            if ($category->rows[0]['name'])
                $categoryName = $category->rows[0]['name'];
            else
                $categoryName = 'NO CATEGORIES';
        } else {
            $categoryName = 'NO CATEGORIES';
        }

        $categoryName = html_entity_decode($categoryName);
        $categoryName = trim($categoryName);

        return $categoryName;
    }


    public function getUserCreateDate($userId)
    {

        $userId = $this->db->escape($userId);

        $user_create_date = (object) $this->db->query("SELECT date_added FROM " . DB_PREFIX . "user WHERE user_id = '" . $userId . "'");

        if (count($user_create_date->rows)) {
            return $user_create_date->rows[0]['date_added'];
        }

        return date('Y-m-d H:i:s');
    }



}
