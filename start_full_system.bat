@echo off
title S-SPARC Full System
start "Backend" cmd /k call start_backend.bat
timeout /t 2 /nobreak >nul
start "Frontend" cmd /k call start_frontend.bat
