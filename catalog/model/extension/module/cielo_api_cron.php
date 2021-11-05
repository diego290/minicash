<?php
class ModelExtensionModuleCieloApiCron extends Model {
    public function getTransactions() {
        $query = $this->db->query("
            SELECT o.store_id, o.currency_code, o.order_status_id, oc.order_cielo_api_id
            FROM `" . DB_PREFIX . "order_cielo_api` oc
            INNER JOIN `" . DB_PREFIX . "order` o ON (o.order_id = oc.order_id)
            WHERE (oc.tipo = 'EletronicTransfer' OR oc.tipo = 'Boleto' OR oc.tipo = 'DebitCard') AND
               (o.order_status_id = '" . $this->config->get('payment_cielo_api_debito_situacao_pendente_id') . "'
               OR o.order_status_id = '" . $this->config->get('payment_cielo_api_transferencia_situacao_gerada_id') . "'
               OR o.order_status_id = '" . $this->config->get('payment_cielo_api_boleto_situacao_gerado_id') . "'
               OR o.order_status_id = '" . $this->config->get('payment_cielo_api_transferencia_situacao_pendente_id') . "'
               OR o.order_status_id = '" . $this->config->get('payment_cielo_api_boleto_situacao_pendente_id') . "')
               AND o.date_added >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");

        return $query->rows;
    }

    public function getTransaction($order_cielo_api_id) {
        if ($order_cielo_api_id > 0) {
            $query = $this->db->query("
                SELECT *
                FROM `" . DB_PREFIX . "order_cielo_api`
                WHERE `order_cielo_api_id` = '" . (int) $order_cielo_api_id . "'
            ");

            if ($query->num_rows) {
                return $query->row;
            }
        }

        return array();
    }

    public function getOrder($order_id) {
        if ($order_id > 0) {
            $query = $this->db->query("
                SELECT order_status_id
                FROM `" . DB_PREFIX . "order`
                WHERE `order_id` = '" . (int) $order_id . "'
            ");

            if ($query->num_rows) {
                return $query->row;
            }
        }

        return array();
    }

    public function updateDebito($data) {
        if (is_array($data) && (count($data) > 0)) {
            $this->db->query("
                UPDATE `" . DB_PREFIX . "order_cielo_api`
                SET status = '" . $this->db->escape($data['status']) . "',
                    nsu = '" . $this->db->escape($data['nsu']) . "',
                    authorizationCode = '" . $this->db->escape($data['authorizationCode']) . "',
                    bin = '" . $this->db->escape($data['bin']) . "',
                    fim = '" . $this->db->escape($data['fim']) . "',
                    holder = '" . $this->db->escape($data['holder']) . "',
                    eci = '" . $this->db->escape($data['eci']) . "',
                    autorizacaoData = '" . $this->db->escape($data['autorizacaoData']) . "',
                    autorizacaoValor = '" . $this->db->escape($data['autorizacaoValor']) . "',
                    capturaData = '" . $this->db->escape($data['capturaData']) . "',
                    capturaValor = '" . $this->db->escape($data['capturaValor']) . "',
                    json = '" . $data['json'] . "'
                WHERE order_cielo_api_id = '" . (int) $data['order_cielo_api_id'] . "'
            ");
        }
    }

    public function updateBoleto($data) {
        if (is_array($data) && (count($data) > 0)) {
            $this->db->query("
                UPDATE `" . DB_PREFIX . "order_cielo_api`
                SET status = '" . $this->db->escape($data['status']) . "',
                    boletoPagamento = '" . $this->db->escape($data['boletoPagamento']) . "',
                    boletoValor = '" . $this->db->escape($data['boletoValor']) . "',
                WHERE order_cielo_api_id = '" . (int) $data['order_cielo_api_id'] . "'
            ");
        }
    }

    public function updateTransferencia($data) {
        if (is_array($data) && (count($data) > 0)) {
            $this->db->query("
                UPDATE `" . DB_PREFIX . "order_cielo_api`
                SET status = '" . $this->db->escape($data['status']) . "',
                    transferenciaPagamento = '" . $this->db->escape($data['transferenciaPagamento']) . "',
                    transferenciaValor = '" . $this->db->escape($data['transferenciaValor']) . "'
                WHERE order_cielo_api_id = '" . (int) $data['order_cielo_api_id'] . "'
            ");
        }
    }

    public function updateStatus($data) {
        if (is_array($data) && (count($data) > 0)) {
            $this->db->query("
                UPDATE `" . DB_PREFIX . "order_cielo_api`
                SET status = '" . $this->db->escape($data['status']) . "'
                WHERE order_cielo_api_id = '" . (int) $data['order_cielo_api_id'] . "'
            ");
        }
    }
}