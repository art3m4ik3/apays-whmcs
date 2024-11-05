<?php

require_once __DIR__ . '/../../../init.php';
App::load_function('gateway');
App::load_function('invoice');

$gatewayModuleName = basename(__FILE__, '.php');

$gatewayParams = getGatewayVariables($gatewayModuleName);

if (!$gatewayParams['type']) {
    die('Module Not Activated');
}

$order_id = filter_input(INPUT_GET, 'order_id');
$status = filter_input(INPUT_GET, 'status');
$receivedSign = filter_input(INPUT_GET, 'sign');

$secret_key = $gatewayParams['secret_key'];

$generatedSign = md5($order_id . ':' . $status . ':' . $secret_key);

if ($generatedSign === $receivedSign) {
    if ($status === 'approve') {
        addInvoicePayment($order_id, $order_id, 0, 0, $gatewayModuleName);
        logTransaction($gatewayParams['name'], 'Payment approved', 'Success');
    } elseif ($status === 'decline') {
        logTransaction($gatewayParams['name'], 'Payment declined', 'Decline');
    }

    http_response_code(200);
    echo 'OK';
} else {
    logTransaction($gatewayParams['name'], 'Invalid signature' . " sign: " . $generatedSign . " received sign: " . $receivedSign . " order_id: " . $order_id . " status: " . $status, 'Failure');
    http_response_code(400);
    echo 'Invalid signature';
}
