<?php
header('Content-Type: application/json');

$apiKey    = $_ENV['BINANCE_API_KEY'];
$apiSecret = $_ENV['BINANCE_API_SECRET'];
$symbol    = "ETHUSDT";

function signedGet($url, $apiKey, $apiSecret, $params = []) {
    $timestamp = (int) round(microtime(true) * 1000);
    $params['timestamp'] = $timestamp;

    // optional safety
    if (!isset($params['recvWindow'])) $params['recvWindow'] = 5000;

    ksort($params);
    $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    $sig   = hash_hmac('sha256', $query, $apiSecret);

    $fullUrl = $url . '?' . $query . '&signature=' . $sig;

    $ch = curl_init($fullUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-MBX-APIKEY: $apiKey"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        return [null, "cURL error: $err"];
    }

    $data = json_decode($resp, true);
    if (!is_array($data)) {
        return [null, "Invalid JSON from Binance"];
    }

    // Binance error format often: { code: -xxxx, msg: "..." }
    if (isset($data['code']) && isset($data['msg']) && !isset($data['balances']) && !isset($data['assets'])) {
        return [null, "Binance error {$data['code']}: {$data['msg']}"];
    }

    return [$data, null];
}

function publicGet($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        return [null, "cURL error: $err"];
    }

    $data = json_decode($resp, true);
    if (!is_array($data)) {
        return [null, "Invalid JSON from Binance"];
    }

    return [$data, null];
}

/* ===================== 1) GET ALL PRICES ONCE ===================== */
[$tickerAll, $tickerErr] = publicGet("https://api.binance.com/api/v3/ticker/price");
$priceMap = [];

if ($tickerAll) {
    foreach ($tickerAll as $row) {
        if (isset($row['symbol'], $row['price'])) {
            $priceMap[$row['symbol']] = (float)$row['price'];
        }
    }
}

/* helper: convert asset amount -> USDT */
function assetToUSDT($asset, $amount, $priceMap) {
    if ($amount <= 0) return 0.0;

    $asset = strtoupper($asset);

    // Treat USDT as 1:1
    if ($asset === 'USDT') return $amount;

    // Optional: treat major stablecoins as 1:1 (Binance "estimated" is close to this)
    $stable = ['USDC','FDUSD','TUSD','DAI','BUSD'];
    if (in_array($asset, $stable, true)) return $amount; // assume ~1 USDT

    // Direct ASSETUSDT
    $sym1 = $asset . "USDT";
    if (isset($priceMap[$sym1]) && $priceMap[$sym1] > 0) {
        return $amount * $priceMap[$sym1];
    }

    // Via BTC: ASSETBTC * BTCUSDT
    $sym2 = $asset . "BTC";
    if (isset($priceMap[$sym2]) && isset($priceMap["BTCUSDT"]) && $priceMap[$sym2] > 0 && $priceMap["BTCUSDT"] > 0) {
        return $amount * $priceMap[$sym2] * $priceMap["BTCUSDT"];
    }

    // Via ETH: ASSETETH * ETHUSDT
    $sym3 = $asset . "ETH";
    if (isset($priceMap[$sym3]) && isset($priceMap["ETHUSDT"]) && $priceMap[$sym3] > 0 && $priceMap["ETHUSDT"] > 0) {
        return $amount * $priceMap[$sym3] * $priceMap["ETHUSDT"];
    }

    // If no route found, ignore (no estimate)
    return 0.0;
}

/* ===================== 2) SPOT ESTIMATED TOTAL (USDT) ===================== */
[$spotData, $spotErr] = signedGet("https://api.binance.com/api/v3/account", $apiKey, $apiSecret);

$spotEstimatedUSDT = 0.0;
$spotBreakdown = [];

if ($spotData && !empty($spotData['balances'])) {
    foreach ($spotData['balances'] as $bal) {
        $asset  = $bal['asset'] ?? '';
        $free   = (float)($bal['free'] ?? 0);
        $locked = (float)($bal['locked'] ?? 0);
        $total  = $free + $locked;

        if ($total <= 0) continue;

        $usdtVal = assetToUSDT($asset, $total, $priceMap);
        if ($usdtVal <= 0) continue;

        $spotEstimatedUSDT += $usdtVal;

        // breakdown (optional)
        $spotBreakdown[] = [
            'asset' => $asset,
            'amount' => $total,
            'estUSDT' => $usdtVal
        ];
    }
}

/* Sort breakdown high->low (optional) */
usort($spotBreakdown, function($a, $b) {
    return $b['estUSDT'] <=> $a['estUSDT'];
});

/* ===================== 3) ISOLATED MARGIN USDT (same as your old logic) ===================== */
[$marginData, $marginErr] = signedGet(
    "https://api.binance.com/sapi/v1/margin/isolated/account",
    $apiKey,
    $apiSecret,
    ['symbols' => $symbol]
);

$isolatedUSDT = 0.0;

if ($marginData && !empty($marginData['assets'])) {
    foreach ($marginData['assets'] as $assetObj) {
        $quote = $assetObj['quoteAsset'] ?? null;
        if ($quote && ($quote['asset'] ?? '') === 'USDT') {
            $isolatedUSDT = (float)($quote['free'] ?? 0);
            break;
        }
    }
}

/* ===================== FINAL RESPONSE ===================== */
echo json_encode([
    'spotEstimatedUSDT' => round($spotEstimatedUSDT, 2),
    'isolatedUSDT'      => round($isolatedUSDT, 2),
    'totalEstimatedUSDT'=> round($spotEstimatedUSDT + $isolatedUSDT, 2),

    // Optional debugging:
    'spotCoinsCount'    => count($spotBreakdown),
    'spotTop'           => array_slice($spotBreakdown, 0, 25),

    // Optional errors:
    'errors' => [
        'ticker' => $tickerErr,
        'spot'   => $spotErr,
        'margin' => $marginErr,
    ],
]);
