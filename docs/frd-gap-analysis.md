# مستند مقارنة الفجوات — الأسابيع 1-3 مقابل الـFRD

> آخر مزامنة: FRD V01.09 (`GCM_Portal_NewSystem_FRD_V01.09.docx`)
> النطاق: §1.1 – §1.7 بس (كل اللي المفروض يكون مبني في الأسابيع 1-3). أي قسم بعد §1.7 (منشآت النفايات، الخدمات، المتعهدين، العملاء، المشروعات، التعاقدات، الرحلات) خارج النطاق عمدًا — مؤجل للأسبوع 4+ ومفيش كود بيغطيه لسه.
> طريقة الفحص: استخراج نص الـFRD مباشرة من `word/document.xml` (مش الـpdf)، مقارنة سطر بسطر مع الكود الفعلي (Policies/Requests/Actions/Views)، تشغيل `php artisan test` للتأكد.

## طريقة القراءة

| الرمز | المعنى |
|---|---|
| ✅ | مطابق للـFRD ومتأكد منه (كود + تست، أو تحقق مباشر بالمتصفح) |
| ⚠️ | فيه انحراف عن نص الـFRD الحرفي — إما مكتشف حديثًا ومحتاج قرار، أو قرار مقصود اتاخد قبل كده وموثّق |
| ❌ | مش متنفذ خالص — غالبًا مؤجل بقرار واضح، مش نسيان |

---

## 🔴 أهم اكتشاف — صلاحيات مدخل البيانات (data_entry) في Users + Drivers أضيق من الـFRD

**الحالة: ✅ اتصلحت.**

نص الـFRD بيتكرر بنفس الصيغة تمامًا في كل موديول (المستخدمين، السائقين، المركبات، الأصول):

> "(انشاء / تعديل) [الحسابات/المركبات/...] مسؤولية (مدير النظام / مدخل البيانات)"
> "تعديل الحساب الي حالة (في إجازة) من خلال (مدير النظام / مدخل البيانات)"
> "حالة التعطيل – Deactivated مسؤولية مدير النظام فقط"

يعني القاعدة الموحّدة في كل موديول: **الإنشاء + التعديل + "في إجازة/في الصيانة" = system_admin أو data_entry. التعطيل النهائي وإعادة التنشيط منه فقط = system_admin.**

**المركبات (`VehiclePolicy` + `UpdateVehicleStatusAction`) والأصول (`AssetPolicy` + `UpdateAssetStatusAction`) بيطبّقوا القاعدة دي بالظبط** — كل الاتنين فيهم docblock صريح بيقتبس نص الـFRD، وفيهم split واضح بين "on_maintenance" (مسموح لـ data_entry) و"deactivated" (system_admin بس).

**المستخدمين (`UserPolicy`) والسائقين (`DriverPolicy`) لأ:**

```php
// app/Policies/UserPolicy.php — كل method بترجع system_admin بس
public function create(User $actor): bool { return $actor->hasRole('system_admin'); }
public function update(User $actor, User $target): bool { return $actor->hasRole('system_admin') && ! $target->hasRole('driver'); }
public function updateStatus(User $actor, User $target): bool { return $actor->hasRole('system_admin'); }
```

```php
// app/Policies/DriverPolicy.php — نفس الحال، وفيه تعليق صريح إنه "نسخة" من UserPolicy
public function create(User $actor): bool { return $actor->hasRole('system_admin'); }
public function update(User $actor, Driver $target): bool { return $actor->hasRole('system_admin'); }
```

وبما إن حساب السائق أصلاً `User`، تغيير حالة السائق (نشط/إجازة/معطّل) بيمر أصلاً عن طريق `PATCH /api/v1/users/{id}/status` (قرار معماري مقصود وموثّق في `UpdateDriverAction`'s docblock) — يعني نفس الـ`UserPolicy::updateStatus` المقفول على system_admin بيمنع data_entry من تغيير حالة السائق كمان، مش بس المستخدم العادي.

**الأثر الفعلي:** دور data_entry حاليًا **معندوش أي وصول خالص** لصفحات Users/Drivers (`GET /api/v1/users` بترجع 403 له — مؤكد في `UserPolicyTest::test_non_system_admin_roles_cannot_list_users`، اللي بيحط `data_entry` صراحة في قائمة الأدوار المتوقع يترفضوا). الفرق شاسع: من المفروض data_entry يقدر يعرض/ينشئ/يعدّل/يحط "في إجازة" — وحاليًا مايقدرش حتى يفتح القائمة.

**الملفات المحتاجة تعديل لو اتقرر نصلحها:**
- `app/Policies/UserPolicy.php` — `viewAny`/`view`/`create`/`update` يضيفوا `data_entry`؛ `updateStatus` يتقسم (زي `VehiclePolicy::updateStatus` مش كافي لوحده — الـsplit الفعلي لازم يبقى جوه `UpdateUserStatusAction` زي `UpdateVehicleStatusAction`).
- `app/Domain/Users/Actions/UpdateUserStatusAction.php` — يضيف فحص: `deactivated`/إعادة تنشيط من `deactivated` = system_admin بس، `on_vacation` = system_admin أو data_entry.
- `app/Policies/DriverPolicy.php` — نفس تعديل `UserPolicy`.
- `resources/menu/verticalMenu.json`/`horizontalMenu.json` — عنصري Users/Drivers محتاجين `"roles"` تتضاف ليهم `data_entry` (حاليًا لو من غير `roles` صراحة، بيظهروا لـsystem_admin بس تحت القاعدة الافتراضية الحالية — لازم يتفحصوا).
- **تستات لازم تتحدّث** (مش تتحذف — تتنقل لقائمة "الأدوار المرفوضة" الصح): `tests/Feature/Users/UserPolicyTest.php:49` و`tests/Feature/Drivers/DriverPolicyTest.php:47` حاليًا بيحطوا `data_entry` في `nonAdminRolesProvider` (المتوقع يترفض) — ده هيبقى غلط بعد الإصلاح، محتاج يتشال من القائمة دي ويتضافله تستات إيجابية بدلها (زي نمط `VehiclePolicyTest`).

**اتصلحت الجلسة اللي فاتت** — `UserPolicy`/`DriverPolicy` بقوا `hasAnyRole(['system_admin', 'data_entry'])` لـ`viewAny`/`view`/`create`/`update`، و`UpdateUserStatusAction` بقى فيه split صريح (زي `UpdateVehicleStatusAction` بالظبط): `on_vacation` مسموح لـdata_entry، `deactivated`/إعادة التنشيط منه لسه system_admin بس (استثناء جديد `CannotDeactivateUserException`). عناصر Users/Drivers في `verticalMenu.json`/`horizontalMenu.json` اتضافلهم `data_entry`. تستات قديمة كانت بتفترض data_entry ممنوع تمامًا اتحدّثت (`UserPolicyTest`/`DriverPolicyTest`/`MenuVisibilityTest`) + تستات جديدة إيجابية. 210 تست إجمالًا. اتحقق فعليًا بالمتصفح: تسجيل دخول data_entry → القائمة بقت فيها Users+Drivers، `GET /api/v1/users`/`drivers` بترجع 200، تغيير حالة لـ"في إجازة" ينجح، محاولة "تعطيل" ترفض بـ422 ورسالة واضحة.

---

## §1.1 — تسجيل الدخول واستعادة كلمة المرور

| البند | الحالة | ملاحظات |
|---|---|---|
| تسجيل دخول بالإيميل/كلمة المرور | ✅ | `LoginController`, تست `TenantLoginTest`/`PlatformLoginTest` |
| استعادة كلمة المرور (إيميل → رابط إعادة تعيين) | ✅ | `PasswordResetLinkController`+`NewPasswordController`، تست `PasswordResetTest` |
| ✅ رسالة الحساب المعطّل عند الدخول | **اتصلحت** | `LoginController.php` بقى بيفصل التحقق: أول حاجة يتأكد إن الإيميل/الباسورد صح (رسالة عامة `auth.failed` لو غلط)، وبعدين بس لو الحساب معطّل يرجّع رسالة الـFRD الصريحة (`auth.deactivated`، مفتاح جديد في `lang/en/auth.php`). **الترتيب ده مقصود لأسباب أمنية** — لو الرسالة المميزة ظهرت قبل التأكد من صحة الباسورد، حد من غير الباسورد الصح كان هيقدر يعرف إن حساب معيّن معطّل بس بمعرفة الإيميل (user enumeration). تستان جديدان في `TenantLoginTest` (الرسالة الصحيحة تظهر بباسورد صح، والرسالة العامة تظهر برضو لو الباسورد غلط حتى لو الحساب معطّل). اتحقق فعليًا بالمتصفح على صفحة `/login` الحقيقية بالحالتين. |

## §1.2 — لوحة التحكم / الشريط العلوي

| البند | الحالة | ملاحظات |
|---|---|---|
| القائمة الجانبية + فلترة حسب الدور | ✅ | `MenuComposer`, موثّق بالتفصيل في `ARCHITECTURE.md §3`. متجمّعة تحت عنوانين (Accounts / Fleet & Assets) بطلب العميل — تنظيم بس، مش من الـFRD |
| اللوجو | ✅ | سقالة Vuexy الأصلية |
| **التاريخ والوقت الحالي** | ✅ (مكانه ⚠️ بطلب العميل) | `navbar.blade.php` + `app.js`، لايف كل 30 ثانية، عربي/إنجليزي حسب locale التينانت، إنجليزي ثابت في الـPlatform. **الـFRD بيرتبه أول عنصر جنب باقي عناصر الشريط، لكن بطلب العميل بقى على الطرف المقابل لمجموعة الأيقونات** (مش متكوّم معاها) |
| قائمة اللغة (عربي افتراضي / إنجليزي) | ✅ | موجودة وشغالة |
| **تغيير الوضع (داكن/مضيء)** | ✅ **رجع اشتغل بطلب صريح من المستخدم** | كان مقفول (`hasCustomizer => false`)، اتأكد الأول من المستخدم إن القرار ساري، وبعدين اتغيّر الرأي فورًا وطلب تفعيله. `config/custom.php`: `hasCustomizer => true` (بيحمّل محرك الـtheme اللازم لزرار التبديل ويخليه يفضل محفوظ في `localStorage`)، مع الإبقاء على `displayCustomizer => false` (لوحة الـcustomizer الكاملة بالـRTL/الـlayout options لسه مقفولة — الطلب كان تحديدًا زرار داكن/مضيء بس، مش اللوحة كلها). زرار النافبار (شمس/قمر/جهاز) بقى شغال فعليًا: Light/Dark/System، محفوظ عبر الصفحات. |
| قائمة الإشعارات (آخر 5 + رابط لكل الإشعارات) | ❌ **مؤجّل بقرار موثّق** | `navbar.blade.php` فيه dropdown فاضي بتعليق صريح: *"intentionally has no notification list yet. This will connect to a real notifications API in a later phase."* مفيش نظام إشعارات فعلي في الباك اند خالص لسه. |
| صفحة الإشعارات المستقلة (§1.3.2) | ❌ | نفس السبب فوق — مفيش أي route/controller حقيقي، بس بقايا سقالة Vuexy الديمو غير المربوطة (`pages-account-settings-notifications` وغيرها) |
| صورة المستخدم + روابط إدارة الحساب | ✅ | اتصلحت الجلسة دي — كانت صورة ديمو ثابتة، بقت تعرض صورة المستخدم الفعلية لو موجودة |
| تسجيل الخروج | ✅ | مسارين منفصلين (session لـPlatform، API لـtenant) |
| فوتر مبسط + حقوق الملكية | ✅ | سقالة Vuexy الأصلية |

## §1.3 — الملف الشخصي

| البند | الحالة | ملاحظات |
|---|---|---|
| تعديل الاسم من صفحة الملف الشخصي | ✅ (اتشال بقرار مطابق للـFRD) | الـFRD مش بيذكر الاسم كحاجة يعدلها المستخدم بنفسه في أي دور — الاسم للقراءة فقط دلوقتي |
| رفع/تغيير الصورة الشخصية من صفحة الملف الشخصي | ✅ | اتضاف الجلسة دي |
| تغيير كلمة المرور ذاتيًا | ✅ **اتحسم — مطابق للـFRD** | كنت وصفته "تعارض" وده كان غلط مني: الـFRD بيفرّق حسب النوع — موظفو GCM ("غير قابلة للتعديل من قبل المستخدم ولكن من قبل مدير النظام او مدخل البيانات") والسائق (الصورة فقط) مايغيّروش الباسورد؛ العميل والمتعهد (أسبوع 4-5) يغيّروه. اتنفذ: صفحة الملف الشخصي = صورة فقط، صفحة/endpoint الأمان 403 (`User::canChangeOwnPassword()`، جاهز لأدوار العميل/المتعهد)، وحقل "كلمة مرور جديدة" اختياري في تعديل المستخدم والسائق لمدير النظام/مدخل البيانات |
| الإيميل غير قابل للتعديل | ✅ | مقصود (`LoginController` بيدور بالإيميل globally قبل معرفة الـtenant) |

## §1.4 — المستخدمين + السائقين

| البند | الحالة | ملاحظات |
|---|---|---|
| صلاحيات (عرض/إنشاء/تعديل/تعطيل) | ✅ | **اتصلحت — راجع القسم "🔴 أهم اكتشاف" فوق** |
| عناوين الصفحات مطابقة لنص "عنوان الصفحة" | ✅ | اتصلحت جلسة سابقة (19 صفحة) |
| "آخر تحديث: تم بواسطة X — التاريخ والوقت" | ✅ | اتضاف الجلسة اللي فاتت لـUsers/Drivers/Vehicles (كان موجود بالفعل لـAssets) |
| رقم الموبايل (حساب واتساب) — label | ✅ | اتصلح الجلسة دي |
| رقم الموبايل مايتكررش داخل نفس الـtenant | ✅ | اتضاف الجلسة دي (validation + DB constraint) |
| مدير نظام واحد بس لكل tenant، غير قابل للتعطيل | ✅ | `OnlyOneSystemAdminPerTenantRule` + `CannotDeactivateSystemAdminException` |
| الإيميل فريد عالميًا (مش بس داخل التينانت) | ✅ | مقصود — `LoginController` بيدور بيه globally |
| قاعدة "لا حذف، تعطيل بس" | ✅ | مفيش `DELETE` route خالص لـUsers/Drivers |
| حالة "في إجازة" — المستخدم يفقد التفاعل ومايظهرش في قوائم الاختيار | ⚠️ **غير قابل للتحقق لسه** | القاعدة دي مرتبطة بموديول الرحلات (Trips) اللي لسه مبنيش (أسبوع 6+) — مفيش "قائمة اختيار سائق لرحلة" فعلية نقيس عليها دلوقتي. مش فجوة فعلية، بس محتاجة تتراجع لما Trips يتبني. |
| إنشاء/تعديل سائق حصريًا عبر `/api/v1/drivers` | ✅ | قرار مؤكد 3 مرات من نص الـFRD — `ARCHITECTURE.md §5` |
| اسم السائق حقل واحد (مش عربي/إنجليزي منفصلين) | ✅ | **اتحسم الجلسة اللي فاتت** — "الاسم باللغة العربية/الإنجليزية" في صفحة عرض السائق فقط نصيّة copy-paste artifact في الـFRD نفسه، مفيش حقل إدخال ثاني في أي فورم إنشاء لأي دور |
| بيانات السائق الكاملة (إقامة/رخصة/رخصة تشغيلية/تأمين/تصاريح دخول) | ✅ | مبنية بالكامل، `DriverCreationTest`/`DriverUpdateTest` |
| "المركبة الافتراضية" — مركبة واحدة لكل سائق | ✅ | `withValidator` في Store/UpdateDriverRequest |
| تصدير Users (Excel/PDF) | ✅ | `UserController::export`, تست `export returns xlsx and pdf` |
| زر "إنشاء مستخدم جديد" قائمة منسدلة (GCM Staff/سائق) | ✅ | نمط DataTables Buttons collection، مطابق لنص الـFRD |

## §1.5 — المركبات (Vehicles)

| البند | الحالة | ملاحظات |
|---|---|---|
| صلاحيات (system_admin/data_entry إنشاء وتعديل، auditor عرض/تصدير) | ✅ | `VehiclePolicy` مطابق تمامًا، docblock بيقتبس الـFRD |
| حالة "في الصيانة" = system_admin/data_entry، التعطيل = system_admin بس | ✅ | `UpdateVehicleStatusAction` — النمط المرجعي لباقي الموديولات |
| لوحة فريدة داخل نفس الـtenant | ✅ | DB constraint + validation، `VehicleUniquePlateTest` |
| الحاوية المدمجة وفلترة السعة حسب مجمع الأصول | ✅ | `EmbeddedCapacityFitsVehicleRule`, `VehicleEmbeddedCapacityTest` |
| تصنيفات المركبات (§1.5.2: خمسة ثابتة) | ⚠️ **انحراف مقصود بطلب العميل** | الـFRD بيثبّت 5 تصنيفات بس (والعميل ضاف سادس: شاحنة جرّارة / Tractor truck — `VehicleCategory::DEFAULTS`). بطلب العميل بقت **خاصة بكل شركة** ومدير النظام بيضيف/يعدّل/يحذف (الحذف ممنوع لو مربوط بمركبات/سائقين/أصول). كل شركة بتبدأ بالستة الافتراضية (`Tenant::created`). راجع `WEEKLY_PLAN.md` |
| "آخر تحديث بواسطة" | ✅ | اتضاف الجلسة اللي فاتت |

## §1.6 — قائمة السائقين

| البند | الحالة | ملاحظات |
|---|---|---|
| قائمة سائقين server-side مع فلاتر/بحث/إحصائيات | ✅ | `datatables-server-side.js`, `DriverListPaginationAndStatsTest` |
| فلتر التبعية (GCM/متعهد) فعلي سيرفر-سايد | ✅ | اتصلح باگ كان شكلي بالكامل |

## §1.7 — مجمع الأصول والمخزون (Assets)

| البند | الحالة | ملاحظات |
|---|---|---|
| صلاحيات (system_admin/data_entry إنشاء وتعديل، auditor عرض/تصدير) | ✅ | `AssetPolicy` مطابق تمامًا، نفس نمط Vehicles |
| حالة "في الصيانة"/"التعطيل" | ✅ | `UpdateAssetStatusAction` |
| تعديل الأصل مقصور على الاسم بس | ✅ | مؤكد من نص الـFRD حرفيًا (سطر 1161) |
| تصنيفات سعة الأصول (Container/Tank/Both) | ✅ | CRUD كامل + فلترة حسب النوع |
| "تصنيف المركبات المتناسب" per-asset | ✅ | قرار اتثبّت مرتين |
| "آخر تحديث بواسطة" | ✅ | كان أول موديول اتطبق عليه العمود ده |

---

## ملخص الأولويات المقترحة (مش قرار نهائي — للمناقشة)

1. **✅ صلاحيات data_entry في Users/Drivers** — اتصلحت.
2. **✅ رسالة الحساب المعطّل عند تسجيل الدخول** — اتصلحت.
3. **✅ كلمة المرور الذاتية** — اتحسمت ونُفّذت (راجع §1.3).
4. **✅ تغيير الوضع (داكن/مضيء)** — رجع اشتغل بطلب صريح من المستخدم.
5. **❌ نظام الإشعارات** — مؤجل بقرار واضح، مش أولوية إلا لو رأيك غير كده.
