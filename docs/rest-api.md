# REST API

The Mail System plugin exposes a small, JWT-authenticated REST API for scheduling
and inspecting newsletter campaigns from external systems (for example an editorial
CMS, a cron job, or an automation platform).

- **Base URL:** `https://your-site.example/wp-json/mail-system/v1`
- **Authentication:** `Authorization: Bearer <jwt>` on every request
- **Content type:** `application/json`

> Always call the API over HTTPS. A bearer token is a credential; sending it over
> plain HTTP exposes it to anyone on the network path.

## Contents

1. [Authentication](#authentication)
2. [Scopes](#scopes)
3. [Endpoints](#endpoints)
   - [GET /lists](#get-lists)
   - [POST /campaigns](#post-campaigns)
   - [GET /campaigns/{id}](#get-campaignsid)
   - [POST /campaigns/{id}/cancel](#post-campaignsidcancel)
4. [Scheduling and time zones](#scheduling-and-time-zones)
5. [Idempotency](#idempotency)
6. [Errors](#errors)
7. [Troubleshooting the Authorization header](#troubleshooting-the-authorization-header)

## Authentication

Tokens are created in the WordPress admin under **Mail System → API Access**. When you
create a token you choose:

- a **name** (a label to recognize it later),
- one or more **scopes** (see below),
- an **expiry** — 30, 90, 365 days, or never.

The token is a standard [JSON Web Token](https://datatracker.ietf.org/doc/html/rfc7519)
signed with `HS256`. **It is displayed only once**, immediately after creation. The
plugin stores only a hash of the token's internal identifier, so it can never show the
token again — copy it into your client's secret storage right away.

Send it on every request as a bearer token:

```
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

### Revocation

Deleting a token on the **API Access** page removes its stored record. Because the
plugin verifies each request against that record, the token stops working **immediately**.
A token is also rejected when:

- it has expired (either by its own `exp` claim or the stored expiry),
- the administrator who created it no longer has the `manage_options` capability,
- the site's WordPress salts have been rotated (this changes the signing secret and
  invalidates every previously issued token).

## Scopes

| Scope              | Grants                                                       |
| ------------------ | ----------------------------------------------------------- |
| `campaigns:read`   | `GET /lists`, `GET /campaigns/{id}`                         |
| `campaigns:write`  | `POST /campaigns`, `POST /campaigns/{id}/cancel`            |

A token used on a route it lacks the scope for receives `403 Forbidden` with the
`insufficient_scope` code. Grant `campaigns:read` alongside `campaigns:write` if the
same token both schedules campaigns and reads their status.

## Endpoints

### GET /lists

Returns the recipient lists available for scheduling (both database lists and any
lists registered by other plugins through the `mskd_register_external_lists` filter).

**Required scope:** `campaigns:read`

```bash
curl https://your-site.example/wp-json/mail-system/v1/lists \
  -H "Authorization: Bearer $TOKEN"
```

```json
{
  "lists": [
    {
      "id": "12",
      "name": "Newsletter subscribers",
      "type": "database",
      "provider": null,
      "subscriber_count": 3480
    },
    {
      "id": "ext_customers",
      "name": "Paying customers",
      "type": "external",
      "provider": "WooCommerce",
      "subscriber_count": 512
    }
  ]
}
```

Use the `id` values verbatim in `POST /campaigns`.

### POST /campaigns

Validates and schedules a newsletter to one or more lists.

**Required scope:** `campaigns:write`
**Required header:** `Idempotency-Key` (see [Idempotency](#idempotency))

**Request body**

| Field          | Type              | Required | Notes                                                                 |
| -------------- | ----------------- | -------- | --------------------------------------------------------------------- |
| `subject`      | string            | yes      | Plain-text subject line.                                              |
| `body`         | string (HTML)     | yes      | Filtered through the plugin's email-safe HTML allowlist.              |
| `list_ids`     | array of strings  | yes      | List identifiers from `GET /lists`.                                   |
| `scheduled_at` | string (ISO-8601) | no       | Omit for immediate send. See [Scheduling](#scheduling-and-time-zones).|
| `bcc`          | array or string   | no       | Bcc recipients; each must be a valid email address.                   |
| `from_email`   | string            | no       | Custom sender address (must be valid).                                |
| `from_name`    | string            | no       | Custom sender name.                                                   |

```bash
curl -X POST https://your-site.example/wp-json/mail-system/v1/campaigns \
  -H "Authorization: Bearer $TOKEN" \
  -H "Idempotency-Key: newsletter-2026-07-22" \
  -H "Content-Type: application/json" \
  -d '{
        "subject": "July newsletter",
        "body": "<h1>Hello {first_name}</h1><p>...</p>",
        "list_ids": ["12", "ext_customers"],
        "scheduled_at": "2026-07-22T10:00:00+03:00",
        "bcc": ["audit@example.com"],
        "from_email": "news@example.com",
        "from_name": "Newsletter"
      }'
```

**`201 Created`**

```json
{
  "campaign_id": 42,
  "status": "scheduled",
  "scheduled_at": "2026-07-22 10:00:00",
  "is_immediate": false,
  "queued": 3992,
  "total_recipients": 3992
}
```

`status` is `queued` for an immediate send and `scheduled` for a future one.
`scheduled_at` is returned in the site's WordPress time zone.

The personalization placeholders `{first_name}`, `{last_name}`, `{email}`,
`{unsubscribe_link}`, and `{unsubscribe_url}` are substituted per recipient at send
time, exactly as in the admin composer.

### GET /campaigns/{id}

Returns a campaign's status and per-recipient counts.

**Required scope:** `campaigns:read`

```bash
curl https://your-site.example/wp-json/mail-system/v1/campaigns/42 \
  -H "Authorization: Bearer $TOKEN"
```

```json
{
  "id": 42,
  "subject": "July newsletter",
  "type": "campaign",
  "status": "processing",
  "total_recipients": 3992,
  "scheduled_at": "2026-07-22 10:00:00",
  "completed_at": null,
  "created_at": "2026-07-21 14:03:00",
  "counts": {
    "pending": 3140,
    "processing": 0,
    "sent": 852,
    "failed": 0,
    "cancelled": 0
  }
}
```

### POST /campaigns/{id}/cancel

Cancels every queued (`pending`) entry for a campaign. An entry already being sent
(`processing`) is allowed to finish, so an in-flight email is never reported as
cancelled after delivery has started. Already-sent emails are unaffected.

**Required scope:** `campaigns:write`

```bash
curl -X POST https://your-site.example/wp-json/mail-system/v1/campaigns/42/cancel \
  -H "Authorization: Bearer $TOKEN"
```

**`200 OK`**

```json
{
  "campaign_id": 42,
  "status": "cancelled",
  "cancelled": 3140
}
```

A campaign that is already completed or cancelled returns `409 Conflict`.

## Scheduling and time zones

- Omit `scheduled_at` (or send an empty string) to **send immediately**.
- Provide an **ISO-8601** timestamp to schedule a future send, e.g.
  `2026-07-22T10:00:00+03:00`.
- Include a timezone offset (or `Z`) in the value. If you omit it, the timestamp is
  interpreted in the **site's** WordPress time zone.
- The value is normalized to the start of the minute (seconds set to `00`) because the
  send queue is processed once per minute.
- A malformed timestamp returns `400 invalid_schedule`; a timestamp in the past returns
  `400 past_schedule`. The API never silently downgrades a bad schedule to an immediate
  send.

Actual delivery speed is governed by the **emails-per-minute** setting under
**Mail System → Settings** and by your server's WP-Cron cadence.

## Idempotency

`POST /campaigns` requires an `Idempotency-Key` header. Choose a unique, stable value
per logical campaign (for example `newsletter-2026-07-22`). If a request with the same
key (from the same token owner) is received again within 24 hours, the API returns the
**original** result with `200 OK` instead of creating a second campaign. This makes it
safe to retry after a network timeout.

A missing `Idempotency-Key` returns `400 missing_idempotency_key`.

## Errors

Errors use conventional HTTP status codes and a JSON body of the shape:

```json
{ "code": "past_schedule", "message": "The scheduled_at value is in the past.", "data": { "status": 400 } }
```

| Status | Codes                                                                                       | Meaning                                        |
| ------ | ------------------------------------------------------------------------------------------- | ---------------------------------------------- |
| `400`  | `missing_idempotency_key`, `missing_subject`, `missing_body`, `missing_lists`, `invalid_sender`, `invalid_bcc`, `unknown_list`, `no_recipients`, `invalid_schedule`, `past_schedule` | Malformed or invalid request. |
| `401`  | `missing_token`, `invalid_token`, `expired_token`, `revoked_token`                           | Authentication failed.                         |
| `403`  | `insufficient_scope`, `forbidden_token`                                                      | Authenticated but not authorized.              |
| `404`  | `not_found`                                                                                  | Campaign does not exist.                       |
| `409`  | `not_cancellable`                                                                            | Campaign can no longer be cancelled.           |
| `500`  | `db_error`                                                                                   | The campaign could not be persisted.           |

## Troubleshooting the Authorization header

Some server configurations strip the `Authorization` header before PHP sees it (this is
common with PHP-FPM/CGI). If every request returns `401 missing_token` even with a valid
token, forward the header explicitly. For Apache, add to the site's `.htaccess`:

```apache
RewriteEngine On
RewriteCond %{HTTP:Authorization} ^(.*)
RewriteRule .* - [E=HTTP_AUTHORIZATION:%1]
```

For Nginx + PHP-FPM, ensure the `fastcgi_param` for the authorization header is passed:

```nginx
fastcgi_param HTTP_AUTHORIZATION $http_authorization;
```

As a last resort the API also accepts the token in an `X-Authorization: Bearer <jwt>`
header, which proxies rarely strip.
