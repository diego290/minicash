<?php
class ModelExtensionTotalDescontoCart5 extends Model {
       
	public function getTotal($total) {
		if ($this->config->get('total_descontocart5_status')) {
			$this->load->model('account/customer');
			$this->language->load('total/credit');
			$total_pedido = $this->cart->getSubTotal();
			$desconto = $this->config->get('total_descontocart5_taxa');
            $minimo = $this->config->get('total_descontocart5_minimo');
            if(isset($this->session->data['session_cart5']) && $total_pedido > 0 && $total_pedido >= $minimo){
                $credit = ($total_pedido/100)*$desconto;
                $total['totals'][] = array(
                    'code'       => 'descontocart5',
                    'title'      => "Desconto de ".$desconto."% (REC)",
                    'value'      => -$credit,
                    'sort_order' => $this->config->get('total_descontocart5_sort_order')
                );
                $total['total'] -= $credit;
            }
		}
	}
}
?>