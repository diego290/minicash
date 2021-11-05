<?php
class ControllerExtensionModuleCart5 extends Controller {
	
	public function logado(){
		$json = array();
		$json['logado'] = ($this->customer->isLogged())?true:false;
		$json['valido'] = $this->sessao_possui_email();
		$json['id'] = $this->customer->getId();
		echo json_encode($json);
	}
	
	public function sessao_possui_email(){
		$sessao = $this->session->getId();
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "cart5_abandonos` WHERE sessao = '".$this->db->escape($sessao)."'");
		if($query->num_rows == 0){
			return false;
		}elseif(!empty($query->row['email'])){
			return true;
		}else{
			return false;
		}
	}
	
	public function form(){
		if(!$this->sessao_possui_email()){
			$data = array();
			$data['link_home'] = $this->url->link('common/home','',true);
			$this->response->setOutput($this->load->view('common/cart5_informar_email', $data));
		}else{
			echo 'Ola, o seu carrinho j&aacute; encontra-se salvos :)';
		}
	}
	
	public function salvar(){
		$json = array();
		$json['erro'] = false;
		if(isset($_POST['email']) && !empty($_POST['email'])){
			$sessao = $this->session->getId();
			$this->db->query("UPDATE `" . DB_PREFIX . "cart5_abandonos` SET email = '".$this->db->escape($_POST['email'])."' WHERE sessao = '".$this->db->escape($sessao)."'");
		}
		$json['log'] = 'Dados do carrinho salvos com sucesso!';
		echo json_encode($json);
	}
	
	public function cron(){
		$this->load->model('checkout/order');

		$minimo = (float)$this->config->get('module_cart5_minimo');
		$sql = "SELECT * FROM `" . DB_PREFIX . "cart5_abandonos` WHERE email != '' AND produtos != '' AND enviado = 'NAO' AND total >= ".$minimo."";
		$order_query = $this->db->query($sql);		
		
        echo 'Consultar registros acima de '.$minimo.' após '.(int)$this->config->get('module_cart5_tempo').' min abandonado(s) ('.$this->data_agora_mysql().')!<br><br>'; 
        
        $tempo = (int)$this->config->get('module_cart5_tempo');
        
		//se possui pedidos
		if($order_query->num_rows > 0){
			//lista os abandonos
			foreach($order_query->rows AS $k => $v){
				//gera a tabela de produtos
				$produtos = $this->produtos($v['produtos']);
                
				$data_p = $v['data'];
				$data_h = $this->data_agora_mysql();
				$tempo_de_abandono = (int)(abs(strtotime($data_h)-strtotime($data_p)) / 60);

				//ver se o carrinho foi abandonado
				if($tempo_de_abandono>=$tempo){
					
					//envia o e-mail
					$this->db->query("UPDATE `" . DB_PREFIX . "cart5_abandonos` SET enviado = 'SIM' WHERE id = '".$this->db->escape($v['id'])."'");
					//monta o email
					$dados = array();
					$dados['nomecliente'] = $v['email'];
					$dados['comprar'] = $this->url->link('extension/module/cart5/recupera', 'hash=' . $v['hash_acesso'], 'SSL');
					$dados['produtos'] = $produtos;
					$html = $this->replace_html($dados);
					$assunto = $this->replace_titulo($dados);
					$this->enviar_email($v['email'],$assunto,$html);
					echo $v['id'].' - Enviado ('.$tempo_de_abandono.' > '.$tempo.') ['.$data_p.']!<br>';
					
				}else{
					echo $v['id'].' - Em espera ('.$tempo_de_abandono.' < '.$tempo.') ['.$data_p.']!<br>';
				}
			}
		}else{
			echo 'Nenhum carrinho abandonado!';
		}
	}
	
	public function recupera() {
		$this->load->model('account/customer');
		if(isset($_GET['hash'])){
			$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "cart5_abandonos` WHERE hash_acesso = '".$this->db->escape($_GET['hash'])."' AND produtos != '' LIMIT 1");
			if($query->num_rows > 0 && isset($query->row['sessao'])){
				//define a session de origem
				$this->session->data['session_cart5'] = $query->row['sessao'];
				
				//limpa o carrinho 
				$this->cart->clear();
				
				//marca como clicado 
				$this->db->query("UPDATE `" . DB_PREFIX . "cart5_abandonos` SET clicado = 'SIM' WHERE hash_acesso = '".$this->db->escape($_GET['hash'])."' LIMIT 1");
				
				//se tem id do cliente loga o mesmo
				if($query->row['id_cliente'] > 0){
					$cliente = $this->model_account_customer->getCustomer($query->row['id_cliente']);
					$this->customer->login($cliente['email'], '', true);
				}
				
				//add produtos ao carrinho novamente
				$produtos = json_decode($query->row['produtos'],true);
				if(count($produtos) > 0){
					foreach($produtos as $k=>$cart){
						$opcoes = isset($cart['option'])?$cart['option']:array();
						$rec = isset($cart['recurring_id'])?$cart['recurring_id']:null;
						$this->cart->add($cart['product_id'], $cart['quantity'], $opcoes, $rec);
					}
				}

				$this->response->redirect($this->url->link('checkout/cart','','SSL'));
			}else{
				$this->response->redirect($this->url->link('common/home','','SSL'));
			}
		}else{
			$this->response->redirect($this->url->link('common/home','','SSL'));
		}
		
	}
	
	public function data_agora_mysql(){
        $data = $this->db->query("SELECT NOW() AS data;");
		if(isset($data->row['data'])){
			return $data->row['data'];
		}else{
			return date('Y-m-d H:i:s');
		}
    }
	
    public function zone1(){
        print_r($this->db->query("SELECT @@global.time_zone;"));
    }
    
    public function zone2(){
        print_r($this->db->query("SELECT NOW();"));
    }
	
    public function produtos($produtos){
		$produtos = json_decode($produtos,true);
        $total = 0;
		$html = '<table style="width:90%;border:1px solid #CCC" cellspacing="0" cellpadding="0" border="1">';
		$html .= '<tr style="background:#CCC"><td style="border:1px solid #CCC">Produto</td><td style="width:10%;border:1px solid #CCC">Qtd</td><td style="width:15%;border:1px solid #CCC">Valor</td><td style="width:15%;border:1px solid #CCC">Total</td></tr>';
		foreach ($produtos as $product) {
            $total_produto = $product['price']*$product['quantity'];
			$html .= '<tr><td>'.$product['name'].'</td><td>'.$product['quantity'].'</td><td>'.$this->formatar($product['price']).'</td><td>'.$this->formatar($total_produto).'</td></tr>';
            $total += $total_produto;
		}
        
		/*
        $desconto_ativo = $this->config->get('total_descontocart5_status');
        $desconto = $this->config->get('total_descontocart5_taxa');
        $minimo = $this->config->get('total_descontocart5_minimo');
        $desconto_total = 0;
        if($desconto_ativo && $total >= $minimo && $desconto > 0){
            $desconto_total = ($total/100)*$desconto;
            $html .= '<tr><td></td><td></td><td>Desconto ('.$desconto.'%):</td><td>-'.$this->formatar($desconto_total).'</td></tr>';
        }
        $html .= '<tr><td></td><td></td><td>Total:</td><td>'.$this->formatar($total-$desconto_total).'</td></tr>';
		*/
		
		$html .= '</table>';
		
		$desconto_ativo = $this->config->get('total_descontocart5_status');
		$desconto = (int)$this->config->get('total_descontocart5_taxa');
        $minimo = $this->config->get('total_descontocart5_minimo');
		if($desconto_ativo && $total >= $minimo && $desconto > 0){
			$html .= '<br><b>Aproveite e ganhe um desconto de '.$desconto.'% para este pedido!</b>';
		}
		
		return $html;
	}
	
    public function formatar($valor){
		if(version_compare(VERSION, '2.2.0.0', '>=')){
            return $this->currency->format($valor, $this->config->get('config_currency'));
		}else{
            return $this->currency->format($valor);
		}
	}
	
    public function replace_html($dados=array()){
		$html = html_entity_decode($this->config->get('module_cart5_email'));
		$logoimg = $this->config->get('config_image');
		if(empty($logoimg)){
			$logoimg = $this->config->get('config_logo');
		}
		$logo = '<img width="250" src="'.HTTPS_SERVER.'image/'.$logoimg.'">';
		$html = str_replace('[logomarca]',$logo,$html);
		$html = str_replace('[nomecliente]',$dados['nomecliente'],$html);
		$html = str_replace('[nomedaloja]',$this->config->get('config_name'),$html);
		$html = str_replace('[produtos]',$dados['produtos'],$html);
		$html = str_replace('[comprar]',$dados['comprar'],$html);
		$html2 = '<meta http-equiv="Content-Type"  content="text/html charset=UTF-8" />';
		return $html2.$html;
	}
	
    public function replace_titulo($dados=array()){
		$titulo = strip_tags($this->config->get('module_cart5_titulo_email'));
		$titulo = str_replace('[nomedaloja]',$this->config->get('config_name'),$titulo);
		return $titulo;
	}
	
	public function fake_mail($html){
		$myfile = fopen(DIR_APPLICATION."../mail/".time().".html", "w");
		fwrite($myfile, $html);
		fclose($myfile);
	}
	
    public function enviar_email($para,$assunto,$html){
		$mail = new Mail($this->config->get('config_mail_engine'));
		$mail->parameter = $this->config->get('config_mail_parameter');	
		$mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
		$mail->smtp_username = $this->config->get('config_mail_smtp_username');
		$mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
		$mail->smtp_port = $this->config->get('config_mail_smtp_port');
		$mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');	
		$mail->setTo($para);
		$mail->setFrom($this->config->get('config_email'));
		$mail->setSender(html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));
		$mail->setSubject(html_entity_decode($assunto, ENT_QUOTES, 'UTF-8'));
		//$this->fake_mail($html);
		$mail->setHtml($html);
		$mail->send();
	}

}