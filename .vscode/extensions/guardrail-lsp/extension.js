const vscode = require('vscode');
const { LanguageClient, TransportKind } = require('vscode-languageclient/node');
const path = require('path');
const fs = require('fs');

let client;

function activate(context) {
    console.log('Guardrail LSP extension is now active');

    const config = vscode.workspace.getConfiguration('guardrailLsp');
    const symbolTablePath = config.get('symbolTablePath');
    const guardrailPath = config.get('guardrailPath');
    const phpPath = config.get('phpPath');
    const useWSL = config.get('useWSL');
    const wslDistribution = config.get('wslDistribution');
    
    // Helper function to convert Windows path to WSL path
    function toWSLPath(windowsPath) {
        // Convert C:\Users\... to /mnt/c/Users/...
        const normalized = windowsPath.replace(/\\/g, '/');
        const match = normalized.match(/^([A-Za-z]):(.*)/);
        if (match) {
            return `/mnt/${match[1].toLowerCase()}${match[2]}`;
        }
        return normalized;
    }
    
    // Determine paths
    let lspServerPath;
    let resolvedSymbolTablePath;
    let resolvedUsageFilePath;

    if (guardrailPath) {
        // User specified Guardrail path
        lspServerPath = path.join(guardrailPath, 'src', 'bin', 'guardrail-lsp.php');
    } else {
        // Try to find Guardrail in vendor
        const workspaceFolder = vscode.workspace.workspaceFolders?.[0];
        if (workspaceFolder) {
            const vendorPath = path.join(workspaceFolder.uri.fsPath, 'vendor', 'bamboohr', 'guardrail', 'src', 'bin', 'guardrail-lsp.php');
            if (fs.existsSync(vendorPath)) {
                lspServerPath = vendorPath;
            } else {
                // Try relative to extension
                lspServerPath = path.join(__dirname, '..', '..', '..', 'src', 'bin', 'guardrail-lsp.php');
            }
        } else {
            vscode.window.showErrorMessage('Guardrail LSP: No workspace folder found');
            return;
        }
    }

    // Resolve symbol table path
    if (path.isAbsolute(symbolTablePath)) {
        resolvedSymbolTablePath = symbolTablePath;
    } else {
        const workspaceFolder = vscode.workspace.workspaceFolders?.[0];
        if (workspaceFolder) {
            resolvedSymbolTablePath = path.join(workspaceFolder.uri.fsPath, symbolTablePath);
        } else {
            vscode.window.showErrorMessage('Guardrail LSP: No workspace folder found');
            return;
        }
    }

    // Try to find method_usage.json in the same directory as symbol table
    const symbolTableDir = path.dirname(resolvedSymbolTablePath);
    resolvedUsageFilePath = path.join(symbolTableDir, 'method_usage.json');
    
    // Check if usage file exists (skip in WSL mode)
    if (!useWSL && !fs.existsSync(resolvedUsageFilePath)) {
        console.log(`Guardrail LSP: method_usage.json not found at ${resolvedUsageFilePath}`);
        resolvedUsageFilePath = null;
    }

    // Verify files exist (skip validation in WSL mode since Windows can't check WSL paths)
    if (!useWSL) {
        if (!fs.existsSync(lspServerPath)) {
            vscode.window.showErrorMessage(`Guardrail LSP: Server not found at ${lspServerPath}`);
            return;
        }

        if (!fs.existsSync(resolvedSymbolTablePath)) {
            vscode.window.showWarningMessage(
                `Guardrail LSP: Symbol table not found at ${resolvedSymbolTablePath}. ` +
                `Run 'php vendor/bin/guardrail.php -i config.json' to generate it.`
            );
            return;
        }
    } else {
        console.log(`Guardrail LSP: Skipping file validation in WSL mode`);
    }

    console.log(`Guardrail LSP: Using server at ${lspServerPath}`);
    console.log(`Guardrail LSP: Using symbol table at ${resolvedSymbolTablePath}`);

    // Configure for WSL or native execution
    let phpCommand;
    let serverArgs;
    let serverOptions;

    if (useWSL) {
        // WSL mode - convert paths and use wsl command
        const wslServerPath = toWSLPath(lspServerPath);
        const wslSymbolTablePath = toWSLPath(resolvedSymbolTablePath);
        const wslUsageFilePath = resolvedUsageFilePath ? toWSLPath(resolvedUsageFilePath) : null;
        
        console.log(`Guardrail LSP: WSL mode enabled`);
        if (wslDistribution) {
            console.log(`Guardrail LSP: Using WSL distribution: ${wslDistribution}`);
        }
        console.log(`Guardrail LSP: WSL server path: ${wslServerPath}`);
        console.log(`Guardrail LSP: WSL symbol table path: ${wslSymbolTablePath}`);
        if (wslUsageFilePath) {
            console.log(`Guardrail LSP: WSL usage file path: ${wslUsageFilePath}`);
        }

        // Build WSL command arguments
        const wslArgs = [];
        if (wslDistribution) {
            wslArgs.push('-d', wslDistribution);
        }
        wslArgs.push('php', wslServerPath, wslSymbolTablePath);
        if (wslUsageFilePath) {
            wslArgs.push(wslUsageFilePath);
        }

        serverOptions = {
            command: 'wsl',
            args: wslArgs,
            options: {
                stdio: 'pipe'
            }
        };
    } else {
        // Native Windows mode
        const normalizedServerPath = lspServerPath.replace(/\\/g, '/');
        const normalizedSymbolTablePath = resolvedSymbolTablePath.replace(/\\/g, '/');
        const normalizedUsageFilePath = resolvedUsageFilePath ? resolvedUsageFilePath.replace(/\\/g, '/') : null;

        // Detect PHP command
        if (phpPath) {
            phpCommand = phpPath;
        } else if (process.platform === 'win32') {
            phpCommand = 'php.exe';
        } else {
            phpCommand = 'php';
        }

        const serverArgs = [normalizedServerPath, normalizedSymbolTablePath];
        if (normalizedUsageFilePath) {
            serverArgs.push(normalizedUsageFilePath);
        }

        serverOptions = {
            command: phpCommand,
            args: serverArgs,
            options: {
                stdio: 'pipe',
                shell: process.platform === 'win32' ? true : false
            }
        };
    }

    // Client options
    const clientOptions = {
        documentSelector: [
            { scheme: 'file', language: 'php' }
        ],
        synchronize: {
            fileEvents: vscode.workspace.createFileSystemWatcher('**/*.php')
        }
    };

    // Create the language client
    client = new LanguageClient(
        'guardrailLsp',
        'Guardrail LSP',
        serverOptions,
        clientOptions
    );

    // Start the client (this will also launch the server)
    client.start().then(() => {
        console.log('Guardrail LSP: Client started successfully');
        vscode.window.showInformationMessage('Guardrail LSP: Server started');
    }).catch(error => {
        console.error('Guardrail LSP: Failed to start client', error);
        vscode.window.showErrorMessage(`Guardrail LSP: Failed to start - ${error.message}`);
    });

    // Register commands
    context.subscriptions.push(
        vscode.commands.registerCommand('guardrailLsp.restart', () => {
            if (client) {
                client.stop().then(() => {
                    activate(context);
                });
            }
        })
    );
}

function deactivate() {
    if (!client) {
        return undefined;
    }
    return client.stop();
}

module.exports = {
    activate,
    deactivate
};
