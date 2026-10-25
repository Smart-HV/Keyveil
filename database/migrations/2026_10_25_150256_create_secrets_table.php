<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Only ciphertext and lifecycle metadata are stored. No IP, User-Agent,
     * referrer or soft-delete columns by design.
     */
    public function up(): void
    {
        Schema::create('secrets', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 22)->unique();
            $table->smallInteger('crypto_version');
            $table->jsonb('envelope');
            $table->boolean('password_protected')->default(false);
            $table->timestampTz('expires_at')->index();
            $table->integer('max_views');
            $table->integer('view_count')->default(0);
            $table->char('delete_token_hash', 64);
            $table->timestampsTz();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE secrets
                    ADD CONSTRAINT secrets_max_views_check CHECK (max_views >= 1),
                    ADD CONSTRAINT secrets_view_count_check CHECK (view_count >= 0 AND view_count < max_views),
                    ADD CONSTRAINT secrets_crypto_version_check CHECK (crypto_version > 0)
                SQL);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('secrets');
    }
};
