"""Lager testprogram.pdf, et lite fiktivt partiprogram brukt i testene.

Kjør: python3 tests/Fixtures/lag_testprogram.py
Krever reportlab. PDF-en har topptekst, bunntekst med sidetall, nummererte
overskrifter, punktlister, orddeling over linjeskift og et avsnitt som
fortsetter på neste side, slik ekte partiprogrammer har.
"""
import os
from reportlab.lib.pagesizes import A4
from reportlab.pdfgen import canvas

UT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "testprogram.pdf")
B, H = A4
VENSTRE = 72
TOPP = H - 100
BUNN = 90


class Skriver:
    def __init__(self):
        self.c = canvas.Canvas(UT, pagesize=A4)
        self.side = 1
        self.y = TOPP
        self.ramme()

    def ramme(self):
        self.c.setFont("Helvetica", 8)
        self.c.drawString(VENSTRE, H - 50, "Testpartiet – Arbeidsprogram 2025–2029")
        self.c.drawString(B / 2, 40, str(self.side))

    def ny_side(self):
        self.c.showPage()
        self.side += 1
        self.y = TOPP
        self.ramme()

    def linje(self, tekst, font="Helvetica", str_=11, x=VENSTRE, luft=15):
        if self.y < BUNN:
            self.ny_side()
        self.c.setFont(font, str_)
        self.c.drawString(x, self.y, tekst)
        self.y -= luft

    def mellomrom(self, n=10):
        self.y -= n

    def overskrift(self, tekst, str_=18):
        self.mellomrom(14)
        self.linje(tekst, "Helvetica-Bold", str_, luft=str_ + 10)

    def avsnitt(self, linjer):
        for l in linjer:
            self.linje(l)
        self.mellomrom(10)

    def punkter(self, punkter):
        for p in punkter:
            for i, l in enumerate(p):
                self.linje(("• " if i == 0 else "  ") + l, x=VENSTRE + 10)
        self.mellomrom(10)

    def lagre(self):
        self.c.save()


s = Skriver()
s.linje("Testpartiets arbeidsprogram 2025–2029", "Helvetica-Bold", 24, luft=40)
s.overskrift("1 Forsvar og sikkerhet")
s.avsnitt([
    "Norge skal være et aktivt medlem av NATO, og vi vil styrke forsvaret.",
    "Forsvarsbudsjettet skal økes til minst to prosent av BNP, jf. NATOs mål.",
    "Vi vil bl.a. styrke Heimevernet og bygge ut kystforsvaret i nord.",
])
s.overskrift("1.1 Allierte og samarbeid", 14)
s.avsnitt([
    "Samarbeidet med de nordiske landene er viktig. Testpartiet vil derfor ut-",
    "vide det nordiske forsvarssamarbeidet, f.eks. innen luftvern.",
])
s.linje("Testpartiet vil:")
s.punkter([
    ["Øke antall soldater i Hæren."],
    ["Kjøpe nye ubåter i samarbeid med Tyskland, og sørge for at",
     "vedlikeholdet skjer i Norge."],
    ["Si nei til permanente utenlandske baser i fredstid."],
])
s.overskrift("2 Europa")
s.avsnitt([
    "Testpartiet mener Norge ikke bør søke medlemskap i EU i denne perioden.",
    "EØS-avtalen gir oss tilgang til det indre markedet. Den skal vi ta vare på.",
])
s.overskrift("3 Skatt og avgifter")
# Fyll opp siden så avsnittet fortsetter på neste side.
while s.y > BUNN + 30:
    s.avsnitt(["Skattesystemet skal være enkelt og rettferdig for alle."])
s.avsnitt([
    "Eiendomsskatten på bolig skal fjernes, og kommunene skal kompenseres",
    "gjennom rammetilskuddet. Formuesskatten skal reduseres med ca. 20 prosent",
    "i løpet av perioden, og bunnfradraget skal økes.",
])
s.lagre()
print(UT)
