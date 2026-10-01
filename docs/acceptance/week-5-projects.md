# قبول التنفيذ — المرحلة 2: مشروعات العملاء + إدراج أصل في مشروع

> آخر مزامنة: **FRD V01.14** (2026-09-29) · شغّل `php artisan test` الأول (لازم كله أخضر) ثم `php artisan migrate:fresh --seed`.

نفس الحسابات من ملف الأسبوع 1–2. بعد الـseed فيه 4 مشروعات تجريبية: `Al Noor Tower` (`ALN-P0001`، وفيه الحاوية `Container A-01` مدرجة) و`Al Noor Residential Complex` (`ALN-P0002`) لشركة Al Noor، و`Gulf Petro Plant Expansion` (`GPC-P0001`) و`Gulf Petro Warehouse` (`GPC-P0002` — معطّل) لشركة Gulf Petrochemicals.

> **حساب ممثل المشروع وعدد المستخدمين** اتفعّلوا مع حسابات العملاء (شوف `week-5-client-accounts.md`، البنود 12–13). **مؤجل لحد ما موديولاته تتبني:** قسم التعاقدات في التفاصيل وعمود "التعاقدات" وإحصائيات الرحلات والنفايات — كلهم بيظهروا دلوقتي `—` أو `0` أو رسالة "هتظهر هنا لما الموديول يبقى متاح". **إخراج أصل من مشروع** مش معرّف في الـFRD (هيجي مع الرحلات).

---

## أ) القائمة والصلاحيات — `/app/project/list`

### 1. القائمة الجانبية
- **FRD:** §1.12 (إدارة مشروعات العملاء)
- **الخطوات:** سجّل دخول بـ`admin` ثم `dataentry` ثم `auditor` ثم `driver` وبص على القائمة الجانبية.
- **المتوقع:** الثلاثة الأوائل يشوفوا تحت عنوان **Clients & Projects** بند **Client Projects** (List + Add — وAdd مش ظاهر للـ`auditor`). `driver` مايشوفش لا البند ولا العنوان، والرابط اليدوي `/api/v1/projects` يرجّع 403.
- **يغطيه آليًا:** `MenuVisibilityTest::test_client_projects_menu_visibility_per_role`، `Projects/ProjectPolicyTest::test_driver_has_no_access_at_all`
- **النتيجة:** ⬜

### 2. صفحة القائمة (جدول + بحث + فلاتر)
- **FRD:** §1.12 (جدول المشروعات: الرقم التعريفي، الشركة، المشروع، التعاقدات، المستخدمين، الحالة، التفاصيل)
- **الخطوات:** افتح Client Projects ← List بـ`admin`. جرّب البحث باسم مشروع، وبالرقم التعريفي (زي `ALN-P`)، وباسم الشركة. جرّب فلتر "الشركة" وفلتر "الحالة".
- **المتوقع:** جدول فيه الأعمدة دي والصفوف الأربعة. البحث بيلاقي بكل واحدة من الثلاثة، وفلتر الشركة بيعرض شركات العميل كلها (حتى المعطّلة)، وفلتر الحالة بيعمل نشط/معطّل. عمود التعاقدات `0`، وعمود المستخدمين بيعرض العدد الحقيقي (Al Noor Tower: `2`).
- **يغطيه آليًا:** `Projects/ProjectManagementTest::test_list_filters_by_company_status_and_search`, `test_the_list_can_be_sorted_and_paginated`, `test_an_unknown_sort_column_falls_back_to_the_name`
- **النتيجة:** ⬜

---

## ب) الإنشاء — `/app/project/add`

### 3. إنشاء مشروع بالحد الأدنى + الرقم التعريفي
- **FRD:** §1.12.2 (إنشاء مشروع جديد)
- **الخطوات:** دوس "Add Project". اكتب اسم مشروع، واختار شركة (`Al Noor Construction`)، واترك الباقي، والحالة "نشطة". دوس Submit.
- **المتوقع:** بترجع للقائمة برسالة نجاح والمشروع ظاهر. رقمه التعريفي **من الاسم المختصر للشركة + `P` + رقم تسلسلي للشركة**: `ALN-P0003` (بعد مشروعين للشركة دي).
- **يغطيه آليًا:** `Projects/ProjectManagementTest::test_system_admin_creates_a_project_with_only_the_required_fields`, `test_name_company_and_status_are_required`
- **النتيجة:** ⬜

### 4. الترقيم بيعدّ لكل شركة
- **FRD:** §1.11.2 (الاسم المختصر "يستخدم كخليط مع الأرقام للتعبير عن رقم غير متكرر يخص الشركة")
- **الخطوات:** أنشئ مشروع تاني لـ`Gulf Petrochemicals`، وبعده مشروع تالت لـ`Al Noor Construction`.
- **المتوقع:** مشروع Gulf ياخد `GPC-P0003`، والتاني لـAl Noor ياخد `ALN-P0004` — كل شركة بتعدّ لوحدها.
- **يغطيه آليًا:** `Projects/ProjectManagementTest::test_the_project_id_is_the_company_prefix_plus_a_running_number_per_company`, `Projects/ProjectTenantIsolationTest::test_the_same_code_can_exist_in_two_tenants`
- **النتيجة:** ⬜

### 5. إنشاء مشروع بكل البيانات
- **FRD:** §1.12.2
- **الخطوات:** افتح "Add Project". املأ: المنطقة التشغيلية، الهاتف، البريد، العنوان، رابط الخريطة (`https://maps.google.com/...`)، وبيانات إضافية. جرّب رابط `javascript:alert(1)` في خانة الخريطة أولًا.
- **المتوقع:** رابط `javascript:` بيترفض برسالة تحت الخانة، ورابط `https` عادي بيتقبل. كل البيانات بتظهر في صفحة التفاصيل والرابط قابل للضغط.
- **يغطيه آليًا:** `Projects/ProjectManagementTest::test_all_the_optional_fields_are_saved`, `test_the_map_link_must_be_an_http_or_https_url`, `test_an_empty_rich_text_editor_is_stored_as_null`
- **النتيجة:** ⬜

### 6. الشركة المعطّلة مش بتظهر في اختيارات المشروع الجديد
- **FRD:** §1.11 (شركة العميل المعطّلة "مش متاحة للاختيار في العمليات الجديدة")
- **الخطوات:** افتح "Add Project" وافتح قائمة "شركة العميل" واكتب `Riyadh` في البحث.
- **المتوقع:** الشركة المعطّلة (`Riyadh Real Estate Group`) مش موجودة؛ الشركتان النشطتان بس. القائمة بتدعم البحث الحي.
- **يغطيه آليًا:** `Projects/ProjectManagementTest::test_a_deactivated_company_is_not_offered_for_new_projects`, `test_another_tenants_company_cannot_own_a_project`
- **النتيجة:** ⬜

---

## ج) التفاصيل — `/app/project/view/{id}`

### 7. صفحة تفاصيل مشروع
- **FRD:** §1.12.3 (صفحة عرض تفاصيل مشروع)
- **الخطوات:** من القائمة دوس أيقونة العين على `Al Noor Tower`.
- **المتوقع:** نفس شكل تفاصيل شركة العميل: كارت شمال (أيقونة + الاسم + الحالة + الرقم التعريفي + الشركة كرابط + المنطقة + الهاتف + البريد + العنوان + رابط الخريطة + بيانات إضافية + زرّي Edit وBack)، ويمين: ثلاث إحصائيات (التعاقدات / الرحلات / النفايات — `0`)، قسم التعاقدات (رسالة "هتظهر هنا لما الموديول يبقى متاح")، وجدول **"الأصول الموجودة في هذا المشروع"** فيه `Container A-01`. آخر تحديث بواسطة ظاهر تحت العنوان.
- **يغطيه آليًا:** `Projects/ProjectManagementTest::test_show_returns_the_project_with_its_company`, `test_the_pages_render_for_a_manager`
- **النتيجة:** ⬜

### 8. الأزرار الكتابية مخفية عن المراقب
- **FRD:** §1.12 (الإنشاء والتعديل لمدير النظام / مدخل البيانات)
- **الخطوات:** سجّل دخول بـ`auditor` وافتح قائمة المشروعات وتفاصيل مشروع.
- **المتوقع:** مفيش زر "Add Project" ولا أيقونة التعديل ولا زر Edit ولا زر "إدراج أصل"؛ العرض والتصدير شغالين. أي طلب كتابة على الـAPI بيرجع 403.
- **يغطيه آليًا:** `Projects/ProjectPolicyTest::test_auditor_can_view_and_export_but_not_change_anything`, `test_the_manager_only_buttons_are_hidden_from_the_auditor`
- **النتيجة:** ⬜

---

## د) التعديل والحالة — `/app/project/edit/{id}`

### 9. تعديل مشروع (الشركة والرقم التعريفي مقفولين)
- **FRD:** §1.12.4 (تعديل تفاصيل مشروع)
- **الخطوات:** افتح تعديل `Al Noor Tower`. غيّر الاسم والعنوان واحفظ.
- **المتوقع:** الرقم التعريفي ظاهر فوق الفورم، وخانة الشركة **مقفولة** (الرقم مبني عليها). بعد الحفظ بترجع لصفحة التفاصيل برسالة "Changes saved successfully" وبيانات محدّثة، والرقم التعريفي زي ما هو `ALN-P0001`، و"آخر تحديث بواسطة" اتغيّر.
- **يغطيه آليًا:** `Projects/ProjectManagementTest::test_update_changes_the_fields_but_never_the_company_or_the_code`, `test_update_still_requires_a_name`
- **النتيجة:** ⬜

### 10. تعطيل / إعادة تنشيط مشروع
- **FRD:** §1.12.4 (تعطيل / تنشيط — مدير النظام / مدخل البيانات)
- **الخطوات:** في صفحة تعديل `Al Noor Residential Complex` نزل لكارت "Project Status"، اختار "Deactivated"، علّم "I confirm"، ودوس Update Status. بعدها افتح شركة `Al Noor Construction` وشوف حالتها.
- **المتوقع:** رسالة نجاح والمشروع بقى معطّل في القائمة. الشركة **لسه نشطة** — تعطيل المشروع مابيمسّش الشركة (وتعطيل الشركة مابيعطّل مشروعاتها). إعادة التنشيط بنفس الطريقة.
- **يغطيه آليًا:** `Projects/ProjectManagementTest::test_status_can_be_changed_both_ways_by_system_admin_and_data_entry`, `test_deactivating_a_company_leaves_its_projects_alone_and_vice_versa`, `test_an_unknown_status_is_rejected`
- **النتيجة:** ⬜

---

## هـ) إدراج أصل في مشروع — `/app/asset/insert-into-project`

### 11. صفحة الإدراج
- **FRD:** §1.7.3 (صفحة ادراج أصل في مشروع)
- **الخطوات:** افتح Assets ← List ودوس "Insert asset into project". اختار الشركة ثم المشروع، ثم النوع (حاوية/صهريج)، ثم الأصل. جرّب كمان تفتحها من زر "Insert asset into project" في صفحة تفاصيل مشروع.
- **المتوقع:** اختيار الشركة بيملّا قائمة المشروعات **النشطة** بس بتاعتها. تغيير النوع بيغيّر قائمة الأصول لأصول **متاحة** بس (نشطة وغير مدرجة في مشروع — مفيش أصل في الصيانة أو معطّل أو مدرج بالفعل). كل القوائم بحث حي. من صفحة المشروع الشركة والمشروع بيتعبّوا لوحدهم. الضغط على Submit من غير اختيار بيطلّع "This field is required" تحت الخانات الفاضية.
- **يغطيه آليًا:** `Projects/InsertAssetIntoProjectTest::test_the_active_filter_means_available_and_in_project_is_its_own_filter`, `test_the_asset_is_required_and_must_exist`
- **النتيجة:** ⬜

### 12. بعد الإدراج: الأصل بقى "في مشروع"
- **FRD:** §1.7.3 (حالة توفر الأصل: "في مشروع + اسم المشروع")
- **الخطوات:** أدرج `Container A-02` في مشروع (مثلًا `Al Noor Residential Complex`). بعدها افتح Assets ← List، ثم تفاصيل الأصل، ثم تفاصيل المشروع.
- **المتوقع:** بيرجعك لصفحة المشروع برسالة نجاح والأصل ظاهر في جدول أصوله. في قائمة الأصول عمود التوفر بيقول **"In a project"** + اسم المشروع، وفلتر الحالة فيه خيار "In a project"، وكارت "In projects" زاد وكارت "Available" نقص. تفاصيل الأصل فيها بادج "In a project" وكارت "Current project" برابط للمشروع. لو بعدها الأصل اتحوّل "في الصيانة" بيظهر بحالة الصيانة (ولسه مرتبط بمشروعه).
- **يغطيه آليًا:** `Projects/InsertAssetIntoProjectTest::test_an_available_asset_is_inserted_into_an_active_project`, `test_the_list_exposes_the_project_and_availability`, `test_the_stat_cards_split_available_from_in_projects`
- **النتيجة:** ⬜

### 13. قواعد الإدراج
- **FRD:** §1.7.3 ("يخرج من أرصدة الأصول المتاحة")
- **الخطوات:** جرّب تدرج: أصل مدرج بالفعل في مشروع، وأصل "في الصيانة" (`Container A-03`)، ومشروع معطّل (`Gulf Petro Warehouse`) — من صفحة الإدراج أو من الـAPI (`POST /api/v1/projects/{id}/assets` بـ`{"asset_id": ...}` من Postman).
- **المتوقع:** الأصول المدرجة/الصيانة/المعطّلة مش بتظهر في قائمة الأصول أصلًا؛ ومن الـAPI بترجع 422 برسالة واضحة على `asset_id`، والمشروع المعطّل 422 على `project`. الأصل بيفضل زي ما هو. مشروع أو أصل من شركة مشغّلة تانية: 404 / 422.
- **يغطيه آليًا:** `Projects/InsertAssetIntoProjectTest::test_an_asset_can_only_be_in_one_project`, `test_an_asset_in_maintenance_or_deactivated_cannot_be_inserted`, `test_nothing_can_be_inserted_into_a_deactivated_project`, `Projects/ProjectTenantIsolationTest::test_an_asset_from_another_tenant_cannot_be_put_in_my_project`
- **النتيجة:** ⬜

### 14. صلاحية الإدراج
- **FRD:** §1.7.3 / دور مدخل البيانات (كل الصلاحيات في إدارة الموارد)
- **الخطوات:** سجّل دخول بـ`dataentry` وأدرج أصل، ثم بـ`auditor` وبص على قائمة الأصول وصفحة تفاصيل مشروع.
- **المتوقع:** `dataentry` يقدر يدرج. `auditor` مايشوفش زر "Insert asset into project" في أي مكان، والـAPI بيرجع 403 (وكذلك `driver`).
- **يغطيه آليًا:** `Projects/InsertAssetIntoProjectTest::test_system_admin_and_data_entry_may_insert`, `test_auditor_and_driver_may_not_insert`
- **النتيجة:** ⬜

---

## و) التصدير

### 15. تصدير قائمة المشروعات بنفس الفلاتر
- **FRD:** §1.12 (زر تصدير Excel – PDF)
- **الخطوات:** في قائمة المشروعات اختار فلتر شركة (أو اكتب في البحث) وبعدها Export ← Excel ثم Pdf.
- **المتوقع:** الملف بيحتوي **نفس الصفوف اللي على الشاشة** بس (مش الجدول كله). الأعمدة: الرقم التعريفي، الشركة، المشروع، المنطقة، التعاقدات، المستخدمين، الحالة، تاريخ الإنشاء. الـPDF بالعربي بيطلع مقروء.
- **يغطيه آليًا:** `ExportFiltersTest::test_project_export_honours_search_and_every_filter`
- **النتيجة:** ⬜

### 16. تصدير الأصول الموجودة في مشروع
- **FRD:** §1.12.3 (قسم الأصول المتواجدة بهذا المشروع — زر تصدير)
- **الخطوات:** في تفاصيل مشروع فيه أصل اختار نوع الأصل من الفلتر وبعدها Export.
- **المتوقع:** الملف فيه أصول المشروع ده بس (بعد فلتر النوع) وعمود "المشروع" فيه اسمه.
- **يغطيه آليًا:** `Projects/InsertAssetIntoProjectTest::test_the_asset_export_can_be_limited_to_one_project`
- **النتيجة:** ⬜

---

## ز) صفحة الشركة

### 17. مشروعات الشركة في صفحة تفاصيلها
- **FRD:** §1.11.3 (قسم المشاريع التابعة للعميل + إحصائية أعداد المشروعات)
- **الخطوات:** افتح تفاصيل `Al Noor Construction` وقائمة الشركات.
- **المتوقع:** إحصائية "Projects" فيها العدد الحقيقي (2 بعد الـseed)، وجدول **Projects** فيه مشروعات الشركة دي بس (بحث + فلتر الحالة + تصدير + زر "Add Project" اللي بيفتح الفورم والشركة مختارة). عمود "Projects" في قائمة الشركات بيعرض العدد نفسه، وتصدير الشركات بيحمله.
- **يغطيه آليًا:** `Projects/ProjectManagementTest::test_the_company_reports_its_real_project_count`, `ExportFiltersTest::test_client_company_export_carries_the_real_project_count`
- **النتيجة:** ⬜

---

## ح) العزل والصلاحيات

### 18. العزل بين الشركات المشغّلة
- **FRD:** عام (بيانات كل شركة مشغّلة معزولة)
- **الدور:** مدير نظام في شركتين مشغّلتين (أو Postman)
- **الخطوات:** مدير نظام في شركة A يحاول يفتح/يعدّل/يعطّل/يدرج أصل في مشروع تابع لشركة B بالـid.
- **المتوقع:** 404 في كل الحالات، والقائمة والتصدير مايظهروش غير مشروعات الشركة المشغّلة الحالية. نفس الرقم التعريفي (`ALN-P0001`) مسموح في شركتين مشغّلتين مختلفتين.
- **يغطيه آليًا:** `Projects/ProjectTenantIsolationTest` (كله)
- **النتيجة:** ⬜

### 19. الصلاحيات لكل دور
- **FRD:** §1.12 + دور مدخل البيانات + دور المراقب
- **الخطوات:** جرّب كل عملية (عرض / تصدير / إنشاء / تعديل / تعطيل) بـ`dataentry` و`auditor` و`driver` (من الواجهة أو Postman).
- **المتوقع:** `dataentry` = كل العمليات. `auditor` = عرض وتصدير بس (403 على الباقي). `driver` = 403 على كل حاجة. من غير تسجيل دخول = 401.
- **يغطيه آليًا:** `Projects/ProjectPolicyTest`
- **النتيجة:** ⬜

---

## ط) اللغة

### 20. العربي
- **FRD:** عام (واجهة ثنائية اللغة)
- **الخطوات:** بدّل اللغة لعربي وافتح: قائمة المشروعات، الإنشاء، التفاصيل، التعديل، وصفحة "إدراج أصل في مشروع".
- **المتوقع:** كل النصوص بالعربي (شاملة رسائل الخطأ والحالات وعناوين الأعمدة) والصفحات بتتعكس (RTL)، وعنوان القائمة "مشروعات العملاء" تحت "العملاء والمشروعات".
- **يغطيه آليًا:** (تحقق بصري — مفيش تست آلي للترجمة)
- **النتيجة:** ⬜
