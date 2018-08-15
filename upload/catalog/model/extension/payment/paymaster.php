<?php


class ModelExtensionPaymentPaymaster extends Model
{
    public function getMethod($address, $total)
    {
        // $currentLanguage = $this->language->get('code');

        $this->load->language('extension/payment/paymaster');

        if ($this->config->get('payment_paymaster_status')) {

            $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->config->get('payment_paymaster_geo_zone_id') . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");

            if ($this->config->get('payment_paymaster_merchant_id') == '') {
                $status = false;
            } elseif (!$this->config->get('payment_paymaster_geo_zone_id')) {
                $status = true;
            } elseif ($query->num_rows) {
                $status = true;
            } else {
                $status = false;
            }
        }
        $method_data = array();

        if ($status) {
            $method_data = array(
                'code' => 'paymaster',
                'title' => $this->language->get('text_title'),
                'terms' => '',
                'sort_order' => $this->config->get('payment_paymaster_sort_order')
            );
        }

        return $method_data;
    }
}

?>
