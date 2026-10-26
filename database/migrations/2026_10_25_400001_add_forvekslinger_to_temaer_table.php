<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nærliggende begreper som ikke er temaet, for eksempel «atomvåpen»
        // for temaet atomkraft. Brukes i gjenfinningen og i prompten.
        Schema::table('temaer', function (Blueprint $table) {
            $table->json('forvekslinger')->nullable()->after('synonymer');
        });
    }

    public function down(): void
    {
        Schema::table('temaer', function (Blueprint $table) {
            $table->dropColumn('forvekslinger');
        });
    }
};
