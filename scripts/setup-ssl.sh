#!/usr/bin/env bash
set -e

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"

DOMAIN="$1"

if [ -z $DOMAIN ]; then
	echo "Kullanim: sudo ./scripts/setup-ssl.sh example.com"
	exit 1
fi

echo "SSL KURULUMU BASLATILIYOR"

if ! command -v certbot &> /dev/null; then
	echo "Certbot yuklu degil, yukleniyor..."
	sudo dnf install -y epel-release
	sudo dnf install -y certbot python3-certbot-nginx
fi

sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --reload

sudo certbot --nginx -d "$DOMAIN" -d "www.$DOMAIN" --non-interactive --agree-tos -m admin@$DOMAIN

echo "SSL kurulumu tamamlandi!"
echo "HTTPS ile erisim: https://$DOMAIN"
