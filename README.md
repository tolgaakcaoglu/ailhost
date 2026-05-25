## ailhost

ailhost, Linux sunucular icin acik kaynak bir hosting yonetim paneli hedefiyle baslatilmis erken asama bir projedir.

Uzun vadeli hedef; website, domain, SSL, DNS, e-posta, FTP, dosya yonetimi, veritabani, PHP, WordPress, backup/restore, firewall, Git deployment, Docker uygulamalari ve kullanici/paket yonetimini tek panelden yonetebilen, CyberPanel/cPanel benzeri fakat daha sade ve gelistirilebilir bir platform olusturmaktir.

Proje su anda temel domain, site, DNS ve SSL kurulum denemeleri asamasindadir. Nihai hedef script kullandiran bir arac degil; ilk kurulumdan website yonetimine kadar web arayuzuyle calisan, CyberPanel benzeri acik kaynak bir hosting panelidir.

## Mevcut Durum

| Alan | Durum |
| --- | --- |
| Website olusturma | Kismi |
| Domain baglama | Kismi |
| Nginx vhost olusturma | Kismi |
| BIND DNS zone olusturma | Kismi |
| Let's Encrypt SSL alma | Kismi |
| Panel arayuzu | Yok |
| API/backend | Yok |
| Kullanici/paket sistemi | Yok |
| Veritabani/PHP/FTP/e-posta/backup | Yok |

Mevcut scriptler simdilik prototip niteligindedir. Uzun vadede kullanici bu scriptleri calistirmayacak; panelin worker/servis katmani bu islemleri guvenli sekilde arka planda yurutmelidir.

- `scripts/add-domain.sh`: domain icin nginx site dizini, vhost ve DNS zone olusturma denemesi.
- `scripts/setup-ssl.sh`: certbot ile nginx uzerinden SSL alma denemesi.
- `scripts/lib/nginx.sh`: nginx kurulumu ve firewall portlari.
- `scripts/lib/site.sh`: basit static site dizini ve nginx config olusturma.
- `scripts/lib/dns.sh`: BIND zone dosyasi olusturma.

## Hedef Platform

ailhost'un hedefledigi ana moduller:

- Website, domain, subdomain, alias ve vhost yonetimi
- SSL/HTTPS ve otomatik sertifika yenileme
- DNS zone ve kayit yonetimi
- PHP versiyonlari ve site bazli PHP ayarlari
- MySQL/MariaDB ve phpMyAdmin yonetimi
- Web tabanli file manager
- FTP hesaplari
- E-posta, mailbox, DKIM/SPF/DMARC ve webmail
- WordPress kurulum, yonetim, staging ve backup
- Backup/restore ve uzak yedek hedefleri
- Firewall, guvenlik kontrolleri ve servis sagligi
- Git deployment ve webhook tabanli yayina alma
- Docker uygulama yonetimi
- Kullanici, rol, paket ve kota sistemi

## Roadmap

Ayrintili gelistirme plani ve fazlara ayrilmis is listesi icin:

[ROADMAP.md](ROADMAP.md)

Release dokumanlari icin:

[docs/release/README.md](docs/release/README.md)

Adim adim gelistirme belgeleri icin:

[docs/README.md](docs/README.md)

## Kurulum ve Upgrade Hazirligi

Panel-first yaklasim korunur; yine de temiz sunucuda minimum terminal bootstrap gerekir.

1. Sistem path bootstrap:

```bash
sudo bash scripts/install/bootstrap.sh
```

2. Paneli acip web installer adimlarini tamamla (`/install`).

3. Upgrade oncesi state yedegi:

```bash
bash scripts/install/backup-before-upgrade.sh
```

Detay:

- `scripts/install/README.md`

## Temel Karar

- Urunun ana arayuzu web panelidir; website, domain, SSL, DNS, PHP, veritabani ve backup islemleri terminalden degil panelden yapilmalidir.
- Ilk kurulum akisi da mumkun oldugunca web tabanli olmalidir: minimal bootstrap sonrasinda admin olusturma, servis secimi, IP/nameserver ayarlari ve ilk site kurulumu arayuzden tamamlanmalidir.
- Scriptler son kullanici araci degil, panelin arka planda kullandigi kontrollu servis adapterleri olarak kalmalidir.
- Repo acik kaynak ve self-hosted olmalidir; kapali kaynak SaaS, harici kontrol paneli servisi veya zorunlu bulut bagimliligi olmamalidir.
- Her buyuk gelistirme parcasi baslamadan once `/docs` altinda `STEP-XXX` belgesi bulunmalidir.
- Bir step tamamlanmadan sonraki step'in koduna gecilmemelidir; once belge guncellenmeli, sonra gelistirme yapilmalidir.
- Root yetkisi gereken islemler kontrollu ve loglanabilir olacak.
- Her modul idempotent calismali: ayni komut ikinci kez calistiginda sistemi bozmamali.
- Desteklenen ilk hedef sistem RHEL uyumlu dagitimlar olacak: AlmaLinux/Rocky Linux.
- Her kritik operasyon icin dry-run, rollback veya en azindan dogrulama adimi bulunacak.
- Panel, sunucu servislerinin uzerine rastgele komut calistiran bir kabuk degil, durum tutan ve denetlenebilir bir yonetim sistemi olacak.

## Hedeflenen Ilk MVP

Ilk kullanilabilir surumun kapsami:

- Web installer / ilk kurulum sihirbazi
- Admin kullanicisi olusturma
- Dashboard
- Website olusturma/listeleme/silme
- Website detay ekrani
- Domain ve subdomain yonetimi
- Nginx veya OpenLiteSpeed vhost yonetimi icin net secim
- Let's Encrypt SSL alma ve yenileme
- MariaDB veritabani ve kullanici olusturma
- PHP-FPM ile site bazli PHP secimi
- Basit dosya yonetimi
- Tek tik WordPress kurulumu
- Website backup/restore
- Temel firewall ve servis durumu ekrani

## Katki

Proje erken asamadadir. Katki vermek isteyenler once `ROADMAP.md` icindeki panel-first MVP maddelerine odaklanabilir.

Ozellikle su alanlarda katki degerlidir:

- Web installer ve panel UI
- Kurulum mimarisi
- Backend/API tasarimi
- Worker/servis adapterlerinin guvenli ve idempotent hale getirilmesi
- Sistem servisleri entegrasyonu
- Test otomasyonu
