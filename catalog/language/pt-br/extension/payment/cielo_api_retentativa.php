<?php
// Heading
$_['heading_title']       = 'Pagamento com Cartão de Débito';

// Text
$_['text_basket']         = 'Carrinho de compras';
$_['text_checkout']       = 'Finalizar pedido';
$_['text_retentativa']    = 'Realizar pagamento';
$_['text_detalhes']       = 'Dados do cartão de débito:';
$_['text_carregando']     = 'Carregando...';
$_['text_redirecionando'] = 'Redirecionando para autenticação. Aguarde...';
$_['text_visa']           = 'Visa Electron';
$_['text_mastercard']     = 'Maestro';
$_['text_elo']            = 'Elo Débito';
$_['text_mes']            = 'Mês';
$_['text_ano']            = 'Ano';
$_['text_info']           = '<strong>Requisitos para utilização de cartões Visa e Maestro:</strong><br><br>- O cartão <strong>deve possuir código de segurança</strong> (geralmente no verso do cartão físico).<br>- O cartão deve está habilitado para realizar pagamentos através de <strong>débito online (lojas online)</strong>.<br>- O banco emissor do cartão deve ter habilitado o serviço <strong>Verified by Visa</strong> ou <strong>Mastercard SecureCode</strong>.<br>- <strong>Para realizar a autenticação bancária</strong>, alguns bancos exigem a utilização de um computador cadastrado no Internet Banking.<br>- <strong>Tenha em mãos</strong> o celular habilitado no Internet Banking, token, cartão de códigos, CPF, data de nascimento e senha do cartão de débito.<br><br><strong>Em caso de dúvidas sobre os requisitos, entre em contato com o banco emissor do seu cartão de débito.</strong>';
$_['text_sandbox']        = '<strong>Atenção:</strong><br>Você está no ambiente Sandbox (apenas teste).<br>Utilize um cartão de teste para realizar o pagamento.';

// Entry
$_['entry_cartao']        = 'Número do ';
$_['entry_nome']          = 'Nome impresso no cartão: ';
$_['entry_valor']         = 'Valor à vista: ';
$_['entry_validade_mes']  = 'Válido até (mês): ';
$_['entry_validade_ano']  = 'Válido até (ano): ';
$_['entry_codigo']        = 'Código de segurança: ';

// Error
$_['error_cartao']        = 'O número não é válido.';
$_['error_nome']          = 'O nome não é válido.';
$_['error_mes']           = 'Selecione o mês.';
$_['error_ano']           = 'Selecione o ano.';
$_['error_codigo']        = 'O código não é válido.';
$_['error_autorizacao']   = '<strong>O pagamento por cartão de débito não foi autorizado.</strong><br><br><strong>VERIFIQUE:</strong><br>- Se o cartão de débito possui <strong>limite disponível</strong> para o pagamento à vista do pedido.<br>- Se você <strong>preencheu corretamente</strong> todos os campos com os dados do cartão de débito.<br>- Se o cartão de débito possui os <strong>requisitos para realizar pagamentos</strong> através de lojas online.<br><br><strong>Importante:</strong><br>Caso deseje, realize a autenticação só mais uma vez, pois o banco emissor poderá bloquear o cartão por 24 horas.';
$_['error_configuracao']  = '<strong>Atenção:</strong><br>Não foi possível autorizar o seu pagamento por problemas técnicos.<br>Tente novamente mais tarde ou selecione outra forma de pagamento.<br>Em caso de dúvidas, entre em contato com nosso atendimento.';
$_['error_bandeiras']     = '<strong>Atenção:</strong><br>Nenhum cartão foi ativado nas configurações da extensão.';