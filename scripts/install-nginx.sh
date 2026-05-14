#!/usr/bin/env bash

set -e

RED='\303[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${GREEN}nginx kurulumu baslatiliyor...${NC}"
dnf install -y nginx

echo -e "${BLUE}nginx indirildi > aktif ediliyor...${NC}" 
systemctl enable nginx

echo -e "${BLUE}nginx aktif > baslatiliyor...${NC}"
systemctl start nginx

echo -e "${YELLOW}firewall http/https izinleri ekleniyor..${NC}"
if systemctl is-active --quiet firewalld; then
	firewall-cmd --permanent --add-service=http
	firewall-cmd --permanent --add-service=https
	firewall-cmd --reload
else
	echo -e "${YELLOW}firewalld aktif degil, bu adim atlandi${NC}"
fi

echo -e "${BLUE}nginx test ediliyor..${NC}"
if curl -I http://localhost | grep -q "200\|301\|302"; then
	echo -e "${GREEN}nginx basarilyla calistirildi${NC}"
else
	echo -e "${RED}nginx calisiyor olabilir ama localhost beklenen cevabi vermedi${NC}"
fi

echo -e "${GREEN}kurulum tamamlandi${NC}" 
