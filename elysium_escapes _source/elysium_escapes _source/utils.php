<?php

require_once 'vendor/autoload.php';

require_once 'init.php';

function validateName($name)
{
    if (preg_match('/^[\.a-zA-Z0-9,!? ]*$/', $name) != 1 || strlen($name) < 2 || strlen($name) > 100) {
        return "Name must be between 2 and 100 characters and include only letters, numbers, space, dash, dot or comma";
    } else {
        return TRUE;
    }
}

function validatePhone($phone)
{
    $valid_number = filter_var($phone, FILTER_SANITIZE_NUMBER_INT);
    $valid_number = str_replace("-", "", $valid_number);
    if (strlen($valid_number) < 10 || strlen($valid_number) > 14) {
        return "Invalid phone number";
    } else {
        return TRUE;
    }
}

function validatePassword($password1, $password2)
{
    if (empty($password1) || ($password1 !== $password2)) {
        return "Passwords do not match";
    } elseif (strlen($password1) < 9 || strlen($password1) > 100) {
        return  "Your Password Must Contain At Least 8 Characters!";
    } elseif (!preg_match("#[0-9]+#", $password1)) {
        return  "Your Password Must Contain At Least 1 Number!";
    } elseif (!preg_match("#[A-Z]+#", $password1)) {
        return "Your Password Must Contain At Least 1 Capital Letter!";
    } elseif (!preg_match("#[a-z]+#", $password1)) {
        return "Your Password Must Contain At Least 1 Lowercase Letter!";
    }  else
        return TRUE;
}


function verifyUploadedPhoto(&$newFilePath, $photo)
{
    $photo = $_FILES['photo'];
    // is there a photo being uploaded and is it okay?
    if ($photo['error'] != UPLOAD_ERR_OK) {
        return "Error uploading photo " . $photo['error'];
    }
    if ($photo['size'] > 2 * 1024 * 1024) { // 2MB
        return "File too big. 2MB max is allowed.";
    }
    $info = getimagesize($photo['tmp_name']);

    if ($info[0] < 200 || $info[0] > 1000 || $info[1] < 200 || $info[1] > 1000) {
        return "Width and height must be within 200-1000 pixels range";
    }
    switch ($info['mime']) {
        case 'image/jpeg':
            $ext = "jpg";
            break;
        case 'image/gif':
            $ext = "gif";
            break;
        case 'image/png':
            $ext = "png";
            break;
        case 'image/bmp':
            $ext = "bmp";
            break;
        default:
            return "Only JPG, GIF, BMP, and PNG file types are accepted";
    }
    $filename = pathinfo($_FILES['photo']['name'], PATHINFO_FILENAME);
    $santitizedPhoto = str_replace(array_merge(
        array_map('chr', range(0, 31)),
        array('<', '>', ':', '"', '/', '\\', '|', '?', '*')
    ), '', $filename);
    $newFilePath = "uploads/" . $santitizedPhoto . "." . $ext;
    return TRUE;
}

function setFlashMessage($message){
    $_SESSION['flashMessage'] = $message;
}

function getAndClearFlashMessage(){
    if(isset($_SESSION['flashMessage'])){
        $message = $_SESSION['flashMessage'];
        unset($_SESSION['flashMessage']);
        return $message;
    }
    return "";
}

function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

function callAPI($url, $bookingApi = false) {
	$curl = curl_init($url);

	curl_setopt_array($curl, [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_SSL_VERIFYPEER => FALSE,
		CURLOPT_SSL_VERIFYHOST => FALSE,
	]);

    if ($bookingApi === true) {
        curl_setopt($curl,CURLOPT_HTTPHEADER , ["x-rapidapi-host: booking-com.p.rapidapi.com",
        "x-rapidapi-key: 00212bd341mshd388f431c6d4eb7p18dfd6jsned63fb89e251"]);
    }
	
	$response = curl_exec($curl);
	$err = curl_error($curl);
	
	curl_close($curl);
	
	$data = json_decode ( $response);

	return $data;
}

function searchLocation($searchLocation, &$dest_type) {
    $apiUrl = "https://booking-com.p.rapidapi.com/v1/hotels/locations?locale=en-us&name=" . urlencode($searchLocation);

    $locationList = callAPI($apiUrl, true);

    foreach ($locationList as $location) {
        if ($location->name == $searchLocation) {
            $dest_type = $location->dest_type;
            return $location->dest_id; 
        }
    }
}

function searchHotels($location, $destType, $adults, $children, $arrival, $departure) {

    $apiUrl = "https://booking-com.p.rapidapi.com/v1/hotels/search?"
    ."dest_type=" . $destType
    ."&checkin_date=" . $arrival
    ."&room_number=1"
    ."&checkout_date=" . $departure
    ."&order_by=popularity"
    ."&dest_id=" . $location
    ."&adults_number=" . $adults
    ."&units=metric"
    ."&filter_by_currency=CAD"
    ."&locale=en-us"
    ."&include_adjacency=false";
    
    if ($children > 0) {
        $childrenAges = "";
        $apiUrl = $apiUrl . "&children_number=" . $children . "&children_ages=";
        for ($i = $children; $i >= 1; $i--) {
            $apiUrl = $apiUrl . "8";
            $childrenAges = $childrenAges . "8";
            if ($i > 1) {
                $apiUrl = $apiUrl . "%2C";
                $childrenAges = $childrenAges . ",";
            }
        }
    }

    return callAPI($apiUrl, true);
    
}

function getHotelPhotos($hotelId) {
    $apiUrl = "https://booking-com.p.rapidapi.com/v1/hotels/photos?hotel_id=" . $hotelId ."&locale=en-us";
    $photos = callAPI($apiUrl, true);
    return $photos;
}

function convertCurrencyToCAD($sourceCurrencyCode, $convertAmount) {
    $apiUrl = "https://free.currconv.com/api/v7/convert?q=" . $sourceCurrencyCode . "_CAD&compact=ultra&apiKey=05d742f1f2b8ff8dd8c3";
    $result = callAPI($apiUrl);
    $convertAmount * $result->{array_keys(get_object_vars($result))[0]};
    return $convertAmount * $result->{array_keys(get_object_vars($result))[0]};
}

    $app->get('/passreset_request', function ($request,$response){
    return $this->view->render($response,'password_reset.html.twig');
});