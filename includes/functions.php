<?php
umask(0027);
require_once(__dir__ ."/zone.php");
require_once(__dir__ ."/named.php");
require_once(__dir__ ."/log.php");

$log = new log(true);

if(!is_dir(tmp)) {
  mkdir(tmp,0750,true);
}

try {
  $pdo = new pdo('mysql:host='. dns_host .';port='. dns_port.';dbname='. dns_database,  dns_user, dns_password,
              array( 	PDO::ATTR_PERSISTENT => false,
                      PDO::ATTR_STRINGIFY_FETCHES => false,
                      PDO::ATTR_EMULATE_PREPARES => false,
                      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));

  $log->info("DATABAS CONNECTED");

} catch (PDOException $e) {
    echo "DATABAS CONNECTION FAILED\n";
    exit(100);
}

function dnsWriteZone(pdo $pdo,log $log,$zone)
{
  $stmtSoa = $pdo->prepare("
    SELECT
      `zone`,
      `server`,
      UNIX_TIMESTAMP(`serial`) as serial,
      `refresh`,
      `retry`,
      `expire`,
      `minimum`,
      `email`,
      `ttl`,
      publicZSK,
      publicKSK,
      dnssec
    FROM zones
    WHERE zone=:zone
    limit 1;
  ");

  $stmtDomains = $pdo->prepare("
  SELECT
    `domain`,
    `type`,
    `data`,
    `priority`,
    `ttl`,
    `weight`,
    `port`
  FROM domains
  WHERE zone=:zone
  ORDER BY
    domain ASC,
    `type` ASC,
    `ttl` asc;
  ");

  $dns = "";
  $stmtSoa->bindValue(':zone', $zone, PDO::PARAM_STR );
  if($stmtSoa->execute()) {
    while($soa = $stmtSoa->fetch()) {

      $named = new zone($zone,$log);
      $dns 		= sprintf("%s %s SOA %s %s (\n%d\n%d\n%d\n%d\n%d\n)\n\n",$soa["zone"],$soa["ttl"],$soa["server"],$soa["email"],$soa["serial"],$soa["refresh"],$soa["retry"],$soa["expire"],$soa["minimum"]);

      if($soa["dnssec"] === 1) {
        $dns .= sprintf("\$INCLUDE %s/%s\n", $named->dir, $soa["publicZSK"]);
        $dns .= sprintf("\$INCLUDE %s/%s\n", $named->dir, $soa["publicKSK"]);
      }

      $stmtDomains->bindValue(':zone', $zone, PDO::PARAM_STR );
      if($stmtDomains->execute()) {
        while($domain = $stmtDomains->fetch()) {
          switch(strtoupper($domain["type"])) {
    				case "MX":
    				$dns .= sprintf("%s\t\tIN %d\t%s\t%d\t%s\n",$domain["domain"],$domain["ttl"],strtoupper($domain["type"]),$domain["priority"],$domain["data"]);
    				break;
    				case "SRV":
    				$dns .= sprintf("%s\t\tIN %d\t%s\t%d\t%d\t%d\t%s\n",$domain["domain"],$domain["ttl"],strtoupper($domain["type"]),$domain["priority"],$domain["weight"],$domain["port"],$domain["data"]);
    				break;
    				case "NS":
    				case "CNAME":
  			    default:

    				$dns .= sprintf("%s\t\tIN %d\t%s\t\t%s\n",$domain["domain"],$domain["ttl"],strtoupper($domain["type"]),$domain["data"]);
    			}
        }
      }
      if($named->writeZone($dns)) {
        if($soa["dnssec"] === 1) {
          if($named->signZone()) {
            return true;
          }
        } else {
          return true;
        }
      }
    }
  }

	return false;
}

?>
