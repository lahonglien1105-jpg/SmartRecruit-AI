@echo off
setlocal
cd /d "%~dp0ai_service"
if not exist ".venv\Scripts\python.exe" (
  echo Chua co moi truong Python. Hay chay install_ai.bat truoc.
  pause
  exit /b 1
)
echo Kiem tra tai http://127.0.0.1:8000/health
".venv\Scripts\python.exe" -m uvicorn main:app --host 127.0.0.1 --port 8000 --reload
if errorlevel 1 pause
