# CMMS - Systém pro řízení údržby (Computerized Maintenance Management System)

**Version 1.0.8**

Funkční web: http://cmms.wz.cz/

Lehká, rychlá a mobilní webová aplikace pro digitální řízení údržby, revize strojů a správu závad. Navrženo s důrazem na uživatelskou přívětivost pro techniky v terénu a bezproblémový běh na běžných sdílených webhostinzích s přísnými limity.

## 🚀 Hlavní funkce

*   **Mobilní revize a QR kódy:** Technici mohou skenovat QR kódy zařízení a rovnou v terénu vyplňovat dynamické kontrolní formuláře.
*   **Chytrá fotodokumentace:** Neomezené pořizování fotografií k revizím i opravám. Komprese obrázků probíhá pomocí HTML5 Canvas přímo v prohlížeči mobilu ještě před odesláním na server, což radikálně šetří datový prostor a obchází striktní `upload_max_filesize` limity sdílených hostingů.
*   **Správa závad (Tiketovací systém):** Automatické zakládání úkolů (tiketů) při zjištění nevyhovujícího stavu ("KO"). Sledování průběhu opravy včetně detailního popisu, fotodokumentace vyměněných dílů a digitálního podpisu.
*   **Elektronické podpisy:** Zabudované dotykové plátno pro okamžitý podpis technika/opraváře přímo na displeji zařízení.
*   **Automatické PDF Reporty (Kniha stroje):** Generování plnohodnotných PDF protokolů obsahujících kompletní historii stroje, naměřené hodnoty, fotografie závad i podpisy (využívá knihovnu TCPDF).
*   **Chytrý Dashboard a plánování:** Přehledná nástěnka se "semaforem údržby", který automaticky hlídá termíny a upozorňuje na propadlé kontroly. Systém umí chytře pracovat s 5denním provozem a automaticky přesouvat víkendové termíny na pondělí.
*   **Role a přístupová práva:** Tříúrovňový systém (Administrátor, Manažer, Technik) pro bezpečné oddělení pravomocí.

## 🔒 Zabezpečení

*   **Přihlášení:** hesla minimálně 12 znaků (velká, malá písmena a číslice), omezení počtu neúspěšných pokusů (5 pro jméno z jedné IP a 30 pro celou IP za 15 minut), ochrana proti zjišťování existujících účtů a bezpečnostní deník přihlášení (záznamy se po 180 dnech mažou).
*   **Relace:** automatické odhlášení po 2 hodinách nečinnosti a nejpozději po 12 hodinách od přihlášení. Odhlášení probíhá přes formulář s CSRF tokenem.
*   **Oprávnění:** tři role (administrátor, manažer, technik) a oddělení dat podle úseků. Manažer a technik vidí a upravují jen zařízení, plány a soubory ze svých úseků. Smazat úsek, který má zařízení, nelze.
*   **CSRF a mazání:** všechny změny dat a mazání probíhají přes POST s CSRF tokenem.
*   **Content Security Policy:** skripty se spouštějí jen z vlastního serveru (bez `unsafe-inline`). Veškerá obsluha stránek je v souborech `assets/js/`.
*   **Soubory a obrázky:** nahrané fotografie a podpisy se ověřují a překreslují na serveru. Zobrazují se jen přes řízený přístup po přihlášení.
*   **Soukromí:** QR kódy se generují přímo na serveru, žádná data se neposílají třetím stranám.

## 🛠 Technologie

*   **Backend:** PHP (čisté a optimalizované, bez nutnosti instalace těžkých frameworků)
*   **Databáze:** MySQL / MariaDB
*   **Frontend:** HTML5, CSS3, Vanilla JavaScript 
*   **Klíčová API:** HTML5 Canvas API (pro zpracování Base64 obrázků a podpisů)
*   **Knihovny třetích stran:** TCPDF (PDF dokumenty a QR kódy), Chart.js (grafy), html5-qrcode (čtečka QR v prohlížeči), heic2any (převod fotografií HEIC). Všechny běží lokálně z projektu, bez načítání skriptů z externích serverů.

## 💡 O projektu

Aplikace byla vyvinuta s cílem nahradit nepřehledné papírové formuláře a zefektivnit každodenní rutinu údržbářů. Díky přesunu výpočetního výkonu (např. komprese velkých fotek z moderních smartphonů) na stranu klienta je systém extrémně rychlý, nenáročný na serverový výkon a plně nasaditelný i na bezplatné nebo levné webhostingy.

Jak postupovat při instalaci: https://github.com/sl-svak/Facility-Management/wiki


## ⬆️ Aktualizace aplikace

1. Nahrajte nové soubory na hosting se zachováním struktury složek a přepište stávající.
2. Nepřepisujte `config/config.php`, složku `assets/uploads/` ani `app/tcpdf/`.
3. Po aktualizaci spusťte jako administrátor `update.php` (doplní databázi a po spuštění se sám přejmenuje).
4. V prohlížeči jednou obnovte stránku (Ctrl+Shift+R). Aplikace používá service worker, který ukládá do mezipaměti jen statické soubory (CSS, JS, ikony). Stránky s daty se nikdy neukládají.

## 📝 Změny ve verzi 1.0.8

*   Opraveno mazání zařízení, úseků, šablon a uživatelů (nyní POST s CSRF tokenem).
*   Smazání šablony formuláře nyní ukáže, kde je použita, a odstraní i související plány údržby.
*   Zpřísněná oprávnění podle úseků u plánů údržby, souborů a zařízení. Neprázdný úsek nelze smazat.
*   Nový časový limit relace, odhlášení přes POST a upravená omezení pokusů o přihlášení.
*   CSP bez `unsafe-inline`, všechny knihovny lokálně. Grafy přepsány z ApexCharts na Chart.js (licence MIT).
*   QR kódy se generují lokálně, tisk štítku na samostatné stránce.
*   Opravy: podpisy u řešení závad, náhled šablon formulářů, service worker.

## 📄 Licence a knihovny třetích stran

Tento projekt (CMMS webová aplikace) je šířen pod licencí GNU-LGPL v3.

### Použitý software třetích stran (Credits)
Tato aplikace by nemohla fungovat bez skvělé práce open-source komunity. Zvláštní poděkování patří:

*   **TCPDF**
    *   Aplikace využívá knihovnu TCPDF pro generování komplexních PDF dokumentů (Knih strojů a protokolů).
    *   Knihovna TCPDF je svobodný software a je distribuována pod licencí **GNU-LGPL v3** (Lesser General Public License).
    *   Autor: Nicola Asuni / Tecnick.com LTD.
    *   Více informací a zdrojové kódy knihovny naleznete na: [https://tcpdf.org/](https://tcpdf.org/)

*   **Chart.js** (MIT)
    *   Grafy vývoje naměřených hodnot. [https://www.chartjs.org/](https://www.chartjs.org/)
*   **html5-qrcode** (Apache-2.0)
    *   Čtení QR kódů kamerou. [https://github.com/mebjas/html5-qrcode](https://github.com/mebjas/html5-qrcode)
*   **heic2any** (MIT)
    *   Převod fotografií HEIC z iPhonů. [https://github.com/alexcorvi/heic2any](https://github.com/alexcorvi/heic2any)

Licenční texty těchto knihoven jsou součástí projektu ve složce `assets/js/vendor/`.
