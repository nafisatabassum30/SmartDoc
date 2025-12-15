"""
Load training data from SmartDoc database
Connects to MySQL database and extracts symptom-specialization mappings
"""

import mysql.connector
from mysql.connector import Error
import os

def get_db_connection():
    """Create database connection"""
    try:
        connection = mysql.connector.connect(
            host='127.0.0.1',
            port=3306,  # Default XAMPP MySQL port
            database='smartdoc',
            user='root',
            password=''  # Default XAMPP MySQL password (empty)
        )
        return connection
    except Error as e:
        print(f"Error connecting to MySQL: {e}")
        return None

def load_symptom_data():
    """Load symptom data from database"""
    connection = get_db_connection()
    if not connection:
        print("⚠️  Could not connect to database. Using SQL file parsing instead...")
        return load_from_sql_file()
    
    try:
        cursor = connection.cursor(dictionary=True)
        
        # Load specializations mapping
        cursor.execute("SELECT specialization_id, specialization_name FROM specialization")
        spec_map = {row['specialization_id']: row['specialization_name'] for row in cursor.fetchall()}
        
        # Load symptoms with their specializations
        cursor.execute("""
            SELECT symptom_name, AffectedArea, AffectedDuration, specialization_id
            FROM symptom
            WHERE specialization_id IS NOT NULL
            ORDER BY specialization_id, symptom_name
        """)
        
        symptoms = cursor.fetchall()
        
        # Group symptoms by specialization for creating combinations
        grouped = {}
        for sym in symptoms:
            spec_id = sym['specialization_id']
            spec_name = spec_map.get(spec_id, 'Unknown')
            if spec_name not in grouped:
                grouped[spec_name] = []
            grouped[spec_name].append(sym)
        
        print(f"✅ Loaded {len(symptoms)} symptoms from database")
        print(f"✅ Found {len(grouped)} specializations")
        
        return symptoms, spec_map, grouped
        
    except Error as e:
        print(f"Error loading data: {e}")
        return load_from_sql_file()
    finally:
        if connection and connection.is_connected():
            cursor.close()
            connection.close()

def load_from_sql_file():
    """Fallback: Parse SQL file if database connection fails"""
    sql_file = 'SmartDoc/smartdoc.sql'
    if not os.path.exists(sql_file):
        print(f"❌ SQL file not found: {sql_file}")
        return None, None, None
    
    print(f"📄 Parsing SQL file: {sql_file}")
    
    # This is a simplified parser - in production, use a proper SQL parser
    # For now, we'll use the hardcoded data from the SQL file
    print("⚠️  SQL file parsing not fully implemented. Using hardcoded data.")
    return None, None, None

def generate_training_dataset(symptoms, spec_map, grouped):
    """Generate training dataset from database symptoms"""
    dataset = []
    
    # Single symptom entries
    for sym in symptoms:
        spec_name = spec_map.get(sym['specialization_id'], 'General Physician')
        dataset.append((
            sym['symptom_name'],
            sym['AffectedArea'] or 'General',
            sym['AffectedDuration'] or 'Days',
            spec_name
        ))
    
    # Create combinations for each specialization (2-3 symptoms)
    for spec_name, sym_list in grouped.items():
        if len(sym_list) >= 2:
            # Create pairs
            for i in range(min(3, len(sym_list))):  # Limit to avoid too many combinations
                for j in range(i+1, min(i+3, len(sym_list))):
                    sym1 = sym_list[i]
                    sym2 = sym_list[j]
                    combined = f"{sym1['symptom_name']},{sym2['symptom_name']}"
                    area = sym1['AffectedArea'] or sym2['AffectedArea'] or 'General'
                    duration = sym1['AffectedDuration'] or sym2['AffectedDuration'] or 'Days'
                    dataset.append((combined, area, duration, spec_name))
    
    # Add some "Could not generate" cases for miscellaneous symptoms
    misc_cases = [
        ("fatigue,headache", "General", "Occasional", None),
        ("mild discomfort", "General", "Occasional", None),
        ("general weakness", "General", "Occasional", None),
    ]
    dataset.extend(misc_cases)
    
    print(f"✅ Generated {len(dataset)} training examples")
    return dataset

if __name__ == '__main__':
    print("=" * 60)
    print("Loading Training Data from Database")
    print("=" * 60)
    
    symptoms, spec_map, grouped = load_symptom_data()
    
    if symptoms:
        dataset = generate_training_dataset(symptoms, spec_map, grouped)
        
        # Save to a file that train_model.py can use
        import json
        with open('training_data.json', 'w', encoding='utf-8') as f:
            json.dump(dataset, f, indent=2, ensure_ascii=False)
        
        print(f"\n✅ Training data saved to training_data.json")
        print(f"   Total examples: {len(dataset)}")
        print(f"\nNext step: Update train_model.py to use this data")
    else:
        print("\n❌ Could not load data. Using hardcoded dataset in train_model.py")

