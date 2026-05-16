<?php
header('Content-Type: application/json');

$apiKey = getenv('BINANCE_API_KEY');
$apiSecret = getenv('BINANCE_API_SECRET');

$symbol = "ETHUSDT";

function sign($query, $secret) {
    return hash_hmac('sha256', $query, $secret);
}

$timestamp = round(microtime(true) * 1000);
$recvWindow = 60000;

/*
|--------------------------------------------------------------------------
| STEP 1: GET CURRENT PRICE
|--------------------------------------------------------------------------
*/

$tickerUrl = "https://api.binance.com/api/v3/ticker/price?symbol=$symbol";

$tickerResponse = file_get_contents($tickerUrl);
$tickerData = json_decode($tickerResponse, true);

$currentPrice = floatval($tickerData['price']);

/*
|--------------------------------------------------------------------------
| STEP 2: GET ALL MARGIN ORDERS
|--------------------------------------------------------------------------
*/

$query = "symbol=$symbol&isIsolated=true&recvWindow=$recvWindow&timestamp=$timestamp";

$signature = sign($query, $apiSecret);

$url = "https://api.binance.com/sapi/v1/margin/allOrders?$query&signature=$signature";

$ch = curl_init($url);

curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "X-MBX-APIKEY: $apiKey"
]);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);

curl_close($ch);

$data = json_decode($response, true);

/*
|--------------------------------------------------------------------------
| HANDLE BINANCE ERROR
|--------------------------------------------------------------------------
*/

if (isset($data['code']) && $data['code'] < 0) {

    echo json_encode([
        'status' => 'error',
        'binanceResponse' => $data
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| FIND NEAREST BUY ORDER BELOW CURRENT PRICE
|--------------------------------------------------------------------------
*/

$nearestBuyPrice = null;
$smallestDifference = PHP_FLOAT_MAX;

foreach ($data as $order) {

    if (
        $order['side'] === 'BUY' &&
        $order['type'] === 'LIMIT' &&
        $order['status'] === 'NEW'
    ) {

        $orderPrice = floatval($order['price']);

        // only below market price
        if ($orderPrice <= $currentPrice) {

            $difference = $currentPrice - $orderPrice;

            if ($difference < $smallestDifference) {

                $smallestDifference = $difference;
                $nearestBuyPrice = $orderPrice;
            }
        }
    }
}

echo json_encode([
    'currentPrice' => $currentPrice,
    'nearestBuyPrice' => $nearestBuyPrice
]);
?>