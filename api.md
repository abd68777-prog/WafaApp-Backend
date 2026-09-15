# توثيق الـAPI — Loyalty Cards Backend

مرجع لمهندس الـFrontend (لوحة الأدمن Next.js وتطبيقي React Native).
كل الأمثلة بهاد الملف ردود حقيقية من السيرفر.

> **الحالة الحالية:** متاح حالياً مصادقة الأدمن فقط. endpoints الزبون (OTP) والتاجر
> والبطاقات والطوابع رح تنضاف لهاد الملف مع مرحلة الـAPI الجاية.

---

## 1. معلومات عامة

| البند | القيمة |
|---|---|
| Base URL (محلي) | `http://127.0.0.1:8000` |
| بادئة الـAPI | `/api/v1` |
| صيغة الطلب والرد | JSON فقط |
| صيغة التواريخ | ISO 8601 بتوقيت UTC، مثال: `2026-09-15T11:59:32+00:00` |
| المصادقة | Bearer Token عبر Laravel Sanctum |

### الـHeaders

| Header | متى | القيمة |
|---|---|---|
| `Accept` | كل طلب (مُستحسن) | `application/json` |
| `Content-Type` | أي طلب فيه body (`POST`) | `application/json` |
| `Authorization` | الـendpoints المحمية (🔒) | `Bearer <token>` |

- السيرفر بيجبر الرد يكون JSON على كل مسارات `/api/*` حتى لو نسيت `Accept`، بس
  الأفضل ترسله دايماً.
- الـtoken شكله `1|r0cxYoNB50xUtD2x...` — تعامل معه كنص كامل بدون تعديل أو
  تقسيم، وابعته كامل بعد كلمة `Bearer `.
- الـtoken **ما إله تاريخ انتهاء** حالياً؛ بيضل صالح لحد ما ينعمل `logout`.

### CORS

الطلبات من المتصفح مسموحة بس من الـorigins المحددة بـ`.env` بالباك اند:

```env
FRONTEND_URLS=http://localhost:3000,http://localhost:5173
```

إذا بدك تشغّل الفرونت على بورت تاني، لازم ينضاف لهاد المتغير. تطبيقات الموبايل
ما بتتأثر بـCORS.

---

## 2. شكل الأخطاء

كل الأخطاء بترجع JSON فيه `message` على الأقل.

| الكود | المعنى | شكل الرد |
|---|---|---|
| `401` | ما في token، أو token غلط، أو انعمله logout | `{"message": "Unauthenticated."}` |
| `404` | الـendpoint مو موجود | `{"message": "Endpoint not found."}` |
| `405` | الـmethod غلط (مثلاً `GET` بدل `POST`) | `{"message": "The GET method is not supported for route ... Supported methods: POST."}` |
| `422` | خطأ بالبيانات المُرسلة | `message` + `errors` (تفاصيل تحت) |
| `429` | تجاوزت حد الطلبات | `{"message": "Too Many Attempts."}` + header `Retry-After` |
| `500` | خطأ بالسيرفر | `{"message": "Server Error"}` |

> بالبيئة المحلية (`APP_DEBUG=true`) ردود `405` و`429` و`500` بيطلع فيها كمان
> `exception` و`file` و`trace`. بالإنتاج بيرجع `message` بس، فلا تعتمد على
> الحقول الإضافية.

### أخطاء التحقق `422`

```json
{
  "message": "The email field is required. (and 1 more error)",
  "errors": {
    "email": ["The email field is required."],
    "password": ["The password field is required."]
  }
}
```

- `errors` كائن: المفتاح اسم الحقل، والقيمة **مصفوفة** رسائل. اعرض أول رسالة
  تحت كل حقل.
- `message` ملخص عام، مفيد كرسالة toast.

---

## 3. حدود الطلبات (Rate Limits)

| الحد | على شو | العدد | محسوب حسب |
|---|---|---|---|
| `api` | كل مسارات `/api/*` | 60 طلب بالدقيقة | المستخدم المسجّل، أو الـIP |
| `auth` | `POST /api/v1/admin/auth/login` | 5 محاولات بالدقيقة | الإيميل + الـIP |

Headers الرد يلي بتقدر تقرأها:

| Header | المعنى |
|---|---|
| `X-RateLimit-Limit` | الحد الأقصى |
| `X-RateLimit-Remaining` | المتبقي |
| `Retry-After` | (مع `429` بس) عدد الثواني قبل ما تقدر تعيد المحاولة |

---

## 4. الـEndpoints

### فهرس

| Method | Path | 🔒 | الوصف |
|---|---|---|---|
| `GET` | `/up` | – | فحص صحة السيرفر |
| `GET` | `/api/v1/ping` | – | فحص الاتصال بالـAPI |
| `POST` | `/api/v1/admin/auth/login` | – | تسجيل دخول الأدمن |
| `GET` | `/api/v1/admin/auth/me` | 🔒 | بيانات الأدمن الحالي |
| `POST` | `/api/v1/admin/auth/logout` | 🔒 | تسجيل خروج من الجهاز الحالي |
| `POST` | `/api/v1/admin/auth/logout-all` | 🔒 | تسجيل خروج من كل الأجهزة |

---

### `GET /up`

فحص إنو السيرفر شغال (للمراقبة والـdeploy). **ما بيرجع JSON.**

- **Headers:** لا شي
- **Body:** لا شي
- **الرد:** `200` مع صفحة HTML — اعتمد على كود الحالة بس.

---

### `GET /api/v1/ping`

فحص سريع إنو التطبيق قادر يوصل للـAPI.

- **Headers:** `Accept: application/json`
- **Body:** لا شي

**رد `200`:**

```json
{
  "message": "pong",
  "version": "v1",
  "time": "2026-09-15T11:59:32+00:00"
}
```

---

### `POST /api/v1/admin/auth/login`

تسجيل دخول الأدمن وأخذ token. ما في endpoint لإنشاء حساب أدمن — الحساب بينعمل
من الباك اند.

**Headers:**

| Header | القيمة |
|---|---|
| `Accept` | `application/json` |
| `Content-Type` | `application/json` |

**Body parameters:**

| الحقل | النوع | مطلوب | القواعد | الوصف |
|---|---|---|---|---|
| `email` | string | ✅ | إيميل صالح | إيميل الأدمن |
| `password` | string | ✅ | – | كلمة المرور |
| `device_name` | string | ❌ | أقصى حد 255 حرف | اسم الجهاز، بيساعد تعرف كل token لأي جهاز. الافتراضي `dashboard` |

**مثال طلب:**

```json
{
  "email": "owner@example.com",
  "password": "your-password",
  "device_name": "Chrome - Office PC"
}
```

**رد `200`:**

```json
{
  "data": {
    "id": 1,
    "name": "Platform Admin",
    "email": "owner@example.com",
    "last_login_at": "2026-09-15T11:59:32+00:00",
    "created_at": "2026-09-15T11:40:38+00:00"
  },
  "token": "1|r0cxYoNB50xUtD2xO5jtibzUUrFrFfwFGLQzJSCs6c36c224",
  "token_type": "Bearer"
}
```

احفظ `token` وابعته بـ`Authorization: Bearer <token>` بكل الطلبات المحمية.

**الأخطاء:**

`422` — حقول ناقصة:

```json
{
  "message": "The email field is required. (and 1 more error)",
  "errors": {
    "email": ["The email field is required."],
    "password": ["The password field is required."]
  }
}
```

`422` — صيغة إيميل غلط:

```json
{
  "message": "The email field must be a valid email address.",
  "errors": {
    "email": ["The email field must be a valid email address."]
  }
}
```

`422` — إيميل أو كلمة مرور غلط:

```json
{
  "message": "These credentials do not match our records.",
  "errors": {
    "email": ["These credentials do not match our records."]
  }
}
```

> لأسباب أمنية نفس الرسالة بترجع سواء الإيميل مو موجود أو كلمة المرور غلط، ودايماً
> على حقل `email`. اعرضها كرسالة عامة فوق الفورم.

`429` — أكتر من 5 محاولات بالدقيقة لنفس الإيميل:

```json
{ "message": "Too Many Attempts." }
```

مع header `Retry-After: 59` — اعرض للمستخدم "حاول مرة تانية بعد X ثانية".

---

### `GET /api/v1/admin/auth/me` 🔒

بيرجع بيانات الأدمن صاحب الـtoken. استخدمه عند فتح اللوحة لتتأكد إنو الـtoken
لسا صالح.

**Headers:**

| Header | القيمة |
|---|---|
| `Accept` | `application/json` |
| `Authorization` | `Bearer <token>` |

**Body:** لا شي

**رد `200`:**

```json
{
  "data": {
    "id": 1,
    "name": "Platform Admin",
    "email": "owner@example.com",
    "last_login_at": "2026-09-15T11:59:32+00:00",
    "created_at": "2026-09-15T11:40:38+00:00"
  }
}
```

**الأخطاء:** `401` إذا الـtoken ناقص أو غلط أو انعمله logout:

```json
{ "message": "Unauthenticated." }
```

---

### `POST /api/v1/admin/auth/logout` 🔒

بيلغي الـtoken المستخدم بهاد الطلب بس. الأجهزة التانية بتضل مسجلة دخول.

**Headers:**

| Header | القيمة |
|---|---|
| `Accept` | `application/json` |
| `Authorization` | `Bearer <token>` |

**Body:** لا شي

**رد `200`:**

```json
{ "message": "Logged out." }
```

بعدها أي طلب بنفس الـtoken بيرجع `401`. امسح الـtoken من التخزين عندك.

**الأخطاء:** `401`

---

### `POST /api/v1/admin/auth/logout-all` 🔒

بيلغي **كل** tokens الأدمن (خروج من كل الأجهزة). مفيد إذا في شك إنو الحساب
انسرق.

**Headers:**

| Header | القيمة |
|---|---|
| `Accept` | `application/json` |
| `Authorization` | `Bearer <token>` |

**Body:** لا شي

**رد `200`:**

```json
{ "message": "Logged out on all devices." }
```

**الأخطاء:** `401`

---

## 5. الكائنات (Objects)

### Admin

| الحقل | النوع | ملاحظة |
|---|---|---|
| `id` | integer | |
| `name` | string | |
| `email` | string | |
| `last_login_at` | string (ISO 8601) \| null | وقت آخر تسجيل دخول |
| `created_at` | string (ISO 8601) \| null | |

> كلمة المرور ما بترجع أبداً بأي رد.

---

## 6. مثال ربط (axios)

```ts
import axios from 'axios';

export const api = axios.create({
  baseURL: 'http://127.0.0.1:8000/api/v1',
  headers: { Accept: 'application/json' },
});

api.interceptors.request.use((config) => {
  const token = getToken(); // من المكان يلي خزنت فيه الـtoken
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

api.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error.response?.status;

    if (status === 401) {
      clearToken();
      // وجّه المستخدم لصفحة الدخول
    }

    if (status === 422) {
      // error.response.data.errors => { field: ["message"] }
    }

    if (status === 429) {
      const retryAfter = error.response.headers['retry-after'];
      // اعرض: حاول بعد retryAfter ثانية
    }

    return Promise.reject(error);
  },
);

// تسجيل الدخول
const { data } = await api.post('/admin/auth/login', {
  email,
  password,
  device_name: 'dashboard',
});
saveToken(data.token);
```

**أين تخزن الـtoken:**
- **لوحة الأدمن (Next.js):** حسب الـPRD (12.3) بـ httpOnly cookie من طرف
  السيرفر، مو بـ`localStorage`.
- **تطبيقات الموبايل:** `expo-secure-store`.
