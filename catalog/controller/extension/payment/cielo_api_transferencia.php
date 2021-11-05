<?php
class ControllerExtensionPaymentCieloApiTransferencia extends Controller {
    const EXTENSION = 'payment_cielo_api_transferencia';

    public function index() {
        $data = $this->load->language('extension/payment/cielo_api_transferencia');

        include_once(DIR_SYSTEM . 'library/cielo_api/versao.php');

        $data['ambiente'] = $this->config->get(self::EXTENSION . '_ambiente');

        $data['instrucoes'] = $this->language->get('text_mensagem');
        if ($this->config->get(self::EXTENSION . '_information_id')) {
            $this->load->model('catalog/information');
            $information_info = $this->model_catalog_information->getInformation($this->config->get(self::EXTENSION . '_information_id'));

            if ($information_info) {
                $data['instrucoes'] = html_entity_decode($information_info['description'], ENT_QUOTES, 'UTF-8');
            }
        }

        $data['cor_normal_texto'] = $this->config->get(self::EXTENSION . '_cor_normal_texto');
        $data['cor_normal_fundo'] = $this->config->get(self::EXTENSION . '_cor_normal_fundo');
        $data['cor_normal_borda'] = $this->config->get(self::EXTENSION . '_cor_normal_borda');
        $data['cor_efeito_texto'] = $this->config->get(self::EXTENSION . '_cor_efeito_texto');
        $data['cor_efeito_fundo'] = $this->config->get(self::EXTENSION . '_cor_efeito_fundo');
        $data['cor_efeito_borda'] = $this->config->get(self::EXTENSION . '_cor_efeito_borda');

        $data['estilo_botao'] = $this->config->get(self::EXTENSION . '_estilo_botao_b3');
        $data['texto_botao'] = $this->config->get(self::EXTENSION . '_texto_botao');

        $data['alerta'] = '';
        if (isset($this->session->data['cielo_api_transferencia_erro'])) {
            $data['alerta'] = $this->session->data['cielo_api_transferencia_erro'];
        }

        $tema = $this->config->get(self::EXTENSION . '_tema');

        return $this->load->view('extension/payment/cielo_api/transferencia_'. $tema, $data);
    }

    public function transacao() {
        $json = array();

        if (isset($this->session->data['order_id']) && $this->session->data['payment_method']['code'] == 'cielo_api_transferencia') {
            $this->language->load('extension/payment/cielo_api_transferencia');

            $erros_cadastro = $this->validar_cadastro();
            if (empty($erros_cadastro)) {
                $order_id = $this->session->data['order_id'];

                $this->load->model('extension/payment/cielo_api_transferencia');

                $this->load->model('checkout/order');
                $order_info = $this->model_checkout_order->getOrder($order_id);

                $total = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false);
                $total = number_format($total, 2, '', '');

                $custom_razao_id = $this->config->get(self::EXTENSION . '_custom_razao_id');
                $custom_cnpj_id = $this->config->get(self::EXTENSION . '_custom_cnpj_id');
                $custom_cpf_id = $this->config->get(self::EXTENSION . '_custom_cpf_id');
                $custom_numero_id = $this->config->get(self::EXTENSION . '_custom_numero_id');
                $custom_complemento_id = $this->config->get(self::EXTENSION . '_custom_complemento_id');

                $razao_coluna = $this->config->get(self::EXTENSION . '_razao_coluna');
                $cnpj_coluna = $this->config->get(self::EXTENSION . '_cnpj_coluna');
                $cpf_coluna = $this->config->get(self::EXTENSION . '_cpf_coluna');
                $numero_coluna = $this->config->get(self::EXTENSION . '_numero_fatura_coluna');
                $complemento_coluna = $this->config->get(self::EXTENSION . '_complemento_fatura_coluna');

                $colunas = array();
                $colunas_info = array();

                $campos = $this->campos();

                if (in_array($custom_razao_id, $campos) && $custom_razao_id == 'N') { array_push($colunas, $razao_coluna); }
                if (in_array($custom_cnpj_id, $campos) && $custom_cnpj_id == 'N') { array_push($colunas, $cnpj_coluna); }
                if (in_array($custom_cpf_id, $campos) && $custom_cpf_id == 'N') { array_push($colunas, $cpf_coluna); }
                if ($custom_numero_id == 'N') { array_push($colunas, $numero_coluna); }
                if ($custom_complemento_id == 'N') { array_push($colunas, $complemento_coluna); }

                if (count($colunas)) {
                    $colunas_info = $this->model_extension_payment_cielo_api_transferencia->getOrder($colunas, $order_id);
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

                $billing_number = $this->campo_valor($order_info['payment_custom_field'], $custom_numero_id, $colunas_info, $numero_coluna);
                $billing_complement = $this->campo_valor($order_info['payment_custom_field'], $custom_complemento_id, $colunas_info, $complemento_coluna);

                $dados['MerchantOrderId'] = $order_id;

                $dados['Customer'] = $customer_name;
                $dados['Identity'] = $document_number;
                $dados['Email'] = $order_info['email'];

                $dados['Street'] = $order_info['payment_address_1'];
                $dados['Number'] = $billing_number;
                $dados['Complement'] = $billing_complement;
                $dados['District'] = $order_info['payment_address_2'];
                $dados['ZipCode'] = $order_info['payment_postcode'];
                $dados['City'] = $order_info['payment_city'];
                $dados['State'] = $order_info['payment_zone_code'];

                $dados['Amount'] = $total;
                $dados['Provider'] = $this->config->get(self::EXTENSION . '_banco');
                $dados['ReturnUrl'] = $this->url->link('checkout/success', '', true);

                $chave = $this->config->get(self::EXTENSION . '_chave');
                $dados['Chave'] = $chave[$this->config->get('config_store_id')];
                $dados['Debug'] = $this->config->get(self::EXTENSION . '_debug');
                $dados['Ambiente'] = $this->config->get(self::EXTENSION . '_ambiente');
                $dados['MerchantId'] = $this->config->get(self::EXTENSION . '_merchantid');
                $dados['MerchantKey'] = $this->config->get(self::EXTENSION . '_merchantkey');

                require_once(DIR_SYSTEM . 'library/cielo_api/cielo.php');
                $cielo = new Cielo();
                $cielo->setParametros($dados);
                $resposta = $cielo->setTransacaoTransferencia();

                if ($resposta) {
                    if (isset($resposta->Payment->Url)) {
                        $campos = array(
                            'order_id' => $order_id,
                            'paymentId' => $resposta->Payment->PaymentId,
                            'status' => $resposta->Payment->Status,
                            'tipo' => $resposta->Payment->Type,
                            'transferenciaData' => $resposta->Payment->ReceivedDate,
                            'transferenciaValor' => $resposta->Payment->Amount,
                            'json' => json_encode($resposta, JSON_HEX_QUOT|JSON_HEX_APOS)
                        );

                        $transferencia_url = $resposta->Payment->Url;

                        $comment = $this->language->get('text_mensagem') . "\n\n";
                        $comment .= sprintf($this->language->get('text_retentativa'), $transferencia_url);

                        if (isset($this->session->data['cielo_api_transferencia_instrucoes'])) {
                            unset($this->session->data['cielo_api_transferencia_instrucoes']);
                        }
                        $this->session->data['cielo_api_transferencia_instrucoes'] = sprintf($this->language->get('text_instrucoes'), $transferencia_url);

                        $this->model_extension_payment_cielo_api_transferencia->addTransaction($campos);

                        $this->model_checkout_order->addOrderHistory($order_id, $this->config->get(self::EXTENSION . '_situacao_gerada_id'), $comment, true);

                        $json['redirect'] = $this->url->link('checkout/success', '', true);
                    } else {
                        $json['error'] = $this->language->get('error_nao_gerou');
                    }
                } else {
                    $json['error'] = $this->language->get('error_nao_gerou');
                }
            } else {
                $json['error'] = sprintf($this->language->get('error_validacao'), $erros_cadastro);
            }
        }

        if (isset($json['error']) && !empty($json['error'])) { $this->session->data['cielo_api_transferencia_erro'] = $json['error']; }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
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

        $this->load->model('extension/payment/cielo_api_transferencia');
        $this->model_extension_payment_cielo_api_transferencia->editOrder($order_data, $this->session->data['order_id']);
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
            $this->load->model('extension/payment/cielo_api_transferencia');
            $colunas_info = $this->model_extension_payment_cielo_api_transferencia->getOrder($colunas, $order_id);
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
            return false;
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