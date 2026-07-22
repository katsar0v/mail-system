# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- **Themed API Reference documentation page** — the developer API reference is now published as a styled GitHub Pages subpage (`docs/api-reference.html`) that matches the project landing page, with a sticky "On this page" table of contents, syntax-highlighted PHP samples, copy-to-clipboard buttons on every code block, and a responsive layout. The Documentation links now open the rendered page instead of the raw Markdown file.

### Changed

### Fixed

### Security

## [1.1.3] - 2026-07-21

### Added
- **JWT-authenticated REST API for campaign scheduling (#118)**
  - New `mail-system/v1` REST namespace: `GET /lists`, `POST /campaigns`, `GET /campaigns/{id}`, and `POST /campaigns/{id}/cancel`
  - Bearer-token authentication using self-contained HS256 JSON Web Tokens signed with a secret derived from the site's WordPress salts — no production Composer dependency
  - Scopes (`campaigns:read`, `campaigns:write`) enforced per route; `Idempotency-Key` header prevents duplicate scheduling on client retries; optional ISO-8601 `scheduled_at` with rejection of malformed or past times
  - New **Mail System → API Access** admin page to create tokens (name, expiry of 30/90/365 days or never, scopes) and revoke them; generated tokens are shown once and only a hash of each token identifier is stored, so deleting a token immediately and irreversibly revokes it
  - New `docs/rest-api.md` (endpoint reference, cURL examples, error catalogue, `Authorization`-header troubleshooting) and `docs/architecture.md` (layering overview)
- **Reusable campaign application service** — `MSKD\Application\Campaign_Service` centralizes campaign validation, list resolution, recipient deduplication and scheduling so the admin compose wizard and the REST API enforce identical rules. `MSKD\Application\Campaign_Query_Service` provides campaign status and list read models.

### Changed
- Campaign creation is now **transactional**: the campaign row and its queue entries are committed together, and a campaign that ends up with zero deliverable recipients is rolled back instead of being reported as a successful, empty send. The admin compose controller reports the actual number of recipients queued.
- The compose controller now delegates recipient resolution, Bcc validation and scheduling to the shared `Campaign_Service` rather than duplicating that logic.

### Fixed
- **Full-width admin queue layout (#122)** — the queue table now clears the floated status filters and uses the available WordPress admin content width without forcing page-level horizontal scrolling.
- **Fresh-install schema drift** — a brand-new installation's campaigns table was missing the `bcc`, `bcc_sent`, `from_email`, and `from_name` columns that every campaign insert expects, and the queue `status` enum omitted the `cancelled` state that cancellation code writes. The canonical schema now includes them, and an upgrade path (schema version 1.9.0) repairs existing installs.

### Security
- **Duplicate delivery from overlapping cron runs** — the queue worker now atomically claims each pending item (a guarded `pending → processing` transition backed by a new `processing_started_at` column and a composite `(status, scheduled_at)` index) before sending, so two concurrent cron runs can no longer send the same email twice. Stuck-item recovery is keyed off when an item was actually claimed rather than its original schedule.
- REST-supplied email bodies are constrained to the plugin's email-safe HTML allowlist (`mskd_kses_email`), and the JWT decoder accepts only `HS256`, rejecting the `alg:none` bypass and asymmetric-to-symmetric algorithm-confusion attacks.

## [1.1.2] - 2026-07-17

### Added
- **Local email delivery guard (#114)** — outgoing email, SMTP connection tests, and queue processing are blocked when WordPress reports a `local` environment or the site uses a common local-development hostname. Administrators see a persistent warning on Mail System pages, and detection remains filterable for project-specific setups.
- **Secure email click analytics (#113)**
  - Rewrites eligible campaign and one-time email links through HMAC-signed, recipient-specific redirect URLs
  - Records privacy-safe first/last click timestamps, repeat counts, inferred opens, unique clickers, CTR/CTOR, and per-link performance
  - Excludes unsubscribe/confirmation links, unsupported schemes, and `data-mskd-no-track` anchors
  - Prevents BCC attribution by disabling open and click tracking on any message copy carrying BCC
  - Adds schema version 1.8.0, uninstall/truncate cleanup, public redirect validation, and automated security/send-path coverage
- **Per-recipient email open analytics (#111)**
  - Adds an unpredictable tracking token and invisible 1×1 pixel to newly queued campaign and one-time emails
  - Records the first open timestamp and total pixel load count without storing IP addresses or user-agent data
  - Shows unique opens, open rates, and per-recipient sent/open timestamps in the Queue overview and campaign detail screens
  - Includes a database upgrade to schema version 1.7.0, unit coverage, and an in-product caveat explaining image blocking, privacy proxy, and prefetch limitations
- **Bulk Subscriber Status Actions**
  - New "Set active" and "Set inactive" options in the bulk actions dropdown on the Subscribers page
  - Available whenever there are editable database subscribers to select, even if no lists exist yet
  - `Subscriber_Service::batch_update_status()` validates the target status and updates selected subscribers, reporting success/failed counts and errors
  - New `mskd_batch_update_subscriber_status` AJAX action, nonce- and capability-checked
  - Status badges in the table update immediately after a successful bulk update
  - Full translations in Bulgarian and German, unit tests covering success, missing subscriber, invalid/empty IDs, and invalid status cases
- **Delete Unconfirmed and Inactive Subscribers**
  - New "Delete unconfirmed and inactive subscribers" button in Admin > Settings > Danger Zone
  - Deletes all subscribers with `inactive` (unconfirmed) status and their list associations
  - Uses `window.confirm` dialog before performing the irreversible action
  - AJAX-powered deletion with real-time feedback
  - Full translations in Bulgarian and German with pluralization support
- **GA4 Conversion Tracking**
  - New "Enable GA4 Tracking" setting in Admin > Settings > Subscription Form
  - Tracks subscription form submissions as conversion events in Google Analytics 4
  - Sends `subscribe` event with event parameters when users successfully subscribe
  - JavaScript-based tracking that integrates with existing GA4 setup
  - Unit tests added for styling settings including GA4 tracking configuration
- **Mass Delete Subscribers**
  - New "Delete" option in bulk actions dropdown on subscribers page
  - Ability to delete multiple subscribers at once with confirmation dialog
  - AJAX-powered deletion with real-time feedback
  - Proper database cleanup (subscribers, list associations, and queue items)
  - Full translations in Bulgarian and German with pluralization support

### Changed
- **Renamed plugin slug** from `mail-system-by-katsarov-design` to `mail-system`. Updated main plugin file, all language files (`.pot`, `.po`, `.mo`), text domain references across all PHP source files, `package.json`, `bin/create-release.sh`, and documentation headers. DB table names, option keys, hooks, and constants are unchanged.
- **Delete Inactive Subscribers button** now also deletes subscribers with `unsubscribed` status, in addition to `inactive` (unconfirmed). Updated button description, confirmation dialog, and success messages accordingly. Translations updated for Bulgarian and German.

### Fixed
- **Bulk actions Apply button not showing** — on the Subscribers page, if a browser restored the bulk actions dropdown's previous value on page load/refresh without firing a `change` event, the Apply button stayed hidden even though a bulk action was selected and subscribers were checked. The button's visibility is now synced on page load, not only on `change`.
- **Opt-in confirmation email wrappers** — confirmation emails now apply the configured email header and footer and replace wrapper template variables (`{first_name}`, `{last_name}`, `{email}`, `{unsubscribe_link}`, `{unsubscribe_url}`) before sending.
- **SMTP password encryption delimiter handling** — encrypted payloads now encode the IV separately before adding the delimiter and still support legacy raw-IV payloads, preventing rare decrypt failures when random IV bytes contained the delimiter sequence.
- **Database repair notice persisting infinitely** — when clicking "Repair Database Now", if the required database table or column did not exist (or `ALTER TABLE` silently failed), the repair notice was shown on every page load indefinitely. The handler now calls `MSKD_Activator::activate()` (which uses `dbDelta` to create missing tables and columns), verifies the schema afterwards, and — if still failing — shows an actionable error notice with the database error message. The schema check also now correctly detects a missing table (not just a missing column).
- **Scheduling/queue timezone mixing** — the scheduling and queue system wrote datetimes from three different bases (WP-local `current_time`, UTC `gmdate`, and the MySQL `CURRENT_TIMESTAMP` default), then compared and rendered them as if identical. On sites whose WordPress timezone differs from UTC, this produced a phantom offset between the Queue "Created" and "Scheduled for" columns and broke retry/stuck-recovery timing (retries landed in the past and fired immediately). Retry `scheduled_at` and the stuck-recovery threshold now use the new site-local `mskd_local_time_from_timestamp()` helper, and campaign/queue `created_at` is set explicitly via `mskd_current_time_normalized()` instead of the DB-server-timezone default, so all writes, comparisons, and display share one convention.
- **Email preview opening in a new tab** — email preview forms (campaign/queue detail and compose wizard) targeted a runtime-generated browsing-context name and then renamed the iframe to match, which is racy and often failed to bind, causing the preview to open in a new tab instead of inline. Preview iframes now render a stable server-side `name`, and the hidden preview form submits to that existing target.
- Fixed undefined variable `$class` (should be `$class_name`) in test bootstrap autoloader, which caused PHP 8.1 test failures due to undefined variable warnings being converted to exceptions.
- **Compose Wizard Step 1 Validation** — fixed bug where "Please select at least one list" alert incorrectly appeared when clicking Continue in step 1. List validation now only triggers in step 3 where list selection is available.

## [1.1.1] - 2025-12-19

### Added
- **Lists Column in Subscribers Page** (Issue #84)
  - New "Lists" column displaying which list(s) each subscriber belongs to
  - Multiple lists are stacked vertically for easy readability
  - Efficient batch query to fetch list data for all displayed subscribers
  - Added `batch_get_lists()` method to Subscriber_Service for optimized fetching
- **Option to hide Name field in Subscribe Form**
  - New "Show Name Field" setting in Admin > Settings > Subscription Form
  - Toggle to show or hide the Name field in the public subscription form
  - Default behavior preserves showing the Name field for existing installations
- **Subscriber Statistics Box**
  - Added a visible box at the top of the subscribers page displaying:
    - Total subscribers count
    - Active subscribers count
    - Inactive subscribers count
    - Unsubscribed count
  - Improved usability for administrators to quickly assess subscriber base health
- **Custom Email Header and Footer**
  - Configurable HTML header prepended to all outgoing emails
  - Configurable HTML footer appended to all outgoing emails
  - Support for template variables (`{first_name}`, `{last_name}`, `{email}`, `{unsubscribe_link}`, `{unsubscribe_url}`)
  - New "Email Template Settings" section in Settings page
  - Documentation in `docs/email-header-footer.md`
- **BCC Display in Queue Details**
  - BCC recipients are now visible in the campaign details page when viewing queue items
  - Only displayed when BCC is configured for the campaign
- **Batch Processing for Email Queue Operations**
  - Added `batch_queue_subscribers()` method to Email_Service for chunking large subscriber lists
  - Added `process_subscriber_chunk()` to handle individual chunks efficiently
  - Added `batch_insert_queue_items()` for optimized database inserts
  - Added `batch_get_or_create()` method to Subscriber_Service for bulk subscriber operations
  - Added `batch_create()` for creating multiple subscribers at once
  - Added `batch_get_by_ids()` for retrieving multiple subscribers by IDs
  - Comprehensive unit tests for batch processing functionality
- **Per-Campaign Custom Sender Configuration**
  - New `from_email` and `from_name` columns in campaigns table
  - UI controls in compose wizard, legacy compose, and one-time email forms
  - Radio button selection between default and custom sender
  - Client-side and server-side email validation
  - Progressive enhancement with default fallback to global settings
  - Custom sender data passed through email service to SMTP mailer
  - Database upgrade from 1.5.0 to 1.6.0 with proper column addition
  - Full backward compatibility - existing campaigns continue working unchanged
- **Encryption Unit Tests**
  - 12 comprehensive tests for encrypt/decrypt functions
  - Tests for edge cases: empty values, special characters, unicode, corrupted data

### Changed
- **Database Schema Upgrade**
  - Campaigns table now supports per-campaign sender override
  - Nullable columns ensure no breaking changes to existing data
  - Proper upgrade handling in activator with version checking
- **WPCS Compliance**
  - Fixed variable naming to avoid overriding WordPress globals
  - Fixed indentation (spaces to tabs) in admin partials
  - Added proper Yoda condition checks
- **Performance improvements**
  - Improved performance when handling large email campaigns through batch processing
  - Reduced database queries by batching operations
  - Prevents memory issues when processing large subscriber lists

### Fixed
- **Dedupe Subscribers in Campaign Queue**
  - Fixed issue where subscribers belonging to multiple lists would receive duplicate emails in the same campaign.
  - Ensures each subscriber (by email or ID) is queued only once per campaign.
- **Campaign Wizard Validation**
  - Fixed console error "invalid form control is not focusable" when no recipient lists are selected in Step 3
  - Added visible alert message "Please select at least one list" for better user feedback
  - Replaced browser-native validation with custom JavaScript validation for the lists selector
- **Directory Naming**
  - Fixed inconsistent directory naming references in translation files
- **Redirect to queue page after campaign creation (#71)**
  - Users are now redirected to the queue page after successfully creating or scheduling a campaign
  - Success message is preserved and displayed on the queue page
- **Repository Cleanup**
  - Removed temporary PHP CodeSniffer report files (`phpcs_remaining.txt` and `phpcs_report.txt`)
  - These files were generated during development and should not be committed to the repository
- **One-time emails now include header and footer**
  - Immediate one-time emails now apply the configured email header and footer
  - Previously only queued/scheduled emails included the header and footer
- **Confirmation Email Sender Configuration**
  - Opt-in confirmation emails now respect the configured SMTP sender settings (from_email and from_name)
  - Updated to use MSKD_SMTP_Mailer instead of wp_mail() for confirmation emails
  - Added test coverage for confirmation email sender configuration
- **Missing Translations in One-Time Email**
  - Synced string literals in `admin/partials/one-time-email.php` with POT file
  - Added missing definite article "the" to match translation keys
- **Translation Updates**
  - Added missing "Show Name Field" and "Subscription Form" translations in Bulgarian (`bg_BG`)
  - Fixed duplicate message definitions in `bg_BG.po` and `de_DE.po` preventing compilation
  - Corrected corrupted headers in German translation file
  - Recompiled MO files for both languages
- **Missing Translation**
  - Added missing translation for "Total Subscribers" in Bulgarian language
- **Corrupted Bulgarian Translations** (PR #90)
  - Removed msgcat merge markers that were showing in the UI
  - Cleaned 9 translation entries with embedded `#-#-#-#-#` markers
  - Recompiled Bulgarian MO file

### Security
- **Critical XSS Vulnerability Fix**
  - Fixed stored XSS in email preview AJAX handler
  - Email content now sanitized with `mskd_kses_email()` before output
- **Improved Password Storage**
  - Replaced weak base64 encoding with AES-256-CBC encryption for SMTP passwords
  - Added `mskd_encrypt()` and `mskd_decrypt()` helper functions with WordPress salts
  - Legacy base64 passwords are automatically handled for backward compatibility
- **Input Sanitization Improvements**
  - Added `wp_unslash()` before sanitization on GET parameters
  - Fixed unsanitized GET parameters in nonce verification
  - Improved REMOTE_ADDR handling with proper validation

## [1.1.0] - 2025-11-28

### Added
- **Email Templates Management System**
  - Predefined templates (Blank, Newsletter, Welcome, Promotional)
  - Custom template creation and management
  - Template duplication functionality
  - Visual editor integration for templates
  - Template usage from compose forms

- **Import/Export Functionality**
  - Import subscribers from CSV/JSON files
  - Export subscribers to CSV/JSON formats
  - Import/export mailing lists
  - Bulk import with list assignment
  - Redesigned Import/Export UI

- **Batch Edit for Subscribers**
  - Bulk list assignment for multiple subscribers
  - Batch status changes
  - Improved subscriber management

- **Configurable Email Rate Limit**
  - New "Emails per minute" setting (1-1000)
  - Adjustable sending speed based on hosting limits
  - Setting accessible in Emails → Settings → Sending settings

- **Admin Shortcodes Page**
  - Visual form gallery with `[mskd_form_gallery]` shortcode
  - Read-only form preview functionality
  - Replaced public shortcode with admin-managed Shortcodes page

- **Queue Improvements**
  - Email content accordion in queue details page
  - Enhanced queue detail view
  - Better campaign tracking

- **Multi-language Support**
  - English as the default/primary language
  - Bulgarian (bg_BG) translation - fully translated
  - German (de_DE) translation - fully translated
  - Automatic language detection based on WordPress locale
  - Documentation for adding new translations

- **API & Developer Features**
  - Comprehensive API reference documentation
  - External lists hook (`mskd_register_external_lists`)
  - External subscribers hook (`mskd_register_external_subscribers`)
  - Template_Service class for programmatic template management

### Changed
- Refactored admin architecture into modular PSR-4 classes
- Replaced Select2 with SlimSelect for multi-select lists
- Updated autoloading to support both PSR-4 and legacy classes
- Source strings changed from Bulgarian to English
- Updated POT file with all English source strings
- Improved CSS structure with better SCSS organization

### Fixed
- Visual editor CSS code appearing in email body
- Visual editor content not transferring to step 3 in campaign wizard
- Timestamp normalization for scheduled tasks
- Various UI improvements and bug fixes

---

## [1.0.0] - 2025-01-15

### Added
- Initial plugin version
- **Subscribers**
  - Add, edit, and delete subscribers
  - Statuses: active, inactive, unsubscribed
  - Filter by status
  - Pagination
- **Lists**
  - Create mailing lists
  - Add subscribers to multiple lists
  - Subscriber count statistics per list
- **Sending Queue**
  - Automatic email queuing
  - WP-Cron integration for sending
  - Speed: 10 emails/minute (MSKD_BATCH_SIZE)
  - Statuses: pending, processing, sent, failed
  - Send attempt tracking
- **Email Composition**
  - WYSIWYG editor for content
  - List selection for sending
  - Placeholders: {first_name}, {last_name}, {email}, {unsubscribe_link}
- **Settings**
  - Sender name and email
  - Reply-to email
  - SMTP configuration
- **Public Features**
  - Subscription form shortcode: [mskd_subscribe_form]
  - AJAX subscription without page reload
  - Unsubscribe page with unique token
- **Internationalization**
  - Ready for translation (.pot file)
- **Administration**
  - Dashboard with general statistics
  - WP-Cron warning (shown only within the plugin)
  - System cron recommendation
- **Technical requirements**
  - Requires PHP 7.4+
  - Requires WordPress 5.0+
  - Uses SMTP for sending (configurable)
  - 4 new database tables (mskd_subscribers, mskd_lists, mskd_subscriber_list, mskd_queue)
  - Automatic table creation on activation
  - Automatic cron job scheduling on activation
  - Automatic cron job removal on deactivation

---

## Versioning

- **Major** (X.0.0) - Incompatible API changes
- **Minor** (0.X.0) - New features, backward compatible
- **Patch** (0.0.X) - Bug fixes, backward compatible

[Unreleased]: https://github.com/katsarov-design/mail-system/compare/1.1.3...HEAD
[1.1.3]: https://github.com/katsarov-design/mail-system/compare/1.1.2...1.1.3
[1.1.2]: https://github.com/katsarov-design/mail-system/compare/1.1.1...1.1.2
[1.1.1]: https://github.com/katsarov-design/mail-system/compare/1.1.0...1.1.1
[1.1.0]: https://github.com/katsarov-design/mail-system/compare/1.0.0...1.1.0
[1.0.0]: https://github.com/katsarov-design/mail-system/releases/tag/1.0.0
