# قبول التنفيذ — المرحلة 2: حسابات مستخدمي المتعهد + ممثل المتعهد

> آخر مزامنة: **FRD V01.14** (2026-10-04) · شغّل `php artisan test` الأول (لازم كله أخضر) ثم `php artisan migrate:fresh --seed`.

نفس الحسابات من ملف الأسبوع 1–2. بعد الـseed فيه مستخدمين متعهد (الباسورد `password`): `contractor.desert@gcm.test` (تابع لـ**Desert Haulage Co.** وهو **ممثل المتعهد** بتاعها) و`contractor.gulf@gcm.test` (تابع لـ**Gulf Logistics**). المتعهد المعطّل **Al Safa Transport** مايظهرش في اختيار المتعهد عند إنشاء مستخدم جديد.

> ملحوظة نطاق: دور "مستخدم متعهد" في الـFRD (§1.4) مراقب بصلاحية **عرض/تصدير** لموارد متعهده فقط (سائقين / مستخدمي المتعهد / مركبات / حاويات / رحلات). صفحات العرض دي بتتبني مع الرحلات في مرحلة لاحقة — دلوقتي الحساب بيتنشأ ويتعدّل ويدخل النظام ويشوف ملفه الشخصي بس، والقائمة الجانبية فاضية له عمدًا.

---

## أ) الإنشاء والعرض

### 1. إضافة مستخدم متعهد من قايمة المستخدمين
- **FRD:** §1.4 (إنشاء مستخدم جديد تابع لأحد المتعهدين)
- **الخطوات:** بـ`admin` افتح Users ← زر "Add User".
- **المتوقع:** القايمة المنسدلة فيها أربع اختيارات: GCM Staff / Client Account / **Contractor User** / Driver. اختيار "Contractor User" بيفتح صفحة "إنشاء مستخدم متعهد".
- **يغطيه آليًا:** `Contractors/ContractorUserManagementTest::test_the_form_pages_are_for_whoever_may_manage_accounts`
- **النتيجة:** ⬜

### 2. فورم الإنشاء والتحقق
- **FRD:** §1.4 (الاسم، البريد، الموبايل، كلمة المرور، الصورة، المتعهد التابع له، حالة الحساب، بيانات إضافية)
- **الخطوات:** في صفحة الإنشاء دوس "Submit" والفورم فاضي. بعدين اكتب بيانات صحيحة، واختار متعهد، وسيب الحالة "Active"، واحفظ.
- **المتوقع:** الفورم الفاضي بيطلّع "This field is required" تحت الاسم والبريد والموبايل وكلمة المرور والمتعهد. حقول الدور مش موجودة (الدور ثابت "مستخدم متعهد"). قايمة المتعهد فيها **النشطين بس** (Desert و Gulf — مش Al Safa). بعد الحفظ بترجع لقايمة المستخدمين برسالة نجاح، والمستخدم ظاهر فيها بتبعية "Contractor" واسم المتعهد في "اسم الجهة".
- **يغطيه آليًا:** `Contractors/ContractorUserManagementTest` (`test_a_contractor_user_is_created_with_the_fixed_role_and_affiliation`, `test_the_required_fields_are_enforced`, `test_the_contractor_must_be_one_of_the_active_ones_of_the_tenant`)
- **النتيجة:** ⬜

### 3. البريد غير مكرر + الصلاحيات
- **FRD:** §1.4 / أدوار النظام
- **الخطوات:** حاول تنشئ مستخدم متعهد ببريد مستخدم بالفعل (مثلًا `admin@gcm.test`). بعدين سجّل دخول بـ`dataentry` وأنشئ واحد. بعدين بـ`auditor` افتح Users.
- **المتوقع:** البريد المكرر بيترفض برسالة تحت الحقل. مدخل البيانات ينشئ عادي. المراقب مايشوفش زر "Add User" ولا أيقونات التعديل، وفتح `/app/contractor-user/add` بيدّيه 403.
- **يغطيه آليًا:** `Contractors/ContractorUserManagementTest` (`test_the_email_must_be_unique_across_the_system`, `test_data_entry_can_create_one_too`, `test_only_admin_and_data_entry_may_create`, `test_the_auditor_cannot_open_the_form_pages`)
- **النتيجة:** ⬜

### 4. صفحة تفاصيل مستخدم المتعهد
- **FRD:** §1.4 (تفاصيل الحساب: الاسم، الحالة، الدور "مستخدم متعهد"، شركة المتعهد، البريد، الموبايل، الصورة، بيانات إضافية)
- **الخطوات:** افتح تفاصيل `Desert Haulage User` من قايمة المستخدمين.
- **المتوقع:** الدور "Contractor User" والتبعية "Contractor" واسم المتعهد **رابط** لصفحة تفاصيل المتعهد. مفيش سطر "Projects" ولا صور توقيع/ختم (دي للعميل بس). زر Edit بيفتح فورم المتعهد (مش الفورم العام).
- **يغطيه آليًا:** `Contractors/ContractorUserManagementTest::test_the_users_list_shows_it_filters_by_role_and_by_contractor`
- **النتيجة:** ⬜

---

## ب) التعديل والحالة

### 5. تعديل كل البيانات (البريد والمتعهد كمان)
- **FRD:** §1.4 (جميع البيانات قابلة للتعديل من خلال مدير النظام أو مدخل البيانات)
- **الخطوات:** افتح تعديل المستخدم. غيّر الاسم والبريد والموبايل والمتعهد، وحط كلمة سر جديدة، واحفظ. سجّل دخول بالبريد الجديد.
- **المتوقع:** الفورم متعبّى بالبيانات الحالية، وترك كلمة السر فاضية بيحافظ على القديمة. بعد الحفظ بترجع للتفاصيل برسالة نجاح و"آخر تحديث" بيتغيّر. الدخول بالبريد الجديد ينفع وبالقديم لأ. المتعهد اللي اتعطّل بعد كده يفضل ظاهر على مستخدميه القدام، لكن مينفعش تنقل مستخدم لمتعهد معطّل.
- **يغطيه آليًا:** `Contractors/ContractorUserManagementTest` (`test_every_field_including_the_email_and_contractor_is_editable`, `test_a_blank_password_keeps_the_current_one`, `test_the_current_contractor_stays_valid_after_it_was_deactivated`, `test_moving_to_a_deactivated_contractor_is_refused`)
- **النتيجة:** ⬜

### 6. الفورم العام مايعدّلش مستخدم متعهد
- **FRD:** §1.4 (إنشاء/تعديل حساب المتعهد صفحة منفصلة)
- **الخطوات:** افتح `/app/user/edit/{id}` لمستخدم متعهد.
- **المتوقع:** بتظهر رسالة بتوجّهك لـ"contractor user form" بدل الفورم العام. عن طريق الـAPI الطلب على `/users/{id}` بيرجّع 403 والدور مايتغيّرش.
- **يغطيه آليًا:** `Contractors/ContractorUserManagementTest` (`test_the_generic_edit_endpoint_refuses_a_contractor_user`, `test_the_generic_users_endpoint_refuses_the_role`)
- **النتيجة:** ⬜

### 7. تعطيل المستخدم أو وضعه "في إجازة"
- **FRD:** §1.4 (تعطيل / تنشيط الحساب — مدير النظام / مدخل البيانات)
- **الخطوات:** من صفحة تفاصيل المستخدم غيّر حالته لـ"On Vacation" ثم "Deactivated" بزر "Update Status". بعدين حاول تدخل بحسابه.
- **المتوقع:** الحالة بتتغيّر. المعطّل مابيدخلش (أول طلب بعد التعطيل بيطلّعه)، والذي "في إجازة" بيدخل بشريط تنبيه وكل أزرار الكتابة مقفولة.
- **يغطيه آليًا:** `Contractors/ContractorUserManagementTest::test_status_is_changed_through_the_shared_endpoint`, `Auth/AccountStatusAccessTest`
- **النتيجة:** ⬜

---

## ج) ممثل المتعهد

### 8. اختيار ممثل المتعهد
- **FRD:** §1.10.2 (حساب ممثل المتعهد — اختياري، من ضمن حسابات المتعهد)
- **الخطوات:** افتح Contractors ← Add وبص على حقل "Contractor representative account". بعدين افتح تعديل `Desert Haulage Co.` واختار مستخدم من القايمة، واحفظ، وافتح التفاصيل.
- **المتوقع:** في الإنشاء الحقل ظاهر **ومقفول** مع تلميح إنه بيتحدد من صفحة التعديل (المتعهد الجديد معندوش مستخدمين). في التعديل القايمة فيها **المستخدمين النشطين لنفس المتعهد بس** (مش مستخدمي متعهد تاني ولا سائقين ولا موظفي GCM). بعد الحفظ الاسم بيظهر في تفاصيل المتعهد تحت "Contractor representative". ينفع تفضّيه.
- **يغطيه آليًا:** `Contractors/ContractorUserManagementTest` (`test_a_contractor_picks_one_of_its_active_users_as_representative`, `test_the_representative_can_be_cleared`, `test_only_an_active_user_of_this_contractor_qualifies`, `test_the_representative_is_only_chosen_on_edit_not_on_create`)
- **النتيجة:** ⬜

### 9. الممثل يفضل سليم بعد التعديلات
- **FRD:** §1.10.2
- **الخطوات:** حط مستخدم ممثل لمتعهده، بعدين انقله لمتعهد تاني من فورم المستخدم. وفي حالة تانية خلّي الممثل "On Vacation" وعدّل أي بيانات في المتعهد واحفظ.
- **المتوقع:** نقل المستخدم لمتعهد تاني بيشيله من خانة الممثل في المتعهد الأول (مايفضلش اسمه ظاهر على متعهد مابقاش تابع له). وضع الممثل "في إجازة" مابيمنعش حفظ بيانات المتعهد، والممثل بيفضل مكانه.
- **يغطيه آليًا:** `Contractors/ContractorUserManagementTest` (`test_moving_the_user_to_another_contractor_releases_the_representation`, `test_an_unchanged_representative_stays_valid_after_going_on_vacation`)
- **النتيجة:** ⬜

---

## د) دخول مستخدم المتعهد

### 10. الدخول والملف الشخصي (من غير صلاحيات إدارية)
- **FRD:** §1.4 (الدور مراقب، عرض/تصدير فقط في أقسام المتعهد، ولا صلاحيات تفاعلية)
- **الخطوات:** سجّل دخول بـ`contractor.desert@gcm.test`. افتح الملف الشخصي وغيّر كلمة السر والصورة. جرّب تفتح `/app/contractor-user/add`.
- **المتوقع:** الدخول بيشتغل والقائمة الجانبية من غير أي صفحة إدارة. الملف الشخصي فيه الصورة وكارت "Change Password" (بدون "Signature & Stamp" — ده للعميل). الصفحات والـAPI الإدارية (مستخدمين، متعهدين، مركبات، سائقين، أصول) بترجّع 403.
- **يغطيه آليًا:** `Contractors/ContractorUserManagementTest` (`test_a_contractor_user_can_change_their_own_password_but_reach_no_admin_module`, `test_a_contractor_user_changes_their_own_password_and_photo_only`, `test_a_contractor_user_cannot_set_a_signature_or_stamp`, `test_a_contractor_user_cannot_write_to_any_admin_module`, `test_a_contractor_user_is_offered_no_admin_menu_entries`, `test_a_contractor_user_cannot_open_the_account_forms`)
- **النتيجة:** ⬜
