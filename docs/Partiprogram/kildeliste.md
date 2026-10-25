# Kildeliste for partiprogrammene

Alle programmene systemet bruker, står i `database/data/programmer.php`, med
planlagt filsti under `docs/Partiprogram/` og lenken filen kommer fra. Mangler
en fil, laster denne kommandoen den ned (gjeldende programmer først):

```
php artisan program:last-ned-til-docs            # alle som mangler
php artisan program:last-ned-til-docs --bare-gjeldende
php artisan program:last-ned-til-docs --torr     # bare vis hva som mangler
```

Commit filene etterpå og kjør `php artisan db:seed --class=ProgramSeeder`, så
kobles de til programmene. Feiler en nedlasting (noen nettsider stenger ute
skript), last ned lenken i nettleseren og lagre filen med navnet i tabellen.

Sist sjekket: 25.10.2026.

## Gjeldende prinsipprogram som manglet

Disse ligger i `Stortingsprogram 2025-2029/`, sammen med arbeidsprogrammene.

| Parti | Program | Lagres som | Lenke |
|---|---|---|---|
| Høyre | Prinsipprogram 2019 | `Hoyres-prinsipprogram-2019.pdf` | https://hoyre.no/content/uploads/2021/09/Hoyres-prinsipprogram-2019.pdf |
| Rødt | Prinsipprogram 2019 (bokmål) | `Rodt-prinsipprogram-2019-bokmal.pdf` | https://roedt.no/fil/f60b95dfc54397a984de84cd600dbbedd2bf8745.pdf?dl= |
| MDG | Prinsipprogram (vedtaksår ikke oppgitt) | `MDG-prinsipprogram.pdf` | https://mdg.no/_service/505809/download/id/1551129/name/Prinsipprogram+for+MDG.pdf |
| KrF | Prinsipprogram 2023 | `KrF-Prinsipprogram-2023.pdf` | https://krf.no/content/uploads/2020/09/KrF-Prinsipprogram-2023.docx.pdf |
| Venstre | Prinsipprogram 2020 | `Venstres-prinsipprogram-2020.pdf` | https://www.venstre.no/assets/Venstres-prinsipprogram-2020.pdf |

Ap har ikke et gjeldende prinsipprogram (det siste er fra 1981, se under). FrP
og Sp har prinsipper og handlingsprogram i samme dokument.

## Stortingsperioden 2021–2025

Mappe: `Stortingsprogram 2021-2025/`

| Parti | Program | Lagres som | Lenke | Merknad |
|---|---|---|---|---|
| Ap | Partiprogram 2021–2025 (bokmål) | `Ap-partiprogram-2021-2025-bokmal.pdf` | https://res.cloudinary.com/arbeiderpartiet/image/upload/fl_attachment:arbeiderpartiets-partiprogram-2021-2025-bokmal/v1/ievv_filestore/c026d552300245c19306ee41345b764f88183fbd93b84cb284899008282b1e37 | Fra Aps side med historiske partiprogrammer |
| FrP | Program 2021–2025 | `FrP-Vedtatt-program-for-2021-2025.pdf` | https://www.frp.no/files/Landsmote/2021/Vedtatt-program-for-2021-2025.pdf | |
| Høyre | Stortingsvalgprogram 2021–2025 | `Hoyres-program-2021-2025.pdf` | https://res.cloudinary.com/hoyre/images/v1646396541/Nye%20hoyre.no/Hovedside/Hoyres-program-2021-2025/Hoyres-program-2021-2025.pdf | |
| SV | Arbeidsprogram 2021–2025 (bokmål) | `SV-arbeidsprogram-2021-2025-bokmal.pdf` | https://www.sv.no/wp-content/uploads/2023/09/arbeidsprogram-21-25-bokm%C3%A5l.pdf | Andre utgaver: https://www.sv.no/wp-content/uploads/2021/05/arbeidsprogram-2021-2025.pdf |
| Sp | Prinsipp- og handlingsprogram 2021–2025 (bokmål) | `Sp-prinsipp-og-handlingsprogram-2021-2025-bokmal.pdf` | https://www.senterpartiet.no/politikk/program-uttaler/program/Stortingsprogram-2021-2025-bokmaal/_/attachment/download/61cec2b8-738a-4b65-b3a1-c432728c7ac0:ee197aac4238ac919ac4406e9bcdb42d7e44df24/Prinsipp-%20og%20handlingsprogram%202021-2024%20bokma%CC%8Al%20nett.pdf | |
| Rødt | Arbeidsprogram 2021–2025 (bokmål) | `Rodt-arbeidsprogram-2021-2025-bokmal.pdf` | https://www.pdf-arkivet.no/rv/program/arbeidsprogram_bokmal_2021.pdf | Ikke på roedt.no lenger. Kopi i pdf-arkivet.no |
| MDG | Arbeidsprogram 2021–2025 | `MDG-arbeidsprogram-2021-2025.pdf` | https://www.eikernytt.no/wp-content/uploads/2021/07/Arbeidsprogram_MDG_2021-2025.pdf | Ikke på mdg.no lenger. Kopi hos Eikernytt, bør sjekkes mot originalen |
| KrF | Stortingsprogram 2021–2025 | `KrF-stortingsprogram-2021-2025.pdf` | https://krf.no/content/uploads/2020/09/KrFs-stortingsprogram-2021-2025-3.pdf | |
| Venstre | Stortingsvalgprogram 2021–2025 | `Venstres-stortingsvalgprogram-2021-2025.pdf` | https://www.venstre.no/assets/Venstres_Stortingsvalgprogram_2021-2025.pdf | |

## Stortingsperioden 2017–2021

Mappe: `Stortingsprogram 2017-2021/`

| Parti | Program | Lagres som | Lenke | Merknad |
|---|---|---|---|---|
| Ap | Partiprogram 2017–2021 (bokmål) | `Ap-partiprogram-2017-2021-bokmal.pdf` | https://res.cloudinary.com/arbeiderpartiet/image/upload/fl_attachment:arbeiderpartiets-partiprogram-2017-2021-bokmal/v1/ievv_filestore/712bd5b874e24958b84c14fbef5b1a98afbfc8ae1e464cac9bcad06f24f2a85f | |
| Høyre | Stortingsvalgprogram 2017–2021 | `Hoyres-partiprogram-2017-2021.pdf` | https://res.cloudinary.com/hoyre/images/v1695041335/Nye%20hoyre.no/Hovedside/Hoyres-partiprogram-2017-2021_1210997706/Hoyres-partiprogram-2017-2021_1210997706.pdf | |
| Sp | Prinsipp- og handlingsprogram 2017–2021 | `Sp-program-2017-2021.pdf` | https://www.senterpartiet.no/aktuelt/senterpartiets-program-2017--2021-copy/_/attachment/download/62870ab7-7a45-49b7-8e1a-1ebb5c6d2dae:b62c7a90c3218d853b670f15fa592d15fd943c29/Vedtatt-ikkekorrekturlest-Sp-%20program%202017-2021.pdf | «Vedtatt, ikke korrekturlest» |
| KrF | Stortingsprogram 2017–2021 | `KrF-stortingsprogram-2017-2021.pdf` | https://krf.no/content/uploads/2020/09/stortingsprogram-krf-2017-2021.pdf | |
| Venstre | Stortingsvalgprogram 2017–2021 | `Venstres-stortingsvalgprogram-2017-2021.pdf` | https://www.venstre.no/assets/Venstres-stortingsvalgprogram-2017-2021-vedtatt-forel%C3%B8pig-02.04.17.pdf | «Vedtatt foreløpig 02.04.17» |

Ikke funnet på nett: FrP, SV, Rødt og MDG 2017–2021. Kan finnes hos
partiene direkte, i Nasjonalbiblioteket (nb.no) eller Arbeiderbevegelsens
arkiv (arbark.no).

## Stortingsperioden 2013–2017

Mappe: `Stortingsprogram 2013-2017/`

| Parti | Program | Lagres som | Lenke |
|---|---|---|---|
| Ap | Partiprogram 2013–2017 | `Ap-partiprogram-2013-2017.pdf` | https://res.cloudinary.com/arbeiderpartiet/image/upload/fl_attachment:arbeiderpartiets-partiprogram-2013-2017/v1/ievv_filestore/2d4028eb898243d1bef587c4744479737ab8d0f98201477897fbcf78b0a82735 |
| Høyre | Stortingsvalgprogram 2013–2017 | `Hoyres-program-2013-2017.pdf` | https://res.cloudinary.com/hoyre/images/v1647850351/Nye%20hoyre.no/Hovedside/program_2013-2017-1/program_2013-2017-1.pdf |
| Venstre | Stortingsvalgprogram 2013–2017 | `Venstres-stortingsvalgprogram-2013-2017.pdf` | https://www.venstre.no/files/sentralt/program2013/stortingsvalgprogram_2013_web.pdf |

Ikke funnet på nett: FrP, SV, Sp, Rødt, MDG og KrF 2013–2017.

## Stortingsperioden 2009–2013

Mappe: `Stortingsprogram 2009-2013/`

| Parti | Program | Lagres som | Lenke |
|---|---|---|---|
| Ap | Skape og dele. Program 2009–2013 | `Ap-program-2009-2013.pdf` | https://res.cloudinary.com/arbeiderpartiet/image/upload/fl_attachment:skape-og-dele-arbeiderpartiets-program-2009-2013/v1/ievv_filestore/c38aa352b4eb44f09535d1bdab39582467c6f4d831004c3e94f5393726700c66 |
| Høyre | Stortingsvalgprogram 2009–2013 | `Hoyres-partiprogram-2009-2013.pdf` | https://res.cloudinary.com/hoyre/images/v1695041355/Nye%20hoyre.no/Hovedside/Hoyres-partiprogram-2009-2013_120993f153/Hoyres-partiprogram-2009-2013_120993f153.pdf |
| Venstre | Stortingsvalgprogram 2009–2013 | `Venstres-stortingsvalgprogram-2009-2013.pdf` | https://www.venstre.no/files/sentralt/politikk/stortingsvalgprogram_2009.pdf |

## Tidligere prinsipprogram

Mappe: `Prinsipprogram tidligere/`

| Parti | Program | Lagres som | Lenke |
|---|---|---|---|
| Ap | Prinsipprogram 1981 (det siste) | `Ap-prinsipprogram-1981.pdf` | https://res.cloudinary.com/arbeiderpartiet/image/upload/fl_attachment:prinsipprogram-1981/v1/ievv_filestore/c08b019306f4400baef402ed7c0d8ae40a9d8af4325b44798d1fd7242afe773e |
| Rødt | Prinsipprogram 2014 | `Rodt-prinsipprogram-2014.pdf` | https://www.pdf-arkivet.no/rv/program/prinsipprogram_2014.pdf |
| Rødt | Prinsipprogram 2010 | `Rodt-prinsipprogram-2010.pdf` | https://www.pdf-arkivet.no/rv/program/prinsipprogram_2010.pdf |

## Eldre arkiver (ikke lagt inn)

Disse er ikke lagt inn som programmer ennå, men kan legges til senere:

- Arbeiderpartiet, alle partiprogrammer fra 1885 til 2005: https://www.arbeiderpartiet.no/om/historien-om-arbeiderpartiet/historiske-partiprogrammer/
- RV og Rødt, programmer fra 1975: https://www.pdf-arkivet.no/rv/index.html
- Høyre 1993–1997: https://res.cloudinary.com/hoyre/images/v1695041340/Nye%20hoyre.no/Hovedside/Hoyres-partiprogram-1993-1997/Hoyres-partiprogram-1993-1997.pdf
- Venstre 2005: https://www.venstre.no/files/organisasjon/organisasjon/stvprogram2005.pdf
- Sikt (tidligere NSD) sitt partidokumentarkiv med programmer fra 1884: https://polsys.sikt.no/en/parties/politicaldocuments. Er nå lagt inn, se under.

## Partidokumenter fra Sikt (lagt inn 25.10.2026)

947 stortingsprogrammer, prinsipprogrammer, sametingsprogrammer og
lokalvalgprogrammer fra 1884 til 2025 er lagt inn fra Sikts samling av
partidokumenter. De ligger i periodemappene over og i de nye mappene
`Sametingsprogram ÅÅÅÅ-ÅÅÅÅ/` og `Lokalvalgprogram ÅÅÅÅ-ÅÅÅÅ/`, med `sikt`
og dokumentnummeret i filnavnet. Oversikten over alle filene står i
[`sikt-partidokumenter.md`](sikt-partidokumenter.md) og
[`sikt-partidokumenter.csv`](sikt-partidokumenter.csv).

Dette fyller flere hull i listene over, blant annet SV 2021–2025, Sp
2017–2021 og FrP, SV, Sp, Rødt, MDG og KrF i 2013–2017 og 2017–2021.
Programmene er ikke koblet til `database/data/programmer.php` ennå.

All bruk av disse dataene må siteres slik:

> Sikt – Kunnskapssektorens tjenesteleverandør. (u.å.). Data om det politiske system. Hentet 25. oktober 2026, fra https://polsys.sikt.no/
