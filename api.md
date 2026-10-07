# توثيق الـAPI — وفاء

كل endpoint **شغّال حالياً** بالباك إند، شو بياخد، وشو بيرجّع، وأخطاؤه. كل الأمثلة ردود حقيقية من
السيرفر. المسارات والأشكال مطابقة لـ«عقد الواجهات البرمجية» من Deep Code (`1.0.0-draft.1`).

- **Base URL:** `http://127.0.0.1:8000/api/v1` (ومن موبايل على نفس الشبكة: `http://<IP-الكمبيوتر>:8000/api/v1`)
- **الطلبات والردود:** JSON فقط (إلا رفع الشعار بالتسجيل: `multipart/form-data`)
- **التواريخ:** ISO 8601 بتوقيت UTC، مثل `2026-09-29T15:00:52Z`. وتاريخ الميلاد لحاله: `1998-05-20`
- **الهاتف:** `+9639XXXXXXXX`، والخادم بيقبل كمان `0933123456` وبيحوّلها
- **المبالغ:** نصوص عشرية مو أرقام، مثل `"10.00"`

## الفهرس

| # | المسار | مين | الحماية |
|---|---|---|---|
| | **عام** | | |
| 1 | `GET /ping` | الكل | — |
| | **تطبيق الزبون** | | |
| 2 | `GET /customer/config` | زبون | — |
| 3 | `POST /customer/auth/otp` | زبون | — |
| 4 | `POST /customer/auth/verify` | زبون | — |
| 5 | `POST /customer/auth/logout` | زبون | توكن |
| 6 | `GET /customer/me` | زبون | توكن |
| 7 | `PATCH /customer/me` | زبون | توكن + جاهز |
| 8 | `DELETE /customer/me` | زبون | توكن |
| 9 | `POST /customer/me/profile` | زبون | توكن |
| 10 | `POST /customer/me/policy-consents` | زبون | توكن |
| 11 | `GET /customer/me/qr` | زبون | توكن + جاهز |
| 12 | `GET /customer/cards` | زبون | توكن + جاهز |
| 13 | `GET /customer/cards/{card_id}` | زبون | توكن + جاهز |
| 14 | `GET /customer/notifications` | زبون | توكن + جاهز |
| 15 | `POST /customer/notifications/{id}/read` | زبون | توكن + جاهز |
| 16 | `POST /customer/notifications/read-all` | زبون | توكن + جاهز |
| 17 | `PUT /customer/devices` | زبون | توكن |
| 18 | `DELETE /customer/devices/{token}` | زبون | توكن |
| | **تطبيق التاجر** | | |
| 19 | `GET /merchant/me` | تاجر | Clerk |
| 20 | `GET /merchant/lookups` | تاجر | Clerk |
| 21 | `POST /merchant/registration/business` | تاجر | Clerk |
| 22 | `POST /merchant/registration/package` | تاجر | Clerk |
| 23 | `POST /merchant/registration/pin` | تاجر | Clerk |
| 24 | `POST /merchant/pin/unlock` | تاجر | مسجّل |
| 25 | `PUT /merchant/pin` | تاجر | مسجّل + PIN |
| 26 | `POST /merchant/pin/reset` | تاجر | مسجّل + دخول حديث |
| 27 | `GET /merchant/cards` | تاجر | مسجّل |
| 28 | `POST /merchant/cards` | تاجر | مسجّل + PIN |
| 29 | `POST /merchant/cards/{card_id}/suspend` | تاجر | مسجّل + PIN |
| 30 | `POST /merchant/scan/resolve` | تاجر | مسجّل |
| 31 | `POST /merchant/stamps` | تاجر | مسجّل |
| 32 | `POST /merchant/redemptions` | تاجر | مسجّل |
| 33 | `GET /merchant/notifications` | تاجر | مسجّل |
| 34 | `POST /merchant/notifications/{id}/read` | تاجر | مسجّل |
| 35 | `POST /merchant/notifications/read-all` | تاجر | مسجّل |
| 36 | `PUT /merchant/devices` | تاجر | مسجّل |
| 37 | `DELETE /merchant/devices/{token}` | تاجر | مسجّل |
| 38 | `GET /merchant/customers/birthdays-today` | تاجر | مسجّل + PIN |
| 39 | `POST /merchant/customers/{customer_id}/birthday-greeting` | تاجر | مسجّل + PIN |
| | **لوحة الإدارة** | | |
| 40 | `GET /admin/auth/me` | إدارة | حساب فعّال |
| 41 | `GET /admin/admin-users` | إدارة | `manage-admin-accounts` |
| 42 | `POST /admin/admin-users` | إدارة | `manage-admin-accounts` |
| 43 | `PATCH /admin/admin-users/{id}` | إدارة | `manage-admin-accounts` |
| 44 | `DELETE /admin/admin-users/{id}` | إدارة | `manage-admin-accounts` |
| 45 | `POST /admin/stamps/{id}/cancel` | إدارة | `cancel-stamps` |
| | **للخادم فقط** | | |
| 46 | `POST /webhooks/clerk` | Clerk | توقيع Svix |

- **توكن:** توكن الزبون من `auth/verify`.
- **جاهز:** الزبون كمّل اسمه وتاريخ ميلاده، ووافق على إصدار سياسة الخصوصية الحالي. غير هيك بياخد `403`
  (§1.3).
- **Clerk:** توكن جلسة Clerk، حتى قبل ما يكمّل التاجر تسجيله.
- **مسجّل:** Clerk + التاجر مكمّل خطوات التسجيل الثلاث. غير هيك `403 REGISTRATION_INCOMPLETE`.
- **PIN:** الترويسة `X-Pin-Token` من `pin/unlock`.

---

## 1. أساسيات

### 1.1 المصادقة والترويسات

| مين | كيف بيفوت | شو بيبعت بـ`Authorization` |
|---|---|---|
| **الزبون** | رقم موبايل + رمز على واتساب | `Bearer <token>` من `auth/verify`. خزّنه بـ`expo-secure-store` |
| **التاجر ولوحة الإدارة** | Clerk (Google أو رمز على البريد) | `Bearer <token>` من `getToken()` تبع Clerk **قبل كل طلب**، وما بيتخزّن |

رمز واتساب لتطبيق الزبون بس. التاجر والإدارة ما بيستعملوه أبداً.

| الترويسة | متى | القيمة |
|---|---|---|
| `Accept` | كل طلب | `application/json` |
| `Authorization` | المسارات المحمية | `Bearer <token>` |
| `X-App-Version` | كل طلبات تطبيق التاجر | نسخة التطبيق، مثل `1.0.0`. الأقدم من الحد الأدنى بياخد `426` |
| `X-App-Platform` | كل طلبات تطبيق التاجر | `android` أو `ios` |
| `X-Pin-Token` | تبويبات التاجر المحمية | `pin_token` من `pin/unlock`. بالذاكرة بس، بيروح لما ينسكّر التطبيق |

**إعداد Clerk بالفرونت:**
- تطبيق Clerk واحد للتاجر واللوحة. الـPublishable key (`pk_test_…`) من مطوّر الباك إند، والمفتاح السري
  `sk_…` **ما بيدخل الفرونت أبداً**.
- تطبيق التاجر (Expo): `@clerk/clerk-expo` مع `tokenCache`.
- لوحة الإدارة (Next.js): `@clerk/nextjs`. أصل اللوحة لازم يكون بـ`CLERK_AUTHORIZED_PARTIES` (وإلا
  `401`) وبـ`FRONTEND_URLS` (وإلا CORS).
- البريد بياخده الخادم من توكن Clerk، لا تبعته بالـbody.

### 1.2 شكل الردود

```jsonc
// عنصر واحد
{ "data": { … } }

// قائمة بتصفح (الإشعارات): ابعت ?cursor=…&limit=20 (الحد 1–50، والافتراضي 20)
{ "data": [ … ], "meta": { "next_cursor": "eyJjcmVhdGVkX2F0…" } }   // null = ما في صفحات بعد

// خطأ
{ "error": { "code": "STAMP_INTERVAL", "message": "Stamp interval not elapsed.", "details": { … } } }
```

**قاعدة:** اعرض للمستخدم نصاً من ملفات التطبيق حسب `error.code`. الـ`message` للتطوير بس، بالإنجليزي.
القيم يلي بيحتاجها النص (دقائق، حدود، تواريخ) بتجي بـ`details`.

أخطاء الحقول (`422 VALIDATION_FAILED`) بتجي هيك:

```json
{
  "error": {
    "code": "VALIDATION_FAILED",
    "message": "The business name field must be at least 2 characters. (and 4 more errors)",
    "details": {
      "fields": {
        "business_name": ["min"],
        "business_type_id": ["required"],
        "phone": ["format"]
      }
    }
  }
}
```

أسماء القواعد: `required` · `min` · `max` · `size` · `format` (نمط، تاريخ، رقم، قيمة مو من القائمة، صورة،
uuid) · `taken` (مستعمل) · `exists` (مو موجود) · `before` · `prohibits` (انبعت مع حقل ما لازم ينبعت معه) ·
`invalid`.

### 1.3 رموز الأخطاء

| الرمز | HTTP | `details` | متى |
|---|---|---|---|
| `UNAUTHENTICATED` | 401 | — | توكن مفقود أو منتهي أو غلط |
| `FORBIDDEN` | 403 | — | الحساب ما إلو صلاحية (دور بلوحة الإدارة، أو توكن لتطبيق تاني) |
| `NOT_FOUND` | 404 | — | المسار أو العنصر مو موجود، أو مو تبع هالحساب |
| `VALIDATION_FAILED` | 422 | `fields` | أخطاء الحقول |
| `RATE_LIMITED` | 429 | `retry_after_seconds` | تجاوز حد الطلبات |
| `APP_VERSION_UNSUPPORTED` | 426 | `min_version`, `download_url` | تطبيق التاجر قديم: شاشة التحديث الإجباري |
| `SERVER_ERROR` | 500 / 503 | — | خطأ غير متوقع، أو ما قدرنا نبعت رمز واتساب |
| `OTP_INVALID` | 422 | `attempts_remaining` | رمز واتساب غلط |
| `OTP_EXPIRED` | 422 | — | الرمز انتهت دقائقه الخمس، أو ما في رمز |
| `OTP_ATTEMPTS_EXCEEDED` | 422 | — | 5 محاولات غلط، اطلب رمز جديد |
| `OTP_RESEND_TOO_SOON` | 429 | `retry_after_seconds` | طلب رمز قبل انتهاء المهلة |
| `POLICY_VERSION_OUTDATED` | 422 | `current_version` | الإصدار المبعوث مو الحالي: حمّل السياسة من جديد |
| `POLICY_CONSENT_REQUIRED` | 403 | `current_version` | في إصدار سياسة جديد ما وافق عليه الزبون |
| `PROFILE_INCOMPLETE` | 403 | — | الزبون ما كمّل اسمه وتاريخ ميلاده |
| `PROFILE_ALREADY_COMPLETED` | 409 | — | إكمال الملف مرة تانية |
| `UNDER_AGE` | 422 | `min_age` | عمره أقل من 13 |
| `REGISTRATION_INCOMPLETE` | 403 | `registration_step` | مسار تاجر قبل ما يكمّل التسجيل |
| `REGISTRATION_STEP_MISMATCH` | 409 | `registration_step` | خطوة تسجيل بغير وقتها |
| `PIN_REQUIRED` | 403 | — | `X-Pin-Token` مفقود أو منتهي |
| `PIN_INVALID` | 422 | `attempts_remaining` | PIN غلط |
| `PIN_LOCKED` | 429 | `retry_after_seconds` | 5 محاولات غلط ورا بعض: قفل 15 دقيقة |
| `PIN_RESET_REQUIRES_RECENT_LOGIN` | 403 | `max_age_seconds` | إعادة ضبط الـPIN بدون دخول حديث لـClerk |
| `QR_INVALID` | 422 | — | رمز مو مقروء أو لزبون مو موجود |
| `QR_EXPIRED` | 422 | — | رمز قديم: «اطلب من الزبون يفتح رمزه من جديد» |
| `SCAN_TOKEN_EXPIRED` | 422 | — | مرّت 3 دقائق على المعاينة: امسح من جديد |
| `STAMP_INTERVAL` | 422 | `last_stamp_at`, `next_allowed_at`, `minutes_since_last`, `minutes_remaining` | الفاصل بين طابعين ما خلص |
| `REWARD_READY_REDEEM_FIRST` | 422 | `cycle_id` | البطاقة مكتملة: سلّم الهدية أولاً |
| `NO_REWARD_READY` | 422 | — | تسليم على دورة ما فيها هدية جاهزة |
| `REDEEM_REQUIRES_QR` | 422 | — | التسليم بيحتاج مسح رمز الزبون، مو رقم مكتوب |
| `REWARD_ALREADY_REDEEMED` | 409 | `cycle_id`, `redeemed_at` | جهاز تاني سلّم الهدية للتو |
| `CARD_SUSPENDED` | 422 | — | بطاقة موقوفة: لا مشتركين جدد ولا دورات جديدة |
| `MERCHANT_STATUS_BLOCKS_ACTION` | 422 | `status`, `action` | حالة الاشتراك بتمنع العملية (`action`: `stamps` أو `cards` أو `campaigns`) |
| `CARDS_LIMIT_REACHED` | 422 | `cards_limit` | وصل حد البطاقات الفعّالة بالباقة |
| `BIRTHDAY_NOT_TODAY` | 422 | — | تهنئة لزبون مو عيد ميلاده اليوم |
| `CAMPAIGN_CONTAINS_LINK` | 422 | `field` | رابط بنص التهنئة أو الهدية |

### 1.4 حدود الطلبات

| على شو | الحد |
|---|---|
| كل `/api/*` | 60 بالدقيقة لكل توكن (أو لكل IP للطلبات بدون توكن) |
| `POST /customer/auth/otp` | 5 بالساعة لكل رقم، و20 لكل IP، ومهلة 60 ثانية بين رمز ورمز |
| `POST /customer/auth/verify` | 10 بالدقيقة لكل رقم، و30 لكل IP |
| الـPIN | 5 محاولات غلط ورا بعض بتقفل 15 دقيقة |

الرد `429` فيه الترويسة `Retry-After` و`details.retry_after_seconds`.

### 1.5 الكائنات المشتركة

**Customer** (للزبون نفسه بس):

| الحقل | النوع | ملاحظة |
|---|---|---|
| `id` | integer | |
| `phone` | string | `+963933123456` |
| `name` | string \| null | `null` لحتى يكمّل ملفه |
| `birthdate` | string \| null | `1998-05-20`. ما بيتعدّل من التطبيق |
| `campaigns_muted` | boolean | إيقاف عروض كل التجار |
| `profile_complete` | boolean | `false` ← اعرض شاشة الاسم وتاريخ الميلاد |
| `consented_policy_version` | string \| null | إذا مختلف عن `privacy_policy_version` بـ`/customer/config` اعرض شاشة الموافقة |
| `registered_at` | string | |

**CardSummary:**

```json
{
  "id": 11,
  "name": "بطاقة القهوة",
  "stamps_required": 3,
  "reward_description": "فنجان قهوة مجاني",
  "terms": "لا تُجمع مع عروض أخرى",
  "icon": { "id": 1, "key": "coffee-cup", "name": "فنجان قهوة" },
  "status": "active"
}
```

التطبيق بيرسم الأيقونة من مكتبته حسب `icon.key`. و`status`: `active` أو `suspended`.

**MerchantSummary:**

```json
{
  "id": 10,
  "business_name": "كافيه الياسمين",
  "logo_url": null,
  "business_type": { "id": 1, "name": "كافيه" },
  "governorate": { "id": 1, "name": "دمشق" }
}
```

**CycleProgress:** تقدّم الزبون على بطاقة.

```json
{ "id": 17, "stamps_count": 1, "status": "COLLECTING", "completed_at": null }
```

- `status`: `COLLECTING` (عم يجمّع) أو `REWARD_READY` (الهدية جاهزة).
- `id: null` مع `stamps_count: 0` يعني ما في دورة مفتوحة: قبل أول طابع، أو بعد استلام الهدية. الدورة
  الجديدة بتنفتح مع الطابع الجاي.

---

## 2. عام

### `GET /ping`

فحص الاتصال.

```json
{ "message": "pong", "version": "v1", "time": "2026-09-29T15:00:50Z" }
```

---

## 3. تطبيق الزبون

### التدفق

```
عند فتح التطبيق:  GET /customer/config  (إصدار السياسة، مدة الرمز، القوائم)

الدخول:
  1. POST /customer/auth/otp        { phone }
  2. POST /customer/auth/verify     { phone, code, policy_version }  →  token
       needs_profile = true   →  3. POST /customer/me/profile  { name, birthdate }
       claimed_stamps فيها شي →  اعرض «طوابعك وصلت»
  4. GET  /customer/me/qr           →  خزّن السر، وولّد الرمز بدون اتصال (§6)
  5. PUT  /customer/devices         { token, platform }

كل ما ينفتح التطبيق وهو داخل:  GET /customer/me
  profile_complete = false                                   →  شاشة الاسم وتاريخ الميلاد
  consented_policy_version ≠ config.privacy_policy_version   →  شاشة الموافقة  →  POST /customer/me/policy-consents
```

### 3.1 `GET /customer/config`

**بدون مصادقة.** بيتقرأ عند فتح التطبيق وبيتخزّن محلياً.

```json
{
  "data": {
    "qr_period_seconds": 60,
    "privacy_policy_version": "1.2",
    "privacy_policy_url": "https://…",
    "customer_terms_url": "https://…",
    "governorates": [ { "id": 1, "name": "دمشق" }, { "id": 2, "name": "ريف دمشق" }, … ],
    "business_types": [ { "id": 1, "name": "كافيه" }, { "id": 2, "name": "مطعم" }, … ]
  }
}
```

### 3.2 `POST /customer/auth/otp`

**بدون مصادقة.** بيبعت رمز من 6 أرقام على واتساب، صالح 5 دقائق. الرد نفسه سواء الرقم مسجّل أو لأ.

| الحقل | النوع | مطلوب |
|---|---|---|
| `phone` | string | ✅ |

**`202`:**

```json
{ "data": { "expires_in_seconds": 300, "resend_after_seconds": 60 } }
```

**أخطاء:**
- `429 OTP_RESEND_TOO_SOON` مع `details.retry_after_seconds`.
- `422 VALIDATION_FAILED` لرقم مو سوري.
- `503 SERVER_ERROR` إذا ما قدرنا نبعت الرسالة.

### 3.3 `POST /customer/auth/verify`

**بدون مصادقة.** بيتحقق من الرمز. إذا الرقم جديد بينشئ الحساب، وإذا كان «زبون معلّق» (تاجر أضافله طوابع
برقمه) بيكمّل نفس الحساب وطوابعه معه. وبيسجّل الموافقة على السياسة.

| الحقل | النوع | مطلوب | ملاحظة |
|---|---|---|---|
| `phone` | string | ✅ | |
| `code` | string | ✅ | 6 أرقام |
| `policy_version` | string | ✅ | الإصدار يلي شافه الزبون، من `config` |

**`200`:**

```json
{
  "data": {
    "token": "10|FJ2HL4cWxpQLwZuDeeAcBWDcAgBc3fuOGaPqmWE5ab4dc686",
    "token_type": "Bearer",
    "customer": {
      "id": 18,
      "phone": "+963933123456",
      "name": null,
      "birthdate": null,
      "campaigns_muted": false,
      "profile_complete": false,
      "consented_policy_version": "1.2",
      "registered_at": "2026-09-29T15:00:51Z"
    },
    "needs_profile": true,
    "claimed_stamps": []
  }
}
```

`claimed_stamps` فيها عناصر بس بأول دخول لرقم كان زبون معلّق:

```json
[ { "merchant": MerchantSummary, "card": CardSummary, "stamps_count": 4, "status": "COLLECTING" } ]
```

**أخطاء:**
- `422 OTP_INVALID` مع `attempts_remaining`.
- `422 OTP_EXPIRED`.
- `422 OTP_ATTEMPTS_EXCEEDED`.
- `422 POLICY_VERSION_OUTDATED` مع `current_version`. بيتفحص قبل الرمز، فالرمز بيضل صالح.

```json
{ "error": { "code": "OTP_INVALID", "message": "The verification code is not correct.", "details": { "attempts_remaining": 4 } } }
```

### 3.4 `POST /customer/auth/logout` 🔒

بيبطل التوكن الحالي بس. ابعت `DELETE /customer/devices/{token}` **قبله** لتوقف الإشعارات على الجهاز.
**`204`** بدون محتوى.

### 3.5 `GET /customer/me` 🔒

بيشتغل حتى لو الملف ناقص أو الموافقة قديمة، وهو يلي بيقرر أي شاشة تنعرض. **`200`:** `{ "data": Customer }`.

```json
{
  "data": {
    "id": 18,
    "phone": "+963933123456",
    "name": "سارة",
    "birthdate": "1998-05-20",
    "campaigns_muted": false,
    "profile_complete": true,
    "consented_policy_version": "1.2",
    "registered_at": "2026-09-29T15:00:51Z"
  }
}
```

### 3.6 `PATCH /customer/me` 🔒 جاهز

الحقل الوحيد القابل للتعديل: إيقاف عروض كل التجار. الاسم وتاريخ الميلاد ما بيتعدّلوا من التطبيق.

| الحقل | النوع | مطلوب |
|---|---|---|
| `campaigns_muted` | boolean | ✅ |

**`200`:** `{ "data": Customer }`.

### 3.7 `DELETE /customer/me` 🔒

حذف فوري للحساب:
- بينمسحوا الرقم، والاسم، وتاريخ الميلاد، ورمز الـQR، والأجهزة، والتوكنات، والإشعارات، والموافقات.
- الطوابع بتضل بدون ما تدل على حدا، لإحصاءات التجار.
- الطوابع والهدايا يلي ما استلمها بتضيع، فنبّهه قبل الحذف.

**`204`** بدون محتوى. نفس الرقم بيقدر يسجّل بعدين كحساب جديد فاضي.

### 3.8 `POST /customer/me/profile` 🔒

الاسم وتاريخ الميلاد، مرة وحدة بعد أول دخول.

| الحقل | النوع | مطلوب | ملاحظة |
|---|---|---|---|
| `name` | string | ✅ | 2–60 حرف |
| `birthdate` | string | ✅ | `YYYY-MM-DD`، بالماضي |

**`200`:** `{ "data": Customer }` مع `profile_complete: true`.

**أخطاء:**
- `422 UNDER_AGE` مع `details.min_age: 13`.
- `409 PROFILE_ALREADY_COMPLETED`.
- `422 VALIDATION_FAILED`.

### 3.9 `POST /customer/me/policy-consents` 🔒

الموافقة على إصدار جديد من سياسة الخصوصية.

| الحقل | النوع | مطلوب |
|---|---|---|
| `policy_version` | string | ✅ |

**`200`:** `{ "data": Customer }`. **أخطاء:** `422 POLICY_VERSION_OUTDATED` مع `current_version`.

لما يطلع إصدار جديد، كل المسارات المعلّمة «جاهز» بترجع `403 POLICY_CONSENT_REQUIRED` لحد ما يوافق.

### 3.10 `GET /customer/me/qr` 🔒 جاهز

سرّ توليد رمز QR. اطلبه مرة بعد الدخول وخزّنه بـ`expo-secure-store`. طريقة التوليد بـ§6.

```json
{
  "data": {
    "qr_id": "Q1Pa9zblIBzE",
    "secret": "4XDGTVZPZAQC34IDCLK7GPZOMBYPLY3Q",
    "algorithm": "SHA256",
    "digits": 8,
    "period_seconds": 60
  }
}
```

### 3.11 `GET /customer/cards` 🔒 جاهز

«بطاقاتي»: كل البطاقات مرة وحدة بدون تصفح. الترتيب: الهدايا الجاهزة أول شي، بعدين حسب آخر طابع.

- بعد استلام هدية على بطاقة فعّالة: البطاقة بتضل بتقدّم 0 و`cycle.id: null`.
- بعد استلام هدية على بطاقة موقوفة: البطاقة بتختفي.

```json
{
  "data": [
    {
      "card": CardSummary,
      "merchant": MerchantSummary,
      "cycle": { "id": null, "stamps_count": 0, "status": "COLLECTING", "completed_at": null },
      "completed_cycles_count": 1,
      "last_stamp_at": "2026-09-29T15:00:52Z",
      "merchant_muted": false
    }
  ]
}
```

| الحقل | ملاحظة |
|---|---|
| `completed_cycles_count` | كم مرة استلم هدية هالبطاقة |
| `last_stamp_at` | آخر طابع غير ملغى، أو `null` |
| `merchant_muted` | موقّف عروض هالمحل |

### 3.12 `GET /customer/cards/{card_id}` 🔒 جاهز

تفاصيل بطاقة: نفس حقول العنصر فوق، ومعها طوابع الدورة الحالية (الأقدم أول) وعنوان المحل.

```json
{
  "data": {
    "card": CardSummary,
    "merchant": MerchantSummary,
    "cycle": { "id": 17, "stamps_count": 2, "status": "COLLECTING", "completed_at": null },
    "completed_cycles_count": 0,
    "last_stamp_at": "2026-09-29T15:00:52Z",
    "merchant_muted": false,
    "stamps": [
      { "stamped_at": "2026-09-27T10:00:00Z", "method": "qr" },
      { "stamped_at": "2026-09-29T15:00:52Z", "method": "phone" }
    ],
    "merchant_address": "دمشق، شارع الحمرا"
  }
}
```

`method`: `qr` (مسح الرمز) أو `phone` (برقمه). **أخطاء:** `404 NOT_FOUND` لبطاقة ما جمّع عليها الزبون.

### 3.13 `GET /customer/notifications` 🔒 جاهز

صندوق الإشعارات، الأحدث أول، بالمؤشر: `?limit=20` وبعدين `?cursor=<meta.next_cursor>`.

```json
{
  "data": [
    {
      "id": "01a0edae-d41f-73e0-a373-7304776dc323",
      "type": "reward_redeemed",
      "title": "استلمت هديتك",
      "body": "استلمت هديتك من كافيه الياسمين. بدأت بطاقتك الجديدة.",
      "data": { "merchant_id": 10, "card_id": 11, "cycle_id": 17 },
      "read_at": null,
      "created_at": "2026-09-29T15:00:52Z"
    },
    {
      "id": "01a0edae-d3dc-710c-b3f7-4d688299b861",
      "type": "card_completed",
      "title": "هديتك جاهزة!",
      "body": "اكتملت بطاقتك في كافيه الياسمين. اعرض رمزك للكاشير لتستلم فنجان قهوة مجاني.",
      "data": { "merchant_id": 10, "card_id": 11, "cycle_id": 17 },
      "read_at": null,
      "created_at": "2026-09-29T15:00:52Z"
    }
  ],
  "meta": { "next_cursor": "eyJjcmVhdGVkX2F0IjoiMjAy…" }
}
```

| `type` | متى | النص |
|---|---|---|
| `stamp_added` | انضافله طابع | «أُضيف لك طابع عند [المحل]. صار لديك 1 من 3.» |
| `card_completed` | اكتملت البطاقة | العنوان «هديتك جاهزة!» والنص «اكتملت بطاقتك في [المحل]. اعرض رمزك للكاشير لتستلم [وصف الهدية].» |
| `birthday_greeting` | تهنئة عيد ميلاد من محل | العنوان «عيد ميلاد سعيد من [المحل]»، والنص رسالة التاجر، ومعها سطر «هديتك: [الهدية]» إذا في هدية |
| `reward_redeemed` | استلم الهدية | «استلمت هديتك من [المحل]. بدأت بطاقتك الجديدة.» |

`data` فيها المعرّفات لتفتح البطاقة لما يضغط (للتهنئة: `merchant_id` بس). الإشعارات هلق **بالصندوق داخل التطبيق بس**.

### 3.14 `POST /customer/notifications/{id}/read` 🔒 جاهز

تعليم إشعار كمقروء. **`204`**. إشعار مو إلو ← `404`.

### 3.15 `POST /customer/notifications/read-all` 🔒 جاهز

تعليم الكل كمقروء. **`204`**.

### 3.16 `PUT /customer/devices` 🔒

تسجيل رمز FCM للجهاز، بعد الدخول وكل ما يتغيّر. إذا الرمز كان لحساب تاني بينتقل لهالحساب.

| الحقل | النوع | مطلوب | ملاحظة |
|---|---|---|---|
| `token` | string | ✅ | لحد 255 حرف |
| `platform` | string | ✅ | `android` أو `ios` |

**`204`**.

### 3.17 `DELETE /customer/devices/{token}` 🔒

إزالة رمز الجهاز عند الخروج. ابعت الرمز مرمَّز: `encodeURIComponent(token)`، لأن رموز FCM فيها `:`.
**`204`** حتى لو الرمز مو موجود.

---

## 4. تطبيق التاجر

### التدفق

```
دخول بـClerk
GET /merchant/me  →  registration_step:
   business  →  POST /merchant/registration/business
   package   →  POST /merchant/registration/package   (التجربة بتبلّش فوراً)
   pin       →  POST /merchant/registration/pin       (بيرجع pin_token كمان)
   done      →  الشاشة الرئيسية
PUT /merchant/devices

المسح (بدون PIN، للكاشير):
   GET  /merchant/cards?status=active       ←  اختيار البطاقة
   POST /merchant/scan/resolve              ←  شاشة التأكيد
   action = stamp   →  POST /merchant/stamps       { scan_token, client_uuid }
   action = redeem  →  POST /merchant/redemptions  { scan_token, cycle_id }

التبويبات المحمية:  POST /merchant/pin/unlock  →  X-Pin-Token
```

**كل** طلبات التاجر بتبعت `X-App-Version` و`X-App-Platform`، والنسخة القديمة بتاخد:

```json
{ "error": { "code": "APP_VERSION_UNSUPPORTED", "message": "A newer version of the app is required.", "details": { "min_version": "1.0.0", "download_url": "https://…/wafa-merchant.apk" } } }
```

وقبل ما يكمّل التسجيل، كل مسار غير `me` و`lookups` والتسجيل بيرجع:

```json
{ "error": { "code": "REGISTRATION_INCOMPLETE", "message": "Finish the registration steps first.", "details": { "registration_step": "business" } } }
```

### 4.1 `GET /merchant/me`

أول طلب عند فتح التطبيق. بيشتغل قبل التسجيل، وبيسجّل آخر دخول وبيحدّث البريد من توكن Clerk.

**مستخدم Clerk جديد:**

```json
{ "data": { "registration_step": "business", "email": "shop@example.com", "merchant": null, "subscription": null, "usage": null } }
```

**تاجر مسجّل (MerchantMe):**

```json
{
  "data": {
    "registration_step": "done",
    "email": "shop@example.com",
    "merchant": {
      "id": 10,
      "email": "shop@example.com",
      "business_name": "كافيه الياسمين",
      "business_type": { "id": 1, "name": "كافيه" },
      "governorate": { "id": 1, "name": "دمشق" },
      "address": "دمشق، شارع الحمرا",
      "owner_name": "أحمد",
      "phone": "+963944111222",
      "logo_url": null,
      "created_at": "2026-09-29T15:00:51Z"
    },
    "subscription": {
      "status": "TRIAL",
      "package": { "id": 2, "name": "المتوسطة", "cards_limit": 2, "weekly_campaigns_limit": 2 },
      "current_period": {
        "id": 10,
        "type": "trial",
        "package": { "id": 2, "name": "المتوسطة", "cards_limit": 2, "weekly_campaigns_limit": 2 },
        "duration_months": null,
        "starts_at": "2026-09-29T15:00:51Z",
        "ends_at": "2026-10-13T15:00:51Z",
        "grace_ends_at": null
      },
      "trial_used": true,
      "days_remaining": 14,
      "capabilities": {
        "stamps": true,
        "new_customers": true,
        "campaigns": true,
        "redemptions": true,
        "create_cards": true,
        "directory_visible": true
      },
      "banner": null,
      "pending_payment": null
    },
    "usage": {
      "active_cards": 0,
      "cards_limit": 2,
      "campaigns_used_this_week": 0,
      "weekly_campaigns_limit": 2,
      "campaigns_resets_at": "2026-10-02T21:00:00Z"
    }
  }
}
```

| الحقل | ملاحظة |
|---|---|
| `registration_step` | `business` · `package` · `pin` · `done` |
| `subscription.status` | `TRIAL` · `ACTIVE` · `GRACE` · `EXPIRED` · `SUSPENDED` · `PENDING_DELETION` |
| `subscription.days_remaining` | لنهاية التجربة أو الاشتراك، أو لنهاية المهلة بـ`GRACE`. `null` بباقي الحالات |
| `subscription.capabilities` | شو مسموح هلق. استعمله للعرض، والخادم بيفرضه على كل حال |
| `subscription.banner` | شريط التنبيه، أو `null` |
| `usage.campaigns_resets_at` | بداية الأسبوع الجاي: السبت 00:00 بتوقيت دمشق، مكتوب بـUTC متل كل التواريخ (`2026-10-02T21:00:00Z` = السبت 3 تشرين الأول 00:00 بدمشق). الأسبوع تقويمي من السبت للجمعة |

**`banner`:** `{ "code", "level", "params" }`:

| `code` | متى | `level` | `params` |
|---|---|---|---|
| `TRIAL_ENDING` | باقي للتجربة 3 أيام أو أقل | `warning` | `days_remaining`, `ends_at` |
| `SUBSCRIPTION_ENDING` | باقي للاشتراك 3 أيام أو أقل | `warning` | `days_remaining`, `ends_at` |
| `GRACE` | مهلة السماح | `danger` | `days_remaining`, `ends_at` |
| `EXPIRED` · `SUSPENDED` · `PENDING_DELETION` | الحالة نفسها | `danger` | — |

**`capabilities` حسب الحالة:** `TRIAL` و`ACTIVE` و`GRACE` كلها `true`. وبـ`EXPIRED` و`SUSPENDED`
و`PENDING_DELETION` بس `redemptions: true` (تسليم الهدايا مسموح دايماً)، والباقي `false`.

### 4.2 `GET /merchant/lookups`

كل شي بتحتاجه نماذج التطبيق، مفتوح قبل التسجيل.

```json
{
  "data": {
    "governorates": [ { "id": 1, "name": "دمشق" }, … ],
    "business_types": [ { "id": 1, "name": "كافيه" }, … ],
    "icons": [ { "id": 1, "key": "coffee-cup", "name": "فنجان قهوة" }, { "id": 2, "key": "tea-glass", "name": "كأس شاي" }, … ],
    "trial_days": 14,
    "packages": [
      {
        "id": 1,
        "name": "الأساسية",
        "cards_limit": 1,
        "weekly_campaigns_limit": 1,
        "prices": [
          { "duration_months": 1, "price_usd": "10.00", "amount_syp": "130000.00" },
          { "duration_months": 3, "price_usd": "27.00", "amount_syp": "351000.00" },
          { "duration_months": 12, "price_usd": "96.00", "amount_syp": "1248000.00" }
        ]
      }
    ],
    "payment": {
      "exchange_rate_syp": "13000.00",
      "syriatel_cash_number": "…",
      "bank_transfer_details": "…",
      "review_sla_hours": 24
    },
    "limits": {
      "card_stamps_min": 3,
      "card_stamps_max": 10,
      "campaign_title_max": 60,
      "campaign_body_max": 300,
      "stamp_interval_minutes": 60,
      "pin_unlock_hours": 12
    },
    "links": { "privacy_policy_url": "…", "merchant_terms_url": "…" }
  }
}
```

- `trial_days`: مدة التجربة المجانية بالأيام، لجملة «جرّب مجاناً لمدة X يوم». حقل زيادة عن العقد.
- `amount_syp` بسعر الصرف الحالي، للعرض بس.
- ابني قواعد النماذج من `limits`.
- الأسعار وحدود الحملات والتجربة قيم مبدئية لحد ما تنحسم، وبتتعدّل من الإعدادات بدون تغيير بالتطبيق.

### 4.3 `POST /merchant/registration/business`

الخطوة 1: بيانات النشاط. مقبولة بس لما `registration_step = business`.

| الحقل | النوع | مطلوب | ملاحظة |
|---|---|---|---|
| `business_name` | string | ✅ | 2–80. ما بيتعدّل بعدين إلا من الدعم |
| `business_type_id` | integer | ✅ | من `lookups.business_types` |
| `governorate_id` | integer | ✅ | من `lookups.governorates` |
| `address` | string | — | لحد 255 |
| `owner_name` | string | ✅ | 2–80 |
| `phone` | string | ✅ | رقم تواصل المحل، مو للدخول. فريد بين التجار |
| `logo` | ملف | — | JPEG أو PNG أو WebP لحد 2 MB. مع الشعار ابعت الطلب `multipart/form-data` |

**`201`:** `{ "data": MerchantMe }` مع `registration_step: "package"` و`subscription: null`.

**أخطاء:**
- `422 VALIDATION_FAILED` (الرقم المستعمل: `phone: ["taken"]`).
- `409 REGISTRATION_STEP_MISMATCH` مع `registration_step`.

### 4.4 `POST /merchant/registration/package`

الخطوة 2: اختيار الباقة، والتجربة المجانية بتبلّش فوراً.

| الحقل | النوع | مطلوب |
|---|---|---|
| `package_id` | integer | ✅ |

**`200`:** `{ "data": MerchantMe + "trial_granted": true }`، مع `registration_step: "pin"`.

إذا البريد أخد تجربة قبل (حتى بحساب محذوف)، الطلب ما بينرفض: `trial_granted: false` و
`subscription.status: "EXPIRED"`. بعد الـPIN بتوجّهه للدفع.

**أخطاء:** `409 REGISTRATION_STEP_MISMATCH`، و`422 VALIDATION_FAILED`.

### 4.5 `POST /merchant/registration/pin`

الخطوة 3: إنشاء الـPIN، وفيها بيكتمل التسجيل.

| الحقل | النوع | مطلوب | ملاحظة |
|---|---|---|---|
| `pin` | string | ✅ | 4–6 أرقام |

**`200`:** بيرجع `pin_token` كمان، حتى ما ينطلب الـPIN فوراً:

```json
{ "data": { "me": MerchantMe, "pin": { "pin_token": "eyJpdiI6…", "expires_at": "2026-09-30T03:00:52Z" } } }
```

**أخطاء:**
- `422 VALIDATION_FAILED` (`pin: ["format"]`).
- `409 REGISTRATION_STEP_MISMATCH`.

### 4.6 `POST /merchant/pin/unlock`

فتح التبويبات المحمية.

| الحقل | النوع | مطلوب |
|---|---|---|
| `pin` | string | ✅ |

**`200`:**

```json
{ "data": { "pin_token": "eyJpdiI6IjI5ZGgzSWRKQzdI…", "expires_at": "2026-09-30T03:00:52Z" } }
```

ابعته بـ`X-Pin-Token` مع التبويبات المحمية. خزّنه **بالذاكرة بس**، فبيروح لما ينسكّر التطبيق. صالح
`pin_unlock_hours` ساعة.

**أخطاء:**
- `422 PIN_INVALID` مع `attempts_remaining`. الخامسة بترجع `0`.
- بعدها `429 PIN_LOCKED` مع `retry_after_seconds` (15 دقيقة). الـPIN الصح بيصفّر العداد.

### 4.7 `PUT /merchant/pin` 🔒 PIN

تغيير الـPIN بالرمز الحالي. كل `pin_token` قديم على كل الأجهزة بيوقف، وهالجهاز بياخد واحد جديد.

| الحقل | النوع | مطلوب |
|---|---|---|
| `current_pin` | string | ✅ |
| `new_pin` | string | ✅ |

**`200`:** `{ "data": { "pin_token", "expires_at" } }`. **أخطاء:** `422 PIN_INVALID` للرمز الحالي الغلط
(بيتحسب من محاولات القفل)، و`403 PIN_REQUIRED`.

### 4.8 `POST /merchant/pin/reset`

لصاحب المحل يلي نسي الـPIN: بيطلع من Clerk وبيفوت من جديد، وبعدين بيحط رمز جديد **خلال 5 دقائق**.
الكاشير الفايت على جهاز المحل ما بيقدر.

| الحقل | النوع | مطلوب |
|---|---|---|
| `new_pin` | string | ✅ |

**`200`:** `{ "data": { "pin_token", "expires_at" } }`.

**أخطاء:**

```json
{ "error": { "code": "PIN_RESET_REQUIRES_RECENT_LOGIN", "message": "Sign in again to reset the PIN.", "details": { "max_age_seconds": 300 } } }
```

بالتطبيق: `signOut()` ثم `signIn` ثم `POST /merchant/pin/reset` مباشرة.

### 4.9 `GET /merchant/cards`

بطاقات المحل بدون تصفح، والفعّالة أول شي. **بدون PIN** لأن شاشة المسح بتستعمله: `?status=active`.

| الاستعلام | القيم |
|---|---|
| `status` | `active` أو `suspended` (اختياري) |

```json
{
  "data": [
    {
      "id": 11,
      "name": "بطاقة القهوة",
      "stamps_required": 3,
      "reward_description": "فنجان قهوة مجاني",
      "terms": "لا تُجمع مع عروض أخرى",
      "icon": { "id": 1, "key": "coffee-cup", "name": "فنجان قهوة" },
      "status": "active",
      "created_at": "2026-09-29T15:00:52Z",
      "suspended_at": null,
      "active_customers": 2,
      "rewards_ready": 1
    }
  ]
}
```

`active_customers`: زبائن إلهم دورة مفتوحة على البطاقة. `rewards_ready`: هدايا جاهزة ما انسلّمت.

### 4.10 `POST /merchant/cards` 🔒 PIN

نشر بطاقة. **ما بتتعدّل بعد النشر، وما في مسار تعديل**.

| الحقل | النوع | مطلوب | ملاحظة |
|---|---|---|---|
| `name` | string | ✅ | 2–60 |
| `stamps_required` | integer | ✅ | بين `limits.card_stamps_min` و`card_stamps_max` (3–10) |
| `reward_description` | string | ✅ | 2–120 |
| `terms` | string | — | لحد 500 |
| `icon_id` | integer | ✅ | من `lookups.icons` |

**`201`:** `{ "data": MerchantCard }` (نفس عنصر §4.9).

**أخطاء:**
- `422 CARDS_LIMIT_REACHED` مع `cards_limit`. البطاقات الموقوفة ما بتنحسب.
- `422 MERCHANT_STATUS_BLOCKS_ACTION` مع `{ "status": "EXPIRED", "action": "cards" }`.
- `422 VALIDATION_FAILED`.
- `403 PIN_REQUIRED`.

### 4.11 `POST /merchant/cards/{card_id}/suspend` 🔒 PIN

إيقاف نهائي، ما في رجعة:
- الزبائن الحاليين بيكمّلوا دوراتهم وبيستلموا هداياهم.
- ما في مشتركين جدد ولا دورات جديدة.
- التكرار بيرجّع البطاقة متل ما هي.

**`200`:** `{ "data": MerchantCard }` مع `status: "suspended"`. **أخطاء:** `404 NOT_FOUND` لبطاقة مو تبع
المحل.

### 4.12 `POST /merchant/scan/resolve`

معاينة ما قبل التأكيد، بعد مسح الرمز أو كتابة الرقم. **ما بتكتب شي بقاعدة البيانات.**
بترجع `scan_token` صالح **3 دقائق**، فالكاشير بيقدر يأكّد حتى لو رمز الزبون تجدد.

| الحقل | النوع | مطلوب | ملاحظة |
|---|---|---|---|
| `card_id` | integer | ✅ | البطاقة المختارة (فعّالة أو موقوفة) |
| `qr` | string | واحد منهم | المحتوى الخام من الكاميرا، مثل `W1.Q1Pa9zblIBzE.19687500` |
| `phone` | string | واحد منهم | رقم كتبه الكاشير |

**`200`** بعد مسح الرمز:

```json
{
  "data": {
    "scan_token": "eyJpdiI6IkpuUjlpTlcvdnhw…",
    "expires_at": "2026-09-29T15:03:52Z",
    "method": "qr",
    "customer": { "id": 18, "kind": "registered", "name": "سارة", "phone_full": null, "phone_masked": "0933***456" },
    "card": CardSummary,
    "cycle": { "id": null, "stamps_count": 0, "status": "COLLECTING", "completed_at": null },
    "action": "stamp",
    "blocked_reason": null,
    "other_ready_rewards": []
  }
}
```

**`action`:** الزر الوحيد بشاشة التأكيد.

| `action` | الزر | الطلب |
|---|---|---|
| `stamp` | «إضافة طابع» | `POST /merchant/stamps` |
| `redeem` | «تسليم الهدية» مع وصفها | `POST /merchant/redemptions` بـ`cycle.id` |
| `none` | ما في زر، اعرض `blocked_reason` | — |

**`customer.kind`:**

| `kind` | مين | شو بيظهر |
|---|---|---|
| `registered` | زبون مسجّل | `name` و`phone_masked` |
| `pending` | رقم انضافله طوابع قبل وما سجّل | `phone_full` بس |
| `new` | رقم ما إلو أي حساب (`id: null`) | `phone_full` بس، ليتأكد الكاشير من الرقم يلي كتبه |

الزبون المعلّق **ما بينعمل هون**، بينعمل عند تأكيد الطابع.

**`blocked_reason`** (مع `action: "none"`) هو كائن خطأ كامل:

```json
{
  "code": "STAMP_INTERVAL",
  "message": "Stamp interval not elapsed.",
  "details": {
    "last_stamp_at": "2026-09-29T15:00:52Z",
    "next_allowed_at": "2026-09-29T16:00:52Z",
    "minutes_since_last": 0,
    "minutes_remaining": 60
  }
}
```

| `code` | متى |
|---|---|
| `STAMP_INTERVAL` | الفاصل بين طابعين لنفس الزبون على نفس البطاقة ما خلص |
| `CARD_SUSPENDED` | بطاقة موقوفة وما في دورة مفتوحة للزبون |
| `MERCHANT_STATUS_BLOCKS_ACTION` | حالة الاشتراك بتمنع الطوابع: `{ "status": "EXPIRED", "action": "stamps" }` |
| `REDEEM_REQUIRES_QR` | في هدية جاهزة بس الزبون انكتب رقمه، والتسليم بيحتاج مسح رمزه |

**`other_ready_rewards`:** هدايا جاهزة لنفس الزبون على بطاقات تانية بالمحل، لتنبيه «عنده هدية على بطاقة
تانية». بتنسلّم بنفس `scan_token` إذا `method = qr`:

```json
"other_ready_rewards": [
  { "cycle_id": 17, "card": CardSummary, "completed_at": "2026-09-29T15:00:52Z" }
]
```

**أخطاء قبل ما ينعرف الزبون:**
- `422 QR_INVALID`.
- `422 QR_EXPIRED`.
- `404 NOT_FOUND` لبطاقة مو تبع المحل.
- `422 VALIDATION_FAILED` لما ما ينبعت `qr` ولا `phone`، أو ينبعتوا التنين سوا.

### 4.13 `POST /merchant/stamps`

تأكيد إضافة طابع. **طابع واحد بكل طلب**، ما في حقل كمية.

| الحقل | النوع | مطلوب | ملاحظة |
|---|---|---|---|
| `scan_token` | string | ✅ | من المعاينة |
| `client_uuid` | string (uuid) | ✅ | ولّده **مرة وحدة لكل ضغطة «تأكيد»**، وأعد استعماله نفسه إذا أعدت المحاولة |

**`201`** طابع جديد:

```json
{
  "data": {
    "stamp": { "id": 42, "stamped_at": "2026-09-29T15:00:52Z", "method": "qr" },
    "cycle": { "id": 17, "stamps_count": 1, "status": "COLLECTING", "completed_at": null },
    "card": CardSummary,
    "customer": { "id": 18, "kind": "registered", "name": "سارة", "phone_full": null, "phone_masked": "0933***456" }
  }
}
```

- **`200`** بنفس الشكل: الطلب انبعت قبل بنفس `client_uuid` (شبكة بطيئة أو ضغط مزدوج)، وهي النتيجة
  الأصلية. ما بينضاف طابع تاني.
- إذا اكتملت البطاقة بهالطابع: `cycle.status: "REWARD_READY"` مع `completed_at`.
- الرقم الجديد بيصير هون زبون معلّق: `customer.kind: "pending"` و`phone_masked`. بعد الحفظ الرقم دايماً
  مخفي.
- الخادم بيعيد فحص كل القواعد لحظة التأكيد، لأن جهاز تاني ممكن يكون أضاف طابع بعد المعاينة.
- الزبون المسجّل بيوصله إشعار بالطابع، وإشعار «هديتك جاهزة» إذا اكتملت البطاقة.

**أخطاء:**
- `422 SCAN_TOKEN_EXPIRED`: مرّت 3 دقائق أو التوكن لمحل تاني.
- `422 STAMP_INTERVAL` مع التفاصيل.
- `422 REWARD_READY_REDEEM_FIRST` مع `cycle_id`.
- `422 CARD_SUSPENDED`.
- `422 MERCHANT_STATUS_BLOCKS_ACTION`.

### 4.14 `POST /merchant/redemptions`

تأكيد تسليم الهدية. العملية ذرّية: إذا ضغط جهازين سوا، واحد بس بينجح.

| الحقل | النوع | مطلوب | ملاحظة |
|---|---|---|---|
| `scan_token` | string | ✅ | من معاينة **مسح رمز** (`method: qr`) |
| `cycle_id` | integer | ✅ | `cycle.id` من المعاينة، أو `cycle_id` من `other_ready_rewards` |

**`200`:**

```json
{
  "data": {
    "cycle_id": 17,
    "redeemed_at": "2026-09-29T15:00:52Z",
    "card": CardSummary,
    "next_cycle_available": true
  }
}
```

- `next_cycle_available: false` إذا البطاقة موقوفة، فما في دورة جديدة.
- مسموح بكل حالات الاشتراك، حتى المنتهي، لأن الهدية حق الزبون.
- الزبون بيوصله إشعار `reward_redeemed`.
- ممكن تسليم الهدية بنفس زيارة الطابع الأخير، وبنفس `scan_token`.

**أخطاء:**

```json
{ "error": { "code": "REWARD_ALREADY_REDEEMED", "message": "This reward was just handed over.", "details": { "cycle_id": 17, "redeemed_at": "2026-09-29T15:00:52Z" } } }
```

- `409 REWARD_ALREADY_REDEEMED`: جهاز تاني سلّمها للتو. اعرض «سُلّمت هذه الهدية للتو».
- `422 REDEEM_REQUIRES_QR`: التوكن من رقم مكتوب.
- `422 NO_REWARD_READY`.
- `404 NOT_FOUND`: الدورة مو لهالزبون أو هالمحل.
- `422 SCAN_TOKEN_EXPIRED`.

### 4.15 `GET /merchant/notifications` · `POST /merchant/notifications/{id}/read` · `POST /merchant/notifications/read-all`

صندوق إشعارات التاجر، بنفس شكل وتصفح إشعارات الزبون (§3.13–3.15). لسا ما في إشعارات بتنبعت للتاجر،
فالقائمة فاضية هلق:

```json
{ "data": [], "meta": { "next_cursor": null } }
```

### 4.16 `PUT /merchant/devices` · `DELETE /merchant/devices/{token}`

متل الزبون (§3.16–3.17): `{ token, platform }` ← **`204`**، والحذف بالرمز مرمَّز ← **`204`**. التاجر
بيبعت الحذف عند الخروج من Clerk.

### 4.17 `GET /merchant/customers/birthdays-today` 🔒 PIN

الزبائن المسجّلين يلي عيد ميلادهم اليوم بتوقيت دمشق، ومن زبائن المحل: إلهم دورة على أي بطاقة عنده، حتى لو
استلموا هديتها. **بدون سنة الميلاد.** مواليد 29 شباط بيطلعوا بـ28 شباط بالسنين غير الكبيسة.

```json
{
  "data": [
    { "id": 20, "name": "سارة", "birthday": "09-30", "greeted_today": true },
    { "id": 21, "name": "عمر", "birthday": "09-30", "greeted_today": false }
  ]
}
```

### 4.18 `POST /merchant/customers/{customer_id}/birthday-greeting` 🔒 PIN

إرسال تهنئة عيد ميلاد، يدوياً من التاجر.

| الحقل | النوع | مطلوب | ملاحظة |
|---|---|---|---|
| `message` | string | ✅ | نص التاجر، لحد 300 حرف، بدون روابط |
| `gift` | string | — | هدية مع التهنئة، لحد 60 حرف، بدون روابط |

**`201`:**

```json
{
  "data": {
    "customer_id": 20,
    "greeted_on": "2026-09-30",
    "message": "كل عام وأنت بخير! نورتينا اليوم.",
    "gift": "قهوة مجانية"
  }
}
```

- الزبون بيوصله إشعار `birthday_greeting`: العنوان «عيد ميلاد سعيد من كافيه الياسمين»، والنص
  «كل عام وأنت بخير! نورتينا اليوم.» وتحته سطر «هديتك: قهوة مجانية».
- **بتوصل لكل الزبائن،** حتى لمين موقّف عروض المحل أو كل العروض.
- مرة وحدة باليوم لكل زبون: الضغطة التانية بنفس اليوم بترجع **`200`** بالتهنئة الأولى، وما بينبعت شي
  جديد.
- ما بتنحسب من حد الحملات الأسبوعي.

**أخطاء:**
- `422 BIRTHDAY_NOT_TODAY`.
- `422 CAMPAIGN_CONTAINS_LINK` مع `details.field` (`message` أو `gift`):

```json
{ "error": { "code": "CAMPAIGN_CONTAINS_LINK", "message": "Links are not allowed in greetings.", "details": { "field": "message" } } }
```

- `422 MERCHANT_STATUS_BLOCKS_ACTION` مع `{ "status": "EXPIRED", "action": "campaigns" }`: للمحل المنتهي أو
  الموقوف أو بانتظار الحذف.
- `404 NOT_FOUND`: زبون ما إلو دورة عند المحل.
- `422 VALIDATION_FAILED`.
- `403 PIN_REQUIRED`.

---

## 5. لوحة الإدارة

المصادقة جلسة Clerk، والدور من جدول `admin_users`. مستخدم Clerk مو مربوط بحساب إدارة فعّال بياخد
`403 FORBIDDEN`.

### الأدوار والصلاحيات

الصلاحية بتنفحص **بالخادم** على كل طلب. اللوحة بتستعمل `permissions` من `me` بس لتخبّي الشاشات
والأزرار. العملية الممنوعة:

```json
{ "error": { "code": "FORBIDDEN", "message": "Your role does not allow this action." } }
```

| الصلاحية | Super Admin | Admin | مراجع المدفوعات | الدعم |
|---|---|---|---|---|
| `manage-admin-accounts` — حسابات الإدارة | ✓ | – | – | – |
| `cancel-stamps` — إلغاء طابع | ✓ | ✓ | – | – |

هدول الصلاحيتين يلي إلهم مسارات هلق. `me` بترجّع كل صلاحيات الدور.

### 5.1 `GET /admin/auth/me`

الحساب والدور والصلاحيات. بأول دخول لصاحب البريد، الحساب بينربط بحساب Clerk تلقائياً.

```json
{
  "data": {
    "id": 9,
    "name": "Doc Admin",
    "email": "doc-admin@wafa.test",
    "role": "super_admin",
    "is_active": true,
    "linked": true,
    "last_login_at": "2026-09-29T15:00:53Z"
  },
  "permissions": [
    "manage-admin-accounts", "manage-packages", "manage-settings", "grant-extensions", "view-audit-log",
    "view-financials", "suspend-merchants", "execute-deletion-requests", "cancel-stamps", "manage-lookups",
    "edit-business-identity", "edit-customer-birthdate", "view-merchants-and-customers", "reveal-customer-phone"
  ]
}
```

`role`: `super_admin` · `admin` · `payments_reviewer` · `support`.

### 5.2 `GET /admin/admin-users` 🔒 `manage-admin-accounts`

`{ "data": [ AdminUser, … ] }`، الفعّالة أول، مرتبة بالاسم.

### 5.3 `POST /admin/admin-users` 🔒 `manage-admin-accounts`

| الحقل | النوع | مطلوب | ملاحظة |
|---|---|---|---|
| `name` | string | ✅ | |
| `email` | string | ✅ | فريد، بدون فرق بالأحرف الكبيرة والصغيرة |
| `role` | string | ✅ | `super_admin` · `admin` · `payments_reviewer` · `support` |

**`201`:**

```json
{
  "data": {
    "id": 10,
    "name": "Payments Reviewer",
    "email": "reviewer@wafa.test",
    "role": "payments_reviewer",
    "is_active": true,
    "linked": false,
    "last_login_at": null
  }
}
```

`linked: false` لحد ما يفوت صاحب البريد على اللوحة بـClerk بنفس البريد.

### 5.4 `PATCH /admin/admin-users/{id}` 🔒 `manage-admin-accounts`

أي حقل من:
- `name`
- `role`
- `is_active`
- `email`، بس **قبل الربط**.

**`200`:** AdminUser.

ما حدا بيغيّر دوره أو بيعطّل حسابه بنفسه:

```json
{ "error": { "code": "VALIDATION_FAILED", "message": "You cannot change the role of your own account or deactivate it.", "details": { "fields": { "admin_user": ["invalid"] } } } }
```

### 5.5 `DELETE /admin/admin-users/{id}` 🔒 `manage-admin-accounts`

بيعطّل الحساب (`is_active: false`) وما بيحذفه، حتى يضل سجل التدقيق يدل على مين عمل شو. بيرجع يتفعّل
بـ`PATCH` مع `is_active: true`. **`200`:** AdminUser.

### 5.6 `POST /admin/stamps/{id}/cancel` 🔒 `cancel-stamps`

إلغاء طابع غلط بسبب مكتوب:
- الطابع ما بينحذف، بينعلّم ملغى.
- إذا كان هو يلي كمّل البطاقة، الدورة بترجع `COLLECTING`.
- العملية بتنكتب بسجل التدقيق.

| الحقل | النوع | مطلوب | ملاحظة |
|---|---|---|---|
| `reason` | string | ✅ | 3–255 |

**`200`:**

```json
{
  "data": {
    "id": 45,
    "cancelled_at": "2026-09-29T15:00:53Z",
    "cancel_reason": "أضيف للزبون الخطأ",
    "cycle": { "id": 18, "stamps_count": 0, "status": "COLLECTING", "completed_at": null }
  }
}
```

**أخطاء:**
- `409 REWARD_ALREADY_REDEEMED`: ممنوع بعد تسليم الهدية، لأنها طلعت من المحل.
- `422 VALIDATION_FAILED`: بدون سبب.

إلغاء نفس الطابع مرة تانية بيرجّعه متل ما هو.

---

## 6. توليد رمز QR بتطبيق الزبون

TOTP حسب RFC 6238، بـHMAC-SHA256، و8 أرقام، ونافذة `period_seconds`. الخادم مختبر مع متجهات الاختبار
الرسمية بالـRFC، فأي مكتبة TOTP قياسية بتعطي نفس الرمز.

```ts
import * as OTPAuth from 'otpauth';

// مرة وحدة بعد الدخول، وخزّنه بـexpo-secure-store
const { data: { data: qr } } = await api.get('/customer/me/qr');

const totp = new OTPAuth.TOTP({
  secret: OTPAuth.Secret.fromBase32(qr.secret),
  algorithm: 'SHA256',
  digits: 8,
  period: qr.period_seconds,
});

// بدون اتصال، كل ما تتغير النافذة
const qrContent = `W1.${qr.qr_id}.${totp.generate()}`;   // مثل W1.Q1Pa9zblIBzE.19687500
const secondsLeft = qr.period_seconds - (Math.floor(Date.now() / 1000) % qr.period_seconds);
```

- الخادم بيقبل النافذة الحالية والسابقة بس، فساعة الموبايل لازم تكون مضبوطة تقريباً.
- الرمز الأقدم لحد 30 دقيقة بيرجع `QR_EXPIRED`، وغير هيك `QR_INVALID`.
- الرمز ما فيه رقم ولا اسم، و`qr_id` عشوائي.

---

## 7. إعداد axios

### تطبيق الزبون

```ts
import axios from 'axios';
import * as SecureStore from 'expo-secure-store';

export const api = axios.create({
  baseURL: 'http://127.0.0.1:8000/api/v1',
  headers: { Accept: 'application/json' },
});

api.interceptors.request.use(async (config) => {
  const token = await SecureStore.getItemAsync('customer_token');
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

api.interceptors.response.use(undefined, async (error) => {
  const code = error.response?.data?.error?.code;
  if (code === 'UNAUTHENTICATED') { await SecureStore.deleteItemAsync('customer_token'); openPhoneScreen(); }
  if (code === 'PROFILE_INCOMPLETE') openProfileScreen();
  if (code === 'POLICY_CONSENT_REQUIRED') openPolicyScreen();
  return Promise.reject(error);
});
```

### تطبيق التاجر

```ts
import axios from 'axios';
import Constants from 'expo-constants';
import { Platform } from 'react-native';

let pinToken: string | null = null;   // بالذاكرة بس
export const setPinToken = (t: string | null) => { pinToken = t; };

export function createMerchantApi(getToken: () => Promise<string | null>) {
  const api = axios.create({ baseURL: 'http://127.0.0.1:8000/api/v1', headers: { Accept: 'application/json' } });

  api.interceptors.request.use(async (config) => {
    const token = await getToken();   // من useAuth() تبع @clerk/clerk-expo
    if (token) config.headers.Authorization = `Bearer ${token}`;
    config.headers['X-App-Version'] = Constants.expoConfig?.version;
    config.headers['X-App-Platform'] = Platform.OS;
    if (pinToken) config.headers['X-Pin-Token'] = pinToken;
    return config;
  });

  api.interceptors.response.use(undefined, (error) => {
    const e = error.response?.data?.error;
    if (e?.code === 'APP_VERSION_UNSUPPORTED') showForcedUpdate(e.details.download_url);
    if (e?.code === 'REGISTRATION_INCOMPLETE') openRegistration(e.details.registration_step);
    if (e?.code === 'PIN_REQUIRED') { pinToken = null; askForPin(); }
    return Promise.reject(error);
  });

  return api;
}

// تأكيد طابع: uuid واحد لكل ضغطة، ونفسه عند إعادة المحاولة
const clientUuid = Crypto.randomUUID();
await api.post('/merchant/stamps', { scan_token: preview.scan_token, client_uuid: clientUuid });
```

### لوحة الإدارة (Next.js)

```ts
'use client';
import axios from 'axios';
import { useAuth } from '@clerk/nextjs';

export function useAdminApi() {
  const { getToken } = useAuth();
  const api = axios.create({ baseURL: process.env.NEXT_PUBLIC_API_URL + '/api/v1', headers: { Accept: 'application/json' } });
  api.interceptors.request.use(async (config) => {
    const token = await getToken();
    if (token) config.headers.Authorization = `Bearer ${token}`;
    return config;
  });
  return api;
}
```

---

## 8. للخادم فقط

### `POST /webhooks/clerk`

**التطبيقات ما بتستعمله.** Clerk بيبعته لما مستخدم يغيّر بريده أو ينحذف، والطلب موقّع بتوقيع Svix
(`svix-id`، `svix-timestamp`، `svix-signature`).

| الحدث | الأثر |
|---|---|
| `user.updated` | البريد الأساسي الجديد بيتحدّث للتاجر ولحساب الإدارة المربوط |
| `user.deleted` | حساب الإدارة بينفك ربطه. **بيانات التاجر ما بتنلمس** (هدايا الزبائن حق إلهم)، بس بينكتب سطر بسجل التدقيق |

الرد `200` لأي حدث موقّع، و`400` للتوقيع الغلط أو الأقدم من 5 دقائق.

---

## 9. للتجريب محلياً

- **OTP:** مع `OTP_DRIVER=log` الرمز ما بينبعت على واتساب، بينكتب بـ`storage/logs/laravel.log`. وبـDocker:
  `docker compose logs -f app` (دوّر على `OTP code issued`).
- **رقم المراجع:**
  - `REVIEW_PHONE` و`REVIEW_OTP_CODE` بـ`.env` بيعطوا رقم برمز ثابت.
  - `php artisan db:seed --class=ReviewAccountSeeder` بيعطيه بطاقة قيد التجميع وهدية جاهزة.
- **Clerk:** `php artisan clerk:smoke-test` بيجرّب التسجيل والـPIN ولوحة الإدارة على نسخة Clerk الحقيقية.
- **الفاصل بين طابعين:** إعداد `stamp_interval_minutes` بجدول `settings` (افتراضياً 60). نزّله للتجريب
  السريع.
