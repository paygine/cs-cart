<?php

if (!defined('BOOTSTRAP')) { die('Access denied'); }

if (defined('PAYMENT_NOTIFICATION')) {
    if (($mode == 'success') || ($mode == 'fail')){
        $operationId = !empty($_REQUEST['operation']) ? (int)$_REQUEST['operation'] : 0;
        $orderId = !empty($_REQUEST['order_id']) ? (int)$_REQUEST['order_id'] : 0;
        $id = !empty($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
        $order_info = fn_get_order_info($orderId);
        $sectorId=$order_info['payment_method']['processor_params']['project_id'];
        $password=$order_info['payment_method']['processor_params']['sign'];
        $test=$order_info['payment_method']['processor_params']['test'];
        $TAX=(isset($order_info['payment_method']['processor_params']['tax']) && $order_info['payment_method']['processor_params']['tax'] > 0 && $order_info['payment_method']['processor_params']['tax'] <= 6) ? $order_info['payment_method']['processor_params']['tax'] : 6;
        if (!$test) {
            $paygine_url = 'https://pay.paygine.com';
        } else {
            $paygine_url = 'https://test.paygine.com';
        }
        $signature = base64_encode(md5($sectorId . $id . $operationId  . $password));
        $url = $paygine_url.'/webapi/Operation';
        $context = stream_context_create(array(
            'http' => array(
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query(array(
                    'sector' => $sectorId,
                    'id' => $id,
                    'operation' => $operationId,
                    'signature' => $signature
                )),
            )
        ));
        $xml = file_get_contents($url, false, $context);

        if (!$xml)
            throw new Exception("Empty data");
        $xml = simplexml_load_string($xml);
        if (!$xml)
            throw new Exception("Non valid XML was received");
        $response = json_decode(json_encode($xml), true);
        if (!$response)
            throw new Exception("Non valid XML was received");

        $tmp_response = (array)$response;
        unset($tmp_response["signature"]);
        $signature = base64_encode(md5(implode('', $tmp_response) . $password));
        if ($signature !== $response['signature']){
            throw new Exception("Invalid signature");
        }
        if ($response['type'] == 'PURCHASE' && $response['state'] == 'APPROVED'){
            $pp_response['order_status'] = 'P';
            $pp_response['reason_text'] = 'Заказ успешно оплачен';
            $pp_response['transaction_id'] = $id;
        } else {
            $pp_response['order_status'] = 'F';
            $pp_response['reason_text'] = 'Ошибка оплаты';
            $pp_response['transaction_id'] = $id;
        }
        fn_finish_payment($orderId, $pp_response);
        fn_order_placement_routines('route', $orderId);
    } else if ($mode == 'notify'){

        $xml = file_get_contents("php://input");
        if (!$xml)
            throw new Exception("Empty data");
        $xml = simplexml_load_string($xml);
        if (!$xml)
            throw new Exception("Non valid XML was received");
        $response = json_decode(json_encode($xml));
        if (!$response)
            throw new Exception("Non valid XML was received");

        if ($response->reason_code) {

            $orderId = $response->reference;
            $order_info = fn_get_order_info($orderId);

            if ($response->reason_code == 1){
                $pp_response['order_status'] = 'P';
                $pp_response['reason_text'] = 'Заказ успешно оплачен';
                $pp_response['transaction_id'] = $id;
            } else {
                $pp_response['order_status'] = 'F';
                $pp_response['reason_text'] = 'Ошибка оплаты';
                $pp_response['transaction_id'] = $id;
            }
            fn_finish_payment($orderId, $pp_response);
            echo "ok";
        }
    }

} else {
    $sectorId=$order_info['payment_method']['processor_params']['project_id'];
    $password=$order_info['payment_method']['processor_params']['sign'];
    $test=$order_info['payment_method']['processor_params']['test'];
    $TAX=(isset($order_info['payment_method']['processor_params']['tax']) && $order_info['payment_method']['processor_params']['tax'] > 0 && $order_info['payment_method']['processor_params']['tax'] <= 6) ? $order_info['payment_method']['processor_params']['tax'] : 6;
    if (!$test) {
        $paygine_url = 'https://pay.paygine.com';
    } else {
        $paygine_url = 'https://test.paygine.com';
    }
    $url = $paygine_url.'/webapi/Register';
    $currency='643';
    $amount=intval($order_info["total"]*100);
    $signature = base64_encode(md5($sectorId . $amount . $currency . $password));
    $confirm_url = fn_url("payment_notification.success?payment=paygine&order_id=$order_id", AREA, 'current');
    $cancel_url = fn_url("payment_notification.fail?payment=paygine&order_id=$order_id", AREA, 'current');

    $fiscalPositions = '';
    foreach ($order_info['products'] as $product) {
        $fiscalPositions .= $product['amount'] . ';';
        $fiscalPositions .= $product['price']*100 . ';';        
        $fiscalPositions .= $TAX . ';';        
        $fiscalPositions .= $product['product'] . '|';        
    }
    if ($order_info['shipping_cost'] > 0) {
        $fiscalPositions.='1;';
        $fiscalPositions.=($order_info['shipping_cost']*100).';';
        $fiscalPositions.=$TAX.';';
        $fiscalPositions.='Доставка'.'|';
    }
    $fiscalPositions = substr($fiscalPositions, 0, -1);

    $data = http_build_query(array(
        'sector' => $sectorId,
        'reference' => $order_id,
        'fiscal_positions' => $fiscalPositions,
        'amount' => $amount,
        'description' => 'Оплата заказа '.$order_id,
        'email' => $order_info['email'],
        'phone' => $order_info['phone'],
        'currency' => $currency,
        'mode' => 1,
        'url' => $confirm_url,
        'failurl' => $cancel_url,
        'signature' => $signature
    ));

    $context = stream_context_create(array(
        'http' => array(
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n"
                . "Content-Length: " . strlen($data) . "\r\n",
            'method'  => 'POST',
            'content' => $data
        )
    ));
    $paygine_order_id = file_get_contents($paygine_url . '/webapi/Register', false, $context);
    if (intval($paygine_order_id) == 0) {
        throw new Exception('error register order');
    }
    $signature = base64_encode(md5($sectorId . $paygine_order_id . $password));
    $link  = $paygine_url
                . '/webapi/Purchase'
                . '?sector=' .$sectorId
                . '&id=' . $paygine_order_id
                . '&signature=' . $signature;

    $post_data = array(
    );

    fn_create_payment_form($link, $post_data, 'Paygine', false);
}
exit;
