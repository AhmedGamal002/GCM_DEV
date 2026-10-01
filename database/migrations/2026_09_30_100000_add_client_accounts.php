<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client accounts — FRD V01.14 §1.4 (Project Manager / Project Auditor
 * users that belong to a client company) and the two "representative
 * account" fields on companies (§1.11) and projects (§1.12) that were
 * deferred until these accounts existed.
 *
 * - users.company_id     the client company the account belongs to (null
 *                        for GCM staff, drivers and — later — contractor
 *                        users).
 * - users.all_projects   FRD: "جميع المشروعات" = every project of the
 *                        company, current and future; false = only the
 *                        projects listed in project_user.
 * - users.signature_image / stamp_image  private uploads that end up on
 *                        the trip documents the client approves (FRD:
 *                        "صورة التوقيع" / "صورة الختم التشغيلي").
 * - users.affiliation    gains 'client'.
 * - project_user         the "مشروعات محددة" assignments.
 * - companies/projects.representative_id  the optional representative
 *                        account (nulled if that user is ever removed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('affiliation', ['gcm', 'contractor', 'client'])->default('gcm')->change();

            $table->foreignId('company_id')->nullable()->after('contractor_id')->constrained()->restrictOnDelete();
            $table->boolean('all_projects')->default(false)->after('company_id');
            $table->string('signature_image')->nullable()->after('all_projects');
            $table->string('stamp_image')->nullable()->after('signature_image');
        });

        Schema::create('project_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->unique(['project_id', 'user_id']);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->foreignId('representative_id')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('representative_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('representative_id');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('representative_id');
        });

        Schema::dropIfExists('project_user');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
            $table->dropColumn(['all_projects', 'signature_image', 'stamp_image']);
            $table->enum('affiliation', ['gcm', 'contractor'])->default('gcm')->change();
        });
    }
};
