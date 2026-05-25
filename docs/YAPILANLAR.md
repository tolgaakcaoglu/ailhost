# Yapılanlar

Bu belge, mevcut kod tabanında şimdiye kadar tamamlanan geliştirme ve görev başlıklarını özetler.

## Temel Altyapı

- Proje iskeleti ve standartlar
- Web tabanlı ilk kurulum akışı
- Backend worker, job queue ve audit log temeli
- Website, domain, DNS ve SSL yönetimi
- PHP-FPM ve MariaDB yönetimi
- WordPress kurulum ve yönetim temeli
- Backup ve restore sistemi
- File manager, FTP ve temel güvenlik

## UI ve İş Akışı

- Figma kurulum sihirbazı tasarım uyarlaması
  Karşılama, sistem gereksinimleri, ağ ayarları ve yönetici oluşturma ekranları yeni tasarıma taşındı.
- UI ve navigasyon refactor
- Workflow ayrımı ve UI polish
- Form akışı ve tehlikeli işlemler alanı
- Runtime ve dosya modülü UI iyileştirmeleri
- Proxy routing management
- Aktivite ve iş gözlemlenebilirliği
- Merkezi log ekranı
- İşlem kuyruğu ekranı
- Log kaynak birleştirme
- Üst bar job göstergesi
- Site aktivite akışı
- İş çakışma korumaları
- İş kilidi UX durumu

## Deploy, Runtime ve Proxy

- Deploy worker execution
- Runtime state model
- Process supervisor adapter
- Proxy apply ve Nginx validation
- Deploy profil ve kuyruk tabanlı yayınlama
- Git deploy webhook
- Docker servis temeli ve container gözlemi

## Kullanıcı, Yetki ve Güvenlik

- Role ve permission modeli
- Kullanıcı yönetimi ekranı
- Oturum ve hesap güvenliği
- Şifre sıfırlama ekranı (UI/istek alma)
  Reset token üretimi ve e-posta ile gerçek şifre yenileme akışı henüz bağlı değil.
- Site write authorization guard
- Login rate limit hardening
- Controller write guard standardization
- Webhook imza doğrulaması, replay koruması ve secret saklama modeli

## Installer ve Sistem Operasyonları

- Installer system checks
- Package install jobs
- Service health ve repair actions
- Installer packaging
- Release readiness hazırlığı

## Website Ürün Kalitesi

- Website create wizard
- SSL manager
- DNS validation ve zone apply
- Backup integrity checks
- Restore dry-run safety policy
- Audit retention ve export

## Dosya, FTP, Mail ve WordPress

- File upload/download
- File operations improvements
- FTP account hardening
- Mail domain records
- Mail queue delivery debug
- Webmail integration plan
- WP-CLI adapter hardening
- WordPress staging
- WordPress security ve optimization
- File manager upload compatibility
- Configurable upload limit

## Test ve State Güvenliği

- Test harness
- JSON storage atomic lock standard
- Adapter JSON atomic lock migration
- Site write guard consolidation
- Remaining JSON write migration

## Rehberli Panel UX

- Rehberli hosting paneli UI/UX standardı
- Kritik işlem geri bildirim standardı
- Operation status strip standardization
- Cross module guided status UX
- Dashboard guided flow center
- Sites list guided selection center
- Site module flow priority strip
- Role aware site flow guidance
- Role aware quick actions
- Form/drawer permission feedback standard
