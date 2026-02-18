<?php
header('Content-Type: application/json');

$apiKey    = $_ENV['BINANCE_API_KEY'];
$apiSecret = $_ENV['BINANCE_API_SECRET'];
$symbol    = "ETHUSDT";

if (!$apiKey || !$apiSecret) {
  echo json_encode(["status"=>"error","msg"=>"Missing API keys on server"]);
  exit;
}
$baseUrl = "https://api.binance.com";
$path = "/sapi/v1/margin/openOrders"; // cancel ALL open orders on symbol (margin)

// Signed params
$timestamp = (int)(microtime(true) * 1000);
$recvWindow = 5000;

// isIsolated=TRUE is the important part for isolated margin
$params = [
  "symbol" => $symbol,
  "isIsolated" => "TRUE",
  "recvWindow" => $recvWindow,
  "timestamp" => $timestamp
];

$query = http_build_query($params, '', '&');
$signature = hash_hmac('sha256', $query, $apiSecret);
$url = $baseUrl . $path . "?" . $query . "&signature=" . $signature;

$ch = curl_init();
curl_setopt_array($ch, [
  CURLOPT_URL => $url,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_CUSTOMREQUEST => "DELETE",
  CURLOPT_HTTPHEADER => [
    "X-MBX-APIKEY: " . $apiKey
  ],
  CURLOPT_TIMEOUT => 20,
]);

$res = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
  echo json_encode(["status"=>"error","msg"=>"cURL error: ".$err]);
  exit;
}

$data = json_decode($res, true);

// Binance returns array of canceled orders on success (usually)
if ($http >= 200 && $http < 300 && is_array($data)) {
  echo json_encode([
    "status" => "success",
    "cancelledCount" => count($data),
    "orders" => $data
  ]);
  exit;
}

// Error shape from Binance often includes code/msg
$msg = $data["msg"] ?? $res;
echo json_encode([
  "status" => "error",
  "msg" => $msg,
  "http" => $http,
  "binanceResponse" => $data
]);
