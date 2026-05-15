#!/usr/bin/env bash
set -e

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"

DOMAIN="$1"
if [ -z "$DOMAIN" ]; then
	echo "Kullanim: sudo ./scripts/add-domain.sh example.com"
	exit 1
fi

source "$SCRIPT_DIR/lib/nginx.sh"
source "$SCRIPT_DIR/lib/site.sh"
source "$SCRIPT_DIR/lib/dns.sh"

install_nginx
create_site "$DOMAIN"
setup_dns "$DOMAIN"

echo "Domain $DOMAIN basariyla hazirlandi!"
