<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, array<int, string>> */
    private array $encrypted = [
        'members' => ['phone', 'email', 'address_line1', 'address_line2', 'town', 'postcode', 'gp_name', 'gp_practice', 'gp_phone'],
        'end_of_day_records' => ['activities', 'toileting_notes', 'medication_notes', 'incident_detail', 'notes', 'concern_detail'],
        'member_reviews' => ['outcomes', 'actions'],
        'member_outcomes' => ['outcome'],
        'member_contacts' => ['name', 'role', 'organisation', 'email', 'phone', 'notes'],
        'member_goals' => ['title', 'description'],
        'member_consents' => ['notes'],
        'comms_log' => ['subject', 'contact_name', 'organisation'],
        'supervisions' => ['discussion', 'actions_agreed', 'development_notes'],
        'referrals' => ['referrer_name', 'referrer_email', 'referrer_phone', 'organisation', 'person_name'],
        'attendances' => ['notes', 'absence_reason'],
        'activity_participants' => ['outcome_notes'],
        'form_submission_data' => ['value'],
        'incidents' => ['title', 'location', 'actions_taken'],
        'tasks' => ['title', 'description', 'notes'],
        'sar_requests' => ['requester_name', 'requester_relationship', 'notes'],
        'transport_ledger' => ['notes'],
    ];

    public function up(): void
    {
        $this->widenEncryptedStringColumns();
        $this->transform(fn (string $value) => $this->encryptIfNeeded($value));
    }

    public function down(): void
    {
        $this->transform(fn (string $value) => $this->decryptIfNeeded($value));
        $this->restoreOriginalStringColumns();
    }

    private function transform(callable $transform): void
    {
        foreach ($this->encrypted as $table => $columns) {
            DB::table($table)->orderBy('id')->chunkById(100, function ($rows) use ($table, $columns, $transform) {
                foreach ($rows as $row) {
                    $updates = [];
                    foreach ($columns as $column) {
                        if ($row->{$column} !== null) {
                            $updates[$column] = $transform((string) $row->{$column});
                        }
                    }
                    if ($updates !== []) {
                        DB::table($table)->where('id', $row->id)->update($updates);
                    }
                }
            });
        }
    }

    private function encryptIfNeeded(string $value): string
    {
        try {
            Crypt::decryptString($value);

            return $value;
        } catch (DecryptException) {
            if ($this->looksLikeLaravelCiphertext($value)) {
                throw new RuntimeException('Existing encrypted data could not be decrypted. Restore the correct APP_KEY before migrating.');
            }

            return Crypt::encryptString($value);
        }
    }

    private function decryptIfNeeded(string $value): string
    {
        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            if ($this->looksLikeLaravelCiphertext($value)) {
                throw new RuntimeException('Encrypted data could not be decrypted. Restore the correct APP_KEY before rolling back.');
            }

            return $value;
        }
    }

    private function looksLikeLaravelCiphertext(string $value): bool
    {
        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            return false;
        }

        $payload = json_decode($decoded, true);

        return is_array($payload)
            && isset($payload['iv'], $payload['value'], $payload['mac']);
    }

    private function widenEncryptedStringColumns(): void
    {
        Schema::table('members', function (Blueprint $table) {
            foreach (['phone', 'email', 'address_line1', 'address_line2', 'town', 'postcode', 'gp_name', 'gp_practice', 'gp_phone'] as $column) {
                $table->text($column)->nullable()->change();
            }
        });
        Schema::table('member_contacts', function (Blueprint $table) {
            $table->text('name')->change();
            foreach (['role', 'organisation', 'email', 'phone'] as $column) {
                $table->text($column)->nullable()->change();
            }
        });
        Schema::table('member_goals', fn (Blueprint $table) => $table->text('title')->change());
        Schema::table('comms_log', function (Blueprint $table) {
            foreach (['subject', 'contact_name', 'organisation'] as $column) {
                $table->text($column)->nullable()->change();
            }
        });
        Schema::table('referrals', function (Blueprint $table) {
            $table->text('referrer_name')->change();
            $table->text('person_name')->change();
            foreach (['referrer_email', 'referrer_phone', 'organisation'] as $column) {
                $table->text($column)->nullable()->change();
            }
        });
        Schema::table('attendances', fn (Blueprint $table) => $table->text('absence_reason')->nullable()->change());
        Schema::table('incidents', function (Blueprint $table) {
            $table->text('title')->change();
            $table->text('location')->nullable()->change();
        });
        Schema::table('tasks', fn (Blueprint $table) => $table->text('title')->change());
        Schema::table('sar_requests', function (Blueprint $table) {
            $table->text('requester_name')->change();
            $table->text('requester_relationship')->nullable()->change();
        });
    }

    private function restoreOriginalStringColumns(): void
    {
        Schema::table('members', function (Blueprint $table) {
            foreach (['phone', 'gp_phone'] as $column) {
                $table->string($column, 30)->nullable()->change();
            }
            $table->string('email')->nullable()->change();
            foreach (['address_line1', 'address_line2', 'town', 'postcode', 'gp_name', 'gp_practice'] as $column) {
                $table->string($column)->nullable()->change();
            }
        });
        Schema::table('member_contacts', function (Blueprint $table) {
            $table->string('name', 100)->change();
            $table->string('role', 100)->nullable()->change();
            $table->string('organisation', 200)->nullable()->change();
            $table->string('email')->nullable()->change();
            $table->string('phone', 30)->nullable()->change();
        });
        Schema::table('member_goals', fn (Blueprint $table) => $table->string('title', 200)->change());
        Schema::table('comms_log', function (Blueprint $table) {
            $table->string('subject', 200)->nullable()->change();
            $table->string('contact_name', 100)->nullable()->change();
            $table->string('organisation', 200)->nullable()->change();
        });
        Schema::table('referrals', function (Blueprint $table) {
            $table->string('referrer_name')->change();
            $table->string('referrer_email')->nullable()->change();
            $table->string('referrer_phone')->nullable()->change();
            $table->string('organisation')->nullable()->change();
            $table->string('person_name')->change();
        });
        Schema::table('attendances', fn (Blueprint $table) => $table->string('absence_reason')->nullable()->change());
        Schema::table('incidents', function (Blueprint $table) {
            $table->string('title')->change();
            $table->string('location')->nullable()->change();
        });
        Schema::table('tasks', fn (Blueprint $table) => $table->string('title')->change());
        Schema::table('sar_requests', function (Blueprint $table) {
            $table->string('requester_name')->change();
            $table->string('requester_relationship')->nullable()->change();
        });
    }
};
