<?php
class ControllerExtensionPaymentCieloApiDebito extends Controller {
    const EXTENSION = 'payment_cielo_api_debito';

    public function index() {
        $data = $this->load->language('extension/payment/cielo_api_debito');

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

        $data['cor_normal_texto'] = $this->config->get(self::EXTENSION . '_cor_normal_texto');
        $data['cor_normal_fundo'] = $this->config->get(self::EXTENSION . '_cor_normal_fundo');
        $data['cor_normal_borda'] = $this->config->get(self::EXTENSION . '_cor_normal_borda');
        $data['cor_efeito_texto'] = $this->config->get(self::EXTENSION . '_cor_efeito_texto');
        $data['cor_efeito_fundo'] = $this->config->get(self::EXTENSION . '_cor_efeito_fundo');
        $data['cor_efeito_borda'] = $this->config->get(self::EXTENSION . '_cor_efeito_borda');

        $data['estilo_botao'] = $this->config->get(self::EXTENSION . '_estilo_botao_b3');
        $data['texto_botao'] = $this->config->get(self::EXTENSION . '_texto_botao');
        $data['container_botao'] = $this->config->get(self::EXTENSION . '_container_botao');

        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        $data['total'] = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], true);

        $i = 1;
        $bandeiras = array();
        foreach ($this->bandeiras() as $bandeira) {
            ($this->config->get(self::EXTENSION . '_' . $bandeira)) ? $bandeiras[] = array('bandeira' => $bandeira, 'titulo' => strtoupper($bandeira), 'imagem' => HTTPS_SERVER .'image/catalog/cielo_api/'. $bandeira .'_debito.png') : '';
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

        if (!isset($this->session->data['attempts'])) {
            $this->session->data['attempts'] = 6;
        } else if ($data['ambiente'] == '1') {
            $this->session->data['attempts'] = 6;
        }

        $data['alerta'] = '';
        if (isset($this->session->data['cielo_api_debito_erro']) && !empty($this->session->data['cielo_api_debito_erro'])) {
            $data['alerta'] = $this->session->data['cielo_api_debito_erro'];
        } else if (isset($this->session->data['attempts']) && $this->session->data['attempts'] <= 0) {
            $data['alerta'] = $this->language->get('error_tentativas');
        }

        $tema = $this->config->get(self::EXTENSION . '_tema');

        return $this->load->view('extension/payment/cielo_api/debito_'. $tema, $data);
    }

    public function retentativa() {
        if (isset($this->session->data['order_id']) && isset($this->session->data['payment_method']['code']) && $this->session->data['payment_method']['code'] == 'cielo_api_debito') {
            $data = $this->load->language('extension/payment/cielo_api_retentativa');

            $this->document->setTitle($this->language->get('heading_title'));

            $data['breadcrumbs'] = array();

            $data['breadcrumbs'][] = array(
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/home')
            );

            $data['breadcrumbs'][] = array(
                'text' => $this->language->get('text_basket'),
                'href' => $this->url->link('checkout/cart')
            );

            $data['breadcrumbs'][] = array(
                'text' => $this->language->get('text_checkout'),
                'href' => $this->url->link('checkout/checkout', '', true)
            );

            $data['breadcrumbs'][] = array(
                'text' => $this->language->get('text_retentativa'),
                'href' => HTTPS_SERVER . 'cielo/api/debito/retentativa'
            );

            include_once(DIR_SYSTEM . 'library/cielo_api/versao.php');

            $this->document->addStyle('catalog/view/theme/default/template/extension/payment/cielo_api/assets/css/normalize.css?v='. $data['versao']);
            $this->document->addStyle('catalog/view/theme/default/template/extension/payment/cielo_api/assets/css/skeleton.css?v='. $data['versao']);
            $this->document->addScript('catalog/view/theme/default/template/extension/payment/cielo_api/assets/js/jquery.loadingoverlay.min.js?v='. $data['versao']);

            $data['ambiente'] = $this->config->get(self::EXTENSION . '_ambiente');

            $data['instrucoes'] = '';
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

            $data['falhou'] = false;

            if (isset($this->session->data['falhou'])) {
                $data['falhou'] = $this->session->data['falhou'];
                unset($this->session->data['falhou']);
            }

            $this->load->model('checkout/order');
            $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

            $data['total'] = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], true);

            $i = 1;
            $bandeiras = array();
            foreach ($this->bandeiras() as $bandeira) {
                ($this->config->get(self::EXTENSION . '_' . $bandeira)) ? $bandeiras[] = array('bandeira' => $bandeira, 'titulo' => strtoupper($bandeira), 'imagem' => HTTPS_SERVER .'image/catalog/cielo_api/'. $bandeira .'_debito.png') : '';
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

            if (!isset($this->session->data['attempts'])) {
                $this->session->data['attempts'] = 6;
            } else if ($data['ambiente'] == '1') {
                $this->session->data['attempts'] = 6;
            }

            $tema = $this->config->get(self::EXTENSION . '_tema');

            $data['column_left'] = $this->load->controller('common/column_left');
            $data['column_right'] = $this->load->controller('common/column_right');
            $data['content_top'] = $this->load->controller('common/content_top');
            $data['content_bottom'] = $this->load->controller('common/content_bottom');
            $data['footer'] = $this->load->controller('common/footer');
            $data['header'] = $this->load->controller('common/header');

            $this->response->setOutput($this->load->view('extension/payment/cielo_api/retentativa_'. $tema, $data));
        } else {
            $this->response->redirect($this->url->link('error/not_found'));
        }
    }

    public function transacao() {
        $json = array();

        $data = $this->language->load('extension/payment/cielo_api_debito');

        if ($this->validar_basico() && $this->validar_post()) {
            $order_id = $this->session->data['order_id'];

            if ($this->session->data['attempts'] > 0) {
                $cartao_bandeira = $this->limpar_string(strtolower($this->request->post['bandeira']));
                $cartao_nome = $this->limpar_string($this->request->post['nome']);
                $cartao_numero = preg_replace("/[^0-9]/", '', $this->request->post['cartao']);
                $cartao_mes = preg_replace("/[^0-9]/", '', $this->request->post['mes']);
                $cartao_ano = preg_replace("/[^0-9]/", '', $this->request->post['ano']);
                $cartao_cvv = preg_replace("/[^0-9]/", '', $this->request->post['codigo']);

                $campos = array($cartao_bandeira, $cartao_nome, $cartao_numero, $cartao_mes, $cartao_ano, $cartao_cvv);

                if ($this->validar_campos($campos) && $this->validar_bandeira($cartao_bandeira)) {
                    $this->session->data['attempts']--;

                    $this->load->model('extension/payment/cielo_api_debito');
                    $pedido_pago = $this->model_extension_payment_cielo_api_debito->getTransactionPaid($order_id);

                    if ($pedido_pago == false) {
                        $this->atualizar_pedido();

                        $this->load->model('checkout/order');
                        $order_info = $this->model_checkout_order->getOrder($order_id);

                        $total = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false);
                        $total = number_format($total, 2, '', '');

                        $bandeiras_cielo = array(
                            'visa' => 'Visa',
                            'mastercard' => 'Master',
                            'elo' => 'Elo'
                        );

                        $dados['MerchantOrderId'] = $order_id;

                        $dados['Customer'] = trim($order_info['firstname'] . ' ' . $order_info['lastname']);

                        $dados['Amount'] = $total;
                        $dados['SoftDescriptor'] = $this->config->get(self::EXTENSION . '_soft_descriptor');

                        $dados['CardNumber'] = $cartao_numero;
                        $dados['Holder'] = $cartao_nome;
                        $dados['ExpirationDate'] = $cartao_mes . '/' . $cartao_ano;
                        $dados['SecurityCode'] = $cartao_cvv;
                        $dados['Brand'] = $bandeiras_cielo[$cartao_bandeira];

                        $dados['ReturnUrl'] = HTTPS_SERVER . 'cielo/api/debito/retorno';

                        $chave = $this->config->get(self::EXTENSION . '_chave');
                        $dados['Chave'] = $chave[$this->config->get('config_store_id')];
                        $dados['Debug'] = $this->config->get(self::EXTENSION . '_debug');
                        $dados['Ambiente'] = $this->config->get(self::EXTENSION . '_ambiente');
                        $dados['MerchantId'] = $this->config->get(self::EXTENSION . '_merchantid');
                        $dados['MerchantKey'] = $this->config->get(self::EXTENSION . '_merchantkey');

                        require_once(DIR_SYSTEM . 'library/cielo_api/cielo.php');
                        $cielo = new Cielo();
                        $cielo->setParametros($dados);
                        $resposta = $cielo->setTransacaoDebito();

                        if ($resposta) {
                            if (isset($resposta->Payment->Status)) {
                                $payment_status = $resposta->Payment->Status;

                                switch ($payment_status) {
                                    case '0':
                                        $campos = array();
                                        $campos['order_id'] = $order_id;
                                        $campos['paymentId'] = $resposta->Payment->PaymentId;
                                        $campos['tipo'] = 'DebitCard';
                                        $campos['tid'] = $resposta->Payment->Tid;
                                        $campos['parcelas'] = '1';
                                        $campos['bandeira'] = $resposta->Payment->DebitCard->Brand;
                                        $campos['status'] = $payment_status;
                                        $campos['json'] = json_encode($resposta, JSON_HEX_QUOT|JSON_HEX_APOS);

                                        $this->session->data['cielo_api_id'] = $this->model_extension_payment_cielo_api_debito->addTransaction($campos);

                                        $this->model_checkout_order->addOrderHistory($order_id, $this->config->get(self::EXTENSION . '_situacao_pendente_id'), $this->language->get('text_pendente'), true);

                                        $json['redirect'] = $resposta->Payment->AuthenticationUrl;

                                        break;
                                    case '2':
                                        $tid = $resposta->Payment->Tid;
                                        $brand = $resposta->Payment->DebitCard->Brand;
                                        $payment_date = $resposta->Payment->CapturedDate;
                                        $payment_amount = $resposta->Payment->CapturedAmount;

                                        $campos = array();
                                        $campos['order_id'] = $order_id;
                                        $campos['paymentId'] = $resposta->Payment->PaymentId;
                                        $campos['tipo'] = 'DebitCard';
                                        $campos['tid'] = $tid;
                                        $campos['parcelas'] = '1';
                                        $campos['bandeira'] = $brand;
                                        $campos['status'] = $payment_status;
                                        $campos['nsu'] = $resposta->Payment->ProofOfSale;
                                        $campos['authorizationCode'] = $resposta->Payment->AuthorizationCode;
                                        $campos['bin'] = substr($resposta->Payment->DebitCard->CardNumber, 0, 6);
                                        $campos['fim'] = substr($resposta->Payment->DebitCard->CardNumber, -4);
                                        $campos['holder'] = $resposta->Payment->DebitCard->Holder;
                                        $campos['eci'] = (isset($resposta->Payment->Eci)) ? $resposta->Payment->Eci : '';
                                        $campos['autorizacaoData'] = $resposta->Payment->ReceivedDate;
                                        $campos['autorizacaoValor'] = $resposta->Payment->Amount;
                                        $campos['capturaData'] = $payment_date;
                                        $campos['capturaValor'] = $payment_amount;
                                        $campos['json'] = json_encode($resposta, JSON_HEX_QUOT|JSON_HEX_APOS);

                                        $date = date('d/m/Y H:i', strtotime($payment_date));
                                        $amount = $this->currency->format(($payment_amount / 100), $order_info['currency_code'], '1.00', true);
                                        $status = $this->language->get('text_capturado');
                                        $order_status_id = $this->config->get(self::EXTENSION . '_situacao_capturada_id');

                                        $comment = $this->language->get('entry_pedido') . $order_id . "\n";
                                        $comment .= $this->language->get('entry_data') . $date . "\n";
                                        $comment .= $this->language->get('entry_tid') . $tid . "\n";
                                        $comment .= $this->language->get('entry_tipo') . $this->language->get('text_cartao_debito') . "\n";
                                        $comment .= $this->language->get('entry_bandeira') . strtoupper($brand) . "\n";
                                        $comment .= $this->language->get('entry_total') . $amount . "\n";
                                        $comment .= $this->language->get('entry_status') . $status;

                                        if (isset($this->session->data['cielo_api_debito_comprovante'])) {
                                            unset($this->session->data['cielo_api_debito_comprovante']);
                                        }
                                        $this->session->data['cielo_api_debito_comprovante'] = $this->language->get('text_comprovante') . "\n" . $comment;

                                        $this->model_extension_payment_cielo_api_debito->addTransaction($campos);

                                        $this->model_checkout_order->addOrderHistory($order_id, $order_status_id, $comment, true);

                                        $this->session->data['attempts'] = 6;

                                        $json['redirect'] = $this->url->link('checkout/success', '', true);

                                        break;
                                    default:
                                        $this->model_checkout_order->addOrderHistory($order_id, $this->config->get(self::EXTENSION . '_situacao_nao_autorizada_id'), $this->language->get('text_nao_autorizado'), true);

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
                    $json['error'] = $this->language->get('error_preenchimento');
                }
            } else {
                if ($this->session->data['attempts'] == 0) {
                    $this->session->data['attempts']--;

                    $this->load->model('checkout/order');
                    $this->model_checkout_order->addOrderHistory($order_id, $this->config->get(self::EXTENSION . '_situacao_nao_autorizada_id'), $this->language->get('text_tentativas'), true);
                }

                $json['error'] = $this->language->get('error_tentativas');
            }
        } else {
            $json['error'] = $this->language->get('error_permissao');
        }

        if (isset($json['error']) && !empty($json['error'])) { $this->session->data['cielo_api_debito_erro'] = $json['error']; }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function retorno() {
        $data = $this->language->load('extension/payment/cielo_api_debito');

        if (isset($this->session->data['order_id']) && isset($this->session->data['cielo_api_id'])) {
            $order_id = $this->session->data['order_id'];
            $cielo_api_id = $this->session->data['cielo_api_id'];

            if (empty($order_id) && empty($cielo_api_id)) {
                $this->response->redirect($this->url->link('error/not_found'));
            } elseif (!empty($order_id) && empty($cielo_api_id)) {
                $this->response->redirect(HTTPS_SERVER . 'cielo/api/debito/retentativa');
            }

            $this->load->model('extension/payment/cielo_api_debito');
            $pedido_pago = $this->model_extension_payment_cielo_api_debito->getTransactionPaid($order_id);

            if ($pedido_pago == true) {
                $this->response->redirect($this->url->link('checkout/success', '', true));
            }

            $this->session->data['falhou'] = true;

            $transaction_info = $this->model_extension_payment_cielo_api_debito->getTransaction($cielo_api_id);

            if ($transaction_info) {
                $chave = $this->config->get(self::EXTENSION . '_chave');
                $dados['Chave'] = $chave[$this->config->get('config_store_id')];
                $dados['Debug'] = $this->config->get(self::EXTENSION . '_debug');
                $dados['Ambiente'] = $this->config->get(self::EXTENSION . '_ambiente');
                $dados['MerchantId'] = $this->config->get(self::EXTENSION . '_merchantid');
                $dados['MerchantKey'] = $this->config->get(self::EXTENSION . '_merchantkey');
                $dados['PaymentId'] = $transaction_info['paymentId'];

                require_once(DIR_SYSTEM . 'library/cielo_api/cielo.php');
                $cielo = new Cielo();
                $cielo->setParametros($dados);
                $resposta = $cielo->getTransacao();

                if (isset($resposta->Payment->Status)) {
                    $colunas = array('currency_code');

                    $order_info = $this->model_extension_payment_cielo_api_debito->getOrder($colunas, $order_id);
                    if ($order_info) {
                        $this->load->model('checkout/order');

                        $payment_status = $resposta->Payment->Status;
                        if ($payment_status == '1' || $payment_status == '2') {
                            $campos = array();
                            $campos['order_cielo_api_id'] = $transaction_info['order_cielo_api_id'];
                            $campos['status'] = $payment_status;
                            $campos['nsu'] = $resposta->Payment->ProofOfSale;
                            $campos['authorizationCode'] = $resposta->Payment->AuthorizationCode;
                            $campos['bin'] = substr($resposta->Payment->DebitCard->CardNumber, 0, 6);
                            $campos['fim'] = substr($resposta->Payment->DebitCard->CardNumber, -4);
                            $campos['holder'] = $resposta->Payment->DebitCard->Holder;
                            $campos['eci'] = (isset($resposta->Payment->Eci)) ? $resposta->Payment->Eci : '';
                            $campos['autorizacaoData'] = $resposta->Payment->ReceivedDate;
                            $campos['autorizacaoValor'] = $resposta->Payment->Amount;
                            if (isset($resposta->Payment->ReceivedDate) && isset($resposta->Payment->CapturedAmount)) {
                                $campos['capturaData'] = $resposta->Payment->CapturedDate;
                                $campos['capturaValor'] = $resposta->Payment->CapturedAmount;
                            } else {
                                $campos['capturaData'] = '';
                                $campos['capturaValor'] = '';
                            }
                            $campos['json'] = json_encode($resposta, JSON_HEX_QUOT|JSON_HEX_APOS);

                            if ($payment_status == '1') {
                                $payment_date = $resposta->Payment->ReceivedDate;
                                $payment_amount = $resposta->Payment->Amount;
                                $status = $this->language->get('text_autorizado');
                                $order_status_id = $this->config->get(self::EXTENSION . '_situacao_autorizada_id');
                            } else if ($payment_status == '2') {
                                $payment_date = $resposta->Payment->CapturedDate;
                                $payment_amount = $resposta->Payment->CapturedAmount;
                                $status = $this->language->get('text_capturado');
                                $order_status_id = $this->config->get(self::EXTENSION . '_situacao_capturada_id');
                            }

                            $tid = $resposta->Payment->Tid;
                            $brand = $resposta->Payment->DebitCard->Brand;
                            $date = date('d/m/Y H:i', strtotime($payment_date));
                            $amount = $this->currency->format(($payment_amount / 100), $order_info['currency_code'], '1.00', true);

                            $comment = $this->language->get('entry_pedido') . $order_id . "\n";
                            $comment .= $this->language->get('entry_data') . $date . "\n";
                            $comment .= $this->language->get('entry_tid') . $tid . "\n";
                            $comment .= $this->language->get('entry_tipo') . $this->language->get('text_cartao_debito') . "\n";
                            $comment .= $this->language->get('entry_bandeira') . strtoupper($brand) . "\n";
                            $comment .= $this->language->get('entry_total') . $amount . "\n";
                            $comment .= $this->language->get('entry_status') . $status;

                            if (isset($this->session->data['cielo_api_debito_comprovante'])) {
                                unset($this->session->data['cielo_api_debito_comprovante']);
                            }
                            $this->session->data['cielo_api_debito_comprovante'] = $this->language->get('text_comprovante') . "\n" . $comment;

                            $this->model_extension_payment_cielo_api_debito->updateTransaction($campos);

                            $this->model_checkout_order->addOrderHistory($order_id, $order_status_id, $comment, true);

                            $this->session->data['attempts'] = 6;

                            $this->response->redirect($this->url->link('checkout/success', '', true));
                        } else {
                            $campos['order_cielo_api_id'] = $transaction_info['order_cielo_api_id'];
                            $campos['status'] = $payment_status;
                            $campos['json'] = json_encode($resposta, JSON_HEX_QUOT|JSON_HEX_APOS);

                            $order_status_id = $this->config->get(self::EXTENSION . '_situacao_nao_autorizada_id');
                            $comment = $this->language->get('text_nao_autorizado');

                            $this->model_extension_payment_cielo_api_debito->updateTransactionStatus($campos);

                            $this->model_checkout_order->addOrderHistory($order_id, $order_status_id, $comment, true);
                        }
                    }
                }
            }

            $this->response->redirect(HTTPS_SERVER . 'cielo/api/debito/retentativa');
        } else {
            $this->response->redirect($this->url->link('error/not_found'));
        }
    }

    private function bandeiras() {
        return array(
            "visa",
            "mastercard",
            "elo"
        );
    }

    private function limpar_string($string) {
        $string = strip_tags($string);
        $string = preg_replace('/[\n\t\r]/', ' ', $string);
        $string = preg_replace('/( ){2,}/', '$1', $string);

        return trim($string);
    }

    private function validar_basico() {
        if (
            isset($this->session->data['order_id']) &&
            isset($this->session->data['payment_method']['code']) &&
            isset($this->session->data['attempts']) &&
            $this->session->data['payment_method']['code'] == 'cielo_api_debito' &&
            $this->session->data['attempts'] >= 0 &&
            $this->session->data['attempts'] <= 6
        ) {
            return true;
        }

        return false;
    }

    private function validar_post() {
        $campos = array('bandeira', 'nome', 'cartao', 'mes', 'ano', 'codigo');

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

        return in_array($bandeira, $bandeiras);
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

        $this->load->model('extension/payment/cielo_api_debito');
        $this->model_extension_payment_cielo_api_debito->editOrder($order_data, $this->session->data['order_id']);
    }
}