<?php

use App\Filament\Resources\Tenants\Pages\EditTenant;
use App\Filament\Resources\Tenants\RelationManagers\UsersRelationManager;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertModelMissing;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    actingAs(User::factory()->admin()->create());
});

it('lists operators and staff across tenants but hides super admins', function () {
    $admin = User::factory()->admin()->create();
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $operator = User::factory()->create(['role' => 'operator', 'tenant_id' => $tenantA->id]);
    $staff = User::factory()->staff()->create(['tenant_id' => $tenantB->id]);

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords([$operator, $staff])
        ->assertCanNotSeeTableRecords([$admin]);
});

it('creates a pre-verified operator for a tenant', function () {
    $tenant = Tenant::factory()->create();

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Owner Person',
            'email' => 'owner@example.com',
            'role' => 'operator',
            'tenant_id' => $tenant->id,
            'password' => 'secret-password',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    assertDatabaseHas('users', [
        'email' => 'owner@example.com',
        'role' => 'operator',
        'tenant_id' => $tenant->id,
    ]);

    $user = User::where('email', 'owner@example.com')->firstOrFail();

    expect($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('secret-password', $user->password))->toBeTrue();
});

it('creates a staff account for a tenant', function () {
    $tenant = Tenant::factory()->create();

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Front Desk',
            'email' => 'desk@example.com',
            'role' => 'staff',
            'tenant_id' => $tenant->id,
            'password' => 'secret-password',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    assertDatabaseHas('users', [
        'email' => 'desk@example.com',
        'role' => 'staff',
        'tenant_id' => $tenant->id,
    ]);
});

it('blocks creating a second operator for a tenant that already has one', function () {
    $tenant = Tenant::factory()->create();
    User::factory()->create(['role' => 'operator', 'tenant_id' => $tenant->id]);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Second Owner',
            'email' => 'second@example.com',
            'role' => 'operator',
            'tenant_id' => $tenant->id,
            'password' => 'secret-password',
        ])
        ->call('create')
        ->assertNotified();

    expect(User::where('tenant_id', $tenant->id)->where('role', 'operator')->count())->toBe(1);
});

it('verifies a stuck operator email from the users table', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->unverified()->create(['role' => 'operator', 'tenant_id' => $tenant->id]);

    Livewire::test(ListUsers::class)
        ->callTableAction('verify_email', $user);

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

it('resets a user password from the users table', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->staff()->create(['tenant_id' => $tenant->id]);

    Livewire::test(ListUsers::class)
        ->callTableAction('reset_password', $user, ['password' => 'brand-new-pass']);

    expect(Hash::check('brand-new-pass', $user->refresh()->password))->toBeTrue();
});

it('edits a user name without changing the tenant', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->staff()->create(['tenant_id' => $tenant->id, 'name' => 'Old Name']);

    Livewire::test(EditUser::class, ['record' => $user->getKey()])
        ->fillForm(['name' => 'New Name'])
        ->call('save')
        ->assertHasNoFormErrors();

    $user->refresh();

    expect($user->name)->toBe('New Name')
        ->and($user->tenant_id)->toBe($tenant->id);
});

it('deletes a user', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->staff()->create(['tenant_id' => $tenant->id]);

    Livewire::test(EditUser::class, ['record' => $user->getKey()])
        ->callAction(DeleteAction::class);

    assertModelMissing($user);
});

it('shows a tenant\'s users in the relation manager', function () {
    $tenant = Tenant::factory()->create();
    $operator = User::factory()->create(['role' => 'operator', 'tenant_id' => $tenant->id]);
    $staff = User::factory()->staff()->create(['tenant_id' => $tenant->id]);
    $otherTenantUser = User::factory()->create(['role' => 'operator', 'tenant_id' => Tenant::factory()->create()->id]);

    Livewire::test(UsersRelationManager::class, [
        'ownerRecord' => $tenant,
        'pageClass' => EditTenant::class,
    ])
        ->assertOk()
        ->assertCanSeeTableRecords([$operator, $staff])
        ->assertCanNotSeeTableRecords([$otherTenantUser]);
});

it('creates a user inline from the tenant relation manager', function () {
    $tenant = Tenant::factory()->create();

    Livewire::test(UsersRelationManager::class, [
        'ownerRecord' => $tenant,
        'pageClass' => EditTenant::class,
    ])
        ->callAction(TestAction::make(CreateAction::class)->table(), [
            'name' => 'Inline Staff',
            'email' => 'inline@example.com',
            'role' => 'staff',
            'password' => 'secret-password',
        ]);

    assertDatabaseHas('users', [
        'email' => 'inline@example.com',
        'role' => 'staff',
        'tenant_id' => $tenant->id,
    ]);
});

it('hides the admin panel from a non-admin operator', function () {
    $tenant = Tenant::factory()->create();
    $operator = User::factory()->create(['role' => 'operator', 'tenant_id' => $tenant->id]);

    actingAs($operator)
        ->get('http://'.config('tenancy.admin_domain').'/')
        ->assertNotFound();
});
