<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('risk_assessments', function (Blueprint $table) {
            $table->string('category')->default('general')->after('title');
            $table->unsignedInteger('version')->default(1)->after('category');
            $table->foreignId('supersedes_id')->nullable()->after('version')->constrained('risk_assessments')->nullOnDelete();
            $table->boolean('is_current')->default(true)->after('status');
            $table->index(['category', 'is_current', 'status']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->date('expires_at')->nullable()->after('requires_read');
            $table->index('expires_at');
        });

        Schema::table('referrals', function (Blueprint $table) {
            $table->dateTime('trial_completed_at')->nullable()->after('reviewed_at');
            $table->dateTime('trial_review_opened_at')->nullable()->after('trial_completed_at');
        });

        Schema::create('vehicle_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('checked_by')->constrained('users');
            $table->dateTime('checked_at');
            $table->decimal('odometer_miles', 10, 1);
            $table->string('fuel_level'); // empty|quarter|half|three_quarters|full
            $table->boolean('tyres_ok');
            $table->boolean('lights_ok');
            $table->boolean('warning_lights_ok');
            $table->boolean('damage_ok');
            $table->boolean('safe_to_drive');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['vehicle_id', 'checked_at']);
        });

        Schema::create('portfolio_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // achievement|project|photo|choice|certificate
            $table->text('title');
            $table->text('description')->nullable();
            $table->date('achieved_on')->nullable();
            $table->nullableMorphs('source', 'portfolio_source_idx');
            $table->string('media_path')->nullable();
            $table->boolean('visible_to_member')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['member_id', 'type', 'achieved_on']);
        });

        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('portfolio_item_id')->nullable()->constrained()->nullOnDelete();
            $table->text('title');
            $table->text('description')->nullable();
            $table->string('certificate_number')->unique();
            $table->date('issued_on');
            $table->foreignId('issued_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('automation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('trigger');
            $table->string('action');
            $table->json('conditions')->nullable();
            $table->json('action_config')->nullable();
            $table->boolean('active')->default(true);
            $table->boolean('system')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('last_run_at')->nullable();
            $table->timestamps();
            $table->index(['trigger', 'active']);
        });

        Schema::create('automation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->string('trigger');
            $table->nullableMorphs('subject', 'automation_subject_idx');
            $table->string('status'); // completed|skipped|failed
            $table->text('summary')->nullable();
            $table->string('fingerprint')->nullable()->unique();
            $table->dateTime('ran_at');
            $table->timestamps();
            $table->index(['trigger', 'ran_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_runs');
        Schema::dropIfExists('automation_rules');
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('portfolio_items');
        Schema::dropIfExists('vehicle_checks');

        Schema::table('referrals', fn (Blueprint $table) => $table->dropColumn(['trial_completed_at', 'trial_review_opened_at']));
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['expires_at']);
            $table->dropColumn('expires_at');
        });
        Schema::table('risk_assessments', function (Blueprint $table) {
            $table->dropForeign(['supersedes_id']);
            $table->dropIndex(['category', 'is_current', 'status']);
            $table->dropColumn(['category', 'version', 'supersedes_id', 'is_current']);
        });
    }
};
