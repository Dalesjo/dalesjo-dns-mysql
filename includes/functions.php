<?php
umask(0022);

if(!is_dir(tmp)) {
  mkdir(tmp,0022,true);
}

try {
  $pdo = new pdo('mysql:host='. dns_host .';port='. dns_port.';dbname='. dns_database,  dns_user, dns_password,
              array( 	PDO::ATTR_PERSISTENT => false,
                      PDO::ATTR_STRINGIFY_FETCHES => false,
                      PDO::ATTR_EMULATE_PREPARES => false,
                      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));

} catch (PDOException $e) {
    echo "NO DATABASE\n";
    exit(100);
}


/* dnsReverse */
function dnsNameReverse($domain) {

  $domain 	= trim($domain,".");
	$part 		= explode(".",$domain);
	$filename 	= "";

  for($i=sizeof($part)-1; $i >= 0;$i--) {
		$filename .= $part[$i] .".";
	}

	$filename = trim($filename,".");
	return $filename;
}

function logtosystem($msg,$level=LOG_INFO)
{
	syslog($level,$msg);
	echo "MyBind: ".$msg."\n";
}


function dnsWriteZone(pdo $pdo,$zone)
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
      `ttl`
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
      $dns 		= sprintf("%s %s SOA %s %s (\n%d\n%d\n%d\n%d\n%d\n)\n\n",$soa["zone"],$soa["ttl"],$soa["server"],$soa["email"],$soa["serial"],$soa["refresh"],$soa["retry"],$soa["expire"],$soa["minimum"]);

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
    				$dns .= sprintf("%s\t\tIN %d\t%s\t\t%s\n",$domain["domain"],$domain["ttl"],strtoupper($domain["type"]),$domain["data"]);
    				break;

    				case "CNAME":
    				$dns .= sprintf("%s\t\tIN %d\t%s\t\t%s\n",$domain["domain"],$domain["ttl"],strtoupper($domain["type"]),$domain["data"]);
    				break;

    			    default:
    				$dns .= sprintf("%s\t\tIN %d\t%s\t\t%s\n",$domain["domain"],$domain["ttl"],strtoupper($domain["type"]),$domain["data"]);
    			}
        }

      }

      $filetmp	= tmp . dnsNameReverse($soa["zone"]);
      $file		= bindroot . dnsNameReverse($soa["zone"]);
      if(file_put_contents($filetmp,$dns)) {
        exec("/usr/sbin/named-checkzone $zone $filetmp", $log, $rtn);
        if($rtn == 0) {
          if(is_file($file)) {
            unlink($file);
          }

          if(rename($filetmp,$file)) {
            return true;
          }
        }
      }

    }



  }

	return false;
}

?>
