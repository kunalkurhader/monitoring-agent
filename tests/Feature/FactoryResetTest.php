<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\User;
use App\Support\EnvironmentFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Mockery;
use Tests\TestCase;

class FactoryResetTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        File::delete(storage_path('app/installed'));
        parent::tearDown();
    }

    public function test_administrator_can_factory_reset_the_application(): void
    {
        $admin = User::factory()->create([
            'password' => 'correct-password',
            'is_admin' => true,
        ]);
        User::factory()->create();
        $agent = Agent::query()->create([
            'id' => 'b7ebc999-12e0-4d91-8e2b-d42b4166d0f2',
            'hostname' => 'web-01',
        ]);
        DB::table('system_stats')->insert([
            'agent_id' => $agent->id,
            'cpu_usage' => 25,
            'total_memory' => 1000,
            'free_memory' => 500,
            'created_at' => now(),
        ]);
        DB::table('agent_api_tokens')->insert([
            'name' => 'Production',
            'token_hash' => hash('sha256', 'secret'),
            'created_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        File::put(storage_path('app/installed'), now()->toIso8601String());

        $environment = Mockery::mock(EnvironmentFile::class);
        $environment->shouldReceive('isWritable')->once()->andReturnTrue();
        $environment->shouldReceive('contents')->once()->andReturn('original environment');
        $environment->shouldReceive('replace')->once()->with(Mockery::on(
            fn (array $values): bool => $values['DB_CONNECTION'] === 'sqlite'
                && $values['AGENT_API_TOKEN'] === '',
        ));
        $this->app->instance(EnvironmentFile::class, $environment);

        $this->actingAs($admin)->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Danger Zone')
            ->assertSee('ERASE EVERYTHING');

        $this->actingAs($admin)->delete(route('settings.factory-reset'), [
            'password' => 'correct-password',
            'confirmation' => 'ERASE EVERYTHING',
        ])->assertRedirect(route('setup.database'));

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('agents', 0);
        $this->assertDatabaseCount('system_stats', 0);
        $this->assertDatabaseCount('agent_api_tokens', 0);
        $this->assertFalse(File::exists(storage_path('app/installed')));
    }

    public function test_factory_reset_rejects_an_incorrect_password(): void
    {
        $admin = User::factory()->create([
            'password' => 'correct-password',
            'is_admin' => true,
        ]);
        $this->app->instance(EnvironmentFile::class, Mockery::mock(EnvironmentFile::class));

        $this->actingAs($admin)->delete(route('settings.factory-reset'), [
            'password' => 'incorrect-password',
            'confirmation' => 'ERASE EVERYTHING',
        ])->assertRedirect(route('settings.index').'#danger-zone')
            ->assertSessionHasErrors('password', null, 'factoryReset');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
        $this->assertAuthenticatedAs($admin);
    }
}
