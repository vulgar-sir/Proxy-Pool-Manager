<?php
require_once __DIR__ . '/ProxyPool.php';

class ProxyPoolCleaner extends ProxyPool {
    private $statsFile;
    
    public function __construct() {
        parent::__construct();
        $this->statsFile = __DIR__ . '/proxy_stats.json';
        if (!file_exists($this->statsFile)) {
            file_put_contents($this->statsFile, json_encode(array()));
        }
    }
    
    protected function loadStats() {
        $content = file_get_contents($this->statsFile);
        return json_decode($content, true) ?: array();
    }
    
    protected function saveStats($stats) {
        file_put_contents($this->statsFile, json_encode($stats, JSON_PRETTY_PRINT));
    }
    
    // 添加去重方法
    protected function removeDuplicateProxies($proxies) {
        $uniqueProxies = array();
        $seen = array();
        
        foreach ($proxies as $proxy) {
            $key = $proxy['ip'] . ':' . $proxy['port'];
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $uniqueProxies[] = $proxy;
            }
        }
        
        return $uniqueProxies;
    }
    
    public function cleanAndRefill() {
        echo str_repeat('-', 80) . "\n";
        echo "开始清理和补充代理池\n";
        
        // 加载当前代理并去重
        $proxies = $this->loadProxies();
        $proxies = $this->removeDuplicateProxies($proxies);
        $this->saveProxies($proxies);
        
        $initialCount = count($proxies);
        echo "当前代理池数量: $initialCount\n";
        
        // 清理无效代理
        $validProxies = $this->cleanInvalidProxies($proxies);
        $validCount = count($validProxies);
        
        // 计算需要补充的数量
        $needProxies = $this->getMaxProxies() - $validCount;
        
        if ($needProxies > 0) {
            echo "需要补充 {$needProxies} 个代理\n";
            $this->fetchNewProxies($needProxies);
        }
        
        // 显示最终代理池状态
        $finalProxies = $this->loadProxies();
        $finalProxies = $this->removeDuplicateProxies($finalProxies);
        $this->saveProxies($finalProxies);
        
        echo "\n当前代理池状态:\n";
        echo "代理池数量: " . count($finalProxies) . "\n";
        echo "代理列表:\n";
        foreach ($finalProxies as $proxy) {
            echo "{$proxy['ip']}:{$proxy['port']}\n";
        }
        
        // 显示API使用统计
        $stats = json_decode(file_get_contents($this->statsFile), true) ?: array();
        $today = date('Y-m-d');
        
        if (isset($stats[$today])) {
            echo "\nAPI使用统计:\n";
            echo "主API获取代理数: " . ($stats[$today]['main_api_count'] ?? 0) . "\n";
            echo "备用API获取代理数: " . ($stats[$today]['backup_api_count'] ?? 0) . "\n";
            
            // 计算历史平均值
            $totalDays = 0;
            $totalMainApi = 0;
            $totalBackupApi = 0;
            
            foreach ($stats as $date => $stat) {
                if ($date != $today) {  // 不计算今天
                    $totalDays++;
                    $totalMainApi += isset($stat['main_api_count']) ? $stat['main_api_count'] : 0;
                    $totalBackupApi += isset($stat['backup_api_count']) ? $stat['backup_api_count'] : 0;
                }
            }
            
            if ($totalDays > 0) {
                $avgMainApi = round($totalMainApi / $totalDays);
                $avgBackupApi = round($totalBackupApi / $totalDays);
                echo "\n历史统计(不含今天):\n";
                echo "平均每天主API获取代理数: $avgMainApi\n";
                echo "平均每天备用API获取代理数: $avgBackupApi\n";
            }
        }
        echo str_repeat('-', 80) . "\n";
    }
    
    // 重写父类的 log 方法，改为直接输出
    protected function log($message) {
        echo "$message\n";
    }
}

// 执行清理和补充
$cleaner = new ProxyPoolCleaner();
$cleaner->cleanAndRefill(); 