<?php

namespace App\Ollama;

use RuntimeException;

/** Modellen svarte, men ikke med gyldig JSON. Andre feil (nett, tidsavbrudd) kastes videre så jobben prøver igjen. */
class UgyldigSvar extends RuntimeException {}
