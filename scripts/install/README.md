# Installer Scripts

Bu dizin, panel kurulumunu destekleyen minimal terminal bootstrap adimlarini tutar.

## 1) Bootstrap

Sistem pathlerini olusturur ve `/etc/ailhost/paths.env` dosyasini yazar:

```bash
sudo bash scripts/install/bootstrap.sh
```

Dry-run:

```bash
bash scripts/install/bootstrap.sh --dry-run
```

## 2) Upgrade Oncesi Backup

Upgrade'den once `config/` ve `var/` klasorlerini yedekler:

```bash
bash scripts/install/backup-before-upgrade.sh
```

Varsayilan cikti:

- `var/backups/upgrades/pre-upgrade-YYYYMMDD-HHMMSS.tar.gz`
