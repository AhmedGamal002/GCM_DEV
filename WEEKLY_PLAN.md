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
- **"آخر تحديث بواسطة X":** عمود `updated_by` مضاف لـ `assets` + `asset_capacity_categories` فقط (بقرار المستخدم) وقت بناء الموديول ده — Users/Drivers/Vehicles اتضافلهم نفس العمود لاحقًا (راجع بند "متابعة — Last updated by" تحت)، activitylog موحّد مؤجّل لسه. راجع `ARCHITECTURE.md §5`.
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

**✅ متابعة — تحقق شامل من تنفيذ الـFRD (ملاحظة من المستخدم إن §1.2.1.4 فيه حاجات ناقصة "زي التاريخ والوقت مثلا وليس الحصر"):**

- **"آخر تحديث بواسطة X — التاريخ والوقت" retrofit لـ Users/Drivers/Vehicles:** الـFRD بيكرر نفس السطر ("اخر تحديث: تم بواسطة...") ~28 مرة عبر كل صفحات التفاصيل تقريبًا — كان مطبّق على Assets بس. اتضاف عمود `updated_by` (nullable FK → `users`, `nullOnDelete`, **عمدًا خارج `$fillable`** زي `code`) لـ `users`/`drivers`/`vehicles` (migration واحدة، ثلاث جداول)، `updatedBy()` relation على التلات Models، وكل Action بيعدّل/بينشئ (`CreateUserAction`, `UpdateUserAction`, `UpdateUserStatusAction`, `CreateDriverAction`, `UpdateDriverAction`, `CreateVehicleAction`, `UpdateVehicleAction`, `UpdateVehicleStatusAction`) بقى يحط `updated_by = auth()->id()` بتعيين مباشر (مش mass-assignment) قبل `save()`. الـResources/Controllers بترجّع `updated_by_name` (eager-loaded)، وصفحات العرض **والتعديل** الست كلها (view+edit × 3 موديولات) بتعرض السطر بنفس نمط JS `t.last_updated_by.replace(':name',...).replace(':at',...)`. **باگ اختبارات جديد اتكشف بالمرة دي:** تست PHPUnit واحد بيعمل `actingAs($userA,'web')` وبعدين `actingAs($userB,'web')` في نفس الـmethod، `auth()->id()` في الطلب التاني بيرجّع لسه `$userA` — نفس فئة باگ كاش الـ`sanctum` guard الموثّق قبل كده، بس على guard `web` نفسه. الحل: تقسيم لتست methods منفصلة لكل actor (مش حل أنيق لكنه موثوق). راجع `CLAUDE.md` الفخ رقم 12. 4 تستات جديدة، 198 تست إجمالًا.
- **التاريخ والوقت الحالي في الشريط العلوي (FRD §1.2، "جزء الشريط الأعلى" → "التاريخ والوقت الحالي"، أول عنصر قبل قائمة اللغة):** كان غير موجود خالص. اتضاف `<small id="navbar-datetime">` في `navbar.blade.php` (قبل Language dropdown مباشرة، مطابق لترتيب الـFRD)، وسكريبت بسيط في `resources/js/app.js` (محمّل عالميًا لكل الصفحات، تينانت + Platform) بيحدّث كل 30 ثانية عبر `Date().toLocaleString()`. **بيراعي لغة الصفحة:** تينانت بيتبع locale الجلسة (`ar-EG`/`en-US`)، لكن Platform (Super Admin) ثابت إنجليزي دايمًا (`window.location.pathname.startsWith('/platform')`) زي باقي نصوص الـPlatform غير المترجمة — من غير كده كان هيتبع locale الجلسة المشتركة ويطلع عربي على صفحة مفروض تبقى إنجليزي بالكامل. اتحقق فعليًا بالمتصفح على الاتنين (تينانت عربي/إنجليزي + Platform).
- **باگ بيئة تطوير منفصل اتكشف أثناء التحقق (مش جزء من الـFRD):** `DB_HOST=localhost` بقت مش ثابتة عبر إعادة تشغيل الجهاز بسبب split-brain WAMP/WSL MySQL على بورت 3306 — الحل `DB_HOST=[::1]`. تفاصيل كاملة في `CLAUDE.md` الفخ رقم 1.
- **رفع صورة البروفايل من صفحة الملف الشخصي (FRD: "المستخدم يستطيع تغيير البيانات التالية من صفحة الملف الشخصي: الصورة الشخصية" — مكررة في كل قسم أدوار تقريبًا):** صفحة `pages/account-settings-account` كانت بس بتعدّل الاسم، من غير أي واجهة رفع صورة خالص. اتضاف: أفاتار دائري + زرار "Upload new photo" + معاينة فورية (`FileReader`) قبل الحفظ، `UpdateProfileRequest` (`photo` nullable|image|max:2048)، `ProfileController@update` بيخزّن الصورة (`avatars` disk، نفس نمط `UpdateUserAction` — تعيين مباشر خارج الـmass-assignment)، والفورم بقى `FormData` + `POST` مع `_method=PATCH` (مش JSON زي قبل كده) عشان الرفع multipart. **صورة النافبار (أفاتار المستخدم في أعلى يمين/شمال الصفحة) كانت هاردكودد على صورة ديمو ثابتة لكل المستخدمين** — اتصلحت كمان عشان تعرض صورة المستخدم الفعلية لو موجودة (`navbar.blade.php`، متسقة مع باقي أماكن عرض الصورة في المشروع). 4 تستات جديدة (كل الأدوار الأربعة) في `ProfileUpdateTest`، 202 تست إجمالًا. اتحقق فعليًا بالمتصفح: رفع صورة، حفظ، إعادة تحميل الصفحة، والتأكد إنها ظهرت في الاتنين (صفحة البروفايل + النافبار).
- **مش محسوم بعد — تعارض داخل نص الـFRD نفسه حول كلمة المرور:** سطر عام (قرب بداية قسم "إدارة وانشاء حسابات مستخدمي النظام") بيقول "بالنسبة لمستخدمي GCM كلمة المرور غير قابلة للتعديل من قبل المستخدم ولكن من قبل مدير النظام او مدخل البيانات" (يعني الباسورد إداري بس)، لكن كل قسم "تعديل حساب" لاحق بيسرد "كلمة المرور" ضمن البيانات اللي "المستخدم يستطيع تغييرها من صفحة الملف الشخصي" — عكس السطر الأول تمامًا. صفحة Security الحالية (`pages-account-settings-security` + `PATCH /api/v1/me/password`) بنيت واتاختبرت في الأسبوع 2 على أساس إن كل دور يقدر يغيّر باسوورده بنفسه — القرار ده **متغيّرش** دلوقتي لحد ما الغموض ده يتحسم مع المستخدم (السطرين مش ممكن يتحققوا مع بعض حرفيًا). محتاج قرار صريح قبل أي تعديل.
- **تصحيح label رقم الموبايل (FRD: "رقم الموبايل (حساب واتساب)" / "Mobile No. (with WhatsApp)" — مكرر حرفيًا في كل قسم أدوار):** كان مجرد "Mobile Number" في كل مكان. Users/Drivers (add/edit/view) بيستخدموا مفتاح ترجمة مشترك واحد (`__('Mobile Number')`) — اتصلح بتعديل القيمة في `lang/ar.json`/`lang/en.json` بس (من غير أي تعديل blade)، فانعكس تلقائيًا على الصفحات الست. فورم إنشاء Tenant الجديد في الـPlatform (`platform/tenants/create.blade.php`, حقل `admin_phone`) نص إنجليزي هاردكودد (مفيش `__()` في صفحات الـPlatform) — اتعدل يدويًا لنفس النص. اتحقق فعليًا بالمتصفح على الاتنين (عربي/إنجليزي) لصفحات Users/Drivers + صفحة Platform tenant-create.
- **✅ حُسمت — "الاسم باللغة العربية/الإنجليزية" في صفحة عرض السائق مش gap حقيقي:** فورم الإنشاء لكل الأدوار (GCM/سائق/عميل/متعهد) فيه حقل "الاسم" واحد بس (عمودي "label عربي/إنجليزي" في جدول الـFRD ده تسمية نفس الحقل بثنائي اللغة، مش حقلين). "الاسم باللغة العربية" + "الاسم باللغة الإنجليزية" كعنصرين منفصلين بيظهروا بس في قسم "عناصر تفاصيل الحساب الظاهرة" (صفحة العرض) لـ 3 أدوار: العميل، المتعهد، السائق — والتلاتة مفيهمش أي حقل إدخال ثاني للاسم في الفورم المقابل أصلاً (ولا حتى في أدوار العميل/المتعهد المؤجلة لأسبوع 4-5). صفحة عرض مستخدمي GCM (المبنية فعليًا) بتسرد "الاسم" **مفرد** في نفس الموضع بالظبط — ده copy-paste artifact في توثيق الـFRD نفسه، مش متطلب حقيقي. **لا تعديل مطلوب** — `app-driver-view.js` بيعرض `d.name` كحقل واحد بالفعل، مطابق للفورم الحقيقي.
- **رقم الموبايل ماينفعش يتكرر داخل نفس الـtenant (طلب مباشر من المستخدم، مش نص صريح في الـFRD):** كل الأرقام (مستخدمي GCM + السائقين) عايشة في عمود واحد `users.phone` (حساب السائق أصلاً `User` بدور `driver`)، فاتضاف تحقق موحّد يغطي الاتنين مع بعض (تعارض مستخدم↔سائق مرفوض كمان، مش بس مستخدم↔مستخدم). **مستوى الفرض:** نفس نمط لوحة المركبة (`Rule::unique(...)->where('tenant_id', $tenantId)`, + `->ignore(...)` وقت التعديل) في الأربع Requests (`StoreUserRequest`, `UpdateUserRequest`, `StoreDriverRequest`, `UpdateDriverRequest`) + قيد DB فعلي `unique(['tenant_id','phone'])` (migration جديدة) دفاعًا ثانيًا — نفس فلسفة `users.code` و`vehicles.plate`. **نطاق الفرض تينانت لا عالمي عمدًا** (عكس الإيميل) لإن رقم الموبايل مش معرّف دخول (`LoginController` بيدور بالإيميل مش بالموبايل)، فمفيش سبب تقني يمنع تكراره بين تينانتين مختلفين. 5 تستات جديدة (`UserUniquePhoneTest`: تكرار داخل نفس التينانت مرفوض على الإنشاء والتعديل، نفس الرقم في تينانت مختلف مسموح، إعادة حفظ رقم المستخدم نفسه من غير تغيير مايترفضش، وتعارض سائق↔مستخدم مرفوض). **أثر جانبي على تست موجود:** `DriverListPaginationAndStatsTest` كان بينشئ 17 سائق بنفس رقم الموبايل (بيانات اختبار غير واقعية أصلاً) — بيانات وهمية اتصلحت لأرقام متسلسلة فريدة. 207 تست إجمالًا. اتحقق فعليًا بالمتصفح: إنشاء مستخدم برقم، محاولة إنشاء تاني بنفس الرقم → رفض واضح "The phone has already been taken."
- **✅ مستند مقارنة شامل ✅/❌/⚠️ لكل الأسبوعين 1-3 مقابل الـFRD:** `docs/frd-gap-analysis.md` — تغطية كاملة §1.1-§1.7 (تسجيل الدخول، لوحة التحكم، الملف الشخصي، المستخدمين، السائقين، المركبات، الأصول). **أهم اكتشاف:** صلاحيات `data_entry` في `UserPolicy`/`DriverPolicy` مقفولة بالكامل على `system_admin` بس، عكس نص الـFRD الصريح ("انشاء/تعديل مسؤولية مدير النظام/مدخل البيانات") — نفس النص المطبّق صح فعليًا في `VehiclePolicy`/`AssetPolicy` (بالـdocblock بتاعهم اللي بيقتبس الـFRD حرفيًا). يعني data_entry حاليًا **معندوش أي وصول خالص** لـUsers/Drivers API (403 على كل حاجة) رغم إن الـFRD بيديله صلاحية إنشاء/تعديل/"في إجازة" واضحة. لقيت كمان فجوتين أصغر: رسالة تسجيل الدخول للحساب المعطّل عامة مش الرسالة المحددة في الـFRD (`LoginController.php:42`)، وقفل تبديل الوضع الداكن/المضيء (`config/custom.php`, "per client request"). **تحديث:** أكبر اكتشاف (صلاحيات data_entry) ورسالة تسجيل الدخول للحساب المعطّل **اتصلحوا فورًا بطلب صريح من المستخدم** — راجع البندين التاليين. **تبديل الوضع الداكن/المضيء:** اتفعّل بطلب المستخدم (راجع بنده تحت). **كلمة المرور الذاتية:** اتحسمت ونُفّذت (راجع آخر بند). الإشعارات (❌) مؤجّلة للأسبوع 8 بالخطة.

**✅ إصلاح صلاحيات data_entry في Users + Drivers:** `UserPolicy`/`DriverPolicy` بقوا `hasAnyRole(['system_admin', 'data_entry'])` بدل `hasRole('system_admin')` بس، لـ`viewAny`/`view`/`create`/`update` — مطابق لنفس نص الـFRD المطبّق فعليًا في `VehiclePolicy`/`AssetPolicy`. `UpdateUserStatusAction` بقى فيه split صريح (نفس شكل `UpdateVehicleStatusAction`): `on_vacation` مسموح لـsystem_admin أو data_entry، `deactivated`/إعادة التنشيط منه لسه system_admin بس — استثناء جديد `CannotDeactivateUserException` (منفصل عن `CannotDeactivateSystemAdminException` اللي بتحمي حساب مدير النظام نفسه تحديدًا، مش نفس القاعدة). بما إن حساب السائق `User` أصلاً وتغيير حالته بيمر بنفس الـendpoint، الإصلاح ده غطى Drivers تلقائيًا من غير أي كود إضافي هناك. عناصر Users/Drivers في `resources/menu/verticalMenu.json`/`horizontalMenu.json` اتضافلهم `data_entry` (الأب + كل عنصر ابن حقيقي، مش الديمو) — الأبناء محتاجين `roles` صريحة بردو زي ما موثّق في `MenuComposer`'s docblock، مش بيورثوا من الأب. **تستات قديمة كانت بتفترض data_entry ممنوع تمامًا اتحدّثت** (مش اتحذفت): `UserPolicyTest`/`DriverPolicyTest` (اتشال data_entry من قائمة الأدوار المرفوضة، اتضاف تستات إيجابية منفصلة)، `MenuVisibilityTest::test_a_data_entry_user_sees_users_drivers_vehicles_and_assets` (كان اسمه وسلوكه عكس كده). تستات جديدة: `UserPolicyTest` (list+create، vacation-vs-deactivate)، `DriverPolicyTest` (list)، `DriverCreationTest`/`DriverUpdateTest` (create/update). **210 تست إجمالًا.** اتحقق فعليًا بالمتصفح: تسجيل دخول data_entry، القائمة بقت فيها Users+Drivers، `GET /api/v1/users`/`drivers` بترجع 200 (كانت 403)، تغيير حالة مستخدم لـ"في إجازة" نجح، محاولة "تعطيل" ترفضت بـ422 والرسالة الصحيحة.

**✅ إصلاح رسالة تسجيل الدخول للحساب المعطّل:** `LoginController.php` كان بيرجّع نفس رسالة "بيانات الدخول غلط" العامة (`auth.failed`) لحساب معطّل زي أي إيميل/باسورد غلط — الـFRD بيحدد رسالة مختلفة صريحة: *"حسابك معطل من قبل مدير النظام. لمزيد من المعلومات برجاء التواصل مع الدعم الفني من خلال الايميل التالي Support@GCM-domain.com"*. اتصلح بفصل التحقق لخطوتين: (1) التأكد إن الإيميل/الباسورد صح أولاً (لو غلط → `auth.failed` زي ما هو)، (2) **بعدين بس** لو الحساب معطّل → مفتاح جديد `auth.deactivated` (في `lang/en/auth.php`). **الترتيب ده مقصود لسبب أمني** — لو الرسالة المميزة ظهرت قبل التأكد من صحة الباسورد، أي حد من غير ما يعرف الباسورد كان هيقدر يستنتج إن حساب معيّن معطّل بس بتجربة إيميله (user enumeration)، فده بيسرّب حالة الحساب لحد مش متأكد هويته. تستان جديدان في `TenantLoginTest` (الرسالة المميزة تظهر بباسورد صح، والرسالة العامة تفضل تظهر لو الباسورد غلط حتى لو الحساب معطّل فعلاً). **211 تست إجمالًا.** اتحقق فعليًا بالمتصفح على `/login` الحقيقية: حساب معطّل + باسورد صح → الرسالة المميزة بالظبط؛ نفس الحساب + باسورد غلط → الرسالة العامة، من غير أي تلميح إن الحساب معطّل.

**✅ تفعيل تبديل الوضع الداكن/المضيء (FRD §1.2: "تغيير الوضع: داكن / مضيء"):** كان مقفول عمدًا (`config/custom.php`, `hasCustomizer => false`) بتعليق "per client request" من قرار سابق — اتأكد المستخدم إن القرار لسه ساري، وبعدها فورًا طلب يرجع يشتغل. الحل: `hasCustomizer => true` (بيحمّل محرك الـtheme اللازم لزرار التبديل + الحفظ في `localStorage`)، مع إبقاء `displayCustomizer => false` — يعني زرار التبديل البسيط (شمس/قمر/جهاز) في النافبار رجع يشتغل، لكن **لوحة الـcustomizer الكاملة** (RTL، خيارات الـlayout، ...) لسه مخفية زي ما كانت — الطلب كان زرار الوضع بس مش فتح اللوحة كلها تاني. ملف JS/CSS الخاص بالـcustomizer (`template-customizer.js`) كان أصلاً موجود في الـbuild manifest من الأصل (مجرد مخفي بـ`@if` في الـblade)، فمحتاجش `npm run build` خالص — تعديل config PHP بس. اتحقق فعليًا بالمتصفح: الزرار ظاهر، التبديل لـDark فعليًا بيغيّر شكل الصفحة كاملة (النافبار + المحتوى)، بيفضل محفوظ بعد التنقل بين الصفحات، وصفحة الـPlatform (Super Admin) فضلت زي ما هي من غير أي تأثير.

**✅ الملف الشخصي = صورة فقط + كلمة المرور بإدارة الأدمن (تصحيح تفسيري + تنفيذ):** كنت سجّلت البند ده قبل كده كـ"تعارض داخل الـFRD" — **ده كان غلط مني**، الـFRD بيفرّق حسب نوع المستخدم: موظفو GCM (سطر "كلمة المرور غير قابلة للتعديل من قبل المستخدم ولكن من قبل مدير النظام او مدخل البيانات"، والمراقب "الا صورته فقط") والسائق ("الصورة الشخصية" فقط) مايغيّروش الباسورد ولا الاسم بنفسهم؛ العميل والمتعهد (أسبوع 4-5) بيغيّروا الصورة + الباسورد. **اتنفذ:** (1) `PATCH /api/v1/me` بقى صورة فقط (`photo` required، الاسم اتشال). (2) `User::canChangeOwnPassword()` (بيرجّع true بس لأدوار `client_project_manager`/`client_project_auditor`/`contractor_user` المحجوزة في `RoleSeeder`) بيحكم `PATCH /me/password` (403 لغيرهم) وصفحة `/pages/account-settings-security` (403) وتاب Security في صفحة الملف الشخصي (مخفي). (3) حقل "كلمة مرور جديدة + تأكيد" اختياري في تعديل المستخدم وتعديل السائق (`UpdateUserRequest`/`UpdateDriverRequest` + الـActions، فاضي = مفيش تغيير) — من غيره كان مفيش أي طريقة تتغيّر بيها كلمة سر موظف/سائق (غير "نسيت كلمة المرور"). (4) **ثغرة أمنية اتقفلت في نفس الجولة:** بعد ما data_entry بقى يقدر يعدّل المستخدمين (الإصلاح اللي قبله) وبقى معاه حقل الباسورد، كان ممكن يعدّل **مدير النظام نفسه** (يعيّن له باسورد = استيلاء على الحساب، أو يقلّل دوره عبر `roles`) — `UserPolicy::update`/`updateStatus` بقوا يمنعوا data_entry من أي هدف دوره `system_admin` (403). (5) صفحة الملف الشخصي اتترجمت (`__()`) بعد ما كانت إنجليزي ثابت. (6) Postman: "Update Profile (Name)" → "Update Profile (Photo)" + وصف `me/password`. **225 تست إجمالًا.** تحقق فعلي بالمتصفح: data_entry → مفيش تابات، الاسم disabled، `/security` و`/me/password` = 403، رفع صورة نجح وظهر في النافبار؛ تعديل الـauditor بكلمة سر جديدة → القديمة 422 والجديدة 200؛ محاولة data_entry تعدّل/تغيّر حالة الـsystem_admin = 403.

**✅ Checklist تفاعلي للـtester (`docs/acceptance/checklist.html`):** ملف HTML واحد بيتفتح بدبل كليك، فيه كل بنود القبول (51 بند: `week-1-2-auth-users-roles` + `week-3-vehicles-drivers-assets` + `week-3-frd-gap-review` الجديد) بخطواتها ونتيجتها المتوقعة والتست الآلي المقابل، مع تجهيز البيئة والحسابات التجريبية. الـtester بيعلّم ✔ / "فشل" + ملاحظة لكل بند، وفيه عدّاد وشريط تقدّم وفلاتر (الكل/المتبقي/تم/فشل) وبحث وزر "نسخ تقرير النتيجة" (بيجمّع الفاشل بملاحظاته + اللي لسه متختبرش)، والتقدّم بيتحفظ في `localStorage`. **بيتولّد من الـmarkdown** (`node docs/acceptance/build-checklist.mjs`) عشان يفضل مصدر واحد للحقيقة. أثناء بنائه اتصلّح في ملفات القبول: بند 18 مكرر في ملف الأسبوع 1-2 (بقى 19)، وبند 10 في ملف الأسبوع 3 كان لسه بيقول إن السائقين لمدير النظام بس (اتصلّح لـ system_admin + data_entry). اتجرّب فعليًا بالمتصفح: التعليم، الفشل + الملاحظة، الفلاتر، البحث، الحفظ بعد Refresh، وعرض الموبايل.

**✅ القائمة الجانبية = اللي اتبنى بس (وضع عرض العميل):** مدير النظام كان بيشوف 38 عنصر في القائمة، أغلبها سقالة Vuexy الديمو (Layouts, Email, Kanban, eCommerce, Charts...) بسبب قاعدة "عنصر من غير `roles` = للأدمن بس". اتقلبت القاعدة: **من غير `roles` = مخفي عن الكل** (`MenuComposer::filter()`)، فمدير النظام دلوقتي بيشوف 5 عناصر بس: Dashboard · Users · Vehicles · Drivers · Assets (+ الفرعيين). عنصر Dashboard الحقيقي اتضاف له `system_admin` (كان بيوصله عن طريق "Dashboards" الديمو). مفتاح **`SHOW_DEMO_MENU=true`** في `.env` (`config/custom.php` → `showDemoMenu`) بيرجّع السقالة لـ`system_admin` بس لو احتجتها كمرجع محلي. القائمة الأفقية (مش الافتراضية) اتظبطت كمان: عقدة "Apps" اتضاف لها `roles` عشان العناصر الحقيقية اللي جواها ماتختفيش. **ملحوظة للعرض:** ده بيخفي الروابط بس — صفحات الديمو نفسها لسه بتفتح لو حد كتب الرابط يدويًا (`/app/email` مثلًا). 227 تست (3 جداد + 1 اتعدّل). اتجرّب بالمتصفح: القائمة بعد تسجيل دخول الأدمن = 5 عناصر.

**✅ تصنيفات المركبات قابلة للإدارة — طلب العميل، خارج الـFRD (§1.5.2 كان بيثبّتها 5 بس):** العميل بيطلب تصنيف جديد كل شوية، فاتعمل CRUD كامل لمدير النظام بس تحت **Vehicles ← Categories** (القائمة الجانبية: Vehicles بقت أب فيه List + Categories، والتاني للأدمن بس). `/app/vehicle-category/{list,add,edit}`، `GET|POST|PATCH|DELETE /api/v1/vehicle-categories` (القراءة لكل الأدوار لأنها مرجع للفورمات؛ الباقي `VehicleCategoryPolicy` أدمن بس). **قرارات تصميم لازم تتعرف:**
- **الجدول كان عالمي (`vehicle_categories` من غير `tenant_id`) ومكتوب صراحة إنه "never user-editable"** — لو اتعمله CRUD كده، أدمن شركة كان هيغيّر/يمسح تصنيفات كل الشركات. فاتحوّل لـ **per-tenant** (`BelongsToTenant` + unique `(tenant_id, slug)` و`(tenant_id, name_en/ar)`). migration `2026_09_22_100000` بتنسخ الخمسة لكل شركة موجودة **وتعيد ربط** مركبات الشركة وأنواع سائقيها المؤهلة وتوافق أصولها بنسختها، وبعدين تمسح العالمية. اتجرّبت فعليًا على داتا بشركتين (مركبات + 8 صفوف pivot) في الاتجاهين (up/down) — واتصلّحت مشكلتين في الـ`down()` على MySQL (فك الـFK قبل الـunique، وإن الـsubquery على نفس الجدول ممنوع).
- **كل شركة جديدة بتاخد الستة الافتراضية (الخمسة الأصلية + شاحنة جرّارة `tractor_truck`) أوتوماتيك** عن طريق `Tenant::created` → `VehicleCategory::seedDefaultsFor()` (idempotent). ده بالذات خلّى **الـ227 تست القديمة تعدّي من غير أي تعديل** رغم تحويل الجدول لـtenant-scoped. `VehicleCategorySeeder` بقى بيضمن الخمسة للشركات الموجودة بس.
- **الستة الأساسيين (الخمسة + شاحنة جرّارة `tractor_truck`) *primary*: يتعدّل اسمهم بس ومايتمسحوش أبدًا** (`VehicleCategory::isDefault()` على الـslug + `PrimaryVehicleCategoryException` ← 422 + `is_default` في الـAPI + أيقونة الحذف مقفولة). قبل كده الحماية كانت "مربوط" بس، والخمسة كانوا شكلهم محميين بس لأن بيانات الـseed بتربطهم بأصول.
- **الحذف ممنوع لو مربوط بمركبات *أو سائقين أو أصول*** (زيادة عن طلبك اللي ذكر العربيات بس): FK المركبات `restrict` لكن جدولي الـpivot `cascadeOnDelete` — من غير فحص صريح الحذف كان هيمسح مؤهلات السائقين وتوافق الأصول **بصمت**. الرسالة بتقول مربوط بإيه ("in use by 3 vehicles, 1 asset")، وأيقونة الحذف في القائمة بتتقفل مقدمًا للمربوط (`in_use` من الـAPI). الخمسة الأصليين حاليًا كلهم مربوطين بأصول الـseed فمقفولين.
- **الكروت والفلتر ديناميكيين**: `GET /vehicles/stats` كان بيرجّع `by_category` كـ object بخمسة slugs ثابتة (وكمان الـJS والـblade مكتوب فيهم الخمسة) — بقى **مصفوفة `{id, slug, name, count}`** لكل تصنيف موجود (الفاضي بصفر)، والكروت والفلتر وعمود الجدول بيتبنوا منها/من `/vehicle-categories`، فعدد الكروت = عدد التصنيفات بالظبط. فورمات المركبة/السائق/الأصل كانت أصلاً بتجيب من الـAPI فاتبعت لوحدها.
- **الـslug ثابت**: بيتولّد مرة من الاسم الإنجليزي (مع suffix رقمي لو مكرر) ومابيتغيرش لو اتعدّل الاسم (فلاتر الـAPI والـseeders بيعتمدوا عليه). الاسمين (إنجليزي + عربي) مطلوبين وفريدين جوه الشركة.
- **أمان:** الأسماء بقت دخل مستخدم بعد ما كانت ثابتة — فورمين السائق (`app-driver-{add,edit}.js`) كانوا بيحطوا `category.name` في `innerHTML` من غير escape (XSS مخزّن محتمل) — اتصلّحوا، وباقي الأماكن (`new Option`، `textContent`، `escapeHtml`) كانت سليمة. اتجرّب بإدخال اسم فيه `<b>` وطلع نص عادي.
- **الصفحات:** قائمة server-side (بحث بالاسمين + فرز + عدّاد المركبات) + نافذة تأكيد حذف + فورم إضافة/تعديل مشترك (`vehicle-category/form-core.js`)، مترجمة عربي/إنجليزي. صفحات الإدارة عليها `Gate::authorize` (403 لغير الأدمن حتى بالرابط المباشر). لينك "Manage categories" فوق الكروت للأدمن بس.
- **ملحوظة:** رسائل الـvalidation من السيرفر إنجليزي في الواجهة العربية (مفيش `lang/ar/validation.php` في المشروع أصلاً — نفس الحال في باقي الفورمات، مش جديد). اتظبطت أسماء الحقول فيها ("The Name (English) has already been taken").
- **التستات:** ملف جديد `VehicleCategoryManagementTest` (25 حالة: CRUD، أدمن بس، المرجع مقروء لكل الأدوار، الحذف ممنوع لمركبة/سائق/أصل ومسموح لغير المستخدم، الـslug ثابت، تكرار الاسم، العزل بين الشركات، نفس الاسم مسموح لشركتين، الكروت تتبع العدد الفعلي، الصفحات 403 لغير الأدمن) + تستين للقائمة الجانبية + تعديل تست الـstats القديم. **258 تست إجمالًا.** اتجرّب بالمتصفح: القائمة، 5 كروت بالعدّاد الصح، إضافة (فاضي/مكرر/صح)، ظهور الكارت والفلتر فورًا، الحذف بالنافذة، منع الحذف للمربوط (422 برسالة واضحة)، التعديل مع ثبات الـslug، الواجهة العربية RTL، و`data_entry` (مفيش Categories في القائمة، الصفحات 403، القراءة شغالة).
- Postman: 4 requests جداد في مجلد "6. Vehicles" + `last_vehicle_category_id` + وصف الـstats اتعدّل. بنود القبول 10-17 في `week-3-frd-gap-review.md` (الـchecklist بقى 59 بند).

**✅ تلات تعديلات شكل بعد ملاحظات العميل على تصنيفات المركبات:**
- **زر "Add category" كان لازق في خانة البحث:** باقي القوائم بتاخد المسافة من زرار Export (`mx-4`) اللي بين البحث وزرار الإضافة، وصفحة التصنيفات ملهاش Export فالزرار لزق. اتضاف `ms-4` على الزرار — اتقاس بالمتصفح: 16px بالظبط زي صفحة تصنيفات الأصول.
- **التاريخ والوقت على الطرف المقابل لمجموعة الأيقونات في الـnavbar** (كان أول عنصر جنب الأيقونات، ثم اتنقل لآخرها، وبعدين العميل قال إنه عايزه في الاتجاه المعاكس عشان مايبقاش كله متكوّم في ناحية): بقى عنصر مستقل قبل الـ`<ul>` بتاع الأيقونات (اللي عليه `ms-auto`)، فبيقعد في بداية الشريط والأيقونات في نهايته — وبينعكس تلقائي في العربي (RTL). اتقاس بالمتصفح: إنجليزي التاريخ شمال والأيقونات يمين بفجوة 933px على 1600px، عربي بالعكس، وعلى موبايل 375px مفيش تداخل ولا scroll أفقي. انحراف مقصود عن ترتيب الـFRD — اتوثّق في `frd-gap-analysis.md`.
- **وضع المحتوى Wide هو الافتراضي بدل Compact:** `config/custom.php` → `contentLayout => wide` (المحتوى `container-fluid` بعرض المساحة كلها بدل `container-xxl` المحدود). بيشمل الـnavbar والفوتر والصفحات كلها لإنهم بيقروا نفس الإعداد. اتقاس بالمتصفح: المحتوى 1325px على شاشة 1600px. ملحوظة من تعليق الملف الأصلي: أي إعداد layout اتخزّن قبل كده في localStorage المتصفح ممكن يغلب الإعداد الجديد — امسحه لو الشكل القديم ظهر.
- **القائمة الجانبية متجمّعة تحت عنوانين:** **Accounts** (Users · Drivers) و**Fleet & Assets** (Vehicles · Assets)، بالترتيب ده تحت Dashboard (`الحسابات` / `الأسطول والأصول` بالعربي). العنوان مالوش `roles` — `MenuComposer::dropEmptyHeaders()` بيشيله أوتوماتيك لو مفيش عنصر ظاهر تحته للدور ده (المراقب مابيشوفش "Accounts"، والسائق مابيشوفش أي عنوان)، فأي موديول جديد مايحتاجش تعديل على عنوانه. عناوين سقالة الديمو بتتشال بنفس الطريقة. القائمة الأفقية اتسابت زي ما هي (مفيهاش عناوين أصلاً). **موديول جديد لازم يتحط في الـJSON تحت العنوان المناسب.** 4 تستات جداد في `MenuVisibilityTest` (18 إجمالًا). اتجرّب بالمتصفح إنجليزي وعربي.

**✅ أداء وإحساس المستخدم — ملاحظتين من العميل بعد النشر على Hostinger:** (1) إنشاء سائق بملفات كبيرة "الصفحة عاملة فريز" ثم بعد فترة بيسجّل، (2) تصدير الـPDF بياخد وقت كبير جدًا والصفحة "بتحمّل فوق".
- **الـPDF — السبب الحقيقي اتقاس، مش تخمين:** DomPDF كان بيتدهور **أسرع من الخطي** مع جدول واحد طويل (على جهازي من غير Xdebug: 600 صف = 5.2ث، 1200 صف = 15.2ث). جرّبت 4 تعديلات في الـCSS (border-collapse، table-layout fixed، بدون borders) وماحلّوش المشكلة؛ اللي حلّها فعليًا **تقسيم الصفوف لجداول بحجم صفحة** (بنفس الصفوف: 1200 صف = 5.8ث، أسرع 2.6x والنمو بقى شبه خطي). اتقاس مقاس الجدول الأنسب: **45 صف بيملا صفحة A4 بالظبط و50 بيفيّض لصفحة تانية**، فاتاخد **40** (هامش أمان). القوالب الخمسة (Users/Drivers/Vehicles/Assets/AssetCategories) بقت تستخدم partial واحد `resources/views/exports/pdf-table.blade.php` بدل 5 جداول متكررة، بستايل خفيف (مفيش border لكل خلية). الـExcel كان أصلاً سريع (0.4ث لـ200 صف). **تنبيه:** ده تحسين مش معجزة — DomPDF لسه أبطأ من Excel بكتير، وعلى استضافة مشتركة أبطأ من جهازي بكام مرة؛ الـcontrollers بقت `set_time_limit(180)` عشان الـ30 ثانية الافتراضية ماتحوّلش export بطيء لـ500. الـqueries كانت متحمّلة صح أصلاً (مفيش N+1).
- **تنبيه قياس:** جهازي عليه **Xdebug شغّال** بيضخّم أي رقم PHP ~3x (16ث بدل 5.2ث لنفس الشغل) — أي قياس أداء لازم `php -d xdebug.mode=off`.
- **الإحساس بالتجميد:** `resources/js/busy.js` (محمّل عالميًا من `app.js`، ونصوصه مترجمة من `window.gcmTexts` في `layouts/sections/scripts.blade.php`) بيدّي: `gcmBusy` (نافذة فوق الصفحة بدايرة تحميل + شريط تقدّم رفع حقيقي عبر axios `onUploadProgress` + حالة "جارٍ الحفظ" بعد ما الرفع يخلص + حالة فشل بزرار)، و`gcmDownload(url)` (بينزّل الـexport كـblob مع النافذة بدل `window.location.assign` اللي كان بيسيب التاب يلف من غير تفسير — اتغيّرت الـ10 أزرار تصدير في القوائم الخمسة)، و`gcmFileGuard(form, limits)` (بيفحص أحجام الملفات قبل الرفع وبيرفض فورًا بدل ما يستنى الرفع كله وبعدين 422). اتطبّق على إنشاء/تعديل السائق والمركبة (الفورمات اللي فيها ملفات كبيرة). الفورم القديم كان **مفيهوش حتى تعطيل لزرار الحفظ في السائق** — يعني الدوس مرتين كان ممكن يبعت طلبين.
- **حدود الملفات (لازم تفضل متطابقة مع الـFormRequests):** السائق: صورة 2MB، مستندات وتصاريح 5MB. المركبة: صور ووثائق وتصاريح 4MB. الـguard بيقرا حد كل input بالـ`name` أو الـ`id` (فورمات السائق مفيهاش `name`، وأول نسخة كانت هتدّي الصورة حد 5MB بالغلط — اتصلّحت قبل ما تتنشر).
- **مش اتعمل (عمدًا):** ضغط الصور قبل الرفع من المتصفح — ممكن يقلّل حجم الرفع جدًا لو الملفات صور موبايل كبيرة، لكن بيغيّر جودة مستندات رسمية وبيحتاج قرار منك. (الـPDF كان بيطلع العربي علامات استفهام — اتصلّح في البند اللي بعده.)
- **التحقق:** 6 تستات جداد (`ExportPdfLayoutTest`: 40 صف/جدول، page-break، الجدول القصير، الفاضي، الـescape، PDF حقيقي متعدد الصفحات) → **264 تست إجمالًا**. بالمتصفح: الـoverlay ظاهر أثناء الانتظار وبيختفي بعد التحميل والملف نزل `vehicles.pdf` وهو PDF حقيقي، حالة فشل بجلسة منتهية ("Unauthenticated" + زرار)، إنشاء سائق بـ4 مستندات × 3MB: الـoverlay + شريط التقدّم اشتغلوا وسجّل مرة واحدة وحوّل للقائمة، ملف 6MB اترفض فورًا بصفر طلبات للسيرفر، والنصوص كلها بالعربي. بنود القبول 21-23.

**✅ تصدير PDF بالعربي:** أي نص عربي في الـPDF كان بيطلع `؟؟؟` (الخط الافتراضي Helvetica مالوش حروف عربية)، واتأكد بالتجربة إن **الخط لوحده مش كفاية**: DomPDF مابيعملش **ربط حروف** (الحرف بيتكتب مفصول) ولا **اتجاه RTL** (النص بيتكتب معكوس).
- **الحل (من غير أي package جديد):** `app/Support/ArabicText.php` بيحوّل النص قبل ما يدخل DomPDF — بيبدّل كل حرف بشكله المتصل (Presentation Forms-B: أول/وسط/آخر/منفصل + لام‑ألف كحرف واحد) ويرتّبه بصريًا (RTL) مع الحفاظ على الكلمات الإنجليزية والأرقام جوه النص العربي بترتيبها. جداول الأشكال **اتولّدت من بيانات Unicode نفسها** (مش كتابة يدوية) وفلترت على الحروف اللي الخط فعلًا فيها. الخط `DejaVu Sans` (متضمّن أصلًا مع dompdf وفيه عربي). اخترت كلاس صغير بدل `ar-php` عشان الأخيرة بتطلب `ext-calendar` مش مضمون على الاستضافة.
- **القالب المشترك `exports/pdf-table.blade.php`:** كل نص (عنوان/هيدر/خلية) بيعدّي على `ArabicText::forPdf()`. **في واجهة العربي الجدول كله بيتعكس** (الأعمدة من اليمين والمحاذاة يمين والعناوين مترجمة)، وفي الإنجليزي الأعمدة زي ما هي لكن أي اسم عربي جوه البيانات بيتعرض صح. الخلايا `white-space: nowrap` لأن النص متعكوس مسبقًا ولو اتلفّ على سطرين هيتقرا بالمقلوب.
- **`app/Support/PdfLabels.php`:** بيحوّل القيم الخام (`on_maintenance`، `contractor`، `data_entry`...) لنفس كلمات الواجهة (`في الصيانة`، `متعهد`، `مدخل بيانات`) — كانت بتظهر في الـPDF خام حتى بالإنجليزي. 7 مفاتيح ترجمة جديدة (Name, Created At, Capacity Category, Applies To, Both, System Admin, Auditor).
- **`enable_font_subsetting=true`** (`AppServiceProvider::boot`): من غيره DomPDF بيضمّن ملف الخط كله (~750KB) في **كل** PDF حتى لو صفحة واحدة — الـPDF كان 878KB وبقى 18KB.
- **قياس:** 40 صف لكل صفحة لسه بتملا A4 (45 تدخل و50 لأ)، و1200 صف = 30 صفحة ≈ 6.4–7.4ث بالإنجليزي و≈8ث بالعربي على جهازي (Xdebug مقفول). جرّبت اختيار الخط حسب وجود عربي وطلع مفيش فرق أداء حقيقي، فالقالب فيه خط واحد ثابت. مفيش package جديد ولا خطوة نشر إضافية (الخط مش محتاج كتابة على `storage/fonts`).
- **حدود معروفة (مقصودة):** مفيش تشكيل (بيتشال)؛ ترتيب الاتجاه مبسّط (مش UAX#9 كامل) — كافي لأسماء ولوحات وتصنيفات، لكن جمل طويلة جدًا بتخلط عربي/إنجليزي/أقواس متداخلة ممكن يبان ترتيبها غريب؛ والخلية العربية الطويلة جدًا مش بتلتف (بتوسّع العمود).
- **التحقق:** 14 تست جديد (10 Unit في `ArabicTextTest` بقيم Unicode متوقعة مكتوبة يدويًا: الربط، اللام‑ألف، التاء المربوطة، الأقواس، الاتجاه المختلط — و4 Feature في `ExportPdfLayoutTest`: عكس الأعمدة، الإنجليزي مع بيانات عربية، ترجمة القيم، PDF عربي حقيقي بحجم < 200KB) → **278 تست إجمالًا**. وبالمتصفح (pdf.js على الـPDF الناتج): جدول المستخدمين بالعربي فعلًا — الأعمدة من اليمين، الأسماء متوصّلة، الأقواس صح، الحالة "نشط" والدور "مدير النظام"/"مدخل بيانات"/"مدقق". بند القبول 24.

**✅ التصدير بيحترم الفلاتر والبحث:** لما تفعّل فلتر (أو تكتب في مربع البحث) وتدوس تصدير، الملف كان بيطلع فيه **الجدول كله** مش اللي شايفه. السبب مزدوج:
- **الواجهة:** أزرار التصدير كانت بتبعت `format` بس، مفيش فلاتر ولا بحث.
- **الـAPI:** `export()` مكانش بيقرا `search` أصلًا (البحث في القائمة سيرفر-سايد بس)، وفلاتر كل قائمة كانت **منسوخة مرتين** (في `index()` وفي `export()`) وبتتباعد — مثلًا Users كان بيدعم `role`/`status` وناقصه البحث، وAsset Categories ناقصه `search`.
- **الحل:** في الـ5 كنترولرز (User/Driver/Vehicle/Asset/AssetCapacityCategory) الفلترة كلها اتنقلت لدالة واحدة `filteredQuery(Request)` بيستخدمها `index()` و`export()` — مفيش نسختين تتباعد تاني. في الواجهة: كل قائمة بقى عندها `listParams()` واحدة بتغذّي **الطلب العادي للجدول والتصدير**، وفيه helper جديد `window.gcmExport(url, format, dt, listParams)` في `datatables-server-side.js` بيضيف نص البحث الحالي (`dt.search()`) وكل فلتر نشط ويتجاهل الفاضي. لو مفيش فلتر التصدير لسه بيطلّع القائمة كاملة (`?format=xlsx` بس).
- **الترتيب (sort) مش مضمّن:** الملف بيطلع مرتّب بالاسم/الافتراضي مش بترتيب العمود اللي دوست عليه في الجدول (مقصود، تحسين ممكن لاحقًا).
- **اكتشاف جانبي:** طلب PDF لتصنيفات سعة الأصول كان ناقص من Postman collection رغم إن الـendpoint شغّال — اتضاف، وكل طلبات التصدير (10) بقى فيها `search` والفلاتر كـparams معطّلة.
- **التحقق:** 6 تستات جديدة (`ExportFiltersTest`: لكل قائمة من الـ5 + PDF — بيمسكوا الصفوف الفعلية اللي اتصدّرت عبر `Excel::fake()`؛ اتأكدت إن اختبار المركبات **بيفشل** على الكود القديم) → **284 تست إجمالًا**. وبالمتصفح على كل الخمس قوائم: فلتر + بحث ثم تصدير — الطلب الفعلي بيحمل الفلاتر والبحث (مثلًا `/api/v1/vehicles/export?format=xlsx&category=compactor&operational_status=active&search=BB`) والجدول على الشاشة وقتها صفّين بس، وبعد مسح الفلاتر الطلب رجع `?format=xlsx` بس. بند القبول 25.

**✅ ترقية الـFRD من V01.09 لـV01.14 — راجعنا المرحلة الأولى بالكامل ضدها.** الفرق الوحيد اللي مسّ اللي بنيناه هو صلاحيات الأدوار (تفاصيل كاملة وموثّقة بالنص الأصلي في `docs/frd-v01.14-review.md`):
- **`data_entry` بقى "كل صلاحيات مدير النظام" على Users/Drivers/Vehicles/Assets — بما فيها التعطيل وإعادة التنشيط** (كان مقصور على "في إجازة"/"في الصيانة" بس، والتعطيل النهائي كان مدير النظام حصريًا). الاستثناء الوحيد الثابت في النسختين حرفيًا: حساب `system_admin` نفسه لسه ممنوع يتلمس من أي حد. اتشالت 3 exceptions باتت ميتة (`CannotDeactivateUserException`/`VehicleException`/`AssetException`) والقيود المصاحبة لها في الـFormRequests والفورمات.
- **`auditor` بقى عنده (عرض/تصدير) على كل صفحة في النظام** — كان ممنوع بالكامل من Users/Drivers (403 كامل تحت V01.09، مع إن الـFRD نفسها ماكانتش بتفصّل ده صراحة، لحد ما V01.14 أضافت قسم مستقل يقول "جميع الصفحات (view)"). `UserPolicy`/`DriverPolicy` والقائمتين الجانبيتين اتحدّثوا.
- **`auditor` و`driver` بقوا يقدروا يغيّروا كلمة سرهم من الملف الشخصي** — كانوا صورة بس. `User::canChangeOwnPassword()` بقى `true` ليهم تحديدًا (السائق اتضاف في مراجعة تانية بعد ما اتفوّت أول مرة).
- **تناقض حرفي جوه V01.14 نفسه** بين قسم عام (بيقول تعطيل المركبات/الأصول "مدير النظام فقط"، زي V01.09) وقسم جديد (بيقول "كل الصلاحيات" لمدخل البيانات) — **العميل أكّد إنها غلطة نسيان في المستند** والقصد التوسيع، فده اللي اتنفّذ.
- **قفل السعة/التصنيف بقى شرطي في V01.14** ("طالما استخدمت مرة واحدة") بدل الدائم في V01.09 — **قرار العميل: نسيبه دايم زي ما هو** لحد ما الرحلات/التعاقدات تتبني ونعرف نعرّف "الاستخدام" فعليًا (مش نفكه على تعريف مش موجود).
- **"Tractor truck / شاحنة جرّارة"** (تصنيف مركبة سادس جديد في V01.14) كان أصلاً متنفّذ من قبل — مفيش تغيير مطلوب.
- **التحقق:** 9 تستات اتعدّلت/اتضافت (Vehicle/Asset/User/Driver management + Policy + Export + MenuVisibility + PasswordChange) → **296 تست إجمالًا**. وبالمتصفح فعليًا: سجّلت دخول بـ`dataentry@gcm.test` وعطّلت مركبة حقيقية من صفحتها (رجّعت "Vehicle status updated successfully" + "Last updated by Data Entry")، وسجّلت دخول بـ`auditor@gcm.test` وشفت قائمة المستخدمين فعليًا (مش 403) وغيّرت كلمة سري من تاب Security اللي ظهر ليّا لأول مرة. بنود القبول 26-28، وأدلة data-entry/auditor اتحدّثوا بالكامل.

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
