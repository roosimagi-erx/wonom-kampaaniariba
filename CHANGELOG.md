# Muudatuste logi

## 1.22.0 - 2026-11-25

### Sooduskoodil võib olla ribast erinev kehtivusaeg

Seni kehtis hallatav kood täpselt sama kaua, kui riba oli eetris. Nüüd saab koodile panna oma alguse ja lõpu — näiteks nii, et uudiskirja saajad saavad koodi kasutada juba eelmisel õhtul, aga riba ilmub lehele alles südaööl.

- **Uued väljad „Kood kehtib alates” ja „Kood kehtib kuni”** kampaania WooCommerce'i osas (nähtavad, kui plugin haldab kupongi). Tühi väli tähendab sama aega mis ribal, nii et olemasolevad kampaaniad töötavad muutmata edasi. Lõpuhetk ise enam sisse ei kuulu, nagu ribalgi.
- **Riba näitamist need väljad ei muuda.** Riba algab ja lõpeb endiselt väljade „Algab” ja „Lõpeb” järgi.
- **Kupongi aegumiskuupäev** WooCommerce'is tuleb nüüd koodi lõpuajast.
- **Broneeritud kood** liigub uuele kampaaniale selle koodi alguse hetkel, mitte riba alguse hetkel.
- **Hoiatus salvestamisel**, kui riba on eetris ka ajal, mil kood ei kehti, või kui koodi lõpp ei ole algusest hiljem.
- Kampaaniate nimekirjas ja kalendri ajakavas on koodi oma kehtivusaeg näha koodi all.

### Prügikasti visatud kampaania ei hoia enam kupongi kinni

Viga: kui kupongi haldav kampaania visati prügikasti, jäi kupong selle kampaania külge ja kood ei kehtinud enam kusagil — ka siis, kui kupong WooCommerce'is uuesti avaldati. Vabanes see alles kampaania jäädaval kustutamisel.

- **Prügikastis olev kampaania ei loe enam kupongi omanikuks.** See parandab ka kupongid, mis jäid kinni varasema versiooniga.
- **Prügikasti saatmisel küsitakse**, mis saab koodist: „Kood jääb kehtima” (kupong läheb tagasi WooCommerce'i hallata, aegumiskuupäev jääb alles) või „Kood ei kehti enam” (kupong pannakse mustandiks). Kui küsimust ei saa näidata, jääb kood kehtima.
- **Taastatud kampaania** on mustand ega võta töötavat koodi kohe tagasi. Sama kupongi võtab ta enda alla siis, kui kampaania uuesti salvestatakse; teist sama koodiga kupongi ei teki.
- Pärast prügikasti saatmist ütleb teade, mis seisus kupong nüüd on.

### Kupongi saab WooCommerce'ile tagasi anda

Viga: kui kampaania oli kupongi korra enda hallata võtnud, piiras plugin koodi kehtivust edasi ka siis, kui valik muudeti tagasi „Ainult näitan koodi ribal”.

- **Valik „Ainult näitan koodi ribal” ja salvestus** annab kupongi nüüd päriselt tagasi: plugin seda enam ei muuda ega piira. Teade ütleb, kas ja kui kaua kood WooCommerce'i enda seadete järgi kehtib.
- **Teade WooCommerce'i kupongi lehel.** Kui kupongi haldab kampaania, on kupongi muutmise lehel kirjas, milline kampaania seda teeb, millal kood kehtib ja et kampaania salvestamine kirjutab kupongi seaded üle. Samas on nupud „Ava kampaania” ja „Anna kupong WooCommerce'ile tagasi”.

Riba, andmefail ja lehtede vahemälu ei muutunud. Pärast uuendust ei ole vaja midagi tühjendada.

## 1.21.1 - 2026-11-23

### Kalender ei näita enam kell 00:00 lõppevat kampaaniat järgmisel päeval

- **Kalender.** Kampaania, mille lõpp on näiteks 26.11 kell 00:00, kehtib 25.11 lõpuni. Kalender näitas seda seni ka 26.11 lahtris. Nüüd loeb kalender lõpuhetke samamoodi nagu riba ja sooduskood: lõpuhetk ise enam kampaania sisse ei kuulu.
- **Selgitus välja „Lõpeb” all.** Kui kampaania peab kestma päeva lõpuni, pane lõpuks järgmine kuupäev kell 00:00. Riba ja sooduskood kehtivad siis kuni 23:59:59 ja järgmine kampaania võib alata samal hetkel, ilma ribata minutita vahepeal.

Riba, sooduskoodi ja andmefaili töö ei muutunud. Vahemälu ei ole vaja tühjendada.

## 1.21.0 - 2026-11-22

### Riba tuleb andmefailist — vahemälu ei tühjendata enam kunagi

Seni kirjutati riba lehe HTML-i sisse. Vahemälus olev leht hoidis seetõttu vana riba ja iga kampaania alguses tuli kõigi lehtede vahemälu tühjendada. Nüüd ei ole riba enam lehe sees.

- **Andmefail.** Kõik eetris olevad ja tulevased kampaaniad kirjutatakse staatilisse faili kaustas `wp-content/uploads/wkr/` (üks fail keele kohta). Failis on iga kampaania algus- ja lõpuaeg ning valmis HTML. Lehel on ainult väike laadija, mis faili loeb.
- **Kampaania algus ja lõpp** toimuvad täpsel sekundil kõigil lehtedel korraga — ka neil, mis on vahemälus või kliendil parajasti lahti. Kui üks kampaania lõpeb ja teine algab samal sekundil, vahetub riba koha peal. Server ei tee sel hetkel midagi.
- **Kampaania salvestamine** kirjutab üle ainult andmefaili. Uus või muudetud riba jõuab kõigile lehtedele umbes minutiga, ka keset päeva.
- **Plugin ei tühjenda enam ühtegi vahemälu.** Ära jäid: aadresside tühjendus salvestamisel, kõigi lehtede tühjendus kampaania algusel, nupp „Tühjenda kogu vahemälu kohe”, seade „Automaatne tühjendamine”, tühjendatavate aadresside nimekiri ning Cloudflare'i väljad ja päringud.
- **Kell.** Brauser võrdleb oma kella serveri omaga, nii et vale kellaga seade ei näita valet kampaaniat ja loendur näitab õiget aega.
- **Ilmumine.** Jaluseriba libiseb alt sisse. Päiseriba jaoks hoitakse ruum kinni, kui eelmisel lehel oli päiseriba näha, nii et leht ei hüppa.
- **Stiilid ja skript** laetakse ainult siis, kui andmefailis on mõni kampaania, ja nende aadress tuleb andmefailist. Vana vahemälus leht saab seetõttu alati plugina praeguse versiooni.
- **Sooduskood** ei ole andmefailis lihttekstina, vaid kodeeritult — ka siis, kui kampaania pealkiri on sama mis kood. Tulevase kampaania koodi ei saa lihtsa tekstiotsinguga enne algust üles korjata.
- **Lühikood** `[wonom_banner]` jätab lehele koha, kuhu brauser riba joonistab. Päise- ja jaluseriba ei vaja enam teema haake.
- **Plugina väljalülitamine** kirjutab andmefaili tühja nimekirja, nii et riba kaob ka vahemälus olevatelt lehtedelt.
- **Seaded.** Uus kaart „Riba andmefail”: faili seis, viimase kirjutamise aeg, kampaaniate arv ja failide aadressid. Nupp „Kirjuta andmefail uuesti”.

### Üleminek — tee üks kord pärast uuendust

1. Ava pärast uuendust üks administraatori leht. Andmefail kirjutatakse siis valmis (Seaded → Riba andmefail peab näitama „Fail on olemas”).
2. **Tühjenda kiirendusplugina lehtede vahemälu üks kord** (FlyingPressis „Purge pages”), vaiksel ajal. Varem vahemällu läinud lehtedel on veel vana, lehe sisse kirjutatud riba ja laadijat neil ei ole, nii et uued kampaaniad sinna ei jõuaks. Pärast seda ühte korda ei ole riba pärast enam kunagi vaja vahemälu tühjendada.
3. Soovi korral lisa kiirendusplugina JavaScripti väljajätmiste hulka märksõna `wkr-restore`. Ilma selleta riba töötab, aga ilmub veidi hiljem.

Versioonilt 1.20.x võib uuendada tavalisel viisil nupuga „Uuenda kohe” — see versioon uuendamisel vahemälu ei tühjenda.

### Arendajale

- Uus filter `wkr_data` (andmefaili sisu enne kirjutamist).
- Ära jäid filtrid ja haagid `wkr_purge_urls`, `wkr_purge_cache`, `wkr_purge_cache_urls` ja `wkr_is_test_host`.
- Valikud `wkr_cf_zone` ja `wkr_cf_token` jäävad andmebaasi alles, aga plugin neid enam ei loe ega muuda.

## 1.20.2 - 2026-11-19

Sisaldab kõike versioonidest 1.20.0 ja 1.20.1 (vaata allpool) ning ühte parandust, mis tuli välja testsaidil.

### Parandus: teise keele lehed jäid salvestamisel tühjendamata

- Mitmekeelses poes (WPML, Polylang) kirjutati tühjendatavate aadresside nimekirjas teise keele rajad jooksva keele järgi ümber. Eesti keele vaates sai rajast `/en/` avaleht ja rajast `/en/shop/` olematu `/shop/`. Tulemus: 16 aadressist tühjendati 15 ja ingliskeelsed lehed jäid vana ribaga. Nüüd võetakse rada täpselt sellisena, nagu see nimekirjas kirjas on.
- Seadetes on tühjendatavate aadresside välja all lahtiklõpsatav loend „Täisaadressid, mis päriselt tühjendatakse” — sealt on näha, mis aadressid vahemälule tegelikult ette antakse.

### Uuendamise märkus

Versioonilt 1.19.0 või vanemalt uuendades jookseb vana kood veel ühe korra ja tühjendab kogu vahemälu. Kui tahad seda vältida: lülita plugin välja → Pluginad → Lisa uus → Laadi ZIP üles → „Asenda praegune” → lülita sisse. Kampaaniad ja seaded jäävad alles. Kustutada pluginat ei tohi. Versioonilt 1.20.0 või 1.20.1 võib uuendada tavalisel viisil.


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
