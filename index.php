<?php
@include_once("config.php");
/****************************************************************************
* Global variables
*****************************************************************************/
$PAC_LISTURLS = array(
	"https://raw.githubusercontent.com/gfwlist/gfwlist/master/gfwlist.txt" => "gfwlist.txt",
	"https://raw.githubusercontent.com/tcpit/SmartPAC/master/gfwminilist.txt" => "gfwminilist.txt",
	"https://raw.githubusercontent.com/tcpit/SmartPAC/master/index.php" => "index.php",
	);
$PAC_TEMPLATE_SMART = <<<PAC_TEMPLATE_SMART
var proxy = "%1\$s; DIRECT";
var direct = "%2\$s";
var domains = { %3\$s };
var hasOwnProperty = Object.hasOwnProperty;
function FindProxyForURL(url, host) {
    if(isPlainHostName(host) || shExpMatch(host, "*.local")  || (/^(\d+\.){3}\d+$/.test(host) && (shExpMatch(host, "10.*") || shExpMatch(host, "127.*") || shExpMatch(host, "192.168.*") || /^172\.(1[6-9]|2[0-9]|3[0-1])\.\d+\.\d+$/.test(host)))){
        return "DIRECT";
    }
    if(/^(.*\.?)(google|blogspot)\.(.*)$/.test(host)){
    	return proxy;
    }
    var suffix;
    var pos = host.lastIndexOf(".");
    pos = host.lastIndexOf(".", pos - 1);
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
var proxy = "%1\$s; DIRECT";
function FindProxyForURL(url, host) {
    if(isPlainHostName(host) || shExpMatch(host, "*.local") || (/^(\d+\.){3}\d+$/.test(host) && (shExpMatch(host, "10.*") || shExpMatch(host, "127.*") || shExpMatch(host, "192.168.*") || /^172\.(1[6-9]|2[0-9]|3[0-1])\.\d+\.\d+$/.test(host)))){
        return "DIRECT";
    }
    return proxy;
}
PAC_TEMPLATE_ALL;
/****************************************************************************
* Functions
*****************************************************************************/
function update(){
	global $PAC_LISTURLS;
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
			global $PAC_PROXYTYPE, $PAC_PROXY;
			if(empty($PAC_PROXYTYPE) || empty($PAC_PROXY)) exit;
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
$mode = isset($_GET["mode"])?strtolower($_GET["mode"]):strtolower(@$_GET["m"]);
if(strpos($mode, "custom") !== false || strpos($mode, "ct") !== false){
	if((isset($_GET["proxytype"]) || isset($_GET["t"]))
	   && (isset($_GET["proxyserver"]) || isset($_GET["s"]))
	   && (isset($_GET["proxyport"]) || isset($_GET["p"]))){
		$PAC_PROXYTYPE = strtoupper(isset($_GET["proxytype"]) ? $_GET["proxytype"] : $_GET["t"]);
		$PAC_PROXY = (isset($_GET["proxyserver"])?$_GET["proxyserver"]:$_GET["s"]).":".(isset($_GET["proxyport"])?$_GET["proxyport"]:$_GET["p"]);
	}
	if((isset($_GET["directtype"]) || isset($_GET["dt"]))
	   && (isset($_GET["directserver"]) || isset($_GET["ds"]))
	   && (isset($_GET["directport"]) || isset($_GET["dp"]))){
		$PAC_DIRECTTYPE = strtoupper(isset($_GET["directtype"])?$_GET["directtype"]:$_GET["dt"]);
		$PAC_DIRECT = (isset($_GET["directserver"])?$_GET["directserver"]:$_GET["ds"]).":".(isset($_GET["directport"])?$_GET["directport"]:$_GET["dp"]);
	}
}else{
	//proxy
	if(isset($_GET["proxytype"]) || isset($_GET["t"])){
		$PAC_PROXYTYPE = strtoupper(isset($_GET["proxytype"]) ? $_GET["proxytype"] : $_GET["t"]);
	}
	$parts = explode(":", $PAC_PROXY);
	$PAC_SERVER = $parts[0];
	$PAC_PORT = $parts[1];
	if(isset($_GET["proxyserver"]) || isset($_GET["s"])){
		$PAC_SERVER = (isset($_GET["proxyserver"])?$_GET["proxyserver"]:$_GET["s"]);
	}
	if(isset($_GET["proxyport"]) || isset($_GET["p"])){
		$PAC_PORT = (isset($_GET["proxyport"])?$_GET["proxyport"]:$_GET["p"]);
	}
	$PAC_PROXY = $PAC_SERVER.":".$PAC_PORT;
	//direct
	if(isset($_GET["directtype"]) || isset($_GET["dt"])){
		$PAC_DIRECTTYPE = strtoupper(isset($_GET["directtype"]) ? $_GET["directtype"] : $_GET["dt"]);
	}
	if(strtoupper($PAC_DIRECT) === "DIRECT" && (isset($_GET["directserver"]) || isset($_GET["ds"])) && (isset($_GET["directport"]) || isset($_GET["dp"]))){
		$PAC_DIRECT = (isset($_GET["directserver"])?$_GET["directserver"]:$_GET["ds"]).":".(isset($_GET["directport"])?$_GET["directport"]:$_GET["dp"]);
	}else{
		$parts = explode(":", $PAC_DIRECT);
		if(count($parts) === 2){
			if(isset($_GET["directserver"]) || isset($_GET["ds"])){
				$PAC_DIRECT = (isset($_GET["directserver"])?$_GET["directserver"]:$_GET["ds"]).":".$parts[1];
			}
			if(isset($_GET["directport"]) || isset($_GET["dp"])){
				$PAC_DIRECT = $parts[0].":".(isset($_GET["directport"])?$_GET["directport"]:$_GET["dp"]);
			}
		}
	}
}

if(strpos($mode, "update") !== false){
	update();
}
$domainList = "";
foreach ($PAC_LISTURLS as $key => $value) {
	if(file_exists($value) == false){
		update();
	}else{
		// automatically update the gfwlist.txt every 24 hours
		clearstatcache();
		if($value === "gfwlist.txt" && time() - filemtime($value) > 24*3600){
			$fp=fsockopen($_SERVER["HTTP_HOST"], $_SERVER["SERVER_PORT"]);
			if($fp){
			    fputs($fp, "GET ".$_SERVER["PHP_SELF"]."?mode=update\r\n");
			}
			fclose($fp);
		}
	}
	if(strpos($mode,"mini")  !== false && $value === "gfwlist.txt"){
		continue;
	}
	if($value === "index.php"){
		continue;
	}
	$domainList .= file_get_contents($value);
}
$domainList = rtrim(implode(array_unique(explode("\n", $domainList))), ",");
if(empty($PAC_PROXYTYPE) || empty($PAC_PROXY)) exit;
$pac = "";
if(strpos($mode, "all") !== false){
	$pac = sprintf($PAC_TEMPLATE_ALL, $PAC_PROXYTYPE === "HTTP" ? "PROXY $PAC_PROXY" : "SOCKS5 $PAC_PROXY; SOCKS $PAC_PROXY");
}else{
	$pac = sprintf($PAC_TEMPLATE_SMART, $PAC_PROXYTYPE === "HTTP" ? "PROXY $PAC_PROXY" : "SOCKS5 $PAC_PROXY; SOCKS $PAC_PROXY", 
		$PAC_DIRECT ==="DIRECT" ? "DIRECT" : ($PAC_DIRECTTYPE === "HTTP" ? "PROXY $PAC_DIRECT; DIRECT" : "SOCKS5 $PAC_DIRECT; SOCKS $PAC_DIRECT; DIRECT"), $domainList);
}
echo $pac;
?>
