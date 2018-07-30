<?php
require_once(__dir__ ."/../config.php");
require_once(__dir__ ."/../includes/functions.php");

$stmtServer = $pdo->prepare("
  SELECT
    `server`,
    `ip`,
		`localIP`,
    `configUpdated`,
		`notifyLocalOnly`,
		`notify`
  FROM servers
  WHERE server=:ns;
");

$stmtServers = $pdo->prepare("
	SELECT
		server,
		ip
	FROM dns.servers;
");

$stmtTrustedServers = $pdo->prepare("
	SELECT
		server,
		ip
	FROM dns.servers
	where trusted=1;
");

$stmtLocalServers = $pdo->prepare("
SELECT
	server,
	localIP
FROM dns.servers
where localIP IS NOT NULL;
");

$stmtZones = $pdo->prepare("
SELECT
	z.`zone`,
	z.`server`,
	z.`serial`,
	z.`refresh`,
	z.`retry`,
	z.`expire`,
	z.`minimum`,
	z.dnssec,
	(select
		concat('\t\t',GROUP_CONCAT(sip.ip SEPARATOR ';\n\t\t'),';')
	FROM zoneServers as zsip
	INNER JOIN servers AS sip ON sip.server=zsip.server
	WHERE zsip.zone=z.zone) as ips,
  IF(zs.server is null,0,1) as `master`
FROM zones AS z
LEFT JOIN zoneServers AS zs ON zs.zone=z.zone AND zs.server=:ns
INNER JOIN servers AS s ON s.server = z.server
ORDER BY z.`zone` asc;
");


$stmtZones->bindValue(':ns', ns, PDO::PARAM_STR );
$stmtServer->bindValue(':ns', ns, PDO::PARAM_STR );
if($stmtServer->execute() && $stmtZones->execute() && $stmtServers->execute() && $stmtTrustedServers->execute() && $stmtLocalServers->execute()) {
	while($ns = $stmtServer->fetch()) {
		
		$named = new named(bindconf_tmp,bindconf);
		$data = "acl mysql-servers  {\n";
		while($server = $stmtServers->fetch()) {
			$data .= sprintf("\t%s; // %s\n",$server["ip"],$server["server"]);
		}
		$data .= "};\n\n";

		$data .= "acl trusted-mysql-servers  {\n";
		while($server = $stmtTrustedServers->fetch()) {
			$data .= sprintf("\t%s; // %s\n",$server["ip"],$server["server"]);
		}
		$data .= "};\n\n";

		$data .= "masters local-mysql-servers  {\n";
		while($server = $stmtLocalServers->fetch()) {
			$data .= sprintf("\t%s; // %s\n",$server["localIP"],$server["server"]);
		}
		$data .= "};\n\n";

	  while($zone = $stmtZones->fetch()){
	    if($zone["master"] === 1) {
				$z = new zone($zone["zone"],$log);

	      if($z->checkZone()) {
					$zoneConfig = sprintf("zone \"%s\" IN {\n",$zone["zone"]);
					$zoneConfig .= sprintf("\ttype master;\n");
					if($zone["dnssec"] === 1 && $z->checkSignedZone()) {
						$zoneConfig .= sprintf("\tfile \"%s\";\n", $z->signed);
					} else {
						$zoneConfig .= sprintf("\tfile \"%s\";\n", $z->file);
					}

					if($ns["notifyLocalOnly"] > 0) {
						$zoneConfig .= sprintf("\tnotify explicit;\n");
						$zoneConfig .= sprintf("\tnotify-source %s;\n",$ns["localIP"]);
						$zoneConfig .= sprintf("\talso-notify { local-mysql-servers; };\n");
					} elseif($ns["notify"] > 0) {
						$zoneConfig .= sprintf("\tnotify yes;\n");
					} else {
						$zoneConfig .= sprintf("\tnotify no;\n");
					}
					$zoneConfig .= sprintf("\tallow-transfer { mysql-servers; };\n");
					$zoneConfig .= sprintf("\tallow-update { none; };\n");

					$zoneConfig .= sprintf("};\n\n");

					$data .= $zoneConfig;
					$log->info(bindconf." updated as master for:\t".$zone["zone"]);

				} else {
						$log->info(bindconf." error in zonefile for:\t".$zone["zone"]);
				}
	    } else {
				$zoneConfig = sprintf("zone \"%s\" IN {\n",$zone["zone"]);
				$zoneConfig .= sprintf("\ttype slave;\n");
				$zoneConfig .= sprintf("\tfile \"slaves/%s\";\n", zone::dnsNameReverse($zone["zone"]));

				$zoneConfig .= sprintf("\tnotify yes;\n");
				$zoneConfig .= sprintf("\tallow-notify { trusted-mysql-servers; };\n");
				$zoneConfig .= sprintf("\tallow-transfer { mysql-servers; };\n");
				$zoneConfig .= sprintf("\tmasters {\n%s\n\t};\n",$zone["ips"]);
				$zoneConfig .= sprintf("};\n\n");
				$data .= $zoneConfig;

	      $log->info(bindconf." updated as slave for:\t".$zone["zone"]);
	    }
	  }

		if($named->writeConf($data)) {
			$named->reload();
			$log->info("named/rdnc reloaded");
		} else {
			$log->warning("Configuration file not valid, aborting");
		}
	}
}
?>
