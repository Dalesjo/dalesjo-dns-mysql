<?php
require_once(__dir__ ."/../config.php");
require_once(__dir__ ."/../includes/functions.php");

if(isset($_GET["zone"]) && isset($_GET["domain"]) && isset($_GET["secret"]))
{
	$zone 	= $_GET["zone"];
	$domain = $_GET["domain"];
	$secret = $_GET["secret"];

	if(isset($_GET["data"])) {
		$data	= $_GET["data"];
	} else {
		$data	= $_SERVER['REMOTE_ADDR'];
	}

	$stmtUpdate = $pdo->prepare("
		SELECT update_domain(	:zone,
								:domain,
								:secret,
								:data)
		AS updated;
	");

	$stmtUpdate->bindValue(':zone', $zone, PDO::PARAM_STR );
	$stmtUpdate->bindValue(':domain', $domain, PDO::PARAM_STR );
	$stmtUpdate->bindValue(':secret', $secret, PDO::PARAM_STR );
	$stmtUpdate->bindValue(':data', $data, PDO::PARAM_STR );

	if($stmtUpdate->execute()) {
		while($result = $stmtUpdate->fetch()) {
			if($result["updated"] > 0) {
				echo "OK: ". $data;
			} else {
				echo "NO: ". $data;
			}
		}
	} else  {
		echo "BAD: ". $data;
	}

	exit();
}
?>
<!DOCTYPE html>
<html>
	<head>
	<style type="text/css">

	h3 {margin-bottom:0; padding-bottom:0;}
	dt {font-weight:bold;}
	dd {font-style:italic;}
	code {white-space: pre-line;}
	</style>
	</head>
	<body>
	<h1>DNS</h1>

	<h3>URL</h3>
	<code>
	http://dns.fnf.nu/{key}/?zone={zone}&domain={domain}&secret={secret}
	http://dns.fnf.nu/{key}/?zone={zone}&domain={domain}&secret={secret}&data={data}
	</code>

	<h3>Förklaring</h3>
	<dl>
	<dt>Key</dt>
	<dd>Nyckel till denna sida</dd>
	<dt>Zone</dt>
	<dd>Zone du vill uppdatera, observera att zonen slutar på en punkt. Exempel: fnf.nu. </dd>
	<dt>Domain<dt>
	<dd>domän du vill uppdatera, exempel: dns</dd>
	<dt>Secret</dt>
	<dd>din hemliga nyckel för att uppdatera den här domänen.</dd>
	<dt>Data (frivilligt)</dt>
	<dd>Data du vill sätta till den här domänen, vanligtvis ditt ip-nummer, exempel: 127.0.0.1<br /></dd>
	</dl>

	<h3>Exempel cronjob</h3>
	<div>
	Exempel för cronjob till Linux uppdaterat ip-nummret var 15 minut om det är nytt.
	</div>
	<code>
	# For details see man 4 crontabs
	# Example of job definition:
	# .---------------- minute (0 - 59)
	# |  .------------- hour (0 - 23)
	# |  |  .---------- day of month (1 - 31)
	# |  |  |  .------- month (1 - 12) OR jan,feb,mar,apr ...
	# |  |  |  |  .---- day of week (0 - 6) (Sunday=0 or 7) OR sun,mon,tue,wed,thu,fri,sat
	# |  |  |  |  |
	# *  *  *  *  * user-name  command to be executed
	*/15  *  *  *  * root     wget https://dns.fnf.nu/{key}/?zone={zone}&domain={domain}&secret={secret}
	</code>

	</body>
</html>
