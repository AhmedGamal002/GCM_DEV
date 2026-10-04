# قبول التنفيذ — المرحلة 2: حسابات العملاء (مدير مشروعات / مراقب مشروعات)

> آخر مزامنة: **FRD V01.14** (2026-09-30) · شغّل `php artisan test` الأول (لازم كله أخضر) ثم `php artisan migrate:fresh --seed`.

الحسابات: `admin@gcm.test` · `dataentry@gcm.test` · `auditor@gcm.test` · `driver@gcm.test` (كلمة السر `password`). وبعد الـseed فيه حسابين عميل تجريبيين لشركة `Al Noor Construction` (نفس كلمة السر `password`):
- `client.manager@gcm.test` — **مدير مشروعات** بصلاحية على **جميع المشروعات**، وهو **ممثل الشركة**.
- `client.auditor@gcm.test` — **مراقب مشروعات** على مشروع **Al Noor Tower** بس، وهو **ممثل المشروع**.

> **مؤجل عمدًا:** حسابات **مستخدمي المتعهد** (محتاجة موديول المتعهدين)، وصفحات دور العميل نفسها (الرحلات / التعاقدات / التقارير) — لسه مش متبنية، فحساب العميل بيسجّل دخول ويشوف الملف الشخصي بس وقائمة جانبية فاضية. قاعدة "العميل يشوف مشروعاته بس" متنفّذة كـ**قاعدة واحدة في الكود** (`Project::visibleTo`) جاهزة للموديولات دي، ومتغطّاة بتستات بس (مفيش شاشة للعميل تعرضها لسه).

---

## أ) القائمة والإنشاء

### 1. حسابات العملاء في قائمة المستخدمين
- **FRD:** §1.4 (جدول مستخدمي النظام: التبعية، الجهة التابع لها، الدور، الحالة)
- **الدور:** مدير النظام
- **الخطوات:**
  - افتح Users ← List.
  - بص على الحسابين التجريبيين (`Client Project Manager` و`Client Project Auditor`).
  - جرّب فلتر "All affiliations" ← Client، وفلتر "All companies" ← Al Noor Construction، وفلتر الدور.
- **المتوقع:** الحسابين ظاهرين بتبعية **Client** واسم الشركة في عمود "Entity" (`Al Noor Construction`) والدور. الفلاتر بتقصر القائمة على حسابات العملاء / على حسابات الشركة المختارة / على الدور المختار.
- **يغطيه آليًا:** `ClientAccounts/ClientUserManagementTest::test_the_users_list_shows_the_company_and_filters_by_affiliation_company_and_project`
- **النتيجة:** ⬜

### 2. زر "Add User" فيه "حساب عميل"
- **FRD:** §1.4 (زر إنشاء مستخدم جديد بقائمة اختيارات: مستخدم GCM / مستخدم عميل / مستخدم متعهد / سائق)
- **الدور:** مدير النظام
- **الخطوات:** في Users ← List دوس "Add User".
- **المتوقع:** القائمة فيها **GCM Staff** و**Client Account (Project Manager / Auditor)** و**Driver**. اختيار حساب العميل بيفتح صفحة "إنشاء حساب عميل". (مستخدم المتعهد هيتضاف مع موديول المتعهدين.)
- **يغطيه آليًا:** `ClientAccounts/ClientUserAccessTest::test_the_user_pages_render_for_a_manager`
- **النتيجة:** ⬜

### 3. إنشاء حساب عميل بصلاحية على جميع المشروعات
- **FRD:** §1.4 (إنشاء مستخدم جديد تابع لأحد العملاء)
- **الدور:** مدير النظام
- **الخطوات:**
  - من "Add User" اختار حساب عميل.
  - املأ الاسم والبريد والموبايل وكلمة السر وتأكيدها.
  - اختار الشركة `Al Noor Construction`، والدور "مدير مشروعات"، وسيب "جميع المشروعات" مختارة، والحالة "نشط".
  - دوس Submit.
- **المتوقع:** بترجع لقائمة المستخدمين برسالة نجاح والحساب ظاهر بتبعية Client واسم الشركة. صفحة تفاصيله بتعرض "Projects: All projects".
- **يغطيه آليًا:** `ClientAccounts/ClientUserManagementTest::test_an_account_with_access_to_all_projects_is_created`
- **النتيجة:** ⬜

### 4. إنشاء حساب بمشروعات محددة
- **FRD:** §1.4 ("مشروعات محددة" ← تظهر مشروعات الشركة للاختيار)
- **الدور:** مدير النظام
- **الخطوات:**
  - افتح صفحة إنشاء حساب عميل، واختار الشركة `Gulf Petrochemicals`.
  - اختار "Specific projects" وبص على القائمة اللي ظهرت.
  - دوس Submit من غير ما تختار مشروع.
  - اختار المشروع وأنشئ الحساب بدور "مراقب مشروعات".
- **المتوقع:** القائمة فيها **مشروعات الشركة دي النشطة بس** (`Gulf Petro Plant Expansion`؛ الـWarehouse المعطّل مش موجود). من غير اختيار مشروع بتظهر رسالة "This field is required" تحت القائمة. بعد الإنشاء التفاصيل بتعرض اسم المشروع كرابط. تغيير الشركة بيفرّغ الاختيارات ويعرض مشروعات الشركة الجديدة.
- **يغطيه آليًا:** `ClientAccounts/ClientUserManagementTest::test_an_account_can_be_limited_to_specific_projects`, `test_specific_projects_need_at_least_one_project`, `test_projects_must_belong_to_the_chosen_company_and_be_active`
- **النتيجة:** ⬜

### 5. الشركة المعطّلة مش متاحة، والتحقق من البيانات
- **FRD:** §1.11 (الشركة المعطّلة "مش متاحة للاختيار في العمليات الجديدة")
- **الدور:** مدير النظام
- **الخطوات:**
  - افتح صفحة إنشاء حساب عميل وافتح قائمة "Company".
  - جرّب تنشئ حساب بنفس البريد (أو نفس الموبايل) لحساب موجود.
- **المتوقع:** الشركة المعطّلة (`Riyadh Real Estate Group`) مش موجودة في القائمة، والقائمة بتدعم البحث الحي. البريد/الموبايل المكرر بيترفض برسالة تحت الخانة، وعدم كتابة حقل لازم بيظهر "This field is required".
- **يغطيه آليًا:** `ClientAccounts/ClientUserManagementTest::test_only_an_active_company_can_be_chosen_on_create`, `test_email_and_phone_must_be_unique`, `ClientAccounts/ClientUserAccessTest::test_a_company_from_another_tenant_cannot_be_chosen`
- **النتيجة:** ⬜

### 6. صورة التوقيع وصورة الختم التشغيلي
- **FRD:** §1.4 (صورة التوقيع، صورة الختم التشغيلي — اختياري)
- **الدور:** مدير النظام
- **الخطوات:**
  - أنشئ حساب عميل وارفع صورة توقيع وصورة ختم صغيرتين (PNG).
  - افتح صفحة تفاصيل الحساب.
  - جرّب ترفع ملف PDF بدل صورة، أو صورة أكبر من 2 ميجا.
- **المتوقع:** الصورتين بتظهر في تفاصيل الحساب. الملف اللي مش صورة، أو الأكبر من 2MB، بيترفض برسالة قبل الرفع. **الصورتين مش على رابط عام** — بيتحمّلوا من رابط محمي بصلاحية (مش لينك مباشر من مجلد التخزين).
- **يغطيه آليًا:** `ClientAccounts/ClientUserManagementTest::test_the_photo_is_public_but_the_signature_and_stamp_are_private`, `test_the_images_must_be_images_within_the_size_limit`, `ClientAccounts/ClientUserAccessTest::test_the_signature_and_stamp_are_served_only_through_the_gate_checked_route`
- **النتيجة:** ⬜

---

## ب) التفاصيل والتعديل والحالة

### 7. صفحة تفاصيل حساب عميل
- **FRD:** §1.4 (تفاصيل الحساب: الاسم، الحالة، الدور، الشركة، المشروعات، البريد، الموبايل، الصورة، بيانات إضافية)
- **الدور:** مدير النظام
- **الخطوات:** من قائمة المستخدمين افتح تفاصيل `Client Project Auditor` ثم `Client Project Manager`.
- **المتوقع:** بتعرض الاسم والحالة والرقم التعريفي والبريد والموبايل والتبعية (Client) والشركة **كرابط لصفحة الشركة** والدور. المشروعات: للمراقب `Al Noor Tower` كرابط لصفحة المشروع، وللمدير "All projects". لو الحساب فيه توقيع/ختم بيظهروا. "آخر تحديث: تم بواسطة ...". زر Edit بيودّي لفورم حساب العميل.
- **يغطيه آليًا:** `ClientAccounts/ClientUserManagementTest::test_an_account_can_be_limited_to_specific_projects`, `ClientAccounts/ClientUserAccessTest::test_the_user_pages_render_for_a_manager`
- **النتيجة:** ⬜

### 8. تعديل حساب عميل
- **FRD:** §1.4 (كل البيانات قابلة للتعديل من مدير النظام / مدخل البيانات)
- **الدور:** مدير النظام
- **الخطوات:**
  - افتح تعديل حساب عميل.
  - غيّر الاسم والموبايل والدور، وبدّل من "مشروعات محددة" لـ"جميع المشروعات" (أو العكس)، وغيّر الشركة.
  - سيب كلمة السر فاضية، ولاحظ خانة البريد.
  - احفظ.
- **المتوقع:** البريد **مقفول** (للقراءة فقط). كلمة السر الفاضية بتسيب كلمة السر الحالية. التعديلات كلها بتتحفظ وبترجع لصفحة التفاصيل برسالة نجاح. الصور الموجودة بتفضل ولو مرفعتش جديدة (فيه رابط "Current file")، ورفع صورة جديدة بيبدّل القديمة.
- **يغطيه آليًا:** `ClientAccounts/ClientUserManagementTest::test_update_changes_every_field_except_the_email`, `test_switching_back_to_all_projects_clears_the_specific_list`, `test_a_replaced_signature_is_deleted_from_disk`
- **النتيجة:** ⬜

### 9. تعديل حساب عميل من فورم المستخدمين العام بيتحوّل
- **FRD:** §1.4
- **الدور:** مدير النظام
- **الخطوات:** افتح مباشرة `/app/user/edit/{id}` لحساب عميل (بدل زر Edit).
- **المتوقع:** بتظهر رسالة إن ده حساب عميل وتعديله من "client account form" (رابط) — مفيش فورم تعديل مستخدمي GCM بيظهر (كان هيغيّر دوره لمدخل بيانات/مراقب بالغلط). ومحاولة حفظ حساب عميل من الفورم العام بتترفض من السيرفر برضو.
- **يغطيه آليًا:** `ClientAccounts/ClientUserManagementTest::test_the_generic_edit_endpoint_refuses_a_client_account`, `test_the_client_endpoint_refuses_a_non_client_account`
- **النتيجة:** ⬜

### 10. تغيير حالة الحساب وتسجيل الدخول
- **FRD:** §1.4 (تعطيل / تنشيط / في إجازة من مدير النظام / مدخل البيانات)
- **الدور:** مدير النظام
- **الخطوات:**
  - من تفاصيل حساب عميل، من كارت "Account Status" اختار "Deactivated"، أكّد، ودوس Update Status.
  - حاول تسجّل دخول بالحساب ده في نافذة تانية.
  - رجّعه "Active".
- **المتوقع:** الحالة بتتغيّر. الحساب المعطّل بتظهر له رسالة "حسابك معطل من قبل مدير النظام..." عند الدخول، وبعد التنشيط بيدخل عادي.
- **يغطيه آليًا:** `ClientAccounts/ClientUserManagementTest::test_status_goes_through_the_shared_status_endpoint`, `test_a_deactivated_client_cannot_log_in`
- **النتيجة:** ⬜

---

## ج) ممثل الشركة وممثل المشروع

### 11. ممثل الشركة + عدد المستخدمين
- **FRD:** §1.11 (حساب ممثل العميل — اختياري؛ عمود ممثل الشركة وعدد المستخدمين في القائمة)
- **الدور:** مدير النظام
- **الخطوات:**
  - افتح Client Companies ← List وبص على `Al Noor Construction`.
  - افتح تعديلها، وافتح خانة "Client representative account".
  - جرّب تشيل الممثل (سيب الخانة فاضية) واحفظ، وبعدين اختاره تاني.
  - افتح صفحة إنشاء شركة جديدة وبص على خانة "Client representative account".
- **المتوقع:** في القائمة: ممثل الشركة `Client Project Manager` وعدد المستخدمين `2`. الخانة في التعديل بتعرض **مديري المشروعات النشطين التابعين للشركة دي بس** (المراقب وحسابات الشركات التانية مش موجودين). صفحة **إنشاء** شركة فيها الخانة **ظاهرة ومقفولة** مع شرح إنها بتتاح بعد إنشاء الشركة وإضافة مديري مشروعاتها (شركة جديدة مفيهاش حسابات لسه — الـFRD نفسه بيقول تتحدد بعد إنشاء الحسابات).
- **يغطيه آليًا:** `ClientAccounts/RepresentativeAccountTest::test_a_project_manager_of_the_company_can_be_its_representative`, `test_only_an_active_project_manager_of_the_same_company_qualifies`, `test_the_company_user_count_counts_its_client_accounts`, `test_a_new_company_has_no_representative_field`
- **النتيجة:** ⬜

### 12. ممثل المشروع + عدد المستخدمين
- **FRD:** §1.12 (حساب ممثل المشروع — اختياري؛ عدد المستخدمين التابعين للمشروع)
- **الدور:** مدير النظام
- **الخطوات:**
  - افتح Client Projects ← List وبص على `Al Noor Tower` و`Al Noor Residential Complex`.
  - افتح تعديل `Al Noor Tower` وبص على خانة "Project representative account".
  - افتح "Add Project"، واختار شركة `Al Noor Construction` وبص على الخانة، وبعدين بدّل لشركة `Gulf Petrochemicals`.
- **المتوقع:** عدد المستخدمين: Tower = `2` (المدير بصلاحية الكل + المراقب المسند له)، والـResidential = `1` (المدير بس). خانة التعديل بتعرض **كل حسابات العميل اللي تقدر تشوف المشروع** (المدير والمراقب) والمختار ظاهر (`Client Project Auditor`). في صفحة الإنشاء الخانة بتعرض حسابات "جميع المشروعات" للشركة المختارة بس، وبتفضى لما تختار شركة مفيهاش حسابات كده.
- **يغطيه آليًا:** `ClientAccounts/RepresentativeAccountTest::test_an_account_that_can_see_the_project_can_be_its_representative`, `test_an_account_that_cannot_see_the_project_is_refused`, `test_a_new_project_may_pick_one_of_the_companys_all_projects_accounts`, `test_the_project_user_count_is_all_projects_accounts_plus_assigned_ones`
- **النتيجة:** ⬜

### 13. الممثل بيتشال لما الحساب يفقد صفته
- **FRD:** §1.11 / §1.12
- **الدور:** مدير النظام
- **الخطوات:**
  - عدّل `Client Project Manager` (ممثل الشركة) وغيّر دوره لـ"مراقب مشروعات"، واحفظ.
  - افتح تعديل الشركة.
  - عدّل `Client Project Auditor` (ممثل Al Noor Tower) وخليه "مشروعات محددة" على Residential Complex بس، واحفظ، وافتح تعديل Al Noor Tower.
- **المتوقع:** ممثل الشركة بقى فاضي (المراقب ما ينفعش يمثل الشركة)، وممثل المشروع Al Noor Tower بقى فاضي (الحساب مابقاش يشوف المشروع ده). مفيش حساب بيفضل مكتوب ممثل لحاجة مش تابعة له.
- **يغطيه آليًا:** `ClientAccounts/RepresentativeAccountTest::test_editing_an_account_out_of_the_role_clears_the_representations`, `test_moving_an_account_to_another_company_clears_both_representations`, `test_removing_a_project_from_an_account_clears_that_projects_representative`
- **النتيجة:** ⬜

---

## د) الصلاحيات والعزل

### 14. الملف الشخصي لحساب العميل
- **FRD:** §1.4 (المستخدم يقدر يغيّر من ملفه الشخصي: الصورة، كلمة المرور، صورة التوقيع، صورة الختم)
- **الدور:** حساب عميل (`client.manager@gcm.test`)
- **الخطوات:**
  - سجّل دخول بحساب العميل وافتح My Profile.
  - ارفع صورة توقيع (بس) واضغط "Save signature & stamp"، وبعدين ارفع صورة ختم.
  - غيّر كلمة السر من كارت "Change Password".
  - جرّب تضغط الحفظ من غير ما تختار صورة.
- **المتوقع:** فيه 3 كروت: الصورة، **Signature & Stamp**، Change Password. رفع صورة واحدة بيسيب التانية زي ما هي. من غير اختيار صورة بتظهر رسالة "Choose a signature or stamp image first". الاسم والبريد للقراءة فقط. القائمة الجانبية فاضية، ولو فتحت `/app/user/list` أو أي API إداري بترجع 403. باقي الأدوار (مدير النظام/مدخل البيانات/المراقب/السائق) مفيش عندهم كارت التوقيع والختم.
- **يغطيه آليًا:** `Profile/ClientSignatureStampTest` (كلها)، `ClientAccounts/ClientUserAccessTest::test_a_client_account_cannot_reach_any_admin_module`, `test_client_accounts_are_offered_no_admin_menu_entries`
- **النتيجة:** ⬜

### 15. الصلاحيات حسب الدور
- **FRD:** §1.4 (إنشاء / تعديل / تعطيل الحسابات مسؤولية مدير النظام / مدخل البيانات؛ المراقب عرض/تصدير)
- **الدور:** مدير النظام / مدخل البيانات / المراقب / السائق
- **الخطوات:**
  - بـ`dataentry` أنشئ وعدّل وعطّل حساب عميل.
  - بـ`auditor` افتح قائمة المستخدمين وتفاصيل حساب عميل، وحاول تفتح `/app/client-user/add`.
  - بـ`driver` حاول تفتح قائمة المستخدمين.
- **المتوقع:** مدخل البيانات نفس صلاحيات مدير النظام تمامًا. المراقب بيشوف ويصدّر بس (الإنشاء/التعديل 403). السائق ممنوع بالكامل (403). لا يقدر مدخل البيانات يعدّل حساب مدير النظام.
- **يغطيه آليًا:** `ClientAccounts/ClientUserAccessTest::test_system_admin_and_data_entry_manage_client_accounts`, `test_the_auditor_can_see_client_accounts_but_not_change_them`, `test_drivers_and_client_accounts_cannot_use_the_client_endpoints`, `test_data_entry_cannot_edit_the_system_admin_through_the_client_endpoint`
- **النتيجة:** ⬜

### 16. عزل الشركات (tenants) وقاعدة رؤية المشروعات
- **FRD:** §1.4 ("مشروع واحد أو عدة مشروعات موكلة إليه" — يشوف مشروعاته بس)
- **الدور:** — (آلي بس)
- **الخطوات:** شغّل `php artisan test --filter=ClientAccounts`.
- **المتوقع:** كله أخضر: حسابات tenant تاني مش بتظهر ولا بتتعدّل، وقاعدة الرؤية: حساب "جميع المشروعات" بيشوف مشروعات شركته كلها (حتى اللي تتعمل بعدين)، وحساب "مشروعات محددة" بيشوف المسندة له بس، ومحدش بيشوف مشروع شركة تانية. (دي القاعدة اللي الرحلات والتعاقدات والتقارير هتبني عليها.)
- **يغطيه آليًا:** `ClientAccounts/ProjectVisibilityTest` (كلها)، `ClientAccounts/ClientUserAccessTest::test_another_tenants_client_accounts_are_invisible`
- **النتيجة:** ⬜

### 17. التصدير بيعرض الشركة
- **FRD:** §1.4 (زر تصدير Excel / PDF لقائمة المستخدمين)
- **الدور:** مدير النظام
- **الخطوات:** في Users ← List فلتر بـ"Client" ودوس Export ← Excel ثم Pdf.
- **المتوقع:** الملفين فيهم حسابات العملاء بس، وعمود "Entity" فيه اسم الشركة، والتبعية "Client" (وبالعربي "عميل").
- **يغطيه آليًا:** `ClientAccounts/ClientUserManagementTest::test_the_users_export_shows_the_company_as_the_entity`
- **النتيجة:** ⬜
