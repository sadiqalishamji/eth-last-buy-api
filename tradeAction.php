<?php
header('Content-Type: application/json');

$apiKey = $_ENV['BINANCE_API_KEY'];
$apiSecret = $_ENV['BINANCE_API_SECRET'];
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

// Place market sell order
$timestamp = round(microtime(true) * 1000);
$query = "symbol=$symbol&side=SELL&type=MARKET&quantity=$lot&timestamp=$timestamp";
$signature = sign($query, $apiSecret);

$ch = curl_init("https://api.binance.com/api/v3/order?$query&signature=$signature");
curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-MBX-APIKEY: $apiKey"]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$marketSell = curl_exec($ch);
curl_close($ch);

// Place limit buy order
$timestamp = round(microtime(true) * 1000);
$query = "symbol=$symbol&side=BUY&type=LIMIT&timeInForce=GTC&quantity=$lot&price=$price&timestamp=$timestamp";
$signature = sign($query, $apiSecret);

$ch = curl_init("https://api.binance.com/api/v3/order?$query&signature=$signature");
curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-MBX-APIKEY: $apiKey"]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$limitBuy = curl_exec($ch);
curl_close($ch);

echo json_encode(['status'=>'success','marketSell'=>$marketSell,'limitBuy'=>$limitBuy]);
?>
