# CourtConnect

## Expire unpaid and rejected-payment bookings

Apply `database/migrations/20261018_payment_rejection_resubmission_window.sql` before deploying the rejected-payment expiry workflow. Rejected GCash payments retain their reservation for 24 hours after rejection; if the player does not submit a replacement payment by the deadline, the booking is canceled and its held court times are released.

The application performs lazy cleanup on HTTP requests. To also run cleanup every minute with Windows Task Scheduler, create a task with:

- Program/script: `C:\xampp\php\php.exe`
- Add arguments: `C:\xampp\htdocs\CourtConnect\cleanup_expired.php`
- Start in: `C:\xampp\htdocs\CourtConnect`
- Trigger: Daily, repeat every 1 minute indefinitely

Equivalent command prompt command:

```bat
schtasks /Create /SC MINUTE /MO 1 /TN "CourtConnectExpireBookings" /TR "C:\xampp\php\php.exe C:\xampp\htdocs\CourtConnect\cleanup_expired.php" /F
```
