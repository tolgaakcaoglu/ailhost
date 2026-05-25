# ailhost Roadmap ve Proje Plani

Bu dokuman ailhost icin teknik yol haritasini, gelistirme fazlarini, modul kapsamlarini ve teslim kriterlerini tanimlar.

Detayli uygulama adimlari `/docs` altindaki `STEP-XXX` belgeleriyle yonetilir. Kod gelistirmeye baslamadan once ilgili step belgesi hazir olmalidir.

## 1. Urun Vizyonu

ailhost; tek veya coklu Linux sunucuda hosting operasyonlarini yonetmek icin acik kaynak, sade, denetlenebilir ve genisletilebilir bir kontrol paneli olacaktir.

Hedef kullanici profilleri:

- Kendi VPS'inde birden fazla site barindiran gelistiriciler
- Kucuk ajanslar ve freelancer'lar
- WordPress agirlikli hosting yapan ekipler
- cPanel/CyberPanel alternatifi isteyen teknik kullanicilar
- Daha sonra ticari hosting operasyonlari

Basari kriteri:

- Temiz kurulu bir AlmaLinux/Rocky Linux sunucuda panel kurulabilmeli ve ilk ayarlar web arayuzunden tamamlanabilmeli.
- Admin panelinden site, domain, SSL, PHP, veritabani, backup ve WordPress islemleri yapilabilmeli.
- Islemler tekrar calistirildiginda sistem bozulmamali.
- Her operasyon loglanmali ve hata durumunda kullaniciya net sebep gosterilmeli.
- Son kullanici site yonetimi icin terminale veya manuel script calistirmaya ihtiyac duymamali.
- Her gelistirme parcasi `/docs/STEP-XXX-*.md` belgesindeki kapsam ve teslim kriterlerine bagli kalmali.

## 2. Kapsam Karari

### Ilk desteklenecek sistemler

- AlmaLinux 9
- Rocky Linux 9
- RHEL uyumlu paket yonetimi: `dnf`
- systemd
- firewalld

### Ilk web server karari

Iki yol var:

- Kisa vadede mevcut kod nedeniyle Nginx ile MVP.
- Uzun vadede hedef nedeniyle OpenLiteSpeed/LiteSpeed destegi.

Onerilen karar:

1. MVP icin Nginx + PHP-FPM ile stabil cekirdek kur.
2. Vhost soyutlamasini bastan web-server bagimsiz tasarla.
3. OpenLiteSpeed destegini Faz 4'te ikinci provider olarak ekle.

### Ilk panel mimarisi

Onerilen yapi:

- Backend API: php.
- Agent/worker: root yetkili sistem islemlerini kontrollu calistiran servis.
- Frontend: html, css, js.
- Veritabani: MariaDB.
- Queue: baslangicta MariaDB tabanli job queue.

En kritik mimari prensip:

Panel direkt shell komutlari basan bir UI olmayacak. UI -> API -> job/worker -> servis adapterleri akisi olacak.

### Dis bagimlilik karari

ailhost repo ve runtime olarak self-hosted kalmalidir.

- Zorunlu kapali kaynak SaaS bagimliligi olmayacak.
- Panel calismak icin harici lisans sunucusu, merkezi API, uzaktan kontrol servisi veya vendor cloud gerektirmeyecek.
- Nginx/OpenLiteSpeed, MariaDB, PHP, BIND/PowerDNS, Postfix/Dovecot gibi bilesenler sistem paketleri veya acik kaynak servisler olarak kurulacak.
- Let's Encrypt, Cloudflare, S3 gibi dis servisler sadece opsiyonel entegrasyon olacak. Panelin temel calismasi bunlara bagli olmayacak.
- Frontend icin zorunlu build zinciri hedeflenmeyecek; ilk surum plain HTML/CSS/JS ile gelistirilebilir. Gerekirse sonradan derleme gerektiren UI yapisina gecis ayri karar olarak alinacak.

### Dokumantasyon ve step karari

Gelistirme sureci step belgeleriyle ilerleyecek.

- Her buyuk is parcasi icin `/docs/STEP-XXX-*.md` dosyasi bulunacak.
- Step belgesi kapsam, kapsam disi alanlar, mimari kararlar, yapilacaklar, dosya etkisi, veri modeli, guvenlik notlari, test ve tamamlanma kriterlerini icerecek.
- Aktif gelistirme sadece ilgili step kapsaminda yapilacak.
- Kapsam degisecekse once step belgesi guncellenecek.
- Step tamamlandiginda durum `Completed` yapilacak ve sonraki step belgesi hazir hale getirilecek.
- Bu kural repo geneli moduler ve uzun vadeli bakimi kolay bir kod tabani icin zorunludur.

## 3. Ana Fazlar

### Faz 0 - Panel-First Temel Karar ve Proje Iskeleti

Amac: Projeyi script koleksiyonundan web panel urunune cevirecek temel iskeleti kurmak.

Isler:

- README ve roadmap dokumantasyonunu netlestir.
- Ana urun kararini yaz: web panel birincil, CLI ikincil.
- Minimal PHP backend iskeleti olustur.
- Plain HTML/CSS/JS panel shell olustur.
- MariaDB schema taslagi hazirla.
- Auth, users, settings, jobs, audit logs tablolarini tasarla.
- `/install` ilk kurulum sihirbazi akisini tasarla.
- Panel icin layout: login, install, dashboard, sites, jobs, settings.
- Root yetkili islemler icin worker/adapter sinirini tanimla.
- Mevcut Bash scriptlerini son kullanici komutu degil, adapter prototipi olarak konumlandir.
- Sistem ayarlari icin `/etc/ailhost`, state icin `/var/lib/ailhost`, log icin `/var/log/ailhost` standardini belirle.
- Harici SaaS bagimliligi olmayacagini dokumante et.

Teslim kriteri:

- Panel acildiginda install ekranina yonlenmeli.
- Admin kullanicisi arayuzden olusturulabilmeli.
- Sistem ayarlari arayuzden kaydedilebilmeli.
- Dashboard bos durumda bile calismali.
- Job ve audit log modeli hazir olmali.

### Faz 1 - Web Installer MVP

Amac: Terminale dayanmadan ilk kurulum adimlarini web arayuzunden tamamlatmak.

Installer ekranlari:

- Sistem kontrolu
- Admin kullanicisi
- Sunucu IP ve hostname
- Nameserver ayarlari
- Web server secimi
- DNS server secimi
- MariaDB ayarlari
- Firewall ayarlari
- Ilk site olusturma

Teknik isler:

- Bootstrap sadece panel installer servisini ayaga kaldirmali.
- Installer her adimi job olarak calistirmali.
- Eksik paketler arayuzden gosterilmeli ve kurulum job'i baslatilabilmeli.
- Paket kurulumu, servis aktiflestirme ve firewall islemleri worker tarafindan yapilmali.
- Her adim geri donulebilir veya tekrar denenebilir olmali.
- Kurulum bitince `/install` kilitlenmeli.
- Kurulum durumu database ve config dosyasina yazilmali.

Teslim kriteri:

- Temiz sunucuda minimal bootstrap sonrasi tarayicidan kurulum tamamlanabilmeli.
- Admin paneline girilebilmeli.
- Ilk site arayuzden olusturulabilmeli.
- Kurulum loglari panelden gorulebilmeli.

### Faz 2 - Backend, Worker ve Job Sistemi

Amac: Panelin kullanacagi API ve guvenli sistem islem katmanini tamamlamak.

Moduller:

- Auth
- Users
- Sites
- Domains
- SSL certificates
- DNS zones
- Jobs
- Audit logs
- System services

API ozellikleri:

- Admin login
- JWT veya session tabanli auth
- Role based access control temeli
- Job queue
- Job status polling
- Audit log
- Structured error response

Worker ozellikleri:

- Root gerektiren islemleri whitelist uzerinden calistirma
- Her job icin stdout/stderr loglama
- Timeout
- Lock mekanizmasi
- Rollback hook'lari

Teslim kriteri:

- Panel uzerinden site olusturma job'i baslatilabilmeli.
- Job durumu panel/API tarafindan izlenebilmeli.
- Basarili ve basarisiz islemler audit log'a yazilmali.

### Faz 3 - Website Yonetimi MVP

Amac: Temel hosting islemlerini web panelinden yapilabilir hale getirmek.

Ekranlar:

- Login
- Dashboard
- Sites listesi
- Site detay
- Website olusturma sihirbazi
- Domain/subdomain yonetimi
- SSL yonetimi
- DNS zone kayitlari
- Servis durumu
- Job gecmisi
- Audit log

UI prensipleri:

- Operasyonel, sade, hizli taranabilir arayuz.
- Her destructive aksiyon onay ister.
- Uzun isler async job olarak gosterilir.
- Hata mesajlari ham terminal ciktisi degil, anlasilir sebep + detay seklinde verilir.

Teslim kriteri:

- Admin panelinden site olusturulup SSL alinabilmeli.
- DNS kayitlari listelenip duzenlenebilmeli.
- Servis durumlari gorulebilmeli.
- Kullanici normal website yonetimi icin terminale inmemeli.

### Faz 4 - PHP ve Veritabani

Amac: Dinamik site ve WordPress icin temel hosting yetenekleri.

PHP hedefleri:

- Coklu PHP surumu kurma
- Site bazli PHP-FPM pool
- Site bazli PHP version secimi
- PHP config limitleri: memory_limit, upload_max_filesize, max_execution_time
- PHP-FPM reload/restart yonetimi

Veritabani hedefleri:

- MariaDB kurulumu
- Database olusturma
- Database kullanicisi olusturma
- Kullanici sifresi resetleme
- Site ile database eslestirme
- phpMyAdmin kurulumu veya harici admin linki

Teslim kriteri:

- Bir site icin PHP aktif edilebilmeli.
- Bir site icin DB ve DB user olusturulabilmeli.
- PHP info veya test index ile calisma dogrulanabilmeli.

### Faz 5 - WordPress Manager

Amac: WordPress agirlikli hosting operasyonlarini kolaylastirmak.

Ozellikler:

- Tek tik WordPress kurulumu
- Otomatik database olusturma
- WP-CLI entegrasyonu
- Admin kullanicisi olusturma
- Plugin listeleme/kurma/silme/update
- Theme listeleme/kurma/aktif etme
- WordPress versiyon update
- Maintenance mode
- Cache entegrasyonu temeli
- WordPress backup
- Staging site olusturma
- Staging -> live sync

Teslim kriteri:

- Panelden yeni WordPress sitesi kurulabilmeli.
- Plugin/theme temel islemleri yapilabilmeli.
- Staging icin ilk calisan akış mevcut olmali.

### Faz 6 - Backup ve Restore

Amac: Site, dosya ve veritabani yedeklerini guvenilir yonetmek.

Ozellikler:

- Website dosya backup
- Database backup
- Full site backup
- Restore
- Local backup retention
- Scheduled backup
- SFTP/FTP remote backup
- Google Drive veya S3 uyumlu hedef
- Backup job loglari
- Restore oncesi otomatik safety backup

Teslim kriteri:

- Panelden full backup alinip geri yuklenebilmeli.
- Planli backup calismali.
- Basarisiz backup kullaniciya net hata vermeli.

### Faz 7 - File Manager ve FTP

Amac: Site dosyalarini panelden veya FTP ile yonetmek.

File manager:

- Dosya listeleme
- Upload/download
- Delete/rename/move/copy
- Kod editoru
- Arsiv olusturma/acma
- Permission/ownership duzenleme
- Website root disina cikmayi engelleyen path guard

FTP:

- FTP server kurulumu
- Site bazli FTP hesabi
- Kullanici sifresi resetleme
- Chroot/site dizinine kisitlama

Teslim kriteri:

- Panelden site dosyalari guvenli sekilde yonetilebilmeli.
- FTP kullanicisi sadece atanmis site dizinine erisebilmeli.

### Faz 8 - DNS, E-posta ve Guvenlik

Amac: Hosting panelini daha tamamlayici hale getirmek.

DNS:

- A, AAAA, CNAME, MX, TXT, NS, SOA kayitlari
- Zone import/export
- DNSSEC
- Cloudflare sync
- Private nameserver

E-posta:

- Postfix
- Dovecot
- Mailbox olusturma
- Webmail
- DKIM/SPF/DMARC
- Mail SSL
- Rspamd entegrasyonu
- Mail queue ve delivery debug

Guvenlik:

- firewalld veya CSF entegrasyonu
- Fail2ban
- SSH hardening kontrolleri
- Malware scan entegrasyonu
- Port/service exposure raporu
- Security checklist

Teslim kriteri:

- Domain icin temel mail sistemi kurulabilmeli.
- DNS kayitlari mail deliverability icin otomatik olusturulabilmeli.
- Panel guvenlik durumunu raporlayabilmeli.

### Faz 9 - Git, Docker ve Uygulama Kurulumlari

Amac: Modern deployment ve uygulama yonetimi.

Git:

- Local repo init
- Remote repo baglama
- Pull/deploy
- Webhook deployment
- Branch secimi
- Deploy loglari
- Rollback

Docker:

- Docker kurulum ve servis kontrolu
- Container listeleme
- Image pull
- Compose app deploy
- Port/domain mapping
- Log goruntuleme
- n8n gibi hazir app sablonlari

Tek tik uygulamalar:

- WordPress
- Joomla
- Drupal
- PrestaShop
- Magento
- Mautic
- n8n

Teslim kriteri:

- Git webhook ile site deploy edilebilmeli.
- En az bir Docker app domain arkasinda calistirilabilmeli.

### Faz 10 - Multi-user, Paket ve Ticari Hosting Temeli

Amac: Tek admin panelinden cok kullanicili hosting yonetimine gecmek.

Ozellikler:

- Kullanici hesaplari
- Roller: admin, reseller, user
- Hosting paketleri
- Disk kotasi
- Domain/site limiti
- Database limiti
- Mailbox limiti
- Bandwidth raporlama
- Kullanici izolasyonu
- Suspend/unsuspend
- Paket degistirme

Teslim kriteri:

- Admin kullanici paket tanimlayabilmeli.
- Normal kullanici sadece kendi sitelerini yonetebilmeli.
- Kota/limitler uygulanabilmeli.

## 4. Modul Bazli Backlog

### Website / Domain

- Website create/list/delete
- Domain add/remove
- Subdomain create/list/delete
- Alias/parked domain
- Addon domain
- Document root degistirme
- Site owner secimi
- Package secimi
- Per-site vhost override
- Access/error log goruntuleme

### Web Server

- Nginx provider
- OpenLiteSpeed provider
- Vhost template sistemi
- Zero/low reload stratejisi
- HTTP/2 ve HTTP/3
- Page caching
- Redis cache
- Memcached
- LiteSpeed cache
- `.htaccess` uyumluluk stratejisi

### SSL

- Let's Encrypt issue
- Auto SSL
- Renewal scheduler
- Wildcard SSL
- Mail SSL
- Force HTTPS
- SSL manager
- Cloudflare SSL uyumlulugu
- Sertifika expiry bildirimi

### PHP

- Multi PHP
- Site bazli version
- PHP-FPM pool
- PHP ini editor
- Extension manager
- Composer destegi
- Per-site resource limit

### Database

- MariaDB install/manage
- Database/user CRUD
- phpMyAdmin
- Remote DB access policy
- Backup/restore
- DB user permission editor

### DNS

- BIND veya PowerDNS secimi
- Zone CRUD
- Record CRUD
- DNSSEC
- Cloudflare sync
- DNS checker
- Default nameserver config

### E-posta

- Postfix/Dovecot
- Mailbox CRUD
- Alias/forwarder
- Webmail
- DKIM/SPF/DMARC
- Rspamd
- Mail queue
- Deliverability debugger

### Backup

- File backup
- DB backup
- Full account backup
- Restore
- Scheduled backup
- Remote destinations
- Retention policies
- Backup encryption

### WordPress

- WP install
- WP list
- Admin auto-login
- Plugin/theme manager
- Updates
- Staging
- Clone
- Backup
- Security scan
- Optimization
- WP-CLI

### Security

- Firewall
- Fail2ban
- Service hardening
- Audit log
- Permission scanner
- Malware scan
- Vulnerability notices
- 2FA

## 5. Teknik Riskler

### Root yetkisi

Risk: Panel compromise olursa sunucu tamamen ele gecirilebilir.

Onlem:

- Root islemlerini dar whitelist ile worker'a tasima.
- UI/API kullanicisina direkt shell calistirtmama.
- Audit log ve job lock.

### Idempotency eksigi

Risk: Ayni islem ikinci kez calistiginda config bozulabilir.

Onlem:

- Her resource icin state tut.
- Apply oncesi diff/dry-run.
- Template tabanli config uretimi.

### Servis config carpisma riski

Risk: Nginx/BIND/Postfix configleri elle degistirilirse panel beklenmeyen davranabilir.

Onlem:

- Panel-managed bloklar kullan.
- Config validation zorunlu olsun.
- Manual config algilama ve uyarilar.

### Backup/restore veri kaybi

Risk: Restore hatasi canli veriyi bozabilir.

Onlem:

- Restore oncesi otomatik safety backup.
- Restore dry-run.
- Checksum ve boyut dogrulama.

### Cok genis kapsam

Risk: Her modulu ayni anda yapmaya calismak projeyi bitiremez hale getirir.

Onlem:

- Once site + SSL + DB + WordPress + backup MVP.
- E-posta, Docker, reseller gibi alanlari sonraki fazlara birak.

## 6. Onerilen Ilk 30 Gunluk Plan

### Hafta 1

- README ve ROADMAP netlestir.
- PHP backend ve plain HTML/CSS/JS panel iskeletini olustur.
- MariaDB schema taslagini yaz: users, settings, sites, domains, jobs, audit_logs.
- Install wizard ekran akisini ciz.
- Login, install ve dashboard sayfalarinin ilk statik hallerini hazirla.
- Config/state/log path standardini belirle.

### Hafta 2

- Admin kullanicisi olusturma akisini arayuzden calistir.
- Settings ekranindan server IP, hostname, nameserver ve web server tercihini kaydet.
- Job queue modelini calistir.
- Worker icin ilk servis adapter arayuzunu yaz.
- Mevcut nginx/site/dns script mantigini worker adapterlerine bol.

### Hafta 3

- Website create wizard ekranini yap.
- Site create job'ini panelden baslat.
- Nginx config validation ve rollback ekle.
- DNS zone create job'ini panelden baslat.
- Job detay ve job log ekranlarini yap.

### Hafta 4

- SSL issue job'ini panelden baslat.
- Certbot renewal durumunu panelde goster.
- Sites listesi ve site detay ekranlarini tamamla.
- Servis durumlari ekranini ekle.
- Ilk web-installer MVP dokumantasyonunu yaz.

## 7. MVP Teslim Tanimi

MVP tamam sayilmasi icin:

- Temiz sunucuda minimal bootstrap ile web installer acilabilmeli.
- Kurulum adimlari web arayuzunden tamamlanabilmeli.
- Admin paneline girilebilmeli.
- Site panelden olusturulabilmeli.
- Domain/subdomain panelden baglanabilmeli.
- SSL panelden alinabilmeli ve yenileme durumu gorulebilmelidir.
- PHP ve MariaDB ile WordPress panelden kurulabilmelidir.
- Site backup panelden alinip restore edilebilmelidir.
- Her islem job olarak izlenebilmelidir.
- Hatalar panelde anlasilir sekilde gorunmelidir.
- Dokumantasyonda kurulum, kaldirma, troubleshooting ve katkida bulunma rehberi bulunmalidir.
- Normal kullanim icin terminal veya manuel script calistirma gerekmemelidir.

## 8. Surumlama Plani

### v0.1 - Web Installer Foundation

- PHP backend iskeleti
- Plain HTML/CSS/JS panel shell
- Install wizard
- Admin kullanicisi
- Config/state/log standardi
- Job ve audit log modeli

### v0.2 - Website Panel Foundation

- API
- Auth
- Jobs
- Audit logs
- Site lifecycle ekranlari
- Worker adapter temeli

### v0.3 - Site, Domain, DNS, SSL MVP

- Login
- Dashboard
- Site/domain/SSL/DNS ekranlari
- Job izleme
- Panelden ilk site yayina alma

### v0.4 - PHP ve Database

- PHP-FPM
- Multi PHP temeli
- MariaDB CRUD
- phpMyAdmin

### v0.5 - WordPress MVP

- Tek tik WP kurulum
- WP-CLI
- Plugin/theme temel yonetimi

### v0.6 - Backup/Restore

- Full site backup
- DB backup
- Restore
- Scheduled backup

### v0.7 - File/FTP/Security

- File manager
- FTP
- Firewall/Fail2ban
- Security dashboard

### v0.8 - Mail/DNS Advanced

- Mail server
- DKIM/SPF/DMARC
- DNSSEC
- Cloudflare sync

### v0.9 - Git/Docker/Apps

- Git deploy
- Docker app manager
- One-click app templates

### v1.0 - Production Ready

- Multi-user
- Packages/quotas
- Upgrade path
- Backup safety
- Hardening
- Stabilization

## 9. Hemen Yapilacaklar

Oncelik sirasi:

1. `public/` veya `panel/` altinda plain HTML/CSS/JS panel iskeleti olustur.
2. PHP backend giris noktasini ve basit router yapisini ekle.
3. MariaDB schema dosyasini hazirla.
4. Install wizard ekranlarini ekle.
5. Admin kullanicisi olusturma akisini yaz.
6. Job queue tablolarini ve job runner mantigini kur.
7. Mevcut nginx/site/dns islemlerini worker adapterlerine tasimaya basla.
8. Website create ekranini ve job'unu ekle.
9. Job log/audit log ekranlarini ekle.
10. Scriptlerdeki mevcut hatalari adaptere tasirken duzelt: `commend`, domain validation, hardcoded IP, template kullanimi.
