@echo off
setlocal
cd /d "%~dp0ai_service"
python -c "import sys; print('Python:',sys.version)" >nul 2>&1
if errorlevel 1 (
  echo KHONG TIM THAY PYTHON. Hay cai Python 3.11/3.12 va them vao PATH.
  pause
  exit /b 1
)
if not exist ".venv\Scripts\python.exe" (
  python -m venv .venv
  if errorlevel 1 (
    echo Khong tao duoc .venv
    pause
    exit /b 1
  )
)
".venv\Scripts\python.exe" -m pip install --upgrade pip
".venv\Scripts\python.exe" -m pip install -r requirements.txt
if errorlevel 1 (
  echo Cai thu vien that bai. Kiem tra Internet va phien ban Python.
  pause
  exit /b 1
)
echo DA CAI XONG. Ban co the chay start_ai.bat tai thu muc SmartRecruit.
pause
