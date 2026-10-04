# قبول التنفيذ — المرحلة 2: المتعهدون (شركات مقاولي الباطن) + التبعية

> آخر مزامنة: **FRD V01.14** (2026-10-04) · شغّل `php artisan test` الأول (لازم كله أخضر) ثم `php artisan migrate:fresh --seed`.

نفس الحسابات من ملف الأسبوع 1–2. بعد الـseed فيه 3 متعهدين: **Desert Haulage Co.** (`DSH-C0001` — بيدعم "Solid Waste Transport" بس، وتحته حاويتين)، **Gulf Logistics** (`GLG-C0002` — ولا خدمة معلّمة يعني بيدعم الكل، وتحته صهريج)، **Al Safa Transport** (`ASF-C0003` — معطّل). الرقم التعريفي للمتعهد = الاسم المختصر + **C** + رقم تسلسلي.

---

## أ) القائمة والصلاحيات

### 1. القائمة الجانبية
- **FRD:** §1.10 (إدارة شركات المتعهدين)
- **الخطوات:** سجّل دخول بـ`admin` ثم `dataentry` ثم `auditor` ثم `driver` وبص على القائمة الجانبية تحت عنوان **Operations**.
- **المتوقع:** بند **Contractors** وتحته **List** و**Add**. المراقب بيشوف **List** بس (من غير "Add"). السائق مايشوفش البند خالص، و`/api/v1/contractors` يرجّع له 403.
- **يغطيه آليًا:** `MenuVisibilityTest::test_contractors_menu_visibility_per_role`, `Contractors/ContractorManagementTest::test_a_driver_has_no_access`
- **النتيجة:** ⬜

### 2. قائمة المتعهدين + كروت الإحصائيات + البحث والفلتر
- **FRD:** §1.10 (إحصائية أعداد المتعهدين + مركبات/أصول/سائقين المتعهدين، جدول data table، شريط بحث، فلتر الحالة، تصدير)
- **الخطوات:** افتح Operations ← Contractors ← List بـ`admin`. بص على الكروت الأربعة فوق. جرّب البحث بـ`Desert` وبـ`DSH` وبـ`DSH-C0001` وبـ`Desert Trucks`، وفلتر الحالة "Deactivated".
- **المتوقع:** أربع كروت: المتعهدين (الإجمالي + عدد النشطين)، المركبات، الأصول (حاويات: 2 · صهاريج: 1 بعد الـseed)، السائقين — والثلاثة الأخيرة بتعدّ اللي بيوفّره المتعهدون بس (مش موارد GCM). الجدول: الرقم التعريفي / المتعهد (والاسم التجاري تحته) / السائقين / المركبات / الحاويات / الصهاريج / الحالة / عين + قلم. البحث بيشمل الاسم والاسم التجاري والاسم المختصر والرقم التعريفي. الفلتر والترقيم والترتيب شغالين على السيرفر.
- **يغطيه آليًا:** `Contractors/ContractorManagementTest` (`test_the_list_*`, `test_the_stats_*`)
- **النتيجة:** ⬜

### 3. المراقب للعرض والتصدير فقط
- **FRD:** §1.10.1 + أدوار النظام (المراقب: عرض/تصدير فقط)
- **الخطوات:** بـ`auditor` افتح قايمة المتعهدين وتفاصيل أي متعهد.
- **المتوقع:** مفيش زر "Add Contractor" ولا أيقونة تعديل ولا زر Edit. التصدير شغّال. أي إنشاء / تعديل / تعطيل عبر الـAPI يرجّع 403.
- **يغطيه آليًا:** `Contractors/ContractorManagementTest::test_the_auditor_can_view_and_export_but_not_write`
- **النتيجة:** ⬜

---

## ب) إنشاء المتعهد وتعديله

### 4. إنشاء متعهد جديد
- **FRD:** §1.10.2 (انشاء شركة متعهد جديدة)
- **الخطوات:** بـ`dataentry` افتح Contractors ← Add. جرّب "Submit" والفورم فاضي. بعدين اكتب اسم "Test Hauler" واسم مختصر `tst` واحفظ.
- **المتوقع:** عنوان الصفحة "إنشاء شركة متعهد جديدة" والفورم كروت: البيانات الأساسية (الاسم*، الاسم المختصر*، الاسم التجاري، الشعار، الهاتف، العنوان، رابط الخريطة، الحالة التشغيلية* وافتراضيها "نشط") ثم **الخدمات المعتمدة** ثم العقد ثم السجل التجاري والضريبي ثم البيانات الإضافية. الفورم الفاضي بيطلّع "This field is required" تحت الاسم والاسم المختصر. بعد الحفظ بترجع للقايمة برسالة نجاح، والمتعهد الجديد رقمه `TST-C0004` (الحروف بتتحوّل لكبيرة تلقائي). مفيش حقل "ممثل المتعهد" لسه (جاي مع حسابات مستخدمي المتعهد).
- **يغطيه آليًا:** `Contractors/ContractorManagementTest` (`test_system_admin_creates_*`, `test_name_prefix_and_status_are_required`, `test_the_prefix_is_normalised_*`)
- **النتيجة:** ⬜

### 5. الاسم المختصر: 3 حروف مميزة + الرقم التعريفي بحرف C + القفل بعد الإنشاء
- **FRD:** §1.10.2 / §1.10.4 (الاسم المختصر — 3 حروف إنجليزية مميزة وغير متكررة)
- **الخطوات:** حاول تنشئ متعهد باسم مختصر `AB` ثم `A1B` ثم `DSH` (مستخدم). بعدين افتح تعديل أي متعهد.
- **المتوقع:** الحالات الثلاثة بتترفض برسالة واضحة. الرقم التعريفي بالشكل `ABC-C0001` — فمستحيل يتطابق مع رقم شركة عميل (`ABC-0001`) حتى لو الحروف نفسها (الاسم المختصر لازم يبقى مميز بين المتعهدين بس). في صفحة التعديل حقل الاسم المختصر معطّل، وأي قيمة تتبعت له بالـAPI بتتجاهل.
- **يغطيه آليًا:** `Contractors/ContractorManagementTest` (`test_a_prefix_that_is_not_three_english_letters_is_rejected`, `test_the_prefix_must_be_unique_*`, `test_the_contractor_id_is_the_prefix_a_c_*`, `test_a_contractor_and_a_client_company_can_share_the_same_letters`, `test_the_prefix_is_locked_after_creation`)
- **النتيجة:** ⬜

### 6. الخدمات المعتمدة (ولا واحدة = الكل)
- **FRD:** §1.10.2 (الخدمات المعتمدة — وفي حال عدم التحديد يعتبر الكل)
- **الخطوات:** في فورم الإنشاء بص على كارت "الخدمات المعتمدة". علّم خدمة واحدة واحفظ، وافتح التفاصيل. بعدين افتح التعديل وشيل التعليم من كل الخدمات واحفظ.
- **المتوقع:** الكارت فيه checkbox لكل خدمة أساسية نشطة. بعد تعليم خدمة بتظهر في التفاصيل تحت "الخدمات المعتمدة". بعد شيل كل التعليمات بتظهر "كل الخدمات". خدمة من شركة تانية مستحيل تتحدد.
- **يغطيه آليًا:** `Contractors/ContractorManagementTest` (`test_certified_services_*`, `test_a_service_of_another_tenant_cannot_be_certified`)
- **النتيجة:** ⬜

### 7. بيانات العقد والمرفقات
- **FRD:** §1.10.2 (رقم العقد، تاريخ البداية والنهاية، صورة العقد، السجل التجاري والضريبي)
- **الخطوات:** ارفع صورة عقد PDF وسجل تجاري وسجل ضريبي، واكتب أرقام العقد والسجلات (جرّب حروف في خانة رقم)، وتاريخ نهاية قبل تاريخ البداية، وملف أكبر من 4MB، ورابط خريطة `javascript:alert(1)`.
- **المتوقع:** الأرقام أرقام بس، وتاريخ النهاية مايبقاش قبل البداية، والملف PDF/JPG/PNG حتى 4MB، ورابط الخريطة http/https بس — كلهم بيترفضوا برسالة تحت الحقل. المرفقات خاصة: بتتحمّل من زر "Download" في التفاصيل (والمراقب يقدر)، لكن السائق بيتلقى 403.
- **يغطيه آليًا:** `Contractors/ContractorManagementTest` (`test_the_registration_numbers_*`, `test_the_contract_end_date_*`, `test_the_location_link_must_be_http_or_https`, `test_files_are_stored_and_the_attachments_stay_private`, `test_an_oversized_or_wrong_type_attachment_is_rejected`, `test_the_documents_download_only_for_users_who_may_view_contractors`)
- **النتيجة:** ⬜

### 8. صفحة تفاصيل المتعهد
- **FRD:** §1.10.3 (عنوان الصفحة: تفاصيل شركة متعهد)
- **الخطوات:** افتح تفاصيل `Desert Haulage Co.` ثم `Gulf Logistics`.
- **المتوقع:** "آخر تحديث: تم بواسطة … — التاريخ والوقت" في الأعلى. اللوجو (أو أيقونة بديلة)، الاسم، الاسم التجاري، الحالة، الرقم التعريفي، الاسم المختصر، الهاتف، العنوان، الموقع، **الخدمات المعتمدة** (Desert: "Solid Waste Transport"، Gulf: "All services")، بيانات العقد وروابط تحميل المرفقات. على اليمين أربع كروت: السائقين، المركبات، الحاويات (2 لـDesert)، الصهاريج. زر Edit (للأدمن ومدخل البيانات بس) وزر Back to list.
- **يغطيه آليًا:** `Contractors/ContractorManagementTest::test_show_returns_the_contractor_with_its_resource_counts`
- **النتيجة:** ⬜

### 9. تعديل المتعهد
- **FRD:** §1.10.4 (تعديل تفاصيل متعهد — مدير النظام / مدخل البيانات)
- **الخطوات:** افتح تعديل متعهد. غيّر الاسم والهاتف وبدّل الخدمات المعتمدة، وارفع عقد جديد، واحفظ.
- **المتوقع:** الفورم متعبّى بالبيانات الحالية، والملفات الحالية ليها رابط "Current file"، وترك خانة الملف فاضية بيحافظ على الملف القديم، ورفع جديد بيبدّله (القديم بيتمسح من التخزين). بعد الحفظ بترجع للتفاصيل برسالة نجاح و"آخر تحديث" بيتغيّر لاسمك. الرقم التعريفي والحالة ماينفعش يتغيّروا من هنا.
- **يغطيه آليًا:** `Contractors/ContractorManagementTest` (`test_every_field_except_the_prefix_is_editable`, `test_editing_cannot_change_the_status_or_the_code`, `test_an_uploaded_replacement_removes_the_old_file`)
- **النتيجة:** ⬜

### 10. التعطيل والتنشيط (من غير تأثير متسلسل)
- **FRD:** §1.10.1 / §1.10.4 (التعديل أو التعطيل مسؤولية مدير النظام / مدخل البيانات)
- **الخطوات:** من كارت "حالة المتعهد" في صفحة تعديل `Desert Haulage Co.` اختار "Deactivated"، علّم "I confirm"، واضغط Update Status. بعدين افتح قايمة المركبات/الأصول وبص على حاويات Desert، وافتح فورم إضافة سائق واختار "تابع لمتعهد".
- **المتوقع:** الزر مقفول لحد ما تعلّم التأكيد. بعد التعطيل المتعهد بيظهر "Deactivated" في القايمة، **لكن حاوياته وسائقيه ومركباته حالتها زي ما هي** (مفيش تأثير متسلسل). المتعهد المعطّل مابيظهرش في قائمة اختيار المتعهد في الفورمات الجديدة. التنشيط يرجّعه للقائمة. مفيش حذف نهائي.
- **يغطيه آليًا:** `Contractors/ContractorManagementTest` (`test_the_status_is_changed_through_its_own_endpoint_*`, `test_deactivating_a_contractor_does_not_touch_its_resources`), `Contractors/ContractorAffiliationTest::test_an_unknown_other_tenant_or_deactivated_contractor_is_refused_on_create`
- **النتيجة:** ⬜

---

## ج) التبعية: السائق / المركبة / الأصل

### 11. تبعية المركبة
- **FRD:** §1.5.3 (التبعية: تابع لشركة GCM / تابع لمتعهد — وفي حال اختيار متعهد تظهر قائمة بأسماء المتعهدين)
- **الخطوات:** افتح Vehicles ← Add. في "Affiliation" اختار "Belongs to a Contractor". اكتب في قايمة المتعهدين "Gul" واختار. كمّل بيانات المركبة واحفظ. بعدين افتح تعديلها.
- **المتوقع:** اختيار "متعهد" بيظهر حقل "Contractor*" (Select2 ببحث حي) فيه المتعهدين **النشطين بس** (Desert و Gulf — مش Al Safa المعطّل). اختيار "GCM" بيخفي الحقل. من غير اختيار متعهد الحفظ بيترفض. في القايمة والتفاصيل عمود/سطر التبعية بيعرض **اسم المتعهد** (مش كلمة "Contractor"). صفحة التعديل بتفتح والتبعية والمتعهد متعلّمين صح — ويمكن تغييرهم (FRD: كل البيانات قابلة للتعديل). المتعهد اللي اتعطّل بعد كده يفضل ظاهر على مركباته القديمة من غير ما يكسر التعديل.
- **يغطيه آليًا:** `Contractors/ContractorAffiliationTest` (`test_a_vehicle_can_be_created_for_a_contractor`, `test_choosing_contractor_requires_picking_one`, `test_a_gcm_vehicle_never_keeps_a_contractor_id`, `test_a_vehicle_can_move_between_gcm_and_a_contractor_on_edit`, `test_a_vehicle_whose_contractor_was_deactivated_can_still_be_edited`)
- **النتيجة:** ⬜

### 12. تبعية السائق
- **FRD:** §1.3 (إنشاء حساب سائق — التبعية الوظيفية)
- **الخطوات:** افتح Drivers ← Add. اختار "Belongs to a Contractor" واختار متعهد، واحفظ. افتح Users وDrivers وفلتر بـ"Contractor". افتح تعديل السائق وارجعه لـGCM.
- **المتوقع:** السائق بيتحفظ تابع للمتعهد، ويظهر اسم المتعهد في عمود التبعية في Drivers وفي "اسم الجهة" في Users، ويتحسب في عداد "السائقين" في صفحة المتعهد. بعد تعديله لـGCM بيرجع عادي وعدّاد المتعهد بينقص.
- **يغطيه آليًا:** `Contractors/ContractorAffiliationTest` (`test_a_driver_can_be_created_for_a_contractor`, `test_a_contractor_driver_requires_a_contractor`, `test_a_driver_can_move_to_another_affiliation_on_edit`, `test_the_driver_list_shows_the_contractor_and_the_users_list_names_the_entity`)
- **النتيجة:** ⬜

### 13. تبعية الأصل (مقفولة بعد الإنشاء)
- **FRD:** §1.7.3 / §1.7.4 (الأصل: التبعية — وتعديل الأصل مقصور على الاسم)
- **الخطوات:** افتح Assets ← Add، اختار "Belongs to a Contractor" واختار متعهد وكمّل واحفظ. افتح تعديل أي أصل تابع لمتعهد (مثلًا `Container D-01`).
- **المتوقع:** الأصل بيتحفظ بتبعيته. في التعديل الاسم بس قابل للتغيير، والتبعية والمتعهد ظاهرين ومقفولين. ظهوره في جدول Assets وفي تفاصيل متعهده (عدّاد الحاويات/الصهاريج).
- **يغطيه آليًا:** `Contractors/ContractorAffiliationTest` (`test_an_asset_can_be_created_for_a_contractor_and_the_choice_is_locked`, `test_an_asset_for_a_contractor_requires_picking_one`, `test_the_asset_list_filters_by_contractor`)
- **النتيجة:** ⬜

---

## د) عام

### 14. التصدير يطابق اللي على الشاشة
- **FRD:** §1.10 (زر تصدير Excel – PDF)
- **الخطوات:** في قايمة المتعهدين فلتر "Deactivated" واكتب بحث، وصدّر Excel ثم PDF. بدّل اللغة للعربي وصدّر PDF.
- **المتوقع:** الملف فيه نفس الصفوف المعروضة بس. الـPDF العربي بيطلع حروفه متصلة وأسماء المتعهدين العربية سليمة. تصدير المركبات/الأصول/السائقين بيعرض اسم المتعهد في عمود التبعية.
- **يغطيه آليًا:** `ExportFiltersTest::test_contractor_export_honours_search_and_filter`, `ExportFiltersTest::test_contractor_export_pdf_works`
- **النتيجة:** ⬜

### 15. العزل بين الشركات
- **FRD:** عام (Multi-tenant)
- **الخطوات:** (فني) متعهد في شركة تانية.
- **المتوقع:** مدير نظام شركة مايشوفش ولا يعدّل متعهدين شركة تانية (404)، ومايقدرش يختارهم في فورم سائق/مركبة/أصل، والاسم المختصر ممكن يتكرر بين شركتين مختلفتين.
- **يغطيه آليًا:** `Contractors/ContractorManagementTest` (`test_a_contractor_of_another_tenant_is_invisible`, `test_the_same_prefix_is_allowed_in_another_tenant`), `ClientAccounts/ClientUserAccessTest::test_a_client_account_cannot_reach_any_admin_module`
- **النتيجة:** ⬜
