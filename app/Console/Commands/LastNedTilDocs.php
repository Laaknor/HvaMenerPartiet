<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Laster ned programfilene som mangler i docs/Partiprogram, fra «kilde_url»
 * i database/data/programmer.php. Filene skal deretter committes, så det er
 * repoet som er kilden. Trenger ikke database.
 */
class LastNedTilDocs extends Command
{
    protected $signature = 'program:last-ned-til-docs
        {parti?* : Bare disse partiene (slug, for eksempel h eller rodt)}
        {--bare-gjeldende : Hopp over programmer fra tidligere perioder}
        {--torr : Vis hva som ville blitt lastet ned, uten å laste ned}';

    protected $description = 'Laster ned manglende partiprogram-PDF-er til docs/Partiprogram (gjeldende først)';

    public function handle(): int
    {
        $mappe = rtrim(config('hvamenerpartiet.digitalisering.programmappe'), '/');
        $partier = $this->argument('parti');

        $programmer = collect(require database_path('data/programmer.php'))
            ->filter(fn (array $p) => ($p['kildefil'] ?? null) && ($p['kilde_url'] ?? null))
            ->filter(fn (array $p) => ! $partier || in_array($p['parti'], $partier, true))
            ->filter(fn (array $p) => ! $this->option('bare-gjeldende') || ($p['gjeldende'] ?? true))
            // Gjeldende først, så nyeste periode.
            ->sortBy([fn ($a, $b) => ($b['gjeldende'] ?? true) <=> ($a['gjeldende'] ?? true), fn ($a, $b) => ($b['vedtatt_ar'] ?? 0) <=> ($a['vedtatt_ar'] ?? 0)])
            ->values();

        $hentet = 0;
        $feilet = [];

        foreach ($programmer as $p) {
            $sti = $mappe.'/'.$p['kildefil'];
            if ($this->finnes($sti)) {
                continue;
            }

            if ($this->option('torr')) {
                $this->line("Mangler: {$p['kildefil']}  ←  {$p['kilde_url']}");

                continue;
            }

            try {
                $svar = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; HvaMenerPartiet/1.0; +https://github.com/laaknor/hvamenerpartiet)',
                    'Accept' => 'application/pdf,*/*',
                ])->timeout(180)->retry(2, 2000, throw: false)->get($p['kilde_url']);

                if (! $svar->successful()) {
                    throw new \RuntimeException("status {$svar->status()}");
                }
                if (! str_starts_with($svar->body(), '%PDF')) {
                    throw new \RuntimeException('svaret er ikke en PDF');
                }

                if (! is_dir(dirname($sti))) {
                    mkdir(dirname($sti), 0775, true);
                }
                file_put_contents($sti, $svar->body());
                $hentet++;
                $this->info("Lastet ned: {$p['kildefil']}");
            } catch (Throwable $e) {
                $feilet[] = $p;
                $this->error("Feilet: {$p['kildefil']} ({$e->getMessage()})");
            }
        }

        $this->newLine();
        $this->line("{$hentet} filer lastet ned, ".count($feilet).' feilet.');
        foreach ($feilet as $p) {
            $this->line("  Last ned manuelt: {$p['kilde_url']}");
            $this->line("  og lagre som:     docs/Partiprogram/{$p['kildefil']}");
        }
        if ($hentet) {
            $this->line('Commit filene, og kjør «php artisan db:seed --class=ProgramSeeder» for å koble dem til programmene.');
        }

        return $feilet ? self::FAILURE : self::SUCCESS;
    }

    private function finnes(string $sti): bool
    {
        if (is_file($sti)) {
            return true;
        }
        if (! class_exists(\Normalizer::class)) {
            return false;
        }

        foreach ([\Normalizer::FORM_C, \Normalizer::FORM_D] as $form) {
            $variant = \Normalizer::normalize($sti, $form);
            if ($variant && is_file($variant)) {
                return true;
            }
        }

        return false;
    }
}
