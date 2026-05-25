# Faz 1 Yapılacaklar

Bu belge, eski step belgeleri kaldırıldıktan sonra kalan gerçek geliştirme başlıklarını Faz 1 olarak sabitler.

## Öncelik 1

- Figma tasarım dilini kalan ekranlara yay
  Kurulum sihirbazı yeni tasarıma taşındı; dashboard, site yönetimi ve operasyon modülleri paylaşılacak yeni Figma ekranlarına göre yeniden ele alınmalı.
- Şifre sıfırlama gerçek akışını tamamla
  UI hazır; reset token üretimi, token doğrulama, yeni şifre belirleme ekranı ve e-posta gönderimi tamamlanmalı.
- Mail ve ileri DNS kapsamını kapat
  Mevcut mail/DNS işi kısmen tamamlandı; kalan ekran, worker ve doğrulama davranışları netleştirilmeli.
- Git, Docker ve tek tık uygulamalar ana kapsamını tamamla
  Git deploy ve Docker temeli var; compose app deploy ve uygulama kataloğu hâlâ ürün seviyesine taşınmadı.
- Çok kullanıcılı paket modeli
  Kullanıcı rolleri var; hosting package, kota, kullanım takibi ve müşteri bazlı sınırlar tamamlanmalı.
- Form validasyon UX standardını tamamla
  Bazı formlar iyileştirildi; tüm kritik formlarda aynı client-side ön doğrulama, limit metni ve alan bazlı hata standardı uygulanmalı.

## Öncelik 2

- Remote backup destinations
  Lokal backup var; uzak hedef, credential güvenliği, doğrulama ve restore akışı ürünleştirilmeli.
- Compose app deploy
  Docker servis gözlemi var; compose tabanlı uygulama deploy akışı eklenmeli.
- One click app catalog
  Tek tık uygulama şablonları, kurulum parametreleri ve job takibi hazırlanmalı.
- Hosting packages
  Paket tanımı, limitler ve kullanıcı/site atama modeli oluşturulmalı.
- Quotas ve usage tracking
  Disk, trafik, site, mailbox, database ve backup kullanım takibi eklenmeli.
- User isolation
  Site/kullanıcı izolasyonu dosya, process ve servis düzeyinde sertleştirilmeli.
- Suspend / unsuspend
  Site ve kullanıcı askıya alma, geri açma ve etkilediği servisler standartlaştırılmalı.

## Öncelik 3

- CI ve release pipeline
  Test harness var; otomatik lint, test, paketleme ve release kontrol akışı kurulmalı.
- Production hardening
  Root gerektiren scriptler, dosya izinleri, servis adapterleri ve secret maskeleme üretim senaryoları için tekrar denetlenmeli.
- Alpha release notlarını yeniden üret
  Eski release belgeleri kaldırıldı; gerçek release anında güncel sınırlamalar, güvenlik notları ve checklist tek kaynaktan yeniden oluşturulmalı.
- Sistem adapterlerini gerçek dağıtımlarda doğrula
  Nginx, PHP-FPM, MariaDB, DNS, mail, firewall, Docker ve supervisor adapterleri farklı host koşullarında test edilmeli.

## Faz 1 Kapanış Kriterleri

- Kullanıcı terminale ihtiyaç duymadan temel hosting operasyonlarını panelden tamamlar.
- Panelde görünen kritik aksiyonların worker/adapter karşılığı gerçek ve tekrarlanabilir çalışır.
- Paket, kota, izolasyon ve askıya alma modeli en az temel seviyede aktif olur.
- Release öncesi test, güvenlik ve bilinen sınırlama belgeleri güncel üretilir.
