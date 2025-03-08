<?php
// 实例环境：php7
// 配置显示错误
ini_set('display_errors', true);
// 设置编码，防止中文乱码
header("Content-type: text/html; charset=utf-8");
function main(){
    // 发送给服务器的标识
    $userAgent = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/532.36 (KHTML, like Gecko) Chrome/97.0.4692.99 Safari/537.36";
    // 可通过多米HTTP代理网站购买后生成代理api链接，每次请求api链接都是新的ip
    $proxyUrl = "http://api.dmdaili.com/dmgetip.asp?apikey=03xxxx88&pwd=0c0cxxxx90a9xxxxxx85f09e0bcxxxxx&getnum=10&httptype=1&geshi=1&fenge=1&fengefu=&Contenttype=1&operate=all";
    // 请求代理url，获取代理ip 
    $outPutProxy = getProxy($proxyUrl, $userAgent);
    if (count($outPutProxy)==0){
        // 没有获取到代理
        return;
    }
    // 目标请求网站
    $url = "https://www.jianshu.com/";
    $content = "";
	// 最大失败次数3次
    for($i=0; $i<3; $i++){
        try{
            $px = array_shift($outPutProxy);
            echo("using proxy".$px[0]);
            echo("<br />");
            $content = requestGet($url, $userAgent, $px);
            break;
        }catch(Exception $e){
            echo($e);
            if (count($outPutProxy)==0){
                // 判断下代理还有没有，没有的了就重新获取下
                $outPutProxy = getProxy($proxyUrl, $userAgent);
            }
        }
    }
    echo("<br />");
    // htmlspecialchars 使用此函数不会渲染为网页
    // 这便是响应内容了
    echo(htmlspecialchars($content));

}
function getProxy($proxyUrl, $userAgent){
    $proxyIps = "";
    $outPutProxy = [];
    try{
        $proxyIps = requestGet($proxyUrl, $userAgent, array());
        # {"code":3002,"data":[],"msg":"error!用户名或密码错误","success":false}
        if (strpos($proxyIps, "{", 0) > -1){
            throw new Exception($proxyIps);
        }
        $eachIps = explode("\r\n", $proxyIps);
        foreach ($eachIps as $value){
            $currentIp = explode(":", $value);
            array_push($outPutProxy, array( $currentIp[0], $currentIp[1]));
        }
        
    }catch(Exception $e){
        echo($e);
    }
     var_dump($outPutProxy);
    echo("总共获取了");
    echo(count($outPutProxy));
    echo("个代理");
    echo("<br/>");
    return $outPutProxy;
}
function requestGet($url, $userAgent, $proxy){
  $headerArray = array("User-Agent:$userAgent;");
  $ch = curl_init();
    # $headerArray =array("Content-type:application/json;","Accept:application/json");
  // 设置要请求的url
  curl_setopt($ch, CURLOPT_URL, $url);
  // 设置整体最大超时时间
  curl_setopt($ch, CURLOPT_TIMEOUT, 5);
  // 设置最大连接超时时间
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
  // 设置不验证ssl证书
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); 
  // 设置不验证ssl证书
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE); 
  // 这样设置的话我们可以拿到响应内容并且可以保存在一个变量里
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  // 设置请求头,比如 user-agent,cookie,referer啥的
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);

  if (count($proxy) != 0){
    // 设置代理
    // curl_setopt($ch, CURLOPT_PROXY, "127.0.0.1");
    // curl_setopt($ch, CURLOPT_PROXYPORT, "10809");
    curl_setopt($ch, CURLOPT_PROXY, $proxy[0]);
    curl_setopt($ch, CURLOPT_PROXYPORT, $proxy[1]);
  }
  // 执行发送请求
  $result = curl_exec($ch);
  curl_close($ch);
  return $result;
}
main()
?>
