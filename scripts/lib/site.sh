#!/usr/bin/env bash
create_site() {
	DOMAIN="$1"
	SITE_ROOT="/var/www/$DOMAIN/public_html"
	echo "Site dizini olusturuluyor: $SITE_ROOT"
	sudo mkdir -p "$SITE_ROOT"
	echo "<h1>$DOMAIN</h1><p>ailhost kurulumunuz basarili!</p>" | sudo tee "$SITE_ROOT/index.html"

	CONF_FILE="/etc/nginx/conf.d/$DOMAIN.conf"
	sudo tee "$CONF_FILE" > /dev/null <<EOF
server {
	listen 80;
	server_name $DOMAIN www.$DOMAIN;
	root $SITE_ROOT;
	index index.html;
}
EOF
	sudo nginx -t
	sudo systemctl reload nginx
	echo "Site ve Nginx config hazir."
}
