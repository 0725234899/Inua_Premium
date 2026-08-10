<?php
function normalizeDurationUnit($loan_duration_unit) {
    $unit = strtolower(trim((string) $loan_duration_unit));
    switch ($unit) {
        case 'day':
        case 'days':
            return 'days';
        case 'week':
        case 'weeks':
            return 'weeks';
        case 'month':
        case 'months':
            return 'months';
        case 'year':
        case 'years':
            return 'years';
        default:
            return 'months';
    }
}

function getCycleInterval($cycle) {
    switch ($cycle) {
        case 'daily':
            return '1 day';
        case 'weekly':
            return '1 week';
        case 'monthly':
            return '1 month';
        case 'yearly':
            return '1 year';
        case 'once':
            return '0 days';
        default:
            return '1 month';
    }
}

function getMaturityDate($loan_release_date, $loan_duration, $loan_duration_unit) {
    $maturity_date = new DateTime($loan_release_date);
    $loan_duration_unit = normalizeDurationUnit($loan_duration_unit);

    switch ($loan_duration_unit) {
        case 'days':
            $interval = new DateInterval('P' . (int) $loan_duration . 'D');
            break;
        case 'weeks':
            $interval = new DateInterval('P' . (int) $loan_duration . 'W');
            break;
        case 'months':
            $interval = new DateInterval('P' . (int) $loan_duration . 'M');
            break;
        case 'years':
            $interval = new DateInterval('P' . (int) $loan_duration . 'Y');
            break;
        default:
            $interval = new DateInterval('P' . (int) $loan_duration . 'M');
            break;
    }

    return $maturity_date->add($interval);
}

function getRepaymentScheduleDates($loan_release_date, $loan_duration, $loan_duration_unit, $repayment_cycle) {
    $loan_duration_unit = normalizeDurationUnit($loan_duration_unit);
    $maturity_date = getMaturityDate($loan_release_date, $loan_duration, $loan_duration_unit);

    if ($repayment_cycle === 'once') {
        switch ($loan_duration_unit) {
            case 'days':
            case 'weeks':
                $scheduleCycle = 'weekly';
                break;
            case 'months':
                $scheduleCycle = 'monthly';
                break;
            case 'years':
                $scheduleCycle = 'yearly';
                break;
            default:
                $scheduleCycle = 'monthly';
                break;
        }

        $scheduleDates = [];
        $current = new DateTime($loan_release_date);
        $interval = DateInterval::createFromDateString(getCycleInterval($scheduleCycle));

        while (true) {
            $next = clone $current;
            $next->add($interval);

            if ($next >= $maturity_date) {
                $scheduleDates[] = $maturity_date->format('Y-m-d');
                break;
            }

            $scheduleDates[] = $next->format('Y-m-d');
            $current = $next;
        }

        return [end($scheduleDates)];
    }

    $scheduleDates = [];
    $current = new DateTime($loan_release_date);
    $interval = DateInterval::createFromDateString(getCycleInterval($repayment_cycle));

    while (true) {
        $next = clone $current;
        $next->add($interval);

        if ($next >= $maturity_date) {
            $scheduleDates[] = $maturity_date->format('Y-m-d');
            break;
        }

        $scheduleDates[] = $next->format('Y-m-d');
        $current = $next;
    }

    return $scheduleDates;
}

$loan_release_date = '2026-08-06';
$loan_duration = 4;
$loan_duration_unit = 'weeks';
$repayment_cycle = 'once';

$dates = getRepaymentScheduleDates($loan_release_date, $loan_duration, $loan_duration_unit, $repayment_cycle);
echo implode(', ', $dates) . "\n";
"}