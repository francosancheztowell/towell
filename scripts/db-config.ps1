<#
.SYNOPSIS
    Administra el bloqueo (skip-worktree) de config/database.php entre entornos.

.DESCRIPTION
    config/database.php difiere por servidor (local, pruebas, produccion) pero
    vive en el mismo repositorio. Este script automatiza el ciclo:
      unlock -> editar a mano -> save (commit + push + re-bloquear)
      sync   -> traer cambios de estructura sin perder tus valores locales

.EXAMPLE
    .\scripts\db-config.ps1 status
    .\scripts\db-config.ps1 unlock
    .\scripts\db-config.ps1 save
    .\scripts\db-config.ps1 sync
#>

param(
    [Parameter(Mandatory = $true, Position = 0)]
    [ValidateSet('status', 'unlock', 'lock', 'save', 'sync')]
    [string]$Action
)

$ErrorActionPreference = 'Stop'
$File = 'config/database.php'

function Test-SkipWorktree {
    $flag = git ls-files -v -- $File
    return $flag.StartsWith('S ')
}

function Show-Status {
    if (Test-SkipWorktree) {
        Write-Host "$File esta BLOQUEADO (skip-worktree activo). git ignora tus cambios locales." -ForegroundColor Yellow
    } else {
        Write-Host "$File esta DESBLOQUEADO. git trackea cualquier cambio normalmente." -ForegroundColor Green
    }
}

switch ($Action) {
    'status' {
        Show-Status
    }

    'unlock' {
        if (-not (Test-SkipWorktree)) {
            Write-Host "Ya estaba desbloqueado." -ForegroundColor Yellow
        } else {
            git update-index --no-skip-worktree $File
            Write-Host "Desbloqueado. Edita $File y despues corre: .\scripts\db-config.ps1 save" -ForegroundColor Green
        }
    }

    'lock' {
        git update-index --skip-worktree $File
        Write-Host "Bloqueado. git va a ignorar cambios locales en $File de ahora en mas." -ForegroundColor Green
    }

    'save' {
        if (Test-SkipWorktree) {
            Write-Host "El archivo esta bloqueado. Corre '.\scripts\db-config.ps1 unlock' primero." -ForegroundColor Red
            exit 1
        }

        $diff = git status --porcelain -- $File
        if (-not $diff) {
            Write-Host "No hay cambios en $File para subir." -ForegroundColor Yellow
            exit 0
        }

        git diff -- $File | Out-Host
        Write-Host ""
        $confirm = Read-Host "Confirmas subir estos cambios de $File? (s/n)"
        if ($confirm -ne 's') {
            Write-Host "Cancelado." -ForegroundColor Yellow
            exit 0
        }

        $msg = Read-Host "Mensaje de commit"
        git add $File
        git commit -m $msg
        git push
        git update-index --skip-worktree $File
        Write-Host "Subido y re-bloqueado." -ForegroundColor Green
    }

    'sync' {
        # Trae cambios de estructura desde el repo preservando los valores
        # locales de este servidor (host/usuario/password propios).
        git update-index --no-skip-worktree $File

        $stashOutput = git stash push -m "db-config-sync" -- $File
        $didStash = -not ($stashOutput -match 'No local changes to save')

        git pull

        if ($didStash) {
            Write-Host "Reaplicando tus valores locales sobre la estructura nueva..." -ForegroundColor Cyan
            git stash pop
            if (Select-String -Path $File -Pattern '^<{7}' -Quiet) {
                Write-Host "Hay conflicto en $File (busca los <<<<<<< / ======= / >>>>>>>)." -ForegroundColor Red
                Write-Host "Resolvelo a mano y despues corre: .\scripts\db-config.ps1 lock" -ForegroundColor Red
                exit 1
            }
        }

        git update-index --skip-worktree $File
        Write-Host "Sincronizado y re-bloqueado." -ForegroundColor Green
    }
}
