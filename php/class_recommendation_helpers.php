<?php

if (!function_exists('fitgym_normalize_text')) {
    function fitgym_normalize_text(?string $value): string
    {
        return strtolower(trim((string)$value));
    }
}

if (!function_exists('fitgym_normalize_intensity')) {
    function fitgym_normalize_intensity(?string $value): string
    {
        $value = fitgym_normalize_text($value);
        if ($value === 'moderate') {
            return 'medium';
        }

        return in_array($value, ['low', 'medium', 'high'], true) ? $value : '';
    }
}

if (!function_exists('fitgym_normalize_fitness_level')) {
    function fitgym_normalize_fitness_level(?string $value): string
    {
        $value = fitgym_normalize_text($value);
        return in_array($value, ['beginner', 'intermediate', 'advanced'], true) ? $value : '';
    }
}

if (!function_exists('fitgym_level_rank')) {
    function fitgym_level_rank(?string $value): int
    {
        $value = fitgym_normalize_fitness_level($value);
        if ($value === 'beginner') {
            return 1;
        }
        if ($value === 'intermediate') {
            return 2;
        }
        if ($value === 'advanced') {
            return 3;
        }

        return 0;
    }
}

if (!function_exists('fitgym_labelize_token')) {
    function fitgym_labelize_token(string $value): string
    {
        return ucwords(str_replace(['_', '-'], ' ', trim($value)));
    }
}

if (!function_exists('fitgym_nullable_int')) {
    function fitgym_nullable_int($value, int $min = 0): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            return null;
        }

        $value = (int)$value;
        return $value >= $min ? $value : null;
    }
}

if (!function_exists('fitgym_goal_options')) {
    function fitgym_goal_options(): array
    {
        return [
            'fat_loss' => 'Fat Loss',
            'maintenance' => 'Maintenance',
            'muscle_gain' => 'Muscle Gain',
            'endurance' => 'Endurance',
            'mobility' => 'Mobility',
            'flexibility' => 'Flexibility',
            'stress_relief' => 'Stress Relief',
        ];
    }
}

if (!function_exists('fitgym_goal_flag_columns')) {
    function fitgym_goal_flag_columns(): array
    {
        return [
            'fat_loss' => 'goal_fat_loss',
            'maintenance' => 'goal_maintenance',
            'muscle_gain' => 'goal_muscle_gain',
            'endurance' => 'goal_endurance',
            'mobility' => 'goal_mobility',
            'flexibility' => 'goal_flexibility',
            'stress_relief' => 'goal_stress_relief',
        ];
    }
}

if (!function_exists('fitgym_activity_multipliers')) {
    function fitgym_activity_multipliers(): array
    {
        return [
            'sedentary' => 1.2,
            'light' => 1.375,
            'moderate' => 1.55,
            'active' => 1.725,
            'very_active' => 1.9,
        ];
    }
}

if (!function_exists('fitgym_calculate_bmr')) {
    function fitgym_calculate_bmr(string $gender, float $weightKg, float $heightCm, int $age): float
    {
        if ($gender === 'male') {
            return (10 * $weightKg) + (6.25 * $heightCm) - (5 * $age) + 5;
        }

        return (10 * $weightKg) + (6.25 * $heightCm) - (5 * $age) - 161;
    }
}

if (!function_exists('fitgym_goal_calorie_adjustment')) {
    function fitgym_goal_calorie_adjustment(string $goal): float
    {
        return match (fitgym_normalize_text($goal)) {
            'fat_loss' => 0.20,
            'muscle_gain' => -0.12,
            default => 0.0,
        };
    }
}

if (!function_exists('fitgym_class_has_any_goal_flags')) {
    function fitgym_class_has_any_goal_flags(array $classRow): bool
    {
        foreach (fitgym_goal_flag_columns() as $column) {
            if ((int)($classRow[$column] ?? 0) === 1) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('fitgym_matches_goal')) {
    function fitgym_matches_goal(array $classRow, string $goal): bool
    {
        $goal = fitgym_normalize_text($goal);
        $columns = fitgym_goal_flag_columns();
        if (!isset($columns[$goal])) {
            return false;
        }

        return (int)($classRow[$columns[$goal]] ?? 0) === 1;
    }
}

if (!function_exists('fitgym_class_burn_range')) {
    function fitgym_class_burn_range(array $classRow): array
    {
        return [
            fitgym_nullable_int($classRow['calories_burn_min'] ?? null, 0),
            fitgym_nullable_int($classRow['calories_burn_max'] ?? null, 0),
        ];
    }
}

if (!function_exists('fitgym_class_duration_minutes')) {
    function fitgym_class_duration_minutes(array $classRow): ?int
    {
        $duration = fitgym_nullable_int($classRow['duration_minutes'] ?? null, 1);
        if ($duration !== null) {
            return $duration;
        }

        return fitgym_nullable_int($classRow['duration_min'] ?? null, 1);
    }
}

if (!function_exists('fitgym_recommendation_profile_errors')) {
    function fitgym_recommendation_profile_errors(array $classRow): array
    {
        $errors = [];
        [$burnMin, $burnMax] = fitgym_class_burn_range($classRow);
        $tdeeMin = fitgym_nullable_int($classRow['tdee_min'] ?? null, 0);
        $tdeeMax = fitgym_nullable_int($classRow['tdee_max'] ?? null, 0);
        $durationMinutes = fitgym_class_duration_minutes($classRow);
        $recommendedFrequency = fitgym_nullable_int($classRow['recommended_frequency_per_week'] ?? null, 1);

        if (fitgym_normalize_intensity((string)($classRow['intensity_level'] ?? '')) === '') {
            $errors[] = 'Intensity level must be low, medium, or high.';
        }
        if (fitgym_normalize_fitness_level((string)($classRow['fitness_level'] ?? ($classRow['level'] ?? ''))) === '') {
            $errors[] = 'Fitness level must be beginner, intermediate, or advanced.';
        }
        if (!fitgym_class_has_any_goal_flags($classRow)) {
            $errors[] = 'At least one goal suitability flag must be enabled.';
        }
        if ($burnMin === null || $burnMax === null) {
            $errors[] = 'Calories burn min and max are required.';
        } elseif ($burnMin > $burnMax) {
            $errors[] = 'Calories burn min cannot be greater than max.';
        }
        if ($durationMinutes === null || $durationMinutes <= 0) {
            $errors[] = 'Session duration must be greater than zero.';
        }
        if ($recommendedFrequency === null || $recommendedFrequency <= 0) {
            $errors[] = 'Recommended weekly frequency must be greater than zero.';
        }
        if ($recommendedFrequency !== null && $recommendedFrequency > 7) {
            $errors[] = 'Recommended weekly frequency cannot exceed seven.';
        }
        if ($tdeeMin !== null && $tdeeMax !== null && $tdeeMin > $tdeeMax) {
            $errors[] = 'TDEE min cannot be greater than max.';
        }

        return $errors;
    }
}

if (!function_exists('fitgym_class_is_recommendation_ready')) {
    function fitgym_class_is_recommendation_ready(array $classRow): bool
    {
        if ((int)($classRow['active'] ?? 1) !== 1) {
            return false;
        }
        if ((int)($classRow['is_active'] ?? 0) !== 1) {
            return false;
        }

        return fitgym_recommendation_profile_errors($classRow) === [];
    }
}

if (!function_exists('fitgym_normalize_class_row')) {
    function fitgym_class_price_rupees(array $classRow, int $fallback = 2000): int
    {
        $rawPrice = $classRow['price'] ?? null;

        if (is_int($rawPrice) || is_float($rawPrice)) {
            $numeric = (int)$rawPrice;
            return $numeric > 0 ? $numeric : $fallback;
        }

        $text = trim((string)$rawPrice);
        if ($text !== '' && preg_match('/(\d+(?:\.\d+)?)/', str_replace(',', '', $text), $matches)) {
            $numeric = (int)round((float)$matches[1]);
            if ($numeric > 0) {
                return $numeric;
            }
        }

        return $fallback;
    }
}

if (!function_exists('fitgym_class_price_label')) {
    function fitgym_class_price_label(array $classRow, int $fallback = 2000): string
    {
        return 'NPR ' . number_format(fitgym_class_price_rupees($classRow, $fallback));
    }
}

if (!function_exists('fitgym_normalize_class_row')) {
    function fitgym_normalize_class_row(array $classRow): array
    {
        $normalized = $classRow;
        $name = trim((string)($classRow['name'] ?? $classRow['title'] ?? $classRow['class_name'] ?? 'Class'));
        $category = fitgym_normalize_text((string)($classRow['category'] ?? ''));
        $fitnessLevel = fitgym_normalize_fitness_level((string)($classRow['fitness_level'] ?? ($classRow['level'] ?? '')));
        $intensityLevel = fitgym_normalize_intensity((string)($classRow['intensity_level'] ?? ''));
        $durationMinutes = fitgym_class_duration_minutes($classRow);
        $burnMin = fitgym_nullable_int(
            $classRow['calories_burn_min'] ?? ($classRow['kcal_min'] ?? null),
            0
        );
        $burnMax = fitgym_nullable_int(
            $classRow['calories_burn_max'] ?? ($classRow['kcal_max'] ?? null),
            0
        );

        $normalized['id'] = (int)($classRow['id'] ?? 0);
        $normalized['name'] = $name;
        $normalized['title'] = $name;
        $normalized['class_name'] = $name;
        $normalized['slug'] = trim((string)($classRow['slug'] ?? ''));
        $normalized['trainer_id'] = (int)($classRow['trainer_account_id'] ?? $classRow['trainer_id'] ?? 0);
        $normalized['price'] = (string)fitgym_class_price_rupees($classRow);
        $normalized['price_formatted'] = fitgym_class_price_label($classRow);
        $normalized['category'] = $category;
        $normalized['category_label'] = $category !== '' ? fitgym_labelize_token($category) : 'General';
        $normalized['description'] = trim((string)($classRow['description'] ?? ''));
        $normalized['active'] = (int)($classRow['active'] ?? 1);
        $normalized['is_active'] = (int)($classRow['is_active'] ?? 0);
        $normalized['intensity_level'] = $intensityLevel;
        $normalized['fitness_level'] = $fitnessLevel;
        $normalized['fitness_level_label'] = $fitnessLevel !== '' ? fitgym_labelize_token($fitnessLevel) : 'Unspecified';
        $goalLabels = [];
        foreach (fitgym_goal_flag_columns() as $goalKey => $column) {
            $goalEnabled = (int)($classRow[$column] ?? 0);
            $normalized[$column] = $goalEnabled;
            if ($goalEnabled === 1) {
                $goalLabels[] = fitgym_goal_options()[$goalKey] ?? fitgym_labelize_token($goalKey);
            }
        }

        $normalized['goal_labels'] = $goalLabels;
        $normalized['calories_burn_min'] = $burnMin;
        $normalized['calories_burn_max'] = $burnMax;
        $normalized['tdee_min'] = fitgym_nullable_int($classRow['tdee_min'] ?? null, 0);
        $normalized['tdee_max'] = fitgym_nullable_int($classRow['tdee_max'] ?? null, 0);
        $normalized['duration_minutes'] = $durationMinutes;
        $normalized['recommended_frequency_per_week'] = fitgym_nullable_int($classRow['recommended_frequency_per_week'] ?? null, 1);
        $normalized['low_impact'] = (int)($classRow['low_impact'] ?? 0);
        $normalized['joint_friendly'] = (int)($classRow['joint_friendly'] ?? 0);
        $normalized['requires_equipment'] = (int)($classRow['requires_equipment'] ?? 0);
        $normalized['recommendation_ready'] = fitgym_class_is_recommendation_ready($normalized);
        $normalized['recommendation_issues'] = fitgym_recommendation_profile_errors($normalized);

        return $normalized;
    }
}

if (!function_exists('fitgym_matches_fitness_level')) {
    function fitgym_matches_fitness_level(array $classRow, array $context): bool
    {
        $classRank = fitgym_level_rank((string)($classRow['fitness_level'] ?? ''));
        $userRank = fitgym_level_rank((string)($context['fitness_level'] ?? ''));

        return $classRank > 0 && $userRank > 0 && $classRank <= $userRank;
    }
}

if (!function_exists('fitgym_matches_safety_requirements')) {
    function fitgym_matches_safety_requirements(array $classRow, array $context): bool
    {
        if (!empty($context['needs_low_impact']) && (int)($classRow['low_impact'] ?? 0) !== 1) {
            return false;
        }
        if (!empty($context['joint_friendly_required']) && (int)($classRow['joint_friendly'] ?? 0) !== 1) {
            return false;
        }

        return true;
    }
}

if (!function_exists('fitgym_matches_tdee_band')) {
    function fitgym_matches_tdee_band(array $classRow, array $context): bool
    {
        $userTdee = fitgym_nullable_int($context['tdee'] ?? null, 0);
        $tdeeMin = fitgym_nullable_int($classRow['tdee_min'] ?? null, 0);
        $tdeeMax = fitgym_nullable_int($classRow['tdee_max'] ?? null, 0);

        if ($userTdee === null) {
            return true;
        }
        if ($tdeeMin !== null && $userTdee < $tdeeMin) {
            return false;
        }
        if ($tdeeMax !== null && $userTdee > $tdeeMax) {
            return false;
        }

        return true;
    }
}

if (!function_exists('fitgym_target_intensity_from_context')) {
    function fitgym_target_intensity_from_context(array $context): string
    {
        if (!empty($context['needs_low_impact']) || !empty($context['joint_friendly_required'])) {
            return 'low';
        }

        $goal = fitgym_normalize_text((string)($context['goal'] ?? 'maintenance'));
        $userRank = fitgym_level_rank((string)($context['fitness_level'] ?? 'beginner'));
        $activity = fitgym_normalize_text((string)($context['activity_level'] ?? 'moderate'));
        $targetBurn = max(0, (int)($context['target_burn_per_session'] ?? 0));

        return match ($goal) {
            'fat_loss' => ($userRank >= 2 && $targetBurn >= 350 && in_array($activity, ['moderate', 'active', 'very_active'], true))
                ? 'high'
                : 'medium',
            'muscle_gain' => $userRank === 1
                ? 'medium'
                : ($targetBurn >= 280 ? 'high' : 'medium'),
            'endurance' => ($userRank >= 2 && $targetBurn >= 320 && in_array($activity, ['moderate', 'active', 'very_active'], true))
                ? 'high'
                : 'medium',
            'mobility', 'flexibility', 'stress_relief' => 'low',
            default => $targetBurn <= 180
                ? 'low'
                : ($userRank >= 3 && $targetBurn >= 340 ? 'high' : 'medium'),
        };
    }
}

if (!function_exists('fitgym_target_burn_per_session')) {
    function fitgym_target_burn_per_session(array $context): int
    {
        $goal = fitgym_normalize_text((string)($context['goal'] ?? 'maintenance'));
        $trainingDays = max(1, (int)($context['weekly_training_days'] ?? 1));
        $userRank = fitgym_level_rank((string)($context['fitness_level'] ?? 'beginner'));
        $activityLevel = fitgym_normalize_text((string)($context['activity_level'] ?? 'moderate'));
        $dailyGap = max(0, (int)($context['daily_calorie_gap'] ?? 0));

        if ($goal === 'fat_loss') {
            $weeklyTargetBurn = $dailyGap * 7;
            return (int)round(max($weeklyTargetBurn / $trainingDays, 120));
        }

        if ($goal === 'muscle_gain') {
            $base = $userRank >= 3 ? 260 : ($userRank === 2 ? 220 : 180);
            if (in_array($activityLevel, ['active', 'very_active'], true)) {
                $base += 20;
            }
            return $base;
        }

        if ($goal === 'endurance') {
            $base = $userRank >= 3 ? 320 : ($userRank === 2 ? 270 : 220);
            if ($activityLevel === 'active') {
                $base += 20;
            } elseif ($activityLevel === 'very_active') {
                $base += 35;
            }
            if (!empty($context['needs_low_impact']) || !empty($context['joint_friendly_required'])) {
                $base -= 40;
            }
            if (!empty($context['duration_preference']) && (int)$context['duration_preference'] >= 55) {
                $base += 20;
            }

            return max(160, $base);
        }

        if ($goal === 'mobility') {
            $base = $userRank >= 3 ? 150 : ($userRank === 2 ? 130 : 110);
            if (!empty($context['duration_preference']) && (int)$context['duration_preference'] >= 55) {
                $base += 10;
            }

            return max(90, $base);
        }

        if ($goal === 'flexibility') {
            $base = $userRank >= 3 ? 170 : ($userRank === 2 ? 145 : 120);
            if (!empty($context['duration_preference']) && (int)$context['duration_preference'] >= 55) {
                $base += 10;
            }

            return max(100, $base);
        }

        if ($goal === 'stress_relief') {
            $base = $userRank >= 3 ? 150 : ($userRank === 2 ? 125 : 100);
            if (in_array($activityLevel, ['active', 'very_active'], true)) {
                $base += 10;
            }

            return max(90, $base);
        }

        $base = $userRank >= 3 ? 220 : ($userRank === 2 ? 180 : 140);
        if (in_array($activityLevel, ['active', 'very_active'], true)) {
            $base += 20;
        }
        if (!empty($context['needs_low_impact']) || !empty($context['joint_friendly_required'])) {
            $base = max(100, $base - 30);
        }

        return $base;
    }
}

if (!function_exists('fitgym_calculate_tdee_context')) {
    function fitgym_calculate_tdee_context(array $input, ?array $activityMultipliers = null): array
    {
        $activityMultipliers = $activityMultipliers ?? fitgym_activity_multipliers();
        $goalOptions = fitgym_goal_options();
        $errors = [];

        $age = fitgym_nullable_int($input['age'] ?? null, 1);
        $heightCm = isset($input['height_cm']) ? (float)$input['height_cm'] : 0.0;
        $weightKg = isset($input['weight_kg']) ? (float)$input['weight_kg'] : 0.0;
        $goal = fitgym_normalize_text((string)($input['goal'] ?? 'maintenance'));
        $fitnessLevel = fitgym_normalize_fitness_level((string)($input['fitness_level'] ?? ($input['experience'] ?? 'beginner')));
        $activityLevel = fitgym_normalize_text((string)($input['activity'] ?? 'moderate'));
        $trainingDays = fitgym_nullable_int($input['training_days_per_week'] ?? ($input['sessions_per_week'] ?? null), 1);
        $durationPreference = fitgym_nullable_int($input['duration_preference'] ?? null, 1);
        $jointPain = fitgym_normalize_text((string)($input['joint_pain'] ?? 'no')) === 'yes';
        $gender = fitgym_normalize_text((string)($input['gender'] ?? 'female'));

        if ($age === null || $age < 14 || $age > 90) {
            $errors[] = 'Age must be between 14 and 90.';
        }
        if ($heightCm < 100 || $heightCm > 250) {
            $errors[] = 'Height must be between 100 and 250 cm.';
        }
        if ($weightKg < 35 || $weightKg > 200) {
            $errors[] = 'Weight must be between 35 and 200 kg.';
        }
        if (!isset($goalOptions[$goal])) {
            $errors[] = 'Select a valid goal.';
        }
        if (!isset($activityMultipliers[$activityLevel])) {
            $errors[] = 'Select a valid activity level.';
        }
        if ($fitnessLevel === '') {
            $errors[] = 'Select a valid fitness level.';
        }
        if ($trainingDays === null || $trainingDays < 1 || $trainingDays > 7) {
            $errors[] = 'Training days per week must be between 1 and 7.';
        }
        if (!in_array($gender, ['male', 'female'], true)) {
            $errors[] = 'Select a valid gender.';
        }

        $context = [
            'goal' => $goal,
            'goal_label' => $goalOptions[$goal] ?? 'Maintenance',
            'fitness_level' => $fitnessLevel !== '' ? $fitnessLevel : 'beginner',
            'weekly_training_days' => $trainingDays ?? 0,
            'needs_low_impact' => $jointPain,
            'joint_friendly_required' => $jointPain,
            'duration_preference' => $durationPreference,
            'activity_level' => $activityLevel,
            'age' => $age ?? 0,
            'gender' => $gender,
            'height_cm' => $heightCm,
            'weight_kg' => $weightKg,
        ];

        if (!empty($errors)) {
            return ['errors' => $errors, 'context' => $context];
        }

        $bmr = fitgym_calculate_bmr($gender, $weightKg, $heightCm, (int)$age);
        $tdee = $bmr * $activityMultipliers[$activityLevel];
        $targetDailyCalories = $tdee * (1 - fitgym_goal_calorie_adjustment($goal));
        $dailyCalorieGap = abs($tdee - $targetDailyCalories);

        $context['bmr'] = (int)round($bmr);
        $context['tdee'] = (int)round($tdee);
        $context['target_daily_calories'] = (int)round($targetDailyCalories);
        $context['daily_calorie_gap'] = (int)round($dailyCalorieGap);
        $context['target_burn_per_session'] = fitgym_target_burn_per_session($context);
        $context['weekly_target_burn'] = $context['target_burn_per_session'] * max(1, (int)$context['weekly_training_days']);
        $context['intensity_preference'] = fitgym_target_intensity_from_context($context);

        return ['errors' => [], 'context' => $context];
    }
}

if (!function_exists('fitgym_burn_distance_to_range')) {
    function fitgym_burn_distance_to_range(int $targetBurn, ?int $burnMin, ?int $burnMax): ?int
    {
        if ($burnMin === null && $burnMax === null) {
            return null;
        }
        if ($burnMin !== null && $burnMax !== null && $targetBurn >= $burnMin && $targetBurn <= $burnMax) {
            return 0;
        }
        if ($burnMin !== null && $burnMax !== null) {
            return min(abs($targetBurn - $burnMin), abs($targetBurn - $burnMax));
        }
        if ($burnMin !== null) {
            return abs($targetBurn - $burnMin);
        }

        return abs($targetBurn - (int)$burnMax);
    }
}

if (!function_exists('fitgym_intensity_rank')) {
    function fitgym_intensity_rank(string $intensity): int
    {
        $map = ['low' => 1, 'medium' => 2, 'high' => 3];
        return $map[fitgym_normalize_intensity($intensity)] ?? 0;
    }
}

if (!function_exists('fitgym_recommendation_reason_text')) {
    function fitgym_recommendation_reason_text(array $reasons, array $fallbackFlags = []): string
    {
        $reasonParts = array_slice(array_values(array_unique(array_filter($reasons))), 0, 4);
        $base = empty($reasonParts)
            ? 'Closest available class for the current training plan.'
            : ucfirst(implode(', ', $reasonParts)) . '.';

        if (!empty($fallbackFlags)) {
            $base .= ' Fallback alternative because ' . implode(' and ', $fallbackFlags) . '.';
        }

        return $base;
    }
}

if (!function_exists('fitgym_score_class_for_context')) {
    function fitgym_score_class_for_context(array $classRow, array $context): array
    {
        $classRow = fitgym_normalize_class_row($classRow);
        [$burnMin, $burnMax] = fitgym_class_burn_range($classRow);
        $targetBurn = max(0, (int)($context['target_burn_per_session'] ?? 0));
        $targetIntensity = fitgym_normalize_intensity((string)($context['intensity_preference'] ?? ''));
        $targetFrequency = (int)($context['weekly_training_days'] ?? 0);
        $durationPreference = fitgym_nullable_int($context['duration_preference'] ?? null, 1);
        $classRank = fitgym_level_rank((string)($classRow['fitness_level'] ?? ''));
        $userRank = fitgym_level_rank((string)($context['fitness_level'] ?? ''));

        $goalMatched = fitgym_matches_goal($classRow, (string)($context['goal'] ?? ''));
        $tdeeMatched = fitgym_matches_tdee_band($classRow, $context);
        $burnDistance = fitgym_burn_distance_to_range($targetBurn, $burnMin, $burnMax);
        $intensityGap = abs(fitgym_intensity_rank((string)$classRow['intensity_level']) - fitgym_intensity_rank($targetIntensity));
        $fitnessGap = abs($userRank - $classRank);
        $durationMinutes = fitgym_class_duration_minutes($classRow);
        $frequency = fitgym_nullable_int($classRow['recommended_frequency_per_week'] ?? null, 1);

        $score = 0;
        $reasons = [];
        $fallbackFlags = [];

        if ($goalMatched) {
            $score += 30;
            $reasons[] = 'goal match';
        } else {
            $fallbackFlags[] = 'its goal profile is not exact';
        }

        if ($classRank === $userRank) {
            $score += 20;
            $reasons[] = 'fitness level match';
        } elseif ($classRank < $userRank) {
            $score += 14;
            $reasons[] = 'safe for your level';
        }

        if ($targetIntensity !== '' && fitgym_normalize_intensity((string)$classRow['intensity_level']) !== '') {
            if ($intensityGap === 0) {
                $score += 15;
                $reasons[] = 'intensity fit';
            } elseif ($intensityGap === 1) {
                $score += 8;
            }
        }

        if ($burnDistance === 0) {
            $score += 30;
            $reasons[] = 'burn target inside class range';
        } elseif ($burnDistance !== null) {
            if ($burnDistance <= 40) {
                $score += 22;
                $reasons[] = 'burn target very close';
            } elseif ($burnDistance <= 80) {
                $score += 15;
            } elseif ($burnDistance <= 140) {
                $score += 8;
            } elseif ($burnDistance <= 220) {
                $score += 3;
            }
        }

        if ($targetFrequency > 0 && $frequency !== null) {
            $frequencyGap = abs($targetFrequency - $frequency);
            if ($frequencyGap === 0) {
                $score += 10;
                $reasons[] = 'weekly frequency fit';
            } elseif ($frequencyGap === 1) {
                $score += 6;
            } elseif ($frequencyGap === 2) {
                $score += 2;
            }
        }

        if ($durationPreference !== null && $durationMinutes !== null) {
            $durationGap = abs($durationPreference - $durationMinutes);
            if ($durationGap <= 10) {
                $score += 5;
                $reasons[] = 'duration fit';
            } elseif ($durationGap <= 20) {
                $score += 2;
            }
        }

        if (!empty($context['needs_low_impact']) && (int)($classRow['low_impact'] ?? 0) === 1) {
            $score += 6;
            $reasons[] = 'low impact support';
        }
        if (!empty($context['joint_friendly_required']) && (int)($classRow['joint_friendly'] ?? 0) === 1) {
            $score += 6;
            $reasons[] = 'joint-friendly support';
        }
        if (!$tdeeMatched) {
            $fallbackFlags[] = 'you fall outside its optional TDEE band';
        }

        $classRow['_goal_match'] = $goalMatched;
        $classRow['_goal_score'] = $goalMatched ? 30 : 0;
        $classRow['_tdee_band_match'] = $tdeeMatched;
        $classRow['_burn_distance'] = $burnDistance ?? PHP_INT_MAX;
        $classRow['_fitness_gap'] = $fitnessGap;
        $classRow['_fallback_flags'] = $fallbackFlags;
        $classRow['score'] = $score;
        $classRow['duration_minutes'] = $durationMinutes;
        $classRow['burn_min_resolved'] = $burnMin;
        $classRow['burn_max_resolved'] = $burnMax;
        $classRow['reason'] = fitgym_recommendation_reason_text($reasons, $fallbackFlags);

        return $classRow;
    }
}

if (!function_exists('fitgym_compare_recommendation_rows')) {
    function fitgym_compare_recommendation_rows(array $left, array $right): int
    {
        $scoreCompare = ((int)($right['score'] ?? 0)) <=> ((int)($left['score'] ?? 0));
        if ($scoreCompare !== 0) {
            return $scoreCompare;
        }

        $goalCompare = ((int)($right['_goal_score'] ?? 0)) <=> ((int)($left['_goal_score'] ?? 0));
        if ($goalCompare !== 0) {
            return $goalCompare;
        }

        $burnCompare = ((int)($left['_burn_distance'] ?? PHP_INT_MAX)) <=> ((int)($right['_burn_distance'] ?? PHP_INT_MAX));
        if ($burnCompare !== 0) {
            return $burnCompare;
        }

        $fitnessCompare = ((int)($left['_fitness_gap'] ?? PHP_INT_MAX)) <=> ((int)($right['_fitness_gap'] ?? PHP_INT_MAX));
        if ($fitnessCompare !== 0) {
            return $fitnessCompare;
        }

        $idCompare = ((int)($left['id'] ?? PHP_INT_MAX)) <=> ((int)($right['id'] ?? PHP_INT_MAX));
        if ($idCompare !== 0) {
            return $idCompare;
        }

        return strcasecmp((string)($left['class_name'] ?? ''), (string)($right['class_name'] ?? ''));
    }
}

if (!function_exists('fitgym_get_recommended_classes')) {
    function fitgym_get_recommended_classes(array $classes, array $context): array
    {
        $exactMatches = [];
        $fallbackMatches = [];

        foreach ($classes as $classRow) {
            $classRow = fitgym_normalize_class_row($classRow);
            if (!$classRow['recommendation_ready']) {
                continue;
            }
            if (!fitgym_matches_fitness_level($classRow, $context)) {
                continue;
            }
            if (!fitgym_matches_safety_requirements($classRow, $context)) {
                continue;
            }

            $scoredRow = fitgym_score_class_for_context($classRow, $context);
            if ($scoredRow['_goal_match'] && $scoredRow['_tdee_band_match']) {
                $exactMatches[] = $scoredRow;
                continue;
            }

            $fallbackMatches[] = $scoredRow;
        }

        usort($exactMatches, 'fitgym_compare_recommendation_rows');
        usort($fallbackMatches, 'fitgym_compare_recommendation_rows');

        $seenSlugs = [];
        $fallbackAlternatives = [];
        foreach ($fallbackMatches as $row) {
            $slug = (string)($row['slug'] ?? '');
            if ($slug !== '' && isset($seenSlugs[$slug])) {
                continue;
            }
            if ($slug !== '') {
                $seenSlugs[$slug] = true;
            }
            $fallbackAlternatives[] = $row;
        }

        return [
            'exact_matches' => array_slice($exactMatches, 0, 5),
            'fallback_alternatives' => array_slice($fallbackAlternatives, 0, 5),
            'has_exact_match' => !empty($exactMatches),
        ];
    }
}
