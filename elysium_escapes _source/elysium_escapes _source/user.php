<?php

require_once 'vendor/autoload.php';

require_once 'init.php';

//INDEX HANDLERS
$app->get('/', function ($request, $response, $args) {
    $destinations = DB::query("SELECT * FROM destinations WHERE `status`=%s", "active");
    return $this->view->render($response, 'index.html.twig', ['destinations' => $destinations]);
});
//INDEX HANDLERS//

//BLOG HANDLERS
$app->get('/blog', function ($request, $response, $args) {
    $testimonials = DB::query("SELECT t.*, u.first_name, d.* FROM testimonials AS t LEFT JOIN users AS u ON t.user_id = u.id LEFT JOIN destinations AS d ON t.destination_id = d.id ORDER BY t.createdTS DESC");
    $images = DB::query("SELECT * FROM images");
    return $this->view->render($response, 'blog.html.twig', ['testimonials' => $testimonials, 'images' => $images]);
});
//BLOG HANDLERS END

//BOOKING HANDLERS
$app->get('/testbooking', function ($request, $response, $args) {

    $destinations = DB::query("SELECT * FROM destinations WHERE `status`=%s", "active");
    return $this->view->render($response, 'testbooking.html.twig', ['d' => $destinations]);
});

$app->get('/bookingConfirm', function ($request, $response, $args) use ($log) {
    $userId = $_SESSION['user']['id'];
    $bookingConfirm = DB::queryFirstRow("SELECT b.*, h.*, d.* FROM booking_history AS b  LEFT JOIN hotel AS h ON b.hotel_id = h.id LEFT JOIN destinations AS d ON b.destination_id = d.id WHERE b.user_id = %s ORDER BY b.paymentTS DESC", $userId);
    $email = DB::queryFirstRow("SELECT email FROM users WHERE id=%i", $userId);

    if ($email) {
        $departureDate = date('M j Y', strtotime($bookingConfirm['departure_date']));
        $returnDate = date('M j Y', strtotime($bookingConfirm['return_date']));
        $bookingDate = date('M j Y', strtotime($bookingConfirm['paymentTS']));
        $totalPrice = number_format($bookingConfirm['total_price'], 2, '.', '');

        $emailBody = file_get_contents('templates/confirmation_email.html.strsub');
        $emailBody = str_replace('CONFIRM_NUMBER', $bookingConfirm['booking_confirm'], $emailBody);
        $emailBody = str_replace('TOTAL_PAID', $totalPrice, $emailBody);
        $emailBody = str_replace('DEPARTURE_DATE', $departureDate, $emailBody);
        $emailBody = str_replace('RETURN_DATE', $returnDate, $emailBody);
        $emailBody = str_replace('NUM_ADULTS', $bookingConfirm['number_adults'], $emailBody);
        $emailBody = str_replace('NUM_CHILDREN', $bookingConfirm['number_children'], $emailBody);
        $emailBody = str_replace('BOOKING_DATE', $bookingDate, $emailBody);
        $emailBody = str_replace('CHOSEN_DEST', $bookingConfirm['destination_name'], $emailBody);
        $emailBody = str_replace('HOTEL_NAME', $bookingConfirm['hotel_name'], $emailBody);
        $emailBody = str_replace('HOTEL_CITY', $bookingConfirm['hotel_city'], $emailBody);
        $emailBody = str_replace('HOTEL_ADDRESS', $bookingConfirm['hotel_address'], $emailBody);
        /* // OPTION 1: PURE PHP EMAIL SENDING - most likely will end up in Spam / Junk folder */
        $to = $email['email'];
        $subject = "Elysian Escapes - Trip Confirmation";
        // Always set content-type when sending HTML email
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        // More headers
        $headers .= 'From: No Reply <noreply@travel.fsd01.ca>' . "\r\n";
        // finally send the email
        $result = mail($to, $subject, $emailBody, $headers);
        if ($result) {
            $log->debug(sprintf("Booking confirmation sent to %s", $email));
        } else {
            $log->error(sprintf("Error sending booking confirmation email to %s\n:%s", $email));
        }
    }
    return $this->view->render($response, 'testBookingConfirm.html.twig', ['bookingConfirm' => $bookingConfirm]);
});
//BOOKING HANDLERS END

// CONTACT US HANDLERS
$app->get('/contactus', function ($request, $response, $args) {
    return $this->view->render($response, 'contactus.html.twig');
});

$app->post('/contactus', function ($request, $response, $args) {
    $first_name = $last_name = $email = $message_body =  "";
    $errors = array('first_name' => '', 'last_name' => '', 'email' => '', 'message_body' => '');

    // check first name
    if (empty($request->getParam('first_name'))) {
        $errors['first_name'] = 'A First Name is required';
    } else {
        $first_name = $request->getParam('first_name');
        if (strlen($first_name) < 2 || strlen($first_name) > 50) {
            $errors['first_name'] = 'First name must be 2-50 characters long';
        } else {
            $finalfirst_name = htmlentities($first_name);
        }
    }

    // check last name
    if (empty($request->getParam('last_name'))) {
        $errors['last_name'] = 'A Last Name is required';
    } else {
        $last_name = $request->getParam('last_name');
        if (strlen($last_name) < 2 || strlen($last_name) > 50) {
            $errors['last_name'] = 'last name must be 2-50 characters long';
        } else {
            $finallast_name = htmlentities($last_name);
        }
    }

    // check email
    if (empty($request->getParam('email'))) {
        $errors['email'] = 'An email is required';
    } else {
        $email = $request->getParam('email');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email must be a valid email address';
            $email = ""; // reset invalid value to empty string
        } else {
            // escape sql chars
            $finalEmail = htmlentities($email);
        }
    }

    // check message_body
    if (empty($request->getParam('message_body'))) {
        $errors['message_body'] = 'A message is required';
    } else {
        $message_body = $request->getParam('message_body');
        if (strlen($message_body) < 2 || strlen($message_body) > 5000) {
            $errors['message_body'] = 'Message must be 2-5000 characters long';
            $message_body = "";
        } else {
            $finalmessage_body = strip_tags($message_body, "<p><ul><li><em><strong><i><b><ol><h3><h4><h5><span><pre>");
            $finalmessage_body = htmlentities($finalmessage_body);
        }
    }

    if (array_filter($errors)) { //STATE 2 = errors
        $valuesList = ['first_name' => $first_name, 'last_name' => $last_name, 'email' => $email, 'message_body' => $finalmessage_body];
        return $this->view->render($response, 'contactus.html.twig', ['errors' => $errors, 'v' => $valuesList]);
    } else {
        // STATE 3: submission successful
        // insert the record and inform user

        //save to db and check
        DB::insert('contact_us', [
            'first_name' => $finalfirst_name,
            'last_name' => $finallast_name,
            'email' => $finalEmail,
            'message_body' => $finalmessage_body
        ]);
        return $this->view->render($response, 'contactus.html.twig');
    } // end POST check
});
// CONTACT US HANDLERS END

// CALENDAR HANDLERS
$app->get('/trcalendar', function ($request, $response, $args) {
    return $this->view->render($response, 'trcalendar.html.twig');
});
// CALENDAR HANDLERS END

// DESTINATIONS HANDLERS
$app->get('/destinations', function ($request, $response, $args) {
    $destinations = DB::query("SELECT * FROM destinations WHERE `status`=%s", "active");
    $images = DB::query("SELECT * FROM images");
    return $this->view->render($response, 'destinations.html.twig', ['destinations' => $destinations, 'images' => $images]);
});
// DESTINATIONS HANDLERS END

// REGISTER HANDLERS
$app->get('/register', function ($request, $response, $args) {
    return $this->view->render($response, 'register.html.twig');
});

$app->get('/isemailtaken/{email}', function ($request, $response, $args) {
    $email = $args['email'];
    $resultEmail = DB::queryFirstRow("SELECT email FROM users WHERE email=%s", $email);

    if ($resultEmail) {
        return $response->write("Email already taken");
    } else {
        return $response->write("");
    }
});

$app->post('/register', function ($request, $response, $args) {
    //extract values submitted
    $firstName = $request->getParam('firstName');
    $lastName = $request->getParam('lastName');
    $email = $request->getParam('email');
    $phoneNumber = $request->getParam('phone');
    $password1 = $request->getParam('password1');
    $password2 = $request->getParam('password2');

    //validate

    $errorList = array('firstName' => '', 'lastName' => '', 'email' => '', 'phone' => '', 'password1' => '');

    if (preg_match('/^[\.a-zA-Z0-9,!? ]*$/', $firstName) != 1 || strlen($firstName) < 2 || strlen($firstName) > 100) {
        $errorList['firstName'] = "Name must be between 2 and 100 characters and include only letters, numbers, space, dash, dot or comma";
    }

    if (preg_match('/^[\.a-zA-Z0-9,!? ]*$/', $lastName) != 1 || strlen($lastName) < 2 || strlen($lastName) > 100) {
        $errorList['lastName'] = "Name must be between 2 and 100 characters and include only letters, numbers, space, dash, dot or comma";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorList['email'] = "Invalid email format";
    } else {

        // check DB for duplicates
        $userRecord = DB::queryFirstRow("SELECT * FROM users WHERE email=%s", $email);
        if ($userRecord) {
            $errorList['email'] = 'Email already taken';
        }
    }

    if (validatePhone($phoneNumber) !== true) {
        $errorList['phone'] = "Invalid Phone Number format";
    }

    $valPass = validatePassword($password1, $password2);
    if ($valPass !== true) {
        $errorList['password1'] = $valPass;
    }

    if (array_filter($errorList)) { //STATE 2: Errors
        $valuesList = ['firstName' => $firstName, 'lastName' => $lastName, 'email' => $email, 'phone' => $phoneNumber];
        return $this->view->render($response, 'register.html.twig', ['errorList' => $errorList, 'v' => $valuesList]);
    } else {
        setFlashMessage("Registration successful! You may now sign in");
        $hash = password_hash($password1, PASSWORD_DEFAULT);
        DB::insert('users', ['first_name' => $firstName, 'last_name' => $lastName, 'email' => $email, 'phone_number' => $phoneNumber, 'password' => $hash]);
        return $response->withRedirect('/login');
    }
});


// REGISTER HANDLERS END

// LOGIN HANDLERS
$app->get('/login', function ($request, $response, $args) {
    return $this->view->render($response, 'login.html.twig');
});

$app->post('/login', function ($request, $response, $args) {

    $errors = array('email' => '', 'password1' => '');
    if (isset($_SESSION['user'])) {
        $errors['email'] = "already signed in";
    }
    // check email
    if (empty($request->getParam('email')) || empty($request->getParam('password1'))) {
        $errors['email'] = 'A email and password is required';
    }

    $email = $request->getParam('email');
    $password1 = $request->getParam('password1');
    // verify inputs
    $userCheck = DB::queryFirstRow("SELECT * FROM users WHERE email=%s", $email);

    if (!$userCheck) {
        $errors['email'] = 'Incorrect entry';
        $email = ""; // reset invalid value to empty string
    }


    //$loginSuccessful = ($userCheck != null) && ($password1 == $userCheck['password']); 
    $loginSuccessful = ($userCheck != null) && password_verify($password1, $userCheck['password']);
    if (!$loginSuccessful) { // STATE 2: login failed
        $errors['email'] = 'Invalid email or password';
    }

    if (array_filter($errors)) {
        return $this->view->render($response, 'login.html.twig', ['errors' => $errors]);
    } else {
        // STATE 3: login successful
        unset($userCheck['password']); // for safety reasons remove the password
        $_SESSION['user'] = $userCheck;
        return $response->withRedirect('/');
    }

    // end POST check   
});

// LOGIN HANDLERS END


// LOGOUT HANDLERS
$app->get('/logout', function ($request, $response, $args) {
    unset($_SESSION['user']);
    return $this->view->render($response, 'logout.html.twig');
});
// LOGOUT HANDLERS END

//USER PROFILE HANDLERS
$app->get('/users/trips', function ($request, $response, $args) {
    $userId = $_SESSION['user']['id'];
    $booking_history = DB::query("SELECT b.*, h.*, d.destination_name, d.destination_imagepath FROM booking_history AS b LEFT JOIN hotel AS h ON b.hotel_id = h.id LEFT JOIN destinations AS d ON b.destination_id = d.id WHERE user_id=%s AND b.payment_status=%s ORDER BY paymentTS DESC", $userId, "paid");
    return $this->view->render($response, 'userProfileTrips.html.twig', ['booking_history' => $booking_history]);
});

$app->post('/users/trips', function ($request, $response, $args) use ($log) {
    $userId = $_SESSION['user']['id'];
    $booking_history = DB::query("SELECT b.*, h.*, d.destination_name, d.destination_imagepath FROM booking_history AS b LEFT JOIN hotel AS h ON b.hotel_id = h.id LEFT JOIN destinations AS d ON b.destination_id = d.id WHERE user_id=%s AND b.payment_status=%s ORDER BY paymentTS DESC", $userId, "paid");

    $comment_body = $comment_title = $photo =  "";
    $errors = array('comment_title' => '', 'comment_body' => '', 'photo' => '');

    // check comment_title
    if (empty($request->getParam('comment_title'))) {
        $errors['comment_title'] = 'A story title is required';
    } else {
        $comment_title = $request->getParam('comment_title');
        if (strlen($comment_title) < 2 || strlen($comment_title) > 50) {
            $errors['comment_title'] = 'story title must be 2-50 characters long';
        } else {
            $finalComment_title = htmlentities($comment_title);
        }
    }

    // check comment_body
    if (empty($request->getParam('comment_body'))) {
        $errors['comment_body'] = 'A story is required to submit';
    } else {
        $comment_body = $request->getParam('comment_body');
        if (strlen($comment_body) < 2 || strlen($comment_body) > 10000) {
            $errors['comment_body'] = 'Body must be 2-10000 characters long';
        } else {
            $final_comment_body = strip_tags($comment_body, "<p><ul><li><em><strong><i><b><ol><h3><h4><h5><span><pre>");
            $final_comment_body = htmlentities($final_comment_body);
        }
    }

    // check photo
    $photo = $_FILES['photo'];
    $isPhoto = TRUE;
    if ($photo['error'] != UPLOAD_ERR_NO_FILE) {
        $photoFilePath = "";
        $retval = verifyUploadedPhoto($photoFilePath, $photo);
        if ($retval !== TRUE) {
            $errors['photo'] = $retval; // string with error was returned, add it to error list
        }
    } else {
        $isPhoto = FALSE;
    }


    if (array_filter($errors)) { //STATE 2 = errors
        $valuesList = ['comment_title' => $comment_title, 'comment_body' => $comment_body, 'image_filepath' => $photoFilePath];
        return $this->view->render($response, 'userProfileTrips.html.twig', ['errors' => $errors, 'v' => $valuesList, 'booking_history' => $booking_history]);
    } elseif ($isPhoto) {
        // STATE 3: submission successful
        // insert the record and inform user

        // 1. move uploaded file to its desired location
        if (!move_uploaded_file($_FILES['photo']['tmp_name'], $photoFilePath)) {
            die("Error moving the uploaded file. Action aborted.");
        }
        // 2. insert a new record with file path
        $finalFilePath = htmlentities($photoFilePath);

        $destination_id = $request->getParam('destination_id');

        //save to db and check
        DB::insert('testimonials', [
            'user_id' => $userId,
            'destination_id' => $destination_id,
            'comment_title' => $finalComment_title,
            'comment_body' => $final_comment_body,
            'image_filepath' => $finalFilePath
        ]);

        $log->debug(sprintf("new testimonial created id=%s", $_SERVER['REMOTE_ADDR'], DB::insertId())); //needs test
        $thanks = "Submission Received!";
        return $this->view->render($response, 'userProfileTrips.html.twig', ['booking_history' => $booking_history, 'thanks' => $thanks]); //needs confirmation signal
    } else {
        $destination_id = $request->getParam('destination_id');

        //save to db and check
        DB::insert('testimonials', [
            'user_id' => $userId,
            'destination_id' => $destination_id,
            'comment_title' => $finalComment_title,
            'comment_body' => $final_comment_body,
        ]);

        $log->debug(sprintf("new testimonial created id=%s", $_SERVER['REMOTE_ADDR'], DB::insertId())); //needs test
        $thanks = "Submission Received!";
        return $this->view->render($response, 'userProfileTrips.html.twig', ['booking_history' => $booking_history, 'thanks' => $thanks]); //needs confirmation signal
    } // end POST check

});


$app->get('/users/edit', function ($request, $response, $args) {
    return $this->view->render($response, 'userProfileEdit.html.twig');
});

$app->post('/users/edit', function ($request, $response, $args) {
    //extract values submitted
    $userId = $_SESSION['user']['id'];

    if ($request->getParam('firstName') !== null) {
        $firstName = $request->getParam('firstName');
    } else {
        $firstName = $_SESSION['user']['first_name'];
    }

    if ($request->getParam('lastName') !== null) {
        $lastName = $request->getParam('lastName');
    } else {
        $lastName = $_SESSION['user']['last_name'];
    }

    if ($request->getParam('email') !== null) {
        $email = $request->getParam('email');
    }

    if ($request->getParam('phone') !== null) {
        $phoneNumber = $request->getParam('phone');
    } else {
        $phoneNumber = $_SESSION['user']['phone_number'];
    }

    //validate

    $errors = [];

    if (preg_match('/^[\.a-zA-Z0-9,!? ]*$/', $firstName) != 1 || strlen($firstName) < 2 || strlen($firstName) > 100) {
        $errors['firstName'] = "Name must be between 2 and 100 characters and include only letters, numbers, space, dash, dot or comma";
        $firstName = "";
    }

    if (preg_match('/^[\.a-zA-Z0-9,!? ]*$/', $lastName) != 1 || strlen($lastName) < 2 || strlen($lastName) > 100) {
        $errors['lastName'] = "Name must be between 2 and 100 characters and include only letters, numbers, space, dash, dot or comma";
        $lastName = "";
    }

    if ($email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = "Invalid email format";
            $email = "";
        } else {

            // check DB for duplicates
            $userRecord = DB::queryFirstRow("SELECT * FROM users WHERE email=%s", $email);
            if ($userRecord) {
                $errors['email'] = 'Email already taken';
                $email = ""; // reset invalid value to empty string
            }
        }
    }

    if (!validatePhone($phoneNumber)) {
        $errors['phone'] = "Invalid Phone Number format";
        $phoneNumber = "";
    }

    if (array_filter($errors)) { //STATE 2: Errors
        $valuesList = ['firstName' => $firstName, 'lastName' => $lastName, 'email' => $email, 'phone' => $phoneNumber];
        return $this->view->render($response, 'userProfileEdit.html.twig', ['errors' => $errors, 'v' => $valuesList]);
    } else {
        if (!$email) {
            DB::query("UPDATE users SET first_name=%s, last_name=%s, phone_number=%s WHERE id=%s", $firstName, $lastName, $phoneNumber, $userId);
            $success = "Profile updated";;
            printf($phoneNumber);
            unset($_SESSION['user']);
            $userCheck = DB::queryFirstRow("SELECT * FROM users WHERE id=%s", $userId);
            $_SESSION['user'] = $userCheck;
            return $this->view->render($response, 'userProfileEdit.html.twig', ['success' => $success]);
        } else {
            DB::query("UPDATE users SET first_name=%s, last_name=%s, email=%s, phone_number=%s WHERE id=%s", $firstName, $lastName, $email, $phoneNumber, $userId);
            $success = "Profile updated";
            unset($_SESSION['user']);
            $userCheck = DB::queryFirstRow("SELECT * FROM users WHERE id=%s", $userId);
            $_SESSION['user'] = $userCheck;
            return $this->view->render($response, 'userProfileEdit.html.twig', ['success' => $success]);
        }
    }
});


//USER PROFILE HANDLERS END

//SESSION HANDLER 
$app->get('/session', function ($request, $response, $args) {
    $session = print_r($_SESSION);
    return $this->view->render($response, 'session.html.twig', ['session' => $session]);
});

$app->post('/testbooking', function ($request, $response, $args) {
    if ($request->getParam('hotel') !== null) {
        $hotel = json_decode($request->getParam('hotel'));
        $options = json_decode($request->getParam('options'));

        $hotelName = $hotel->hotel_name;
        $hotelCity = $hotel->city;
        $hotelAddress = $hotel->address;
        $hotelCurrencyCode = $hotel->currency_code;
        $hotelPrice = $hotel->price_breakdown->all_inclusive_price;
        $cadPrice = convertCurrencyToCAD($hotelCurrencyCode, $hotelPrice);
        $cadPrice = number_format($cadPrice, 2, '.', '');

        $min = 100000000000000;
        $max = 999999999999999;
        $rand = random_int($min, $max); // Consider improved approach - check db for match
        $destinationId = DB::queryFirstField("SELECT id FROM destinations WHERE destination_name=%s", $options->location);
        $hotelData = ['destination_id' => $destinationId, 'hotel_name' => $hotelName, 'hotel_city' => $hotelCity, 'hotel_address' => $hotelAddress, 'hotel_currency' => $hotelCurrencyCode, 'price_hotel_currency' => $hotelPrice, 'price_cad' => $cadPrice, 'confirmation' => $rand];

        DB::insert('hotel', $hotelData);
        $hotelId = DB::insertId();

        $dummyFlight = ['destination_id' => $destinationId, 'flight_name' => "test flight", 'price' => 1, 'confirmation' => $rand];
        DB::insert('flight', $dummyFlight);
        $flightId = DB::insertId();

        $valuesList = [
            'user_id' => $_SESSION['user']['id'],
            'destination_id' => $destinationId,
            'hotel_id' => $hotelId,
            'flight_id' => $flightId,
            'number_adults' => $options->adults,
            'number_children' => $options->children,
            'total_price' => $cadPrice,
            'departure_date' => $options->arrival,
            'return_date' => $options->departure,
            'booking_confirm' => $rand
        ];

        DB::insert('booking_history', $valuesList);

        $bookingId = DB::insertId();

        return $this->view->render($response, 'checkout.html.twig', ['hotel' => $hotel, 'options' => $options, 'cad_price' => $cadPrice, 'booking_id' => $bookingId]);
    } else {
        $location = $request->getParam('location');
        $adults = $request->getParam('adults');
        $children = $request->getParam('children');
        $arrival = $request->getParam('arrival');
        $departure = $request->getParam('departure');
        $destType = "";
        $locationId = searchLocation($location, $destType);
        $hotelData = searchHotels($locationId, $destType, $adults, $children, $arrival, $departure);
        $hotelList = [];
        foreach ($hotelData->result as $hotel) {
            if ($hotel->accommodation_type_name == "Resort") {
                $hotelList[] = $hotel;
            }
        }
        $hotelPhotos = [];
        foreach ($hotelList as $hotel) {
            $currentPhotoSet = getHotelPhotos($hotel->hotel_id);
            $hotelPhotos[] = $currentPhotoSet;
        }

        $errorList = [];

        if (!$location) {
            $errorList['location'] = "You must select a location";
        }
        if ($adults < 1 || !$adults) {
            $errorList['adults'] = "Number of adults must be at least 1";
            $adults = "";
        }
        if (!$children) {
            $children = 0;
        }
        if ($children < 0) {
            $errorList['children'] = "Number of children can not be a negative number";
            $adults = "";
        }
        if ($arrival < date("Y-m-d") || $departure < date("Y-m-d")) {
            $errorList['date'] = "You can not select a past date";
            if ($arrival < date("Y-m-d")) {
                $arrival = "";
            }
            if ($departure < date("Y-m-d")) {
                $departure = "";
            }
        } else if ($arrival > $departure) {
            $errorList['date'] = "Your departure date must be later than your arrival date";
            $arrival = "";
            $departure = "";
        }

        if (array_filter($errorList)) {
            $destinations = DB::query("SELECT * FROM destinations WHERE `status`=%s", "active");
            $valuesList = ['adults' => $adults, 'children' => $children, 'arrival' => $arrival, 'departure' => $departure];
            return $this->view->render($response, 'testbooking.html.twig', ['d' => $destinations, 'errorList' => $errorList, 'v' => $valuesList]);
        }
        return $this->view->render($response, 'apitestbooking.html.twig', ['options' => ['location' => $location, 'adults' => $adults, 'children' => $children, 'arrival' => $arrival, 'departure' => $departure], 'h' => $hotelList, 'images' => $hotelPhotos]);
    }
});

$app->post('/create', function ($request, $response, $args) {
    \Stripe\Stripe::setApiKey('sk_test_51JuPDTKzuA9IpUUKot3YMvv0KCWLD5GXtkRASmhqQ96VrLzHufknH8XmZzTexDcaIiOcmcuGfQpHMQQ5jY6nd0da007T6z1Bi9');

    try {
        // retrieve JSON from POST body
        $jsonStr = file_get_contents('php://input');
        $jsonObj = json_decode($jsonStr, true);
        $totalCost = $jsonObj['price'];
        $bookingId = $jsonObj['booking_id'];
        $totalCost = number_format($totalCost, 2, '.', '');
        $costAsCents = $totalCost * 100;

        // Create a PaymentIntent with amount and currency
        $paymentIntent = \Stripe\PaymentIntent::create([
            'amount' => $costAsCents,
            'currency' => 'CAD',
            'payment_method_types' => ['card'],
            'metadata' => ['bookingId' => $bookingId],
        ]);

        $output = [
            'clientSecret' => $paymentIntent->client_secret,
        ];
        return $response->write(json_encode($output));
    } catch (Error $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
});
