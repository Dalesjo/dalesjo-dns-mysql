<?php
require_once(__dir__ ."/../config.php");
require_once(__dir__ ."/../includes/functions.php");

$stmtServer = $pdo->prepare("select `update_dnssec`();");
$stmtServer->execute();

exit(0);
?>