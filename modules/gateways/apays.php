<?php
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Define module related meta data.
 *
 * Values returned here are used to determine module related capabilities and
 * settings.
 *
 * @see https://developers.whmcs.com/payment-gateways/meta-data-params/
 *
 * @return array
 */
function apays_MetaData()
{
    return array(
        'DisplayName' => 'APay Merchant Gateway Module',
        'APIVersion' => '1.1',
        'DisableLocalCredtCardInput' => true,
        'TokenisedStorage' => false,
    );
}

/**
 * Define gateway configuration options.
 *
 * The fields you define here determine the configuration options that are
 * presented to administrator users when activating and configuring your
 * payment gateway module for use.
 *
 * Supported field types include:
 * * text
 * * password
 * * yesno
 * * dropdown
 * * radio
 * * textarea
 *
 * Examples of each field type and their possible configuration parameters are
 * provided in the sample function below.
 *
 * @see https://developers.whmcs.com/payment-gateways/configuration/
 *
 * @return array
 */
function apays_config()
{
    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'APay',
        ),
        'client_id' => array(
            'FriendlyName' => 'Client-ID',
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => '',
        ),
        'secret_key' => array(
            'FriendlyName' => 'Secret-Key',
            'Type' => 'text',
            'Size' => '36',
            'Default' => '',
            'Description' => '',
        ),
    );
}

/**
 * Payment link.
 *
 * Required by third party payment gateway modules only.
 *
 * Defines the HTML output displayed on an invoice. Typically consists of an
 * HTML form that will take the user to the payment gateway endpoint.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see https://developers.whmcs.com/payment-gateways/third-party-gateway/
 *
 * @return string
 */
function apays_link($params)
{
    $client_id = $params['client_id'];
    $secret_key = $params['secret_key'];

    $sum = $params['amount'] * 10000;

    $invoice = $params['invoiceid'] . '-' . time();

    $input = 'client_id=' . $client_id . '&order_id=' . $invoice . '&amount=' . $sum . '&sign=' . md5($invoice . ':' . $sum . ':' . $secret_key);
    $url = 'https://apays.io/backend/create_order';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . '?' . $input);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return 'Error: Connection to payment system failed. Please try again later.';
    }

    if ($httpCode != 200) {
        return 'Error: Payment system returned an error. Please try again later.';
    }

    $result = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return 'Error: Invalid response from payment system. Please try again later.';
    }

    if (!isset($result['status']) || !$result['status']) {
        return 'Error: Payment initialization failed. Please try again later.';
    }

    if (!isset($result['url'])) {
        return 'Error: Payment URL not received. Please try again later.';
    }

    $host = parse_url($result['url'])['host'] . parse_url($result['url'])['path'];
    $query = parse_url($result['url'])['query'];
    parse_str($query, $params);

    $newSign = md5($invoice . ':' . $secret_key);
    logTransaction($gatewayParams['name'], 'Invoice: ' . $invoice . ' New Sign: ' . $newSign, 'Order Created');
    return '<form method="GET" action="' . htmlspecialchars("https://" . $host, ENT_QUOTES, 'UTF-8') . '">'
        . '<input type="hidden" name="id" value="' . htmlspecialchars($params['id'], ENT_QUOTES, 'UTF-8') . '" />'
        . '<input type="submit" value="Pay Now" class="btn btn-primary" />'
        . '</form>';
}
