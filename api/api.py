"""
SmartDoc ML API Server
Flask API that uses trained ML model to predict specialists based on symptoms, area, and duration.
"""

from flask import Flask, request, jsonify
from flask_cors import CORS
import joblib
import os

app = Flask(__name__)
CORS(app)  # Allow all origins

# Global variables for model components
model = None
vectorizer = None
label_encoder = None
model_type = 'random_forest'  # Change to 'naive_bayes' if you trained that model

def create_feature_vector(symptoms, affected_area, duration):
    """Create a combined feature string for vectorization"""
    # Handle symptoms as list or string
    if isinstance(symptoms, list):
        symptoms_str = " ".join([s.lower().strip() for s in symptoms])
    else:
        symptoms_str = " ".join(symptoms.lower().split(",")) if symptoms else ""
    
    area_str = affected_area.lower() if affected_area else ""
    duration_str = duration.lower() if duration else ""
    combined = f"{symptoms_str} {area_str} {duration_str}".strip()
    return combined

def load_model():
    """Load the trained model and preprocessors"""
    global model, vectorizer, label_encoder
    
    model_path = f'models/specialist_model_{model_type}.pkl'
    vectorizer_path = 'models/vectorizer.pkl'
    encoder_path = 'models/label_encoder.pkl'
    
    try:
        if not os.path.exists(model_path):
            print(f"⚠️  Warning: Model file not found: {model_path}")
            print("   Using fallback prediction logic. Train the model first!")
            return False
        
        print(f"📦 Loading ML model from {model_path}...")
        model = joblib.load(model_path)
        vectorizer = joblib.load(vectorizer_path)
        label_encoder = joblib.load(encoder_path)
        print("✅ Model loaded successfully!")
        return True
    except Exception as e:
        print(f"❌ Error loading model: {e}")
        print("   Using fallback prediction logic.")
        return False

def validate_and_predict_specialist(symptoms_list, affected_area, duration):
    """
    Comprehensive validation and rule-based prediction before using ML model.
    This ensures medically accurate predictions based on symptom-area-duration combinations.
    """
    symptoms_lower = [s.lower() for s in symptoms_list]
    # Handle multiple areas (comma-separated)
    if affected_area:
        areas_list = [a.strip().lower() for a in affected_area.split(',')]
        area_lower = affected_area.lower()
    else:
        areas_list = []
        area_lower = ""
    duration_lower = duration.lower() if duration else ""
    
    # Comprehensive symptom-to-specialist mapping
    symptom_specialist_map = {
        # Respiratory symptoms -> Pulmonologist
        'respiratory': {
            'symptoms': ['asthma', 'asthma attacks', 'wheezing', 'chronic cough', 'coughing blood', 
                        'chest congestion', 'difficulty breathing', 'difficulty breathing at night', 
                        'shortness of breath', 'shortness of breath on exertion'],
            'specialist': 'Pulmonologist',
            'valid_areas': ['chest', 'lungs', 'chest/lungs', 'general'],
            'invalid_areas': ['skin', 'eyes', 'abdomen', 'stomach', 'head', 'brain', 'legs', 'back', 'knee/hip/shoulder', 'shoulder'],
            'confidence': 0.85
        },
        # Digestive symptoms -> Gastroenterologist
        'digestive': {
            'symptoms': ['abdominal pain', 'constipation', 'diarrhea', 'vomiting', 'nausea', 'bloating', 
                        'acid reflux', 'bloody stool', 'pain in upper right abdomen', 'vomiting (child)'],
            'specialist': 'Gastroenterologist',
            'valid_areas': ['abdomen', 'stomach', 'general'],
            'invalid_areas': ['skin', 'eyes', 'head', 'brain', 'chest', 'legs', 'back', 'knee/hip/shoulder', 'shoulder'],
            'confidence': 0.85
        },
        # Skin symptoms -> Dermatologist
        'skin': {
            'symptoms': ['acne', 'rash', 'itchy rash', 'itching', 'eczema', 'psoriasis', 'skin discoloration'],
            'specialist': 'Dermatologist (Skin & Sex)',
            'valid_areas': ['skin', 'general'],
            'invalid_areas': ['abdomen', 'stomach', 'eyes', 'head', 'brain', 'chest', 'legs', 'back', 'knee/hip/shoulder', 'shoulder'],
            'confidence': 0.85
        },
        # Neurological symptoms -> Neurologist
        'neurological': {
            'symptoms': ['headache', 'severe headache', 'dizziness', 'vertigo', 'seizures', 'numbness', 
                        'migraine', 'memory loss', 'hallucinations', 'tremors', 'paralysis'],
            'specialist': 'Neurologist',
            'valid_areas': ['head', 'brain', 'head/inner ear', 'general'],
            'confidence': 0.85
        },
        # Cardiac symptoms -> Cardiologist
        'cardiac': {
            'symptoms': ['chest pain', 'palpitations', 'heart palpitations', 'irregular heartbeat', 
                        'fainting', 'fainting during exertion', 'shortness of breath'],
            'specialist': 'Cardiologist',
            'valid_areas': ['chest', 'chest/lungs', 'general'],
            'invalid_areas': ['skin', 'eyes', 'abdomen', 'stomach', 'head', 'brain', 'legs', 'back', 'knee/hip/shoulder', 'shoulder'],
            'confidence': 0.85
        },
        # Orthopedic symptoms -> Orthopedic Surgeon
        'orthopedic': {
            'symptoms': ['joint pain', 'knee pain', 'back pain', 'neck pain', 'shoulder pain', 
                        'fractures', 'limited mobility', 'difficulty walking'],
            'specialist': 'Orthopedic Surgeon',
            'valid_areas': ['knee/hip/shoulder', 'shoulder', 'back', 'legs', 'general'],
            'confidence': 0.85
        },
        # ENT symptoms -> Otolaryngologist
        'ent': {
            'symptoms': ['ear pain', 'hearing loss', 'ringing in ears', 'sore throat', 'sinus pain', 
                        'runny nose', 'sneezing and runny nose', 'voice changes'],
            'specialist': 'Otolaryngologist (ENT)',
            'valid_areas': ['nose/throat', 'head/inner ear', 'head', 'general'],
            'confidence': 0.85
        },
        # Urological symptoms -> Urologist
        'urological': {
            'symptoms': ['frequent urination', 'frequent urination at night', 'pain during urination', 
                        'blood in urine', 'foamy urine', 'dark urine', 'urine retention', 'reduced urination'],
            'specialist': 'Urologist (Urinary)',
            'valid_areas': ['general'],
            'confidence': 0.85
        },
        # Endocrine symptoms -> Endocrinologist
        'endocrine': {
            'symptoms': ['excessive thirst', 'extreme thirst', 'cold intolerance', 'heat intolerance', 
                        'unexplained weight changes', 'irregular periods', 'missed period'],
            'specialist': 'Endocrinologist (Thyroid)',
            'valid_areas': ['general'],
            'confidence': 0.80
        },
        # Eye symptoms -> Ophthalmologist
        'eye': {
            'symptoms': ['blurred vision', 'double vision', 'vision loss', 'eye pain', 'red eyes', 
                        'yellow eyes', 'swelling around eyes'],
            'specialist': 'Ophthalmologist',
            'valid_areas': ['eyes', 'general'],
            'invalid_areas': ['skin', 'abdomen', 'stomach', 'chest', 'head', 'brain', 'legs', 'back', 'knee/hip/shoulder', 'shoulder'],
            'confidence': 0.85
        },
        # General symptoms -> General Physician (should be checked first)
        'general': {
            'symptoms': ['fever', 'fever (child)', 'fatigue', 'cough', 'sore throat', 'runny nose', 
                        'sneezing and runny nose', 'general discomfort'],
            'specialist': 'General Physician',
            'valid_areas': ['general', 'head', 'chest', 'nose/throat', 'chest/lungs'],
            'confidence': 0.80
        }
    }
    
    # Check for mismatched symptom-area combinations
    # IMPORTANT: Check general symptoms FIRST (fever, etc.) as they should take priority
    matched_systems = []
    general_system = None
    
    for system_name, system_data in symptom_specialist_map.items():
        has_symptom = any(sym in ' '.join(symptoms_lower) for sym in system_data['symptoms'])
        if has_symptom:
            # Check if ANY of the selected areas match (for multiple areas)
            if areas_list:
                # Check if at least one area matches
                area_matches = any(
                    any(valid_area in area for valid_area in system_data['valid_areas'])
                    for area in areas_list
                )
                # Also check if ALL areas are invalid (critical mismatch)
                all_areas_invalid = False
                if 'invalid_areas' in system_data:
                    all_areas_invalid = all(
                        any(inv_area in area for inv_area in system_data['invalid_areas'])
                        for area in areas_list
                    )
            else:
                area_matches = True
                all_areas_invalid = False
            
            system_info = {
                'system': system_name,
                'specialist': system_data['specialist'],
                'confidence': system_data['confidence'],
                'area_matches': area_matches,
                'all_areas_invalid': all_areas_invalid if areas_list else False,
                'symptom_count': sum(1 for sym in system_data['symptoms'] if sym in ' '.join(symptoms_lower))
            }
            
            # Track general symptoms separately
            if system_name == 'general':
                general_system = system_info
            else:
                matched_systems.append(system_info)
    
    # If we have general symptoms (fever, etc.) and only 1-2 other symptoms, prioritize General Physician
    if general_system and len(matched_systems) <= 1:
        # Fever in child with head area -> General Physician (pediatric case)
        if general_system['area_matches'] or not areas_list:
            return general_system['specialist'], general_system['confidence'], "General symptom (fever) - General Physician"
    
    # Add general system to matched if no other strong matches
    if general_system and len(matched_systems) == 0:
        matched_systems.append(general_system)
    
    # If multiple unrelated systems, check if one is general (fever, etc.)
    if len(matched_systems) >= 3:
        # If general symptoms are present, they might be secondary - check if others are more specific
        has_general = any(s['system'] == 'general' for s in matched_systems)
        if has_general:
            # Remove general from consideration if we have more specific symptoms
            matched_systems = [s for s in matched_systems if s['system'] != 'general']
            if len(matched_systems) >= 3:
                return "Could not generate", 0.0, "Too many unrelated symptoms"
        else:
            return "Could not generate", 0.0, "Too many unrelated symptoms"
    
    # If we have a clear match with area validation
    if len(matched_systems) == 1:
        system = matched_systems[0]
        if system['area_matches'] or not areas_list:  # Area matches or no area specified
            # Special case: "Seasonal" duration with ENT/respiratory symptoms might indicate allergies
            if duration_lower == 'seasonal' and system['system'] in ['ent', 'respiratory']:
                return system['specialist'], system['confidence'], "Rule-based match (seasonal)"
            return system['specialist'], system['confidence'], "Rule-based match"
        else:
            # Check for critical area mismatches using invalid_areas from symptom_specialist_map
            if 'invalid_areas' in symptom_specialist_map.get(system['system'], {}) and areas_list:
                invalid_areas = symptom_specialist_map[system['system']]['invalid_areas']
                # Check if ALL selected areas are invalid
                all_invalid = all(
                    any(inv_area in area for inv_area in invalid_areas)
                    for area in areas_list
                )
                if all_invalid:
                    return "Could not generate", 0.0, f"Selected areas don't match {system['system']} symptoms"
            
            # Fallback critical area mismatches
            if areas_list:
                critical_area_mismatches = {
                    'digestive': ['skin', 'eyes', 'head', 'brain', 'chest', 'legs', 'back'],
                    'eye': ['skin', 'abdomen', 'stomach', 'chest', 'head', 'brain', 'legs', 'back'],
                    'skin': ['abdomen', 'stomach', 'eyes', 'head', 'brain', 'chest', 'legs', 'back'],
                    'cardiac': ['skin', 'eyes', 'abdomen', 'stomach', 'head', 'brain', 'legs', 'back'],
                    'respiratory': ['skin', 'eyes', 'abdomen', 'stomach', 'head', 'brain', 'legs', 'back'],
                }
                
                if system['system'] in critical_area_mismatches:
                    invalid_areas = critical_area_mismatches[system['system']]
                    # Check if ALL selected areas are invalid
                    all_invalid = all(
                        any(inv_area in area for inv_area in invalid_areas)
                        for area in areas_list
                    )
                    if all_invalid:
                        return "Could not generate", 0.0, f"Selected areas don't match {system['system']} symptoms"
            # Symptom present but area doesn't match - check for critical mismatches
            # Critical mismatches: digestive symptoms with head area, respiratory with head, etc.
            critical_mismatches = [
                ('digestive', ['head', 'brain']),  # Vomiting/nausea with head area doesn't make sense
                ('respiratory', ['head', 'brain']),  # Asthma with head area doesn't make sense
                ('skin', ['head', 'brain']),  # Acne/rash with head area (unless it's scalp, but that's different)
            ]
            
            for system_type, invalid_areas in critical_mismatches:
                if system['system'] == system_type and any(inv_area in area_lower for inv_area in invalid_areas):
                    # Special case: Vomiting with head area + seasonal might be migraine/allergy related
                    if system['system'] == 'digestive' and 'vomiting' in ' '.join(symptoms_lower) and duration_lower == 'seasonal':
                        # Could be migraine (Neurologist) or allergy-related (ENT) - unclear, return "Could not generate"
                        return "Could not generate", 0.0, "Vomiting with head area and seasonal duration - could be migraine or allergy, unclear"
                    return "Could not generate", 0.0, f"Critical mismatch: {system['system']} symptoms with {area_lower} area"
            
            # Non-critical mismatch - still use the specialist but lower confidence
            return system['specialist'], system['confidence'] * 0.7, "Rule-based match (area mismatch)"
    
    # If 2 systems, check if they're related or conflicting
    if len(matched_systems) == 2:
        sys1, sys2 = matched_systems[0], matched_systems[1]
        
        # Check for area mismatch first - if area doesn't match either system, it's conflicting
        if areas_list:
            area_matches_sys1 = any(
                any(valid_area in area for valid_area in symptom_specialist_map[sys1['system']]['valid_areas'])
                for area in areas_list
            )
            area_matches_sys2 = any(
                any(valid_area in area for valid_area in symptom_specialist_map[sys2['system']]['valid_areas'])
                for area in areas_list
            )
            
            # If area is specified and doesn't match either system, return "Could not generate"
            if not area_matches_sys1 and not area_matches_sys2:
                return "Could not generate", 0.0, f"Selected areas don't match {sys1['system']} or {sys2['system']} symptoms"
        else:
            area_matches_sys1 = True
            area_matches_sys2 = True
        
        # Related systems (e.g., cardiac + respiratory both can be chest-related)
        related_combos = [
            ('cardiac', 'respiratory'), ('respiratory', 'cardiac'),
            ('neurological', 'ent'), ('ent', 'neurological'),
            ('digestive', 'urological'), ('urological', 'digestive')
        ]
        
        # Unrelated conflicting systems (should return "Could not generate")
        conflicting_combos = [
            ('digestive', 'eye'), ('eye', 'digestive'),
            ('digestive', 'skin'), ('skin', 'digestive'),
            ('eye', 'skin'), ('skin', 'eye'),
            ('digestive', 'orthopedic'), ('orthopedic', 'digestive'),
            ('eye', 'orthopedic'), ('orthopedic', 'eye'),
        ]
        
        if (sys1['system'], sys2['system']) in conflicting_combos:
            return "Could not generate", 0.0, f"Conflicting systems: {sys1['system']} + {sys2['system']}"
        
        if (sys1['system'], sys2['system']) in related_combos:
            # Use the one with more symptoms or higher confidence
            if sys1['symptom_count'] >= sys2['symptom_count']:
                return sys1['specialist'], sys1['confidence'] * 0.8, "Rule-based (related systems)"
            else:
                return sys2['specialist'], sys2['confidence'] * 0.8, "Rule-based (related systems)"
        else:
            # Other conflicting systems
            return "Could not generate", 0.0, "Conflicting symptom systems"
    
    # No clear match - return None to use ML model
    return None, 0.0, "No rule-based match"

def predict_with_model(symptoms, affected_area, duration):
    """Predict specialist using the trained ML model"""
    try:
        # Check for conflicting/miscellaneous symptoms
        symptoms_list = symptoms if isinstance(symptoms, list) else [s.strip() for s in str(symptoms).split(',')]
        symptoms_lower = [s.lower() for s in symptoms_list]
        
        # First, try rule-based validation
        rule_result, rule_confidence, rule_reason = validate_and_predict_specialist(symptoms_list, affected_area, duration)
        if rule_result and rule_result != "Could not generate":
            print(f"✅ Rule-based prediction: {rule_result} ({rule_reason})")
            return rule_result, rule_confidence
        
        if rule_result == "Could not generate":
            print(f"⚠️  Rule-based: {rule_reason}")
            return "Could not generate", 0.0
        
        # Check for too many unrelated symptoms (should return "Could not generate")
        if len(symptoms_list) > 4:
            return "Could not generate", 0.0
        
        # Check for conflicting symptoms from different body systems
        cardiac_symptoms = ['chest pain', 'shortness of breath', 'palpitations', 'heart palpitations', 'irregular heartbeat', 'fainting']
        respiratory_symptoms = ['asthma', 'asthma attacks', 'wheezing', 'cough', 'chronic cough', 'coughing blood', 'chest congestion', 'difficulty breathing', 'difficulty breathing at night']
        digestive_symptoms = ['constipation', 'diarrhea', 'abdominal pain', 'vomiting', 'nausea', 'bloating', 'acid reflux', 'bloody stool']
        neurological_symptoms = ['headache', 'severe headache', 'dizziness', 'vertigo', 'seizures', 'numbness', 'migraine']
        skin_symptoms = ['rash', 'itchy rash', 'itching', 'acne', 'eczema', 'psoriasis']
        orthopedic_symptoms = ['joint pain', 'knee pain', 'back pain', 'neck pain', 'shoulder pain']
        
        symptom_groups = [
            cardiac_symptoms,
            respiratory_symptoms,
            digestive_symptoms,
            neurological_symptoms,
            skin_symptoms,
            orthopedic_symptoms
        ]
        
        # Check for symptom-area mismatches (handle multiple areas)
        if affected_area:
            areas_list = [a.strip().lower() for a in affected_area.split(',')]
        else:
            areas_list = []
        
        has_respiratory = any(sym in ' '.join(symptoms_lower) for sym in respiratory_symptoms)
        has_neurological_symptom = any(sym in ' '.join(symptoms_lower) for sym in neurological_symptoms)
        
        # Respiratory symptoms should match chest/lungs area, not head/legs/eyes
        if has_respiratory and areas_list:
            has_invalid_area = any(area in ['head', 'brain', 'legs', 'eyes', 'skin', 'abdomen', 'stomach'] for area in areas_list)
            has_valid_area = any(area in ['chest', 'lungs', 'chest/lungs'] for area in areas_list)
            if has_invalid_area and not has_valid_area:
                print(f"⚠️  Symptom-area mismatch: respiratory symptoms ({symptoms_list}) with invalid areas: {areas_list}")
                return "Could not generate", 0.0
        
        # Count how many different symptom groups are present
        groups_present = sum(1 for group in symptom_groups if any(sym in ' '.join(symptoms_lower) for sym in group))
        
        # If symptoms from 2+ different systems, check if they're truly conflicting
        if groups_present >= 2:
            # Check if we have both cardiac AND digestive symptoms (conflicting)
            has_cardiac = any(sym in ' '.join(symptoms_lower) for sym in cardiac_symptoms)
            has_digestive = any(sym in ' '.join(symptoms_lower) for sym in digestive_symptoms)
            has_neurological = any(sym in ' '.join(symptoms_lower) for sym in neurological_symptoms)
            has_skin = any(sym in ' '.join(symptoms_lower) for sym in skin_symptoms)
            has_orthopedic = any(sym in ' '.join(symptoms_lower) for sym in orthopedic_symptoms)
            
            # Count distinct systems
            systems = sum([has_cardiac, has_respiratory, has_digestive, has_neurological, has_skin, has_orthopedic])
            
            # If 3+ different systems, definitely miscellaneous
            if systems >= 3:
                return "Could not generate", 0.0
            
            # If cardiac + digestive (common conflict), check duration
            if has_cardiac and has_digestive:
                # If both are present and duration suggests different specialists, return "Could not generate"
                if len(symptoms_list) >= 2:
                    return "Could not generate", 0.0
        
        feature_text = create_feature_vector(symptoms, affected_area, duration)
        X = vectorizer.transform([feature_text])
        prediction_encoded = model.predict(X)[0]
        prediction = label_encoder.inverse_transform([prediction_encoded])[0]
        
        # Get confidence score
        probabilities = model.predict_proba(X)[0]
        confidence = max(probabilities)
        
        # Get top 3 predictions for better validation
        top_indices = probabilities.argsort()[-3:][::-1]
        top_predictions = [(label_encoder.inverse_transform([idx])[0], probabilities[idx]) for idx in top_indices]
        
        print(f"   Top predictions: {[(p[0], f'{p[1]:.2%}') for p in top_predictions]}")
        
        # Lower threshold for duration variations - model might have less training data for some durations
        # If confidence is too low (< 0.15), consider it uncertain
        if confidence < 0.15:
            print(f"⚠️  Low confidence ({confidence:.2%}) for prediction: {prediction}")
            return "Could not generate", confidence
        
        # If prediction is "Could not generate", return it
        if prediction == "Could not generate" or prediction is None:
            return "Could not generate", confidence
        
        # Additional validation: If top 2 predictions are very close and one makes more sense, use that
        if len(top_predictions) >= 2:
            top1_spec, top1_conf = top_predictions[0]
            top2_spec, top2_conf = top_predictions[1]
            
            # If confidence difference is less than 10%, validate both
            if abs(top1_conf - top2_conf) < 0.10:
                # Use rule-based validation to pick the better one
                rule_result, rule_conf, _ = validate_and_predict_specialist(symptoms_list, affected_area, duration)
                if rule_result and rule_result != "Could not generate":
                    # If rule-based matches one of the top predictions, use that
                    if rule_result == top1_spec or rule_result == top2_spec:
                        print(f"🔧 Using rule-validated prediction: {rule_result} (was {top1_spec} vs {top2_spec})")
                        return rule_result, max(rule_conf, top1_conf)
        
        return prediction, confidence
    except Exception as e:
        print(f"Error in prediction: {e}")
        return None, 0.0

def fallback_predict(symptoms_list, affected_area, duration):
    """
    Fallback prediction logic when model is not available.
    This is a simple rule-based system.
    """
    symptoms_lower = [s.lower() for s in symptoms_list]
    area_lower = affected_area.lower() if affected_area else ""
    duration_lower = duration.lower() if duration else ""
    
    # Check for miscellaneous/too many symptoms
    if len(symptoms_list) > 4:
        return "Could not generate", 0.0
    
    # General symptoms (fever, especially in children) -> General Physician (should be checked first)
    if any(s in symptoms_lower for s in ['fever', 'fever (child)']):
        # Fever is a general symptom - should go to General Physician unless there are other specific symptoms
        return "General Physician", 0.75
    
    # Respiratory symptoms (asthma, wheezing, cough) -> Pulmonologist
    if any(s in symptoms_lower for s in ['asthma', 'asthma attacks', 'wheezing', 'chronic cough', 'coughing blood']):
        return "Pulmonologist", 0.8
    
    # Duration-based logic for chest pain
    if 'chest pain' in symptoms_lower:
        if 'months' in duration_lower:
            return "Oncologist", 0.7
        elif any(d in duration_lower for d in ['days', 'weeks', 'hours', 'intermittent']):
            return "Cardiologist", 0.8
    
    # Other symptom-based rules with area validation
    if any(s in symptoms_lower for s in ['chest pain', 'shortness of breath', 'palpitations']):
        return "Cardiologist", 0.75
    elif any(s in symptoms_lower for s in ['severe headache', 'dizziness', 'migraine']) and ('head' in area_lower or 'brain' in area_lower or not area_lower):
        if 'inner ear' in area_lower or 'vertigo' in ' '.join(symptoms_lower):
            return "Otolaryngologist (ENT)", 0.7
        return "Neurologist", 0.75
    elif any(s in symptoms_lower for s in ['joint pain', 'knee pain', 'back pain', 'neck pain', 'shoulder pain']):
        return "Orthopedic Surgeon", 0.75
    elif any(s in symptoms_lower for s in ['acne', 'itchy rash', 'rash', 'eczema', 'psoriasis']):
        return "Dermatologist (Skin & Sex)", 0.75
    elif any(s in symptoms_lower for s in ['vomiting', 'diarrhea', 'abdominal pain', 'constipation', 'nausea', 'bloating']):
        # Digestive symptoms should NOT be Neurologist unless area is clearly head/brain
        if 'head' in area_lower and 'brain' not in area_lower and not any(ns in ' '.join(symptoms_lower) for ns in ['headache', 'dizziness', 'vertigo']):
            # Digestive symptom with head area but no neurological symptoms = mismatch
            return "Could not generate", 0.0
        return "Gastroenterologist", 0.7
    elif any(s in symptoms_lower for s in ['excessive thirst', 'frequent urination']):
        if 'urinary' in area_lower or 'urination' in ' '.join(symptoms_lower):
            return "Urologist (Urinary)", 0.7
        return "Endocrinologist (Thyroid)", 0.7
    elif 'fever' in symptoms_lower and 'cough' in symptoms_lower:
        return "General Physician", 0.7
    
    # Too vague or miscellaneous
    if len(symptoms_list) <= 2 and any(s in symptoms_lower for s in ['fatigue', 'mild discomfort', 'general']):
        return "Could not generate", 0.0
    
    return "General Physician", 0.5

@app.route('/predict', methods=['POST'])
def predict_specialist():
    """
    Main prediction endpoint
    
    Expected JSON:
    {
        "symptoms": ["chest pain", "shortness of breath"] or "chest pain,shortness of breath",
        "area": "Chest",
        "duration": "Days to weeks"
    }
    """
    try:
        data = request.json
        
        # Extract inputs
        symptoms_input = data.get('symptoms', [])
        affected_area = data.get('area', '')
        duration = data.get('duration', '')
        
        # Normalize symptoms to list
        if isinstance(symptoms_input, str):
            symptoms_list = [s.strip() for s in symptoms_input.split(',')]
        elif isinstance(symptoms_input, list):
            symptoms_list = symptoms_input
        else:
            symptoms_list = []
        
        print(f"📥 Received request:")
        print(f"   Symptoms: {symptoms_list}")
        print(f"   Area: {affected_area}")
        print(f"   Duration: {duration}")
        
        # Parse multiple areas (comma-separated)
        if affected_area:
            areas_list = [a.strip().lower() for a in affected_area.lower().split(',')]
            area_lower = affected_area.lower()
        else:
            areas_list = []
            area_lower = ""
        
        # Quick check for obvious conflicts before model prediction
        symptoms_lower = [s.lower() for s in symptoms_list]
        cardiac_keywords = ['chest pain', 'shortness of breath', 'palpitations', 'heart']
        respiratory_keywords = ['asthma', 'asthma attacks', 'wheezing', 'cough', 'chronic cough', 'coughing blood', 'chest congestion', 'difficulty breathing']
        digestive_keywords = ['constipation', 'diarrhea', 'abdominal pain', 'vomiting', 'nausea', 'stomach', 'bloody stool']
        eye_keywords = ['blurred vision', 'double vision', 'vision loss', 'eye pain', 'red eyes', 'yellow eyes']
        skin_keywords = ['acne', 'rash', 'itchy rash', 'itching', 'eczema', 'psoriasis', 'skin discoloration']
        
        has_cardiac = any(keyword in ' '.join(symptoms_lower) for keyword in cardiac_keywords)
        has_respiratory = any(keyword in ' '.join(symptoms_lower) for keyword in respiratory_keywords)
        has_digestive = any(keyword in ' '.join(symptoms_lower) for keyword in digestive_keywords)
        has_eye = any(keyword in ' '.join(symptoms_lower) for keyword in eye_keywords)
        has_skin = any(keyword in ' '.join(symptoms_lower) for keyword in skin_keywords)
        
        # COMPREHENSIVE area-symptom validation (handles multiple areas)
        if areas_list and 'general' not in areas_list:
            # Define ALL valid areas for each symptom type
            valid_areas_by_symptom = {
                'cardiac': ['chest', 'chest/lungs', 'general'],
                'respiratory': ['chest', 'lungs', 'chest/lungs', 'general'],
                'digestive': ['abdomen', 'stomach', 'general'],
                'eye': ['eyes', 'general'],
                'skin': ['skin', 'general'],
                'neurological': ['head', 'brain', 'head/inner ear', 'general'],
                'orthopedic': ['knee/hip/shoulder', 'shoulder', 'back', 'legs', 'general'],
                'ent': ['nose/throat', 'head/inner ear', 'head', 'general']
            }
            
            # Check which symptom types are present
            present_symptom_types = []
            if has_cardiac:
                present_symptom_types.append('cardiac')
            if has_respiratory:
                present_symptom_types.append('respiratory')
            if has_digestive:
                present_symptom_types.append('digestive')
            if has_eye:
                present_symptom_types.append('eye')
            if has_skin:
                present_symptom_types.append('skin')
            
            # If we have symptoms, check if ANY area matches ANY symptom type
            if present_symptom_types:
                area_matches_any = False
                for symptom_type in present_symptom_types:
                    valid_areas = valid_areas_by_symptom.get(symptom_type, ['general'])
                    # Check if any selected area matches this symptom type
                    if any(area in valid_areas for area in areas_list):
                        area_matches_any = True
                        break
                
                # If NO area matches ANY symptom type, it's a mismatch
                if not area_matches_any:
                    print(f"⚠️  Area mismatch: '{affected_area}' doesn't match {present_symptom_types} symptoms")
                    return jsonify({
                        'specialist': 'Could not generate',
                        'confidence': 0.0,
                        'model_used': 'mismatch_detection',
                        'reason': f'Selected areas do not match the selected symptoms ({", ".join(present_symptom_types)})'
                    })
                
                # Check if too many unrelated areas selected (e.g., 5+ areas)
                if len(areas_list) >= 5:
                    print(f"⚠️  Too many areas selected: {len(areas_list)} areas")
                    return jsonify({
                        'specialist': 'Could not generate',
                        'confidence': 0.0,
                        'model_used': 'mismatch_detection',
                        'reason': 'Too many areas selected - unclear focus'
                    })
        
        # Check for multiple unrelated symptoms with area mismatch
        unrelated_systems = sum([has_cardiac, has_respiratory, has_digestive, has_eye, has_skin])
        if unrelated_systems >= 2:
            # Check if area matches any of the systems
            area_matches_any = False
            if 'skin' in area_lower and has_skin:
                area_matches_any = True
            elif 'eyes' in area_lower and has_eye:
                area_matches_any = True
            elif ('abdomen' in area_lower or 'stomach' in area_lower) and has_digestive:
                area_matches_any = True
            elif ('chest' in area_lower or 'lung' in area_lower) and (has_cardiac or has_respiratory):
                area_matches_any = True
            
            # If we have unrelated symptoms and area doesn't match, return "Could not generate"
            if not area_matches_any and area_lower:
                # Special case: digestive + eye with skin area = conflicting
                if has_digestive and has_eye and 'skin' in area_lower:
                    print(f"⚠️  Conflicting symptoms: digestive + eye with Skin area")
                    return jsonify({
                        'specialist': 'Could not generate',
                        'confidence': 0.0,
                        'model_used': 'mismatch_detection',
                        'reason': 'Conflicting symptoms (digestive + eye) with mismatched area (Skin)'
                    })
        
        # Digestive symptoms (vomiting, nausea, abdominal pain) should NOT match head area
        if has_digestive and 'head' in area_lower and 'brain' not in area_lower:
            duration_lower = duration.lower() if duration else ""
            # Special case: Vomiting with head area + seasonal could be migraine/allergy related
            if 'vomiting' in ' '.join(symptoms_lower) and duration_lower == 'seasonal':
                print(f"⚠️  Critical mismatch: Vomiting with Head area and Seasonal duration - could be migraine or allergy, unclear")
                return jsonify({
                    'specialist': 'Could not generate',
                    'confidence': 0.0,
                    'model_used': 'mismatch_detection',
                    'reason': 'Vomiting with Head area and Seasonal duration - unclear if migraine or allergy related'
                })
            else:
                print(f"⚠️  Critical mismatch: Digestive symptoms with 'Head' area (not Brain)")
                return jsonify({
                    'specialist': 'Could not generate',
                    'confidence': 0.0,
                    'model_used': 'mismatch_detection',
                    'reason': 'Digestive symptoms (vomiting/nausea) do not match Head area'
                })
        
        # Respiratory symptoms (asthma, wheezing) should match chest/lungs, not head
        if has_respiratory and 'head' in area_lower and 'chest' not in area_lower and 'lung' not in area_lower:
            print(f"⚠️  Symptom-area mismatch: respiratory symptoms with 'Head' area - ignoring area mismatch, using symptom priority")
            # Note: We'll still predict, but the model should prioritize the symptom over the area
        
        # If both cardiac and digestive symptoms present, check if they conflict
        if has_cardiac and has_digestive and len(symptoms_list) >= 2:
            area_lower = affected_area.lower() if affected_area else ""
            # If area clearly favors digestive system, prioritize that
            if 'stomach' in area_lower or 'abdomen' in area_lower:
                # Area suggests digestive - but we have cardiac symptoms too
                # This is conflicting, return "Could not generate"
                print(f"⚠️  Conflicting symptoms: cardiac + digestive (area suggests digestive)")
                return jsonify({
                    'specialist': 'Could not generate',
                    'confidence': 0.0,
                    'model_used': 'conflict_detection',
                    'reason': 'Conflicting symptoms from different body systems'
                })
            elif 'chest' in area_lower:
                # Area suggests cardiac - but we have digestive symptoms too
                # This is conflicting, return "Could not generate"
                print(f"⚠️  Conflicting symptoms: cardiac + digestive (area suggests cardiac)")
                return jsonify({
                    'specialist': 'Could not generate',
                    'confidence': 0.0,
                    'model_used': 'conflict_detection',
                    'reason': 'Conflicting symptoms from different body systems'
                })
            else:
                # Unclear area with conflicting symptoms
                print(f"⚠️  Conflicting symptoms: cardiac + digestive with unclear area")
                return jsonify({
                    'specialist': 'Could not generate',
                    'confidence': 0.0,
                    'model_used': 'conflict_detection',
                    'reason': 'Conflicting symptoms from different body systems'
                })
        
        # Rule-based override for clear symptom-area mismatches
        # If respiratory symptoms with head area, prioritize symptom (Pulmonologist)
        if has_respiratory and 'head' in area_lower and 'chest' not in area_lower and 'lung' not in area_lower:
            print(f"🔧 Override: Respiratory symptoms with mismatched 'Head' area -> using symptom priority")
            # Still use model, but log the override
        
        # Use ML model if available, otherwise fallback
        if model is not None:
            specialist, confidence = predict_with_model(symptoms_list, affected_area, duration)
            print(f"🤖 ML Prediction: {specialist} (confidence: {confidence:.2%})")
            
            # Post-prediction validation: Override if model prediction doesn't make medical sense
            symptoms_lower_check = [s.lower() for s in symptoms_list]
            area_lower_check = affected_area.lower() if affected_area else ""
            
            # Override for respiratory symptoms with wrong area
            if has_respiratory and specialist not in ['Pulmonologist', 'Could not generate']:
                print(f"🔧 Override: Correcting {specialist} to Pulmonologist for respiratory symptoms")
                specialist = "Pulmonologist"
                confidence = 0.75
            
            # Override for digestive symptoms (abdominal pain, etc.) - but check for area mismatch
            has_digestive_symptoms = any(s in ' '.join(symptoms_lower_check) for s in ['abdominal pain', 'constipation', 'diarrhea', 'vomiting', 'nausea', 'vomiting (child)'])
            if has_digestive_symptoms:
                if specialist == 'Neurologist':
                    # But if area is Head, this is a mismatch - return "Could not generate"
                    if 'head' in area_lower_check and 'brain' not in area_lower_check:
                        print(f"🔧 Override: Digestive symptoms with Head area (not Brain) - returning 'Could not generate'")
                        specialist = "Could not generate"
                        confidence = 0.0
                    else:
                        print(f"🔧 Override: Correcting Neurologist to Gastroenterologist for digestive symptoms")
                        specialist = "Gastroenterologist"
                        confidence = 0.75
                elif specialist == 'Gastroenterologist' and 'head' in area_lower_check and 'brain' not in area_lower_check:
                    # Vomiting with Head area (not Brain) is a mismatch
                    print(f"🔧 Override: Vomiting/nausea with Head area is a mismatch - returning 'Could not generate'")
                    specialist = "Could not generate"
                    confidence = 0.0
            
            # Override for skin symptoms (acne, rash, etc.)
            if any(s in ' '.join(symptoms_lower_check) for s in ['acne', 'rash', 'itchy rash', 'eczema']) and specialist == 'Neurologist':
                print(f"🔧 Override: Correcting Neurologist to Dermatologist for skin symptoms")
                specialist = "Dermatologist (Skin & Sex)"
                confidence = 0.75
            
            # If multiple unrelated symptoms (e.g., abdominal pain + acne + head area), return "Could not generate"
            has_digestive_sym = any(s in ' '.join(symptoms_lower_check) for s in ['abdominal pain', 'constipation', 'diarrhea', 'vomiting', 'nausea'])
            has_skin_sym = any(s in ' '.join(symptoms_lower_check) for s in ['acne', 'rash', 'itchy rash', 'eczema'])
            has_neurological_sym = any(s in ' '.join(symptoms_lower_check) for s in ['headache', 'dizziness', 'vertigo', 'seizures'])
            
            if (has_digestive_sym and has_skin_sym) or (has_digestive_sym and has_neurological_sym and 'head' in area_lower_check):
                if specialist != 'Could not generate':
                    print(f"🔧 Override: Multiple unrelated symptoms detected, returning 'Could not generate'")
                    specialist = "Could not generate"
                    confidence = 0.0
        else:
            specialist, confidence = fallback_predict(symptoms_list, affected_area, duration)
            print(f"📋 Fallback Prediction: {specialist} (confidence: {confidence:.2%})")
        
        # Return response
        response = {
            'specialist': specialist,
            'confidence': round(confidence, 2),
            'model_used': 'ml' if model is not None else 'fallback'
        }
        
        return jsonify(response)
        
    except Exception as e:
        print(f"❌ Error in /predict endpoint: {e}")
        return jsonify({
            'error': 'Internal server error',
            'message': str(e),
            'specialist': 'Could not generate'
        }), 500

@app.route('/health', methods=['GET'])
def health_check():
    """Health check endpoint"""
    return jsonify({
        'status': 'healthy',
        'model_loaded': model is not None,
        'model_type': model_type if model is not None else 'none'
    })

if __name__ == '__main__':
    # Try to load the model on startup
    model_loaded = load_model()
    
    if not model_loaded:
        print("\n" + "=" * 60)
        print("⚠️  ML Model not found!")
        print("=" * 60)
        print("To use the ML model:")
        print("1. Train the model: python train_model.py")
        print("2. Ensure model files are in the 'models/' directory")
        print("3. Restart this server")
        print("\nCurrently using fallback prediction logic.")
        print("=" * 60 + "\n")
    
    print("\n" + "=" * 60)
    print("🚀 Starting SmartDoc ML API server")
    print("=" * 60)
    print("📍 Endpoint: http://127.0.0.1:5000/predict")
    print("🏥 Health check: http://127.0.0.1:5000/health")
    print("=" * 60 + "\n")
    
    app.run(port=5000, debug=True)
