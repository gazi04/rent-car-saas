<?php

namespace App\Enums;

enum PlanFeatureType
{
    /** On/off access to a feature (rendered as a toggle in the plan form). */
    case Toggle;

    /** Numeric cap; null means unlimited (rendered as a numeric input). */
    case Limit;
}
