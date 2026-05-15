### ailhost

### hosting yonetimi icin bu projeyi baslattim.

* uzun vadede cyberpanel gibi acik kaynak repo olmasini hedefliyorum.
* yersiz ve milsiz cpanel icin simdilik scriptleri hazirliyorum.
* gelistirme sureci tarafimca bos vakit buldukca finale yaklasacaktir. katkida bulunmak isteyen olursa canima minnet. 

## roadmap
yazacak havali seylerim yok. md dosyasi hazirlamaktan nefret ediyorum.

platform gelistirme sureci sonunda sunlari icermeyi hedefliyor;
Website, domain, ssl, dns, email, ftp, file manager, mysql/phpmyadmin, php manager, wordpress manager, backup/restore, firewall/security, git, docker, kullanici/paket yonetimi, openlitespeed/litespeed.

# website / domain yonetimi

| HEDEF	| DURUM	|
|-------|-------|
| website olusturme | evet |
| website listeleme / silme | hayir |
| domain baglama | evet |
| subdomain / child domain olusturma | hayir |
| child domain listeleme / silme | hayir |
| addon domain / alt domain yonetimi | hayir |
| domain alias / parked domain olusturma | hayir |
| website sahibi secme | hayir |
| website icin paket secme | hayir |
| website basina php surum secme | hayir |
| website yonetim paneli | hayir |
| website dosya yolu / document root yonetimi | hayir |
| website bazli vhost ayari | hayir |
| openlitesped vhost yapilandirmasi | hayir |
| litespeed serveralias / vhost uyumluluk | hayir |


# web server ozellikleri

| HEDEF | DURUM |
|-------|-------|
| openlitespeed destegi | hayir |
| http/3 destegi | hayir |
| lscache / litespeed cache destegi | hayir |
| buildin page caching | hayir |
| redis mass hosting | hayir |
| memcached destegi | hayir |
| web server restart ihtiyacini azaltan redis tabanli hosting yapisi | hayir |
| vhost / sanal host yenetimi | hayir |
| /htaccess uyumlulugu icin add-on modul | hayir |


# ssl / https

| HEDEF | DURUM |
|-------|-------|
| lets encrypt ssl alma | evet |
| website icin auto ssl | evet |
| domain / subdomain ssl | hayir |
| mail server ssl | hayir |
| ssl yenileme / auto renewal | hayir |
| ssl manager | hayir |
| force https | hayir |
| ssl v2 add-on | hayir |
| cloudflare ssl ile calisma | hayir |


# php yonetimi

| HEDEF | DURUM |
|-------|-------|
| coklu php surumu | hayir |
| site basina php surum secme | hayir |
| php manager | hayir |
| php ayar yonetimi | hayir |
| php surum degistirme | hayir |
| openlitespeed / litespeed ile php entegrasyonu | hayir |
| php yapilandirma yonetimi | hayir |


# dosya yonetimi

| HEDEF | DURUM |
|-------|-------|
| web tabanli file manager | hayir |
| dosya yukleme | hayir |
| dosya indirme | hayir |
| dosya silme | hayir |
| dosya duzenleme | hayir |
| kod editoru | hayir |
| dosya goruntuleme | hayir |
| dosya sikistirma | hayir |
| arsiv acma | hayir |
| root file manager add-on | hayir |
| website dosya izinleriyle calisma | hayir |
| web tabanli terminal | hayir |


# ftp yonetimi

| HEDEF | DURUM |
|-------|-------|
| ftp server | hayir |
| ftp hesap olusturma | hayir |
| ftp kullanicilarini yonetme | hayir |
| ftp sifresi belirleme | hayir |
| website dizinine ftp ile erisim | hayir |
| ftp manager | hayir |

 
# veritabani yonetimi

| HEDEF | DURUM |
|-------|-------|
| mysql / mariadb veritabani olusturma | hayir |
| veritabani kullanici yonetimi | hayir |
| mysql manager | hayir |
| phpmyadmin | hayir |
| website bazli veritabani islemleri | hayir |
| wordpress kurulurken otomatik veritabani olusturma | hayir |
| backup icinde database yedekleme | hayir |
| git manager ile database takip / aktarim senaryolari | hayir |


# dns yonetimi

| HEDEF | DURUM |
|-------|-------|
| dns server | hayir |
| powerdns | hayir |
| dns zone olusturma | hayir |
| a kaydi | hayir |
| aaaa kaydi | hayir |
| cname kaydi | hayir yazmaktan yoruldum |
| mx kaydi | h |
| txt kaydi | h |
| ns kaydi | h |
| soa kaydi | h |
| default nameserver yapilandirma | hayir |
| private / child nameserver | h |
| cloudflare dns sync | h |
| dns checker araci | h |
| dnssec | h |


# e-posta yonetimi 

| HEDEF | DURUM |
|-------|-------|
| mail server | h |
| postfix | h |
| dovecot | h |
| mailbox olusturma | h |
| domain bazli email olusturma | h |
| webmail / rainloop | h |
| mail dns kayitlari | h |
| mx kaydi | h |
| spf | h |
| spf | h |
| dkim | h |
| dmarc | h |
| mailserver ssl | h |
| email debugger | h |
| server-wide email checks | h |
| website-level email issue checks | h |
| rspamd manager add-on | h |
| harici email delivery servis | h |


# backup / restore

| HEDEF | DURUM |
|-------|-------|
| tek tik backup | h |
| website backup | h |
| database backup | h |
| backup restore | h |
| local backup | h |
| remote backup destinasyon | |
| sftp backup | |
| ftp backup | |
| google drive / uzak yedekleme senaryolari | |
| zamanlanmis backup |  |
| cloud backup servisi |  |
| backup v2 add-on |  |
| wordpress manager icinde wordpress backup |  |


# wordpress ozellikleri

| HEDEF | DURUM |
|-------|-------|
| tek tik wp kurulum | hayiiiiiiiir |
| lscache ile wp kurulum | |
| wp manager | |
| wp listeleme | |
| wp admin auto-login |  |
| wp siteye direkt gitme  |  |
| wp silme  |  |
| plugin yonetimi  | |
| plugin update  | |
| plugin silme  | |
| tema yonetim  | |
| wp staging  | |
| klon /staging site olusturma | |
| stagingden canli siteye sync | |
| wp backup | |
| wp site scanning | |
| wp optimizasyon | |
| wp manager add-on | |
| wp cli | |


# tek tik uygulama kurulumlari

| HEDEF | DURUM |
|-------|-------|
| wordpress | HAYIR |
| joomla | |
| prestashop | |
| magento | |
| git | |
| drupal | |
| mautic | |
| docker uygulamalari | |
| n8n docker app | |


# docker / uygulama yonetimi

| HEDEF | DURUM |
|-------|-------|
| docker manager | |
| docker container deploy | |
| docker container yonetimi | |
| docker apps manager | |
| n8n app deploy | |
| cli kullanmadan docker app calistirma | |


# git / deployment

| HEDEF | DURUM |
|-------|-------|
| git manager | hayir |
| local repo init | |
| remote repo baglama | |
| github / gitlab benzeri repo baglantisi | |
| pull islemleri | |
| commit islemleri | |
| file change history goruntuleme | |
| webhook ile oto deployment | |
| website dosyalarini git ile takip etme | |
| db / email / child domain dosya yapisi gibi iverikleri repo akisina dahil etme senaryolari | |


# guvenlik ozellikleri

| HEDEF | DURUM |
|-------|-------|
| build in firewall | hayir |
| csf firewall entegrasyonu | |
| firewall managment | |
| modsecurity | |
| web app firewall | |
| ssh hardening | |
| imunify360 entegrasyon | |
| ai security scanner | |
| ai wrordpress scanner | |
| brute force / .htaccess modul destegi | |
| acik basedir protection | |
| website malware / vulnerability | |
| security misconfiguration scan | |
| ebpf security | |


# kullanici / reseller / yetki yonetimi

| HEDEF | DURUM |
|-------|-------|
| admin kullanici | |
| farkli kullanici seviyeleri | |
| website sahip secme | |
| kullanici olusturma | |
| kullanici listeleme | |
| kullanci silme | |
| reseller mantigi | |
| paket bazli limit verme | |
| acl / yetki seviyesi | |
| musteri bazli site yonetimi | |


# paket / limit yonetimi

| HEDEF | DURUM |
|-------|-------|
| hosting paket olusturma | yo hayir |
| paket duzenleme | |
| paket silme | |
| disk limit | |
| bandwidth limiti | |
| website sayisi limiti | |
| email hesap limiti | |
| database limiti | |
| ftp limit | |
| domain / child domain limitleri | |
| kullaniciya paket atama | |


# sunucu yonetimi / izleme

| HEDEF | DURUM |
|-------|-------|
| dashboard | yakinda insallah |
| sunucu kaynaklarini gorme | hayir |
| ram / cpu / disk kullanimini izleme | |
| servis durumlarini gorme | |
| servis restart islemleri | |
| log goruntuleme | |
| ailhost guncelleme | |
| web tabanli terminal | |
| server status | |
| logs managment | |
| troubleshooting araclari | |
| admin sifresi resetleme | |
| ayar dosyalarinin yerini takip etme | |


# gelistirici / api ozellikleri

| HEDEF | DURUM |
|-------|-------|
| ailhost api | |
| api docs | |
| github kaynak kod | e zaten |
| python tabanli gelistirme | yakinda |
| panel uzerinden git yonetimi | |
| webhook deployment | |
| web terminal | |


# harici / ekosistem araclari

| HEDEF | DURUM |
|-------|-------|
| load tester | yoruldum |
| email tester | bir saattir |
| dns checker | md hazirliyorum |
| wp scanner | bunu yazmistim sanki |
| ailVPN | tadindan yenmez |
| email delivery servis | |
| dns hosting | |
| cloud backup | |
| cloud vps | |
| next-gen cloud servers | |
| managed wordpress | |
| openlitespeed any panel | |
| ailhost nginx for plesk | |


# add-on

| HEDEF | DURUM |
|-------|-------|
| .htaccess modul | |
| add-ons bundle | |
| ssl v2 | |
| wp manager pro | |
| backup v2 | |
| email debugger | |
| root file manager | |
| rspamd manager | |
| litespeed lisans | |
| cloud backup | |
| email delivery servisi | |
| dns hosting servisi | |
| ai scannerlar falan iste | |

sunu yapay zekaya yazdirmak vardi. avanak gibi baslamis bulunduk yazmaya. 


