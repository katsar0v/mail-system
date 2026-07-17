# API Reference & Developer Guide

This document provides comprehensive documentation for third-party plugin developers who want to extend or integrate with Mail System by Katsarov Design.

## Table of Contents

1. [Overview](#overview)
2. [Quick Start](#quick-start)
3. [Hooks Reference](#hooks-reference)
   - [Lists Hooks](#lists-hooks)
   - [Subscribers Hooks](#subscribers-hooks)
   - [Email Hooks](#email-hooks)
4. [Services API](#services-api)
   - [MSKD_List_Provider](#mskd_list_provider)
   - [MSKD_SMTP_Mailer](#mskd_smtp_mailer)
   - [Template_Service](#template_service)
5. [Database Schema](#database-schema)
6. [Email Templates](#email-templates)
7. [Examples](#examples)
8. [Best Practices](#best-practices)
9. [Troubleshooting](#troubleshooting)

---

## Overview

The Mail System provides a hook-based architecture that allows third-party plugins to:

- **Register external lists** - Add automated/dynamic subscriber lists
- **Register external subscribers** - Add subscribers from external sources (CRM, WooCommerce, etc.)
- **Control editability** - Lock specific lists or subscribers from being modified
- **Extend email functionality** - Modify email content, add custom placeholders
- **Manage email templates** - Create, edit, and use reusable email templates

### Key Concepts

| Concept | Description |
|---------|-------------|
| **Database entities** | Lists and subscribers stored in `mskd_*` tables |
| **External entities** | Lists and subscribers provided dynamically via hooks |
| **External ID prefix** | All external IDs are prefixed with `ext_` automatically |
| **Provider** | Name of the plugin/source providing external entities |

---

## Quick Start

### Register an External List

```php
add_filter( 'mskd_register_external_lists', 'my_plugin_register_lists' );

function my_plugin_register_lists( $lists ) {
    $lists[] = array(
        'id'          => 'my_plugin_vip_users',
        'name'        => 'VIP Users',
        'description' => 'Users with VIP membership',
        'provider'    => 'My Plugin',
        'subscriber_callback' => 'my_plugin_get_vip_subscribers',
    );
    return $lists;
}

function my_plugin_get_vip_subscribers() {
    // Return array of subscriber arrays
    // 'email' is required, 'first_name' and 'last_name' are optional
    return array(
        array(
            'email'      => 'john@example.com',
            'first_name' => 'John',
            'last_name'  => 'Doe',
        ),
        array(
            'email'      => 'jane@example.com',
            'first_name' => 'Jane',
            'last_name'  => 'Smith',
        ),
        array(
            'email'      => 'user@example.com', // Minimal - only email
        ),
    );
}
```

### Register External Subscribers

```php
add_filter( 'mskd_register_external_subscribers', 'my_plugin_register_subscribers', 10, 2 );

function my_plugin_register_subscribers( $subscribers, $args ) {
    $subscribers[] = array(
        'id'         => 'crm_contact_123',
        'email'      => 'john@example.com',
        'first_name' => 'John',
        'last_name'  => 'Doe',
        'status'     => 'active',
        'provider'   => 'My CRM Plugin',
    );
    return $subscribers;
}
```

---

## Hooks Reference

### Lists Hooks

#### `mskd_register_external_lists`

Register external/automated subscriber lists that appear alongside database lists.

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| `id` | string\|int | Yes | Unique identifier (auto-prefixed with `ext_`) |
| `name` | string | Yes | Display name in admin interface |
| `description` | string | No | Description shown in lists table |
| `provider` | string | No | Plugin/provider name. Default: "External" |
| `subscriber_callback` | callable | No | Returns array of subscriber arrays (see format below) |

**Example:**

```php
add_filter( 'mskd_register_external_lists', function( $lists ) {
    $lists[] = array(
        'id'                  => 'recent_buyers',
        'name'                => 'Recent Buyers',
        'description'         => 'Customers who purchased in the last 30 days',
        'provider'            => 'WooCommerce',
        'subscriber_callback' => 'get_recent_buyer_emails',
    );
    return $lists;
});
```

#### `mskd_list_is_editable`

Control whether a database list can be edited. External lists are always non-editable.

**Parameters:**
- `$is_editable` (bool) - Current editable status
- `$list_id` (int) - The list ID

**Example:**

```php
add_filter( 'mskd_list_is_editable', function( $is_editable, $list_id ) {
    // Lock system lists
    $locked = array( 1, 2, 3 );
    return in_array( $list_id, $locked, true ) ? false : $is_editable;
}, 10, 2 );
```

---

### Subscribers Hooks

#### `mskd_register_external_subscribers`

Register external subscribers that appear in the Subscribers admin page.

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| `id` | string\|int | Yes | Unique identifier (auto-prefixed with `ext_`) |
| `email` | string | Yes | Valid email address |
| `first_name` | string | No | Subscriber's first name |
| `last_name` | string | No | Subscriber's last name |
| `status` | string | No | `active`, `inactive`, or `unsubscribed` |
| `provider` | string | No | Plugin/provider name |
| `lists` | array | No | Array of list IDs subscriber belongs to |

**Parameters:**
- `$subscribers` (array) - Current external subscribers
- `$args` (array) - Query arguments (status filter, pagination)

**Example:**

```php
add_filter( 'mskd_register_external_subscribers', function( $subscribers, $args ) {
    // Respect status filter
    $my_subs = get_my_external_subscribers();
    
    if ( ! empty( $args['status'] ) ) {
        $my_subs = array_filter( $my_subs, function( $sub ) use ( $args ) {
            return $sub['status'] === $args['status'];
        });
    }
    
    return array_merge( $subscribers, $my_subs );
}, 10, 2 );
```

#### `mskd_subscriber_is_editable`

Control whether a database subscriber can be edited.

**Parameters:**
- `$is_editable` (bool) - Current editable status
- `$subscriber_id` (int) - The subscriber ID

---

### Email Hooks

#### `mskd_process_queue`

Action hook triggered by WP-Cron to process the email queue.

#### `mskd_subscriber_unsubscribed`

Fired when a subscriber unsubscribes.

**Parameters:**
- `$email` (string) - Subscriber's email
- `$token` (string) - Unsubscribe token

**Example:**

```php
add_action( 'mskd_subscriber_unsubscribed', function( $email, $token ) {
    // Sync unsubscribe with external system
    if ( strpos( $token, 'ext_' ) === 0 ) {
        my_crm_mark_unsubscribed( $email );
    }
}, 10, 2 );
```

---

## Services API

### MSKD_List_Provider

Static class for list and subscriber management.

```php
require_once MSKD_PLUGIN_DIR . 'includes/services/class-mskd-list-provider.php';
```

#### List Methods

| Method | Description | Returns |
|--------|-------------|---------|
| `get_all_lists()` | Get all lists (database + external) | `array` |
| `get_database_lists()` | Get only database lists | `array` |
| `get_external_lists()` | Get external lists from filter | `array` |
| `get_list( $list_id )` | Get a single list by ID | `object\|null` |
| `is_list_editable( $list_id )` | Check if list can be edited | `bool` |
| `list_exists( $list_id )` | Check if list exists | `bool` |
| `get_list_subscriber_count( $list )` | Get total subscriber count | `int` |
| `get_list_active_subscriber_count( $list )` | Get active subscriber count | `int` |
| `get_list_subscriber_ids( $list )` | Get active subscriber IDs | `array` |
| `get_list_subscribers_full( $list )` | Get full subscriber objects | `array` |

#### Subscriber Methods

| Method | Description | Returns |
|--------|-------------|---------|
| `get_all_subscribers( $args )` | Get all subscribers | `array` |
| `get_database_subscribers( $args )` | Get database subscribers | `array` |
| `get_external_subscribers( $args )` | Get external subscribers | `array` |
| `get_subscriber( $id )` | Get subscriber by ID | `object\|null` |
| `is_external_id( $id )` | Check if ID is external (`ext_*`) | `bool` |
| `is_subscriber_editable( $id )` | Check if subscriber can be edited | `bool` |
| `get_total_subscriber_count( $status )` | Get total count by status | `int` |

#### Utility Methods

| Method | Description |
|--------|-------------|
| `invalidate_cache()` | Clear external lists cache |

**Usage Example:**

```php
// Get all lists
$all_lists = MSKD_List_Provider::get_all_lists();

// Get external list by ID
$list = MSKD_List_Provider::get_list( 'ext_my_plugin_users' );

// Get subscriber count
$count = MSKD_List_Provider::get_list_active_subscriber_count( $list );

// Get subscribers for a list
$subscriber_ids = MSKD_List_Provider::get_list_subscriber_ids( $list );
```

---

### MSKD_SMTP_Mailer

SMTP email sending service using PHPMailer.

```php
require_once MSKD_PLUGIN_DIR . 'includes/services/class-smtp-mailer.php';

$mailer = new MSKD_SMTP_Mailer();
```

| Method | Description | Returns |
|--------|-------------|---------|
| `is_enabled()` | Check if SMTP is configured | `bool` |
| `send( $to, $subject, $body, $headers )` | Send an email | `bool` |
| `test_connection()` | Test SMTP connection | `array` |
| `get_last_error()` | Get last error message | `string` |
| `get_debug_log()` | Get debug log array | `array` |

---

### Template_Service

Service for managing email templates.

```php
use MSKD\Services\Template_Service;

$template_service = new Template_Service();
```

#### Methods

| Method | Description | Returns |
|--------|-------------|---------|
| `get_all( $args )` | Get all templates with optional filters | `array` |
| `get_by_id( $id )` | Get template by ID | `object\|null` |
| `get_by_name( $name )` | Get template by name | `object\|null` |
| `create( $data )` | Create a new template | `int\|false` |
| `update( $id, $data )` | Update a template | `bool` |
| `delete( $id )` | Delete a template | `bool` |
| `duplicate( $id )` | Duplicate a template | `int\|false` |
| `count( $type, $status )` | Get template count | `int` |
| `get_predefined()` | Get all predefined templates | `array` |
| `get_custom()` | Get all custom templates | `array` |
| `get_active()` | Get all active templates | `array` |
| `install_defaults()` | Install default predefined templates | `void` |

#### Usage Example

```php
use MSKD\Services\Template_Service;

$template_service = new Template_Service();

// Create a custom template
$template_id = $template_service->create( array(
    'name'    => 'My Newsletter Template',
    'subject' => 'Weekly Newsletter',
    'content' => '<h1>Hello {first_name}!</h1><p>Your content here...</p>',
    'type'    => 'custom',
    'status'  => 'active',
) );

// Get a template by ID
$template = $template_service->get_by_id( $template_id );

// Update a template
$template_service->update( $template_id, array(
    'subject' => 'Updated Weekly Newsletter',
) );

// Duplicate a template
$new_id = $template_service->duplicate( $template_id );

// Delete a template (only custom templates can be deleted)
$template_service->delete( $template_id );
```

---

## Database Schema

### Tables

All tables use the `{$wpdb->prefix}mskd_` prefix.

#### `mskd_subscribers`

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint(20) | Primary key |
| `email` | varchar(255) | Unique email address |
| `first_name` | varchar(100) | First name |
| `last_name` | varchar(100) | Last name |
| `status` | enum | `active`, `inactive`, `unsubscribed` |
| `unsubscribe_token` | varchar(64) | Unique unsubscribe token |
| `created_at` | datetime | Creation timestamp |
| `updated_at` | datetime | Last update timestamp |

#### `mskd_lists`

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint(20) | Primary key |
| `name` | varchar(255) | List name |
| `description` | text | List description |
| `created_at` | datetime | Creation timestamp |

#### `mskd_subscriber_list`

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint(20) | Primary key |
| `subscriber_id` | bigint(20) | FK to subscribers |
| `list_id` | bigint(20) | FK to lists |
| `subscribed_at` | datetime | Subscription timestamp |

#### `mskd_templates`

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint(20) | Primary key |
| `name` | varchar(255) | Template name |
| `subject` | varchar(255) | Default email subject |
| `content` | longtext | HTML email content |
| `json_content` | longtext | JSON content for visual editor |
| `thumbnail` | varchar(500) | Thumbnail image URL |
| `type` | enum | `predefined`, `custom` |
| `status` | enum | `active`, `inactive` |
| `created_at` | datetime | Creation timestamp |
| `updated_at` | datetime | Last update timestamp |

#### `mskd_queue`

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint(20) | Primary key |
| `subscriber_id` | bigint(20) | FK to subscribers (0 for external) |
| `subscriber_data` | text | JSON data for external subscribers |
| `subject` | varchar(255) | Email subject |
| `body` | longtext | Email body (HTML) |
| `status` | enum | `pending`, `processing`, `sent`, `failed` |
| `scheduled_at` | datetime | Scheduled send time |
| `sent_at` | datetime | Actual send time |
| `attempts` | int | Retry attempt count |
| `error_message` | text | Last error message |
| `created_at` | datetime | Creation timestamp |

---

## Email Templates

The Mail System includes a comprehensive email templates feature that allows users to create reusable email templates.

### Template Types

| Type | Description |
|------|-------------|
| **Predefined** | Built-in templates provided by the system. Cannot be deleted but can be duplicated. |
| **Custom** | User-created templates. Can be edited, duplicated, and deleted. |

### Default Templates

The system includes the following predefined templates:

1. **Blank Template** - An empty starting point
2. **Newsletter** - Standard newsletter layout with header, content area, and footer
3. **Welcome Email** - Template for welcoming new subscribers
4. **Promotional** - Template for promotional offers with call-to-action buttons

### Using Templates

Templates can be used when composing a new email:

1. Navigate to **Emails > Templates**
2. Click **"Use"** on any template
3. The compose form will be pre-filled with the template's subject and content
4. Modify as needed and send

### Template Placeholders

All templates support the standard email placeholders:

| Placeholder | Description |
|-------------|-------------|
| `{first_name}` | Subscriber's first name |
| `{last_name}` | Subscriber's last name |
| `{email}` | Subscriber's email address |
| `{unsubscribe_link}` | HTML link to unsubscribe |
| `{unsubscribe_url}` | Raw unsubscribe URL |

### Programmatic Template Usage

```php
use MSKD\Services\Template_Service;

$template_service = new Template_Service();

// Get a template
$template = $template_service->get_by_id( 1 );

// Use the template content
$subject = $template->subject;
$content = $template->content;

// Apply placeholders
$content = str_replace(
    array( '{first_name}', '{last_name}', '{email}' ),
    array( $subscriber->first_name, $subscriber->last_name, $subscriber->email ),
    $content
);
```

---

## Examples

### WooCommerce Integration

Complete example of integrating WooCommerce customers:

```php
<?php
/**
 * Plugin Name: MSKD WooCommerce Integration
 * Description: Adds WooCommerce customer lists to Mail System
 */

class MSKD_WooCommerce_Integration {
    
    public function __construct() {
        add_filter( 'mskd_register_external_lists', array( $this, 'register_lists' ) );
        add_filter( 'mskd_register_external_subscribers', array( $this, 'register_subscribers' ), 10, 2 );
    }
    
    public function register_lists( $lists ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return $lists;
        }
        
        // Recent Customers
        $lists[] = array(
            'id'                  => 'wc_recent_customers',
            'name'                => 'Recent Customers (30 days)',
            'description'         => 'Customers with orders in the last 30 days',
            'provider'            => 'WooCommerce',
            'subscriber_callback' => array( $this, 'get_recent_customers' ),
        );
        
        // VIP Customers
        $lists[] = array(
            'id'                  => 'wc_vip_customers',
            'name'                => 'VIP Customers',
            'description'         => 'Customers with total spend over $500',
            'provider'            => 'WooCommerce',
            'subscriber_callback' => array( $this, 'get_vip_customers' ),
        );
        
        return $lists;
    }
    
    public function register_subscribers( $subscribers, $args ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return $subscribers;
        }
        
        $customers = get_users( array( 'role' => 'customer', 'number' => 100 ) );
        
        foreach ( $customers as $customer ) {
            $sub = array(
                'id'         => 'wc_customer_' . $customer->ID,
                'email'      => $customer->user_email,
                'first_name' => get_user_meta( $customer->ID, 'billing_first_name', true ),
                'last_name'  => get_user_meta( $customer->ID, 'billing_last_name', true ),
                'status'     => 'active',
                'provider'   => 'WooCommerce',
            );
            
            // Apply status filter if provided
            if ( empty( $args['status'] ) || $sub['status'] === $args['status'] ) {
                $subscribers[] = $sub;
            }
        }
        
        return $subscribers;
    }
    
    public function get_recent_customers() {
        global $wpdb;
        
        return $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT pm.meta_value 
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE pm.meta_key = '_billing_email'
            AND p.post_type = 'shop_order'
            AND p.post_date > %s",
            date( 'Y-m-d', strtotime( '-30 days' ) )
        ) );
    }
    
    public function get_vip_customers() {
        global $wpdb;
        
        if ( ! class_exists( 'WC_Customer' ) ) {
            return array();
        }
        
        return $wpdb->get_col(
            "SELECT email FROM {$wpdb->prefix}wc_customer_lookup 
            WHERE total_spend > 500"
        );
    }
}

new MSKD_WooCommerce_Integration();
```

### CRM Integration with External Subscribers

```php
<?php
/**
 * Plugin Name: MSKD CRM Integration
 */

add_filter( 'mskd_register_external_lists', function( $lists ) {
    $lists[] = array(
        'id'                  => 'crm_leads',
        'name'                => 'CRM Leads',
        'description'         => 'Active leads from CRM system',
        'provider'            => 'My CRM',
        'subscriber_callback' => 'get_crm_leads',
    );
    return $lists;
});

function get_crm_leads() {
    // Fetch from CRM API and return subscriber arrays
    $leads = my_crm_api_get_leads();
    
    return array_map( function( $lead ) {
        return array(
            'email'      => $lead->email,
            'first_name' => $lead->first_name,
            'last_name'  => $lead->last_name,
        );
    }, $leads );
}
```

---

## Best Practices

### 1. Use Unique Prefixes

Prevent ID collisions by prefixing with your plugin slug:

```php
// ✅ Good
'id' => 'myplugin_premium_users'

// ❌ Bad - may collide
'id' => 'premium_users'
```

### 2. Cache Expensive Queries

```php
function get_premium_subscribers() {
    $cache_key = 'myplugin_premium_subs';
    $cached = wp_cache_get( $cache_key, 'mskd_external' );
    
    if ( false !== $cached ) {
        return $cached;
    }
    
    global $wpdb;
    $result = $wpdb->get_col( /* expensive query */ );
    
    wp_cache_set( $cache_key, $result, 'mskd_external', HOUR_IN_SECONDS );
    
    return $result;
}
```

### 3. Always Return Arrays

```php
function get_my_subscribers() {
    $result = some_function();
    return is_array( $result ) ? $result : array();
}
```

### 4. Respect Query Arguments

```php
add_filter( 'mskd_register_external_subscribers', function( $subscribers, $args ) {
    $my_subs = get_my_subscribers();
    
    // Apply status filter
    if ( ! empty( $args['status'] ) ) {
        $my_subs = array_filter( $my_subs, function( $sub ) use ( $args ) {
            return $sub['status'] === $args['status'];
        });
    }
    
    return array_merge( $subscribers, $my_subs );
}, 10, 2 );
```

### 5. Document Your Lists

```php
$lists[] = array(
    'id'          => 'wc_repeat_buyers',
    'name'        => 'Repeat Buyers',
    'description' => 'Customers with 3+ orders. Updated hourly via cron.',
    'provider'    => 'WooCommerce Pro',
);
```

### 6. Handle Unsubscribes

```php
add_action( 'mskd_subscriber_unsubscribed', function( $email, $token ) {
    // Sync with external system
    my_crm_mark_unsubscribed( $email );
}, 10, 2 );
```

---

## Troubleshooting

### List Not Appearing

1. Ensure your filter runs before admin pages load (`plugins_loaded` or earlier)
2. Verify list has required `id` and `name` properties
3. Check PHP error log for exceptions

### Subscriber Count Shows 0

1. Verify callback returns array of arrays with `email` key
2. Test callback directly: `var_dump( your_callback() );`
3. Check for PHP errors in the callback function

### Emails Not Sending

1. Verify `subscriber_callback` is callable
2. Check queue table for error messages
3. Ensure SMTP is configured in Settings
4. Review `MSKD_Cron_Handler::MAX_ATTEMPTS` (default: 3)

### External Subscriber Can't Receive Emails

1. Ensure `subscriber_callback` returns array of arrays with `email` key
2. Optionally include `first_name` and `last_name` for personalization
3. Data is stored as JSON in `subscriber_data` column

---

## Constants

| Constant | Default | Description |
|----------|---------|-------------|
| `MSKD_VERSION` | `1.1.2` | Plugin version |
| `MSKD_BATCH_SIZE` | `10` | Emails per cron run (configurable via settings) |
| `MSKD_PLUGIN_DIR` | — | Plugin directory path |
| `MSKD_PLUGIN_URL` | — | Plugin URL |

---

## Support

For questions, bug reports, or feature requests:

- **Website:** [katsarov.design](https://katsarov.design)
- **GitHub:** [Repository Issues](https://github.com/katsar0v/mail-system/issues)
