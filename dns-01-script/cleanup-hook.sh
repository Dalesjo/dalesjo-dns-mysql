#!/bin/bash

echo "Clean DNS service"
wget -q --spider --no-check-certificate "https://dns.fnf.nu/a7e04d4e8cf0169fec0f13fb5933f7c3275e4942/?zone=${DNS_ZONE}&domain=${DNS_ACME}.${DNS_DOMAIN}&secret=${DNS_SECRET}&data=not-used"
echo "cleanup-hook done"

