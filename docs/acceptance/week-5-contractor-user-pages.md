# قبول التنفيذ — المرحلة 2: صفحات دور "مستخدم المتعهد" (عرض وتصدير موارد متعهده)

> آخر مزامنة: **FRD V01.14** (2026-10-15 — تصحيح مطبعي واحد في فورم شركة العميل، مالوش أثر هنا) · شغّل `php artisan test` الأول (لازم كله أخضر) ثم `php artisan migrate:fresh --seed`.

الحسابات (الباسورد `password`): `contractor.desert@gcm.test` (تابع لـ**Desert Haulage Co.**) و`contractor.gulf@gcm.test` (تابع لـ**Gulf Logistics**) و`admin@gcm.test` لإنشاء بيانات تجربة. **عشان تشوف صفوف فعلية:** بـ`admin` أنشئ مركبة وسائق وأصل تابعين لـ Desert Haulage، ومركبة وسائق تابعين لـ Gulf Logistics، ومركبة تابعة لـ GCM.

> ملحوظة نطاق: الـFRD §6 بيدّي الدور **عرض/تصدير** لموارد متعهده: **المستخدمين** (سائقيه + مستخدمي المتعهد التابعين له)، و**إدارة الموارد** (السيارات + الحاويات)، و**الرحلات**. اللي بيتغطى هنا هو الأول والتاني. جدول الرحلات ومستند DN مؤجّلين لموديول الرحلات (أسبوع 7). قرارنا: بيان "الحاويات" بيعرض **كل أصول المتعهد (حاويات وصهاريج)** بفلتر النوع — لو العميل عايز الحاويات بس، تعديل بسيط.

---

## أ) القائمة والصفحات

### 1. القائمة الجانبية لمستخدم المتعهد
- **FRD:** §6 (توصيف صفحات النظام لدور مستخدمي المتعهد)
- **الخطوات:** سجّل دخول بـ`contractor.desert@gcm.test` وبص على القائمة الجانبية.
- **المتوقع:** Dashboard + Users (List بس) + Drivers (List بس) + Vehicles (List بس) + Assets (List بس). **مفيش** Add ولا Categories ولا Capacities ولا Contractors ولا Companies ولا Projects ولا Facilities ولا Services ولا General Settings.
- **يغطيه آليًا:** `Contractors/ContractorUserManagementTest::test_a_contractor_user_is_offered_only_the_read_pages_of_its_resources`
- **النتيجة:** ⬜

### 2. الصفحات عرض فقط
- **FRD:** §6 ("ولا يملك صلاحيات تفاعلية")
- **الخطوات:** افتح قوائم المستخدمين والسائقين والمركبات والأصول، وافتح تفاصيل صف من كل واحدة. حاول تفتح يدويًا `/app/vehicle/add` و`/app/asset/add` و`/app/driver/add` و`/app/user/add` و`/app/asset/insert-into-project`.
- **المتوقع:** مفيش زر "Add" ولا أيقونة تعديل ولا كارت تغيير حالة في أي صفحة. فتح روابط الإضافة يدويًا = 403.
- **يغطيه آليًا:** `Contractors/ContractorUserResourcePagesTest` (`test_the_read_pages_open_and_the_form_pages_do_not`, `test_the_pages_are_view_only_for_the_role`)
- **النتيجة:** ⬜

### 3. الفلاتر اللي مالهاش معنى متخفية
- **FRD:** §6
- **الخطوات:** في قوائم المستخدمين والسائقين والمركبات والأصول بص على صف الفلاتر.
- **المتوقع:** مفيش فلتر "التبعية" ولا "الشركة" (كل الصفوف تابعة لمتعهده أصلًا). فلتر الدور في المستخدمين فيه "contractor_user" و"driver" بس. باقي الفلاتر (الحالة، التصنيف، حالة الوثائق...) شغالة.
- **يغطيه آليًا:** `Contractors/ContractorUserResourcePagesTest::test_the_filters_that_do_not_apply_are_hidden`
- **النتيجة:** ⬜

---

## ب) نطاق الرؤية (المتعهد بتاعه بس)

### 4. المستخدمين: سائقيه ومستخدمو متعهده فقط
- **FRD:** §6.1.1 (عرض السائقين الخاصين به + عرض مستخدمي المتعهد الخاصين به)
- **الخطوات:** بـ`contractor.desert@gcm.test` افتح Users.
- **المتوقع:** بتشوف سائقي Desert وباقي مستخدمي Desert بس. **مش** حسابك نفسه، ولا مستخدمي/سائقي Gulf، ولا سائقي GCM، ولا موظفي GCM، ولا حسابات العملاء. فتح تفاصيل حساب خارج النطاق بالـid = 403.
- **يغطيه آليًا:** `Contractors/ContractorUserResourcePagesTest` (`test_the_users_list_holds_only_its_contractors_drivers_and_users`, `test_the_users_list_filters_by_role_inside_that_scope`, `test_it_opens_its_own_drivers_and_colleagues_only`)
- **النتيجة:** ⬜

### 5. السائقين: سائقيه فقط (بالوثائق)
- **FRD:** §6.1.1
- **الخطوات:** افتح Drivers، وبعدين تفاصيل سائق من سائقيك. جرّب `/app/driver/view/<id>` لسائق Gulf أو سائق GCM.
- **المتوقع:** القايمة والكروت (متاح / في إجازة / معطّل) أرقامها لسائقي متعهدك بس. تفاصيل سائقك بتفتح بوثائقه (الإقامة، الرخصة...). سائق متعهد تاني أو تابع لـGCM = 403، وتحميل وثيقته = 403.
- **يغطيه آليًا:** `Contractors/ContractorUserResourcePagesTest` (`test_the_drivers_list_stats_and_export_are_its_own`, `test_it_opens_its_own_driver_but_not_another_contractors_or_gcms`, `test_a_document_download_re_checks_the_same_rule`)
- **النتيجة:** ⬜

### 6. المركبات: مركبات متعهده فقط
- **FRD:** §6.1.2 (بيان بالسيارات)
- **الخطوات:** افتح Vehicles، وتفاصيل مركبة، وجرّب فتح مركبة Gulf أو GCM بالـid.
- **المتوقع:** القايمة وكروت التصنيفات والحالة (`stats`) أرقامها لمركبات متعهدك بس. تفاصيل مركبة متعهد تاني أو GCM = 403، وتحميل وثائقها = 403.
- **يغطيه آليًا:** `Contractors/ContractorUserResourcePagesTest` (`test_the_vehicles_list_stats_and_export_are_its_own`, `test_it_opens_its_own_vehicle_but_not_another_contractors_or_gcms`)
- **النتيجة:** ⬜

### 7. الأصول: أصول متعهده فقط
- **FRD:** §6.1.2 (بيان بالحاويات)
- **الخطوات:** افتح Assets، وتفاصيل أصل، وجرّب أصل Gulf أو GCM بالـid.
- **المتوقع:** القايمة وكروت الحاويات/الصهاريج لأصول متعهدك بس. أصل متعهد تاني أو GCM = 403.
- **يغطيه آليًا:** `Contractors/ContractorUserResourcePagesTest` (`test_the_assets_list_stats_and_export_are_its_own`, `test_it_opens_its_own_asset_but_not_another_contractors_or_gcms`)
- **النتيجة:** ⬜

### 8. التصدير بنفس النطاق
- **FRD:** §6 (عرض / تصدير)
- **الخطوات:** من كل قايمة (مستخدمين، سائقين، مركبات، أصول) دوس Export ← Excel ثم PDF.
- **المتوقع:** الملف فيه صفوف متعهدك بس (نفس اللي على الشاشة بنفس البحث والفلاتر)، وبرأس الـGCM المعتاد.
- **يغطيه آليًا:** `Contractors/ContractorUserResourcePagesTest` (نفس اختبارات القوايم — التصدير فيها)
- **النتيجة:** ⬜

---

## ج) الحماية

### 9. ممنوع أي كتابة
- **FRD:** §6 ("ولا يملك صلاحيات تفاعلية اخري")
- **الخطوات:** بـ Postman بتوكن مستخدم المتعهد: `POST /vehicles` · `PATCH /vehicles/{id}` و`/status` · نفس الحاجة للأصول والسائقين · `POST /users` · `PATCH /users/{id}/status`.
- **المتوقع:** 403 في كل الحالات، ومفيش حاجة اتغيّرت.
- **يغطيه آليًا:** `Contractors/ContractorUserResourcePagesTest::test_it_never_writes_anything`
- **النتيجة:** ⬜

### 10. حساب متعهد من غير متعهد مرتبط
- **الخطوات:** (تحقق آلي بس — مش حالة بتحصل من الواجهة) مستخدم متعهد `contractor_id = null`.
- **المتوقع:** كل القوايم فاضية (مش كل بيانات الشركة)، وأي تفاصيل = 403.
- **يغطيه آليًا:** `Contractors/ContractorUserResourcePagesTest::test_a_contractor_account_with_no_contractor_sees_nothing`
- **النتيجة:** ⬜

### 11. باقي الأدوار ماتأثرتش
- **الخطوات:** سجّل دخول بـ`auditor` وافتح قوايم المركبات والأصول.
- **المتوقع:** المراقب لسه بيشوف كل مركبات وأصول كل المتعهدين وGCM (النطاق بيضيّق دور مستخدم المتعهد بس).
- **يغطيه آليًا:** `Contractors/ContractorUserResourcePagesTest::test_staff_still_see_every_contractors_resources`
- **النتيجة:** ⬜

---

## د) مؤجّل عمدًا

- **جدول الرحلات** الخاصة بمستخدمي المتعهد أو سياراته (الحاويات لأ) + الاطلاع على بيانات الرحلة ومستند DN: مرتبط بموديول الرحلات (أسبوع 7).
- **"عرض كلمة المرور"** في تفاصيل حساب مستخدم المتعهد (مذكورة في §1.4 للتفاصيل): مش مطبّقة عمدًا (كلمة السر مخزّنة مُشفّرة ومينفعش تتعرض) — غالبًا غلطة في المستند، محتاجة تأكيد العميل.
