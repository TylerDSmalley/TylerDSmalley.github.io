<?php

// PlaceId
$montreal = "YMQA-sky";

$bahamas = "BS-sky";
$fiji = "FJ-sky";
// $maldives = "MV-sky";
// $aruba = "AW-sky";
// $borabora = "BOB-sky";
$hawaii = "HNL-sky"; // Honolulu
$frenchPolynesia = "PF-sky";

echo "Flight Data" . "<br><br><br>";


// Swap out location variables to change search results
displayResults($montreal, $bahamas);


function callAPI($url) {
	$curl = curl_init($url);

	curl_setopt_array($curl, [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_SSL_VERIFYPEER => FALSE,
		CURLOPT_SSL_VERIFYHOST => FALSE,
        CURLOPT_HTTPHEADER => [
            "x-rapidapi-host: PLACEHOLDER",
            "x-rapidapi-key: PLACEHOLDER"
        ],
	]);
	
	$response = curl_exec($curl);
	$err = curl_error($curl);
	
	curl_close($curl);
	
	$data = json_decode ( $response );

	return $data;
}

function displayResults($departLocation, $destination) {
    $data = getQuotes($departLocation, $destination, "anytime");

    // print_r($data);

    $quotes = $data->Quotes;
    $places = $data->Places;

    foreach($quotes as $Quote) {
        $OriginId = $Quote->OutboundLeg->OriginId;
        $DestinationId = $Quote->OutboundLeg->DestinationId;

        $departurePlaceName = "";
        $destinationPlaceName = "";

        foreach($places as $Place) {
            if ($Place->PlaceId == $OriginId) {
                $departurePlaceName = $Place->Name . ", " . $Place->CountryName;
            }
            if ($Place->PlaceId == $DestinationId) {
                $destinationPlaceName = $Place->Name . ", " . $Place->CountryName;
            }
        }
        echo "*********************************************<br>";
        echo "Outbound" . "<br><br>";
        echo "Depart from: " . $departurePlaceName . "<br>";
        echo "Arrive at: " . $destinationPlaceName . "<br>";
        echo "Departure Date: " . $Quote->OutboundLeg->DepartureDate . "<br><br><br>";
        echo "Inbound" . "<br><br>";
        echo "Depart from: " . $destinationPlaceName . "<br>";
        echo "Arrive at: " . $departurePlaceName . "<br>";
        echo "Departure Date: " . $Quote->InboundLeg->DepartureDate . "<br>";
        echo "MinPrice: " . $Quote->MinPrice . "<br>";
        echo "*********************************************<br><br><br>";
    }

}


function getPlaces($location) {
    $baseURL = "https://skyscanner-skyscanner-flight-search-v1.p.rapidapi.com/apiservices/autosuggest/v1.0/CA/CAD/en-CA/?query=";
    return callAPI($baseURL . $location);
}

function getQuotes($departureLocation, $arrivalLocation, $date) {
    $baseURL = "https://skyscanner-skyscanner-flight-search-v1.p.rapidapi.com/apiservices/browsequotes/v1.0/CA/CAD/en-CA/";
    $apiLink = $baseURL . $departureLocation . "/" . $arrivalLocation . "/" . $date . "/anytime";
    return callAPI($apiLink);
}

