<?php
define("dns_host","192.168.61.114");
define("dns_port","3030");

define("dns_database","dns");
define("dns_user","dns");
define("dns_password","GPe5q7KuSThjmILCYbMhWn");

// binds root configuration. (används ej just nu)
define("bindroot","/var/named/");

// konfigurationsfil för domäner via mybind (bör ägas av root)
define("bindconf","/etc/named.mysql.conf");


define("bindconf_tmp","/tmp/named.mysql.conf");

/* Domännamn för dns server */
define("ns","ns1.fnf.nu.");

// temporary folder.
define("tmp","/tmp/dalesjo-dns-mysql/");
?>
