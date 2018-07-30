<?php
require_once(__dir__ ."/../config.php");
require_once(__dir__ ."/../includes/functions.php");

$stmtServer = $pdo->prepare("
  SELECT
    `server`,
    `ip`,
    `configUpdated`,
		`notifyLocalOnly`
  FROM servers
  WHERE server=:ns;
");

$stmtUpdate = $pdo->prepare("
  SELECT
    update_server(:ns);
");

$stmtUpdateZone = $pdo->prepare("
  SELECT
    update_zoneServer(:ns,:zone);
");

$stmtZones = $pdo->prepare("
  SELECT
    z.`zone`
  FROM zones AS z
  INNER JOIN zoneServers AS zs ON zs.zone=z.zone
  WHERE (zs.configUpdated is null or z.`serial` > zs.configUpdated  )
  AND zs.server=:ns;
");

$stmtServer->bindValue(':ns', ns, PDO::PARAM_STR );

if($stmtServer->execute()) {
  while($server = $stmtServer->fetch()) {
    $stmtUpdateZone->bindValue(':ns', $server["server"], PDO::PARAM_STR );
    $stmtZones->bindValue(':ns', $server["server"], PDO::PARAM_STR );

    if($stmtZones->execute()) {
      while($zone = $stmtZones->fetch()) {
        if(dnsWriteZone($pdo,$log,$zone["zone"])) {
          $stmtUpdateZone->bindValue(':zone', $zone["zone"], PDO::PARAM_STR );
          if($stmtUpdateZone->execute()) {
            $log->info($zone["zone"] ." updated.");
          } else {
            $log->info($zone["zone"] ." database malfunction.");
          }
    		} else {
    			$log->info($zone["zone"] ." error.");
    		}
      }
    }
  }

  if($stmtServer->rowCount() > 0) {
    $stmtUpdate->bindValue(':ns', ns, PDO::PARAM_STR );
    exec("/usr/sbin/rndc reload", $logRndc, $rtnRndc);
    if($rtnRndc === 0) {
      $log->info("named/rndc reloaded");
      if($stmtUpdate->execute()) {
        exit(0);
      }
    } else {
      $log->info(implode("\n",$logRndc));
      exit(2);
    }
  }


}

?>
