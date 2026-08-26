# GCM Portal — وثيقة المعمارية (Architecture Decision Record)

> هذا الملف يلخّص كل القرارات المعمارية المتفق عليها لمشروع GCM Portal، مبني على وثائق FRD/BRD الأصلية وقالب Vuexy 10 (Laravel/Blade). استخدم هذا الملف كنقطة انطلاق عند بدء العمل على المشروع في Claude Code.

> **مصدر الحقيقة (Source of Truth):** عند وجود أي تعارض بين تفاصيل BRD وتفاصيل FRD (حقول الفورم، القيم المسموحة، سياسات التعديل والتعطيل)، **يُعتمد الـ FRD** — هو الوثيقة الأدق على مستوى الحقل والصفحة. الـ BRD يبقى مرجعًا للسياق العام للأعمال فقط.

> **مرجع تكميلي:** راجع `FRD_REVIEW.md` — تقرير مراجعة شاملة لكل صفحات FRD (52 صفحة) يحتوي التفاصيل الدقيقة لكل موديول (حقول الفورم، القيم المسموحة، سياسات التعديل/التعطيل) بالإضافة لاكتشاف حرج: **قسم دورة حياة الرحلة (Trip Lifecycle) والتقارير غير موثّقين بالتفصيل في FRD** — مطلوب الحصول على التفاصيل من العميل أو من تصفح النظام الحالي `gcm-gulf.com` قبل بدء الأسبوع 7.

## 1. السياق والهدف

- إعادة بناء نظام GCM الحالي (Node.js) بـ **Laravel 12**.
- الواجهة مبنية على قالب **Vuexy 10** (نسخة Laravel/Blade، وليست Vue SPA).
- المشروع الأصلي: نظام تشغيلي لإدارة رحلات نقل المخلفات (Waste Management System) لصالح شركة GCM، تخدم شركات عملاء متعددة عبر تعاقدات (PO) ومشاريع ورحلات.
- **الهدف الاستراتيجي:** المشروع لن يبقى نظام داخلي لـ GCM فقط — سيتم تحويله لاحقًا إلى **منتج SaaS يُباع لأكثر من شركة تشغيل**.
- **المهلة الزمنية:** شهران للنسخة الأولى (المرحلة الأولى فقط من BRD — بدون تطبيق السائق Offline-First أو SSE، وهذه مؤجلة للمرحلة الثانية).

## 2. المعمارية العامة

**Modular Monolith** — تطبيق Laravel واحد، قاعدة بيانات واحدة، لكن منظّم بمجلدات حسب الدومين (وليس حزمة `nwidart/laravel-modules` الفعلية — overhead غير مبرر لمهلة شهرين). لا microservices.

### القرار الأهم: فصل Web عن API داخل نفس التطبيق

- **`routes/web.php`** → صفحات Blade فقط (نفس تصميم Vuexy)، الكنترولر يرجّع `view()` فقط بدون أي بيانات حقيقية. محمي بـ `auth` middleware (session) — مستخدم غير مسجل دخول لا يرى الصفحة أصلاً.
- **`routes/api.php`** → كل منطق الأعمال والبيانات الحقيقية. محمي بـ `auth:sanctum` + middleware الأدوار.
- الصفحة تُحمَّل من `web.php`، وبعدها الـ JS الموجود بداخلها (نفس ملفات Vuexy، بعد تعديل مصدر الـ `ajax`) ينادي `api.php` بنفس الأصل (same-origin).

### الأمان: Sanctum SPA Mode (Cookie-based) — ليس Bearer Token

- بما أن Web و API على نفس الدومين، لا داعي لتخزين Token في localStorage (يفتح باب XSS).
- تسجيل الدخول يولّد **session cookie واحد (HttpOnly + Secure)** يصادق على تحميل صفحات Blade **وعلى نداءات API** معًا.
- الحماية من CSRF تلقائية عبر `X-XSRF-TOKEN` (موجود أصلاً كـ meta tag في layout الخاص بـ Vuexy).
- لا حاجة لإعداد CORS (نفس الدومين) — يقلل سطح الهجوم.

## 3. Multi-tenancy (قرار حاسم لتحويل المشروع لمنتج)

**النمط المختار: Row-Level Multi-tenancy (قاعدة بيانات واحدة مشتركة + tenant_id)**
وليس Database-per-tenant (أبطأ بناءً وصيانةً، غير مناسب لمهلة شهرين — يمكن الترقية له لاحقًا لعملاء كبار يطلبون عزلًا أقوى).

### التنفيذ:
- جدول **`tenants`** جديد — كل شركة تشترك في المنتج (GCM ستكون أول Tenant، وليست استثناءً بالكود).
- عمود **`tenant_id`** يُضاف لكل جدول رئيسي (companies, projects, contracts, trips, users...) **من أول migration** — لا يُؤجَّل أبدًا، لأن إضافته لاحقًا تعني مراجعة كل query في المشروع (خطر تسريب بيانات بين الشركات).
- **Trait: `BelongsToTenant`** يُطبَّق على كل Model، ينشئ **Global Scope** يفلتر تلقائيًا كل query حسب الـ tenant الحالي — لا يمكن نسيانه لأنه في الطبقة الأساسية.
- **Middleware لتحديد الـ Tenant**: عبر subdomain (`gcm.product.com`, `clientx.product.com`) أو عبر ربط المستخدم بالـ tenant بعد تسجيل الدخول.
- **عزل مزدوج**: `Tenant` (الشركة المشغّلة للنظام) ← يحتوي على ← `Company` (عملاء تلك الشركة المشغّلة، حسب الـ FRD الأصلي). الـ middleware الحالي `EnsureCompanyScope` يبقى طبقة داخل الـ Tenant، لا بديلاً عنه.

## 3.5 تصحيح مهم: قائمة الكيانات والأدوار الكاملة (بعد مراجعة BRD بالكامل)

> **تنبيه:** النسخة الأولى من هذا الملف خلطت بين "شركة العميل" و"شركة المتعهد" في Model واحد. هذا القسم يصحح ذلك ويكمل الكيانات الناقصة.

### الكيانات (Entities) الكاملة

| الكيان | ملاحظات |
|---|---|
| `Tenant` | الشركة المشغّلة للنظام (GCM أو أي عميل مستقبلي للمنتج) |
| `Company` | **شركة العميل فقط** — لها Projects و Contracts |
| `Contractor` | **شركة المتعهد/مقاول الباطن — كيان منفصل تمامًا عن Company** — لها Drivers و Vehicles و Containers/Tanks تابعة لها |
| `Project` | تابع لـ Company |
| `Contract` (PO) | تابع لمشروع واحد، **قد يحتوي أكثر من خدمة أساسية كبنود منفصلة**. المشروع الواحد طبيعي أن يكون له عدة PO نشطة في نفس الوقت — كل PO (أو بند داخله) مسؤول عن خدمة أساسية مختلفة. **التتبع الفعلي للحالة والحصة (Quota) والتنبيهات يكون على مستوى (المشروع + الخدمة الأساسية)، وليس على مستوى المشروع أو الـ PO ككل** — لأن قاعدة "منع تكرار نفس الخدمة الأساسية في PO ساري آخر لنفس المشروع" مبنية على هذا المستوى تحديدًا |
| `MainService` | الخدمة الأساسية (مستوى أعلى) |
| `SubService` | الخدمة الفرعية — تتبع MainService، ولها نسبة إعادة تدوير لكل منشأة وسيطة |
| `UnitOfMeasure` | مكوّن تلقائي مستقل (طن، كيلوجرام، جالون...) |
| `Driver` | قد يتبع GCM مباشرة أو يتبع Contractor |
| `Vehicle` | قد يتبع GCM أو Contractor |
| `VehicleCategory` | تصنيف المركبات |
| `Asset` | حاوية (`Container`) أو صهريج (`Tank`) — **حالتان لا تلغي بعضهما**: النوع (container/tank) قابل لأن يكون الاثنين معًا في نفس التصنيف حسب FRD. 3 حالات دورة حياة: `available`, `on_maintenance`, `deactivated` (وليس حالتين فقط) |
| `AssetCapacityCategory` | يحمل **معًا**: السعة بالمتر المكعب (CBM) + السعة بالطن (TON) — ليس اختيار نوع قياس واحد. بعد الإنشاء، **الاسم فقط** قابل للتعديل — السعة والنوع مقفولان تمامًا لتأثيرهما المباشر على الأصول المرتبطة والخدمات الفرعية والـ PO |
| `IntermediateFacility` | منشأة وسيطة لإعادة التدوير/التخلص — مسؤولة عن خدمة واحدة فقط (دفن آمن / إعادة تدوير / معالجة صرف) |
| `WasteYard` | ساحة النفايات — وجهة احتياطية بديلة عند عدم توفر منشأة وسيطة |
| `Trip` | سائق + مركبة + حاوية ذهاب + حاوية رجوع + منشآت وسيطة مرتبطة بالخدمات الفرعية |
| `GeneratedDocument` | 4 أنواع: وثيقة إسناد المهمة (Ticket) / ملخص الرحلة (Manifest) / إشعار تسليم (DN) / بيان إعادة تدوير (Recycle Receipt) |

### الأدوار الفعلية (7 أدوار — مصحّحة بعد المراجعة الكاملة لـ FRD)

> **تصحيح:** النسخة السابقة من هذا الملف افترضت 9 أدوار (Client General Manager/Auditor منفصلين عن Project Manager/Auditor). المراجعة الكاملة لـ FRD (راجع `FRD_REVIEW.md`) تؤكد عدم وجود دور "عام" منفصل — بدلاً من ذلك، دور واحد بحقل نطاق (`scope`).

**جانب GCM:**
- System Admin — دور واحد فقط، غير قابل للاستنساخ أو التعطيل
- Data Entry
- System Auditor — عرض/تصدير فقط
- Driver (تابع لـ GCM أو لمتعهد عبر عمود `affiliation` — **نفس الدور**، ليس دورين)

**جانب Company:**
- Client Project Manager — بحقل `scope` (enum: `all_projects` / `specific_projects`) + علاقة many-to-many بالمشاريع لو `specific_projects`
- Client Project Auditor — نفس نمط الـ `scope`، بدون صلاحية الاطلاع على PO

**جانب Contractor:**
- Contractor User

**حالة الحساب (Account Status) — 3 قيم تنطبق على كل أنواع المستخدمين:**
- `active` (افتراضي)
- `on_vacation` — يقدر يسجل دخول ويشوف حسابه فقط، يفقد كل قدرة تفاعلية (لا يظهر في قوائم الاختيار التفاعلية كسائق مثلاً)، لكن يبقى ظاهرًا بشكل طبيعي في أي سجل/رحلة سابقة
- `deactivated` — منع دخول كامل، Admin فقط يقدر يفعّلها

**قاعدة عامة:** لا حذف فعلي للحسابات (No hard delete) — تعطيل فقط.

### إدارة الأدوار والصلاحيات — Super Admin فقط

> **قرار (أسبوع 2):** الأدوار مفهوم على مستوى المنتج كله (Spatie Permission بيفرض تفرّد اسم الدور أصلاً، فمفيش تكرار للأدوار لكل Tenant)، فالـ CRUD الكامل (إنشاء/تعديل/حذف دور، وتحديد صلاحيات كل دور) **مقصور على Super Admin (guard `platform`) فقط**.
>
> **System Admin** (ومستخدمي الـ Tenant عمومًا) يشوفوا قائمة الأدوار بشكل طبيعي (endpoint للقراءة فقط، مثلاً عند إسناد دور لمستخدم) لكن **بدون أي صلاحية تعديل** — أي محاولة وصول لـ endpoints إدارة الأدوار من غير guard `platform` ترجع 403. صفحة "Roles & Permissions" في القائمة الجانبية **تظهر لـ Super Admin بس**، ومحذوفة تمامًا من قائمة أي دور تينانت آخر.

### لوحة التحكم — ليست موحّدة

كل دور من الـ 9 له محتوى لوحة تحكم مختلف (روابط عمليات + تقارير وإحصائيات خاصة بدوره). يُبنى كـ endpoint واحد `GET /api/v1/dashboard` يُرجع محتوى مختلف حسب دور المستخدم الحالي (وليس صفحات منفصلة).

### قسم التقارير والإحصائيات — كيان مستقل

البنية المطلوبة تقارير مقسّمة حسب الكيان (مستخدمين، خدمات، شركات عملاء، مشاريع، PO، متعهدين، سائقين، سيارات، حاويات، منشآت وسيطة، ساحة نفايات، رحلات) + تقارير ذات طبيعة خاصة (تجميعية للمستندات، تجميعية لإعادة التدوير). يُبنى كموديول تقارير موحّد (`Services/Report/*Service.php` لكل نوع) بدل توزيع منطق التقارير داخل كل Controller.

## 3.6 Super Admin مقابل Tenant Admin (فصل صارم)

> **مبدأ أساسي:** Super Admin (يدير المنتج نفسه عبر كل الشركات المشتركة) و System Admin/Tenant Admin (مدير النظام الأصلي من الـ FRD، لكن مقصور الآن على tenant واحد) **كيانان منفصلان تمامًا** — ليسا نفس الجدول ولا نفس الـ Guard. الفصل الكامل يمنع أي احتمال تسريب صلاحيات بين مستوى المنتج ومستوى الشركة المستأجرة.

| | Super Admin | Tenant Admin (`system_admin`) |
|---|---|---|
| الجدول | `platform_admins` — **منفصل تمامًا عن `users`**، خارج نطاق `BelongsToTenant` Global Scope بالكامل | `users` بدور `system_admin` (Spatie) + `tenant_id` مُلزم |
| Auth Guard | `platform` (مستقل) | `web` (Sanctum، نفس البنية الحالية) |
| نقطة الدخول | مسار/دومين منفصل تمامًا، مثلاً `admin.product.com` أو `/platform/login` | صفحة تسجيل الدخول العادية لكل tenant |
| النطاق | كل الـ Tenants — إدارة المنتج نفسه | Tenant واحد فقط (شركته) |
| الصلاحيات | إنشاء/تعطيل/تفعيل Tenant، إنشاء أول Tenant Admin لأي Tenant جديد، إحصائيات عبر كل الـ Tenants (اشتراكات لاحقًا) | كل صلاحيات "مدير النظام" الأصلية من الـ FRD — لكن مقصورة على tenant_id الخاص به فقط |
| قاعدة "مدير واحد فقط" (من الـ FRD الأصلي) | لا تنطبق — يمكن أكثر من Super Admin | تنطبق **لكل Tenant على حدة**: تينانت GCM له مدير نظام واحد فقط، وأي Tenant جديد يُنشأ له مدير نظام واحد فقط خاص به |

**آلية الإنشاء:** عند إنشاء Tenant جديد من لوحة Super Admin، يُنشأ تلقائيًا أول حساب `system_admin` لهذا الـ Tenant.

**في الواجهة:** كل Tenant Admin يرى اسم شركته (Tenant) بوضوح بجانب دوره في الـ topbar/dashboard — مثلاً "مدير النظام — GCM"، وليس تسمية عامة غامضة. يمنع أي التباس بين كونه مدير نظام لشركته فقط وليس للمنتج كله.

**مسارات إضافية:**
```
routes/
  platform.php    # محمي بـ auth:platform — Super Admin فقط
```

**نموذج بيانات إضافي:**
```
app/Models/PlatformAdmin.php   # لا يمتلك trait BelongsToTenant إطلاقًا
```

## 3.7 حالة جاهزية الهيكل — لكل قسم على حدة

| القسم | الحالة | ملاحظة |
|---|---|---|
| Tenant / Auth / Roles / Users | ✅ مؤكد من FRD | راجع FRD_REVIEW.md |
| Vehicle Fleet / Drivers / Assets | ✅ مؤكد من FRD | تفاصيل دقيقة لكل حقل |
| Facilities / Services (Main+Sub) | ✅ مؤكد من FRD | |
| Companies / Projects / Contractors | ✅ مؤكد من FRD | |
| Contracts (PO) | ✅ مؤكد من FRD (مع تصحيح مستوى التتبع لـ project+main_service) | |
| **Trip Lifecycle** | ⚠️ **غير مؤكد** | لا يوجد تفصيل في FRD — مبني على تخمين من إشارات BRD فقط. **لا تُنفَّذ فعليًا قبل الحصول على التفاصيل** |
| **Documents (PDF) / Reports** | ⚠️ **غير مؤكد** | نفس السبب — تابع لعدم اكتمال قسم Trip |
| Units of Measure | 🔄 مُصحَّح | ليس CRUD كامل — جدول بيانات ثابت (Seeded) بـ 4 مجموعات ثابتة (كتلة/حجم/طول/مساحة) + منطق تحويل تلقائي. لا يحتاج `UnitOfMeasureController` بمعنى إدارة كاملة، فقط Seeder + Service للتحويل بين الوحدات |

## 4. هيكل الفولدرز الكامل (نسخة مدمجة نهائية)

> **مصدر هذا الهيكل:** دُمج هيكل مقترح من مصدر خارجي (نمط Domain-Driven مع Actions منفصلة، Quota Ledger، وState Machine للرحلة — أرقى تقنيًا من المسودة الأولى لهذا الملف) **مع** طبقة الـ Multi-tenancy الكاملة (كانت غائبة تمامًا من المصدر الخارجي — أهم ثغرة تم سدها) وتصحيحين إضافيين (تسمية قاعدة عدم التداخل، وحقل نطاق دور مدير المشروعات).

```
gcm-wms/
│
├── app/
│   ├── Console/
│   │   └── Commands/
│   │       ├── RecalculatePoBalances.php
│   │       └── PurgeExpiredSignedUrls.php
│   │
│   ├── Domain/                                   # منطق الأعمال — Actions منفصلة المسؤولية، تُختبر بمعزل عن HTTP
│   │   ├── Platform/                              # 🆕 طبقة Super Admin — منفصلة تمامًا عن باقي الدومينات
│   │   │   └── Actions/
│   │   │       ├── CreateTenantAction.php          # ينشئ Tenant + أول Tenant Admin تلقائيًا
│   │   │       ├── SuspendTenantAction.php
│   │   │       └── ReactivateTenantAction.php
│   │   │
│   │   ├── Users/
│   │   │   ├── Actions/
│   │   │   │   ├── CreateUserAction.php
│   │   │   │   ├── DeactivateUserAction.php       # يفرض قاعدة non_deactivatable + cascade
│   │   │   │   └── SetVacationStatusAction.php     # الحالة الثالثة on_vacation
│   │   │   └── Rules/
│   │   │       └── UniqueLoginIdentifierRule.php   # unique داخل نطاق tenant_id وليس globally
│   │   │
│   │   ├── Companies/
│   │   │   ├── Actions/
│   │   │   │   ├── CreateCompanyAction.php
│   │   │   │   └── DisableCompanyAction.php        # cascade لـ projects/users/POs
│   │   │   └── Scopes/
│   │   │       └── CompanyVisibilityScope.php       # يُبنى فوق BelongsToTenant، وليس بديلاً عنه
│   │   │
│   │   ├── Projects/
│   │   │   ├── Actions/
│   │   │   │   ├── CreateProjectAction.php
│   │   │   │   ├── AssignProjectManagerAction.php   # يكتب project_user + عمود scope
│   │   │   │   └── DisableProjectAction.php
│   │   │   └── Scopes/
│   │   │       └── ProjectVisibilityScope.php        # يحترم scope: all_projects أو specific_projects
│   │   │
│   │   ├── Contractors/
│   │   │   ├── Actions/
│   │   │   │   ├── CreateContractorAction.php
│   │   │   │   └── DisableContractorAction.php      # cascade لـ drivers/vehicles/assets
│   │   │   └── Scopes/
│   │   │       └── ContractorVisibilityScope.php
│   │   │
│   │   ├── Fleet/
│   │   │   ├── Actions/
│   │   │   │   ├── CreateVehicleAction.php
│   │   │   │   ├── SetVehicleMaintenanceAction.php
│   │   │   │   └── AssignDefaultDriverAction.php
│   │   │   └── Rules/
│   │   │       └── VehicleCategoryMatchesDriverLicenseRule.php
│   │   │
│   │   ├── Assets/
│   │   │   ├── Actions/
│   │   │   │   ├── CreateAssetAction.php            # حاويات/صهاريج
│   │   │   │   └── SetAssetMaintenanceAction.php     # الحالة الثالثة on_maintenance
│   │   │   └── Rules/
│   │   │       └── AssetCapacityImmutableAfterCreationRule.php  # الاسم فقط قابل للتعديل بعد الإنشاء
│   │   │
│   │   ├── Services/
│   │   │   ├── Actions/
│   │   │   │   ├── CreateMainServiceAction.php
│   │   │   │   ├── CreateSubServiceAction.php
│   │   │   │   └── DisableServiceAction.php          # cascade main→sub، محظور لو مرتبط بـ PO ساري
│   │   │   └── UnitConversion/
│   │   │       ├── UnitConverter.php                 # محرك تحويل عديم الحالة
│   │   │       ├── UnitCategory.php                  # enum: Mass, Volume, Length, Area
│   │   │       └── UnitAmount.php                    # Value Object: قيمة + وحدة + فئة
│   │   │
│   │   ├── Facilities/
│   │   │   ├── Actions/
│   │   │   │   ├── CreateFacilityAction.php
│   │   │   │   └── DisableFacilityAction.php
│   │   │   └── ValueObjects/
│   │   │       └── RecycleRate.php
│   │   │
│   │   ├── PurchaseOrders/
│   │   │   ├── Actions/
│   │   │   │   ├── CreatePurchaseOrderAction.php
│   │   │   │   ├── AddLineItemAction.php
│   │   │   │   ├── IncreaseLineItemAction.php        # قاعدة "بالزيادة فقط بعد الاستخدام"
│   │   │   │   └── DisablePurchaseOrderAction.php
│   │   │   ├── Rules/
│   │   │   │   └── NoOverlappingMainServiceRule.php   # ✏️ مُصحَّح الاسم — المستوى: (project + main_service)
│   │   │   └── Quota/
│   │   │       ├── QuotaLedger.php                   # سجل استهلاك Append-only — يوفر تدقيق كامل
│   │   │       ├── QuotaBalance.php                   # رصيد محسوب/مخزّن مؤقتًا (Value Object)
│   │   │       └── DeductQuotaAction.php
│   │   │
│   │   └── Trips/                                     # ⚠️ راجع FRD_REVIEW.md — تصميم غير مؤكد بعد
│   │       ├── Actions/
│   │       │   ├── CreateTripAction.php
│   │       │   ├── SubmitTripAction.php
│   │       │   ├── ApproveTripAction.php               # → DeductQuotaAction + توليد مستندات + إشعار
│   │       │   ├── RejectTripAction.php
│   │       │   ├── CompleteTripAction.php
│   │       │   └── CancelTripAction.php
│   │       ├── States/                                  # State Machine (spatie/laravel-model-states)
│   │       │   ├── TripState.php
│   │       │   ├── InProgress.php
│   │       │   ├── AwaitingApproval.php
│   │       │   ├── Approved.php
│   │       │   ├── Rejected.php
│   │       │   ├── Completed.php
│   │       │   └── Cancelled.php
│   │       ├── Rules/
│   │       │   └── DestinationMatchesSubServiceRule.php
│   │       └── Scopes/
│   │           └── TripVisibilityScope.php              # سائق/متعهد/عميل/مشروع — فوق BelongsToTenant
│   │
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Platform/                                 # 🆕 Super Admin — Guard منفصل
│   │   │   │   └── TenantController.php
│   │   │   │
│   │   │   ├── Api/V1/
│   │   │   │   ├── ApiController.php                     # قاعدة: ApiResponses trait
│   │   │   │   ├── Auth/
│   │   │   │   │   ├── LoginController.php
│   │   │   │   │   ├── LogoutController.php
│   │   │   │   │   ├── ForgotPasswordController.php
│   │   │   │   │   └── MeController.php
│   │   │   │   ├── UserController.php
│   │   │   │   ├── DriverController.php                  # فورمات فرعية: رخصة/تأمين/تصاريح
│   │   │   │   ├── CompanyController.php
│   │   │   │   ├── ProjectController.php
│   │   │   │   ├── ContractorController.php
│   │   │   │   ├── VehicleController.php
│   │   │   │   ├── AssetController.php
│   │   │   │   ├── FacilityController.php
│   │   │   │   ├── MainServiceController.php
│   │   │   │   ├── SubServiceController.php
│   │   │   │   ├── PurchaseOrderController.php
│   │   │   │   ├── TripController.php
│   │   │   │   ├── TripActionController.php              # submit/approve/reject/complete/cancel
│   │   │   │   ├── NotificationController.php
│   │   │   │   └── Reports/
│   │   │   │       ├── DashboardStatsController.php       # محتوى ديناميكي حسب الدور
│   │   │   │       ├── AggregatedDocumentsReportController.php
│   │   │   │       └── RecycleRateReportController.php
│   │   │   │
│   │   │   └── Web/                                       # Blade shell فقط — بدون بيانات حقيقية
│   │   │       ├── DashboardController.php
│   │   │       └── AuthenticatedSessionController.php     # صفحة دخول Vuexy بجلسة Cookie
│   │   │
│   │   ├── Requests/
│   │   │   ├── Users/{Store,Update}UserRequest.php
│   │   │   ├── PurchaseOrders/{Store,AddLineItem}Request.php
│   │   │   ├── Trips/{Store,Approve}TripRequest.php
│   │   │   └── ...                                         # زوج واحد لكل موديول
│   │   │
│   │   ├── Resources/                                      # شكل الـ JSON — مصدر حقيقة واحد
│   │   │   ├── UserResource.php / DriverResource.php
│   │   │   ├── CompanyResource.php / ProjectResource.php / ContractorResource.php
│   │   │   ├── VehicleResource.php / AssetResource.php / FacilityResource.php
│   │   │   ├── ServiceResource.php
│   │   │   ├── PurchaseOrderResource.php / PurchaseOrderLineItemResource.php
│   │   │   └── TripResource.php / TripDocumentResource.php
│   │   │
│   │   ├── Middleware/
│   │   │   ├── EnsureTenant.php                            # 🆕 تحديد الـ tenant من subdomain/user
│   │   │   ├── EnsureAccountIsActive.php                   # يمنع deactivated/on_vacation من التفاعل
│   │   │   └── LogApiRequest.php
│   │   │
│   │   └── Concerns/
│   │       └── ApiResponses.php
│   │
│   ├── Concerns/
│   │   └── BelongsToTenant.php                             # 🆕 Global Scope — العمود الفقري للعزل
│   │
│   ├── Models/
│   │   ├── Tenant.php                                       # 🆕
│   │   ├── PlatformAdmin.php                                # 🆕 جدول منفصل تمامًا، بدون BelongsToTenant
│   │   ├── User.php
│   │   ├── Company.php / Project.php / Contractor.php
│   │   ├── Vehicle.php / VehicleCategory.php
│   │   ├── Asset.php / AssetCapacity.php
│   │   ├── MainService.php / SubService.php
│   │   ├── Facility.php
│   │   ├── PurchaseOrder.php / PoLineItem.php / PoQuotaLedgerEntry.php
│   │   └── Trip.php / TripLineItem.php / TripDocument.php
│   │
│   ├── Policies/                                             # Policy لكل Model أعلاه
│   ├── Notifications/
│   │   ├── TripAwaitingApprovalNotification.php / TripApprovedNotification.php
│   │   ├── TripRejectedNotification.php / PasswordResetNotification.php
│   │
│   ├── Jobs/
│   │   ├── GenerateOperationsTicket.php / GenerateWasteManifest.php
│   │   ├── GenerateDeliveryNote.php / GenerateRecycleReceipt.php
│   │   ├── BuildAggregatedDocumentsPdf.php
│   │   └── RecalculatePoBalanceCache.php
│   │
│   └── Providers/
│       ├── AppServiceProvider.php
│       ├── AuthServiceProvider.php                           # ربط الـ Policies + Guards (web/platform)
│       └── HorizonServiceProvider.php
│
├── config/
│   ├── gcm-roles.php                                         # خريطة الأدوار/الصلاحيات — 7 أدوار (راجع 3.6)
│   ├── sanctum.php / permission.php / horizon.php
│
├── database/migrations/
│   ├── 000_create_tenants_table.php                          # 🆕 أول migration دائمًا
│   ├── 001_create_platform_admins_table.php                  # 🆕
│   ├── 002_create_companies_table.php                        # + tenant_id (FK) من البداية
│   ├── 003_create_contractors_table.php                      # + tenant_id
│   ├── 004_create_users_table.php                             # + tenant_id
│   ├── 005_create_projects_table.php                          # + tenant_id
│   ├── 006_create_project_user_table.php                      # + عمود scope (all/specific)
│   ├── 007_create_vehicle_categories_table.php                # + tenant_id
│   ├── 008_create_vehicles_table.php                          # + tenant_id + contractor_id (بدون FK أولاً)
│   ├── 009_create_asset_capacities_table.php                  # + tenant_id، capacity_cbm + capacity_ton معًا
│   ├── 010_create_assets_table.php                             # + tenant_id + contractor_id (بدون FK أولاً)
│   ├── 011_create_main_services_table.php / 012_create_sub_services_table.php
│   ├── 013_create_facilities_table.php
│   ├── 014_add_contractor_fk_to_fleet_and_assets.php           # ⚠️ إضافة FK بعد إنشاء contractors
│   ├── 015_create_purchase_orders_table.php / 016_create_po_line_items_table.php
│   ├── 017_create_po_quota_ledger_table.php
│   └── 018_create_trips_table.php / 019_..._trip_line_items / 020_..._trip_documents
│
├── database/seeders/
│   ├── DatabaseSeeder.php / RoleSeeder.php                     # 7 أدوار
│   ├── VehicleCategorySeeder.php                                # 5 أنواع ثابتة
│   ├── UnitOfMeasureSeeder.php                                  # 🆕 بيانات ثابتة — لا Controller
│   └── DemoDataSeeder.php                                       # local/staging فقط
│
├── resources/
│   ├── views/                                                    # لوحة Vuexy Blade
│   │   ├── layouts/panel.blade.php
│   │   ├── auth/{login,forgot-password}.blade.php
│   │   ├── dashboard.blade.php
│   │   ├── users/ companies/ projects/ contractors/ fleet/ assets/
│   │   ├── services/ facilities/ purchase-orders/ trips/ reports/
│   │   │   # كل صفحة هنا Shell رفيع يستدعي /api/v1/* عبر axios فقط
│   ├── js/
│   │   ├── api/client.js                                        # axios instance + Sanctum CSRF bootstrap
│   │   └── pages/{trips,purchase-orders,...}/{index,create,...}.js
│   └── lang/ar/ + lang/en/                                       # 🆕 ثنائية اللغة (كانت غائبة تمامًا)
│
├── routes/
│   ├── platform.php                                               # 🆕 Super Admin — auth:platform
│   ├── api.php                                                    # /api/v1/* — اللوحة والموبايل معًا لاحقًا
│   ├── web.php                                                    # Blade shell فقط
│   └── console.php
│
├── tests/
│   ├── Feature/
│   │   ├── Tenancy/TenantIsolationTest.php                        # 🆕 إلزامي — يُشغَّل كل أسبوع
│   │   ├── Auth/ Users/ Companies/
│   │   ├── Projects/ProjectAuthorizationTest.php
│   │   ├── PurchaseOrders/{QuotaDeductionTest,OverlappingMainServiceTest}.php
│   │   └── Trips/{TripAuthorizationTest,TripStateTransitionTest,TripDocumentGenerationTest}.php
│   └── Unit/{UnitConversionTest,QuotaLedgerTest}.php
│
├── storage/app/
│   ├── private/                                                     # وثائق سائقين/عقود — أبدًا public disk
│   └── generated-documents/
│
├── composer.json / pint.json / phpstan.neon
```

**الفروق الجوهرية عن الهيكل المصدر:**
1. طبقة `Platform/` + `Tenant`/`PlatformAdmin` Models + `EnsureTenant` middleware + `BelongsToTenant` trait — كانت غائبة بالكامل.
2. كل migration من الكيانات الأساسية تحمل `tenant_id` من أول سطر، وليس إضافة لاحقة.
3. `NoOverlappingSubServiceRule` → `NoOverlappingMainServiceRule` (تصحيح تسمية ليعكس المستوى الصحيح من FRD).
4. عمود `scope` على `project_user` pivot لتفعيل نمط "جميع المشروعات" بدون دور منفصل.
5. `UnitOfMeasureSeeder` بدل Controller كامل (تصحيح سابق).
6. `tests/Feature/Tenancy/TenantIsolationTest.php` مُضاف كاختبار إلزامي دوري — لم يكن موجودًا في الهيكل المصدر رغم أهميته الحرجة.

## 5. الأمان — ملخص القواعد

- Laravel Sanctum (Cookie/SPA mode) — لا JWT يدوي.
- Spatie Laravel Permission لإدارة الأدوار (مدير نظام، مدخل بيانات، مراقب، مدير/مراقب مشروعات العميل، مستخدم المتعهد، سائق).
- Rate limiting على كل API routes، خصوصًا auth.
- Audit log (`spatie/laravel-activitylog`) لكل تعديل — إلزامي لنظام فيه تعاقدات وقيم مالية.
- `$fillable` صريح في كل Model (mass assignment protection).
- HTTPS إجباري، تشفير الحقول الحساسة.
- **Global Scope للـ tenant إلزامي على كل query** — هذا هو خط الدفاع الأول ضد تسريب بيانات بين الشركات المشتركة في المنتج.

## 6. الأداء

- Redis للـ cache والـ queue معًا (مع Horizon لمراقبة الطوابير).
- كل عملية بطيئة (توليد PDF، إشعارات، تقارير مجمعة) → Job في الطابور، ليست sync.
- Eager loading دائمًا (`with()`) لتفادي N+1 — الهيكل متداخل (tenant → company → project → contract → trip).
- Pagination بـ Laravel القياسي (limit/offset) كما هو محدد في BRD.
- فهرسة `tenant_id` على كل جدول (أداء العزل).

## 7. خطة الشهرين — 3 مراحل

> **قرار مهم:** الربط مع Vuexy (توصيل صفحات Blade بالـ API الحقيقي) **مطلوب من أول يوم لكل موديول**، وليس خطوة نهائية مؤجلة لآخر المشروع. كل موديول يُبنى ويُربط بواجهته في نفس الأسبوع — هذا يكشف مشاكل التكامل مبكرًا بدل تكديسها في نهاية المشروع.

### المرحلة 1 (أسابيع 1-3): البنية التحتية + الموارد التشغيلية الأساسية

- Tenant infrastructure + `BelongsToTenant` trait
- Auth (Sanctum cookie-based) + فصل Super Admin (`platform_admins`) عن Tenant Admin
- الأدوار — **جانب GCM فقط في هذه المرحلة**: `system_admin`, `data_entry`, `auditor`, `driver` (+ Super Admin بشكل منفصل تمامًا). أدوار العميل والمتعهد تُضاف في المرحلة 2 عند وجود `Company` و`Contractor` فعليًا.
- إدارة المستخدمين (جانب GCM)
- أسطول المركبات (`Vehicle` + `VehicleCategory`)
- إدارة السائقين (`Driver`)
- إدارة مجمع الأصول (`Asset`: حاوية/صهريج + `AssetCapacityCategory`)

**⚠️ ملاحظة تقنية إلزامية:** جداول `drivers`, `vehicles`, `assets` يجب أن تحتوي على عمود `contractor_id` **بدون Foreign Key constraint** في هذه المرحلة (لأن جدول `contractors` غير موجود بعد). الـ constraint يُضاف في migration منفصلة بالمرحلة 2. تجاهل هذه الملاحظة يعني الرجوع لتعديل هيكل الجداول لاحقًا.

**ربط Vuexy:** صفحات تسجيل الدخول، الداشبورد (شِل عام)، قائمة المستخدمين، Fleet، السائقين، مجمع الأصول — تُربط بالـ API فور بناء كل واحدة.

### المرحلة 2 (أسابيع 4-5): الكيانات التجارية

- `Company` (شركات العملاء) + `Project`
- الخدمات: `MainService` + `SubService` + `UnitOfMeasure`
- `IntermediateFacility` + `WasteYard`
- `Contractor` (شركات المتعهدين) — كيان منفصل عن `Company`
- إضافة أدوار جانب العميل (مدير عام/مدير مشروع/مراقب عام/مراقب مشروع) وأدوار المتعهد (`contractor_user`)
- إضافة Foreign Key constraint على `contractor_id` في جداول المرحلة 1 (drivers/vehicles/assets)

**ربط Vuexy:** صفحات Companies، Projects، الخدمات، المنشآت الوسيطة، المتعهدين.

### المرحلة 3 (أسابيع 6-8): المنطق الأعقد + الطبقات المتقاطعة

- `Contract` (PO) — يعتمد على Company/Project/Services من المرحلة 2
- دورة حياة `Trip` الكاملة (الأعقد في FRD) — يعتمد على كل الكيانات من المرحلتين السابقتين
- المستندات التلقائية (PDF) — 4 أنواع مرتبطة بحالات الـ Trip
- نظام الإشعارات (Email + in-app)
- التقارير والإحصائيات — موديول مستقل (`Services/Report/*`) يغطي كل الكيانات

**ربط Vuexy:** صفحات PO/Contracts، الرحلات (خريطة + جدول)، التقارير، الإشعارات — آخر ما يُربط لأنها تعتمد على بيانات حقيقية من كل ما سبق.

**مؤجَّل عمدًا للمرحلة الثانية من المشروع ككل (خارج الشهرين):** تطبيق السائق Offline-First، إشعارات SSE للموبايل.

## 8. الاستفادة من قالب Vuexy الموجود

- القالب Blade عادي — الجداول تقرأ حاليًا من ملفات JSON ثابتة (`ajax: assetsPath + 'json/xxx.json'`)، والكنترولرز فارغة من المنطق (`return view(...)` فقط).
- صفحة `app-logistics-fleet` (خريطة Mapbox + قائمة سائقين/سيارات/حاويات) نقطة انطلاق ممتازة لصفحة الرحلات — تحتاج فقط استبدال الـ demo markers ببيانات حقيقية من API، وإضافة Mapbox access token حقيقي.
- صفحات `Access Roles` / `Access Permission` جاهزة تصميميًا — تُربط بـ Spatie Permission API بدل بنائها من الصفر، لكن تحت `routes/platform.php` (guard `platform`) فقط — راجع "إدارة الأدوار والصلاحيات" في قسم 3.5.
- صفحات `Invoice` قد تفيد في عرض تفاصيل الـ PO/التعاقد.
- القاعدة: أي Blade page يفضل شِل فارغ (HTML فقط)، والبيانات كلها عبر JS يستهلك API endpoints — نفس الـ endpoints ستُستخدم لاحقًا لتطبيق السائق في المرحلة الثانية.
