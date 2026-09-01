<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\Plan;
use Filament\Support\Contracts\HasLabel;

/**
 * Registry of everything a subscription plan can gate. This enum is the single
 * source of truth: the admin plan form, the JSON stored on plans.features, and
 * the Plan/Tenant feature helpers all derive from cases() — adding a feature is
 * one new case here plus one enforcement call site where the feature applies.
 *
 * Defaults are deliberately permissive (toggles on, limits unlimited) so a
 * tenant whose plan slug has no plans row behaves exactly as before this
 * feature existed.
 */
enum PlanFeature: string implements HasLabel
{
    case VehicleLimit = 'vehicle_limit';
    case PhotosPerVehicle = 'photos_per_vehicle';
    case Reports = 'reports';
    case FleetHeatmap = 'fleet_heatmap';
    case Branding = 'branding';
    case AiListingWriter = 'ai_listing_writer';
    case AiBusinessSummary = 'ai_business_summary';
    case AiPricingSuggestions = 'ai_pricing_suggestions';
    case StaffSeatLimit = 'staff_seat_limit';
    case Templates = 'templates';
    case PromoCodes = 'promo_codes';
    case MaintenanceReminders = 'maintenance_reminders';
    case Reviews = 'reviews';
    case Waitlist = 'waitlist';
    case StockAlert = 'stock_alert';
    case AiConcierge = 'ai_concierge';

    public function type(): PlanFeatureType
    {
        return match ($this) {
            self::VehicleLimit, self::PhotosPerVehicle, self::StaffSeatLimit => PlanFeatureType::Limit,
            self::Reports, self::FleetHeatmap, self::Branding, self::Templates, self::PromoCodes,
            self::MaintenanceReminders, self::Reviews, self::Waitlist, self::StockAlert,
            self::AiListingWriter, self::AiBusinessSummary, self::AiPricingSuggestions,
            self::AiConcierge => PlanFeatureType::Toggle,
        };
    }

    /**
     * Display order for the public pricing cards. A raw cases() order is grouped
     * by type() rather than by marketing importance; this puts branding first,
     * then the caps, then the value toggles, then the AI add-ons. Every case
     * must appear here — PlanFeatureMarketingLineTest pins that.
     *
     * @return array<int, self>
     */
    public static function marketingOrder(): array
    {
        return [
            self::Branding,
            self::VehicleLimit,
            self::StaffSeatLimit,
            self::PhotosPerVehicle,
            self::Reports,
            self::FleetHeatmap,
            self::Templates,
            self::PromoCodes,
            self::MaintenanceReminders,
            self::Reviews,
            self::Waitlist,
            self::StockAlert,
            self::AiListingWriter,
            self::AiBusinessSummary,
            self::AiPricingSuggestions,
            self::AiConcierge,
        ];
    }

    /**
     * One bullet line for $plan's public pricing card. Returns null when the
     * feature should not be advertised for the plan: a disabled toggle, or an
     * AI add-on the plan doesn't include.
     */
    public function marketingLine(Plan $plan): ?string
    {
        if ($this->type() === PlanFeatureType::Toggle) {
            return $plan->allows($this)
                ? (string) __('marketing.plan_feature_'.$this->value)
                : null;
        }

        $limit = $plan->limit($this);

        return $limit === null
            ? (string) __('marketing.plan_feature_'.$this->value.'_unlimited')
            : trans_choice('marketing.plan_feature_'.$this->value, $limit, ['count' => $limit]);
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::VehicleLimit => 'Max vehicles',
            self::PhotosPerVehicle => 'Max photos per vehicle',
            self::Reports => 'Reports page + CSV export',
            self::FleetHeatmap => 'Fleet utilization heatmap (requires Reports page)',
            self::Branding => 'Branding customization',
            self::AiListingWriter => 'AI vehicle listing writer',
            self::AiBusinessSummary => 'AI weekly business summary',
            self::AiPricingSuggestions => 'AI pricing suggestions',
            self::StaffSeatLimit => 'Max staff accounts',
            self::Templates => 'Custom contract & email templates',
            self::PromoCodes => 'Promo codes',
            self::MaintenanceReminders => 'Maintenance reminders + auto-block',
            self::Reviews => 'Review request email + home showcase',
            self::Waitlist => 'Waitlist for booked-out dates',
            self::StockAlert => 'Stock alert for unavailable vehicles',
            self::AiConcierge => 'AI storefront FAQ concierge',
        };
    }

    public function default(): mixed
    {
        // AI features cost real API money per call — unlike the rest, a plan
        // that doesn't mention them (or a tenant with no plan row) gets them OFF.
        if (in_array($this, [self::AiListingWriter, self::AiBusinessSummary, self::AiPricingSuggestions, self::AiConcierge], true)) {
            return false;
        }

        return match ($this->type()) {
            PlanFeatureType::Toggle => true,
            PlanFeatureType::Limit => null,
        };
    }
}
