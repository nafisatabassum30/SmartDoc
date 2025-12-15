# Windows Installation Guide - Fixing C++ Build Tools Error

## Problem
You're seeing: `Microsoft Visual C++ 14.0 or greater is required`

This happens because `scikit-learn` needs to compile C extensions on Windows.

## Solution Options (Choose One)

### Option 1: Install Pre-built Wheels (Easiest - Recommended)

Use Python 3.11 or 3.12 instead of 3.13 (better compatibility):

1. **Download Python 3.11 or 3.12** from [python.org](https://www.python.org/downloads/)
2. **Install it** (check "Add Python to PATH")
3. **In VS Code**, select the new Python version:
   - Press `Ctrl+Shift+P`
   - Type "Python: Select Interpreter"
   - Choose Python 3.11 or 3.12
4. **Install packages**:
   ```bash
   pip install -r requirements.txt
   ```

### Option 2: Install Microsoft C++ Build Tools

If you want to keep Python 3.13:

1. **Download Microsoft C++ Build Tools**:
   - Visit: https://visualstudio.microsoft.com/visual-cpp-build-tools/
   - Download "Build Tools for Visual Studio"
   - Run the installer

2. **During installation**:
   - Select "C++ build tools" workload
   - Check "MSVC v143 - VS 2022 C++ x64/x86 build tools"
   - Check "Windows 10/11 SDK"
   - Click Install

3. **Restart VS Code** and try again:
   ```bash
   pip install -r requirements.txt
   ```

### Option 3: Use Conda (Alternative)

If you have Anaconda/Miniconda:

```bash
conda create -n smartdoc python=3.11
conda activate smartdoc
conda install scikit-learn pandas numpy flask flask-cors joblib
```

### Option 4: Install Packages Individually (May Work)

Sometimes installing packages one by one helps:

```bash
pip install numpy
pip install pandas
pip install scikit-learn
pip install flask flask-cors joblib
```

## Quick Fix for Now

If you just want to test quickly, try installing without scikit-learn first:

```bash
pip install flask flask-cors pandas numpy joblib
```

Then manually install scikit-learn from a wheel:
```bash
pip install scikit-learn --only-binary :all:
```

## Recommended Approach

**I recommend Option 1** - Use Python 3.11 or 3.12. It's the fastest and most reliable for Windows.

After switching Python versions, the installation should work smoothly!

## Verify Installation

After installing, verify:
```bash
python -c "import sklearn, flask, pandas, numpy; print('All packages installed!')"
```

