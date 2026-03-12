<?php

function calculate_fee_fixed($target_value, $intervals) {
    $result = 0;
    foreach ($intervals as $interval) {
        $start_value = isset($interval['start']) ? (float) $interval['start'] : 0;
        $end_value = isset($interval['end']) && !empty($interval['end']) ? (float) $interval['end'] : PHP_INT_MAX;

        if ($target_value >= $start_value && $target_value <= $end_value) {
            $result += $interval['rate'];
        }
    }
    return $result;
}

function calculate_fee_variable($target_value, $intervals) {
    $result = 0;
    foreach ($intervals as $interval) {
        $start_value = isset($interval['start']) ? (float) $interval['start'] : 0;
        $end_value = isset($interval['end']) && !empty($interval['end']) ? (float) $interval['end'] : PHP_INT_MAX;

        if ($target_value >= $start_value && $target_value <= $end_value) {
            if (isset($interval['type']) && $interval['type'] === 'fixed') {
                $result += isset($interval['rate']) ? floatval($interval['rate']) : 0;
            } elseif (isset($interval['type']) && $interval['type'] === 'percentage') {
                $result += isset($interval['rate']) ? $target_value * (floatval($interval['rate']) / 100) : 0;
            }
        }
    }
    return $result;
}