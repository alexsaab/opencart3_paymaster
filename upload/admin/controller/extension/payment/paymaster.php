<?php

/**
 * Платежная система PayMaster - онлайн касса и интеграция различных способов оплаты
 *
 * @cms    Opencart
 * @author     dev@agaxx.ru (Alexey Agafonov)
 * @version    3.1.0
 * @license
 * @copyright  Copyright (c) 2017 Wallet One (http://www.paymaster.ru)
 */

/**
 * Тут объявляем константы
 */
define('paymasterTitle', 'Оплата через метод PayMaster');
define('paymasterTitleDesc', 'PayMaster решение номер один - платежный агрегатор.');
define('paymasterDesc', 'Оплата через агрегатор платежей "PayMaster"');
define('titleEdit', 'Редактирование');
define('textPayment', 'Оплата');


/**
 * Class ControllerExtensionPaymentPayMaster
 */
class ControllerExtensionPaymentPaymaster extends Controller
{

    private $error = array();

    /**
     * Declaration of class client
     *
     * @var object
     */


    /**
     * Функция конструктор
     * ControllerExtensionPaymentPaymaster constructor.
     * @param $registry
     */
    public function __construct($registry)
    {
        parent::__construct($registry);
        $currentLanguage = $this->language->get('code');
    }

    /**
     * Установщик, тут не нужен, но делаем для порадка
     * @return [type] тут не нужен, но делаем для порадка
     */
    public function install()
    {


    }

    /**
     * Выводит меню с настройками
     * Для администрирования модуляƒ
     */
    public function index()
    {
        $this->load->language('extension/payment/paymaster');
        $this->document->setTitle = $this->language->get('heading_title');
        $this->document->setTitle(paymasterTitle);

        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && ($this->validate())) {
            $this->model_setting_setting->editSetting('payment_paymaster', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
        }


        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        //a version php check
        if (PHP_VERSION_ID < 50400) {
            $data['errorPhpVersion'] = paymasterErrorPhpVersion;
        }

        //text for headings
        $data['headingTitle'] = paymasterTitle;
        $data['heading_title'] = paymasterTitle;
        $data['titleEdit'] = titleEdit;


        $data['heading_title'] = $this->language->get('heading_title');
        $data['text_enabled'] = $this->language->get('text_enabled');
        $data['text_disabled'] = $this->language->get('text_disabled');
        $data['text_all_zones'] = $this->language->get('text_all_zones');
        $data['text_card'] = $this->language->get('text_card');

        $data['entry_merchant_id'] = $this->language->get('entry_merchant_id');
        $data['entry_secret_key'] = $this->language->get('entry_secret_key');
        $data['entry_hash_alg'] = $this->language->get('entry_hash_alg');

        $data['entry_order_status'] = $this->language->get('entry_order_status');
        $data['entry_geo_zone'] = $this->language->get('entry_geo_zone');
        $data['entry_status'] = $this->language->get('entry_status');
        $data['entry_sort_order'] = $this->language->get('entry_sort_order');
        $data['entry_tax'] = $this->language->get('entry_tax');
        $data['entry_log'] = $this->language->get('entry_log');
        $data['entry_class_tax'] = $this->language->get('entry_class_tax');
        $data['entry_text_tax'] = $this->language->get('entry_text_tax');

        $data['button_save'] = $this->language->get('button_save');
        $data['button_cancel'] = $this->language->get('button_cancel');
        $data['button_add'] = $this->language->get('button_add');

        $data['tab_general'] = $this->language->get('tab_general');

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->error['merchant_id'])) {
            $data['error_merchant_id'] = $this->error['merchant_id'];
        } else {
            $data['error_merchant_id'] = '';
        }

        if (isset($this->error['secret_key'])) {
            $data['error_secret_key'] = $this->error['secret_key'];
        } else {
            $data['error_secret_key'] = '';
        }

        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );
        $data['breadcrumbs'][] = array(
            'text' => textPayment,
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
        );
        $data['breadcrumbs'][] = array(
            'text' => paymasterTitle,
            'href' => $this->url->link('extension/payment/paymaster', 'user_token=' . $this->session->data['user_token'], true)
        );


        $data['action'] = $this->url->link('extension/payment/paymaster', 'user_token=' . $this->session->data['user_token'], true);

        $data['cancel'] = $this->url->link('extension/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);


        if (isset($this->request->post['payment_paymaster_merchant_id'])) {
            $data['payment_paymaster_merchant_id'] = $this->request->post['payment_paymaster_merchant_id'];
        } else {
            $data['payment_paymaster_merchant_id'] = $this->config->get('payment_paymaster_merchant_id');
        }

        if (isset($this->request->post['payment_paymaster_secret_key'])) {
            $data['payment_paymaster_secret_key'] = $this->request->post['payment_paymaster_secret_key'];
        } else {
            $data['payment_paymaster_secret_key'] = $this->config->get('payment_paymaster_secret_key');
        }

        if (isset($this->request->post['payment_paymaster_hash_alg'])) {
            $data['payment_paymaster_hash_alg'] = $this->request->post['payment_paymaster_hash_alg'];
        } else {
            $data['payment_paymaster_hash_alg'] = $this->config->get('payment_paymaster_hash_alg');
        }

        if (isset($this->request->post['payment_paymaster_order_status_id'])) {
            $data['payment_paymaster_order_status_id'] = $this->request->post['payment_paymaster_order_status_id'];
        } else {
            $data['payment_paymaster_order_status_id'] = $this->config->get('payment_paymaster_order_status_id');
        }

        $this->load->model('localisation/order_status');


        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        if (isset($this->request->post['payment_paymaster_geo_zone_id'])) {
            $data['payment_paymaster_geo_zone_id'] = $this->request->post['payment_paymaster_geo_zone_id'];
        } else {
            $data['payment_paymaster_geo_zone_id'] = $this->config->get('payment_paymaster_geo_zone_id');
        }

        if (isset($this->request->post['payment_paymaster_log'])) {
            $data['payment_paymaster_log'] = $this->request->post['payment_paymaster_log'];
        } else {
            $data['payment_paymaster_log'] = $this->config->get('payment_paymaster_log');
        }

        if (isset($this->request->post['payment_paymaster_classes'])) {
            $data['payment_paymaster_classes'] = $this->request->post['payment_paymaster_classes'];
        } elseif ($this->config->get('payment_paymaster_classes')) {
            $data['payment_paymaster_classes'] = $this->config->get('payment_paymaster_classes');
        } else {
            $data['payment_paymaster_classes'] = array(
                array(
                    'payment_paymaster_nalog' => 1,
                    'payment_paymaster_tax_rule' => 1
                )
            );
        }

        $data['tax_rules'] = $this->get_tax_rules();

        $this->load->model('localisation/tax_class');
        $data['tax_classes'] = $this->model_localisation_tax_class->getTaxClasses();

        $this->load->model('localisation/geo_zone');

        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

        if (isset($this->request->post['payment_paymaster_status'])) {
            $data['payment_paymaster_status'] = $this->request->post['payment_paymaster_status'];
        } else {
            $data['payment_paymaster_status'] = $this->config->get('payment_paymaster_status');
        }

        if (isset($this->request->post['payment_paymaster_sort_order'])) {
            $data['payment_paymaster_sort_order'] = $this->request->post['payment_paymaster_sort_order'];
        } else {
            $data['payment_paymaster_sort_order'] = $this->config->get('payment_paymaster_sort_order');
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        // вывод вида
        $this->response->setOutput($this->load->view('extension/payment/paymaster', $data));


    }


    /**
     * Получение налоговых ставок
     * @return [type] [description]
     */
    private function get_tax_rules()
    {
        return array(
            array(
                'id' => 0,
                'name' => '18'
            ),
            array(
                'id' => 1,
                'name' => '10'
            ),
            array(
                'id' => 2,
                'name' => '0'
            ),
            array(
                'id' => 3,
                'name' => 'no'
            ),
            array(
                'id' => 4,
                'name' => '18/118'
            ),
            array(
                'id' => 5,
                'name' => '10/110'
            )
        );
    }


    /**
     * Валидация формы
     * @return bool
     */
    private function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/payment/paymaster')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (!$this->request->post['payment_paymaster_merchant_id']) {
            $this->error['merchant_id'] = $this->language->get('error_merchant_id');
        }

        if (!$this->request->post['payment_paymaster_secret_key']) {
            $this->error['secret_key'] = $this->language->get('error_secret_key');
        }

        return !$this->error;
    }


}

