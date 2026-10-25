<?php

namespace App\Flyt;

use App\Models\Program;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/** PDF-ene i docs/Partiprogram, som er kilden for alle programmene. */
class Programmappe
{
    public function sti(): string
    {
        return rtrim(config('hvamenerpartiet.digitalisering.programmappe'), '/');
    }

    /** @return list<string> stier relativt til mappen, sortert */
    public function filer(): array
    {
        $mappe = $this->sti();
        if (! is_dir($mappe)) {
            return [];
        }

        $filer = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($mappe, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($iterator as $fil) {
            if ($fil->isFile() && strtolower($fil->getExtension()) === 'pdf') {
                $filer[] = $this->normaliser(substr($fil->getPathname(), strlen($mappe) + 1));
            }
        }
        sort($filer);

        return $filer;
    }

    /** PDF-er som ingen programmer peker på. @return list<string> */
    public function utenProgram(): array
    {
        $brukt = Program::query()->whereNotNull('kildefil')->pluck('kildefil')->map(fn ($f) => $this->normaliser($f))->flip();

        return array_values(array_filter($this->filer(), fn ($f) => ! $brukt->has($f)));
    }

    private function normaliser(string $sti): string
    {
        return class_exists(\Normalizer::class) ? (\Normalizer::normalize($sti, \Normalizer::FORM_C) ?: $sti) : $sti;
    }
}
