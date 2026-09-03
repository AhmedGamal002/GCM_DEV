# DEPLOYMENT.md — نشر على سيرفر تطوير (Hostinger Business Web Hosting)

> دليل عملي لأول نشر على سيرفر تطوير/staging — مش production حقيقي للعميل بعد. الهدف: نتأكد إن كل حاجة شغالة في بيئة حقيقية (دومين حقيقي، PHP حقيقي، من غير `php artisan serve`) قبل ما نكمل.

## 0. قبل ما تبدأ

- **خطة Hostinger:** Business Web Hosting — بتدّي SSH access (لازم تتفعّل يدوي من hPanel: Advanced → SSH Access) + Composer (متاح عبر SSH أو أداة "Composer" جوه hPanel → Advanced) + اختيار PHP version (hPanel → Advanced → PHP Configuration، اختار 8.2 أو أعلى، المشروع شغال محليًا على 8.3).
- **مفيش Redis مطلوب حاليًا** — `.env` المحلي شغال بـ `QUEUE_CONNECTION=database`, `CACHE_STORE=database`, `SESSION_DRIVER=database`. لو Hostinger مش هيديك Redis، مفيش مشكلة دلوقتي (`ARCHITECTURE.md §6` بيوصف Redis كهدف نهائي — لسه مبنيش عليه فعليًا).
- **مفيش queue worker دائم مطلوب حاليًا** — مفيش حاجة بترمي Job فعليًا في الكود لسه (كل حاجة sync). لو الأسابيع الجاية ضافت Jobs حقيقية، شيرد هوستنج مبيسمحش بعملية `queue:work` دائمة — الحل وقتها Cron بيشغّل `queue:work --stop-when-empty` كل دقيقة، مش دلوقتي.

## 1. بنية الفولدرات على السيرفر — أهم جزء أمنيًا في النشر كله

**القاعدة الذهبية اللي كل حاجة تحت مبنية عليها: مشروع Laravel كامل عمره ما يتحط جوه أي `public_html/` زي ما هو.** الحماية الوحيدة الحقيقية 100%: الملفات الحساسة (`.env`, `app/`, `config/`, `database/`, `vendor/`, `storage/`) تبقى **فيزيائيًا برة أي `public_html`** — مش بس برة الـ Document Root بتاع الـ subdomain المحدد.

**الشكل الفعلي على حسابك** (`domains/<اسم الدومين>/public_html/` لكل دومين مملوك، وأي ساب-دومين متولّد منه بيتحط كفولدر **جوه نفس الـ `public_html`** بتاعة الدومين الأب — ده الشكل المؤكد على حسابك، مش الشكل التاني اللي كل ساب-دومين بياخد فولدر منفصل تحت `domains/`):

```
/home/<hostinger-username>/
├── domains/
│   ├── digitswat.com/
│   │   └── public_html/
│   │       ├── (محتوى الدومين الرئيسي نفسه لو موجود — موقع الشركة مثلاً، سيبه زي ما هو)
│   │       ├── gcm/                 ← فولدر مشروع GCM كله — فاصل بينه وبين أي مشروع تاني
│   │       │   ├── dev/             ← dev.digitswat.com
│   │       │   ├── testing/         ← testing.digitswat.com
│   │       │   ├── staging/         ← staging.digitswat.com
│   │       │   └── production/      ← gcm.digitswat.com (الاسم مختلف عمدًا عن اسم الفولدر — تفصيل تحت)
│   │       │       (كل فولدر من دول نسخة من public/ بس — مش كود، راجع §2)
│   │       └── <مشروع-تاني>/         ← نفس الفكرة، فولدر منفصل تمامًا لما يجي مشروع جديد
│   │
│   └── thecloud-it.com/public_html/    ← نفس الفكرة لو مشروع على الدومين ده بدل ده
│
└── apps/
    └── gcm/                        ← كل بيئات مشروع GCM هنا، برة `domains/` تمامًا
        ├── dev/                    ← checkout كامل مستقل (app/, vendor/, .env, إلخ)
        ├── testing/                ← نفس الشيء، مستقل تمامًا عن dev
        ├── staging/
        └── production/
```

**تفصيل التسمية:** فولدر `production/` (جوه `public_html/gcm/`) هو الـ Document Root بتاع `gcm.digitswat.com` — الاسمين مختلفين عمدًا (الفولدر بيتسمى بدوره `production` زي باقي البيئات للاتساق، السب-دومين بيتسمى `gcm` لأنه ده اللي هيشوفه المستخدم النهائي). Hostinger مش بيشترط تطابق اسم الفولدر مع اسم الساب-دومين — بس لازم تحدد المسار ده بالظبط (`public_html/gcm/production`) وقت إنشاء الساب-دومين من hPanel، مش تسيبه على القيمة الافتراضية المقترحة.

**السبب إن `~/apps/gcm/...` (كود المشروع الحقيقي) أفضل من فولدر جنب `public_html` جوه `domains/digitswat.com/` نفسها:** من ناحية "هل رابط ممكن يوصله؟" الاتنين متساويين (أي مكان برة أي `public_html` مش قابل للوصول عبر HTTP خالص). الفرق عملي مش نظري:
1. **مستقل تمامًا عن أي دومين معيّن** — لو يوم عملت reset لساب-دومين من hPanel، أي عملية "تنضيف" بتشتغل جوه `domains/<domain>/` بس، مش هتلمس `apps/gcm` خالص.
2. **مكان واحد ثابت** لأي مشروع/بيئة تانية على نفس الحساب — مش هيتلخبط مع فولدرات أي دومين تاني.

## 2. بيئات متعددة (dev / testing / staging / production) — لكل مشروع

بما إنك قررت الشكل ده لكل مشروع، خد بالك من النقاط دي قبل ما تبدأ تكرر الإعداد 4 مرات:

- **كل بيئة = checkout Git مستقل بالكامل** — مش فولدر واحد بيتشارك، ولا `vendor/` مشترك. كل بيئة ليها `composer install` خاص بيها، `.env` خاص بيها، `storage/` خاص بيها (رفوعات/logs منفصلة تمامًا). لو مشتركين في أي حاجة، بيئة هتأثر في التانية بالغلط (باگ ظهر في dev هيبان في production فورًا، أو العكس).
- **كل بيئة محتاجة قاعدة بيانات منفصلة تمامًا** — مقترح تسمية: `gcm_dev`, `gcm_testing`, `gcm_staging`, `gcm_production` (هوستنجر عادةً بيضيف بادئة اسم الحساب تلقائي، يعني الاسم الفعلي هيبقى شكله `u123456_gcm_dev`). **اتأكد من عدد قواعد البيانات المسموح بيه في خطتك من hPanel قبل ما تخطط لـ4 قواعد لكل مشروع** — الخطط بتختلف في الحد الأقصى.
- **كل بيئة محتاجة subdomain منفصل** (بما إن خطتك مبتسمحش بـ Document Root مخصص، كل بيئة لازم فولدر `public_html` خاص بيها زي §1) — كمان اتأكد من عدد الساب-دومينز المسموح بيه في خطتك.
- **`git branch` مختلف لكل بيئة** مقترح شائع (مش إلزامي): `dev` يتابع الفرع اللي بتشتغل عليه يوميًا (زي `phase_1_merge` حاليًا)، `testing`/`staging` يتابعوا فروع أكتر استقرارًا (`develop`/`release`)، `production` يتابع `main` بس بعد مراجعة. القرار ده ليك، بس لو مقررتوش هتلاقوا نفسكم بتنشروا نفس الكود العشوائي على الأربعة.
- **`APP_ENV` لازم يبقى مختلف فعليًا لكل بيئة** (`local`/`testing`/`staging`/`production` — مش الأربعة `production` زي بعض) — بعض الحزم (زي `spatie/laravel-ignition`) بتتصرف مختلف حسب القيمة دي.
- **السكريبت `scripts/hostinger-sync-public.sh` بقى عام (parameterized)** بالظبط عشان الحالة دي — بياخد `<app_dir> <web_dir>` كباراميترات بدل مسارات مكتوبة جواه، فبيشتغل لأي بيئة/مشروع من غير ما تعدّل الملف نفسه:
  ```bash
  bash ~/apps/gcm/dev/scripts/hostinger-sync-public.sh \
    ~/apps/gcm/dev \
    ~/domains/digitswat.com/public_html/gcm/dev

  bash ~/apps/gcm/testing/scripts/hostinger-sync-public.sh \
    ~/apps/gcm/testing \
    ~/domains/digitswat.com/public_html/gcm/testing

  bash ~/apps/gcm/staging/scripts/hostinger-sync-public.sh \
    ~/apps/gcm/staging \
    ~/domains/digitswat.com/public_html/gcm/staging

  bash ~/apps/gcm/production/scripts/hostinger-sync-public.sh \
    ~/apps/gcm/production \
    ~/domains/digitswat.com/public_html/gcm/production
  ```
  (المسارات دي لمشروع GCM بالذات على `digitswat.com` — أي مشروع تاني، أو على `thecloud-it.com`، نفس الفكرة بالظبط بس بفولدر مشروع مختلف: `public_html/<project>/<env>`.)
- **`production` على نفس حساب Hostinger ده تحديدًا** — مقبول تمامًا لعميل صغير/فترة تجريبية، لكن لما يبقى فيه عميل حقيقي بيانه حساس وحركة فعلية، الأفضل production ينتقل لحساب/سيرفر منفصل تمامًا عن `dev`/`testing` (تصادف حمل أو غلطة نشر في `dev` عمرها ميأثرش على `production` لو كانوا نفس الحساب فعليًا معزولين بالكامل بيئويًا بس على نفس الموارد). قرار مستقبلي، مش عاجل دلوقتي.

باقي الخطوات تحت (§3 لحد §11) بتتكرر **لكل بيئة على حدة** — كل بيئة ليها `git clone`/`.env`/migrate/build خاص بيها، مش خطوة واحدة تتعمل مرة وتتشارك.

## 3. باگ حقيقي اتلاقى واتصلح أثناء تجهيز النشر ده

`php artisan route:cache` (خطوة أداء قياسية لأي نشر production) كان بيفشل بـ `LogicException: Unable to prepare route [...] for serialization. Another route has already been assigned name [...]`. السبب: تلات routes ديمو (Vuexy scaffold الأصلي في `routes/web.php`) اتعملهم copy-paste من غير ما تتغيّر أسماءهم:

- `/layouts/horizontal` و`/layouts/vertical` كانوا الاتنين باسم `dashboard-analytics` (منسوخين من `/dashboard/analytics`).
- `/auth/forgot-password-basic` كان باسم `auth-reset-password-basic` (منسوخ من الراوت الجنب).

`Route::name()` مبيرميش error وقت التسجيل لو الاسم مكرر — بس بيتسبب في `route('...')` بترجع آخر واحد اتسجل، ومشكلة فعلية بس وقت أي حاجة محتاجة تسلسل/تعداد كل الـ routes بأسمائها بشكل فريد (`route:cache`). اتصلح بإعادة تسمية التلاتة (`layouts-horizontal`, `layouts-vertical`, `auth-forgot-password-basic`). **ده منفصل تمامًا عن مشكلة `php artisan route:list` الموثقة في `CLAUDE.md` (فخ #8، `ReflectionException` على `NavbarFull`)** — مشكلة مختلفة، لسه موجودة، ومقصود إننا مش بنصلحها (أداة تشخيص محلية بس، مش خطوة نشر).

## 4. رفع الكود (لكل بيئة)

**الخيار الأسهل مع Business plan:** أداة "Git" في hPanel (Advanced → Git) — بتربط مباشرة بريبو GitHub/GitLab. **حدد مسار النشر (repository path) جوه `apps/gcm/<environment>/` (زي §1/§2)، مش جوه `domains/` خالص.** بتعمل `git pull` بضغطة زرار (أو auto-deploy عند push لو فعّلتها). البديل: `git clone` عادي عبر SSH لو الأداة مش متاحة على خطتك بالظبط.

```bash
# عبر SSH، بعد ما فعّلت SSH access من hPanel — مثال لبيئة dev، كرّرها لكل بيئة بمسارها
mkdir -p ~/apps/gcm
cd ~/apps/gcm
git clone <repo-url> dev
cd dev
git checkout phase_1_merge   # أو أي branch مخصص للبيئة دي — راجع §2
```

## 5. Composer

```bash
composer install --no-dev --optimize-autoloader
```

`--no-dev` بيستبعد `phpunit`/`laravel/pint`/`spatie/laravel-ignition` (أدوات تطوير بس، مش محتاجة على السيرفر) — **إلا في بيئة `testing`** لو نويت تشغّل `php artisan test` عليها فعليًا (وقتها سيبها من غير `--no-dev`، أو ثبّت `phpunit` بشكل منفصل).

**⚠️ باگ حقيقي اتلاقى بالتجربة الفعلية أثناء النشر (`php artisan db:seed` رمى `Call to undefined function Database\Factories\fake()`):** `fakerphp/faker` كان في `require-dev` (افتراض Laravel القياسي: الفاكتوريز أداة تستات بس) — لكن `DatabaseSeeder.php` بيستخدم `User::factory()->create(...)` فعليًا لزرع حسابات حقيقية (admin/data_entry/auditor/driver)، و`UserFactory::definition()` بيستخدم `fake()->name()`/`fake()->unique()->safeEmail()` حتى لو القيم دي هتتجاوز بعدين بالـ`create()` override — الدالة `definition()` بتتنفّذ كاملة قبل أي override. يعني `composer install --no-dev` (زي ما موصّى بيه فوق) كان بيكسر الزرع بالكامل على أي بيئة جديدة. **اتصلح** بنقل `fakerphp/faker` من `require-dev` لـ`require` في `composer.json` (`composer require fakerphp/faker` من غير `--dev`) — بقى متاح حتى مع `--no-dev`. **درس عام:** أي حزمة require-dev بتتستخدم فعليًا من كود بيشتغل بره التستات (Seeder بيتشغّل على بيئة حقيقية، مش بس `RefreshDatabase` في PHPUnit) لازم تبقى `require` عادية، مهما كان اسمها بيوحي إنها "أداة تطوير".

## 6. `.env` — لازم يتكتب من الصفر على السيرفر لكل بيئة، مش نسخة من المحلي ولا عن طريق الريبو

القيم دي **مختلفة عن `.env` المحلي عمدًا، ومختلفة كمان بين كل بيئة والتانية** — انسخها وعدّل القيم بين `<>` (المثال هنا لبيئة `dev`):

```env
APP_NAME="GCM Portal (Dev)"
APP_ENV=local                     # local لـ dev، testing لـ testing، staging لـ staging، production لـ production
APP_KEY=                          # يتولد بالخطوة الجاية، سيبه فاضي دلوقتي — مفتاح منفصل لكل بيئة
APP_DEBUG=true                    # مقبول في dev/testing بس — production/staging لازم false دايمًا
APP_URL=https://dev.digitswat.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=<اسم قاعدة بيانات هذه البيئة تحديدًا من hPanel>
DB_USERNAME=<username من hPanel>
DB_PASSWORD=<password من hPanel>

SESSION_DRIVER=database
SESSION_DOMAIN=dev.digitswat.com
SESSION_SECURE_COOKIE=true        # الموقع هيبقى شغال بـ HTTPS

SANCTUM_STATEFUL_DOMAINS=dev.digitswat.com

QUEUE_CONNECTION=database
CACHE_STORE=database
FILESYSTEM_DISK=local

MAIL_MAILER=log                   # لحد ما يبقى فيه إعداد بريد حقيقي
```

```bash
php artisan key:generate
```

## 7. الداتابيز

```bash
php artisan migrate --force
```

**لأول مرة بس** على أي بيئة جديدة فاضية ممكن كمان `php artisan db:seed` — لكن خد بالك: الـ seeder بيزرع حسابات بباسوردات معروفة (`admin@gcm.test` / `password`، وهكذا) مخصصة للتطوير المحلي. لو أي بيئة من دول متاحة لأي حد برة الفريق، إما:
- غيّر الباسوردات فورًا بعد الزرع، أو
- احمي البيئة كلها بـ HTTP Basic Auth من hPanel (Advanced → Password Protect Directories) لحد ما يبقى فيها بيانات حقيقية.

**متستخدمش `migrate:fresh` على أي بيئة بيانها محتفظ بيه بين مرات** (خصوصًا `staging`/`production`) — بيمسح كل حاجة. `migrate:fresh` مقبول بس في `dev` أو `testing` لما تصفّر التجربة عن قصد.

## 8. الأصول (JS/CSS)

Business shared hosting مفيهوش Node.js غالبًا. ابنِ الأصول محليًا وارفع الناتج:

```bash
# محليًا
npm run build
# بيطلع public/build/
```

**رفع الفولدر ده للسيرفر — بما إنك عندك SSH شغال، `scp` أبسط طريقة** (تشتغل من نفس الترمينال على جهازك، مش على السيرفر):

```bash
# محليًا، من جذر المشروع — بدّل <port> و<user>@<host> بالقيم من hPanel → Advanced → SSH Access
scp -r -P <port> public/build u<...>@<host>:~/apps/gcm/dev/public/
```

- `-r` عشان `build/` فولدر فيه فولدرات فرعية (`assets/` إلخ)، مش ملف واحد.
- `-P` (كابيتال) بورت الـ SSH — لاحظ الفرق عن أمر `ssh` نفسه اللي بيستخدم `-p` (سمول) لنفس الغرض، غلطة شائعة.
- النتيجة هتبقى `~/apps/gcm/dev/public/build/...` — لو الفولدر مش موجود أصلًا هيتعمل تلقائي.
- **البديل من غير SSH:** File Manager جوه hPanel — ارفع `public/build` كـ zip واحد وفكّه هناك (أسرع من رفع كل ملف لوحده لو الملفات كتير).

بعد الرفع، شغّل السكريبت (مثال لبيئة `dev`، بدّل المسارين لأي بيئة تانية) عشان الأصول الجديدة توصل فعليًا لـ `public_html` بتاعة الساب-دومين اللي هو المكان اللي بيتقدّم للزوار:

```bash
bash ~/apps/gcm/dev/scripts/hostinger-sync-public.sh \
  ~/apps/gcm/dev \
  ~/domains/digitswat.com/public_html/gcm/dev
```

## 9. Storage + الكاش

```bash
php artisan config:cache
php artisan route:cache      # كان بيفشل قبل إصلاح §3 — لازم يشتغل من غير error
php artisan view:cache

# يعمل الـ symlink الفعلي اللي بيتقدّم للزوار — مثال لبيئة dev (زي §8)
bash ~/apps/gcm/dev/scripts/hostinger-sync-public.sh \
  ~/apps/gcm/dev \
  ~/domains/digitswat.com/public_html/gcm/dev
```

**`php artisan storage:link` مش مطلوب في الإعداد ده خالص — سيبه.** الأمر ده بينشئ symlink جوه `<app_dir>/public/storage` (زي `apps/gcm/dev/public/storage`)، لكن الفولدر ده **مش** اللي بيتقدّم للزوار في إعدادنا (ده `<web_dir>` — `public_html` بتاعة الساب-دومين، فولدر منسوخ منفصل تمامًا). حتى لو اشتغل الأمر بنجاح، الـsymlink بتاعه في مكان محدش بيشوفه. كمان بعض استضافات الشيرد بتقفل دالة PHP `symlink()` نفسها (`disable_functions`) لأسباب أمنية، فممكن يرمي error صريح بدل ما "يشتغل بلا فايدة" بس. السكريبت (`hostinger-sync-public.sh`) بيعمل الـsymlink الصح لوحده (`ln -s` shell-level، مش عن طريق PHP، فمش متأثر بنفس القيد) عند المكان الحقيقي — ده الوحيد المطلوب.

## 10. صلاحيات الملفات

`storage/` و`bootstrap/cache/` لازم يكونوا قابلين للكتابة من PHP process (عادة `www-data` أو مستخدم مشابه على Hostinger):

```bash
chmod -R 775 storage bootstrap/cache
```

## 11. SSL

Hostinger Business بيدّي شهادة Let's Encrypt مجانية — فعّلها من hPanel (SSL → Let's Encrypt) على **كل** subdomain (dev/testing/staging/production كلهم منفصلين) قبل أي تسجيل دخول حقيقي (الـ session/Sanctum cookies بتفترض HTTPS في production عبر `SESSION_SECURE_COOKIE=true`).

## 12. تحقق أمني بعد النشر — جرّب الروابط دي بنفسك، متفترضش (كرّرها لكل بيئة)

كل سطر تحت لازم تفتحه فعليًا في المتصفح (أو `curl -I`) وتتأكد من النتيجة المكتوبة — مش تقرأها وتفترض إنها هتشتغل. البيئة الأخطر لو اتنسيت هي `production` (بيانات حقيقية) — ابدأ التحقق منها هي بالذات:

- [ ] `https://<subdomain>/.env` → **404**. لو رجع محتوى الملف، وقف فورًا — راجع إن `apps/gcm/<environment>/` فعلاً برة `domains/` تمامًا.
- [ ] `https://<الدومين الرئيسي>/apps/gcm/...` أو أي مسار مشابه تحت **الدومين الرئيسي** (مش الساب-دومين) → **404** كمان.
- [ ] `https://<subdomain>/build/` (من غير اسم ملف) → **403/404**، مش قائمة ملفات.
- [ ] `https://<subdomain>/app/Models/User.php` أو أي مسار بيحاول يوصل لكود PHP مباشرة → **404**.
- [ ] لو الدومين الأب (`digitswat.com`) نفسه فيه موقع تاني شغال على جذره (موقع الشركة مثلاً): افتح `https://dev.digitswat.com` وتأكد إنه فعليًا بيعرض GCM Portal، مش موقع الشركة أو صفحة 404 بتاعته — لو الموقع التاني عنده `.htaccess`/SPA routing بيمسك كل المسارات (catch-all)، ممكن يعترض الساب-دومين قبل ما يوصل للفولدر المتداخل. لو حصل، الحل مراجعة `.htaccess` بتاع الموقع الأب، مش تعديل هنا.
- [ ] جرّب توصل لخطأ 500 عن قصد وتأكد إن الاستجابة **مفيهاش** stack trace ولا قيم `.env` — بيتأكد إن `APP_DEBUG=false` فعليًا على `staging`/`production` تحديدًا.
- [ ] `curl -I https://<subdomain>/build/assets/<أي ملف .js موجود فعليًا>` → لازم يظهر `Cache-Control: public, max-age=31536000, immutable`. ده تحقق لباگ أداء حقيقي اتلاقى (`ARCHITECTURE.md §6`) — بدون الهيدر ده، أي ملف JS كبير (زي `datatables-bootstrap5-*.js`، ~2.4 ميجا) بيتنزّل من جديد في كل تنقّل بين الصفحات. **الفحص ده لازم يتعمل هنا** — `.htaccess` مش شغال على `php artisan serve` المحلي، أول مرة ممكن تتأكد منه فعليًا هي على Apache الحقيقي.
- [ ] فتح كل subdomain بـ HTTPS من غير أي تحذير شهادة.
- [ ] تسجيل دخول (تينانت + Platform) شغال.
- [ ] القائمة الجانبية بتفلتر صح حسب الدور (راجع `ARCHITECTURE.md §3`).
- [ ] رفع صورة/مستند وتحميله تاني — بيتأكد الـ symlink بتاع `storage` صح.
- [ ] تصدير Excel/PDF شغال.
- [ ] `php artisan migrate:fresh --seed` **متشتغلش على `staging`/`production` بالغلط أبدًا** — القاعدة دي لـ `dev`/`testing` بس.

## 13. "هل ده آمن فعلًا؟" — إجابة صريحة، مش طمأنة عامة

**اللي الطريقة دي بتضمنه فعليًا (تقني، أكيد):** `.env`، كود `app/`، `vendor/`، `database/`، ملفات الـ logs — كل ده **مش تحت أي Document Root خالص**. مش مسألة `.htaccess` بيمنع الوصول (قاعدة ممكن تتنسى أو تتعدل غلط) — الملفات دي فيزيائيًا مش في المكان اللي Apache بيقرا منه أصلًا، فمفيش رابط في الدنيا يوصلها. ده أقوى ضمان ممكن على شيرد هوستنج، وهو نفس الأسلوب المعياري لأي نشر Laravel على استضافة مشتركة.

**اللي **مش** بيتغطى بالطريقة دي، ولسه محتاج انتباهك:**

1. **باسوردات الحسابات التجريبية** (`admin@gcm.test` / `password` وغيرها) — لو أي بيئة متاحة لأي حد برة الفريق، حد ممكن يسجّل دخول عادي بيها من غير أي اختراق تقني خالص. ده أرجح مسار هجوم فعلي من تسريب ملفات، خصوصًا لو نفس البيانات دي موجودة بالغلط على `staging`/`production`. **لازم** تحط Basic Auth على أي subdomain مش production من hPanel (Advanced → Password Protect Directories)، أو تغيّر الباسوردات فورًا بعد أي زرع على بيئة حساسة.
2. **`APP_DEBUG` لازم يفضل `false` على `staging`/`production`** — ده مسؤوليتك تتأكد منها في كل `.env` على حدة، مش حاجة بتتضمن تلقائيًا؛ لو اتنسي `true`، صفحة أي خطأ (حتى بسيط) بتعرض stack trace كامل وأحيانًا قيم من `.env` نفسه.
3. **ثغرات على مستوى الكود نفسه (SQL injection, XSS, تجاوز صلاحيات)** — مالهاش علاقة ببنية الفولدرات خالص. دي بتتغطى بمراجعة الكود المستمرة اللي حصلت طول الجلسات دي (allowlist على `sort_by`، escape في DataTables، الـ Policies)، مش بمكان الملفات على السيرفر.
4. **صلاحيات الملفات نفسها على السيرفر** — تأكد `.env` مش قابل للقراءة من أي مستخدم تاني على نفس السيرفر (نادر يبقى مشكلة على حساب Hostinger منفصل، لكن `chmod 640` أو أقل على `.env` احتياط إضافي رخيص).
5. **السكريبت (`hostinger-sync-public.sh`) لازم يتشغّل بعد كل تحديث كود، لكل بيئة على حدة** — نسيان ده مش ثغرة أمنية، بس ممكن يخليك تفتكر إن حاجة "اتصلحت" وهي لسه بالنسخة القديمة على الموقع الفعلي.
6. **خلط بيانات بين البيئات** — لو `staging`/`production` استخدموا نفس الـ `DB_DATABASE` بالغلط (نسخ `.env` بدل كتابته من الصفر)، تجربة على `staging` ممكن تعدّل بيانات `production` الحقيقية. راجع §6 كل مرة تعمل `.env` لبيئة جديدة.
