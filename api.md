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
| 19 | `PUT /customer/merchants/{merchant_id}/mute` | زبون | توكن + جاهز |
| 20 | `DELETE /customer/merchants/{merchant_id}/mute` | زبون | توكن + جاهز |
| 21 | `GET /customer/directory` | زبون | توكن + جاهز |
| 22 | `GET /customer/merchants/{merchant_id}` | زبون | توكن + جاهز |
| 23 | `GET /customer/shop-contacts` | زبون | توكن + جاهز |
| | **تطبيق التاجر** | | |
| 24 | `GET /merchant/me` | تاجر | Clerk |
| 25 | `GET /merchant/lookups` | تاجر | Clerk |
| 26 | `POST /merchant/registration/business` | تاجر | Clerk |
| 27 | `POST /merchant/registration/package` | تاجر | Clerk |
| 28 | `POST /merchant/registration/pin` | تاجر | Clerk |
| 29 | `POST /merchant/pin/unlock` | تاجر | مسجّل |
| 30 | `PUT /merchant/pin` | تاجر | مسجّل + PIN |
| 31 | `POST /merchant/pin/reset` | تاجر | مسجّل + دخول حديث |
| 32 | `GET /merchant/cards` | تاجر | مسجّل |
| 33 | `POST /merchant/cards` | تاجر | مسجّل + PIN |
| 34 | `POST /merchant/cards/{card_id}/suspend` | تاجر | مسجّل + PIN |
| 35 | `POST /merchant/scan/resolve` | تاجر | مسجّل |
| 36 | `POST /merchant/stamps` | تاجر | مسجّل |
| 37 | `POST /merchant/redemptions` | تاجر | مسجّل |
| 38 | `GET /merchant/notifications` | تاجر | مسجّل |
| 39 | `POST /merchant/notifications/{id}/read` | تاجر | مسجّل |
| 40 | `POST /merchant/notifications/read-all` | تاجر | مسجّل |
| 41 | `PUT /merchant/devices` | تاجر | مسجّل |
| 42 | `DELETE /merchant/devices/{token}` | تاجر | مسجّل |
| 43 | `GET /merchant/customers/birthdays-today` | تاجر | مسجّل + PIN |
| 44 | `POST /merchant/customers/{customer_id}/birthday-greeting` | تاجر | مسجّل + PIN |
| 45 | `GET /merchant/subscription` | تاجر | مسجّل + PIN |
| 46 | `GET /merchant/payments` | تاجر | مسجّل + PIN |
| 47 | `POST /merchant/payments` | تاجر | مسجّل + PIN |
| 48 | `GET /merchant/campaigns` | تاجر | مسجّل + PIN |
| 49 | `POST /merchant/campaigns` | تاجر | مسجّل + PIN |
| 50 | `GET /merchant/stats` | تاجر | مسجّل + PIN |
| 51 | `GET /merchant/customers` | تاجر | مسجّل + PIN |
| 52 | `PATCH /merchant/profile` | تاجر | مسجّل + PIN |
| 53 | `POST /merchant/profile/logo` | تاجر | مسجّل + PIN |
| | **لوحة الإدارة** | | |
| 54 | `GET /admin/auth/me` | إدارة | حساب فعّال |
| 55 | `GET /admin/admin-users` | إدارة | `manage-admin-accounts` |
| 56 | `POST /admin/admin-users` | إدارة | `manage-admin-accounts` |
| 57 | `PATCH /admin/admin-users/{id}` | إدارة | `manage-admin-accounts` |
| 58 | `DELETE /admin/admin-users/{id}` | إدارة | `manage-admin-accounts` |
| 59 | `POST /admin/stamps/{id}/cancel` | إدارة | `cancel-stamps` |
| 60 | `GET /admin/packages` | إدارة | `manage-packages` |
| 61 | `POST /admin/packages` | إدارة | `manage-packages` |
| 62 | `PATCH /admin/packages/{id}` | إدارة | `manage-packages` |
| 63 | `GET /admin/settings/billing` | إدارة | `manage-settings` |
| 64 | `PATCH /admin/settings/billing` | إدارة | `manage-settings` |
| 65 | `GET /admin/payments` | إدارة | `review-payments` |
| 66 | `GET /admin/payments/{id}` | إدارة | `review-payments` |
| 67 | `POST /admin/payments/{id}/approve` | إدارة | `review-payments` |
| 68 | `POST /admin/payments/{id}/reject` | إدارة | `review-payments` |
| 69 | `POST /admin/merchants/{id}/trial-extension` | إدارة | `grant-extensions` |
| 70 | `GET /admin/merchants` | إدارة | `view-merchants-and-customers` |
| 71 | `GET /admin/merchants/{id}` | إدارة | `view-merchants-and-customers` |
| 72 | `PATCH /admin/merchants/{id}` | إدارة | `edit-business-identity` |
| 73 | `POST /admin/merchants/{id}/suspend` | إدارة | `suspend-merchants` |
| 74 | `POST /admin/merchants/{id}/reactivate` | إدارة | `suspend-merchants` |
| 75 | `POST /admin/cards/{id}/suspend` | إدارة | `suspend-merchants` |
| 76 | `GET /admin/customers` | إدارة | `view-merchants-and-customers` |
| 77 | `GET /admin/customers/{id}` | إدارة | `view-merchants-and-customers` |
| 78 | `PATCH /admin/customers/{id}` | إدارة | `edit-customer-birthdate` |
| 79 | `POST /admin/customers/{id}/reveal-phone` | إدارة | `reveal-customer-phone` |
| 80 | `GET /admin/card-cycles/{id}` | إدارة | `view-merchants-and-customers` |
| 81 | `GET /admin/business-types` | إدارة | `manage-lookups` |
| 82 | `POST /admin/business-types` | إدارة | `manage-lookups` |
| 83 | `PATCH /admin/business-types/{id}` | إدارة | `manage-lookups` |
| 84 | `GET /admin/icons` | إدارة | `manage-lookups` |
| 85 | `POST /admin/icons` | إدارة | `manage-lookups` |
| 86 | `PATCH /admin/icons/{id}` | إدارة | `manage-lookups` |
| 87 | `GET /admin/settings/app` | إدارة | `manage-settings` |
| 88 | `PATCH /admin/settings/app` | إدارة | `manage-settings` |
| 89 | `GET /admin/audit-logs` | إدارة | `view-audit-log` |
| | **للخادم فقط** | | |
| 90 | `POST /webhooks/clerk` | Clerk | توقيع Svix |

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

**مدة جلسة الزبون:** التوكن ما إلو مدة ثابتة، بس بيوقف إذا ما انستعمل 90 يوم (`CUSTOMER_TOKEN_IDLE_DAYS`). كل طلب
بيجدّد العداد، فالزبون يلي بيفتح التطبيق عادي ما بيحس بشي. التوكن المنتهي بياخد `401 UNAUTHENTICATED`، ورجّع
الزبون لشاشة الرقم. جلسات التاجر والإدارة تبع Clerk وما بتتأثر.

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
| `MERCHANT_STATUS_BLOCKS_ACTION` | 422 | `status`, `action` | حالة الاشتراك بتمنع العملية (`action`: `stamps` أو `cards` أو `campaigns` أو `payments`) |
| `CARDS_LIMIT_REACHED` | 422 | `cards_limit` | وصل حد البطاقات الفعّالة بالباقة |
| `BIRTHDAY_NOT_TODAY` | 422 | — | تهنئة لزبون مو عيد ميلاده اليوم |
| `CAMPAIGN_WEEKLY_LIMIT_REACHED` | 422 | `weekly_limit`, `resets_at` | خلصت حملات الأسبوع بالباقة |
| `CAMPAIGN_CONTAINS_LINK` | 422 | `field` | رابط بعنوان الحملة أو نصها، أو بنص التهنئة أو الهدية |
| `PAYMENT_ALREADY_PENDING` | 409 | `payment_id` | في دفعة سابقة لسا ما انحسمت |
| `PRICE_NOT_AVAILABLE` | 422 | — | ما في سعر لهالباقة بهالمدة |
| `KEEP_CARDS_REQUIRED` | 422 | `cards_limit`, `active_cards` | تخفيض لباقة أصغر بدون اختيار البطاقات يلي بتضل |
| `PAYMENT_NOT_PENDING` | 409 | `status` | لوحة الإدارة: الدفعة انحسمت قبل |
| `TRIAL_NOT_EXTENDABLE` | 422 | `reason` | لوحة الإدارة: ما في تجربة بتتمدد (`paid` أو `no_trial` أو `status`) |
| `MERCHANT_STATUS_CONFLICT` | 409 | `status` | لوحة الإدارة: حالة التاجر ما بتسمح بالإيقاف أو إعادة التفعيل |

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
| `campaign` | حملة من محل | العنوان «[المحل]: [عنوان الحملة]» والنص نص الحملة. `data`: `merchant_id` و`campaign_id` |
| `birthday_greeting` | تهنئة عيد ميلاد من محل | العنوان «عيد ميلاد سعيد من [المحل]»، والنص رسالة التاجر، ومعها سطر «هديتك: [الهدية]» إذا في هدية |
| `reward_redeemed` | استلم الهدية | «استلمت هديتك من [المحل]. بدأت بطاقتك الجديدة.» |

`data` فيها المعرّفات لتفتح البطاقة لما يضغط (للتهنئة: `merchant_id` بس).

**الإشعارات على الموبايل (FCM):** كل إشعار بينكتب بالصندوق فوراً، وبينبعت كمان push عن طريق Firebase Cloud
Messaging لكل أجهزة الحساب المسجّلة بـ`PUT /customer/devices`، أندرويد وiOS. الـpush بيطلع من الـqueue خلال ثواني،
وفيه نفس العنوان والنص يلي بالصندوق:

```json
{
  "notification": { "title": "هديتك جاهزة!", "body": "اكتملت بطاقتك في كافيه الياسمين. اعرض رمزك للكاشير لتستلم فنجان قهوة مجاني." },
  "data": {
    "type": "card_completed",
    "notification_id": "01a0edae-d3dc-710c-b3f7-4d688299b861",
    "merchant_id": "10",
    "card_id": "11",
    "cycle_id": "17"
  }
}
```

- **كل قيم `data` نصوص،** حوّل المعرّفات لأرقام بالتطبيق.
- `notification_id` هو `id` نفس الإشعار بالصندوق: لما يضغط عليه علّمه مقروء بـ`POST /customer/notifications/{id}/read`.
- أولوية عالية مع الصوت الافتراضي على أندرويد وiOS.
- الرمز يلي بيرجّع عليه FCM `UNREGISTERED` (التطبيق انشال) بينحذف لحاله.

### 3.14 `POST /customer/notifications/{id}/read` 🔒 جاهز

تعليم إشعار كمقروء. **`204`**. إشعار مو إلو ← `404`.

### 3.15 `POST /customer/notifications/read-all` 🔒 جاهز

تعليم الكل كمقروء. **`204`**.

### 3.16 `PUT /customer/devices` 🔒

تسجيل رمز FCM للجهاز، بعد الدخول وكل ما يتغيّر. إذا الرمز كان لحساب تاني بينتقل لهالحساب. على iOS كمان
ابعت رمز **FCM** (من مكتبة Firebase)، مو رمز APNs.

| الحقل | النوع | مطلوب | ملاحظة |
|---|---|---|---|
| `token` | string | ✅ | لحد 255 حرف |
| `platform` | string | ✅ | `android` أو `ios` |

**`204`**.

### 3.17 `DELETE /customer/devices/{token}` 🔒

إزالة رمز الجهاز عند الخروج. ابعت الرمز مرمَّز: `encodeURIComponent(token)`، لأن رموز FCM فيها `:`.
**`204`** حتى لو الرمز مو موجود.

### 3.18 `PUT /customer/merchants/{merchant_id}/mute` · `DELETE /customer/merchants/{merchant_id}/mute` 🔒 جاهز

إيقاف عروض محل واحد وإرجاعها. **`204`** بالحالتين، والتكرار ما بيغيّر شي.

- بيوقف **حملات هالمحل بس**. إشعارات الطوابع والهدايا بتضل توصل، وتهنئة عيد الميلاد كمان.
- بيبيّن فوراً بـ`merchant_muted` بـ«بطاقاتي» (§3.11).
- إيقاف عروض **كل** المحلات هو `campaigns_muted` بـ`PATCH /customer/me` (§3.6).
- **أخطاء:** `404 NOT_FOUND` لمحل مو موجود.

### 3.19 `GET /customer/directory` 🔒 جاهز

دليل المحلات كله دفعة وحدة، مرتب بالاسم. **البحث والفلترة على الموبايل**، فعبارات البحث ما بتوصل للخادم.

```json
{
  "data": [
    {
      "id": 10,
      "business_name": "كافيه الياسمين",
      "logo_url": null,
      "business_type": { "id": 1, "name": "كافيه" },
      "governorate": { "id": 1, "name": "دمشق" },
      "address": "شارع الحمرا",
      "active_cards_count": 2
    }
  ]
}
```

- **مين بيظهر:** المحل يلي حالته `TRIAL` أو `ACTIVE` أو `GRACE`، وعنده بطاقة فعّالة وحدة على الأقل. الزبون يلي بيروح على محل شافه بالدليل لازم ياخد طابع.
- **ETag:** الرد فيه ترويسة `ETag`. خزّنها وابعتها بـ`If-None-Match` بالمرة الجاية. إذا ما تغيّر شي بيرجع **`304`** بدون جسم، فاستعمل النسخة المخزّنة.

### 3.20 `GET /customer/merchants/{merchant_id}` 🔒 جاهز

صفحة المحل، من الدليل أو من تفاصيل بطاقة.

```json
{
  "data": {
    "id": 10,
    "business_name": "كافيه الياسمين",
    "logo_url": null,
    "business_type": { "id": 1, "name": "كافيه" },
    "governorate": { "id": 1, "name": "دمشق" },
    "address": "شارع الحمرا",
    "accepting_stamps": true,
    "muted": false,
    "cards": [ CardSummary ]
  }
}
```

- `cards`: البطاقات الفعّالة بس.
- `accepting_stamps`: `false` للمحل المنتهي أو الموقوف أو بانتظار الحذف.
- `muted`: الزبون موقّف عروض هالمحل (§3.18).
- **محل مو بالدليل** (منتهي مثلاً): الصفحة بتفتح بس إذا الزبون إلو دورة عنده، ومع `cards: []`. غير هيك **`404`**.

### 3.21 `GET /customer/shop-contacts` 🔒 جاهز

نافذة «المحلات المشتركة»: كل المحلات يلي شغّالة مع وفاء هلق، مع عناوينها وأرقام التواصل. **مو جزء من العقد**، فالدليل (§3.19) بيضل متل ما هو.

```json
{
  "data": [
    {
      "id": 10,
      "business_name": "كافيه الياسمين",
      "logo_url": null,
      "business_type": { "id": 1, "name": "كافيه" },
      "governorate": { "id": 1, "name": "دمشق" },
      "address": "شارع الحمرا",
      "contact_phone": "+963112223344"
    }
  ]
}
```

- **مين بيطلع:** المحل يلي حالته `TRIAL` أو `ACTIVE` أو `GRACE`. بعكس الدليل، بيطلع حتى لو ما عنده بطاقة فعّالة. الترتيب بالاسم.
- **`contact_phone`:** الرقم يلي المحل اختار ينشره من ملفه (§4.26)، موبايل أو أرضي، بصيغة `+963…`. ممكن يكون `null`. رقم تسجيل التاجر ما بيطلع أبداً.
- **ETag:** متل الدليل. ابعت `If-None-Match`، فإذا ما تغيّر شي بيرجع **`304`**.

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
      "contact_phone": null,
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

صندوق إشعارات التاجر، بنفس شكل وتصفح ونفس push إشعارات الزبون (§3.13–3.15).

| `type` | متى | النص |
|---|---|---|
| `birthdays_today` | كل يوم الساعة 9 الصبح بتوقيت دمشق، إذا في زبائن عيد ميلادهم اليوم | العنوان «أعياد ميلاد اليوم» والنص «اليوم عيد ميلاد 2 من زبائنك. هنّئهم من التطبيق.» (ولزبون واحد: «اليوم عيد ميلاد أحد زبائنك. هنّئه من التطبيق.») |
| `trial_ending` | قبل 3 أيام من نهاية التجربة، مرة وحدة | «تنتهي تجربتك المجانية بعد 3 أيام. اشترك لتكمل.» |
| `subscription_ending` | قبل 3 أيام من نهاية الاشتراك، مرة وحدة | «ينتهي اشتراكك بعد 3 أيام.» |
| `grace_started` | خلص الاشتراك المدفوع وبلّشت المهلة | «انتهى اشتراكك، ولديك مهلة 3 أيام قبل توقف الطوابع.» |
| `subscription_expired` | خلصت التجربة أو المهلة | «اشتراكك منتهٍ والطوابع متوقفة. تسليم الهدايا ما زال متاحاً.» |
| `payment_approved` | المراجع قبل الدفعة | «قُبلت دفعتك، واشتراكك فعّال حتى 2027/01/10.» |
| `payment_rejected` | المراجع رفض الدفعة | «لم تُقبل دفعتك: المبلغ ناقص.» |

- `data`: لـ`birthdays_today` فيها `count`. لإشعارات الاشتراك `period_id`، وللدفعات `payment_id` (والرفض كمان `reason`).
- عدد الأيام بالنص بيتبع الواقع: «يوم واحد» و«يومين» و«3 أيام».
- `birthdays_today` مرة وحدة باليوم، وما بيوصل للمحل يلي حالته ما بتسمح بالتهنئة (منتهي، موقوف، بانتظار الحذف).
- الأسماء ما بتطلع بالإشعار: بتنعرض بـ`GET /merchant/customers/birthdays-today` ورا الـPIN.

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

### 4.19 `GET /merchant/subscription` 🔒 PIN

تبويب الاشتراك: نفس `subscription` يلي بـ`me`، ومعها `periods`، كل الفترات الأحدث أول، حتى التجديد يلي لسا ما بلّش.

```json
{
  "data": {
    "status": "ACTIVE",
    "package": { "id": 2, "name": "المتوسطة", "cards_limit": 2, "weekly_campaigns_limit": 2 },
    "current_period": { "id": 12, "type": "trial", "package": { … }, "duration_months": null, "starts_at": "2026-09-26T12:39:10Z", "ends_at": "2026-10-10T12:39:10Z", "grace_ends_at": null },
    "trial_used": true,
    "days_remaining": 94,
    "capabilities": { … },
    "banner": null,
    "pending_payment": null,
    "periods": [
      { "id": 13, "type": "paid", "package": { … }, "duration_months": 3, "starts_at": "2026-10-10T12:39:10Z", "ends_at": "2027-01-10T12:39:10Z", "grace_ends_at": "2027-01-13T12:39:10Z" },
      { "id": 12, "type": "trial", "package": { … }, "duration_months": null, "starts_at": "2026-09-26T12:39:10Z", "ends_at": "2026-10-10T12:39:10Z", "grace_ends_at": null }
    ]
  }
}
```

بالمثال: التاجر دفع خلال التجربة. الحالة صارت `ACTIVE` فوراً، والفترة المدفوعة بتبلّش لما تخلص التجربة، فما خسر أيام.

**كيف بتمشي الحالة (بتتحدّث كل ساعة):**

| من | لـ | متى |
|---|---|---|
| `TRIAL` | `EXPIRED` | خلصت التجربة. ما في مهلة بعد التجربة |
| `TRIAL` · `GRACE` · `EXPIRED` | `ACTIVE` | انقبلت دفعة |
| `ACTIVE` | `GRACE` | خلص الاشتراك المدفوع: مهلة `grace_days` (افتراضياً 3) والطوابع شغّالة |
| `GRACE` | `EXPIRED` | خلصت المهلة |

- **`current_period`** هي الفترة يلي شغّالة هلق، وهي يلي بتحدد الباقة والحدود. التجديد المدفوع سلفاً ما بيغيّر شي قبل ما يبلّش.
- **`days_remaining` والشريط** بيعدّوا لآخر الاشتراك كله، مع التجديدات، فيلي جدّد بكير ما بيطلعله «ينتهي قريباً».
- **وين بتبلّش الفترة الجديدة لما تنقبل الدفعة:**
  - إذا الاشتراك شغّال أو بالمهلة: من تاريخ الانتهاء القديم. يلي بيدفع بكير ما بيخسر شي، ويلي بيدفع بالمهلة ما بيربح أيامها.
  - إذا منتهي: من يوم القبول.
  - **الترقية** (باقة ببطاقات أكتر): بتشتغل فوراً، وبتخلص بنفس التاريخ يلي كان رح يخلص فيه التجديد. يعني الباقة الأعلى بتغطي كمان الأيام المدفوعة.
  - **التخفيض** بيبلّش بس بالتجديد، يعني بعد ما تخلص الفترة الحالية.

### 4.20 `GET /merchant/payments` 🔒 PIN

سجل الدفعات، الأحدث أول، بالمؤشر (`?limit=20` و`?cursor=`): `{ "data": [ Payment, … ], "meta": { "next_cursor" } }`.

### 4.21 `POST /merchant/payments` 🔒 PIN

رفع إثبات دفع، بـ`multipart/form-data`. التاجر بيحوّل برّا التطبيق (المعلومات بـ`lookups.payment`)، وبعدين بيرفع الصورة هون.

| الحقل | النوع | مطلوب | ملاحظة |
|---|---|---|---|
| `package_id` | integer | ✅ | باقة فعّالة من `lookups.packages` |
| `duration_months` | integer | ✅ | `1` أو `3` أو `12` |
| `method` | string | ✅ | `syriatel_cash` أو `transfer` |
| `reference` | string | — | رقم العملية، لحد 100 حرف |
| `proof` | ملف | ✅ | صورة JPEG أو PNG أو WebP لحد 5 MB |
| `keep_card_ids[]` | integer[] | للتخفيض بس | البطاقات يلي بتضل فعّالة. بالـmultipart ابعتها `keep_card_ids[]` مكررة |

**`201`:**

```json
{
  "data": {
    "id": 1,
    "package": { "id": 2, "name": "المتوسطة", "cards_limit": 2, "weekly_campaigns_limit": 2 },
    "duration_months": 3,
    "price_usd": "46.00",
    "exchange_rate": "13000.0000",
    "amount_syp": "598000.00",
    "method": "syriatel_cash",
    "reference": "SC-778812",
    "proof_url": "http://…/storage/payments/proofs/UHur….png?expires=1791464950&signature=9bb3…",
    "status": "PENDING",
    "rejection_reason": null,
    "created_at": "2026-10-08T12:39:10Z",
    "reviewed_at": null
  }
}
```

- **الخادم بينسخ** السعر بالدولار وسعر الصرف والمبلغ بالليرة لحظة الرفع، وما بيقرأ أي مبلغ من الطلب. إذا تغيّر السعر بعدين، الدفعة بتضل على سعرها.
- `proof_url` رابط موقّع بيعيش 30 دقيقة. اطلبه من جديد إذا بدك تعرض الصورة.
- `status`: `PENDING` ← `APPROVED` أو `REJECTED`. و`rejection_reason`: `transfer_not_received` · `amount_short` · `unclear_image` · `invalid_proof`.
- **التخفيض:** إذا حد الباقة الجديدة أقل من البطاقات الفعّالة، لازم `keep_card_ids` (لحد الحد الجديد). باقي البطاقات بتتوقف لما تبلّش الفترة الجديدة، مو وقت الرفع.

**أخطاء:**
- `409 PAYMENT_ALREADY_PENDING` مع `payment_id`: في دفعة لسا ما انحسمت.
- `422 PRICE_NOT_AVAILABLE`.
- `422 KEEP_CARDS_REQUIRED` مع `{ "cards_limit": 1, "active_cards": 2 }`.
- `422 MERCHANT_STATUS_BLOCKS_ACTION` مع `action: payments`: للحساب الموقوف أو يلي بانتظار الحذف.
- `422 VALIDATION_FAILED`.
- `403 PIN_REQUIRED`.

### 4.22 `POST /merchant/campaigns` 🔒 PIN

إرسال حملة: عنوان ونص بس، بدون صور ولا روابط.

| الحقل | النوع | مطلوب | ملاحظة |
|---|---|---|---|
| `title` | string | ✅ | لحد `limits.campaign_title_max` (60) |
| `body` | string | ✅ | لحد `limits.campaign_body_max` (300) |

**`201`:**

```json
{
  "data": {
    "id": 7,
    "title": "عرض الخميس",
    "body": "القهوة التانية مجاناً كل يوم خميس.",
    "recipients_count": 2,
    "sent_at": "2026-10-07T12:00:00Z"
  }
}
```

**مين بيوصله (المتطلبات §7.3):**
- كل زبون مسجّل إلو دورة على أي بطاقة بالمحل، حتى لو استلم هديتها من زمان.
- **ما بيوصل** لمين موقّف كل العروض (`campaigns_muted`)، ولا لمين موقّف عروض هالمحل (§3.18)، ولا للزبون المعلّق.
- `recipients_count`: عدد المستلمين لحظة الإرسال.
- **الإرسال بالخلفية:** الرد بيرجع فوراً، والإشعارات بتطلع خلال ثواني (`worker`)، حتى لو المحل عنده آلاف الزبائن.
- الزبون بيوصله `campaign` بالعنوان «كافيه الياسمين: عرض الخميس».

**حد الأسبوع:** `weekly_campaigns_limit` تبع الباقة، والأسبوع من السبت للجمعة بتوقيت دمشق (`usage` بـ`me`). التهنئة ما بتنحسب منه.

**أخطاء:**
- `422 CAMPAIGN_WEEKLY_LIMIT_REACHED`:

```json
{ "error": { "code": "CAMPAIGN_WEEKLY_LIMIT_REACHED", "message": "This week's campaigns are used up.", "details": { "weekly_limit": 1, "resets_at": "2026-10-09T21:00:00Z" } } }
```

- `422 CAMPAIGN_CONTAINS_LINK` مع `details.field` (`title` أو `body`).
- `422 MERCHANT_STATUS_BLOCKS_ACTION` مع `{ "status": "EXPIRED", "action": "campaigns" }`.
- `422 VALIDATION_FAILED`.
- `403 PIN_REQUIRED`.

### 4.23 `GET /merchant/campaigns` 🔒 PIN

الحملات المرسلة، الأحدث أول، بالمؤشر. `meta` فيها كمان عدّاد الأسبوع:

```json
{
  "data": [ { "id": 7, "title": "عرض الخميس", "body": "…", "recipients_count": 2, "sent_at": "2026-10-07T12:00:00Z" } ],
  "meta": {
    "next_cursor": null,
    "campaigns_used_this_week": 1,
    "weekly_campaigns_limit": 1,
    "campaigns_resets_at": "2026-10-09T21:00:00Z"
  }
}
```

### 4.24 `GET /merchant/stats` 🔒 PIN

تبويب الإحصاءات: المجموع، ولكل بطاقة، وسلسلة يومية للرسم. **الطوابع الملغاة ما بتنحسب.** الأيام بتوقيت دمشق، والأسبوع من السبت.

| الاستعلام | القيم |
|---|---|
| `card_id` | اختياري: الإحصاءات لبطاقة وحدة. بطاقة مو تبع المحل ← `404` |
| `days` | `7` (الافتراضي) أو `30`: طول السلسلة اليومية |

```json
{
  "data": {
    "generated_at": "2026-10-06T22:30:00Z",
    "totals": {
      "customers_total": 2,
      "stamps_today": 2,
      "stamps_this_week": 3,
      "stamps_via_phone_today": 1,
      "rewards_ready": 1,
      "rewards_redeemed_total": 1
    },
    "per_card": [
      { "card_id": 11, "card_name": "القهوة", "status": "active", "customers_total": 2, "stamps_today": 2, "stamps_this_week": 3, "stamps_via_phone_today": 1, "rewards_ready": 1, "rewards_redeemed_total": 0 }
    ],
    "daily": [
      { "date": "2026-10-01", "stamps": 0, "stamps_via_phone": 0, "rewards_redeemed": 0 },
      …
      { "date": "2026-10-07", "stamps": 2, "stamps_via_phone": 1, "rewards_redeemed": 0 }
    ]
  }
}
```

| الحقل | شو بيعدّ |
|---|---|
| `customers_total` | الزبائن يلي إلهم دورة، والمعلّقين معهم |
| `stamps_today` · `stamps_this_week` | الطوابع غير الملغاة اليوم، ومن السبت |
| `stamps_via_phone_today` | طوابع اليوم يلي انضافت بكتابة الرقم |
| `rewards_ready` | هدايا جاهزة لسا ما انسلّمت |
| `rewards_redeemed_total` | كل الهدايا المسلّمة |

- `per_card`: كل بطاقات المحل، الفعّالة والموقوفة، ومع كل وحدة `card_id` و`card_name` و`status`.
- `daily`: يوم لكل عنصر، الأقدم أول، بطول `days`.

### 4.25 `GET /merchant/customers` 🔒 PIN

زبائن المحل: كل زبون إلو دورة على أي بطاقة، حتى المعلّق. **الأحدث نشاطاً بهالمحل أول**، ونشاطه بمحلات تانية ما بيأثر. بالمؤشر (`?limit=20` و`?cursor=`).

| الاستعلام | القيم |
|---|---|
| `q` | جزء من الاسم، 2–50 حرف. البحث بالرقم ما في لأنو الرقم مخفي |

```json
{
  "data": [
    {
      "id": 20,
      "name": "سارة",
      "phone_masked": "0933***456",
      "registered": true,
      "last_stamp_at": "2026-10-06T09:00:00Z",
      "cards": [
        { "card_id": 11, "card_name": "القهوة", "stamps_count": 3, "stamps_required": 5, "status": "COLLECTING" }
      ]
    },
    { "id": 21, "name": null, "phone_masked": "0944***111", "registered": false, "last_stamp_at": "…", "cards": [ … ] }
  ],
  "meta": { "next_cursor": null }
}
```

- `name: null` و`registered: false` للزبون المعلّق.
- `cards`: الدورات المفتوحة بس (`COLLECTING` أو `REWARD_READY`).
- `last_stamp_at`: آخر طابع غير ملغى بهالمحل.

### 4.26 `PATCH /merchant/profile` 🔒 PIN

ابعت الحقول يلي بدك تغيّرها بس. **اسم النشاط ونوعه والمحافظة ما بيتعدّلوا من التطبيق**، بس من الدعم.

| الحقل | النوع | ملاحظة |
|---|---|---|
| `owner_name` | string | 2–80 |
| `phone` | string | رقم سوري، فريد بين التجار (`taken` إذا مستعمل) |
| `address` | string \| null | لحد 255. `null` بيمسحه |
| `contact_phone` | string \| null | رقم التواصل يلي بيشوفه **كل الزبائن** بنافذة المحلات (§3.21). موبايل (`0944…`) أو أرضي (`011 222 3344`)، وبيرجع بصيغة `+963…`. `null` بيخبّيه |

**`200`:** `{ "data": MerchantProfile }`، نفس `merchant` بـ`GET /merchant/me`.

### 4.27 `POST /merchant/profile/logo` 🔒 PIN

تغيير الشعار، بـ`multipart/form-data` (`POST` لأنو PHP ما بيقرأ ملفات بـ`PATCH`).

| الحقل | النوع | مطلوب | ملاحظة |
|---|---|---|---|
| `logo` | ملف | ✅ | JPEG أو PNG أو WebP لحد 2 MB |

**`200`:** `{ "data": MerchantProfile }` مع `logo_url` الجديد. الشعار القديم بينمسح، والشعار الجديد بيبيّن على كل بطاقات المحل.

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
| `manage-packages` — الباقات والأسعار | ✓ | – | – | – |
| `manage-settings` — كل الإعدادات | ✓ | – | – | – |
| `review-payments` — قبول الدفعات ورفضها | – | – | ✓ | – |
| `grant-extensions` — تمديد التجربة | ✓ | – | – | – |
| `view-audit-log` — سجل التدقيق | ✓ | – | – | – |
| `view-merchants-and-customers` — عرض التجار والزبائن | ✓ | ✓ | – | ✓ |
| `edit-business-identity` — تعديل اسم النشاط ونوعه | ✓ | ✓ | – | ✓ |
| `suspend-merchants` — إيقاف تاجر أو بطاقة وإعادة التفعيل | ✓ | ✓ | – | – |
| `edit-customer-birthdate` — تعديل تاريخ ميلاد زبون | ✓ | ✓ | – | ✓ |
| `reveal-customer-phone` — عرض رقم الزبون كامل | ✓ | ✓ | – | ✓ |
| `manage-lookups` — أنواع النشاط والأيقونات | ✓ | ✓ | – | – |

هدول الصلاحيات يلي إلهم مسارات هلق. `me` بترجّع كل صلاحيات الدور.

**مبدأ (المتطلبات §5.1):** يلي بيقبل الدفعات ما بيعدّل أسعار ولا بيمدد، ولا حتى الـSuper Admin بيقبل دفعات بنفسه. إذا
صاحب المشروع بدو يراجع الدفعات، بياخد حساب تاني بدور مراجع المدفوعات.

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

كل عمليات القسم الجاي بتنكتب بسجل التدقيق مع «قبل وبعد»: مين، ومتى، وعلى أي تاجر أو باقة.

### 5.7 `GET /admin/packages` 🔒 `manage-packages`

كل الباقات حتى المعطّلة، مرتبة بـ`sort_order`:

```json
{
  "data": [
    {
      "id": 2,
      "name": "المتوسطة",
      "cards_limit": 2,
      "weekly_campaigns_limit": 2,
      "is_active": true,
      "sort_order": 2,
      "prices": [
        { "duration_months": 1, "price_usd": "18.00" },
        { "duration_months": 3, "price_usd": "46.00" },
        { "duration_months": 12, "price_usd": "163.00" }
      ]
    }
  ]
}
```

### 5.8 `POST /admin/packages` 🔒 `manage-packages`

| الحقل | النوع | مطلوب | ملاحظة |
|---|---|---|---|
| `name` | string | ✅ | 2–100، فريد |
| `cards_limit` | integer | ✅ | 1–20 |
| `weekly_campaigns_limit` | integer | ✅ | 0–20 |
| `is_active` | boolean | — | افتراضياً `true` |
| `sort_order` | integer | — | 0–1000 |
| `prices` | array | ✅ | `[{ "duration_months": 1, "price_usd": 20 }]`، المدد `1` و`3` و`12`، والسعر من 0.01 |

**`201`:** الباقة بنفس شكل §5.7. الباقة الفعّالة بتطلع فوراً بـ`GET /merchant/lookups`.

### 5.9 `PATCH /admin/packages/{id}` 🔒 `manage-packages`

أي حقل من §5.8. بـ`prices` كل مدة بتنبعت بتتعدّل، و`"price_usd": null` بيشيلها. المدد يلي ما انبعتت ما بتتغيّر.

```json
{ "prices": [ { "duration_months": 1, "price_usd": 18 }, { "duration_months": 12, "price_usd": null } ] }
```

- **ما في حذف للباقة:** `is_active: false` بيخبّيها من التطبيق، والفترات القديمة بتضل مربوطة فيها.
- **تغيير السعر** بيأثر على الدفعات الجاية بس، لأنو كل دفعة محفوظ فيها سعرها.
- **تغيير الحدود** (البطاقات والحملات) بيأثر فوراً على كل التجار يلي على الباقة.

### 5.10 `GET /admin/settings/billing` · `PATCH /admin/settings/billing` 🔒 `manage-settings`

يلي بتعرضه شاشة الدفع بتطبيق التاجر، ومدة التجربة والمهلة. بالتعديل ابعت الحقول يلي بدك تغيّرها بس.

```json
{
  "data": {
    "exchange_rate_syp": "13000.00",
    "syriatel_cash_number": "0933000111",
    "bank_transfer_details": "بنك سورية الدولي الإسلامي — حساب 12345",
    "payment_review_sla_hours": 24,
    "trial_days": 14,
    "grace_days": 3
  }
}
```

| الحقل | القيم |
|---|---|
| `exchange_rate_syp` | من 1 |
| `syriatel_cash_number` | لحد 30 حرف |
| `bank_transfer_details` | لحد 1000 حرف |
| `payment_review_sla_hours` | 1–168. بعدها الدفعة بتنعلّم متأخرة (`overdue`) |
| `trial_days` | 1–90، للتجارب الجديدة |
| `grace_days` | 0–30، للدفعات يلي بتنقبل بعدين |

### 5.11 `GET /admin/payments` 🔒 `review-payments`

طابور المراجعة: `?status=PENDING` (الافتراضي، الأقدم أول) أو `APPROVED` أو `REJECTED` (الأحدث أول). بالمؤشر متل باقي القوائم.
كل عنصر هو `Payment` (§4.21)، ومعه:

```json
{
  "merchant": { "id": 13, "business_name": "كافيه الياسمين", "email": "shop@example.com", "status": "TRIAL" },
  "overdue": false,
  "duplicate_reference": false,
  "reviewed_by": null,
  "subscription_period_id": null
}
```

- `overdue`: مرّت `payment_review_sla_hours` والدفعة لسا معلّقة.
- `duplicate_reference`: نفس رقم المرجع انستعمل بدفعة تانية. **انتبه:** ممكن يكون نفس الإيصال انرفع مرتين.
- **الصورة مو الدليل.** الدليل إنو المبلغ وصل للحساب، فطابق مع سيريتل كاش أو دفتر الحوالات قبل ما تقبل.

### 5.12 `GET /admin/payments/{id}` 🔒 `review-payments`

دفعة وحدة بنفس الشكل، مع `proof_url` جديد.

### 5.13 `POST /admin/payments/{id}/approve` 🔒 `review-payments`

بيعمل فترة مدفوعة حسب قواعد §4.19، وبيرجّع التاجر `ACTIVE`، وبيبعتله `payment_approved`. **`200`:** الدفعة مع
`status: "APPROVED"` و`reviewed_by` و`subscription_period_id`.

**أخطاء:** `409 PAYMENT_NOT_PENDING` مع `details.status`، إذا انحسمت قبل (حتى لو مراجعين ضغطوا سوا، واحد بس بينجح).

### 5.14 `POST /admin/payments/{id}/reject` 🔒 `review-payments`

| الحقل | النوع | مطلوب | القيم |
|---|---|---|---|
| `reason` | string | ✅ | `transfer_not_received` · `amount_short` · `unclear_image` · `invalid_proof` |

**`200`:** الدفعة مع `status: "REJECTED"`. التاجر بيوصله `payment_rejected` بالسبب، وبيقدر يرفع دفعة جديدة.
**أخطاء:** `409 PAYMENT_NOT_PENDING`.

### 5.15 `POST /admin/merchants/{id}/trial-extension` 🔒 `grant-extensions`

تمديد التجربة المجانية لتاجر **ما دفع أبداً**.

| الحقل | النوع | مطلوب | ملاحظة |
|---|---|---|---|
| `days` | integer | ✅ | 1–90 |
| `reason` | string | ✅ | 3–255، بينكتب بسجل التدقيق |

- الأيام بتنضاف لنهاية التجربة، أو لليوم إذا كانت خلصت. فالتاجر المنتهي بيرجع `TRIAL` فوراً.
- تذكير «تنتهي تجربتك» بيرجع ينبعت قبل النهاية الجديدة.

**`200`:** اشتراك التاجر بعد التمديد، بنفس شكل `subscription` بـ`GET /merchant/me`:

```json
{ "data": { "status": "TRIAL", "current_period": { "type": "trial", "ends_at": "2026-10-15T12:39:10Z", … }, "days_remaining": 7, … } }
```

**أخطاء:** `422 TRIAL_NOT_EXTENDABLE` مع `details.reason`:
- `paid`: التاجر دفع قبل.
- `no_trial`: ما أخد تجربة أصلاً، لأنو بريده أخد تجربة بحساب قديم.
- `status`: حسابه موقوف أو بانتظار الحذف.

```json
{ "error": { "code": "TRIAL_NOT_EXTENDABLE", "message": "This shop has paid; only a trial can be extended.", "details": { "reason": "paid" } } }
```

### 5.16 `GET /admin/merchants` 🔒 `view-merchants-and-customers`

قائمة التجار، الأحدث تسجيلاً أول، بالمؤشر. التاجر يلي لسا عم يسجّل بيطلع كمان، وحالته `null`.

| الاستعلام | القيم |
|---|---|
| `status` | `TRIAL` · `ACTIVE` · `GRACE` · `EXPIRED` · `SUSPENDED` · `PENDING_DELETION` · `DELETED` |
| `governorate_id` · `business_type_id` | معرّف من القوائم |
| `package_id` | الباقة يلي التاجر عليها **هلق** |
| `trial_ending` | `week`: تجربته بتخلص خلال 7 أيام |
| `q` | 2–100 حرف: جزء من اسم النشاط أو البريد، أو رقم الهاتف كامل بأي صيغة |
| `registration_step` | `package` (ما اختار باقة) · `pin` (ما حط PIN) · `done` |

```json
{
  "data": [
    {
      "id": 13,
      "business_name": "كافيه الياسمين",
      "owner_name": "أحمد",
      "email": "shop@example.com",
      "phone": "+963933111222",
      "logo_url": null,
      "business_type": { "id": 1, "name": "كافيه" },
      "governorate": { "id": 1, "name": "دمشق" },
      "status": "ACTIVE",
      "registration_step": "done",
      "package": { "id": 2, "name": "ذهبية", "cards_limit": 3, "weekly_campaigns_limit": 2 },
      "subscription_ends_at": "2026-11-01T12:00:00Z",
      "suspended_at": null,
      "suspension_reason": null,
      "registered_at": "2026-10-01T12:00:00Z"
    }
  ],
  "meta": { "next_cursor": null }
}
```

- `status: null` و`registration_step: "package"`: التاجر **لم يختر باقة بعد**، فاللوحة بتكتبها جنب اسمه. ما عنده اشتراك ولا بطاقات.
- `registration_step`: وين وقف التاجر بالتسجيل. `pin` يعني اختار باقة بس لسا ما حط PIN.
- `phone`: هاتف المحل كامل، لأنو رقم شغل مو رقم زبون.
- `package`: باقة الفترة الحالية، أو آخر باقة إذا الاشتراك منتهي.
- `subscription_ends_at`: نهاية آخر فترة، حتى لو التجديد مدفوع سلف.

### 5.17 `GET /admin/merchants/{id}` 🔒 `view-merchants-and-customers`

صفحة التاجر: نفس عنصر القائمة، ومعه:

```json
{
  "data": {
    "…": "حقول §5.16",
    "address": "شارع الحمرا",
    "last_login_at": "2026-10-06T09:00:00Z",
    "subscription": { "status": "ACTIVE", "days_remaining": 25, "…": "نفس subscription بـGET /merchant/me" },
    "periods": [ SubscriptionPeriod ],
    "payments": [ Payment ],
    "cards": [
      { "id": 11, "name": "القهوة", "icon_key": "coffee", "stamps_required": 5, "reward_description": "قهوة مجانية", "terms": null, "status": "active", "customers_count": 42, "created_at": "…", "suspended_at": null }
    ],
    "stats": { "customers_total": 42, "stamps_total": 310, "stamps_via_phone_total": 25, "rewards_ready": 3, "rewards_redeemed_total": 51, "campaigns_total": 4 }
  }
}
```

- `registered_at` واضح، لأنو تصحيح الاسم بيصير بأول 7 أيام. هي قاعدة تشغيلية، والخادم ما بيفرضها.
- `periods`: كل الفترات، الأحدث أول. `payments`: آخر 10 دفعات، وكلهم بشاشة الدفعات.
- `cards`: الفعّالة والموقوفة.
- `stats`: من أول ما فتح المحل، والطوابع الملغاة ما بتنحسب.

### 5.18 `PATCH /admin/merchants/{id}` 🔒 `edit-business-identity`

تصحيح اسم النشاط أو نوعه، يلي ما بيتعدّلوا من التطبيق. ابعت الحقول يلي بدك تغيّرها بس.

| الحقل | النوع | مطلوب | ملاحظة |
|---|---|---|---|
| `business_name` | string | – | 2–80 |
| `business_type_id` | integer | – | نوع فعّال |
| `reason` | string | ✅ | 3–255، بينكتب بسجل التدقيق |

**`200`:** صفحة التاجر (§5.17). كل تغيير بينسجّل (`merchant.identity_updated`) مع القيمة قبل وبعد.

### 5.19 `POST /admin/merchants/{id}/suspend` · `POST /admin/merchants/{id}/reactivate` 🔒 `suspend-merchants`

إيقاف التاجر لاحتيال أو مخالفة، وإعادة تفعيله. الجسم `{ "reason": "…" }` (3–255) إلزامي بالاثنين.

- **الإيقاف:** من أي حالة إلا `SUSPENDED` و`PENDING_DELETION` و`DELETED`. التاجر الموقوف ما بيقدر يضيف طوابع ولا زبائن جداد ولا يبعت حملات، وبيختفي من الدليل، **بس تسليم الهدايا بيضل شغّال**.
- **إعادة التفعيل:** من `SUSPENDED` بس. الحالة بترجع للحالة يلي بتحددها التواريخ هلق: إذا الاشتراك خلص وقت الإيقاف، بيرجع `EXPIRED` مو `ACTIVE`.
- كل وحدة بتنسجّل بسجل التدقيق مع السبب.

**`200`:** اشتراك التاجر بعد التغيير، بنفس شكل `subscription` بـ`GET /merchant/me`.

**أخطاء:** `409 MERCHANT_STATUS_CONFLICT` مع `details.status`، إذا الحالة ما بتسمح.

```json
{ "error": { "code": "MERCHANT_STATUS_CONFLICT", "message": "Only a suspended shop can be reactivated.", "details": { "status": "ACTIVE" } } }
```

### 5.20 `POST /admin/cards/{id}/suspend` 🔒 `suspend-merchants`

إيقاف بطاقة محتواها مو مناسب. الجسم `{ "reason": "…" }` إلزامي. البطاقة بتنوقف متل ما التاجر بيوقفها: ما في طوابع ولا زبائن جداد، والهدايا المستحقة بتنسلّم عادي.
إذا كانت موقوفة من قبل، ما بيصير شي.

```json
{ "data": { "id": 11, "merchant_id": 13, "name": "القهوة", "status": "suspended", "suspended_at": "2026-10-09T10:00:00Z" } }
```

### 5.21 `GET /admin/customers?phone=` 🔒 `view-merchants-and-customers`

البحث عن زبون **بالرقم كامل** بس (`0933…` أو `+963933…`). ما في تصفّح لكل الزبائن. بيرجّع قائمة فيها زبون واحد أو فاضية،
وكل عنصر بنفس شكل §5.22 بدون `cycles`. رقم ناقص بيرجع `422` (`phone: format`).

### 5.22 `GET /admin/customers/{id}` 🔒 `view-merchants-and-customers`

```json
{
  "data": {
    "id": 20,
    "name": "سارة",
    "phone_masked": "0933***222",
    "birthdate": "1995-04-20",
    "registered": true,
    "registered_at": "2026-09-01T10:00:00Z",
    "last_activity_at": "2026-10-06T09:00:00Z",
    "campaigns_muted": false,
    "created_at": "2026-08-20T10:00:00Z",
    "cycles": [
      {
        "id": 300,
        "merchant": { "id": 13, "business_name": "كافيه الياسمين" },
        "card": { "id": 11, "name": "القهوة", "status": "active" },
        "stamps_count": 3,
        "stamps_required": 5,
        "status": "COLLECTING",
        "started_at": "…",
        "completed_at": null,
        "redeemed_at": null
      }
    ]
  }
}
```

- **الرقم مخفي دايماً.** الرقم الكامل بس من §5.24.
- `cycles`: كل الدورات، الأحدث نشاطاً أول، والمسلّمة معها.
- الزبون المحذوف بيرجع `404`. ما في حجب للزبائن، لأنو الزبون ما بيقدر يأذي حدا (المتطلبات §5.4).

### 5.23 `PATCH /admin/customers/{id}` 🔒 `edit-customer-birthdate`

تصحيح تاريخ الميلاد، بعد التأكد من هوية الزبون.

| الحقل | النوع | مطلوب | ملاحظة |
|---|---|---|---|
| `birthdate` | `YYYY-MM-DD` | ✅ | قبل اليوم |
| `reason` | string | ✅ | 3–255 |

**`200`:** صفحة الزبون (§5.22). التغيير بينسجّل (`customer.birthdate_updated`).
**أخطاء:** `422 UNDER_AGE` إذا العمر أقل من 13.

### 5.24 `POST /admin/customers/{id}/reveal-phone` 🔒 `reveal-customer-phone`

زر «عرض الرقم كاملاً». **كل ضغطة بتنسجّل بسجل التدقيق** (`customer.phone_revealed`): مين وإيمتى وعلى أي زبون.

```json
{ "data": { "phone": "+963933111222" } }
```

### 5.25 `GET /admin/card-cycles/{id}` 🔒 `view-merchants-and-customers`

الدورة مع كل طوابعها، والملغاة معها. من هون بينلغى الطابع الغلط (§5.6).

```json
{
  "data": {
    "id": 300,
    "customer_id": 20,
    "merchant": { "id": 13, "business_name": "كافيه الياسمين" },
    "card": { "id": 11, "name": "القهوة" },
    "stamps_count": 1,
    "stamps_required": 5,
    "status": "COLLECTING",
    "started_at": "…",
    "completed_at": null,
    "redeemed_at": null,
    "stamps": [
      { "id": 900, "method": "phone", "stamped_at": "…", "cancelled_at": null, "cancel_reason": null, "cancelled_by": null },
      { "id": 901, "method": "qr", "stamped_at": "…", "cancelled_at": "…", "cancel_reason": "أضيف للزبون الخطأ", "cancelled_by": { "id": 2, "name": "سامر" } }
    ]
  }
}
```

### 5.26 أنواع النشاط والأيقونات 🔒 `manage-lookups`

`GET` · `POST /admin/business-types` · `PATCH /admin/business-types/{id}`، ونفسها لـ`/admin/icons`.

```json
{ "data": [ { "id": 4, "key": "coffee-cup", "name": "فنجان", "sort_order": 3, "is_active": true } ] }
```

| الحقل | النوع | ملاحظة |
|---|---|---|
| `name` | string | 2–100، فريد. مطلوب بالإنشاء |
| `sort_order` | integer | 0–65535 |
| `is_active` | boolean | `false` بيخبّيه من التطبيقات |
| `key` | string | **للأيقونات بس، وبالإنشاء بس**: أحرف صغيرة وأرقام و`-` و`_`، فريد. التطبيقات بترسم الأيقونة منه، فما بيتغيّر بعدين |

- **ما في حذف.** التعطيل بدل الحذف، لأنو في محلات وبطاقات قديمة بتأشّر عليه.
- القائمة فيها المعطّل كمان، مرتبة بـ`sort_order`. الإنشاء بيرجع `201`.

### 5.27 `GET /admin/settings/app` · `PATCH /admin/settings/app` 🔒 `manage-settings`

باقي الإعدادات (المتطلبات §5.5). بالتعديل ابعت الحقول يلي بدك تغيّرها بس. التطبيقات بتقرأها من `lookups` و`config`، فالتغيير ما بيحتاج إصدار جديد.

```json
{
  "data": {
    "stamp_interval_minutes": 60,
    "card_stamps_min": 3,
    "card_stamps_max": 10,
    "qr_period_seconds": 60,
    "campaign_title_max": 60,
    "campaign_body_max": 300,
    "merchant_min_app_version": "1.0.0",
    "merchant_app_download_url": "https://…/wafa-merchant.apk",
    "privacy_policy_url": "https://…",
    "customer_terms_url": "https://…",
    "merchant_terms_url": "https://…",
    "pin_unlock_hours": 12
  }
}
```

| الحقل | القيم |
|---|---|
| `stamp_interval_minutes` | 0–1440: أقل وقت بين طابعين لنفس الزبون على نفس البطاقة |
| `card_stamps_min` · `card_stamps_max` | 1–50. الأدنى ما بيتجاوز الأعلى (`invalid`)، حتى لو بعتت وحدة بس |
| `qr_period_seconds` | 15–600: عمر رمز QR |
| `campaign_title_max` · `campaign_body_max` | 10–255 · 20–2000 |
| `merchant_min_app_version` | `X.Y.Z`. الأقدم منها بياخد `426` مع رابط التنزيل |
| `merchant_app_download_url` · `*_url` | `https` بس، لحد 500 |
| `pin_unlock_hours` | 1–72 |

كل تعديل بينسجّل (`settings.app_updated`) مع القيم قبل وبعد.

### 5.28 `GET /admin/audit-logs` 🔒 `view-audit-log`

سجل التدقيق، الأحدث أول، بالمؤشر.

| الاستعلام | القيم |
|---|---|
| `action` | مثلاً `payment.approved` · `trial.extended` · `stamp.cancelled` · `merchant.suspended` · `customer.phone_revealed` |
| `admin_user_id` | مين عمل العملية |
| `subject_type` + `subject_id` | على شو: `merchant` · `customer` · `payment` · `stamp` · `card` · `package` · `admin`. `subject_id` بدون `subject_type` بيرجع `422` |

```json
{
  "data": [
    {
      "id": 51,
      "action": "merchant.suspended",
      "admin": { "id": 1, "name": "عبد" },
      "subject_type": "merchant",
      "subject_id": 13,
      "before": { "status": "ACTIVE" },
      "after": { "status": "SUSPENDED", "reason": "طوابع وهمية" },
      "ip_address": "10.0.0.1",
      "created_at": "2026-10-09T10:00:00Z"
    }
  ],
  "meta": { "next_cursor": null }
}
```

`admin: null` للعمليات يلي عملها النظام لحالو، متل webhook من Clerk.

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
- **Push:** `php artisan push:test <رمز-FCM>` بيبعت إشعار تجريبي لجهاز مباشرة. بيحتاج `FIREBASE_CREDENTIALS`،
  والإرسال العادي بيحتاج `php artisan queue:work` (أو خدمة `worker` بـDocker).
- **Clerk:** `php artisan clerk:smoke-test` بيجرّب التسجيل والـPIN ولوحة الإدارة على نسخة Clerk الحقيقية.
- **الفاصل بين طابعين:** إعداد `stamp_interval_minutes` بجدول `settings` (افتراضياً 60). نزّله للتجريب
  السريع.
