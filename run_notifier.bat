@echo off
cd /d C:\zoho-notifier
php src\scripts\filter-invoice-reminders.php
php notify_due_customers.php
pause
