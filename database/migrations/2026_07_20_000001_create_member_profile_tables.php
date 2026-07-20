<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('gp_name')->nullable();
            $table->string('gp_practice')->nullable();
            $table->string('gp_phone')->nullable();
            $table->text('medication')->nullable(); // encrypted
        });

        // Circle of Care: social workers, appointees, GPs, family contacts.
        Schema::create('member_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('role')->nullable(); // social worker|appointee|gp|family|other
            $table->string('organisation')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('member_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('active'); // active|achieved|paused
            $table->date('target_date')->nullable();
            $table->date('achieved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('member_outcomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_goal_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->text('outcome');
            $table->foreignId('user_id')->constrained();
            $table->timestamps();
        });

        Schema::create('member_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // allergy|medical|behaviour|dietary|other
            $table->text('text'); // encrypted
            $table->string('severity')->default('amber'); // amber|red
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('member_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('consent_type'); // photos|outings|medication|data_sharing|emergency_treatment
            $table->boolean('granted')->default(false);
            $table->date('recorded_on');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['member_id', 'consent_type']);
        });

        Schema::create('comms_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // email|phone|letter|meeting|text|other
            $table->string('direction')->default('outbound'); // inbound|outbound|both
            $table->string('subject')->nullable();
            $table->text('summary')->nullable(); // encrypted
            $table->string('contact_name')->nullable();
            $table->string('organisation')->nullable();
            $table->date('date');
            $table->foreignId('user_id')->constrained();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('subject');
            $table->text('body');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('comms_log');
        Schema::dropIfExists('member_consents');
        Schema::dropIfExists('member_alerts');
        Schema::dropIfExists('member_outcomes');
        Schema::dropIfExists('member_goals');
        Schema::dropIfExists('member_contacts');
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['gp_name', 'gp_practice', 'gp_phone', 'medication']);
        });
    }
};
