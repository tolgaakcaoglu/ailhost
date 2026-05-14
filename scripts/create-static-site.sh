#!/usr/bin/env bash

set -e

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m'

DOMAIN="$1"

if [ -z "$DOMAIN" ]; then
	echo -e "${RED}HATA! domain adi girilmedi${NC}"
	echo "Kullanim: sudo ./scripts/create-static-site.sh example.com"
	exit 1
fi

if [[ "$DOMAIN" == *"/"* ]] || [[ "$DOMAIN" == *" "* ]]; then
	echo -e "${RED}HATA! gecersiz domain adi${NC}"
	exit 1
fi

SITE_ROOT="/var/www/${DOMAIN}/public_html"
NGINX_CONF="/etc/nginx/conf.d/${DOMAIN}.conf"
TEMPLATE_FILE="/opt/mini-host-panel/templates/nginx-static-site.conf.template"

echo -e "${BLUE}domain:${NC} ${DOMAIN}"
echo -e "${BLUE}site dizini:${NC} ${SITE_ROOT}"
echo -e "${BLUE}nginx config:${NC} ${NGINX_CONF}"

if [ ! -f "$TEMPLATE_FILE" ]; then
	echo -e "${RED}HATA! template dosyasi bulunamadi: ${TEMPLATE_FILE}${NC}"
	exit 1
fi

echo -e "${YELLOW}site dizini olusturuluyor..${NC}"
mkdir -p "${SITE_ROOT}"

echo -e "yazi${YELLOW}index.html olusturuluyor..${NC}"
cat > "${SITE_ROOT}/index.html" <<EOF
<!DOCTYPE html>
<html lang="tr">
<head>
	<meta charset="UTF-8">
	<title>${DOMAIN}</title>
</head>
<body>
	<h1>${DOMAIN} yayinda!</h1>
	<p>Bu site sizin icin ailhost tarafindan gecici olarak olusturulmustur :)</p>
</body>
</html>
EOF

echo -e "${YELLOW}dosya izinleri ayarlaniyor..${NC}"
chown -R nginx:nginx "/var/www/${DOMAIN}"
chmod -R 755 "/var/www/${DOMAIN}"

if [ -f "${NGINX_CONF}" ]; then
	echo -e "${YELLOW}Uyari: nginx config zaten var, uzerine yaziliyor..${NC}"
fi

echo -e "${YELLOW}nginx config olusturuluyor..${NC}"
sed "s/{{DOMAIN}}/${DOMAIN}/g" "$TEMPLATE_FILE" > "$NGINX_CONF"

echo -e "${YELLOW}nginx config test ediliyor...${NC}"
nginx -t

echo -e "${YELLOW}nginx yeniden baslatiliyor..${NC}"
systemctl reload nginx

echo -e "${GREEN}site basariyla olusturuldu!${NC}"
echo -e "${GREEN}test:${NC} http://${DOMAIN}"
echo -e "${GREEN}yerel test:${NC} curl -H 'Host: ${DOMAIN}' http://127.0.0.1"

