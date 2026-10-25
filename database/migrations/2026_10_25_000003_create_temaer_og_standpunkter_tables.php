<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('temaer', function (Blueprint $table) {
            $table->id();
            $table->string('navn');
            $table->string('slug')->unique();
            $table->text('beskrivelse')->nullable();
            // Påstanden standpunktet måles mot, for eksempel «Norge bør være medlem av NATO».
            $table->string('pastand', 500);
            $table->json('synonymer')->nullable();
            $table->foreignId('overordnet_tema_id')->nullable()->constrained('temaer')->nullOnDelete();
            $table->string('status', 20)->default('foreslatt');
            $table->binary('embedding')->nullable();
            $table->string('embeddingmodell', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('standpunkter', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tema_id')->constrained('temaer')->cascadeOnDelete();
            $table->foreignId('program_id')->constrained('programmer')->cascadeOnDelete();
            // Arbeidsprogram eller prinsipprogram. For kombinerte dokumenter
            // angir den hvilken del standpunktet bygger på.
            $table->string('kildetype', 20);
            $table->string('verdi', 20);
            $table->string('sikkerhet', 10);
            $table->text('begrunnelse')->nullable();
            $table->boolean('uenighet')->default(false);
            // Svarene fra hver kjøring, for kontroll i admin.
            $table->json('kjoringer')->nullable();
            $table->json('merknader')->nullable();
            $table->string('modell', 100)->nullable();
            $table->string('prompt_versjon', 40)->nullable();
            $table->string('status', 20)->default('maskinell');
            $table->foreignId('godkjent_av')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('godkjent_tid')->nullable();
            $table->timestamps();

            $table->unique(['tema_id', 'program_id', 'kildetype']);
        });

        // Setningene som støtter standpunktet. Én rad per setning (i stedet for
        // fra/til-intervall), slik at kildene overlever en ny import der
        // setninger flyttes. Intervaller for lenker regnes ut ved visning.
        Schema::create('standpunkt_kilder', function (Blueprint $table) {
            $table->id();
            $table->foreignId('standpunkt_id')->constrained('standpunkter')->cascadeOnDelete();
            $table->foreignId('setning_id')->constrained('setninger')->cascadeOnDelete();
            $table->unsignedSmallInteger('rekkefolge');

            $table->unique(['standpunkt_id', 'setning_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('standpunkt_kilder');
        Schema::dropIfExists('standpunkter');
        Schema::dropIfExists('temaer');
    }
};
