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

/* Auto-create tables (safe to keep on) - matching SQL schema exactly */
$con->query("CREATE TABLE IF NOT EXISTS `admins` (
  `admin_id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(160) NOT NULL,
  `full_name` varchar(120) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('super','editor') DEFAULT 'super',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`admin_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

$con->query("CREATE TABLE IF NOT EXISTS `location` (
  `location_id` int(11) NOT NULL AUTO_INCREMENT,
  `city` varchar(100) DEFAULT NULL,
  `area_name` varchar(250) DEFAULT NULL,
  PRIMARY KEY (`location_id`),
  UNIQUE KEY `unique_area_name` (`area_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

$con->query("CREATE TABLE IF NOT EXISTS `specialization` (
  `specialization_id` int(11) NOT NULL AUTO_INCREMENT,
  `specialization_name` varchar(120) NOT NULL,
  PRIMARY KEY (`specialization_id`),
  UNIQUE KEY `specialization_name` (`specialization_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

$con->query("CREATE TABLE IF NOT EXISTS `hospital` (
  `hospital_id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_name` varchar(160) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `location_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`hospital_id`),
  KEY `idx_hosp_location` (`location_id`),
  CONSTRAINT `fk_hosp_location` FOREIGN KEY (`location_id`) REFERENCES `location` (`location_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

$con->query("CREATE TABLE IF NOT EXISTS `doctor` (
  `doctor_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `designation` varchar(200) DEFAULT NULL,
  `website_url` varchar(255) DEFAULT NULL,
  `specialization_id` int(11) DEFAULT NULL,
  `hospital_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`doctor_id`),
  KEY `fk_doc_spec` (`specialization_id`),
  KEY `idx_doc_hosp` (`hospital_id`),
  CONSTRAINT `fk_doc_spec` FOREIGN KEY (`specialization_id`) REFERENCES `specialization` (`specialization_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_doc_hosp` FOREIGN KEY (`hospital_id`) REFERENCES `hospital` (`hospital_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

$con->query("CREATE TABLE IF NOT EXISTS `availability` (
  `availability_id` int(11) NOT NULL AUTO_INCREMENT,
  `day_of_week` varchar(16) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `doctor_id` int(11) NOT NULL,
  PRIMARY KEY (`availability_id`),
  KEY `fk_av_doc` (`doctor_id`),
  CONSTRAINT `fk_av_doc` FOREIGN KEY (`doctor_id`) REFERENCES `doctor` (`doctor_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

$con->query("CREATE TABLE IF NOT EXISTS `disease` (
  `disease_id` int(11) NOT NULL AUTO_INCREMENT,
  `disease_name` varchar(160) NOT NULL,
  PRIMARY KEY (`disease_id`),
  UNIQUE KEY `disease_name` (`disease_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

$con->query("CREATE TABLE IF NOT EXISTS `symptom` (
  `symptom_id` int(11) NOT NULL AUTO_INCREMENT,
  `symptom_name` varchar(160) NOT NULL,
  `AffectedArea` varchar(100) DEFAULT NULL,
  `AffectedDuration` varchar(50) DEFAULT NULL,
  `specialization_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`symptom_id`),
  UNIQUE KEY `symptom_name` (`symptom_name`),
  KEY `idx_symp_spec` (`specialization_id`),
  CONSTRAINT `fk_symptom_spec` FOREIGN KEY (`specialization_id`) REFERENCES `specialization` (`specialization_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
