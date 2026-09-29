# GCM Portal — Postman Collection

مجموعة Postman تغطي كل الـ endpoints الشغالة فعليًا في المشروع حتى الآن (الأسبوع 1 + 2 + موديولات المركبات ومجمع الأصول وإدارة السائقين من الأسبوع 3). بتتحدث أول بأول مع كل خاصية جديدة — راجع "قاعدة التحديث" تحت.

## الملفات

- `GCM-Portal.postman_collection.json` — المجموعة نفسها (12 مجلد).
- `GCM-Portal-Local.postman_environment.json` — إعدادات بيئة التطوير المحلي (بيانات الدخول الافتراضية من الـ seeders).

## الاستيراد

1. Postman → Import → اسحب الملفين التنين.
2. من قائمة الـ Environment أعلى يمين Postman، اختار **"GCM Portal - Local"**.
3. تأكد إن السيرفر شغال على `http://localhost:8000` (أو عدّل `base_url` في الـ environment لو مختلف).

## نظامين مصادقة مختلفين تمامًا — مهم تفهمهم قبل ما تستخدم أي مجلد

### 1. تينانت (`/api/v1/*`) — Bearer Token

المجلدات 1-8 (Auth/Me/Roles/Users/Drivers/Vehicles/Assets/Intermediate Facilities). الـ collection معمول عليها auth من نوع Bearer على مستوى الـ root بقيمة `{{tenant_token}}`.

- شغّل **"1. Auth (Tenant) → Login"** الأول. Postman مش هيبقى أبدًا من ضمن `SANCTUM_STATEFUL_DOMAINS`، فالسيرفر هيرجّعله `token` (نفس المسار اللي هيتستخدم لموبايل السائق مستقبلًا) بدل session cookie. الـ test script بتاع الريكوست بيحفظ التوكن تلقائي في `tenant_token`.
- من بعدها أي ريكوست في المجلدات 2-8 هيبعت الـ Authorization header تلقائي.
- `last_user_id` و`last_driver_id` و`last_vehicle_id` و`last_vehicle_category_id` و`last_asset_id` و`last_asset_capacity_category_id` و`last_facility_id` بيتحفظوا تلقائيًا من ريسبونس "Create User"/"Create Driver"/"Create Vehicle"/"Create Asset"/"Create Capacity Category"/"Create Facility" عشان تقدر تكمل على طول بـ Show/Update من غير ما تنسخ id يدوي. (`last_vehicle_document_id` بيتحط يدوي من ريسبونس "Show Vehicle" عشان تجرب "Download Document".)

### 2. Platform / Super Admin (`/platform/*`) — Session + CSRF

المجلدات 9-12. **مش** Bearer — دي كنترولرز Blade كلاسيكية (guard `platform` منفصل تمامًا)، فمفيش JSON API هنا أصلًا.

- شغّل **"9. Platform Auth → Get Login Page (capture CSRF)"** الأول — بيسحب الـ `_token` من صفحة اللوجن ويحفظه في `platform_csrf`.
- بعدين **"Login"** — بيرجّع 302 + session cookie، وPostman بيحتفظ بالكوكي تلقائي (cookie jar بتاعه) لباقي الريكوستات.
- أي ريكوست POST/PUT/PATCH/DELETE في المجلدات 10-12 معاه **pre-request script** بيسحب توكن CSRF جديد تلقائيًا قبل ما يبعت (من صفحة `/platform/tenants` — أي صفحة مسجّل دخولها كفاية لإن الـ token واحد للـ session كله مش لكل صفحة). مش محتاج تعمل حاجة يدوي.
- بعض الريكوستات (زي "Get Edit Tenant Form"، "Delete Permission") محتاجة id يدوي (`last_tenant_id`, `last_role_id`, `last_permission_id`) — مفيش auto-capture ليهم لإن صفحات الإنشاء بترجّع redirect للقائمة مش JSON فيه الـ id. جيب الـ id من ريسبونس "List..." وحطه في الـ variable يدوي. (نفس الكلام على `last_vehicle_document_id`.)

## قاعدة التحديث — أي endpoint جديد لازم يتضاف هنا فورًا

نفس اللحظة اللي بتضيف فيها route جديد في `routes/api.php` أو `routes/platform.php`:

1. حدد المجلد المناسب (أو أنشئ مجلد رقم جديد لو موديول جديد بالكامل — الموديولات التينانتية تتضاف قبل مجلدات الـ Platform مع إعادة ترقيمها، زي ما اتعمل مع "6. Vehicles" و"7. Assets").
2. الـ API tenant requests: من غير auth إضافي (بيورث الـ Bearer من الـ collection root تلقائي) إلا لو الـ endpoint استثنائي (زي login).
3. الـ Platform requests: لازم `"auth": {"type": "noauth"}` على مستوى المجلد (موجود بالفعل)، وأي POST/PUT/PATCH/DELETE لازم يحمل نفس الـ pre-request script بتاع تجديد CSRF (انسخه من أي ريكوست مشابه في المجلدات 9-11).
4. لو الـ endpoint بيرجّع id جديد مفيد لريكوستات تانية (زي Create)، ضيف test script بسيط يحفظه في collection variable جديد بنفس نمط `last_user_id`/`last_driver_id`.
5. بعد أي تعديل، افتح الملف في Postman وجرّب الـ flow كامل مرة، وبعدين اعمل Export تاني فوق نفس الملف ده (مش ملف جديد) عشان الـ git diff يفضل نضيف.

## ملاحظات

- كل الـ multipart requests (Create/Update User & Driver & Vehicle) فيها حقول `photo`/`photo_front`/`photo_back`/`*_attachment` معطّلة (`disabled`) بشكل افتراضي — فعّلها يدوي واختار ملف لو عايز تجرب رفع صورة/مستند فعليًا.
- **الـ exports بتحترم نفس فلاتر القائمة وبحثها** (`search` + فلاتر كل قائمة — كلهم params معطّلة في الطلبات، فعّلهم عشان تجرّب)؛ نفس الـ`filteredQuery()` اللي بتستخدمه `index`، فالملف بيحتوي بالظبط الصفوف اللي تظهر على الشاشة.
- الـ exports (`/users/export`, `/drivers/export`, `/vehicles/export`, `/assets/export`, `/asset-capacity-categories/export`) بترجّع binary (xlsx/pdf) — في Postman دوس "Save Response" أو استخدم تبويب "Send and Download" بدل "Send" العادي عشان تقدر تفتح الملف.
- مستندات السائق/المركبة (`/drivers/{id}/documents/{type}`، `/vehicles/{id}/documents/{doc}/download`) بترجع 404 لو مفيش ملف مرفوع فعليًا للمستند ده — طبيعي، مش باگ.
- كل حقول الأرقام في فورم المركبة (`plate_numbers`، أرقام الوثائق، رقم التصريح) بتقبل أرقام فقط — أي حروف بترجّع 422.
