<?php
require_once("config.php");

/****************************************************************************
* Global variables
*****************************************************************************/
$PAC_LISTURLS = array(
	"https://raw.githubusercontent.com/gfwlist/gfwlist/master/gfwlist.txt" => "gfwlist.txt",
	"https://raw.githubusercontent.com/tcpit/smartpac/master/gfwminilist.txt" => "gfwminilist.txt",
	"https://raw.githubusercontent.com/tcpit/smartpac/master/index.php" => "index.php",
	);

$PAC_TEMPLATE_SMART = <<<PAC_TEMPLATE_SMART
var proxy = "%1\$s; DIRECT;";
var direct = "%2\$s;";
var domains = { %3\$s };
var hasOwnProperty = Object.hasOwnProperty;
function FindProxyForURL(url, host) {
    var suffix;
    var pos = host.lastIndexOf(".");
    pos = host.lastIndexOf(".", pos - 1);
    
    if(shExpMatch(host, "localhost")) return direct;
    else if(shExpMatch(host, "127.0.0.1")) return direct;
    else if(shExpMatch(host, "10.[0-9]+.[0-9]+.[0-9]+")) return direct;
    else if(shExpMatch(host, "172.[0-9]+.[0-9]+.[0-9]+")) return direct;
    else if(shExpMatch(host, "192.168.[0-9]+.[0-9]+")) return direct;
    else if(host.indexOf("google") != -1 || host.indexOf("blogspot") != -1)
    	return proxy;

    while(1) {
        if (pos <= 0) {
            if (hasOwnProperty.call(domains, host)) {
                return proxy;
            } else {
            	return direct;
            }
        }
        suffix = host.substring(pos + 1);
        if (hasOwnProperty.call(domains, suffix)) {
            return proxy;
        }
        pos = host.lastIndexOf(".", pos - 1);
    }
}
PAC_TEMPLATE_SMART;

$PAC_TEMPLATE_ALL = <<<PAC_TEMPLATE_ALL
var proxy = "%1\$s; DIRECT;";
function FindProxyForURL(url, host) {
    return proxy;
}
PAC_TEMPLATE_ALL;
/****************************************************************************
* Functions
*****************************************************************************/
function update(){
	global $PAC_LISTURLS, $PAC_PROXYTYPE, $PAC_PROXY;
	if(!defined("CURLPROXY_SOCKS5_HOSTNAME")){
		define("CURLPROXY_SOCKS5_HOSTNAME", 7);
	}
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	foreach ($PAC_LISTURLS as $key => $value) {
		$url = $key;
		$localFile = $value;
		curl_setopt($ch, CURLOPT_URL, $url);
		
		curl_setopt($ch, CURLOPT_PROXYTYPE, NULL);
		curl_setopt($ch, CURLOPT_PROXY, NULL);
		$data = curl_exec($ch);
		if(curl_errno($ch)){
			if($PAC_PROXYTYPE === "HTTP"){
				curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
			}else{
				curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5_HOSTNAME);
			}
			curl_setopt($ch, CURLOPT_PROXY, $PAC_PROXY);
			$data = curl_exec($ch);
		}
		if($localFile === "gfwlist.txt"){
			$data = parse_gfwlist($data);
		}
		file_put_contents($localFile, $data);
	}
	curl_close($ch);
}

function parse_gfwlist($content) {
	$gfwlistContent = base64_decode($content);
	$gfwlistRules = explode("\n", $gfwlistContent);
	$list = array();
	$regexp = array();
	foreach($gfwlistRules as $line) {
	    $line = rtrim($line);
	    $line = str_replace("http://", "", $line);
	    $line = str_replace("https://", "", $line);
	    if(empty($line)
	        || $line[0] == "!"
	        || substr($line, 0, 2) == "@@"
	        || strpos($line, "[AutoProxy") === 0
	        || strpos($line, ".") === false
	    ) continue;
	    if(substr($line, 0, 2) == "||") {
	        $url = substr($line, 2);
	    } elseif($line[0] == "|") {
	        $url = substr($line, 1);
	    } elseif(strpos($line, "\/") !== false) {
	        $regexp[] = $line;
	    } elseif($line[0] == ".") {
	        $url = substr($line, 1);
	    } else {
	        $url = $line;
	    }
	    
	    $pos = strpos($url, "/");
	    if($pos !== false){
	    	$url = substr($url, 0, $pos);
	    }

	    $pos = strpos($url, "*");
	    if($pos !== false){
	    	$dotpos = strpos($url, ".");
	    	if($dotpos !== false && $dotpos > $pos){
	    		$url = substr($url, $dotpos+1);	
	    	}else{
	    		$url = substr($url, 0, $pos);
	    	}
	    }
		
		$list[] = $url;
	}
	$list[] = "naver.jp";
	$list[] = "edgesuite.net";
	$list = array_unique($list);
	
	$gfwlist = "";
	foreach ($list as $key => $value) {
		$gfwlist .= "'$value':1,";
	}

	return $gfwlist;
}

// generate pac
$mode = strtolower(@$_GET["mode"]);

if(strpos($mode, "custom") !== false){
	if(isset($_GET["proxytype"]) && isset($_GET["proxyserver"]) && isset($_GET["proxyport"])){
		$PAC_PROXYTYPE = strtoupper($_GET["proxytype"]);
		$PAC_PROXY = $_GET["proxyserver"].":".$_GET["proxyport"];
	}
	if(isset($_GET["directtype"]) && isset($_GET["directserver"]) && isset($_GET["directport"])){
		$PAC_DIRECTTYPE = strtoupper($_GET["directtype"]);
		$PAC_DIRECT = $_GET["directserver"].":".$_GET["directport"];
	}
}


if($mode === "update"){
	update();
}
$domainList = "";
foreach ($PAC_LISTURLS as $key => $value) {
	if(file_exists($value) == false){
		update();
	}
	if(strpos($mode,"mini")  !== false && $value === "gfwlist.txt"){
		continue;
	}
	if($value === "index.php"){
		continue;
	}
	$domainList .= file_get_contents($value);
}
$domainList = implode(array_unique(explode("\n", $domainList)));


$pac = "";
if(strpos($mode, "all") !== false){
	$pac = sprintf($PAC_TEMPLATE_ALL, $PAC_PROXYTYPE === "HTTP" ? "PROXY $PAC_PROXY" : "SOCKS5 $PAC_PROXY; SOCKS $PAC_PROXY");
}else{
	$pac = sprintf($PAC_TEMPLATE_SMART, $PAC_PROXYTYPE === "HTTP" ? "PROXY $PAC_PROXY" : "SOCKS5 $PAC_PROXY; SOCKS $PAC_PROXY", 
		$PAC_DIRECT ==="DIRECT" ? "DIRECT" : ($PAC_DIRECTTYPE === "HTTP" ? "PROXY $PAC_DIRECT" : "SOCKS5 $PAC_DIRECT; SOCKS $PAC_DIRECT; DIRECT"), $domainList);
}

echo $pac;
?>
