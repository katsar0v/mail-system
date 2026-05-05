# Instructions for Mail System by Katsarov Design

WordPress plugin for email newsletter management with subscribers, lists, and queue processing. Full Bulgarian UI.

## Architecture Overview

```
mail-system.php  → Entry point, constants (MSKD_*), built-in autoloader
├── admin/class-admin.php           → All admin logic (1000+ lines, handles CRUD, AJAX, forms)
├── public/class-public.php         → Public shortcodes, AJAX subscription, unsubscribe handling
├── includes/
│   ├── class-activator.php         → DB table creation, cron scheduling, default options
│   ├── class-deactivator.php       → Cron cleanup
│   ├── Admin/                      → PSR-4 namespaced admin classes (MSKD\Admin\*)
│   └── services/
│       ├── class-cron-handler.php  → Queue processing (10 emails/min via MSKD_BATCH_SIZE)
       ├── class-list-service.php  → PSR-4 namespaced service classes (MSKD\Services\*)
│       └── class-smtp-mailer.php   → PHPMailer wrapper for SMTP sending
```

**Important**: The plugin has a built-in autoloader and does **NOT require Composer** to run.
Composer is only needed for development (tests, coding standards).

**Database Tables** (all prefixed `mskd_`):
- `mskd_subscribers` - email, first/last name, status (active/inactive/unsubscribed), unsubscribe_token
- `mskd_lists` - mailing list definitions
- `mskd_subscriber_list` - many-to-many pivot table
- `mskd_queue` - email queue with status (pending/processing/sent/failed), retry tracking

## Naming Conventions

| Type | Prefix | Example |
|------|--------|---------|
| Constants | `MSKD_` | `MSKD_BATCH_SIZE`, `MSKD_PLUGIN_DIR` |
| Classes | `MSKD_` | `MSKD_Admin`, `MSKD_Cron_Handler` |
| Functions | `mskd_` | `mskd_load_textdomain()` |
| DB tables | `mskd_` | `{$wpdb->prefix}mskd_subscribers` |
| Hooks/Actions | `mskd_` | `mskd_process_queue` |
| Admin pages | `mskd-` | `mskd-dashboard`, `mskd-subscribers` |

## Testing

Tests run **inside Docker** using Brain Monkey + Mockery (no WordPress installation needed):

```bash
# From host: enter PHP container and run tests
docker exec -it <php-container> bash -c "cd /var/www/html/wp-content/plugins/mail-system && composer test"

# Or inside container at plugin dir:
composer test                    # All tests
./vendor/bin/phpunit tests/Unit/SubscriberTest.php  # Single file
```

## SCSS Structure

Follow `SCSS_GUIDELINES.md`. Uses 7-1 pattern with `@use`/`@forward`:

```scss
admin/scss/
├── main.scss              # Entry point (@use 'abstracts', 'base', 'components')
├── abstracts/_variables.scss  # $color-*, $spacing-*, $font-*
├── abstracts/_mixins.scss     # @mixin card, responsive breakpoints
├── base/_base.scss            # Reset, general styles
└── components/                # _dashboard.scss, _forms.scss, _queue.scss, etc.
```

**Key variables**: `$color-primary: #2271b1`, 4px grid spacing (`$spacing-1` to `$spacing-8`), status colors (`$color-success-bg`, etc.)

## Key Patterns

**Email placeholders**: `{first_name}`, `{last_name}`, `{email}`, `{unsubscribe_link}`, `{unsubscribe_url}`

**Admin partials**: `admin/partials/*.php` - PHP templates included by `MSKD_Admin` methods

**Security**: Always use `wp_verify_nonce()`, `current_user_can('manage_options')`, `sanitize_*()` functions

**Translations**: Text domain is `mail-system`. All user-facing strings are in Bulgarian.

## Common Tasks

- **Add admin page**: Add `add_submenu_page()` in `MSKD_Admin::register_menu()`, create partial in `admin/partials/`
- **Add AJAX endpoint**: Add `add_action('wp_ajax_mskd_*')` in class `init()`, implement handler method
- **Modify queue processing**: Edit `MSKD_Cron_Handler::process_queue()`, constant `MSKD_BATCH_SIZE` controls batch size
- **Add subscriber field**: Update `class-activator.php` schema, `MSKD_Admin` CRUD methods, partials

## Composer Scripts (Development Only)

**Note**: Composer is only required for development. The plugin works without `vendor/` directory for end users.

All composer and PHP commands must run **inside the Docker PHP container**:

```bash
# From host: run commands via docker exec
docker exec -it <php-container> bash -c "cd /var/www/html/wp-content/plugins/mail-system && composer install"
docker exec -it <php-container> bash -c "cd /var/www/html/wp-content/plugins/mail-system && composer test"

# Or enter the container first, then navigate to plugin dir:
docker exec -it <php-container> bash
cd /var/www/html/wp-content/plugins/mail-system

# Available composer scripts (development only):
composer install       # Install dev dependencies (required before running tests)
composer test          # Run PHPUnit tests
composer test:unit     # Run only unit tests
composer phpcs         # WordPress coding standards check
composer phpcbf        # Auto-fix coding standards
composer translations  # Compile .po to .mo translation files
```

### Autoloading

The plugin uses a **built-in autoloader** that handles both:
- Legacy classes (`MSKD_*`): `MSKD_Admin`, `MSKD_Cron_Handler`, etc.
- PSR-4 namespaced classes (`MSKD\*`): `MSKD\Admin\Admin`, `MSKD\Services\List_Service`, etc.

Composer's autoloader is loaded only if `vendor/autoload.php` exists (for dev dependencies like PHPUnit).

## WordPress Coding Standards (WPCS)

**All PHP code MUST follow WordPress Coding Standards.** Before submitting PRs:

1. **Check for violations**: `composer phpcs`
2. **Auto-fix what's possible**: `composer phpcbf`
3. **Manually fix remaining issues**

### Key WPCS Rules to Follow

| Rule | Example |
|------|---------|
| **Yoda conditions** | `if ( 'value' === $var )` NOT `if ( $var === 'value' )` |
| **wp_unslash before sanitize** | `sanitize_text_field( wp_unslash( $_POST['field'] ) )` |
| **Pre-increment** | `++$counter;` NOT `$counter++;` |
| **Nonce verification** | Always check `isset()` before `wp_verify_nonce()` |
| **Direct file operations** | Add `// phpcs:ignore` comment with reason if `fopen()`/`file_get_contents()` is needed for local files |

### phpcs:ignore Usage

For legitimate exceptions (e.g., reading uploaded temp files), use inline comments:

```php
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local uploaded file.
$content = file_get_contents( $file_path );

// phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition -- Standard CSV reading pattern.
while ( false !== ( $row = fgetcsv( $handle ) ) ) {
```

## Translation Workflow

**IMPORTANT**: When adding or modifying user-facing strings, always update translations:

1. **Add new strings** to all `.po` files in `languages/`:
   - `mail-system.pot` (template - empty msgstr)
   - `mail-system-bg_BG.po` (Bulgarian translation)
   - `mail-system-de_DE.po` (German translation)

2. **Compile translations** after updating `.po` files:
   ```bash
   docker exec -it <php-container> bash -c "cd /var/www/html/wp-content/plugins/mail-system && composer translations"
   ```

3. **String format** in `.po` files:
   ```po
   #: path/to/file.php
   msgid "Original English string"
   msgstr "Translated string"
   ```

All user-facing strings must use `__()`, `_e()`, or `esc_html__()` with text domain `mail-system`.
