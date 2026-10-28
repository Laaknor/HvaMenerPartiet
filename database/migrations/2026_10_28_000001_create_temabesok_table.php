<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Besøk på temasidene, telt per dag. Brukes til «mest besøkte» på
        // forsiden. Ingen opplysninger om den besøkende lagres.
        Schema::create('temabesok', function (Blueprint $table) {
            $table->foreignId('tema_id')->constrained('temaer')->cascadeOnDelete();
            $table->date('dato');
            $table->unsignedInteger('antall')->default(0);
            $table->primary(['tema_id', 'dato']);
            $table->index('dato');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temabesok');
    }
};
