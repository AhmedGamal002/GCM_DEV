# اختبار قبول المستخدم — دور مدير النظام (`admin@gcm.test`)

> آخر مزامنة: **FRD V01.14** (2026-10-15) · بنود خاصة بمدير النظام بس (`build-uat.mjs` بيسحبها بالرقم مع بنود الأسابيع والبنود المشتركة). مش جزء من `checklist.html` الداخلي.

---

## أ) القائمة الجانبية

### 1. القائمة الجانبية لمدير النظام بعد المرحلة التانية
- **FRD:** جداول الصلاحيات لكل قسم + توصيف صفحات النظام لدور مدير النظام
- **الخطوات:** سجّل دخول بحسابك وبص على القائمة الجانبية، وبعدين بدّل اللغة لعربي.
- **المتوقع:**
  - Dashboard.
  - عنوان **Accounts**: Users (List) وDrivers (List وAdd).
  - عنوان **Fleet & Assets**: Vehicles (List وAdd وCategories) وAssets (List وAdd وCapacities).
  - عنوان **Operations**: Facilities (List وAdd) وServices (Main Services وSub Services — كل واحد List وAdd) وContractors (List وAdd).
  - عنوان **Clients & Projects**: Client Companies وClient Projects (كل واحد List وAdd).
  - عنوان **System**: General Settings.
  - مفيش أي بنود تانية. بالعربي العناوين: "الحسابات" و"الأسطول والأصول" و"العمليات" و"العملاء والمشروعات" و"النظام".
- **يغطيه آليًا:** `MenuVisibilityTest`
- **النتيجة:** ⬜
