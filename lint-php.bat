@echo off
cd /d "%~dp0"
echo Running PHP lint on all PHP files under:
echo %CD%
echo.

for /R %%f in (*.php) do (
    echo Checking %%f
    "C:\php\php.exe" -l "%%f"

    echo.
)

echo ----------------------------------------
echo Lint finished.
echo If you saw "Errors parsing" above, fix those files.
echo ----------------------------------------
pause
