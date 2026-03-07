# Guardrail LSP Setup for Windsurf on Windows

This guide shows you how to run the Guardrail LSP server in WSL (Windows Subsystem for Linux) from Windsurf.

## Recommended: Use WSL Mode

The easiest way to use Guardrail LSP on Windows is to run the server in WSL. This avoids needing to install PHP on Windows.

### Step 1: Enable WSL Mode

1. Open Windsurf settings (Ctrl+,)
2. Search for "Guardrail LSP"
3. Enable **Guardrail Lsp: Use WSL** (check the box)

That's it! The extension will automatically:
- Convert Windows paths to WSL paths (`C:\Users\...` → `/mnt/c/Users/...`)
- Run the server using `wsl php` command
- Handle all communication between Windows and WSL

### Step 2: Verify PHP in WSL

Make sure PHP is installed in your WSL distribution:

```bash
wsl php --version
```

If PHP is not installed in WSL:

```bash
wsl sudo apt update
wsl sudo apt install php-cli
```

### Step 3: Generate Symbol Table in WSL

Generate the symbol table from WSL:

```bash
wsl php vendor/bin/guardrail.php -i config.json
```

### Step 4: Reload Windsurf

Press Ctrl+Shift+P → "Developer: Reload Window"

## Alternative: Use Windows PHP

If you prefer to install PHP on Windows instead of using WSL:

### Step 1: Verify PHP Installation

Make sure PHP is installed on Windows (not just in WSL):

```powershell
php --version
```

If this doesn't work, you need to install PHP for Windows:
- Download from: https://windows.php.net/download/
- Or use Chocolatey: `choco install php`
- Or use Scoop: `scoop install php`

### Step 2: Configure PHP Path (if needed)

If PHP is installed but not in your PATH, or you want to use a specific PHP version:

1. Open Windsurf settings (Ctrl+,)
2. Search for "Guardrail LSP"
3. Set **Guardrail Lsp: Php Path** to your PHP executable, for example:
   - `C:\php\php.exe`
   - `C:\tools\php82\php.exe`
   - `C:\Program Files\PHP\v8.2\php.exe`

### Step 3: Set Symbol Table Path

1. In Windsurf settings, set **Guardrail Lsp: Symbol Table Path**:
   - Use forward slashes: `symbol_table.json`
   - Or absolute path: `C:/Users/jon/guardrail/symbol_table.json`

### Step 4: Reload Windsurf

After changing settings:
1. Press Ctrl+Shift+P
2. Run "Developer: Reload Window"

## Troubleshooting

### Error: "Cannot start WSL connection"

**Cause**: Windsurf is trying to use WSL for the PHP command.

**Solution**: The extension now uses `php.exe` explicitly on Windows. If this doesn't work:

1. Set a full path to PHP in settings:
   ```json
   {
     "guardrailLsp.phpPath": "C:/php/php.exe"
   }
   ```

2. Make sure you're using Windows paths (not WSL paths like `/mnt/c/...`)

### Error: "php.exe is not recognized"

**Cause**: PHP is not installed on Windows or not in PATH.

**Solution**: 

1. Install PHP for Windows (see Step 1 above)
2. Or set the full path in settings:
   ```json
   {
     "guardrailLsp.phpPath": "C:/path/to/php.exe"
   }
   ```

### Error: "Symbol table not found"

**Cause**: The symbol table hasn't been generated or the path is wrong.

**Solution**:

1. Generate the symbol table:
   ```powershell
   php vendor/bin/guardrail.php -i config.json
   ```

2. Verify the file exists:
   ```powershell
   dir symbol_table.json
   ```

3. Set the correct path in settings (use forward slashes):
   ```json
   {
     "guardrailLsp.symbolTablePath": "symbol_table.json"
   }
   ```

### Extension not activating

**Check the Output panel**:
1. View → Output (Ctrl+Shift+U)
2. Select "Guardrail LSP" from the dropdown
3. Look for error messages

**Common issues**:
- PHP not found → Set `guardrailLsp.phpPath`
- Server script not found → Set `guardrailLsp.guardrailPath`
- Symbol table missing → Run indexing command

## Recommended Settings for Windsurf on Windows

Add to your `.vscode/settings.json`:

```json
{
  "guardrailLsp.phpPath": "C:/php/php.exe",
  "guardrailLsp.symbolTablePath": "symbol_table.json",
  "guardrailLsp.guardrailPath": "C:/Users/jon/guardrail",
  "guardrailLsp.trace.server": "verbose"
}
```

Adjust paths to match your installation.

## Testing the Setup

1. Open a PHP file in your project
2. Hover over a class name - you should see type information
3. Ctrl+Click on a class name - it should jump to the definition
4. Press Ctrl+T and search for a symbol

If these work, the LSP server is running correctly!

## Still Having Issues?

1. Check the Output panel for detailed error messages
2. Try running the LSP server manually to test:
   ```powershell
   php C:/Users/jon/guardrail/src/bin/guardrail-lsp.php symbol_table.json
   ```
3. If it starts without errors, the issue is in the extension configuration

## Alternative: Use WSL Intentionally

If you prefer to use WSL PHP:

1. Set the PHP path to use WSL:
   ```json
   {
     "guardrailLsp.phpPath": "wsl php"
   }
   ```

2. Use WSL paths for files:
   ```json
   {
     "guardrailLsp.symbolTablePath": "/mnt/c/Users/jon/guardrail/symbol_table.json",
     "guardrailLsp.guardrailPath": "/mnt/c/Users/jon/guardrail"
   }
   ```

Note: This is not recommended as it adds complexity. Using Windows PHP directly is simpler.
