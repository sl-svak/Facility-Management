# CMMS - Systém pro řízení údržby (Computerized Maintenance Management System)

Funkční web: http://cmms.wz.cz/

Lehká, rychlá a mobilní webová aplikace pro digitální řízení údržby, revize strojů a správu závad. Navrženo s důrazem na uživatelskou přívětivost pro techniky v terénu a bezproblémový běh na běžných sdílených webhostinzích s přísnými limity.

## 🚀 Hlavní funkce

*   **Mobilní revize a QR kódy:** Technici mohou skenovat QR kódy zařízení a rovnou v terénu vyplňovat dynamické kontrolní formuláře.
*   **Chytrá fotodokumentace:** Neomezené pořizování fotografií k revizím i opravám. Komprese obrázků probíhá pomocí HTML5 Canvas přímo v prohlížeči mobilu ještě před odesláním na server, což radikálně šetří datový prostor a obchází striktní `upload_max_filesize` limity sdílených hostingů.
*   **Správa závad (Tiketovací systém):** Automatické zakládání úkolů (tiketů) při zjištění nevyhovujícího stavu ("KO"). Sledování průběhu opravy včetně detailního popisu, fotodokumentace vyměněných dílů a digitálního podpisu.
*   **Elektronické podpisy:** Zabudované dotykové plátno pro okamžitý podpis technika/opraváře přímo na displeji zařízení.
*   **Automatické PDF Reporty (Kniha stroje):** Generování plnohodnotných PDF protokolů obsahujících kompletní historii stroje, naměřené hodnoty, fotografie závad i podpisy (využívá knihovnu TCPDF).
*   **Chytrý Dashboard a plánování:** Přehledná nástěnka se "semaforem údržby", který automaticky hlídá termíny a upozorňuje na propadlé kontroly. Systém umí chytře pracovat s 5denním provozem a automaticky přesouvat víkendové termíny na pondělí.
*   **Role a přístupová práva:** Tříúrovňový systém (Administrátor, Dispečer, Technik) pro bezpečné oddělení pravomocí.

## 🛠 Technologie

*   **Backend:** PHP (čisté a optimalizované, bez nutnosti instalace těžkých frameworků)
*   **Databáze:** MySQL / MariaDB
*   **Frontend:** HTML5, CSS3, Vanilla JavaScript 
*   **Klíčová API:** HTML5 Canvas API (pro zpracování Base64 obrázků a podpisů)
*   **Knihovny třetích stran:** TCPDF (pro generování výstupních dokumentů)

## 💡 O projektu

Aplikace byla vyvinuta s cílem nahradit nepřehledné papírové formuláře a zefektivnit každodenní rutinu údržbářů. Díky přesunu výpočetního výkonu (např. komprese velkých fotek z moderních smartphonů) na stranu klienta je systém extrémně rychlý, nenáročný na serverový výkon a plně nasaditelný i na bezplatné nebo levné webhostingy.
