<?php
//https://github.com/RockBlack-VPN/ip-address/blob/main/Global/ChatGPT(OpenAI)/ChatGPT(OpenAI)_0.0.6.bat
$newSubnetsFile = __DIR__ . '/newSubnets.txt';
$oldSubnetsFile = __DIR__ . '/routes.bat';
$regExpIp = '!([\d]{1,3}\.[\d]{1,3}\.[\d]{1,3}\.[\d]{1,3}).+?(255\.[\d]{1,3}\.[\d]{1,3}\.[\d]{1,3})!';
$vpnIp = '10.8.1.3';    //local client vpn ip (can be any)

//ip1=>mask1
//ip2=>mask2
function parseIps($filePath){
	global $regExpIp;
	$result = [];
	
	if(!file_exists($filePath))
		return $result;
	
	$content = file_get_contents($filePath);
	
	$newSubnetsArr = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	
	foreach ($newSubnetsArr as $line) {
		if(!preg_match($regExpIp, $line, $match))
			continue;
		
		$result[$match[1]] = $match[2];
	}
	
	return $result;
}

if(!file_exists($newSubnetsFile)){
	die(basename($newSubnetsFile)." not found\n");
}

$newSubnets = parseIps($newSubnetsFile);
$oldSubnets = parseIps($oldSubnetsFile);

$result = $oldSubnets;
$added = 0;

foreach ($newSubnets as $ip=>$mask) {
	if(isset($result[$ip]) and $result[$ip] == $mask)
		continue;
	
	$result[$ip] = $mask;
	$added ++;
}

$fileContent = '';
foreach ($result as $ip=>$mask) {
	$fileContent .= "route ADD {$ip} MASK {$mask} {$vpnIp}\n";
}

if(file_put_contents($oldSubnetsFile, trim($fileContent)))
	echo "added {$added} subnets\n";
else
	echo "error\n";
	





















