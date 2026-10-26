<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Verdiene TemaSeeder sist skrev. Seederen kjøres ved hver deploy, og
        // bruker dem til å se om et felt er endret i admin siden, så det ikke
        // overskrives.
        Schema::table('temaer', function (Blueprint $table) {
            $table->json('seedverdier')->nullable()->after('embeddingmodell');
        });
    }

    public function down(): void
    {
        Schema::table('temaer', function (Blueprint $table) {
            $table->dropColumn('seedverdier');
        });
    }
};
