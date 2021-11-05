<?php

class ControllerExtensionModuleMercadolivre extends Controller {

    public function auth() {
        
        $this->registry->set('mercadolivre', new Mercadolivre($this->registry));
        $app_id = $this->config->get('module_mercadolivre_app_id');
        $app_secret = $this->config->get('module_mercadolivre_app_secret');
        $redirect_uri = HTTPS_SERVER.'mercadolivre_auth';
        if (isset($this->request->get['code'])) {
            if ($data['text_alert'] = $this->mercadolivre->getAccessToken($this->request->get['code'], $redirect_uri)) {
               $data['class_alert'] = 'danger';
            } else {
                $this->mercadolivre->getAppUser();
                $this->session->data['success'] = 'ML_AUTH_OK';
                $this->response->redirect($redirect_uri);
            }
        }
        
        if (isset($this->session->data['success']) && $this->session->data['success'] == 'ML_AUTH_OK') {
            $data['text_alert'] = 'Usuário #' . $this->config->get('module_mercadolivre_user_id') . ' vinculado com sucesso!';
            $data['class_alert'] = 'success';
            unset($this->session->data['success']);
        }
        
        
        $data['heading_title'] = 'MercadoLivre Auth';
        $data['text_intro'] = 'Utilize esta página para vincular sua conta com esta integração.';
        $this->document->setTitle($data['heading_title']);
            
        $data['auth']  = 'http://auth.mercadolibre.com/authorization?response_type=code&client_id=' . $app_id . '&redirect_uri=' . $redirect_uri;
        $data['text_auth']  = $this->config->get('module_mercadolivre_access_token') ? 'Re-autenticar vínculo' : 'Autenticar vínculo';
        $data['class_auth'] = $this->config->get('module_mercadolivre_access_token') ? 'success' : 'primary';
        
        $data['cancel'] = 'Cancelar';

        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');
        $this->response->setOutput($this->load->view('extension/module/mercadolivre_auth', $data));
    }

    public function index() {

          $this->registry->set('mercadolivre', new Mercadolivre($this->registry));
  
          $this->language->load('extension/module/mercadolivre');
          $data['breadcrumbs'] = array();
        
            $data['breadcrumbs'][] = array(
                'text'      => $this->language->get('text_home'),
                'href'      => $this->url->link('common/home'),
                'separator' => false
            );
        
    
            $data['breadcrumbs'][] = array(
                'text'      => $this->language->get('text_title'),
                'href'      => $this->url->link('extension/module/mercadolivre'),
                'separator' => $this->language->get('text_separator')
            );
                
            $this->document->setTitle($this->language->get('text_title'));
            
            $data['heading_title'] = $this->language->get('text_title');
            $data['text_intro'] = $this->language->get('text_intro');
            $data['text_auth'] = $this->language->get('text_auth');
            $data['text_auth_done'] = $this->language->get('text_auth_done');

            $data['button_continue'] = $this->language->get('button_continue');
            $data['continue'] = $this->url->link('common/home');
            $data['column_left'] = $this->load->controller('common/column_left');
            $data['column_right'] = $this->load->controller('common/column_right');
            $data['content_top'] = $this->load->controller('common/content_top');
            $data['content_bottom'] = $this->load->controller('common/content_bottom');
            $data['footer'] = $this->load->controller('common/footer');
            $data['header'] = $this->load->controller('common/header');
            $this->response->setOutput($this->load->view('extension/module/mercadolivre', $data));
      
    }
    
   public function syncat() {
     $this->registry->set('mercadolivre', new Mercadolivre($this->registry)); 
     $this->mercadolivre->updateCategory();
     $this->mercadolivre->updateListingType();
     echo 'Done.';
   }
   
    public function testUser() {
      $this->registry->set('mercadolivre', new Mercadolivre($this->registry));
      $this->mercadolivre->createTestUser();
    }

    public function debugAnswer() {
      $id = $this->request->get['id'];
      $this->registry->set('mercadolivre', new Mercadolivre($this->registry));
      $this->mercadolivre->answerDebug($id);
    }

    public function debugMsg() {
      $mlOrderId = $this->request->get['mlid'];
      $orderId = $this->request->get['ocid'];
      $this->registry->set('mercadolivre', new Mercadolivre($this->registry));
      $this->mercadolivre->saleMsgDebug($mlOrderId, $orderId);
    }
    

    public function callback() {
     
        $this->registry->set('mercadolivre', new Mercadolivre($this->registry));
        $data = file_get_contents('php://input');
        $data=json_decode($data, true);

        if($data['topic']=='items') {
            $itemId=str_replace('/items/','',$data['resource']);
            $itemId=trim($itemId);
            $this->mercadolivre->updateOpencartProduct($itemId);
        }
        if($data['topic']=='orders' || $data['topic']=='created_orders') {
            $orderId=str_replace('/orders/','',$data['resource']);
            $this->mercadolivre->addAPICall($orderId,'order');
            
        }
        if($data['topic']=='questions') {
            $question_id=str_replace('/questions/','',$data['resource']);
            $question_id=trim($question_id);
            $this->mercadolivre->processAnswers($question_id);
        }
        if($this->config->get('module_mercadolivre_debug')) {
           $this->log->write('MERCADOLIVRE callback - Type ='.$data['topic'].' Response='.print_r($data,true));
        }
        
        $this->response->addHeader($_SERVER["SERVER_PROTOCOL"]." 200 OK");
        $this->response->setOutput('done');
    }
    
    public function cron() {
        
        $this->registry->set('mercadolivre', new Mercadolivre($this->registry));
        $this->mercadolivre->processAPIOrder();
        
         /* product post to ml*/
        $this->load->model('extension/module/mercadolivre');
        $products = $this->model_extension_module_mercadolivre->getProductFromSyncQueue();
        if ($products) {
            foreach ($products as $product_single) {
                 if(isset($product_single['product_id']) && $product_single['product_id']) {
                     
                     if ($product_single['type']=='pause') {
                        $this->model_extension_module_mercadolivre->pauseProduct($product_single['product_id']);
                     } elseif ($product_single['type']=='resume') {
                        $this->model_extension_module_mercadolivre->resumeProduct($product_single['product_id']);
                     } elseif ($product_single['type']=='desync') {
                        $this->model_extension_module_mercadolivre->desynchronizeProduct($product_single['product_id']);
                     } elseif ($product_single['type']=='update') {
                        $this->model_extension_module_mercadolivre->updateProduct($product_single['product_id']);
                     } elseif ($product_single['type']=='picture') {
                        $this->model_extension_module_mercadolivre->updatePicture($product_single['product_id']);
                     } else {
                        $this->model_extension_module_mercadolivre->synchronizeProduct($product_single['product_id']);
                     }

                     $this->model_extension_module_mercadolivre->deleteSyncQueue($product_single['product_id']);
                 }
            }
        }
        
        $this->response->setOutput('done');
    }

    /* Catalog event*/
    public function onNewOrder($route, $response) {
       $this->registry->set('mercadolivre', new Mercadolivre($this->registry));
       $order_id = isset($response[0]) ? $response[0] : 0;
       if ($order_id) {
         $this->mercadolivre->orderNew((int)$order_id); 
       }
    }
}

?>