<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;

/*
|--------------------------------------------------------------------------
| #[Locked] on Livewire model properties
|--------------------------------------------------------------------------
|
| A public property typed as an Eloquent model is a record identity the browser
| carries and hands back on every /livewire/update. mount()'s eligibility guard
| — abort_unless($vehicle->is_public, 404), a Completed-status check — runs once
| and never again, so the property is the only thing naming which record the
| component's actions then act on.
|
| Livewire does refuse to re-point such a property on its own (see
| tests/Feature/Security/LivewireModelTamperingTest.php, which pins that
| guarantee). #[Locked] is required here anyway: it states the intent at the
| declaration, and it turns a tampering attempt from a silent discard into
| CannotUpdateLockedPropertyException, so a framework regression surfaces as a
| failure rather than as quiet data exposure.
|
| Pest's class-based arch() API cannot express this. Every Livewire component in
| this app is a single-file component inside a .blade.php view — `grep -rln
| "extends Component" app/` is empty — so there is no class for expect() to
| inspect. This scans the source instead, the way tests/Feature/DesignSystemTest
| does. The framework is not booted for the Arch suite, so the views root is
| resolved from __DIR__ rather than resource_path().
|
*/

/** Every Livewire single-file component under resources/views. */
function livewireSingleFileComponents(): array
{
    $root = dirname(__DIR__, 2).'/resources/views';

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

    $components = [];

    foreach ($files as $file) {
        if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }

        if (str_contains((string) file_get_contents($file->getPathname()), 'extends Component')) {
            $components[] = substr($file->getPathname(), strlen($root) + 1);
        }
    }

    sort($components);

    return $components;
}

/**
 * Public properties on a component whose type resolves to an Eloquent model,
 * each paired with the attribute run declared directly above it.
 *
 * @return list<array{name: string, type: string, attributes: string}>
 */
function eloquentPropertiesIn(string $relativePath): array
{
    $source = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/'.$relativePath);

    preg_match_all('/^use\s+(?P<fqcn>[A-Za-z_\\\\][A-Za-z0-9_\\\\]*);/m', $source, $imports);

    $aliases = [];

    foreach ($imports['fqcn'] as $fqcn) {
        $aliases[substr((string) strrchr('\\'.$fqcn, '\\'), 1)] = $fqcn;
    }

    preg_match_all(
        '/(?P<attributes>(?:[ \t]*#\[[^\]]*\][ \t]*\R)*)[ \t]*public\s+\??(?P<type>[A-Za-z_\\\\][A-Za-z0-9_\\\\]*)\s+\$(?P<name>[A-Za-z_][A-Za-z0-9_]*)/',
        $source,
        $matches,
        PREG_SET_ORDER
    );

    $properties = [];

    foreach ($matches as $match) {
        $class = $aliases[$match['type']] ?? $match['type'];

        if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            continue;
        }

        $properties[] = [
            'name' => $match['name'],
            'type' => $class,
            'attributes' => $match['attributes'],
        ];
    }

    return $properties;
}

it('locks every Eloquent property on a Livewire component', function (string $component) {
    $unlocked = array_values(array_filter(
        eloquentPropertiesIn($component),
        fn (array $property): bool => ! str_contains($property['attributes'], '#[Locked]')
    ));

    $names = implode(', ', array_map(
        fn (array $property): string => "public {$property['type']} \${$property['name']}",
        $unlocked
    ));

    expect($unlocked)->toBe([], "resources/views/{$component}: {$names} — an Eloquent property is a record identity the "
        .'browser hands back on every update, and mount()\'s eligibility guard does not run again. Add #[Locked] above '
        .'it; the rationale is on vehicle-show.blade.php.');
})->with(livewireSingleFileComponents());

it('still finds the model properties it is meant to be governing', function () {
    // A glob or regex that quietly stops matching would turn the rule above into
    // a no-op that passes forever. These four are the known population; the
    // number may grow, and this only fails if it shrinks.
    $found = array_sum(array_map(
        fn (string $component): int => count(eloquentPropertiesIn($component)),
        livewireSingleFileComponents()
    ));

    expect($found)->toBeGreaterThanOrEqual(4);
});
