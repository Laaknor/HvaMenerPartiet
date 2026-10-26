<?php

/*
| Programmene for stortingsperioden 2025–2029, fra kildelisten
| «partiprogrammer-2025-2029» (sist sjekket 25.10.2026). Første versjon
| bruker bokmålsutgaven.
|
| Ap har ikke et gjeldende prinsipprogram, og vises med «ingen prinsipprogram».
| FrP og Sp har prinsipper og handlingsprogram i samme dokument (kombinert).
| For FrP er det ikke bekreftet at dokumentet har en prinsippdel.
|
| «kildefil» er stien under docs/Partiprogram i repoet. Det er filene der
| systemet leser, ikke nettsidene. Seederen kobler programmet til filen bare
| når filen faktisk ligger der, så en sti kan stå her før PDF-en er lagt inn.
| «kilde_url» er hvor filen kommer fra, og brukes av
| «php artisan program:last-ned-til-docs» til å hente filer som mangler.
|
| Programmer fra tidligere stortingsperioder står med «gjeldende» => false
| nederst. De vises som eldre programmer og behandles bare når ingen
| gjeldende programmer venter i prosessflyten.
*/

return [
    [
        'parti' => 'ap', 'type' => 'arbeidsprogram', 'tittel' => 'Partiprogram 2025–2029', 'periode' => '2025-2029', 'vedtatt_ar' => 2025,
        'kildefil' => 'Stortingsprogram 2025-2029/AP-partiprogram-2026-2029.pdf',
        'kilde_url' => 'https://res.cloudinary.com/arbeiderpartiet/image/upload/fl_attachment:partiprogram-2025-2029/v1/ievv_filestore/130322a45ccf45889bfc6fa4116c5b480cadc1e93e51491f8199451dd231c7c5',
        'kilde_side' => 'https://www.arbeiderpartiet.no/om/program/',
    ],
    [
        'parti' => 'frp', 'type' => 'kombinert', 'tittel' => 'Program 2025–2029', 'periode' => '2025-2029', 'vedtatt_ar' => 2025,
        'kildefil' => 'Stortingsprogram 2025-2029/FRP-Program-2025-2029.pdf',
        'kilde_url' => 'https://www.frp.no/files/Program/2025/Program-2025-2029.pdf',
        'kilde_side' => 'https://www.frp.no',
    ],
    [
        'parti' => 'h', 'type' => 'arbeidsprogram', 'tittel' => 'Stortingsvalgprogram 2025–2029', 'periode' => '2025-2029', 'vedtatt_ar' => 2025,
        'kildefil' => 'Stortingsprogram 2025-2029/Hoyres_stortingsvalgprogram_2025-2029_-_tekstversjon.pdf',
        'kilde_url' => 'https://hoyre.no/content/uploads/sites/83/2025/04/Hoyres_stortingsvalgprogram_2025-2029_-_tekstversjon.pdf',
    ],
    [
        'parti' => 'h', 'type' => 'prinsipprogram', 'tittel' => 'Høyres prinsipprogram', 'vedtatt_ar' => 2019,
        'kildefil' => 'Stortingsprogram 2025-2029/Hoyres-prinsipprogram-2019.pdf',
        'kilde_url' => 'https://hoyre.no/content/uploads/2021/09/Hoyres-prinsipprogram-2019.pdf',
    ],
    [
        'parti' => 'sv', 'type' => 'arbeidsprogram', 'tittel' => 'Arbeidsprogram 2025–2029', 'periode' => '2025-2029', 'vedtatt_ar' => 2025,
        'kildefil' => 'Stortingsprogram 2025-2029/SV-arbeidsprogram-2025-interaktiv-bokmal.pdf',
        'kilde_url' => 'https://www.sv.no/wp-content/uploads/2025/06/arbeidsprogram-2025-2029-pa-bokmal.pdf',
    ],
    [
        'parti' => 'sv', 'type' => 'prinsipprogram', 'tittel' => 'Prinsipprogram', 'vedtatt_ar' => 2023,
        'kildefil' => 'Stortingsprogram 2025-2029/SV-prinsipprogram-vedtatt-2023-bokmal.pdf',
        'kilde_url' => 'https://www.sv.no/wp-content/uploads/2026/08/prinsipprogram-vedtatt-2023-bokmal.pdf',
        'kilde_side' => 'https://www.sv.no/politikken/prinsipprogram/',
    ],
    [
        'parti' => 'sp', 'type' => 'kombinert', 'tittel' => 'Prinsipp- og handlingsprogram 2025–2029', 'periode' => '2025-2029', 'vedtatt_ar' => 2025,
        'kildefil' => 'Stortingsprogram 2025-2029/Senterpartiets stortingsvalgprogram 2025-2029 bokmål i PDF.pdf',
        'kilde_url' => 'https://www.senterpartiet.no/politikk/program-uttaler/Nytt%20prinsipp-%20og%20handlingsprogram%202025-2029/_/attachment/download/ea7e4063-1374-47bf-8b10-839bf68904c0:3c54bc9ec99b5e017f069f2312b3962bd934c78a/Senterpartiets%20stortingsvalgprogram%202025-2029%20bokm%C3%A5l%20i%20PDF.pdf',
    ],
    [
        'parti' => 'rodt', 'type' => 'arbeidsprogram', 'tittel' => 'Arbeidsprogram 2025–2029', 'periode' => '2025-2029', 'vedtatt_ar' => 2025,
        'kildefil' => 'Stortingsprogram 2025-2029/Rodt - arbeidsprogram25-29_skjerm.pdf',
        'kilde_url' => 'https://roedt.no/fil/7393ab981e025ec77c5c78797892dfc9f020fc17.pdf?dl=',
        'kilde_side' => 'https://roedt.no/arbeidsprogram',
    ],
    [
        'parti' => 'rodt', 'type' => 'prinsipprogram', 'tittel' => 'Prinsipprogram', 'vedtatt_ar' => 2019,
        'kildefil' => 'Stortingsprogram 2025-2029/Rodt - prinsipprogram2019.pdf',
        'kilde_url' => 'https://roedt.no/fil/f60b95dfc54397a984de84cd600dbbedd2bf8745.pdf?dl=',
        'kilde_side' => 'https://roedt.no/styringsdokumenter',
    ],
    [
        'parti' => 'mdg', 'type' => 'arbeidsprogram', 'tittel' => 'Arbeidsprogram 2025–2029', 'periode' => '2025-2029', 'vedtatt_ar' => 2025,
        'kildefil' => 'Stortingsprogram 2025-2029/MDGs+arbeidsprogram+2025-2029.pdf',
        'kilde_url' => 'https://mdg.no/_service/505809/download/id/1506077/name/MDGs+arbeidsprogram+2025-2029.pdf',
        'kilde_side' => 'https://www.mdg.no/program2025',
    ],
    [
        // Vedtaksåret er ikke oppgitt i kildelisten og må finnes.
        'parti' => 'mdg', 'type' => 'prinsipprogram', 'tittel' => 'Prinsipprogram for MDG', 'vedtatt_ar' => null,
        'kildefil' => 'Stortingsprogram 2025-2029/MDG-prinsipprogram.pdf',
        'kilde_url' => 'https://mdg.no/_service/505809/download/id/1551129/name/Prinsipprogram+for+MDG.pdf',
        'kilde_side' => 'https://www.mdg.no/partiprogram_og_dybdeinformasjon',
    ],
    [
        'parti' => 'krf', 'type' => 'arbeidsprogram', 'tittel' => 'Stortingsprogram 2025–2029', 'periode' => '2025-2029', 'vedtatt_ar' => 2025,
        'kildefil' => 'Stortingsprogram 2025-2029/KrF-Partiprogram-2025-2029.pdf',
        'kilde_url' => 'https://krf.no/content/uploads/2020/09/Partiprogram-2025-2029.pdf',
    ],
    [
        'parti' => 'krf', 'type' => 'prinsipprogram', 'tittel' => 'KrFs prinsipprogram', 'vedtatt_ar' => 2023,
        'kildefil' => 'Stortingsprogram 2025-2029/KrF-Prinsipprogram-2023.pdf',
        'kilde_url' => 'https://krf.no/content/uploads/2020/09/KrF-Prinsipprogram-2023.docx.pdf',
    ],
    [
        'parti' => 'v', 'type' => 'arbeidsprogram', 'tittel' => 'Stortingsvalgprogram 2025–2029', 'periode' => '2025-2029', 'vedtatt_ar' => 2025,
        'kildefil' => 'Stortingsprogram 2025-2029/venstre-stortingsprogram-2025-2029.pdf',
        'kilde_url' => 'https://www.venstre.no/assets/venstre-stortingsprogram-2025-2029.pdf',
    ],
    [
        'parti' => 'v', 'type' => 'prinsipprogram', 'tittel' => 'Venstres prinsipprogram', 'vedtatt_ar' => 2020,
        'kildefil' => 'Stortingsprogram 2025-2029/Venstres-prinsipprogram-2020.pdf',
        'kilde_url' => 'https://www.venstre.no/assets/Venstres-prinsipprogram-2020.pdf',
    ],
    /*
    | Tidligere stortingsperioder (lav prioritet i prosessflyten).
    | Programmer som ikke er funnet på nett, står ikke her. Se
    | docs/Partiprogram/kildeliste.md for hva som mangler.
    */

    // Stortingsperioden 2021–2025
    [
        'parti' => 'ap', 'type' => 'arbeidsprogram', 'tittel' => 'Partiprogram 2021–2025', 'periode' => '2021-2025', 'vedtatt_ar' => 2021, 'gjeldende' => false,
        'kildefil' => 'Stortingsprogram 2021-2025/Ap-partiprogram-2021-2025-bokmal.pdf',
        'kilde_url' => 'https://res.cloudinary.com/arbeiderpartiet/image/upload/fl_attachment:arbeiderpartiets-partiprogram-2021-2025-bokmal/v1/ievv_filestore/c026d552300245c19306ee41345b764f88183fbd93b84cb284899008282b1e37',
        'kilde_side' => 'https://www.arbeiderpartiet.no/om/historien-om-arbeiderpartiet/historiske-partiprogrammer/',
    ],
    [
        'parti' => 'frp', 'type' => 'kombinert', 'tittel' => 'Program 2021–2025', 'periode' => '2021-2025', 'vedtatt_ar' => 2021, 'gjeldende' => false,
        'kildefil' => 'Stortingsprogram 2021-2025/FrP-Vedtatt-program-for-2021-2025.pdf',
        'kilde_url' => 'https://www.frp.no/files/Landsmote/2021/Vedtatt-program-for-2021-2025.pdf',
    ],
    [
        'parti' => 'h', 'type' => 'arbeidsprogram', 'tittel' => 'Stortingsvalgprogram 2021–2025', 'periode' => '2021-2025', 'vedtatt_ar' => 2021, 'gjeldende' => false,
        'kildefil' => 'Stortingsprogram 2021-2025/Hoyres-program-2021-2025.pdf',
        'kilde_url' => 'https://res.cloudinary.com/hoyre/images/v1646396541/Nye%20hoyre.no/Hovedside/Hoyres-program-2021-2025/Hoyres-program-2021-2025.pdf',
    ],
    [
        'parti' => 'sv', 'type' => 'arbeidsprogram', 'tittel' => 'Arbeidsprogram 2021–2025', 'periode' => '2021-2025', 'vedtatt_ar' => 2021, 'gjeldende' => false,
        'kildefil' => 'Stortingsprogram 2021-2025/SV-arbeidsprogram-2021-2025-bokmal.pdf',
        'kilde_url' => 'https://www.sv.no/wp-content/uploads/2023/09/arbeidsprogram-21-25-bokm%C3%A5l.pdf',
    ],
    [
        'parti' => 'sp', 'type' => 'kombinert', 'tittel' => 'Prinsipp- og handlingsprogram 2021–2025', 'periode' => '2021-2025', 'vedtatt_ar' => 2021, 'gjeldende' => false,
        'kildefil' => 'Stortingsprogram 2021-2025/Sp-prinsipp-og-handlingsprogram-2021-2025-bokmal.pdf',
        'kilde_url' => 'https://www.senterpartiet.no/politikk/program-uttaler/program/Stortingsprogram-2021-2025-bokmaal/_/attachment/download/61cec2b8-738a-4b65-b3a1-c432728c7ac0:ee197aac4238ac919ac4406e9bcdb42d7e44df24/Prinsipp-%20og%20handlingsprogram%202021-2024%20bokma%CC%8Al%20nett.pdf',
        'kilde_side' => 'https://www.senterpartiet.no/politikk/program-uttaler/program/Stortingsprogram-2021-2025-bokmaal',
    ],
    [
        // Ikke på roedt.no lenger. Kopien ligger i pdf-arkivet.no, som samler RV/Rødt-dokumenter.
        'parti' => 'rodt', 'type' => 'arbeidsprogram', 'tittel' => 'Arbeidsprogram 2021–2025', 'periode' => '2021-2025', 'vedtatt_ar' => 2021, 'gjeldende' => false,
        'kildefil' => 'Stortingsprogram 2021-2025/Rodt-arbeidsprogram-2021-2025-bokmal.pdf',
        'kilde_url' => 'https://www.pdf-arkivet.no/rv/program/arbeidsprogram_bokmal_2021.pdf',
        'kilde_side' => 'https://www.pdf-arkivet.no/rv/index.html',
    ],
    [
        // Ikke på mdg.no lenger. Kopien er fra Eikernytt og bør sjekkes mot originalen.
        'parti' => 'mdg', 'type' => 'arbeidsprogram', 'tittel' => 'Arbeidsprogram 2021–2025', 'periode' => '2021-2025', 'vedtatt_ar' => 2021, 'gjeldende' => false,
        'kildefil' => 'Stortingsprogram 2021-2025/MDG-arbeidsprogram-2021-2025.pdf',
        'kilde_url' => 'https://www.eikernytt.no/wp-content/uploads/2021/07/Arbeidsprogram_MDG_2021-2025.pdf',
    ],
    [
        'parti' => 'krf', 'type' => 'arbeidsprogram', 'tittel' => 'Stortingsprogram 2021–2025', 'periode' => '2021-2025', 'vedtatt_ar' => 2021, 'gjeldende' => false,
        'kildefil' => 'Stortingsprogram 2021-2025/KrF-stortingsprogram-2021-2025.pdf',
        'kilde_url' => 'https://krf.no/content/uploads/2020/09/KrFs-stortingsprogram-2021-2025-3.pdf',
    ],
    [
        'parti' => 'v', 'type' => 'arbeidsprogram', 'tittel' => 'Stortingsvalgprogram 2021–2025', 'periode' => '2021-2025', 'vedtatt_ar' => 2021, 'gjeldende' => false,
        'kildefil' => 'Stortingsprogram 2021-2025/Venstres-stortingsvalgprogram-2021-2025.pdf',
        'kilde_url' => 'https://www.venstre.no/assets/Venstres_Stortingsvalgprogram_2021-2025.pdf',
    ],

    // Stortingsperioden 2017–2021
    [
        'parti' => 'ap', 'type' => 'arbeidsprogram', 'tittel' => 'Partiprogram 2017–2021', 'periode' => '2017-2021', 'vedtatt_ar' => 2017, 'gjeldende' => false,
        'kildefil' => 'Stortingsprogram 2017-2021/Ap-partiprogram-2017-2021-bokmal.pdf',
        'kilde_url' => 'https://res.cloudinary.com/arbeiderpartiet/image/upload/fl_attachment:arbeiderpartiets-partiprogram-2017-2021-bokmal/v1/ievv_filestore/712bd5b874e24958b84c14fbef5b1a98afbfc8ae1e464cac9bcad06f24f2a85f',
        'kilde_side' => 'https://www.arbeiderpartiet.no/om/historien-om-arbeiderpartiet/historiske-partiprogrammer/',
    ],
    [
        'parti' => 'h', 'type' => 'arbeidsprogram', 'tittel' => 'Stortingsvalgprogram 2017–2021', 'periode' => '2017-2021', 'vedtatt_ar' => 2017, 'gjeldende' => false,
        'kildefil' => 'Stortingsprogram 2017-2021/Hoyres-partiprogram-2017-2021.pdf',
        'kilde_url' => 'https://res.cloudinary.com/hoyre/images/v1695041335/Nye%20hoyre.no/Hovedside/Hoyres-partiprogram-2017-2021_1210997706/Hoyres-partiprogram-2017-2021_1210997706.pdf',
    ],
    [
        // Filen heter «vedtatt, ikke korrekturlest».
        'parti' => 'sp', 'type' => 'kombinert', 'tittel' => 'Prinsipp- og handlingsprogram 2017–2021', 'periode' => '2017-2021', 'vedtatt_ar' => 2017, 'gjeldende' => false,
        'kildefil' => 'Stortingsprogram 2017-2021/Sp-program-2017-2021.pdf',
        'kilde_url' => 'https://www.senterpartiet.no/aktuelt/senterpartiets-program-2017--2021-copy/_/attachment/download/62870ab7-7a45-49b7-8e1a-1ebb5c6d2dae:b62c7a90c3218d853b670f15fa592d15fd943c29/Vedtatt-ikkekorrekturlest-Sp-%20program%202017-2021.pdf',
    ],
    [
        'parti' => 'krf', 'type' => 'arbeidsprogram', 'tittel' => 'Stortingsprogram 2017–2021', 'periode' => '2017-2021', 'vedtatt_ar' => 2017, 'gjeldende' => false,
        'kildefil' => 'Stortingsprogram 2017-2021/KrF-stortingsprogram-2017-2021.pdf',
        'kilde_url' => 'https://krf.no/content/uploads/2020/09/stortingsprogram-krf-2017-2021.pdf',
    ],
    [
        // Filen heter «vedtatt foreløpig 02.04.17».
        'parti' => 'v', 'type' => 'arbeidsprogram', 'tittel' => 'Stortingsvalgprogram 2017–2021', 'periode' => '2017-2021', 'vedtatt_ar' => 2017, 'gjeldende' => false,
        'kildefil' => 'Stortingsprogram 2017-2021/Venstres-stortingsvalgprogram-2017-2021.pdf',
        'kilde_url' => 'https://www.venstre.no/assets/Venstres-stortingsvalgprogram-2017-2021-vedtatt-forel%C3%B8pig-02.04.17.pdf',
    ],

    // Stortingsperioden 2013–2017
    [
        'parti' => 'ap', 'type' => 'arbeidsprogram', 'tittel' => 'Partiprogram 2013–2017', 'periode' => '2013-2017', 'vedtatt_ar' => 2013, 'gjeldende' => false,
        'kildefil' => 'Stortingsprogram 2013-2017/Ap-partiprogram-2013-2017.pdf',
        'kilde_url' => 'https://res.cloudinary.com/arbeiderpartiet/image/upload/fl_attachment:arbeiderpartiets-partiprogram-2013-2017/v1/ievv_filestore/2d4028eb898243d1bef587c4744479737ab8d0f98201477897fbcf78b0a82735',
        'kilde_side' => 'https://www.arbeiderpartiet.no/om/historien-om-arbeiderpartiet/historiske-partiprogrammer/',
    ],
    [
        'parti' => 'h', 'type' => 'arbeidsprogram', 'tittel' => 'Stortingsvalgprogram 2013–2017', 'periode' => '2013-2017', 'vedtatt_ar' => 2013, 'gjeldende' => false,
        'kildefil' => 'Stortingsprogram 2013-2017/Hoyres-program-2013-2017.pdf',
        'kilde_url' => 'https://res.cloudinary.com/hoyre/images/v1647850351/Nye%20hoyre.no/Hovedside/program_2013-2017-1/program_2013-2017-1.pdf',
    ],
    [
        'parti' => 'v', 'type' => 'arbeidsprogram', 'tittel' => 'Stortingsvalgprogram 2013–2017', 'periode' => '2013-2017', 'vedtatt_ar' => 2013, 'gjeldende' => false,
        'kildefil' => 'Stortingsprogram 2013-2017/Venstres-stortingsvalgprogram-2013-2017.pdf',
        'kilde_url' => 'https://www.venstre.no/files/sentralt/program2013/stortingsvalgprogram_2013_web.pdf',
    ],

    // Stortingsperioden 2009–2013
    [
        'parti' => 'ap', 'type' => 'arbeidsprogram', 'tittel' => 'Skape og dele. Arbeiderpartiets program 2009–2013', 'periode' => '2009-2013', 'vedtatt_ar' => 2009, 'gjeldende' => false,
        'kildefil' => 'Stortingsprogram 2009-2013/Ap-program-2009-2013.pdf',
        'kilde_url' => 'https://res.cloudinary.com/arbeiderpartiet/image/upload/fl_attachment:skape-og-dele-arbeiderpartiets-program-2009-2013/v1/ievv_filestore/c38aa352b4eb44f09535d1bdab39582467c6f4d831004c3e94f5393726700c66',
        'kilde_side' => 'https://www.arbeiderpartiet.no/om/historien-om-arbeiderpartiet/historiske-partiprogrammer/',
    ],
    [
        'parti' => 'h', 'type' => 'arbeidsprogram', 'tittel' => 'Stortingsvalgprogram 2009–2013', 'periode' => '2009-2013', 'vedtatt_ar' => 2009, 'gjeldende' => false,
        'kildefil' => 'Stortingsprogram 2009-2013/Hoyres-partiprogram-2009-2013.pdf',
        'kilde_url' => 'https://res.cloudinary.com/hoyre/images/v1695041355/Nye%20hoyre.no/Hovedside/Hoyres-partiprogram-2009-2013_120993f153/Hoyres-partiprogram-2009-2013_120993f153.pdf',
    ],
    [
        'parti' => 'v', 'type' => 'arbeidsprogram', 'tittel' => 'Stortingsvalgprogram 2009–2013', 'periode' => '2009-2013', 'vedtatt_ar' => 2009, 'gjeldende' => false,
        'kildefil' => 'Stortingsprogram 2009-2013/Venstres-stortingsvalgprogram-2009-2013.pdf',
        'kilde_url' => 'https://www.venstre.no/files/sentralt/politikk/stortingsvalgprogram_2009.pdf',
    ],

    // Tidligere prinsipprogram
    [
        // Aps siste egne prinsipprogram. Partiet har siden bare hatt partiprogram.
        'parti' => 'ap', 'type' => 'prinsipprogram', 'tittel' => 'Prinsipprogram 1981', 'vedtatt_ar' => 1981, 'gjeldende' => false,
        'kildefil' => 'Prinsipprogram tidligere/Ap-prinsipprogram-1981.pdf',
        'kilde_url' => 'https://res.cloudinary.com/arbeiderpartiet/image/upload/fl_attachment:prinsipprogram-1981/v1/ievv_filestore/c08b019306f4400baef402ed7c0d8ae40a9d8af4325b44798d1fd7242afe773e',
        'kilde_side' => 'https://www.arbeiderpartiet.no/om/historien-om-arbeiderpartiet/historiske-partiprogrammer/',
    ],
    [
        'parti' => 'rodt', 'type' => 'prinsipprogram', 'tittel' => 'Prinsipprogram 2014', 'vedtatt_ar' => 2014, 'gjeldende' => false,
        'kildefil' => 'Prinsipprogram tidligere/Rodt-prinsipprogram-2014.pdf',
        'kilde_url' => 'https://www.pdf-arkivet.no/rv/program/prinsipprogram_2014.pdf',
        'kilde_side' => 'https://www.pdf-arkivet.no/rv/index.html',
    ],
    [
        'parti' => 'rodt', 'type' => 'prinsipprogram', 'tittel' => 'Prinsipprogram 2010', 'vedtatt_ar' => 2010, 'gjeldende' => false,
        'kildefil' => 'Prinsipprogram tidligere/Rodt-prinsipprogram-2010.pdf',
        'kilde_url' => 'https://www.pdf-arkivet.no/rv/program/prinsipprogram_2010.pdf',
        'kilde_side' => 'https://www.pdf-arkivet.no/rv/index.html',
    ],
];
