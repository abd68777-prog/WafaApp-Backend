# توثيق الـAPI — Loyalty Cards Backend

مرجع لمهندس الـFrontend (تطبيق الزبون، تطبيق التاجر، لوحة الأدمن).
كل الأمثلة بهاد الملف ردود حقيقية من السيرفر.

> **الحالة الحالية:** متاح **دخول الزبون بالـOTP** و**دخول التاجر والأدمن عبر Clerk**.
> endpoints البطاقات والطوابع رح تنضاف لهاد الملف مع كل مرحلة.

---

## 1. معلومات عامة

| البند | القيمة |
|---|---|
| Base URL (محلي) | `http://127.0.0.1:8000` |
| بادئة الـAPI | `/api/v1` |
| صيغة الطلب والرد | JSON فقط |
| صيغة التواريخ | ISO 8601 بتوقيت UTC، مثال: `2026-09-17T15:28:22+00:00` |

### نوعين من المصادقة

| مين | كيف بيسجّل دخول | شو بيبعت بـ`Authorization` |
|---|---|---|
| **الزبون** | رقم موبايل + رمز OTP على واتساب | توكن السيرفر يلي بيرجع من `otp/verify` |
| **التاجر والأدمن** | Clerk (Google وغيره) | توكن جلسة Clerk من `getToken()` — بكل طلب |

### الـHeaders

| Header | متى | القيمة |
|---|---|---|
| `Accept` | كل طلب (مُستحسن) | `application/json` |
| `Content-Type` | أي طلب فيه body (`POST`) | `application/json` |
| `Authorization` | الـendpoints المحمية (🔒) | `Bearer <token>` |

- السيرفر بيجبر الرد يكون JSON على كل مسارات `/api/*` حتى لو نسيت `Accept`.
- **كل توكن محصور بمساراته:** توكن الزبون بيشتغل بس على `/customer/*`، وتوكن Clerk
  على `/merchant/*` و`/admin/*`.

### CORS

الطلبات من المتصفح مسموحة بس من الـorigins المحددة بـ`.env` بالباك اند:

```env
FRONTEND_URLS=http://localhost:3000,http://localhost:5173
```

تطبيقات الموبايل ما بتتأثر بـCORS.

---

## 2. شكل الأخطاء

كل الأخطاء بترجع JSON فيه `message` على الأقل.

| الكود | المعنى | شكل الرد |
|---|---|---|
| `401` | ما في توكن، أو توكن غلط/منتهي، أو انعمله logout | `{"message": "Unauthenticated."}` |
| `403` | التوكن صالح بس صاحبه ما عنده صلاحية على هاد المسار | `{"message": "…"}` — وأحياناً مع `code` (شوف تحت) |
| `404` | الـendpoint مو موجود | `{"message": "Endpoint not found."}` |
| `405` | الـmethod غلط | `{"message": "The GET method is not supported for route …"}` |
| `409` | العملية متعارضة مع حالة موجودة (مثلاً تسجيل نشاط مرتين) | `{"message": "…"}` |
| `422` | خطأ بالبيانات المُرسلة | `message` + `errors` (تفاصيل تحت) |
| `429` | تجاوزت حد الطلبات أو مدة الانتظار | `{"message": "…", "retry_after": 57}` + header `Retry-After` |
| `503` | ما قدرنا نوصّل رمز التحقق (مشكلة عند مزوّد الرسائل) | `{"message": "Could not send the verification code right now. Please try again shortly."}` |
| `500` | خطأ بالسيرفر | `{"message": "Server Error"}` |

> بالبيئة المحلية (`APP_DEBUG=true`) بعض الردود بيطلع فيها كمان `exception` و`trace`.
> بالإنتاج بيرجع `message` بس، فلا تعتمد على الحقول الإضافية.

### رموز `403` للتاجر

مسارات التاجر الجاية (البطاقات، الطوابع…) بترجع `403` مع حقل `code` بتقدر تبني عليه
التوجيه بالتطبيق:

| `code` | المعنى | شو يعمل التطبيق |
|---|---|---|
| `merchant_not_registered` | مسجّل دخول بـClerk بس لسا ما عبّى بيانات نشاطه | افتح شاشة تسجيل النشاط |
| `merchant_suspended` | الحساب موقوف | شاشة «تواصل مع الدعم» |
| `merchant_rejected` | الحساب ما انقبل | شاشة «تواصل مع الدعم» |

### أخطاء التحقق `422`

```json
{
  "message": "The owner name field is required. (and 2 more errors)",
  "errors": {
    "owner_name": ["The owner name field is required."],
    "phone": ["The phone field is required."],
    "package_code": ["The package code field is required."]
  }
}
```

- `errors` كائن: المفتاح اسم الحقل، والقيمة **مصفوفة** رسائل. اعرض أول رسالة تحت كل حقل.
- `message` ملخص عام، مفيد كرسالة toast.

---

## 3. حدود الطلبات (Rate Limits)

| الحد | على شو | العدد | محسوب حسب |
|---|---|---|---|
| `api` | كل مسارات `/api/*` | 60 طلب بالدقيقة | المستخدم، أو الـIP |
| `otp` | `POST /customer/auth/otp/request` | 5 بالساعة لكل رقم · 20 بالساعة لكل IP | الرقم بعد التوحيد، والـIP |
| `otp-verify` | `POST /customer/auth/otp/verify` | 10 بالدقيقة لكل رقم · 30 بالدقيقة لكل IP | الرقم بعد التوحيد، والـIP |

بالإضافة لهيك، في **مدة انتظار 60 ثانية بين رمز ورمز** لنفس الرقم، وبترجع `429` مع
`retry_after`.

Headers الرد: `X-RateLimit-Limit` · `X-RateLimit-Remaining` · `Retry-After` (مع `429`).

---

## 4. الـEndpoints

### فهرس

| Method | Path | 🔒 | الوصف |
|---|---|---|---|
| `GET` | `/up` | – | فحص صحة السيرفر |
| `GET` | `/api/v1/ping` | – | فحص الاتصال بالـAPI |
| `POST` | `/api/v1/customer/auth/otp/request` | – | إرسال رمز تحقق لرقم الزبون |
| `POST` | `/api/v1/customer/auth/otp/verify` | – | التحقق من الرمز وإصدار توكن |
| `GET` | `/api/v1/customer/auth/me` | 🔒 زبون | بيانات الزبون الحالي |
| `POST` | `/api/v1/customer/auth/logout` | 🔒 زبون | خروج من الجهاز الحالي |
| `POST` | `/api/v1/customer/auth/logout-all` | 🔒 زبون | خروج من كل الأجهزة |
| `GET` | `/api/v1/merchant/auth/me` | 🔒 Clerk | حالة التاجر: مسجّل نشاطه أو لأ |
| `POST` | `/api/v1/merchant/auth/register` | 🔒 Clerk | تسجيل النشاط التجاري وبدء التجربة |
| `GET` | `/api/v1/admin/auth/me` | 🔒 Clerk + أدمن | بيانات الأدمن |

---

### `GET /up`

فحص إنو السيرفر شغال. **ما بيرجع JSON** — اعتمد على كود الحالة.

### `GET /api/v1/ping`

**رد `200`:** `{ "message": "pong", "version": "v1", "time": "2026-09-17T14:06:33+00:00" }`

---

## الزبون — دخول بالـOTP

### التدفق

```
1. المستخدم بيدخل رقمه            →  POST /customer/auth/otp/request
2. بيوصله رمز 6 أرقام على واتساب  →  صالح 5 دقائق
3. بيدخل الرمز                    →  POST /customer/auth/otp/verify
   ├─ إذا الحساب جديد: السيرفر بيرجع 422 على حقل name
   │  ⇒ اعرض شاشة الاسم، وأعد الإرسال بنفس الرمز (لسا صالح)
   └─ إذا تمام: بيرجع token + بيانات الزبون
4. احفظ الـtoken وابعته بكل طلب لاحق
```

- طلب الرمز بيرجع **نفس الرد** سواء الرقم مسجّل أو لأ، حتى ما ينكشف مين عنده حساب.
- الزبون يلي ضافه التاجر برقمه قبل ما ينزّل التطبيق بينحسب **جديد** كمان، بس بعد
  التحقق بيلاقي كل طوابعه القديمة بمحفظته — نفس السجل.
- توكن الزبون **ما إله تاريخ انتهاء**؛ بيضل صالح لحد `logout`.

---

### `POST /api/v1/customer/auth/otp/request`

**Headers:** `Accept: application/json` · `Content-Type: application/json`

| الحقل | النوع | مطلوب | الوصف |
|---|---|---|---|
| `phone` | string | ✅ | رقم موبايل سوري |

**صيغ الرقم المقبولة** (كلها بتنحفظ `+963947123456`):
`+963947123456` · `963947123456` · `00963947123456` · `0947123456` · `947123456`
— والمسافات والشرطات بتنتجاهل.

**رد `200`:**

```json
{ "message": "Verification code sent.", "expires_in": 300, "resend_after": 60 }
```

| الحقل | المعنى |
|---|---|
| `expires_in` | مدة صلاحية الرمز بالثواني |
| `resend_after` | كم ثانية قبل ما يقدر يطلب رمز جديد — استخدمها كعدّاد لزر «إعادة الإرسال» |

> الرمز **ما بيرجع بالرد أبداً**. بالتطوير المحلي (`OTP_DRIVER=log`) بينكتب
> بـ`storage/logs/laravel.log` (وبـDocker: `docker compose logs app`).

**الأخطاء:**
- `422` — رقم غير صالح: `"The phone must be a valid Syrian mobile number."` على حقل `phone`.
- `429` — طلب رمز قبل مدة الانتظار: `{ "message": "Please wait before requesting another code.", "retry_after": 57 }` مع header `Retry-After`.
- `503` — ما قدرنا نوصّل الرسالة. **ما بينحفظ أي رمز**، فبيقدر يعيد المحاولة فوراً.

---

### `POST /api/v1/customer/auth/otp/verify`

**Headers:** `Accept: application/json` · `Content-Type: application/json`

| الحقل | النوع | مطلوب | القواعد | الوصف |
|---|---|---|---|---|
| `phone` | string | ✅ | نفس صيغ الرقم فوق | نفس الرقم يلي طلبت له الرمز |
| `code` | string | ✅ | 6 أرقام | الرمز يلي وصل على واتساب |
| `name` | string | ⚠️ | أقصى 255 حرف | **إلزامي للحساب الجديد فقط** |
| `birthdate` | string \| null | ❌ | `YYYY-MM-DD` قبل اليوم | بيفعّل هدية عيد الميلاد |
| `device_name` | string | ❌ | أقصى 255 حرف | اسم الجهاز |

**رد `200`:**

```json
{
  "data": {
    "id": 7,
    "name": "سارة",
    "phone": "+963947123456",
    "birthdate": "1998-05-20",
    "qr_token": "jiTy7j7uVJSinXDF7OgKtYaJGsQ09TGdQrWmBn0R",
    "status": "active",
    "created_at": "2026-09-17T14:06:33+00:00"
  },
  "token": "3|uySpz7jSK9A75mSaJlxojXYLi1HOgphHXMasVC6e3ba055b2",
  "token_type": "Bearer",
  "is_new_customer": true
}
```

| الحقل | المعنى |
|---|---|
| `qr_token` | رمز الـQR الدائم للزبون — بيعرضه بشاشة «QR الخاص فيني» وبيمسحه التاجر |
| `is_new_customer` | `true` إذا الحساب انفتح أو انفعّل بهالطلب |

**الأخطاء:**
- `422` على `name` — `"Your name is required to finish creating your account."`.
  **الرمز بيضل صالح**: اعرض شاشة الاسم وأعد نفس الطلب مع `name`.
- `422` على `code` — `"The verification code is invalid or has expired."` (نفس الرسالة
  للرمز الغلط والمنتهي والمستهلك). بعد **5 محاولات خاطئة** لازم رمز جديد.
- `403` — `"This account has been suspended."`.
- `429` — أكتر من 10 محاولات تحقق بالدقيقة لنفس الرقم.

---

### `GET /api/v1/customer/auth/me` 🔒 زبون

**رد `200`:** نفس كائن `Customer` داخل `data`.
**الأخطاء:** `401` بدون توكن صالح · `403` `"This token is not allowed to access this resource."` لتوكن مو تبع زبون.

### `POST /api/v1/customer/auth/logout` 🔒 زبون

بيلغي التوكن الحالي بس. **رد `200`:** `{ "message": "Logged out." }`

### `POST /api/v1/customer/auth/logout-all` 🔒 زبون

بيلغي كل توكنات الزبون. **رد `200`:** `{ "message": "Logged out on all devices." }`

---

## التاجر والأدمن — دخول عبر Clerk

### كيف بيشتغل

- تسجيل الدخول والخروج **كلو بيصير بـClerk على الفرونت** — ما في endpoints دخول أو خروج بالباك اند.
- بكل طلب لمسارات `/merchant/*` أو `/admin/*`، ابعت توكن جلسة Clerk:
  `Authorization: Bearer <token>`.
- توكن Clerk **عمره قصير (حوالي دقيقة)**. لا تخزّنه — اطلبه من SDK Clerk قبل كل طلب،
  والـSDK بيرجّع نسخة محفوظة أو بيجدده لحاله.
- **لوحة الأدمن (متصفح):** لازم الـorigin تبعها يكون ضمن `CLERK_AUTHORIZED_PARTIES`
  بالباك اند، وإلا بيرجع `401`. **تطبيق التاجر (Expo):** ما بيحتاج شي، توكنات
  الموبايل ما فيها origin.

**Next.js (لوحة الأدمن):**

```ts
import { useAuth } from '@clerk/nextjs';

const { getToken } = useAuth();
const token = await getToken();

await fetch('http://127.0.0.1:8000/api/v1/admin/auth/me', {
  headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
});
```

**Expo (تطبيق التاجر):**

```ts
import { useAuth } from '@clerk/clerk-expo';

const { getToken } = useAuth();
const token = await getToken();
```

### تدفق التاجر بعد الدخول

```
1. التاجر بيسجّل دخول بـClerk       (على الفرونت)
2. GET /merchant/auth/me
   ├─ registered: false  ⇒ اعرض فورم تسجيل النشاط  ⇒  POST /merchant/auth/register
   └─ registered: true   ⇒ ادخل على التطبيق
```

---

### `GET /api/v1/merchant/auth/me` 🔒 Clerk

بيرجع **دايماً `200`** لأي مستخدم Clerk صالح، مع `registered` بيقول إذا عبّى بيانات نشاطه.

**Headers:** `Accept: application/json` · `Authorization: Bearer <Clerk token>`

**رد `200` — لسا ما سجّل نشاطه:**

```json
{ "registered": false, "data": null }
```

**رد `200` — مسجّل:**

```json
{
  "registered": true,
  "data": {
    "id": 2,
    "business_name": "Cafe Yasmin",
    "owner_name": "Ahmad",
    "phone": "+963933111222",
    "email": "cafe@example.com",
    "city": "Damascus",
    "address": null,
    "status": "trial",
    "package": { "code": "standard", "name": "المتوسطة", "max_cards": 2 },
    "trial_ends_at": "2026-10-01T15:28:22+00:00",
    "subscription_ends_at": null,
    "birthday_gift_enabled": false,
    "created_at": "2026-09-17T15:28:22+00:00"
  }
}
```

**الأخطاء:** `401` — ما في توكن Clerk، أو منتهي، أو من origin مو مسموح.

---

### `POST /api/v1/merchant/auth/register` 🔒 Clerk

بينشئ النشاط التجاري لمستخدم Clerk الحالي، وبيبلش **الفترة التجريبية فوراً**.

**Headers:** `Accept: application/json` · `Content-Type: application/json` · `Authorization: Bearer <Clerk token>`

**Body parameters:**

| الحقل | النوع | مطلوب | القواعد |
|---|---|---|---|
| `business_name` | string | ✅ | أقصى 255 حرف |
| `owner_name` | string | ✅ | أقصى 255 حرف |
| `phone` | string | ✅ | رقم موبايل سوري (نفس صيغ الزبون)، ما يكون مستخدم لتاجر تاني |
| `package_code` | string | ✅ | `basic` · `standard` · `premium` |
| `email` | string \| null | ❌ | إيميل صالح، ما يكون مستخدم لتاجر تاني |
| `city` | string \| null | ❌ | أقصى 100 حرف |
| `address` | string \| null | ❌ | أقصى 255 حرف |

**مثال طلب:**

```json
{
  "business_name": "Cafe Yasmin",
  "owner_name": "Ahmad",
  "phone": "0933 111 222",
  "email": "cafe@example.com",
  "city": "Damascus",
  "package_code": "standard"
}
```

**رد `201`:** نفس كائن `Merchant` داخل `data` (متل مثال `me` فوق) مع `status: "trial"`
و`trial_ends_at` بعد 14 يوم.

| الباقة `package_code` | عدد البطاقات |
|---|---|
| `basic` — الأساسية | 1 |
| `standard` — المتوسطة | 2 |
| `premium` — الشاملة | 5 |

**الأخطاء:**

`409` — هاد الحساب مسجّل نشاطه من قبل:

```json
{ "message": "This account already has a registered business." }
```

`422` — حقول ناقصة (مثال حقيقي بالقسم 2)، أو:
- `phone`: `"The phone has already been taken."` — الرقم مستخدم لتاجر تاني.
- `package_code`: `"The selected package code is invalid."` — باقة مو موجودة.

`401` — ما في توكن Clerk صالح.

---

### `GET /api/v1/admin/auth/me` 🔒 Clerk + أدمن

**Headers:** `Accept: application/json` · `Authorization: Bearer <Clerk token>`

الأدمن هو مستخدم Clerk **مربوط بجدول `admins`** بالباك اند — مو أي حساب Clerk.

**رد `200`:**

```json
{
  "data": {
    "id": 1,
    "name": "Platform Admin",
    "email": "owner@example.com",
    "last_login_at": null,
    "created_at": "2026-09-17T15:26:26+00:00"
  }
}
```

**الأخطاء:**

`403` — مستخدم Clerk صالح بس مو أدمن (مثلاً تاجر فتح لوحة الأدمن):

```json
{ "message": "This account does not have admin access." }
```

`401` — ما في توكن Clerk صالح، أو الـorigin مو ضمن `CLERK_AUTHORIZED_PARTIES`.

---

## 5. الكائنات (Objects)

### Customer

| الحقل | النوع | ملاحظة |
|---|---|---|
| `id` | integer | |
| `name` | string \| null | |
| `phone` | string | دايماً `+9639XXXXXXXX` |
| `birthdate` | string \| null | `YYYY-MM-DD` |
| `qr_token` | string \| null | رمز الـQR الدائم — لتطبيق الزبون فقط |
| `status` | string | `pending` · `active` · `suspended` |
| `created_at` | string | ISO 8601 |

### Merchant

| الحقل | النوع | ملاحظة |
|---|---|---|
| `id` | integer | |
| `business_name` | string | |
| `owner_name` | string | |
| `phone` | string | دايماً `+9639XXXXXXXX` |
| `email` | string \| null | |
| `city` | string \| null | |
| `address` | string \| null | |
| `status` | string | `pending_review` · `trial` · `active` · `suspended` · `rejected` |
| `package` | object | `{ code, name, max_cards }` |
| `trial_ends_at` | string \| null | نهاية الفترة التجريبية |
| `subscription_ends_at` | string \| null | نهاية الاشتراك المدفوع |
| `birthday_gift_enabled` | boolean | |
| `created_at` | string | ISO 8601 |

### Admin

| الحقل | النوع | ملاحظة |
|---|---|---|
| `id` | integer | |
| `name` | string | |
| `email` | string | |
| `last_login_at` | string \| null | |
| `created_at` | string \| null | |

---

## 6. مثال ربط (axios)

**تطبيق الزبون — توكن السيرفر:**

```ts
import axios from 'axios';

export const api = axios.create({
  baseURL: 'http://127.0.0.1:8000/api/v1',
  headers: { Accept: 'application/json' },
});

api.interceptors.request.use(async (config) => {
  const token = await getStoredToken(); // expo-secure-store
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// دخول الزبون
await api.post('/customer/auth/otp/request', { phone });

try {
  const { data } = await api.post('/customer/auth/otp/verify', { phone, code });
  await saveToken(data.token);
} catch (error) {
  if (error.response?.data?.errors?.name) {
    showNameStep(); // حساب جديد: أعد نفس الطلب مع name — الرمز لسا صالح
  }
}
```

**تطبيق التاجر ولوحة الأدمن — توكن Clerk بكل طلب:**

```ts
export function createClerkApi(getToken: () => Promise<string | null>) {
  const api = axios.create({
    baseURL: 'http://127.0.0.1:8000/api/v1',
    headers: { Accept: 'application/json' },
  });

  api.interceptors.request.use(async (config) => {
    const token = await getToken(); // لا تخزّنه — عمره قصير
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  });

  api.interceptors.response.use(
    (response) => response,
    (error) => {
      if (error.response?.data?.code === 'merchant_not_registered') {
        openBusinessRegistration();
      }
      return Promise.reject(error);
    },
  );

  return api;
}

// بعد الدخول بـClerk
const { data } = await api.get('/merchant/auth/me');
if (!data.registered) {
  openBusinessRegistration();
}
```

**أين تخزّن التوكن:**
- **توكن الزبون:** `expo-secure-store`.
- **توكن Clerk:** ما بيتخزّن — `getToken()` قبل كل طلب.
