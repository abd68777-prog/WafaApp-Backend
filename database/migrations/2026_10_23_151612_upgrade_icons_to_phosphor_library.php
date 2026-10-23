<?php

use Database\Seeders\IconSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The first icon keys were not Phosphor names, so the apps could not draw
     * them. Each moves to its Phosphor replacement, in this order: tea-glass
     * has no tea counterpart and joins coffee once coffee-cup has become it.
     *
     * @var array<string, string>
     */
    private const REPLACEMENTS = [
        'coffee-cup' => 'coffee',
        'cake-slice' => 'cake',
        'juice' => 'orange-slice',
        'burger' => 'hamburger',
        'tea-glass' => 'coffee',
    ];

    /**
     * Run the migrations.
     *
     * Servers never seed again, so the full library arrives here: the old
     * keys are renamed in place (cards keep their icon id) or, when the new
     * key already exists, their cards move to it and the old icon is switched
     * off — icons are never deleted. A fresh database has no icons yet and
     * gets the library from IconSeeder instead.
     */
    public function up(): void
    {
        if (! DB::table('icons')->exists()) {
            return;
        }

        DB::transaction(function (): void {
            foreach (self::REPLACEMENTS as $oldKey => $newKey) {
                $oldId = DB::table('icons')->where('key', $oldKey)->value('id');

                if ($oldId === null) {
                    continue;
                }

                $newId = DB::table('icons')->where('key', $newKey)->value('id');

                if ($newId === null) {
                    DB::table('icons')->where('id', $oldId)->update(['key' => $newKey, 'updated_at' => now()]);

                    continue;
                }

                DB::table('cards')->where('icon_id', $oldId)->update(['icon_id' => $newId]);
                DB::table('icons')->where('id', $oldId)->update(['is_active' => false, 'updated_at' => now()]);
            }

            (new IconSeeder)->run();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The old keys were never drawable; there is nothing to go back to.
    }
};
