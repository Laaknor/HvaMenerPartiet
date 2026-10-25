<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partier', function (Blueprint $table) {
            $table->id();
            $table->string('navn');
            $table->string('forkortelse', 20);
            $table->string('slug', 40)->unique();
            $table->string('farge', 7)->nullable();
            $table->string('nettside')->nullable();
            $table->date('aktiv_fra')->nullable();
            $table->date('aktiv_til')->nullable();
            $table->timestamps();
        });

        Schema::create('programmer', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parti_id')->constrained('partier')->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('tittel');
            // Brukes i lenker: /program/sv/arbeidsprogram-2025
            $table->string('slug', 80);
            // Prefiks for faste setnings-ID-er, for eksempel «sv-ap2025».
            $table->string('nokkel', 60)->unique();
            $table->string('periode', 20)->nullable();
            $table->unsignedSmallInteger('vedtatt_ar')->nullable();
            $table->boolean('gjeldende')->default(true);
            $table->string('sprak', 5)->default('nb');
            $table->text('kilde_url')->nullable();
            $table->text('kilde_side')->nullable();
            $table->string('filsti')->nullable();
            $table->char('filhash', 64)->nullable();
            $table->timestamp('lastet_ned')->nullable();
            $table->string('digitalisering_status', 20)->default('registrert');
            $table->text('digitalisering_merknad')->nullable();
            $table->unsignedSmallInteger('antall_sider')->nullable();
            $table->timestamp('digitalisert')->nullable();
            $table->timestamp('godkjent')->nullable();
            $table->timestamps();

            $table->unique(['parti_id', 'slug', 'sprak']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programmer');
        Schema::dropIfExists('partier');
    }
};
