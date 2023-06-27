<?php

use Tygh\Http;
use Tygh\Enum\OrderStatuses;

if(!defined('BOOTSTRAP')){
	die(__('paygine.access_denied'));
}

if(defined('PAYMENT_NOTIFICATION')){

	if(($mode == 'success') || ($mode == 'fail')){
		
		if(!empty($_REQUEST['modal'])) {
			$args = $_REQUEST;
			$args['action'] = "payment_notification.{$mode}";
			fn_create_payment_form(fn_url('paygine.redirect'), $args);
		}
		
		$order_id = !empty($_REQUEST['order_id']) ? (int)$_REQUEST['order_id'] : 0;
		if (!fn_check_payment_script(PAYGINE_PROCESSOR, $order_id)) {
			die(__('paygine.access_denied'));
		}
		$operation_id = !empty($_REQUEST['operation']) ? (int)$_REQUEST['operation'] : 0;
		$native_id = !empty($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
		$order_info = fn_get_order_info($order_id);
		$processor_data = fn_get_payment_method_data((int) $order_info['payment_id']);
		$params = !empty($processor_data['processor_params']) ? $processor_data['processor_params'] : [];
		
		$data = [
			'id' => $native_id,
			'operation' => $operation_id,
		];
		fn_paygine_sign_data($data, $params);
		$url = fn_paygine_get_url($params) . '/webapi/Operation';
		$operation = [];
		$operation_is_valid = false;
		try {
			$response = Http::post($url, $data);
			$operation = fn_paygine_parse_xml($response);
			$operation_is_valid = fn_paygine_is_operation_approved($operation, $params);
		} catch(Exception $e) {
			fn_set_notification('E', 'Error', $e->getMessage());
			fn_order_placement_routines('route', $order_id);
		}
		
		$pp_response['order_id'] = $native_id;
		$pp_response['status'] = $operation['order_state'];
		$pp_response['payment_type'] = !empty($params['payment_type']) ? $params['payment_type'] : '';
		$pp_response['currency'] = 'RUB';
		$pp_response['amount'] = (!empty($operation['buyIdSumAmount']) ? $operation['buyIdSumAmount'] : $operation['amount']) / 100;
		
		if($operation_is_valid && $operation['state'] === PAYGINE_OPERATION_APPROVED && in_array($operation['type'], PAYGINE_PAYMENT_TYPES))
			$pp_response['order_status'] = fn_paygine_get_custom_order_status($operation['type'], $params);
		else
			$pp_response['order_status'] = OrderStatuses::FAILED;
		
		fn_finish_payment($order_id, $pp_response);
		fn_order_placement_routines('route', $order_id);
		
	} elseif($mode == 'notify') {
		
		try {
			$response = file_get_contents("php://input");
			$response_xml = fn_paygine_parse_xml($response);
		} catch(Exception $e) {
			die($e->getMessage());
		}
		
		if(!empty($response_xml['reason_code'])) {
			$order_id = $response_xml['reference'];
			$order_info = fn_get_order_info($order_id);
			$processor_data = fn_get_payment_method_data((int) $order_info['payment_id']);
			$params = !empty($processor_data['processor_params']) ? $processor_data['processor_params'] : [];
			try {
				$operation_is_valid = fn_paygine_is_operation_approved($response_xml, $params);
				if(!$operation_is_valid)
					throw new Exception(__('paygine.operation_not_valid'));
			} catch(Exception $e) {
				die($e->getMessage());
			}
			$pp_response['order_id'] = $response_xml['order_id'];
			$pp_response['status'] = $response_xml['order_state'];
			
			if ($response_xml['reason_code'] == 1)
				$pp_response['order_status'] = fn_paygine_get_custom_order_status($response_xml['type'], $params);
			else
				$pp_response['order_status'] = OrderStatuses::FAILED;

			fn_update_order_payment_info($order_id, $pp_response);
			fn_change_order_status($order_id, $pp_response['order_status']);
			echo "ok";
		}
	}
	
} else {
	
	$params = !empty($processor_data['processor_params']) ? $processor_data['processor_params'] : [];
	$tax = (isset($params['tax']) && $params['tax'] > 0 && $params['tax'] <= 6) ? $params['tax'] : 6;
	$paygine_url = fn_paygine_get_url($params);
	$register_url = $paygine_url . '/webapi/Register';
	$currency = '643';
	$amount = intval($order_info['total'] * 100);
	$signature = base64_encode(md5($params['sector_id'] . $amount . $currency . $params['password']));
	$confirm_uri = "payment_notification.success?payment=paygine&order_id=$order_id";
	$cancel_uri = "payment_notification.fail?payment=paygine&order_id=$order_id";
	if(!empty($params['modal_payform'])) {
		$confirm_uri .= "&modal=1";
		$cancel_uri .= "&modal=1";
	}
	$confirm_url = fn_url($confirm_uri, AREA, 'current');
	$cancel_url = fn_url($cancel_uri, AREA, 'current');
	list($fiscal_positions, $shop_cart) = fn_paygine_calc_fiscal_positions_shop_cart($order_info, $tax);
	
	$data = [
		'sector' => $params['sector_id'],
		'reference' => $order_id,
		'fiscal_positions' => $fiscal_positions,
		'amount' => $amount,
		'description' => 'Оплата заказа ' . $order_id,
		'email' => $order_info['email'],
		'phone' => $order_info['phone'],
		'currency' => $currency,
		'mode' => 1,
		'url' => $confirm_url,
		'failurl' => $cancel_url,
		'signature' => $signature
	];
	
	try {
		$paygine_id = Http::post($register_url, $data);
		if(intval($paygine_id) == 0)
			throw new Exception(__('paygine.payment_process_error'));
	} catch(Exception $e){
		fn_set_notification('E', 'Error', $e->getMessage());
		fn_redirect('checkout.checkout');
	}
	
	$args = [
		'sector' => $params['sector_id'],
		'id' => $paygine_id,
	];
	
	switch($params['payment_type']){
		case 'two_steps':
			$payment_path = '/webapi/Authorize';
			break;
		case 'halva':
			$payment_path = '/webapi/custom/svkb/PurchaseWithInstallment';
			break;
		case 'halva_two_steps':
			$payment_path = '/webapi/custom/svkb/AuthorizeWithInstallment';
			break;
		case 'sbp':
			$payment_path = '/webapi/PurchaseSBP';
			break;
		default:
			$payment_path = '/webapi/Purchase';
	}
	
	$shop_cart_encoded = '';
	if($shop_cart && ($params['payment_type'] == 'halva' || $params['payment_type'] == 'halva_two_steps')) {
		$shop_cart_encoded = base64_encode(json_encode($shop_cart, JSON_UNESCAPED_UNICODE));
		$args['shop_cart'] = $shop_cart_encoded;
	}
	$args['signature'] = base64_encode(md5($params['sector_id'] . $paygine_id . $shop_cart_encoded . $params['password']));
	
	if (!empty($params['modal_payform']))
		fn_create_payment_form(fn_url('paygine.modal'), ['modal_url' => $paygine_url . $payment_path . '?' . http_build_query($args)]);
	
	$form_url = $paygine_url . $payment_path;
	fn_create_payment_form($form_url, $args, 'Paygine', false);
}
exit;
