-- Test Query: Find all doctors in Bashundhara hospitals
-- This will help verify if doctors exist in Bashundhara for different specializations

-- Hospitals in Bashundhara (from the database):
-- hospital_id 7: Evercare Hospital Dhaka (Plot 81, Block E, Bashundhara R/A)
-- hospital_id 17: Evercare Hospital Dhaka (Plot 81, Block E, Bashundhara R/A)
-- hospital_id 30: Evercare Hospital Dhaka (Plot 81, Block E, Bashundhara R/A)
-- hospital_id 86: Bashundhara Eye Hospital & Research Institute (Ka-9/1, Bashundhara Road, Bashundhara R/A)

-- Query 1: Find all doctors in Bashundhara hospitals with their specializations
SELECT 
    d.doctor_id,
    d.name AS doctor_name,
    d.designation,
    s.specialization_id,
    s.specialization_name,
    h.hospital_id,
    h.hospital_name,
    h.address AS hospital_address
FROM doctor d
LEFT JOIN specialization s ON d.specialization_id = s.specialization_id
LEFT JOIN hospital h ON d.hospital_id = h.hospital_id
WHERE h.hospital_id IN (7, 17, 30, 86)
ORDER BY s.specialization_name, d.name;

-- Query 2: Count doctors by specialization in Bashundhara hospitals
SELECT 
    s.specialization_name,
    COUNT(d.doctor_id) AS doctor_count,
    GROUP_CONCAT(d.name SEPARATOR ', ') AS doctors
FROM doctor d
LEFT JOIN specialization s ON d.specialization_id = s.specialization_id
LEFT JOIN hospital h ON d.hospital_id = h.hospital_id
WHERE h.hospital_id IN (7, 17, 30, 86)
GROUP BY s.specialization_id, s.specialization_name
ORDER BY doctor_count DESC;

-- Query 3: Test address matching - Find hospitals with "Bashundhara" in address
SELECT 
    hospital_id,
    hospital_name,
    address,
    CASE 
        WHEN LOWER(address) LIKE '%bashundhara%' THEN 'MATCH'
        ELSE 'NO MATCH'
    END AS match_status
FROM hospital
WHERE LOWER(address) LIKE '%bashundhara%'
ORDER BY hospital_id;

-- Query 4: Find Gastroenterologists specifically in Bashundhara
SELECT 
    d.doctor_id,
    d.name AS doctor_name,
    d.designation,
    h.hospital_name,
    h.address AS hospital_address
FROM doctor d
LEFT JOIN specialization s ON d.specialization_id = s.specialization_id
LEFT JOIN hospital h ON d.hospital_id = h.hospital_id
WHERE s.specialization_name = 'Gastroenterologist'
  AND h.hospital_id IN (7, 17, 30, 86)
ORDER BY d.name;

-- Query 5: Find Ophthalmologists specifically in Bashundhara
SELECT 
    d.doctor_id,
    d.name AS doctor_name,
    d.designation,
    h.hospital_name,
    h.address AS hospital_address
FROM doctor d
LEFT JOIN specialization s ON d.specialization_id = s.specialization_id
LEFT JOIN hospital h ON d.hospital_id = h.hospital_id
WHERE s.specialization_name = 'Ophthalmologist'
  AND h.hospital_id IN (7, 17, 30, 86)
ORDER BY d.name;

