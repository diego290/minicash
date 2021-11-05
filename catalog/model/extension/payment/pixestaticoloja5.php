<?php
class ModelExtensionPaymentPixEstaticoLoja5 extends Model {
	public function getMethod($address, $total) {
		$this->load->language('extension/payment/cod');

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->config->get('payment_pixestaticoloja5_geo_zone_id') . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");

		if ((float)$this->config->get('payment_pixestaticoloja5_total') > 0 && (float)$this->config->get('payment_pixestaticoloja5_total') > $total) {
			$status = false;
		} elseif (!$this->config->get('payment_pixestaticoloja5_geo_zone_id')) {
			$status = true;
		} elseif ($query->num_rows) {
			$status = true;
		} else {
			$status = false;
		}

		$method_data = array();

		if($this->config->get('payment_pixestaticoloja5_status')){
			if ($status) {
				$method_data = array(
					'code'       => 'pixestaticoloja5',
					'title'      => html_entity_decode($this->config->get('payment_pixestaticoloja5_nome')),
					//'title'      => '<img src="url da imagem">',//exemplo imagem
					'terms'      => '',
					'sort_order' => $this->config->get('payment_pixestaticoloja5_sort_order')
				);
			}
		}

		return $method_data;
	}
}