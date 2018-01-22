<?php
define("dns_host","192.168.61.108");
define("dns_port","3306");

define("dns_database","dns");
define("dns_user","dns");
define("dns_password","secret");

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
