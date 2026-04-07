CREATE TABLE IF NOT EXISTS accounts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  role VARCHAR(20) NOT NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(255) NULL UNIQUE,
  login_code VARCHAR(120) NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  phone VARCHAR(50),
  gender VARCHAR(25),
  specialization VARCHAR(150),
  experience_years INT DEFAULT 0,
  image_path VARCHAR(255),
  availability VARCHAR(255),
  qualification TEXT,
  qualification_status VARCHAR(20) DEFAULT 'pending',
  verified_by_account_id INT NULL,
  verified_at TIMESTAMP NULL,
  last_login_at TIMESTAMP NULL,
  active TINYINT(1) DEFAULT 1,
  legacy_source VARCHAR(20),
  legacy_id INT,
  UNIQUE KEY uniq_legacy_source_id (legacy_source, legacy_id),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS active TINYINT(1) DEFAULT 1,
  ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

CREATE TABLE IF NOT EXISTS user_profiles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  height_cm INT,
  weight_kg INT,
  goal VARCHAR(100),
  level VARCHAR(50),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS classes_admin (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(120) UNIQUE NOT NULL,
  category VARCHAR(100) NOT NULL,
  description TEXT NOT NULL,
  weekly_schedule VARCHAR(255),
  schedule_config LONGTEXT NULL,
  max_participants INT NOT NULL DEFAULT 20,
  trainer_account_id INT NULL,
  image_path VARCHAR(255) NULL,
  image_mime VARCHAR(100) NULL,
  image_data LONGBLOB NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  intensity_level ENUM('low','medium','high') NULL,
  fitness_level ENUM('beginner','intermediate','advanced') NULL,
  goal_fat_loss TINYINT(1) NOT NULL DEFAULT 0,
  goal_maintenance TINYINT(1) NOT NULL DEFAULT 0,
  goal_muscle_gain TINYINT(1) NOT NULL DEFAULT 0,
  goal_endurance TINYINT(1) NOT NULL DEFAULT 0,
  goal_mobility TINYINT(1) NOT NULL DEFAULT 0,
  goal_flexibility TINYINT(1) NOT NULL DEFAULT 0,
  goal_stress_relief TINYINT(1) NOT NULL DEFAULT 0,
  calories_burn_min INT NULL,
  calories_burn_max INT NULL,
  tdee_min INT NULL,
  tdee_max INT NULL,
  duration_minutes INT NULL,
  recommended_frequency_per_week INT NULL,
  low_impact TINYINT(1) NOT NULL DEFAULT 0,
  joint_friendly TINYINT(1) NOT NULL DEFAULT 0,
  requires_equipment TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (trainer_account_id) REFERENCES accounts(id) ON DELETE SET NULL,
  CONSTRAINT chk_classes_admin_capacity CHECK (max_participants > 0),
  CONSTRAINT chk_classes_admin_burn CHECK (
    (calories_burn_min IS NULL OR calories_burn_min >= 0)
    AND (calories_burn_max IS NULL OR calories_burn_max >= 0)
    AND (calories_burn_min IS NULL OR calories_burn_max IS NULL OR calories_burn_min <= calories_burn_max)
  ),
  CONSTRAINT chk_classes_admin_tdee CHECK (
    (tdee_min IS NULL OR tdee_min >= 0)
    AND (tdee_max IS NULL OR tdee_max >= 0)
    AND (tdee_min IS NULL OR tdee_max IS NULL OR tdee_min <= tdee_max)
  ),
  CONSTRAINT chk_classes_admin_duration CHECK (duration_minutes IS NULL OR duration_minutes > 0),
  CONSTRAINT chk_classes_admin_frequency CHECK (
    recommended_frequency_per_week IS NULL
    OR (recommended_frequency_per_week >= 1 AND recommended_frequency_per_week <= 7)
  ),
  CONSTRAINT chk_classes_admin_recommendable CHECK (
    is_active = 0
    OR (
      active = 1
      AND intensity_level IS NOT NULL
      AND fitness_level IS NOT NULL
      AND (goal_fat_loss + goal_maintenance + goal_muscle_gain + goal_endurance + goal_mobility + goal_flexibility + goal_stress_relief) > 0
      AND calories_burn_min IS NOT NULL
      AND calories_burn_max IS NOT NULL
      AND calories_burn_min <= calories_burn_max
      AND duration_minutes IS NOT NULL
      AND duration_minutes > 0
      AND recommended_frequency_per_week IS NOT NULL
      AND recommended_frequency_per_week BETWEEN 1 AND 7
    )
  )
);

ALTER TABLE classes_admin
  ADD COLUMN IF NOT EXISTS category VARCHAR(100) NULL AFTER name,
  ADD COLUMN IF NOT EXISTS description TEXT NULL AFTER category,
  ADD COLUMN IF NOT EXISTS schedule_config LONGTEXT NULL AFTER weekly_schedule,
  ADD COLUMN IF NOT EXISTS image_path VARCHAR(255) NULL AFTER trainer_account_id,
  ADD COLUMN IF NOT EXISTS image_mime VARCHAR(100) NULL AFTER image_path,
  ADD COLUMN IF NOT EXISTS image_data LONGBLOB NULL AFTER image_mime,
  ADD COLUMN IF NOT EXISTS active TINYINT(1) NOT NULL DEFAULT 1 AFTER image_data,
  ADD COLUMN IF NOT EXISTS intensity_level ENUM('low','medium','high') NULL AFTER description,
  ADD COLUMN IF NOT EXISTS fitness_level ENUM('beginner','intermediate','advanced') NULL AFTER intensity_level,
  ADD COLUMN IF NOT EXISTS goal_fat_loss TINYINT(1) NOT NULL DEFAULT 0 AFTER fitness_level,
  ADD COLUMN IF NOT EXISTS goal_maintenance TINYINT(1) NOT NULL DEFAULT 0 AFTER goal_fat_loss,
  ADD COLUMN IF NOT EXISTS goal_muscle_gain TINYINT(1) NOT NULL DEFAULT 0 AFTER goal_maintenance,
  ADD COLUMN IF NOT EXISTS goal_endurance TINYINT(1) NOT NULL DEFAULT 0 AFTER goal_muscle_gain,
  ADD COLUMN IF NOT EXISTS goal_mobility TINYINT(1) NOT NULL DEFAULT 0 AFTER goal_endurance,
  ADD COLUMN IF NOT EXISTS goal_flexibility TINYINT(1) NOT NULL DEFAULT 0 AFTER goal_mobility,
  ADD COLUMN IF NOT EXISTS goal_stress_relief TINYINT(1) NOT NULL DEFAULT 0 AFTER goal_flexibility,
  ADD COLUMN IF NOT EXISTS calories_burn_min INT NULL AFTER goal_stress_relief,
  ADD COLUMN IF NOT EXISTS calories_burn_max INT NULL AFTER calories_burn_min,
  ADD COLUMN IF NOT EXISTS tdee_min INT NULL AFTER calories_burn_max,
  ADD COLUMN IF NOT EXISTS tdee_max INT NULL AFTER tdee_min,
  ADD COLUMN IF NOT EXISTS duration_minutes INT NULL AFTER tdee_max,
  ADD COLUMN IF NOT EXISTS recommended_frequency_per_week INT NULL AFTER duration_minutes,
  ADD COLUMN IF NOT EXISTS low_impact TINYINT(1) NOT NULL DEFAULT 0 AFTER recommended_frequency_per_week,
  ADD COLUMN IF NOT EXISTS joint_friendly TINYINT(1) NOT NULL DEFAULT 0 AFTER low_impact,
  ADD COLUMN IF NOT EXISTS requires_equipment TINYINT(1) NOT NULL DEFAULT 0 AFTER joint_friendly,
  ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 0 AFTER requires_equipment;

ALTER TABLE bookings
  ADD COLUMN IF NOT EXISTS status VARCHAR(30) DEFAULT 'Pending';

CREATE TABLE IF NOT EXISTS recommendation_rules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  goal VARCHAR(100) NOT NULL,
  workout_type VARCHAR(150) NOT NULL,
  duration_weeks INT DEFAULT 6,
  days_per_week INT DEFAULT 4,
  daily_minutes INT DEFAULT 30,
  difficulty_map VARCHAR(255),
  active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tips (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  body TEXT NOT NULL,
  category VARCHAR(80),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS content_blocks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  block_key VARCHAR(80) UNIQUE NOT NULL,
  title VARCHAR(150),
  body TEXT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  message TEXT NOT NULL,
  audience VARCHAR(100) DEFAULT 'All Users',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_name VARCHAR(120) NOT NULL,
  rating INT DEFAULT 5,
  comment TEXT,
  status VARCHAR(30) DEFAULT 'Pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(100) UNIQUE NOT NULL,
  setting_value TEXT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
