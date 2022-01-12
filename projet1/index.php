<?php
stream_context_set_default(array('http' => array('proxy' => 'tcp://www-cache:3128', 'request_fulluri' => true)));

// Récupération des données de géolocalisation
$ipURI = "http://ip-api.com/xml/?lang=fr";
$geolocData = simplexml_load_string(file_get_contents($ipURI));
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

        $ipVeloStationURI = "http://www.velostanlib.fr/service/carto";
        $dataVeloStation = simplexml_load_string(file_get_contents($ipVeloStationURI))->markers;
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

        $urlPlaces = "http://www.velostanlib.fr/service/stationdetails/nancy/";
        $newTab = array();
        foreach ($stations as $station) {
            $data = simplexml_load_string(file_get_contents($urlPlaces . $station['number']));
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
        $json = json_encode($newTab);

        if ($http_response_header[0] === 'HTTP/1.1 200 OK') {
            echo "<br>";
            // Affichage de la carte
            $html = <<<HTML
                <div id="map">
                </div>
                <script src="https://unpkg.com/leaflet@1.3.1/dist/leaflet.js" integrity="sha512-/Nsx9X4HebavoBvEBuyp3I7od5tA0UzAxs+j83KgC8PU0kgB4XiK4Lfe4y4cgBtaRJQEIFCW+oC506aPT2L1zw==" crossorigin=""></script>
                <script>
                    function initMap() {
                        myMap = L.map('map').setView([$geolocData->lat, $geolocData->lon], 15)
                        L.tileLayer('https://{s}.tile.openstreetmap.fr/osmfr/{z}/{x}/{y}.png', {
                            // Lien vers la source des données
                            attribution: 'données © <a href="//osm.org/copyright">OpenStreetMap</a>/ODbL - rendu <a href="//openstreetmap.fr">OSM France</a>',
                            minZoom: 14,
                            maxZoom: 17
                        }).addTo(myMap)
                        L.marker([$geolocData->lat, $geolocData->lon]).addTo(myMap)
                        // Par magie, ça marche SEULEMENT si on a ce console.log donc pas touche
                        console.log($json);
                        $json.forEach((el)=>{
                           L.marker([el.lat[0], el.lng[0]]).addTo(myMap)
                           .bindPopup(
                                 "<b>" + el.name[0] + "</b><br>" + el.address[0] + "<br>" + el.bikes[0] + " vélos disponibles<br>" + el.slots[0] + " places disponibles"
                           )
                        })
                    }
                    window.onload = function () {
                        initMap()
                    }
                </script>
                HTML;
            echo $html;
        }
    } else {
        echo "Erreur lors de la récupération des données météo";
    }
} else {
    echo "Erreur de connexion au serveur d'IP-API.com";
}