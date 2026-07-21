# Architecture

This document describes how the plugin is layered, with an emphasis on the campaign
scheduling path shared by the admin UI and the REST API.

## Layers

```
┌──────────────────────────────────────────────────────────────────────┐
│ Entry points (adapters)                                              │
│                                                                      │
│  Admin controllers (MSKD\Admin\*)      REST controller (MSKD\Api\    │
│  – parse $_POST, nonces, redirects      Rest_Controller)             │
│  – render admin partials                – parse JSON, bearer auth,   │
│                                           scopes, idempotency        │
└───────────────┬──────────────────────────────────┬───────────────────┘
                │                                  │
                ▼                                  ▼
┌──────────────────────────────────────────────────────────────────────┐
│ Application services (MSKD\Application\*)                             │
│                                                                      │
│  Campaign_Service        – validation, list resolution, dedup,       │
│                            atomic campaign + queue creation           │
│  Campaign_Query_Service  – campaign status, per-status counts, lists  │
└───────────────┬──────────────────────────────────────────────────────┘
                │
                ▼
┌──────────────────────────────────────────────────────────────────────┐
│ Domain / persistence services (MSKD\Services\*)                      │
│                                                                      │
│  Email_Service, Subscriber_Service, List_Provider, Template_Service, │
│  Email_Tracking_Service, SMTP_Mailer, Cron_Handler                   │
└───────────────┬──────────────────────────────────────────────────────┘
                │
                ▼
┌──────────────────────────────────────────────────────────────────────┐
│ WordPress + database ($wpdb, custom tables, WP-Cron)                 │
└──────────────────────────────────────────────────────────────────────┘
```

The guiding rule: **entry points are thin adapters.** They translate a transport
(an admin form post or an HTTP request) into a call on an application service and
translate the result back (a redirect with a notice, or a JSON response with a status
code). All business rules — what makes a campaign valid, who its recipients are, and how
it is persisted — live in the application and domain services so both entry points behave
identically.

## Campaign scheduling flow

Both the admin compose wizard (`MSKD\Admin\Admin_Email`) and `POST /campaigns`
(`MSKD\Api\Rest_Controller`) call the same method:

```
Campaign_Service::schedule( array $input ): array
```

`schedule()`:

1. Validates the subject, body and list selection.
2. Validates the optional custom sender and Bcc addresses.
3. Resolves recipients from the selected lists via `MSKD_List_Provider`, deduplicating by
   email.
4. Opens a database transaction, delegates the campaign row and queue-entry inserts to
   `Email_Service::queue_campaign()`, and:
   - rolls back and reports `db_error` if the campaign insert fails, or
   - rolls back and reports `no_recipients` if nothing was actually queued (so an empty
     campaign is never reported as a success), otherwise
   - commits and returns the real queued count.

The adapters differ only in what they do around this call:

| Concern                  | Admin (`Admin_Email`)                | REST (`Rest_Controller`)                    |
| ------------------------ | ------------------------------------ | ------------------------------------------- |
| Input parsing            | `$_POST` + nonce                     | JSON body + bearer token + scope            |
| Body sanitization        | preserved raw (trusted admin)        | `mskd_kses_email()` allowlist               |
| Scheduling input model   | `schedule_type`/`delay_*` form fields | ISO-8601 `scheduled_at`                     |
| Duplicate protection     | one submission                       | `Idempotency-Key` header                    |
| Result presentation      | admin notice + redirect              | JSON body + HTTP status                     |

Because scheduling *input formats* genuinely differ, each adapter resolves its own input
into a canonical `scheduled_at` (a WordPress-timezone MySQL datetime) that the service
consumes; the scheduling *rules* (immediate vs. future, minute normalization) are shared.

## Queue processing and delivery

`MSKD_Cron_Handler::process_queue()` runs once per minute. For each due item it performs
an **atomic claim** — a guarded `pending → processing` update that only one worker can
win — before sending, so overlapping cron runs cannot deliver the same email twice. The
claim records `processing_started_at`, which drives stuck-item recovery independently of
the item's original schedule. A composite `(status, scheduled_at)` index backs the due-item
lookup.

## REST authentication

`MSKD\Api\Token_Service` issues and verifies HS256 JWTs using `MSKD\Api\Jwt_Codec`, a
deliberately tiny codec that supports only HS256 (closing `alg:none` and algorithm-confusion
attacks) and carries no production Composer dependency. The signing secret is derived from
the site's WordPress salts. Only a hash of each token's random `jti` is stored, so tokens
are shown once and are revoked by deleting their database record. See
[`rest-api.md`](rest-api.md) for the external contract.

## Autoloading

Production uses the plugin's own autoloader (in `mail-system.php`); Composer is only needed
for development (tests, coding standards). Namespaces map to directories under `includes/`:

| Namespace            | Directory              |
| -------------------- | ---------------------- |
| `MSKD\Admin\*`       | `includes/Admin/`      |
| `MSKD\Application\*` | `includes/Application/`|
| `MSKD\Api\*`         | `includes/Api/`        |
| `MSKD\Services\*`    | `includes/services/`   |
| `MSKD\Traits\*`      | `includes/traits/`     |
| `MSKD\Models\*`      | `includes/models/`     |

Legacy `MSKD_*` classes (e.g. `MSKD_List_Provider`, `MSKD_Cron_Handler`) remain supported
by the same autoloader.
