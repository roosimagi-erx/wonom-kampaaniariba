=== Wonom kampaaniad ===
Contributors: wonomdigital
Tags: woocommerce, banner, campaign, coupon, promotion
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.22.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Ajastatud sooduspakkumiste riba poe päisesse ja jalusesse. Kampaaniad kalendris, tekstid kahes keeles, sooduskood ühe klõpsuga kopeeritav.

== Description ==

Wonom kampaaniad on WooCommerce'i poe pakkumiste teavitusriba, mille saab ette ära ajastada.

Iga kampaania on eraldi kirje, millel on:

* kolm tekstirida, esimene esiletõstetud värviga
* sooduskood eraldi väljal — klõps kopeerib koodi kliendi lõikelauale
* värvid (taust, tekst, esiletõst), font, kirja suurus
* asukoht: päis, jalus või mõlemad; jaluses kas üle ekraani või ujuva kaardina
* algus- ja lõpuaeg koos ajavööndiga
* tagasiloendus lõpuni
* kihi järjekord (z-index), et riba jääks ostukorvi paneeli alla

= Ajastus =

Algus- ja lõpuaeg salvestatakse kohaliku seinakellana koos IANA ajavööndiga ning
teisendatakse salvestamisel Unixi ajatempliks. Nii ei nihku kampaania suve- ja
talveaja vahetusel ega olene serveri ajavööndist.

Riba ei ole lehe HTML-i sees. Lehel on väike laadija, mis loeb riba andmefailist,
ja millist kampaaniat näidata, otsustab kliendi brauser kellaaja järgi. Kampaania
algab ja lõpeb seetõttu täpsel sekundil ka lehel, mis on vahemälus või kliendil
juba lahti. Seadme vale kell ei sega: brauser võrdleb oma kella serveri omaga.

= Sulgemine =

Kui klient riba sulgeb ja kampaanial on sooduskood, jääb alles kitsas riba ainult
koodiga — pakkumine ei kao ekraanilt, aga ei sega enam. Noolega saab täisriba
tagasi avada. Sulgemine jäetakse meelde brauseris kampaania ID kaupa, nii et uus
kampaania ilmub ka neile, kes eelmise sulgesid.

= Kaks keelt =

Iga tekstiväli on eraldi eesti ja inglise keeles, koos oma lingiga. Riba valib
keele Polylangi või WPML-i järgi, muidu saidi lokaadi järgi. Tühjaks jäetud
ingliskeelne väli võtab eestikeelse teksti, nii et riba ei jää kunagi tühjaks.

Nupp „Tõlgi eesti keelest" täidab ingliskeelsed väljad automaatselt. Kui seadetes
on DeepL API võti, kasutatakse DeepL-i; ilma võtmeta täidetakse väljad
sisseehitatud sõnastikuga, mis on ainult mustand ja vajab alati ülelugemist.
Tulemus on tavaline muudetav tekst ja väljad märgitakse „automaatne tõlge",
kuni neid käsitsi puudutad.

Oma tõlketeenuse saab ühendada filtriga:

`add_filter( 'wkr_autotranslate', function ( $result, $source, $target ) {
    return array( 'fields' => array( 'l1' => '...' ), 'engine' => 'minu teenus' );
}, 10, 3 );`

= WooCommerce =

Kui sooduskood on määratud ja valik „Rakenda kood ostukorvis" sees, jätab klõps
ribal koodi kliendi brauserisse küpsisesse ja soodustus läheb ostukorvi kohe, kui
seal on mõni toode. Riba link jääb puhtaks, nii et leht tuleb vahemälust. Vanad
lingid kujul `?wkr_coupon=KOOD` töötavad edasi.

Kupongi saab luua otse kampaania alt. Kastis „WooCommerce kupong" on kaks režiimi:

* Ainult näitan koodi ribal (vaikimisi) — kupong on WooCommerce'is käsitsi tehtud,
  plugin ei puutu seda. Kast näitab ainult, kas sellise koodiga kupong on olemas.
* Loo ja hoia WooCommerce kupong siit — plugin loob kampaania salvestamisel päris
  shop_coupon kirje, mis ilmub Turundus - Kupongid alla nagu iga teine kupong.
  Saad määrata soodustuse liigi ja väärtuse, vähima ostukorvi summa, kasutuslimiidid,
  üksinda kasutatavuse ja tasuta tarne.

Kupongi aegumiskuupäev tuleb kampaania lõpuajast. Algusaega WooCommerce'il endal ei
ole, seetõttu hoiab seda plugin: enne kampaania algust kood ei kehti, ka siis kui
keegi selle ära arvab. Pärast kampaania lõppu näeb klient WooCommerce'i tavalist
teadet, et kupong on aegunud.

Plugin puudutab ainult neid kuponge, mille ta ise lõi. Kui sama koodiga kupong on
juba olemas ja selle on keegi käsitsi teinud, jätab plugin selle rahule ja ütleb
seda ka administraatorile. Ülevõtmiseks tuleb eraldi linnuke märkida.

Toote- ja kategooriapiiranguid saab hoida kas WooCommerce'i kupongi all või
plugina sees. Vaikimisi plugin neid välju ei puutu. Linnuke „Halda ka toote- ja
kategooriapiiranguid siit" lülitab sisse neli välja: tooted, välistatud tooted,
tootekategooriad ja välistatud kategooriad. Valijad on WooCommerce'i enda omad,
seega otsing ja käitumine on täpselt samasugused nagu kupongi all.

Kui linnuke on sees, kirjutatakse kõik neli välja igal salvestusel üle, ka
tühjaks jäetud väljad. Kui see on väljas, jäävad kupongi all käsitsi tehtud
valikud puutumata.

Seadetes saab määrata püsivälistused — tooted ja kategooriad, mis lisatakse iga
plugina hallatava kupongi välistuste hulka. Nii ei pea kinkekaarte iga kampaania
juures uuesti meelde tuletama.

Muud peenemad kupongiseaded, näiteks lubatud e-posti aadressid ja brändid, jäävad
WooCommerce'i kupongi alla ja plugin neid üle ei kirjuta.

Kampaania prügikasti viimisel läheb hallatav kupong mustandiks; taastamisel
avaldatakse uuesti. Plugina eemaldamisel kuponge ei kustutata, sest need on seotud
juba tehtud tellimustega.

= Riba andmefail ja vahemälu =

Plugin ei tühjenda ühtegi vahemälu — ei kampaania salvestamisel, alguses, lõpus
ega plugina uuendamisel. See ei ole vajalik, sest riba ei ole lehe sees.

Kõik eetris olevad ja tulevased kampaaniad kirjutatakse staatilisse faili kaustas
`wp-content/uploads/wkr/` (üks fail keele kohta, näiteks `campaigns-et.js`). Failis
on iga kampaania algus- ja lõpuaeg ning valmis HTML. Fail kirjutatakse üle siis,
kui kampaaniat muudad, ja kord tunnis koristuseks.

Lehe päises on laadija, mis:

* küsib andmefaili (aadressi lõpus on minutipõhine number, nii et vahemälu annab
  kõige rohkem minuti vanuse faili)
* laeb riba stiilid ja skripti ainult siis, kui failis on mõni kampaania
* hoiab päiseriba jaoks ruumi kinni, kui eelmisel lehel oli päiseriba näha, et
  leht ei hüppaks

Mida see annab:

* uus või muudetud kampaania jõuab kõigile lehtedele umbes minutiga, ka keset päeva
* kampaaniad vahetuvad täpsel sekundil, ilma et server sel hetkel midagi teeks
* leht võib seista lehe- või CDN-vahemälus ükskõik kui kaua

Tulevase kampaania sooduskood ei ole andmefailis lihttekstina, vaid kodeeritult,
et seda ei saaks lihtsa tekstiotsinguga enne algust üles korjata. Kood ise hakkab
kehtima alles kampaania algusest — seda kontrollib server.

Kiirenduspluginad: kui sinu plugin lükkab JavaScripti edasi või viivitab seda kuni
külastaja esimese tegevuseni, lisa tema väljajätmiste hulka märksõna `wkr-restore`.
Ilma selleta riba töötab, aga ilmub hiljem.

Üleminek versioonilt 1.20 või vanemalt: tühjenda kiirendusplugina lehtede vahemälu
üks kord pärast uuendust. Varem vahemällu läinud lehtedel on veel vana, lehe sisse
kirjutatud riba ja laadijat neil ei ole.

Plugina väljalülitamisel kirjutatakse andmefaili tühi nimekiri, nii et riba kaob
ka vahemälus olevatelt lehtedelt.

Andmefaili sisu saab muuta filtriga:

`add_filter( 'wkr_data', function ( $data, $lang ) {
    return $data;
}, 10, 2 );`

WP-Cron: riba ise cronist ei sõltu. Cron teeb kord tunnis koristust ja annab
broneeritud sooduskoodi alguse hetkel üle (kui cron hilineb, teeb seda esimene
päring poodi). Seadete leht näitab, millal cron viimati käivitus.

== Installation ==

1. Laadi ZIP üles: Pluginad - Lisa uus - Laadi plugin üles.
2. Aktiveeri.
3. Menüüs tekib „Wonom kampaaniad". Lisa esimene kampaania.
4. Riba ilmub automaatselt. Kui tahad riba lehe sisse kindlasse kohta, kasuta
   lühikoodi `[wonom_banner position="top"]`.

== Frequently Asked Questions ==

= Riba katab ostukorvi paneeli =

Tõsta kampaania z-index madalamaks. Enamikus teemades on ostukorvi sahtel ja
modaalaknad kihil 400-600; riba vaikeväärtus 90 jääb nendest allapoole.

= Riba ei ilmu =

Kontrolli, et kampaania on avaldatud (publish), „Kampaania sisse lülitatud" on
märgitud ning praegune aeg jääb algus- ja lõpuaja vahele. Vaata ka Seaded →
Riba andmefail: fail peab olemas olema. Kui uuendasid vanemalt versioonilt,
tühjenda kiirendusplugina lehtede vahemälu üks kord.

= Kuidas teha olemasolevast kampaaniast koopia? =

Vii hiir kampaania nime kohale nimekirjas ja vali „Klooni". Sama nupp on ka
kampaania muutmisvaates avaldamiskastis. Koopia salvestatakse mustandina, kus
kujundus, mõlema keele tekstid, sooduskood ja ajastus on samad.

Kaks asja jäävad koopial teadlikult originaali külge: WooCommerce'i kupongi
haldus lülitatakse koopial välja ja seos originaali kupongiga ei kandu üle. Nii
ei saa koopia kogemata originaali kupongi muuta. Kui tahad, et ka koopia haldaks
oma kupongi, vaheta kood ära ja lülita haldus uuesti sisse.

= Kas kampaaniaid võib olla mitu korraga? =

Jah. Päisesse ja jalusesse valitakse esimene sobiv kampaania. Järjekorda saab
muuta kampaania „Järjekord" väljaga (Lehe atribuudid).

= Miks minu käsitsi tehtud kupong ei muutunud? =

See on meelega. Plugin muudab ainult neid kuponge, mille ta ise lõi. Olemasoleva
ülevõtmiseks märgi kampaania all „Võta olemasolev kupong üle".

== Automaatsed uuendused ==

Plugin oskab end ise uuendada, ilma et ZIP-i peaks iga kord käsitsi üles laadima.
Seaded on Wonom kampaaniad - Seaded - Automaatsed uuendused.

GitHubi puhul:

1. Vali allikaks „GitHubi väljalase".
2. Kirjuta hoidla kujul kasutaja/hoidla, nt wonomdigital/wonom-kampaaniariba.
3. Privaatse hoidla puhul lisa juurdepääsuvõti; avaliku puhul jäta tühjaks.

Plugin võtab hoidla viimase väljalaske (release). Kui väljalaskele on lisatud
ZIP-fail, kasutatakse seda; muidu võetakse GitHubi enda lähtekoodi ZIP ja kausta
nimi parandatakse paigalduse ajal ära.

Uus versioon jõuab poodi siis, kui väljalaske silt (tag) on suurem kui
paigaldatud versioon, nt v1.3.0 versiooni 1.2.0 vastu. Sildi number ja plugina
faili Version-rida peavad olema samad.

Oma serveri puhul vali „Oma serveris olev JSON-fail" ja anna aadress. Faili kuju:

`{
  "version": "1.3.0",
  "download_url": "https://wonom.ee/updates/wonom-kampaaniariba-1.3.0.zip",
  "requires": "6.0",
  "tested": "6.7",
  "requires_php": "7.4",
  "last_updated": "2026-09-06 10:00:00",
  "homepage": "https://wonom.ee/plugins/wonom-kampaaniariba",
  "sections": { "changelog": "Mis muutus." }
}`

Tulemust hoitakse vahemälus kuus tundi. Nupp „Kontrolli uuendusi kohe" tühjendab
vahemälu ja küsib kohe uuesti.

Kui uuendus on saadaval, ilmub olekurea kõrvale nupp „Uuenda kohe versioonile X".
Uuendus tehakse samal lehel, Pluginad-lehele minna ei ole vaja. Kui brauseris on
JavaScript keelatud või midagi ei laadinud, viib sama nupp WordPressi tavalisele
uuendusekraanile, kus uuendus samuti ära tehakse.

== Changelog ==

= 1.22.1 =
* Prügikastist avaldatuna taastatud kampaania võtab oma endise kupongi kohe tagasi; mustandina taastatud kampaania teeb seda järgmisel salvestusel.

= 1.22.0 =
* Sooduskoodil võib olla ribast erinev kehtivusaeg: uued väljad „Kood kehtib alates” ja „Kood kehtib kuni”. Tühi väli = sama aeg mis ribal.
* Prügikasti visatud kampaania ei hoia enam kupongi kinni. Prügikasti saatmisel küsitakse, kas kood jääb kehtima või läheb mustandiks.
* Valik „Ainult näitan koodi ribal” annab hallatud kupongi salvestamisel päriselt WooCommerce'ile tagasi.
* WooCommerce'i kupongi lehel on teade, kui kupongi haldab kampaania, koos nupuga „Anna kupong WooCommerce'ile tagasi”.
* Broneeritud kood liigub uuele kampaaniale selle koodi alguse hetkel.

= 1.21.1 =
* Kalender ei näita enam kell 00:00 lõppevat kampaaniat järgmise päeva lahtris. Lõpuhetk ise kampaania sisse ei kuulu, nagu ribal ja sooduskoodil.
* Välja „Lõpeb” all on selgitus: päeva lõpuni kestva kampaania lõpuks pane järgmine kuupäev kell 00:00.

= 1.21.0 =
* Riba ei kirjutata enam lehe HTML-i sisse. Kõik eetris olevad ja tulevased kampaaniad on staatilises andmefailis (wp-content/uploads/wkr/, üks fail keele kohta) ja lehel olev laadija loeb riba sealt.
* Kampaania algab ja lõpeb täpsel sekundil kõigil lehtedel korraga, ka vahemälus ja kliendil lahti olevatel. Samal sekundil lõppev ja algav kampaania vahetuvad koha peal.
* Kampaania salvestamine kirjutab üle ainult andmefaili; muudatus jõuab kõigile lehtedele umbes minutiga.
* Plugin ei tühjenda enam ühtegi vahemälu. Ära jäid aadresside tühjendus, alguse täistühjendus, nupp „Tühjenda kogu vahemälu kohe”, seade „Automaatne tühjendamine” ning Cloudflare'i väljad ja päringud.
* Brauser võrdleb oma kella serveri omaga, nii et vale kellaga seade ei näita valet kampaaniat.
* Jaluseriba libiseb sisse; päiseriba jaoks hoitakse ruum kinni, kui eelmisel lehel oli päiseriba näha.
* Stiilid ja skript laetakse ainult siis, kui mõni kampaania on olemas, ja alati plugina praeguses versioonis.
* Sooduskood on andmefailis kodeeritult, mitte lihttekstina.
* Plugina väljalülitamisel kaob riba ka vahemälus olevatelt lehtedelt.
* NB! Pärast uuendust tühjenda kiirendusplugina lehtede vahemälu üks kord (FlyingPressis „Purge pages”). Varem vahemällu läinud lehtedel on veel vana riba ja laadijat neil ei ole.

= 1.20.2 =
* Parandus: mitmekeelses poes (WPML, Polylang) kirjutati tühjendatavate aadresside nimekirjas teise keele rajad jooksva keele järgi ümber, nii et ingliskeelsed lehed jäid kampaania salvestamisel tühjendamata. Rada võetakse nüüd täpselt sellisena, nagu see nimekirjas on.
* Seadetes on näha täisaadressid, mis päriselt tühjendatakse.

= 1.20.1 =
* Kampaania alguse tühjendus on ajastatud täpselt algusajale (varem 5 sekundit hiljem), nii et alguse hetkel käivituv cron teeb selle kohe ja uus riba ilmub sekundite, mitte minutite pärast.
* Varusündmus 5 minutit hiljem tühjendab ainult siis, kui alguse hetke sündmus jäi tegemata.
* Üks tühjendus alguse kohta ka siis, kui samal hetkel algab mitu kampaaniat või üks lõpeb ja teine algab. Vahetult enne algust tehtud käsitsi tühjendus ei jäta alguse tühjendust enam vahele.
* Kampaania lõpuhetk loetakse juba lõppenuks: samal sekundil lõppev ja algav kampaania ei ole hetkekski koos eetris.

= 1.20.0 =
* Vahemälu: kampaania salvestamine, prügikasti viimine ja taastamine ei tühjenda enam kogu saiti. Tühjendatakse ainult seadetes loetletud aadressid (kuni 30), üks kord, umbes 45 sekundit pärast viimast muudatust, ja need laetakse kohe uuesti vahemällu.
* Vahemälu: kampaania lõpp ei tühjenda enam midagi — riba peidab brauser lõpuaja järgi, ka vahemälus oleval lehel ja ka ilma loendurita.
* Vahemälu: plugina uuendamine ei tühjenda enam midagi; objektivahemälu plugin ei puutu; Cloudflare'is tühjendatakse kampaania muutmisel ainult loetletud aadressid.
* Vahemälu: kampaania algus tühjendab ajutiselt veel kõik lehed, aga üks kord ka siis, kui samal hetkel algab mitu kampaaniat. See kaob järgmises suuremas versioonis.
* Testsait (aadress algab test., staging. või dev. või keskkonna tüüp ei ole production): Cloudflare'i päringuid ei tehta ja kampaania algus ei tühjenda kõiki lehti. Filter `wkr_is_test_host`.
* Sooduskood: riba link on puhas. Klõps jätab koodi küpsisesse ja soodustus läheb korvi, kui seal on mõni toode. Vanad `?wkr_coupon=` lingid töötavad edasi.
* Sooduskood: broneeritud koodi üleandmine kampaania alguses on lukuga, nii et samal sekundil koodi sisestav klient ei saa vastuseks „kood ei kehti”.
* Parandus: „Nulli loendur selle salvestusega” ei läinud arvesse, kui kood oli broneeritud ja tuli kampaaniasse alles alguse hetkel. Nüüd nullitakse loendur üleandmisel.
* „Kontrolli uuendusi kohe” värskendab ainult selle plugina kirjet ega sunni teisi pluginaid end uuesti kontrollima.
* Nupp „Tühjenda kogu vahemälu kohe” küsib kinnitust; seadetes on näha viimased tühjendused.
* NB! Versioonilt 1.19.0 või vanemalt uuendades tühjendab vana kood veel ühe korra kogu vahemälu. Vältimiseks: lülita plugin välja → Lisa uus → Laadi ZIP üles → „Asenda praegune” → lülita sisse.

= 1.19.0 =
* Muudatus: lõpuni jäänud aja loendur ilmub ribale alles kampaania viimasel 24 tunnil. Varem seisis see ribal kogu kampaania aja, ka siis, kui lõpuni oli veel nädal.
* Otsuse teeb brauser, mitte server: vahemälus seisev leht (FlyingPress, Cloudflare) näitab loendurit õigel hetkel ka siis, kui see on salvestatud päevi varem. Loenduri ilmumisel arvutatakse jaluse riba kõrgus uuesti.
* Linnukese silt on nüüd „Näita lõpuni jäänud aega (viimased 24 tundi)" ja eelvaade näitab loendurit ilma päevadeta.

= 1.18.0 =
* Plugina nimi on nuud „Wonom kampaaniad" — nii pluginate lehel, admin-menuus kui ka plugina enda ularibal. Varem oli „Wonom Kampaaniariba" ja menuus lihtsalt „Kampaaniariba".
* Ingliskeelses administraatorivaates on nimi „Wonom campaigns". Kaasa tuli kaust `languages` en_US tolkega, mis katab nime, menuu ja postitupi sildid. Ulejaanud liides jaab eesti keelde.
* WooCommerce'i kupongi kirjeldus on nuud „Loodud Wonom kampaaniaga: …".
* Plugina kaust, failinimi ja uuenduste allikas ei muutunud, nii et olemasolevad paigaldused uuenevad tavaparaselt edasi.

= 1.17.0 =
* Uus: koodi ulevotmist saab nuud tulevikku ajastada. Kui kampaania algab alles hiljem, siis kupongi kohe ara ei votata, vaid kood broneeritakse ja omanik vahetub tapselt selle kampaania alguse hetkel. Eetris olev kampaania tootab oma akna lopuni, keegi ei tomba vaipa alt.
* Varem oli ulevotmine hetkeline: tulevase kampaania seadistamine votis koodi elusalt kampaanialt kohe ara ja riba reklaamis poes koodi, mis ostukorvis enam ei kehtinud.
* Kattuvate kampaaniate puhul votab koodi ule see, kes hiljem algab. Salvestamise eel oeldakse kattumine eraldi valja.
* Olekuriba naitab molemat poolt: broneerinud kampaanial „Kood on broneeritud — liigub siia 05.10.2026 18:40", praegusel omanikul „Kood on broneeritud: 05.10.2026 18:40 laheb see ule kampaaniale X".
* Uleandmine kaib WP-Croniga tapselt oigel ajal. Kui cron hilineb voi on surnud, teeb plugin vahetuse esimesel lehevaatel ise ara — kupongi kehtivus ei soltu cronist.
* Koodi ei vii endaga kaasa kampaania, kes pariselt eetrisse ei lainud: mustandi, valja lulitatud voi juba loppenud kampaania broneering kustub ilma kupongi puutumata.

= 1.16.2 =
* Parandus: linnuke „Vota olemasolev kupong ule ja hakka seda siit haldama" ei teinud midagi, kui vali „Mida pluginaga kupongiga teha" oli „Ainult naitan koodi ribal". Nuud lulitab linnuke reziimi ise umber ja kupongi seaded tulevad kohe nahtavale.
* Parandus: kui linnuke salvestati ikkagi ilma halduseta, vaikis plugin maha. Nuud utleb ara, et kupongi ei puututud ja mida teha.
* Parandus: peidetud vihjed ja hoiatused paistsid ekraanil valja. Meie oma klasside `display: block` kirjutas brauseri `[hidden]` reegli ule, mistottu kampaania muutmise ekraanil seisid alaliselt nahtaval tuhi kollane riba, nupp „Tolgi eesti keelest", automaatse tolke marge ja kihi korguse hoiatus — ka siis, kui kiht oli taiesti normaalne.
* Ulevotmise ja loenduri nullimise linnukesed kustutatakse nuud kohe salvestuse alguses, nii et poolele teele katkenud salvestus ei jata neid rippuma.

= 1.16.1 =
* Parandus: kuupaeva- ja kellaajavalija ei tootanud. Uue kujunduse meta box'i reegel `overflow: hidden` loikas kalendriakna kaardi servas pooleks — kellaaja valikud ja nupp „Valmis" jaid tapselt sinna loigatud ossa ega olnud klikitavad. Umarad nurgad seda reeglit ei vaja, nii et see sai valja.
* Parandus: kampaania muutmise ekraanil ilmus lehe keskele tekst „Aitah, et kasutad WordPressi" koos versiooninumbriga. Pohjus oli uleliigne `</div>` WooCommerce'i kupongi meta box'is, mis sulges WordPressi lehekonteineri liiga vara ja jattis jaluse sellest valja. Jalus on taas lehe lopus.

= 1.16.0 =
* Uus: kupongikaardil on nuud „Kasutuskordade loendur" plokk. See naitab, mitu korda koodi on kasutatud ja mis on piir, ning linnukesega „Nulli loendur selle salvestusega" saab arvestuse otsast peale alustada.
* Nullimine puhastab ka kliendipohise ajaloo (`_used_by`), nii et varem ostnud klient saab sama koodi uuel jooksul uuesti kasutada. Ainult loenduri nullimine oleks nad endiselt ukse taha jatnud.
* Linnuke kehtib uhe korra ja kustutatakse kohe, nii et jargmine salvestus loendurit ei puutu. WooCommerce'i muugiaruandeid nullimine ei muuda.
* Salvestamise jarel oeldakse ara, mitu kasutust loendur enne nullimist naitas.

= 1.15.0 =
* Uus: sama sooduskoodi saab nuud kampaaniast kampaaniasse edasi anda. Varem luku plugin koodi esimese kampaania kulge jaadavalt — uus kampaania sai ainult teate „koodi haldab juba kampaania X" ja kupong jai vana aegumiskuupaevaga kehtetuks.
* Olemasoleva koodi juures ilmub nuud alati linnuke „Vota olemasolev kupong ule". Ulevotmisel saab kupong selle kampaania kuupaevad ja hakkab uuesti kehtima, vana kampaania viide koristatakse ara.
* Hoiatus on olukorrapohine: kui eelmine kampaania on labi, siis oeldakse seda; kui see on veel eetris voi ootel, hoiatatakse, et kood lakkab seal kehtimast.
* Uus: kui kupongi kasutuskorrad on tais, utleb plugin seda kohe koodi valja juures. See on korduvkasutatava koodi puhul sagedaseim pohjus, miks kood ka kehtiva kampaania ajal ostukorvis vastu votmata jaab — WooCommerce kontrollib kasutuspiiri enne plugina oma kontrolli.
* Ulevotmine annab nuud rohelise teate selle kohta, mis tegelikult juhtus.
* Parandus: kustutatud kampaania jattis koodi igaveseks lukku. Nuud koheldakse sellist kupongi nagu kasitsi tehtut.

= 1.14.3 =
* Parandus: „WooCommerce kupong" kaardis olid kupongivaljad kolmes ja tootevalikud kahes veerus, nii et servad hupelsid 334 piksli vorra. Kogu kaart kaib nuud uhe kaheveerulise ruudustiku jargi.
* Kupongivaljad on paarides: soodustuse liik ja vaartus, kasutuskorrad kokku ja kliendi kohta, vahim ostukorvi summa ja lisavalikud.

= 1.14.2 =
* Parandus: kampaania „Kujundus" kaardis olid 2- ja 3-veerulised read labisegi, mistottu valjade servad hupelsid kaardi sees 231 piksli vorra. Koik valjad kaivad nuud uhe ja sama kolmeveerulise ruudustiku jargi.
* Valjad on umber ruhmitatud loogilisemalt: varvid, kiri, paigutus, sulgemisnupp, teema „keri ules" nupp ja kleepuva riba seaded igauks oma real.
* WooCommerce'i kupongi kaardis said toote- ja kategooriavalikud uhe ruudustiku ning „Valista kategooriad" sama selgituse mis teised valjad.

= 1.14.1 =
* Parandus: seadete lehel hupeldas kaardi serv sisse ja valja, sest kirjeldused olid pealkirjast ja tabelist kitsamad. Iga plokk on nuud uks paris kaart ja sisu laius ei liiguta enam serva.
* Esimene plokk sai pealkirja „Riba vaikeseaded" ja korraliku ulemise aare.

= 1.14.0 =
* Uus admin-kujundus, sama disainikeelega mis Wonom Feedil: ulemine riba navigatsiooniga, kaardid, pehmed nurgad ja uhtne varvipalett.
* Kujundus rakendub ainult plugina enda ekraanidel, ulejaanud WordPressi admin jaab puutumata.

= 1.13.0 =
* Parandus: varem suletud riba ilmus jargmisel lehel hetkeks uuesti avatuna ja kukkus kokku alles siis, kui kulastaja midagi tegi. Oleku taastamine kaib nuud uhe skriptiga head-osas, mida kiirendusplugin ei saa valiseks defer-skriptiks umber kirjutada.
* Kui kiirendusplugin viivitab kogu JavaScripti, lisa valjajatmiste hulka margisona wkr-restore. Seadete leht tuletab seda meelde.

= 1.12.0 =
* Sulgemisnupu ja avamisnoole suurus on nuud kampaania seadetes muudetav. Nool oli varem fikseeritud 12px ja jai suurema risti korval kiduraks.
* Avamisnool on naha ka administraatori elavas eelvaates.

= 1.11.2 =
* Riba kulgpolster arvestab nuud sulgemisnupu kaugusega servast, nii et tekst ei jookse kunagi nupu alla ega satu sellega vastamisi.

= 1.11.1 =
* Riba sulgemisnupp on 1,5 korda suurem ja rasvases kirjas.

= 1.11.0 =
* Sooduskoodi olekut kontrollitakse kohe koodi kirjutamise ajal. Kloonitud kampaanial ei pea enam vahepeal salvestama, et naha, kas uue koodiga kupong on olemas.
* Uks keelevaheti mole asemel: Sisu ploki keelevalik juhib nuud ka eelvaadet.

= 1.10.3 =
* Varskelt paigaldatud pluginal on cron-oleku hoiatusel 20-minutiline ooteaken, et esimese pulsi ootamise ajal ei naidataks alusetut hoiatust.

= 1.10.2 =
* Seadete leht kontrollib nüüd, kas WP-Cron päriselt käivitub, mitte ainult seda, kas DISABLE_WP_CRON on seatud. Serveri cron-tööga poes ei näidata enam alusetut hoiatust.
* Plugina eemaldamine koristab kõik seaded ja ajastatud sündmused.

= 1.10.1 =
* Parandus: seadete lehe uuendusnupp jäi tiksuma ega teinud midagi. WordPressi wp.updates.updatePlugin eeldab Pluginate ekraani ja viskas mujal vea enne päringu saatmist. Nüüd kasutatakse alusfunktsiooni wp.updates.ajax ja nupp ei saa enam kinni jääda.

= 1.10.0 =
* Cloudflare'i serva vahemälu tühjendatakse koos ülejäänuga. Seadetes Zone ID ja API tokeni väljad.
* Ühe päringu jooksul tühjendatakse ainult korra, et Cloudflare'i päevalimiiti mitte kulutada.
* Manuaalne tühjendus näitab ka vead, kui Cloudflare vastas tõrkega.

= 1.9.0 =
* Vahemälu tühjendatakse automaatselt: kampaania salvestamisel, kampaania algus- ja lõpuajal ning plugina uuendamisel.
* Seadete lehel on näha leitud vahemälupluginad ja nupp „Tühjenda vahemälu kohe".
* Filter wkr_purge_cache serveri- või Cloudflare'i tasemel vahemälu jaoks.

= 1.8.0 =
* Uuenduse saab teha otse seadete lehelt — nupp „Uuenda kohe versioonile X" olekurea kõrval.

= 1.7.0 =
* Kupongile saab plugina alt määrata tooted, tootekategooriad ja välistused, kasutades WooCommerce'i enda valijaid.
* Seadetes püsivälistused, mis lisatakse iga hallatava kupongi külge — kinkekaardid jäävad soodustusest välja ilma iga kord meelde tuletamata.
* Valik „Ei kehti allahindlusega toodetele".

= 1.6.0 =
* Teema ujuv „keri üles" nupp ei jää enam jaluse riba ette. Valikud: tõsta riba kohale (vaikimisi), peida, jäta riba taha või ära puutu.

= 1.5.0 =
* Kampaania kloonimine: „Klooni" link nimekirjas ja avaldamiskastis. Koopia tuleb mustandina, kupongihaldus on välja lülitatud.

= 1.4.0 =
* Kleepuv jaluse riba tõuseb automaatselt teema alumise mobiilimenüü kohale, nii et see ei jää enam menüü taha. Lisaks käsitsi lisanihe.

= 1.3.0 =
* Sulgemisnupu asukoht on nüüd seadistatav: parem või vasak serv, ülanurgad, koos kaugusega servast. Vajalik siis, kui teema „keri üles" nupp või vestlusmull istub nupu peal.

= 1.2.2 =
* Parandus: kokkukäinud sooduskoodi riba oli korraga täisribaga nähtaval.

= 1.2.1 =
* Riba nupud peavad nüüd vastu teemade nupustiilidele (WoodMart jt).

= 1.2.0 =
* Automaatsed uuendused GitHubi väljalaskest või oma serveri JSON-failist.
* Seadetes näeb paigaldatud ja saadaoleva versiooni ning saab kohe kontrollida.

= 1.1.0 =
* WooCommerce'i kupongi loomine ja hoidmine otse kampaania alt.
* Kupong kehtib ainult kampaania ajal — WooCommerce'i puuduv „kehtib alates".
* Käsitsi tehtud kuponge ei muudeta ilma sõnaselge ülevõtmiseta.

= 1.0.0 =
* Esimene versioon.
