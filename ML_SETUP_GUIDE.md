# SmartDoc ML Model Setup Guide

This guide will help you set up the ML training environment in VS Code and train the specialist prediction model.

## 📋 Prerequisites

- Python 3.8 or higher
- VS Code installed
- XAMPP (for database access)

## 🔧 VS Code Setup

### 1. Install Required Extensions

Open VS Code and install these extensions:

1. **Python** (by Microsoft)
   - Extension ID: `ms-python.python`
   - Provides Python language support, debugging, and IntelliSense

2. **Python Debugger** (by Microsoft)
   - Extension ID: `ms-python.debugpy`
   - For debugging Python code

3. **Jupyter** (by Microsoft) - Optional but recommended
   - Extension ID: `ms-toolsai.jupyter`
   - Useful for data exploration and testing

### 2. Install Python Libraries

**⚠️ IMPORTANT for Windows Users:**

If you see "Microsoft Visual C++ 14.0 or greater is required" error:

**Option A: Use Python 3.11 or 3.12 (Recommended)**
- Python 3.13 may not have pre-built wheels for scikit-learn
- Download Python 3.11 or 3.12 from [python.org](https://www.python.org/downloads/)
- In VS Code: `Ctrl+Shift+P` → "Python: Select Interpreter" → Choose 3.11 or 3.12

**Option B: Install C++ Build Tools**
- See `INSTALL_WINDOWS.md` for detailed instructions

**Option C: Use the installation script**
```bash
# Run the batch file
install_packages.bat
```

**Normal Installation:**
Open a terminal in VS Code (`Ctrl + ~` or `Terminal > New Terminal`) and run:

```bash
# Navigate to your project directory
cd C:\xampp\htdocs\SmartDoc-Drafting

# Install required packages
pip install -r requirements.txt
```

If you encounter permission issues, use:
```bash
pip install --user -r requirements.txt
```

**If scikit-learn fails to install:**
```bash
# Try installing with pre-built wheels only
pip install scikit-learn --only-binary :all:
```

### 3. Verify Installation

Test that all packages are installed correctly:

```bash
python -c "import flask, sklearn, pandas, numpy, joblib; print('All packages installed successfully!')"
```

## 🚀 Training the Model

### Step 1: Train Random Forest Model (Recommended)

```bash
python train_model.py random_forest
```

### Step 2: Train Naive Bayes Model (Alternative)

```bash
python train_model.py naive_bayes
```

### What Happens During Training?

1. The script loads the enhanced dataset with symptom combinations
2. Features are vectorized using TF-IDF
3. Labels are encoded
4. Data is split into training (80%) and testing (20%)
5. Model is trained and evaluated
6. Model files are saved to the `models/` directory:
   - `specialist_model_random_forest.pkl` (or `naive_bayes`)
   - `vectorizer.pkl`
   - `label_encoder.pkl`
   - `class_mapping.json`

### Expected Output

You should see:
- Dataset preparation status
- Feature vectorization details
- Training progress
- Accuracy score (should be > 80%)
- Classification report
- Confirmation of saved files

## 📊 Understanding the Dataset

The enhanced dataset includes:

- **Symptoms**: Multiple symptoms can be combined (e.g., "chest pain,shortness of breath")
- **Affected Area**: Body region (e.g., "Chest", "Head", "General")
- **Duration**: Time period (e.g., "Days to weeks", "Months")
- **Specialist**: Target prediction (e.g., "Cardiologist", "Neurologist")

### Key Features:

1. **Duration-based logic**: 
   - Chest pain for days/weeks → Cardiologist
   - Chest pain for months → Oncologist

2. **Miscellaneous handling**:
   - Too many unrelated symptoms → "Could not generate"
   - Vague symptoms → "Could not generate"

3. **Combination patterns**:
   - Multiple related symptoms strengthen prediction
   - Area + duration provide context

## 🔄 Updating the Dataset

To add more training examples, edit `train_model.py` and add entries to `ENHANCED_DATASET`:

```python
("symptom1,symptom2", "AffectedArea", "Duration", "Specialist"),
```

## 🧪 Testing the Model

After training, test the model:

```bash
python test_model.py
```

This will run sample predictions to verify the model works correctly.

## 📝 Next Steps

1. ✅ Train the model (you just did this!)
2. ✅ Update `api.py` to use the trained model
3. ✅ Test the Flask API
4. ✅ Integrate with PHP frontend

## 🐛 Troubleshooting

### Issue: "Module not found"
**Solution**: Make sure you're in the correct directory and have installed requirements.txt

### Issue: "Permission denied"
**Solution**: Use `pip install --user` or run VS Code as administrator

### Issue: Low accuracy
**Solution**: 
- Add more training examples to `ENHANCED_DATASET`
- Try the other model type (Random Forest vs Naive Bayes)
- Adjust model hyperparameters in `train_model.py`

### Issue: Model files not created
**Solution**: Check that the `models/` directory exists or is created automatically

## 📚 Additional Resources

- [Scikit-learn Documentation](https://scikit-learn.org/stable/)
- [Flask Documentation](https://flask.palletsprojects.com/)
- [Random Forest Classifier](https://scikit-learn.org/stable/modules/generated/sklearn.ensemble.RandomForestClassifier.html)
- [Multinomial Naive Bayes](https://scikit-learn.org/stable/modules/generated/sklearn.naive_bayes.MultinomialNB.html)

## ✅ Checklist

- [ ] VS Code Python extension installed
- [ ] All packages from requirements.txt installed
- [ ] Model trained successfully
- [ ] Model files saved in `models/` directory
- [ ] Accuracy > 80%
- [ ] Ready to update api.py

---

**Note**: The model will be saved in the `models/` directory. Make sure this directory is included in your version control (or add it to `.gitignore` if the files are too large).

