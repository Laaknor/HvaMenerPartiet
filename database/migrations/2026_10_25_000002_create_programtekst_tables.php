<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kapitler', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('programmer')->cascadeOnDelete();
            $table->foreignId('overordnet_kapittel_id')->nullable()->constrained('kapitler')->cascadeOnDelete();
            // Nummer blant søsken. Hovedkapitler nummereres fra 1, innledning før første overskrift er 0.
            $table->unsignedSmallInteger('nr');
            $table->unsignedTinyInteger('niva')->default(1);
            $table->unsignedInteger('rekkefolge');
            $table->string('tittel', 500);
            $table->string('slug', 40);
            $table->string('del', 20)->nullable();
            $table->unsignedSmallInteger('side')->nullable();
            $table->timestamps();

            $table->index(['program_id', 'rekkefolge']);
        });

        Schema::create('avsnitt', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('programmer')->cascadeOnDelete();
            $table->foreignId('kapittel_id')->constrained('kapitler')->cascadeOnDelete();
            // Hovedkapittelet avsnittet hører til. Avsnittsnummeret telles per
            // hovedkapittel, slik at /kap-4#a12 er entydig.
            $table->foreignId('hovedkapittel_id')->constrained('kapitler')->cascadeOnDelete();
            $table->unsignedInteger('nr');
            $table->unsignedInteger('rekkefolge');
            $table->string('del', 20)->nullable();
            $table->unsignedSmallInteger('side')->nullable();
            $table->unsignedSmallInteger('til_side')->nullable();
            $table->boolean('er_liste')->default(false);
            $table->mediumText('tekst');
            $table->char('tekst_hash', 40);
            $table->timestamps();

            $table->index(['program_id', 'rekkefolge']);
        });

        Schema::create('setninger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('programmer')->cascadeOnDelete();
            $table->foreignId('avsnitt_id')->constrained('avsnitt')->cascadeOnDelete();
            $table->unsignedInteger('nr');
            // Fast ID, for eksempel «rodt-ap2025-k7-a3-s2». Beholdes ved ny
            // import så lenge teksten er uendret.
            $table->string('nokkel', 100)->unique();
            $table->boolean('er_punkt')->default(false);
            $table->mediumText('tekst');
            $table->char('tekst_hash', 40);
            $table->timestamps();

            $table->index(['avsnitt_id', 'nr']);
        });

        // Biter på 200–600 ord brukt til gjenfinning. Bygges på nytt fra
        // avsnittene, og embeddings brukes bare i bakgrunnsjobbene.
        Schema::create('tekstbiter', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('programmer')->cascadeOnDelete();
            $table->foreignId('kapittel_id')->constrained('kapitler')->cascadeOnDelete();
            $table->foreignId('fra_avsnitt_id')->constrained('avsnitt')->cascadeOnDelete();
            $table->foreignId('til_avsnitt_id')->constrained('avsnitt')->cascadeOnDelete();
            $table->string('del', 20)->nullable();
            $table->string('overskriftssti', 1000);
            $table->mediumText('tekst');
            $table->unsignedInteger('antall_ord');
            $table->binary('embedding')->nullable();
            $table->string('embeddingmodell', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tekstbiter');
        Schema::dropIfExists('setninger');
        Schema::dropIfExists('avsnitt');
        Schema::dropIfExists('kapitler');
    }
};
