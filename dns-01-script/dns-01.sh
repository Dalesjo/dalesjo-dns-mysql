#!/bin/bash

SCRIPT_DIR=$( cd -- "$( dirname -- "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )
export $(grep -v '^#' ${SCRIPT_DIR}/.env | xargs)

certbot certonly --manual --preferred-challenges=dns --manual-auth-hook ${SCRIPT_DIR}/auth-hook.sh --manual-cleanup-hook ${SCRIPT_DIR}/cleanup-hook.sh -d ${DNS_DOMAIN}.${DNS_ZONE}