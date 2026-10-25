<?php

namespace App\Seo;

/**
 * Gir sidene til en rute med parametere, for eksempel én side per publisert
 * tema. Knyttes til ruten med ->sitemap(TemaSider::class).
 */
interface Sitemapkilde
{
    /** @return iterable<Side> */
    public function sider(): iterable;
}
