@echo off
REM Wrapper so you don't have to remember PowerShell's execution-policy flag.
REM Usage: tools\setup-bel-pms.cmd -SeedDemoData -StartXampp
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0setup-bel-pms.ps1" %*
