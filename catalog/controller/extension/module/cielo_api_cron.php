<?php
class ControllerExtensionModuleCieloApiCron extends Controller {
    const EXTENSION = 'module_cielo_api_cron';

    public function index() {
        if ($this->config->get(self::EXTENSION . '_status')) {
            $this->load->language('extension/module/cielo_api_cron');

            if (isset($this->request->get['key'])) {
                if ($this->config->get(self::EXTENSION . '_chave_cron') == $this->request->get['key']) {
                    $this->debug($this->language->get('text_cron_iniciada'));

                    $this->load->model('extension/module/cielo_api_cron');
                    $transactions = $this->model_extension_module_cielo_api_cron->getTransactions();

                    foreach ($transactions as $transaction) {
                        $store_id = $transaction['store_id'];
                        $currency_code = $transaction['currency_code'];
                        $order_status_id = $transaction['order_status_id'];
                        $order_cielo_api_id = $transaction['order_cielo_api_id'];
                        $this->consultar($store_id, $currency_code, $order_status_id, $order_cielo_api_id);
                    }

                    $this->debug($this->language->get('text_cron_encerrada'));
                } else {
                    $this->debug($this->language->get('error_cron_invalida'));
                    $this->response->redirect($this->url->link('error/not_found'));
                }
            } else {
                $this->debug($this->language->get('error_cron_negada'));
                $this->response->redirect($this->url->link('error/not_found'));
            }
        }
    }

    private function consultar($store_id, $currency_code, $order_status_id, $order_cielo_api_id) {
        $this->load->model('extension/module/cielo_api_cron');
        $transaction_info = $this->model_extension_module_cielo_api_cron->getTransaction($order_cielo_api_id);

        $transaction_type = $transaction_info['tipo'];

        if ($transaction_type == 'DebitCard') {
            $tipo = 'debito';
        } else if ($transaction_type == 'EletronicTransfer') {
            $tipo = 'transferencia';
        } else if ($transaction_type == 'Boleto') {
            $tipo = 'boleto';
        }

        $chave = $this->config->get('payment_cielo_api_'. $tipo .'_chave');
        $dados['Chave'] = $chave[$store_id];
        $dados['Debug'] = $this->config->get('payment_cielo_api_'. $tipo .'_debug');
        $dados['Ambiente'] = $this->config->get('payment_cielo_api_'. $tipo .'_ambiente');
        $dados['MerchantId'] = $this->config->get('payment_cielo_api_'. $tipo .'_merchantid');
        $dados['MerchantKey'] = $this->config->get('payment_cielo_api_'. $tipo .'_merchantkey');
        $dados['PaymentId'] = $transaction_info['paymentId'];

        require_once(DIR_SYSTEM . 'library/cielo_api/cielo.php');
        $cielo = new Cielo();
        $cielo->setParametros($dados);
        $resposta = $cielo->getTransacao();

        if ($resposta) {
            if (!empty($resposta->Payment)) {
                $order_id = $transaction_info['order_id'];
                $payment_status = $resposta->Payment->Status;

                switch ($payment_status) {
                    case '0':
                        if ($tipo == 'debito') {
                            $comment = $this->language->get('text_pendente');
                            $order_status_id = $this->config->get('payment_cielo_api_debito_situacao_pendente_id');
                        } else if ($tipo == 'transferencia') {
                            $comment = $this->language->get('text_gerada');
                            $order_status_id = $this->config->get('payment_cielo_api_transferencia_situacao_gerada_id');
                        }

                        break;
                    case '1':
                        if ($tipo == 'debito') {
                            $tid = $resposta->Payment->Tid;
                            $brand = $resposta->Payment->DebitCard->Brand;
                            $date = date('d/m/Y H:i', strtotime($resposta->Payment->ReceivedDate));
                            $amount = $this->currency->format(($resposta->Payment->Amount / 100), $currency_code, '1.00', true);

                            $comment = $this->language->get('entry_pedido') . $order_id . "\n";
                            $comment .= $this->language->get('entry_data') . $date . "\n";
                            $comment .= $this->language->get('entry_tid') . $tid . "\n";
                            $comment .= $this->language->get('entry_tipo') . $this->language->get('text_cartao_debito') . "\n";
                            $comment .= $this->language->get('entry_bandeira') . strtoupper($brand) . "\n";
                            $comment .= $this->language->get('entry_total') . $amount . "\n";
                            $comment .= $this->language->get('entry_status') . $this->language->get('text_autorizado');

                            $order_status_id = $this->config->get('payment_cielo_api_debito_situacao_autorizada_id');
                        } else if ($tipo == 'boleto') {
                            $comment = $this->language->get('text_gerado');
                            $order_status_id = $this->config->get('payment_cielo_api_boleto_situacao_gerado_id');
                        }

                        break;
                    case '2':
                        if ($tipo == 'debito') {
                            $tid = $resposta->Payment->Tid;
                            $brand = $resposta->Payment->DebitCard->Brand;
                            $date = date('d/m/Y H:i', strtotime($resposta->Payment->CapturedDate));
                            $amount = $this->currency->format(($resposta->Payment->CapturedAmount / 100), $currency_code, '1.00', true);

                            $comment = $this->language->get('entry_pedido') . $order_id . "\n";
                            $comment .= $this->language->get('entry_data') . $date . "\n";
                            $comment .= $this->language->get('entry_tid') . $tid . "\n";
                            $comment .= $this->language->get('entry_tipo') . $this->language->get('text_cartao_debito') . "\n";
                            $comment .= $this->language->get('entry_bandeira') . strtoupper($brand) . "\n";
                            $comment .= $this->language->get('entry_total') . $amount . "\n";
                            $comment .= $this->language->get('entry_status') . $this->language->get('text_capturado');

                            $order_status_id = $this->config->get('payment_cielo_api_debito_situacao_capturada_id');
                        } else if ($tipo == 'transferencia') {
                            $comment = $this->language->get('text_paga');
                            $order_status_id = $this->config->get('payment_cielo_api_transferencia_situacao_paga_id');
                        } else if ($tipo == 'boleto') {
                            $comment = $this->language->get('text_pago');
                            $order_status_id = $this->config->get('payment_cielo_api_boleto_situacao_pago_id');
                        }

                        break;
                    case '3':
                        if ($tipo == 'debito') {
                            $comment = $this->language->get('text_nao_autorizado');
                            $order_status_id = $this->config->get('payment_cielo_api_debito_situacao_nao_autorizada_id');
                        } else if ($tipo == 'transferencia') {
                            $comment = $this->language->get('text_negada');
                            $order_status_id = $this->config->get('payment_cielo_api_transferencia_situacao_negada_id');
                        }

                        break;
                    case '12':
                        if ($tipo == 'transferencia') {
                            $comment = $this->language->get('text_pendente');
                            $order_status_id = $this->config->get('payment_cielo_api_transferencia_situacao_pendente_id');
                        } else if ($tipo == 'boleto') {
                            $comment = $this->language->get('text_pendente');
                            $order_status_id = $this->config->get('payment_cielo_api_boleto_situacao_pendente_id');
                        }

                        break;
                    case '10':
                    case '13':
                        if ($tipo == 'transferencia') {
                            $comment = $this->language->get('text_cancelada');
                            $order_status_id = $this->config->get('payment_cielo_api_transferencia_situacao_cancelada_id');
                        } else if ($tipo == 'boleto') {
                            $comment = $this->language->get('text_cancelado');
                            $order_status_id = $this->config->get('payment_cielo_api_boleto_situacao_cancelado_id');
                        }

                        break;
                }

                $order_info = $this->model_extension_module_cielo_api_cron->getOrder($order_id);

                if ($order_info['order_status_id'] != $order_status_id) {
                    switch ($payment_status) {
                        case '0':
                            if ($tipo == 'transferencia') {
                                $campos = array();
                                $campos['order_cielo_api_id'] = $order_cielo_api_id;
                                $campos['status'] = $payment_status;
                                $campos['transferenciaPagamento'] = '';
                                $campos['transferenciaValor'] = $resposta->Payment->Amount;
                                $campos['json'] = json_encode($resposta, JSON_HEX_QUOT|JSON_HEX_APOS);

                                $this->model_extension_module_cielo_api_cron->updateTransferencia($campos);
                            }

                            break;
                        case '1':
                            if ($tipo == 'debito') {
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
                                $campos['capturaData'] = '';
                                $campos['capturaValor'] = '';
                                $campos['json'] = json_encode($resposta, JSON_HEX_QUOT|JSON_HEX_APOS);

                                $this->model_extension_module_cielo_api_cron->updateDebito($campos);
                            } else if ($tipo == 'boleto') {
                                $campos = array();
                                $campos['order_cielo_api_id'] = $order_cielo_api_id;
                                $campos['status'] = $payment_status;
                                $campos['boletoPagamento'] = '';
                                $campos['boletoValor'] = $resposta->Payment->Amount;
                                $campos['json'] = json_encode($resposta, JSON_HEX_QUOT|JSON_HEX_APOS);

                                $this->model_extension_module_cielo_api_cron->updateBoleto($campos);
                            }

                            break;
                        case '2':
                            if ($tipo == 'debito') {
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
                                $campos['capturaData'] = $resposta->Payment->CapturedDate;
                                $campos['capturaValor'] = $resposta->Payment->CapturedAmount;
                                $campos['json'] = json_encode($resposta, JSON_HEX_QUOT|JSON_HEX_APOS);

                                $this->model_extension_module_cielo_api_cron->updateDebito($campos);
                            } else if ($tipo == 'transferencia') {
                                $campos = array();
                                $campos['order_cielo_api_id'] = $order_cielo_api_id;
                                $campos['status'] = $payment_status;
                                $campos['transferenciaPagamento'] = $resposta->Payment->CapturedDate;
                                $campos['transferenciaValor'] = $resposta->Payment->CapturedAmount;
                                $campos['json'] = json_encode($resposta, JSON_HEX_QUOT|JSON_HEX_APOS);

                                $this->model_extension_module_cielo_api_cron->updateTransferencia($campos);
                            } else if ($tipo == 'boleto') {
                                $campos = array();
                                $campos['order_cielo_api_id'] = $order_cielo_api_id;
                                $campos['status'] = $payment_status;
                                $campos['boletoPagamento'] = $resposta->Payment->CapturedDate;
                                $campos['boletoValor'] = $resposta->Payment->CapturedAmount;
                                $campos['json'] = json_encode($resposta, JSON_HEX_QUOT|JSON_HEX_APOS);

                                $this->model_extension_module_cielo_api_cron->updateBoleto($campos);
                            }

                            break;
                        case '3':
                        case '10':
                        case '12':
                        case '13':
                            $campos = array();
                            $campos['order_cielo_api_id'] = $order_cielo_api_id;
                            $campos['status'] = $payment_status;

                            $this->model_extension_module_cielo_api_cron->updateStatus($campos);

                            break;
                    }

                    if ($this->config->get(self::EXTENSION . '_notification')) {
                        $mail = new Mail();
                        $mail->protocol = $this->config->get('config_mail_protocol');
                        $mail->parameter = $this->config->get('config_mail_parameter');
                        $mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
                        $mail->smtp_username = $this->config->get('config_mail_smtp_username');
                        $mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
                        $mail->smtp_port = $this->config->get('config_mail_smtp_port');
                        $mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

                        $mail->setTo($this->config->get('config_email'));
                        $mail->setFrom($this->config->get('config_email'));
                        $mail->setSender(html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));
                        $mail->setSubject(html_entity_decode(sprintf($this->language->get('text_email_subject'), $order_id), ENT_QUOTES, 'UTF-8'));
                        $mail->setText(html_entity_decode(sprintf($this->language->get('text_email_content'), $order_id, $comment), ENT_QUOTES, 'UTF-8'));
                        $mail->send();
                    }

                    $this->load->model('checkout/order');
                    $this->model_checkout_order->addOrderHistory($order_id, $order_status_id, $comment, true);
                }
            }
        }
    }

    private function debug($log) {
        if (defined('DIR_LOGS')) {
            if ($this->config->get(self::EXTENSION . '_debug')) {
                $file = DIR_LOGS . 'cielo_api.log';
                $handle = fopen($file, 'a');
                fwrite($handle, date('d/m/Y H:i:s (T)') . "\n");
                fwrite($handle, print_r($log, true) . "\n");
                fclose($handle);
            }
        }
    }
}