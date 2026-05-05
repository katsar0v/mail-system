# Testing Guide for Mail System by Katsarov Design

## Test Structure

```
tests/
├── bootstrap.php              # PHPUnit bootstrap file
├── phpunit.xml                # PHPUnit configuration
└── Unit/
    ├── TestCase.php           # Base test case with common mocks
    ├── ActivatorTest.php      # Plugin activation tests
    ├── DeactivatorTest.php    # Plugin deactivation tests
    ├── SubscriberTest.php     # Subscriber CRUD tests
    ├── CronHandlerTest.php    # Email queue processing tests
    ├── PublicSubscriptionTest.php # Public subscription tests
    └── UnsubscribeTest.php    # Unsubscribe functionality tests
```

## Prerequisites

Tests should be run inside the PHP Docker container. The plugin is mounted at:

```
/var/www/html/wp-content/plugins/mail-system
```

## Running Tests in Docker

### 1. Enter the PHP container

```bash
docker exec -it <php-container-name> bash
```

### 2. Navigate to the plugin directory

```bash
cd /var/www/html/wp-content/plugins/mail-system
```

### 3. Install dependencies

```bash
composer install
```

### 4. Run tests

```bash
composer test
```

### One-liner (from host machine)

```bash
docker exec -it <php-container-name> bash -c "cd /var/www/html/wp-content/plugins/mail-system && composer install && composer test"
```

> **Note:** Replace `<php-container-name>` with your actual PHP container name (e.g., `radostna-php`, `php-fpm`, etc.)

## Running Tests (Alternative - Direct)

If you're already inside the container at the plugin directory:

### Run all tests
```bash
composer test
```

Or directly with PHPUnit:
```bash
./vendor/bin/phpunit --configuration tests/phpunit.xml
```

### Run a specific test suite
```bash
./vendor/bin/phpunit --configuration tests/phpunit.xml --testsuite Unit
```

### Run a specific test file
```bash
./vendor/bin/phpunit --configuration tests/phpunit.xml tests/Unit/ActivatorTest.php
```

### Run with coverage report
```bash
./vendor/bin/phpunit --configuration tests/phpunit.xml --coverage-html coverage/
```

## Test Suites Overview

### 1. ActivatorTest (5 tests)
Tests plugin activation lifecycle:
- `test_tables_created_on_activation` - Verifies all 4 database tables are created
- `test_cron_scheduled_on_activation` - Verifies cron event is scheduled
- `test_cron_not_rescheduled_if_exists` - Verifies no duplicate cron scheduling
- `test_default_options_set` - Verifies default settings are saved
- `test_db_version_stored` - Verifies database version is stored

### 2. DeactivatorTest (2 tests)
Tests plugin deactivation:
- `test_cron_unscheduled_on_deactivation` - Verifies cron is cleared
- `test_deactivation_when_no_cron_scheduled` - Handles missing cron gracefully

### 3. SubscriberTest (7 tests)
Tests subscriber management:
- `test_add_subscriber_with_valid_email` - Valid subscriber creation
- `test_add_subscriber_invalid_email_rejected` - Email validation
- `test_add_subscriber_duplicate_email_rejected` - Duplicate prevention
- `test_edit_subscriber_updates_data` - Subscriber editing
- `test_delete_subscriber_removes_from_lists` - Cascade deletion to lists
- `test_delete_subscriber_removes_pending_queue` - Cascade deletion to queue
- `test_subscriber_status_validation` - Status enum validation

### 4. CronHandlerTest (8 tests)
Tests email queue processing:
- `test_process_queue_sends_pending_emails` - Basic email sending
- `test_process_queue_respects_batch_size` - MSKD_BATCH_SIZE enforcement
- `test_process_queue_skips_inactive_subscribers` - Active-only filtering
- `test_process_queue_marks_sent_on_success` - Success status update
- `test_process_queue_marks_failed_on_error` - Failure handling
- `test_placeholder_replacement` - Email placeholder substitution
- `test_attempts_counter_incremented` - Retry counter
- `test_empty_queue_does_nothing` - Empty queue handling

### 5. PublicSubscriptionTest (6 tests)
Tests public-facing subscription:
- `test_ajax_subscribe_creates_new_subscriber` - New subscription
- `test_ajax_subscribe_reactivates_unsubscribed` - Re-subscription
- `test_ajax_subscribe_adds_to_list` - List assignment
- `test_ajax_subscribe_invalid_email_returns_error` - Email validation
- `test_shortcode_renders_form` - Shortcode output
- `test_ajax_subscribe_skips_duplicate_list_assignment` - Duplicate list prevention

### 6. UnsubscribeTest (8 tests)
Tests unsubscribe functionality:
- `test_valid_token_unsubscribes_user` - Successful unsubscribe
- `test_invalid_token_returns_error` - Invalid token handling
- `test_invalid_token_format_rejected` - Token format validation
- `test_token_with_special_chars_rejected` - Security validation
- `test_rate_limiting_prevents_abuse` - Rate limiting enforcement
- `test_rate_limit_counter_incremented` - Rate limit counter
- `test_unsubscribe_changes_status_to_unsubscribed` - Status update
- `test_no_query_param_does_nothing` - Missing param handling

## Dependencies

The following dev dependencies are required:

```json
{
    "require-dev": {
        "phpunit/phpunit": "^9.0",
        "yoast/phpunit-polyfills": "^2.0",
        "brain/monkey": "^2.6",
        "mockery/mockery": "^1.6"
    }
}
```

## Testing Approach

This test suite uses a **hybrid approach**:

1. **Unit Tests with Mocks**: All WordPress functions are mocked using Brain Monkey
2. **Database Mocking**: `$wpdb` is mocked using Mockery
3. **No WordPress Installation Required**: Tests run independently

### Key Libraries

- **Brain Monkey**: Mocks WordPress functions and hooks
- **Mockery**: Creates mock objects for `$wpdb` and other dependencies
- **PHPUnit**: Test framework

## Writing New Tests

1. Extend `MSKD\Tests\Unit\TestCase`
2. Use `$this->setup_wpdb_mock()` to create a mocked `$wpdb`
3. Use `Functions\expect()` or `Functions\stubs()` for WordPress functions
4. Clean up `$_POST`, `$_GET` in `tearDown()`

Example:
```php
<?php
namespace MSKD\Tests\Unit;

use Brain\Monkey\Functions;

class MyTest extends TestCase {
    public function test_something(): void {
        $wpdb = $this->setup_wpdb_mock();
        
        Functions\expect('some_wp_function')
            ->once()
            ->andReturn('value');
        
        // Your test logic here
        $this->assertTrue(true);
    }
}
```

## Code Coverage

To generate code coverage reports, ensure Xdebug or PCOV is installed:

```bash
./vendor/bin/phpunit --configuration tests/phpunit.xml --coverage-html coverage/
```

Then open `coverage/index.html` in a browser.
