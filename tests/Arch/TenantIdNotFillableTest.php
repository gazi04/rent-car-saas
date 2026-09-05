<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/*
|--------------------------------------------------------------------------
| tenant_id is never mass-assignable on a tenant-scoped model
|--------------------------------------------------------------------------
|
| BelongsToTenant::bootBelongsToTenant() fills tenant_id only when it is NOT
| already set:
|
|     if (! $model->getAttribute('tenant_id') && ! $model->relationLoaded('tenant'))
|
| so a caller that passes tenant_id wins over the trait. Combined with a
| fillable tenant_id, one Model::create($input) reached by request data writes
| a row into another tenant — no error, no scope violation. Nothing exploits
| that today, but it is the one class of bug this codebase is built to make
| impossible, so the primitive is removed rather than watched.
|
| The rule keys on the TRAIT, not a list of class names: the central models
| that legitimately keep tenant_id fillable (TenantPayment, AiUsageLog,
| EmailLog — nothing supplies the value for them) are exempt because they do
| not use it, so the exemption cannot rot the way an ignore list would.
|
| Scoped to App\Models. tests/Fixtures/ScopedItem is a deliberate $guarded = []
| fixture and is not governed by this.
|
| This does NOT stop forceFill/forceCreate/setAttribute/Model::unguarded() —
| that is why factories keep working (Factory::makeInstance wraps in
| Model::unguarded) and why the deliberate cross-tenant writes in the admin
| tests are unaffected. Those paths are explicit and greppable; mass assignment
| is not.
|
*/

/**
 * Every App\Models class that is tenant-scoped via BelongsToTenant.
 *
 * @return list<class-string<Model>>
 */
function tenantScopedModels(): array
{
    $models = [];

    foreach (glob(dirname(__DIR__, 2).'/app/Models/*.php') ?: [] as $file) {
        /** @var class-string $class */
        $class = 'App\\Models\\'.basename($file, '.php');

        if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            continue;
        }

        if (! in_array(BelongsToTenant::class, class_uses_recursive($class), true)) {
            continue;
        }

        $models[] = $class;
    }

    sort($models);

    return $models;
}

/**
 * The columns declared by a model's #[Fillable] attribute.
 *
 * @param  class-string  $model
 * @return list<string>
 */
function fillableColumnsOf(string $model): array
{
    $attributes = (new ReflectionClass($model))->getAttributes(Fillable::class);

    return $attributes === [] ? [] : array_values($attributes[0]->newInstance()->columns);
}

it('keeps tenant_id out of #[Fillable] on every tenant-scoped model', function (string $model) {
    // Asserted as "the offending columns are []" rather than not->toContain():
    // Pest's toContain() is variadic, so a second string argument is read as
    // another needle, not as a failure message, and the rule silently passes.
    $offending = array_values(array_intersect(fillableColumnsOf($model), ['tenant_id']));

    expect($offending)->toBe(
        [],
        "{$model} uses BelongsToTenant, which already fills tenant_id from the active tenant — but only "
        .'when it is not already set. Listing it in #[Fillable] turns any create()/fill() that carries a '
        .'tenant_id key into a cross-tenant write. Remove it; the trait supplies the value. If a caller '
        .'genuinely must set it (a central model administered cross-tenant), that model should not be '
        .'using BelongsToTenant at all.'
    );
})->with(tenantScopedModels());

it('still finds the models it is meant to be governing', function () {
    // A glob or trait check that quietly stops matching would turn the rule
    // above into a no-op that passes forever. Eleven is the known population;
    // it may grow, and this only fails if it shrinks.
    expect(tenantScopedModels())->toHaveCount(11);
});
