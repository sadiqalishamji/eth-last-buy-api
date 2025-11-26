<?php
header('Content-Type: application/json');

$apiKey = getenv('BINANCE_API_KEY');
$apiSecret = getenv('BINANCE_API_SECRET');
$symbol = "ETHUSDT";

// Get POST data
$price = $_POST['price'] ?? 0;
$lot   = $_POST['lot'] ?? 0;
if (!$price || !$lot) {
    echo json_encode(['status'=>'error','msg'=>'Invalid data']);
    exit;
}

// Binance signature function
function sign($query, $secret) {
    return hash_hmac('sha256', $query, $secret);
}

// ===== 1. Market SELL Order (Isolated Margin) =====
$timestamp = round(microtime(true) * 1000);
$recvWindow = 60000;
$querySell = "symbol=$symbol&side=SELL&type=MARKET&quantity=$lot&isIsolated=true&recvWindow=$recvWindow&timestamp=$timestamp";
$signatureSell = sign($querySell, $apiSecret);
$urlSell = "https://api.binance.com/sapi/v1/margin/order?$querySell&signature=$signatureSell";

$ch = curl_init($urlSell);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-MBX-APIKEY: $apiKey"]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$responseSell = curl_exec($ch);
curl_close($ch);
$sellResp = json_decode($responseSell, true);

// Check for error
if (isset($sellResp['code']) && $sellResp['code'] < 0) {
    echo json_encode(['status'=>'error','msg'=>'Sell failed','binanceResponse'=>$sellResp]);
    exit;
}

// ===== 2. Buy LIMIT Order (Isolated Margin) =====
$timestamp = round(microtime(true) * 1000);
$queryBuy = "symbol=$symbol&side=BUY&type=LIMIT&timeInForce=GTC&quantity=$lot&price=$price&isIsolated=true&recvWindow=$recvWindow&timestamp=$timestamp";
$signatureBuy = sign($queryBuy, $apiSecret);
$urlBuy = "https://api.binance.com/sapi/v1/margin/order?$queryBuy&signature=$signatureBuy";

$ch = curl_init($urlBuy);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-MBX-APIKEY: $apiKey"]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$responseBuy = curl_exec($ch);
curl_close($ch);
$buyResp = json_decode($responseBuy, true);

// Check for error
if (isset($buyResp['code']) && $buyResp['code'] < 0) {
    echo json_encode(['status'=>'error','msg'=>'Limit buy failed','binanceResponse'=>$buyResp]);
    exit;
}

// ===== Final Success Output (Real) =====
echo json_encode([
    'status'=>'success',
    'sellResponse'=>$sellResp,
    'buyResponse'=>$buyResp
]);
?>
