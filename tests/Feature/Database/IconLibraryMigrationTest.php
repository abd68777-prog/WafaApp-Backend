<?php

namespace Tests\Feature\Database;

use App\Models\Card;
use App\Models\Icon;
use App\Support\PhosphorIcons;
use Database\Seeders\IconSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Servers that started with the first, non-Phosphor icon keys move to the
 * full Phosphor library without any card losing its icon.
 */
class IconLibraryMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_old_icons_move_to_their_phosphor_names_and_the_cards_follow(): void
    {
        $oldIcons = collect(['coffee-cup', 'tea-glass', 'juice', 'cake-slice', 'ice-cream', 'burger', 'pizza', 'bread', 'scissors', 'gift'])
            ->mapWithKeys(fn (string $key) => [$key => Icon::factory()->create(['key' => $key])]);

        $coffeeCard = Card::factory()->create(['icon_id' => $oldIcons['coffee-cup']->id]);
        $teaCard = Card::factory()->create(['icon_id' => $oldIcons['tea-glass']->id]);
        $burgerCard = Card::factory()->create(['icon_id' => $oldIcons['burger']->id]);

        $this->migration()->up();
        $this->migration()->up();

        $this->assertSame('coffee', $coffeeCard->fresh()->icon->key);
        $this->assertSame($oldIcons['coffee-cup']->id, $coffeeCard->fresh()->icon_id);
        $this->assertSame('coffee', $teaCard->fresh()->icon->key);
        $this->assertFalse($oldIcons['tea-glass']->fresh()->is_active);
        $this->assertSame('hamburger', $burgerCard->fresh()->icon->key);

        $activeKeys = Icon::query()->where('is_active', true)->pluck('key');
        $this->assertCount(53, $activeKeys);
        $this->assertTrue($activeKeys->every(fn (string $key) => PhosphorIcons::exists($key)));
    }

    public function test_a_fresh_database_is_left_to_the_seeder(): void
    {
        $this->migration()->up();

        $this->assertSame(0, Icon::query()->count());
    }

    public function test_every_seeded_icon_is_a_phosphor_icon(): void
    {
        $this->seed(IconSeeder::class);

        $this->assertSame(53, Icon::query()->count());
        $this->assertTrue(Icon::query()->pluck('key')->every(fn (string $key) => PhosphorIcons::exists($key)));
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_10_23_151612_upgrade_icons_to_phosphor_library.php');
    }
}
