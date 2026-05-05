# Mail System by Katsarov Design

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

Email newsletter management system with subscribers, lists, and sending queue. Supports English (default), Bulgarian, and German interfaces.

## 📋 Description

**Mail System by Katsarov Design** is a WordPress plugin for managing email newsletters with full internationalization support.

### Key Features

- **Subscriber Management** - Add, edit, and delete subscribers
- **Subscriber Statuses** - Active, Inactive, Unsubscribed
- **Lists** - Organize your subscribers into different lists
- **Import/Export** - Import and export subscribers and lists via CSV or JSON
- **Sending Queue** - Emails are added to a queue and sent automatically
- **WP-Cron Integration** - Automatic email sending via WP-Cron
- **SMTP Support** - Configure an external SMTP server for reliable sending
- **Shortcode for Subscription Form** - Easily add a subscription form
- **Unsubscribe Link** - Automatic generation of unsubscribe links
- **Multi-language Support** - English, Bulgarian, and German interfaces

### Technical Specifications

| Feature | Value |
|---------|-------|
| Sending Speed | Configurable (default: 10 emails/minute) |
| Sending Method | `wp_mail()` or SMTP (configurable) |
| Supported Protocols | SSL, TLS (StartTLS), no encryption |
| Minimum PHP Version | 7.4 |
| Minimum WP Version | 5.0 |

### Email Placeholders

| Placeholder | Description |
|-------------|-------------|
| `{first_name}` | Subscriber's first name |
| `{last_name}` | Subscriber's last name |
| `{email}` | Subscriber's email |
| `{unsubscribe_link}` | Unsubscribe link |
| `{unsubscribe_url}` | Unsubscribe URL (without HTML) |

## 🚀 Installation

### Manual Installation

1. Upload the `mail-system` folder to `/wp-content/plugins/`
2. Activate the plugin from the "Plugins" menu in WordPress
3. Go to the "Emails" menu to start using the plugin

**Note:** No Composer or `vendor/` directory is required. The plugin includes its own autoloader and works out of the box.

## 🌍 Internationalization (i18n)

### Supported Languages

The plugin supports the following languages out of the box:

| Language | Locale | Status |
|----------|--------|--------|
| English | `en_US` | Default (source language) |
| Bulgarian | `bg_BG` | Fully translated |
| German | `de_DE` | Fully translated |

### How Language Detection Works

The plugin automatically detects and uses the appropriate language based on WordPress settings:

1. **User Language** - If a user has set a preferred language in their profile, that language is used
2. **Site Language** - If no user preference is set, the site's general language setting is used (Settings → General → Site Language)
3. **Fallback** - If translations for the detected language are not available, English (the source language) is displayed

The plugin uses WordPress's standard `load_plugin_textdomain()` function with the text domain `mail-system`.

### Adding New Translations

To add a new translation:

1. **Copy the POT file** - Copy `languages/mail-system.pot` to a new file named `mail-system-{locale}.po` (e.g., `mail-system-fr_FR.po` for French)

2. **Translate the strings** - Open the PO file with a tool like [Poedit](https://poedit.net/) or a text editor and translate all `msgstr` values

3. **Compile the MO file** - Generate the binary MO file:
   ```bash
   msgfmt -o mail-system-{locale}.mo mail-system-{locale}.po
   ```

4. **Place the files** - Put both the `.po` and `.mo` files in the `languages/` folder

### Example PO File Structure

```po
# Header
msgid ""
msgstr ""
"Project-Id-Version: Mail System by Katsarov Design 1.1.0\n"
"Language: fr_FR\n"
"MIME-Version: 1.0\n"
"Content-Type: text/plain; charset=UTF-8\n"

# Translation entry
#: admin/class-admin.php
msgid "Dashboard"
msgstr "Tableau de bord"
```

### Translation Files

| File | Purpose |
|------|---------|
| `mail-system.pot` | Template file with all translatable strings |
| `mail-system-{locale}.po` | Human-readable translation file |
| `mail-system-{locale}.mo` | Compiled binary file used by WordPress |

## ⚙️ Configuration

### SMTP Settings

For more reliable email sending, we recommend configuring an SMTP server.

**Step 1:** Go to **Emails → Settings**

**Step 2:** Enable SMTP and fill in the settings:

| Setting | Description |
|---------|-------------|
| **SMTP Host** | SMTP server address (e.g., `smtp.gmail.com`, `smtp.mailgun.org`) |
| **SMTP Port** | Server port. Standard: 25, 465 (SSL), 587 (TLS) |
| **Encryption** | SSL, TLS (StartTLS), or no encryption |
| **SMTP Authentication** | Enable if the server requires username and password |
| **SMTP Username** | Usually your email address |
| **SMTP Password** | Access password. For Gmail, use App Password |

**Step 3:** Use the "Send test email" button to verify settings

#### Example SMTP Settings

**Gmail:**
- Host: `smtp.gmail.com`
- Port: `587`
- Encryption: TLS
- Note: Create an App Password from Google account settings

**Mailgun:**
- Host: `smtp.mailgun.org`
- Port: `587`
- Encryption: TLS

**SendGrid:**
- Host: `smtp.sendgrid.net`
- Port: `587`
- Encryption: TLS
- Username: `apikey`

### System Cron Recommendation

For more reliable email sending, we recommend using system cron instead of WP-Cron.

**Step 1:** Add to `wp-config.php`:

```php
define('DISABLE_WP_CRON', true);
```

**Step 2:** Set up system cron:

```bash
* * * * * php /path/to/wordpress/wp-cron.php
```

## 📖 Usage

### Subscription Form Shortcode

Basic usage:

```
[mskd_subscribe_form]
```

With parameters:

```
[mskd_subscribe_form list_id="1" title="Subscribe to news"]
```

| Parameter | Description | Default |
|-----------|-------------|---------|
| `list_id` | ID of the list to subscribe to | 0 (no list) |
| `title` | Form title | "Subscribe" |

### Admin Menus

After activation, you will see a new **"Emails"** menu with submenus:

- **Dashboard** - General statistics
- **Subscribers** - Subscriber management
- **Lists** - List management
- **New email** - Create and send emails
- **Queue** - View the sending queue
- **Settings** - Plugin configuration
- **Import/Export** - Import and export subscribers and lists

### Import/Export

The Import/Export feature allows you to easily migrate or back up your subscribers and lists.

#### Export

Go to **Emails → Import/Export** to export data:

- **Subscribers** - Export all subscribers or filter by list and status
- **Lists** - Export all mailing lists
- **Formats** - CSV (Excel-compatible) or JSON

#### Import

Import subscribers or lists from CSV or JSON files:

**Subscribers CSV Format:**
```csv
email,first_name,last_name,status,lists
john@example.com,John,Doe,active,Newsletter;Updates
jane@example.com,Jane,Smith,active,Newsletter
```

**Lists CSV Format:**
```csv
name,description
Newsletter,Weekly newsletter subscribers
Updates,Product update notifications
```

**Import Options:**
- **Update existing subscribers** - Updates information for subscribers that already exist
- **Assign to lists from file** - Creates lists from the file if they don't exist and assigns subscribers

## 🗄️ Database

The plugin creates 4 tables:

| Table | Description |
|-------|-------------|
| `{prefix}mskd_subscribers` | Subscribers |
| `{prefix}mskd_lists` | Lists |
| `{prefix}mskd_subscriber_list` | Subscriber-list relationship |
| `{prefix}mskd_queue` | Sending queue |

## ❓ FAQ

### How do I add a subscription form?

Use the shortcode `[mskd_subscribe_form]` on a page or post.

### How many emails are sent per minute?

By default, 10 emails are sent per minute. This limit can be configured in the plugin settings (**Emails → Settings → Sending settings → Emails per minute**). You can adjust this value between 1 and 1000 emails per minute, depending on your hosting provider limits.

### Why aren't emails being sent?

Check if:

1. There are pending emails in the queue
2. WP-Cron is working correctly
3. Your site receives visits (WP-Cron runs on visits)

For more reliable sending, configure SMTP and/or system cron.

### How do I configure SMTP?

Go to **Emails → Settings** and enable SMTP. Fill in the SMTP host, port, encryption, and authentication data. Use the "Send test email" button to verify settings. For more information, see the **SMTP Settings** section above.

### How do I change the sending speed?

Go to **Emails → Settings → Sending settings** and adjust the "Emails per minute" value. You can set any value between 1 and 1000. Higher values may exceed your hosting provider limits, so adjust according to your needs.

### How do I change the interface language?

The interface language automatically follows your WordPress site language setting. Go to **Settings → General → Site Language** to change it. If translations are available for that language, they will be used automatically.

## 🔧 Development

### Requirements

**For End Users (Clients):**
- PHP 7.4+
- WordPress 5.0+
- No Composer required - the plugin works without the `vendor/` directory

**For Developers:**
- All of the above, plus:
- Composer (for running tests and coding standards checks)
- Docker (recommended for development environment)

### Installing Development Dependencies

```bash
# Inside Docker PHP container
docker exec -it <php-container> bash -c "cd /var/www/html/wp-content/plugins/mail-system && composer install"
```

### Available Composer Scripts (Development Only)

```bash
composer test          # Run PHPUnit tests
composer test:unit     # Run only unit tests
composer phpcs         # Check WordPress coding standards
composer phpcbf        # Auto-fix coding standards violations
composer translations  # Compile .po to .mo translation files
```

### Project Structure

```
mail-system/
├── mail-system.php  # Main file
├── composer.json
├── README.md
├── CHANGELOG.md
├── uninstall.php
├── includes/
│   ├── class-activator.php
│   ├── class-deactivator.php
│   └── services/
│       ├── class-cron-handler.php
│       └── class-smtp-mailer.php
├── admin/
│   ├── class-admin.php
│   ├── css/
│   ├── js/
│   └── partials/
├── public/
│   ├── class-public.php
│   ├── css/
│   ├── js/
│   └── partials/
└── languages/
    ├── mail-system.pot
    ├── mail-system-bg_BG.po
    ├── mail-system-bg_BG.mo
    ├── mail-system-de_DE.po
    └── mail-system-de_DE.mo
```

### Naming Conventions

| Type | Prefix | Example |
|------|--------|---------|
| Constants | `MSKD_` | `MSKD_BATCH_SIZE` |
| Classes | `MSKD_` | `MSKD_Admin` |
| Functions | `mskd_` | `mskd_load_textdomain()` |
| Tables | `mskd_` | `wp_mskd_subscribers` |
| Hooks | `mskd_` | `mskd_process_queue` |

## 📄 License

This plugin is licensed under [GPL v2 or later](https://www.gnu.org/licenses/gpl-2.0.html).

## 👤 Author

**Katsarov Design**

- Website: [https://katsarov.design](https://katsarov.design)

## 🤝 Contributing

Contributions are welcome! Please read our [Contributing Guidelines](.github/CONTRIBUTING.md) before submitting a pull request.

### Quick Start

1. Fork the repository
2. Create an issue or find an existing one
3. Create a feature branch (`git checkout -b feature/issue-123-description`)
4. Make your changes following [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
5. Run tests and coding standards checks
6. Commit your changes (`git commit -m "Fix #123: Brief description"`)
7. Push to your fork (`git push origin feature/issue-123-description`)
8. Open a Pull Request referencing the issue

**Important**: The `main` branch is protected. All changes must go through pull requests, and each issue requires its own PR.
