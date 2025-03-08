# Proxy Pool Manager

一个用于管理和清理代理池的 PHP 项目。该项目能够从 API 获取代理，清理无效代理，并提供有效的代理供其他应用使用。

## 特性

- 从主 API 和备用 API 获取代理
- 自动清理无效代理
- 去重代理列表
- 提供 API 接口获取代理
- 统计 API 使用情况

## 安装

1. 克隆项目到本地：
    ```bash
    git clone https://github.com/vulgar-sir/Proxy-Pool-Manager.git
    cd 项目名
    ```

2. 确保您的环境中安装了 PHP 7 或更高版本。

3. 配置 API 密钥和密码：
   - 在 `ProxyPool.php` 文件中，更新主 API 和备用 API 的配置。

## 使用

### 清理和补充代理池

运行 `clean_proxies.php` 文件以清理和补充代理池：
```bash
php clean_proxies.php
```

### 获取代理

您可以通过访问 `get_proxy.php` 文件来获取一个有效的代理：
```bash
curl http://localhost/项目名/get_proxy.php
```

### 示例请求

在 `demo.php` 文件中，您可以看到如何使用获取的代理进行请求：
```php
// 示例代码
$proxyUrl = "http://api.dmdaili.com/dmgetip.asp?apikey=您的API密钥&pwd=您的密码&getnum=10&httptype=1&geshi=1&fenge=1&fengefu=&Contenttype=1&operate=all";
$outPutProxy = getProxy($proxyUrl, $userAgent);
```

## 贡献

欢迎贡献！请遵循以下步骤：

1. Fork 这个仓库
2. 创建您的特性分支 (`git checkout -b feature/YourFeature`)
3. 提交您的更改 (`git commit -m 'Add some feature'`)
4. 推送到分支 (`git push origin feature/YourFeature`)
5. 创建一个新的 Pull Request

## 许可证

此项目使用 [MIT 许可证](LICENSE)。