<?php
class ProxyPool {
    private $proxyFile = __DIR__ . '/proxies.json';
    private $statsFile = __DIR__ . '/proxy_stats.json';
    
    // 主API配置
    private $apiKey = '您的API密钥';
    private $apiPwd = '您的密码';
    private $proxyApiUrl = 'http://need1.dmdaili.com:7771/dmgetip.asp';
    private $apiParams = 'getnum=1&httptype=1&geshi=2&fenge=1&fengefu=&operate=all';
    
    // 备用API配置
    private $backupApiKey = '您的API密钥';
    private $backupApiPwd = '您的密码';
    private $backupProxyApiUrl = 'http://need1.dmdaili.com:7771/dmgetip.asp';
    private $backupApiParams = 'getnum=1&httptype=1&geshi=2&fenge=1&fengefu=&operate=all';
    
    private $maxProxies = 10;
    private $logFile = __DIR__ . '/proxy.log';
    
    public function __construct() {
        if (!file_exists($this->proxyFile)) {
            file_put_contents($this->proxyFile, json_encode(array()));
        }
        if (!file_exists($this->statsFile)) {
            file_put_contents($this->statsFile, json_encode(array()));
        }
    }
    
    protected function log($message) {
        $time = date('Y-m-d H:i:s');
        file_put_contents($this->logFile, "[$time] $message\n", FILE_APPEND);
    }
    
    protected function updateStats($isBackup) {
        $stats = json_decode(file_get_contents($this->statsFile), true) ?: array();
        $today = date('Y-m-d');
        
        if (!isset($stats[$today])) {
            $stats[$today] = array(
                'main_api_count' => 0,
                'backup_api_count' => 0
            );
        }
        
        if ($isBackup) {
            $stats[$today]['backup_api_count']++;
        } else {
            $stats[$today]['main_api_count']++;
        }
        
        file_put_contents($this->statsFile, json_encode($stats, JSON_PRETTY_PRINT));
        
        // 输出今日统计
        $this->log(sprintf(
            "今日API使用统计 - 主API: %d次, 备用API: %d次",
            $stats[$today]['main_api_count'],
            $stats[$today]['backup_api_count']
        ));
    }
    
    // 获取一个代理（不做验证，假设代理池中的都是有效的）
    public function getProxy() {
        $proxies = $this->loadProxies();
        
        if (empty($proxies)) {
            $this->log("代理池为空");
            return null;
        }
        
        // 随机返回一个代理
        $index = array_rand($proxies);
        $proxy = $proxies[$index];
        
        $this->log("返回代理: {$proxy['ip']}:{$proxy['port']}");
        return $proxy;
    }
    
    // 清理无效代理并返回可用代理
    protected function cleanInvalidProxies($proxies) {
        $validProxies = array();
        
        foreach ($proxies as $proxy) {
            if ($this->validateProxy($proxy)) {
                $validProxies[] = $proxy;
            }
        }
        
        // 保存清理后的代理列表
        $this->saveProxies($validProxies);
        
        return $validProxies;
    }
    
    // 从API获取新代理
    protected function fetchNewProxies($count) {
        // 先尝试主API
        $proxy = $this->fetchFromApi($this->proxyApiUrl, $this->apiKey, $this->apiPwd, false);
        
        // 只有当代理池为空，且主API获取的代理无效时，才尝试备用API
        if (!$proxy && empty($this->loadProxies())) {
            $this->log("代理池为空且主API获取代理失败，尝试备用API");
            $proxy = $this->fetchFromApi($this->backupProxyApiUrl, $this->backupApiKey, $this->backupApiPwd, true);
        }
        
        if ($proxy) {
            $proxies = $this->loadProxies();
            $proxies[] = $proxy;
            $this->saveProxies($proxies);
            $this->log("保存代理池，当前数量: " . count($proxies));
        }
    }
    
    // 从指定API获取代理
    protected function fetchFromApi($apiUrl, $apiKey, $apiPwd, $isBackup) {
        $params = $isBackup ? $this->backupApiParams : $this->apiParams;
        $url = sprintf(
            "%s?apikey=%s&pwd=%s&%s",
            $apiUrl,
            $apiKey,
            $apiPwd,
            $params
        );
        
        $this->log("获取新代理: $url");
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            $this->log("CURL错误: $error");
            return null;
        }
        
        $this->log("API响应: $response");
        
        if (!empty($response)) {
            $result = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->log("JSON解析错误: " . json_last_error_msg());
                return null;
            }
            
            if (isset($result['code']) && $result['code'] == 605) {
                $this->log("白名单更新中，3秒后重试...");
                sleep(3);
                return $this->fetchFromApi($apiUrl, $apiKey, $apiPwd, $isBackup);
            }
            
            if (isset($result['code']) && $result['code'] === 0 && 
                isset($result['success']) && $result['success'] === true && 
                !empty($result['data'])) {
                
                // 只处理第一个代理
                if (isset($result['data'][0]['ip']) && isset($result['data'][0]['port'])) {
                    $proxy = array(
                        'ip' => $result['data'][0]['ip'],
                        'port' => $result['data'][0]['port']
                    );
                    
                    $this->log("测试代理: {$proxy['ip']}:{$proxy['port']}");
                    
                    if ($this->validateProxy($proxy)) {
                        $this->log("代理有效，返回代理");
                        // 更新统计
                        $this->updateStats($isBackup);
                        return $proxy;
                    } else {
                        $this->log("代理无效，跳过");
                    }
                }
            } else {
                $this->log("API错误或无数据: " . json_encode($result));
            }
        }
        
        return null;
    }
    
    // 验证代理是否可用
    protected function validateProxy($proxy) {
        $maxRetries = 2;
        $retryCount = 0;
        
        while ($retryCount < $maxRetries) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://www.baidu.com/');
            curl_setopt($ch, CURLOPT_PROXY, $proxy['ip']);
            curl_setopt($ch, CURLOPT_PROXYPORT, $proxy['port']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                $this->log("代理验证错误: " . $error);
            }
            
            if ($httpCode == 200) {
                return true;
            }
            
            $retryCount++;
            if ($retryCount < $maxRetries) {
                sleep(1);
            }
        }
        
        return false;
    }
    
    // 加载代理列表
    protected function loadProxies() {
        $content = file_get_contents($this->proxyFile);
        return json_decode($content, true) ?: array();
    }
    
    // 保存代理列表
    protected function saveProxies($proxies) {
        file_put_contents($this->proxyFile, json_encode($proxies));
    }
    
    // 获取最大代理数量
    protected function getMaxProxies() {
        return $this->maxProxies;
    }
} 