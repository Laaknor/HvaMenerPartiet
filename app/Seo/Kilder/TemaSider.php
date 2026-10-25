<?php

namespace App\Seo\Kilder;

use App\Enums\Temastatus;
use App\Models\Tema;
use App\Seo\Side;
use App\Seo\Sitemapkilde;

/** Én side per publisert tema. */
class TemaSider implements Sitemapkilde
{
    public function sider(): iterable
    {
        foreach (Tema::query()->where('status', Temastatus::Publisert)->orderBy('navn')->get() as $tema) {
            yield new Side(
                url: route('tema', $tema),
                tittel: "Hva mener partiene om {$tema->navn}?",
                beskrivelse: "Påstand: «{$tema->pastand}»",
                sistEndret: $tema->updated_at,
                endringsfrekvens: 'weekly',
                prioritet: 0.8,
            );
        }
    }
}
