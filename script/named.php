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

  $f 		= fopen(bindconf_tmp,'w');

	$acl = "acl trusted-mysql-servers  {\n";
	while($server = $stmtServers->fetch()) {
		$acl .= sprintf("\t%s; // %s\n",$server["ip"],$server["server"]);
	}
	$acl .= "};\n\n";
	if(fwrite($f,$acl)) {
	  while($zone = $stmtZones->fetch()){
	    if($zone["master"] === 1) {
	      $zonefile		= escapeshellarg(bindroot . dnsNameReverse($zone["zone"]));
	      $zonename = escapeshellarg($zone["zone"]);

	      exec("/usr/sbin/named-checkzone $zonename $zonefile", $logCheckzone, $rtnCheckzone);
	      if($rtnCheckzone === 0) {
					if(fwrite($f,sprintf("zone \"%s\" IN {\n\ttype master;\n\tfile \"%s\";\n\tnotify yes;\n\tallow-transfer { trusted-mysql-servers; };\n};\n\n",$zone["zone"],dnsNameReverse($zone["zone"])))) {
						logtosystem(bindconf." updated as master for:\t".$zone["zone"]);
					}
				} else {
						logtosystem(bindconf." error in zonefile for:\t".$zone["zone"]);
				}
	    } else {
	      if(fwrite($f,sprintf("zone \"%s\" IN {\n\ttype slave;\n\tfile \"slaves/%s\";\n\tallow-notify { trusted-mysql-servers; };\n\tallow-transfer { trusted-mysql-servers; };\n\tmasters {\n%s\n\t};\n};\n\n",$zone["zone"],dnsNameReverse($zone["zone"]),$zone["ips"]))) {
	        logtosystem(bindconf." updated as slave for:\t".$zone["zone"]);
	      }
	    }
	  }
	}
	fclose($f);

  /**
   * test temporary named.conf and replace old named.conf if ok.
   */
	 if(is_file(bindconf_tmp)) {
		exec("/usr/sbin/named-checkconf ". bindconf_tmp, $logCheckConf, $rtnCheckConf);
 		if($rtnCheckConf === 0)	{
 			if(is_file(bindconf)) {
 				unlink(bindconf);
 			}

 			if(rename(bindconf_tmp,bindconf)) {
 				exec("/usr/sbin/rndc reload", $logRndc, $rtnRndc);

 				if($rtnRndc === 0) {
 					logtosystem("named/rndc reloaded");
 					exit(0);
 				} else {
 					logtosystem(implode("\n",$logRndc));
 					exit(2);
 				}
 			}
 		} else {
 			logtosystem($logCheckConf);
 			exit(1);
 		}
	} else {
		logtosystem("Missing file ". bindconf_tmp);
		exit(3);
	}


}


?>
