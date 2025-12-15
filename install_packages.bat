@echo off
echo ========================================
echo SmartDoc Package Installation Script
echo ========================================
echo.

echo Checking Python version...
python --version
echo.

echo Attempting to install packages...
echo.

REM Try installing with pre-built wheels first
echo [1/2] Installing packages (pre-built wheels)...
pip install --upgrade pip
pip install flask flask-cors pandas numpy joblib
pip install scikit-learn --only-binary :all:

if %ERRORLEVEL% EQU 0 (
    echo.
    echo ========================================
    echo SUCCESS! All packages installed.
    echo ========================================
    echo.
    python -c "import sklearn, flask, pandas, numpy; print('Verification: All packages working!')"
) else (
    echo.
    echo ========================================
    echo ERROR: Installation failed.
    echo ========================================
    echo.
    echo Please try one of these solutions:
    echo 1. Use Python 3.11 or 3.12 (recommended)
    echo 2. Install Microsoft C++ Build Tools
    echo 3. See INSTALL_WINDOWS.md for details
    echo.
    pause
)

