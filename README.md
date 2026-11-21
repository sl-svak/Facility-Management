# CMMS – Systém pro řízení údržby (v2.0)

PHP 8.x + MySQL/MariaDB + Vanilla JS. Funguje na sdíleném hostingu bez Composeru, SSH i Node.js –
stačí nahrát soubory přes FTP (všechny knihovny jsou přiložené v `app/libs/`).

## Instalace a aktualizace

* Nová instalace: `install.php` (po instalaci ho smažte).
* Aktualizace z předchozí verze: nahrajte soubory a jako administrátor otevřete `update.php`
  (skript je idempotentní – lze jej spustit opakovaně; po úspěchu se sám přejmenuje na `update.php.bak`).
* Složky `assets/uploads/` a `app/libs/mpdf/tmp/` musí být zapisovatelné webserverem.

## Novinky ve verzi 2.0

* PDF přes **mPDF** (náhrada TCPDF), QR štítky přes **chillerlan/php-qrcode**.
* Export PDF pro jedno zařízení: vybraný měsíc nebo celý kalendářní rok.
* Vlastní výstupní šablony vázané na zařízení (výběr parametrů z přiřazených kontrolních formulářů,
  pořadí, vlastní názvy, kopírování na zařízení se stejnými formuláři).
* Externí revize a dodavatelé, přepínání volitelných modulů v Nastavení.

## Knihovny třetích stran (`app/libs/`)

| Knihovna | Verze | Licence |
|---|---|---|
| mpdf/mpdf | 8.3.1 | GPL-2.0-only |
| setasign/fpdi, myclabs/deep-copy, paragonie/random_compat, psr/log, psr/http-message (+ shim) | viz `app/libs/mpdf/vendor/composer/installed.json` | MIT |
| DejaVu fonty (součást mPDF) | – | Bitstream Vera / DejaVu (`ttfonts/DejaVuinfo.txt`) |
| chillerlan/php-qrcode, chillerlan/php-settings-container | 4.4.2 / 3.3.0 | MIT |

Z balíčku mPDF byly odstraněny nepoužívané fonty (zůstala rodina DejaVu), testy a dokumentace.
Znovu-sestavení: `composer require mpdf/mpdf:^8.2 chillerlan/php-qrcode:^4.3` mimo hosting a nahrání složky `vendor/`.

**Licenční upozornění:** vlastní kód projektu je pod LGPL-3.0 (`LICENSE`), mPDF je GPL-2.0-only.
Tyto licence nejsou navzájem kompatibilní pro společně šířené dílo – před veřejným šířením
sjednoťte licenci projektu (např. na GPL-2.0-only / GPL-2.0-or-later kompatibilní variantu)
nebo mPDF nahraďte knihovnou s liberální licencí.
