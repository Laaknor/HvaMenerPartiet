<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // «Send til feilsøking» i admin. Pakken er et øyeblikksbilde av det
        // som trengs for å feilsøke, satt sammen fra en fast liste med felt.
        Schema::create('feilsokingssaker', function (Blueprint $table) {
            $table->id();
            $table->uuid('referanse')->unique();
            $table->string('gjelder_type');
            $table->unsignedBigInteger('gjelder_id');
            $table->text('notat')->nullable();
            $table->json('pakke');
            $table->unsignedInteger('github_issue')->nullable();
            $table->string('github_feil')->nullable();
            $table->boolean('lost')->default(false);
            $table->timestamp('sist_hentet')->nullable();
            $table->foreignId('opprettet_av')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['gjelder_type', 'gjelder_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feilsokingssaker');
    }
};
