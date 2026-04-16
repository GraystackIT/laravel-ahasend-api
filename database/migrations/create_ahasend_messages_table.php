<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ahasend_messages', function (Blueprint $table): void {
            $table->id();

            /** Unique identifier shared between this package and Ahasend's API. */
            $table->string('message_id')->unique()->index();

            /** Primary recipient email address(es) — comma-separated for multi-recipient sends. */
            $table->string('recipient');

            $table->string('subject');

            /**
             * Current delivery status.
             * Possible values: queued | sent | delivered | opened | failed | bounced
             */
            $table->string('status')->default('queued')->index();

            /** Full API request or webhook payload stored as JSON. */
            $table->json('payload')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ahasend_messages');
    }
};
