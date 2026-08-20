@echo off
chcp 65001 >nul
cd /d "%~dp0"

echo.
echo ==========================================
echo   本地 PHP 环境一键安装
echo ==========================================
echo.

set "PHP_DIR=%~dp0php"
set "PHP_EXE=%PHP_DIR%\php.exe"
set "PHP_ZIP=%~dp0php-portable.zip"
set "PHP_URL=https://windows.php.net/downloads/releases/php-8.3.32-nts-Win32-vs16-x64.zip"

if exist "%PHP_EXE%" (
    echo [OK] 本地 PHP 已存在，跳过下载
    goto :configure
)

echo [1/3] 正在下载 PHP 8.3（约 32MB，首次需要几分钟）...
echo.

powershell -NoProfile -ExecutionPolicy Bypass -Command ^
    "$ProgressPreference = 'SilentlyContinue';" ^
    "try {" ^
    "  Invoke-WebRequest -Uri '%PHP_URL%' -OutFile '%PHP_ZIP%' -UseBasicParsing;" ^
    "  Write-Host '[OK] 下载完成';" ^
    "} catch {" ^
    "  Write-Host '[X] 下载失败:' $_.Exception.Message;" ^
    "  exit 1;" ^
    "}"

if errorlevel 1 (
    echo.
    echo 下载失败，请手动操作：
    echo 1. 打开 %PHP_URL%
    echo 2. 下载 zip 并解压到 %PHP_DIR%
    echo 3. 重新运行本脚本
    pause
    exit /b 1
)

echo.
echo [2/3] 正在解压...

if exist "%PHP_DIR%" rmdir /s /q "%PHP_DIR%"
mkdir "%PHP_DIR%"

powershell -NoProfile -ExecutionPolicy Bypass -Command ^
    "Expand-Archive -Path '%PHP_ZIP%' -DestinationPath '%PHP_DIR%' -Force"

del "%PHP_ZIP%" 2>nul

if not exist "%PHP_EXE%" (
    echo [X] 解压失败，未找到 php.exe
    pause
    exit /b 1
)

:configure
echo.
echo [3/3] 正在配置 php.ini ...

if not exist "%PHP_DIR%\php.ini" (
    copy /y "%PHP_DIR%\php.ini-development" "%PHP_DIR%\php.ini" >nul
)

:: 启用 curl 和 openssl（访问 Hypixel HTTPS API 必需）
powershell -NoProfile -ExecutionPolicy Bypass -Command ^
    "$ini = Get-Content '%PHP_DIR%\php.ini';" ^
    "$ini = $ini -replace ';extension_dir = \"ext\"', 'extension_dir = \"ext\"';" ^
    "$ini = $ini -replace ';extension=curl', 'extension=curl';" ^
    "$ini = $ini -replace ';extension=openssl', 'extension=openssl';" ^
    "$ini = $ini -replace ';extension=mbstring', 'extension=mbstring';" ^
    "$ini = $ini -replace ';extension=fileinfo', 'extension=fileinfo';" ^
    "    Set-Content '%PHP_DIR%\php.ini' $ini -Encoding UTF8"

:: 配置 SSL 证书（访问 Hypixel HTTPS API 必需）
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0setup-ssl.ps1"

echo.
echo ==========================================
echo   安装完成！
echo ==========================================
echo.
"%PHP_EXE%" -v
echo.
echo 下一步：双击运行 start.bat 启动网站
echo 然后访问 http://localhost:8080
echo.
pause
