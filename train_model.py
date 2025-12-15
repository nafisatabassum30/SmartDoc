"""
SmartDoc ML Model Training Script
Trains a Random Forest Classifier to predict specialists based on symptoms, affected area, and duration.
Can load data from database or use hardcoded dataset.
"""

import pandas as pd
import numpy as np
from sklearn.model_selection import train_test_split, cross_val_score, StratifiedKFold
from sklearn.ensemble import RandomForestClassifier
from sklearn.naive_bayes import MultinomialNB
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.preprocessing import LabelEncoder
from sklearn.metrics import accuracy_score, classification_report, confusion_matrix, f1_score, precision_score, recall_score
import joblib
import json
import os
import mysql.connector
from mysql.connector import Error

# Enhanced dataset with more intricate combinations
# Format: symptoms (comma-separated), affected_area, duration, specialist
ENHANCED_DATASET = [
    # Cardiologist cases
    ("chest pain,shortness of breath", "Chest", "Minutes to hours", "Cardiologist"),
    ("chest pain,palpitations", "Chest", "Days to weeks", "Cardiologist"),
    ("chest pain,dizziness", "Chest", "Hours to days", "Cardiologist"),
    ("shortness of breath,chest pain", "Chest/Lungs", "Weeks", "Cardiologist"),
    ("chest pain", "Chest", "Intermittent", "Cardiologist"),
    ("chest pain,shortness of breath", "Chest", "Months", "Oncologist"),  # Longer duration -> oncologist
    ("chest pain,weight loss", "Chest", "Months", "Oncologist"),
    ("chest pain,fatigue", "Chest", "Weeks", "Cardiologist"),
    
    # Neurologist cases
    ("severe headache,dizziness", "Head", "Hours to days", "Neurologist"),
    ("severe headache,nausea", "Head", "Days to weeks", "Neurologist"),
    ("severe headache,vision problems", "Head", "Hours to days", "Neurologist"),
    ("severe headache", "Head", "Intermittent", "Neurologist"),
    
    # ENT cases
    ("dizziness vertigo,nausea", "Head/Inner ear", "Minutes to hours", "Otolaryngologist (ENT)"),
    ("dizziness vertigo,ear pain", "Head/Inner ear", "Days to weeks", "Otolaryngologist (ENT)"),
    ("sneezing and runny nose,congestion", "Nose/Throat", "Seasonal", "Otolaryngologist (ENT)"),
    ("sneezing and runny nose,sore throat", "Nose/Throat", "Days to weeks", "Otolaryngologist (ENT)"),
    
    # Orthopedic cases
    ("joint pain,swelling", "Knee/Hip/Shoulder", "Months", "Orthopedic Surgeon"),
    ("joint pain,stiffness", "Knee/Hip/Shoulder", "Weeks", "Orthopedic Surgeon"),
    ("joint pain", "Knee/Hip/Shoulder", "Days to weeks", "Orthopedic Surgeon"),
    ("knee pain,swelling", "Knee/Hip/Shoulder", "Days to weeks", "Orthopedic Surgeon"),
    
    # Dermatologist cases
    ("itchy rash,redness", "Skin", "Days to weeks", "Dermatologist (Skin & Sex)"),
    ("itchy rash,blisters", "Skin", "Weeks", "Dermatologist (Skin & Sex)"),
    ("itchy rash", "Skin", "Intermittent", "Dermatologist (Skin & Sex)"),
    
    # Gastroenterologist cases
    ("abdominal pain,nausea", "General", "Days to weeks", "Gastroenterologist"),
    ("abdominal pain,vomiting", "General", "Hours to days", "Gastroenterologist"),
    ("abdominal pain,diarrhea", "General", "Days to weeks", "Gastroenterologist"),
    ("vomiting,diarrhea", "General", "Hours to days", "Gastroenterologist"),
    
    # Endocrinologist cases
    ("excessive thirst,frequent urination", "General", "Weeks", "Endocrinologist (Thyroid)"),
    ("excessive thirst,weight loss", "General", "Months", "Endocrinologist (Thyroid)"),
    ("excessive thirst", "General", "Weeks", "Endocrinologist (Thyroid)"),
    
    # Urologist cases
    ("frequent urination,painful urination", "General", "Weeks", "Urologist (Urinary)"),
    ("frequent urination,blood in urine", "General", "Days to weeks", "Urologist (Urinary)"),
    ("frequent urination", "General", "Weeks", "Urologist (Urinary)"),
    
    # Gynaecologist cases
    ("pelvic pain,irregular periods", "General", "Months", "Gynaecologist (Obstetric)"),
    ("pelvic pain,abnormal bleeding", "General", "Weeks", "Gynaecologist (Obstetric)"),
    
    # Hepatologist cases
    ("abdominal pain,jaundice", "General", "Weeks", "Hepatologist (Liver)"),
    ("abdominal pain,fatigue", "General", "Months", "Hepatologist (Liver)"),
    
    # General Physician cases (mild/mixed symptoms)
    ("fever,cough", "General", "Days to weeks", "General Physician"),
    ("fever,headache", "Head", "Hours to days", "General Physician"),
    ("cough,cold", "Nose/Throat", "Days to weeks", "General Physician"),
    ("fever,body ache", "General", "Days to weeks", "General Physician"),
    ("cold,sore throat", "Nose/Throat", "Days to weeks", "General Physician"),
    
    # Additional Cardiologist cases
    ("chest pain,arm pain", "Chest", "Hours to days", "Cardiologist"),
    ("chest pain,back pain", "Chest", "Days to weeks", "Cardiologist"),
    ("shortness of breath,fatigue", "Chest/Lungs", "Weeks", "Cardiologist"),
    
    # Additional Neurologist cases
    ("severe headache,sensitivity to light", "Head", "Hours to days", "Neurologist"),
    ("severe headache,neck pain", "Head", "Days to weeks", "Neurologist"),
    
    # Additional ENT cases
    ("ear pain,hearing loss", "Head/Inner ear", "Days to weeks", "Otolaryngologist (ENT)"),
    ("sore throat,hoarse voice", "Nose/Throat", "Days to weeks", "Otolaryngologist (ENT)"),
    
    # Additional Orthopedic cases
    ("back pain,stiffness", "General", "Months", "Orthopedic Surgeon"),
    ("hip pain,difficulty walking", "Knee/Hip/Shoulder", "Weeks", "Orthopedic Surgeon"),
    
    # Additional Dermatologist cases
    ("skin rash,itching", "Skin", "Days to weeks", "Dermatologist (Skin & Sex)"),
    ("acne,redness", "Skin", "Weeks", "Dermatologist (Skin & Sex)"),
    
    # Additional Gastroenterologist cases
    ("stomach pain,bloating", "General", "Days to weeks", "Gastroenterologist"),
    ("nausea,loss of appetite", "General", "Days to weeks", "Gastroenterologist"),
    
    # Additional Endocrinologist cases
    ("weight gain,fatigue", "General", "Months", "Endocrinologist (Thyroid)"),
    ("hair loss,weight changes", "General", "Months", "Endocrinologist (Thyroid)"),
    
    # Additional Urologist cases
    ("painful urination,urgency", "General", "Days to weeks", "Urologist (Urinary)"),
    ("lower back pain,frequent urination", "General", "Weeks", "Urologist (Urinary)"),
    
    # Additional Gynaecologist cases
    ("irregular periods,abdominal pain", "General", "Months", "Gynaecologist (Obstetric)"),
    ("pelvic pain,discharge", "General", "Weeks", "Gynaecologist (Obstetric)"),
    
    # Additional Hepatologist cases
    ("abdominal pain,yellow skin", "General", "Weeks", "Hepatologist (Liver)"),
    ("fatigue,abdominal swelling", "General", "Months", "Hepatologist (Liver)"),
    
    # Additional Oncologist cases
    ("chest pain,weight loss,cough", "Chest", "Months", "Oncologist"),
    ("unexplained weight loss,fatigue", "General", "Months", "Oncologist"),
    
    # Miscellaneous/uncertain cases (should return "Could not generate")
    ("fatigue,headache", "General", "Occasional", None),  # Too vague
    ("mild discomfort", "General", "Occasional", None),  # Too vague
    ("chest pain,headache,joint pain,skin rash", "General", "Intermittent", None),  # Too many unrelated symptoms
    ("general weakness", "General", "Occasional", None),  # Too vague
    ("multiple unrelated symptoms", "General", "Intermittent", None),  # Too vague
]

def create_feature_vector(symptoms, affected_area, duration):
    """Create a combined feature string for vectorization"""
    # Combine all features into a single string
    symptoms_str = " ".join(symptoms.lower().split(",")) if symptoms else ""
    area_str = affected_area.lower() if affected_area else ""
    duration_str = duration.lower() if duration else ""
    combined = f"{symptoms_str} {area_str} {duration_str}".strip()
    return combined

def load_from_database():
    """Load symptom data from MySQL database"""
    try:
        connection = mysql.connector.connect(
            host='127.0.0.1',
            port=3306,
            database='smartdoc',
            user='root',
            password=''
        )
        
        cursor = connection.cursor(dictionary=True)
        
        # Load specializations
        cursor.execute("SELECT specialization_id, specialization_name FROM specialization")
        spec_map = {row['specialization_id']: row['specialization_name'] for row in cursor.fetchall()}
        
        # Load symptoms
        cursor.execute("""
            SELECT symptom_name, AffectedArea, AffectedDuration, specialization_id
            FROM symptom
            WHERE specialization_id IS NOT NULL
        """)
        
        symptoms = cursor.fetchall()
        dataset = []
        
        # Single symptoms
        for sym in symptoms:
            spec_name = spec_map.get(sym['specialization_id'], 'General Physician')
            dataset.append((
                sym['symptom_name'],
                sym['AffectedArea'] or 'General',
                sym['AffectedDuration'] or 'Days',
                spec_name
            ))
        
        # Create combinations (group by specialization)
        from collections import defaultdict
        import random
        grouped = defaultdict(list)
        for sym in symptoms:
            spec_id = sym['specialization_id']
            if spec_id:
                grouped[spec_id].append(sym)
        
        # Add combinations - create more diverse combinations
        for spec_id, sym_list in grouped.items():
            if len(sym_list) >= 2:
                spec_name = spec_map.get(spec_id, 'General Physician')
                # Create multiple combinations per specialization (up to 5 combinations)
                combinations_created = 0
                max_combinations = min(5, len(sym_list) * (len(sym_list) - 1) // 2)
                
                for i in range(len(sym_list)):
                    for j in range(i+1, len(sym_list)):
                        if combinations_created >= max_combinations:
                            break
                        combined = f"{sym_list[i]['symptom_name']},{sym_list[j]['symptom_name']}"
                        area = sym_list[i]['AffectedArea'] or sym_list[j]['AffectedArea'] or 'General'
                        duration = sym_list[i]['AffectedDuration'] or sym_list[j]['AffectedDuration'] or 'Days'
                        dataset.append((combined, area, duration, spec_name))
                        combinations_created += 1
                    if combinations_created >= max_combinations:
                        break
                
                # Also create triple combinations for specialties with enough symptoms
                if len(sym_list) >= 3 and combinations_created < 3:
                    for i in range(min(2, len(sym_list))):
                        for j in range(i+1, min(i+3, len(sym_list))):
                            for k in range(j+1, min(j+2, len(sym_list))):
                                if combinations_created >= 3:
                                    break
                                combined = f"{sym_list[i]['symptom_name']},{sym_list[j]['symptom_name']},{sym_list[k]['symptom_name']}"
                                area = sym_list[i]['AffectedArea'] or sym_list[j]['AffectedArea'] or sym_list[k]['AffectedArea'] or 'General'
                                duration = sym_list[i]['AffectedDuration'] or sym_list[j]['AffectedDuration'] or sym_list[k]['AffectedDuration'] or 'Days'
                                dataset.append((combined, area, duration, spec_name))
                                combinations_created += 1
                            if combinations_created >= 3:
                                break
                        if combinations_created >= 3:
                            break
        
        cursor.close()
        connection.close()
        
        print(f"   Loaded {len(dataset)} examples from database")
        return dataset
        
    except Error as e:
        print(f"   Database connection failed: {e}")
        return None
    except Exception as e:
        print(f"   Error loading from database: {e}")
        return None

def prepare_dataset():
    """Prepare the dataset for training"""
    data = []
    labels = []
    
    # Try to load from database first
    print("\n[0/6] Loading dataset...")
    db_dataset = load_from_database()
    
    if db_dataset:
        dataset = db_dataset
        # Add miscellaneous cases
        dataset.extend([
            ("fatigue,headache", "General", "Occasional", None),
            ("mild discomfort", "General", "Occasional", None),
            ("general weakness", "General", "Occasional", None),
        ])
    else:
        print("   Using hardcoded dataset")
        dataset = ENHANCED_DATASET
    
    # Add mismatch examples for better accuracy
    mismatch_examples = [
        # Digestive + Eye with wrong area
        ("bloody stool,blurred vision", "Skin", "Hours to days", None),
        ("abdominal pain,blurred vision", "Skin", "Days", None),
        ("vomiting,double vision", "Skin", "Hours to days", None),
        
        # Digestive + Skin with wrong area
        ("bloody stool,acne", "Head", "Days", None),
        ("abdominal pain,rash", "Head", "Weeks", None),
        ("vomiting,itchy rash", "Eyes", "Hours to days", None),
        
        # Eye + Skin with wrong area
        ("blurred vision,acne", "Abdomen", "Days", None),
        ("double vision,rash", "Stomach", "Weeks", None),
        ("eye pain,eczema", "Chest", "Days to weeks", None),
        
        # Respiratory + Digestive with wrong area
        ("asthma attacks,abdominal pain", "Head", "Days", None),
        ("wheezing,vomiting", "Skin", "Hours to days", None),
        ("shortness of breath,bloody stool", "Eyes", "Weeks", None),
        
        # Neurological + Digestive with wrong area
        ("headache,abdominal pain", "Skin", "Days", None),
        ("dizziness,vomiting", "Eyes", "Hours to days", None),
        ("migraine,bloody stool", "Skin", "Days to weeks", None),
        
        # Multiple unrelated symptoms
        ("chest pain,abdominal pain,blurred vision", "Skin", "Days", None),
        ("asthma attacks,vomiting,acne", "Head", "Weeks", None),
        ("headache,abdominal pain,rash", "Eyes", "Days to weeks", None),
        
        # Area mismatches for single symptoms
        ("bloody stool", "Skin", "Days", None),
        ("blurred vision", "Skin", "Hours to days", None),
        ("abdominal pain", "Head", "Days", None),
        ("asthma attacks", "Head", "Days", None),
        ("acne", "Abdomen", "Weeks", None),
        ("rash", "Chest", "Days", None),
        
        # Too vague combinations
        ("fatigue,headache", "General", "Occasional", None),
        ("mild discomfort", "General", "Occasional", None),
        ("general weakness", "General", "Occasional", None),
    ]
    
    dataset.extend(mismatch_examples)
    print(f"   Added {len(mismatch_examples)} mismatch examples for better accuracy")
    
    for symptoms, area, duration, specialist in dataset:
        feature_text = create_feature_vector(symptoms, area, duration)
        data.append(feature_text)
        labels.append(specialist if specialist else "Could not generate")
    
    return data, labels

def train_model(model_type='random_forest'):
    """
    Train the ML model
    
    Args:
        model_type: 'random_forest' or 'naive_bayes'
    """
    print("=" * 60)
    print("SmartDoc ML Model Training")
    print("=" * 60)
    
    # Prepare data
    print("\n[1/6] Preparing dataset...")
    X_text, y = prepare_dataset()
    print(f"   Total samples: {len(X_text)}")
    print(f"   Unique specialists: {len(set(y))}")
    
    # Show class distribution
    from collections import Counter
    class_dist = Counter(y)
    print(f"\n   Class distribution:")
    for cls, count in class_dist.most_common():
        print(f"      {cls}: {count} samples")
    
    # Check balance of "Could not generate" class
    cng_count = class_dist.get("Could not generate", 0)
    total_count = len(y)
    print(f"\n   'Could not generate' samples: {cng_count} ({cng_count/total_count*100:.1f}% of total)")
    
    # Vectorize text features with improved parameters
    print("\n[2/6] Vectorizing features...")
    vectorizer = TfidfVectorizer(
        max_features=1000,  # Increased from 500 for more features
        ngram_range=(1, 3),  # Include unigrams, bigrams, and trigrams
        min_df=1,
        max_df=0.9,  # Slightly lower to filter very common terms
        sublinear_tf=True,  # Apply sublinear tf scaling (1 + log(tf))
        smooth_idf=True,  # Smooth idf weights
        use_idf=True
    )
    X = vectorizer.fit_transform(X_text)
    print(f"   Feature matrix shape: {X.shape}")
    
    # Encode labels
    print("\n[3/6] Encoding labels...")
    label_encoder = LabelEncoder()
    y_encoded = label_encoder.fit_transform(y)
    print(f"   Classes: {list(label_encoder.classes_)}")
    
    # Split data
    print("\n[4/6] Splitting dataset...")
    n_samples = X.shape[0]
    n_classes = len(label_encoder.classes_)
    test_size = 0.2
    
    # Check class distribution
    from collections import Counter
    class_counts = Counter(y_encoded)
    min_class_count = min(class_counts.values())
    
    print(f"   Class distribution: min={min_class_count}, max={max(class_counts.values())}")
    
    # Check if we can use stratified split
    # Stratified split requires at least 2 samples per class
    if min_class_count < 2:
        print(f"   Warning: Some classes have only {min_class_count} sample(s)")
        print("   Using non-stratified split...")
        X_train, X_test, y_train, y_test = train_test_split(
            X, y_encoded, test_size=test_size, random_state=42
        )
    else:
        min_test_samples = int(n_samples * test_size)
        if min_test_samples < n_classes:
            print(f"   Warning: Test set too small for stratified split")
            print("   Using non-stratified split...")
            X_train, X_test, y_train, y_test = train_test_split(
                X, y_encoded, test_size=test_size, random_state=42
            )
        else:
            # Use stratified split
            try:
                X_train, X_test, y_train, y_test = train_test_split(
                    X, y_encoded, test_size=test_size, random_state=42, stratify=y_encoded
                )
                print("   Using stratified split")
            except ValueError as e:
                print(f"   Stratified split failed: {e}")
                print("   Falling back to non-stratified split...")
                X_train, X_test, y_train, y_test = train_test_split(
                    X, y_encoded, test_size=test_size, random_state=42
                )
    
    print(f"   Training samples: {X_train.shape[0]}")
    print(f"   Test samples: {X_test.shape[0]}")
    
    # Train model with improved hyperparameters
    print(f"\n[5/6] Training {model_type} model...")
    
    # Calculate class weights to handle imbalance, especially for "Could not generate"
    from collections import Counter
    class_counts = Counter(y_encoded)
    total_samples = len(y_encoded)
    n_classes = len(class_counts)
    
    # Create class weights - give more weight to minority classes
    class_weights = {}
    for class_idx, count in class_counts.items():
        # Inverse frequency weighting
        class_weights[class_idx] = total_samples / (n_classes * count)
    
    print(f"   Using class weights to handle imbalance")
    
    if model_type == 'random_forest':
        model = RandomForestClassifier(
            n_estimators=200,  # Increased from 100
            max_depth=20,  # Increased from 15
            min_samples_split=3,  # Increased from 2 to reduce overfitting
            min_samples_leaf=2,  # Increased from 1 to reduce overfitting
            max_features='sqrt',  # Use sqrt of features for each tree
            bootstrap=True,
            class_weight=class_weights,  # Use calculated weights instead of 'balanced'
            random_state=42,
            n_jobs=-1,
            verbose=1
        )
    else:  # naive_bayes
        model = MultinomialNB(alpha=0.5)  # Reduced from 1.0 for better sensitivity
    
    model.fit(X_train, y_train)
    print("   Training complete!")
    
    # Cross-validation for better accuracy estimation
    print("\n[6/6] Performing cross-validation...")
    try:
        cv_folds = min(5, min_class_count)  # Use 5-fold CV if possible
        if cv_folds >= 2:
            skf = StratifiedKFold(n_splits=cv_folds, shuffle=True, random_state=42)
            cv_scores = cross_val_score(model, X_train, y_train, cv=skf, scoring='accuracy', n_jobs=-1)
            print(f"   Cross-validation accuracy: {cv_scores.mean():.4f} (+/- {cv_scores.std() * 2:.4f})")
            print(f"   Individual fold scores: {[f'{s:.4f}' for s in cv_scores]}")
        else:
            print("   Skipping cross-validation (insufficient samples per class)")
    except Exception as e:
        print(f"   Cross-validation skipped: {e}")
    
    # Evaluate
    print("\n" + "=" * 60)
    print("Model Evaluation")
    print("=" * 60)
    y_pred = model.predict(X_test)
    accuracy = accuracy_score(y_test, y_pred)
    precision = precision_score(y_test, y_pred, average='weighted', zero_division=0)
    recall = recall_score(y_test, y_pred, average='weighted', zero_division=0)
    f1 = f1_score(y_test, y_pred, average='weighted', zero_division=0)
    
    print(f"\n📊 Test Set Metrics:")
    print(f"   Accuracy:  {accuracy:.4f} ({accuracy*100:.2f}%)")
    print(f"   Precision: {precision:.4f} ({precision*100:.2f}%)")
    print(f"   Recall:    {recall:.4f} ({recall*100:.2f}%)")
    print(f"   F1-Score:  {f1:.4f} ({f1*100:.2f}%)")
    
    # Get unique classes in test set
    unique_test_classes = sorted(set(y_test) | set(y_pred))
    unique_class_names = [label_encoder.classes_[i] for i in unique_test_classes]
    
    print(f"\nClasses in test set: {len(unique_test_classes)} out of {len(label_encoder.classes_)} total")
    
    print("\nClassification Report:")
    try:
        print(classification_report(y_test, y_pred, labels=unique_test_classes, target_names=unique_class_names, zero_division=0))
    except ValueError as e:
        print(f"Note: Could not generate full classification report: {e}")
        print("\nPer-class accuracy (simplified):")
        from collections import Counter
        correct = Counter()
        total = Counter()
        for true, pred in zip(y_test, y_pred):
            total[true] += 1
            if true == pred:
                correct[true] += 1
        for class_idx in unique_test_classes:
            class_name = label_encoder.classes_[class_idx]
            acc = correct[class_idx] / total[class_idx] if total[class_idx] > 0 else 0
            print(f"  {class_name}: {acc:.2%} ({correct[class_idx]}/{total[class_idx]})")
    
    # Save model and preprocessors
    print("\n" + "=" * 60)
    print("Saving Model")
    print("=" * 60)
    
    os.makedirs('models', exist_ok=True)
    
    model_path = f'models/specialist_model_{model_type}.pkl'
    vectorizer_path = 'models/vectorizer.pkl'
    encoder_path = 'models/label_encoder.pkl'
    
    joblib.dump(model, model_path)
    joblib.dump(vectorizer, vectorizer_path)
    joblib.dump(label_encoder, encoder_path)
    
    print(f"\n✓ Model saved: {model_path}")
    print(f"✓ Vectorizer saved: {vectorizer_path}")
    print(f"✓ Label encoder saved: {encoder_path}")
    
    # Save class mapping for reference
    class_mapping = {i: cls for i, cls in enumerate(label_encoder.classes_)}
    with open('models/class_mapping.json', 'w') as f:
        json.dump(class_mapping, f, indent=2)
    print(f"✓ Class mapping saved: models/class_mapping.json")
    
    print("\n" + "=" * 60)
    print("Training Complete!")
    print("=" * 60)
    print("\nNext steps:")
    print("1. Update api.py to load this model")
    print("2. Test the API endpoint")
    print("3. Integrate with your PHP frontend")
    
    return model, vectorizer, label_encoder

if __name__ == '__main__':
    import sys
    
    # Choose model type
    model_type = 'random_forest'  # or 'naive_bayes'
    if len(sys.argv) > 1:
        model_type = sys.argv[1].lower()
        if model_type not in ['random_forest', 'naive_bayes']:
            print("Invalid model type. Use 'random_forest' or 'naive_bayes'")
            sys.exit(1)
    
    print(f"\nTraining {model_type} model...\n")
    train_model(model_type)

