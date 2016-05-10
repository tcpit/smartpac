# SmartPAC
Fetch the gfwlist and convert it to PAC automaticly.

## Usage
[1] Replace the configuration in `config.php`
```php
/** proxy type: HTTP, SOCKS5 */
$PAC_PROXYTYPE = "SOCKS5";

/** proxy server and port */
$PAC_PROXY = "PROXY_SERVER:PROXY_PORT";

/** direct proxy type: HTTP, SOCKS5. If $PAC_DIRECT = "DIRECT", this option is not available. */
$PAC_DIRECTTYPE = "SOCKS5";

/** direct setting, default value: DIRECT */
$PAC_DIRECT = "DIRECT";
```
[2] The PAC url can be:
* `http://yourdomain/index.php` 
* `http://yourdomain/index.php?mode=all`
* `http://yourdomain/index.php?mode=mini`
* `http://yourdomain/index.php?mode=custom&proxytype=SOCKS5&proxyserver=abcdef.com&proxyport=1080&directtype=HTTP&directserver=hijiklmn.net&directport=1080`
```php
mode = {
    smart,      // default(optional), load the configuration from config.php
    update,     // fetch the latest gfwlist.txt/gfwminilist.txt/index.php from github
    all,        // all are forwarded to local proxy server
    mini,       // fetch the latest gfwminilist.txt/index.php only
    custom,     // override the configuration via url parameters
    custommini, // both custom and mini are enabled
    customall, // both custom and all are enabled
    smartmini,  // both smart and mini are enabled
}
```
