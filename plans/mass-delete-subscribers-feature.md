# Mass Delete Subscribers Feature

## Overview
This feature adds the ability to delete multiple subscribers at once using the bulk actions dropdown in the subscribers management page.

## Implementation Details

### Files Modified

1. **[`admin/partials/subscribers.php`](admin/partials/subscribers.php:306)**
   - Added "Delete" option to bulk actions dropdown

2. **[`admin/js/admin-script.js`](admin/js/admin-script.js:633-648, 698-767)**
   - Updated bulk action toggle handler to show/hide apply button for delete action
   - Added delete action handling in bulk apply button click handler with:
     - Confirmation dialog showing subscriber count
     - AJAX request to `mskd_batch_delete_subscribers`
     - Visual removal of deleted rows from table
     - Form reset after successful deletion

3. **[`includes/Admin/class-admin-ajax.php`](includes/Admin/class-admin-ajax.php:42, 495-577)**
   - Added AJAX action registration for `mskd_batch_delete_subscribers`
   - Added `batch_delete_subscribers()` handler method with:
     - Nonce verification
     - Permission check
     - Input sanitization and validation
     - Batch deletion using Subscriber_Service
     - Success/error response handling

4. **[`includes/services/class-subscriber-service.php`](includes/services/class-subscriber-service.php:907-977)**
   - Added `batch_delete()` method that:
     - Accepts array of subscriber IDs
     - Validates and sanitizes IDs
     - Deletes each subscriber using existing `delete()` method
     - Cleans up pivot table and queue items
     - Returns array with success/failed counts and errors

5. **[`languages/mail-system-by-katsarov-design-bg_BG.po`](languages/mail-system-by-katsarov-design-bg_BG.po:2617-2636)**
   - Added Bulgarian translations for all new strings

6. **[`languages/mail-system-by-katsarov-design-de_DE.po`](languages/mail-system-by-katsarov-design-de_DE.po:2661-2675)**
   - Added German translations for all new strings

7. **[`languages/mail-system-by-katsarov-design.pot`](languages/mail-system-by-katsarov-design.pot:2626-2648)**
   - Added English strings to template file

## Translation Strings Added

| English String | Bulgarian Translation | German Translation |
|---------------|---------------------|------------------|
| Delete | Изтрий | Löschen |
| Are you sure you want to delete %d subscriber(s)? This action cannot be undone. | Сигурни ли сте, че искате да изтриете %d абонат? Това действие не може да бъде отменено. | Sind Sie sicher, dass Sie %d Abonnenten löschen möchten? Diese Aktion kann nicht rückgängig gemacht werden. |
| %d subscriber deleted successfully. | %d абонат е изтрит успешно. | %d Abonnent erfolgreich gelöscht. |
| %d subscribers deleted successfully. | %d абонати са изтрити успешно. | %d Abonnenten erfolgreich gelöscht. |
| No subscribers selected. | Не са избрани абонати. | Keine Abonnenten ausgewählt. |
| Failed to delete subscriber ID %d | Неуспешно изтриване на абонат с ID %d | Löschen des Abonnenten mit ID %d fehlgeschlagen |
| No subscribers were deleted. | Нито един абонат не беше изтрит. | Keine Abonnenten wurden gelöscht. |

## User Flow

1. User navigates to Subscribers page
2. User selects one or more subscribers using checkboxes
3. User selects "Delete" from bulk actions dropdown
4. "Apply" button appears
5. User clicks "Apply"
6. Confirmation dialog appears: "Are you sure you want to delete X subscriber(s)? This action cannot be undone."
7. User confirms deletion
8. AJAX request sent to `mskd_batch_delete_subscribers`
9. Server validates request and deletes subscribers
10. Deleted rows fade out from table
11. Success message displayed: "X subscriber(s) deleted successfully."
12. Checkboxes cleared and form reset

## Security Features

- **Nonce verification**: All AJAX requests include `check_ajax_referer('mskd_admin_nonce', 'nonce')`
- **Permission check**: `current_user_can('manage_options')` verified before processing
- **Input sanitization**: All user inputs sanitized using `wp_unslash()` and `sanitize_*()` functions
- **Yoda conditions**: All conditional checks use Yoda format (e.g., `'value' === $var`)

## Database Operations

The `batch_delete()` method in [`Subscriber_Service`](includes/services/class-subscriber-service.php:907-977) performs the following operations:

1. Validates and sanitizes subscriber IDs
2. For each subscriber ID:
   - Calls existing `delete()` method
   - Removes subscriber from `mskd_subscribers` table
   - Removes associations from `mskd_subscriber_list` pivot table
   - Removes pending queue items for that subscriber from `mskd_queue` table
3. Tracks success/failed counts
4. Returns detailed result array

## Next Steps

- [ ] Test mass deletion functionality
- [ ] Run PHP coding standards check (`composer phpcs`)
- [ ] Compile translations (`composer translations`)
- [ ] Create pull request using `gh` command line

## Branch

Feature implemented on branch: `feature/mass-delete-subscribers`
