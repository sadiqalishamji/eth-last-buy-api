<?php
$apiKey = $_ENV['BINANCE_API_KEY'];
$apiSecret = $_ENV['BINANCE_API_SECRET'];
$symbol = "ETHUSDT";

// Timestamp and signature
$timestamp = round(microtime(true) * 1000);
$query = "symbol=$symbol&timestamp=$timestamp";
$signature = hash_hmac('sha256', $query, $apiSecret);
$url = "https://api.binance.com/api/v3/openOrders?$query&signature=$signature";

// cURL request
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-MBX-APIKEY: $apiKey"]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

// Fetch current price
$ch = curl_init("https://api.binance.com/api/v3/ticker/price?symbol=$symbol");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$currentPriceResp = curl_exec($ch);
curl_close($ch);
$currentPriceData = json_decode($currentPriceResp, true);
$currentPrice = $currentPriceData['price'] ?? 0;

$nearestBuy = 0;
$minDiff = PHP_FLOAT_MAX;

if (!empty($data)) {
    foreach ($data as $order) {
        if ($order['side'] === 'BUY' && $order['type'] === 'LIMIT') {
            $diff = abs($order['price'] - $currentPrice);
            if ($diff < $minDiff) {
                $minDiff = $diff;
                $nearestBuy = $order['price'];
            }
        }
    }
}

echo json_encode(['nearestBuyPrice' => $nearestBuy]);
