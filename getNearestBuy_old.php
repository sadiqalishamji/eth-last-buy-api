<?php
header('Content-Type: application/json');

$apiKey = getenv('BINANCE_API_KEY');
$apiSecret = getenv('BINANCE_API_SECRET');
$symbol = "ETHUSDT";

// Binance signature function
function sign($query, $secret) {
    return hash_hmac('sha256', $query, $secret);
}

// Timestamp & recvWindow
$timestamp = round(microtime(true) * 1000);
$recvWindow = 60000;

// Build query for margin allOrders with isolated filter
$query = "symbol=$symbol&isIsolated=true&status=NEW&type=LIMIT&recvWindow=$recvWindow&timestamp=$timestamp";
$signature = sign($query, $apiSecret);
$url = "https://api.binance.com/sapi/v1/margin/allOrders?$query&signature=$signature";

// CURL request
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-MBX-APIKEY: $apiKey"]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

// If Binance returned an error, return it directly
if (isset($data['code']) && $data['code'] < 0) {
    echo json_encode(['status'=>'error','msg'=>'Binance API error','binanceResponse'=>$data]);
    exit;
}

$highestPrice = 0;

// Loop orders & find highest LIMIT BUY price
if (!empty($data)) {
    foreach ($data as $order) {
        if ($order['side'] === 'BUY' && $order['type'] === 'LIMIT' && $order['status'] === 'NEW') {
            $orderPrice = floatval($order['price']); // convert string to float
            if ($orderPrice > $highestPrice) {
                $highestPrice = $orderPrice;
            }
        }
    }
}

echo json_encode([
    'nearestBuyPrice' => $highestPrice
]);
?>
