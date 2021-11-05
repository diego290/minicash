<?php
class ControllerExtensionPaymentCieloApiCredito extends Controller {
    const EXTENSION = 'payment_cielo_api_credito';

    private $valor_total = 0;

    public function index() {
        $data = $this->load->language('extension/payment/cielo_api_credito');

        include_once(DIR_SYSTEM . 'library/cielo_api/versao.php');

        $data['ambiente'] = $this->config->get(self::EXTENSION . '_ambiente');

        $data['instrucoes'] = '';
        if ($this->config->get(self::EXTENSION . '_information_id')) {
            $this->load->model('catalog/information');
            $information_info = $this->model_catalog_information->getInformation($this->config->get(self::EXTENSION . '_information_id'));

            if ($information_info) {
                $data['instrucoes'] = html_entity_decode($information_info['description'], ENT_QUOTES, 'UTF-8');
            }
        }

        $data['exibir_parcelas'] = $this->config->get(self::EXTENSION . '_exibir_parcelas');
        $data['exibir_juros'] = $this->config->get(self::EXTENSION . '_exibir_juros');

        $data['cor_normal_texto'] = $this->config->get(self::EXTENSION . '_cor_normal_texto');
        $data['cor_normal_fundo'] = $this->config->get(self::EXTENSION . '_cor_normal_fundo');
        $data['cor_normal_borda'] = $this->config->get(self::EXTENSION . '_cor_normal_borda');
        $data['cor_efeito_texto'] = $this->config->get(self::EXTENSION . '_cor_efeito_texto');
        $data['cor_efeito_fundo'] = $this->config->get(self::EXTENSION . '_cor_efeito_fundo');
        $data['cor_efeito_borda'] = $this->config->get(self::EXTENSION . '_cor_efeito_borda');

        $data['estilo_botao'] = $this->config->get(self::EXTENSION . '_estilo_botao_b3');
        $data['texto_botao'] = $this->config->get(self::EXTENSION . '_texto_botao');
        $data['container_botao'] = $this->config->get(self::EXTENSION . '_container_botao');

        $i = 1;
        $bandeiras = array();
        foreach ($this->bandeiras() as $bandeira => $parcelas) {
            ($this->config->get(self::EXTENSION . '_' . $bandeira)) ? $bandeiras[] = array('bandeira' => $bandeira, 'titulo' => strtoupper($bandeira), 'imagem' => HTTPS_SERVER .'image/catalog/cielo_api/'. $bandeira .'.png', 'parcelas' => $parcelas) : '';
            $i++;
        }
        $data['bandeiras'] = json_encode($bandeiras);
        $data['habilitado'] = $i;

        $meses = array();
        for ($i = 1; $i <= 12; $i++) {
            $meses[] = array(
                'text' => sprintf('%02d', $i),
                'value' => sprintf('%02d', $i)
            );
        }
        $data['meses'] = json_encode($meses);

        $anos = array();
        $hoje = getdate();
        for ($i = $hoje['year']; $i < $hoje['year'] + 12; $i++) {
            $anos[] = array(
                'text' => strftime('%Y', mktime(0, 0, 0, 1, 1, $i)),
                'value' => strftime('%Y', mktime(0, 0, 0, 1, 1, $i))
            );
        }
        $data['anos'] = json_encode($anos);

        $data['captcha'] = $this->config->get(self::EXTENSION . '_recaptcha_status');
        if ($data['captcha']) {
            $data['site_key'] = $this->config->get(self::EXTENSION . '_recaptcha_site_key');
        }

        if (isset($this->session->data['secury_token'])) { unset($this->session->data['secury_token']); }
        require_once(DIR_SYSTEM . 'library/cielo_api/helper.php');
        $this->session->data['secury_token'] = secury_token(32);
        $data['token'] = $this->session->data['secury_token'];

        if (!isset($this->session->data['attempts'])) {
            $this->session->data['attempts'] = 6;
        } else if ($data['ambiente'] == '1') {
            $this->session->data['attempts'] = 6;
        }

        $this->session->data['cielo_api_credito_bloqueio'] = 0;

        $data['alerta'] = '';
        if (isset($this->session->data['cielo_api_credito_erro']) && !empty($this->session->data['cielo_api_credito_erro'])) {
            $data['alerta'] = $this->session->data['cielo_api_credito_erro'];
        } else if (isset($this->session->data['attempts']) && $this->session->data['attempts'] <= 0) {
            $data['alerta'] = $this->language->get('error_tentativas');
        }

        $tema = $this->config->get(self::EXTENSION . '_tema');

        return $this->load->view('extension/payment/cielo_api/credito_'. $tema, $data);
    }

    public function parcelas() {
        $json = array();

        if ($this->validar_basico() && isset($this->request->get['bandeira'])) {
            $bandeira = strtolower($this->request->get['bandeira']);

            if ($this->validar_bandeira($bandeira)) {
                $colunas = array('currency_code', 'currency_value', 'total');
                $order_id = $this->session->data['order_id'];

                $this->load->model('extension/payment/cielo_api_credito');
                $order_info = $this->model_extension_payment_cielo_api_credito->getOrder($colunas, $order_id);

                $total = $order_info['total'];
                $currency_code = strtoupper($order_info['currency_code']);
                $currency_value = $order_info['currency_value'];

                $total = $this->currency->format($total, $currency_code, $currency_value, false);
                $this->valor_total = $total;

                $desconto = ($this->config->get(self::EXTENSION . '_desconto') > 0) ? (float) $this->config->get(self::EXTENSION . '_desconto') : 0;
                if ($desconto > 0) {
                     $shipping = $this->model_extension_payment_cielo_api_credito->getOrderShippingValue($order_id);

                    if ($shipping > 0) {
                        $shipping = $this->currency->format($shipping, $currency_code , $currency_value, false);
                    }
                }

                if ($bandeira != 'discover' && $currency_code == 'BRL') {
                    $parcelas = $this->config->get(self::EXTENSION . '_'. $bandeira . '_parcelas');
                    $sem_juros = $this->config->get(self::EXTENSION . '_'. $bandeira . '_sem_juros');
                    $juros = $this->config->get(self::EXTENSION . '_'. $bandeira . '_juros');

                    $valor_minimo = ($this->config->get(self::EXTENSION . '_minimo') > 0) ? $this->config->get(self::EXTENSION . '_minimo') : '0';

                    for ($i = 1; $i <= $parcelas; $i++) {
                        if ($i <= $sem_juros) {
                            if ($i == 1) {
                                if ($desconto > 0) {
                                    $subtotal = $total-$shipping;
                                    $desconto = ($subtotal*$desconto)/100;
                                    $valor_parcela = ($subtotal-$desconto)+$shipping;

                                    $desconto = $this->currency->format($desconto, $currency_code, '1.00', true);
                                } else {
                                    $valor_parcela = $total;
                                }

                                $valor_parcela = $this->currency->format($valor_parcela, $currency_code, '1.00', true);

                                $json[] = array(
                                    'parcela' => 1,
                                    'desconto' => $desconto,
                                    'valor' => $valor_parcela,
                                    'juros' => 0,
                                    'total' => $valor_parcela
                                );
                            } else {
                                $valor_parcela = ($total/$i);
                                if ($valor_parcela >= $valor_minimo) {
                                    $json[] = array(
                                        'parcela' => $i,
                                        'desconto' => 0,
                                        'valor' => $this->currency->format($valor_parcela, $currency_code, '1.00', true),
                                        'juros' => 0,
                                        'total' => $this->currency->format($total, $currency_code, '1.00', true)
                                    );
                                }
                            }
                        } else {
                            $resultado = $this->calcular_parcela($bandeira, $i, $currency_code);
                            if ($resultado['valor_parcela'] >= $valor_minimo) {
                                $json[] = array(
                                    'parcela' => $i,
                                    'desconto' => 0,
                                    'valor' => $this->currency->format($resultado['valor_parcela'], $currency_code, '1.00', true),
                                    'juros' => $juros,
                                    'total' => $this->currency->format($resultado['valor_total'], $currency_code, '1.00', true)
                                );
                            }
                        }
                    }
                } else {
                    if ($desconto > 0) {
                        $subtotal = $total-$shipping;
                        $desconto = ($subtotal*$desconto)/100;
                        $valor_parcela = ($subtotal-$desconto)+$shipping;

                        $desconto = $this->currency->format($desconto, $currency_code, '1.00', true);
                    } else {
                        $valor_parcela = $total;
                    }

                    $valor_parcela = $this->currency->format($valor_parcela, $currency_code, '1.00', true);

                    $json[] = array(
                        'parcela' => 1,
                        'desconto' => $desconto,
                        'valor' => $valor_parcela,
                        'juros' => 0,
                        'total' => $valor_parcela
                    );
                }
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function transacao() {
        $json = array();

        $this->language->load('extension/payment/cielo_api_credito');

        if ($this->validar_basico() && $this->validar_post()) {
            $erros_cadastro = $this->validar_cadastro();

            if (empty($erros_cadastro)) {
                $order_id = $this->session->data['order_id'];

                if ($this->session->data['attempts'] > 0) {
                    if ($this->validar_captcha()) {
                        $cartao_bandeira = $this->limpar_string(strtolower($this->request->post['bandeira']));
                        $cartao_parcelas = preg_replace("/[^0-9]/", '', $this->request->post['parcelas']);
                        $cartao_nome = $this->limpar_string($this->request->post['nome']);
                        $cartao_numero = preg_replace("/[^0-9]/", '', $this->request->post['cartao']);
                        $cartao_mes = preg_replace("/[^0-9]/", '', $this->request->post['mes']);
                        $cartao_ano = preg_replace("/[^0-9]/", '', $this->request->post['ano']);
                        $cartao_cvv = preg_replace("/[^0-9]/", '', $this->request->post['codigo']);

                        $campos = array($cartao_bandeira, $cartao_parcelas, $cartao_nome, $cartao_numero, $cartao_mes, $cartao_ano, $cartao_cvv);

                        if ($this->validar_campos($campos) && $this->validar_bandeira($cartao_bandeira) && ($cartao_parcelas <= '12')) {
                            if ($this->session->data['cielo_api_credito_bloqueio'] == 0) {
                                $this->session->data['cielo_api_credito_bloqueio'] = 1;

                                $this->session->data['attempts']--;

                                $this->load->model('extension/payment/cielo_api_credito');
                                $pedido_pago = $this->model_extension_payment_cielo_api_credito->getTransactionPaid($order_id);

                                if ($pedido_pago == false) {
                                    $this->load->model('checkout/order');
                                    $order_info = $this->model_checkout_order->getOrder($order_id);

                                    $currency_code = strtoupper($order_info['currency_code']);
                                    $currency_value = $order_info['currency_value'];

                                    $total = $this->currency->format($order_info['total'], $currency_code, $currency_value, false);
                                    $this->valor_total = $total;

                                    $shipping = $this->model_extension_payment_cielo_api_credito->getOrderShippingValue($order_id);

                                    if ($shipping > 0) {
                                        $shipping = $this->currency->format($shipping, $currency_code, $currency_value, false);
                                    }

                                    if ($cartao_parcelas <= '1') {
                                        $desconto = ($this->config->get(self::EXTENSION . '_desconto') > 0) ? (float) $this->config->get(self::EXTENSION . '_desconto') : 0;

                                        if ($desconto > 0) {
                                            $subtotal = $total-$shipping;
                                            $desconto = ($subtotal*$desconto)/100;
                                            $total = ($subtotal-$desconto)+$shipping;
                                        }
                                    } else {
                                        $sem_juros = $this->config->get(self::EXTENSION . '_' . $cartao_bandeira . '_sem_juros');

                                        if ($cartao_parcelas > $sem_juros) {
                                            $resultado = $this->calcular_parcela($cartao_bandeira, $cartao_parcelas, $currency_code);
                                            $total = $resultado['valor_total'];
                                        }
                                    }

                                    $bandeiras_cielo = array(
                                        'visa' => 'Visa',
                                        'mastercard' => 'Master',
                                        'diners' => 'Diners',
                                        'discover' => 'Discover',
                                        'elo' => 'Elo',
                                        'amex' => 'Amex',
                                        'hiper' => 'Hiper',
                                        'hipercard' => 'Hipercard',
                                        'jcb' => 'Jcb',
                                        'aura' => 'Aura',
                                    );

                                    $dados['MerchantOrderId'] = $order_id;
                                    $dados['Customer'] = trim($order_info['firstname'].' '.$order_info['lastname']);
                                    $dados['Amount'] = number_format($total, 2, '', '');
                                    $dados['Installments'] = $cartao_parcelas;
                                    $dados['Capture'] = $this->config->get(self::EXTENSION . '_captura');
                                    $dados['SoftDescriptor'] = $this->config->get(self::EXTENSION . '_soft_descriptor');
                                    $dados['CardNumber'] = $cartao_numero;
                                    $dados['Holder'] = $cartao_nome;
                                    $dados['ExpirationDate'] = $cartao_mes. '/' .$cartao_ano;
                                    $dados['SecurityCode'] = $cartao_cvv;
                                    $dados['Brand'] = $bandeiras_cielo[$cartao_bandeira];

                                    $antifraude = $this->config->get(self::EXTENSION . '_antifraude_status');

                                    if ($antifraude && isset($this->session->data['cielo_api_fingerprint_id'])) {
                                        $has_shipping = $this->cart->hasShipping();

                                        $custom_razao_id = $this->config->get(self::EXTENSION . '_custom_razao_id');
                                        $custom_cnpj_id = $this->config->get(self::EXTENSION . '_custom_cnpj_id');
                                        $custom_cpf_id = $this->config->get(self::EXTENSION . '_custom_cpf_id');
                                        $custom_numero_id = $this->config->get(self::EXTENSION . '_custom_numero_id');
                                        $custom_complemento_id = $this->config->get(self::EXTENSION . '_custom_complemento_id');

                                        $razao_coluna = $this->config->get(self::EXTENSION . '_razao_coluna');
                                        $cnpj_coluna = $this->config->get(self::EXTENSION . '_cnpj_coluna');
                                        $cpf_coluna = $this->config->get(self::EXTENSION . '_cpf_coluna');
                                        $numero_fatura_coluna = $this->config->get(self::EXTENSION . '_numero_fatura_coluna');
                                        $numero_entrega_coluna = $this->config->get(self::EXTENSION . '_numero_entrega_coluna');
                                        $complemento_fatura_coluna = $this->config->get(self::EXTENSION . '_complemento_fatura_coluna');
                                        $complemento_entrega_coluna = $this->config->get(self::EXTENSION . '_complemento_entrega_coluna');

                                        $colunas = array();
                                        $colunas_info = array();

                                        $campos = $this->campos();

                                        if (in_array($custom_razao_id, $campos) && $custom_razao_id == 'N') { array_push($colunas, $razao_coluna); }
                                        if (in_array($custom_cnpj_id, $campos) && $custom_cnpj_id == 'N') { array_push($colunas, $cnpj_coluna); }
                                        if (in_array($custom_cpf_id, $campos) && $custom_cpf_id == 'N') { array_push($colunas, $cpf_coluna); }
                                        if ($custom_numero_id == 'N') { array_push($colunas, $numero_fatura_coluna); }
                                        if ($custom_complemento_id == 'N') { array_push($colunas, $complemento_fatura_coluna); }
                                        if ($has_shipping) {
                                            if ($custom_numero_id == 'N') { array_push($colunas, $numero_entrega_coluna); }
                                            if ($custom_complemento_id == 'N') { array_push($colunas, $complemento_entrega_coluna); }
                                        }

                                        if (count($colunas)) {
                                            $colunas_info = $this->model_extension_payment_cielo_api_credito->getOrder($colunas, $order_id);
                                        }

                                        $customer_name = '';
                                        if (in_array($custom_razao_id, $campos)) {
                                            $customer_name = $this->campo_valor($order_info['custom_field'], $custom_razao_id, $colunas_info, $razao_coluna);
                                            $customer_name = trim($customer_name);
                                        }

                                        if (empty($customer_name)) {
                                            $customer_name = trim($order_info['firstname'] . ' ' . $order_info['lastname']);
                                        }

                                        $document_number = '';
                                        if (in_array($custom_cnpj_id, $campos)) {
                                            $document_number = $this->campo_valor($order_info['custom_field'], $custom_cnpj_id, $colunas_info, $cnpj_coluna);
                                            $document_number = trim($document_number);
                                        }

                                        if (empty($document_number)) {
                                            if (in_array($custom_cpf_id, $campos)) {
                                                $document_number = $this->campo_valor($order_info['custom_field'], $custom_cpf_id, $colunas_info, $cpf_coluna);
                                            }
                                        }

                                        $billing_number = $this->campo_valor($order_info['payment_custom_field'], $custom_numero_id, $colunas_info, $numero_fatura_coluna);
                                        $billing_complement = $this->campo_valor($order_info['payment_custom_field'], $custom_complemento_id, $colunas_info, $complemento_fatura_coluna);

                                        if ($has_shipping) {
                                            $shipping_number = $this->campo_valor($order_info['shipping_custom_field'], $custom_numero_id, $colunas_info, $numero_entrega_coluna);
                                            $shipping_complement = $this->campo_valor($order_info['shipping_custom_field'], $custom_complemento_id, $colunas_info, $complemento_entrega_coluna);
                                        }

                                        $dados['FingerPrintId'] = $this->session->data['cielo_api_fingerprint_id'];

                                        $dados['Customer'] = $customer_name;
                                        $dados['Identity'] = $document_number;
                                        $dados['Email'] = $order_info['email'];
                                        $dados['Phone'] = '55' . $order_info['telephone'];

                                        $dados['PaymentStreet'] = $order_info['payment_address_1'];
                                        $dados['PaymentNumber'] = $billing_number;
                                        $dados['PaymentComplement'] = $billing_complement;
                                        $dados['PaymentDistrict'] = $order_info['payment_address_2'];
                                        $dados['PaymentZipcode'] = $order_info['payment_postcode'];
                                        $dados['PaymentCity'] = $order_info['payment_city'];
                                        $dados['PaymentState'] = $order_info['payment_zone_code'];

                                        if ($has_shipping) {
                                            $dados['ShippingStreet'] = $order_info['shipping_address_1'];
                                            $dados['ShippingNumber'] = $shipping_number;
                                            $dados['ShippingComplement'] = $shipping_complement;
                                            $dados['ShippingDistrict'] = $order_info['shipping_address_2'];
                                            $dados['ShippingZipcode'] = $order_info['shipping_postcode'];
                                            $dados['ShippingCity'] = $order_info['shipping_city'];
                                            $dados['ShippingState'] = $order_info['shipping_zone_code'];

                                            if ($order_info['shipping_code'] == 'pickup.pickup') {
                                                $dados['ShippingMethod'] = 'Pickup';
                                            } else {
                                                $dados['ShippingMethod'] = 'Other';
                                            }
                                        } else {
                                            $dados['ShippingMethod'] = 'None';
                                        }

                                        $giftcategory = $this->config->get(self::EXTENSION . '_antifraude_giftcategory');
                                        $hosthedge = $this->config->get(self::EXTENSION . '_antifraude_hosthedge');
                                        $nonsensicalhedge = $this->config->get(self::EXTENSION . '_antifraude_nonsensicalhedge');
                                        $obscenitieshedge = $this->config->get(self::EXTENSION . '_antifraude_obscenitieshedge');
                                        $risk = $this->config->get(self::EXTENSION . '_antifraude_risk');
                                        $timehedge = $this->config->get(self::EXTENSION . '_antifraude_timehedge');
                                        $type = $this->config->get(self::EXTENSION . '_antifraude_type');
                                        $velocityhedge = $this->config->get(self::EXTENSION . '_antifraude_velocityhedge');

                                        $products = $this->model_extension_payment_cielo_api_credito->getOrderProducts($order_id);

                                        $dados['Items'] = array();
                                        foreach ($products as $product) {
                                            $dados['Items'][] = array(
                                                'GiftCategory' => $giftcategory,
                                                'HostHedge' => $hosthedge,
                                                'NonSensicalHedge' => $nonsensicalhedge,
                                                'ObscenitiesHedge' => $obscenitieshedge,
                                                'Name' => $product['name'],
                                                'UnitPrice' => number_format(($product['price'] + $product['tax']) * $currency_value, 2, '', ''),
                                                'Quantity' => $product['quantity'],
                                                'Sku' => $product['sku'],
                                                'Risk' => $risk,
                                                'TimeHedge' => $timehedge,
                                                'Type' => $type,
                                                'VelocityHedge' => $velocityhedge
                                            );
                                        }

                                        if (!empty($this->session->data['vouchers'])) {
                                            foreach ($this->session->data['vouchers'] as $voucher) {
                                                $dados['Items'][] = array(
                                                    'Name' => $voucher['description'],
                                                    'UnitPrice' => number_format($voucher['amount'] * $currency_value, 2, '', ''),
                                                    'Quantity' => '1',
                                                    'Sku' => 'VALE PRESENTES'
                                                );
                                            }
                                        }

                                        $dados['ReturnsAccepted'] = ($this->config->get(self::EXTENSION . '_antifraude_returnsaccepted')) ? 'true' : 'false';
                                    }

                                    $chave = $this->config->get(self::EXTENSION . '_chave');
                                    $dados['Chave'] = $chave[$this->config->get('config_store_id')];
                                    $dados['Debug'] = $this->config->get(self::EXTENSION . '_debug');
                                    $dados['Ambiente'] = $this->config->get(self::EXTENSION . '_ambiente');
                                    $dados['Antifraude'] = $antifraude;
                                    $dados['MerchantId'] = $this->config->get(self::EXTENSION . '_merchantid');
                                    $dados['MerchantKey'] = $this->config->get(self::EXTENSION . '_merchantkey');

                                    require_once(DIR_SYSTEM . 'library/cielo_api/cielo.php');
                                    $cielo = new Cielo();
                                    $cielo->setParametros($dados);
                                    $resposta = $cielo->setTransacaoCredito();

                                    if ($resposta) {
                                        if (isset($resposta->Payment->Status)) {
                                            $payment_status = $resposta->Payment->Status;
                                            $payment_paymentid = $resposta->Payment->PaymentId;
                                            $payment_installments = $resposta->Payment->Installments;
                                            $payment_brand = $resposta->Payment->CreditCard->Brand;
                                            $payment_amount = $this->currency->format(($resposta->Payment->Amount/100), $currency_code, '1.00', true);

                                            $fraud_analysis = 0;
                                            if (isset($resposta->Payment->FraudAnalysis->StatusDescription)) {
                                                $fraud_analysis_status_description = $resposta->Payment->FraudAnalysis->StatusDescription;

                                                if ($fraud_analysis_status_description != 'Aborted') {
                                                    $fraud_analysis = 1;
                                                }
                                            }

                                            if ($payment_status != '13') {
                                                $payment_date = date('d/m/Y H:i', strtotime($resposta->Payment->ReceivedDate));
                                                if (isset($resposta->Payment->Tid)) {
                                                    $payment_tid = $resposta->Payment->Tid;
                                                }
                                            }

                                            $response = json_encode($resposta, JSON_HEX_QUOT|JSON_HEX_APOS);

                                            switch ($payment_status) {
                                                case '0':
                                                    $campos = array(
                                                        'order_id' => $order_id,
                                                        'paymentId' => $payment_paymentid,
                                                        'status' => $payment_status,
                                                        'tipo' => 'CreditCard',
                                                        'antifraude' => $fraud_analysis,
                                                        'parcelas' => $payment_installments,
                                                        'bandeira' => $payment_brand,
                                                        'json' => $response
                                                    );

                                                    $comment = $this->language->get('entry_pedido') . $order_id . "\n";
                                                    $comment .= $this->language->get('entry_data') . $payment_date . "\n";
                                                    $comment .= $this->language->get('entry_tipo') . $this->language->get('text_cartao_credito') . "\n";
                                                    $comment .= $this->language->get('entry_bandeira') . strtoupper($payment_brand) . "\n";
                                                    $comment .= $this->language->get('entry_parcelas') . $payment_installments . 'x ' . $this->language->get('text_total') . $payment_amount . "\n";
                                                    $comment .= $this->language->get('entry_status') . $this->language->get('text_aguardando');

                                                    if (isset($this->session->data['cielo_api_credito_comprovante'])) {
                                                        unset($this->session->data['cielo_api_credito_comprovante']);
                                                    }
                                                    $this->session->data['cielo_api_credito_comprovante'] = $this->language->get('text_comprovante') . "\n" . $comment;

                                                    $this->model_extension_payment_cielo_api_credito->addTransaction($campos);

                                                    $this->model_checkout_order->addOrderHistory($order_id, $this->config->get(self::EXTENSION . '_situacao_pendente_id'), $comment, true);

                                                    $json['redirect'] = $this->url->link('checkout/success', '', true);

                                                    break;
                                                case '1':
                                                    $campos = array(
                                                        'order_id' => $order_id,
                                                        'paymentId' => $payment_paymentid,
                                                        'status' => $payment_status,
                                                        'tipo' => 'CreditCard',
                                                        'antifraude' => $fraud_analysis,
                                                        'tid' => $payment_tid,
                                                        'nsu' => $resposta->Payment->ProofOfSale,
                                                        'authorizationCode' => $resposta->Payment->AuthorizationCode,
                                                        'bandeira' => $payment_brand,
                                                        'bin' => substr($resposta->Payment->CreditCard->CardNumber, 0, 6),
                                                        'fim' => substr($resposta->Payment->CreditCard->CardNumber, -4),
                                                        'holder' => $resposta->Payment->CreditCard->Holder,
                                                        'eci' => (isset($resposta->Payment->Eci)) ? $resposta->Payment->Eci : '',
                                                        'parcelas' => $payment_installments,
                                                        'autorizacaoData' => $resposta->Payment->ReceivedDate,
                                                        'autorizacaoValor' => $resposta->Payment->Amount,
                                                        'json' => $response
                                                    );

                                                    if ($this->config->get(self::EXTENSION . '_clearsale_status')) {
                                                        $status = $this->language->get('text_em_analise');
                                                    } else if ($this->config->get(self::EXTENSION . '_fcontrol_status')) {
                                                        $status = $this->language->get('text_em_analise');
                                                    } else {
                                                        $status = $this->language->get('text_autorizado');
                                                    }

                                                    $comment = $this->language->get('entry_pedido') . $order_id . "\n";
                                                    $comment .= $this->language->get('entry_data') . $payment_date . "\n";
                                                    $comment .= $this->language->get('entry_tid') . $payment_tid . "\n";
                                                    $comment .= $this->language->get('entry_tipo') . $this->language->get('text_cartao_credito') . "\n";
                                                    $comment .= $this->language->get('entry_bandeira') . strtoupper($payment_brand) . "\n";
                                                    $comment .= $this->language->get('entry_parcelas') . $payment_installments . 'x ' . $this->language->get('text_total') . $payment_amount . "\n";
                                                    $comment .= $this->language->get('entry_status') . $status;

                                                    if (isset($this->session->data['cielo_api_credito_comprovante'])) {
                                                        unset($this->session->data['cielo_api_credito_comprovante']);
                                                    }
                                                    $this->session->data['cielo_api_credito_comprovante'] = $this->language->get('text_comprovante') . "\n" . $comment;

                                                    $this->model_extension_payment_cielo_api_credito->addTransaction($campos);

                                                    $this->model_checkout_order->addOrderHistory($order_id, $this->config->get(self::EXTENSION . '_situacao_autorizada_id'), $comment, true);

                                                    $json['redirect'] = $this->url->link('checkout/success', '', true);

                                                    break;
                                                case '2':
                                                    $campos = array(
                                                        'order_id' => $order_id,
                                                        'paymentId' => $payment_paymentid,
                                                        'status' => $payment_status,
                                                        'tipo' => 'CreditCard',
                                                        'antifraude' => $fraud_analysis,
                                                        'tid' => $payment_tid,
                                                        'nsu' => $resposta->Payment->ProofOfSale,
                                                        'authorizationCode' => $resposta->Payment->AuthorizationCode,
                                                        'bandeira' => $payment_brand,
                                                        'bin' => substr($resposta->Payment->CreditCard->CardNumber, 0, 6),
                                                        'fim' => substr($resposta->Payment->CreditCard->CardNumber, -4),
                                                        'holder' => $resposta->Payment->CreditCard->Holder,
                                                        'eci' => (isset($resposta->Payment->Eci)) ? $resposta->Payment->Eci : '',
                                                        'parcelas' => $payment_installments,
                                                        'autorizacaoData' => $resposta->Payment->ReceivedDate,
                                                        'autorizacaoValor' => $resposta->Payment->Amount,
                                                        'capturaData' => $resposta->Payment->CapturedDate,
                                                        'capturaValor' => $resposta->Payment->CapturedAmount,
                                                        'json' => $response
                                                    );

                                                    if ($this->config->get(self::EXTENSION . '_clearsale_status')) {
                                                        $status = $this->language->get('text_em_analise');
                                                    } else if ($this->config->get(self::EXTENSION . '_fcontrol_status')) {
                                                        $status = $this->language->get('text_em_analise');
                                                    } else {
                                                        $status = $this->language->get('text_capturado');
                                                    }

                                                    $payment_date = date('d/m/Y H:i', strtotime($resposta->Payment->CapturedDate));
                                                    $payment_amount = $this->currency->format(($resposta->Payment->CapturedAmount / 100), $currency_code, '1.00', true);

                                                    $comment = $this->language->get('entry_pedido') . $order_id . "\n";
                                                    $comment .= $this->language->get('entry_data') . $payment_date . "\n";
                                                    $comment .= $this->language->get('entry_tid') . $payment_tid . "\n";
                                                    $comment .= $this->language->get('entry_tipo') . $this->language->get('text_cartao_credito') . "\n";
                                                    $comment .= $this->language->get('entry_bandeira') . strtoupper($payment_brand) . "\n";
                                                    $comment .= $this->language->get('entry_parcelas') . $payment_installments . 'x ' . $this->language->get('text_total') . $payment_amount . "\n";
                                                    $comment .= $this->language->get('entry_status') . $status;

                                                    if (isset($this->session->data['cielo_api_credito_comprovante'])) {
                                                        unset($this->session->data['cielo_api_credito_comprovante']);
                                                    }
                                                    $this->session->data['cielo_api_credito_comprovante'] = $this->language->get('text_comprovante') . "\n" . $comment;

                                                    $this->model_extension_payment_cielo_api_credito->addTransaction($campos);

                                                    $this->model_checkout_order->addOrderHistory($order_id, $this->config->get(self::EXTENSION . '_situacao_capturada_id'), $comment, true);

                                                    $json['redirect'] = $this->url->link('checkout/success', '', true);

                                                    break;
                                                case '12':
                                                    $campos = array(
                                                        'order_id' => $order_id,
                                                        'paymentId' => $payment_paymentid,
                                                        'status' => $payment_status,
                                                        'tipo' => 'CreditCard',
                                                        'antifraude' => $fraud_analysis,
                                                        'tid' => $payment_tid,
                                                        'parcelas' => $payment_installments,
                                                        'bandeira' => $payment_brand,
                                                        'json' => $response
                                                    );

                                                    $comment = $this->language->get('entry_pedido') . $order_id . "\n";
                                                    $comment .= $this->language->get('entry_data') . $payment_date . "\n";
                                                    $comment .= $this->language->get('entry_tid') . $payment_tid . "\n";
                                                    $comment .= $this->language->get('entry_tipo') . $this->language->get('text_cartao_credito') . "\n";
                                                    $comment .= $this->language->get('entry_bandeira') . strtoupper($payment_brand) . "\n";
                                                    $comment .= $this->language->get('entry_parcelas') . $payment_installments . 'x ' . $this->language->get('text_total') . $payment_amount . "\n";
                                                    $comment .= $this->language->get('entry_status') . $this->language->get('text_pendente');

                                                    if (isset($this->session->data['cielo_api_credito_comprovante'])) {
                                                        unset($this->session->data['cielo_api_credito_comprovante']);
                                                    }
                                                    $this->session->data['cielo_api_credito_comprovante'] = $this->language->get('text_comprovante') . "\n" . $comment;

                                                    $this->model_extension_payment_cielo_api_credito->addTransaction($campos);

                                                    $this->model_checkout_order->addOrderHistory($order_id, $this->config->get(self::EXTENSION . '_situacao_pendente_id'), $comment, true);

                                                    $json['redirect'] = $this->url->link('checkout/success', '', true);

                                                    break;
                                                case '13':
                                                    $comment = $this->language->get('entry_pedido') . $order_id . "\n";
                                                    $comment .= $this->language->get('entry_tipo') . $this->language->get('text_cartao_credito') . "\n";
                                                    $comment .= $this->language->get('entry_parcelas') . $payment_installments . 'x ' . $this->language->get('text_total') . $payment_amount . "\n";

                                                    if ($antifraude && isset($fraud_analysis_status_description)) {
                                                        switch ($fraud_analysis_status_description) {
                                                            case 'Started':
                                                            case 'Pendent':
                                                                $comment .= $this->language->get('entry_status') . $this->language->get('text_em_analise');
                                                                break;
                                                            case 'Review':
                                                                $comment .= $this->language->get('entry_status') . $this->language->get('text_em_revisao');
                                                                break;
                                                            case 'Reject':
                                                                $comment .= $this->language->get('entry_status') . $this->language->get('text_reprovado');
                                                                break;
                                                            default:
                                                                $comment .= $this->language->get('entry_status') . $this->language->get('text_nao_autorizado');
                                                                break;
                                                        }

                                                        if (
                                                            $fraud_analysis_status_description == 'Started' ||
                                                            $fraud_analysis_status_description == 'Pendent' ||
                                                            $fraud_analysis_status_description == 'Review'
                                                        ) {
                                                            $campos = array(
                                                                'order_id' => $order_id,
                                                                'paymentId' => $payment_paymentid,
                                                                'status' => $payment_status,
                                                                'tipo' => 'CreditCard',
                                                                'antifraude' => $fraud_analysis,
                                                                'parcelas' => $payment_installments,
                                                                'json' => $response
                                                            );

                                                            if (isset($this->session->data['cielo_api_credito_comprovante'])) {
                                                                unset($this->session->data['cielo_api_credito_comprovante']);
                                                            }
                                                            $this->session->data['cielo_api_credito_comprovante'] = $this->language->get('text_comprovante') . "\n" . $comment;

                                                            $this->model_extension_payment_cielo_api_credito->addTransaction($campos);

                                                            $this->model_checkout_order->addOrderHistory($order_id, $this->config->get(self::EXTENSION . '_situacao_pendente_id'), $comment, true);

                                                            $json['redirect'] = $this->url->link('checkout/success', '', true);
                                                        } else {
                                                            $this->model_checkout_order->addOrderHistory($order_id, $this->config->get(self::EXTENSION . '_situacao_nao_autorizada_id'), $comment, true);

                                                            $json['error'] = $this->language->get('error_autorizacao');
                                                        }
                                                    } else {
                                                        $comment .= $this->language->get('entry_status') . $this->language->get('text_nao_autorizado');

                                                        $this->model_checkout_order->addOrderHistory($order_id, $this->config->get(self::EXTENSION . '_situacao_nao_autorizada_id'), $comment, true);

                                                        $json['error'] = $this->language->get('error_autorizacao');
                                                    }

                                                    break;
                                                default:
                                                    $comment = $this->language->get('entry_pedido') . $order_id . "\n";
                                                    $comment .= $this->language->get('entry_data') . $payment_date . "\n";
                                                    $comment .= $this->language->get('entry_tid') . $payment_tid . "\n";
                                                    $comment .= $this->language->get('entry_tipo') . $this->language->get('text_cartao_credito') . "\n";
                                                    $comment .= $this->language->get('entry_bandeira') . strtoupper($payment_brand) . "\n";
                                                    $comment .= $this->language->get('entry_parcelas') . $payment_installments . 'x ' . $this->language->get('text_total') . $payment_amount . "\n";
                                                    $comment .= $this->language->get('entry_status') . $this->language->get('text_nao_autorizado');

                                                    $this->model_checkout_order->addOrderHistory($order_id, $this->config->get(self::EXTENSION . '_situacao_nao_autorizada_id'), $comment, true);

                                                    $json['error'] = $this->language->get('error_autorizacao');

                                                    break;
                                            }
                                        } else {
                                            $json['error'] = $this->language->get('error_status');
                                        }
                                    } else {
                                        $json['error'] = $this->language->get('error_configuracao');
                                    }
                                } else {
                                    $json['redirect'] = $this->url->link('checkout/success', '', true);
                                }
                            } else {
                                sleep(5);

                                $this->load->model('extension/payment/cielo_api_credito');
                                $pedido_pago = $this->model_extension_payment_cielo_api_credito->getTransactionPaid($order_id);

                                if ($pedido_pago == true) {
                                    $json['redirect'] = $this->url->link('checkout/success', '', true);
                                } else if (!isset($json['error'])) {
                                    $json['error'] = $this->language->get('error_status');
                                }
                            }
                        } else {
                            $json['error'] = $this->language->get('error_preenchimento');
                        }
                    } else {
                        $json['error'] = $this->language->get('error_captcha');
                    }
                } else {
                    if ($this->session->data['attempts'] == 0) {
                        $this->session->data['attempts']--;

                        $this->load->model('checkout/order');
                        $this->model_checkout_order->addOrderHistory($order_id, $this->config->get(self::EXTENSION . '_situacao_nao_autorizada_id'), $this->language->get('text_tentativas'), true);
                    }

                    if (isset($this->session->data['payment_method'])) {
                        unset($this->session->data['payment_method']);
                    }

                    $json['error'] = $this->language->get('error_tentativas');
                }
            } else {
                $json['error'] = sprintf($this->language->get('error_validacao'), $erros_cadastro);
            }
        } else {
            $json['error'] = $this->language->get('error_permissao');
        }

        if (isset($json['redirect']) && !empty($json['redirect'])) {
            $this->session->data['attempts'] = 6;
        }

        if (isset($json['error']) && !empty($json['error'])) {
            $this->session->data['cielo_api_credito_bloqueio'] = 0;

            $this->session->data['cielo_api_credito_erro'] = $json['error'];
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    private function bandeiras() {
        return array(
            "visa" => $this->config->get(self::EXTENSION . '_visa_parcelas'),
            "mastercard" => $this->config->get(self::EXTENSION . '_mastercard_parcelas'),
            "diners" => $this->config->get(self::EXTENSION . '_diners_parcelas'),
            "discover" => '1',
            "elo" => $this->config->get(self::EXTENSION . '_elo_parcelas'),
            "amex" => $this->config->get(self::EXTENSION . '_amex_parcelas'),
            "hiper" => $this->config->get(self::EXTENSION . '_hiper_parcelas'),
            "hipercard" => $this->config->get(self::EXTENSION . '_hipercard_parcelas'),
            "jcb" => $this->config->get(self::EXTENSION . '_jcb_parcelas'),
            "aura" => $this->config->get(self::EXTENSION . '_aura_parcelas')
        );
    }

    private function calcular_parcela($bandeira, $parcelas, $currency_code) {
        $total = $this->valor_total;

        if ($bandeira != 'discover' && $currency_code == 'BRL') {
            $parcelar = $this->config->get(self::EXTENSION . '_' . $bandeira . '_parcelas');
            $juros = $this->config->get(self::EXTENSION . '_' . $bandeira . '_juros')/100;
            $calculo  = $this->config->get(self::EXTENSION . '_calculo');

            if ($parcelas > $parcelar) {
                $parcelas = $parcelar;
            }

            if ($calculo) {
                $valor_parcela = ($total*$juros)/(1-(1/pow(1+$juros, $parcelas)));
            } else {
                $valor_parcela = ($total*pow(1+$juros, $parcelas))/$parcelas;
            }

            $valor_parcela = round($valor_parcela, 2);
            $valor_total = $parcelas*$valor_parcela;
        } else {
            $valor_parcela = $total;
            $valor_total = $total;
        }

        return array(
            'valor_parcela' => $valor_parcela,
            'valor_total' => $valor_total
        );
    }

    private function limpar_string($string) {
        $string = strip_tags($string);
        $string = preg_replace('/[\n\t\r]/', ' ', $string);
        $string = preg_replace('/( ){2,}/', '$1', $string);

        return trim($string);
    }

    private function validar_basico() {
        require_once(DIR_SYSTEM . 'library/cielo_api/helper.php');

        if (
            isset($this->session->data['order_id']) &&
            isset($this->session->data['payment_method']['code']) &&
            isset($this->session->data['secury_token']) &&
            isset($this->session->data['attempts']) &&
            isset($this->request->get['token']) &&
            $this->session->data['payment_method']['code'] == 'cielo_api_credito' &&
            hash_equals($this->session->data['secury_token'], trim($this->request->get['token'])) &&
            $this->session->data['attempts'] >= 0 &&
            $this->session->data['attempts'] <= 6
        ) {
            return true;
        }

        return false;
    }

    private function validar_post() {
        $campos = array('bandeira', 'parcelas', 'nome', 'cartao', 'mes', 'ano', 'codigo');

        $erros = 0;
        foreach ($campos as $campo) {
            if (!isset($this->request->post[$campo])) {
                $erros++;
                break;
            }
        }

        if ($erros == 0) {
            return true;
        } else {
            return false;
        }
    }

    private function validar_campos($campos) {
        $erros = 0;

        foreach ($campos as $campo) {
            if (empty($campo)) {
                $erros++;
                break;
            }
        }

        if ($erros == 0) {
            return true;
        } else {
            return false;
        }
    }

    private function validar_bandeira($bandeira) {
        $bandeiras = $this->bandeiras();

        return array_key_exists($bandeira, $bandeiras);
    }

    private function validar_captcha() {
        if (!$this->config->get(self::EXTENSION . '_recaptcha_status')) {
            return true;
        }

        if (!isset($this->session->data['attempts'])) {
            return false;
        }

        if (empty($this->request->post['g-recaptcha-response'])) {
            return false;
        }

        $recaptcha = file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret=' . urlencode($this->config->get(self::EXTENSION . '_recaptcha_secret_key')) . '&response=' . $this->request->post['g-recaptcha-response'] . '&remoteip=' . $this->request->server['REMOTE_ADDR']);
        $recaptcha = json_decode($recaptcha);

        if (isset($recaptcha->success)) {
            if ($recaptcha->success) {
                return true;
            }
        }

        return false;
    }

    private function antifraude() {
        $status = false;

        if ($this->config->get(self::EXTENSION . '_antifraude_status')) {
            $status = true;
        }

        if ($this->config->get(self::EXTENSION . '_clearsale_status')) {
            $status = true;
        }

        if ($this->config->get(self::EXTENSION . '_fcontrol_status')) {
            $status = true;
        }

        return $status;
    }

    private function atualizar_pedido() {
        $order_data['custom_field'] = array();

        if ($this->customer->isLogged()) {
            $this->load->model('account/customer');
            $customer_info = $this->model_account_customer->getCustomer($this->customer->getId());

            $order_data['custom_field'] = json_decode($customer_info['custom_field'], true);
        } else if (isset($this->session->data['guest'])) {
            $order_data['custom_field'] = $this->session->data['guest']['custom_field'];
        }

        $order_data['payment_custom_field'] = (isset($this->session->data['payment_address']['custom_field']) ? $this->session->data['payment_address']['custom_field'] : array());

        if ($this->cart->hasShipping()) {
            $order_data['shipping_custom_field'] = (isset($this->session->data['shipping_address']['custom_field']) ? $this->session->data['shipping_address']['custom_field'] : array());
        } else {
            $order_data['shipping_custom_field'] = array();
        }

        $this->load->model('extension/payment/cielo_api_credito');
        $this->model_extension_payment_cielo_api_credito->editOrder($order_data, $this->session->data['order_id']);
    }

    private function campos() {
        if ($this->customer->isLogged()) {
            $customer_group_id = $this->customer->getGroupId();
        } elseif (isset($this->session->data['guest']['customer_group_id'])) {
            $customer_group_id = $this->session->data['guest']['customer_group_id'];
        } else {
            $customer_group_id = $this->config->get('config_customer_group_id');
        }

        $this->load->model('account/custom_field');
        $custom_fields = $this->model_account_custom_field->getCustomFields($customer_group_id);

        $fields = array();
        foreach ($custom_fields as $custom_field) {
            array_push($fields, $custom_field['custom_field_id']);
        }

        return $fields;
    }

    private function campo_valor($custom_data, $field_key, $collumn_data, $field_collumn) {
        $field_value = '';

        if ($field_key == 'N') {
            if (isset($collumn_data[$field_collumn]) && !empty($collumn_data[$field_collumn])) {
                $field_value = $collumn_data[$field_collumn];
            }
        } else if (!empty($field_key) && is_array($custom_data)) {
            foreach ($custom_data as $key => $value) {
                if ($field_key == $key) { $field_value = $value; }
            }
        }

        return $field_value;
    }

    private function validar_cadastro() {
        $antifraude = $this->antifraude();
        if ($antifraude == false) { return ''; }

        $this->load->language('extension/payment/cielo_api_validacao');

        $this->atualizar_pedido();

        $custom_razao_id = $this->config->get(self::EXTENSION . '_custom_razao_id');
        $custom_cnpj_id = $this->config->get(self::EXTENSION . '_custom_cnpj_id');
        $custom_cpf_id = $this->config->get(self::EXTENSION . '_custom_cpf_id');
        $custom_numero_id = $this->config->get(self::EXTENSION . '_custom_numero_id');

        $razao_coluna = $this->config->get(self::EXTENSION . '_razao_coluna');
        $cnpj_coluna = $this->config->get(self::EXTENSION . '_cnpj_coluna');
        $cpf_coluna = $this->config->get(self::EXTENSION . '_cpf_coluna');
        $numero_coluna = $this->config->get(self::EXTENSION . '_numero_fatura_coluna');

        $colunas = array();
        $colunas_info = array();

        $campos = $this->campos();

        if (in_array($custom_razao_id, $campos) && $custom_razao_id == 'N') { array_push($colunas, $razao_coluna); }
        if (in_array($custom_cnpj_id, $campos) && $custom_cnpj_id == 'N') { array_push($colunas, $cnpj_coluna); }
        if (in_array($custom_cpf_id, $campos) && $custom_cpf_id == 'N') { array_push($colunas, $cpf_coluna); }
        if ($custom_numero_id == 'N') { array_push($colunas, $numero_coluna); }

        $order_id = $this->session->data['order_id'];

        if (count($colunas)) {
            $this->load->model('extension/payment/cielo_api_credito');
            $colunas_info = $this->model_extension_payment_cielo_api_credito->getOrder($colunas, $order_id);
        }

        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($order_id);

        $erros = array();

        $razao = '';
        if (in_array($custom_razao_id, $campos)) {
            $razao = $this->campo_valor($order_info['custom_field'], $custom_razao_id, $colunas_info, $razao_coluna);
            $razao = trim($razao);
        }

        if (empty($razao)) {
            $nome = trim($order_info['firstname'] . ' ' . $order_info['lastname']);
            if (empty($nome)) {
                $erros['nome'] = $this->language->get('error_nome');
            }
        }

        $documento = '';
        if (in_array($custom_cnpj_id, $campos)) {
            $documento = $this->campo_valor($order_info['custom_field'], $custom_cnpj_id, $colunas_info, $cnpj_coluna);
            $documento = trim($documento);
        }

        if (in_array($custom_cpf_id, $campos) && empty($documento)) {
            $documento = $this->campo_valor($order_info['custom_field'], $custom_cpf_id, $colunas_info, $cpf_coluna);
            $documento = trim($documento);
        }

        $documento = preg_replace("/[^0-9]/", '', $documento);
        $documento = strlen($documento);
        if ($documento == 14 || $documento == 11) {
        } else {
            $erros['documento'] = $this->language->get('error_documento');
        }

        $telefone = strlen(preg_replace("/[^0-9]/", '', trim($order_info['telephone'])));
        if ($telefone < 10 || $telefone > 11) {
            $erros['telefone'] = $this->language->get('error_telefone');
        }

        $cep = preg_replace("/[^0-9]/", '', trim($order_info['payment_postcode']));
        if (strlen($cep) != 8) {
            $erros['cep'] = $this->language->get('error_pagamento_cep');
        }

        $endereco = $this->sanitize_string($order_info['payment_address_1']);
        if (empty($endereco)) {
            $erros['endereco'] = $this->language->get('error_pagamento_endereco');
        }

        $numero = $this->campo_valor($order_info['payment_custom_field'], $custom_numero_id, $colunas_info, $numero_coluna);
        $numero = preg_replace("/[^0-9]/", '', $numero);
        if (strlen($numero) < 1) {
            $erros['numero'] = $this->language->get('error_pagamento_numero');
        }

        $bairro = $this->sanitize_string($order_info['payment_address_2']);
        if (empty($bairro)) {
            $erros['bairro'] = $this->language->get('error_pagamento_bairro');
        }

        $cidade = $this->sanitize_string($order_info['payment_city']);
        if (empty($cidade)) {
            $erros['cidade'] = $this->language->get('error_pagamento_cidade');
        }

        $estado = $this->sanitize_string($order_info['payment_zone_code']);
        if (empty($estado)) {
            $erros['estado'] = $this->language->get('error_pagamento_estado');
        }

        if (count($erros) > 0) {
            $resultado = '';

            foreach ($erros as $key => $value) {
                $resultado .= $value;
            }

            return $resultado;
        } else {
            return '';
        }
    }

    private function sanitize_string($string) {
        $substituir = array('&amp;', '&');
        $string = str_replace($substituir, 'E', $string);

        $remover = array('(', ')', 'º', 'ª', '|');
        $string = str_replace($remover, '', $string);

        if ($string !== mb_convert_encoding(mb_convert_encoding($string, 'UTF-32', 'UTF-8'), 'UTF-8', 'UTF-32'))
            $string = mb_convert_encoding($string, 'UTF-8', mb_detect_encoding($string));

        $string = htmlentities($string, ENT_NOQUOTES, 'UTF-8');
        $string = preg_replace('`&([a-z]{1,2})(acute|uml|circ|grave|ring|cedil|slash|tilde|caron|lig);`i', '\1', $string);
        $string = html_entity_decode($string, ENT_NOQUOTES, 'UTF-8');
        $string = preg_replace(array('`[^a-z0-9]`i','`[-]+`'), ' ', $string);

        $string = preg_replace('/[\n\t\r]/', ' ', $string);
        $string = preg_replace('/( ){2,}/', '$1', $string);
        $string = trim($string);

        return strtoupper($string);
    }
}