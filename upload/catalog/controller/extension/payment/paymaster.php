<?php

class ControllerExtensionPaymentPaymaster extends Controller
{
    const STATUS_TAX_OFF = 'no_vat';
    const MAX_POS_IN_CHECK = 100;
    const BEGIN_POS_IN_CHECK = 0;

    /**
     * Формируем полностью
     * @return [type] [description]
     */
    public function index()
    {
        $data['button_confirm'] = $this->language->get('button_confirm');
        $data['button_back'] = $this->language->get('button_back');
        $data['action'] = 'https://paymaster.ru/Payment/Init';

        $this->load->language('extension/payment/paymaster');

        $this->load->model('extension/payment/paymaster');

        $order = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        $order_products = $this->cart->getProducts();

        //Продукты в заказе
        // Сумма для продуктов
        $product_amount = 0;

        if ($order_products) {
            foreach ($order_products as $order_product) {
                $data['order_check'][] = array(
                    'name' => $order_product['name'],
                    'price' => $order_product['price'],
                    'quantity' => $order_product['quantity'],
                    'tax' => $this->config->get('tax_status') ? $this->getTax($order_product['product_id']) : self::STATUS_TAX_OFF,
                );

                $product_amount += $order_product['price'] * $order_product['quantity'];

            }
        }

        // Доставка товара
        // Так как мы не нашли как получить сумму доставки из заказа пришлось ее вычислять из закака

        $data['order_check'][] = array(
            'name' => $order['shipping_method'],
            'price' => $order['total'] - $product_amount,
            'quantity' => 1,
            'tax' => self::STATUS_TAX_OFF,
        );

        if (count($data['order_check']) > self::MAX_POS_IN_CHECK) {
            $data['error_warning'] = $this->language->get('error_max_pos');
        }

        $data['pos'] = self::BEGIN_POS_IN_CHECK;

        $this->load->model('checkout/order');

        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        $data['merchant_id'] = $this->config->get('payment_paymaster_merchant_id');
        $data['email'] = $order_info['email'];
        $data['order_id'] = $this->session->data['order_id'];
        $data['amount'] = number_format($order_info['total'], 2, ".", "");
        $data['lmi_currency'] = $order_info['currency_code'];
        $secret_key = htmlspecialchars_decode($this->config->get('payment_paymaster_secret_key'));

        // Формируем подпись
        $hash_alg = $this->config->get('payment_paymaster_hash_alg');
        $plain_sign = $data['merchant_id'] . $data['order_id'] . $data['amount'] . $data['lmi_currency'] . $secret_key;
        $data['sign'] = base64_encode(hash($hash_alg, $plain_sign, true));

        // Работаем с URL
        $data['payment_notification_url'] = $this->url->link('extension/payment/paymaster/callback', '', true);
        $data['success_url'] = $this->url->link('extension/payment/paymaster/success', '', true);
        $data['fail_url'] = $this->url->link('extension/payment/paymaster/fail', '', true);

        $data['description'] = $this->language->get('text_order') . ' ' . $data['order_id'];
        $this->createLog(__METHOD__, $data);

        return $this->load->view('extension/payment/paymaster', $data);
    }


    /**
     * Логироание не мое
     * @param  [type] $method метод (страница)
     * @param  array $data данные (для дампа)
     * @param  string $text текст (для описания)
     * @return [type]         [description]
     */
    public function createLog($method, $data = array(), $text = '')
    {
        if ($this->config->get('payment_paymaster_log')) {
            if ($method == 'index') {
                $order_check = array();
                foreach ($data['order_check'] as $check) {
                    $order_check = array(
                        'LMI_SHOPPINGCART.ITEMS[' . $check['pos'] . '].NAME' => $check['name'],
                        'LMI_SHOPPINGCART.ITEMS[' . $check['pos'] . '].QTY' => $check['quantity'],
                        'LMI_SHOPPINGCART.ITEMS[' . $check['pos'] . '].PRICE' => $check['price'],
                        'LMI_SHOPPINGCART.ITEMS[' . $check['pos'] . '].TAX' => $check['tax'],
                    );
                }

                $data = array_merge(array(
                    'LMI_MERCHANT_ID' => $data['merchant_id'],
                    'LMI_PAYMENT_AMOUNT' => $data['amount'],
                    'LMI_CURRENCY' => $data['lmi_currency'],
                    'LMI_PAYMENT_NO' => $data['order_id'],
                    'LMI_PAYMENT_DESC' => $data['description'],
                    'SIGN' => $data['sign'],
                ), $order_check);
            }

            $this->log->write('---------PAYMASTER START LOG---------');
            $this->log->write('---Вызываемый метод: ' . $method . '---');
            $this->log->write('---Описание: ' . $text . '---');
            $this->log->write($data);
            $this->log->write('---------PAYMASTER END LOG---------');
        }
        return true;
    }


    /**
     * Неуспешный платеж сообщение пользователю
     * @return [type] [description]
     */
    public function fail()
    {
        $this->createLog(__METHOD__, '', 'Платеж не выполнен');
        $this->response->redirect($this->url->link('checkout/checkout', '', 'SSL'));
        return true;
    }


    /**
     * Успешный платеж сообщение пользователю
     * @return [type] [description]
     */
    public function success()
    {

        $request = $this->request->post;

        if (empty($request)) {
            $request = $this->request->get;
        }

        $order_id = $request["LMI_PAYMENT_NO"];
        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($order_id);

        if ((int)$order_info["order_status_id"] == (int)$this->config->get('payment_paymaster_order_status_id')) {
            $this->model_checkout_order->addOrderHistory($order_id, $this->config->get('payment_paymaster_order_status_id'), 'PayMaster', true);
            $this->createLog(__METHOD__, $request, 'Платеж успешно завершен');

            // Сброс всех cookies и сессий
            unset($this->session->data['shipping_method']);
            unset($this->session->data['shipping_methods']);
            unset($this->session->data['payment_method']);
            unset($this->session->data['payment_methods']);
            unset($this->session->data['guest']);
            unset($this->session->data['comment']);
            unset($this->session->data['order_id']);
            unset($this->session->data['coupon']);
            unset($this->session->data['reward']);
            unset($this->session->data['voucher']);
            unset($this->session->data['vouchers']);
            unset($this->session->data['totals']);

            // очищаем карточку
            $this->cart->clear();

            $this->response->redirect($this->url->link('checkout/success', '', 'SSL'));

            return true;
        }

        return false;
    }

    /**
     * Callback № 1 где проверяется подпись
     * @return function [description]
     */
    public function callback()
    {
        if (isset($this->request->post)) {
            $this->createLog(__METHOD__, $this->request->post, 'Данные с сервиса PAYMASTER');
        }

        $order_id = $this->request->post["LMI_PAYMENT_NO"];
        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($order_id);

        $amount = number_format($order_info['total'], 2, '.', '');
        $currency = $order_info['currency_code'];
        
        $merchant_id = $this->config->get('payment_paymaster_merchant_id');

        // Если у нас есть предварительные запрос
        if (isset($this->request->post['LMI_PREREQUEST'])) {
            if ($this->request->post['LMI_MERCHANT_ID'] == $merchant_id && $this->request->post['LMI_PAYMENT_AMOUNT'] == $amount) {
                echo 'YES';
                exit;
            } else {
                echo 'FAIL';
                exit;
            }
        }

        // Проверка на совпадение ID мерчанта если нет уходим
        if ($merchant_id != $this->request->post['LMI_MERCHANT_ID']) {
            echo 'FAIL';
            exit;
        }

        // Проверка на валюту и сумму платежа
        if (($currency != $this->request->post['LMI_PAID_CURRENCY']) && ($amount != $this->request->post['LMI_PAYMENT_AMOUNT'])){
            echo 'FAIL';
            exit;
        }

        // Самая важная проверка HASH 
        if (isset($this->request->post['LMI_HASH'])) {
            $lmi_hash = $this->request->post['LMI_HASH'];
            $lmi_sign = $this->request->post['SIGN'];
            $hash = $this->getHash($this->request->post);
            $sign = $this->getSign($this->request->post);
            if (($lmi_hash == $hash) && ($lmi_sign == $sign)) {
                if ($order_info['order_status_id'] == 0) {
                    $this->model_checkout_order->addOrderHistory($order_id, $this->config->get('payment_paymaster_order_status_id'), 'Оплачено через PayMaster');
                    exit;
                }
                if ($order_info['order_status_id'] != $this->config->get('payment_paymaster_order_status_id')) {
                    $this->model_checkout_order->addOrderHistory($order_id, $this->config->get('payment_paymaster_order_status_id'), 'PayMaster', true);
                }
            } else {
                $this->log->write("PayMaster sign or hash is not correct!");
            }
        }

    }

    /**
     * Получение подписи для дополнительной безопасности
     * @param  [type] $request фактически post запрос
     * @return [type]          [description]
     */
    public function getSign($request)
    {
        $hash_alg = $this->config->get('payment_paymaster_hash_alg');
        $secret_key = htmlspecialchars_decode($this->config->get('payment_paymaster_secret_key'));
        $plain_sign = $request['LMI_MERCHANT_ID'] . $request['LMI_PAYMENT_NO'] . $request['LMI_PAID_AMOUNT'] . $request['LMI_PAID_CURRENCY'] . $secret_key;
        return base64_encode(hash($hash_alg, $plain_sign, true));
    }

    /**
     * Получаем HASH
     * @param  [type] $request фактически post запрос
     * @return [type]          [description]
     */
    public function getHash($request)
    {
        $hash_alg = $this->config->get('payment_paymaster_hash_alg');
        $SECRET = htmlspecialchars_decode($this->config->get('payment_paymaster_secret_key'));
        // Получаем ID продавца не из POST запроса, а из модуля (исключаем, тем самым его подмену)
        $LMI_MERCHANT_ID = $request['LMI_MERCHANT_ID'];
        //Получили номер заказа очень нам он нужен, смотрите ниже, что мы с ним будем вытворять
        $LMI_PAYMENT_NO = $request['LMI_PAYMENT_NO'];
        //Номер платежа в системе PayMaster
        $LMI_SYS_PAYMENT_ID = $request['LMI_SYS_PAYMENT_ID'];
        //Дата платежа
        $LMI_SYS_PAYMENT_DATE = $request['LMI_SYS_PAYMENT_DATE'];
        $LMI_PAYMENT_AMOUNT = $request['LMI_PAYMENT_AMOUNT'];
        //Теперь получаем валюту заказа, то что была в заказе
        $LMI_CURRENCY =   $request['LMI_CURRENCY'];
        $LMI_PAID_AMOUNT = $request['LMI_PAID_AMOUNT'];
        $LMI_PAID_CURRENCY = $request['LMI_PAID_CURRENCY'];
        $LMI_PAYMENT_SYSTEM = $request['LMI_PAYMENT_SYSTEM'];
        $LMI_SIM_MODE = $request['LMI_SIM_MODE'];
        $string = $LMI_MERCHANT_ID . ";" . $LMI_PAYMENT_NO . ";" . $LMI_SYS_PAYMENT_ID . ";" . $LMI_SYS_PAYMENT_DATE . ";" . $LMI_PAYMENT_AMOUNT . ";" . $LMI_CURRENCY . ";" . $LMI_PAID_AMOUNT . ";" . $LMI_PAID_CURRENCY . ";" . $LMI_PAYMENT_SYSTEM . ";" . $LMI_SIM_MODE . ";" . $SECRET;
        $hash = base64_encode(hash($hash_alg, $string, true));
        return $hash;
    }

    /**
     * Получение налоговой информации по продукту
     * @param  [type] $product_id  id продукта
     * @return [type]             [description]
     */
    protected function getTax($product_id)
    {
        $this->load->model('catalog/product');
        $product_info = $this->model_catalog_product->getProduct($product_id);
        $tax_rule_id = 3;

        foreach ($this->config->get('payment_paymaster_classes') as $i => $tax_rule) {
            if ($tax_rule['paymaster_nalog'] == $product_info['tax_class_id']) {
                $tax_rule_id = $tax_rule['paymaster_tax_rule'];
            }
        }

        $tax_rules = array(
            array(
                'id' => 0,
                'name' => 'vat18',
            ),
            array(
                'id' => 1,
                'name' => 'vat10',
            ),
            array(
                'id' => 2,
                'name' => 'vat0',
            ),
            array(
                'id' => 3,
                'name' => 'no_vat',
            ),
            array(
                'id' => 4,
                'name' => 'vat118',
            ),
            array(
                'id' => 5,
                'name' => 'vat110',
            ),
        );
        return $tax_rules[$tax_rule_id]['name'];
    }

    /**
     * Моя любимая функция Logger
     * @param  [type] $var  [description]
     * @param  string $text [description]
     * @return [type]       [description]
     */
    public function logger($var, $text = '')
    {
        // Название файла
        $loggerFile = __DIR__ . '/logger.log';
        if (is_object($var) || is_array($var)) {
            $var = (string)print_r($var, true);
        } else {
            $var = (string)$var;
        }
        $string = date("Y-m-d H:i:s") . " - " . $text . ' - ' . $var . "\n";
        file_put_contents($loggerFile, $string, FILE_APPEND);
    }
}
