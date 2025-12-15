# SmartDoc Final Project Report Content (IEEE Template Ready)

> Copy-paste this content into the provided IEEE Word template.
> Replace all placeholders inside `<< >>`.
>
> Required PDF name (per announcement): `CS299.17_FA25_GROUP_NAME_Final_Project_Report.pdf`

---

## Cover Page

**Department of Electrical and Computer Engineering**  
**North South University**  

**Junior Design Project CSE299 Report**  

**Title of the Project:** SmartDoc: Healthcare Management System with ML Based Symptom to Specialist Recommendation

**Submitted By:**  
- <<Name 1>>, ID: <<ID 1>>  
- <<Name 2>>, ID: <<ID 2>>  
- <<Name 3>>, ID: <<ID 3>>  

**Faculty Advisor:** <<Advisor Name>>  
**Semester:** Fall 2025  
**Course:** CSE299  

---

## Abstract

SmartDoc is a web based healthcare management platform that helps patients find appropriate doctors and provides an automated symptom based specialist recommendation. The system integrates a patient portal and an administrator portal with a MySQL database for managing doctors, hospitals, symptoms, locations, and consultation history. A machine learning powered API supports real time specialist prediction using user selected symptoms, affected area, and duration. The platform also improves the doctor discovery experience with location based filtering and rating aware doctor listings. This report presents the design, implementation, and evaluation of SmartDoc, including database level verification of doctor data and quantitative results of the trained specialist prediction model. The system demonstrates how a practical healthcare information system can combine traditional database driven workflows with an ML assisted decision support component while preserving safety through conflict and mismatch handling.

**Keywords:** healthcare management, symptom checker, specialist recommendation, machine learning, Flask API, PHP, MySQL

---

## 1. Introduction

Healthcare services often suffer from information gaps for patients: patients may not know which specialist to consult, how to find suitable doctors nearby, or how to manage their consultation history efficiently. In many contexts, especially in densely populated cities, the challenge is not only locating a hospital but selecting the correct specialist and then selecting a doctor with adequate information, such as specialization, hospital, address, and patient ratings.

SmartDoc addresses this problem by providing (i) a patient portal to search and browse doctors, generate a specialist recommendation from symptoms, and keep consultation records; (ii) an administrative portal to manage healthcare entities such as doctors, hospitals, locations, symptoms, and specializations; and (iii) an ML assisted recommendation API that predicts likely specialists from symptom context. The goal is to reduce the time and uncertainty a patient faces when deciding where to start care, while keeping the recommendation process consistent and explainable.

**Contributions of this work** include a full stack implementation with PHP and MySQL, a Flask based ML inference service, an end to end symptom to specialist flow integrated into the UI, and safety focused logic that handles conflicting symptoms and symptom area mismatches.

---

## 2. Literature Review

This project is related to three common solution approaches in digital health systems.

**A. Rule based symptom to specialist mapping**  
Many symptom checkers use rule based decision trees or symptom to body system mappings. Pros include interpretability, predictable behavior, and low compute cost. Cons include limited coverage, difficulty scaling to many combinations, and brittleness when symptoms overlap across specialties.

**B. Machine learning based classification**  
Supervised learning models can map symptom text and contextual features to specialist classes using vectorization and multi class classifiers. Pros include better generalization to combinations and easier updates through data collection. Cons include data dependence, risk of low confidence predictions, and potential unsafe outputs without validation.

**C. Hybrid approach**  
A practical approach combines rule based validation with an ML model. Rules can reject obvious mismatches and handle special cases, while ML covers broader combinations. This hybrid method improves robustness and supports safe failure modes.

**Unique contribution of SmartDoc** is a hybrid pipeline implemented in a production like web workflow: the ML API supports predictions, while rule based validation and mismatch detection reduce unsafe recommendations. Additionally, the platform connects recommendation output directly to real doctor listings and location based filtering, turning a prediction into an actionable next step.

---

## 3. Design Model

### A. System Architecture

SmartDoc consists of the following major components.

- **Patient Web Application (PHP, Bootstrap)**: symptom selection, specialist recommendation UI, doctor search and filtering, consultation history.
- **Admin Web Application (PHP)**: CRUD management for doctors, hospitals, symptoms, specializations, and locations.
- **Database (MySQL)**: stores users, doctors, hospitals, symptoms, specializations, ratings, and consultation history.
- **ML Training Pipeline (Python)**: trains a Random Forest classifier using TF IDF features from symptoms and context.
- **ML Inference API (Flask)**: exposes a predict endpoint used by the patient UI.
- **Geocoding and Location Filter (Nominatim via server proxy)**: estimates user area and ranks doctors based on distance and area match.

### B. Simplified Block Diagram

```
+-------------------+        HTTP (JSON)         +---------------------+
| Patient UI (PHP)  |  <---------------------->  | Flask ML API (Python)|
| selectSpecialist  |                            | /predict, /health    |
+---------+---------+                            +----------+----------+
          |                                                    |
          | SQL queries                                         | loads
          v                                                    v
+-------------------+                                  +------------------+
| MySQL Database    |                                  | Trained Model     |
| doctors, symptoms |                                  | .pkl files         |
| hospitals, ratings|                                  | vectorizer encoder |
+---------+---------+                                  +------------------+
          |
          | Admin CRUD (PHP)
          v
+-------------------+
| Admin UI (PHP)    |
+-------------------+
```

---

## 4. Implementation

### A. Patient Portal

1) **Symptom based specialist flow**  
- Patients select symptoms, affected area, and duration in `patients/selectSpecialist.php`.
- The UI sends a JSON request to the ML API endpoint and receives a specialist name.
- The system redirects to `patients/GenerateSpecialist.php`, which validates the ML recommendation against area and symptom context and then loads matching doctors.

2) **Database based symptom checker flow**  
- A second workflow in `patients/symptom-checker.php` computes a best matching specialization ID by counting the frequency of specialization IDs among selected symptoms.
- The system redirects to `patients/find-doctors.php` to show doctors and optional location filtering.

3) **Doctor discovery and location filtering**  
- `patients/find-doctors.php` supports search by doctor name, specialization, and a text location.
- `patients/fetch_doctors_ajax.php` provides an API to fetch doctors for a specialization and returns rating metadata.
- `patients/geocode_proxy.php` is a server side proxy to the Nominatim API to avoid client side CORS issues.

### B. ML API and Model

- `api.py` starts a Flask server that loads model files from `models/`.
- The `/predict` endpoint supports symptoms, affected area, and duration. It includes rule based validation to detect mismatches and conflicting symptoms and can return a safe fallback output.
- `train_model.py` trains a Random Forest classifier using TF IDF text features with ngrams, class weighting, and cross validation.

### C. Admin Portal

The admin module provides management features for system data such as hospitals, doctors, symptoms, locations, and specializations. These features ensure the database remains consistent and updateable without code changes.

### D. Final Implementation Screenshots

Insert the following screenshots into your Word template.

- **Figure 1**: Landing Page `LandingPage.html`.
- **Figure 2**: Patient login and patient dashboard.
- **Figure 3**: Symptom selection UI `patients/selectSpecialist.php`.
- **Figure 4**: Recommendation page and doctors list `patients/GenerateSpecialist.php`.
- **Figure 5**: Doctor search page with filters and ratings `patients/find-doctors.php`.
- **Figure 6**: Admin dashboard and at least one admin CRUD screen such as doctors or hospitals.

---

## 5. Results and Discussions

### A. ML Model Evaluation

The Random Forest model was trained using the repository training script.

- **Dataset size**: 97 samples  
- **Number of classes**: 13 specialist labels including a safe output label Could not generate  
- **Feature representation**: TF IDF with unigrams bigrams and trigrams  
- **Train test split**: 80 percent training 20 percent testing with stratification  

**Test set metrics produced by `train_model.py`:**

- Accuracy: 75.00 percent  
- Precision weighted: 74.17 percent  
- Recall weighted: 75.00 percent  
- F1 score weighted: 73.59 percent  
- Cross validation accuracy mean: 58.42 percent with variability due to small class counts

**Functional test cases produced by `test_model.py`:** 5 out of 7 correct, 71.4 percent. Two failures illustrate realistic ambiguity for long duration chest pain and mixed symptom sets, which is why the system includes rule based conflict handling and validation in the API.

### B. Database and Location Filtering Verification

Doctor data and address matching were verified with SQL queries focused on Bashundhara area hospitals. The repository includes `BASHUNDHARA_TEST_RESULTS.md` containing SQL queries and expected doctor matches. Improvements documented there include expanded area detection radius, enhanced address matching patterns, and fallback matching when GPS area detection fails.

### C. Discussion of Hurdles and Solutions

- **Limited training data and class imbalance**: Some specialists have few samples. The training pipeline uses class weighting and adds mismatch examples to strengthen the safe class.
- **Conflicting symptoms and unsafe recommendations**: The inference API includes rule based checks for mismatched symptom area and multiple unrelated symptom systems and can return a safe no recommendation output.
- **Geolocation and external API reliability**: Client side geocoding can fail due to CORS and rate limits. A server side proxy with rate limiting was implemented and area based matching was used as a fast fallback.

### D. Future Improvements

- Expand dataset using real symptom tables from the database once deployed.
- Add model calibration and confidence threshold tuning for improved safe rejection.
- Add explainability output by returning a short reason based on matched rules and top ML probabilities.

---

## 6. Complex Engineering Problem Solving and Complex Engineering Activities (CEP and CEA)

### A. CEP Attributes

**a) What knowledge did you need to work on this project**  
This project required knowledge of full stack web development, relational database design, API design, and machine learning classification. It also required understanding of safe decision support behavior, including how to handle ambiguous or conflicting inputs.

**b) What unique way did you use to design this project**  
A hybrid design was used. The system does not rely solely on ML. It combines database driven logic, ML predictions, and rule based validation to reduce unsafe or inconsistent outputs. The approach also integrates the prediction result directly into doctor discovery to make the recommendation actionable.

**c) What topics did you need to learn from previous courses that you were not familiar with**  
Key topics included text feature engineering with TF IDF, multi class model training and evaluation, cross validation, and production integration of an ML model through a REST style API. Additional learning involved security and reliability concerns such as input validation, CORS issues, and rate limiting.

### B. CEA Attributes

**a) Which resources did the project involve**  
The project involved human resources for UI development, backend development, and data preparation. Modern tools included PHP with Apache, MySQL, Python with scikit learn, and Flask for API deployment. External services included Nominatim for geocoding accessed via a proxy.

**b) What is the innovation**  
The main innovation is an end to end, safety oriented symptom to specialist workflow that combines ML predictions with validation and fallback logic, and then connects the result to nearby doctor discovery with rating and location aware ranking.

---

## 7. References

[1] Pallets, Flask Documentation, accessed Dec 2025.

[2] Scikit learn Developers, Scikit learn User Guide, accessed Dec 2025.

[3] T. Joachims, A Probabilistic Analysis of the Rocchio Algorithm with TFIDF for Text Categorization, 1997.

[4] L. Breiman, Random Forests, Machine Learning, vol 45, pp 5 to 32, 2001.

[5] OpenStreetMap Nominatim Usage Policy and API Documentation, accessed Dec 2025.

[6] Oracle, MySQL 8 0 Reference Manual, accessed Dec 2025.

[7] Bootstrap Contributors, Bootstrap 5 Documentation, accessed Dec 2025.

---

## 8. Appendix

### A. Source Code Link

Repository link: <<Paste your GitHub link here>>

### B. Key Code Snippets

**1) ML training entry point**  
File: `train_model.py`  
Purpose: prepares dataset, vectorizes features, trains and evaluates model, saves artifacts.

```python
def create_feature_vector(symptoms, affected_area, duration):
    symptoms_str = " ".join(symptoms.lower().split(",")) if symptoms else ""
    area_str = affected_area.lower() if affected_area else ""
    duration_str = duration.lower() if duration else ""
    return f"{symptoms_str} {area_str} {duration_str}".strip()

# Vectorization
vectorizer = TfidfVectorizer(max_features=1000, ngram_range=(1, 3), max_df=0.9, sublinear_tf=True)
X = vectorizer.fit_transform(X_text)

# Model training (Random Forest)
model = RandomForestClassifier(
    n_estimators=200, max_depth=20, min_samples_split=3, min_samples_leaf=2,
    max_features='sqrt', class_weight=class_weights, random_state=42, n_jobs=-1
)
model.fit(X_train, y_train)
```

**2) ML inference API endpoint**  
File: `api.py`  
Purpose: receives symptoms, area, duration, validates conflicts, returns specialist and confidence.

```python
@app.route('/predict', methods=['POST'])
def predict_specialist():
    data = request.json
    symptoms_input = data.get('symptoms', [])
    affected_area = data.get('area', '')
    duration = data.get('duration', '')

    # Normalize symptoms to list
    if isinstance(symptoms_input, str):
        symptoms_list = [s.strip() for s in symptoms_input.split(',')]
    else:
        symptoms_list = symptoms_input

    # Rule based validation (mismatch and conflict detection) runs before ML
    # If valid, model predicts specialist and returns confidence
    return jsonify({
        'specialist': specialist,
        'confidence': round(confidence, 2),
        'model_used': 'ml' if model is not None else 'fallback'
    })
```

**3) Patient UI to ML API integration**  
File: `patients/selectSpecialist.php`  
Purpose: collects selected symptoms and sends request to `http://127.0.0.1:5000/predict` then redirects to `GenerateSpecialist.php`.

```javascript
const API_URL = "http://127.0.0.1:5000/predict";
const payload = [...selectedSymptoms.map(s => s.symptom_name), area, duration].filter(Boolean);

const res = await fetch(API_URL, {
  method: "POST",
  headers: {"Content-Type":"application/json"},
  body: JSON.stringify({ symptoms: payload })
});
const data = await res.json();
window.location.href = `GenerateSpecialist.php?specialist_name=${data.specialist}&symptom_ids=${ids}&area=${area}&duration=${duration}`;
```

**4) Recommendation validation and doctor loading**  
File: `patients/GenerateSpecialist.php`  
Purpose: validates ML recommendation against area and symptom exceptions and shows recommended doctors.

**5) Location and geocoding proxy**  
Files: `patients/geocode_proxy.php`, `patients/find-doctors.php`, `patients/fetch_doctors_ajax.php`  
Purpose: avoid CORS issues, apply rate limiting, compute user area, and rank doctors by distance and ratings.

**6) Database verification query example (Bashundhara doctors)**  
File: `BASHUNDHARA_TEST_RESULTS.md`

```sql
SELECT 
    d.doctor_id,
    d.name AS doctor_name,
    d.designation,
    s.specialization_name,
    h.hospital_name,
    h.address AS hospital_address
FROM doctor d
LEFT JOIN specialization s ON d.specialization_id = s.specialization_id
LEFT JOIN hospital h ON d.hospital_id = h.hospital_id
WHERE h.hospital_id IN (7, 17, 30, 86)
ORDER BY s.specialization_name, d.name;
```
