<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ahasend_domains', function (Blueprint $table): void {
            $table->id();

            /** Identifier of the domain object in Ahasend. */
            $table->string('ahasend_domain_id')->unique();

            $table->string('domain')->unique();

            /** DNS records Ahasend expects, each with type/host/content/required/propagated. */
            $table->json('dns_records')->nullable();

            $table->boolean('dns_valid')->default(false);

            /**
             * Local lifecycle state.
             * Possible values: pending | checking | verified | failed
             */
            $table->string('verify_state')->default('pending')->index();

            $table->timestamp('last_dns_check_at')->nullable();

            $table->string('tracking_subdomain')->nullable();
            $table->string('return_path_subdomain')->nullable();
            $table->string('subscription_subdomain')->nullable();
            $table->string('media_subdomain')->nullable();

            $table->string('dkim_selector')->nullable();
            $table->unsignedSmallInteger('dkim_rotation_interval_days')->nullable();
            $table->boolean('rotation_ready')->default(false);

            $table->string('dsn_recipient')->nullable();

            /**
             * Optional owner supplied by the consuming application (e.g. a tenant).
             * The package never interprets it beyond grouping domains by owner.
             * The id column is a string so both UUID and integer keys fit.
             */
            $table->string('owner_type')->nullable();
            $table->string('owner_id')->nullable();

            $table->timestamps();

            $table->index(['owner_type', 'owner_id']);
        });

        // Two concurrent "add domain" clicks would slip past an application-level
        // check, so the one-open-domain-per-owner rule is settled by the database.
        // Owner-less rows (our own shared domains) are excluded.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                CREATE UNIQUE INDEX ahasend_domains_owner_unverified_unique
                    ON ahasend_domains (owner_type, owner_id)
                    WHERE verify_state <> 'verified' AND owner_type IS NOT NULL
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ahasend_domains');
    }
};
