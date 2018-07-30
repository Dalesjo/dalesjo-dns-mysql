<?php
require_once(__dir__ ."/../config.php");
require_once(__dir__ ."/../includes/functions.php");

$stmtServers = $pdo->prepare("
	SELECT
		server,
		ip
	FROM dns.servers;
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
if($stmtZones->execute() && $stmtServers->execute()) {

	$named = new named(bindconf_tmp,bindconf);
	$data = "acl trusted-mysql-servers  {\n";
	while($server = $stmtServers->fetch()) {
		$data .= sprintf("\t%s; // %s\n",$server["ip"],$server["server"]);
	}
	$data .= "};\n\n";

  while($zone = $stmtZones->fetch()){
    if($zone["master"] === 1) {
			$z = new zone($zone["zone"],$log);

      if($z->checkZone()) {
				$zoneConfig = sprintf("zone \"%s\" IN {\n",$zone["zone"]);
				$zoneConfig .= sprintf("\ttype master;\n");
				if($zone["dnssec"] === 1 && $z->checkSignedZone()) {
					//$zoneConfig .= sprintf("\tdnssec-enable yes;\n");
					//$zoneConfig .= sprintf("\tdnssec-validation yes;\n");
					//$zoneConfig .= sprintf("\tdnssec-lookaside auto;\n");
					$zoneConfig .= sprintf("\tfile \"%s\";\n", $z->signed);
				} else {
					$zoneConfig .= sprintf("\tfile \"%s\";\n", $z->file);
				}
				$zoneConfig .= sprintf("\tnotify yes;\n");
				$zoneConfig .= sprintf("\tallow-transfer { trusted-mysql-servers; };\n");
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

			if($zone["dnssec"] === 1) {
				//$zoneConfig .= sprintf("\tdnssec-enable yes;\n");
				//$zoneConfig .= sprintf("\tdnssec-validation yes;\n");
				//$zoneConfig .= sprintf("\tdnssec-lookaside auto;\n");
			}

			$zoneConfig .= sprintf("\tallow-notify { trusted-mysql-servers; };\n");
			$zoneConfig .= sprintf("\tallow-transfer { trusted-mysql-servers; };\n");
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
?>
