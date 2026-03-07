# Guardrail LSP Extension for VSCode

A minimal VSCode extension that provides Language Server Protocol support for Guardrail PHP static analysis.

## Features

- **Go to Definition** - Jump to class, function, interface, or trait definitions
- **Hover Information** - View type signatures and documentation
- **Workspace Symbols** - Search symbols across your entire codebase
- **Document Outline** - Browse the structure of the current file

## Installation

### Prerequisites

1. **Guardrail** must be installed in your project
2. **Symbol table** must be generated: `php vendor/bin/guardrail.php -i config.json`

### Setup

1. Copy this extension folder to your workspace's `.vscode/extensions/` directory
2. Install dependencies:
   ```bash
   cd .vscode/extensions/guardrail-lsp
   npm install
   ```
3. Reload VSCode
4. The extension will activate automatically when you open a PHP file

## Configuration

Open VSCode settings and configure:

- **guardrailLsp.symbolTablePath** - Path to `symbol_table.json` (default: `symbol_table.json`)
- **guardrailLsp.guardrailPath** - Path to Guardrail installation (leave empty to auto-detect)
- **guardrailLsp.trace.server** - Enable server communication logging (off/messages/verbose)

### Example Settings

**For WSL (Recommended on Windows):**
```json
{
  "guardrailLsp.symbolTablePath": "symbol_table.json",
  "guardrailLsp.useWSL": true,
  "guardrailLsp.trace.server": "off"
}
```

**For Windows PHP:**
```json
{
  "guardrailLsp.symbolTablePath": "symbol_table.json",
  "guardrailLsp.phpPath": "C:/php/php.exe",
  "guardrailLsp.useWSL": false,
  "guardrailLsp.trace.server": "off"
}
```

## Usage

1. Generate the symbol table:
   ```bash
   php vendor/bin/guardrail.php -i config.json
   ```

2. Open a PHP file in VSCode

3. Use LSP features:
   - **F12** or **Ctrl+Click** - Go to definition
   - **Hover** - View type information
   - **Ctrl+T** - Search workspace symbols
   - **Ctrl+Shift+O** - View document outline

## Commands

- **Guardrail LSP: Restart** - Restart the language server

## Troubleshooting

### Extension not activating

- Check the Output panel (View → Output) and select "Guardrail LSP" from the dropdown
- Verify PHP is in your PATH: `php --version`

### Symbol table not found

- Ensure `symbol_table.json` exists in your workspace root
- Check the path in settings matches your actual file location
- Regenerate: `php vendor/bin/guardrail.php -i config.json`

### Server not starting

- Check that `guardrail-lsp.php` exists at the configured path
- Verify file permissions
- Check the Output panel for error messages

## Development

To modify this extension:

1. Make changes to `extension.js`
2. Reload VSCode (Ctrl+R in Extension Development Host)
3. Test with a PHP project

## License

Same as Guardrail (Apache 2.0)
