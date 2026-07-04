<!DOCTYPE html>
<html lang="{{ $booking->locale ?? 'sq' }}">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ __('contract.title') }} – {{ $booking->reference }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "dejavu sans", sans-serif; font-size: 10pt; color: #1a1a1a; line-height: 1.5; }
        .page { padding: 30px 40px; }
        .header { border-bottom: 2px solid #1a1a1a; padding-bottom: 14px; margin-bottom: 20px; }
        .header h1 { font-size: 16pt; font-weight: bold; letter-spacing: 0.5px; }
        .header .operator-name { font-size: 12pt; color: #444; margin-top: 4px; }
        .meta { font-size: 9pt; color: #666; margin-top: 4px; }
        .section { margin-bottom: 18px; }
        .section-title { font-size: 10pt; font-weight: bold; background-color: #f0f0f0; padding: 4px 8px; margin-bottom: 8px; border-left: 3px solid #1a1a1a; }
        .two-col { width: 100%; }
        .two-col td { width: 50%; vertical-align: top; padding-right: 16px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data td { padding: 4px 6px; font-size: 9.5pt; }
        table.data td.label { font-weight: bold; width: 40%; color: #333; }
        table.pricing { width: 100%; border-collapse: collapse; }
        table.pricing td { padding: 5px 8px; border: 1px solid #ddd; font-size: 9.5pt; }
        table.pricing td.label { font-weight: bold; background-color: #f7f7f7; width: 60%; }
        table.pricing tr.total td { font-weight: bold; background-color: #1a1a1a; color: #fff; }
        .terms { font-size: 9pt; color: #444; line-height: 1.6; }
        .signatures { margin-top: 30px; }
        table.sig { width: 100%; }
        table.sig td { width: 50%; vertical-align: bottom; padding: 0 10px 0 0; }
        .sig-line { border-top: 1px solid #1a1a1a; margin-top: 40px; padding-top: 4px; font-size: 9pt; }
        .footer { margin-top: 30px; border-top: 1px solid #ccc; padding-top: 8px; font-size: 8pt; color: #888; text-align: center; }
    </style>
</head>
<body>
<div class="page">

    {{-- Header --}}
    <div class="header">
        @php
            $tenant = \App\Models\Tenant::find($booking->tenant_id);
            $logoPath = $tenant?->getFirstMediaPath('logo', 'thumb');
        @endphp
        @if($logoPath && file_exists($logoPath))
        <div style="margin-bottom:8px;">
            <img src="{{ $logoPath }}" alt="{{ $tenant->name }}" style="max-height:50px;max-width:180px;">
        </div>
        @else
        <div class="operator-name">{{ $tenant?->name ?? config('app.name') }}</div>
        @endif
        <h1>{{ __('contract.title') }}</h1>
        <div class="meta">
            {{ __('contract.reference') }}: <strong>{{ $booking->reference }}</strong>
            &nbsp;&nbsp;|&nbsp;&nbsp;
            {{ __('contract.generated_at') }}: {{ now()->format('d/m/Y') }}
        </div>
    </div>

    {{-- Parties --}}
    <div class="section">
        <div class="section-title">{{ __('contract.parties_title') }}</div>
        <table class="two-col">
            <tr>
                <td>
                    <strong>{{ __('contract.operator_label') }}</strong><br>
                    {{ $tenant?->name ?? config('app.name') }}<br>
                    @if($tenant?->email)
                        {{ __('contract.email') }}: {{ $tenant->email }}<br>
                    @endif
                    @if($tenant?->phone)
                        {{ __('contract.phone') }}: {{ $tenant->phone }}<br>
                    @endif
                </td>
                <td>
                    <strong>{{ __('contract.customer_label') }}</strong><br>
                    {{ $booking->customer_name }}<br>
                    {{ __('contract.phone') }}: {{ $booking->customer_phone }}<br>
                    @if($booking->customer_email)
                        {{ __('contract.email') }}: {{ $booking->customer_email }}<br>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    {{-- Vehicle --}}
    <div class="section">
        <div class="section-title">{{ __('contract.vehicle_title') }}</div>
        <table class="data">
            <tr>
                <td class="label">{{ __('contract.vehicle') }}</td>
                <td>{{ $booking->vehicle->name }}</td>
            </tr>
            <tr>
                <td class="label">{{ __('contract.year') }}</td>
                <td>{{ $booking->vehicle->year }}</td>
            </tr>
            <tr>
                <td class="label">{{ __('contract.fuel_type') }}</td>
                <td>{{ ucfirst($booking->vehicle->fuel_type->value) }}</td>
            </tr>
            <tr>
                <td class="label">{{ __('contract.transmission') }}</td>
                <td>{{ ucfirst($booking->vehicle->transmission->value) }}</td>
            </tr>
        </table>
    </div>

    {{-- Rental details --}}
    <div class="section">
        <div class="section-title">{{ __('contract.rental_title') }}</div>
        <table class="data">
            <tr>
                <td class="label">{{ __('contract.start_date') }}</td>
                <td>{{ $booking->start_date->format('d/m/Y H:i') }}</td>
            </tr>
            <tr>
                <td class="label">{{ __('contract.end_date') }}</td>
                <td>{{ $booking->end_date->format('d/m/Y H:i') }}</td>
            </tr>
            <tr>
                <td class="label">{{ __('contract.duration') }}</td>
                <td>{{ __('contract.days', ['count' => $booking->start_date->diffInDays($booking->end_date)]) }}</td>
            </tr>
            @if($booking->pickup_location)
            <tr>
                <td class="label">{{ __('contract.pickup_location') }}</td>
                <td>{{ $booking->pickup_location }}</td>
            </tr>
            @endif
        </table>
    </div>

    {{-- Pricing --}}
    <div class="section">
        <div class="section-title">{{ __('contract.pricing_title') }}</div>
        <table class="pricing">
            <tr>
                <td class="label">{{ __('contract.rate_type') }}</td>
                <td>{{ ucfirst($booking->rate_type->value) }}</td>
            </tr>
            <tr>
                <td class="label">{{ __('contract.subtotal') }}</td>
                <td>&#8364;{{ number_format($booking->subtotal, 2) }}</td>
            </tr>
            @if($booking->discount_amount > 0)
            <tr>
                <td class="label">{{ __('contract.discount') }}</td>
                <td>-&#8364;{{ number_format($booking->discount_amount, 2) }}</td>
            </tr>
            @endif
            @if($booking->deposit > 0)
            <tr>
                <td class="label">{{ __('contract.deposit') }}</td>
                <td>&#8364;{{ number_format($booking->deposit, 2) }}</td>
            </tr>
            @endif
            <tr class="total">
                <td class="label">{{ __('contract.total') }}</td>
                <td>&#8364;{{ number_format($booking->total, 2) }}</td>
            </tr>
        </table>
    </div>

    {{-- Terms --}}
    <div class="section">
        <div class="section-title">{{ __('contract.terms_title') }}</div>
        <p class="terms">{!! nl2br(e($terms ?? __('contract.terms_body'))) !!}</p>
    </div>

    {{-- Signatures --}}
    <div class="signatures">
        <div class="section-title">{{ __('contract.signatures_title') }}</div>
        <table class="sig">
            <tr>
                <td>
                    <div class="sig-line">
                        {{ __('contract.operator_signature') }}<br>
                        {{ __('contract.date_label') }}: _______________
                    </div>
                </td>
                <td>
                    <div class="sig-line">
                        {{ __('contract.customer_signature') }}<br>
                        {{ __('contract.date_label') }}: _______________
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">{{ $tenant?->name ?? config('app.name') }} &bull; {{ $booking->reference }}</div>

</div>
</body>
</html>
