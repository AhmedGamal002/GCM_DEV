# GCM Portal — Postman Collection

مجموعة Postman تغطي كل الـ endpoints الشغالة فعليًا في المشروع حتى الآن (الأسبوع 1 + 2 + جزء إدارة السائقين من الأسبوع 3). بتتحدث أول بأول مع كل خاصية جديدة — راجع "قاعدة التحديث" تحت.

## الملفات

- `GCM-Portal.postman_collection.json` — المجموعة نفسها (9 مجلدات).
- `GCM-Portal-Local.postman_environment.json` — إعدادات بيئة التطوير المحلي (بيانات الدخول الافتراضية من الـ seeders).

## الاستيراد

1. Postman → Import → اسحب الملفين التنين.
2. من قائمة الـ Environment أعلى يمين Postman، اختار **"GCM Portal - Local"**.
3. تأكد إن السيرفر شغال على `http://localhost:8000` (أو عدّل `base_url` في الـ environment لو مختلف).

## نظامين مصادقة مختلفين تمامًا — مهم تفهمهم قبل ما تستخدم أي مجلد

### 1. تينانت (`/api/v1/*`) — Bearer Token

المجلدات 1-5 (Auth/Me/Roles/Users/Drivers). الـ collection معمول عليها auth من نوع Bearer على مستوى الـ root بقيمة `{{tenant_token}}`.

- شغّل **"1. Auth (Tenant) → Login"** الأول. Postman مش هيبقى أبدًا من ضمن `SANCTUM_STATEFUL_DOMAINS`، فالسيرفر هيرجّعله `token` (نفس المسار اللي هيتستخدم لموبايل السائق مستقبلًا) بدل session cookie. الـ test script بتاع الريكوست بيحفظ التوكن تلقائي في `tenant_token`.
- من بعدها أي ريكوست في المجلدات 2-5 هيبعت الـ Authorization header تلقائي.
- `last_user_id` و`last_driver_id` بيتحفظوا تلقائيًا من ريسبونس "Create User"/"Create Driver" عشان تقدر تكمل على طول بـ Show/Update من غير ما تنسخ id يدوي.

### 2. Platform / Super Admin (`/platform/*`) — Session + CSRF

المجلدات 6-9. **مش** Bearer — دي كنترولرز Blade كلاسيكية (guard `platform` منفصل تمامًا)، فمفيش JSON API هنا أصلًا.

- شغّل **"6. Platform Auth → Get Login Page (capture CSRF)"** الأول — بيسحب الـ `_token` من صفحة اللوجن ويحفظه في `platform_csrf`.
- بعدين **"Login"** — بيرجّع 302 + session cookie، وPostman بيحتفظ بالكوكي تلقائي (cookie jar بتاعه) لباقي الريكوستات.
- أي ريكوست POST/PUT/PATCH/DELETE في المجلدات 7-9 معاه **pre-request script** بيسحب توكن CSRF جديد تلقائيًا قبل ما يبعت (من صفحة `/platform/tenants` — أي صفحة مسجّل دخولها كفاية لإن الـ token واحد للـ session كله مش لكل صفحة). مش محتاج تعمل حاجة يدوي.
- بعض الريكوستات (زي "Get Edit Tenant Form"، "Delete Permission") محتاجة id يدوي (`last_tenant_id`, `last_role_id`, `last_permission_id`) — مفيش auto-capture ليهم لإن صفحات الإنشاء بترجّع redirect للقائمة مش JSON فيه الـ id. جيب الـ id من ريسبونس "List..." وحطه في الـ variable يدوي.

## قاعدة التحديث — أي endpoint جديد لازم يتضاف هنا فورًا

نفس اللحظة اللي بتضيف فيها route جديد في `routes/api.php` أو `routes/platform.php`:

1. حدد المجلد المناسب (أو أنشئ مجلد رقم جديد لو موديول جديد بالكامل، زي "10. Fleet" لما نبدأ فيه).
2. الـ API tenant requests: من غير auth إضافي (بيورث الـ Bearer من الـ collection root تلقائي) إلا لو الـ endpoint استثنائي (زي login).
3. الـ Platform requests: لازم `"auth": {"type": "noauth"}` على مستوى المجلد (موجود بالفعل)، وأي POST/PUT/PATCH/DELETE لازم يحمل نفس الـ pre-request script بتاع تجديد CSRF (انسخه من أي ريكوست مشابه في المجلدات 7-9).
4. لو الـ endpoint بيرجّع id جديد مفيد لريكوستات تانية (زي Create)، ضيف test script بسيط يحفظه في collection variable جديد بنفس نمط `last_user_id`/`last_driver_id`.
5. بعد أي تعديل، افتح الملف في Postman وجرّب الـ flow كامل مرة، وبعدين اعمل Export تاني فوق نفس الملف ده (مش ملف جديد) عشان الـ git diff يفضل نضيف.

## ملاحظات

- كل الـ multipart requests (Create/Update User & Driver) فيها حقول `photo`/`*_attachment` معطّلة (`disabled`) بشكل افتراضي — فعّلها يدوي واختار ملف لو عايز تجرب رفع صورة/مستند فعليًا.
- الـ exports (`/users/export`, `/drivers/export`) بترجّع binary (xlsx/pdf) — في Postman دوس "Save Response" أو استخدم تبويب "Send and Download" بدل "Send" العادي عشان تقدر تفتح الملف.
- مستندات السائق (`/drivers/{id}/documents/{type}`) بترجع 404 لو مفيش ملف مرفوع فعليًا لنوع المستند ده — طبيعي، مش باگ.
