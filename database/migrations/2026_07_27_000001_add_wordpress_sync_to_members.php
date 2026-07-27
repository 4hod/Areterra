<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->unsignedBigInteger('wordpress_id')->nullable()->unique()->after('id');
            $table->text('medical_notes')->nullable()->after('support_needs');
            $table->text('interests')->nullable()->after('medical_notes');
        });

        Schema::create('member_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('wordpress_note_id')->nullable()->unique();
            $table->string('note_type')->default('general');
            $table->text('note');
            $table->string('author_name')->nullable();
            $table->dateTime('noted_at')->nullable();
            $table->string('source')->default('wordpress');
            $table->timestamps();

            $table->index(['member_id', 'noted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_notes');

        Schema::table('members', function (Blueprint $table) {
            $table->dropUnique(['wordpress_id']);
            $table->dropColumn(['wordpress_id', 'medical_notes', 'interests']);
        });
    }
};
