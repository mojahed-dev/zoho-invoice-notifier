@echo off
cd /d "C:\Mojahed Files\Codes\zoho-notifier"

REM Create log folder if it doesn’t exist (relative to project root)
if not exist "log" mkdir "log"

REM Run and log outputs with timestamp
echo [%DATE% %TIME%] --- STARTING REMINDER --- >> log\task_log.txt
php src\scripts\filter-invoice-reminders.php >> log\task_log.txt 2>&1

echo [%DATE% %TIME%] --- STARTING RETRIES --- >> log\task_log.txt
php src\scripts\RetryFailedNotifications.php >> log\task_log.txt 2>&1

echo [%DATE% %TIME%] --- STARTING MAINTENANCE --- >> log\task_log.txt
php src\scripts\MaintenanceTasks.php >> log\task_log.txt 2>&1

echo [%DATE% %TIME%] --- TASKS COMPLETED --- >> log\task_log.txt

echo ✅ All tasks executed. Logs saved to log\task_log.txt
pause
