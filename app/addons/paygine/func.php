<?php

use Tygh\Enum\ObjectStatuses;
use Tygh\Enum\YesNo;
use Tygh\Http;
use Tygh\Enum\OrderDataTypes;
use Tygh\Enum\OrderStatuses;

defined('BOOTSTRAP') or die('Access denied');

/**
 * Creates Paygine payment processor on add-on installation.
 *
 * @return void
 */
function fn_paygine_add_payment_processor()
{
	db_query(
		'INSERT INTO ?:payment_processors ?e', [
			'processor'          => 'Paygine',
			'processor_script'   => PAYGINE_PROCESSOR,
			'processor_template' => 'views/orders/components/payments/cc_outside.tpl',
			'admin_template'     => 'paygine.tpl',
			'callback'           => YesNo::NO,
			'type'               => 'P',
			'addon'              => 'paygine',
		]
	);
}

/**
 * Removes Paygine payment processor and disables payment methods on add-on uninstallation.
 *
 * @return void
 */
function fn_paygine_delete_payment_processor()
{
	$addon_processor_id = db_get_field(
		'SELECT processor_id FROM ?:payment_processors WHERE processor_script = ?s',
		PAYGINE_PROCESSOR
	);
	
	db_query(
		'UPDATE ?:payments SET status = ?s, processor_params = ?s, processor_id = ?i WHERE processor_id = ?s',
		ObjectStatuses::DISABLED,
		'',
		0,
		$addon_processor_id
	);
	
	db_query(
		'DELETE FROM ?:payment_processors WHERE processor_id = ?i',
		$addon_processor_id
	);
}

function fn_paygine_get_url($params) {
	return empty($params['test_mode']) ? 'https://pay.paygine.com' : 'https://test.paygine.com';
}

function fn_paygine_parse_xml($string) {
	if (!$string)
		throw new Exception(__('paygine.empty_response'));
	$xml = simplexml_load_string($string);
	if (!$xml)
		throw new Exception(__('paygine.invalid_xml'));
	$valid_xml = json_decode(json_encode($xml), true);
	if (!$valid_xml)
		throw new Exception(__('paygine.invalid_xml'));
	return $valid_xml;
}

function fn_paygine_operation_is_valid($response, $params) {
	if(empty($response['reason_code']) && !empty($response['code']) && !empty($response['description']))
		throw new Exception($response['code'] . " : " . $response['description']);
	if(empty($response['signature']))
		throw new Exception(__('paygine.empty_signature'));
	$tmp_response = (array)$response;
	unset($tmp_response['signature'], $tmp_response['ofd_state']);
	$signature = base64_encode(md5(implode('', $tmp_response) . $params['password']));
	if ($signature !== $response['signature'])
		throw new Exception(__('paygine.invalid_signature'));
	if(!in_array($response['type'], PAYGINE_SUPPORTED_TYPES))
		throw new Exception(__('paygine.unknown_operation') . ' : ' . $response['type']);
	return true;
}

function fn_paygine_calc_fiscal_positions_shop_cart($order_info, $tax) {
	$fiscal_positions = '';
	$shop_cart = [];
	$fiscal_amount = 0;
	$sc_key = 0;
	foreach($order_info['products'] as $product) {
		$fiscal_positions .= $product['amount'] . ';';
		$element_price = intval(round($product['price'] * 100));
		$fiscal_positions .= $element_price . ';';
		$fiscal_positions .= $tax . ';';
		$fiscal_positions .= $product['product'] . '|';
		$fiscal_amount += $product['amount'] * $element_price;
		
		$shop_cart[$sc_key]['name'] = $product['product'];
		$shop_cart[$sc_key]['quantityGoods'] = (int) $product['amount'];
		$shop_cart[$sc_key]['goodCost'] = round($product['price'] * $shop_cart[$sc_key]['quantityGoods'], 2);
		$sc_key++;
	}
	if($order_info['shipping_cost'] > 0){
		$fiscal_positions .= '1;';
		$element_price = intval(round($order_info['shipping_cost'] * 100));
		$fiscal_positions .= $element_price . ';';
		$fiscal_positions .= $tax . ';';
		$fiscal_positions .= 'Доставка' . '|';
		$fiscal_amount += $element_price;
		
		$shop_cart[$sc_key]['quantityGoods'] = 1;
		$shop_cart[$sc_key]['goodCost'] = round($order_info['shipping_cost'], 2);
		$shop_cart[$sc_key]['name'] = 'Доставка';
	}
	$order_amount = intval($order_info['total'] * 100);
	$fiscal_diff = abs($fiscal_amount - $order_amount);
	if ($fiscal_diff) {
		$fiscal_positions .= '1;' . $fiscal_diff . ';6;Скидка;14|';
		$shop_cart = [];
	}
	$fiscal_positions = substr($fiscal_positions, 0, -1);
	return [$fiscal_positions, $shop_cart];
}

function fn_paygine_prepare_order_info(&$order_info) {
	if($order_info['payment_method']['processor'] == 'Paygine') {
		$payment_type = !empty($order_info['payment_info']['payment_type']) ? $order_info['payment_info']['payment_type'] : 'one_stage';
		$prefix = 'paygine.';
		$type_name = __($prefix . $payment_type);
		if(strpos($type_name, $prefix) === false)
			$order_info['payment_info']['payment_type'] = $type_name;
	}
}

function fn_paygine_order_can_be_refund($order_info) {
	if($order_info['payment_method']['processor'] == 'Paygine') {
		$status = !empty($order_info['payment_info']['status']) ? $order_info['payment_info']['status'] : '';
		$order_id = !empty($order_info['payment_info']['order_id']) ? $order_info['payment_info']['order_id'] : '';
		if ($order_id && ($status == 'COMPLETED' || $status == 'AUTHORIZED'))
			return true;
	}
	return false;
}

function fn_paygine_order_can_be_complete($order_info) {
	if($order_info['payment_method']['processor'] == 'Paygine') {
		$status = !empty($order_info['payment_info']['status']) ? $order_info['payment_info']['status'] : '';
		$order_id = !empty($order_info['payment_info']['order_id']) ? $order_info['payment_info']['order_id'] : '';
		if ($order_id && $status == 'AUTHORIZED')
			return true;
	}
	return false;
}

function fn_paygine_order_refund($order_info, $params) {
	$data = fn_paygine_prepare_order_data($order_info);
	fn_paygine_sign_data($data, $params);
	$payment_type = !empty($order_info['payment_info']['payment_type']) ? $order_info['payment_info']['payment_type'] : '';
	$path = ($payment_type == 'halva' || $payment_type == 'halva_two_steps') ? '/webapi/custom/svkb/Reverse' : '/webapi/Reverse';
	$url = fn_paygine_get_url($params) . $path;
	$response = Http::post($url, $data);
	$response_xml = fn_paygine_parse_xml($response);
	if (!fn_paygine_operation_is_valid($response_xml, $params))
		throw new Exception(__('paygine.operation_not_valid'));
	if($response_xml['state'] !== PAYGINE_OPERATION_APPROVED)
		throw new Exception(__('paygine.operation_not_approved'));
	return ['status' => $response_xml['order_state']];
}

function fn_paygine_order_complete($order_info, $params) {
	$data = fn_paygine_prepare_order_data($order_info);
	fn_paygine_sign_data($data, $params);
	$payment_type = !empty($order_info['payment_info']['payment_type']) ? $order_info['payment_info']['payment_type'] : '';
	$path = ($payment_type == 'halva' || $payment_type == 'halva_two_steps') ? '/webapi/custom/svkb/Complete' : '/webapi/Complete';
	$url = fn_paygine_get_url($params) . $path;
	$response = Http::post($url, $data);
	$response_xml = fn_paygine_parse_xml($response);
	if (!fn_paygine_operation_is_valid($response_xml, $params))
		throw new Exception(__('paygine.operation_not_valid'));
	if($response_xml['state'] !== PAYGINE_OPERATION_APPROVED)
		throw new Exception(__('paygine.operation_not_approved'));
	return ['status' => $response_xml['order_state']];
}

function fn_paygine_prepare_order_data($order_info) {
	$paygine_id = !empty($order_info['payment_info']['order_id']) ? $order_info['payment_info']['order_id'] : '';
	if(!$paygine_id)
		throw new Exception(__('paygine.no_order_id'));
	$amount = intval($order_info['total'] * 100);
	$currency = '643';
	return [
		'id' => $paygine_id,
		'amount' => $amount,
		'currency' => $currency
	];
}

function fn_paygine_sign_data(&$data, $params, $password = true) {
	$sign = $params['sector_id'] . implode('', $data);
	if($password)
		$sign .= $params['password'];
	$data['sector'] = $params['sector_id'];
	$data['signature'] = base64_encode(md5($sign));
}

function fn_paygine_get_custom_order_status($operation_type, $params) {
	switch($operation_type){
		case 'PURCHASE':
		case 'PURCHASE_BY_QR':
		case 'COMPLETE':
			return !empty($params['order_completed']) ? $params['order_completed'] : OrderStatuses::PAID;
		case 'AUTHORIZE':
			return !empty($params['order_authorized']) ? $params['order_authorized'] : OrderStatuses::PAID;
		case 'REVERSE':
			return !empty($params['order_canceled']) ? $params['order_canceled'] : OrderStatuses::CANCELED;
	}
	return '';
}