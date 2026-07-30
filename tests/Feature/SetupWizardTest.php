<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SetupWizardTest extends TestCase
{
    private string $marker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->marker = storage_path('app/installed');
        File::delete($this->marker);
    }

    protected function tearDown(): void
    {
        File::delete($this->marker);
        parent::tearDown();
    }

    public function test_base_url_redirects_to_database_setup(): void
    {
        $this->get('/')->assertRedirect(route('setup.database'));
    }

    public function test_database_selection_advances_to_connection_step(): void
    {
        $this->post(route('setup.database.store'), ['driver' => 'pgsql'])
            ->assertSessionHas('setup.driver', 'pgsql')
            ->assertRedirect(route('setup.connection'));

        $this->get(route('setup.connection'))
            ->assertOk()
            ->assertSee('Connect to PostgreSQL');
    }

    public function test_invalid_database_driver_is_rejected(): void
    {
        $this->post(route('setup.database.store'), ['driver' => 'sqlite'])
            ->assertSessionHasErrors('driver');
    }

    public function test_installer_is_locked_after_completion(): void
    {
        File::put($this->marker, now()->toIso8601String());

        $this->get(route('setup.database'))->assertRedirect(route('dashboard'));
        $this->get('/')->assertRedirect(route('dashboard'));
    }
}
