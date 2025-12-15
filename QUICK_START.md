# 🚀 Quick Start Guide - ML Model Integration

## Step 1: Install VS Code Extensions

1. Open VS Code
2. Go to Extensions (Ctrl+Shift+X)
3. Install:
   - **Python** (ms-python.python)
   - **Python Debugger** (ms-python.debugpy)

## Step 2: Install Python Libraries

Open terminal in VS Code (Ctrl+~) and run:

```bash
pip install -r requirements.txt
```

## Step 3: Train the Model

```bash
python train_model.py random_forest
```

This will:
- Create an enhanced dataset with symptom combinations
- Train a Random Forest classifier
- Save model files to `models/` directory
- Show accuracy and evaluation metrics

**Expected output:** Accuracy should be > 80%

## Step 4: Test the Model

```bash
python test_model.py
```

This verifies the model works correctly with sample inputs.

## Step 5: Start the Flask API

```bash
python api.py
```

You should see:
```
✅ Model loaded successfully!
🚀 Starting SmartDoc ML API server
📍 Endpoint: http://127.0.0.1:5000/predict
```

## Step 6: Test the API

The API is now ready! Your PHP frontend (`selectSpecialist.php`) will automatically use it.

## 📋 What Was Created

1. **train_model.py** - Training script with enhanced dataset
2. **api.py** - Updated Flask API that uses the trained model
3. **test_model.py** - Test script to verify model accuracy
4. **requirements.txt** - Python dependencies
5. **ML_SETUP_GUIDE.md** - Detailed setup instructions

## 🎯 Key Features

- ✅ Handles symptoms, affected area, and duration
- ✅ Duration-based logic (e.g., chest pain for months → Oncologist)
- ✅ Detects miscellaneous symptoms → "Could not generate"
- ✅ Fallback prediction if model not loaded
- ✅ Confidence scores included

## 🔧 Troubleshooting

**Model not found?**
- Make sure you ran `python train_model.py` first
- Check that `models/` directory contains `.pkl` files

**Low accuracy?**
- Add more training examples to `ENHANCED_DATASET` in `train_model.py`
- Try `naive_bayes` instead: `python train_model.py naive_bayes`

**API not working?**
- Check Flask is running: `python api.py`
- Verify endpoint: http://127.0.0.1:5000/health

## 📚 Next Steps

1. ✅ Train model
2. ✅ Test model
3. ✅ Start API
4. ✅ Test with PHP frontend
5. 🔄 Add more training data as needed

---

**Need help?** Check `ML_SETUP_GUIDE.md` for detailed instructions.

