<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transport_ledger', function (Blueprint $table) {
            // Nullable, and only ever populated for type='charge' rows (see
            // backfill below + TransportController). MySQL/InnoDB unique
            // indexes permit any number of NULLs, so payment rows — which
            // leave this column null — are never affected by the constraint.
            // This turns the app-level "already charged today" check into a
            // DB-enforced guarantee, closing the race where two near-
            // simultaneous "mark collected" requests could double-charge.
            $table->date('charge_date')->nullable()->after('entry_date');
        });

        // Backfill: existing charge rows get charge_date = entry_date so the
        // constraint applies retroactively without altering any amounts.
        \Illuminate\Support\Facades\DB::table('transport_ledger')
            ->where('type', 'charge')
            ->update(['charge_date' => \Illuminate\Support\Facades\DB::raw('entry_date')]);

        // Defensive: if the pre-existing race condition already produced a
        // double-charge for some member/day, adding the unique index below
        // would fail outright. Keep the earliest charge row for each
        // (member_id, entry_date) pair and drop any extras first.
        $duplicates = \Illuminate\Support\Facades\DB::table('transport_ledger')
            ->select('member_id', 'entry_date', \Illuminate\Support\Facades\DB::raw('MIN(id) as keep_id'))
            ->where('type', 'charge')
            ->groupBy('member_id', 'entry_date')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $dup) {
            \Illuminate\Support\Facades\DB::table('transport_ledger')
                ->where('type', 'charge')
                ->where('member_id', $dup->member_id)
                ->where('entry_date', $dup->entry_date)
                ->where('id', '!=', $dup->keep_id)
                ->delete();
        }

        Schema::table('transport_ledger', function (Blueprint $table) {
            $table->unique(['member_id', 'charge_date'], 'transport_ledger_one_charge_per_day');
        });
    }

    public function down(): void
    {
        Schema::table('transport_ledger', function (Blueprint $table) {
            $table->dropUnique('transport_ledger_one_charge_per_day');
            $table->dropColumn('charge_date');
        });
    }
};
