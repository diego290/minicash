<?php 
class ModelExtensionPaymentPagseguro extends Model {
  	public function getMethod($address, $total) {
		$this->load->language('extension/payment/pagseguro');
		
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->config->get('payment_pagseguro_geo_zone_id') . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");
		
		if ($this->config->get('payment_pagseguro_total') > 0 && $this->config->get('payment_pagseguro_total') > $total) {
			$status = false;
		} elseif (!$this->config->get('payment_pagseguro_geo_zone_id')) {
			$status = true;
		} elseif ($query->num_rows) {
			$status = true;
		} else {
			$status = false;
		}	
		
		$method_data = array();
		
		//pegar imagem da linguagem
		$title = $this->language->get('text_title');
		/*
		if($this->config->get('payment_pagseguro_name')) {
			$title = $this->config->get('payment_pagseguro_name');
		} else {
			$title = $this->language->get('text_title');	
		}
		*/	
	
		if ($status) {  
      		$method_data = array( 
        		'code'       => 'pagseguro',
        		'title'      => $title,
        		'terms'      => '',
				'sort_order' => $this->config->get('payment_pagseguro_sort_order')
      		);
    	}
   
    	return $method_data;
  	}
	
  	public function getFieldValue($order_info, $custom_fields, $fieldname) {
		
		$value = '';
		
		foreach ($custom_fields as $custom_field) {
			if (isset($order_info['payment_custom_field'][$custom_field['custom_field_id']]) && $custom_field['name'] == $fieldname) {
				$value =  $order_info['payment_custom_field'][$custom_field['custom_field_id']];
			} else if (isset($order_info['shipping_custom_field'][$custom_field['custom_field_id']]) && $custom_field['name'] == $fieldname) {
				$value =  $order_info['shipping_custom_field'][$custom_field['custom_field_id']];
			} else if (isset($order_info['custom_field'][$custom_field['custom_field_id']]) && $custom_field['name'] == $fieldname) {
				$value =  $order_info['custom_field'][$custom_field['custom_field_id']];
			}			
		}
		return $value;
  	}	
}
?>