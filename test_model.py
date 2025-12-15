"""
Test script for the trained ML model
Run this after training to verify the model works correctly
"""

import joblib
import json
import os

def create_feature_vector(symptoms, affected_area, duration):
    """Create a combined feature string for vectorization"""
    symptoms_str = " ".join(symptoms.lower().split(",")) if symptoms else ""
    area_str = affected_area.lower() if affected_area else ""
    duration_str = duration.lower() if duration else ""
    combined = f"{symptoms_str} {area_str} {duration_str}".strip()
    return combined

def load_model(model_type='random_forest'):
    """Load the trained model and preprocessors"""
    model_path = f'models/specialist_model_{model_type}.pkl'
    vectorizer_path = 'models/vectorizer.pkl'
    encoder_path = 'models/label_encoder.pkl'
    
    if not os.path.exists(model_path):
        print(f"❌ Model file not found: {model_path}")
        print("Please train the model first using: python train_model.py")
        return None, None, None
    
    model = joblib.load(model_path)
    vectorizer = joblib.load(vectorizer_path)
    label_encoder = joblib.load(encoder_path)
    
    return model, vectorizer, label_encoder

def predict(model, vectorizer, label_encoder, symptoms, affected_area, duration):
    """Make a prediction"""
    feature_text = create_feature_vector(symptoms, affected_area, duration)
    X = vectorizer.transform([feature_text])
    prediction_encoded = model.predict(X)[0]
    prediction = label_encoder.inverse_transform([prediction_encoded])[0]
    
    # Get prediction probabilities
    probabilities = model.predict_proba(X)[0]
    class_mapping = {i: cls for i, cls in enumerate(label_encoder.classes_)}
    prob_dict = {class_mapping[i]: prob for i, prob in enumerate(probabilities)}
    
    return prediction, prob_dict

def main():
    print("=" * 60)
    print("SmartDoc ML Model Test")
    print("=" * 60)
    
    # Load model
    print("\nLoading model...")
    model, vectorizer, label_encoder = load_model('random_forest')
    
    if model is None:
        return
    
    print("✓ Model loaded successfully!\n")
    
    # Test cases
    test_cases = [
        {
            "symptoms": "chest pain,shortness of breath",
            "area": "Chest",
            "duration": "Days to weeks",
            "expected": "Cardiologist"
        },
        {
            "symptoms": "chest pain",
            "area": "Chest",
            "duration": "Months",
            "expected": "Oncologist"
        },
        {
            "symptoms": "severe headache,dizziness",
            "area": "Head",
            "duration": "Hours to days",
            "expected": "Neurologist"
        },
        {
            "symptoms": "joint pain,swelling",
            "area": "Knee/Hip/Shoulder",
            "duration": "Months",
            "expected": "Orthopedic Surgeon"
        },
        {
            "symptoms": "itchy rash",
            "area": "Skin",
            "duration": "Days to weeks",
            "expected": "Dermatologist (Skin & Sex)"
        },
        {
            "symptoms": "fatigue,headache",
            "area": "General",
            "duration": "Occasional",
            "expected": "Could not generate"
        },
        {
            "symptoms": "chest pain,headache,joint pain,skin rash",
            "area": "General",
            "duration": "Intermittent",
            "expected": "Could not generate"
        }
    ]
    
    print("Running test cases...\n")
    print("-" * 60)
    
    correct = 0
    total = len(test_cases)
    
    for i, test in enumerate(test_cases, 1):
        prediction, probabilities = predict(
            model, vectorizer, label_encoder,
            test["symptoms"], test["area"], test["duration"]
        )
        
        status = "✓" if prediction == test["expected"] else "✗"
        if prediction == test["expected"]:
            correct += 1
        
        print(f"\nTest {i}: {status}")
        print(f"  Symptoms: {test['symptoms']}")
        print(f"  Area: {test['area']}")
        print(f"  Duration: {test['duration']}")
        print(f"  Expected: {test['expected']}")
        print(f"  Predicted: {prediction}")
        
        # Show top 3 probabilities
        sorted_probs = sorted(probabilities.items(), key=lambda x: x[1], reverse=True)[:3]
        print(f"  Top probabilities:")
        for spec, prob in sorted_probs:
            print(f"    - {spec}: {prob:.2%}")
        
        print("-" * 60)
    
    print(f"\n{'=' * 60}")
    print(f"Test Results: {correct}/{total} correct ({correct/total*100:.1f}%)")
    print(f"{'=' * 60}\n")

if __name__ == '__main__':
    main()

