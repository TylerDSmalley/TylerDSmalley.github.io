<?php
date_default_timezone_set('America/Montreal');

require_once 'vendor/autoload.php';
require_once 'init.php';
require_once 'utils.php';

// Define app routes below
require_once 'user.php';
require_once 'admin.php';



$app->post('/webhook', function ($request, $response, $args) {
    $endpoint_secret = 'PLACEHOLDER';
    $payload = @file_get_contents('php://input');
    $sig_header = $_SERVER['PLACEHOLDER'];
    $event = null;

    try {
    $event = \Stripe\Webhook::constructEvent(
        $payload, $sig_header, $endpoint_secret
    );
    } catch(\UnexpectedValueException $e) {
    // Invalid payload
    http_response_code(400);
    exit();
    } catch(\Stripe\Exception\SignatureVerificationException $e) {
    // Invalid signature
    http_response_code(400);
    exit();
    }

    // Handle the event
    switch ($event->type) {
    case 'payment_intent.succeeded':
        $paymentIntent = $event->data->object;
        DB::update('booking_history', ['payment_status' => "paid"], "id=%i", $paymentIntent->metadata->bookingId);
        echo 'Received payment of ' . $paymentIntent->amount . ' Booking Id: ' . $paymentIntent->metadata->bookingId;
        break;
        
    case 'payment_intent.payment_failed':
        $paymentIntent = $event->data->object;
        DB::update('booking_history', ['payment_status' => "failed"], "id=%i", $paymentIntent->metadata->bookingId);
        echo 'Failed payment' . ' Booking Id: ' . $paymentIntent->metadata->bookingId;
        break;
        
    default:
        echo 'Received unknown event type ' . $event->type;
        break;
    }

    http_response_code(200);
});



    $app->post('/passreset_request', function ( $request, $response) {
    global $log;
    $post = $request->getParsedBody();
    $email = filter_var($post['email'], FILTER_VALIDATE_EMAIL);
    $user = DB::queryFirstRow("SELECT * FROM users WHERE email=%s", $email);
    if ($user) { 
        $secret = generateRandomString(60);
        $dateTime = gmdate("Y-m-d H:i:s"); // GMT time zone
        DB::insertUpdate('password_resets', ['user_id' => $user['id'],'secretCode' => $secret,'createdTS' => $dateTime], 
        ['secretCode' => $secret, 'createdTS' => $dateTime]);
       
        $emailBody = file_get_contents('templates/password_reset_email.html.strsub');
        $emailBody = str_replace('EMAIL', $email, $emailBody);
        $emailBody = str_replace('SECRET', $secret, $emailBody);
        
        $to = $email;
        $subject = "Password reset";
        
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        
        $headers .= 'From: No Reply <noreply@travel.fsd01.ca>' . "\r\n";
        
        $result = mail($to, $subject, $emailBody, $headers);
        if ($result) {
            $log->debug(sprintf("Password reset sent to %s", $email));
        } else {
            $log->error(sprintf("Error sending password reset email to %s\n:%s", $email));
        } 
    }
        return $this->view->render($response, 'password_reset_sent.html.twig');
    });

    $app->map(['GET', 'POST'], '/passresetaction/{secret}', function ( $request, $response, array $args) {
        global $log;
       
        $secret = $args['secret'];
        $resetRecord = DB::queryFirstRow("SELECT * FROM password_resets WHERE secretCode=%s", $secret);
        if (!$resetRecord) {
            $log->debug(sprintf('password reset token not found, token=%s', $secret));
            return $this->view->render($response, 'password_reset_action_notfound.html.twig');
        }
        // check if password reset has not expired
        $creationDT = strtotime($resetRecord['createdTS']); // convert to seconds since Jan 1, 1970 (UNIX time)
        $nowDT = strtotime(gmdate("Y-m-d H:i:s")); // current time GMT
        if ($nowDT - $creationDT > 60*60) { // expired
            DB::delete('password_resets', 'secretCode=%s', $secret);
            $log->debug(sprintf('password reset token expired user_id=%s, token=%s', $resetRecord['user_id'], $secret));
            return $this->view->render($response, 'password_reset_action_notfound.html.twig');
        }
        
        if ($request->getMethod() == 'POST') {
            $post = $request->getParsedBody();
            $pass1 = $post['pass1'];
            $pass2 = $post['pass2'];
            $errorList = array();

            $result = validatePassword($pass1,$pass2);
            if($result !== TRUE){
                $errorList = $result;
            }
        
            if ($errorList) {
                return $this->view->render($response, 'password_reset_action.html.twig', ['errorList' => $errorList]);
            } else {
                $hash = password_hash($pass1, PASSWORD_DEFAULT);
                DB::update('users', ['password' => $hash], "id=%d", $resetRecord['user_id']);
                DB::delete('password_resets', 'secretCode=%s', $secret); // cleanup the record
                return $this->view->render($response, 'password_reset_action_success.html.twig');
            }
        } else {
            return $this->view->render($response, 'password_reset_action.html.twig');
        }
    });

$app->run();