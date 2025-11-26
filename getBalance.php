<?php
header('Content-Type: application/json');

$apiKey = $_ENV['BINANCE_API_KEY'];
$apiSecret = $_ENV['BINANCE_API_SECRET'];
$symbol = "ETHUSDT";  // or the pair you're using

$timestamp = round(microtime(true)*1000);
$query = "timestamp=$timestamp&symbols=$symbol";
$signature = hash_hmac('sha256', $query, $apiSecret);

$url = "https://api.binance.com/sapi/v1/margin/isolated/account?$query&signature=$signature";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-MBX-APIKEY: $apiKey"]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

// default
$usdtFree = 0;

if (!empty($data['assets'])) {
    foreach ($data['assets'] as $assetObj) {
        $quote = $assetObj['quoteAsset'];
        if ($quote['asset'] === 'USDT') {
            $usdtFree = (float)$quote['free'];  // available (unlocked) USDT
            break;
        }
    }
}

echo json_encode(['freeUSDT' => $usdtFree]);
