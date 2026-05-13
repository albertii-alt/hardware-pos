<?php

/**
 * Format a quantity for display.
 * Measured units (allows_decimal=true): show 3 decimal places → "2.500"
 * Fixed units (allows_decimal=false):   show clean integer    → "2"
 */
function formatQuantity(float $qty, bool $allowsDecimal): string
{
    if ($allowsDecimal) {
        return number_format($qty, 3, '.', '');
    }
    return (string)(int)round($qty);
}

/**
 * Format quantity with unit label appended.
 * Examples: "2.500 kg", "15 pcs"
 */
function formatQuantityWithUnit(float $qty, bool $allowsDecimal, string $unit): string
{
    return formatQuantity($qty, $allowsDecimal) . ' ' . $unit;
}

/**
 * Validate that a quantity value meets all rules for a product.
 * Returns null on success, or an error string on failure.
 */
function validateQuantityPrecision(float $qty, bool $allowsDecimal, float $minSellQty, float $step = 0.0): ?string
{
    if ($qty <= 0) {
        return 'Quantity must be greater than zero.';
    }

    if (!$allowsDecimal) {
        if (!isWholeQuantity($qty)) {
            return 'This product only allows whole number quantities.';
        }
        if ($qty < 1) {
            return 'Minimum quantity is 1.';
        }
    } else {
        if (round($qty, 3) !== round($qty, 10)) {
            return 'Maximum 3 decimal places allowed.';
        }
        if ($qty < $minSellQty) {
            return 'Minimum quantity is ' . number_format($minSellQty, 3) . '.';
        }
        if ($step > 0) {
            $err = validateQuantityStep($qty, $step, true);
            if ($err !== null) return $err;
        }
    }

    return null;
}

/**
 * Returns true if the quantity is effectively a whole number.
 */
function isWholeQuantity(float $qty): bool
{
    return abs($qty - round($qty)) < 0.0001;
}

/**
 * Normalize a raw numeric input to a safe DECIMAL(10,3) float.
 * Clamps to 3 decimal places, rejects non-numeric values.
 */
function normalizeQuantity(mixed $raw): ?float
{
    if (!is_numeric($raw)) {
        return null;
    }
    $val = round((float)$raw, 3);
    if ($val <= 0) {
        return null;
    }
    return $val;
}

/**
 * Parse and validate a quantity from API/form input.
 * Returns ['value' => float] on success or ['error' => string] on failure.
 */
function parseQuantityInput(mixed $raw, bool $allowsDecimal, float $minSellQty): array
{
    $qty = normalizeQuantity($raw);
    if ($qty === null) {
        return ['error' => 'Invalid quantity value.'];
    }
    $err = validateQuantityPrecision($qty, $allowsDecimal, $minSellQty);
    if ($err !== null) {
        return ['error' => $err];
    }
    return ['value' => $qty];
}

/**
 * Validate that a quantity aligns with the product's quantity_step.
 * Fixed products always use step=1.
 * Returns null on success, error string on failure.
 */
function validateQuantityStep(float $qty, float $step, bool $allowsDecimal): ?string
{
    $effectiveStep = $allowsDecimal ? $step : 1.0;
    if ($effectiveStep <= 0) return null;
    $remainder = fmod(round($qty, 3), round($effectiveStep, 3));
    // Allow tiny floating-point tolerance
    if ($remainder > 0.0005 && ($effectiveStep - $remainder) > 0.0005) {
        return 'Quantity must be a multiple of ' . number_format($effectiveStep, 3) . '.';
    }
    return null;
}

/**
 * Snap a quantity to the nearest valid step multiple (rounds up).
 */
function snapToQuantityStep(float $qty, float $step, bool $allowsDecimal): float
{
    $effectiveStep = $allowsDecimal ? $step : 1.0;
    if ($effectiveStep <= 0) return round($qty, 3);
    return round(ceil(round($qty, 3) / $effectiveStep) * $effectiveStep, 3);
}

/**
 * Return a human-readable step label.
 * Examples: 0.250 → "0.25", 1.000 → "1", 0.500 → "0.5"
 */
function formatStepLabel(float $step): string
{
    // Trim trailing zeros after decimal
    return rtrim(rtrim(number_format($step, 3, '.', ''), '0'), '.');
}
