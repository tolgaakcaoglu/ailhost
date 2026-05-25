# ailhost UI ve Geliştirme Notları

## UI Kararları

- Arayüz light tema olmalıdır.
- Varsayılan panel dili sade ve kontrollü kalmalıdır; ancak kullanıcı Figma tasarımı verdiğinde renk, shadow, gradient, border, spacing ve ikon tercihleri Figma kaynağına birebir uyarlanmalıdır.
- Figma kaynağı olmayan ekranlarda gereksiz UI elementi, dekoratif şekil, gradient, gölge gösterisi ve açıklama metninden kaçınılmalıdır.
- Arayüz dili Türkçe olmalıdır.
- Türkçe karakter kullanılabilir: ş, ı, ğ, ü, ö, ç.
- Metinler kısa, operasyonel ve doğrudan olmalıdır. Gereksiz AI açıklamaları veya geliştirme notları arayüzde gösterilmemelidir.
- Hafif radius kullanılabilir.
- Butonlar stadium border olmalıdır: yüksek radius, pill formu.
- Status farkları renk yerine metin, yazı kalınlığı, border veya gri kontrast ile anlatılmalıdır.
- Formlar sade, düzenli ve hızlı taranabilir olmalıdır.
- Tasarım dili modern yönetim paneli düzeninde olmalıdır: solda sabit navigasyon, üstte ince araç çubuğu, içerikte modüler kartlar.
- Sol menü yapısı sade olmalıdır: grup başlıkları küçük harf aralıklı (tracking) metin, altında net tek satır menü öğeleri.
- İçerik alanında hiyerarşi şu sırayla korunmalıdır: sayfa başlığı -> kısa açıklama -> metrik/kart alanı -> detay tabloları/formlar.
- Kart sistemi sabit kullanılmalıdır: ince border, düşük radius, dengeli iç boşluk; kart içinde gereksiz dekoratif eleman bulunmamalıdır.
- Grid düzeni sıkı ve öngörülebilir olmalıdır. Büyük boşluklar ve dengesiz hizalamalar engellenmelidir.
- Form alanları yatayda aynı genişlik ritmini izlemelidir; label, input ve aksiyon mesafeleri platform genelinde tutarlı olmalıdır.
- Butonlar pill (stadium) formda kalmalıdır; birincil/ikincil farkı sadece dolgu ve border ile verilmelidir.
- Login ekranı iki kolonlu kurgu mantığını takip etmelidir: solda form odağı, sağda marka/bağlam alanı. Ancak tema light kalmalı ve renkli vurgu kullanılmamalıdır.
- Kurulum sihirbazı için güncel kaynak `docs/ailpanel-setup-ui-screens` dizinindeki görsellerdir. Karşılama, sistem gereksinimleri, ağ ayarları ve yönetici oluşturma ekranları bu görsellerdeki ailpanel kimliği, icon.png kullanımı, Tabler benzeri ikon dili, yeşil birincil aksiyon, yumuşak gölge ve geniş merkez hizalı form ritmiyle korunmalıdır.
- Panel modülleri tek uzun sayfada biriktirilmemelidir. Website operasyonları domain, runtime, dosya, mail, DNS, backup ve güvenlik gibi ayrı ekranlara bölünmelidir.
- Sol navigasyon global alanları göstermeli; seçili site içinde ayrıca modül navigasyonu bulunmalıdır.
- Form gönderimlerinden sonra kullanıcı yaptığı işlemin ait olduğu modül ekranına dönmelidir.
- `/sites` ekranı yalnızca website listesi ve oluşturma işlemi için kullanılmalıdır; site operasyonları bu listeden seçilen site üzerinden açılmalıdır.
- DNS, Mail ve Backup gibi modüllerde form alanları varsayılan kapalı olmalı; kullanıcı "ekle/al" aksiyonu ile formu açmalıdır.
- DNS, Mail, Backup, Domain ve Proxy gibi kayıt ekleme akışları sayfa içi kalabalık yaratmamak için sağ panel drawer ile açılmalıdır.
- Tehlikeli işlemler (silme, restore, askıya alma) ayrı bir "Tehlikeli İşlemler" alanında toplanmalı ve onay adımı içermelidir.
- Silme/restore gibi riskli işlemlerde tarayıcı `confirm()` yerine platform içi onay modalı kullanılmalıdır.
- Runtime ekranında durum kartları ve log alanı bulunmalı; ileri işlemler açılır panellerde gösterilmelidir.
- Dosya ekranı dizin gezinimi, açık dosya düzenleme ve hızlı aksiyonları tek bakışta gösterecek şekilde listelenmelidir.
- Ana ürün modeli "Rehberli Panel"dir: kullanıcı her kritik ekranda hangi site üzerinde olduğunu, güvenli işlem sırasını, işlem etkisini, çakışmaları ve sonucu nereden takip edeceğini görebilmelidir.
- cPanel görsel olarak kopyalanmaz; sadece bilgi açıklığı, işlem barizliği ve kullanıcıya güvenli sırayı gösterme yaklaşımı referans alınır.
- Deploy, backup/restore, DNS, dosyalar, runtime ve tehlikeli işlemler gibi kritik modüllerde kısa "İşlem Rehberi" bulunmalıdır.
- Risk, etki, geri alınabilirlik ve çakışma bilgisi ilgili butona veya forma yakın gösterilmelidir; kullanıcı aksiyona basmadan önce neyi değiştirdiğini anlamalıdır.
- Kritik işlemlerde buton sadece pasif bırakılmamalıdır; kapalıysa sebebi ve kullanıcının bakacağı yer açıkça yazılmalıdır.

## Ürün Kararları

- ailhost web panel öncelikli bir hosting kontrol panelidir.
- Kullanıcıya açık hosting işlemleri terminal scriptleriyle değil web arayüzünden yapılmalıdır.
- Scriptler iç worker/servis adapter prototipi olabilir; ana kullanıcı deneyimi olamaz.
- Repo self-hosted ve açık kaynak kalmalıdır. Zorunlu SaaS veya vendor cloud bağımlılığı eklenmemelidir.

## Dokümantasyon Disiplini

- Büyük bir geliştirme adımına başlamadan önce ilgili `/docs/STEP-XXX-*.md` dosyası oluşturulmalı veya güncellenmelidir.
- Step belgeleri uygulama durumuyla uyumlu tutulmalıdır.
- Kapsam genişletilecekse önce ilgili step belgesi güncellenmelidir.
