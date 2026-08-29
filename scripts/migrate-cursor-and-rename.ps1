# Ship-To Rules: rename plugin folder AND preserve Cursor chat history.
# RUN ONLY WITH CURSOR FULLY CLOSED (no Cursor.exe in Task Manager).
#
# Usage (PowerShell — run from ANY folder, Cursor must be CLOSED):
#   powershell -ExecutionPolicy Bypass -File D:\www\wordpress\wp-content\plugins\wp-country-search\scripts\migrate-cursor-and-rename.ps1
#
# Do NOT cd into wp-country-search first; if you do, the script still cd's out before rename.
# Optional dry run (no changes):
#   .\scripts\migrate-cursor-and-rename.ps1 -DryRun

param(
    [switch]$DryRun
)

$ErrorActionPreference = 'Stop'

$OldPlugin = 'D:\www\wordpress\wp-content\plugins\wp-country-search'
$NewPlugin = 'D:\www\wordpress\wp-content\plugins\ship-to-rules'
$BirthtimeMs = '1787629380733'  # Same after rename on same volume; do not change.

$OldWorkspaceId = 'a44e7191913e8e544eb5a8426f562b6d'
$NewWorkspaceId = 'f346d19a798df39100ff5cd505cdc5f0'

$OldProjectSlug = 'd-www-wordpress-wp-content-plugins-wp-country-search'
$NewProjectSlug = 'd-www-wordpress-wp-content-plugins-ship-to-rules'

$OldUri = 'file:///d%3A/www/wordpress/wp-content/plugins/wp-country-search'
$NewUri = 'file:///d%3A/www/wordpress/wp-content/plugins/ship-to-rules'

$CursorRoaming = Join-Path $env:APPDATA 'Cursor\User'
$WorkspaceStorage = Join-Path $CursorRoaming 'workspaceStorage'
$GlobalStorage = Join-Path $CursorRoaming 'globalStorage'
$ProjectsRoot = Join-Path $env:USERPROFILE '.cursor\projects'

function Test-CursorClosed {
    $procs = Get-Process -Name 'Cursor' -ErrorAction SilentlyContinue
    if ($procs) {
        throw "Cursor sigue abierto ($($procs.Count) proceso(s)). Cerralo por completo antes de continuar."
    }
}

function Write-Step($msg) { Write-Host "`n==> $msg" -ForegroundColor Cyan }

Write-Step 'Verificando que Cursor este cerrado'
Test-CursorClosed

# Si el cwd actual esta dentro del plugin, salir antes de cualquier operacion.
$oldLeaf = Split-Path -Leaf $OldPlugin
if ((Get-Location).Path -like "*\$oldLeaf*" -or (Get-Location).Path -like "*\$oldLeaf") {
    Set-Location (Split-Path -Parent $OldPlugin)
    Write-Host "Shell movido a $(Get-Location) (evita bloqueo al renombrar)." -ForegroundColor Yellow
}

if (-not (Test-Path $OldPlugin)) {
    if (Test-Path $NewPlugin) {
        Write-Host "La carpeta ya fue renombrada a ship-to-rules. Continuando solo migracion de metadata..." -ForegroundColor Yellow
    } else {
        throw "No se encuentra la carpeta del plugin: $OldPlugin"
    }
}

$ts = Get-Date -Format 'yyyyMMdd-HHmmss'
$BackupRoot = Join-Path $env:USERPROFILE ".cursor\ship-to-rules-migration-backup-$ts"

Write-Step "Creando backup en $BackupRoot"
if (-not $DryRun) {
    New-Item -ItemType Directory -Path $BackupRoot -Force | Out-Null
    if (Test-Path (Join-Path $ProjectsRoot $OldProjectSlug)) {
        Copy-Item -Recurse (Join-Path $ProjectsRoot $OldProjectSlug) (Join-Path $BackupRoot $OldProjectSlug)
    }
    Copy-Item (Join-Path $GlobalStorage 'storage.json') (Join-Path $BackupRoot 'storage.json')
    Copy-Item (Join-Path $GlobalStorage 'state.vscdb') (Join-Path $BackupRoot 'state.vscdb')
    $oldWs = Join-Path $WorkspaceStorage $OldWorkspaceId
    if (Test-Path $oldWs) {
        Copy-Item -Recurse $oldWs (Join-Path $BackupRoot $OldWorkspaceId)
    }
}

Write-Step 'Renombrando carpeta del plugin'
if (-not $DryRun -and (Test-Path $OldPlugin)) {
    if (Test-Path $NewPlugin) { throw "Ya existe $NewPlugin" }
    # Windows bloquea el rename si el shell (o Cursor) tiene el cwd dentro de la carpeta.
    $pluginsDir = Split-Path -Parent $OldPlugin
    Push-Location $pluginsDir
    try {
        Rename-Item -LiteralPath (Split-Path -Leaf $OldPlugin) -NewName (Split-Path -Leaf $NewPlugin)
    } finally {
        Pop-Location
    }
}

Write-Step 'Renombrando metadata de Agent (.cursor/projects)'
$oldProj = Join-Path $ProjectsRoot $OldProjectSlug
$newProj = Join-Path $ProjectsRoot $NewProjectSlug
if (-not $DryRun) {
    if (Test-Path $oldProj) {
        if (Test-Path $newProj) { Remove-Item -Recurse -Force $newProj }
        Rename-Item $oldProj $newProj
    } elseif (-not (Test-Path $newProj)) {
        throw "No se encontro metadata de proyecto en $oldProj"
    }
}

Write-Step 'Migrando workspaceStorage'
$oldWsPath = Join-Path $WorkspaceStorage $OldWorkspaceId
$newWsPath = Join-Path $WorkspaceStorage $NewWorkspaceId
if (-not $DryRun) {
    if (Test-Path $oldWsPath) {
        if (Test-Path $newWsPath) { Remove-Item -Recurse -Force $newWsPath }
        Copy-Item -Recurse $oldWsPath $newWsPath
        @{
            folder = $NewUri
        } | ConvertTo-Json | Set-Content (Join-Path $newWsPath 'workspace.json') -Encoding UTF8
    }
}

Write-Step 'Actualizando storage.json'
$storagePath = Join-Path $GlobalStorage 'storage.json'
if (-not $DryRun -and (Test-Path $storagePath)) {
    $json = Get-Content $storagePath -Raw -Encoding UTF8
    $json = $json.Replace($OldUri, $NewUri)
    $json = $json.Replace('wp-country-search', 'ship-to-rules')
    $json = $json.Replace($OldWorkspaceId, $NewWorkspaceId)
    Set-Content $storagePath $json -Encoding UTF8 -NoNewline
}

Write-Step 'Actualizando state.vscdb (indice global de chats)'
$pyScript = Join-Path $PSScriptRoot 'patch-cursor-state.py'
# Guardar ruta absoluta antes del rename (PSScriptRoot deja de existir tras renombrar la carpeta).
$pyScriptResolved = (Resolve-Path -LiteralPath $pyScript).Path
if (-not (Test-Path $pyScriptResolved)) { throw "Falta $pyScriptResolved" }

$pyArgs = @(
    $pyScriptResolved,
    (Join-Path $GlobalStorage 'state.vscdb'),
    $OldPlugin,
    $NewPlugin,
    'wp-country-search',
    'ship-to-rules',
    $OldWorkspaceId,
    $NewWorkspaceId,
    $OldUri,
    $NewUri
)
if ($DryRun) { $pyArgs += '--dry-run' }

& python @pyArgs

Write-Step 'Listo'
Write-Host @"

MIGRACION COMPLETADA.

1. Abri Cursor
2. File -> Open Folder -> $NewPlugin
3. Verifica el sidebar de Agent: deben aparecer TODOS los chats anteriores
   (incluido este: 5a44d61b-1e58-4f17-9dcc-dff7393b0a51)
4. En WordPress: desactiva el plugin viejo si aparece, activa Ship-To Rules

Backup guardado en: $BackupRoot

"@ -ForegroundColor Green
