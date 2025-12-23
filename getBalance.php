<?php
header('Content-Type: application/json');

$apiKey    = $_ENV['BINANCE_API_KEY'];
$apiSecret = $_ENV['BINANCE_API_SECRET'];
$symbol    = "ETHUSDT";

$timestamp = round(microtime(true) * 1000);

/* ===================== SPOT BALANCE ===================== */
$spotQuery     = "timestamp=$timestamp";
$spotSignature = hash_hmac('sha256', $spotQuery, $apiSecret);
$spotUrl       = "https://api.binance.com/api/v3/account?$spotQuery&signature=$spotSignature";

$ch = curl_init($spotUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-MBX-APIKEY: $apiKey"]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$spotResponse = curl_exec($ch);
curl_close($ch);

$spotData = json_decode($spotResponse, true);

$spotUSDT = 0;
if (!empty($spotData['balances'])) {
    foreach ($spotData['balances'] as $bal) {
        if ($bal['asset'] === 'USDT') {
            $spotUSDT = (float)$bal['free']; // spot free balance
            break;
        }
    }
}

/* ===================== ISOLATED MARGIN BALANCE ===================== */
$marginQuery     = "timestamp=$timestamp&symbols=$symbol";
$marginSignature = hash_hmac('sha256', $marginQuery, $apiSecret);
$marginUrl       = "https://api.binance.com/sapi/v1/margin/isolated/account?$marginQuery&signature=$marginSignature";

$ch = curl_init($marginUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-MBX-APIKEY: $apiKey"]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$marginResponse = curl_exec($ch);
curl_close($ch);

$marginData = json_decode($marginResponse, true);

$isolatedUSDT = 0;
if (!empty($marginData['assets'])) {
    foreach ($marginData['assets'] as $assetObj) {
        $quote = $assetObj['quoteAsset'];
        if ($quote['asset'] === 'USDT') {
            $isolatedUSDT = (float)$quote['free']; // isolated free USDT
            break;
        }
    }
}

/* ===================== FINAL RESPONSE ===================== */
echo json_encode([
    'spotUSDT'     => $spotUSDT,
    'isolatedUSDT' => $isolatedUSDT,
    'totalUSDT'    => $spotUSDT + $isolatedUSDT
]);
