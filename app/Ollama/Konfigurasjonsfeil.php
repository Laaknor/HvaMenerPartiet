<?php

namespace App\Ollama;

use RuntimeException;

/**
 * Serveren er satt opp feil (for eksempel uten støtte for embeddings).
 * Et nytt forsøk hjelper ikke, så jobben feiler med en gang.
 */
class Konfigurasjonsfeil extends RuntimeException {}
