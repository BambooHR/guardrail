@echo off
echo Regenerating Guardrail index with class references...
echo.
wsl -d Ubuntu-24.04 php vendor/bin/guardrail.php -a config.json
echo.
echo Index regenerated! Restart your LSP server to use the new index.
echo Press any key to exit...
pause >nul
