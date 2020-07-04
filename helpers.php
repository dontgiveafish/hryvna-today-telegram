<?php

/**
 * Function to format exchange rates
 *
 * @param stdClass $exchange_rate
 * @return string
 */
function formatRate(stdClass $exchange_rate): string
{
    $value = formatNumber($exchange_rate->value);
    $diff = formatNumber($exchange_rate->diff);

    if ($diff > 0) {
        $diff = '+' . $diff;
    }

    return "$value ($diff)";
}

function formatNumber(float $value, int $precision = 2): string
{
    return number_format(round($value, $precision), $precision);
}
