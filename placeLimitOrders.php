<?php
header('Content-Type: application/json');

// Get the posted JSON data (orders)
$orders = json_decode(file_get_contents('php://input'), true);

// Check if orders are valid
if (empty($orders) || !is_array($orders)) {
  echo json_encode(["status" => "error", "msg" => "Invalid orders data"]);
  exit;
}

$apiKey    = $_ENV['BINANCE_API_KEY'];
$apiSecret = $_ENV['BINANCE_API_SECRET'];
$symbol    = "ETHUSDT";

if (!$apiKey || !$apiSecret) {
  echo json_encode(["status"=>"error","msg"=>"Missing API keys on server"]);
  exit;
}


// Binance API base URL
$baseUrl = "https://api.binance.com";
$path = "/sapi/v1/margin/order"; // to place limit orders on isolated margin

$symbol = "ETHUSDT"; // You can change the symbol if needed
$isIsolated = "TRUE"; // Isolated margin

// Loop through each order and place it
$successCount = 0;
foreach ($orders as $order) {
  $price = $order['price'];
  $lotSize = $order['lotSize'];

  $timestamp = (int)(microtime(true) * 1000);
  $recvWindow = 5000;

  // Parameters for placing limit orders
  $params = [
    "symbol" => $symbol,
    "side" => "BUY", // or SELL based on your requirement
    "type" => "LIMIT",
    "price" => $price,
    "quantity" => $lotSize,
    "timeInForce" => "GTC", // Good till canceled
    "isIsolated" => $isIsolated,
    "recvWindow" => $recvWindow,
    "timestamp" => $timestamp,
  ];

  // Create signature
  $query = http_build_query($params, '', '&');
  $signature = hash_hmac('sha256', $query, $apiSecret);
  $url = $baseUrl . $path . "?" . $query . "&signature=" . $signature;

  // Execute cURL
  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
      "X-MBX-APIKEY: " . $apiKey
    ],
    CURLOPT_TIMEOUT => 20,
  ]);

  $res = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err = curl_error($ch);
  curl_close($ch);

  // If cURL error
  if ($err) {
    echo json_encode(["status" => "error", "msg" => "cURL error: " . $err]);
    exit;
  }

  // Binance response
  $data = json_decode($res, true);

  if ($http >= 200 && $http < 300 && isset($data['orderId'])) {
    $successCount++;
  }
}

echo json_encode([
  "status" => "success",
  "orderCount" => $successCount
]);
?>