# CMMS - Systém pro řízení údržby (Computerized Maintenance Management System)

**Version 2.0**

Funkční web: http://cmms.wz.cz/

Lehká, rychlá a mobilní webová aplikace pro digitální řízení údržby, revize strojů a správu závad. Navrženo s důrazem na uživatelskou přívětivost pro techniky v terénu a bezproblémový běh na běžných sdílených webhostinzích s přísnými limity.

## 🚀 Hlavní funkce

*   **Mobilní revize a QR kódy:** Technici mohou skenovat QR kódy zařízení a rovnou v terénu vyplňovat dynamické kontrolní formuláře. U každého pole je vidět hodnota z předchozí kontroly a tlačítko „Označit vše jako OK“.
*   **Chytrá fotodokumentace:** Neomezené pořizování fotografií k revizím i opravám. Komprese obrázků probíhá pomocí HTML5 Canvas přímo v prohlížeči mobilu ještě před odesláním na server, což radikálně šetří datový prostor a obchází striktní `upload_max_filesize` limity sdílených hostingů.
*   **Správa závad (Tiketovací systém):** Automatické zakládání úkolů (tiketů) při zjištění nevyhovujícího stavu ("KO"). Sledování průběhu opravy včetně detailního popisu, fotodokumentace vyměněných dílů a digitálního podpisu.
*   **Elektronické podpisy:** Zabudované dotykové plátno pro okamžitý podpis technika/opraváře přímo na displeji zařízení. Všechny podpisy jsou jednotně modré.
*   **PDF výkazy (Kniha stroje):** PDF protokoly s historií stroje, naměřenými hodnotami, fotografiemi závad i podpisy. Export lze vytvořit pro **jedno zařízení** za **vybraný měsíc** (průběžný tisk po uzavření měsíce) nebo za **celý kalendářní rok**. Využívá knihovnu mPDF.
*   **Vlastní výstupní šablony:** Tiskové formuláře vázané na konkrétní zařízení, které nahrazují papírové evidenční záznamy. Do šablony lze vybrat jen parametry z kontrolních formulářů přiřazených k zařízení, nastavit jejich pořadí a názvy pro tisk. Šablonu lze zkopírovat na jiné zařízení, které používá stejné kontrolní formuláře. Stále lze vytisknout i kompletní formulář.
*   **Externí revize a dodavatelé:** Plány revizí navázané na zařízení (interval, upozornění předem, termín přesně na den nebo do konce měsíce), evidence dodavatelů, zápis provedení revize a nahrání PDF protokolu. Semafor propadlých a blížících se termínů je na dashboardu.
*   **Chytrý Dashboard a plánování:** Přehledná nástěnka se "semaforem údržby", který automaticky hlídá termíny a upozorňuje na propadlé kontroly. Systém umí chytře pracovat s 5denním provozem a automaticky přesouvat víkendové termíny na pondělí. Zobrazuje zařízení v provozu / celkem.
*   **Moduly:** Kontroly, tikety, externí revize a výstupní šablony lze v Nastavení jednotlivě vypnout. Data v databázi zůstávají zachována.
*   **Role a přístupová práva:** Tříúrovňový systém (Administrátor, Manažer, Technik) pro bezpečné oddělení pravomocí.

## 🔒 Zabezpečení

*   **Přihlášení:** hesla minimálně 12 znaků (velká, malá písmena a číslice), omezení počtu neúspěšných pokusů (5 pro jméno z jedné IP a 30 pro celou IP za 15 minut), ochrana proti zjišťování existujících účtů a bezpečnostní deník přihlášení (záznamy se po 180 dnech mažou).
*   **Relace:** automatické odhlášení po 2 hodinách nečinnosti a nejpozději po 12 hodinách od přihlášení. Odhlášení probíhá přes formulář s CSRF tokenem.
*   **Oprávnění:** tři role (administrátor, manažer, technik) a oddělení dat podle úseků. Manažer a technik vidí a upravují jen zařízení, plány, šablony a soubory ze svých úseků. Smazat úsek, který má zařízení, nelze.
*   **CSRF a mazání:** všechny změny dat a mazání probíhají přes POST s CSRF tokenem.
*   **Content Security Policy:** skripty se spouštějí jen z vlastního serveru (bez `unsafe-inline`). Veškerá obsluha stránek je v souborech `assets/js/`.
*   **Soubory a obrázky:** nahrané fotografie, podpisy a PDF protokoly se ověřují (podpisy a fotografie se překreslují na serveru). Zobrazují se jen přes řízený přístup po přihlášení.
*   **Soukromí:** QR kódy se generují přímo na serveru, žádná data se neposílají třetím stranám.

## 🛠 Technologie

*   **Backend:** PHP 8.x (čisté a optimalizované, bez nutnosti instalace těžkých frameworků, **bez Composeru na hostingu**)
*   **Databáze:** MySQL / MariaDB
*   **Frontend:** HTML5, CSS3, Vanilla JavaScript
*   **Klíčová API:** HTML5 Canvas API (pro zpracování Base64 obrázků a podpisů)
*   **Knihovny třetích stran:** mPDF (PDF dokumenty), chillerlan/php-qrcode (QR štítky), Chart.js (grafy), html5-qrcode (čtečka QR v prohlížeči), heic2any (převod fotografií HEIC). Všechny běží lokálně z projektu, bez načítání skriptů z externích serverů.

## 💡 O projektu

Aplikace byla vyvinuta s cílem nahradit nepřehledné papírové formuláře a zefektivnit každodenní rutinu údržbářů. Díky přesunu výpočetního výkonu (např. komprese velkých fotek z moderních smartphonů) na stranu klienta je systém extrémně rychlý, nenáročný na serverový výkon a plně nasaditelný i na bezplatné nebo levné webhostingy.

Jak postupovat při instalaci: https://github.com/sl-svak/Facility-Management/wiki

## 📦 Instalace

1. Nahrajte všechny soubory na hosting (FTP / správce souborů). Nic dalšího se na serveru neinstaluje.
2. Otevřete `install.php`, zadejte údaje k databázi a vytvořte administrátora. Po dokončení se instalátor sám smaže.
   - Instalátor se tvrdě zablokuje (HTTP 403), jakmile existuje `config/config.php` nebo `config/installed.lock`, a to bez ohledu na stav databáze. Pokud se nepodaří smazat `install.php` automaticky, smažte ho přes FTP.
   - Krok 2 (založení administrátora) lze dokončit jen ve stejném prohlížeči, ve kterém proběhl krok 1. Když instalaci mezi kroky přerušíte (zavřete prohlížeč), smažte přes FTP `config/config.php` a `config/install.pending` a začněte znovu.
   - Instalaci opakujete jen ručně: smažte přes FTP `config/config.php`, `config/installed.lock` a `config/install.pending` a nahrajte znovu `install.php`.
3. Složky `assets/uploads/` a `app/libs/mpdf/tmp/` musí být zapisovatelné webserverem. První export PDF může trvat o pár sekund déle (mPDF si vytváří cache fontů).

## ⬆️ Aktualizace aplikace

1. Nahrajte nové soubory na hosting se zachováním struktury složek a přepište stávající. U souborů, které se nepřepíší (zvlášť `index.php`), zkontrolujte výpis chyb FTP klienta.
2. Nepřepisujte `config/config.php` ani složku `assets/uploads/`. Složku `app/libs/` nahrajte celou (nahrazuje původní `app/tcpdf/`, ten lze smazat).
3. Po aktualizaci spusťte jako administrátor `update.php` (doplní databázi a po spuštění se sám přejmenuje). Je bezpečné ho spustit opakovaně.
4. V prohlížeči jednou obnovte stránku (Ctrl+Shift+R). Aplikace používá service worker, který ukládá do mezipaměti jen statické soubory (CSS, JS, ikony). Stránky s daty se nikdy neukládají.

## 📝 Změny ve verzi 2.0

*   PDF přes **mPDF** místo TCPDF, QR štítky přes **chillerlan/php-qrcode** (obojí přiloženo v `app/libs/`, bez Composeru).
*   Export PDF pro zařízení za vybraný měsíc nebo celý rok, ošetření období bez záznamů.
*   Vlastní výstupní šablony vázané na zařízení včetně kopírování mezi zařízeními se stejnými formuláři.
*   Externí revize a dodavatelé, přepínání volitelných modulů v Nastavení.
*   Dashboard: zařízení v provozu / celkem; seznam zařízení zobrazuje stav (V provozu / Odstaveno), který lze změnit v úpravě zařízení.
*   Opravy: chyba „Povinné pole [Podpis technika] nebylo vyplněno“ u odstaveného zařízení, jednotná modrá barva podpisů, obnovené vyplňování kontrol (podpis, foto, stav zařízení), instalátor a `update.php` vytvářejí schéma po jednotlivých příkazech, ochrana kroku 2 instalace proti založení dalšího administrátora.

Starší změny: viz verze 1.0.8 v historii repozitáře.

## 📄 Licence a knihovny třetích stran

Tento projekt (CMMS webová aplikace) je od verze 2.0 šířen pod licencí **GNU GPL v2 (GPL-2.0-only)**, viz soubor `LICENSE`. Verze do 1.0.8 byly vydány pod GNU LGPL v3.

Důvod změny: aplikace je distribuována společně s knihovnou mPDF, která je licencována jako GPL-2.0-only. Tu nelze kombinovat s kódem pod LGPL v3 pod jednou licencí, proto je celý projekt sjednocen na GPL-2.0-only.

### Použitý software třetích stran (Credits)
Tato aplikace by nemohla fungovat bez skvělé práce open-source komunity. Zvláštní poděkování patří:

| Knihovna | Verze | Licence | Umístění |
|---|---|---|---|
| [mPDF](https://mpdf.github.io/) | 8.3.1 | GPL-2.0-only | `app/libs/mpdf/` |
| setasign/fpdi, myclabs/deep-copy, paragonie/random_compat, psr/log, psr/http-message (+ shim) | viz `app/libs/mpdf/vendor/composer/installed.json` | MIT | `app/libs/mpdf/` |
| DejaVu fonty (součást mPDF) | - | Bitstream Vera / DejaVu (`ttfonts/DejaVuinfo.txt`) | `app/libs/mpdf/` |
| [chillerlan/php-qrcode](https://github.com/chillerlan/php-qrcode), php-settings-container | 4.4.2 / 3.3.0 | MIT | `app/libs/qrcode/` |
| [Chart.js](https://www.chartjs.org/) | - | MIT | `assets/js/vendor/` |
| [html5-qrcode](https://github.com/mebjas/html5-qrcode) | - | Apache-2.0 | `assets/js/vendor/` |
| [heic2any](https://github.com/alexcorvi/heic2any) | - | MIT | `assets/js/vendor/` |

Licenční texty knihoven jsou součástí projektu (`app/libs/*/vendor/**/LICENSE*` a `assets/js/vendor/*.LICENSE`). Z balíčku mPDF byly odstraněny nepoužívané fonty (zůstala rodina DejaVu), testy a dokumentace.

**Poznámka k Apache-2.0:** knihovna html5-qrcode (Apache-2.0) je do prohlížeče doručována jako samostatný soubor pod vlastní licencí a s aplikací v PHP není slinkována. Pokud byste projekt šířili jako jeden spojený celek pod GPL-2.0-only, posuďte kompatibilitu (Apache-2.0 je kompatibilní s GPL v3, ne s GPL v2).

### Sestavení knihoven znovu
Mimo hosting: `composer require mpdf/mpdf:^8.2 chillerlan/php-qrcode:^4.3` a nahrání složky `vendor/` do `app/libs/mpdf/`, resp. `app/libs/qrcode/`.
