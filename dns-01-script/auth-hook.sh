#!/bin/bash
echo "Update DNS service"
wget -q --spider --no-check-certificate "https://dns.fnf.nu/a7e04d4e8cf0169fec0f13fb5933f7c3275e4942/?zone=${DNS_ZONE}&domain=${DNS_ACME}.${DNS_DOMAIN}&secret=${DNS_SECRET}&data=${CERTBOT_VALIDATION}"

while : ; do
    echo "Sleep 10s"
    sleep 15
    
    DNS_VALUE=$(dig +short -t txt ${DNS_ACME}.${DNS_DOMAIN}.${DNS_ZONE} | tr -d '"')
    
    echo "Verify if DNS is updated"
    if [ "${DNS_VALUE}" == "${CERTBOT_VALIDATION}" ]; then
        echo "DNS is updated"
        break
    fi
done

echo "auth-hook done"