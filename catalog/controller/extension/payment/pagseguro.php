<?php
class ControllerExtensionPaymentPagseguro extends Controller {
 	public function index() {
	
		$this->language->load('extension/payment/pagseguro');
		
		require_once(DIR_SYSTEM . 'library/PagSeguroLibrary/PagSeguroLibrary.php');
		
		// Altera a codificação padrão da API do PagSeguro (ISO-8859-1)
		PagSeguroConfig::setApplicationCharset('UTF-8');
		
		if($this->config->get('payment_pagseguro_ambiente') == 'sandbox') {
			PagSeguroConfig::setEnvironment('sandbox');
		}
		
		$mb_substr = (function_exists("mb_substr")) ? true : false;
		
    	$this->load->model('checkout/order');
		$this->load->model('extension/payment/pagseguro');
		
	    $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
		
		$data['success_redirect'] = $this->url->link('checkout/success');
		$data['cancel_redirect'] = $this->url->link('common/home');

		if($order_info) {
			
			$paymentRequest = new PagSeguroPaymentRequest();
			
			/* 
			 * Dados do cliente
			 */
			 
			// Ajuste no nome do comprador para o máximo de 50 caracteres exigido pela API
			$customer_name = trim($order_info['payment_firstname']) . ' ' . trim($order_info['payment_lastname']);
			
			if($mb_substr){
				$customer_name = mb_substr($customer_name, 0, 50, 'UTF-8');
			}
			else{
				$customer_name = utf8_encode(substr(utf8_decode($customer_name), 0, 50));
			}
			
			if($order_info['currency_code'] != "BRL"){
				$this->log->write("PagSeguro :: Pedido " . $this->session->data['order_id'] . ". O PagSeguro só aceita moeda BRL (Real) e a loja está configurada para a moeda " . $order_info['currency_code']);
			}
			
			$paymentRequest->setCurrency($order_info['currency_code']);
			$paymentRequest->setSenderName(trim($customer_name));
			$paymentRequest->setSenderEmail(trim($order_info['email'])); // há limitação de 60 caracteres de acordo com a API
			
			$this->load->model('account/custom_field');
			$custom_fields = $this->model_account_custom_field->getCustomFields();
			
			$cpf = '';
			
			if($this->config->get('payment_pagseguro_campo_cpf') != "" && isset($order_info[$this->config->get('payment_pagseguro_campo_cpf')]) && $order_info[$this->config->get('payment_pagseguro_campo_cpf')] != ''){
				$cpf = html_entity_decode($order_info[$this->config->get('payment_pagseguro_campo_cpf')], ENT_QUOTES, 'UTF-8');
			} else if($this->config->get('payment_pagseguro_campo_cpf') != ""){
				$cpf_temp = $this->model_extension_payment_pagseguro->getFieldValue($order_info, $custom_fields, $this->config->get('payment_pagseguro_campo_cpf'));
				
				if($cpf_temp){
					$cpf = html_entity_decode($cpf_temp, ENT_QUOTES, 'UTF-8');
				}
			}
			
			if ($this->isCPF($cpf)) {
				$paymentRequest->addSenderDocument('CPF', preg_replace ("/[^0-9]/", '', $cpf));
			}

			$ddd = '';
			
			if($this->config->get('payment_pagseguro_campo_ddd') != "" && isset($order_info[$this->config->get('payment_pagseguro_campo_ddd')]) && $order_info[$this->config->get('payment_pagseguro_campo_ddd')] != ''){
				$ddd = html_entity_decode($order_info[$this->config->get('payment_pagseguro_campo_ddd')], ENT_QUOTES, 'UTF-8');
			} else if($this->config->get('payment_pagseguro_campo_ddd') != ""){
				$ddd_temp = $this->model_extension_payment_pagseguro->getFieldValue($order_info, $custom_fields, $this->config->get('payment_pagseguro_campo_ddd'));
				
				if($ddd_temp){
					$ddd = html_entity_decode($ddd_temp, ENT_QUOTES, 'UTF-8');
				}
			}
			
			$ddd = preg_replace("/[^0-9]/", '', $ddd);
			$telephone = preg_replace("/[^0-9]/", '', $order_info['telephone']);
			
			if(strlen($ddd) == 2 && strlen($telephone) >= 7 && strlen($telephone) <= 9) {
				$paymentRequest->setSenderPhone($ddd, $telephone);
			} else {
				$telephone = ltrim($telephone, '0');
				
				if(strlen($telephone) >= 9) {
					$ddd = substr($telephone, 0, 2);
					$telephone = substr($telephone, 2, strlen($telephone) - 1);
					
					if(strlen($ddd) == 2 && strlen($telephone) >= 7 && strlen($telephone) <= 9) {
						$paymentRequest->setSenderPhone($ddd, $telephone);
					}					
				}
			}
	  
			/* 
			 * Frete
			 */
			
			$tipo_frete = $this->config->get('payment_pagseguro_tipo_frete');
			
			if($tipo_frete){
				$paymentRequest->setShippingType($tipo_frete);	    	
			}
			else{
				$paymentRequest->setShippingType(3); // 3: Não especificado
			}		
			
			$this->load->model('localisation/zone');
		
			if ($this->cart->hasShipping()) {
				
				$number = '';
				
				if($this->config->get('payment_pagseguro_campo_numero') != "" && isset($order_info['shipping_' . $this->config->get('payment_pagseguro_campo_numero')]) && $order_info['shipping_' . $this->config->get('payment_pagseguro_campo_numero')] != ''){
					$number = html_entity_decode($order_info['shipping_' . $this->config->get('payment_pagseguro_campo_numero')], ENT_QUOTES, 'UTF-8');
				} else if($this->config->get('payment_pagseguro_campo_numero') != ""){
					$numero = $this->model_extension_payment_pagseguro->getFieldValue($order_info, $custom_fields, $this->config->get('payment_pagseguro_campo_numero'));
					
					if($numero){
						$number = html_entity_decode($numero, ENT_QUOTES, 'UTF-8');
					}
				}

				$complement = '';
				
				if($this->config->get('payment_pagseguro_campo_complemento') != "" && isset($order_info['shipping_' . $this->config->get('payment_pagseguro_campo_complemento')]) && $order_info['shipping_' . $this->config->get('payment_pagseguro_campo_complemento')] != ''){
					$complement = html_entity_decode($order_info['shipping_' . $this->config->get('payment_pagseguro_campo_complemento')], ENT_QUOTES, 'UTF-8');
				} else if($this->config->get('payment_pagseguro_campo_complemento') != ""){
					$complemento = $this->model_extension_payment_pagseguro->getFieldValue($order_info, $custom_fields, $this->config->get('payment_pagseguro_campo_complemento'));
					
					if($complemento){
						$complement = html_entity_decode($complemento, ENT_QUOTES, 'UTF-8');
					}
				}			

				$zone = $this->model_localisation_zone->getZone($order_info['shipping_zone_id']);
				
				// Endereço para entrega		
				$paymentRequest->setShippingAddress(  
					Array(  
						'postalCode'=> preg_replace ("/[^0-9]/", '', $order_info['shipping_postcode']),
						'street' 	=> $order_info['shipping_address_1'],     
						'number' 	=> $number,
						'complement'=> $complement,
						'district' 	=> $order_info['shipping_address_2'],         
						'city' 		=> $order_info['shipping_city'],        
						'state' 	=> (isset($zone['code'])) ? $zone['code'] : '',       
						'country' 	=> $order_info['shipping_iso_code_3']
					)  
				);
			}
			else{
				$number = '';
				
				if($this->config->get('payment_pagseguro_campo_numero') != "" && isset($order_info['payment_' . $this->config->get('payment_pagseguro_campo_numero')]) && $order_info['payment_' . $this->config->get('payment_pagseguro_campo_numero')] != ''){
					$number = html_entity_decode($order_info['payment_' . $this->config->get('payment_pagseguro_campo_numero')], ENT_QUOTES, 'UTF-8');
				} else if($this->config->get('payment_pagseguro_campo_numero') != ""){
					$numero = $this->model_extension_payment_pagseguro->getFieldValue($order_info, $custom_fields, $this->config->get('payment_pagseguro_campo_numero'));
					
					if($numero){
						$number = html_entity_decode($numero, ENT_QUOTES, 'UTF-8');
					}
				}

				$complement = '';
				
				if($this->config->get('payment_pagseguro_campo_complemento') != "" && isset($order_info['payment_' . $this->config->get('payment_pagseguro_campo_complemento')]) && $order_info['payment_' . $this->config->get('payment_pagseguro_campo_complemento')] != ''){
					$complement = html_entity_decode($order_info['payment_' . $this->config->get('payment_pagseguro_campo_complemento')], ENT_QUOTES, 'UTF-8');
				} else if($this->config->get('payment_pagseguro_campo_complemento') != ""){
					$complemento = $this->model_extension_payment_pagseguro->getFieldValue($order_info, $custom_fields, $this->config->get('payment_pagseguro_campo_complemento'));
					
					if($complemento){
						$complement = html_entity_decode($complemento, ENT_QUOTES, 'UTF-8');
					}
				}			
				
				$zone = $this->model_localisation_zone->getZone($order_info['payment_zone_id']);
				
				// Endereço para entrega		
				$paymentRequest->setShippingAddress(  
					Array(  
						'postalCode'=> preg_replace ("/[^0-9]/", '', $order_info['payment_postcode']),
						'street' 	=> $order_info['payment_address_1'],     
						'number' 	=> $number,
						'complement'=> $complement,
						'district' 	=> $order_info['payment_address_2'],         
						'city' 		=> $order_info['payment_city'],        
						'state' 	=> (isset($zone['code'])) ? $zone['code'] : '',       
						'country' 	=> $order_info['payment_iso_code_3']
					)  
				);			
			}	   	

			/*
			 * Produtos
			 */
			$this->load->model('tool/upload');
			
			foreach ($this->cart->getProducts() as $product) {
				$options_names = array();
				$model = ($product['model'] != '') ? ' | Modelo: ' . $product['model'] : '';
				
				foreach ($product['option'] as $option) {
					if ($option['type'] != 'file') {
						$value = $option['value'];
					} else {
						$upload_info = $this->model_tool_upload->getUploadByCode($option['value']);

						if ($upload_info) {
							$value = $upload_info['name'];
						} else {
							$value = '';
						}
					}
					$options_names[] = $option['name'] . ": " . (utf8_strlen($value) > 20 ? utf8_substr($value, 0, 20) . '..' : $value);
				}
					
				if(!empty($options_names)){
					$options = " | Opções:: " . implode(', ', $options_names);
				}
				else{
					$options = '';
				}
				
				// limite de 100 caracteres para a descrição do produto
				if($mb_substr){
					$description = mb_substr($product['name'].$model.$options, 0, 100, 'UTF-8');
				}
				else{
					$description = utf8_encode(substr(utf8_decode($product['name'].$model.$options), 0, 100));
				}
				
				$amount = $this->currency->format($product['price'], $order_info['currency_code'], $order_info['currency_value'], false);
				
				$item = Array(
					'id' => $product['product_id'],
					'description' => trim($description),
					'quantity' => $product['quantity'],
					'amount' => $amount
				);
				
				// O frete será calculado pelo PagSeguro.
				if($tipo_frete){
					$peso = $this->getPesoEmGramas($product['weight_class_id'], $product['weight'])/$product['quantity'];
					$item['weight'] = round($peso);
				}

				$paymentRequest->addItem($item);
			}
			
			// Referência do pedido no PagSeguro
			if($this->config->get('payment_pagseguro_posfixo') != ""){
				$paymentRequest->setReference($this->session->data['order_id']."_".$this->config->get('payment_pagseguro_posfixo'));
			}
			else{
				$paymentRequest->setReference($this->session->data['order_id']);
			}	    

			// url para redirecionar o comprador ao finalizar o pagamento
			$paymentRequest->setRedirectUrl($this->url->link('checkout/success'));
			
			// url para receber notificações sobre o status das transações
			$paymentRequest->setNotificationURL($this->url->link('extension/payment/pagseguro/callback', '', true)); 
			
			// obtendo frete, descontos e taxas
			$total = $this->currency->format($order_info['total'] - $this->cart->getSubTotal(), $order_info['currency_code'], $order_info['currency_value'], false);

			if ($total > 0) {
				$item = Array(
					'id' 			=> '-',
					'description' 	=> $this->language->get('text_extra_amount'),
					'quantity' 		=> 1,
					'amount' 		=> $total
				);
				$paymentRequest->addItem($item);			
			} 
			else if($total < 0) {
				$paymentRequest->setExtraAmount($total);
			}  
			
			/* 
			 * Fazendo a chamada para a API de Pagamentos do PagSeguro. 
			 * Se tiver sucesso, retorna o código (url) de requisição para este pagamento.
			 */
			$data['url'] = '';
			$data['api_code'] = '';
			try {
				if($this->config->get('payment_pagseguro_ambiente') == 'sandbox') {
					$token = $this->config->get('payment_pagseguro_token_sandbox');
				} else {
					$token = $this->config->get('payment_pagseguro_token');
				}
				
				$credentials = new PagSeguroAccountCredentials(trim($this->config->get('payment_pagseguro_email')), trim($token));
				$url = $paymentRequest->register($credentials);
				$data['url'] = $url;
				
				$api_code = explode('code=', $url);
				$data['api_code'] = $api_code[1];

			} catch (PagSeguroServiceException $e) {
				$this->log->write('PagSeguro :: ' . $e->getOneLineMessage());
			}

			$data['payment_pagseguro_ambiente'] = $this->config->get('payment_pagseguro_ambiente');
		
			return $this->load->view('extension/payment/pagseguro', $data);
		}
	}
		
	public function confirm() {
		if ($this->session->data['payment_method']['code'] == 'pagseguro') {
			$this->load->model('checkout/order');

			$this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $this->config->get('config_order_status_id'));
		}
	}
			
	public function callback() {
		
		require_once(DIR_SYSTEM . 'library/PagSeguroLibrary/PagSeguroLibrary.php');
		
		if($this->config->get('payment_pagseguro_ambiente') == 'sandbox') {
			PagSeguroConfig::setEnvironment('sandbox');
			$token = $this->config->get('payment_pagseguro_token_sandbox');
		} else {
			$token = $this->config->get('payment_pagseguro_token');
		}		
		
		$code = (isset($_POST['notificationCode']) && trim($_POST['notificationCode']) != "") ? trim($_POST['notificationCode']) : null;
    	$type = (isset($_POST['notificationType']) && trim($_POST['notificationType']) != "") ? trim($_POST['notificationType']) : null;
    	
    	if($code && $type) {

    		$notificationType = new PagSeguroNotificationType($type);
    		$strType = $notificationType->getTypeFromValue();
    		
    		switch($strType) {
				
				case 'TRANSACTION':
					
    				$credentials = new PagSeguroAccountCredentials(trim($this->config->get('payment_pagseguro_email')), trim($token));
										
    		    	try {
			    		$transaction = PagSeguroNotificationService::checkTransaction($credentials, $code);
			    		
			    		$transactionStatus	= $transaction->getStatus();
			    		$id_pedido 			= explode('_', $transaction->getReference());
			    		$paymentMethod 		= $transaction->getPaymentMethod();
			    		$parcelas 			= $transaction->getInstallmentCount();
						$pagSeguroShipping 	= $transaction->getShipping();
						$codigo_transacao	= $transaction->getCode(); 
						  
						$this->load->model('checkout/order');
						$order = $this->model_checkout_order->getOrder($id_pedido[0]);
						
						// Obtendo o tipo de pagamento escolhido
						$payment_code = $paymentMethod->getCode();
    		    		$comment = "Código da transação: " . $codigo_transacao . "\nTipo de pagamento: " . $payment_code->getTypeFromValue()."\nParcelas: ".$parcelas . "\n";
    		    		
    		    		// Obtendo o tipo e o valor do frete
						$pagSeguroShippingType = $pagSeguroShipping->getType(); 
						$valor_frete = $pagSeguroShipping->getCost();
						
						// Valor 1: Pac, valor 2: Sedex, valor 3: não especificado ou cálculo não realizado pelo PagSeguro
    		    		if($pagSeguroShippingType->getValue() != 3){
							$comment .= "\nTipo de frete escolhido no PagSeguro: " . $pagSeguroShippingType->getTypeFromValue() . "\nValor do frete: " . $this->currency->format($valor_frete, $order['currency_code'], $order['currency_value'], false);
    		    		}
	    
					    $update_status_alert = false;
					    if($this->config->get('payment_pagseguro_update_status_alert')){
					    	$update_status_alert = true;
					    }
					    
						switch($transactionStatus->getTypeFromValue()){
				
							case 'WAITING_PAYMENT':
								if($order['order_status_id'] != $this->config->get('payment_pagseguro_order_aguardando_pagamento')){
									$this->model_checkout_order->addOrderHistory($id_pedido[0], $this->config->get('payment_pagseguro_order_aguardando_pagamento'), $comment, $update_status_alert);
								}
								break;
											
							case 'IN_ANALYSIS':
								if($order['order_status_id'] != $this->config->get('payment_pagseguro_order_analise')){
									$this->model_checkout_order->addOrderHistory($id_pedido[0], $this->config->get('payment_pagseguro_order_analise'), $comment, $update_status_alert);
								}
								break;
							
							case 'PAID':
								if($order['order_status_id'] != $this->config->get('payment_pagseguro_order_paga')){
									$this->model_checkout_order->addOrderHistory($id_pedido[0], $this->config->get('payment_pagseguro_order_paga'), $comment, $update_status_alert);
								}
								break;
							case 'AVAILABLE':
								//if($order['order_status_id'] != $this->config->get('payment_pagseguro_order_disponivel')){
								//	$this->model_checkout_order->addOrderHistory($id_pedido[0], $this->config->get('payment_pagseguro_order_disponivel'), '', false);
								//}
								break;
							case 'IN_DISPUTE':
								if($order['order_status_id'] != $this->config->get('payment_pagseguro_order_disputa')){
									$this->model_checkout_order->addOrderHistory($id_pedido[0], $this->config->get('payment_pagseguro_order_disputa'), $comment, $update_status_alert);
								}
								break;
							case 'REFUNDED':
								if($order['order_status_id'] != $this->config->get('payment_pagseguro_order_devolvida')){
									$this->model_checkout_order->addOrderHistory($id_pedido[0], $this->config->get('payment_pagseguro_order_devolvida'), $comment, $update_status_alert);
								}
								break;
							case 'CANCELLED':
								if($order['order_status_id'] != $this->config->get('payment_pagseguro_order_cancelada')){
									$this->model_checkout_order->addOrderHistory($id_pedido[0], $this->config->get('payment_pagseguro_order_cancelada'), $comment, $update_status_alert);
								}
								break;																																
						}						
			    		
			    	} catch (PagSeguroServiceException $e) {
						$this->log->write('PagSeguro :: ' . $e->getOneLineMessage());
			    	}					
					break;
				
				default:
					$this->log->write('PagSeguro :: tipo de notificação desconhecido ['.$notificationType->getValue().']');
			}    		
    	}
    	else{
    		$this->log->write('PagSeguro :: Parâmetros de notificação (notificationCode e notificationType) retornados vazios pelo PagSeguro. 1) Verifique se o link de \'Notificação da Transação no site do PagSeguro\' está configurado corretamente. 2) Clique na transação (no site do PagSeguro) para abrir os detalhes. Clique no link \'Notificações da transação enviadas para o servidor\' para mais detalhes do erro.');
    	}	
	}
	
	private function getPesoEmGramas($weight_class_id, $peso){
		
		if($this->weight->getUnit($weight_class_id) == 'g'){
			return $peso;
		}
		return $peso * 1000;
	}
	
	private function isCPF($str) {
		if (!preg_match('|^(\d{3})\.?(\d{3})\.?(\d{3})\-?(\d{2})$|', $str, $matches)){
			return false;
		}

		array_shift($matches);
		$str = implode('', $matches);

		for ($i=0; $i < 10; $i++){
			if ($str == str_repeat($i, 11)){
				return false;
			}
		}

		for ($t=9; $t < 11; $t++) {
			for ($d=0, $c=0; $c < $t; $c++){
				$d += $str[$c] * ($t + 1 - $c);
			}
		
			$d = ((10 * $d) % 11) % 10;
		
			if ($str[$c] != $d){
				return false;
			}
		}

		return $str;
	}	
}
?>
