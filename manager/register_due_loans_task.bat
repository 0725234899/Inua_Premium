@echo off
REM Register a scheduled task that runs daily at 01:00 and executes the cron_send_due_loans.php script
REM Run this file as Administrator to create the scheduled task.

SET TASKNAME=SendDueLoansReports
SET PHP_EXE=C:\xampp\php\php.exe
SET SCRIPT=C:\xampp\htdocs\inua_premium_services\manager\cron_send_due_loans.php

REM Delete existing task if present
schtasks /Query /TN "%TASKNAME%" >nul 2>&1
IF %ERRORLEVEL% EQU 0 (
    echo Deleting existing scheduled task "%TASKNAME%"...
    schtasks /Delete /TN "%TASKNAME%" /F
)

echo Creating scheduled task "%TASKNAME%" to run daily at 14:00.
REM This will run under the current user account. You may be prompted for credentials if needed.
schtasks /Create /SC DAILY /TN "%TASKNAME%" /TR "\"%PHP_EXE%\" \"%SCRIPT%\"" /ST 14:00 /F

echo Scheduled task created. Use Task Scheduler to review/run the task.
pause
