<x-layouts.offentlig tittel="Om og metode" beskrivelse="Hvordan HvaMenerPartiet.no lager og kontrollerer standpunktene, hva skalaen betyr og hvilke svakheter metoden har.">
    <div class="ramme tekstside">
        <h1 class="sidetittel">Om og metode</h1>
        <p class="seksjon__ingress">HvaMenerPartiet.no viser hva partiene skriver i sine egne programmer, tema for tema. Tjenesten anbefaler ikke parti og tolker ikke hva partiene «egentlig» mener.</p>

        <h2>Kildene</h2>
        <p>Standpunktene bygger på partienes arbeidsprogram for stortingsperioden 2025–2029 og deres gjeldende prinsipprogram. Programmene er gjort om fra PDF til tekst, delt i kapitler, avsnitt og setninger, og kontrollert mot originalen. Hver setning har en fast adresse, så sitatene lenker rett til riktig sted. <a href="{{ route('programmer') }}">Se programmene</a>.</p>
        <p>Eldre stortingsprogrammer, lokalvalgprogrammer, sametingsprogrammer og prinsipprogrammer fra 1884 og fram til i dag kommer fra Sikts samling av partidokumenter. Kilde: Sikt – Kunnskapssektorens tjenesteleverandør. (u.å.). <cite>Data om det politiske system</cite>. Hentet 25. oktober 2026, fra <a href="https://polsys.sikt.no/">https://polsys.sikt.no/</a>.</p>

        <h2 id="skalaen">Skalaen</h2>
        <p>Hvert tema har en nøytralt formulert påstand, for eksempel «Norge bør være medlem av NATO». Standpunktet sier hvordan programmet forholder seg til påstanden:</p>
        <x-skala />
        <p>«Ikke omtalt» og «Delt / uklart» er ikke det samme. Det første betyr at programmet ikke sier noe om temaet, det andre at teksten finnes, men peker i flere retninger. Et parti uten prinsipprogram vises som «Ingen prinsipprogram».</p>

        <h2>Arbeidsprogram og prinsipprogram</h2>
        <p>Arbeidsprogrammet sier hva partiet vil gjennomføre i perioden, prinsipprogrammet hva det mener på lang sikt. De vurderes hver for seg, og visningen følger en fast regel:</p>
        <ul>
            <li>Omtaler arbeidsprogrammet temaet, er det merkelappen herfra som vises. Prinsipprogrammet står som «Prinsipielt syn».</li>
            <li>Omtaler bare prinsipprogrammet temaet, vises merkelappen herfra, merket «Fra prinsipprogrammet».</li>
            <li>Peker de to i ulik retning, vises begge merkelappene med merknaden «Langsiktig mål avviker fra planen for perioden».</li>
        </ul>
        <p>Du kan også velge å se bare arbeidsprogrammene eller bare prinsipprogrammene.</p>

        <h2>Slik lages standpunktene</h2>
        <ol>
            <li>For hvert tema finner vi de mest relevante avsnittene i hvert program, med søk på ord og betydning.</li>
            <li>En språkmodell som kjører på vår egen server leser bare disse avsnittene, uten å få vite hvilket parti de kommer fra, og foreslår standpunkt og hvilke setninger som støtter det. Forslaget lages to ganger, og er svarene uenige, settes det til «Delt / uklart».</li>
            <li>Sitatet hentes alltid ordrett fra programteksten, aldri fra modellens egne ord.</li>
            <li>Et menneske kontrollerer hvert standpunkt før det publiseres. Bare kontrollerte standpunkter vises.</li>
        </ol>
        <p>Søket på siden slår bare opp ferdige, kontrollerte svar. Ingenting lages mens du venter, og søker du på et tema som ikke finnes, kan du foreslå det.</p>

        <h2>Sikkerhet</h2>
        <p>Hvert standpunkt har en sikkerhet (høy, middels eller lav) som sier hvor tydelig programmet er. Lav sikkerhet betyr at teksten kan leses på flere måter, og at det er ekstra lurt å lese sitatet selv.</p>

        <h2>Kjente svakheter</h2>
        <ul>
            <li>Et program sier ikke alt. At et parti ikke omtaler et tema, betyr ikke at det ikke har en mening.</li>
            <li>Hvordan påstanden er formulert, påvirker svaret. Derfor står påstanden alltid øverst på temasiden.</li>
            <li>Klassifiseringen kan ta feil, selv etter kontroll. Åpne partiet på temasiden og bruk «Meld fra om feil», så ser vi på det.</li>
            <li>Stemmegivning på Stortinget er ikke med ennå.</li>
        </ul>

        <h2>Åpenhet</h2>
        <p>Koden er åpen (MIT-lisens) på <a href="{{ config('seo.kildekode') }}">GitHub</a>, med designdokument og metode. Alle publiserte standpunkter finnes også samlet som tekst i <a href="{{ url('llms-full.txt') }}">llms-full.txt</a>.</p>
    </div>
</x-layouts.offentlig>
