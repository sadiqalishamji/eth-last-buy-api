<?php
$apiKey = $_ENV['BINANCE_API_KEY'] ?? 'DUMMY_KEY';
$apiSecret = $_ENV['BINANCE_API_SECRET'] ?? 'DUMMY_SECRET';

$symbol = "ETHUSDT";
$isIsolated = "true";
$limit = 10; 
$timestamp = round(microtime(true) * 1000);

$query = "symbol=$symbol&isIsolated=$isIsolated&limit=$limit&timestamp=$timestamp";
$signature = hash_hmac('sha256', $query, $apiSecret);

$url = "https://api.binance.com/sapi/v1/margin/myTrades?$query&signature=$signature";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-MBX-APIKEY: $apiKey"]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

$lastBuyPrice = 0;
if (!empty($data)) {
    foreach (array_reverse($data) as $trade) {
        if (isset($trade['isBuyer']) && $trade['isBuyer'] == true) {
            $lastBuyPrice = $trade['price'];
            break;
        }
    }
}

header('Content-Type: application/json');
echo json_encode(['lastBuyPrice' => $lastBuyPrice]);
