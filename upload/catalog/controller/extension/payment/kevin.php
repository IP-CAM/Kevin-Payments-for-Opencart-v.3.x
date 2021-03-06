<?php
/*
* 2020 Kevin. payment  for OpenCart v.3.0.x.x  
* @version 0.1.3.15
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* 
*  @author 2020 kevin. <info@getkevin.eu>
*  @copyright kevin.
*  @license http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
*/
use Kevin\Client;
class ControllerExtensionPaymentKevin extends Controller {

    private $type = 'payment';
    private $name = 'kevin'; 
	
    public function index() {	
	//	date_default_timezone_set('Europe/Vilnius');		
		require_once dirname(dirname(dirname(__DIR__))) . '/model/extension/payment/kevin/vendor/autoload.php';
		$clientId = $this->config->get('payment_kevin_client_id');
		$clientSecret = $this->config->get('payment_kevin_client_secret');
		$options = ['error' => 'array'];

		$kevinClient = new Client($clientId, $clientSecret, $options);

		$this->load->model('checkout/order');
        $this->load->model('extension/payment/kevin');
        $this->load->language('extension/payment/kevin');
		$this->load->model('localisation/language');
		
		$current_language = $this->config->get('config_language_id');

        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
		if(!$order_info) {
			$order_info['total'] = 0;
			$order_info['currency_code'] = $this->config->get('config_currency');
			$order_info['currency_value'] = 1;
		}
		
		$total = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false, false);
		
		//$this->model_extension_payment_kevin->addKevinOrder($order_info, $total);
		
		$data['kevin_instr_title'] = $this->config->get('payment_kevin_ititle' . $current_language);
	//	$data['kevin_instr'] =  html_entity_decode($this->config->get('payment_kevin_instruction' . $current_language));
		$data['kevin_instr'] =  $this->config->get('payment_kevin_instruction' . $current_language);

		if (isset($this->session->data['iso_code_2'])) {
			$current_country_code = $this->session->data['iso_code_2'];
		} else {
			$current_country_code = $order_info['payment_iso_code_2'];
		}
		
		$contries = $kevinClient->auth()->getCountries();
		//$countryCodes = array("LT", "LV", "EE");
		$countryCodes = $contries['data'];

	//	echo '<pre>';	print_r($this->session->data['order_id']); echo '</pre>';

		$country_code = ['countryCode' => $current_country_code];
		
		$banks = $kevinClient->auth()->getBanks($country_code);
		
		if (isset($banks['error']['code'])) {
			$data['error_bank_missing'] = $banks['error']['description'] . ' Error code: ' . $banks['error']['code'] . '. Please try another payment method.';
		}

		$bank_ids = array();

		$data['text_sandbox_alert'] = '';
		foreach ($banks['data'] as $bank) {
			if ($bank['isSandbox']) {
				$data['text_sandbox_alert'] = 'This payment method is set to Sandbox mode. Only for test payments. Real payments is not available!';
				break;
			} 
		}

		$data['banks'] = $banks['data'];
	//	echo '<pre>';	print_r($banks); echo '</pre>';
		$data['action'] = $this->url->link('extension/payment/kevin/redirect');
		$data['bank_name_enable'] = $this->config->get('payment_kevin_bank_name_enabled');
		
		
		
        $order_id = $order_info['order_id'];
        $currency = $order_info['currency_code'];
		$data['currency'] = $currency;
		if ($currency != 'EUR') {
			$data['text_error_currency'] = $this->language->get('error_currency');
		} 
		
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');

		return $this->load->view('extension/' . $this->type . '/' . $this->name, $data);
    }

	public function redirect() {
	//	date_default_timezone_set('Europe/Vilnius');
		if (isset($this->request->post['bank'])) {
			$bank_id = $this->request->post['bank'];
		} else if (isset($this->request->get['bank'])) {
			$bank_id = $this->request->get['bank'];
		} else {
			$bank_id = '';
			$this->session->data['error'] = "Bank ID is missing! Please try again, or choose another payment method.";
			$this->KevinLog($this->session->data['error']);
			$this->response->redirect($this->url->link('checkout/cart'));
		}

		require_once dirname(dirname(dirname(__DIR__))) . '/model/extension/payment/kevin/vendor/autoload.php';
		
		$clientId = $this->config->get('payment_kevin_client_id');
		$clientSecret = $this->config->get('payment_kevin_client_secret');
		$options = ['error' => 'array'];

		$kevinClient = new Client($clientId, $clientSecret, $options);
		$this->load->model('checkout/order');
        $this->load->model('extension/payment/kevin');
		$this->load->language('extension/payment/kevin');

		$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
		
		if (!$order_info) {
			$this->KevinLog('Order ID: ' . $this->session->data['order_id'] . ' missing in DB. ');
			$this->session->data['error'] = 'Order ID: ' . $this->session->data['order_id'] . ' missing in database. If you want to order the desired products, please try again.';
			unset($this->session->data['order_id']);
			$this->response->redirect($this->url->link('checkout/cart'));
		}
				
		$ip_address = $order_info['ip'];

	//	echo '<pre>id:';	print_r($order_info['order_id']); echo '</pre>'; die;
		$order_id = (int)$order_info['order_id'];
		$order_status_id = $order_info['order_status_id'];
		
		if(!$order_info) {
			$order_info['total'] = 0;
			$order_info['currency_code'] = $this->config->get('config_currency');
			$order_info['currency_value'] = 1;
		}
		
		$total = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false, false);
	
			
		// Vendor logo can be added to the payment confirmation, if Kevin API will support it.
        if ($this->config->get('config_logo') && file_exists(DIR_IMAGE . $this->config->get('config_logo'))) {
            $vendor_logo = $this->config->get('config_url') . 'image/' . $this->config->get('config_logo') . ' ';
        } else {
            $vendor_logo = '';
        }
		//('<img src="' . $vendor_logo . '" style="height: 32px width: auto;" />')
		$confirm_url = $this->url->link('extension/payment/kevin/confirm');
        $webhook_url = $this->url->link('extension/payment/kevin/webhook');
		
		if (!empty($this->config->get('payment_kevin_redirect_preferred') && $this->config->get('payment_kevin_redirect_preferred') == 1)) {
			$redirect_preferred = true;
		} else {
			$redirect_preferred = false;
		}
		
		$payment_attr = [
			'redirectPreferred'       => $redirect_preferred,
			'bankId'                  => $bank_id,
            'Redirect-URL'            => $confirm_url,
            'Webhook-URL'             => $webhook_url,
            'endToEndId'              => $order_id,
            'informationUnstructured' => sprintf('Order ID %s', $order_id),
            'currencyCode'            => $order_info['currency_code'],
            'amount'                  => number_format((float)$total, 2, '.', ''),
			'creditorName'            => $this->config->get('payment_kevin_client_company'),
			'creditorAccount'         => [
				'iban'                => $this->config->get('payment_kevin_client_iban')
			]
		];
		
		$init_payment = $kevinClient->payment()->initPayment($payment_attr);

		//echo '<pre>';	print_r($init_payment); echo '</pre>'; die;
		
		if (!empty($init_payment['id'])) {
			$payment_id = $init_payment['id'];
		} else {
			$log_data = 'Answer on Redirect Kevin... '  . $init_payment['error']['description'] . ': '  . $init_payment['error']['code'] . '.';
			$this->KevinLog($log_data);
			$this->session->data['error'] = $this->language->get('error_kevin_payment') . ' Code: '. $init_payment['error']['code'];
			$this->response->redirect($this->url->link('checkout/cart'));
			$payment_id = 0;
		}
	
		$this->model_extension_payment_kevin->addKevinOrder($order_info, $init_payment, $ip_address, $order_status_id, $total);

		$get_payment_attr = ['PSU-IP-Address' => $ip_address];
		$get_payment = $kevinClient->payment()->getPayment($payment_id, $get_payment_attr);

		/*log*/
		$log_data = 'Answer on Redirect Kevin... Payment ID: ' . $payment_id . '; Order ID: ' . $order_id . '; Payment Status: ' . $get_payment['statusGroup'] . '; Total: ' . $get_payment['amount'] . $get_payment['currencyCode'] . '; Bank ID: ' . $bank_id . '.';
		$this->KevinLog($log_data);
		
		$current_country_code = $order_info['payment_iso_code_2'];
		$lang_code = $this->language->get('code');
		$available_lang = array('en', 'lt', 'lv', 'ee', 'fi', 'se', 'ru');
		if (in_array($lang_code, $available_lang)) {
			$lang = $lang_code;
		} else {
			$lang = 'en';
		}
		
		//header('Location:' . $init_payment['confirmLink'] . '&amp;lang=' . $lang);
		$this->response->redirect($init_payment['confirmLink'] . '&amp;lang=' . $lang);
	}

    public function confirm() {
		//date_default_timezone_set('Europe/Vilnius');
		unset($this->session->data['error']);

		require_once dirname(dirname(dirname(__DIR__))) . '/model/extension/payment/kevin/vendor/autoload.php';
		
		$clientId = $this->config->get('payment_kevin_client_id');
		$clientSecret = $this->config->get('payment_kevin_client_secret');
		$options = ['error' => 'array'];

		$kevinClient = new Client($clientId, $clientSecret, $options);
  
        $this->language->load('extension/payment/kevin');
        $this->load->model('checkout/order');
        $this->load->model('extension/payment/kevin');

		if (isset($this->request->get['paymentId'])) {
            $payment_id = $this->request->get['paymentId'];
        } elseif (isset($_POST['paymentId'])) {
            $payment_id = $_POST['paymentId'];
        } else {
            $payment_id = '';
			$log_data = 'On the order confirm Payment ID not received from Kevin\'s server!';
        }
	
		if (!$payment_id) {
			$this->KevinLog($log_data);
			$this->session->data['error'] = $this->language->get('error_kevin_payment_id');
			$this->response->redirect($this->url->link('checkout/cart'));
		}
		
		$order_query = $this->model_extension_payment_kevin->getKevinOrders($payment_id);
	
        if ($order_query) {
            $order_id = $order_query['order_id'];
			$old_status_id = $order_query['order_status_id'];
        } else {
            $order_id = 0;
			$this->session->data['error'] = 'An error occurred. Order Session have been ended! Please try again.';
			$this->KevinLog($this->session->data['error']);
			$this->response->redirect($this->url->link('checkout/cart'));
        }
		
		$order_info = $this->model_checkout_order->getOrder($order_id);
		
		$ip_address = $order_info['ip'];
		$payment_status_attr = ['PSU-IP-Address' => $ip_address];
		$get_payment_status = $kevinClient->payment()->getPaymentStatus($payment_id, $payment_status_attr);

		switch ($get_payment_status['group']) {
			case 'started':
				$new_status_id = $this->config->get('payment_kevin_started_status_id');
				$new_status = $get_payment_status['group'];
				break;
			case 'pending':
				$new_status_id = $this->config->get('payment_kevin_pending_status_id'); 
				$new_status = $get_payment_status['group'];
				break;
			case 'completed':
				$new_status_id = $this->config->get('payment_kevin_completed_status_id');
				$new_status = $get_payment_status['group'];
				break;
			case 'failed':
				$new_status_id = $this->config->get('payment_kevin_failed_status_id');
				$new_status = $get_payment_status['group'];
				break;
			default:
				$new_status_id = null;
				$new_status = $get_payment_status['group'];
		}

		if (!$new_status_id) {
			$this->session->data['error'] = 'An error occurred. On response not received any status group.' . $get_payment_status['error']['code'] . ' ' . $get_payment_status['error']['description'];
			$this->KevinLog($this->session->data['error']);
			$this->response->redirect($this->url->link('checkout/cart'));
		}
		
		/*log*/
		$log_data = 'Answer on Confirm Kevin... Payment ID: ' . $payment_id . '; Order ID: ' . $order_info['order_id'] . '; Payment Status: ' . $new_status  . '.';
		
		if ($old_status_id != $new_status_id && $order_info['order_id'] == $this->session->data['order_id']) {
			$this->KevinLog($log_data);
			$this->model_extension_payment_kevin->updateConfirmKevinOrder($payment_id, $get_payment_status, $new_status_id);
			$payment_status = true;
		} else {
			$payment_status = false;
		}
        
		/*validate order*/

        if ($new_status == 'completed') {
            $order_status_id = $this->config->get('payment_kevin_completed_status_id');
			if ($payment_status) {
				$this->model_checkout_order->addOrderHistory($order_id, $order_status_id, '', true);
				$this->response->redirect($this->url->link('checkout/success'));
			}	
		} else if ($new_status == 'pending') {
			$order_status_id = $this->config->get('payment_kevin_pending_status_id');
			if ($payment_status) {
				$this->model_checkout_order->addOrderHistory($order_id, $order_status_id, '', true);
				$this->response->redirect($this->url->link('checkout/success'));
			}
        } else if ($new_status == 'failed') {
			$order_status_id = $this->config->get('payment_kevin_failed_status_id');
			if ($payment_status) {
				$this->model_checkout_order->addOrderHistory($order_id, $order_status_id, '', true);
				$this->response->redirect($this->url->link('checkout/failure'));
			}
        } else {
			$this->session->data['error'] = $this->language->get('error_kevin_payment') . $get_payment_status['error']['code'] . ' ' . $get_payment_status['error']['description'];
			$this->response->redirect($this->url->link('checkout/cart'));
		}
    }

    public function webhook() {
		//date_default_timezone_set('Europe/Vilnius');
        $this->language->load('extension/payment/kevin');
        $this->load->model('checkout/order');
        $this->load->model('extension/payment/kevin');
		
		$webhook_data = file_get_contents('php://input');
		
		if ($webhook_data !== false && !empty($webhook_data)) {
			$get_payment_status = json_decode($webhook_data, true);
		} else {
			$this->KevinLog('Payment status not received from the remote server!');
			die();
		}
		
		$payment_id = $get_payment_status['id'];

		switch ($get_payment_status['statusGroup']) {
			case 'started':
				$new_status_id = $this->config->get('payment_kevin_started_status_id');
				$new_status = $get_payment_status['statusGroup'];
				break;
			case 'pending':
				$new_status_id = $this->config->get('payment_kevin_pending_status_id'); 
				$new_status = $get_payment_status['statusGroup'];
				break;
			case 'completed':
				$new_status_id = $this->config->get('payment_kevin_completed_status_id');
				$new_status = $get_payment_status['statusGroup'];
				break;
			case 'failed':
				$new_status_id = $this->config->get('payment_kevin_failed_status_id');
				$new_status = $get_payment_status['statusGroup'];
				break;
			default:
				$new_status_id = null;
				$new_status = $get_payment_status['statusGroup'];
		}
		
		$order_query = $this->model_extension_payment_kevin->getKevinOrders($payment_id);
	
        if ($order_query) {
            $order_id = $order_query['order_id'];
			$old_status_id = $order_query['order_status_id'];
        } else {
            $order_id = 0;
			$this->KevinLog('An error occurred. The order have been dleted from database.');
			$die();
        }

		$order_info = $this->model_checkout_order->getOrder($order_id);
		
		$log_data = 'Answer on WebHook Kevin... Payment ID: ' . $payment_id . '; Order ID: ' . $order_info['order_id'] . '; Payment Status: ' . $new_status . '.';
		
		//$old_status_id = $order_info['order_status_id'];
		
		if ($old_status_id != $new_status_id && $order_info['order_id'] == $order_id) {
			$this->KevinLog($log_data);
			$this->model_extension_payment_kevin->updateWebhookKevinOrder($payment_id, $get_payment_status, $new_status_id);
			$payment_status = true;
		} else {
			$payment_status = false;
		}

		if  ($new_status == 'completed' && $payment_status){
			$order_status_id = $this->config->get('payment_kevin_completed_status_id');
			$this->model_checkout_order->addOrderHistory($order_id, $order_status_id, '', true);
			
		} else if ($new_status == 'pending' && $payment_status) {
			$order_status_id = $this->config->get('payment_kevin_pending_status_id');
			$this->model_checkout_order->addOrderHistory($order_id, $order_status_id, '', true);

        } else if ($new_status == 'failed' && $payment_status) {
			$order_status_id = $this->config->get('payment_kevin_failed_status_id');
			$this->model_checkout_order->addOrderHistory($order_id, $order_status_id, '', true);

		} 
    }
		
	/*log*/
	public function KevinLog($log_data) {
		if ($this->config->get('payment_kevin_log')) {
            $kevin_log = new Log('kevin_payment.log');
            $kevin_log->write($log_data);
		} else { 
			null; 
		}
	}
}
