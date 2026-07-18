<?php

namespace App\Enums;

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

    public function type(): PlanFeatureType
    {
        return match ($this) {
            self::VehicleLimit, self::PhotosPerVehicle, self::StaffSeatLimit => PlanFeatureType::Limit,
            self::Reports, self::FleetHeatmap, self::Branding, self::Templates, self::PromoCodes, self::MaintenanceReminders,
            self::Reviews, self::AiListingWriter, self::AiBusinessSummary, self::AiPricingSuggestions => PlanFeatureType::Toggle,
        };
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
        };
    }

    public function default(): mixed
    {
        // AI features cost real API money per call — unlike the rest, a plan
        // that doesn't mention them (or a tenant with no plan row) gets them OFF.
        if (in_array($this, [self::AiListingWriter, self::AiBusinessSummary, self::AiPricingSuggestions], true)) {
            return false;
        }

        return match ($this->type()) {
            PlanFeatureType::Toggle => true,
            PlanFeatureType::Limit => null,
        };
    }
}
