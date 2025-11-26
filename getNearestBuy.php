<?php
header('Content-Type: application/json');

$apiKey = $_ENV['BINANCE_API_KEY'];
$apiSecret = $_ENV['BINANCE_API_SECRET'];
$symbol = "ETHUSDT";

// Binance signature function
function sign($query, $secret) {
    return hash_hmac('sha256', $query, $secret);
}

// Current timestamp
$timestamp = round(microtime(true) * 1000);
$query = "symbol=$symbol&isIsolated=true&timestamp=$timestamp";
$signature = sign($query, $apiSecret);

$url = "https://api.binance.com/sapi/v1/margin/allOrders?$query&signature=$signature";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-MBX-APIKEY: $apiKey"]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
$nearestPrice = 0;
$nearestDiff = PHP_INT_MAX;

// Fetch current price from Binance
$priceResp = file_get_contents("https://api.binance.com/api/v3/ticker/price?symbol=$symbol");
$currentPrice = json_decode($priceResp, true)['price'] ?? 0;

// Find nearest isolated LIMIT buy order
if (!empty($data)) {
    foreach ($data as $order) {
        if ($order['side'] === 'BUY' && $order['status'] === 'NEW' && $order['type'] === 'LIMIT') {
            $diff = abs($currentPrice - $order['price']);
            if ($diff < $nearestDiff) {
                $nearestDiff = $diff;
                $nearestPrice = $order['price'];
            }
        }
    }
}

echo json_encode(['nearestBuyPrice' => $nearestPrice, 'currentPrice' => $currentPrice]);
