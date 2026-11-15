# قبول التنفيذ — المرحلة 3 (أسبوع 6): التعاقدات Purchase Orders

> آخر مزامنة: **FRD V01.14** (2026-11-11) · شغّل `php artisan test` الأول (لازم كله أخضر) ثم `php artisan migrate:fresh --seed`. الصفحات محتاجة `npm run build` لو الـbuild قديم.

الحسابات: `admin@gcm.test` · `dataentry@gcm.test` · `auditor@gcm.test` · `driver@gcm.test` · `client.manager@gcm.test` (مدير مشروعات عميل، كل مشروعات Al Noor) · `client.auditor@gcm.test` (مراقب مشروعات عميل) · `contractor.desert@gcm.test` (مستخدم متعهد) — كلمة السر `password`.

> **الصلاحيات (FRD §1.13):** الإنشاء والتعديل والتعطيل لمدير النظام **فقط**. العرض والتصدير لمدير النظام والمراقب. مدير مشروعات العميل بيشوف تعاقدات مشروعاته بس بكل المبالغ، من غير تصدير. **مدخل البيانات ومراقب مشروعات العميل ومستخدم المتعهد والسائق ملهمش أي وصول للتعاقدات — حتى العرض** (نص V01.14 حرفيًا في قسم دور مدخل البيانات: "ليس له أي صلاحيات تخص التعاقدات بما فيها صلاحية العرض").
>
> **مؤجل لحد موديول الرحلات:** عدد الرحلات الحقيقي المربوطة بالتعاقد، وإحصائية الكميات المنقولة، وخصم الحصة من الرحلات نفسها — كلهم بيظهروا `0` لحد ما الرحلات تتبني (الحصص نفسها والحالات المحسوبة شغالة وبتتجرّب هنا بدفتر تجريبي من التستات).

---

## أ) القائمة والصلاحيات — `/app/po/list`

### 1. القائمة الجانبية والصلاحيات لكل دور
- **FRD:** §1.13 (التعريف وصلاحية الإنشاء والتعطيل)
- **الخطوات:** سجّل دخول بـ`admin` ثم `auditor` ثم `client.manager` ثم `dataentry` ثم `client.auditor` ثم `contractor.desert` ثم `driver` وبص على القائمة الجانبية.
- **المتوقع:** بند **Purchase Orders** (تحت عنوان **Clients & Projects**) بيظهر لـ`admin` (List + Add)، و`auditor` و`client.manager` (List بس). الباقيين مايشوفوش البند، وفتح `/app/po/list` أو `/api/v1/purchase-orders` يدويًا بيرجّع 403.
- **يغطيه آليًا:** `MenuVisibilityTest::test_purchase_orders_menu_visibility_per_role`، `PurchaseOrders/Pages/PurchaseOrderPagesTest` (كل دور)، `PurchaseOrders/Api/PurchaseOrderPolicyApiTest`
- **النتيجة:** ⬜

### 2. صفحة القائمة: الكروت والجدول
- **FRD:** §1.13 (إحصائيات + جدول التعاقدات)
- **الخطوات:** افتح Purchase Orders ← List بـ`admin` (بعد ما تنشئ تعاقد).
- **المتوقع:** 5 كروت: عدد التعاقدات، الشركات، المشروعات، الرحلات، إجمالي بنود التكاليف (بالريال) — **التعاقدات النشطة بس**. وجدول بالأعمدة: الرقم التعريفي، الشركة، المشروع، المدى الزمني، الأيام المتبقية، الحالة العامة للبنود، حالة الحساب، التفاصيل.
- **يغطيه آليًا:** `Api/PurchaseOrderApiTest::test_the_stats_count_active_pos_only`, `test_the_list_is_paginated_and_carries_each_pos_state`, `Pages/PurchaseOrderPagesTest::test_the_list_shell_gives_the_admin_the_write_and_export_flags`
- **النتيجة:** ⬜

### 3. الفلاتر والبحث والترتيب
- **FRD:** §1.13 (شركة العميل – المشروع – المدى الزمني – الحالة العامة للبنود – حالة حساب الـPO)
- **الخطوات:** جرّب البحث برقم التعاقد وباسم الشركة وباسم المشروع، وفلتر الشركة (وبعدها المشروع بيتحدّث لمشروعات الشركة دي)، وفلتر المدى الزمني (من/إلى)، وفلتر "الحالة العامة"، وفلتر "حالة الحساب". رتّب بالأيام المتبقية وبالحالة العامة.
- **المتوقع:** كل فلتر بيضيّق الجدول والعدّاد، والفلاتر بتتجمّع. المدى الزمني بيجيب أي تعاقد تواريخه بتتقاطع مع المدى. الترتيب بالحالة: سارية ← على وشك النفاذ ← نفذت.
- **يغطيه آليًا:** `Api/PurchaseOrderApiTest::test_the_list_filters_by_company_project_account_status_and_search`, `test_the_time_range_filter_matches_pos_whose_dates_overlap_it`, `test_the_items_status_filter_is_resolved_from_the_ledger`, `test_the_list_sorts_and_pages_by_the_items_status_in_the_database`, `PurchaseOrderStatusSqlTest`
- **النتيجة:** ⬜

### 4. التصدير بنفس اللي على الشاشة
- **FRD:** §1.13 (زر تصدير Excel / PDF)
- **الخطوات:** طبّق فلتر وبحث، وصدّر Excel ثم PDF بـ`admin` (وبـ`auditor`).
- **المتوقع:** الملف فيه نفس الصفوف اللي على الشاشة بالظبط، وبهيدر اللوجو و"صدّر بواسطة" والتاريخ. زر التصدير **مش ظاهر** لـ`client.manager` ورابط التصدير اليدوي بيرجّع 403.
- **يغطيه آليًا:** `Api/PurchaseOrderExportTest`, `ExportExcelHeaderTest`
- **النتيجة:** ⬜

---

## ب) الإنشاء — `/app/po/add`

### 5. فورم الإنشاء
- **FRD:** §1.13 (إنشاء تعاقد جديد)
- **الخطوات:** دوس "Add Purchase Order" بـ`admin`. اختار الشركة `Al Noor Construction` ثم مشروع منها.
- **المتوقع:** الخانات في كروت: البيانات الأساسية (رقم التعاقد قدامه الاسم المختصر `ALN-`، الشركة، المشروع، المنطقة التشغيلية، المدى الزمني، الحالة، المرفق) وخدمات التعاقد والبيانات الإضافية. المنطقة التشغيلية بتتملى من منطقة المشروع وتتغيّر. الحقول الإلزامية عليها `*`.
- **يغطيه آليًا:** `Api/PurchaseOrderApiTest::test_creating_a_po_builds_the_number_from_the_company_prefix_and_returns_its_state`, `Pages/PurchaseOrderPagesTest::test_the_system_admin_opens_every_page`
- **النتيجة:** ⬜

### 6. إضافة خدمة بطريقة "تكلفة الرحلات فقط"
- **FRD:** §1.13 (اضافة خدمات التعاقد تباعا — "الرحلات فقط")
- **الخطوات:** رقم التعاقد `1200`، مدى من أول الشهر الحالي لـ5 شهور. دوس "إضافة خدمة": اختار `Solid Waste Transport`، طريقة الاحتساب "تكلفة الرحلات فقط"، الحد الأقصى للرحلات `150`، تكلفة الرحلة `50`، النسبة `20`. Submit.
- **المتوقع:** "إجمالي الحد الأقصى لتكلفة الرحلات" بيتحسب لحظيًا `7500.00`. بترجع للقائمة برسالة نجاح، والتعاقد رقمه **`ALN-1200`**، حالته "سارية" والأيام المتبقية ظاهرة.
- **يغطيه آليًا:** `Api/PurchaseOrderApiTest::test_creating_a_po_builds_the_number_from_the_company_prefix_and_returns_its_state`, `PurchaseOrderActionsTest`
- **النتيجة:** ⬜

### 7. طريقة "الرحلات والخدمات المساعدة"
- **FRD:** §1.13 (الحصة للرحلات + تكلفة الخدمات المساعدة)
- **الخطوات:** أنشئ تعاقد تاني (رقم `1201`) لمشروع تاني من نفس الشركة بطريقة "تكلفة الرحلات وتكلفة الخدمات المساعدة": رحلات `150`، تكلفة الرحلة `50`، تكلفة الخدمات المساعدة `30`.
- **المتوقع:** خانة "تكلفة الخدمات المساعدة للرحلة" بتظهر بس مع الطريقة دي (وبتبقى إلزامية)، والإجماليين بيتحسبوا `7500.00` و`4500.00`، وكارت "إجمالي بنود التكاليف" بيجمعهم `12,000.00`.
- **يغطيه آليًا:** `Api/PurchaseOrderApiTest::test_a_trips_and_ancillary_line_returns_both_totals`, `test_the_validation_errors_name_the_fields`
- **النتيجة:** ⬜

### 8. أخطاء الفورم
- **FRD:** §1.13
- **الخطوات:** جرّب رقم تعاقد فيه حروف، وتاريخ نهاية قبل البداية، وإضافة نفس الخدمة مرتين، وفورم من غير أي خدمة، وإعادة استخدام رقم `1200`.
- **المتوقع:** كل خطأ بيظهر تحت الحقل أو البند اللي بيخصه بجملة مفهومة (مش رسالة عامة). الرقم المستخدم بيترفض **حتى لو التعاقد القديم معطّل**.
- **يغطيه آليًا:** `Api/PurchaseOrderApiTest::test_a_taken_number_and_an_overlapping_service_are_refused_by_the_actions`, `PurchaseOrderActionsTest`
- **النتيجة:** ⬜

### 9. منع تداخل نفس الخدمة في نفس المشروع
- **FRD:** §1.13 (قاعدة التعاقدات — مثال فندق الرياض)
- **الخطوات:** حاول تنشئ تعاقد جديد لنفس المشروع ونفس الخدمة (`Solid Waste Transport`) في مدى فيه يوم واحد مشترك مع `ALN-1200`.
- **المتوقع:** بيترفض برسالة تذكر التعاقد القائم ومداه. ولو نفدت الحصة في التعاقد القائم (أي مكوّن: الرحلات أو التكلفة أو التاريخ) بيتقبل.
- **يغطيه آليًا:** `NoOverlappingMainServiceTest`, `Api/PurchaseOrderApiTest::test_reactivating_is_refused_when_another_po_now_holds_the_service`
- **النتيجة:** ⬜

### 10. المرفق
- **FRD:** §1.13 (مرفقات)
- **الخطوات:** أرفق PDF (أقل من 5 ميجا) عند الإنشاء، وجرّب ملف `.exe` وملف أكبر من 5 ميجا.
- **المتوقع:** PDF/صورة بتتقبل وبتظهر في التفاصيل كرابط تنزيل. غيرهم بيترفض. الملف على القرص الخاص ومابيتنزلش غير بعد تسجيل الدخول وبصلاحية العرض.
- **يغطيه آليًا:** `Api/PurchaseOrderApiTest::test_the_attachment_is_stored_privately_and_served_through_the_gate_checked_route`, `test_the_attachment_must_be_a_pdf_or_an_image_up_to_5_mb`
- **النتيجة:** ⬜

---

## ج) التفاصيل — `/app/po/view/{id}`

### 11. صفحة التفاصيل وحالات البنود
- **FRD:** §1.13 (تفاصيل تعاقد + تفاصيل بنود التعاقد وحالة كل بند)
- **الخطوات:** افتح تفاصيل `ALN-1201`.
- **المتوقع:** كارت شمال (الرقم، الحالتين، الشركة، المشروع، المنطقة، المدى، الأيام المتبقية، المرفق، Edit وBack) وعلى اليمين كروت: الرحلات والتكلفة المستنفذة والكمية، وكارت لكل خدمة فيها الحد الأقصى والتكاليف المحسوبة ونسبة التنبيه وأشرطة المتبقي من (الأيام، الرحلات، تكلفة الرحلات، تكلفة الخدمات المساعدة) بلون كل حالة، و"آخر تحديث بواسطة".
- **يغطيه آليًا:** `Api/PurchaseOrderApiTest::test_show_returns_the_state_the_usage_and_the_edit_lock`, `PurchaseOrderStatusTest`
- **النتيجة:** ⬜

### 12. الحالات المحسوبة (سارية / على وشك النفاذ / نفذت / لم يبدأ / منتهي)
- **FRD:** §1.13 + الداشبورد (على وشك النفاذ = بند متبقي فيه أقل من حد التنبيه)
- **الخطوات:** أنشئ تعاقد مداه في المستقبل، وتعاقد مداه انتهى، وراجعهم في القائمة والتفاصيل.
- **المتوقع:** "لم يبدأ بعد" بيظهر "يبدأ بعد X يوم"، و"منتهي" بيظهر "انتهى منذ X يوم" وحالته العامة "نفذت بالكامل". "على وشك النفاذ" لما أي مكوّن يبقى متبقيه أقل من نسبة البند (أقل بالظبط، مش يساوي). حالة التعاقد "نفذ بالكامل" لما **كل** بنوده تنفذ، وبند واحد نافذ = "على وشك النفاذ".
- **يغطيه آليًا:** `PurchaseOrderStatusTest`, `PurchaseOrderStatusSqlTest` (نفس القواعد في SQL وPHP على كل الحالات الحدّية)
- **النتيجة:** ⬜

---

## د) التعديل والتعطيل — `/app/po/edit/{id}`

### 13. تعديل تعاقد ما عليهوش رحلات
- **FRD:** §1.13 (تعديل تعاقد — مدير النظام فقط)
- **الخطوات:** عدّل تعاقد جديد: غيّر الرقم والتواريخ والمنطقة وعدّل بنود الخدمة (الحد الأقصى، التكلفة، أضف خدمة، احذف خدمة).
- **المتوقع:** كل حاجة بتتعدّل ويتسجّل "آخر تحديث بواسطة". الرقم بيتعاد بناؤه على الاسم المختصر للشركة، والمشروع لازم من نفس الشركة، وقاعدة التداخل بتتفحص تاني.
- **يغطيه آليًا:** `Api/PurchaseOrderApiTest::test_without_trips_everything_can_be_edited`, `PurchaseOrderEditabilityTest`
- **النتيجة:** ⬜

### 14. قفل التعديل لما تتسجل رحلة
- **FRD:** §1.13 (تعديل تعاقد — مدير النظام فقط)
- **الخطوات:** على تعاقد اتسجّلت عليه رحلة غير ملغاة، افتح تعديله. (موديول الرحلات لسه مش جاهز، فالحالة دي بتتجرّب بالتستات الآلية.)
- **المتوقع:** تنبيه إن رحلة اتسجلت، وكل الحقول مقفولة (الرقم، الشركة، المشروع، التواريخ، المنطقة، الملاحظات، المرفق، الخدمات نفسها وطريقة الاحتساب، وإضافة/حذف بند) **ماعدا حصة كل بند**: الحد الأقصى للرحلات (مايقلش عن المستهلك)، تكلفة الرحلة، تكلفة الخدمات المساعدة، نسبة التنبيه. وتغيير تكلفة الرحلة بيسري على الرحلات اللاحقة بس.
- **يغطيه آليًا:** `Api/PurchaseOrderApiTest::test_with_a_trip_only_the_quota_can_change`, `test_the_max_trips_cannot_go_below_what_was_consumed`, `test_a_trip_blocks_adding_a_line_and_replacing_the_attachment`, `test_a_cancelled_trip_lifts_the_lock`
- **النتيجة:** ⬜

### 15. التعطيل وإعادة التنشيط
- **FRD:** §1.13 (التعديل أو التعطيل مسؤولية مدير النظام فقط)
- **الخطوات:** من كارت "Purchase Order Status" في التعديل: عطّل تعاقد، ثم نشّطه. وجرّب تنشّط تعاقد معطّل تعاقد تاني نشط اتعمل بعده على نفس الخدمة والمدى.
- **المتوقع:** التعطيل والتنشيط مسموحين مع أو من غير رحلات (بعد تأكيد بالـcheckbox). التعاقد المعطّل مابيتحسبش في إحصائيات الكروت ولا بيمنع تعاقد جديد. إعادة التنشيط بتتفحص ضد التداخل وبترفض برسالة على الحالة.
- **يغطيه آليًا:** `Api/PurchaseOrderApiTest::test_deactivating_and_reactivating_work_with_or_without_trips`, `test_reactivating_is_refused_when_another_po_now_holds_the_service`
- **النتيجة:** ⬜

---

## هـ) الربط مع الشركة والمشروع

### 16. قسم "التعاقدات" في صفحة الشركة
- **FRD:** §1.11.3 (قسم التعاقدات التابعة للعميل في مختلف مشاريعه)
- **الخطوات:** افتح Client Companies ← تفاصيل `Al Noor Construction` بـ`admin`.
- **المتوقع:** كارت "Contracts" في الإحصائيات بالعدد **الحقيقي** (كل تعاقدات الشركة، نشطة أو معطّلة)، وجدول "Contracts" تحت جدول المشروعات فيه تعاقدات الشركة كلها بعمود المشروع، بحث وفلتر الحالة العامة وتصدير و"Add Purchase Order" (بتفتح الفورم والشركة مختارة). وعمود "Contracts" في جدول مشروعات الشركة بالعدد الحقيقي.
- **يغطيه آليًا:** `PurchaseOrders/Linking/PurchaseOrderLinkingTest::test_a_company_reports_the_contracts_of_all_its_projects_and_none_of_another_companys`, `test_the_company_page_has_the_contracts_table_for_the_admin`, `test_the_po_list_narrows_to_a_company_and_to_a_project_for_the_sections`
- **النتيجة:** ⬜

### 17. قسم "التعاقدات" في صفحة المشروع وعمود القائمة والتصدير
- **FRD:** §1.12 (جدول المشروعات: التعاقدات) و§1.12.3 (قسم التعاقدات التابعة لهذا المشروع)
- **الخطوات:** افتح تفاصيل مشروع فيه تعاقدات بـ`admin`، وبعدها Client Projects ← List وصدّر Excel/PDF.
- **المتوقع:** الكارت وعمود القائمة بالعدد الحقيقي، وجدول تعاقدات المشروع (من غير عمود المشروع). عمود "Contracts" في ملف التصدير بالعدد الحقيقي.
- **يغطيه آليًا:** `PurchaseOrders/Linking/PurchaseOrderLinkingTest::test_a_project_reports_its_real_contract_count_with_every_status`, `test_the_project_export_carries_the_real_count_for_a_role_that_may_see_it`
- **النتيجة:** ⬜

### 18. مدخل البيانات مايشوفش التعاقدات في أي مكان
- **FRD:** نص V01.14 في قسم دور مدخل البيانات ("ليس له أي صلاحيات تخص التعاقدات بما فيها صلاحية العرض")
- **الخطوات:** سجّل دخول بـ`dataentry` وافتح صفحة الشركة وصفحة المشروع وقائمة المشروعات، وصدّر المشروعات.
- **المتوقع:** مفيش قسم "Contracts" ولا كارته، وعمود "Contracts" بيظهر `—` (وفاضي في ملف التصدير)، وأي وصول مباشر للتعاقدات (`/app/po/list`، `/api/v1/purchase-orders`) 403.
- **يغطيه آليًا:** `PurchaseOrders/Linking/PurchaseOrderLinkingTest::test_data_entry_gets_no_contract_figure_anywhere`, `test_data_entry_gets_no_contracts_section_on_either_page`, `test_the_project_export_leaves_the_column_blank_for_data_entry`
- **النتيجة:** ⬜

---

## و) الصلاحيات للأدوار الأخرى وعزل الشركات

### 19. المراقب: عرض وتصدير بس
- **FRD:** §1.13 (العرض مدير النظام / مراقب النظام فقط)
- **الخطوات:** سجّل دخول بـ`auditor`: افتح القائمة والتفاصيل وصدّر، وحاول تفتح `/app/po/add` أو `/app/po/edit/{id}`.
- **المتوقع:** القائمة والتفاصيل والتصدير شغالين، من غير زرّي Add وEdit، والرابطين اليدويين 403، وأي تعديل أو تعطيل بالـAPI 403.
- **يغطيه آليًا:** `Api/PurchaseOrderPolicyApiTest::test_the_auditor_reads_and_exports_but_cannot_change`, `Pages/PurchaseOrderPagesTest::test_the_auditor_reads_and_exports_but_cannot_open_add_or_edit`
- **النتيجة:** ⬜

### 20. مدير مشروعات العميل: تعاقدات مشروعاته بكل المبالغ
- **FRD:** §1.4 (مدير المشروعات: متابعة حالة التعاقدات الخاصة بمشروعاته)
- **الخطوات:** سجّل دخول بـ`client.manager` وافتح Purchase Orders ← List ثم تفاصيل تعاقد. جرّب تفتح تعاقد مشروع مش ليك (لو حسابك على مشروعات محددة).
- **المتوقع:** بيشوف تعاقدات مشروعاته بس بكل المبالغ، من غير تصدير ولا إضافة ولا تعديل، وفلاتر الشركة والمشروع مخفية، والكروت على مشروعاته بس. تعاقد مشروع مش بتاعه 403. `client.auditor` ملوش أي وصول للتعاقدات.
- **يغطيه آليًا:** `Api/PurchaseOrderPolicyApiTest::test_a_manager_of_chosen_projects_sees_only_their_pos`, `test_the_manager_stats_cover_only_their_projects`, `test_a_manager_cannot_export_or_change_anything`, `test_a_client_project_auditor_has_no_access`
- **النتيجة:** ⬜

### 21. عزل الشركات (Tenants)
- **FRD:** §1.1
- **الخطوات:** (آليًا) شركة تانية بتعمل نفس الاسم المختصر والرقم.
- **المتوقع:** تعاقدات شركة مابتظهرش للتانية بأي شكل (قائمة، تفاصيل، مرفق، إحصاءات، أرقام، اختيار شركة/مشروع/خدمة)، والرقم `ALN-1200` ممكن يتكرر بين شركتين.
- **يغطيه آليًا:** `PurchaseOrderTenantIsolationTest`, `Api/PurchaseOrderPolicyApiTest::test_another_tenants_admin_sees_none_of_these_pos`, `test_a_po_cannot_be_created_with_another_tenants_company_project_or_service`, `Linking/PurchaseOrderLinkingTest::test_another_tenants_counts_never_include_these_contracts`
- **النتيجة:** ⬜

### 22. قفل الخدمة الأساسية لما تتربط بتعاقد
- **FRD:** §1.9.1 (التعديل أو التعطيل مسموح لو الخدمة ماستُخدمتش في تعاقد)
- **الخطوات:** بعد ما تتربط `Solid Waste Transport` بتعاقد نشط، حاول تعدّل اسمها أو تعطّلها من Services.
- **المتوقع:** بيترفض لإن الخدمة في تعاقد. تعطيل التعاقد بيفك القفل.
- **يغطيه آليًا:** `Services/MainServiceManagementTest`, `PurchaseOrderActionsTest`
- **النتيجة:** ⬜
