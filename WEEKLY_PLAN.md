# GCM Portal — خطة أسبوعية تفصيلية (8 أسابيع)

> مرجع تنفيذي مكمّل لملف `ARCHITECTURE.md`. كل أسبوع له: الأهداف، مهام الباك اند، ربط Vuexy، معيار القبول (Definition of Done)، والمخاطر/الملاحظات الواجب الانتباه لها.

---

## المرحلة 1: البنية التحتية + الموارد التشغيلية الأساسية (أسابيع 1-3)

### الأسبوع 1 — Tenant Infrastructure + Auth

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

### الأسبوع 2 — إدارة المستخدمين + أسطول المركبات

**مهام الباك اند:**
- CRUD كامل: `UserController` (API + Web) — إنشاء مستخدم بدور GCM محدد، تعطيل/تفعيل، تصدير (PDF/Excel)
- Migration: `vehicle_categories`, `vehicles` — **عمود `contractor_id` بدون FK constraint** (راجع الملاحظة التقنية في ARCHITECTURE.md)
- `VehicleController` + `VehicleCategoryController` (API + Web)
- Policy: `UserPolicy`, `VehiclePolicy`

**ربط Vuexy:**
- صفحة قائمة المستخدمين (`app-user-list`) → API حقيقي، فلاتر (الدور/الحالة) تعمل فعليًا
- صفحة تفاصيل المستخدم → API حقيقي
- صفحة Logistics Fleet كأساس لصفحة الأسطول — الجدول الجانبي يُربط بقائمة المركبات الحقيقية (الخريطة لسه ممكن تفضل demo لحد ما نوصل لموديول الرحلات)

**معيار القبول:**
- إنشاء/تعديل/تعطيل مستخدم من الواجهة فعليًا يعكس نفسه في القاعدة
- إنشاء تصنيف مركبات ومركبة مرتبطة به من الواجهة

---

### الأسبوع 3 — السائقين + مجمع الأصول

**مهام الباك اند:**
- Migration: `drivers` (بعمود `contractor_id` بدون FK)، `asset_capacity_categories` (بعمودي `capacity_cbm` و `capacity_ton` معًا + نوع الأصل المتوافق)، `assets` (عمود `status` enum: `available`/`on_maintenance`/`deactivated`، بعمود `contractor_id` بدون FK)
- `DriverController`, `AssetController`, `AssetCapacityCategoryController`
- منطق "لا يمكن تعطيل سائق أثناء رحلة جارية" و"لا يمكن تعطيل أصل أثناء وجوده مع سيارة/مشروع" (business rules من FRD)
- بعد الإنشاء: تعديل `AssetCapacityCategory` مقصور على الاسم فقط (السعة والنوع مقفولان)
- إحصائيات لوحة التحكم لكل من الحاويات والصهاريج بشكل منفصل: إجمالي متاح / في مشاريع / في صيانة / معطل

**ربط Vuexy:**
- صفحة السائقين → API حقيقي
- صفحة مجمع الأصول (حاويات/صهاريج) → API حقيقي

**معيار القبول:**
- **مراجعة أمنية لنهاية المرحلة 1**: تشغيل كل الـ Feature tests الخاصة بعزل الـ Tenant على كل الموديولات المبنية لحد دلوقتي
- عرض تجريبي (Demo) كامل: تسجيل دخول، إدارة مستخدمين، أسطول، سائقين، أصول — كله شغال على بيانات حقيقية عبر Vuexy

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
