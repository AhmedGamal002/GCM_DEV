<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Part 7.3 — the e-mailed one-time codes. One row per code sent.
 *
 *  - `scope` ties a code, and the verification it gives, to ONE login: the
 *    session (sha256 of its id) or the API token ("token:<id>"). That is what
 *    makes "once per session" true: a new login, or a token, starts unverified.
 *  - only a keyed hash of the code is stored (`code_hash`), never the code.
 *  - `attempts` counts the wrong tries; `invalidated_at` is set when a newer
 *    code replaces it or the tries run out; `verified_at` when it is accepted
 *    (the row then stays as the proof that the scope is verified).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('scope', 80);
            $table->string('code_hash', 64);
            $table->string('purpose', 100)->nullable();

            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('last_sent_at');
            $table->timestamp('expires_at');
            $table->timestamp('invalidated_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'scope']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_challenges');
    }
};
