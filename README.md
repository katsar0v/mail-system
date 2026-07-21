<h1 align="center">Mail System</h1>

<p align="center">
  Email newsletter management for WordPress — subscribers, mailing lists, templates, a visual editor, and a cron-based sending queue in one clean admin workspace.
</p>

<p align="center">
  <img alt="Version 1.1.2" src="https://img.shields.io/badge/version-1.1.2-1f6feb?style=for-the-badge">
  <img alt="WordPress 5.0+" src="https://img.shields.io/badge/WordPress-5.0%2B-21759b?style=for-the-badge&logo=wordpress&logoColor=white">
  <img alt="PHP 7.4+" src="https://img.shields.io/badge/PHP-7.4%2B-777bb4?style=for-the-badge&logo=php&logoColor=white">
  <img alt="License GPL-2.0-or-later" src="https://img.shields.io/badge/license-GPL--2.0--or--later-0f766e?style=for-the-badge">
</p>

## Overview

Mail System adds a dedicated WordPress admin area for managing subscribers, mailing lists, email templates, and campaign delivery. It includes a visual drag-and-drop email editor, a sending queue with configurable throughput, SMTP configuration, WP-Cron integration, CSV/JSON import and export, a subscription form shortcode, and translation support for English, Bulgarian, and German.

The plugin ships without a required build step and works out of the box without running Composer on production.

## Highlights

| Area | What it gives you |
| --- | --- |
| Subscribers | Add, edit, and delete subscribers with active, inactive, and unsubscribed statuses. |
| Mailing lists | Organize subscribers into named lists and send campaigns to one or more lists. |
| Email templates | Reusable templates with a visual drag-and-drop editor for composing campaigns. |
| New campaign | Compose a campaign from a template, select target lists, and queue or publish immediately. |
| One-time email | Send a single ad-hoc email to a specific subscriber without creating a campaign. |
| Sending queue | Emails are queued and dispatched by WP-Cron at a configurable rate (default: 10/minute). |
| Email analytics | Review sent totals, unique opens, unique clickers, CTR/CTOR, repeat activity, and per-link performance. |
| SMTP | Configure an external SMTP server with SSL or TLS for reliable delivery. |
| Import / Export | Bulk import and export subscribers and lists in CSV or JSON format. |
| Subscription shortcode | `[mskd_subscribe_form]` renders a signup form on any page or post. |
| Internationalization | PHP and PO/MO translations for English (default), Bulgarian, and German. |

## Requirements

| Requirement | Version |
| --- | --- |
| WordPress | 5.0 or newer |
| PHP | 7.4 or newer |
| Composer | Development only — not required on production |

## Admin Screens

| Screen | Slug | Purpose |
| --- | --- | --- |
| Dashboard | `mskd-dashboard` | Subscriber and queue statistics at a glance. |
| Subscribers | `mskd-subscribers` | Browse, add, edit, and delete subscribers. |
| Lists | `mskd-lists` | Create and manage mailing lists. |
| Templates | `mskd-templates` | Save and manage reusable email templates. |
| New campaign | `mskd-compose` | Compose and queue a newsletter campaign. |
| One-time email | `mskd-one-time-email` | Send a single email to one subscriber. |
| Queue | `mskd-queue` | Inspect delivery status and per-campaign open/click analytics. |
| Settings | `mskd-settings` | Configure SMTP, sending rate, and plugin options. |
| API Access | `mskd-api` | Create and revoke JWT tokens for the REST API. |
| Import / Export | `mskd-import-export` | Bulk import or export subscribers and lists (CSV or JSON). |
| Shortcodes | `mskd-shortcodes` | Reference for available shortcodes and parameters. |

## Installation

Place the plugin directory inside WordPress:

```bash
wp-content/plugins/mail-system
```

Activate the plugin in WordPress:

```bash
wp plugin activate mail-system
```

You can also activate it from `Plugins` in the WordPress admin. No Composer or `vendor/` directory is required — the plugin includes its own autoloader and works out of the box.

## First-Time Setup

1. Open `Emails` in the WordPress admin sidebar.
2. Go to `Settings` and set the sending rate (emails per minute) appropriate for your host.
3. Enable SMTP if you want to use an external mail server instead of `wp_mail()`.
   - Fill in the SMTP host, port, encryption (SSL or TLS), and credentials.
   - Common providers: Gmail (`smtp.gmail.com:587`, TLS, App Password), Mailgun (`smtp.mailgun.org:587`, TLS), SendGrid (`smtp.sendgrid.net:587`, TLS, username `apikey`).
   - Use the **Send test email** button to verify the connection.
4. Go to `Lists` and create at least one mailing list.
5. Go to `Subscribers` and add subscribers, or use `Import / Export` to bulk-import from CSV.
6. Go to `Templates` to create a reusable email template using the visual editor.
7. Go to `New campaign`, select a template and target lists, and send or queue the campaign.

## Production Cron

WordPress traffic-based cron can delay scheduled email delivery on quiet sites. For production, use a real system cron job and disable the traffic trigger.

Add this to `wp-config.php`:

```php
define( 'DISABLE_WP_CRON', true );
```

Run WordPress cron every minute from the server:

```cron
* * * * * wget -q -O - https://example.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1
```

The plugin registers this event:

| Event | Interval | Purpose |
| --- | --- | --- |
| `mskd_process_queue` | Every minute | Dispatch queued emails up to the configured rate limit. |

## Shortcode

Place the subscription form on any page or post:

```
[mskd_subscribe_form]
```

With parameters:

```
[mskd_subscribe_form list_id="1" title="Subscribe to our newsletter"]
```

| Parameter | Description | Default |
| --- | --- | --- |
| `list_id` | ID of the list to subscribe to | 0 (no list) |
| `title` | Form heading | "Subscribe" |

### Email Placeholders

Use these placeholders inside campaign and template bodies:

| Placeholder | Description |
| --- | --- |
| `{first_name}` | Subscriber's first name |
| `{last_name}` | Subscriber's last name |
| `{email}` | Subscriber's email address |
| `{unsubscribe_link}` | Unsubscribe anchor tag |
| `{unsubscribe_url}` | Raw unsubscribe URL |

## Data Storage

The plugin creates custom tables using the active WordPress table prefix.

| Table | Purpose |
| --- | --- |
| `mskd_subscribers` | Subscriber records and statuses. |
| `mskd_lists` | Mailing list definitions. |
| `mskd_subscriber_list` | Many-to-many subscriber-to-list relationships. |
| `mskd_queue` | Queued email jobs and delivery status. |
| `mskd_clicks` | Per-recipient, per-link click aggregates with privacy-safe display URLs. |

The plugin stores settings in `mskd_settings` and database versioning in `mskd_db_version`.

### Engagement Analytics

Every newly queued recipient receives a unique, non-identifying tracking URL. When the recipient's email client loads the invisible image, the queue row records its first-open timestamp and increments its pixel-load count. The Queue screen reports unique opens and calculates open rate against successfully sent emails.

Open data is approximate. Email clients that block remote images can cause missed opens, while privacy proxies and image prefetching can load the pixel before a recipient reads the message. The plugin does not store IP addresses or user-agent strings for these events.

Eligible `http://` and `https://` links are routed through a recipient-specific, HMAC-signed redirect URL. A valid click records first/last timestamps and a repeat-click count, then redirects to the original destination. Clicks also infer an open when the tracking pixel was blocked. The Queue screen reports unique clickers, total clicks, CTR, CTOR, per-recipient activity, and per-link performance. Stored reporting labels retain only the destination origin (scheme, host, and port), and no IP address, user-agent, device, or location data is retained.

Click aggregates follow the queue's lifecycle: clearing all campaigns clears their click rows, and uninstalling the plugin drops the click analytics table.

Unsubscribe and confirmation links, non-web schemes, and anchors carrying `data-mskd-no-track` are never rewritten. Messages sent with BCC are intentionally left untracked because the To and BCC recipients share one message body; this prevents BCC activity from being attributed to the primary recipient. Click counts remain approximate because security scanners and email clients may prefetch tracked links before a person clicks them.

## Development

Install development dependencies:

```bash
composer install
```

Check local Docker containers first:

```bash
docker ps -a
```

Run PHPUnit in the WordPress test container:

```bash
docker exec -w /var/www/html/wp-content/plugins/mail-system php vendor/bin/phpunit -c phpunit.xml
```

Run WordPress coding standards:

```bash
docker exec -w /var/www/html/wp-content/plugins/mail-system php composer phpcs
```

Auto-fix coding standards violations:

```bash
docker exec -w /var/www/html/wp-content/plugins/mail-system php composer phpcbf
```

Compile `.po` translation files to `.mo`:

```bash
docker exec -w /var/www/html/wp-content/plugins/mail-system php composer translations
```

There is no frontend build step. Admin CSS and JavaScript live directly in `admin/css` and `admin/js`.

## Architecture

```text
mail-system.php                  Plugin bootstrap and activation hooks
includes/class-activator.php     Activation: table creation and cron scheduling
includes/class-mskd-deactivator.php  Deactivation: cron teardown
includes/Admin/                  Admin menu, controllers, assets, and notices
includes/Application/            Application services: campaign scheduling and queries
includes/Api/                    JWT codec, token service, and REST controller
includes/services/               Business logic: email, subscriber, list, queue, SMTP, import/export
admin/partials/                  WordPress admin templates
admin/css/                       Admin styles
admin/js/                        Admin screens and interactions
admin/editor/                    Visual email editor assets
public/                          Front-end shortcode rendering
tests/                           PHPUnit test suite
languages/                       POT, PO, and MO translation files
```

See [`docs/architecture.md`](docs/architecture.md) for how the adapter, application, and
persistence layers fit together.

## REST API

A JWT-authenticated REST API under `mail-system/v1` lets external systems schedule and
inspect campaigns:

- `GET /wp-json/mail-system/v1/lists` — available recipient lists
- `POST /wp-json/mail-system/v1/campaigns` — schedule a newsletter
- `GET /wp-json/mail-system/v1/campaigns/{id}` — campaign status and counts
- `POST /wp-json/mail-system/v1/campaigns/{id}/cancel` — cancel unsent recipients

Create and revoke bearer tokens under **Mail System → API Access** (each token has a name,
scopes, and an expiry, and is shown only once). Full reference, request/response schemas,
and cURL examples are in [`docs/rest-api.md`](docs/rest-api.md).

```bash
curl -X POST https://your-site.example/wp-json/mail-system/v1/campaigns \
  -H "Authorization: Bearer $TOKEN" \
  -H "Idempotency-Key: newsletter-2026-07-22" \
  -H "Content-Type: application/json" \
  -d '{"subject":"July newsletter","body":"<h1>Hello</h1>","list_ids":["12"]}'
```

## Internationalization

Generate the translation template:

```bash
docker exec -w /var/www/html/wp-content/plugins/mail-system php wp i18n make-pot . languages/mail-system.pot --exclude=vendor,node_modules --allow-root
```

Compile a `.po` file to `.mo` after translating:

```bash
msgfmt -o languages/mail-system-{locale}.mo languages/mail-system-{locale}.po
```

Bundled translations: English (`en_US`, default), Bulgarian (`bg_BG`), German (`de_DE`).

## Uninstall Behavior

Uninstalling the plugin drops all custom tables (`mskd_subscribers`, `mskd_lists`, `mskd_subscriber_list`, `mskd_queue`, `mskd_campaigns`, `mskd_templates`, `mskd_clicks`, `mskd_api_tokens`), deletes stored options, and clears the scheduled cron event.

## License

Mail System is licensed under `GPL-2.0-or-later`.

Copyright (C) 2026 Katsarov Design.
