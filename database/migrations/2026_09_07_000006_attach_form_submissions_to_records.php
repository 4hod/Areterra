<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A report filled in about a member or animal should live on that record.
 * Without this, a submission only knows which form it was and who typed it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->nullableMorphs('subject', 'form_submissions_subject_idx');
        });

        Schema::table('form_definitions', function (Blueprint $table) {
            // Which records this form can be filled in about: member, animal,
            // vehicle, or null for a general form with no subject.
            $table->string('subject_type')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('form_definitions', fn (Blueprint $t) => $t->dropColumn('subject_type'));
        Schema::table('form_submissions', fn (Blueprint $t) => $t->dropMorphs('subject'));
    }
};
