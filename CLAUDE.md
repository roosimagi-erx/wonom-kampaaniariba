# Wonom kampaaniad (wonom-kampaaniariba)

Enne tööd loe läbi mõlemad juhendid kaustast C:\Users\User\OneDrive - Wonom Digital OÜ\Wonom juhendid (wonom-woocommerce-plugin-alus.md ja wonom-pluginad-serveri-koormuse-reeglid.md). Need failid ei tohi sattuda reposse ega GitHubi ja neid ei tohi sellest kaustast mujale tõsta ega kopeerida.

## Väljalase

Väljalaske skript asub plugina kaustast üks tase kõrgemal (väljaspool hoidlat). Enne väljalaset lisa `CHANGELOG.md`-sse sektsioon `## X.Y.Z - AAAA-KK-PP` ja `readme.txt`-sse sama versiooni kirje.

    ..\build-release.ps1 -Version X.Y.Z -Release

Skript tõstab versiooni, ehitab ZIP-i (ilma `*.md` ja arendusfailideta), teeb commiti, pushib ja loob GitHubi väljalaske, mille märkmete esimene rida on „TESTIMATA”. Üks versioon korraga. Testis ja live'is uuendab ainult Erki, alati enne testis.
