# Koodikontroll K1–K17 — versioon 1.22.1

Kuupäev: 25.11.2026. Kontrollitud kood: `wonom-kampaaniariba` 1.22.1 (riba andmefailist; koodil oma kehtivusaeg).

| # | Tulemus | Selgitus |
|---|---|---|
| K1 | korras | Koodis ei ole ühtegi vahemälu täistühjendust ega `update_plugins` kustutamist. |
| K2 | ei kehti | Plugin ei tee Cloudflare'i päringuid. |
| K3 | korras | `upgrader_process_complete` (class-wkr-updater.php) kustutab ainult plugina enda uuendusinfo. Vahemälu ei puututa. |
| K4 | ei kehti | Vahemälu tühjendavaid kutseid ei ole. |
| K5 | korras | `init`: broneeritud sooduskoodi üleandmine (üks autoload'itud valiku lugemine; töö ainult alguse hetkel, lukuga). `wp_head`: trükib laadija, andmebaasi ei loe. `wp_loaded`: küpsise kontroll, kood korvi ainult siis, kui küpsis on olemas. |
| K6 | korras | Tunnine pulss: üks valiku uuendus ja andmefaili kontroll (üks päring, fail kirjutatakse ainult muutuse korral). Üksiksündmus: sooduskoodi üleandmine, lukuga. |
| K7 | korras | Ainult kampaania enda haagid: `save_post_wonom_banner`, prügikast, taastamine ja kustutamine (teiste postitüüpide puhul üks tüübikontroll ja välja). Tootehaake ei ole. |
| K8 | ei kehti | Partiisid ei ole. |
| K9 | korras | GitHub ja DeepL: ajapiir 15 s. GitHubi vastus on vahemälus 6 h, ebaõnnestumine 1 h. Kumbki ei käivitu külastaja päringus. |
| K10 | korras | Piirid: 200 (kalender), 100 (broneeringud, eemaldamine), 20 (andmefail). |
| K11 | ei kehti | REST-otspunkte ei ole; AJAX on ainult sisseloginud kasutajale. |
| K12 | korras | Leht küsib ühe staatilise faili (mitte PHP). Serverit taimeriga ei küsitleta: andmed küsitakse uuesti ainult siis, kui klient tuleb vahelehele tagasi ja eelmisest korrast on üle 5 minuti. Ainus `setInterval` on loenduri kohalik sekunditaimer. |
| K13 | korras | Administraatori päringud on viitega ja dubleerimiskaitsega; eelvaade joonistatakse brauseris. Prügikasti küsimus on brauseris, serverit ei küsitleta. |
| K14 | korras | Plugin ei saada päiseid ega kirjuta `.htaccess`-i. |
| K15 | korras | Riba lingid on parameetrita. Parameeter `?wkr=` on ainult staatilise andmefaili aadressil. |
| K16 | ei kehti | Plugin ei tee midagi, mis testsaidilt päris poodi mõjutaks: Cloudflare'i päringuid ega tühjendusi ei ole. |
| K17 | ei kehti | Plugin ei loe enam valikuid `wkr_cf_zone` ja `wkr_cf_token`. |

## Kus plugin kirjutab faili või käivitab taustatöö

| Koht | Mis käivitab | Mida teeb |
|---|---|---|
| `WKR_Data::rebuild` | kampaania salvestamine, prügikast, taastamine, kustutamine | üks päring (kuni 20 kampaaniat) ja kuni kaks väikest faili |
| `WKR_Data::rebuild` | tunnine pulss | sama; fail kirjutatakse ainult siis, kui sisu muutus |
| `WKR_Data::ensure` | administraatori lehe avamine | kontrollib, et fail on olemas; kirjutab ainult siis, kui puudub |
| `WKR_Coupon::handover` | kampaania algus (cron või esimene päring pärast algust) | ühe kupongi salvestus, andmebaasi lukuga |
| `WKR_Updater::remote` | WordPressi uuenduste kontroll või nupp seadetes | üks GitHubi päring, vastus vahemälus |
| `WKR_Coupon::on_trash` | kampaania prügikasti saatmine | kupongi märke eemaldus; valiku korral kupong mustandiks |
| `WKR_Coupon::handle_release` | nupp WooCommerce'i kupongi lehel | kupongi ja kampaania märgete eemaldus |
