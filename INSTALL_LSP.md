# Guardrail LSP Server Installation Guide

This guide will walk you through installing and configuring the Guardrail Language Server Protocol (LSP) server for use with your IDE.

## Prerequisites

- **PHP 7.4 or higher** with CLI support
- **Composer** for dependency management
- **Guardrail** installed and configured
- An IDE or editor with LSP support (VSCode, Neovim, Sublime Text, etc.)

## Installation Steps

### 1. Install Dependencies

Navigate to your Guardrail installation directory and install the required dependencies:

```bash
cd /path/to/guardrail
composer install
```

This will install:
- `felixfbecker/language-server-protocol` - LSP protocol implementation
- `nikic/php-parser` - PHP parser (already included)
- Other Guardrail dependencies

### 2. Generate the Symbol Table

The LSP server requires a symbol table index. Generate it by running Guardrail's indexing phase:

```bash
php vendor/bin/guardrail.php -i config.json
```

This creates a `symbol_table.json` file containing:
- All class, interface, and trait definitions with positions
- All function definitions with positions
- All constant definitions with positions
- Serialized type signatures

**Note**: You'll need to regenerate the symbol table whenever your codebase changes significantly.

### 3. Verify Installation

Test the LSP components:

```bash
php src/Lsp/test-lsp.php
```

You should see output like:
```
Testing Guardrail LSP Components
=================================

Test 1: PositionMapper
----------------------
✓ PASS

Test 2: URI Conversion
----------------------
✓ PASS

...
```

### 4. Test the LSP Server

Start the LSP server manually to verify it works:

```bash
php src/bin/guardrail-lsp.php symbol_table.json 2> lsp-debug.log
```

The server will start and wait for LSP messages on stdin. You should see log output in `lsp-debug.log`:

```
[LSP] Guardrail LSP Server starting...
[LSP] Symbol table loaded from: symbol_table.json
```

Press `Ctrl+C` to stop the server.

## IDE Configuration

### Visual Studio Code

#### Option 1: Using a Generic LSP Extension

1. Install an LSP client extension like [vscode-languageclient](https://marketplace.visualstudio.com/items?itemName=vscode.vscode-languageclient)

2. Create `.vscode/settings.json` in your project:

```json
{
  "php.suggest.basic": false,
  "php.validate.enable": false,
  "languageServerExample.trace.server": "verbose"
}
```

3. Create a custom extension or use a generic LSP launcher with this configuration:

```json
{
  "command": "php",
  "args": [
    "/absolute/path/to/guardrail/src/bin/guardrail-lsp.php",
    "/absolute/path/to/your/project/symbol_table.json"
  ],
  "filetypes": ["php"],
  "rootPatterns": ["composer.json", ".git"]
}
```

#### Option 2: Create a Simple VSCode Extension

Create a minimal extension in `.vscode/extensions/guardrail-lsp/`:

**package.json**:
```json
{
  "name": "guardrail-lsp",
  "version": "0.1.0",
  "engines": {
    "vscode": "^1.60.0"
  },
  "activationEvents": ["onLanguage:php"],
  "main": "./extension.js",
  "contributes": {
    "configuration": {
      "type": "object",
      "title": "Guardrail LSP",
      "properties": {
        "guardrailLsp.symbolTablePath": {
          "type": "string",
          "default": "symbol_table.json",
          "description": "Path to symbol_table.json"
        }
      }
    }
  }
}
```

**extension.js**:
```javascript
const vscode = require('vscode');
const { LanguageClient } = require('vscode-languageclient/node');
const path = require('path');

let client;

function activate(context) {
    const config = vscode.workspace.getConfiguration('guardrailLsp');
    const symbolTablePath = config.get('symbolTablePath');
    
    const serverOptions = {
        command: 'php',
        args: [
            path.join(__dirname, '../../../src/bin/guardrail-lsp.php'),
            symbolTablePath
        ]
    };

    const clientOptions = {
        documentSelector: [{ scheme: 'file', language: 'php' }]
    };

    client = new LanguageClient(
        'guardrailLsp',
        'Guardrail LSP',
        serverOptions,
        clientOptions
    );

    client.start();
}

function deactivate() {
    if (client) {
        return client.stop();
    }
}

module.exports = { activate, deactivate };
```

### Neovim (with nvim-lspconfig)

Add to your Neovim configuration:

```lua
local lspconfig = require('lspconfig')
local configs = require('lspconfig.configs')

-- Define Guardrail LSP
if not configs.guardrail then
  configs.guardrail = {
    default_config = {
      cmd = {
        'php',
        '/path/to/guardrail/src/bin/guardrail-lsp.php',
        '/path/to/symbol_table.json'
      },
      filetypes = {'php'},
      root_dir = lspconfig.util.root_pattern('composer.json', '.git'),
      settings = {},
    },
  }
end

-- Enable Guardrail LSP for PHP files
lspconfig.guardrail.setup{}
```

### Sublime Text (with LSP package)

1. Install the [LSP package](https://packagecontrol.io/packages/LSP)

2. Add to your LSP settings (`Preferences > Package Settings > LSP > Settings`):

```json
{
  "clients": {
    "guardrail": {
      "enabled": true,
      "command": [
        "php",
        "/path/to/guardrail/src/bin/guardrail-lsp.php",
        "/path/to/symbol_table.json"
      ],
      "selector": "source.php",
      "schemes": ["file"]
    }
  }
}
```

### Emacs (with lsp-mode)

Add to your Emacs configuration:

```elisp
(require 'lsp-mode)

(lsp-register-client
 (make-lsp-client
  :new-connection (lsp-stdio-connection
                   '("php" "/path/to/guardrail/src/bin/guardrail-lsp.php" "/path/to/symbol_table.json"))
  :major-modes '(php-mode)
  :server-id 'guardrail))

(add-hook 'php-mode-hook #'lsp)
```

## Windows Subsystem for Linux (WSL) Setup

If you're using WSL with a Windows IDE:

### From Windows VSCode to WSL

```json
{
  "command": "wsl",
  "args": [
    "php",
    "/mnt/c/path/to/guardrail/src/bin/guardrail-lsp.php",
    "/mnt/c/path/to/symbol_table.json"
  ],
  "filetypes": ["php"]
}
```

### Path Conversion

The LSP server handles Windows/Unix path conversion automatically. You can use either:
- Windows paths: `C:\Users\jon\project\file.php`
- WSL paths: `/mnt/c/Users/jon/project/file.php`

## Configuration

### Symbol Table Location

The LSP server requires the path to `symbol_table.json` as its first argument. You can:

1. **Use absolute path**: `/full/path/to/symbol_table.json`
2. **Use relative path**: `./symbol_table.json` (relative to where you start the server)
3. **Use project-specific path**: Each project can have its own symbol table

### Regenerating the Symbol Table

Regenerate the symbol table when:
- You add new classes, functions, or files
- You modify class signatures or function parameters
- You update dependencies

```bash
php vendor/bin/guardrail.php -i config.json
```

**Tip**: Add this to your build process or git hooks to keep the index up to date.

### Performance Tuning

The LSP server includes a symbol cache (default: 1000 symbols). To adjust:

Edit `src/Lsp/SymbolResolver.php`:
```php
private int $cacheSize = 2000; // Increase for larger codebases
```

## Troubleshooting

### Server Won't Start

**Problem**: `Error loading symbol table`

**Solution**: 
- Verify `symbol_table.json` exists
- Check file permissions
- Ensure the path is correct (absolute paths recommended)

### Symbol Not Found

**Problem**: Go to definition doesn't work for a symbol

**Solution**:
- Regenerate the symbol table: `php vendor/bin/guardrail.php -i config.json`
- Verify the file is in the `index` section of `config.json`
- Check that the symbol is a class/function/interface (not a method or property)

### Position Incorrect

**Problem**: Jump to definition goes to wrong line

**Solution**:
- Ensure you've rebuilt the symbol table after recent code changes
- The position data was recently added - older symbol tables won't have it

### High Memory Usage

**Problem**: LSP server uses too much memory

**Solution**:
- Reduce cache size in `SymbolResolver.php`
- Index only necessary directories in `config.json`
- Exclude test files and vendor code if not needed

### Slow Response

**Problem**: LSP operations are slow

**Solution**:
- Check symbol table size (should be < 50MB for most projects)
- Verify you're not indexing unnecessary files
- Use SSD storage for better I/O performance

## Debugging

Enable verbose logging:

```bash
php src/bin/guardrail-lsp.php symbol_table.json 2> lsp-debug.log
```

Then tail the log:
```bash
tail -f lsp-debug.log
```

You'll see:
```
[LSP] Guardrail LSP Server starting...
[LSP] Symbol table loaded from: symbol_table.json
[LSP] Received: initialize (id: 1)
[LSP] Sent response for: initialize
[LSP] Received: textDocument/definition (id: 2)
[LSP] Sent response for: textDocument/definition
```

## Features Available

Once installed, you'll have:

- ✅ **Go to Definition** - Click on any class/function/interface to jump to its definition
- ✅ **Hover Information** - Hover over symbols to see type signatures and documentation
- ✅ **Workspace Symbols** - Search for symbols across your entire codebase (Ctrl+T in VSCode)
- ✅ **Document Outline** - View structure of current file in the outline panel

## Limitations

- **Read-only**: No live updates as you type (requires symbol table regeneration)
- **No completion**: Auto-completion is not yet implemented
- **No diagnostics**: Error reporting is handled by Guardrail's analysis phase separately
- **Definitions only**: Finding all references requires usage tracking (future feature)

## Updating

To update the LSP server:

```bash
cd /path/to/guardrail
git pull
composer update
```

Then restart your IDE's LSP client.

## Getting Help

- **Documentation**: See `src/Lsp/README.md` for technical details
- **Issues**: Report bugs to the Guardrail repository
- **Logs**: Always check `lsp-debug.log` for error messages

## Next Steps

After installation:

1. Try "Go to Definition" on a class name
2. Hover over a function to see its signature
3. Use workspace symbol search (Ctrl+T) to find symbols
4. Set up automatic symbol table regeneration in your workflow

Enjoy enhanced PHP development with Guardrail LSP!
