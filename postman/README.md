# GCM Portal — Postman Collection

مجموعة Postman تغطي كل الـ endpoints الشغالة فعليًا في المشروع حتى الآن (الأسبوع 1 + 2 + موديولات المركبات ومجمع الأصول وإدارة السائقين من الأسبوع 3 + المنشآت الوسيطة والخدمات وشركات العملاء ومشروعاتهم وحسابات العملاء من المرحلة 2). بتتحدث أول بأول مع كل خاصية جديدة — راجع "قاعدة التحديث" تحت.

## الملفات

- `GCM-Portal.postman_collection.json` — المجموعة نفسها (17 مجلد).
- `GCM-Portal-Local.postman_environment.json` — إعدادات بيئة التطوير المحلي (بيانات الدخول الافتراضية من الـ seeders).

## الاستيراد

1. Postman → Import → اسحب الملفين التنين.
2. من قائمة الـ Environment أعلى يمين Postman، اختار **"GCM Portal - Local"**.
3. تأكد إن السيرفر شغال على `http://localhost:8000` (أو عدّل `base_url` في الـ environment لو مختلف).

## نظامين مصادقة مختلفين تمامًا — مهم تفهمهم قبل ما تستخدم أي مجلد

### 1. تينانت (`/api/v1/*`) — Bearer Token

المجلدات 1-13 (Auth/Me/Roles/Users/Drivers/Vehicles/Assets/Intermediate Facilities/Services/Contractors/Client Companies/Client Projects/General Settings). الـ collection معمول عليها auth من نوع Bearer على مستوى الـ root بقيمة `{{tenant_token}}`.

- شغّل **"1. Auth (Tenant) → Login"** الأول. Postman مش هيبقى أبدًا من ضمن `SANCTUM_STATEFUL_DOMAINS`، فالسيرفر هيرجّعله `token` (نفس المسار اللي هيتستخدم لموبايل السائق مستقبلًا) بدل session cookie. الـ test script بتاع الريكوست بيحفظ التوكن تلقائي في `tenant_token`.
- من بعدها أي ريكوست في المجلدات 2-11 هيبعت الـ Authorization header تلقائي.
- `last_user_id` و`last_driver_id` و`last_vehicle_id` و`last_vehicle_category_id` و`last_asset_id` و`last_asset_capacity_category_id` و`last_facility_id` و`last_main_service_id` و`last_sub_service_id` و`last_company_id` و`last_project_id` بيتحفظوا تلقائيًا من ريسبونس "Create User"/"Create Driver"/"Create Vehicle"/"Create Asset"/"Create Capacity Category"/"Create Facility"/"Create Main Service"/"Create Sub-service"/"Create Client Company"/"Create Client Project" عشان تقدر تكمل على طول بـ Show/Update من غير ما تنسخ id يدوي. (`last_vehicle_document_id` بيتحط يدوي من ريسبونس "Show Vehicle" عشان تجرب "Download Document".)

### 2. Platform / Super Admin (`/platform/*`) — Session + CSRF

المجلدات 14-17. **مش** Bearer — دي كنترولرز Blade كلاسيكية (guard `platform` منفصل تمامًا)، فمفيش JSON API هنا أصلًا.

- شغّل **"14. Platform Auth → Get Login Page (capture CSRF)"** الأول — بيسحب الـ `_token` من صفحة اللوجن ويحفظه في `platform_csrf`.
- بعدين **"Login"** — بيرجّع 302 + session cookie، وPostman بيحتفظ بالكوكي تلقائي (cookie jar بتاعه) لباقي الريكوستات.
- أي ريكوست POST/PUT/PATCH/DELETE في المجلدات 13-15 معاه **pre-request script** بيسحب توكن CSRF جديد تلقائيًا قبل ما يبعت (من صفحة `/platform/tenants` — أي صفحة مسجّل دخولها كفاية لإن الـ token واحد للـ session كله مش لكل صفحة). مش محتاج تعمل حاجة يدوي.
- بعض الريكوستات (زي "Get Edit Tenant Form"، "Delete Permission") محتاجة id يدوي (`last_tenant_id`, `last_role_id`, `last_permission_id`) — مفيش auto-capture ليهم لإن صفحات الإنشاء بترجّع redirect للقائمة مش JSON فيه الـ id. جيب الـ id من ريسبونس "List..." وحطه في الـ variable يدوي. (نفس الكلام على `last_vehicle_document_id`.)

## قاعدة التحديث — أي endpoint جديد لازم يتضاف هنا فورًا

نفس اللحظة اللي بتضيف فيها route جديد في `routes/api.php` أو `routes/platform.php`:

1. حدد المجلد المناسب (أو أنشئ مجلد رقم جديد لو موديول جديد بالكامل — الموديولات التينانتية تتضاف قبل مجلدات الـ Platform مع إعادة ترقيمها، زي ما اتعمل مع "6. Vehicles" و"7. Assets" و"8. Intermediate Facilities" و"9. Services" و"10. Contractors" و"11. Client Companies" و"12. Client Projects").
2. الـ API tenant requests: من غير auth إضافي (بيورث الـ Bearer من الـ collection root تلقائي) إلا لو الـ endpoint استثنائي (زي login).
3. الـ Platform requests: لازم `"auth": {"type": "noauth"}` على مستوى المجلد (موجود بالفعل)، وأي POST/PUT/PATCH/DELETE لازم يحمل نفس الـ pre-request script بتاع تجديد CSRF (انسخه من أي ريكوست مشابه في المجلدات 14-16).
4. لو الـ endpoint بيرجّع id جديد مفيد لريكوستات تانية (زي Create)، ضيف test script بسيط يحفظه في collection variable جديد بنفس نمط `last_user_id`/`last_driver_id`.
5. بعد أي تعديل، افتح الملف في Postman وجرّب الـ flow كامل مرة، وبعدين اعمل Export تاني فوق نفس الملف ده (مش ملف جديد) عشان الـ git diff يفضل نضيف.

## ملاحظات

- كل الـ multipart requests (Create/Update User & Driver & Vehicle & Client Company) فيها حقول `photo`/`photo_front`/`photo_back`/`*_attachment` معطّلة (`disabled`) بشكل افتراضي — فعّلها يدوي واختار ملف لو عايز تجرب رفع صورة/مستند فعليًا.
- **الـ exports بتحترم نفس فلاتر القائمة وبحثها** (`search` + فلاتر كل قائمة — كلهم params معطّلة في الطلبات، فعّلهم عشان تجرّب)؛ نفس الـ`filteredQuery()` اللي بتستخدمه `index`، فالملف بيحتوي بالظبط الصفوف اللي تظهر على الشاشة.
- الـ exports (`/users/export`, `/drivers/export`, `/vehicles/export`, `/assets/export`, `/asset-capacity-categories/export`, `/companies/export`, `/projects/export`, `/main-services/export`) بترجّع binary (xlsx/pdf) — في Postman دوس "Save Response" أو استخدم تبويب "Send and Download" بدل "Send" العادي عشان تقدر تفتح الملف.
- مستندات السائق/المركبة (`/drivers/{id}/documents/{type}`، `/vehicles/{id}/documents/{doc}/download`) بترجع 404 لو مفيش ملف مرفوع فعليًا للمستند ده — طبيعي، مش باگ.
- **حسابات العملاء** (مجلد 4: "Create Client Account" / "Update Client Account" / "Download Signature / Stamp") — بتتنشئ من `POST /client-users` بس (`POST /users` بيرفض أدوار العميل)، وتعديلها من `PATCH /client-users/{id}` (وتعديلها من `PATCH /users/{id}` بيرجّع 403). صورة التوقيع والختم على قرص خاص وبتتنزّل من `/users/{id}/images/{signature|stamp}` بس. ممثل الشركة/المشروع (`representative_id`) حقل اختياري في طلبات تعديل الشركة وإنشاء/تعديل المشروع.
- **التعاقدات (مجلد "Purchase Orders" من غير ترقيم — المرحلة 3، الجزء 6.2):** `/purchase-orders` (قائمة/إحصائيات/إنشاء/عرض/تعديل/تفعيل وتعطيل/تنزيل المرفق/تصدير). إنشاء وتعديل وتعطيل = مدير النظام فقط، وعرض وتصدير = مدير النظام والمراقب، ومدير مشروعات العميل يشوف تعاقدات مشروعاته بس (من غير تصدير)، ومدخل البيانات مالوش أي وصول حتى العرض (403). رقم التعاقد = اختصار الشركة + الرقم الورقي (`ALN-1200`). `last_purchase_order_id` بيتحفظ تلقائيًا من "Create Purchase Order". المجلدات من غير ترقيم لحد نهاية المرحلة، ونسخة العميل (`build-p2-collection.php`) بتتولّد مرة واحدة عند التكامل.
- كل حقول الأرقام في فورم المركبة (`plate_numbers`، أرقام الوثائق، رقم التصريح) بتقبل أرقام فقط — أي حروف بترجّع 422.

## نسخة المرحلة الأولى لـ gcm.digitswat.com

- **`GCM-Portal-Phase1.postman_collection.json`** — المرحلة الأولى بس: Auth / Profile / Roles / Users / Drivers / Vehicles / Assets (مجلدات 1-7). **من غير أي حاجة تخص الـPlatform أو تعدد الشركات (multi-tenant)** ومن غير المنشآت الوسيطة وشركات العملاء والمشروعات، وأسماء المتغيرات والأوصاف اتنضّفت من أي إشارة ليهم. متولّدة من الـcollection الكاملة، فلو اتغيّرت حاجة في المرحلة الأولى حدّث الاتنين.
- **`GCM-Portal-Phase1-digitswat.postman_environment.json`** — `base_url` = `https://gcm.digitswat.com`، و`auth_email`/`auth_password` **فاضيين عمدًا** — املاهم من Postman بعد الـimport ومتحطهمش في الملف قبل ما تعمل commit. شغّل "1. Auth ← Login" الأول (بيحفظ `auth_token` تلقائي).

## نسخة العميل — المرحلتين مع بعض، من غير الـPlatform

- **`GCM-Portal-Client.postman_collection.json`** — كل الـ endpoints التينانتية (مجلدات 1-13: Auth / Me / Roles / Users / Drivers / Vehicles / Assets / Intermediate Facilities / Services / Contractors / Client Companies / Client Projects / General Settings) — **من غير مجلدات الـPlatform (Super Admin) خالص ومن غير أي إشارة لتعدد الشركات (tenant)**. أسماء المتغيرات `auth_email` / `auth_password` / `auth_token` (زي نسخة المرحلة الأولى)، وأمثلة الإيميلات على `example.com`، والأوصاف اتنضّفت من أسماء الكلاسات والمراجع الداخلية ومن أي إشارة لأسبوع/مرحلة/إصدار FRD.
- **`GCM-Portal-Client-digitswat.postman_environment.json`** — `base_url` = `https://gcm.digitswat.com`، و`auth_email`/`auth_password` **فاضيين عمدًا** — العميل بيملاهم بنفسه بعد الـimport. شغّل "1. Auth ← Login" الأول (بيحفظ `auth_token` تلقائي).
- **النسخة دي مشتقة من `GCM-Portal.postman_collection.json` بسكريبت** (`build-client-collection.php`، محفوظ برّه الريبو في `D:\personal\GCM\dev-tools\postman\` — مش جوه المشروع). أي endpoint أو وصف يتغيّر في الكاملة اعمل الـbuild تاني بدل التعديل اليدوي، وبعدها اتأكد إن مفيش كلمات tenant/platform/super/FRD V0 اتسربت (السكريبت بيطبع مراجعة قبل/بعد للأوصاف اللي اتعدّلت).
- **رمز التحقق OTP وتذكير العميل (مجلد "Verification code (OTP) & client reminder" من غير ترقيم — الجزء 7.3):** `POST /otp/send` → الرمز بييجي على إيميل الحساب (مع `MAIL_MAILER=log` اقراه من `storage/logs/laravel.log`) → `POST /otp/verify` بالرمز، ولحد ما يتحقق أي انتقال حساس في الرحلات (لما يتبني في 7.4) بيرجّع 403 `otp_required`. التحقق مربوط بالتوكن الحالي (توكن جديد = رمز جديد). و`POST /trips/{{last_trip_id}}/remind-client` تذكير العميل بالاعتماد (مدير النظام/مدخل البيانات، من غير رمز، فاصل ساعة بين تذكيرين). `last_trip_id` متغيّر يدوي لحد ما تتبني قايمة الرحلات.
- **قوائم فورم الرحلة (مجلد "Trip form options" من غير ترقيم — الجزء 7.2):** 13 طلب GET على `/trips/options/*` (سائقين، مركبات السائق، شركات، مشروعات، تعاقدات المشروع، خدمات أساسية/فرعية، السعة والكمية الافتراضية، حاويات ذهاب/رجوع، منشآت، المعتمد الافتراضي من العميل ومن GCM)، كل طلب معاه **مثال رد فعلي** (متاخد من بيانات التستات). محتاجين `last_driver_id`/`last_company_id`/`last_project_id`/`last_vehicle_id`/`last_main_service_id`/`last_sub_service_id`/`last_asset_id` (من طلبات القوايم في المجلدات التانية). **مش متضاف لنسخة `GCM-Portal-P2`** (نسخة تسليم المرحلة 2؛ أوصافه فيها مراجع داخلية) — إضافة مجلدات المرحلة 3 لنسخة العميل قرار لوحده.
- **الرحلات (مجلد "Trips" من غير ترقيم — الجزء 7.4):** 11 طلب: القايمة بالفلاتر والبحث، العدّادات، التصدير، التفاصيل، الإنشاء (بيحفظ `last_trip_id`)، التعديل (`POST` + `_method=PATCH`)، الاعتماد النهائي، قبول/رفض اعتراض العميل، الإلغاء، وفتح ملف الرحلة. كل طلب بيرجّع حاجة معاه **مثال رد فعلي** (من بيانات التستات)، والانتقالات اللي عليها رمز تحقق معاها مثال `403 otp_required` كمان: الاعتماد النهائي ورفض الاعتراض والإلغاء محتاجين `POST /otp/send` ثم `POST /otp/verify` الأول (مجلد الـOTP). **مش متضاف لنسخة `GCM-Portal-P2`** زي «Trip form options».
