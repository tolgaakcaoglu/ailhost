#!/usr/bin/env bash
install_nginx() {
	echo "nginx kuruluyor..."
	if ! commend -v nginx &> /dev/null; then
		sudo dnf install -y nginx
		sudo systemctl enable --now nginx
	else
		echo "nginx zaten kuruluydu"
	fi

	sudo firewall-cmd --permanent --add-service=http
	sudo firewall-cmd --permanent --add-service=https
	sudo firewall-cmd --reload
	echo "nginx ve firewall ayarlari tamamlandi."
}
