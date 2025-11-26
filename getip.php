<?php
$ip = file_get_contents('https://api.ipify.org'); // returns your public IPv4
echo "Public IP: $ip";
?>

<?php
$ip = file_get_contents('https://api64.ipify.org'); // returns IPv6
echo "Public IP: $ip";
?>
