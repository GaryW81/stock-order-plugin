@echo off
cd /d "%~dp0"
echo Running PHP lint on all PHP files under:
echo %CD%
echo.

REM ========================================================
REM 1) Standard PHP syntax check
REM ========================================================

for /R %%f in (*.php) do (
    echo Checking %%f
    "C:\php\php.exe" -l "%%f"
    echo.
)

echo ----------------------------------------
echo PHP lint finished.
echo If you saw "Errors parsing" above, fix those files.
echo ----------------------------------------
echo.


REM ========================================================
REM 2) UTF-8 BOM CHECK
REM ========================================================

echo Checking for UTF-8 BOM in PHP files...
echo (BOM = EF BB BF, causes "unexpected output" in WP)
echo.

for /R %%F in (*.php) do (
    powershell -NoProfile -Command "$bytes = Get-Content -Encoding Byte -TotalCount 3 '%%F'; if ($bytes.Length -ge 3 -and $bytes[0] -eq 239 -and $bytes[1] -eq 187 -and $bytes[2] -eq 191) { Write-Host 'BOM FOUND: %%F' -ForegroundColor Red }"
)

echo ----------------------------------------
echo BOM check complete.
echo ----------------------------------------
echo.


REM ========================================================
REM 3) WHITESPACE BEFORE PHP OPEN TAG CHECK
REM ========================================================
REM Detect files that do NOT start with "<?php" on the first line.
REM Leading spaces, blank lines, or BOM-like junk can break WP.
REM ========================================================

echo Checking for leading whitespace before "<?php" in PHP files...
echo.

for /R %%F in (*.php) do (
    powershell -NoProfile -Command "$firstLine = (Get-Content '%%F' -TotalCount 1); if ($firstLine -ne $null -and $firstLine -notmatch '^\s*<\?php') { Write-Host 'LEADING WHITESPACE FOUND: %%F' -ForegroundColor Yellow }"
)

echo ----------------------------------------
echo Whitespace check complete.
echo ----------------------------------------
echo.

echo All linting and safety checks complete.
echo ----------------------------------------
pause
