<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permissions move from "your role decides what you can do" to "your account
 * decides what you can do".
 *
 * Roles survive as presets on the permissions screen, so setting up a new
 * support worker is still one click — but afterwards each person's access is
 * their own and can be adjusted without inventing a new role.
 *
 * Existing users are backfilled from whatever their role granted them today,
 * so nobody's access changes at the moment this runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_capabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('capability', 64);
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'capability']);
            $table->index('capability');
        });

        // Backfill: everyone keeps exactly what they have right now.
        $presets = config('capabilities.roles');
        $all = config('capabilities.all');
        $now = now();
        $rows = [];

        foreach (DB::table('users')->select('id', 'role')->cursor() as $user) {
            // Administrators previously got everything via Gate::before, which
            // is being retired — so their grants are written out in full.
            $capabilities = $user->role === 'administrator'
                ? $all
                : ($presets[$user->role] ?? []);

            foreach ($capabilities as $capability) {
                $rows[] = [
                    'user_id' => $user->id,
                    'capability' => $capability,
                    'granted_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('user_capabilities')->insert($chunk);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_capabilities');
    }
};
