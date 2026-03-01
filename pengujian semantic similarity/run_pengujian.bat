@echo off
setlocal

cd /d "%~dp0\.."

echo [INFO] Menjalankan pengujian semantic similarity...
python "pengujian semantic similarity\run_all.py"

if %ERRORLEVEL% neq 0 (
  echo [ERROR] Pengujian gagal. Cek log di terminal.
  exit /b %ERRORLEVEL%
)

echo [OK] Pengujian selesai.
endlocal
