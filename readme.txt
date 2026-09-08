=== Wonom Kampaaniariba ===
Contributors: wonomdigital
Tags: woocommerce, banner, campaign, coupon, promotion
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.11.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Ajastatud sooduspakkumiste riba poe päisesse ja jalusesse. Kampaaniad kalendris, tekstid kahes keeles, sooduskood ühe klõpsuga kopeeritav.

== Description ==

Kampaaniariba on WooCommerce'i poe pakkumiste teavitusriba, mille saab ette ära ajastada.

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

Nähtavuse otsustab PHP, mitte JavaScript — leht jääb vahemälusõbralikuks.
Kui kampaanial on tagasiloendus sisse lülitatud, peidab lühike JS-taimer riba
täpsel sekundil ka siis, kui leht on juba vahemälust serveeritud.

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

Kui sooduskood on määratud ja valik „Rakenda kood ostukorvis" sees, lisatakse riba
lingile `?wkr_coupon=KOOD` ja kood rakendatakse kliendi ostukorvis automaatselt.

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

= Vahemälu =

Riba kirjutatakse lehe HTML-i sisse. See hoiab lehe kiirena, aga tähendab, et
vahemällu salvestatud leht hoiab riba sellisena, nagu see salvestamise hetkel
oli. Plugin tühjendab lehtede vahemälu ise:

* kampaania salvestamisel, prügikasti viimisel ja taastamisel
* kampaania algus- ja lõpuajal, ajastatud WP-Cron sündmusega
* plugina enda uuendamisel, sest markup või stiilid võivad olla muutunud

Tuntud pluginad: FlyingPress, WP Rocket, LiteSpeed Cache, W3 Total Cache,
WP Super Cache, WP Fastest Cache, Cache Enabler, SiteGround Optimizer. Seadete
lehel on kirjas, millised neist poes leiti, ja nupp „Tühjenda vahemälu kohe".

= Cloudflare =

Cloudflare vaikimisi HTML-i ei vahemälusta, ainult staatilisi faile. Kui aga
poel on Cache Everything reegel või APO, hoiab Cloudflare ka lehe koopiat servas
ja serveripoolne tühjendus sinna ei ulatu.

Kontrolli oma poe päistest: `cf-cache-status: DYNAMIC` tähendab, et HTML-i ei
vahemälustata ja midagi teha ei ole vaja. `HIT` või `MISS` tähendab, et
vahemälustatakse.

Seadetes on väljad Zone ID ja API token. Token tee Cloudflare'is
My Profile - API Tokens - Create Token, õigusega Zone - Cache Purge - Purge, ja
piira see ainult selle domeeni tsooniga. Globaalset API võtit siia panna ei tohi.

Tasuta ja Pro paketis saab tühjendada ainult kogu tsooni korraga. Riba on igal
lehel, seega see on siin niikuinii õige valik.

Muu vahemälu saab ühendada filtriga:

`add_action( 'wkr_purge_cache', function ( $done ) {
    // oma vahemälu tühjendamine
} );`

Algus- ja lõpuaja tühjendus sõltub WP-Cronist, mis WordPressis käivitub
lehekülastuste pealt. Tugevalt vahemälustatud poes ei pruugi külastus PHP-ni
jõuda, seetõttu tasub seadistada serveris päris cron-töö, mis käivitab
wp-cron.php iga paari minuti tagant. Seadete leht ajastab ise tunnise pulsi ja naitab, millal cron viimati kaivitus. Nii on kohe naha, kas seadistus toimib.

Staatiliste failide pärast muretsema ei pea: CSS-i ja JS-i aadressis on plugina
versiooninumber, seega uuendus toob brauserisse uued failid ise.

== Installation ==

1. Laadi ZIP üles: Pluginad - Lisa uus - Laadi plugin üles.
2. Aktiveeri.
3. Menüüs tekib „Kampaaniariba". Lisa esimene kampaania.
4. Riba ilmub automaatselt. Kui teema ei kasuta wp_body_open haaki, paiguta
   riba lühikoodiga `[wonom_banner position="top"]`.

== Frequently Asked Questions ==

= Riba katab ostukorvi paneeli =

Tõsta kampaania z-index madalamaks. Enamikus teemades on ostukorvi sahtel ja
modaalaknad kihil 400-600; riba vaikeväärtus 90 jääb nendest allapoole.

= Riba ei ilmu =

Kontrolli, et kampaania on avaldatud (publish), „Kampaania sisse lülitatud" on
märgitud ning praegune aeg jääb algus- ja lõpuaja vahele. Kui teema ei kutsu
wp_body_open haaki, kasuta lühikoodi.

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
Seaded on Kampaaniariba - Seaded - Automaatsed uuendused.

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
