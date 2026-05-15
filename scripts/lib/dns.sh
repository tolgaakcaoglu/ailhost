#!/usr/bin/env bash
setup_dns() {
	DOMAIN="$1"
	ZONE_FILE="/var/named/$DOMAIN.db"
	NAMED_CONF="/etc/named.conf"

	echo "BIND zone dosyasi olusturuluyor: $ZONE_FILE"
	sudo tee "$ZONE_FILE" > /dev/null <<EOF
\$TTL 86400
@	IN	SOA	ns1.$DOMAIN. admin.$DOMAIN. (
	2026051501	; serial
	3600		; refresh
	1800		; retry
	604800		; expire
	86400		; minimum
)
@	IN	NS	ns1.$DOMAIN.
@	IN	NS	ns2.$DOMAIN.
@	IN	A	95.70.159.147
www	IN	A	95.70.159.147
ns1	IN	A	95.70.159.147
ns2	IN	A	95.70.159.147
EOF

	sudo chown root:named "$ZONE_FILE"
	sudo chmod 640 "$ZONE_FILE"

	ZONE_DECL="zone \"$DOMAIN\" IN { type master; file \"$ZONE_FILE\"; allow-update { none; }; };"

	if ! grep -q "$DOMAIN" "$NAMED_CONF"; then
		echo "$ZONE_DECL" | sudo tee -a "$NAMED_CONF"
	fi

	if ! systemctl is-active --quiet named; then
		sudo systemctl enable --now named
	fi
	sudo systemctl reload named
	echo "DNS zone ve named servisi hazir."
 
}
