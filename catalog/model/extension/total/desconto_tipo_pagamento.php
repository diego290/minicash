<?php
class ModelExtensionTotalDescontoTipoPagamento extends Model {

    private $route = 'extension/total/desconto_tipo_pagamento';
    private $key_prefix = 'total_desconto_tipo_pagamento';
    
    public function getTotal($total) {
        
        if (isset($this->session->data['desconto_tipo_pagamento'])) {
            unset($this->session->data['desconto_tipo_pagamento']);
        }
        
        if (!isset($this->session->data['payment_method']['code'])) {
            return false;
        }

        $desconto = 0;

        $this->language->load($this->route);

        $rules = $this->config->get($this->key_prefix . '_payments');
        foreach ($rules as $rule) {
            if ($this->session->data['payment_method']['code'] == $rule['payment_type']) {
                if (preg_match('#%#', $rule['discount'])) {
                    $desconto = preg_replace('/[\D\.]/', '', $rule['discount']);
                    $desconto = (($desconto / 100) * $this->cart->getSubTotal());
                } else {
                    $desconto = floatval($rule['discount']);
                }
            }
        }
        
        if ($desconto > 0) {
            $this->session->data['desconto_tipo_pagamento'] = $desconto;
            
            $total['totals'][] = array(
                'code'       => 'desconto_tipo_pagamento',
                'title'      => sprintf($this->language->get('text_desconto'), $this->currency->format($desconto, $this->session->data['currency'])),
                'value'      => -$desconto,
                'sort_order' => ($this->config->get('sub_total_sort_order') + 1)
            );
            
            $total['total'] -= $desconto;
        }
    }
}