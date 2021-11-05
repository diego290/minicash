<?php
class ControllerExtensionModuleJuliocesar757Ef extends Controller {
	public function index() {
		if ($this->config->get('module_juliocesar757_ef_status')) {
			$this->load->language('extension/module/juliocesar757_ef');

			if (isset($this->session->data['shipping_address']['country_id'])) {
				$data['country_id'] = $this->session->data['shipping_address']['country_id'];
			} else {
				$data['country_id'] = $this->config->get('config_country_id');
			}

			$this->load->model('localisation/zone');

			$data['zones'] = $this->model_localisation_zone->getZonesByCountryId($this->config->get('config_country_id'));
			
			$data['country_id'] = $this->config->get('config_country_id');

			if (isset($this->session->data['shipping_address']['zone_id'])) {
				$data['zone_id'] = $this->session->data['shipping_address']['zone_id'];
			} else {
				$data['zone_id'] = $this->config->get('config_zone_id');
			}

			if (isset($this->session->data['shipping_address']['postcode'])) {
				$data['postcode'] = $this->session->data['shipping_address']['postcode'];
			} else {
				$data['postcode'] = '';
			}

			if (isset($this->session->data['shipping_method'])) {
				$data['shipping_method'] = $this->session->data['shipping_method']['code'];
			} else {
				$data['shipping_method'] = '';
			}

			return $this->load->view('extension/module/juliocesar757_ef', $data);
		}
	}

	public function quote() {
		$this->load->language('extension/module/juliocesar757_ef');

		$json = array();
		
		if(!isset($this->request->post['quantity'])) {
			$this->request->post['quantity'] = 1;
		}
		
		if((int)$this->request->post['quantity'] == 0) {
			$json['error']['quantity'] = $this->language->get('error_quantity');
		}

		if ($this->request->post['country_id'] == '') {
			$json['error']['country'] = $this->language->get('error_country');
		}
		
		// adicionado pra calcular frete sem escolher o estado
		$this->load->model('localisation/zone');
		
		$zone_id = $this->getZoneIdByPostcode($this->request->post['postcode']);
		
		if(!$zone_id) {
			if (!isset($this->request->post['zone_id']) || $this->request->post['zone_id'] == '') {
				$json['error']['zone'] = $this->language->get('error_zone');
			} else {
				$zone_id = $this->request->post['zone_id'];
			}			
		}
		// fim		

		$this->load->model('localisation/country');

		$country_info = $this->model_localisation_country->getCountry($this->request->post['country_id']);
		
		$postcode = preg_replace("/[^0-9]/", '', $this->request->post['postcode']);

		if ($country_info && $country_info['postcode_required'] && utf8_strlen($postcode) != 8) {
			$json['error']['postcode'] = $this->language->get('error_postcode');
		}

		if (!$json) {
			
			$temp_cart = $this->getCart();
			
			$this->cart->clear();
					
			if (isset($this->request->post['option'])) {
				$option = array_filter($this->request->post['option']);
			} else {
				$option = array();
			}

			$this->cart->add($this->request->post['product_id'], $this->request->post['quantity'], $option, 0);			
			
			if($this->cart->hasShipping()) {
			
				if ($country_info) {
					$country = $country_info['name'];
					$iso_code_2 = $country_info['iso_code_2'];
					$iso_code_3 = $country_info['iso_code_3'];
					$address_format = $country_info['address_format'];
				} else {
					$country = '';
					$iso_code_2 = '';
					$iso_code_3 = '';
					$address_format = '';
				}			
				
				$this->tax->setShippingAddress($this->request->post['country_id'], $zone_id);			

				$zone_info = $this->model_localisation_zone->getZone($zone_id);

				if ($zone_info) {
					$zone = $zone_info['name'];
					$zone_code = $zone_info['code'];
				} else {
					$zone = '';
					$zone_code = '';
				}

				$this->session->data['shipping_address'] = array(
					'firstname'      => '',
					'lastname'       => '',
					'company'        => '',
					'address_1'      => '',
					'address_2'      => '',
					'postcode'       => $this->request->post['postcode'],
					'city'           => '',
					'zone_id'        => $zone_id,
					'zone'           => $zone,
					'zone_code'      => $zone_code,
					'country_id'     => $this->request->post['country_id'],
					'country'        => $country,
					'iso_code_2'     => $iso_code_2,
					'iso_code_3'     => $iso_code_3,
					'address_format' => $address_format
				);			
				
				$quote_data = array();

				$this->load->model('setting/extension');

				$results = $this->model_setting_extension->getExtensions('shipping');

				foreach ($results as $result) {
					if ($this->config->get('shipping_' . $result['code'] . '_status')) {
						$this->load->model('extension/shipping/' . $result['code']);

						$quote = $this->{'model_extension_shipping_' . $result['code']}->getQuote($this->session->data['shipping_address']);

						if ($quote) {
							$quote_data[$result['code']] = array(
								'title'      => $quote['title'],
								'quote'      => $quote['quote'],
								'sort_order' => $quote['sort_order'],
								'error'      => $quote['error']
							);
						}
					}
				}

				$sort_order = array();

				foreach ($quote_data as $key => $value) {
					$sort_order[$key] = $value['sort_order'];
				}

				array_multisort($sort_order, SORT_ASC, $quote_data);
				
				$this->session->data['shipping_methods'] = $quote_data;
				
				if ($this->session->data['shipping_methods']) {
					$json['shipping_method'] = $this->session->data['shipping_methods'];
				} else {
					$json['error']['warning'] = sprintf($this->language->get('error_no_shipping'), $this->url->link('information/contact'));
				}
			} else {
				$json['error']['no_shipping'] = $this->language->get('text_no_shipping');
			}
			
			$this->cart->clear();
			
			foreach ($temp_cart as $product) {
				$this->cart->add($product['product_id'], $product['quantity'], json_decode($product['option']), $product['recurring_id']);
			}			
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
	
	public function getZoneIdByPostcode($postcode){
		
		$zone_id = 0;
		
		$postcode = preg_replace ("/[^0-9]/", '', $postcode); 
			
		$tabela['ac'] = array(
			'cepini' => '69900000',
			'cepfim' => '69999999' 
		);
		$tabela['al'] = array(
			'cepini' => '57000000',
			'cepfim' => '57999999' 
		);
		$tabela['am'] = array(
			'cepini' => '69000000',
			'cepfim' => '69299999' 
		);
		$tabela['am.2'] = array(
			'cepini' => '69400000',
			'cepfim' => '69899999' 
		);		
		$tabela['ap'] = array(
			'cepini' => '68900000',
			'cepfim' => '68999999' 
		);
		$tabela['ba'] = array(
			'cepini' => '40000000',
			'cepfim' => '48999999 '
		);
		$tabela['ce'] = array(
			'cepini' => '60000000',
			'cepfim' => '63999999' 
		);
		$tabela['df'] = array(
			'cepini' => '70000000',
			'cepfim' => '72799999'
		);
		$tabela['df.2'] = array(
			'cepini' => '73000000',
			'cepfim' => '73699999'
		);				
		$tabela['es'] = array(
			'cepini' => '29000000',
			'cepfim' => '29999999' 
		);
		$tabela['go'] = array(
			'cepini' => '72800000',
			'cepfim' => '72999999' 
		);
		$tabela['go.2'] = array(
			'cepini' => '73700000',
			'cepfim' => '76799999' 
		);		
		$tabela['ma'] = array(
			'cepini' => '65000000',
			'cepfim' => '65999999' 
		);
		$tabela['mg'] = array(
			'cepini' => '30000000',
			'cepfim' => '39999999' 
		);
		$tabela['ms'] = array(
			'cepini' => '79000000',
			'cepfim' => '79999999' 
		);
		$tabela['mt'] = array(
			'cepini' => '78000000',
			'cepfim' => '78899999' 
		);
		$tabela['pa'] = array(
			'cepini' => '66000000',
			'cepfim' => '68899999' 
		);
		$tabela['pb'] = array(
			'cepini' => '58000000',
			'cepfim' => '58999999' 
		);
		$tabela['pe'] = array(
			'cepini' => '50000000',
			'cepfim' => '56999999' 
		);		
		$tabela['pi'] = array(
			'cepini' => '64000000',
			'cepfim' => '64999999' 
		);		
		$tabela['pr'] = array(
			'cepini' => '80000000',
			'cepfim' => '87999999' 
		);		
		$tabela['rj'] = array(
			'cepini' => '20000000',
			'cepfim' => '28999999' 
		);		
		$tabela['rn'] = array(
			'cepini' => '59000000',
			'cepfim' => '59999999' 
		);		
		$tabela['ro'] = array(
			'cepini' => '76800000',
			'cepfim' => '76999999' 
		);		
		$tabela['rr'] = array(
			'cepini' => '69300000',
			'cepfim' => '69399999' 
		);		
		$tabela['rs'] = array(
			'cepini' => '90000000',
			'cepfim' => '99999999' 
		);
		$tabela['sc'] = array(
			'cepini' => '88000000',
			'cepfim' => '89999999' 
		);		
		$tabela['se'] = array(
			'cepini' => '49000000',
			'cepfim' => '49999999' 
		);
		$tabela['sp'] = array(
			'cepini' => '01000000',
			'cepfim' => '19999999'
		);
		$tabela['to'] = array(
			'cepini' => '77000000',
			'cepfim' => '77999999' 
		);
		
		foreach($tabela as $zone_code => $postcode_range){
			
			if((int)$postcode >= (int)$postcode_range['cepini'] && (int)$postcode <= (int)$postcode_range['cepfim']){
				$key = explode('.', $zone_code);
				
				$zone_info = $this->getZoneIdByCode($key[0]);
				
				$zone_id = $zone_info['zone_id'];
				
				break;
			}
		}
		return $zone_id;				
	}
	
	private function getZoneIdByCode($zone_code) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone WHERE code = '" . $this->db->escape(strtoupper($zone_code)) . "' AND country_id = '" . (int)$this->config->get('config_country_id') . "' AND status = '1'");
		
		return $query->row;
	}	
	
	public function getCart() {
		$cart_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "cart WHERE api_id = '" . (isset($this->session->data['api_id']) ? (int)$this->session->data['api_id'] : 0) . "' AND customer_id = '" . (int)$this->customer->getId() . "' AND session_id = '" . $this->db->escape($this->session->getId()) . "'");
		
		$products = array();
		
		foreach ($cart_query->rows as $cart) {
			$products[] = array(
				'product_id' => $cart['product_id'],
				'quantity' => $cart['quantity'],
				'option' => $cart['option'],
				'recurring_id' => $cart['recurring_id']
			);
		}
		
		return $products;
	}	
}