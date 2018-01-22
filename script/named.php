<?php
require_once(__dir__ ."/../config.php");
require_once(__dir__ ."/../includes/functions.php");

$stmtZones = $pdo->prepare("
SELECT
	z.`zone`,
	z.`server`,
	z.`serial`,
	z.`refresh`,
	z.`retry`,
	z.`expire`,
	z.`minimum`,
  s.`ip`,
  IF(zs.server is null,0,1) as `master`
FROM zones AS z
LEFT JOIN zoneservers AS zs ON zs.zone=z.zone AND zs.server=:ns
INNER JOIN servers AS s ON s.server = z.server
ORDER BY z.`zone` asc;
");

$stmtZones->bindValue(':ns', ns, PDO::PARAM_STR );
if($stmtZones->execute()) {

  $f 		= fopen(bindconf_tmp,'w');
  while($zone = $stmtZones->fetch()){
    if($zone["master"] === 1) {
      $zonefile		= escapeshellarg(bindroot . dnsNameReverse($zone["zone"]));
      $zonename = escapeshellarg($zone["zone"]);

      exec("/usr/sbin/named-checkzone $zonename $zonefile", $logCheckzone, $rtnCheckzone);
      if($rtnCheckzone === 0) {
				if(fwrite($f,sprintf("zone \"%s\" IN {\n\ttype master;\n\tfile \"%s\";\n};\n\n",$zone["zone"],dnsNameReverse($zone["zone"])))) {
					logtosystem(bindconf." updated as master for:\t".$zone["zone"]);
				}
			} else {
					logtosystem(bindconf." error in zonefile for:\t".$zone["zone"]);
			}
    } else {
      if(fwrite($f,sprintf("zone \"%s\" IN {\n\ttype slave;\n\tfile \"slaves/%s\";\n	masters {%s;};\n};\n\n",$zone["zone"],dnsNameReverse($zone["zone"]),$zone["ip"]))) {
        logtosystem(bindconf." updated as slave for:\t".$zone["zone"]);
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
 				system("");
 				exec("/usr/sbin/rndc", $logRndc, $rtnRndc);

 				if($rtnRndc === 0) {
 					logtosystem("named/rndc reloaded");
 					exit(0);
 				} else {
 					logtosystem($logRndc);
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
