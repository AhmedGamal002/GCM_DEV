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

**آلية الإنشاء:** عند إنشاء Tenant جديد من لوحة Super Admin، يُنشأ تلقائيًا أول حساب `system_admin` لهذا الـ Tenant. **✅ مُنفَّذ فعليًا (أسبوع 2):** `Platform\TenantController` + `Domain\Tenants\Actions\CreateTenantAction` — فورم واحد (بيانات الشركة + بيانات أول `system_admin` لها معًا)، `/platform/tenants`. تعطيل الـ Tenant (`status = suspended`) بيفعّل فورًا خلال `EnsureTenant` middleware — أي جلسة مستخدم تابعة لـ tenant معطّل بتتقفل تلقائيًا (force logout) في أول طلب تالي.

**⚠️ فخ لازم الانتباه له عند أي `withCount()`/subquery على علاقة `users` من كود الـ Platform:** أي استعلام Platform-side (مفيهوش tenant مربوط في الـ container) لو استخدم `Tenant::withCount('users')` أو أي subquery بترجع لجدول `users`، هيرمي `TenantContextMissingException` — لإن الـ subquery لسه شايل الـ Global Scope بتاع `BelongsToTenant` حتى لو الاستعلام الأساسي على `Tenant` مش `User`. الحل الثابت المستخدم في كل من `Platform\RoleController` و`Platform\TenantController`: عدّ صريح بحلقة `foreach` + `User::withoutGlobalScope(BelongsToTenant::class)->where('tenant_id', $x)->count()` بدل `withCount()`.

**في الواجهة:** كل Tenant Admin يرى اسم شركته (Tenant) بوضوح بجانب دوره في الـ topbar/dashboard — مثلاً "مدير النظام — GCM"، وليس تسمية عامة غامضة. يمنع أي التباس بين كونه مدير نظام لشركته فقط وليس للمنتج كله.

**⚠️ فخ اكتُشف عمليًا (أسبوع 2) — القائمة الجانبية وقت وجود جلستين معًا:** Laravel بيحتفظ بتسجيل دخول كل guard (`web` و`platform`) **بشكل مستقل تمامًا في نفس session/متصفح** — يعني مستخدم سجّل دخول Super Admin وبعدين سجّل دخول Tenant admin (أو العكس) من غير logout صريح من الأول، الاتنين بيفضلوا شغالين في نفس الوقت. قرار "أعرض قائمة السوبر أدمن ولا لأ؟" **لازم** يعتمد على الرابط الحالي (`Request::is('platform*')`) **مع** `Auth::guard('platform')->check()` معًا — الاعتماد على `Auth::guard('platform')->check()` لوحدها (من غير فحص الرابط) بيوري قائمة Super Admin حتى وإنت فاتح صفحة تينانت عادية. راجع `App\View\Composers\MenuComposer`.

**⚠️ باگ حقيقي اتكشف بسؤال مباشر من المستخدم (أسبوع 3) — القائمة الجانبية ماكنتش بتفلتر بالدور خالص، بس بـ `platformOnly`.** يعني أي مستخدم تينانت مسجّل دخول — **حتى `driver`، اللي مالوش أي وصول API لـ Users/Drivers/Vehicles خالص** (راجع `UserPolicy`/`DriverPolicy`/`VehiclePolicy`) — كان بيشوف بالظبط نفس قائمة `system_admin` الكاملة. مكنش ده ثغرة أمنية فعلية (البيانات الحقيقية محمية صح على مستوى الـ API عبر الـ Policies)، لكن تجربة استخدام مضلّلة: الدخول على أي رابط منها كان بيحمّل صفحة حقيقية (200) وبعدين طلب البيانات بيرجع 403 بصمت — الجدول بيظهر فاضي "No data available" زي أي جدول فاضي عادي، من غير أي إشارة إن السبب هو الصلاحيات مش عدم وجود بيانات.

**الحل:** إضافة مفتاح `"roles": [...]` اختياري لأي عنصر في `resources/menu/verticalMenu.json`/`horizontalMenu.json` (لازم يتطابق يدويًا مع `Policy::viewAny()` بتاعة نفس الصفحة — مفيش مصدر واحد مشترك يُشتق منه تلقائي)، و`MenuComposer::filter()` بيستبعد أي عنصر لو المستخدم الحالي مالوش أي دور من القايمة دي. عنصر من غير `roles` (كل سقالة Vuexy الديمو) يفضل زي ما هو — مش جزء من الإصلاح ده. كمان `horizontalMenu.json` كان ناقص عنصر "Drivers" بالكامل (اتضاف كمان، حتى لو `myLayout` الافتراضي في `config/custom.php` هو `vertical` مش `horizontal` حاليًا).

**دفاع إضافي (defense-in-depth):** `datatables-server-side.js`'s `gcmServerSideAjax()` بقى بياخد باراميتر تالت اختياري (رسالة مترجمة) بيستبدل بيه نص "مفيش بيانات" الافتراضي لو الطلب رجع 403 تحديدًا — عشان حتى لو حد فتح الرابط مباشرة (بدل ما يدوس من القائمة)، هيشوف رسالة واضحة "لا تملك صلاحية عرض هذه البيانات" مش جدول فاضي غامض.

**تحقق:** 5 تستات جديدة/محدّثة في `MenuVisibilityTest` (driver ميشوفش Users/Vehicles/Drivers، auditor يشوف Vehicles بس، system_admin يشوف التلاتة). تحقق فعلي بالمتصفح: تسجيل دخول بالتلات الأدوار فعليًا، تأكيد نص القائمة الظاهر عبر `get_page_text`، ومحاولة فتح `/app/user/list` مباشرة كـ driver — اتأكد الطلب رجع فعليًا `403 Forbidden` (عبر `read_network_requests`) والجدول عرض رسالة الصلاحية مش "لا يوجد بيانات".

**⚠️ تحديث لاحق (وضع عرض العميل):** القاعدة الافتراضية اللي تحت اتقلبت تاني بطلب صريح — عنصر من غير `"roles"` بقى **مخفي عن الكل حتى `system_admin`** (`MenuComposer::filter()`)، عشان القائمة تعرض بس اللي اتبنى فعلًا (Dashboard/Users/Vehicles/Drivers/Assets). مفتاح `SHOW_DEMO_MENU=true` في `.env` (`config/custom.php` → `showDemoMenu`) بيرجّع السقالة لـ`system_admin` بس للتطوير المحلي. عنصر "Dashboard" الحقيقي بقى معاه `system_admin` كمان (كان بيوصله من خلال "Dashboards" الديمو). في `horizontalMenu.json` عقدة "Apps" (اللي جواها Users/Vehicles/...) اتضاف لها `roles` عشان العناصر الحقيقية ماتختفيش معاها.

**تعميق لاحق، بطلب صريح من المستخدم ("المستخدمين دول ما عدا الأدمن، يفضل زي ما هو، القايمة تبقى فيها اللي مسموح يشوفوا بس"):** الإصلاح فوق كان بيغطي Users/Vehicles/Drivers بس — باقي سقالة Vuexy الديمو (Layouts, Front Pages, Email/Chat/Calendar/Kanban, Components, Forms & Tables, Charts & Maps, ...) كانت لسه ظاهرة لأي دور، من غير أي معنى فعلي ليها. **قلب `MenuComposer::filter()` الافتراضي:** أي عنصر من غير مفتاح `"roles"` بقى **يظهر لـ `system_admin` بس افتراضيًا** (مش "يظهر للكل" زي الأول) — ده بيخلي قائمة admin **زي ما هي بالظبط من غير أي تغيير مرئي** (كل عنصر ديمو مالوش `roles` أصلًا، فبيفضل يشوفه)، بينما أي دور تاني بيشوف بس العناصر اللي عليها `roles` صريحة ومطابقة لدوره. **عنصر جديد "Dashboard"** (`roles: [data_entry, auditor, driver]`, بيوّدي لـ `/dashboard` الحقيقي) اتضاف عشان الأدوار دي يبقى ليها نقطة رجوع واضحة بعد ما اختفت سقالة "Dashboards" الديمو من قائمتهم (`admin` مش من ضمن `roles` العنصر ده عمدًا — يفضل معتمد على عنصر "Dashboards" الديمو الموجود أصلًا، زي ما هو). **ملاحظة للمستقبل:** العنصر الابن ميرثش `roles` أبوه — أي عنصر أب جديد بـ `roles` غير system_admin وليه أبناء، لازم الأبناء تتعلّم بـ `roles` صريحة كمان وإلا هتختفي حتى للأدوار المفروض تشوف الأب.

**تحقق إضافي:** 3 تستات جديدة في `MenuVisibilityTest` (driver/auditor مايشوفوش أي عنصر ديمو لكن يشوفوا "Dashboard"، system_admin لسه شايف السقالة كاملة). تحقق فعلي بالمتصفح بالأربع حسابات (system_admin/data_entry/auditor/driver) — تأكيد نص القائمة كامل لكل واحد عبر `get_page_text`.

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

## 3.8 الرقم التعريفي للمستخدم (`users.code`) — نمط عام لأي كيان محتاج "رقم عرض" مش الـ `id` الخام

> **قرار (أسبوع 2):** عمود `users.code` — رقم عشوائي (مش متسلسل) من 6 خانات، فريد **لكل tenant على حدة** (unique index على `(tenant_id, code)`)، بيتولّد تلقائيًا. الغرض: "الرقم التعريفي" اللي الـ FRD بيطلبه كعمود في جدول عرض المستخدمين — لازم يكون هوية مستقرة ومميزة، مش الـ `id` الخام لقاعدة البيانات (اللي بيكشف حجم البيانات الفعلي ومتسلسل يسهل تخمينه).

**التنفيذ (النمط ده قابل لإعادة الاستخدام لأي كيان تاني محتاج نفس الحاجة):**
- التوليد في `Model::booted()` عبر `static::creating()` hook — **مش** في الـ Action/Controller — عشان يشتغل تلقائيًا لأي طريقة إنشاء (factory، seeder، Action) من غير تكرار الكود.
- التوليد عشوائي مع تحقق تكرار في حلقة `do...while` (`random_int` + `str_pad` + `exists()` check) — مش متسلسل (`max()+1`) لإن ده بيكشف عدد السجلات الفعلي.
- التفرد مضبوط بمستوى الـ tenant (مش عالمي) — بما إن الـ Global Scope بتاع `BelongsToTenant` بيفلتر أي `where('code', ...)->exists()` تلقائيًا على الـ tenant الحالي، مفيش حاجة إضافية مطلوبة غير الـ unique index المركّب.
- العمود **مش** في `$fillable` — بيتولّد جوه الـ hook بس، مايتقبلش عن طريق mass assignment من أي request.

## 3.9 فخاخ بيئة التطوير (اتكشفت عمليًا، بتضيّع وقت لو اتنسيت)

- **`APP_URL` لازم يشمل رقم البورت** في بيئة التطوير المحلية (`http://localhost:8000`، مش `http://localhost`) — أي كود بيستخدم `Storage::disk('public')->url(...)` أو `url()`/`route()` هيولّد روابط بمنفذ غلط (80 بدل 8000) لو `APP_URL` ناقص البورت. النتيجة الملموسة: صور بترفع وتتخزن صح على القرص، بس رابط عرضها بيتكسر في المتصفح (يبان وكإن "الصورة مش بتتخزن" رغم إنها موجودة فعليًا).
- **`php artisan serve --no-reload` (مُفعّل عمدًا من الأسبوع 1 لحل بطء الأداء عبر `PHP_CLI_SERVER_WORKERS`) بيخلي أي تعديل في `.env` مايتفعّلش غير بعد إعادة تشغيل السيرفر بالكامل.** السبب: PHP بتستخدم `putenv()` لضبط متغيرات البيئة، وده تأثيره على مستوى الـ **process** مش الـ request — أول ما الـ worker process يقرأ `.env` مرة، القيمة بتفضل عالقة في الـ process لحد ما يتقفل. `php artisan config:clear` **مش كافي** لوحده هنا — لازم إيقاف وإعادة تشغيل السيرفر (`preview_stop` ثم `preview_start`، أو `Stop-Process` على أي `php.exe` شايل البورت لو معلّق).
- **`Storage::fake('public')` في الاختبارات بيسقط أي إعداد `'url'` مخصص في `config/filesystems.php`** (`Storage::buildDiskConfiguration()` بترجّع بس `throw` + الإعدادات الممرَّرة + `root` — مش الإعدادات الأصلية كلها). يعني تستات رفع الصور لازم تتأكد من وجود الملف (`Storage::disk('public')->assertExists(...)`) ومحتوى الرابط (`assertStringContainsString`)، **مش** من كونه absolute URL بالكامل — الـ absolute-ness مضمونة بس في التشغيل الحقيقي (غير الـ fake) عن طريق `APP_URL`.
- **`Route::currentRouteName()`/الجلسة بتتصفّر بعد `php artisan migrate:fresh`** (جدول `sessions` بيتصفّر معاها) — أي جلسة متصفح شغالة قبل الأمر ده هتترمي على صفحة تسجيل الدخول تاني.

## 3.9.5 فخ DataTables — فلتر `column().search()` بيقارن مع الـ HTML المعروض مش القيمة الخام

> **اتكشف عمليًا (أسبوع 3) في فلتر "في إجازة" على صفحتي المستخدمين والسائقين معًا:** أي عمود DataTables له `render` callback بيرجّع HTML منسّق (زي badge ملوّن)، فلتر الـ dropdown (`column().search('^' + value + '$', true, false)`) بيقارن مع **النص المعروض بعد الـ render**، مش القيمة الخام في `data`. لو القيمة الخام والنص المعروض مختلفين شكليًا (زي `on_vacation` مقابل "On Vacation" — شرطة سفلية مقابل مسافة)، الفلتر مابيتطابقش أبدًا حتى لو القيمة صح 100%. قيم زي `active`/`deactivated` كانت "شغالة" بالصدفة بس لإن النص المعروض بعد تجاهل حالة الأحرف بيتطابق مع القيمة الخام حرفيًا.

**الحل الثابت:** أي عمود بيتفلتر عليه لازم الـ render function يرجّع القيمة الخام (مش الـ HTML) لما `type` يكون `'filter'` أو `'sort'` أو `'type'`:
```js
render: function (data, type, full) {
  if (type === 'filter' || type === 'sort' || type === 'type') return full.status;
  return '<span class="badge ...">' + label + '</span>'; // العرض بس
}
```

## 3.10 تخزين المستندات الحساسة — `local` مش `public` (قرار أمان، أسبوع 3)

> **قرار (السائقين):** أي ملف مرفوع يمثّل **مستند رسمي حساس** (إقامة، رخصة قيادة، رخصة تشغيلية، تأمين، تصريح دخول) **لازم يتخزن على disk `local`** (`config/filesystems.php` — `storage_path('app')`, مش web-accessible)، **مش** `public` disk اللي بتستخدمه صورة البروفايل الشخصية.

**السبب:** أي ملف على `public` disk بيبقى قابل للوصول لأي حد يعرف الرابط (hash عشوائي بس مفيش أي تحقق صلاحيات) — مقبول لصورة بروفايل (بيانات مش حساسة)، **مرفوض** لمستند رسمي (رقم إقامة، رخصة قيادة) ممكن يكون فيه بيانات شخصية حقيقية لو اتسرب الرابط.

**التنفيذ:**
- الرفع: `$file->store('driver-documents', 'local')` (مش `'public'`).
- الاسترجاع: **مفيش رابط مباشر يترجع للـ frontend خالص** — الـ API Resource (`DriverResource`) بيرجّع بس `has_attachment: true/false`، مش المسار ولا الرابط.
- العرض: route محمي بالـ Policy بيعيد بث الملف وقت الطلب فقط، بعد ما يتأكد من نفس صلاحية `view` بتاعة الكيان نفسه — `Storage::disk('local')->response($path)`. كل طلب تحميل بيعمل تحقق صلاحيات من الأول، مفيش "رابط دائم" يتخزن أو يتشارك.
- الـ pattern ده قابل لإعادة الاستخدام لأي مستند حساس تاني في المشروع (عقود PO، مستندات المتعهدين... إلخ) — مش خاص بالسائقين بس.

## 3.11 باگ حرج اتصلح: أي طلب بـ Bearer Token كان بيفشل (500) على كل endpoint تينانتي

> اتكشف أثناء بناء Postman collection (أول مرة فعليًا بيتحصل على `/api/v1/*` بتوكن حقيقي عبر `Authorization: Bearer` header بدل session cookie) — مش عن طريق اختبار مكتوب مسبقًا. كل الـ 86 تست الموجودين وقتها كانوا بيستخدموا `actingAs($user, 'web')`، اللي بيحقن المستخدم المصادق عليه مباشرة **من غير** ما يمرّ فعليًا على الـ guard/middleware pipeline — فمفيش تست واحد كان بيغطي المسار الحقيقي اللي هيستخدمه موبايل السائق مستقبلًا.

**الأعراض:** أي طلب فيه `Authorization: Bearer <token>` صحيح لأي endpoint تحت `['auth:sanctum','tenant']` (مثلاً `/api/v1/users`) كان بيرجّع **500** (`TenantContextMissingException`) — حتى إن الـ token نفسه صحيح 100%.

**السبب الجذري (اتنين مشكلتين متسلسلتين):**

1. Sanctum بيتحقق من الـ bearer token عن طريق `Guard::isValidAccessToken()`، اللي بيعمل `$accessToken->tokenable` (علاقة `MorphTo` على موديل `PersonalAccessToken` الأصلي بتاع الحزمة) عشان يجيب الـ `User` المالك للتوكن. الاستعلام ده بيمر بمسار **Eloquent relation عادي تمامًا**، مش عن طريق auth provider بتاعنا (`TenantUnawareEloquentUserProvider`) اللي أصلاً اتعمل مخصوص عشان يحل نفس المشكلة دي لكن لمسار الـ `web` guard session بس (`retrieveById`/`retrieveByCredentials`). يعني: الحل الموجود من قبل كان بيغطي نص المشكلة بس (تسجيل الدخول بالكوكي)، والنص التاني (تسجيل الدخول بالتوكن) كان لسه مكشوف.
2. حتى لو اتصلحت المشكلة الأولى، `EnsureTenant` middleware كانت بتحدد التينانت الحالي عن طريق `Auth::guard('web')->user()->tenant_id` **بس** — طلب متحقق منه بتوكن (مش session) مايظهرش خالص في guard `web`، فالـ tenant كان هيفضل مش مربوط برضو، والاستعلام بعدها في الكنترولر هيرمي نفس الاستثناء.

**الحل (فايلين + تصحيح middleware):**
- [`app/Models/PersonalAccessToken.php`](app/Models/PersonalAccessToken.php) — موديل جديد بيورث من `Laravel\Sanctum\PersonalAccessToken`، بيعمل override لـ `tokenable()` بإضافة `->withoutGlobalScope(BelongsToTenant::class)` — بالظبط نفس منطق `TenantUnawareEloquentUserProvider` لكن لمسار الـ token.
- مُسجّل في `AppServiceProvider::boot()` عن طريق `Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class)`.
- [`app/Http/Middleware/EnsureTenant.php`](app/Http/Middleware/EnsureTenant.php) — اتصلح عشان يجيب المستخدم عن طريق دالة `currentUser()` جديدة بدل `Auth::guard('web')` مباشرة: بتتحقق من guard `web` الأول (طلب stateful/كوكي)، ولو مفيش، بتتحقق يدويًا من bearer token (`PersonalAccessToken::findToken()`). **ملحوظة:** أول نسخة من الإصلاح ده استخدمت `Auth::guard('sanctum')->user()` بدل التحقق اليدوي — اتصلحت لاحقًا بعد ما اتكشف إنها بتكاش المستخدم على مستوى الـ guard instance عبر تستات متعددة في نفس الـ method (راجع §5 لتفاصيل الباگ ده والدرس المستفاد منه).
- دالة `revokeAuthentication()` جديدة جوه نفس الـ middleware: لو تم اكتشاف تعارض tenant (نفس منطق الحماية الموجود قبل كده)، بترفض بـ logout+session invalidate لو الطلب كان بكوكي، أو **تحذف الـ access token نفسه** لو كان بتوكن (مفيش session تتلغي أصلاً) — عشان التوكن المرفوض ميتقدرش يتستخدم تاني.

**تست الحماية:** [`tests/Feature/Auth/BearerTokenTenantAccessTest.php`](tests/Feature/Auth/BearerTokenTenantAccessTest.php) — 3 تستات بتستخدم توكن حقيقي فعليًا (مش `actingAs`) عبر `Authorization: Bearer` header حقيقي: وصول ناجح، عزل تينانت لسه شغال بالتوكن، وتينانت متعطل بيرفض الطلب **ويمسح التوكن**. اتأكد فعليًا إن الـ 3 تستات دول بيفشلوا (بنفس الـ exception) لو رجّعنا الفيكس (`git stash` مؤقت) — مش افتراض.

**الدرس العام:** أي auth provider مخصص (زي `TenantUnawareEloquentUserProvider`) بيغطي مسار guard واحد بس. لو فيه أكتر من طريقة مصادقة على نفس الموديل (session guard + token guard هنا)، كل مسار لازم فحص/فيكس منفصل — الغطاء الظاهري لتست واحد شغال (session، عن طريق المتصفح) مبيضمنش إن المسار التاني (توكن) شغال، خصوصًا لو التستات كلها بتستخدم `actingAs()` اللي بيتجاوز الـ guard resolution تمامًا.

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
│   │   ├── Vehicles/                              # موديول مستقل (زي Users) — مش تحت "Fleet"
│   │   │   ├── Actions/
│   │   │   │   ├── CreateVehicleAction.php
│   │   │   │   ├── UpdateVehicleAction.php
│   │   │   │   └── UpdateVehicleStatusAction.php
│   │   │   └── Exceptions/
│   │   │       └── CannotDeactivateVehicleException.php
│   │   │
│   │   ├── Assets/                                  # ✅ أسبوع 3 — مبني (أصول + تصنيفات سعة الأصول)
│   │   │   ├── Actions/
│   │   │   │   ├── CreateAssetAction.php / UpdateAssetAction.php (اسم فقط) / UpdateAssetStatusAction.php
│   │   │   │   └── CreateAssetCapacityCategoryAction.php / UpdateAssetCapacityCategoryAction.php (اسم فقط)
│   │   │   ├── Exceptions/CannotDeactivateAssetException.php
│   │   │   └── Exports/AssetsExport.php / AssetCapacityCategoriesExport.php
│   │   │   # "الاسم فقط قابل للتعديل" مفروض في UpdateAssetRequest/UpdateAssetCapacityCategoryRequest (whitelist للـ name فقط)، مش Rule class
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
│   │   │   └── Web/                                       # Blade shell فقط — بدون بيانات حقيقية، مجلد لكل موديول
│   │   │       ├── DashboardController.php
│   │   │       ├── AuthenticatedSessionController.php     # صفحة دخول Vuexy بجلسة Cookie
│   │   │       └── Vehicles/Vehicle{List,Add,Account}Controller.php   # 🆕 (Users/Drivers/... زيّها — يتدمجوا من فرع الزميل)
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
│   ├── 007_create_vehicle_categories_table.php                # جدول عالمي بدون tenant_id — قائمة ثابتة (5 أنواع) مبذورة، نفس مبرر roles
│   ├── 008_create_vehicles_table.php                          # + tenant_id + contractor_id (بدون FK أولاً)، هوية = اللوحة (لا code)
│   ├── 009_create_asset_capacity_categories_table.php         # + tenant_id، capacity_cbm + capacity_ton معًا. ✅ أسبوع 3: expand migration منفصلة أضافت applies_to (container/tank/both) + additional_data + updated_by
│   ├── 010_create_assets_table.php                             # ✅ أسبوع 3: + tenant_id + contractor_id (بدون FK أولاً) + asset_type + asset_capacity_category_id + operational_status (3 حالات) + purchase_date + updated_by. هوية = الاسم (لا code). + جدول pivot asset_vehicle_categories (تصنيفات المركبات المتوافقة)
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
│   ├── views/
│   │   ├── layouts/ + layouts/sections/ + _partials/             # سقالة Vuexy المشتركة — ما تتحركش
│   │   ├── content/                                              # صفحات Vuexy الديمو — تفضل مكانها
│   │   └── tenant/                                               # 🆕 صفحات GCM الحقيقية، مجلد لكل موديول:
│   │       ├── vehicles/{list,add,edit,view,_form}.blade.php     #    + vehicles/export-pdf.blade.php (نفس نمط users/drivers)
│   │       └── users/ auth/ profile/  (على فرع الزميل — يتدمج)
│   │   │   # كل صفحة هنا Shell رفيع يستدعي /api/v1/* عبر axios فقط
│   ├── js/
│   │   ├── api/client.js                                        # axios instance + Sanctum CSRF bootstrap
│   │   └── pages/{trips,purchase-orders,...}/{index,create,...}.js
│   └── lang/ar/ + lang/en/                                       # 🆕 ثنائية اللغة (كانت غائبة تمامًا)
│
├── routes/
│   ├── platform.php                                               # 🆕 Super Admin — auth:platform
│   ├── api.php                                                    # /api/v1/* — اللوحة والموبايل معًا لاحقًا
│   ├── web.php                                                    # سقالة Vuexy الديمو فقط — require tenant.php في آخره
│   ├── tenant.php                                                 # 🆕 راوتات صفحات GCM الحقيقية (Web\{Module}\* controllers)
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
- **⚠️ قاعدة اتأكدت أهميتها فعليًا (أسبوع 2) — أي DataTables `render` callback في JS بيرجع HTML خام لازم يعمل escape للحقول اللي مصدرها بيانات مستخدم (اسم، إيميل، أي حقل self-service قابل للتعديل).** DataTables بتحقن النتيجة عبر `.html()` مش كـ نص، فأي حقل زي `full.name` (مستخدم عادي أي دور يقدر يغيّره بنفسه عن طريق `/api/v1/me`) لو اتحط في الـ HTML string من غير escaping بيبقى XSS مخزّن قابل للتنفيذ في جلسة أي حد بيشوف الجدول (زي system_admin وهو بيفتح صفحة المستخدمين). استخدم helper بسيط زي `$('<div>').text(value).html()` قبل أي تسلسل نصي HTML. الحقول اللي مصدرها الكود نفسه (roles enum ثابت، صور برابط متولّد من hash عشوائي server-side) مش محتاجة نفس المعاملة.
- **⚠️ قاعدة اتأكدت أهميتها فعليًا (أسبوع 3، بعد 3 جولات تصحيح) — إنشاء سائق حصريًا عن طريق `/api/v1/drivers`، مش category جوه فورم المستخدمين العام.** الجولتين الأوليين ترددوا (استبعاد كامل ← ثم سماح مع إنشاء صف فاضي تلقائي)، لحد ما المستخدم راجع نص الـ FRD حرفيًا ولقى قسم منفصل تمامًا "إدارة وانشاء حسابات السائقين" بصفحة "انشاء مستخدم جديد (سائق)" خاصة بيها (Reference URL منفصل، فورم كامل خاص). القرار النهائي: `StoreUserRequest` بيرفض `driver` نهائيًا، و`UserPolicy::update()` بيرفض 403 أي تعديل على مستخدم `hasRole('driver')` — الإنشاء والتعديل للسائق **حصريًا** عن طريق موديول Drivers (`CreateDriverAction`/`UpdateDriverAction`)، مفيش استثناء. **الدرس الأعمق:** لما توثيق الـ FRD يبان غامض أو بيحتمل أكتر من تفسير، الرجوع للنص الأصلي حرفيًا (مش الافتراض المنطقي "الأسهل تقنيًا") هو الحسم — حصل هنا 3 مرات على نفس النقطة قبل ما يتأكد بالنص. راجع `WEEKLY_PLAN.md` (قسم الأسبوع 3) لتفاصيل الجولات التلاتة.
- **⚠️ قاعدة اتأكدت أهميتها فعليًا (أسبوع 3) — أي تحقق (`in_array`, `==` صارم) على IDs جايين من فورم HTML حقيقي لازم يتعامل معاها كـ string، مش يفترض إنها int.** فورم حقيقي (`multipart/form-data` عبر `FormData` في الـ JS) بيبعت كل حاجة كـ string دايمًا، عكس `postJson()` في التستات اللي بتحافظ على نوع البيانات الأصلي (JSON encoding مش بيحوّل الأرقام لـ strings). باگ حقيقي اتكشف بالتجربة الفعلية بالمتصفح (مش بالتستات — التستات عدّت بنجاح رغم الباگ): تحقق "المركبة الافتراضية تنتمي لتصنيف مختار" في `StoreDriverRequest`/`UpdateDriverRequest` كان بيستخدم `in_array($int, $arrayOfStrings, true)` — `"3" !== 3` بمقارنة strict بتفشل دايمًا. الحل: `in_array((int) $x, array_map('intval', $ids), true)`. **درس اختبار عام:** أي تحقق من النوع ده لازم تست بـ `->post()` بقيم **string** صراحة كمان، مش `->postJson()` بس.
- **⚠️ قاعدة اتأكدت أهميتها فعليًا (أسبوع 3) — `Auth::guard('sanctum')->user()` بتكاش المستخدم على مستوى الـ guard instance، مش الطلب.** `RequestGuard` (اللي `Auth::guard('sanctum')` بيرجعه) بيحتفظ بالمستخدم المُحلَّل في property داخلية بمجرد أول استدعاء — في التشغيل الحقيقي مفيش مشكلة (كل HTTP request عملية PHP جديدة كليًا)، لكن في PHPUnit Feature tests اللي بتعمل `actingAs($userA)` وبعدين `actingAs($userB)` **في نفس الـ test method**، الكاش بيفضل شايل $userA غلط لأي نداء تاني لـ `Auth::guard('sanctum')` — `actingAs()` بترجع بس الـ guard اللي انت مررته بالاسم (`web` مثلًا)، مش `sanctum`. اتكشف فعليًا من تست حقيقي (`VehicleUniquePlateTest`) كان بيفشل غلط. الحل في `EnsureTenant`: التحقق من guard `web` مباشرة + تحقق يدوي من bearer token (`PersonalAccessToken::findToken()`) بدل المرور بالـ guard المكاش. **درس عام:** أي كود بيعتمد على `Auth::guard()` لمسار مصادقة بديل (زي `sanctum`) لازم ينتبه إن التستات اللي بتبدّل المستخدم أكتر من مرة في نفس الـ method ممكن تدّي نتيجة غلط بصمت. **تطبيق عملي في موديول الأصول:** أي تست بيسوّي `create` بمستخدم و`update` بمستخدم تاني في نفس الـ method لازم يبني الكيان بالـ factory بدل نداء API تاني (راجع `AssetManagementTest`).

- **"آخر تحديث بواسطة X — التاريخ/الوقت" (FRD على صفحات التعديل):** لسه مش عام. أسبوع 3 موديول الأصول ضاف عمود `updated_by` (FK nullable لـ `users`, `nullOnDelete`) على `assets` و`asset_capacity_categories` فقط، بيتعبّى في الـ Actions من `$request->user()`، ويتعرض على صفحتي التعديل + صفحة تفاصيل الأصل. **Users/Drivers/Vehicles لسه من غيره** — retrofit موحّد عبر `spatie/laravel-activitylog` (مذكور كإلزامي في §5) لسه مؤجّل؛ لو اتعمل، يستبدل الـ `updated_by` اليدوي ده.

## 6. الأداء

- Redis للـ cache والـ queue معًا (مع Horizon لمراقبة الطوابير).
- كل عملية بطيئة (توليد PDF، إشعارات، تقارير مجمعة) → Job في الطابور، ليست sync.
- Eager loading دائمًا (`with()`) لتفادي N+1 — الهيكل متداخل (tenant → company → project → contract → trip).
- Pagination بـ Laravel القياسي (limit/offset) كما هو محدد في BRD.
- فهرسة `tenant_id` على كل جدول (أداء العزل).
- **⚠️ فخ N+1 اتأكد عمليًا مع Spatie Permission (أسبوع 2):** أي `Resource` بيستخدم `getRoleNames()`/`getPermissionNames()` من `HasRoles` trait، الدالة دي بتعمل `loadMissing('roles')` — يعني لو الـ query الأساسي مجابش `roles` ضمن `->with([...])`، كل صف في القائمة هيسبب query منفصل لجلب أدواره (N+1 كامل). لازم أي `index()`/`export()` بيرجّع مجموعة `User` (أو أي Model عليه `HasRoles`) يضيف الـ relation المطلوبة صراحة في `->with([...])` — اتأكد منها بتست بيعد عدد الـ queries الفعلي (`DB::enableQueryLog()`) بدل الاعتماد على المراجعة البصرية للكود بس.
- **⚠️ باگ أداء حرج اتكشف بسؤال مباشر من المستخدم (أسبوع 3) — DataTables كانت client-side بالكامل على الصفحات التلاتة (Users/Drivers/Vehicles).** كل جدول كان بيعمل `ajax` مرة واحدة بـ `per_page=1000` ثابت، وDataTables بتعمل الباقي (بحث/فرز/فلترة/pagination) في المتصفح على البيانات المحمّلة بس. مع عميل عنده ~8000 موظف، ده مش مجرد "بطء" — **فقدان بيانات فعلي**: أي حد بعد أول 1000 صف مش هيظهر خالص، مش في الجدول ولا في نتائج البحث ولا في قوائم فلترة الدور/الحالة (اللي كانت بتتبني من البيانات المحمّلة نفسها عبر `column(N).data()`). صفحة السائقين كان فيها إضافة: **الكروت الإحصائية الأربعة كانت بتتحسب من نفس البيانات المحمّلة العميل-سايد** (مش من endpoint حقيقي) — نفس المشكلة بالظبط.

  **الحل:** تحويل الجداول التلاتة لـ **server-side processing** حقيقي:
  - `resources/assets/js/datatables-server-side.js` — helper مشترك واحد (`window.gcmServerSideAjax(url, getExtraParams)`) بيترجم طلب DataTables (`draw`/`start`/`length`/`search`/`order`) لباراميترات الـ API الموجودة أصلاً (`page`/`per_page`/`search`/`sort_by`/`sort_dir`) بدل ما نعيد تصميم شكل استجابة الـ API نفسه — أي مستهلك تاني (Postman، إلخ) لسه شغال زي ما هو.
  - `UserController`/`DriverController`/`VehicleController::index()`: إضافة `sort_by`/`sort_dir` بقائمة أعمدة مسموحة صراحة (`SORTABLE_COLUMNS` allowlist) — **لا تقبل اسم عمود من الطلب مباشرة أبدًا** (SQL error على الأقل، تسريب معلومات على الأكتر). `DriverController` محتاجة `join('users', ...)` لإن code/name/status بيانات على جدول `users` مش `drivers`.
  - `GET /api/v1/drivers/stats` endpoint جديد (بنفس نمط `VehicleController::stats()` الموجود بالفعل) — الكروت الأربعة بتتحسب في SQL على المجموعة الكاملة، مش على صفحة الجدول المحمّلة.
  - فلاتر الـ dropdown (دور/حالة/تبعية/تصنيف) بقت قوائم ثابتة (القيم المعروفة مسبقًا) بدل ما تتبني من البيانات المحمّلة، وبتبعت طلب جديد للسيرفر عند التغيير بدل فلترة العميل-سايد.

  **باگين إضافيين اتكشفوا أثناء الإصلاح (مش حاجة من الأصل، كانوا مستخبيين وراء الفلترة العميل-سايد):**
  1. **فلتر "Affiliation" في صفحة السائقين كان شكلي بالكامل** — الـ dropdown موجود في الواجهة، لكن `DriverController::index()` مكنش بيقرأ باراميتر `affiliation` خالص. كان "شغال" بالصدفة بس لإن الفلترة العميل-سايد على البيانات المحمّلة كانت بتعمل الشغل الحقيقي. اتصلح بإضافة الفلتر فعليًا في الـ query.
  2. **`$request->string('sort_dir')->lower() === 'desc'` بترجع `false` دايمًا** — `->lower()` بترجع كائن `Stringable` مش string خام، والمقارنة الصارمة `===` مع string حرفي بتفشل دايمًا (كائن مش string). يعني `sort_dir=desc` كان بيتجاهل تمامًا في الكود، الترتيب كان ascending دايمًا بغض النظر عن الطلب. اتصلح بـ `->lower()->toString() === 'desc'`. **درس عام:** أي مقارنة `===`/`==` مع نتيجة `$request->string(...)` لازم `->toString()` صريحة أول — الـ Stringable مقصود يتصرف كـ string في سياقات كتير (concatenation، إلخ) لكن **مش** في المقارنة الصارمة.

  **امتداد (أسبوع 3، موديول الأصول):** جدولَي الأصول (`AssetController::index`) وتصنيفات سعة الأصول (`AssetCapacityCategoryController::index`) اتبنوا server-side من أول يوم بنفس الـ helper + allowlist. تصنيفات السعة فيها لفّة: نفس الـ `index` endpoint هو feed الـ dropdowns في فورم المركبة/الأصل واللي محتاج القائمة كاملة — فالـ pagination بيتفعّل **بس لو `per_page` مبعوت** (صفحة القائمة بتبعته عبر `gcmServerSideAjax`)، من غيره بيرجّع collection كاملة. `AssetListPaginationTest` + `AssetCapacityCategoryListPaginationTest` (فيه تست صريح إن غياب `per_page` بيرجّع القائمة كاملة بدون meta).

  **تحقق:** 10 تستات جديدة (`UserListPaginationTest`, `DriverListPaginationAndStatsTest`, `VehicleListPaginationTest`) بتتأكد إن `per_page` صغير لسه بيرجّع الـ total الصح، صفحة 2 مش بتكرر صفحة 1، الفرز `desc` فعليًا بيعكس الترتيب (اتأكدت الاختبارات دي فعليًا كانت بتفشل قبل إصلاح باگ الـ Stringable)، وإن `sort_by` غير مسموح بيه ميرميش 500. تحقق فعلي بالمتصفح كمان: تأكيد إن كل طلب فعليًا بيوصل بـ `per_page=10` (مش 1000) عبر `read_network_requests`، مش افتراض. **اتحقق منه بحجم واقعي فعليًا** — `database/seeders/DevVehicleVolumeSeeder.php` (أداة dev مؤقتة، مش جزء من `DatabaseSeeder`) بيزرع 3000 مركبة، واستخدمناه لتصفح صفحة Vehicles فعليًا بعنيك على حجم قريب من عميل الـ 7952 موظف.

- **⚠️ باگ بحث اتكشف بنفس اختبار الحجم الواقعي (3000 مركبة): البحث بالقيمة المعروضة بالظبط (`AAA 0001`) مكنش بيرجّع نتيجة.** `Vehicle::plate()` بيعرض اللوحة كـ`plate_letters` + مسافة + `plate_numbers`، لكن البحث في `VehicleController::index()` كان بيقارن كل عمود لوحده (`plate_letters LIKE` أو `plate_numbers LIKE` منفصلين) — مفيش عمود فيه القيمة المجمّعة بالمسافة، فالبحث بالشكل المعروض بالظبط كان بيفشل دايمًا. **الحل:** إضافة مطابقة على التجميع (`CONCAT(plate_letters, ' ', plate_numbers)`) وعلى النسخة بدون مسافة كمان، مع فرع صريح للفرق بين MySQL (`CONCAT`) وSQLite بتاع التستات (`||` operator — `CONCAT` مش موجودة فيه أصلًا، بترمي `no such function` لو استخدمتها زي ما هي). تست `test_searching_the_displayed_plate_with_a_space_finds_the_vehicle` بيغطي 4 صيغ بحث. **درس عام:** أي عمودين بيتعرضوا للمستخدم مجمّعين (`concat` في الـ UI/الـ Resource) لازم يبقى فيه مسار بحث سيرفر-سايد يقارن نفس التجميع، مش بس الأعمدة الخام لوحدها — وأي `whereRaw` بيستخدم دالة DB-specific لازم فرع لـ SQLite (بيئة التستات) صراحة.

  **نفس فئة الباگ اتلاقت كمان في Users وDrivers بعد المراجعة:** عمود `code` هو أول عمود معروض في الجدولين، لكن `UserController`/`DriverController::index()` كان البحث فيهم بيقارن `name`/`email` بس، من غير `code` خالص — بحث بالقيمة المعروضة في أول عمود كان بيفشل دايمًا. اتصلح بإضافة `orWhere('code', 'like', ...)` (Users) و`orWhere('code', ...)` جوه نفس `whereHas('user', ...)` (Drivers، لإن `code` عمود على `users` مش `drivers`). تستات جديدة `test_searching_by_the_displayed_code_finds_the_user`/`_driver` في نفس ملفات الـ pagination tests. تحقق فعلي بالمتصفح كمان (بحث بكود مستخدم/سائق حقيقي، تأكيد الطلب والنتيجة عبر `read_network_requests`).

- **⚠️ باگ أداء منفصل تمامًا (frontend مش backend) اتكشف بسؤال مباشر من المستخدم أثناء نقاش النشر: "هل `npm run build` ممكن يكون سبب بطء؟"** — قياس فعلي عبر `performance.getEntriesByType('resource')` في المتصفح (مش افتراض) على صفحة Users list كشف: **4.8 ميجابايت** إجمالي وزن الصفحة، منها **2.4 ميجابايت في ملف واحد بس** (`datatables-bootstrap5-*.js` — تجميعة DataTables+Bootstrap5+الإضافات بتاعتها من Vuexy). أخطر من الحجم نفسه: **الملف ده كان بيتنزّل من جديد بالكامل في كل تنقّل بين صفحات** (Users → Vehicles → Drivers) — `fetch()` مباشر أكّد صفر headers خاصة بالكاش (`Cache-Control`/`ETag`/`Last-Modified` كلهم `null`)، يعني المتصفح مالوش أي طريقة يعرف إنه نفس الملف من غير ما يعيد تحميله بالكامل تاني. **ده باگ مختلف تمامًا عن باگ الـ per_page=1000 الموثق فوق** — ده وزن الـ JS نفسه اللي بيتحمّل قبل حتى أي طلب API، مش سرعة استجابة الـ API.

  **الحل:** إضافة قاعدة caching في `public/.htaccess` (الملف الرئيسي المتتبّع بـ git — **مش** جوه `public/build/` نفسها لإنها متولّدة ومتمسحة بالكامل في كل `npm run build`، أي حاجة تتحط جواها هتضيع). القاعدة بتحدد الطلبات اللي مسارها `/build/assets/` (عبر `RewriteRule` بتحط environment variable، مش `<FilesMatch>` بامتداد الملف — عشان متأثرش على `public/assets/` غير المُهشّرة اللي ممكن تتغيّر من غير ما يتغيّر اسمها) وتحط عليها `Cache-Control: public, max-age=31536000, immutable`. آمن 100% لإن Vite بيحط hash في اسم كل ملف (`app-XxU8kiOC.js`) — أي تعديل في المحتوى بيغيّر الاسم نفسه، فالكاش الطويل مايعنيش أبدًا محتوى قديم.

  **ملاحظتين مهمتين للسياق:**
  1. القياس ده كان على `php artisan serve` محليًا (بيئة تطوير)، اللي **مالوش أي ضغط (gzip/brotli) ولا أي cache headers أصلًا** — بيانات `npm run build`'s الأصلية سجّلت نفس الملف كـ2.3 ميجا خام مقابل ~1 ميجا بعد gzip، يعني الحجم الحقيقي على Apache/Hostinger (اللي فيه ضغط افتراضي غالبًا) هيبقى تقريبًا نص الرقم ده — لكن مشكلة الكاش المفقود لسه موجودة برضه بغض النظر عن الضغط.
  2. **الإصلاح ده متتأكدش فعليًا بالمتصفح** — `.htaccess` ملف خاص بـ Apache فقط، و`php artisan serve` (سيرفر PHP المدمج البسيط) بيتجاهله تمامًا. لازم يتحقق منه بـ `curl -I` على السيرفر الحقيقي (Hostinger) بعد النشر، مش قبل كده — راجع `DEPLOYMENT.md §12`.
  3. **حجم الـ2.4 ميجا نفسه لسه فرصة تحسين منفصلة، لسه ماتحلتش**: على الأغلب `datatables-bootstrap5` بتجمع إضافات أكتر مما الصفحات التلاتة (Users/Drivers/Vehicles) فعليًا مستخدماه — يحتاج مراجعة دقيقة لملف `resources/assets/vendor/libs/datatables-bootstrap5/` قبل أي تقليم، عشان الوظائف الحالية (Export buttons، Responsive، إلخ) متتكسرش. مؤجل، مش أولوية فورية.

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

## 9. اتفاقيات واجهة ثابتة (Standing UI Conventions)

> قواعد إلزامية على أي صفحة محتوى جديدة من هنا فصاعدًا — مش اقتراح، جزء من تعريف "الصفحة خلصت".

- **Breadcrumb إلزامي على كل صفحة** (`@section('content')`) — عبر الـ partial المشترك `resources/views/_partials/breadcrumb.blade.php`:
  ```blade
  @include('_partials.breadcrumb', ['breadcrumbs' => [
    ['title' => __('Section Name'), 'url' => route('...')],
    ['title' => __('Current Page')],  // آخر عنصر (أو أي عنصر من غير 'url') بيتعرض active مش رابط
  ]])
  ```
  - صفحات التينانت: العناوين عبر `__()` (ثنائية اللغة، زي باقي الصفحة).
  - صفحات `routes/platform.php`: عناوين إنجليزي ثابت من غير `__()` (نفس اتفاقية بقية صفحات الـ Platform)، ولازم تمرّر `'homeUrl' => route('platform.dashboard')` صراحة (الافتراضي `url('/')` بيوصل لداشبورد التينانت مش داشبورد السوبر أدمن).
  - المرجع: أي صفحة من صفحات Users/Drivers/Platform الحالية (أسبوع 2-3) بتطبّق النمط ده بالظبط.

## 9.5 فصل كود GCM الحقيقي عن سقالة Vuexy الديمو (Views/Controllers/Routes)

> اتعمل بعد ملاحظة مباشرة من المستخدم: صفحات المشروع الحقيقية كانت متفرقة وسط عشرات ملفات ديمو Vuexy غير مستخدمة في نفس المجلدات بالظبط (`content/apps/` كان فيه 47 ملف، 8 بس حقيقيين؛ `Controllers/apps/` فيه 42، 4 بس حقيقيين؛ `routes/web.php` 380 سطر، أقل من 20 منهم حقيقي). القرار: **فصل بس، من غير حذف أي ديمو** — حذف الديمو المؤكد إنه مش هيتستخدم مؤجل لاحقًا (قرار مقصود، راجع نقاش الجلسة).

**البنية الجديدة (تنطبق على أي موديول جديد من هنا فصاعدًا — Fleet/Assets/Companies/...):**

| الطبقة | المكان الجديد | مثال |
|---|---|---|
| Views | `resources/views/tenant/{module}/` | `resources/views/tenant/users/list.blade.php` |
| Controllers | `App\Http\Controllers\Web\{Module}\` | `App\Http\Controllers\Web\Users\UserListController` |
| Routes | `routes/tenant.php` (مش `routes/web.php`) | يتحمّل عبر `require` في آخر `web.php`، بيورث middleware group `web` تلقائي |
| Exports (PDF/Excel) | `app/Domain/{Module}/Exports/` | `app/Domain/Users/Exports/UsersExport.php` |
| قالب الـ PDF بتاع الـ Export | `resources/views/tenant/{module}/export-pdf.blade.php` | `resources/views/tenant/users/export-pdf.blade.php` |

- **`routes/web.php` يفضل زي ما هو** — سقالة Vuexy الأصلية بالكامل، بدون أي تعديل تاني غير حذف السطور اللي اتنقلت. أي حد بيدور على راوت GCM حقيقي يفتح `routes/tenant.php` (~45 سطر) مش يدور وسط 380 سطر ديمو.
- **مسارات الراوت (`/app/user/list`) وأسماؤها (`app-user-list`) متغيّرتش خالص** — بس مكان ملف الكنترولر والـ view اتغيّر. يعني أي حاجة بتربط بالاسم (menu JSON، breadcrumbs، `route()` calls، الـ 89 تست) اشتغلت من غير أي تعديل.
- **اكتشاف مهم أثناء النقل:** بعض ملفات "الديمو" مش نسخة منفصلة — هي **الصفحة الحقيقية نفسها بدون تكرار** (مثلاً `content/authentications/auth-login-basic.blade.php` كانت الصفحة الحقيقية لـ `/login` **وكمان** صفحة الديمو `/auth/login-basic` في نفس الوقت، عن طريق كنترولرين مختلفين بيرجّعوا نفس اسم الـ view). لما الملف اتنقل لـ `tenant/auth/login.blade.php`، الكنترولر الديمو (`App\Http\Controllers\authentications\LoginBasic`) اتحدّث يشاور على المكان الجديد بدل ما يتكسر — **مفيش نسخ**، نفس الملف لسه بيخدم الاتنين. نفس الشيء لصفحات forgot/reset password. **درس عام:** قبل نقل أي ملف "ديمو"، لازم تتأكد الأول إنه مش بيتستخدم فعليًا من كنترولر حقيقي، مش تفترض بالاسم بس.
- **تم التحقق فعليًا** (مش افتراض): كل صفحة اتنقلت جُرّبت بمتصفح حقيقي بعد تسجيل دخول فعلي (login → users list → driver add form)، + `curl` على كل route بمصادقة session حقيقية، + الـ 89 تست شغالين، + تأكيد إن صفحات الديمو المرتبطة (login-basic gallery, إلخ) لسه شغالة.
- **موجود من قبل، مبيتأثرش:** `resources/views/platform/` (بالفعل مكان مستقل لصفحات الـ Platform) اتوسّع بـ `platform/auth/login.blade.php` بس (كان قاعد لوحده وسط `content/authentications/` بدون داعي).
- **إضافة لاحقة (نفس الجلسة):** `app/Exports/` (كان فيه `UsersExport.php`/`DriversExport.php` بس، مش مختلط بديمو، لكن برّه أي "موديول" — نقلوا لـ `app/Domain/{Module}/Exports/` عشان يبقوا مع باقي منطق نفس الموديول (Actions/Rules/Exceptions)، وده استكمال طبيعي لنفس القاعدة مش استثناء ليها. قوالب الـ PDF (`resources/views/exports/*-pdf.blade.php`) نقلت بنفس المنطق لـ `resources/views/tenant/{module}/export-pdf.blade.php`. بلاست ريديوس صغير جدًا (2 كنترولر بس بيستوردوا `App\Exports\*`) فاتنقلت مباشرة من غير الحاجة لسؤال.
- **قرار مقصود بعدم النقل: `app/Models/`** — 6 موديولز (`User`, `Driver`, `DriverEntryPermit`, `Tenant`, `PlatformAdmin`, `PersonalAccessToken`) قاعدين flat، **وده مقصود يفضل كده**. الفرق عن Views/Controllers/Exports: مفيش أي تلوث ديمو هنا (كل الملفات بتاعتنا 100%)، والـ Laravel convention القياسي فعلاً flat `app/Models/` (مش استثناء غريب). أهم من كده: `App\Models\User` لوحده متستخدم في **43 ملف** عبر المشروع (Actions, Requests, Policies, Controllers, Seeders, Factories, config/auth.php, تستات) — نقله لموديول Domain هيحتاج تحديث الـ 43 ملف دول كلهم لمجرد فايدة تنظيمية، بلاست ريديوس أكبر بكتير من أي نقل تاني اتعمل، من غير حل مشكلة حقيقية زي مشكلة الديمو. لو الموديلز كبرت لـ 15-20 موديول لاحقًا (مرحلة 2-3) يستاهل نعيد التقييم، لكن دلوقتي مش مبرر.

## 10. Postman Collection

> مجلد `postman/` في جذر المشروع فيه مجموعة Postman كاملة (`GCM-Portal.postman_collection.json` + `GCM-Portal-Local.postman_environment.json` + `README.md`) بتغطي كل الـ endpoints الشغالة حتى الآن — الأسبوع 1+2 وجزء إدارة السائقين من الأسبوع 3، بنظامي المصادقة الاتنين (`/api/v1/*` بـ Bearer token، `/platform/*` بـ session+CSRF).

**قاعدة إلزامية:** أي route جديد يتضاف في `routes/api.php` أو `routes/platform.php` **لازم** ريكوست مقابل له في نفس اللحظة (نفس الـ commit/session)، مش تأجيل لاحق — راجع "قاعدة التحديث" في `postman/README.md` للتفاصيل (المجلد المناسب، نمط الـ pre-request script بتاع CSRF للـ Platform، نمط حفظ الـ id في collection variable للـ Create requests).
