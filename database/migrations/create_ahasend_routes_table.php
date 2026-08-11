<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ahasend_routes', function (Blueprint $table): void {
            $table->id();

            /**
             * Our own opaque identifier, used in the inbound endpoint URL.
             *
             * Assigned before the route is created so the URL is known up front —
             * Ahasend only hands out its own id in the create response, which
             * would otherwise force a second call to patch the URL.
             */
            $table->ulid('public_id')->unique();

            /** Identifier of the route object in Ahasend. */
            $table->string('ahasend_route_id')->unique();

            /** The domain this route was provisioned for, if it belongs to one. */
            $table->foreignId('ahasend_domain_id')
                ->nullable()
                ->constrained('ahasend_domains')
                ->nullOnDelete();

            $table->string('name');

            /** Match pattern, e.g. "*@acme.at". Ahasend derives the domain from it. */
            $table->string('recipient');

            /** Endpoint Ahasend posts inbound mail to, including the route id. */
            $table->string('url');

            /**
             * Route signing secret. Ahasend returns it only when the route is
             * created, so it cannot be recovered — stored encrypted.
             */
            $table->text('secret')->nullable();

            $table->boolean('attachments')->default(false);
            $table->boolean('headers')->default(false);
            $table->boolean('strip_replies')->default(false);
            $table->boolean('group_by_message_id')->default(false);
            $table->boolean('enabled')->default(true);

            $table->timestamps();

            $table->index('recipient');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ahasend_routes');
    }
};
