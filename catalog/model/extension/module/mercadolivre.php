<?php

class ModelExtensionModuleMercadolivre extends Model {

  public function synchronizeProduct($product_id, $updateOnly = false) {
     $this->mercadolivre->synchronizeProduct($product_id, $updateOnly);
  }   

    public function addMessageHistory($data) {
       
       $this->db->query("DELETE FROM " . DB_PREFIX . "mercadolivre_msg_history WHERE msgTime < DATE_SUB(NOW(), INTERVAL 90 DAY)"); 
       
       if ($data) {
          $this->db->query("INSERT INTO " . DB_PREFIX . "mercadolivre_msg_history SET msgId='".(int) $data['msgId']."', orderId = '" . $this->db->escape($data['orderId']) . "', msgTime = NOW()"); 
       }
    }
    
    public function getMessageHistory($msgId, $orderId) {
    
       return $this->db->query("SELECT * FROM " . DB_PREFIX . "mercadolivre_msg_history WHERE msgId='".(int) $msgId."' and orderId='".$this->db->escape($orderId)."'")->row;
    }

    public function getMessageHistoryByOrderId($orderId) {
        
        return $this->db->query("SELECT * FROM " . DB_PREFIX . "mercadolivre_msg_history WHERE orderId='".$this->db->escape($orderId)."'")->row;
    }

    
    public function getMatchedMessage($shipping, $payment, $orderId, $date_created, $showDebug = false) {
      $rows = $this->getMessages();
      //echo 'ship=='.$shipping.' & pay=='.$payment.' & date='.$date_created;
      $date_created = strtotime($date_created);
      $return = array();
      foreach($rows as $row) {
            $status = true;
            $debug = array();
        
            if (isset($row['days']) && $row['days']) {
                $row['days'] = unserialize($row['days']);
         } else {
             $row['days'] = array();
         }

         if (isset($row['shippings']) && $row['shippings']) {
              $row['shippings'] = is_array($row['shippings']) ? $row['shippings'] : @unserialize($row['shippings']);
          }
          if (!isset($row['shippings']) || !is_array($row['shippings'])) {
               $row['shippings'] = array();
          }

          if (isset($row['payments']) && $row['payments']) {
              $row['payments'] = is_array($row['payments']) ? $row['payments'] : @unserialize($row['payments']);
          } 
          if (!isset($row['payments']) || !is_array($row['payments'])) {
               $row['payments'] = array();
          }

            
        if (!isset($row['time_start'])) $row['time_start']='';
              if (!isset($row['time_end'])) $row['time_end']='';
            
            /*Days of week checking*/
            $day=date('w',$date_created);
            
            if (!in_array($day,$row['days'])) {
                $status = false; 
                $debug[] = 'Days ("'.$day.'")';
            }
            
            /*time checking*/
            $time=date('H:i',$date_created);
            
            if ($row['time_start'] != "" && $row['time_end'] != "") {
                if ($time < $row['time_start'] || $time> $row['time_end']) {
                    $status = false; 
                    $debug[] = 'Time ("'.$time.'")';
                }  
            }

        if ($row['shippings'] && !in_array($shipping, $row['shippings'])) {
            $status = false; 
            $debug[] = 'Shipping ("'.$shipping.'")';
        }

        if ($row['payments'] && !in_array($payment, $row['payments'])) {
            $status = false; 
            $debug[] = 'Payment ("'.$payment.'")';
        }
             
             
             if (!$row['message']) {
                 $status = false; 
                 $debug[] = 'Empty Message ("'.$haystack.'")';
             }
             
            if ($status && !$this->getMessageHistory($row['id'], $orderId)) {
               $return[$row['id']] = $row['message'];
             } 
             
            if ($debug && $showDebug) {
                echo '------------Sales Debug------------';
                echo '<br />';
                $debug[] = 'ID ("' . $row['id'] . '")';
                echo implode($debug, ' && ').'<br />';
                echo '-----------------------------------';
                echo '<br />';
            }
             
        }  
      
      return $return;
    }

    /*answer methods*/
    
    public function getMatchedAnswer($text, $title, $question_id, $date_created, $showDebug = false) {
        $rows = $this->getAnswers();
        //$text = $this->mercadolivre->removeAccents($text);
        //$title = $this->mercadolivre->removeAccents($title);
        //echo 'ship=='.$shipping.' & pay=='.$payment.' & date='.$date_created;

        $product_parts = array_unique(explode(' ', $title));
        $question_parts = array_unique(explode(' ', $text));

        $remove_space = function($value) {
            $value = strtolower($value);
            $value = str_replace(array('!',',','@','#','$','%','^','&','(',')','=','+','_','-','{','}','[',']','|'),'',$value);
            $value = trim($value);
            $value = trim($value,'?');
            return $value;
        };

        $product_parts = array_map($remove_space, $product_parts);
        $question_parts = array_map($remove_space, $question_parts);

        $date_created = strtotime($date_created);
        $return = array();
        foreach($rows as $row) {
            $status = true;
            $toBeWaited = false;
            $debug = array();

            if (isset($row['days']) && $row['days']) {
                $row['days'] = unserialize($row['days']);
            } else {
                $row['days'] = array();
            }

            if (!isset($row['time_start'])) $row['time_start']='';
            if (!isset($row['time_end'])) $row['time_end']='';

            /*Days of week checking*/
            $day=date('w',$date_created);

            if (!in_array($day,$row['days'])) {
                $status = false; 
                $debug[] = 'Days ("'.$day.'")';
            }

            /*time checking*/
            $time=date('H:i',$date_created);

            if ($row['time_start'] != "" && $row['time_end'] != "") {
                if ($time < $row['time_start'] || $time> $row['time_end']) {
                    $status = false; 
                    $debug[] = 'Time ("'.$time.'")';
                }  
            }

            if ($row['ml_product']) {
                $match_status = false;
                $_ml_product = explode(',', $row['ml_product']);
                $_ml_product = array_map($remove_space, $_ml_product);

                $match_rule = ($row['product_rule'] == 'yes');

                if (array_intersect($_ml_product, $product_parts) == $match_rule) {
                    $match_status = true;
                }

                if (!$match_status) {
                    foreach($_ml_product as $each_word) {
                        if ((strpos($each_word,'*')!==false || strpos($each_word,'?')!==false) && preg_match('/'.str_replace(array('\*','\?'),array('(.*?)','[a-zA-Z0-9]'),preg_quote($each_word)).'/i',trim($title))) {
                            $match_status=true;  
                            break;
                        }   
                    }   
                }
                
                if (!$match_status) {
                    $debug[] = 'Matching Product ("'.$title.'")';
                    $status = false;    
                }
                
            }

            if ($row['text']) {
                $match_status = false;
                $_words = explode(',', $row['text']);
                $_words = array_map($remove_space, $_words);

                $match_rule = ($row['text_rule'] == 'yes');

                if (array_intersect($_words, $question_parts) == $match_rule) {
                    $match_status = true;
                }

                if (!$match_status) {
                    foreach($_words as $each_word) {
                        if ((strpos($each_word,'*')!==false || strpos($each_word,'?')!==false) && preg_match('/'.str_replace(array('\*','\?'),array('(.*?)','[a-zA-Z0-9]'),preg_quote($each_word)).'/i',trim($text))) {
                            $match_status=true;  
                            break;
                        }   
                    }   
                }

                if (!$match_status) {
                    $debug[] = 'Matching Text ("'.$text.'")';
                    $status = false;    
                }
                
            }

            $question_words = count(explode(' ',$text));
            if ($row['word_count'] && (int)$row['word_count'] < $question_words) {
                $status = false; 
                $debug[] = 'Word count QL:'.strlen($text).' and WC'.(int)$row['word_count'];
            }

            if (!$row['answer']) {
                $status = false; 
                $debug[] = 'Empty Answer';
            }

            if ($status && !$this->getAnswerHistory($row['id'], $question_id)) {
                $return = $row;
                break;
            } 

            if ($debug) {
                $debug[] = 'ID ("'.$row['id'].'")';
                //echo implode($debug, ' && ').'<br />';
            }

            if ($debug && $showDebug) {
                echo '------------Answer Debug------------';
                echo '<br />';
                echo implode($debug, ' && ').'<br />';
                echo '-----------------------------------';
                echo '<br />';
            }
        }
        return $return;
    }
    
    public function addAnswerHistory($data) {
       
       $this->db->query("DELETE FROM " . DB_PREFIX . "mercadolivre_answer_history WHERE ansTime < DATE_SUB(NOW(), INTERVAL 90 DAY)"); 
       
       if ($data) {
          $this->db->query("INSERT INTO " . DB_PREFIX . "mercadolivre_answer_history SET ansId='".(int) $data['ansId']."', productId = '" . $this->db->escape($data['productId']) . "', questionId = '" . $this->db->escape($data['questionId']) . "', ansTime = NOW()"); 
       }
    }
    
    public function getAnswerHistory($ansId, $questionId) {
    
       return $this->db->query("SELECT * FROM " . DB_PREFIX . "mercadolivre_answer_history WHERE ansId='".(int) $ansId."' and questionId='".$this->db->escape($questionId)."'")->row;
    }

    /* end of answer methods*/

  /* common model functions */
    public function getProductFromSyncQueue() {
      $limit = $this->config->get('module_mercadolivre_sync_limit');
      if (!$limit) $limit = 15;

       $rows=$this->db->query("SELECT * FROM " . DB_PREFIX . "mercadolivre_sync_queue order by product_id asc limit 0,".$limit)->rows; 
       return $rows;
    }
    
    public function deleteSyncQueue($product_id) {
       $this->db->query("DELETE FROM " . DB_PREFIX . "mercadolivre_sync_queue WHERE product_id = '" . (int) $product_id. "'");
    }
    
    
    public function desynchronizeProduct($product_id) {
       $product_info=$this->db->query("SELECT * FROM " . DB_PREFIX . "mercadolivre_product WHERE product_id = '" . (int) $product_id. "'")->row; 
       
       if($product_info){
           $this->mercadolivre->delete($product_info['mercaId']);
           $this->db->query("UPDATE " . DB_PREFIX . "mercadolivre_product SET mercaId = '', status = '', substatus = '', url = '' WHERE product_id = '" . (int) $product_id. "'");
           $this->db->query("DELETE FROM " . DB_PREFIX . "mercadolivre_combination WHERE product_id = '" . (int) $product_id. "'");
            
       }
       return true; 
    }
    public function updatePicture($product_id) {
       $product_info = $this->db->query("SELECT * FROM " . DB_PREFIX . "mercadolivre_product WHERE product_id = '" . (int) $product_id. "'")->row; 
       
       if($product_info) {
           $this->mercadolivre->synchronizePicture($product_id);
       }
       return true; 
    }
    public function updateProduct($product_id) {
       $product_info = $this->db->query("SELECT * FROM " . DB_PREFIX . "mercadolivre_product WHERE product_id = '" . (int) $product_id. "'")->row; 
       
       if($product_info) {
           $this->mercadolivre->synchronizeProduct($product_id, true);
       }
       return true; 
    }

    public function pauseProduct($product_id) {
       $product_info = $this->db->query("SELECT * FROM " . DB_PREFIX . "mercadolivre_product WHERE product_id = '" . (int) $product_id. "'")->row; 
       
       if($product_info) {
           $this->mercadolivre->delete($product_info['mercaId'], true);
           $this->db->query("UPDATE " . DB_PREFIX . "mercadolivre_product SET forcePause = 1 WHERE product_id = '" . (int) $product_id. "'");
       }
       return true; 
    }

    public function resumeProduct($product_id) {
       $product_info = $this->db->query("SELECT * FROM " . DB_PREFIX . "mercadolivre_product WHERE product_id = '" . (int) $product_id. "'")->row; 
       
       if($product_info) {
           $this->mercadolivre->resume($product_info['mercaId']);
           $this->db->query("UPDATE " . DB_PREFIX . "mercadolivre_product SET forcePause = 0 WHERE product_id = '" . (int) $product_id. "'");
       }
       return true; 
    }
    
    public function getCustomShipping() {
       $shippings = $this->getShippings();
       $shipping_costs=array();
       foreach($shippings as $single) {
            if(isset($single['name']) && $single['name']) {
               $shipping_costs[]=array('description'=>$single['name'],'cost'=>(float)$single['cost']);
            }
        }       
       return $shipping_costs;
    }

    public function getMLOption($option_id) {
       
       $row = $this->db->query("SELECT * FROM " . DB_PREFIX . "mercadolivre_option WHERE option_id ='".(int)$option_id."'")->row; 
       return $row;
    }

    public function getMLOptionValue($option_value_id) {
       $row = $this->db->query("SELECT * FROM " . DB_PREFIX . "mercadolivre_option_value WHERE option_value_id ='".(int)$option_value_id."'")->row; 
       return $row;
    }

    public function getShippings() {
    
       return $this->db->query("SELECT * FROM " . DB_PREFIX . "mercadolivre_shipping")->rows;
    }

    public function getMLVariation($product_id, $option_id, $option_value_id) {
       
       $row = $this->db->query("SELECT * FROM " . DB_PREFIX . "mercadolivre_variation WHERE product_id ='".(int)$product_id."' and option_id ='".(int)$option_id."' and option_value_id ='".(int)$option_value_id."'")->row; 
       
       return $row;
    }

    public function getOCProductAttributes($product_id, $language_id) {
        $product_attribute_group_data = array();

        $product_attribute_group_query = $this->db->query("SELECT ag.attribute_group_id, agd.name FROM " . DB_PREFIX . "product_attribute pa LEFT JOIN " . DB_PREFIX . "attribute a ON (pa.attribute_id = a.attribute_id) LEFT JOIN " . DB_PREFIX . "attribute_group ag ON (a.attribute_group_id = ag.attribute_group_id) LEFT JOIN " . DB_PREFIX . "attribute_group_description agd ON (ag.attribute_group_id = agd.attribute_group_id) WHERE pa.product_id = '" . (int)$product_id . "' AND agd.language_id = '" . (int)$language_id . "' GROUP BY ag.attribute_group_id ORDER BY ag.sort_order, agd.name");

        foreach ($product_attribute_group_query->rows as $product_attribute_group) {
            $product_attribute_data = array();

            $product_attribute_query = $this->db->query("SELECT a.attribute_id, ad.name, pa.text FROM " . DB_PREFIX . "product_attribute pa LEFT JOIN " . DB_PREFIX . "attribute a ON (pa.attribute_id = a.attribute_id) LEFT JOIN " . DB_PREFIX . "attribute_description ad ON (a.attribute_id = ad.attribute_id) WHERE pa.product_id = '" . (int)$product_id . "' AND a.attribute_group_id = '" . (int)$product_attribute_group['attribute_group_id'] . "' AND ad.language_id = '" . (int)$language_id . "' AND pa.language_id = '" . (int)$language_id . "' ORDER BY a.sort_order, ad.name");

            foreach ($product_attribute_query->rows as $product_attribute) {
                $product_attribute_data[] = array(
                    'attribute_id' => $product_attribute['attribute_id'],
                    'name'         => $product_attribute['name'],
                    'text'         => $product_attribute['text']
                );
            }

            $product_attribute_group_data[] = array(
                'attribute_group_id' => $product_attribute_group['attribute_group_id'],
                'name'               => $product_attribute_group['name'],
                'attribute'          => $product_attribute_data
            );
        }
        
        $return = '';
        
        if($product_attribute_group_data) {

          foreach ($product_attribute_group_data as $attribute_group) {
                $return .= '**'.$attribute_group['name']."\r\n";
                
                foreach ($attribute_group['attribute'] as $attribute) {
                    $return .=$attribute['name'].' - '.$attribute['text']."\r\n";
                } 
          }

        }
            
        return $return;
    }

    public function getMessages() {

       return $this->db->query("SELECT * FROM " . DB_PREFIX . "mercadolivre_message")->rows;
    }

     public function getAnswers() {
    
       return $this->db->query("SELECT * FROM " . DB_PREFIX . "mercadolivre_answer")->rows;
    }

   /* end of common model function */

}