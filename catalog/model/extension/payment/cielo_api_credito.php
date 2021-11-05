<?php
class ModelExtensionPaymentCieloApiCredito extends Model {
    const EXTENSION = 'payment_cielo_api_credito';

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
                'code' => 'cielo_api_credito',
                'title' => $title,
                'terms' => '',
                'sort_order' => $this->config->get(self::EXTENSION . '_sort_order')
            );
        }

        return $method_data;
    }

    public function getOrder($data, $order_id) {
        if (is_array($data) && (count($data) > 0) && ($order_id > '0')) {
            $columns = implode(", ", array_values($data));

            $query = $this->db->query("
                SELECT " . $columns . "
                FROM `" . DB_PREFIX . "order`
                WHERE order_id = '" . (int) $order_id . "'
            ");

            if ($query->num_rows) {
                return $query->row;
            }
        }

        return array();
    }

    public function getOrderShippingValue($order_id) {
        $value = 0;

        if ($order_id > '0') {
            $query = $this->db->query("
                SELECT *
                FROM `" . DB_PREFIX . "order_total`
                WHERE order_id = '" . (int) $order_id . "'
                ORDER BY sort_order ASC
            ");

            $totals = $query->rows;
            foreach ($totals as $total) {
                if ($total['value'] > 0) {
                    if ($total['code'] == "shipping") {
                        $value += $total['value'];
                    }
                }
            }
        }

        return $value;
    }

    public function getOrderProducts($order_id) {
        if ($order_id > '0') {
            $query = $this->db->query("
                SELECT op.name, op.price, op.tax, op.quantity, p.sku
                FROM `" . DB_PREFIX . "order_product` op
                INNER JOIN `" . DB_PREFIX . "product` p ON (op.product_id = p.product_id)
                WHERE op.order_id = '" . (int) $order_id . "'
            ");

            return $query->rows;
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

    public function getTransactionPaid($order_id) {
        if ($order_id > '0') {
            $query = $this->db->query("
                SELECT status FROM `" . DB_PREFIX . "order_cielo_api`
                WHERE order_id = '" . (int) $order_id . "'
                AND tipo = 'CreditCard'
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
        }
    }
}