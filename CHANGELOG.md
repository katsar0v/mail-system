# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- **GA4 Conversion Tracking**
  - New "Enable GA4 Tracking" setting in Admin > Settings > Subscription Form
  - Tracks subscription form submissions as conversion events in Google Analytics 4
  - Sends `subscribe` event with event parameters when users successfully subscribe
  - JavaScript-based tracking that integrates with existing GA4 setup
  - Unit tests added for styling settings including GA4 tracking configuration

### Fixed
- **Compose Wizard Step 1 Validation**
  - Fixed bug where "Please select at least one list" alert incorrectly appeared when clicking Continue in step 1
  - List validation now only triggers in step 3 where list selection is available

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

### Performance
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

### Fixed
- **Missing Translation**
  - Added missing translation for "Total Subscribers" in Bulgarian language
- **Corrupted Bulgarian Translations** (PR #90)
  - Removed msgcat merge markers that were showing in the UI
  - Cleaned 9 translation entries with embedded `#-#-#-#-#` markers
  - Recompiled Bulgarian MO file

### Planned
- Open and click statistics
- A/B testing
- Integration with popular SMTP plugins

---

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

### Technical Details
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

[Unreleased]: https://github.com/katsarov-design/mail-system/compare/1.1.1...HEAD
[1.1.1]: https://github.com/katsarov-design/mail-system/compare/1.1.0...1.1.1
[1.1.0]: https://github.com/katsarov-design/mail-system/compare/1.0.0...1.1.0
[1.0.0]: https://github.com/katsarov-design/mail-system/releases/tag/1.0.0