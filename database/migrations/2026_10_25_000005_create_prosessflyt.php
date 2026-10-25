<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programmer', function (Blueprint $table) {
            // Filen i docs/Partiprogram som er kilden, for eksempel
            // «Stortingsprogram 2025-2029/SV-arbeidsprogram-2025-interaktiv-bokmal.pdf».
            $table->string('kildefil', 500)->nullable()->after('kilde_side');
        });

        // Én rad per jobb som er startet, automatisk eller fra admin, slik at
        // flyten kan følges og ledig kapasitet regnes ut.
        Schema::create('prosesskjoringer', function (Blueprint $table) {
            $table->id();
            $table->string('steg', 20);
            $table->foreignId('program_id')->nullable()->constrained('programmer')->cascadeOnDelete();
            $table->foreignId('tema_id')->nullable()->constrained('temaer')->cascadeOnDelete();
            $table->string('kildetype', 20)->nullable();
            $table->string('status', 20)->default('i_ko');
            $table->string('utlost_av', 20);
            $table->foreignId('bruker_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('forsok')->default(0);
            $table->text('melding')->nullable();
            $table->timestamp('startet')->nullable();
            $table->timestamp('ferdig')->nullable();
            $table->timestamps();

            $table->index(['status', 'steg']);
            $table->index(['steg', 'program_id', 'tema_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prosesskjoringer');
        Schema::table('programmer', fn (Blueprint $table) => $table->dropColumn('kildefil'));
    }
};
