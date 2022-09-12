#!/bin/bash

SCRIPT_DIR=$( cd -- "$( dirname -- "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )
export $(grep -v '^#' ${SCRIPT_DIR}/.env | xargs)

${SCRIPT_DIR}/cleanup-hook.sh