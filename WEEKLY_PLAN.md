# GCM Portal — خطة أسبوعية تفصيلية (8 أسابيع)

> مرجع تنفيذي مكمّل لملف `ARCHITECTURE.md`. كل أسبوع له: الأهداف، مهام الباك اند، ربط Vuexy، معيار القبول (Definition of Done)، والمخاطر/الملاحظات الواجب الانتباه لها.

---

## المرحلة 1: البنية التحتية + الموارد التشغيلية الأساسية (أسابيع 1-3)

### الأسبوع 1 — Tenant Infrastructure + Auth ✅ خلص

**مهام الباك اند:**
- تثبيت Laravel 12، Sanctum، Spatie Permission، إعداد `.env`
- Migration: `tenants`, `platform_admins` (جدول منفصل تمامًا، بدون tenant_id)
- `BelongsToTenant` trait + Global Scope + `EnsureTenant` middleware (تحديد الـ tenant من subdomain أو من المستخدم بعد تسجيل الدخول)
- Auth Guards: `web` (Sanctum cookie، للـ tenant users) و `platform` (منفصل، للـ Super Admin)
- Seeder: أدوار جانب GCM فقط — `system_admin`, `data_entry`, `auditor`, `driver`
- Migration: `users` (بعمود `tenant_id` إلزامي)
- Route: تسجيل دخول لكل من الـ Tenant users والـ Super Admin (مسارين منفصلين)

**ربط Vuexy:**
- صفحة تسجيل الدخول (`auth-login`) → API حقيقي
- صفحة استعادة كلمة المرور → API حقيقي
- Dashboard shell عام (بدون بيانات — فقط للتأكد إن الـ session شغالة)
- Topbar: عرض اسم الـ Tenant + اسم المستخدم + دوره

**معيار القبول:**
- تسجيل دخول ناجح لأربعة أدوار GCM + Super Admin منفصل تمامًا
- اختبار آلي (Feature test) يثبت إن مستخدم Tenant A لا يقدر يرى بيانات Tenant B حتى لو حاول تلاعب بالـ ID مباشرة في الطلب
- Session cookie شغال على تحميل الصفحة وعلى نداء API في نفس الوقت (CSRF يعمل)

**مخاطر:** لو اختبار عزل الـ Tenant فشل هنا، **لا تكمل** للأسبوع التالي قبل حله — أي كود يُبنى فوق ثغرة عزل هيتضاعف الخطر.

---

### الأسبوع 2 — إدارة المستخدمين + الأدوار والصلاحيات ✅ خلص

> **تعديل عن الخطة الأصلية:** أسطول المركبات اتنقل للأسبوع 3 (هيتضم مع محتواه الأصلي: السائقين + مجمع الأصول، ومن المحتمل يُبنى بالتوازي بواسطة زميل تاني ويترجع للمراجعة). الأسبوع ده بقى مخصص بالكامل لإدارة المستخدمين + الأدوار والصلاحيات.

> **حالة الأسبوع: كل معايير القبول محققة (70 Feature test شغالة) + إضافات تانية اتعملت بعد جولات مراجعة/ملاحظات حية، وتوثيقها تحت.**

**قرار جديد — ملكية إدارة الأدوار والصلاحيات:**
- الـ CRUD الكامل للأدوار والصلاحيات (إنشاء/تعديل/حذف دور، تحديد صلاحيات كل دور) **مقصور على Super Admin (Platform) فقط** — نفس منطق كون الأدوار مفهوم على مستوى المنتج مش على مستوى كل Tenant لوحده (Spatie Permission بيفرض تفرّد اسم الدور أصلاً، فمفيش داعي لتكراره لكل Tenant).
- **System Admin (تينانت GCM)** يشوف قائمة الأدوار بشكل طبيعي (مثلاً عند إسناد دور لمستخدم جديد)، لكن **مفيش أي صلاحية تعديل** على الأدوار/الصلاحيات نفسها — أي محاولة وصول لـ endpoints الإدارة ترجع 403.
- **صفحة "Roles & Permissions" في القائمة الجانبية بتظهر لـ Super Admin بس** — مش موجودة خالص في قائمة System Admin.

**مهام الباك اند:**
- CRUD كامل: `UserController` (API) — إنشاء مستخدم بدور GCM محدد، تعطيل/تفعيل، تصدير (PDF/Excel)
- `Platform\RoleController` (API، تحت `auth:platform` فقط) — CRUD كامل للأدوار وربطها بالصلاحيات
- Endpoint للقراءة فقط (`GET /api/v1/roles`) متاح لـ `auth:sanctum` — يرجع قائمة الأدوار بس، بدون أي إمكانية تعديل، يُستخدم في dropdown إسناد الدور
- Policy: `UserPolicy` (تمنع أي محاولة CRUD على الأدوار من غير guard `platform`)
- توسعة `GET /api/v1/me` (موجود من الأسبوع 1) بـ `PATCH /api/v1/me` (تعديل الاسم) و`PUT /api/v1/me/password` (تغيير كلمة السر الذاتي — يتطلب كلمة السر الحالية) — الملف الشخصي الخاص بالمستخدم نفسه، مش إدارة مستخدمين تانيين

**ربط Vuexy:**
- صفحة قائمة المستخدمين (`app-user-list`) → API حقيقي، فلاتر (الدور/الحالة) تعمل فعليًا
- صفحة تفاصيل المستخدم → API حقيقي
- صفحة **عرض الملف الشخصي** (`pages-profile-user` + `pages-account-settings-account` الجاهزتين تصميميًا في القالب) → API حقيقي (`/api/v1/me`) لكل مستخدم مسجّل دخول (أي دور) — عرض الاسم/الإيميل/الدور/الـ Tenant + تعديل الاسم + تغيير كلمة السر
- صفحات Access Roles / Access Permission الجاهزة تصميميًا في القالب → تتربط بـ Platform API، وتُبنى تحت `routes/platform.php` مش `routes/web.php`
- القائمة الجانبية: رابط "Roles & Permissions" يظهر لـ Super Admin بس، ومحذوف تمامًا من قائمة System Admin/باقي أدوار الـ Tenant
- رابط "My Profile" في الـ navbar dropdown (موجود شكليًا من الأسبوع 1) يتربط بصفحة الملف الشخصي الحقيقية بدل الرابط الوهمي الحالي

**معيار القبول:**
- ✅ إنشاء/تعديل/تعطيل مستخدم من الواجهة (مع إسناد دور) فعليًا يعكس نفسه في القاعدة
- ✅ Super Admin يقدر ينشئ/يعدّل/يحذف دور وصلاحياته من لوحته
- ✅ اختبار آلي يثبت إن أي محاولة من System Admin (تينانت) للوصول لـ role management endpoints بترجع 403، وإن قائمة الأدوار (read-only) لسه متاحة له
- ✅ أي مستخدم (أي دور) يقدر يفتح ملفه الشخصي، يعدّل اسمه، ويغيّر كلمة سره (بعد إدخال كلمة السر الحالية صح)

**إضافات اتعملت بعد المراجعة الحية (خارج نطاق الخطة الأصلية المكتوبة فوق):**

- **الجدول والفورم بقوا مطابقين لـ `GCM_Portal_NewSystem_FRD_V01.06.docx` حرفيًا** (اتقري بنجاح عن طريق فك ضغط الـ docx واستخراج `word/document.xml`، بعد ما نسخة الـ PDF فشلت تديني نص عربي سليم):
  - أعمدة جدول القائمة: الرقم التعريفي / الاسم / التبعية / اسم الجهة التابع لها / الدور الوظيفي / حالة الحساب / الإجراءات.
  - فورم الإنشاء صفحة منفصلة (`/app/user/add`) بتصميم "Multi Column with Form Separator" (نفس نمط `/form/layouts-vertical`)، مقسّم لفروع حسب الدور — **مُنفَّذ فرعي GCM والسائق (تابع GCM بس) دلوقتي**، فروع الشركة العميلة والمتعهد **مؤجلة عمدًا للأسبوع 4-5** لعدم وجود الكيانات دي لسه.
- **الرقم التعريفي (`users.code`)**: عمود جديد، رقم عشوائي من 6 خانات (مش الـ `id` الخام)، فريد لكل tenant، بيتولّد تلقائيًا في `User::booted()` — راجع ARCHITECTURE.md §3.8.
- **صفحتين منفصلتين للعرض والتعديل** (`/app/user/view/{id}`, `/app/user/edit/{id}`) بدل الـ offcanvas الأصلي — نفس نمط كارت "User View" الكلاسيكي بتاع Vuexy (صورة دائرية كبيرة + قائمة "Details")، وكارت "Account Status" منفصل تحت (نفس نمط كارت "Delete Account" الأصلي) بدل زرار تفعيل/تعطيل صغير جوه الكارت الرئيسي.
- **صورة المستخدم**: بترفع وتتخزن فعليًا (`Storage::disk('public')`)، ورابط عرضها بيتولّد من `APP_URL` — راجع الملاحظة الحرجة عن `APP_URL`/`--no-reload` في ARCHITECTURE.md §3.9.
- **CRUD كامل لإدارة الـ Tenants من لوحة Super Admin** (`/platform/tenants`) — إنشاء شركة جديدة + أول `system_admin` بتاعها في نفس الفورم، تعديل، تعطيل/تفعيل (بدون حذف فعلي، بنفس قاعدة "no hard delete"). **ده مكنش في الخطة الأصلية** — كان إنشاء الـ tenant الوحيد بيتم يدويًا عن طريق seeder/tinker، واتضاف بعد ما اتلاحظ إن مفيش طريقة للسوبر أدمن يضيف عميل جديد من الواجهة.
- **قائمة Super Admin الجانبية بقت مخصصة** (Dashboard + Tenants + Roles & Permissions بس) بدل ما تعرضله قائمة التينانت الكاملة (Users/Fleet/كل صفحات الديمو) اللي روابطها مكنتش هتشتغل ليه أصلاً — راجع ARCHITECTURE.md §3.6 (تحديث).
- **مراجعة أمان وأداء شاملة**: XSS مخزّن (stored) في عمود الاسم بجدول المستخدمين اتصلح (escaping قبل الحقن في HTML)، N+1 query لجلب أدوار كل مستخدم اتصلح (eager loading). راجع ARCHITECTURE.md §5 (تحديث) للقواعد العامة.
- تنضيف واجهة: شيل قسم "Misc" (Support/Documentation) من القائمة الجانبية، شيل الـ 4 روابط من الفوتر، شيل أيقونة الـ Template Customizer (gear) وتثبيت الثيم على Light + Semi Dark، الاقتصار على لغتين بس (عربي/إنجليزي) بدل الأربعة الأصليين.

---

### الأسبوع 3 — أسطول المركبات + السائقين + مجمع الأصول

> **يضم الآن محتوى الأسبوع 2 الأصلي (Fleet) + محتوى الأسبوع 3 الأصلي (السائقين + الأصول)** بعد نقل Fleet هنا.

> **حالة الأسبوع: ✅ الموديولات التلاتة (المركبات + السائقين + مجمع الأصول) مكتملة — 178 Feature test شغالة.** التفاصيل تحت لكل موديول.

> **حالة موديول المركبات: ✅ مكتمل.** **موديول مستقل top-level (زي Users) — مش تحت "Fleet":** `app/Domain/Vehicles/`، `tests/Feature/Vehicles/`، `/app/vehicle/{list,add,view,edit}`، عنصر قائمة جانبية "Vehicles" مسطّح (مش submenu). **بنية الملفات الجديدة (Part 4 من handoff الترتيب):** الـ views في `resources/views/tenant/vehicles/*.blade.php` (+ `exports/vehicles-pdf.blade.php`)، الـ web shell controllers في `app/Http/Controllers/Web/Vehicles/Vehicle{List,Add,Account}Controller.php`، والراوتات في `routes/tenant.php` (مطلوبة من `web.php`). مسارات وأسماء الراوتات ما اتغيّرتش. الـ API controllers (`Api/V1/Vehicle*`) والـ JS (`resources/assets/js/app-vehicle-*.js`) في مكانهم. Postman: مجلد "6. Vehicles" جوه `postman/GCM-Portal.postman_collection.json` (الكوليكشن المستقلة القديمة اندمجت واتحذفت). جداول `vehicle_categories` (عالمي، 5 أنواع مبذورة) + `asset_capacity_categories` (نسخة مصغّرة: جدول + Model + Seeder + endpoint قراءة فقط — CRUD كامل يتأجل لموديول الأصول) + `vehicles` + `vehicle_documents` (رخصة/حالة/فحص/تأمين + تصاريح دخول متكررة). `VehicleController` (index/store/show/update/status/stats/export/downloadDocument) + `VehiclePolicy` (system_admin+data_entry ينشئوا/يعدّلوا، auditor عرض/تصدير، deactivate = system_admin فقط) + Domain `app/Domain/Vehicles/{Actions,Exceptions}/*`. فورم الإضافة/التعديل يتشارك محرك JS واحد (`resources/assets/js/vehicle/form-core.js`) فيه: تحقق inline لكل حقل (نص خطأ تحت الحقل، client-side + mapping لأخطاء 422 من السيرفر)، وحقول أرقام فقط (رقم اللوحة + أرقام كل الوثائق + رقم التصريح — `regex:/^[0-9]+$/` + strip فوري في JS). تعديل المركبة بيحافظ على مرفقات تصاريح الدخول (upsert بالـ id مش delete-and-recreate). 29 Feature test (`tests/Feature/Vehicles/`، منهم 6 لفلترة سعة الحاوية المدمجة). **مؤجَّل عمدًا:** عمود/إحصائيات "عدد الرحلات" وجدول رحلات المركبة وحالة "في رحلة رقم..." — يعتمد على موديول Trip (أسبوع 7)، يُعرض `0`/حالة فارغة. المركبة هويتها اللوحة (لا عمود `code`). `contractor_id` بدون FK (أسبوع 5).

**مهام الباك اند:**
- Migration: `vehicle_categories`, `vehicles` — **عمود `contractor_id` بدون FK constraint** (راجع الملاحظة التقنية في ARCHITECTURE.md)
- `VehicleController` + `VehicleCategoryController` (API)
- Migration: `drivers` (بعمود `contractor_id` بدون FK)، `asset_capacity_categories` (بعمودي `capacity_cbm` و `capacity_ton` معًا + نوع الأصل المتوافق)، `assets` (عمود `status` enum: `available`/`on_maintenance`/`deactivated`، بعمود `contractor_id` بدون FK)
- `DriverController`, `AssetController`, `AssetCapacityCategoryController`
- منطق "لا يمكن تعطيل سائق أثناء رحلة جارية" و"لا يمكن تعطيل أصل أثناء وجوده مع سيارة/مشروع" (business rules من FRD)
- بعد الإنشاء: تعديل `AssetCapacityCategory` مقصور على الاسم فقط (السعة والنوع مقفولان)
- إحصائيات لوحة التحكم لكل من الحاويات والصهاريج بشكل منفصل: إجمالي متاح / في مشاريع / في صيانة / معطل
- Policy: `VehiclePolicy`

**ربط Vuexy:**
- صفحة Logistics Fleet كأساس لصفحة الأسطول — الجدول الجانبي يُربط بقائمة المركبات الحقيقية (الخريطة لسه ممكن تفضل demo لحد ما نوصل لموديول الرحلات)
- صفحة السائقين → API حقيقي
- صفحة مجمع الأصول (حاويات/صهاريج) → API حقيقي

**معيار القبول:**
- إنشاء تصنيف مركبات ومركبة مرتبطة به من الواجهة
- **مراجعة أمنية لنهاية المرحلة 1**: تشغيل كل الـ Feature tests الخاصة بعزل الـ Tenant على كل الموديولات المبنية لحد دلوقتي
- عرض تجريبي (Demo) كامل: تسجيل دخول، إدارة مستخدمين، أدوار وصلاحيات، أسطول، سائقين، أصول — كله شغال على بيانات حقيقية عبر Vuexy

**✅ "إدارة السائقين" — ما تم فعليًا (81 Feature test شغالة):**

- **جدول `drivers` منفصل، 1:1 مع `users`** (مش أعمدة إضافية على `users`) — لإن بيانات السائق (إقامة/رخصص/تأمين/تصاريح) مالهاش معنى لأي دور تاني. `contractor_id` موجود بدون FK زي ما هو موثق فوق. جدول `driver_entry_permits` منفصل (علاقة one-to-many) لإن السائق ممكن يكون عنده أي عدد من تصاريح الدخول أو صفر.
- **"إنشاء سائق" صفحة واحدة بتنشئ الـ User (دور `driver`) + الـ Driver profile + تصاريح الدخول** كلها في transaction واحدة — مش "اعمل مستخدم الأول وبعدين اربطه بسائق" — الـ FRD بيعامل ده كفورم واحد.
- **قسم "المركبة الافتراضية" من الـ FRD اتأجل عمدًا** (قرار مؤكد مع المستخدم) — محتاج `vehicles`/`vehicle_categories` اللي هي نص "الأسطول" في الأسبوع ده، ولسه مبنيينش.
- **⚠️ قرار أمان مهم: مستندات السائق (إقامة/رخصص/تأمين/تصاريح) بتتخزن على disk `local` (خاص)، مش `public`** — عكس صورة البروفايل تمامًا. دي مستندات رسمية حساسة، مايصحش يكون ليها رابط عام قابل للتخمين. بتترجع بس عن طريق route محمي بالـ Policy (`GET /api/v1/drivers/{driver}/documents/{type}`) بيتحقق من الصلاحية في كل طلب. راجع ARCHITECTURE.md §3.10.
- `DriverPolicy` نفس نمط `UserPolicy` بالظبط (system_admin بس).
- صفحات Vuexy: `/app/driver/add` (Multi Column Form Separator بـ6 أقسام + تصاريح دخول قابلة للتكرار بزرار "إضافة تصريح")، `/app/driver/view/{id}` و`/app/driver/edit/{id}` (نفس نمط صفحات المستخدمين + كروت تفصيلية للمستندات).
- رابط "Drivers" جديد في القائمة الجانبية.

**⚠️ تصحيحين بعد المراجعة (مهم — راجع لو حصل لبس تاني في جدول السائقين):**

1. **الجولة الأولى**: جدول `/app/driver/list` الأول كان بأعمدة مخترعة (تاريخ انتهاء الرخصة/التأمين) من غير زر تصدير. صلّحته بخطأ عشان يطابق جدول المستخدمين السبعة أعمدة (ID/الاسم/التبعية/الجهة/الدور/الحالة/الإجراءات) — **ده كان غلط برضو**.
2. **الجولة الثانية (الصح)**: الـ FRD فعليًا بيوصف **صفحة "إدارة السائقين" منفصلة تمامًا** تحت قسم "إدارة أسطول المركبات" (مش تحت قسم "إدارة المستخدمين" اللي فيه جدول الـ 7 أعمدة) — بمواصفات مختلفة كليًا:
   - **4 كروت إحصائية** أعلى الصفحة: إجمالي السائقين المتاحين / في رحلات / في إجازة / المعطلة حساباتهم (Reference: نمط `cards/analytics`).
   - **جدول بـ5 أعمدة بس**: الرقم التعريفي | اسم السائق | التبعية (لشركة GCM / لشركة متعهد) | توفر السائق (نشط/في إجازة/معطل) | التفاصيل — **من غير عمود "الجهة" ولا "الدور الوظيفي"** (الدور دايمًا "سائق" هنا، تكراره بلا فايدة).
   - فلاتر: التبعية + توفر السائق.
   - زر تصدير (Excel/PDF) — بنفس أعمدة الجدول (4 أعمدة، من غير Entity/Role).
   - زر "إنشاء مستخدم جديد سائق".

   راجع `frd_full.txt` (مستخرج من `word/document.xml`) السطر 553-573 لو احتجت ترجع للنص الأصلي. الأعمدة السبعة اللي في الجولة الأولى بتاعت جدول *المستخدمين العام* (`/app/user/list`) بس، مش السائقين.

3. **باگ حقيقي اتكشف أثناء اختبار الفلتر**: فلتر "في إجازة" (`on_vacation`) كان بيرجّع "No matching records found" **في الصفحتين (المستخدمين والسائقين)** حتى قبل أي تعديل خاص بالسائقين — لأن DataTables' `column().search()` بيقارن مع الـ **HTML المعروض** (الشارة الملوّنة "On Vacation") مش القيمة الخام `on_vacation` لما يكون فيه `render` callback. "active"/"deactivated" كانوا شغالين بالصدفة بس (النص المعروض بعد تجاهل حالة الأحرف بيتطابق مع القيمة الخام)، أما "on_vacation" (بمسافة في العرض "On Vacation" مقابل شرطة سفلية في القيمة الخام) فمكانش هيتطابق أبدًا. الحل: أي عمود `render` بيتفلتر عليه لازم يرجّع القيمة الخام لما `type` يكون `'filter'`/`'sort'`/`'type'`، والعرض المنسّق بس لما يكون `'display'` (أو undefined). اتصلح في الصفحتين، وده نمط لازم يتوقّع بيه في أي عمود DataTables تاني بعد كده فيه render + فلتر مع بعض.

بالمناسبة، تصدير المستخدمين نفسه كان فيه مشكلة أعمدة مشابهة (Email/Created At بدل Affiliation/Entity، والـ ID كان الـ `id` الخام مش `code`) — اتصلح في نفس الوقت.

**⚠️ باگ حرج منفصل تمامًا عن السائقين، اتكشف أثناء بناء Postman collection:** كل طلب بـ `Authorization: Bearer <token>` (مسار موبايل السائق المستقبلي) كان بيفشل بـ 500 على أي `/api/v1/*` endpoint تينانتي — مش حاجة خاصة بالسائقين، تأثيره عام على أي endpoint. راجع `ARCHITECTURE.md §3.11` للتفاصيل والحل (`app/Models/PersonalAccessToken.php` + تصحيح `EnsureTenant`) — اتصلح فورًا مع تست حماية حقيقي (`BearerTokenTenantAccessTest`)، مش بس تقرير.

**⚠️ باگ حقيقي رابع، اتكشف بملاحظة حية من المستخدم بعد كده:** صفحة/API "إنشاء مستخدم" العامة (`/app/user/add`, `POST /api/v1/users`) كانت بتسمح باختيار الدور `driver`، لكن `CreateUserAction` بينشئ صف `users` بس — من غير صف `drivers` المطلوب (إقامة/رخصة/تأمين). النتيجة: مستخدم بدور `driver` **مش ظاهر خالص** في صفحة إدارة السائقين (اللي بتقرا من جدول `drivers` مش من `users`). نفس المشكلة بالظبط كانت في `UpdateUserRequest` (تعديل مستخدم موجود لدور `driver`)، **وكانت موجودة حتى في بيانات الـ seeder التجريبية نفسها** (`driver@gcm.test` كان معمول له `assignRole('driver')` مباشرة من غير صف `Driver` — تأكدت بالفحص الفعلي على قاعدة البيانات).

**⚠️ أول حل كان غلط ومتصحح بعد ملاحظة تانية من المستخدم:** أول تصحيح شال خيار "Driver" بالكامل من فورم إنشاء مستخدم، وخلّى الإنشاء يمر **حصريًا** بـ `/api/v1/drivers`. المستخدم صحّح: الـ FRD فعليًا بيدي فورم إنشاء المستخدم العام خيار "Driver" كـ Category — البيانات الإضافية (إقامة/رخصة/تأمين) **مش إجبارية** وقت الإنشاء، ممكن تتعدّل بعدين. يعني المشكلة الحقيقية مكنتش "driver مسموح بيه هنا"، كانت "مفيش صف `Driver` بيتنشئ لما يتختار".

**الحل الصح:**
- `StoreUserRequest`: الدور `driver` **فضل مسموح بيه** في `roles.*` (زي الأصل، مطابق للـ FRD).
- `CreateUserAction`: بقى بيعمل `Driver::create(['user_id' => $user->id])` (صف فاضي — كل الأعمدة غير `user_id` nullable في الـ schema أصلًا) لو الدور `driver` — كده المستخدم بيظهر فورًا في صفحة السائقين، والتفاصيل تتضاف بعدين من صفحة تعديل السائق المخصصة.
- `resources/views/tenant/users/add.blade.php` + `app-user-add.js`: خيار "User Category" (GCM Staff / Driver) رجع زي ما كان بالظبط.
- **`UpdateUserRequest`/`UserPolicy::update()` فضلوا زي ما هم** (الدور `driver` لسه ممنوع من التعديل العام، وتعديل سائق موجود لسه 403) — الفرق بين الإنشاء والتعديل مقصود: الإنشاء بيضمن صف `Driver` فورًا (زي فوق)، لكن التحويل من دور تاني لـ `driver` عبر التعديل العام مالوش نفس الضمان، فلسه ممنوع؛ وتعديل بيانات سائق موجود (إقامة/رخصة/إلخ) دايمًا هيفضل حصريًا في صفحة السائقين.
- `database/seeders/DatabaseSeeder.php`: السائق التجريبي فضل بينشئله صف `Driver` فعلي (مش `assignRole()` لوحدها) — ده الجزء اللي فضل صح من الحل الأول.
- تستات: `tests/Feature/Users/DriverRoleExclusionTest.php` (4 تستات).
- **درس عام مُصحّح (تراجع بعد جولة تصحيح تالتة):** المستخدم رجّع يأكد بعدها، بعد ما راجع نص الـ FRD حرفيًا، إن إنشاء السائق **فعلاً منفصل تمامًا** كصفحة/قسم خاص بيه ("إدارة وانشاء حسابات السائقين" — Reference URL منفصل، فورم كامل خاص بيه) — مش category جوه فورم المستخدمين العام زي ما كان القرار المؤقت فوق. يعني **`StoreUserRequest` رجع يرفض `driver` تاني** (زي الحل الأول الأصلي)، و`CreateUserAction` رجع لشكله الأصلي (من غير إنشاء `Driver` تلقائي). الفصل الحقيقي: إنشاء سائق حصريًا عن طريق `/api/v1/drivers`، تعديله حصريًا عن طريق `/api/v1/drivers/{id}` (زي ما كان بالظبط من الأول) — راجع `ARCHITECTURE.md §5` للتفاصيل الكاملة والنص المقتبس من الـ FRD.

**✅ إضافة "المركبة الافتراضية – Default Car" لفورم السائق (بعد اكتمال موديول Vehicles):** قسم من الـ FRD كان مؤجّل عمدًا (Fleet/Vehicles ما كانوش مبنيين) — دلوقتي اتضاف كامل:
- Migration: `default_vehicle_id` (nullable FK لـ `vehicles`, `nullOnDelete`) على `drivers` + جدول pivot `driver_vehicle_categories` (many-to-many مع `vehicle_categories`، بدون tenant_id بتاعه — العزل بييجي transitively من `driver_id`).
- `StoreDriverRequest`/`UpdateDriverRequest`: `vehicle_category_ids` (إجباري، على الأقل تصنيف واحد) + `default_vehicle_id` (إجباري) + تحقق cross-field إن المركبة الافتراضية فعلاً تابعة لأحد التصنيفات المختارة.
- الفورم (Add/Edit): قسم جديد "2. Default Vehicle" — checkboxes للتصنيفات الخمسة (من `GET /api/v1/vehicle-categories`)، وselect للمركبة الافتراضية بيتفلتر ديناميكيًا (`GET /api/v1/vehicles?category=...&operational_status=active`) حسب أي تصنيفات متعلّمة.
- صفحة العرض: سطر "Default Vehicle" جديد في تفاصيل السائق.
- **⚠️ باگ حقيقي اتكشف بالتجربة الفعلية بالمتصفح (مش بالتستات!):** فحص "المركبة تنتمي للتصنيف المختار" كان بيستخدم `in_array(..., true)` (strict) — مقارنة `"3" !== 3` بتفشل دايمًا لإن الفورم الحقيقي (multipart/FormData) بيبعت كل حاجة كـ string، بعكس `postJson()` في التستات اللي بتحافظ على الأنواع الأصلية (JSON encoding). يعني التستات عدّت بنجاح رغم الباگ لإنها مكنتش بتحاكي إرسال فورم حقيقي. الحل: `in_array((int) $x, array_map('intval', $ids), true)`. **درس عام:** أي تحقق يعتمد على مقارنة IDs جايين من فورم HTML حقيقي لازم يُختبر بـ `->post()` بقيم **string** صراحة، مش `->postJson()` بس — JSON بيحافظ على الأنواع، الفورم الحقيقي لأ.
- تستات إضافية: `test_creating_a_driver_succeeds_when_ids_are_submitted_as_strings_like_a_real_form_does` (تتأكد فعليًا إنها كانت بتفشل قبل الإصلاح، مش افتراض) + `test_default_vehicle_must_belong_to_a_selected_qualified_category`.

**⚠️ باگين إضافيين اتكشفوا واتصلحوا أثناء المراجعة (مش حاجة من الجلسة دي أصلًا، من دمج موديول Vehicles):**
1. **`VehiclesExport` كانت misplaced/mis-namespaced** — الـ merge حط الملف في `app/Domain/Users/Exports/` بدل `app/Domain/Vehicles/Exports/` (تعارض rename-detection وقت الـ merge)، والـ namespace فيها لسه `App\Exports` (مش موجود) → تصدير المركبات كان بيرمي 500. اتصلح: نقل الملف + تصحيح الـ namespace + تحديث الـ import في `VehicleController`.
2. **باگ اختبار حرج في `EnsureTenant`**: كانت بتستخدم `Auth::guard('sanctum')->user()` (من إصلاح باگ الـ Bearer Token الأسبق) — الـ guard ده (`RequestGuard`) بيكاش المستخدم على مستوى الـ **guard instance نفسه**، مش الطلب. في التشغيل الحقيقي مفيش مشكلة (كل request عملية PHP جديدة)، لكن في PHPUnit Feature tests اللي بتعمل `actingAs($userA)` وبعدين `actingAs($userB)` في نفس الـ test method، الكاش بيفضل شايل $userA غلط — ظهر فعليًا في تست زميلك `VehicleUniquePlateTest` (كان بيرفض نفس رقم اللوحة في تينانتين مختلفين غلط). الحل: `EnsureTenant` بقت تتحقق من guard `web` مباشرة + تتحقق من bearer token يدويًا (`PersonalAccessToken::findToken()`) بدل ما تمر بالـ guard المكاش. راجع `EnsureTenant.php`'s docblock للتفاصيل الكاملة.

**✅ زر "Add User" بقى قائمة منسدلة (اقتراح من المستخدم، مطابق لنص الـ FRD حرفيًا):** الـ FRD بيوصف زر "انشاء مستخدم جديد" في صفحة المستخدمين العامة إنه بيفتح قائمة اختيارات (مستخدم GCM / مستخدم عميل / مستخدم متعهد / سائق)، كل واحد بيوديك لفورمه المخصص — مش زرار واحد بيودي لفورم واحد. اتنفّذ بنفس نمط DataTables Buttons "collection" الموجود بالفعل لزرار Export (`extend: 'collection'` مع `buttons: [...]` فرعية) في `app-user-list.js`. حاليًا خيارين بس (GCM Staff، Driver) لإن Client/Contractor لسه مبنيينش — هيتضافوا لنفس القائمة في أسبوع 4-5 من غير أي إعادة هيكلة. **نمط قابل لإعادة الاستخدام:** أي زر "Add X" مستقبلي بيؤدي لأكتر من فورم حسب نوع فرعي يستخدم نفس نمط الـ collection ده.

**✅ إصلاح باگ أداء/فقدان بيانات حرج (سؤال مباشر من المستخدم: "العميل عنده 7952 موظف، الجدول هيكون بطيء؟"):** جداول Users/Drivers/Vehicles الاتلاتة كانت client-side بالكامل (`per_page=1000` ثابت + DataTables بتعمل بحث/فرز/فلترة/pagination في المتصفح). مع عميل بآلاف الموظفين، ده مش بطء — **فقدان بيانات فعلي**: أي حد بعد أول 1000 صف مش هيظهر خالص، مش في الجدول ولا البحث ولا قوائم الفلترة. اتصلح بتحويل التلاتة لـ server-side processing حقيقي (`resources/assets/js/datatables-server-side.js` helper مشترك + `sort_by`/`sort_dir` على الباك اند بـ allowlist + `GET /api/v1/drivers/stats` endpoint جديد للكروت الإحصائية). لقينا كمان باگين حقيقيين مستخبيين وراء الفلترة العميل-سايد (فلتر Affiliation السائقين شكلي بالكامل، ومقارنة `Stringable === 'desc'` بترجع false دايمًا). 10 تستات جديدة + تحقق فعلي بالمتصفح (`read_network_requests`) إن كل طلب بيوصل بـ `per_page` صغير فعلي مش 1000. اتحقق منه كمان بحجم واقعي (3000 مركبة اتزرعت مؤقتًا للتجربة البصرية)، وده كشف باگ بحث منفصل: البحث بلوحة مركبة معروضة بالظبط (`AAA 0001`) كان بيفشل لإن البحث كان بيقارن `plate_letters`/`plate_numbers` كل عمود لوحده مش القيمة المجمّعة المعروضة — واتلاقى نفس الفئة في Users/Drivers (عمود `code` مش داخل مسار البحث خالص). التفاصيل الكاملة في `ARCHITECTURE.md §6`.

**✅ فحص صلاحيات المستخدم/السائق عند الدخول (سؤال مباشر من المستخدم: "هل احنا مراعيين المستخدم التابع لGCM أو السائق إنهم لما يدخلوا هيلاقوا إيه وصلاحياتهم مظبوط؟"):** القائمة الجانبية ماكنتش بتفلتر بالدور خالص (بس بـ `platformOnly` للسوبر أدمن) — أي مستخدم تينانت، حتى `driver` اللي مالوش أي وصول API لـ Users/Drivers/Vehicles، كان بيشوف نفس قائمة `system_admin` الكاملة، وكل رابط بيودّي لصفحة حقيقية بيانتها بترجع 403 بصمت (جدول فاضي زي أي جدول فاضي عادي، من غير أي إشارة إن السبب صلاحيات). اتصلح بإضافة allowlist `"roles"` لعناصر القائمة (`resources/menu/verticalMenu.json`/`horizontalMenu.json`) مطابقة لـ Policies الفعلية، فلترة في `MenuComposer`، وتحسين `datatables-server-side.js` عشان يعرض رسالة "لا تملك صلاحية عرض هذه البيانات" بدل جدول فاضي غامض لو الطلب رجع 403 (دفاع إضافي لأي حد بيفتح رابط مباشر). **متابعة بطلب صريح من المستخدم:** باقي سقالة Vuexy الديمو (Layouts/Email/Kanban/Components/...) كانت لسه ظاهرة لكل الأدوار من غير معنى فعلي — اتقلب افتراضي `MenuComposer` بحيث أي عنصر من غير `roles` يظهر لـ `system_admin` بس (قائمة admin فضلت **زي ما هي بالظبط**)، وباقي الأدوار بيشوفوا بس العناصر المسموحة صراحة + عنصر "Dashboard" جديد يوصلهم لـ `/dashboard` الحقيقي. 8 تستات إجمالًا + تحقق فعلي بتسجيل دخول بالأربع حسابات (system_admin/data_entry/auditor/driver). التفاصيل الكاملة في `ARCHITECTURE.md §3`.

**✅ "مجمع الأصول والمخزون Asset & Supply Hub" — ما تم فعليًا (39 Feature test جديدة + 6 لفلترة الحاوية المدمجة في فورم المركبة، الإجمالي 178):**

- **موديول مستقل top-level زي Vehicles.** كيانان: **الأصول** (حاويات/صهاريج) + **تصنيفات سعة الأصول** (توسعة CRUD كاملة على النسخة المصغّرة اللي اتبنت في موديول المركبات). عنصر قائمة جانبية "Assets" فيه بندين: List + Categories (`ti ti-box`).
- **البنية:** `app/Domain/Assets/{Actions,Exceptions,Exports}` + `Api/V1/AssetController` (+ `AssetCapacityCategoryController` توسّع) + `Http/Requests/Assets/*` + `Http/Requests/AssetCapacityCategories/*` + `AssetPolicy`/`AssetCapacityCategoryPolicy` + `Http/Resources/AssetResource` + views في `resources/views/tenant/{assets,asset-categories}/*` + web shells في `app/Http/Controllers/Web/Assets/*` + راوتات في `routes/tenant.php` (`/app/asset/*`, `/app/asset-category/*`) و`routes/api.php`. JS: `app-asset-list.js` **و** `app-asset-category-list.js` — **الاتنين server-side** عبر `window.gcmServerSideAjax` (نفس نمط المركبات/السائقين/المستخدمين، `SORTABLE_COLUMNS` allowlist على الاتنين) + كروت إحصائية حاويات/صهاريج منفصلة، `asset/form-core.js` + entries، `app-asset-view.js`. **ملاحظة:** endpoint `GET /asset-capacity-categories` بيرجّع pagination بس لو `per_page` مبعوت (صفحة القائمة)؛ من غيره بيرجّع القائمة كاملة (dropdowns فورم المركبة/الأصل).
- **جداول:** توسعة `asset_capacity_categories` (migration منفصلة: `applies_to` enum container/tank/both + `additional_data` + `updated_by`) + `assets` (`asset_type`, `asset_capacity_category_id`, `operational_status` 3 حالات، `affiliation`, `contractor_id` **بدون FK** أسبوع 5، `purchase_date`, `updated_by`) + pivot `asset_vehicle_categories` (تصنيفات المركبات المتوافقة، per-asset، زي `driver_vehicle_categories`).
- **صلاحيات:** إنشاء/تعديل = system_admin + data_entry؛ عرض/تصدير + auditor؛ driver ممنوع (بس لسه بيقدر يقرأ endpoint تصنيفات السعة عشان dropdown فورم المركبة). التعطيل/التنشيط-من-معطّل = system_admin فقط (نفس نمط `UpdateVehicleStatusAction`).
- **قواعد FRD حرفية:** (1) تعديل الأصل = **الاسم فقط** ("اما السعة والتصنيف وما سواها" مقفول)؛ تعديل تصنيف السعة = **الاسم فقط** كمان (السعة والنوعية مقفولين). مفروض بـ whitelist في الـ Update requests. (2) قائمة سعة الأصل في فورم الأصل بتتفلتر حسب النوع المختار (`?for_type=`، و`both` بيطابق الاتنين) — مفروض client + server (`withValidator`). (3) "تصنيف المركبات المتناسب" checkbox فضل **per-asset** (مش على التصنيف — قرار المستخدم اتثبّت مرتين).
- **فلترة سعة "الحاوية المدمجة" في فورم المركبة (FRD §1.5.3):** لما المركبة "تشمل حاوية مدمجة" ويتم اختيار (تصنيف المركبة + حاوية/صهريج)، قائمة السعة بتتفلتر ديناميكيًا عن طريق **مجمع الأصول**: تظهر فقط تصنيفات السعة اللي عندها أصل من النوع المختار مربوط بيها ومعلّم متوافق مع تصنيف المركبة. لو مفيش → خطأ inline، **مفيش fallback لكل السعات**. `AssetCapacityCategory::scopeCompatibleWithVehicle($vcId, $type)` + `App\Domain\Vehicles\Rules\EmbeddedCapacityFitsVehicleRule` (Store + Update) + `GET /asset-capacity-categories?vehicle_category_id=X&asset_type=Y`. `vehicle/form-core.js` بيعيد تحميل القائمة عند تغيير (تصنيف المركبة / نوع الحاوية / زر الحاوية المدمجة). 6 تستات `VehicleEmbeddedCapacityTest` + `VehicleManagementTest::setUp` بقى بيزرع أصل متوافق.
- **مؤجَّل عمدًا:** صفحة "إدراج أصل في مشروع" (محتاجة Company + Project — أسبوع 4)، حالة توفر "في مشروع" + اسم المشروع + عدّاد "في مشاريع" (يُعرض `0`، تعليق واضح — نفس نمط "في رحلة" المؤجّل للمركبات)، تبعية "متعهد" (أسبوع 5، `gcm` بس فعليًا)، قاعدة "لا يمكن تعطيل أصل أثناء وجوده مع مركبة/مشروع" (لا شيء يُفحص ضده لسه).
- **"آخر تحديث بواسطة X":** عمود `updated_by` مضاف لـ `assets` + `asset_capacity_categories` فقط (بقرار المستخدم) — Users/Drivers/Vehicles لسه من غيره، retrofit موحّد بـ activitylog مؤجّل. راجع `ARCHITECTURE.md §5`.
- **Postman:** مجلد جديد "7. Assets" (مجلدات Platform ترقّمت 8-11)، `last_asset_id` + `last_asset_capacity_category_id`.

**✅ جولة مراجعة شاملة بعد دمج موديول الأصول (7 طلبات في رسالة واحدة من المستخدم، 194 Feature test إجمالًا):**

1. **تأكيد اكتمال الأسبوع 3** — راجعت موديول الأصول فعليًا في الكود (مش بس التوثيق فوق) وشغّلت الاختبارات الكاملة؛ الأسبوع خلص بالكامل.
2. **باگ حقيقي: نفس المركبة كانت ممكن تتحدد "افتراضية" لأكتر من سائق في نفس الوقت** — مفيش أي منع، لا في الفورم ولا في السيرفر. اتصلح بقاعدة `withValidator` جديدة في `StoreDriverRequest`/`UpdateDriverRequest` (استثناء السائق الحالي نفسه وقت التعديل)، + فلتر جديد `unassigned_as_default`/`exclude_default_of_driver` على `GET /api/v1/vehicles` بيمنع المركبات المأخوذة من الظهور في الـdropdown من الأساس (دفاع مزدوج زي نمط الصلاحيات). 5 تستات جديدة.
3. **كلمات إنجليزية في الواجهة العربية** — سببين مختلفين اتكشفوا: (أ) نمط `t.key || 'English fallback'` في JS بيخفي غياب أي مفتاح ترجمة بصمت — 3 مفاتيح في `app-driver-{add,edit}.js` كانت مستخدمة بس مش موجودة في `window.driverAddTranslations`/`driverEditTranslations`. (ب) **36 مفتاح `__('...')` كامل** (كل موديول السائقين تقريبًا: "Drivers", "Default Vehicle", "Residence Number"... إلخ) موجودين في الكود فعليًا بس **غايبين تمامًا** من `lang/en.json` **و** `lang/ar.json` الاتنين — `__()` بترجع نص المفتاح الإنجليزي حرفيًا لو مفيش مدخل، من غير أي error. اتصلح الاتنين، وضفت فحص سكريبت (مقارنة كل `__('...')` مستخدم فعليًا مقابل مفاتيح ملفات اللغة) — راجع `CLAUDE.md` للنمط العام.
4. **صلاحيات auditor/data_entry "مش مظبوطة"** — الباگ الفعلي: عنصر قائمة "Assets" (المُضاف حديثًا مع الموديول) ما كانش عليه `"roles"` خالص في `verticalMenu.json`/`horizontalMenu.json` — تحت افتراض `MenuComposer` الجديد (عنصر من غير `roles` = admin بس)، ده كان بيخفي Assets تمامًا عن auditor/data_entry **حتى وهم عندهم API access كامل حسب `AssetPolicy`**. اتصلح بإضافة `"roles": ["system_admin","data_entry","auditor"]` للعنصر الأب والفرعين. تستات جديدة في `MenuVisibilityTest`.
5. **`additional_data` بتظهر كعنوان فاضي لو مفيش بيانات** — Quill (محرر البيانات الإضافية) بيبعت `<p><br></p>` لمحرر فاضي، مش string فاضي — قيمة truthy فتظهر كـ"بيانات موجودة" رغم إنها فاضية بصريًا، في الأربع موديولات (Users/Drivers/Vehicles/Assets). اتصلح بتطبيع مركزي (`App\Http\Requests\Concerns\NormalizesRichTextInput` trait، `strip_tags()` + فحص فاضي) في `prepareForValidation()` لكل Store/Update request بتاعة الحقل ده (Assets بس عند الإنشاء — التعديل مقصور على الاسم بس زي فوق). 4 تستات جديدة (واحد لكل موديول).
6. **Breadcrumb ناقص من موديول Vehicles بالكامل** (4 صفحات: list/add/view/edit) + صفحتين الملف الشخصي (Account/Security) — اتصلح، الأدوات مطابقة لنفس النمط في Users/Drivers/Assets.
7. **عنوان صفحة ظاهر تحت الـbreadcrumb** — اتضاف مركزيًا في `_partials/breadcrumb.blade.php` نفسه (باراميتر `pageTitle` اختياري، افتراضيًا بياخد آخر عنصر في الـbreadcrumb) — كل صفحة عندها breadcrumb بالفعل ورثت العنوان تلقائيًا من غير ما تتعدّل، مش خطوة منفصلة لكل صفحة.

**✅ متابعة — عناوين الصفحات مطابقة للـFRD (ملاحظة من المستخدم إن "add user" مكتوب "Add" مع إن الـFRD كاتب "عنوان الصفحة: انشاء مستخدم جديد"):** الـFRD (V01.09) بيحدد "عنوان الصفحة: ..." صريح لكل صفحة. اتستخرجت الأسطر دي من `word/document.xml` واتعملها mapping لكل صفحة (list/add/view/edit في Users/Vehicles/Drivers/Assets/Asset-Categories — 19 صفحة). اتمرّر `pageTitle` صريح لكل `@include('_partials.breadcrumb', ...)` بالعنوان الوصفي المطابق (مثلاً "إنشاء مستخدم جديد" / "تفاصيل الحساب" / "تعديل تفاصيل المركبة")، والـbreadcrumb trail نفسه فضل مختصر ("المستخدمين > إضافة"). اتشال الـ`<h5 class="card-header">` من فورم الإنشاء/التعديل في 7 ملفات (كان بيكرر العنوان). مفتاحين اتظبطوا في `lang/ar.json` ليطابقوا نص الـFRD حرفيًا (`Create New User` → "إنشاء" مش "إضافة"، `Account Details` → "تفاصيل" مش "بيانات") + 8 مفاتيح جديدة. **قاعدة مستديمة:** أي نسخة FRD أحدث → قارن أسطر "عنوان الصفحة" وحدّث مفاتيح `lang/*.json` المقابلة. راجع `docs/acceptance/` بند 18.

---

## المرحلة 2: الكيانات التجارية (أسابيع 4-5)

### الأسبوع 4 — Companies + Projects + أدوار العميل

**مهام الباك اند:**
- Migration: `companies`, `projects`
- إضافة أدوار العميل للـ Seeder: `client_general_manager`, `client_project_manager`, `client_general_auditor`, `client_project_auditor` (الآن ممكن لأن `Company` موجود)
- `CompanyController`, `ProjectController` (API + Web)
- منطق تعطيل الشركة/المشروع (يعطل كل ما تحته تلقائيًا) — من الـ BRD

**ربط Vuexy:**
- صفحات Companies و Projects → API حقيقي

**معيار القبول:**
- إنشاء شركة عميل، إنشاء مشروع تابع لها، إنشاء مستخدم بدور "مدير مشروع" مرتبط بمشروع محدد ويتأكد إنه يشوف مشروعه بس

---

### الأسبوع 5 — الخدمات + المنشآت الوسيطة + المتعهدين

**مهام الباك اند:**
- Migration: `main_services`, `sub_services`, `units_of_measure`, `intermediate_facilities`, `facility_sub_service_recycling_rate` (جدول ربط النسب)، `waste_yards`
- Migration: `contractors` + **إضافة FK constraint على `contractor_id`** في `drivers`, `vehicles`, `assets` (migration منفصلة بـ `Schema::table()`)
- إضافة دور `contractor_user` للـ Seeder
- Controllers: `MainServiceController`, `SubServiceController`, `UnitOfMeasureController`, `FacilityController`, `WasteYardController`, `ContractorController`

**ربط Vuexy:**
- صفحات الخدمات، المنشآت الوسيطة، المتعهدين → API حقيقي
- صفحات Access Roles / Access Permission (الجاهزة تصميميًا) → تُربط بـ Spatie Permission API

**معيار القبول:**
- **نهاية المرحلة 2**: كل الكيانات الأساسية (غير Trip وPO) موجودة وقابلة للإدارة الكاملة من الواجهة
- اختبار: إعادة تعيين سائق/مركبة/أصل من GCM مباشرة إلى متعهد، والتأكد إن الـ FK اشتغل صح بعد الترقية

---

## المرحلة 3: المنطق الأعقد + الطبقات المتقاطعة (أسابيع 6-8)

### الأسبوع 6 — Contracts (PO)

**مهام الباك اند:**
- Migration: `contracts`, `contract_sub_service_quota`
- `ContractController` — قواعد العمل: تعاقد لمشروع واحد (يمكن أن يحتوي أكثر من خدمة أساسية كبنود منفصلة)، لا تكرار نفس الخدمة الأساسية **لنفس المشروع** في PO ساري آخر لنفس المدى الزمني ولو تداخل يوم واحد (إلا لو استُهلكت بنود الخدمة بالكامل) — منطق هذا الشرط في `Domain/PurchaseOrders/Rules/NoOverlappingMainServiceRule.php`
- `Domain/PurchaseOrders/Quota/` — `QuotaLedger` (سجل استهلاك Append-only) + `QuotaBalance` (رصيد محسوب) + `DeductQuotaAction` — بدل عداد بسيط، كل استهلاك حصة يُسجَّل كـ entry منفصل قابل للتدقيق
- Query/تقرير مساعد: "الخدمات الأساسية المغطاة بـ PO نشط لكل مشروع" — عرض طبيعي بما إن المشروع الواحد له عدة PO متزامنة

**ربط Vuexy:**
- صفحة PO (بالاستفادة من صفحات Invoice الجاهزة تصميميًا في القالب) → API حقيقي

**معيار القبول:**
- إنشاء PO مرتبط بمشروع، بخدمات فرعية وحصص، وحساب الحالة العامة يظهر صح في الجدول

---

### الأسبوع 7 — دورة حياة الـ Trip الكاملة

**مهام الباك اند:**
- Migration: `trips`, `trip_sub_services`, `trip_documents`
- `TripLifecycleService` — الأعقد في المشروع: سائق + مركبة + حاوية ذهاب + حاوية رجوع + خدمات فرعية + وجهات (منشأة وسيطة/ساحة نفايات) لكل خدمة + حالات الرحلة — يُبنى كـ **State Machine حقيقي** (`Domain/Trips/States/`: InProgress, AwaitingApproval, Approved, Rejected, Completed, Cancelled) وليس عمود `status` عادي، مع Actions منفصلة لكل انتقال (`SubmitTripAction`, `ApproveTripAction`...)
- ربط استهلاك Quota من `DeductQuotaAction` (راجع الأسبوع 6) عند اعتماد الرحلة (`ApproveTripAction`)
- `TripController` (API + Web)

**ربط Vuexy:**
- صفحة الرحلات (خريطة + جدول من Logistics Fleet كأساس) → بيانات حقيقية بالكامل، إضافة Mapbox access token حقيقي، استبدال الـ markers الوهمية

**معيار القبول:**
- تنفيذ رحلة كاملة من الإنشاء للاكتمال، والتأكد إن رصيد الـ PO والأصول يتحدّث صح في كل خطوة
- هذا الأسبوع الأكثر عرضة للتأخير — احتفظ بهامش يوم أو اثنين احتياطي

---

### الأسبوع 8 — المستندات + الإشعارات + التقارير + الاختبار النهائي

**مهام الباك اند:**
- `TripDocumentService` + 4 Jobs لتوليد المستندات (Ticket, Manifest, DN, Recycle Receipt) — DomPDF، Queue
- نظام الإشعارات: Email (نسيان كلمة المرور + تحديثات هامة) + in-app notification center
- موديول التقارير: `Services/Report/*Service.php` لكل كيان + التقارير الخاصة (تجميعية مستندات، تجميعية إعادة تدوير)
- مراجعة أمنية شاملة نهائية: rate limiting، CORS (تأكيد عدم الحاجة له أصلاً)، مراجعة كل الـ Policies

**ربط Vuexy:**
- مركز الإشعارات، صفحات التقارير والإحصائيات → بيانات حقيقية بالكامل
- مراجعة نهائية لكل الصفحات المربوطة سابقًا (لا صفحة تقرأ من JSON ثابت)

**معيار القبول:**
- عرض تجريبي كامل End-to-End لكل الأدوار التسعة
- كل الـ Feature tests تعمل (عزل Tenant، صلاحيات الأدوار، دورة حياة الرحلة، حساب الحصص)
- لا توجد صفحة Blade في المشروع تستخدم `ajax: assetsPath + 'json/...'` — كلها API حقيقي

---

## ملخص المخاطر الزمنية

| الخطر | الأسبوع المتأثر | التخفيف |
|---|---|---|
| تعقيد `TripLifecycleService` أكبر من المتوقع | 7 | ابدأ فيه بأبسط سيناريو (رحلة بخدمة فرعية واحدة) ثم أضف التعقيد تدريجيًا |
| نسيان FK constraint على contractor_id | 5 | مذكور صراحة في هذا الملف — راجعه قبل بدء الأسبوع 5 |
| ثغرة عزل Tenant تُكتشف متأخرًا | أي أسبوع | اختبار عزل Tenant إلزامي في نهاية كل أسبوع، ليس فقط نهاية المرحلة |
| تراكم شغل ربط Vuexy | كل أسبوع | ربط كل صفحة فور بناء الموديول الخاص بها — لا تؤجل |
