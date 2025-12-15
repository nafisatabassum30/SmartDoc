<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/util.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$DB_HOST='localhost:3306'; $DB_USER='root'; $DB_PASS=''; $DB_NAME='smartdoc';

try {
  $con = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
  $con->set_charset('utf8mb4');
} catch (Exception $e){
  http_response_code(500);
  echo '<h1>DB Error</h1><pre>'.h($e->getMessage()).'</pre>';
  exit;
}

/* Ensure patient table exists - matching SQL schema exactly */
$con->query("CREATE TABLE IF NOT EXISTS `patient` (
  `patient_ID` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `age` int(11) DEFAULT NULL,
  `gender` varchar(20) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `patient_location` varchar(250) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`patient_ID`),
  UNIQUE KEY `uq_patient_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

/* Add password_hash column if it doesn't exist (for existing tables) */
$result = $con->query("SHOW COLUMNS FROM `patient` LIKE 'password_hash'");
if ($result->num_rows == 0) {
  $con->query("ALTER TABLE `patient` ADD COLUMN `password_hash` varchar(255) DEFAULT NULL");
}

/* Ensure pastconsultation table exists - matching SQL schema exactly */
$con->query("CREATE TABLE IF NOT EXISTS `pastconsultation` (
  `consultation_ID` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) DEFAULT NULL,
  `symptom_id` int(11) DEFAULT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `consultation_date` date DEFAULT NULL,
  `status` enum('pending','confirmed','completed','cancelled') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`consultation_ID`),
  KEY `idx_pc_patient` (`patient_id`),
  KEY `idx_pc_symptom` (`symptom_id`),
  KEY `idx_pc_doctor` (`doctor_id`),
  CONSTRAINT `fk_pc_patient` FOREIGN KEY (`patient_id`) REFERENCES `patient` (`patient_ID`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_pc_symptom` FOREIGN KEY (`symptom_id`) REFERENCES `symptom` (`symptom_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_pc_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctor` (`doctor_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

/* Add status column if it doesn't exist (for existing tables) */
$result = $con->query("SHOW COLUMNS FROM `pastconsultation` LIKE 'status'");
if ($result->num_rows == 0) {
  $con->query("ALTER TABLE `pastconsultation` ADD COLUMN `status` enum('pending','confirmed','completed','cancelled') DEFAULT 'pending'");
}

/* Add notes column if it doesn't exist */
$result = $con->query("SHOW COLUMNS FROM `pastconsultation` LIKE 'notes'");
if ($result->num_rows == 0) {
  $con->query("ALTER TABLE `pastconsultation` ADD COLUMN `notes` text DEFAULT NULL");
}

/* Add created_at column if it doesn't exist */
$result = $con->query("SHOW COLUMNS FROM `pastconsultation` LIKE 'created_at'");
if ($result->num_rows == 0) {
  $con->query("ALTER TABLE `pastconsultation` ADD COLUMN `created_at` timestamp NOT NULL DEFAULT current_timestamp()");
}

/* Ensure doctor_ratings table exists for storing individual patient ratings */
$con->query("CREATE TABLE IF NOT EXISTS `doctor_ratings` (
  `doctor_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `rating` decimal(3,1) NOT NULL CHECK (`rating` >= 0 AND `rating` <= 5),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`doctor_id`,`patient_id`),
  KEY `idx_dr_patient` (`patient_id`),
  CONSTRAINT `fk_dr_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctor` (`doctor_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_dr_patient` FOREIGN KEY (`patient_id`) REFERENCES `patient` (`patient_ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

// Ensure primary key exists (in case table was created without it)
$checkPK = $con->query("SHOW KEYS FROM `doctor_ratings` WHERE Key_name = 'PRIMARY'");
if ($checkPK && $checkPK->num_rows === 0) {
  // Check if there are duplicate rows before adding primary key
  $dupCheck = $con->query("SELECT `doctor_id`, `patient_id`, COUNT(*) as cnt FROM `doctor_ratings` GROUP BY `doctor_id`, `patient_id` HAVING cnt > 1");
  if ($dupCheck && $dupCheck->num_rows === 0) {
    // No duplicates, safe to add primary key
    @$con->query("ALTER TABLE `doctor_ratings` ADD PRIMARY KEY (`doctor_id`, `patient_id`)");
  }
}

/* Ensure rating_count column exists on doctor table to store number of votes */
$result = $con->query("SHOW COLUMNS FROM `doctor` LIKE 'rating_count'");
if ($result && $result->num_rows == 0) {
  $con->query("ALTER TABLE `doctor` ADD COLUMN `rating_count` int(11) NOT NULL DEFAULT 0");
}

/* Ensure symptom_check_history table exists for storing patient symptom checks */
$con->query("CREATE TABLE IF NOT EXISTS `symptom_check_history` (
  `check_id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) DEFAULT NULL,
  `symptoms` text DEFAULT NULL COMMENT 'Comma-separated symptom names',
  `affected_area` varchar(200) DEFAULT NULL,
  `intensity` varchar(50) DEFAULT NULL COMMENT 'Mild, Moderate, Severe',
  `recommended_specialist_id` int(11) DEFAULT NULL,
  `recommended_specialist_name` varchar(200) DEFAULT NULL,
  `selected_doctor_id` int(11) DEFAULT NULL,
  `selected_doctor_name` varchar(200) DEFAULT NULL,
  `selected_hospital_name` varchar(200) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`check_id`),
  KEY `idx_sch_patient` (`patient_id`),
  KEY `idx_sch_specialist` (`recommended_specialist_id`),
  KEY `idx_sch_doctor` (`selected_doctor_id`),
  CONSTRAINT `fk_sch_patient` FOREIGN KEY (`patient_id`) REFERENCES `patient` (`patient_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_sch_specialist` FOREIGN KEY (`recommended_specialist_id`) REFERENCES `specialization` (`specialization_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_sch_doctor` FOREIGN KEY (`selected_doctor_id`) REFERENCES `doctor` (`doctor_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

/* Add selected doctor columns if they don't exist */
$result = $con->query("SHOW COLUMNS FROM `symptom_check_history` LIKE 'selected_doctor_id'");
if ($result && $result->num_rows == 0) {
  $con->query("ALTER TABLE `symptom_check_history` ADD COLUMN `selected_doctor_id` int(11) DEFAULT NULL AFTER `recommended_specialist_name`");
  $con->query("ALTER TABLE `symptom_check_history` ADD COLUMN `selected_doctor_name` varchar(200) DEFAULT NULL AFTER `selected_doctor_id`");
  $con->query("ALTER TABLE `symptom_check_history` ADD COLUMN `selected_hospital_name` varchar(200) DEFAULT NULL AFTER `selected_doctor_name`");
  $con->query("ALTER TABLE `symptom_check_history` ADD KEY `idx_sch_doctor` (`selected_doctor_id`)");
  $con->query("ALTER TABLE `symptom_check_history` ADD CONSTRAINT `fk_sch_doctor` FOREIGN KEY (`selected_doctor_id`) REFERENCES `doctor` (`doctor_id`) ON DELETE SET NULL ON UPDATE CASCADE");
}

