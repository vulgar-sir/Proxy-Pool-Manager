<?php
require_once __DIR__ . '/ProxyPool.php';

$proxyPool = new ProxyPool();
$proxy = $proxyPool->getProxy();

header('Content-Type: application/json');
if ($proxy) {
    echo json_encode($proxy);
} else {
    echo json_encode(array('skip_proxy' => true));
} 