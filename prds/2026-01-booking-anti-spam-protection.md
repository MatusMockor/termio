# Public Booking Anti-Spam Protection PRD

**Created**: 2026-01-15
**Status**: Draft
**Owner**: Backend Team
**Target Release**: Q1 2026
**Document Type**: Technical Requirements

---

## 1. Goal

Implementovať viacvrstvovú ochranu proti spam-botom, automatizovaným útokom a fake rezerváciám na public booking endpoint (`/api/book/{slug}/create`). Cieľom je dosiahnuť **98%+ redukciu spam rezervácií** bez negatívneho vplyvu na legitímnych užívateľov.

---

## 2. Problém

### Aktuálny stav
- Public booking endpoint je nechránený
- Boti môžu vytvárať neobmedzený počet fake rezervácií
- Disposable emaily (10minutemail, guerrillamail) umožňujú anonymné rezervácie
- Žiadna verifikácia, či request pochádza od človeka

### Dopady
- Plný kalendár fake rezerváciami
- Zbytočné notifikácie majiteľom prevádzok
- Strata reálnych zákazníkov
- Poškodenie reputácie služby

---

## 3. Navrhované riešenie

### Viacvrstvová ochrana

```
┌─────────────────────────────────────────────────────────────────┐
│  VRSTVA 1: Rate Limiting                                        │
│  → Max 5 rezervácií / IP / hodina                               │
│  → Okamžité blokovanie pri prekročení                           │
├─────────────────────────────────────────────────────────────────┤
│  VRSTVA 2: Honeypot                                             │
│  → Skryté pole, ktoré boti vyplnia                              │
│  → Časová kontrola (min 3 sekundy na vyplnenie)                 │
├─────────────────────────────────────────────────────────────────┤
│  VRSTVA 3: Disposable Email Detection                           │
│  → Blokovanie 110,000+ známych disposable domén                 │
│  → Automatická synchronizácia zoznamu                           │
├─────────────────────────────────────────────────────────────────┤
│  VRSTVA 4: Google reCAPTCHA v3                                  │
│  → Neviditeľná verifikácia na pozadí                            │
│  → Score-based hodnotenie (threshold 0.5)                       │
└─────────────────────────────────────────────────────────────────┘
```

---

## 4. Technická špecifikácia

### 4.1 Rate Limiting

**Technológia**: Laravel built-in Rate Limiter

**Konfigurácia**:
```php
// AppServiceProvider.php
RateLimiter::for('public-booking', function (Request $request) {
    return Limit::perHour(5)->by($request->ip());
});
```

**Aplikácia**:
```php
// routes/api.php
Route::post('/create', [BookingController::class, 'create'])
    ->middleware('throttle:public-booking');
```

**Parametre**:
| Parameter | Hodnota | Zdôvodnenie |
|-----------|---------|-------------|
| Max requests | 5 / hodina | Rozumný limit pre legitímneho užívateľa |
| Identifikátor | IP adresa | Jednoduché, efektívne |
| Response code | 429 | Štandardná HTTP odpoveď |

**Response pri prekročení**:
```json
{
    "message": "Príliš veľa pokusov. Skúste to znova o chvíľu.",
    "retry_after": 3600
}
```

---

### 4.2 Honeypot

**Balík**: [spatie/laravel-honeypot](https://github.com/spatie/laravel-honeypot) v4.6.1+

**Inštalácia**:
```bash
composer require spatie/laravel-honeypot
php artisan vendor:publish --provider="Spatie\Honeypot\HoneypotServiceProvider" --tag=honeypot-config
```

**Konfigurácia** (`config/honeypot.php`):
```php
return [
    'enabled' => env('HONEYPOT_ENABLED', true),
    'name_field_name' => 'full_name_confirm',
    'valid_from_field_name' => 'valid_from',
    'amount_of_seconds' => 3,
    'respond_to_spam_with' => \App\Http\Responses\SpamResponse::class,
];
```

**Frontend integrácia** (React):

Endpoint `/api/book/{slug}/honeypot` vráti:
```json
{
    "enabled": true,
    "nameFieldName": "full_name_confirm",
    "validFromFieldName": "valid_from",
    "encryptedValidFrom": "eyJpdiI6Ik..."
}
```

React komponent:
```tsx
// HoneypotFields.tsx
interface HoneypotData {
  enabled: boolean;
  nameFieldName: string;
  validFromFieldName: string;
  encryptedValidFrom: string;
}

export function HoneypotFields({ data }: { data: HoneypotData }) {
  if (!data.enabled) return null;

  return (
    <div style={{ position: 'absolute', left: '-9999px' }} aria-hidden="true">
      <input
        type="text"
        name={data.nameFieldName}
        tabIndex={-1}
        autoComplete="off"
      />
      <input
        type="hidden"
        name={data.validFromFieldName}
        value={data.encryptedValidFrom}
      />
    </div>
  );
}
```

**Middleware**:
```php
// bootstrap/app.php - NIE globálne, len pre booking routes
```

```php
// routes/api.php
Route::post('/create', [BookingController::class, 'create'])
    ->middleware([
        'throttle:public-booking',
        \Spatie\Honeypot\ProtectAgainstSpam::class,
    ]);
```

**Custom Spam Response**:
```php
// app/Http/Responses/SpamResponse.php
namespace App\Http\Responses;

use Spatie\Honeypot\SpamResponder\SpamResponder;
use Illuminate\Http\Request;

class SpamResponse implements SpamResponder
{
    public function respond(Request $request, Closure $next)
    {
        return response()->json([
            'message' => 'Neplatný požiadavok.',
        ], 422);
    }
}
```

---

### 4.3 Disposable Email Detection

**Balík**: [erag/laravel-disposable-email](https://github.com/eramitgupta/laravel-disposable-email)

**Inštalácia**:
```bash
composer require erag/laravel-disposable-email
php artisan erag:install-disposable-email
```

**Registrácia providera** (`bootstrap/providers.php`):
```php
return [
    // ...
    \EragLaravelDisposableEmail\LaravelDisposableEmailServiceProvider::class,
];
```

**Konfigurácia** (`config/disposable-email.php`):
```php
return [
    'blacklist_file' => storage_path('app/blacklist_file'),
    'remote_url' => [
        'https://raw.githubusercontent.com/eramitgupta/disposable-email/main/disposable_email.txt',
    ],
    'cache_enabled' => true,
    'cache_ttl' => 1440, // 24 hodín
];
```

**Použitie v Request**:
```php
// app/Http/Requests/Booking/PublicCreateBookingRequest.php
public function rules(): array
{
    return [
        'client_email' => [
            'required',
            'email',
            'disposable_email', // Blokovanie disposable emailov
        ],
        // ... ostatné pravidlá
    ];
}
```

**Vlastné chybové hlášky** (`lang/sk/validation.php`):
```php
return [
    'disposable_email' => 'Dočasné emailové adresy nie sú povolené. Použite prosím váš bežný email.',
];
```

**Synchronizácia** (Scheduler):
```php
// routes/console.php
Schedule::command('erag:sync-disposable-email-list')->weekly();
```

---

### 4.4 Google reCAPTCHA v3

**Balík**: [josiasmontag/laravel-recaptchav3](https://github.com/josiasmontag/laravel-recaptchav3)

**Inštalácia**:
```bash
composer require josiasmontag/laravel-recaptchav3
```

**Konfigurácia** (`.env`):
```env
RECAPTCHA_V3_SITE_KEY=your_site_key
RECAPTCHA_V3_SECRET_KEY=your_secret_key
```

**Konfigurácia** (`config/recaptchav3.php`):
```php
return [
    'origin' => 'https://www.google.com/recaptcha',
    'sitekey' => env('RECAPTCHA_V3_SITE_KEY'),
    'secret' => env('RECAPTCHA_V3_SECRET_KEY'),
    'locale' => 'sk',
];
```

**Backend validácia**:
```php
// app/Http/Requests/Booking/PublicCreateBookingRequest.php
use Lunaweb\RecaptchaV3\Facades\RecaptchaV3;

public function rules(): array
{
    return [
        'recaptcha_token' => ['required', 'string'],
        'client_email' => ['required', 'email', 'disposable_email'],
        // ...
    ];
}

public function withValidator(Validator $validator): void
{
    $validator->after(function (Validator $validator) {
        $score = RecaptchaV3::verify($this->input('recaptcha_token'), 'booking');

        if ($score < 0.5) {
            $validator->errors()->add('recaptcha', 'Verifikácia zlyhala. Skúste to prosím znova.');
        }
    });
}
```

**Frontend integrácia** (React):

Inštalácia npm balíka:
```bash
npm install react-google-recaptcha-v3
```

Provider wrapper (`App.tsx`):
```tsx
import { GoogleReCaptchaProvider } from 'react-google-recaptcha-v3';

function App() {
  return (
    <GoogleReCaptchaProvider
      reCaptchaKey={import.meta.env.VITE_RECAPTCHA_SITE_KEY}
      language="sk"
      scriptProps={{
        async: true,
        defer: true,
      }}
    >
      <Routes />
    </GoogleReCaptchaProvider>
  );
}
```

Použitie v BookingPage:
```tsx
import { useGoogleReCaptcha } from 'react-google-recaptcha-v3';

export default function BookingPage() {
  const { executeRecaptcha } = useGoogleReCaptcha();

  const handleConfirm = async () => {
    if (!executeRecaptcha) {
      toast.error('reCAPTCHA nie je pripravená');
      return;
    }

    const recaptchaToken = await executeRecaptcha('booking');

    createBookingMutation.mutate({
      service_id: selectedService.id,
      // ... ostatné dáta
      recaptcha_token: recaptchaToken,
    });
  };
}
```

**Skrytie badge** (podľa Google pravidiel):

CSS:
```css
.grecaptcha-badge {
  visibility: hidden;
}
```

Povinný text v UI:
```tsx
<p className="text-xs text-slate-400 text-center mt-4">
  Táto stránka je chránená reCAPTCHA. Platia{' '}
  <a href="https://policies.google.com/privacy" className="underline">
    Zásady ochrany súkromia
  </a>{' '}
  a{' '}
  <a href="https://policies.google.com/terms" className="underline">
    Podmienky používania
  </a>{' '}
  Google.
</p>
```

---

## 5. API zmeny

### Nový endpoint: GET `/api/book/{slug}/honeypot`

**Response**:
```json
{
    "enabled": true,
    "nameFieldName": "full_name_confirm",
    "validFromFieldName": "valid_from",
    "encryptedValidFrom": "eyJpdiI6Ik5hQ2..."
}
```

### Upravený endpoint: POST `/api/book/{slug}/create`

**Nové povinné polia**:
| Pole | Typ | Popis |
|------|-----|-------|
| `recaptcha_token` | string | Token z reCAPTCHA v3 |
| `full_name_confirm` | string | Honeypot pole (musí byť prázdne) |
| `valid_from` | string | Encrypted timestamp |

**Nové chybové odpovede**:

| HTTP kód | Situácia |
|----------|----------|
| 422 | Disposable email detekovaný |
| 422 | reCAPTCHA score < 0.5 |
| 422 | Honeypot vyplnený alebo príliš rýchle odoslanie |
| 429 | Rate limit prekročený |

---

## 6. Súbory na vytvorenie/úpravu

### Backend (Laravel)

**Nové súbory**:
- `app/Http/Responses/SpamResponse.php`
- `config/disposable-email.php` (publish)
- `config/honeypot.php` (publish)
- `config/recaptchav3.php` (publish)

**Upravené súbory**:
- `app/Http/Requests/Booking/PublicCreateBookingRequest.php` - pridať validácie
- `app/Http/Controllers/Public/BookingController.php` - pridať honeypot endpoint
- `app/Providers/AppServiceProvider.php` - rate limiter
- `routes/api.php` - middleware
- `routes/console.php` - scheduler pre sync
- `bootstrap/providers.php` - registrácia providerov
- `.env.example` - nové premenné

### Frontend (React)

**Nové súbory**:
- `src/components/booking/HoneypotFields.tsx`
- `src/components/booking/RecaptchaNotice.tsx`

**Upravené súbory**:
- `src/App.tsx` - GoogleReCaptchaProvider
- `src/pages/booking/BookingPage.tsx` - integrácia všetkých ochranných prvkov
- `src/api/booking.ts` - nové polia a endpoint
- `.env.example` - VITE_RECAPTCHA_SITE_KEY

---

## 7. Testy

### Unit testy

```php
// tests/Unit/Rules/DisposableEmailRuleTest.php
- test_blocks_known_disposable_domains
- test_allows_legitimate_email_domains
- test_handles_edge_cases
```

### Feature testy

```php
// tests/Feature/Api/BookingSpamProtectionTest.php
- test_rate_limiting_blocks_excessive_requests
- test_honeypot_blocks_bot_submissions
- test_honeypot_blocks_fast_submissions
- test_disposable_email_rejected
- test_recaptcha_low_score_rejected
- test_legitimate_booking_passes_all_checks
```

---

## 8. Rollout plán

### Fáza 1: Rate Limiting (Okamžitá ochrana)
- Implementácia rate limitera
- Nasadenie do produkcie
- Monitorovanie 429 responses

### Fáza 2: Honeypot + Disposable Email
- Inštalácia balíkov
- Integrácia do frontendu
- Testovanie

### Fáza 3: reCAPTCHA v3
- Vytvorenie Google reCAPTCHA účtu
- Integrácia backendu a frontendu
- Nastavenie score threshold

### Fáza 4: Monitoring
- Logovanie zablokovaných pokusov
- Dashboard metrík
- Alerting pri anomáliách

---

## 9. Konfigurácia prostredia

### .env.example
```env
# Honeypot
HONEYPOT_ENABLED=true

# reCAPTCHA v3
RECAPTCHA_V3_SITE_KEY=
RECAPTCHA_V3_SECRET_KEY=

# Rate Limiting
BOOKING_RATE_LIMIT_PER_HOUR=5
```

### Frontend .env.example
```env
VITE_RECAPTCHA_SITE_KEY=
```

---

## 10. Metriky úspechu

| Metrika | Cieľ | Meranie |
|---------|------|---------|
| Spam redukcia | 98%+ | Porovnanie pred/po |
| False positive rate | <1% | Sťažnosti užívateľov |
| Latencia | <100ms overhead | APM monitoring |
| Konverzný pomer | Zachovaný | Analytics |

---

## 11. Riziká a mitigácie

| Riziko | Dopad | Mitigácia |
|--------|-------|-----------|
| False positives | Straty zákazníkov | Nízky reCAPTCHA threshold (0.5), WhiteList VIP domény |
| Rate limit pre zdieľané IP | Blokovanie kancelárií | Zvýšiť limit pre známe IP ranges |
| reCAPTCHA outage | Nemožnosť rezervácií | Fallback na honeypot-only mód |
| Disposable list zastaralý | Nové domény prejdú | Týždňová automatická synchronizácia |

---

## 12. Budúce rozšírenia

- [ ] Email verifikácia (double opt-in) pre high-value rezervácie
- [ ] Phone verification cez SMS OTP
- [ ] Machine learning na detekciu anomálií
- [ ] IP reputation database integrácia
- [ ] Cloudflare Turnstile ako alternatíva k reCAPTCHA
