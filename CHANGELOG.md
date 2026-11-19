# Muudatuste logi

## 1.20.1 - 2026-11-19

Sisaldab kõike versioonist 1.20.0 (vaata allpool) ja kahte täpsustust kampaania alguse kohta.

### Uus riba ilmub alguse hetkel, mitte mõni minut hiljem

- **Kampaania alguse tühjendus on ajastatud täpselt algusajale.** Varem oli see 5 sekundit hiljem. Kui serveri cron käivitub kindla sammuga (näiteks iga 5 minuti tagant täisminutil), jäi sündmus alguse hetke käivitusest napilt maha ja tehti alles järgmisega — vana riba oli selleks ajaks juba peidetud ja uut veel ei olnud. Nüüd teeb tühjenduse alguse hetkel käivituv cron.
- **Varusündmus 5 minutit hiljem.** See tühjendab ainult siis, kui alguse hetke sündmus jäi tegemata või katkes. Kui esimene õnnestus, ei tee varusündmus midagi.
- **Üks tühjendus alguse kohta.** Otsus tehakse selle järgi, kas pärast selle kampaania algust on kõik lehed juba tühjendatud. Nii annavad ühe tühjenduse mitu samal hetkel algavat kampaaniat ja samal hetkel lõppev ning algav kampaania. Versioonis 1.20.0 oli selle asemel 10-minutiline aken, mis oleks alguse tühjenduse vahele jätnud, kui vahetult enne algust oli vahemälu käsitsi tühjendatud.
- **Lõpuhetk loetakse juba lõppenuks.** Kui üks kampaania lõpeb ja teine algab samal sekundil, ei ole nad hetkekski koos eetris: alguse sekundil kokku pandud leht saab kohe uue riba, mitte vana. Sama kehtib kupongi kohta — lõppeva kampaania kood ei kehti enam lõpusekundil.

### Uuendamise märkus

Versioonilt 1.19.0 või vanemalt uuendades jookseb vana kood veel ühe korra ja tühjendab kogu vahemälu. Kui tahad seda vältida: lülita plugin välja → Pluginad → Lisa uus → Laadi ZIP üles → „Asenda praegune” → lülita sisse. Kampaaniad ja seaded jäävad alles. Kustutada pluginat ei tohi.


## 1.20.0 - 2026-11-19

### Vahemälu: kogu saidi tühjendus on asendatud aadressipõhisega

Kogu saidi vahemälu tühjendamine on poele kallis: pärast seda tuleb iga leht uuesti kokku panna. Plugin tegi seda seni igal salvestusel, uuendamisel ning iga kampaania alguses ja lõpus. Nüüd:

- **Kampaania salvestamine, prügikasti viimine ja taastamine** tühjendab ainult seadetes loetletud aadressid (vaikimisi avaleht, pood ja tähtsamad kategooriad; kuni 30). Tühjendus tehakse üks kord, umbes 45 sekundit pärast viimast muudatust, nii et mitu salvestust järjest annavad ühe tühjenduse. Samad lehed laetakse kohe uuesti vahemällu. Nimekirja saab muuta: Seaded → Vahemälu → „Tühjendatavad aadressid”.
- **Kampaania lõpp ei tühjenda enam midagi.** Riba peidab brauser lõpuaja järgi — ka vahemälus oleval lehel ja ka siis, kui loendur ei ole sisse lülitatud. Lõppenud riba ei vilksata lehe laadimisel.
- **Plugina uuendamine ei tühjenda enam vahemälu.** Stiilide ja skriptide aadressis on versiooninumber, nii et need uuenevad ise.
- **Objektivahemälu** (`wp_cache_flush`) plugin enam ei puutu.
- **Cloudflare:** kampaania muutmisel tühjendatakse ainult loetletud aadressid, mitte kogu tsoon. Päringu ajapiir on 15 sekundit.
- **Kampaania algus** tühjendab ajutiselt veel kõik lehed, nagu varem, et uus riba jõuaks igale poole. Kui samal hetkel üks kampaania lõpeb ja teine algab või algab mitu kampaaniat korraga, tehakse üks tühjendus, mitte mitu. See jääb alles ainult järgmise suurema versioonini, kus riba andmed liiguvad eraldi faili ja lehti ei ole enam vaja tühjendada.
- **Testsait:** kui saidi aadress algab `test.`, `staging.` või `dev.` või keskkonna tüüp ei ole `production`, ei tee plugin Cloudflare'i päringuid ka siis, kui võti on sisestatud, ja kampaania algus ei tühjenda seal kõiki lehti. Filter: `wkr_is_test_host`.
- Nupu „Tühjenda vahemälu kohe” nimi on nüüd „Tühjenda kogu vahemälu kohe”. Nupp küsib kinnitust ja selle juures on hoiatus. Tavatöös seda vaja ei ole.
- Seadete lehel on näha viimased tühjendused: millal, miks ja mitu aadressi.

### Sooduskood

- **Riba link on nüüd puhas.** Varem lisati lingile `?wkr_coupon=KOOD`. Sellise parameetriga aadressi ei saa vahemälust serveerida, nii et iga klõps ribal pani serveri lehte nullist kokku panema. Nüüd jätab klõps koodi kliendi brauserisse küpsisesse ja soodustus läheb korvi kohe, kui seal on mõni toode. Kui kood parajasti ei sobi (näiteks korvi summa on liiga väike), klient veateadet ei näe ja plugin proovib uuesti, kui korv muutub. Vanad `?wkr_coupon=` lingid töötavad edasi.
- **Broneeritud koodi üleandmine kampaania alguses on lukuga.** Kui alguse hetkel jõuavad korraga kohale ajastatud töö ja koodi sisestav klient, teeb üleandmise üks neist ja teine ootab hetke, kuni kupong on valmis. Varem võis klient saada sel hetkel vastuseks, et kood ei kehti.
- **Parandus:** „Nulli loendur selle salvestusega” läks kaduma, kui kood oli broneeritud ja pidi kampaaniasse tulema alles alguse hetkel. Nüüd jäetakse soov meelde ja loendur nullitakse üleandmisel. Kampaania vormis on näha, et nullimine on ootel.

### Uuendused

- „Kontrolli uuendusi kohe” värskendab ainult selle plugina kirjet. Varem kustutas see WordPressi kogu uuenduste nimekirja ja sundis kõik teised pluginad end uuesti kontrollima.
- Plugina välja- ja sisselülitamine ajastab tulevaste kampaaniate sündmused uuesti.

### Uuendamise märkus

Versioonilt 1.19.0 või vanemalt uuendades jookseb vana kood veel ühe korra ja tühjendab kogu vahemälu. Kui tahad seda vältida: lülita plugin välja → Pluginad → Lisa uus → Laadi ZIP üles → „Asenda praegune” → lülita sisse. Kampaaniad ja seaded jäävad alles. Kustutada pluginat ei tohi.
