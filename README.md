# PickDash — Live multi-account faucet monitor

## Nima qiladi
- Barcha **tonpick / tronpick** hisoblarni bitta saytda ko‘rsatadi
- Balance, claim/fail, captcha, keyingi claim vaqti
- Ustiga bosib batafsil panel
- Live yangilanish (5 soniya)
- Bot `api.php` ga POST qiladi
- DB: **SQLite** (docker) yoki **PostgreSQL** (Render `DATABASE_URL`)
- Ustun yo‘q bo‘lsa **avtomatik yaratadi** (`ensureColumn`)

## Lokal Docker
```bash
cd pickdash
docker compose up -d --build
# http://localhost:8080
```

## Render
1. PostgreSQL qo‘shing → `DATABASE_URL` avtomatik
2. Web Service: Dockerfile shu papkadan
3. ENV:
   - `DASH_API_KEY=uzun-sirli-kalit`
   - `DATABASE_URL` (Render beradi)

## Bot ENV
```env
DASH_URL=https://YOUR-DASH.onrender.com/api.php
DASH_API_KEY=uzun-sirli-kalit
```

## Botga ulash (tonpick.php / tronpick.php)
Claim success dan keyin:
```php
require_once __DIR__ . '/DashReporter.php';
DashReporter::claim('tonpick', $d['username'], $d['balance'], $capName, $msg, $totalWait, [
  'claims_today' => $stats['claims_today'],
  'fails_today' => $stats['fails_today'],
  'total_claims' => $stats['total_claims'],
  'api_balance' => $this->solver->balance(),
]);
```

Fail da:
```php
DashReporter::fail('tonpick', $user, $msg, $capName);
```

## API misol
```bash
curl -X POST 'https://DASH/api.php?key=SECRET' \
  -H 'Content-Type: application/json' \
  -d '{"action":"claim","site":"tonpick","username":"test","balance":"0.01","captcha":"IconCaptcha","message":"OK","next_claim_seconds":3700}'
```
