#!/usr/bin/env bash

set -e

DOMAIN="$1"

SITE_ROOT="/var/www/${DOMAIN}/public_html"
ZONE_FILE="/var/named/${DOMAIN}.db"
NAMED_CONF="/etc/named.conf"

if [ -z "$DOMAIN" ]; then
	echo "hata! domain adi girilmedi"
	echo "kullanim: sudo ./setup-dns-and-perms.sh example.com"
	exit 1
fi

echo "domain: $DOMAIN"
echo "site izinleri ayarlaniyor......."

if [ -d "$SITE_ROOT" ]; then
	chown -R $USER:$USER "/var/www/${DOMAIN}"
	chmod -R 755 "/var/www/${DOMAIN}"
else
	echo "uyari!!!! site dizini yok, sadece DNS ve SELinux ayarlanacak!"

fi

if command -v semanage &> /dev/null; then
	echo "selinux context ayarlaniyor......."
	semanage fcontext -a -t httpd_sys_content_t "/var/www/${DOMAIN}(/.*)?" || true
	restorecon -Rv "/var/www/${DOMAIN}" || true
else
	echo "semanage bulunamadi, selinux context atlaniyor"
fi

echo "bind zone dosyasi olusturuluyor: ${ZONE_FILE}"

sudo tee "$ZONE_FILE" > /dev/null <<EOF
\$TTL 86400
@	IN	SOA ns1.${DOMAIN}. admin.${DOMAIN}. (
		2026051401	; serial
		3600		; refresh
		1800		; retry
		604800		; expire
		86400		; minimum
)
@	IN	NS	ns1.${DOMAIN}.
@	IN	NS	ns2.${DOMAIN}.
@	IN	A	95.70.159.147
www	IN	A	95.70.159.147
ns1	IN	A	95.70.159.147
ns2	IN	A	95.70.159.147
EOF

echo "zone dosya izinler ayarlaniyor.."
chown root:named "$ZONE_FILE"
chmod 640 "$ZONE_FILE"

echo "named.conf'a zone ekleniyor.."
ZONE_DECL="zone \"$DOMAIN\" IN { type master; file \"$ZONE_FILE\"; allow-update { none; }; };"

if ! grep -q "$DOMAIN" "$NAMED_CONF"; then
	echo "$ZONE_DECL" | sudo tee -a "$NAMED_CONF"
else
	echo "UYARI: zone zaten named.conf icinde, atlandi"
fi

echo "named servisi kontrol ediliyor..."
if ! systemctl is-active --quiet named; then
	echo "named servisi aktif degil, baslatiliyor ve aktif ediliyor"
	sudo systemctl enable --now named
else
	echo "named servisi zaten aktifti"
fi

echo "named servisi yeniden baslatiliyor...."
sudo systemctl reload named

echo "DNS ve IZIN AYARLARI TAMAMLANDI: BITTI"
