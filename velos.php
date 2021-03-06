<?php
stream_context_set_default(array('http' => array('proxy' => 'tcp://www-cache:3128', 'request_fulluri' => true), 'ssl' => array('verify_peer' => false, 'verify_peer_name' => false)));

function sky_curl_get_file_contents($URL)
{
    $c = curl_init();
    curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($c, CURLOPT_URL, $URL);
    curl_setopt($c, CURLOPT_PROXY, 'tcp://www-cache:3128');
    $contents = curl_exec($c);
    curl_close($c);
    if ($contents)
        return $contents;
    else
        return false;
}

// Récupération des données de géolocalisation
$ipGeoloc = "http://ip-api.com/xml/?lang=fr";
$geolocData = simplexml_load_string(file_get_contents($ipGeoloc));
$coo = $geolocData->lat . "," . $geolocData->lon;

if ($http_response_header[0] === 'HTTP/1.1 200 OK') {
    // Récupération des données météo
    $lienMeteoAPI = "https://www.infoclimat.fr/public-api/gfs/xml?_ll=" . $coo . "&_auth=ARsDFFIsBCZRfFtsD3lSe1Q8ADUPeVRzBHgFZgtuAH1UMQNgUTNcPlU5VClSfVZkUn8AYVxmVW0Eb1I2WylSLgFgA25SNwRuUT1bPw83UnlUeAB9DzFUcwR4BWMLYwBhVCkDb1EzXCBVOFQoUmNWZlJnAH9cfFVsBGRSPVs1UjEBZwNkUjIEYVE6WyYPIFJjVGUAZg9mVD4EbwVhCzMAMFQzA2JRMlw5VThUKFJiVmtSZQBpXGtVbwRlUjVbKVIuARsDFFIsBCZRfFtsD3lSe1QyAD4PZA%3D%3D&_c=19f3aa7d766b6ba91191c8be71dd1ab2";
    $data = simplexml_load_string(file_get_contents($lienMeteoAPI));
    if ($http_response_header[0] === 'HTTP/1.1 200 OK') {
        // Chargement du fichier XSL
        $xsl = new DOMDocument;
        $xsl->load('meteo.xsl');

        // Configuration du transformateur
        $proc = new XSLTProcessor;
        $proc->importStyleSheet($xsl); // attachement des règles xsl

        echo $proc->transformToXml($data);

        // Récupération des stations
        $ipVeloStationURI = "http://www.velostanlib.fr/service/carto";
        $dataVeloStation = simplexml_load_string(file_get_contents($ipVeloStationURI))->markers;
        if ($http_response_header[0] === 'HTTP/1.1 200 OK') {
            $stations = array();
            foreach ($dataVeloStation->marker as $station) {
                $stations[] = array(
                    'number' => $station['number'],
                    'lat' => $station['lat'],
                    'lng' => $station['lng'],
                    'name' => $station['name'],
                    'address' => $station['address'],
                );
            }
            // Récupération des disponibilités
            $urlPlaces = "http://www.velostanlib.fr/service/stationdetails/nancy/";
            $newTab = array();
            foreach ($stations as $station) {
                $data = simplexml_load_string(file_get_contents($urlPlaces . $station['number']));
                if ($http_response_header[0] === 'HTTP/1.1 200 OK') {
                    $newTab[] = array(
                        'number' => $station['number'],
                        'lat' => $station['lat'],
                        'lng' => $station['lng'],
                        'name' => $station['name'],
                        'address' => $station['address'],
                        'bikes' => $data->available,
                        'slots' => $data->free
                    );
                }
            }
            $jsonDataStations = json_encode($newTab);
        }

        if ($http_response_header[0] === 'HTTP/1.1 200 OK') {
            // Récupération de la qualité de l'air
            $urlQualiteAir = "https://services3.arcgis.com/Is0UwT37raQYl9Jj/arcgis/rest/services/ind_grandest/FeatureServer/0/query?where=lib_zone%3D%27Nancy%27&objectIds=&time=&geometry=&geometryType=esriGeometryEnvelope&inSR=&spatialRel=esriSpatialRelIntersects&resultType=none&distance=0.0&units=esriSRUnit_Meter&returnGeodetic=false&outFields=*&returnGeometry=true&featureEncoding=esriDefault&multipatchOption=xyFootprint&maxAllowableOffset=&geometryPrecision=&outSR=&datumTransformation=&applyVCSProjection=false&returnIdsOnly=false&returnUniqueIdsOnly=false&returnCountOnly=false&returnExtentOnly=false&returnQueryGeometry=false&returnDistinctValues=false&cacheHint=false&orderByFields=&groupByFieldsForStatistics=&outStatistics=&having=&resultOffset=&resultRecordCount=&returnZ=false&returnM=false&returnExceededLimitFeatures=true&quantizationParameters=&sqlFormat=none&f=pjson&token=";
            $dataAir = sky_curl_get_file_contents($urlQualiteAir);
            if ($http_response_header[0] === 'HTTP/1.1 200 OK') {
                // Récupération de la localisation de l'IUT
                $urlApi = 'https://api-adresse.data.gouv.fr/search/?q=';
                $address = "IUT Nancy-Charlemagne";
                $urlApi = $urlApi . str_replace(' ', '+', $address);
                $geocode = file_get_contents($urlApi);
                if ($http_response_header[0] === 'HTTP/1.1 200 OK') {
                    $output = json_decode($geocode);
                    $lon = $output->features[0]->geometry->coordinates[0];
                    $lat = $output->features[0]->geometry->coordinates[1];
                } else {
                    echo "Erreur de récupération de l'adresse de l'IUT";
                }

                // Affichage de la carte
                $html = <<<HTML
                <h2 class="ms-4">Carte des parkings velolib de Nancy</h2>
                <div id="map" style="height: 55vh;" class="ms-5 w-75">
                </div>
                <div id="apiCalls"></div>
                <link rel="stylesheet" href="./bootstrap.css" />
                <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" integrity="sha512-xodZBNTC5n17Xt2atTPuE1HxjVMSvLVW9ocqUKLsCC5CXdbqCmblAshOMAS6/keqq/sMZMZ19scR4PsZChSR7A==" crossorigin="" />
                <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js" integrity="sha512-XQoYMqMTK8LvdxXYG3nZ448hOEQiglfqkJs1NOQV44cWnUrBc8PkAOcXy20w0vlaXaVUearIOBhiXZ5V3ynxwA==" crossorigin=""></script>
                <script>
                    function initMap() {
                        const myMap = L.map('map', { tap: false }).setView([$geolocData->lat, $geolocData->lon], 15)
                        L.tileLayer('https://{s}.tile.osm.org/{z}/{x}/{y}.png', {
                            // Lien vers la source des données
                            attribution: 'données © <a href="//osm.org/copyright">OpenStreetMap</a>/ODbL - rendu <a href="//openstreetmap.fr">OSM France</a>',
                        }).addTo(myMap)

                        const parkingIcon = L.icon({
                            iconUrl: './assets/icons/parking-solid.svg',
                            iconSize: [24, 24],
                        });
                        
                        const json = $jsonDataStations
                        json.forEach((el)=>{
                           L.marker([el.lat[0], el.lng[0]], { icon: parkingIcon }).addTo(myMap)
                           .bindPopup(
                                 "<b>" + el.name[0] + "</b><br>" + el.address[0] + "<br>" + el.bikes[0] + " vélos disponibles<br>" + el.slots[0] + " places disponibles"
                           )
                        })

                        const userIcon = L.icon({
                            iconUrl: './assets/icons/user-solid.svg',
                            iconSize: [24, 24],
                        });

                        L.marker([$geolocData->lat, $geolocData->lon], { icon: userIcon }).addTo(myMap).bindPopup("<b>Vous êtes ici</b>")

                        if ($lat && $lon){
                            const userIcon = L.icon({
                                iconUrl: './assets/icons/graduation-cap-solid.svg',
                                iconSize: [24, 24],
                            });
                            L.marker([$lat, $lon], { icon: userIcon }).addTo(myMap).bindPopup("<b>L'IUT est ici</b>")
                        }

                        const jsonAir = $dataAir
                        document.getElementById('conditions').innerHTML += "<h2 class='ms-4'>Qualité de l'air du jour : " + jsonAir.features[0].attributes.lib_qual + "</h2><hr/>"

                        const coo = "<?php echo $coo; ?>"
                        document.getElementById('apiCalls').innerHTML = `
                        <hr/>
                        <div class='ms-4'>
                            <h2>Appels API :</h2>
                            <ul>
                                <li>
                                    <p>Géolocalisation : <a href="http://ip-api.com/xml/?lang=fr">http://ip-api.com/xml/?lang=fr</a></p>
                                </li>
                                <li>
                                    <p>Infos climatiques : <a href="https://www.infoclimat.fr/public-api/gfs/xml?_ll=${coo}&_auth=ARsDFFIsBCZRfFtsD3lSe1Q8ADUPeVRzBHgFZgtuAH1UMQNgUTNcPlU5VClSfVZkUn8AYVxmVW0Eb1I2WylSLgFgA25SNwRuUT1bPw83UnlUeAB9DzFUcwR4BWMLYwBhVCkDb1EzXCBVOFQoUmNWZlJnAH9cfFVsBGRSPVs1UjEBZwNkUjIEYVE6WyYPIFJjVGUAZg9mVD4EbwVhCzMAMFQzA2JRMlw5VThUKFJiVmtSZQBpXGtVbwRlUjVbKVIuARsDFFIsBCZRfFtsD3lSe1QyAD4PZA%3D%3D&_c=19f3aa7d766b6ba91191c8be71dd1ab2">https://www.infoclimat.fr/public-api/gfs/xml?_ll=${coo}&_auth=ARsDFFIsBCZRfFtsD3lSe1Q8ADUPeVRzBHgFZgtuAH1UMQNgUTNcPlU5VClSfVZkUn8AYVxmVW0Eb1I2WylSLgFgA25SNwRuUT1bPw83UnlUeAB9DzFUcwR4BWMLYwBhVCkDb1EzXCBVOFQoUmNWZlJnAH9cfFVsBGRSPVs1UjEBZwNkUjIEYVE6WyYPIFJjVGUAZg9mVD4EbwVhCzMAMFQzA2JRMlw5VThUKFJiVmtSZQBpXGtVbwRlUjVbKVIuARsDFFIsBCZRfFtsD3lSe1QyAD4PZA%3D%3D&_c=19f3aa7d766b6ba91191c8be71dd1ab2</a></p>
                                </li>
                                <li>
                                    <p>Liste des stations de vélib : <a href="http://www.velostanlib.fr/service/carto">http://www.velostanlib.fr/service/carto</a></p>
                                </li>
                                <li>
                                    <p>Infos sur les stations de vélib : <a href="http://www.velostanlib.fr/service/stationdetails/nancy/">http://www.velostanlib.fr/service/stationdetails/nancy/</a></p>
                                </li>
                                <li>
                                    <p>Infos sur qualité de l'air : <a href="https://services3.arcgis.com/Is0UwT37raQYl9Jj/arcgis/rest/services/ind_grandest/FeatureServer/0/query?where=lib_zone%3D%27Nancy%27&objectIds=&time=&geometry=&geometryType=esriGeometryEnvelope&inSR=&spatialRel=esriSpatialRelIntersects&resultType=none&distance=0.0&units=esriSRUnit_Meter&returnGeodetic=false&outFields=*&returnGeometry=true&featureEncoding=esriDefault&multipatchOption=xyFootprint&maxAllowableOffset=&geometryPrecision=&outSR=&datumTransformation=&applyVCSProjection=false&returnIdsOnly=false&returnUniqueIdsOnly=false&returnCountOnly=false&returnExtentOnly=false&returnQueryGeometry=false&returnDistinctValues=false&cacheHint=false&orderByFields=&groupByFieldsForStatistics=&outStatistics=&having=&resultOffset=&resultRecordCount=&returnZ=false&returnM=false&returnExceededLimitFeatures=true&quantizationParameters=&sqlFormat=none&f=pjson&token=">https://services3.arcgis.com/Is0UwT37raQYl9Jj/arcgis/rest/services/ind_grandest/FeatureServer/0/query?where=lib_zone%3D%27Nancy%27&objectIds=&time=&geometry=&geometryType=esriGeometryEnvelope&inSR=&spatialRel=esriSpatialRelIntersects&resultType=none&distance=0.0&units=esriSRUnit_Meter&returnGeodetic=false&outFields=*&returnGeometry=true&featureEncoding=esriDefault&multipatchOption=xyFootprint&maxAllowableOffset=&geometryPrecision=&outSR=&datumTransformation=&applyVCSProjection=false&returnIdsOnly=false&returnUniqueIdsOnly=false&returnCountOnly=false&returnExtentOnly=false&returnQueryGeometry=false&returnDistinctValues=false&cacheHint=false&orderByFields=&groupByFieldsForStatistics=&outStatistics=&having=&resultOffset=&resultRecordCount=&returnZ=false&returnM=false&returnExceededLimitFeatures=true&quantizationParameters=&sqlFormat=none&f=pjson&token=</a></p>
                                </li>
                            </ul>
                        </div>`

                    }
                    window.onload = function () {
                        initMap()
                    }
                </script>
                HTML;
                echo $html;
            } else {
                echo "Erreur de récupération de la qualité de l'air";
            }
        }
    } else {
        echo "Erreur lors de la récupération des données météo";
    }
} else {
    echo "Erreur de connexion au serveur d'IP-API.com";
}
