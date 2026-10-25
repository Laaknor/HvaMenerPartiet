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
