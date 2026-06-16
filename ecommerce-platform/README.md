# 🚀 Multi-Vendor E-Commerce Platform Architecture Documentation

Bu doküman, yüksek trafikli, çok satıcılı (Trendyol benzeri) bir e-ticaret platformunun sıfırdan, en modern mühendislik standartlarıyla nasıl inşa edildiğini anlatan kapsamlı bir mimari kılavuzdur.

---

## 1. Proje Genel Bakış & Mimari Felsefe

### Amacımız
Milyonlarca ürünün listelenebileceği, binlerce satıcının kendi ürünlerini yönetip satış yapabileceği (Multi-Vendor), hata toleransı yüksek ve saniyelik stok rekabetlerini (race-conditions) kaldırabilecek devasa bir e-ticaret ekosistemi kurmak.

### Teknoloji Yığını (Tech Stack)
- **Backend Core**: Laravel 11 (PHP 8.2+)
- **Veritabanı**: MySQL 8.0+
- **Frontend / Dashboard**: TALL Stack (Tailwind CSS, Alpine.js, Laravel, Livewire 3)
- **Ödeme & Kargo**: Iyzico (3D Secure), Webhook tabanlı dinamik kargo takibi.

### Tasarım Kalıpları ve Sağladığı Esneklik
Bu proje, kodun zamanla çürümesini (code-rot) engelleyen kusursuz bir mimariyle örülmüştür:
* **SOLID Prensipleri**: Controller'lar asla doğrudan veritabanına bağlanmaz veya iş mantığı içermez. Her sınıfın tek bir sorumluluğu vardır.
* **Repository / Service Pattern**: Veri erişim katmanı (Repository) ile iş kuralı katmanı (Service) birbirinden ayrılmıştır. Yarın MySQL yerine MongoDB'ye geçilmek istendiğinde, `ProductRepositoryInterface`'i implemente eden yeni bir sınıf yazmak yeterlidir; controller ve servisler hiçbir değişiklik hissetmez.
* **Adapter / Factory Pattern**: Ödeme (Iyzico), Kargo (Yurtiçi, Aras) ve Arama (MySQL, ElasticSearch) gibi dış dünyaya bağımlı sistemler için kullanılmıştır. Örneğin `ShippingCarrierAdapterInterface` sayesinde, sisteme yeni bir kargo firması eklemek sadece bir sınıf (örn. `MngAdapter`) yazmak kadar kolaydır.

---

## 2. Veri Tabanı ve İlişki Mimarisi (Database Blueprint)

Geliştirdiğimiz veritabanı, büyük veriyi yormadan işleyecek indekslemelere ve tutarlılığa sahiptir.

- **`users` & `roles`**: Çoklu persona desteği için özel RBAC sistemi kurulmuştur. Sisteme `Admin`, `Vendor` ve `Customer` rolleri tanımlanmıştır. Satıcıların komisyon oranları ve mağaza isimleri `users` tablosunda saklanır.
- **`categories`**: Sınırsız derinlikte kategori hiyerarşisi için materialized `path` (örn: `1/5/12`) kullanılmıştır. Bu sayede bir kategorinin altındaki tüm ürünleri bulmak `O(1)` hızına yaklaşır.
- **`products` & `product_variants`**: Trendyol tarzı ürün yapısının kalbidir. Bir tişört (`product`) tek başına satılamaz; onun varyantları (`product_variants`) olan "Kırmızı - XL" satılır. Stok ve fiyat yönetimi varyant bazlı yapılır. Dinamik seçenekler `variant_options` (Renk, Beden) ve `variant_option_values` (Kırmızı, Mavi) ile sağlanır.
- **`carts` & `cart_items`**: Veritabanı tabanlı kalıcı sepet yapısı. Kullanıcı farklı bir cihaza geçse bile sepetini kaybetmez.
- **`orders` & `order_items`**: Sipariş oluşturulduğu an, ürün fiyatı, kargo adresi ve komisyon oranları JSON formatında **snapshot (anlık görüntü)** olarak bu tablolara kopyalanır. Satıcı yarın ürünün fiyatını veya adını değiştirse dahi, eski siparişin faturası bozulmaz.

---

## 3. Kritik İş Mantığı ve Güvenlik Katmanları (Business Logic)

### Sipariş ve Stok Güvenliği (Pessimistic Locking)
E-ticaretin en büyük kâbusu, 1 adet kalan son ürünü aynı saniyede 5 kişinin satın almasıdır (Race Condition). Bunu engellemek için `OrderService` içerisinde **Pessimistic Locking** uygulanmıştır:
```php
DB::transaction(function () {
    // lockForUpdate() ile MySQL satırını diğer işlemlere kilitleriz
    $variant = ProductVariant::lockForUpdate()->find($id);
    if ($variant->stock < $quantity) throw new InsufficientStockException();
    // Stok düşülür ve sipariş onaylanır...
});
```
Eğer sipariş sürecinde herhangi bir ağ kopması veya hata olursa, `transaction` geri alınır (rollback) ve kimsenin parası boş yere çekilmez.

### iyzico 3D Secure Entegrasyonu
Ödeme akışı iki aşamalı (Two-step checkout) tasarlanmıştır:
1. **Initialize (`PaymentApiController@initialize3DS`)**: Stok kilitlenir, `pending` (bekliyor) durumunda sipariş açılır ama **sepet henüz boşaltılmaz**. Iyzico SDK kullanılarak oluşturulan 3D Secure banka onay ekranı, `Raw HTML String` olarak frontend'e fırlatılır.
2. **Callback (`PaymentApiController@callback3DS`)**: Bankadan gelen başarılı/başarısız yanıt burada işlenir. Yanıt başarılıysa `OrderService@completePayment` çalışır, sepet temizlenir ve satıcıya haber verilir. Başarısızsa `failPayment` ile ayrılan stoklar stoğa geri iade edilir.

### Kargo Takip & Webhook Mekanizması
Kargo firmaları paket hareketlerini sisteme webhook ile bildirir.
- `ShippingWebhookController`, gelen isteği `ShippingService`'e iletir.
- `ShippingService`, kargo firmasının adına göre doğru adaptörü (`YurticiAdapter` vb.) Factory pattern ile yaratır.
- Adaptör, webhook güvenliğini (`X-Signature`) doğrular, karmaşık kargo statülerini (örn: `Status=4`) sistemimizin anladığı `delivered` statüsüne çevirir ve sipariş durumunu otomatik günceller.

---

## 4. Gelişmiş Servisler (Search & Notification)

### Akıllı Arama & Otomatik Tamamlama (Search Service)
Müşteriler arama kutusuna bir şeyler yazdığında ışık hızında yanıt dönmek için `SearchEngineInterface` kurulmuştur.
- Şu anda `MysqlSearchEngine` aktiftir. `LIKE` ve `JsonContains` (JSON etiket arama) kullanarak isim, SKU ve tag alanlarında tarama yapar. Arama sonuçlarını sadece ihtiyaç duyulan id, isim ve resim ile sınırlandırarak aşırı hızlı bir `/autocomplete` deneyimi sunar.
- **Gelecek Uyumluluğu**: Yarın Elasticsearch sunucusu kiralandığında, `ElasticsearchEngine` sınıfını yazıp `SearchService` içerisinde `driver` adını değiştirmek, tüm sistemi anında Elastic'e geçirir.

### Çok Kanallı Bildirim Sistemi
Sipariş alındığında, kargolandığında ve teslim edildiğinde kullanıcıya hem Mail hem SMS atacak bir altyapı kurulmuştur.
- **SmsChannel & SmsProviderInterface**: Laravel'in standart bildirim kanallarına (Mail, Slack vb.) ek olarak kendi `SmsChannel` sınıfımız yazılmıştır.
- **Adapter**: Netgsm üzerinden giden örnek bir adaptör kurgulanmıştır.
- `OrderPlacedNotification`, `OrderShippedNotification` gibi bildirimler; Observer Pattern ile servis sınıflarının sonuna takılmıştır. Şablonlar Laravel'in varsayılan MailMessage sınıfı ile oluşturulmuş olup, arayüz ileride `vendor:publish` ile rahatça özelleştirilebilir durumdadır.

---

## 5. TALL Stack Yönetim Panelleri (Admin & Vendor Dashboards)

Frontend ve gösterge panelleri **Livewire 3 + Tailwind CSS + Alpine.js** ile modern SPA hızında kodlanmıştır.

- **Dinamik ve Rol Bazlı Sidebar**: Sisteme kimin (Admin veya Satıcı) girdiğini algılar. Super Admin, sistemin toplam kazançlarını, satıcı listelerini ve onay bekleyen mağazaları görürken; Satıcı sadece kendi ürünlerini ve kendi siparişlerini görür.
- **Livewire Components**: `Admin\Dashboard` ve `Vendor\Dashboard` controller'ları, veriyi veritabanından alıp blade şablonlarına real-time basar.
- **ProductManager (Kusursuz Varyant Yönetimi)**: 
  - Ürün ekleme ekranında Blade ve Vanilla JS kullanmanın getirdiği "spaghetti code" kâbusu, Livewire'ın reaktif veri modeliyle aşılmıştır.
  - Satıcı "Varyant Ekle" butonuna bastığında, Livewire PHP içerisindeki array'e bir eleman ekler ve arayüz Alpine.js geçişleriyle anında render olur.
  - Sınırsız Beden/Renk/Fiyat/Stok kombinasyonu tek bir form üzerinden (sayfa yenilenmeden) girilebilir ve `WithFileUploads` kullanılarak resimler progress bar ile yüklenir.
  - Arayüz, Indigo ve Slate renkleriyle, Tailwind'in `ring`, `rounded-xl` ve `shadow-sm` sınıflarıyla bezenmiş tamamen Premium bir görünüme sahiptir.

---

## 6. Kurulum ve Canlıya Alma Kılavuzu (Installation Guide)

Bu muazzam mimariyi taze bir Laravel 11 projesinde ayağa kaldırmak için aşağıdaki adımları sırasıyla uygulayınız:

1. **Dosyaları Taşıyın**: 
   Üretilen `app`, `database` ve `routes` klasörlerini direkt projenizin kök dizinine kopyalayın. Tasarım bileşenleri için `resources/views` klasörünü kopyalayın.

2. **Bağımlılıkları Yükleyin**:
   ```bash
   composer require livewire/livewire
   composer require iyzico/iyzipay-php
   ```

3. **Interface & Repository Bağlantılarını Yapın**:
   `app/Providers/AppServiceProvider.php` içerisindeki `register()` metoduna şu bağlamayı ekleyin:
   ```php
   $this->app->bind(
       \App\Repositories\Contracts\ProductRepositoryInterface::class,
       \App\Repositories\Eloquent\EloquentProductRepository::class
   );
   ```

4. **Middleware Kaydını Yapın**:
   `bootstrap/app.php` içerisinde Role middleware'ini tanımlayın:
   ```php
   ->withMiddleware(function (Middleware $middleware) {
       $middleware->alias([
           'role' => \App\Http\Middleware\CheckRole::class,
       ]);
   })
   ```

5. **Veritabanını Hazırlayın**:
   `.env` dosyanızda MySQL bilgilerinizi girin ve tabloları oluşturun:
   ```bash
   php artisan migrate
   ```

6. **Konfigürasyonları Ekleyin**:
   `config/services.php` içerisine Iyzico ve Kargo güvenlik ayarlarını eklemeyi unutmayın:
   ```php
   'iyzipay' => [
       'api_key'    => env('IYZIPAY_API_KEY'),
       'secret_key' => env('IYZIPAY_SECRET_KEY'),
       'base_url'   => env('IYZIPAY_BASE_URL', 'https://sandbox-api.iyzipay.com'),
   ],
   'shipping' => [
       'yurtici' => [
           'webhook_secret' => env('YURTICI_WEBHOOK_SECRET'),
       ]
   ],
   ```

Bu mimariyle artık saniyede binlerce kullanıcının sorunsuz alışveriş yapabileceği, satıcıların kendi mağazalarını rahatlıkla yönetebileceği ölçeklenebilir ve kurumsal seviye bir e-ticaret platformuna sahipsiniz!
