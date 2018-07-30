# Installation

Create cron script to run zones.php every 6th minute (for dyndns).

# nano /etc/cron.d/dns
```
SHELL=/bin/bash
PATH=/sbin:/bin:/usr/sbin:/usr/bin
# For details see man 4 crontabs
# Example of job definition:
# .---------------- minute (0 - 59)
# |  .------------- hour (0 - 23)
# |  |  .---------- day of month (1 - 31)
# |  |  |  .------- month (1 - 12) OR jan,feb,mar,apr ...
# |  |  |  |  .---- day of week (0 - 6) (Sunday=0 or 7) OR sun,mon,tue,wed,thu,fri,sat
# |  |  |  |  |
# *  *  *  *  *         user-name       command to be executed
*/6  *  *  *  *	        root            /opt/rh/rh-php71/root/bin/php /root/bin/dalesjo-dns-mysql/script/zones.php
0    *  *  *  *         root            /opt/rh/rh-php71/root/bin/php /root/bin/dalesjo-dns-mysql/script/named.php
```
Run the command below to secure files

```#chmod o= /etc/cron.d/dns
#chmod o= /root/bin/dalesjo-dns-mysql/
#chown root:named /etc/named.mysql.conf
#chmod o= /etc/named.mysql.conf```
