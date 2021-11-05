<?php
// Text
$_['text_payment']        = 'Seu pedido será liberado assim que o pagamento for confirmado.';
$_['text_detalhes']       = 'Dados do cartão de débito:';
$_['text_carregando']     = 'Carregando...';
$_['text_redirecionando'] = 'Redirecionando para autenticação. Aguarde...';
$_['text_cartao_debito']  = 'Cartão de Débito';
$_['text_visa']           = 'Visa Electron';
$_['text_mastercard']     = 'Maestro';
$_['text_elo']            = 'Elo Débito';
$_['text_mes']            = 'Mês';
$_['text_ano']            = 'Ano';
$_['text_comprovante']    = '<strong>COMPROVANTE:</strong>';
$_['text_pendente']       = 'Aguardando autenticação bancária.';
$_['text_autorizado']     = 'Pagamento autorizado';
$_['text_capturado']      = 'Pagamento confirmado';
$_['text_nao_autorizado'] = 'Não autorizado pelo banco emissor.';
$_['text_tentativas']     = 'O limite de tentativas de pagamento com o cartão de débito foi excedido.';
$_['text_info']           = '<strong>Requisitos para utilização de cartões Visa e Maestro:</strong><br><br>- O cartão <strong>deve possuir código de segurança</strong> (geralmente no verso do cartão físico).<br>- O cartão deve está habilitado para realizar pagamentos através de <strong>débito online (lojas online)</strong>.<br>- O banco emissor do cartão deve ter habilitado o serviço <strong>Verified by Visa</strong> ou <strong>Mastercard SecureCode</strong>.<br>- <strong>Para realizar a autenticação bancária</strong>, alguns bancos exigem a utilização de um computador cadastrado no Internet Banking.<br>- <strong>Tenha em mãos</strong> o celular habilitado no Internet Banking, token, cartão de códigos, CPF, data de nascimento e senha do cartão de débito.<br><br><strong>Em caso de dúvidas sobre os requisitos, entre em contato com o banco emissor do seu cartão de débito.</strong>';
$_['text_sandbox']        = '<strong>Atenção:</strong><br>Você está no ambiente Sandbox (apenas teste).<br>Utilize um cartão de teste para realizar o pagamento.';

// Entry
$_['entry_cartao']        = 'Número do&nbsp;';
$_['entry_nome']          = 'Nome impresso no cartão: ';
$_['entry_valor']         = 'Valor à vista: ';
$_['entry_validade_mes']  = 'Válido até (mês): ';
$_['entry_validade_ano']  = 'Válido até (ano): ';
$_['entry_codigo']        = 'Código de segurança: ';
$_['entry_pedido']        = 'Pedido: ';
$_['entry_data']          = 'Data: ';
$_['entry_tid']           = 'TID: ';
$_['entry_tipo']          = 'Pago com: ';
$_['entry_bandeira']      = 'Bandeira: ';
$_['entry_total']         = 'Total: ';
$_['entry_status']        = 'Status: ';

// Error
$_['error_cartao']        = 'O número não é válido.';
$_['error_nome']          = 'O nome não é válido.';
$_['error_mes']           = 'Selecione o mês.';
$_['error_ano']           = 'Selecione o ano.';
$_['error_codigo']        = 'O código não é válido.';
$_['error_permissao']     = 'Acesso negado!';
$_['error_autorizacao']   = '<strong>O pagamento por cartão de débito não foi autorizado.</strong><br><br><strong>VERIFIQUE:</strong><br>- Se o cartão de débito possui <strong>limite disponível</strong> para o pagamento à vista do pedido.<br>- Se você <strong>preencheu corretamente</strong> todos os campos com os dados do cartão de débito.<br>- Se o cartão de débito possui os <strong>requisitos para realizar pagamentos</strong> através de lojas online.<br><br><strong>Importante:</strong><br>Caso deseje, realize a autenticação só mais uma vez, pois o banco emissor poderá bloquear o cartão por 24 horas.';
$_['error_preenchimento'] = '<strong>Atenção:</strong><br>Todos os campos são de preenchimento obrigatório.';
$_['error_tentativas']    = '<strong>Atenção:</strong><br>Você excedeu o limite de tentativas para pagamento.<br>Em caso de dúvidas, entre em contato com nosso atendimento.';
$_['error_status']        = '<strong>Atenção:</strong><br>Não foi possível autorizar o seu pagamento.<br>Tente novamente, e em caso de dúvidas, entre em contato com nosso atendimento.';
$_['error_configuracao']  = '<strong>Atenção:</strong><br>Não foi possível autorizar o seu pagamento por problemas técnicos.<br>Tente novamente mais tarde ou selecione outra forma de pagamento.<br>Em caso de dúvidas, entre em contato com nosso atendimento.';
$_['error_bandeiras']     = '<strong>Atenção:</strong><br>Nenhum cartão foi ativado nas configurações da extensão.';