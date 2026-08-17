<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sar_requests', function (Blueprint $table) {
            $table->id();
            $table->string('requester_name');
            $table->string('requester_relationship')->nullable(); // e.g. "self", "parent", "social worker"
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->date('received_date');
            $table->date('deadline_date'); // statutory: received_date + 1 calendar month
            $table->string('status')->default('pending'); // pending|in_progress|fulfilled|declined
            $table->date('fulfilled_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('logged_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sar_requests');
    }
};
