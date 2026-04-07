-- FitGym TDEE recommendation refactor
-- Run this once against the existing FitGym database.

START TRANSACTION;

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

UPDATE classes_admin
SET
  category = CASE
    WHEN LOWER(TRIM(COALESCE(category, ''))) <> '' THEN LOWER(TRIM(category))
    WHEN LOWER(name) LIKE '%yoga%' THEN 'yoga'
    WHEN LOWER(name) LIKE '%pilates%' THEN 'pilates'
    WHEN LOWER(name) LIKE '%zumba%' OR LOWER(name) LIKE '%jumba%' OR LOWER(name) LIKE '%dance%' THEN 'dance'
    WHEN LOWER(name) LIKE '%box%' OR LOWER(name) LIKE '%cardio%' OR LOWER(name) LIKE '%swim%' THEN 'cardio'
    WHEN LOWER(name) LIKE '%lift%' OR LOWER(name) LIKE '%strength%' OR LOWER(name) LIKE '%power%' THEN 'strength'
    WHEN LOWER(name) LIKE '%recovery%' OR LOWER(name) LIKE '%mobility%' THEN 'recovery'
    ELSE 'mixed'
  END,
  description = COALESCE(NULLIF(description, ''), CONCAT('Join our ', name, ' program with guided coaching and a repeatable weekly structure.')),
  intensity_level = CASE
    WHEN LOWER(TRIM(COALESCE(intensity_level, ''))) IN ('low', 'medium', 'high') THEN LOWER(TRIM(intensity_level))
    ELSE NULL
  END,
  fitness_level = CASE
    WHEN LOWER(TRIM(COALESCE(fitness_level, ''))) IN ('beginner', 'intermediate', 'advanced') THEN LOWER(TRIM(fitness_level))
    WHEN LOWER(TRIM(COALESCE(level, ''))) IN ('beginner', 'intermediate', 'advanced') THEN LOWER(TRIM(level))
    ELSE NULL
  END,
  calories_burn_min = COALESCE(NULLIF(calories_burn_min, 0), NULLIF(LEAST(COALESCE(kcal_min, 0), COALESCE(kcal_max, 0)), 0)),
  calories_burn_max = COALESCE(NULLIF(calories_burn_max, 0), NULLIF(GREATEST(COALESCE(kcal_min, 0), COALESCE(kcal_max, 0)), 0)),
  duration_minutes = COALESCE(NULLIF(duration_minutes, 0), NULLIF(duration_min, 0)),
  tdee_min = NULLIF(tdee_min, 0),
  tdee_max = NULLIF(tdee_max, 0),
  active = COALESCE(active, 1),
  is_active = COALESCE(is_active, 0);

UPDATE classes_admin
SET
  name = 'Yoga',
  slug = 'yoga',
  category = 'yoga',
  description = 'Low-impact yoga sessions focused on mobility, breathing control, posture, and steady beginner-friendly conditioning.',
  intensity_level = 'low',
  fitness_level = 'beginner',
  goal_fat_loss = 1,
  goal_maintenance = 1,
  goal_muscle_gain = 0,
  calories_burn_min = 120,
  calories_burn_max = 220,
  tdee_min = NULL,
  tdee_max = NULL,
  duration_minutes = 60,
  recommended_frequency_per_week = 3,
  low_impact = 1,
  joint_friendly = 1,
  requires_equipment = 0,
  active = 1,
  is_active = 1
WHERE id = 2;

UPDATE classes_admin
SET
  name = 'Zumba',
  slug = 'zumba',
  category = 'dance',
  description = 'Rhythm-driven cardio dance with easy-to-follow movement patterns for calorie burn and general conditioning.',
  intensity_level = 'medium',
  fitness_level = 'beginner',
  goal_fat_loss = 1,
  goal_maintenance = 1,
  goal_muscle_gain = 0,
  calories_burn_min = 280,
  calories_burn_max = 420,
  tdee_min = NULL,
  tdee_max = NULL,
  duration_minutes = 50,
  recommended_frequency_per_week = 3,
  low_impact = 0,
  joint_friendly = 0,
  requires_equipment = 0,
  active = 1,
  is_active = 1
WHERE id = 4;

UPDATE classes_admin
SET
  name = 'Dance Cardio',
  slug = 'dance',
  category = 'dance',
  description = 'Upbeat cardio dance focused on coordination, stamina, and moderate calorie burn for general fitness.',
  intensity_level = 'medium',
  fitness_level = 'beginner',
  goal_fat_loss = 1,
  goal_maintenance = 1,
  goal_muscle_gain = 0,
  calories_burn_min = 220,
  calories_burn_max = 360,
  tdee_min = NULL,
  tdee_max = NULL,
  duration_minutes = 45,
  recommended_frequency_per_week = 3,
  low_impact = 0,
  joint_friendly = 0,
  requires_equipment = 0,
  active = 1,
  is_active = 1
WHERE id = 5;

UPDATE classes_admin
SET
  name = 'Swimming Conditioning',
  slug = 'swimming-conditioning',
  category = 'cardio',
  description = 'Low-impact pool conditioning sessions that improve stamina while staying joint-friendly.',
  intensity_level = 'medium',
  fitness_level = 'beginner',
  goal_fat_loss = 1,
  goal_maintenance = 1,
  goal_muscle_gain = 0,
  calories_burn_min = 260,
  calories_burn_max = 420,
  tdee_min = NULL,
  tdee_max = NULL,
  duration_minutes = 45,
  recommended_frequency_per_week = 2,
  low_impact = 1,
  joint_friendly = 1,
  requires_equipment = 1,
  active = 1,
  is_active = 1
WHERE id = 6;

UPDATE classes_admin
SET
  name = 'Cardio Circuit',
  slug = 'cardio-circuit',
  category = 'cardio',
  description = 'Structured cardio intervals combining bodyweight stations and short bursts of continuous movement.',
  intensity_level = 'medium',
  fitness_level = 'beginner',
  goal_fat_loss = 1,
  goal_maintenance = 1,
  goal_muscle_gain = 0,
  calories_burn_min = 300,
  calories_burn_max = 450,
  tdee_min = NULL,
  tdee_max = NULL,
  duration_minutes = 45,
  recommended_frequency_per_week = 4,
  low_impact = 0,
  joint_friendly = 0,
  requires_equipment = 0,
  active = 1,
  is_active = 1
WHERE id = 7;

UPDATE classes_admin
SET
  description = 'Gentle flow yoga focused on mobility, breathing rhythm, and consistent recovery-friendly movement.',
  tdee_min = NULL,
  tdee_max = NULL
WHERE id = 10;

UPDATE classes_admin
SET
  description = 'High-intensity interval training for calorie burn, conditioning, and short explosive work blocks.',
  calories_burn_min = 420,
  calories_burn_max = 620,
  tdee_min = NULL,
  tdee_max = NULL
WHERE id = 11;

UPDATE classes_admin
SET
  description = 'Strength-focused class using compound movements and resistance work for muscle-building progress.',
  calories_burn_min = 240,
  calories_burn_max = 360,
  goal_maintenance = 1,
  tdee_min = NULL,
  tdee_max = NULL
WHERE id = 12;

UPDATE classes_admin
SET
  description = 'Beginner strength foundations class built around controlled lifting technique and progressive overload.',
  tdee_min = NULL,
  tdee_max = NULL
WHERE id = 14;

UPDATE classes_admin
SET
  description = 'Mobility and recovery class for flexibility, posture, and lighter maintenance-focused sessions.',
  tdee_min = NULL,
  tdee_max = NULL
WHERE id = 15;

UPDATE classes_admin
SET
  description = 'Boxing-based cardio intervals for stamina, footwork, and high calorie expenditure.',
  tdee_min = NULL,
  tdee_max = NULL
WHERE id = 16;

UPDATE classes_admin
SET
  description = 'Dance-driven cardio combining simple choreographed patterns with sustained heart-rate work.',
  tdee_min = NULL,
  tdee_max = NULL
WHERE id = 17;

UPDATE bookings
SET class_slug = 'zumba', class_name = 'Zumba'
WHERE LOWER(class_slug) IN ('jumba', 'zumba') OR LOWER(class_name) IN ('jumba', 'zumba');

UPDATE bookings
SET class_slug = 'dance', class_name = 'Dance Cardio'
WHERE LOWER(class_slug) = 'dance';

UPDATE classes_admin
SET
  goal_endurance = 0,
  goal_mobility = 0,
  goal_flexibility = 0,
  goal_stress_relief = 0;

UPDATE classes_admin
SET goal_endurance = 1
WHERE LOWER(COALESCE(slug, '')) IN (
    'swimming-conditioning',
    'aqua-cardio',
    'zumba',
    'dance',
    'dance-cardio',
    'cardio-circuit',
    'boxing-cardio',
    'hiit-burn',
    'cycling-intervals',
    'athletic-bootcamp',
    'kettlebell-conditioning'
)
   OR LOWER(COALESCE(name, '')) REGEXP 'swim|aqua|zumba|dance|cardio|boxing|hiit|cycling|bootcamp';

UPDATE classes_admin
SET
  goal_mobility = 1,
  goal_flexibility = 1
WHERE LOWER(COALESCE(slug, '')) IN (
    'yoga',
    'yoga-foundations',
    'chair-yoga',
    'chair-yoga-mobility',
    'recovery-mobility',
    'beginner-pilates',
    'pilates-core'
)
   OR LOWER(COALESCE(name, '')) REGEXP 'yoga|mobility|pilates|flex';

UPDATE classes_admin
SET goal_stress_relief = 1
WHERE LOWER(COALESCE(slug, '')) IN (
    'yoga',
    'yoga-foundations',
    'chair-yoga',
    'chair-yoga-mobility',
    'recovery-mobility'
)
   OR LOWER(COALESCE(name, '')) REGEXP 'yoga|recovery|mobility';

UPDATE classes_admin
SET is_active = 0
WHERE NOT (
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
);

ALTER TABLE classes_admin DROP FOREIGN KEY classes_admin_ibfk_1;
ALTER TABLE classes_admin
  DROP COLUMN trainer_id,
  DROP COLUMN duration_min,
  DROP COLUMN level,
  DROP COLUMN kcal_min,
  DROP COLUMN kcal_max;

ALTER TABLE classes_admin
  MODIFY COLUMN category VARCHAR(100) NOT NULL,
  MODIFY COLUMN description TEXT NOT NULL,
  MODIFY COLUMN active TINYINT(1) NOT NULL DEFAULT 1,
  MODIFY COLUMN is_active TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE classes_admin
  ADD CONSTRAINT fk_classes_admin_trainer_account
    FOREIGN KEY (trainer_account_id) REFERENCES accounts(id) ON DELETE SET NULL,
  ADD CONSTRAINT chk_classes_admin_capacity
    CHECK (max_participants > 0),
  ADD CONSTRAINT chk_classes_admin_burn
    CHECK (
      (calories_burn_min IS NULL OR calories_burn_min >= 0)
      AND (calories_burn_max IS NULL OR calories_burn_max >= 0)
      AND (calories_burn_min IS NULL OR calories_burn_max IS NULL OR calories_burn_min <= calories_burn_max)
    ),
  ADD CONSTRAINT chk_classes_admin_tdee
    CHECK (
      (tdee_min IS NULL OR tdee_min >= 0)
      AND (tdee_max IS NULL OR tdee_max >= 0)
      AND (tdee_min IS NULL OR tdee_max IS NULL OR tdee_min <= tdee_max)
    ),
  ADD CONSTRAINT chk_classes_admin_duration
    CHECK (duration_minutes IS NULL OR duration_minutes > 0),
  ADD CONSTRAINT chk_classes_admin_frequency
    CHECK (
      recommended_frequency_per_week IS NULL
      OR (recommended_frequency_per_week >= 1 AND recommended_frequency_per_week <= 7)
    ),
  ADD CONSTRAINT chk_classes_admin_recommendable
    CHECK (
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
    );

COMMIT;
