// Offentlige sider. Alt virker uten JavaScript; dette er forbedringer.

// Lenker til siterte setninger: …/kap-4#a12-s3-s5 markerer setning 3 til 5 i
// avsnitt 12, og #a12-s3 bare setning 3. #a12 ruller til avsnittet.
function markerSetninger() {
    document.querySelectorAll('.setning--markert, .avsnitt--markert').forEach((el) => {
        el.classList.remove('setning--markert', 'avsnitt--markert');
    });

    const treff = /^#a(\d+)(?:-s(\d+)(?:-s(\d+))?)?$/.exec(window.location.hash);
    if (!treff) return;

    const avsnitt = document.getElementById(`a${treff[1]}`);
    if (!avsnitt) return;

    let forste = avsnitt;
    if (treff[2]) {
        const fra = Number(treff[2]);
        const til = Number(treff[3] ?? treff[2]);
        let funnet = null;
        for (let nr = Math.min(fra, til); nr <= Math.max(fra, til); nr++) {
            const setning = document.getElementById(`a${treff[1]}-s${nr}`);
            if (setning) {
                setning.classList.add('setning--markert');
                funnet ??= setning;
            }
        }
        forste = funnet ?? avsnitt;
    } else {
        avsnitt.classList.add('avsnitt--markert');
    }

    forste.scrollIntoView({ block: 'center' });
}

if (document.querySelector('.programtekst')) {
    window.addEventListener('hashchange', markerSetninger);
    markerSetninger();
}

// Søk: et valg fra autofullføringen spores som «Velg forslag» i Matomo.
document.querySelectorAll('[data-temasok]').forEach((skjema) => {
    skjema.addEventListener('submit', () => {
        const felt = skjema.querySelector('input[name="q"]');
        const liste = skjema.querySelector('datalist');
        if (!felt || !liste || !window.HvaMenerPartiet?.spor) return;
        const valgt = Array.from(liste.options).some((o) => o.value.toLowerCase() === felt.value.trim().toLowerCase());
        if (valgt) window.HvaMenerPartiet.spor('Søk', 'Velg forslag', felt.value.trim());
    });
});

// Temasiden: en lenke til #parti-12 åpner raden for partiet.
function apnePartirad() {
    const rad = window.location.hash.startsWith('#parti-') ? document.getElementById(window.location.hash.slice(1)) : null;
    if (rad && rad.tagName === 'DETAILS') rad.open = true;
}
if (document.querySelector('.partirad')) {
    window.addEventListener('hashchange', apnePartirad);
    apnePartirad();
}

// Forsiden: siden caches, så de tilfeldige temaene trekkes her ved hver visning.
document.querySelectorAll('[data-oversikt][data-tilfeldige]').forEach((oversikt) => {
    const antall = Number(oversikt.dataset.tilfeldige);
    const rader = Array.from(oversikt.querySelectorAll('tr[data-gruppe="tilfeldig"]'));
    for (let i = rader.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [rader[i], rader[j]] = [rader[j], rader[i]];
    }
    rader.forEach((rad, i) => {
        rad.hidden = i >= antall;
        rad.parentNode.appendChild(rad);
    });
});

// Alle temaer: sammenlign utvalgte partier. Valget står i adressen
// (#partier=ap,h&uenige), så lenken kan deles uten å lage nye sider i cachen.
document.querySelectorAll('[data-oversikt]').forEach((oversikt) => {
    const velger = oversikt.querySelector('[data-partivelger]');
    if (!velger) return;
    velger.hidden = false;

    const valg = Array.from(velger.querySelectorAll('[data-parti-valg]'));
    const uenige = velger.querySelector('[data-bare-uenige]');
    const nullstill = velger.querySelector('[data-nullstill]');

    function lesAdresse() {
        const parametere = new URLSearchParams(window.location.hash.slice(1));
        const valgte = (parametere.get('partier') || '').split(',').filter(Boolean);
        valg.forEach((boks) => { boks.checked = valgte.includes(boks.value); });
        uenige.checked = parametere.has('uenige');
    }

    function oppdater(skrivAdresse) {
        const valgte = valg.filter((b) => b.checked).map((b) => b.value);
        const filtrer = valgte.length > 0;

        oversikt.querySelectorAll('[data-parti]').forEach((celle) => {
            celle.toggleAttribute('data-skjult-parti', filtrer && !valgte.includes(celle.dataset.parti));
        });

        oversikt.querySelectorAll('tbody tr').forEach((rad) => {
            let skjul = false;
            if (uenige.checked && valgte.length > 1) {
                // «For» og «Delvis for» regnes som samme retning. Partier som ikke
                // omtaler temaet eller ikke er kontrollert, teller ikke.
                const retninger = new Set(
                    valgte
                        .map((slug) => rad.querySelector(`td[data-parti="${slug}"]`)?.dataset.verdi)
                        .filter((v) => ['for', 'delvis_for', 'mot', 'delt_uklart'].includes(v))
                        .map((v) => (v === 'delvis_for' ? 'for' : v)),
                );
                skjul = retninger.size < 2;
            }
            rad.hidden = skjul;
        });

        uenige.disabled = valgte.length < 2;
        nullstill.hidden = !filtrer;

        if (skrivAdresse) {
            const deler = [];
            if (filtrer) deler.push(`partier=${valgte.join(',')}`);
            if (filtrer && uenige.checked) deler.push('uenige');
            history.replaceState(null, '', deler.length ? `#${deler.join('&')}` : window.location.pathname + window.location.search);
            if (filtrer && window.HvaMenerPartiet?.spor) window.HvaMenerPartiet.spor('Oversikt', 'Sammenlign', valgte.join(','));
        }
    }

    valg.forEach((boks) => boks.addEventListener('change', () => oppdater(true)));
    uenige.addEventListener('change', () => oppdater(true));
    nullstill.addEventListener('click', () => {
        valg.forEach((b) => { b.checked = false; });
        uenige.checked = false;
        oppdater(true);
    });

    lesAdresse();
    oppdater(false);
});
