<?php
date_default_timezone_set("Europe/Moscow");
set_time_limit(0);
ini_set('max_execution_time', 0);

$timeout = 5;
ini_set('default_socket_timeout', $timeout);
//192.168.1.1:777

$host = '192.168.1.1';
$port = 23;
$user = '';
$pass = '';
$credFile = __DIR__ . '/credentials.txt';
$routesFile = __DIR__ . '/routes.bat';

if(!file_exists($routesFile))
	die('routesFile not found');

if (file_exists($credFile)) {
	$lines = file($credFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	$host = $lines[0] ?? '';
	$port = $lines[1] ?? '';
	$user = $lines[2] ?? '';
	$pass = $lines[3] ?? '';
	
	if(!$host or !$port or !$user or !$pass){
		unlink($credFile);
	}
}

$fp = fsockopen($host, $port, $errno, $errstr, $timeout);

if (!$fp){
	$host = prompt("Enter router ip: ");
	$port = prompt("Enter ssh port (default:23): ");
}
sleep(1);

if(!$user)
	$user = prompt("Enter router USERNAME:");

if(!$pass)
	$pass = prompt("Enter PASSWORD:");

echo "\n";

if(!$fp){
	$fp = fsockopen($host, $port, $errno, $errstr, $timeout);
}

if (!$fp) {
	unlink($credFile);
	die("Connection ERROR: $errstr ($errno)\n");
}



stream_set_timeout($fp, $timeout);


$output = read_until($fp, 'Login:');
fwrite($fp, "$user\r\n");

$output = read_until($fp, 'Password:');
fwrite($fp, "$pass\r\n");

$output = read_until($fp, '/(\(config\)>|Login:)/is', 2, true);

if(!str_contains($output, '>')){
	if(file_exists($credFile))
		unlink($credFile);
	
	die("wrong USERNAME/PASSWORD\n");
}

file_put_contents($credFile, "$host\n$port\n$user\n$pass");
echo "Login/Pass saved to credentials.txt\n";


fwrite($fp, "show interface\r\n");
$output = read_until($fp, '(config)>', 5);

if(!preg_match_all('!Interface, name \= ".+?"(.+?)summary:!is', $output , $matchesInterface)){
	die('error get interfaces');
}

$wgIp = '';
$interfaceId = '';

foreach($matchesInterface[1] as $interfaceContent){
	if(preg_match('!id: ([^\s]+).+? type: Wireguard.+?address: ([\d\.]+).+?status: up!is', $interfaceContent, $matches)){
		$interfaceId = $matches[1];
		$wgIp = $matches[2];
		break;
	}
}

if(!$wgIp or !$interfaceId)
	die('error find wireguard interface');


echo "\nREsponse:\n";
echo $output;

echo "wg ip: {$wgIp}\n";
echo "wg InterfaceID: {$interfaceId}\n";



$lines = file($routesFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

//fwrite($fp, "ip route 122.122.122.0 255.255.255.0 {$interfaceId} auto reject\r\n");
//$output = read_until($fp, '>');

$addedRoutes = 0;
$errorCount = 0;

foreach ($lines as $line) {
	//route ADD 8.34.208.0 MASK 255.255.240.0 10.8.1.3
	if(!preg_match('!route ADD ([\d\.]+) MASK ([\d\.]+)!i', $line, $matches))
		continue;
	
	$dstIp = $matches[1];
	$mask = $matches[2];
	
	$command = "ip route {$dstIp} {$mask} {$wgIp} {$interfaceId} auto reject\r\n";
	echo "adding new route $dstIp $mask \n";
	
	fwrite($fp, $command);
	usleep(80000);
	$addedRoutes++;
//  $output = read_until($fp, '(config)>', 2);
//	if(str_contains($output, 'Added static route') or str_contains($output, 'Renewed static route'))
//		$addedRoutes++;
//	else {
//		$errorCount++;
//
//		if ($errorCount > 5)
//			die('error');
//	}
	
}
echo "\n saving to flash..";

fwrite($fp, "system configuration save");
sleep(2);
fwrite($fp, "copy running-config startup-config");
sleep(3);
echo "added routes: {$addedRoutes}\n";




//exit
fwrite($fp, "exit\r\n");
fclose($fp);















function prompt($text) {
	echo $text;
	return trim(fgets(STDIN));
}

/**
 * @param $fp
 * @return string - response
 */
function read_until(&$fp, string $expected, int $timeout = 6, bool $isRegExp = false) {
	$buffer = '';
	$start = time();
	
	while (!feof($fp)) {
		$buffer .= fread($fp, 1024);
		
		if((time() - $start) > $timeout)
			break;
		
		if($isRegExp)
			if(preg_match($expected, $buffer))
				break;
		else
			if(str_contains($buffer, $expected))
				break;
			
		usleep(200000);
	}
	return $buffer;
}