# Circuit Breaker Pattern

Status: **NOT IMPLEMENTED** — documented here so the decision to add it later is
deliberate and the shape is agreed before code exists.

## When to use

Any external API that can fail intermittently and is called from the request
path. A circuit breaker protects the app from a *down* dependency dragging
down *every* request: instead of every request waiting on the full timeout
against a dead service, the breaker opens and requests fail fast without ever
touching the network.

Candidates in this project:

- **Stripe** (`payments-stripe` gateway: `authorizeDeposit`, `captureDeposit`,
  `releaseDeposit`, `refund`, `syncAuthorizationStatus`)
- **Email** (`App\Mail\*` via the queued Mailable/listener pipeline)
- Future SMS/notification providers
- (Meilisearch already has its own resilience path — see below — and is a
  *read* dependency with a database fallback, so it is not a breaker candidate.)

## How it works

Track failures in cache (Redis/file). The standard three-state machine:

1. **Closed** (normal): every call goes through. Each failure increments a
   counter. After **N failures in T seconds** (e.g. 5 failures in 60s), the
   circuit **opens**.
2. **Open**: subsequent calls **fail fast** — they return an error/fallback
   immediately without hitting the external service. After a **cooldown**
   (e.g. 30s), the circuit transitions to **half-open**.
3. **Half-open**: exactly **one** trial request is allowed through. If it
   succeeds, the circuit **closes** (resets the counters). If it fails, the
   circuit **opens** again.

Failure classification matters: a **4xx** (e.g. Stripe card declined) is the
*correct* outcome of a well-formed call and must **not** count toward opening
the circuit. Only **5xx / network / timeout** errors (the service is down or
broken) should count. Log every state transition — an open circuit is an
operational signal, not just an implementation detail.

## Where to apply

### StripeGateway (highest value)

`payments-stripe`'s `StripeGateway` is the prime candidate: authorize/capture/
release/refund all hit `api.stripe.com`, and a Stripe outage would otherwise
turn every booking-confirmation request into a slow, doomed wait. Wrap the
gateway's Stripe SDK calls in a breaker keyed per-operation family (a
breakdown in `authorizeDeposit` shouldn't open the circuit for `refund`), and
surface the open-circuit state as a typed failure the checkout flow already
handles (`PaymentAuthorized`/`PaymentFailed` paths).

### Email sending

The confirmation emails are queued (`ShouldQueue`), so a dead mail provider
doesn't block the request path — but a breaker still prevents the queue worker
from hammering a down provider with retries. Add if/when send volume grows or
a synchronous send path is introduced.

### Future SMS/notification gateway

Same shape as Stripe: build the breaker in from the start.

## Meilisearch: why it is deliberately NOT a breaker candidate

The search suggestions endpoint already implements **fail-fast + fallback**
(`SearchController::suggestions`): a 5s timeout on the Meilisearch client
(see `config/scout.php` → `meilisearch.timeout`, wired in
`AppServiceProvider::boot()`) and a `catch (\Throwable)` that falls back to a
raw database `LIKE` query. The search box always works regardless of
Meilisearch availability. A circuit breaker would add no benefit here — the
fallback is instant and local.

## Current state

**Not implemented.** What exists today, and why it is *basic* resilience
rather than a breaker:

- **Stripe lazy client** (`StripeGateway` builds `StripeClient` on first use,
  not at boot) — an empty/misconfigured key can't take down the whole site.
- **Checkout try/catch** — a failed hold is caught and surfaced as a booking
  failure, not a crash.

Neither limits the *cost* of repeated calls to a down Stripe (every request
still waits out its timeout). Add the breaker when:

1. Stripe usage scales (real production traffic, not the dev flow), **or**
2. A second payment gateway is added (the breaker then becomes the shared
   mechanism for choosing/backing off between gateways).
