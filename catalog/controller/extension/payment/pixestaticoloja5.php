<?php

//require_once DIR_SYSTEM."../app/pixestaticoloja5/vendor/autoload.php";
class ControllerExtensionPaymentPixEstaticoLoja5 extends Controller {
	
	public function index() {
		$this->load->model('checkout/order');;
		//dados pedido
		$data['pedido'] = $this->session->data['order_id'];
		$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        $data['id_pedido'] = $this->session->data['order_id'];
		$data['pedido_hash'] = sha1(md5($this->session->data['order_id']));
		$data['continue'] = $this->url->link('checkout/success','','SSL');
		
		//valida a chave 
		$erro = '';
		$chave = trim($this->config->get('payment_pixestaticoloja5_chave'));
		$tpchave = trim($this->config->get('payment_pixestaticoloja5_tipo_chave'));
		if($tpchave=='email' && !filter_var($chave, FILTER_VALIDATE_EMAIL)){
			$erro = 'Chave do tipo e-mail cadastrada de forma incorreta, contate o suporte da loja!';
		}elseif($tpchave=='telefone' && strlen(preg_replace('/\D/', '', $chave))!=11){
			$erro = 'Chave do tipo telefone cadastrada de forma incorreta, contate o suporte da loja!';
		}elseif($tpchave=='cpf' && strlen(preg_replace('/\D/', '', $chave))!=11){
			$erro = 'Chave do tipo cpf cadastrada de forma incorreta, contate o suporte da loja!';
		}elseif($tpchave=='cnpj' && strlen(preg_replace('/\D/', '', $chave))!=14){
			$erro = 'Chave do tipo cnpj cadastrada de forma incorreta, contate o suporte da loja!';
		}elseif($tpchave=='aleatoria' && strlen($chave)!=36){
			$erro = 'Chave do tipo aleatoria cadastrada de forma incorreta, contate o suporte da loja!';
		}
		$data['erro'] = $erro;
		
		return $this->load->view('extension/payment/pixestaticoloja5', $data);;
	}

	private function salvar_log($dados_log){
		$log = new Log('pixestaticoloja5-'.md5($this->config->get('payment_pixestaticoloja5_serial')).'-'.date('mY').'.log');
		$log->write($dados_log);
		return true;
	}
	
	public function via(){
		$this->load->model('checkout/order');
		$chave = trim($this->config->get('payment_pixestaticoloja5_chave'));
		$pedido_id = isset($_GET['pedido'])?$_GET['pedido']:'';
		$hash = isset($_GET['hash'])?$_GET['hash']:'';
		$pedido = $this->model_checkout_order->getOrder($pedido_id);
		if(sha1($chave)==$hash){
			$link = htmlspecialchars_decode($this->url->link('checkout/success','pixestaticoloja5=true&pedido='.$pedido_id.'&hash='.sha1(md5($pedido_id.$chave)).'','SSL'));
			die('<script>location.href="'.$link.'";</script>');
		}else{
			die('acesso negado!');
		}
	}
	
	public function atualizar(){
		$this->load->model('checkout/order');
		$json = array();
		$hash = isset($_POST['hash'])?$_POST['hash']:'';
		$pedido = (int)isset($_POST['pedido'])?$_POST['pedido']:'';
		$status = (int)isset($_POST['status'])?$_POST['status']:'';
		$log = isset($_POST['log'])?$_POST['log']:'';
		if(md5($this->config->get('payment_pixestaticoloja5_serial'))!=$hash){
			$json['erro'] = 'Hash de autorização inválido!';
			die(json_encode($json));
		}
		if($pedido=='' || $pedido==0){
			$json['erro'] = 'Número do pedido inválido!';
			die(json_encode($json));
		}
		if($status=='' || $status==0){
			$json['erro'] = 'Status a atualizado do pedido inválido!';
			die(json_encode($json));
		}
		$pedido_loja = $this->model_checkout_order->getOrder($pedido);
		if(!$pedido_loja){
			$json['erro'] = 'Número do pedido não encontrado na loja!';
			die(json_encode($json));
		}
		//atualiza o status 
		$this->model_checkout_order->addOrderHistory($pedido, $status, $log, true);
		//ok 
		$json['sucesso'] = 'Pedido #'.$pedido.' Pix atualizado com sucesso na loja para ['.$status.']!';
		die(json_encode($json));
	}
	
	public function confirm() {
		$this->load->model('checkout/order');
		
		//valida o pedido 
		if(!isset($this->session->data['order_id']) || $this->session->data['order_id']==0){
			die(json_encode(array('erro'=>true,'log'=>"Pedido inválido ou com problema, atualize a página e tente novamente!")));
		}
		
		//dados do pedido
		$pedido_id = $this->session->data['order_id'];
		$pedido = $this->model_checkout_order->getOrder($pedido_id);
		$chave = trim($this->config->get('payment_pixestaticoloja5_chave'));

		//cria um log 
		$this->salvar_log('Pagamento iniciado por PIX #'.$pedido_id.'!');
		
		//link
		$link = htmlspecialchars_decode($this->url->link('checkout/success','pixestaticoloja5=true&pedido='.$pedido_id.'&hash='.sha1(md5($pedido_id.$chave)).'','SSL'));
		
		//registra no banco de dados
		$this->db->query("INSERT INTO `".DB_PREFIX."pixestaticoloja5_pedidos` SET id_pedido = '" . $pedido_id . "', total_pedido = '" . $pedido['total'] . "', ref = '".$pedido['payment_firstname']." ".$pedido['payment_lastname']."', status = 'Iniciado', data = NOW()");
		
		//cria o pedido na loja
		$html = 'Pix Pagamentos - <a class="btn btn-success" href="'.$link.'" target="_blank">Ver QrCode PIX</a>';				
		$this->model_checkout_order->addOrderHistory($pedido_id, $this->config->get('payment_pixestaticoloja5_iniciado'), $html, true);
		
		//ok
		die(json_encode(array('erro'=>false,'log'=>'Pagamento iniciado com sucesso!','link'=>$link)));
	}
	
	public function limpar_string($str) {
		$replaces = array(
			'S'=>'S', 's'=>'s', 'Z'=>'Z', 'z'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
			'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U',
			'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss', 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c',
			'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o',
			'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y'
		);
		
		return preg_replace('/[^0-9A-Za-z;,.\- ]/', '', strtoupper(strtr(trim($str), $replaces)));
	}
	
	public function json(){
		$this->load->model('checkout/order');
		if(isset($this->request->get['pixestaticoloja5']) && $this->request->get['pixestaticoloja5']=='true'){
			//class 
			include(DIR_SYSTEM."../app/pixestaticoloja5/include/qrcode.php");
			include(DIR_SYSTEM."../app/pixestaticoloja5/include/funcoes_pix.php");
			//valida a transação 
			$hash = isset($_GET['hash'])?$_GET['hash']:'';
			$pedido_id = isset($_GET['pedido'])?$_GET['pedido']:'';
			$chave = trim($this->config->get('payment_pixestaticoloja5_chave'));
			$pedido = $this->model_checkout_order->getOrder($pedido_id);
			if(sha1(md5($pedido_id.$chave))==$hash){
				//tratar chave
				$tpchave = trim($this->config->get('payment_pixestaticoloja5_tipo_chave'));
				if($tpchave=='telefone'){
					$chave = '+55'.preg_replace('/\D/', '', $chave).'';
				}elseif($tpchave=='cpf' || $tpchave=='cnpj'){
					$chave = preg_replace('/\D/', '', $chave);
				}
				
				//dados do pix
				$chave_pix = $chave;
				$identificador = $pedido_id;
				$recebedor = substr($this->limpar_string(trim($this->config->get('payment_pixestaticoloja5_nome_recebedor'))),0,25);
				$cidade_recebedor = substr($this->limpar_string(trim($this->config->get('payment_pixestaticoloja5_cidade'))),0,15);
				$valor = $pedido['total'];
				$pagamento_unico = false;
				
				//monta o pix colavel 
				//baseado em https://github.com/renatomb/php_qrcode_pix
				$px[00]="01"; //Payload Format Indicator, Obrigatório, valor fixo: 01
				// Se o QR Code for para pagamento único (só puder ser utilizado uma vez), descomente a linha a seguir.
				if($pagamento_unico){
					$px[01]="12"; //Se o valor 12 estiver presente, significa que o BR Code só pode ser utilizado uma vez. 
				}
				$px[26][00]="BR.GOV.BCB.PIX"; //Indica arranjo específico; “00” (GUI) obrigatório e valor fixo: br.gov.bcb.pix
				$px[26][01]=trim($chave_pix); //Chave do destinatário do pix, pode ser EVP, e-mail, CPF ou CNPJ. Em caso de e-mails substituir o @ por espaço em branco.
				$px[52]="0000"; //Merchant Category Code “0000” ou MCC ISO18245
				$px[53]="986"; //Moeda, “986” = BRL: real brasileiro - ISO4217
				$px[54]=number_format($valor, 2, '.', ''); //Valor da transação, se comentado o cliente especifica o valor da transação no próprio app. Utilizar o . como separador decimal. Máximo: 13 caracteres.
				$px[58]="BR"; //“BR” – Código de país ISO3166-1 alpha 2
				$px[59]=$recebedor; //Nome do beneficiário/recebedor. Máximo: 25 caracteres.
				$px[60]=$cidade_recebedor; //Nome cidade onde é efetuada a transação. Máximo 15 caracteres.
				//O campo 62 é um campo facultativo, que permite especificar um identificador da transação.
				$px[62][05]=$identificador; //Campo facultativo. Identificador da transação.
				//$px[62][50][00]="BR.GOV.BCB.BRCODE"; //Payment system specific template - GUI
				//$px[62][50][01]="1.0.0"; //Payment system specific template - versão
				//Caso queira visualizar a matriz dos dados que serão montados no pix descomente a linha a seguir.
				//print_r($px);
				$pix=montaPix($px);
				/*
				# A função montaPix prepara todos os campos existentes antes do CRC (campo 63).
				# O CRC deve ser calculado em cima de todo o conteúdo, inclusive do próprio 63.
				# O CRC tem 4 dígitos, então o campo será um 6304.
				*/
				$pix.="6304"; //Adiciona o campo do CRC no fim da linha do pix.
				$pix.=crcChecksum($pix); //Calcula o checksum CRC16 e acrescenta ao final.
				
				//imagem qrcode
				ob_start();
				QRCode::png($pix, null,'M',5);
				$imageString = base64_encode( ob_get_contents() );
				ob_end_clean();

				//ok 
				die(json_encode(array('erro'=>false,'img'=>$imageString,'code'=>$pix)));
			}
		}
		//erro
		die(json_encode(array('erro'=>true,'log'=>"Pedido inválido ou com problema, atualize a página e tente novamente!")));
	}
}
?>