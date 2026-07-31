<?php

use App\Models\Tenant;
use Sentry\Event;
use Sentry\SentrySdk;
use Sentry\State\Scope;

afterEach(function () {
    tenancy()->end();
});

/**
 * The tags currently on Sentry's active scope, read back by applying that scope
 * to a throwaway event — the scope keeps its tags private, and applying it is the
 * same path a real captured event takes.
 *
 * @return array<string, string>
 */
function sentryScopeTags(): array
{
    $applied = null;

    SentrySdk::getCurrentHub()->configureScope(function (Scope $scope) use (&$applied): void {
        $applied = $scope->applyToEvent(Event::createEvent());
    });

    return $applied?->getTags() ?? [];
}

it('tags the sentry scope with the tenant when tenancy initializes', function () {
    // A syntactically valid DSN so the listener's blank() guard passes. Nothing is
    // transmitted — configureScope() only mutates local state, it never sends.
    config(['sentry.dsn' => 'https://public@sentry.example.com/1']);

    $tenant = Tenant::factory()->withDomain('ardi')->create(['name' => 'Ardi Rent A Car']);

    tenancy()->initialize($tenant);

    expect(sentryScopeTags())
        ->toHaveKey('tenant_id', (string) $tenant->id)
        ->toHaveKey('tenant_name', 'Ardi Rent A Car');
});

it('does not touch the sentry scope when no dsn is configured', function () {
    // The default for local and CI (phpunit.xml pins SENTRY_LARAVEL_DSN to empty).
    // Skipping the work entirely keeps it off the hot path of every tenant request.
    config(['sentry.dsn' => null]);

    $tenant = Tenant::factory()->withDomain('besa')->create();

    tenancy()->initialize($tenant);

    expect(sentryScopeTags())->not->toHaveKey('tenant_id');
});
