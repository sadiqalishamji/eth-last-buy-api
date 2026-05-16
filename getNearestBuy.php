<?php
header('Content-Type: application/json');

error_reporting(E_ALL);
ini_set('display_errors', 1);

$apiKey = getenv('BINANCE_API_KEY');
$apiSecret = getenv('BINANCE_API_SECRET');

$symbol = "ETHUSDT";

function sign($query, $secret) {
    return hash_hmac('sha256', $query, $secret);
}

if (!$apiKey || !$apiSecret) {
    echo json_encode([
        'status' => 'error',
        'msg' => 'API Key or Secret missing'
    ]);
    exit;
}

$timestamp = round(microtime(true) * 1000);
$recvWindow = 60000;

/*
|--------------------------------------------------------------------------
| GET OPEN ISOLATED MARGIN ORDERS
|--------------------------------------------------------------------------
*/

$query = http_build_query([
    'symbol' => $symbol,
    'isIsolated' => 'TRUE',
    'recvWindow' => $recvWindow,
    'timestamp' => $timestamp
]);

$signature = sign($query, $apiSecret);

$url = "https://api.binance.com/sapi/v1/margin/openOrders?$query&signature=$signature";

/*
|--------------------------------------------------------------------------
| CURL
|--------------------------------------------------------------------------
*/

$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "X-MBX-APIKEY: $apiKey"
    ],
    CURLOPT_TIMEOUT => 30,
]);

$response = curl_exec($ch);

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {

    echo json_encode([
        'status' => 'error',
        'msg' => 'CURL Error',
        'curlError' => curl_error($ch),
    ]);

    curl_close($ch);
    exit;
}

curl_close($ch);

$data = json_decode($response, true);

/*
|--------------------------------------------------------------------------
| DEBUG RESPONSE
|--------------------------------------------------------------------------
*/

if ($httpCode !== 200) {

    echo json_encode([
        'status' => 'error',
        'msg' => 'Binance HTTP Error',
        'httpCode' => $httpCode,
        'rawResponse' => $response,
        'decodedResponse' => $data
    ]);

    exit;
}

if (!is_array($data)) {

    echo json_encode([
        'status' => 'error',
        'msg' => 'Invalid JSON received from Binance',
        'rawResponse' => $response
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| FIND HIGHEST BUY PRICE
|--------------------------------------------------------------------------
*/

$highestPrice = 0;
$matchedOrders = [];

foreach ($data as $order) {

    if (
        isset($order['side']) &&
        isset($order['type']) &&
        isset($order['price']) &&
        $order['side'] === 'BUY' &&
        $order['type'] === 'LIMIT'
    ) {

        $price = (float)$order['price'];

        $matchedOrders[] = [
            'price' => $price,
            'origQty' => $order['origQty'] ?? '',
            'status' => $order['status'] ?? '',
            'orderId' => $order['orderId'] ?? '',
        ];

        if ($price > $highestPrice) {
            $highestPrice = $price;
        }
    }
}

/*
|--------------------------------------------------------------------------
| RETURN RESULT
|--------------------------------------------------------------------------
*/

echo json_encode([
    'status' => 'success',
    'nearestBuyPrice' => $highestPrice,
    'matchedOrdersCount' => count($matchedOrders),
    'matchedOrders' => $matchedOrders,
    'serverTime' => date('Y-m-d H:i:s'),
]);
?>