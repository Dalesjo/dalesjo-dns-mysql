<?php
require_once(__dir__ ."/../config.php");
require_once(__dir__ ."/../includes/functions.php");

$stmtServer = $pdo->prepare("
  SELECT
    `server`,
    `ip`,
    `configUpdated`
  FROM servers
  WHERE server=:ns;
");

$stmtUpdate = $pdo->prepare("
  SELECT
    update_server(:ns);
");

$stmtZones = $pdo->prepare("
  SELECT
    z.`zone`
  FROM zones AS z
  INNER JOIN zoneServers AS zs ON zs.zone=z.zone
  WHERE z.`serial` > :time
  AND zs.server=:ns;
");

$stmtServer->bindValue(':ns', ns, PDO::PARAM_STR );
if($stmtServer->execute()) {
  while($server = $stmtServer->fetch()) {
    $stmtZones->bindValue(':time', $server["configUpdated"], PDO::PARAM_STR );
    $stmtZones->bindValue(':ns', ns, PDO::PARAM_STR );

    if($stmtZones->execute()) {
      while($zone = $stmtZones->fetch()) {
        if(dnsWriteZone($pdo,$zone["zone"])) {
    			logtosystem($zone["zone"] ." updated.");
    		} else {
    			logtosystem($zone["zone"] ." error.");
    		}
      }
    }
  }

  if($stmtServer->rowCount() > 0) {
    $stmtUpdate->bindValue(':ns', ns, PDO::PARAM_STR );
    if($stmtUpdate->execute()) {
      exec("/usr/sbin/rndc reload", $logRndc, $rtnRndc);
      if($rtnRndc === 0) {
        logtosystem("named/rndc reloaded");
        exit(0);
      } else {
        logtosystem($logRndc);
        exit(2);
      }
    }
  }


}

?>
