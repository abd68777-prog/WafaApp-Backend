# توثيق الـAPI — وفاء

مرجع لمهندس الـFrontend (تطبيق الزبون، تطبيق التاجر، لوحة الإدارة).
كل الأمثلة بهاد الملف ردود حقيقية من السيرفر.

> **الحالة الحالية:** جاهز **الدخول والتسجيل والصلاحيات** للأطراف الثلاثة: حساب الزبون
> كامل (مع الحذف وتجديد الموافقة)، وتسجيل التاجر وحماية الـPIN وتغييره، وأدوار لوحة
> الإدارة وإدارة حساباتها، ورموز الإشعارات. الطوابع والبطاقات والحملات والدفعات
> بتنضاف بالمراحل الجاية، وكل وحدة منها محمية بالحارس المكتوب بـ«خريطة الوصول» (§11).

---

## 1. معلومات عامة

| البند | القيمة |
|---|---|
| Base URL (محلي) | `http://127.0.0.1:8000` — وبـDocker نفس العنوان |
| بادئة الـAPI | `/api/v1` |
| صيغة الطلب والرد | JSON فقط |
| صيغة التواريخ | ISO 8601 بتوقيت UTC |

### نوعين من المصادقة

| مين | كيف بيسجّل دخول | شو بيبعت بـ`Authorization` |
|---|---|---|
| **الزبون** | رقم موبايل + رمز OTP على واتساب | توكن السيرفر الراجع من `otp/verify` |
| **التاجر ولوحة الإدارة** | Clerk | توكن جلسة Clerk من `getToken()` — بكل طلب |

> **رمز واتساب (OTP) لتطبيق الزبون بس.** التاجر والإدارة ما بيستعملوه أبداً، ودخولهم كله عبر
> Clerk (حساب Google أو رمز على البريد). و`phone` بخطوة «بيانات النشاط» للتاجر هو رقم تواصل
> للمحل، مو للدخول.

### Clerk بتطبيق التاجر ولوحة الإدارة

- **تطبيق Clerk واحد** للاتنين. المفتاح العام (`pk_test_…`) بتاخده من مطوّر الباك إند، وما هو سرّي.
  **المفتاح السري (`sk_…`) ما بيدخل الفرونت أبداً.**
- **طرق الدخول:** حساب Google، أو بريد برمز تحقق. ما في كلمة سر.
- **تطبيق التاجر (Expo):**
  - `@clerk/clerk-expo` مع `tokenCache` تبع Clerk.
  - قبل كل طلب: `getToken()`، والتوكن بينبعت بـ`Authorization`.
  - توكنات التطبيق الأصلي ما فيها `azp`، والخادم بيقبلها.
- **لوحة الإدارة (Next.js):** `@clerk/nextjs`. أصل اللوحة (مثلاً `http://localhost:3000`) لازم يكون
  بإعدادَين بالباك إند:
  - `CLERK_AUTHORIZED_PARTIES`، وإلا الرد `401`.
  - `FRONTEND_URLS`، وإلا المتصفح بيرفض بسبب CORS.
- **البريد:** الخادم بياخده من توكن Clerk نفسه، لأن ادعاء `email` مضاف بلوحة Clerk. ما تبعته بالـbody.
- **تغيير الـPIN** بيحتاج `useReverification` (§7).
- **Google بملف الـAPK:** لازم تنضاف بصمة SHA-1 تبع مفتاح توقيع الـAPK بإعدادات Google وClerk.
  بدونها دخول Google بيفشل عند التجار، مع إنه بيشتغل وقت التطوير.

### الـHeaders

| Header | متى | القيمة |
|---|---|---|
| `Accept` | كل طلب | `application/json` |
| `Content-Type` | أي طلب فيه body | `application/json` |
| `Authorization` | الـendpoints المحمية 🔒 | `Bearer <token>` |
| `X-App-Version` | **تطبيق التاجر فقط** | نسخة التطبيق، مثال `1.4.0` |
| `X-Pin-Token` | **تطبيق التاجر — التبويبات المحمية** | `pin_token` الراجع من `pin/verify` |

`X-App-Version` مطلوب من تطبيق التاجر لأنه موزَّع خارج المتاجر ولا يتحدث تلقائياً:
الخادم بيرفض النسخ الأقدم من الحد الأدنى برمز خاص لتظهر شاشة التحديث الإجباري.
الطلب بدون الهيدر بيمرّ (المتصفح والأدوات ما بتبعته).

---

## 2. شكل الأخطاء

| الكود | المعنى | الرد |
|---|---|---|
| `401` | ما في توكن، أو منتهي، أو غير صالح | `{"message": "Unauthenticated."}` |
| `403` | التوكن صالح بس صاحبه ما عنده صلاحية — ومعه `code` | شوف الجدول تحت |
| `404` | الـendpoint مو موجود | `{"message": "Endpoint not found."}` |
| `404` | العنصر المطلوب مو موجود | `{"message": "Resource not found."}` |
| `409` | العملية متعارضة مع الحالة الحالية (تسجيل مرتين مثلاً) | `{"message": "…"}` |
| `422` | خطأ بالبيانات | `message` + `errors` |
| `426` | نسخة تطبيق التاجر قديمة | `code: app_update_required` |
| `429` | تجاوز حد الطلبات | `{"message":"Too Many Attempts."}` + هيدر `Retry-After` |
| `429` | طلب رمز جديد قبل انتهاء مدة الانتظار | `{"message":"…","retry_after":57}` |
| `503` | ما قدرنا نوصّل رمز التحقق | `{"message":"Could not send the verification code right now. Please try again shortly."}` |

### رموز `403`

| `code` | مين | متى | شو يعمل التطبيق |
|---|---|---|---|
| `merchant_not_registered` | تاجر | مستخدم Clerk بدون نشاط مسجّل | افتح شاشة تسجيل النشاط |
| `registration_incomplete` | تاجر | التسجيل ناقص — ومعه `registration_step` | كمّل الخطوة المطلوبة |
| `merchant_deleted` | تاجر | الحساب محذوف | شاشة تواصل مع الدعم |
| `pin_required` | تاجر | تبويب محمي بدون `X-Pin-Token` صالح | اطلب الـPIN |
| `reverification_required` | تاجر | تغيير الـPIN بدون دخول حديث بـClerk | `useReverification()` بيتعامل معه لحاله (§7) |
| `permission_denied` | إدارة | دور الحساب ما بيسمح بهالعملية | خبّي الزر، واعرض رسالة |

---

## 3. حدود الطلبات

| الحد | على شو | العدد |
|---|---|---|
| `api` | كل `/api/*` | 60 بالدقيقة |
| `otp` | طلب رمز | 5 بالساعة لكل رقم · 20 بالساعة لكل IP |
| `otp-verify` | التحقق من الرمز | 10 بالدقيقة لكل رقم · 30 لكل IP |
| `pin` | التحقق من الـPIN | 5 بالدقيقة لكل تاجر |

تجاوز أي حد منهم بيرجع `429` مع `{"message":"Too Many Attempts."}` وهيدر `Retry-After`.
وفوقهم مدة انتظار **60 ثانية بين رمز ورمز** لنفس الرقم، وهي بترجع `429` مع `retry_after` بالـbody.

---

## 4. الفهرس

| Method | Path | 🔒 | الوصف |
|---|---|---|---|
| `GET` | `/api/v1/ping` | – | فحص الاتصال |
| `GET` | `/api/v1/lookups/governorates` | – | المحافظات الـ14 |
| `GET` | `/api/v1/lookups/business-types` | – | أنواع النشاط |
| `GET` | `/api/v1/lookups/icons` | – | مكتبة أيقونات البطاقات |
| `GET` | `/api/v1/lookups/packages` | – | الباقات ومصفوفة الأسعار |
| `GET` | `/api/v1/policy` | – | إصدار سياسة الخصوصية وروابطها |
| `POST` | `/api/v1/customer/auth/otp/request` | – | إرسال رمز تحقق |
| `POST` | `/api/v1/customer/auth/otp/verify` | – | التحقق وإنشاء الحساب وإصدار توكن |
| `GET` | `/api/v1/customer/auth/me` | 🔒 زبون | بيانات الزبون |
| `POST` | `/api/v1/customer/auth/logout` | 🔒 زبون | خروج من الجهاز الحالي |
| `POST` | `/api/v1/customer/auth/logout-all` | 🔒 زبون | خروج من كل الأجهزة |
| `POST` | `/api/v1/customer/devices` | 🔒 زبون | تسجيل رمز إشعارات الجهاز |
| `POST` | `/api/v1/customer/policy/accept` | 🔒 زبون | الموافقة على إصدار سياسة جديد |
| `DELETE` | `/api/v1/customer/account` | 🔒 زبون | حذف الحساب |
| `GET` | `/api/v1/merchant/auth/me` | 🔒 Clerk | حالة التاجر وخطوة التسجيل التالية |
| `POST` | `/api/v1/merchant/auth/logout` | 🔒 Clerk | نسيان رمز إشعارات الجهاز عند الخروج |
| `POST` | `/api/v1/merchant/registration/business` | 🔒 Clerk | خطوة 1: بيانات النشاط |
| `POST` | `/api/v1/merchant/registration/package` | 🔒 Clerk | خطوة 2: اختيار الباقة وبدء التجربة |
| `POST` | `/api/v1/merchant/registration/pin` | 🔒 Clerk | خطوة 3: إنشاء رمز PIN |
| `POST` | `/api/v1/merchant/pin/verify` | 🔒 تاجر | التحقق من الـPIN وإصدار توكن فتح |
| `PUT` | `/api/v1/merchant/pin` | 🔒 تاجر + دخول حديث | تغيير الـPIN أو استرجاعه |
| `POST` | `/api/v1/merchant/devices` | 🔒 تاجر | تسجيل رمز إشعارات الجهاز |
| `GET` | `/api/v1/admin/auth/me` | 🔒 إدارة | الحساب ودوره وصلاحياته |
| `GET` | `/api/v1/admin/admin-users` | 🔒 Super Admin | حسابات الإدارة |
| `POST` | `/api/v1/admin/admin-users` | 🔒 Super Admin | إضافة حساب إدارة |
| `PATCH` | `/api/v1/admin/admin-users/{id}` | 🔒 Super Admin | تعديل الاسم أو الدور أو التفعيل |
| `DELETE` | `/api/v1/admin/admin-users/{id}` | 🔒 Super Admin | تعطيل حساب إدارة |
| `POST` | `/api/v1/webhooks/clerk` | توقيع Clerk | **للخادم فقط** — مش للفرونت (§12) |

---

## 5. القوائم

### `GET /api/v1/lookups/governorates`

```json
{ "data": [ { "id": 1, "name": "دمشق" }, { "id": 2, "name": "ريف دمشق" } ] }
```

`GET /api/v1/lookups/business-types` و`GET /api/v1/lookups/icons` بنفس الشكل
(الأيقونات فيها `key` كمان، والتطبيق بيرسم حسبها).

### `GET /api/v1/lookups/packages`

```json
{
  "data": [
    {
      "id": 1,
      "name": "الأساسية",
      "cards_limit": 1,
      "weekly_campaigns_limit": 1,
      "prices": [
        { "duration_months": 1, "price_usd": "10.00" },
        { "duration_months": 3, "price_usd": "27.00" },
        { "duration_months": 12, "price_usd": "96.00" }
      ]
    }
  ]
}
```

> الأسعار والأسماء قيم مبدئية لحد ما تجهز قيم Deep Code، وبتتعدّل من اللوحة.

### `GET /api/v1/policy`

```json
{
  "privacy_policy_version": "1.2",
  "privacy_policy_url": "",
  "customer_terms_url": "",
  "merchant_terms_url": ""
}
```

`privacy_policy_version` هو يلي لازم يرجع بطلب التحقق مع مربّع الموافقة.

---

## 6. تطبيق الزبون

### التدفق

```
1. الرقم + مربّع الموافقة على السياسة   →  POST /customer/auth/otp/request
2. رمز 6 أرقام على واتساب (صالح 5 دقائق)
3. الرمز + الاسم + تاريخ الميلاد + إصدار السياسة  →  POST /customer/auth/otp/verify
   ├─ حساب جديد وبيانات ناقصة  ⇒ 422، والرمز بيضل صالح
   └─ تمام ⇒ توكن + qr_secret + «طوابعك وصلت» إذا كان رقمه ممسوح من قبل
4. احفظ التوكن وابعته بكل طلب
5. سجّل رمز إشعارات الجهاز  →  POST /customer/devices
6. عند كل فتح للتطبيق: GET /customer/auth/me — وإذا policy.update_required ⇒ شاشة السياسة الجديدة
```

### `POST /customer/auth/otp/request`

| الحقل | النوع | مطلوب |
|---|---|---|
| `phone` | string | ✅ |

**صيغ الرقم المقبولة** (كلها بتنحفظ `+963947123456`): `+963947123456` · `963947123456` ·
`00963947123456` · `0947123456` · `947123456` — والمسافات والشرطات بتنتجاهل.

**رد `200`:**

```json
{ "message": "Verification code sent.", "expires_in": 300, "resend_after": 60 }
```

الرمز **ما بيرجع بالرد**. محلياً وبـDocker (`OTP_DRIVER=log`) بينكتب بالسجل.

**الأخطاء:** `422` رقم غير صالح · `429` قبل انتهاء مدة الانتظار (مع `retry_after`) ·
`503` فشل التوصيل (وما بينحفظ رمز، فبيقدر يعيد فوراً).

> **عدّاد «إعادة الإرسال»:** ابنِه على `retry_after` من الرد، مو على 60 ثابتة.
> مزوّد واتساب بيضاعف مدة الانتظار مع كل طلب متكرر لنفس الرقم خلال 6 ساعات
> (60 ← 120 ← 240 ثانية…)، والخادم بيرجّع المدة الفعلية.

---

### `POST /customer/auth/otp/verify`

| الحقل | النوع | مطلوب | ملاحظة |
|---|---|---|---|
| `phone` | string | ✅ | نفس الرقم |
| `code` | string | ✅ | 6 أرقام |
| `name` | string | ⚠️ | **للحساب الجديد فقط** |
| `birthdate` | string | ⚠️ | `YYYY-MM-DD` — **للحساب الجديد فقط**، والعمر 13 سنة فأكثر |
| `policy_version` | string | ⚠️ | **للحساب الجديد فقط** — من `GET /policy` |
| `device_name` | string | ❌ | اسم الجهاز |

**رد `200`:**

```json
{
  "data": {
    "id": 4,
    "name": "سارة",
    "phone": "+963900000099",
    "birthdate": "1995-03-10",
    "qr_secret": "RlcVzJlgyMF2ta1vnw9MR9ckrRjzDXwKa0zJbGu0",
    "qr_period_seconds": 60,
    "campaigns_muted": false,
    "registered_at": "2026-09-23T16:54:03+00:00",
    "policy": { "accepted_version": "1.2", "current_version": "1.2", "update_required": false }
  },
  "token": "1|XJoYklhBagldWLUnIQEom0goRXwejNFvlmaUiHsn84320fbb",
  "token_type": "Bearer",
  "is_new_customer": true,
  "stamps_waiting": [
    { "merchant": "كافيه الياسمين", "card": "بطاقة القهوة", "stamps": 2 }
  ]
}
```

| الحقل | المعنى |
|---|---|
| `qr_secret` | سرّ توليد رمز الـQR على الجهاز — بيتجدد كل `qr_period_seconds` ثانية ويشتغل دون اتصال |
| `is_new_customer` | `true` إذا انفتح الحساب أو انفعّل بهالطلب |
| `stamps_waiting` | طوابع كانت مسجّلة على رقمه قبل ما ينزّل التطبيق ⇒ اعرض شاشة «طوابعك وصلت» |

**الأخطاء:**

`422` — حساب جديد وبيانات ناقصة (**والرمز بيضل صالح**، أعد الإرسال بعد تعبئتها):

```json
{
  "message": "Your name is required to finish creating your account. (and 2 more errors)",
  "errors": {
    "name": ["Your name is required to finish creating your account."],
    "birthdate": ["Your date of birth is required to finish creating your account."],
    "policy_version": ["You must accept the privacy policy to create an account."]
  }
}
```

`422` — العمر تحت 13:

```json
{
  "message": "You must be at least 13 years old to use Wafa.",
  "errors": { "birthdate": ["You must be at least 13 years old to use Wafa."] }
}
```

`422` — رمز غلط أو منتهي أو مستهلك: `"The verification code is invalid or has expired."`
(نفس الرسالة للحالات الثلاثة، وبعد 5 محاولات خاطئة لازم رمز جديد).

`422` — إصدار سياسة قديم: `"This version of the privacy policy is no longer current."`

---

### `GET /customer/auth/me` 🔒 زبون

بيرجع كائن `Customer` داخل `data`، ومعه كتلة `policy`:

```json
"policy": { "accepted_version": "1.2", "current_version": "1.2", "update_required": false }
```

لما `update_required` تكون `true`، يعني صدرت نسخة جديدة من السياسة: اعرض شاشة التغييرات
وزر موافقة بيبعت `POST /customer/policy/accept`.

**الأخطاء:** `401` · `403` لتوكن مو تبع زبون.

### `POST /customer/policy/accept` 🔒 زبون

| الحقل | النوع | مطلوب |
|---|---|---|
| `policy_version` | string | ✅ لازم يكون الإصدار الحالي |

**رد `200`:** كائن `Customer` محدَّث (`policy.update_required: false`).
**`422`:** `"This version of the privacy policy is no longer current."`

### `POST /customer/devices` 🔒 زبون

سجّل رمز Firebase بعد الدخول، وكل ما Firebase يغيّره.

| الحقل | النوع | مطلوب |
|---|---|---|
| `token` | string | ✅ رمز FCM |
| `platform` | string | ✅ `ios` · `android` |

**رد `200`:** `{ "message": "Device registered." }`

إذا فات شخص تاني على نفس الجهاز، الرمز بينتقل لحسابه، والحساب القديم ما عاد بيوصله إشعارات هالجهاز.

### `POST /customer/auth/logout` 🔒 زبون

| الحقل | النوع | مطلوب |
|---|---|---|
| `device_token` | string | ❌ رمز FCM لهالجهاز — ابعته حتى تنقطع إشعاراته |

`{ "message": "Logged out." }`

### `POST /customer/auth/logout-all` 🔒 زبون

بيلغي كل التوكنات وبينسى كل الأجهزة. `{ "message": "Logged out on all devices." }`

### `DELETE /customer/account` 🔒 زبون

حذف الحساب من داخل التطبيق، وهو شرط من المتجرين. **قبل الاستدعاء** لازم التطبيق
يعرض شو بينحذف وشو بيبقى، وينبّه إنو الطوابع والهدايا غير المستلمة بتضيع نهائياً.

**رد `200`:** `{ "message": "Account deleted." }`

| شو بيصير | التفاصيل |
|---|---|
| بينحذف فوراً | الرقم، الاسم، تاريخ الميلاد، سرّ الـQR، رموز الأجهزة، التوكنات، الموافقات، التفضيلات |
| بيبقى بدون ربط | سجلات الطوابع والزيارات، لإحصاءات التجار فقط |
| بعدها | التوكن بيرجع `401`، ونفس الرقم فيه يسجّل من جديد كحساب جديد فاضي |

---

## 7. تطبيق التاجر

### التدفق

```
1. دخول بـClerk (Google أو بريد برمز)         ← على الفرونت
2. GET /merchant/auth/me  →  registration_step
   ├─ "business" ⇒ POST /registration/business   (بيانات النشاط)
   ├─ "package"  ⇒ POST /registration/package    (الباقة — تبدأ التجربة فوراً)
   ├─ "pin"      ⇒ POST /registration/pin        (رمز الحماية)
   └─ "done"     ⇒ ادخل على التطبيق
3. POST /merchant/devices  (رمز الإشعارات)
4. شاشة المسح مفتوحة دائماً — والتبويبات المحمية بتطلب الـPIN:
   POST /merchant/pin/verify  →  pin_token  →  هيدر X-Pin-Token على طلباتها
```

### `GET /merchant/auth/me` 🔒 Clerk

**رد `200` — مستخدم Clerk جديد:**

```json
{ "registered": false, "registration_step": "business", "data": null }
```

بعد التسجيل بيرجع `registered: true` و`registration_step: "done"` مع كائن `Merchant`.
كل استدعاء بيسجّل وقت آخر دخول، وبيحدّث `email` إذا غيّر التاجر بريده بـClerk.

---

### `POST /merchant/registration/business` 🔒 Clerk

`multipart/form-data` إذا في شعار، وإلا JSON.

| الحقل | النوع | مطلوب | ملاحظة |
|---|---|---|---|
| `business_name` | string | ✅ | **ما بيتعدّل لاحقاً إلا من الدعم** |
| `business_type_id` | integer | ✅ | من `lookups/business-types` |
| `governorate_id` | integer | ✅ | من `lookups/governorates` |
| `owner_name` | string | ✅ | |
| `phone` | string | ✅ | رقم سوري، ما يكون مستخدم لتاجر تاني |
| `address` | string | ❌ | يظهر بصفحة المحل بالدليل فقط |
| `logo` | file | ❌ | صورة، أقصى 2MB |

البريد **ما بينبعت بالـbody**: الخادم بياخده من توكن Clerk وبيحفظه مع بيانات النشاط.

**رد `201`:**

```json
{
  "registration_step": "package",
  "data": {
    "id": 2,
    "email": "owner@example.com",
    "business_name": "كافيه التجربة",
    "business_type": { "id": 1, "name": "كافيه" },
    "governorate": { "id": 1, "name": "دمشق" },
    "address": null,
    "owner_name": "سامر",
    "phone": "+963988111222",
    "logo_url": null,
    "status": null,
    "registration_step": "package",
    "has_pin": false,
    "subscription": null,
    "created_at": "2026-09-23T16:53:04+00:00"
  }
}
```

**الأخطاء:** `409` النشاط مسجّل من قبل لهاد الحساب · `422` رقم مستخدم أو نوع/محافظة غير موجودة.

---

### `POST /merchant/registration/package` 🔒 Clerk

| الحقل | النوع | مطلوب |
|---|---|---|
| `package_id` | integer | ✅ (من `lookups/packages`) |

**رد `200` — التجربة بدأت:**

```json
{
  "registration_step": "pin",
  "data": {
    "status": "TRIAL",
    "subscription": {
      "package": { "id": 1, "name": "الأساسية" },
      "type": "trial",
      "starts_at": "2026-09-23T16:53:10+00:00",
      "ends_at": "2026-10-07T16:53:10+00:00",
      "grace_ends_at": null
    }
  },
  "trial_granted": true
}
```

**رد `200` — التجربة مستهلكة سابقاً بنفس البريد:**

```json
{ "registration_step": "pin", "data": { "status": "EXPIRED" }, "trial_granted": false }
```

التجربة **مرة واحدة لكل تاجر**، محمية ببصمة مشفّرة للبريد تبقى حتى بعد حذف الحساب.
لما `trial_granted` يكون `false`، التاجر بيكمّل التسجيل وبعدين بيروح لشاشة الدفع.

**الأخطاء:** `409` الباقة مختارة من قبل · `403` لسا ما سجّل بيانات نشاطه · `422` باقة غير موجودة.

---

### `POST /merchant/registration/pin` 🔒 Clerk

| الحقل | النوع | مطلوب |
|---|---|---|
| `pin` | string | ✅ 4–6 أرقام |
| `pin_confirmation` | string | ✅ مطابق |

**رد `200`:** `registration_step: "done"` و`data.has_pin: true`.
**الأخطاء:** `409` قبل اختيار الباقة · `422` رمز غير مطابق أو بصيغة غلط.

---

### `POST /merchant/pin/verify` 🔒 تاجر

بيتحقق من الـPIN وبيرجّع **توكن فتح** للتبويبات المحمية (الإحصاءات، الزبائن، الحملات،
البطاقات، الاشتراك، الإعدادات).

| الحقل | النوع | مطلوب |
|---|---|---|
| `pin` | string | ✅ |

**رد `200`:**

```json
{
  "message": "PIN accepted.",
  "pin_token": "eyJpdiI6IklzZlJEamVKUklzdkUyTUNNNVJUQ1E9PSIsInZhbHVlIjoi…",
  "expires_at": "2026-09-24T06:24:34+00:00"
}
```

- ابعت `pin_token` بهيدر **`X-Pin-Token`** على كل طلبات التبويبات المحمية.
- خزّنه **بالذاكرة بس**، وامسحه لما ينسكّر التطبيق: الوثيقة بتقول «تُقفل عند إغلاق التطبيق».
  `expires_at` هو سقف من الخادم (12 ساعة افتراضياً، بتنضبط من اللوحة).
- تغيير الـPIN بيبطل كل التوكنات القديمة.

**`422`:** `{ "message": "This PIN is not correct.", "errors": { "pin": ["This PIN is not correct."] } }`

**`403`** إذا التسجيل ناقص:

```json
{
  "message": "Finish the registration steps first.",
  "code": "registration_incomplete",
  "registration_step": "pin"
}
```

**التبويب المحمي بدون توكن صالح** (غايب، منتهي، لتاجر تاني، أو من قبل تغيير الـPIN) بيرجع:

```json
{ "message": "Enter the PIN to open this section.", "code": "pin_required" }
```

---

### `PUT /merchant/pin` 🔒 تاجر + دخول حديث بـClerk

تغيير الـPIN من الإعدادات، **وكمان لـ«نسيت الرمز»**: ما بيطلب الـPIN القديم. بدالها
بيطلب إنو المالك يكون أكّد هويته بـClerk خلال آخر **10 دقائق**. الكاشير ما بيقدر يعمل هالشي،
لأن رمز Clerk بيروح على بريد المالك.

| الحقل | النوع | مطلوب |
|---|---|---|
| `pin` | string | ✅ 4–6 أرقام |
| `pin_confirmation` | string | ✅ مطابق |

**رد `200`:** `{ "message": "PIN updated." }`

**`403` — لازم تأكيد الهوية:** الرد بصيغة Clerk نفسها، فـ`useReverification()` من
`@clerk/clerk-expo` بيلقطه، بيطلب من المالك يأكّد هويته، وبيعيد الطلب لحاله:

```json
{
  "message": "Sign in again to change the PIN.",
  "code": "reverification_required",
  "clerk_error": {
    "type": "forbidden",
    "reason": "reverification-error",
    "metadata": { "reverification": { "level": "first_factor", "afterMinutes": 10 } }
  }
}
```

**`422`:** رمز غير مطابق أو بصيغة غلط.

---

### `POST /merchant/devices` 🔒 تاجر

نفس حقول وردّ `POST /customer/devices` (`token` و`platform`).

### `POST /merchant/auth/logout` 🔒 Clerk

| الحقل | النوع | مطلوب |
|---|---|---|
| `device_token` | string | ❌ رمز FCM لهالجهاز |

بينسى رمز إشعارات الجهاز. تسجيل الخروج من Clerk نفسه بيصير بالتطبيق (`signOut()`).
`{ "message": "Logged out." }`

---

### تحديث إجباري (`426`)

أي طلب من تطبيق التاجر بنسخة أقدم من الحد الأدنى:

```json
{
  "message": "A newer version of the app is required.",
  "code": "app_update_required",
  "minimum_version": "1.0.0",
  "download_url": ""
}
```

---

## 8. لوحة الإدارة

### الأدوار والصلاحيات

الصلاحية بتنفحص **بالخادم** على كل طلب. اللوحة بتستعمل `permissions` من `me` بس لتخبّي
الشاشات والأزرار.

| الصلاحية (`permission`) | Super Admin | Admin | مراجع المدفوعات | الدعم |
|---|---|---|---|---|
| `manage-admin-accounts` — حسابات الإدارة | ✓ | – | – | – |
| `manage-packages` — الباقات والأسعار | ✓ | – | – | – |
| `manage-settings` — سعر الصرف وبيانات الاستلام والإعدادات | ✓ | – | – | – |
| `grant-extensions` — التمديد اليدوي | ✓ | – | – | – |
| `view-audit-log` — سجل التدقيق | ✓ | – | – | – |
| `review-payments` — قبول أو رفض إثبات الدفع | – | – | ✓ | – |
| `view-financials` — سجل المدفوعات والتقارير المالية | ✓ | – | ✓ | – |
| `suspend-merchants` — إيقاف تاجر أو إعادة تفعيله | ✓ | ✓ | – | – |
| `execute-deletion-requests` — تنفيذ طلبات الحذف | ✓ | ✓ | – | – |
| `cancel-stamps` — إلغاء طابع | ✓ | ✓ | – | – |
| `manage-lookups` — الأيقونات وأنواع النشاط | ✓ | ✓ | – | – |
| `edit-business-identity` — تعديل اسم النشاط أو نوعه | ✓ | ✓ | – | ✓ |
| `edit-customer-birthdate` — تعديل تاريخ ميلاد زبون | ✓ | ✓ | – | ✓ |
| `view-merchants-and-customers` — عرض التجار والبطاقات والزبائن | ✓ | ✓ | – | ✓ |
| `reveal-customer-phone` — عرض رقم الزبون كاملاً | ✓ | ✓ | – | ✓ |

> **مبدأ من الوثيقة:** يلي بيقبل الدفعات ما بيعدّل أسعار ولا بيمنح تمديد، ولا حتى الـSuper Admin
> بيقبل دفعات. إذا صاحب المشروع بده يراجع الدفعات بنفسه، بياخد حساب تاني بدور مراجع مدفوعات.

العملية الممنوعة بترجع:

```json
{ "message": "Your role does not allow this action.", "code": "permission_denied" }
```

### إضافة حساب إدارة جديد

```
1. Super Admin: POST /admin/admin-users  بالاسم والبريد والدور  →  linked: false
2. صاحب البريد بيفوت على اللوحة بـClerk بنفس البريد (Google أو رمز على البريد)
3. أول طلب بيربط حساب Clerk بالحساب تلقائياً  →  linked: true
```

الربط بيعتمد على ادعاء `email` بتوكن Clerk (لازم يكون مفعّل من لوحة Clerk).
الحساب المربوط ما بيقدر حدا تاني ياخده ولو دخل بنفس البريد.

### `GET /admin/auth/me` 🔒 إدارة

```json
{
  "data": {
    "id": 3,
    "name": "Live Reviewer",
    "email": "live-reviewer@wafa.test",
    "role": "payments_reviewer",
    "is_active": true,
    "linked": true,
    "last_login_at": "2026-09-23T18:20:14+00:00"
  },
  "permissions": ["review-payments", "view-financials"]
}
```

مستخدم Clerk مو مربوط بحساب لوحة (أو حسابه معطّل) بياخد:

```json
{ "message": "This account does not have admin access." }
```

### `GET /admin/admin-users` 🔒 `manage-admin-accounts`

`{ "data": [ AdminUser, … ] }` — الفعّالة أولاً، مرتبة بالاسم.

### `POST /admin/admin-users` 🔒 `manage-admin-accounts`

| الحقل | النوع | مطلوب | ملاحظة |
|---|---|---|---|
| `name` | string | ✅ | |
| `email` | string | ✅ | فريد (بدون فرق بالأحرف الكبيرة والصغيرة) |
| `role` | string | ✅ | `super_admin` · `admin` · `payments_reviewer` · `support` |

**رد `201`:**

```json
{
  "data": {
    "id": 3,
    "name": "Live Reviewer",
    "email": "live-reviewer@wafa.test",
    "role": "payments_reviewer",
    "is_active": true,
    "linked": false,
    "last_login_at": null
  }
}
```

### `PATCH /admin/admin-users/{id}` 🔒 `manage-admin-accounts`

أي حقل من: `name` · `role` · `is_active` · `email` (البريد **بس قبل الربط**).
**رد `200`:** كائن `AdminUser` محدَّث.

### `DELETE /admin/admin-users/{id}` 🔒 `manage-admin-accounts`

بيعطّل الحساب (`is_active: false`) وما بيحذفه، حتى يضل سجل التدقيق يدل على مين عمل شو.
بيرجع يتفعّل بـ`PATCH` مع `is_active: true`. **رد `200`:** كائن `AdminUser`.

**حمايات (`422`):** ما حدا بيغيّر دوره أو بيعطّل حسابه بنفسه:

```json
{
  "message": "You cannot change the role of your own account or deactivate it.",
  "errors": { "admin_user": ["You cannot change the role of your own account or deactivate it."] }
}
```

كل إنشاء وتعديل وتعطيل وربط بينكتب بسجل التدقيق، مع القيم قبل وبعد.

---

## 9. الكائنات

### Customer

| الحقل | النوع | ملاحظة |
|---|---|---|
| `id` | integer | |
| `name` | string \| null | |
| `phone` | string | `+9639XXXXXXXX` |
| `birthdate` | string \| null | `YYYY-MM-DD` — ما بيتعدّل من التطبيق |
| `qr_secret` | string \| null | سرّ رمز الـQR — لتطبيق الزبون فقط |
| `qr_period_seconds` | integer | مدة تجدد الرمز |
| `campaigns_muted` | boolean | إيقاف عروض كل التجار |
| `registered_at` | string \| null | فاضي = زبون معلّق |
| `policy` | object | `{ accepted_version, current_version, update_required }` |

### Merchant

| الحقل | النوع | ملاحظة |
|---|---|---|
| `id` | integer | |
| `email` | string \| null | بريد الدخول من Clerk |
| `business_name` · `owner_name` · `phone` | string | |
| `business_type` · `governorate` | object | `{ id, name }` |
| `address` | string \| null | |
| `logo_url` | string \| null | |
| `status` | string \| null | `TRIAL` · `ACTIVE` · `GRACE` · `EXPIRED` · `SUSPENDED` · `PENDING_DELETION` · `DELETED` — و`null` قبل اختيار الباقة |
| `registration_step` | string | `business` · `package` · `pin` · `done` |
| `has_pin` | boolean | |
| `subscription` | object \| null | الباقة ونوع الفترة وتواريخها |

### AdminUser

`id` · `name` · `email` · `role` · `is_active` · `linked` (دخل صاحبه مرة وحدة على الأقل) · `last_login_at`

### Package

`id` · `name` · `cards_limit` · `weekly_campaigns_limit` · `prices[{duration_months, price_usd}]`

---

## 10. مثال ربط (axios)

**تطبيق الزبون:**

```ts
const api = axios.create({ baseURL: 'http://127.0.0.1:8000/api/v1', headers: { Accept: 'application/json' } });

api.interceptors.request.use(async (config) => {
  const token = await getStoredToken(); // expo-secure-store
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

// التسجيل
const { data: policy } = await api.get('/policy');
await api.post('/customer/auth/otp/request', { phone });

try {
  const { data } = await api.post('/customer/auth/otp/verify', {
    phone, code, name, birthdate, policy_version: policy.privacy_policy_version,
  });
  await saveToken(data.token);
  await api.post('/customer/devices', { token: await getFcmToken(), platform: Platform.OS });
  if (data.stamps_waiting.length) showStampsArrivedScreen(data.stamps_waiting);
} catch (error) {
  const errors = error.response?.data?.errors;
  if (errors?.birthdate) showAgeError(errors.birthdate[0]);
}

// الخروج
await api.post('/customer/auth/logout', { device_token: await getFcmToken() });
await clearToken();
```

**تطبيق التاجر (Clerk + نسخة التطبيق + الـPIN):**

```ts
let pinToken: string | null = null;   // بالذاكرة فقط — بيروح لما ينسكّر التطبيق

const api = axios.create({ baseURL: 'http://127.0.0.1:8000/api/v1', headers: { Accept: 'application/json' } });

api.interceptors.request.use(async (config) => {
  const token = await getToken();            // @clerk/clerk-expo — لا تخزّنه
  if (token) config.headers.Authorization = `Bearer ${token}`;
  config.headers['X-App-Version'] = Constants.expoConfig.version;
  if (pinToken) config.headers['X-Pin-Token'] = pinToken;
  return config;
});

api.interceptors.response.use(undefined, (error) => {
  const data = error.response?.data;
  if (data?.code === 'app_update_required') showForcedUpdate(data.download_url);
  if (data?.code === 'merchant_not_registered') openRegistration();
  if (data?.code === 'registration_incomplete') openRegistration(data.registration_step);
  if (data?.code === 'pin_required') { pinToken = null; askForPin(); }
  return Promise.reject(error);
});

// فتح التبويبات المحمية
const { data } = await api.post('/merchant/pin/verify', { pin });
pinToken = data.pin_token;

// تغيير الـPIN — useReverification بيعيد الطلب بعد ما المالك يأكّد هويته
const changePin = useReverification((pin: string) =>
  api.put('/merchant/pin', { pin, pin_confirmation: pin })
    .then((r) => r.data)
    // مرّر للـhook بس ردّ طلب التأكيد؛ باقي الأخطاء (422 مثلاً) بتضل أخطاء
    .catch((e) => (e.response?.data?.clerk_error ? e.response.data : Promise.reject(e))),
);
```

**أين تخزّن التوكنات:** توكن الزبون بـ`expo-secure-store`؛ توكن Clerk ما بيتخزّن —
`getToken()` قبل كل طلب؛ و`pin_token` بالذاكرة بس.

---

## 11. خريطة الوصول

الوظائف الجاية كل وحدة إلها حارس جاهز من هلق. لما تنبنى، بتنضاف لهالملف بنفس الشكل.

| الوظيفة | الطرف | الحارس |
|---|---|---|
| المسح وإضافة طابع وتسليم الهدية | تاجر | دخول Clerk + تسجيل مكتمل — **بدون PIN**. وحالة الاشتراك بتقرر: الطوابع بـ`TRIAL`/`ACTIVE`/`GRACE` بس، والتسليم بكل حالة غير نهائية |
| الإحصاءات، الزبائن، الحملات، البطاقات، الاشتراك، الإعدادات | تاجر | `X-Pin-Token` |
| بطاقاتي، المحلات، رمزي، الإشعارات، حسابي | زبون | توكن الزبون |
| طابور مراجعة الدفعات | إدارة | `review-payments` |
| التجار والزبائن (عرض) | إدارة | `view-merchants-and-customers` — وكشف الرقم كامل `reveal-customer-phone` مع سجل تدقيق |
| إيقاف تاجر · طلبات الحذف · إلغاء طابع | إدارة | `suspend-merchants` · `execute-deletion-requests` · `cancel-stamps` |
| الباقات والأسعار · الإعدادات · التمديد · سجل التدقيق | إدارة | `manage-packages` · `manage-settings` · `grant-extensions` · `view-audit-log` |

---

## 12. للخادم فقط

### `POST /api/v1/webhooks/clerk`

**التطبيقات ما بتستعمله.** Clerk بيبعته لما مستخدم يغيّر بريده أو ينحذف، والطلب موقّع بتوقيع
Svix (`svix-id`، `svix-timestamp`، `svix-signature`).

| الحدث | الأثر |
|---|---|
| `user.updated` | البريد الأساسي الجديد بيتحدّث للتاجر ولحساب الإدارة المربوط |
| `user.deleted` | حساب الإدارة بينفك ربطه، فإذا انعمل المستخدم من جديد بنفس البريد بيرجع ينربط بأول دخول. **بيانات التاجر ما بتنلمس** (هدايا الزبائن حق إلهم)، بس بينكتب سطر بسجل التدقيق |

الرد `200` لأي حدث موقّع، و`400` للتوقيع الغلط أو إذا كان أقدم من 5 دقائق.
