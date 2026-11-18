# PromptEdit × Pixelforge

Users write a prompt, see the price before they generate, and watch the image arrive. Every generation is charged at Pixelforge's exact cost plus 25%. Failed generations are free.

Built with Laravel 13, Inertia 3 and Vue 3, on top of the official Vue starter kit.

## Running it

Needs PHP 8.4+ (developed on 8.5, with `pdo_mysql`), Composer, Node 22 and MySQL 8.

```bash
composer install && npm ci
cp .env.example .env && php artisan key:generate
# create the MySQL database, then fill in DB_* and PIXELFORGE_* in .env
php artisan migrate
npm run build
```

Three processes must run:

| Process                                                        | Command                                                                           |
| -------------------------------------------------------------- | --------------------------------------------------------------------------------- |
| Web app                                                        | nginx + php-fpm serving `public/` (or `php artisan serve` locally)                |
| Queue worker, which submits generations to Pixelforge          | `php artisan queue:work`                                                          |
| Scheduler, which polls generations whose webhooks went missing | `php artisan schedule:work`, or `php artisan schedule:run` from cron every minute |

Use a separate Pixelforge API key for each environment and set these in `.env`:

| Variable                    | What it is                               |
| --------------------------- | ---------------------------------------- |
| `PIXELFORGE_API_KEY`        | `pf_…` key from the Pixelforge dashboard |
| `PIXELFORGE_WEBHOOK_SECRET` | that key's `whsec_…` signing secret      |

In the Pixelforge dashboard, point the key's webhook URL at `https://<your host>/webhooks/pixelforge`.

To give someone access to the operators' report, have them register, then run `php artisan app:make-admin someone@example.com`.

Run the checks with `php artisan test`, `vendor/bin/phpstan analyse`, `npm run types:check` and `npm run check`. Tests run on in-memory SQLite by default. To run them against MySQL, use a separate empty database, because the tests wipe it: `DB_CONNECTION=mysql DB_DATABASE=promptedit_test php artisan test`.

## How a generation flows

```
Studio form ─▶ StartGeneration: price it, hold the maximum from the balance, save as "pending"
            └▶ SubmitGeneration job ─▶ POST /v1/generations
                  Idempotency-Key = our generation id, metadata.generation_id = our id
Pixelforge webhook ─▶ verify signature ─▶ record event id once ─▶ GenerationUpdater
generations:reconcile (every minute) ─▶ GET quiet generations ──────▶ GenerationUpdater
Studio page polls every 2s while anything is unfinished
```

`GenerationUpdater` is the only code that changes a generation's status or money. It locks the row and only ever moves the status forward (`pending → queued → processing → succeeded | failed`). Cost is recorded, and the user's hold settled, on the move to a final status, and a generation can only get there once.

## Decisions

**Money is whole nanodollars.** Pixelforge bills to 9 decimal places, so every amount is a `BIGINT` of 1e-9 USD wrapped in the `Usd` value object. Decimal strings are parsed without floats, and a rate with up to 3 decimals costs a whole number of nanodollars per token, so nothing is rounded.

**Users pay the exact amount, not a rounded cent.** Rounding each image up to a cent would turn a 25% markup into roughly 40–80% on low-quality images. At current rates, cost × 1.25 is always a whole nanodollar. If future rates ever produce half a nanodollar, `Markup` rounds up. The UI shows 4 decimal places, with the exact value on hover. The admin report shows all 9.

**The actual cost uses the generation's own `rates`.** Pixelforge fixes rates when a generation is created. Estimates use `GET /v1/pricing`, cached for 5 minutes.

**The estimate shows the likely price and the most it can cost.** Image and output tokens are exact. Text tokens are `ceil(chars / 4)` give or take 15%, and reasoning tokens fall in a range set by quality. "Expected" takes text at face value and reasoning mid-range. "At most" takes text +15% and reasoning at its maximum, and reproduces the docs' worked example (`0.373875000`). The server works out the price, so the formula lives in one place.

**Balances hold the maximum and settle at the actual cost.** Starting a generation locks the user row and holds its maximum price, so parallel requests can't overspend. On success the user gets back the hold minus the actual charge; on failure, the whole hold. A test checks that the balance always equals purchases − charges − holds. Credits are added by a fake checkout ($5, $10 or $25 packs).

**Webhooks are deduplicated twice.** The `webhook_events` primary key is Pixelforge's event id, and the insert sits in the same transaction as the update. A repeated delivery becomes a no-op, and if processing fails the transaction rolls back so Pixelforge's retry gets another go. Out-of-order events can't do damage either, because the status only moves forward. Webhooks are matched by `metadata.generation_id`, so one that arrives before our `POST` returns still finds its row.

**A timeout is never treated as a failure.** If the `POST` times out or returns an unexpected 5xx, Pixelforge may have created the generation and will bill us for it. That generation stays pending and is retried with the same Idempotency-Key, which either creates it or returns the existing one. Only errors that Pixelforge documents as "nothing was created" mark it failed.

**Limits.**

- Pixelforge allows 3 generations in flight per account, so the job waits for a free slot before calling the API.
- `429` and `503` responses are retried after `Retry-After`, with exponential backoff, up to 6 attempts. After that the generation fails, for free.
- The daily quota and `400`/`422` responses fail immediately.
- The form checks Pixelforge's own rules before sending anything.
- `POST /generations` is throttled to 10 a minute per user, which protects the shared daily quota.

**Missed webhooks are polled.** Every minute, `generations:reconcile` does two things:

- It fetches up to 10 generations that have been queued or processing for over 2 minutes. That stays well inside Pixelforge's 60 requests a minute.
- It resubmits anything still pending after 30 minutes.

**The admin report matches `/v1/usage` exactly.** Like Pixelforge, it groups by the UTC day each generation _completed_ and counts only succeeded generations. Each day is checked against `GET /v1/usage` (count and exact cost) and flagged when they differ.

**Other choices.**

- From the starter kit, only sign-up, log-in and log-out remain. Two-factor auth, passkeys, password reset, email verification and the settings pages were removed: the brief doesn't ask for them, and the demo has no mail server.
- Generations and credit purchases survive a deleted user (`user_id` is nulled), because billing records have to match Pixelforge's.
- Images aren't copied anywhere. Pixelforge's links expire after 24 hours, and the card then says so.

## Trying the failure paths

Put a Pixelforge keyword in the prompt: `[fail]`, `[blocked]`, `[overloaded]`, `[slow]`, `[duplicate]` or `[succeed]`.
