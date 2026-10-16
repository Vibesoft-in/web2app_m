<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Plan;
use InvalidArgumentException;

class PricingCalculator
{
    // Base pricing tiers in Paise (INR lowest unit: 1 INR = 100 Paise)
    private const DEFAULT_PRICES = [
        'starter'    => 99900,   // ₹999 / month
        'pro'        => 199900,  // ₹1,999 / month
        'enterprise' => 499900,  // ₹4,999 / month
    ];

    private const ADDON_PRICES = [
        'whatsapp'     => 99900,  // ₹999 / month
        'extra_build'  => 49900,  // ₹499 per build
    ];

    private const ANNUAL_DISCOUNT_PERCENT = 20;

    /**
     * Calculates the exact server-side subscription or order total.
     * Prevents client-side price tampering by computing all values server-side.
     *
     * @param Plan|string $plan Plan instance or plan slug
     * @param string $interval 'monthly' or 'annual'
     * @param array<string, int> $addons Add-on quantities e.g. ['whatsapp' => 1]
     * @return array{
     *     plan_name: string,
     *     base_monthly: int,
     *     interval: string,
     *     months: int,
     *     subtotal: int,
     *     discount: int,
     *     addons_total: int,
     *     total: int,
     *     currency: string
     * }
     */
    public function calculate(Plan|string $plan, string $interval = 'monthly', array $addons = []): array
    {
        $planName = $plan instanceof Plan ? strtolower($plan->name) : strtolower($plan);
        $baseMonthly = $plan instanceof Plan && $plan->price_monthly > 0
            ? $plan->price_monthly
            : (self::DEFAULT_PRICES[$planName] ?? 99900);

        $interval = strtolower(trim($interval));
        if (! in_array($interval, ['monthly', 'annual'], true)) {
            throw new InvalidArgumentException("Unsupported billing interval: {$interval}");
        }

        $months = $interval === 'annual' ? 12 : 1;
        $planSubtotal = $baseMonthly * $months;

        // Apply 20% discount on annual subscriptions
        $discount = 0;
        if ($interval === 'annual') {
            $discount = (int) round($planSubtotal * (self::ANNUAL_DISCOUNT_PERCENT / 100));
        }

        // Calculate add-ons
        $addonsTotal = 0;
        foreach ($addons as $addonKey => $qty) {
            $qty = max(0, (int) $qty);
            $unitPrice = self::ADDON_PRICES[$addonKey] ?? 0;
            $addonsTotal += ($unitPrice * $qty * $months);
        }

        $finalTotal = max(0, ($planSubtotal - $discount) + $addonsTotal);

        return [
            'plan_name'     => ucfirst($planName),
            'base_monthly'  => $baseMonthly,
            'interval'      => $interval,
            'months'        => $months,
            'subtotal'      => $planSubtotal,
            'discount'      => $discount,
            'addons_total'  => $addonsTotal,
            'total'         => $finalTotal,
            'currency'      => 'INR',
        ];
    }
}
