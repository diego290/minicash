<?php
class ModelExtensionPaymentCieloApiDebito extends Model {
    const EXTENSION = 'payment_cielo_api_debito';

    public function getMethod($address, $total) {
        $query = $this->db->query("
            SELECT *
            FROM `" . DB_PREFIX . "zone_to_geo_zone`
            WHERE geo_zone_id = '" . (int) $this->config->get(self::EXTENSION . '_geo_zone_id') . "'
              AND country_id = '" . (int) $address['country_id'] . "'
              AND (zone_id = '" . (int) $address['zone_id'] . "' OR zone_id = '0')
        ");

        if ($total <= 0) {
            $status = false;
        } elseif ($this->config->get(self::EXTENSION . '_total') > 0 && $this->config->get(self::EXTENSION . '_total') > $total) {
            $status = false;
        } elseif (!$this->config->get(self::EXTENSION . '_geo_zone_id')) {
            $status = true;
        } elseif ($query->num_rows) {
            $status = true;
        } else {
            $status = false;
        }

        $currencies = array('BRL');
        $currency_code = $this->session->data['currency'];
        if (!in_array(strtoupper($currency_code), $currencies)) {
            $status = false;
        }

        if (!in_array($this->config->get('config_store_id'), $this->config->get(self::EXTENSION . '_stores'))) {
            $status = false;
        }

        if ($this->customer->isLogged()) {
            $customer_group_id = $this->customer->getGroupId();
        } elseif (isset($this->session->data['guest']['customer_group_id'])) {
            $customer_group_id = $this->session->data['guest']['customer_group_id'];
        } else {
            $customer_group_id = $this->config->get('config_customer_group_id');
        }
        if (!in_array($customer_group_id, $this->config->get(self::EXTENSION . '_customer_groups'))) {
            $status = false;
        }

        $method_data = array();

        if ($status) {
            if (strlen(trim($this->config->get(self::EXTENSION . '_imagem'))) > 0) {
                $title = '<img src="' . HTTPS_SERVER . 'image/' . $this->config->get(self::EXTENSION . '_imagem') . '" alt="' . $this->config->get(self::EXTENSION . '_titulo') . '" />';
            } else {
                $title = $this->config->get(self::EXTENSION . '_titulo');
            }

            $method_data = array(
                'code' => 'cielo_api_debito',
                'title' => $title,
                'terms' => '',
                'sort_order' => $this->config->get(self::EXTENSION . '_sort_order')
            );
        }

        return $method_data;
    }

    public function getOrder($data, $order_id) {
        if (is_array($data) && (count($data) > 0) && ($order_id > '0')) {
            if (is_array($data) && (count($data) > 0) && ($order_id > '0')) {
                $columns = implode(", ", array_values($data));

                $query = $this->db->query("
                    SELECT " . $columns . "
                    FROM `" . DB_PREFIX . "order`
                    WHERE `order_id` = '" . (int) $order_id . "'
                ");

                if ($query->num_rows) {
                    return $query->row;
                }
            }
        }

        return array();
    }

    public function editOrder($data, $order_id) {
        if (is_array($data) && (count($data) > 0) && ($order_id > '0')) {
            $this->db->query("
                UPDATE `" . DB_PREFIX . "order`
                SET custom_field = '" . $this->db->escape(json_encode($data['custom_field'])) . "',
                    payment_custom_field = '" . $this->db->escape(json_encode($data['payment_custom_field'])) . "',
                    shipping_custom_field = '" . $this->db->escape(json_encode($data['shipping_custom_field'])) . "'
                WHERE order_id = '" . (int) $order_id . "'
            ");
        }
    }

    public function getTransaction($order_cielo_api_id) {
        if ($order_cielo_api_id > '0') {
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

    public function getTransactionPaid($order_id) {
        if ($order_id > '0') {
            $query = $this->db->query("
                SELECT status FROM `" . DB_PREFIX . "order_cielo_api`
                WHERE order_id = '" . (int) $order_id . "'
                AND tipo = 'DebitCard'
            ");

            if ($query->num_rows) {
                $transactions = $query->rows;
                foreach ($transactions as $transaction) {
                    if ($transaction['status'] == '1' || $transaction['status'] == '2') {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function addTransaction($data) {
        if (is_array($data) && (count($data) > 0)) {
            $columns = implode(", ", array_keys($data));
            $values = "'".implode("', '", array_values($data))."'";

            $this->db->query("
                INSERT INTO `" . DB_PREFIX . "order_cielo_api`
                ($columns) VALUES ($values)
            ");

            return $this->db->getLastId();
        }

        return false;
    }

    public function updateTransaction($data) {
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

    public function updateTransactionStatus($data) {
        if (is_array($data) && (count($data) > 0)) {
            $this->db->query("
                UPDATE `" . DB_PREFIX . "order_cielo_api`
                SET status = '" . $this->db->escape($data['status']) . "',
                    json = '" . $data['json'] . "'
                WHERE order_cielo_api_id = '" . (int) $data['order_cielo_api_id'] . "'
            ");
        }
    }
}