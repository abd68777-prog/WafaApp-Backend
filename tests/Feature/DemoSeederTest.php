<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Merchant;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * The demo shops and customers never reach a production database, whether
 * seeding runs in full or the demo seeder is asked for by name.
 */
class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_demo_seeder_refuses_to_run_in_production(): void
    {
        $this->app['env'] = 'production';

        try {
            $this->app->make(DemoSeeder::class)->run();
            $this->fail('The demo seeder ran in production.');
        } catch (RuntimeException $exception) {
            $this->assertSame('The demo data is never seeded in production.', $exception->getMessage());
        }

        $this->assertSame(0, Merchant::query()->count());
        $this->assertSame(0, Customer::query()->count());
    }

    public function test_seeding_production_adds_the_lists_but_no_demo_data(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])->assertSuccessful();

        $this->assertDatabaseMissing('merchants', ['clerk_user_id' => 'user_demo_merchant']);
        $this->assertSame(0, Customer::query()->count());
        $this->assertDatabaseHas('settings', ['key' => 'trial_days']);
    }
}
