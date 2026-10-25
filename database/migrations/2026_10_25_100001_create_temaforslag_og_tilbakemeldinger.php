<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // «Foreslå tema» fra søk uten treff. Like forslag telles opp i stedet
        // for å lage nye rader, så de mest etterspurte står øverst i admin.
        Schema::create('temaforslag', function (Blueprint $table) {
            $table->id();
            $table->string('sokeord', 200);
            $table->string('normalisert', 200)->unique();
            $table->unsignedInteger('antall')->default(1);
            $table->timestamp('sist_foreslatt')->nullable();
            $table->boolean('behandlet')->default(false);
            $table->timestamps();
        });

        // «Meld fra om feil» på et partikort.
        Schema::create('tilbakemeldinger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tema_id')->constrained('temaer')->cascadeOnDelete();
            $table->foreignId('parti_id')->constrained('partier')->cascadeOnDelete();
            $table->foreignId('standpunkt_id')->nullable()->constrained('standpunkter')->nullOnDelete();
            $table->text('tekst');
            $table->boolean('behandlet')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tilbakemeldinger');
        Schema::dropIfExists('temaforslag');
    }
};
