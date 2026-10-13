# CourtConnect

## Expire unpaid bookings

The application performs lazy cleanup on HTTP requests. To also run cleanup every minute with Windows Task Scheduler, create a task with:

- Program/script: `C:\xampp\php\php.exe`
- Add arguments: `C:\xampp\htdocs\CourtConnect\cleanup_expired.php`
- Start in: `C:\xampp\htdocs\CourtConnect`
- Trigger: Daily, repeat every 1 minute indefinitely

Equivalent command prompt command:

```bat
schtasks /Create /SC MINUTE /MO 1 /TN "CourtConnectExpireUnpaidBookings" /TR "C:\xampp\php\php.exe C:\xampp\htdocs\CourtConnect\cleanup_expired.php" /F
```
