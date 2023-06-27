<?php
if (!defined('BOOTSTRAP')) die(__('paygine.access_denied'));

if($mode == 'refund') {
	
	$order_id = $_REQUEST['order_id'];
	$order_info = fn_get_order_info($order_id);
	$processor_data = fn_get_payment_method_data((int) $order_info['payment_id']);
	$params = !empty($processor_data['processor_params']) ? $processor_data['processor_params'] : [];
	try {
		$pp_response = fn_paygine_order_refund($order_info, $params);
	} catch(Exception $e) {
		fn_set_notification('E', 'ERROR', $e->getMessage());
		return [CONTROLLER_STATUS_OK, 'orders.details?order_id=' . $order_id];
	}
	fn_update_order_payment_info($order_info['order_id'], $pp_response);
	if(!empty($params['order_canceled']))
		fn_change_order_status($order_id, $params['order_canceled']);
	fn_set_notification('N', 'OK', __('paygine.refund_completed'));
	return [CONTROLLER_STATUS_OK, 'orders.details?order_id=' . $order_id];
	
} elseif($mode == 'complete') {
	
	$order_id = $_REQUEST['order_id'];
	$order_info = fn_get_order_info($order_id);
	$processor_data = fn_get_payment_method_data((int) $order_info['payment_id']);
	$params = !empty($processor_data['processor_params']) ? $processor_data['processor_params'] : [];
	try {
		$pp_response = fn_paygine_order_complete($order_info, $params);
	} catch(Exception $e) {
		fn_set_notification('E', 'ERROR', $e->getMessage());
		return [CONTROLLER_STATUS_OK, 'orders.details?order_id=' . $order_id];
	}
	fn_update_order_payment_info($order_info['order_id'], $pp_response);
	if(!empty($params['order_completed']))
		fn_change_order_status($order_id, $params['order_completed']);
	fn_set_notification('N', 'OK', __('paygine.payment_successful'));
	return [CONTROLLER_STATUS_OK, 'orders.details?order_id=' . $order_id];
	
}
exit;