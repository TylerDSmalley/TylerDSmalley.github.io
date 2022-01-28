<?php

require_once 'vendor/autoload.php';
require_once 'utils.php';
require_once 'init.php';

// LIST USERS/DESTINATION/CONTACTUS/BOOKINGS/TESTIMONIALS/HOTELS HANDLER
$app->get('/admin/{op:users|destinations|contactus|bookings|testimonials|hotels}/list', function ($request, $response, $args) {
   if (!isset($_SESSION['user']) || $_SESSION['user']['account_type'] != 'admin') {
      return $this->view->render($response, 'not_found.html.twig');
   }
   if ($args['op'] == 'users') {
      $userList = DB::query("SELECT * FROM users WHERE status='active'");
      return $this->view->render($response, 'admin/users_list.html.twig', ['usersList' => $userList]);
   }

   if ($args['op'] == 'destinations') {
      $destinationsList = DB::query("SELECT * FROM  destinations WHERE status='active'");
      return $this->view->render($response, 'admin/destinations_list.html.twig', ['destinationsList' => $destinationsList]);
   }

   if ($args['op'] == 'contactus') {
      $contactsList = DB::query("SELECT * FROM  contact_us ORDER BY contactTS DESC");
      return $this->view->render($response, 'admin/contactus_list.html.twig', ['contactsList' => $contactsList]);
   }

   if ($args['op'] == 'bookings') {
      $bookingsList = DB::query("SELECT * FROM  booking_history ORDER BY paymentTS DESC");
      return $this->view->render($response, 'admin/bookings_list.html.twig', ['bookingsList' => $bookingsList]);
   }

   if ($args['op'] == 'testimonials') {
      $testimonialsList = DB::query("SELECT * FROM  testimonials ORDER BY createdTS DESC");
      return $this->view->render($response, 'admin/testimonials_list.html.twig', ['testimonialsList' => $testimonialsList]);
   }

   if ($args['op'] == 'hotels') {
      $hotelsList = DB::query("SELECT * FROM  hotel ORDER BY id DESC");
      return $this->view->render($response, 'admin/hotels_list.html.twig', ['hotelsList' => $hotelsList]);
   }
});


// INACTIVE USERS HANDLER
$app->get('/admin/users/inactive', function ($request, $response, $args) {
   if (!isset($_SESSION['user']) || $_SESSION['user']['account_type'] != 'admin') {
      return $this->view->render($response, 'not_found.html.twig');
   }
   $userList = DB::query("SELECT * FROM users WHERE status='inactive'");
   return $this->view->render($response, 'admin/inactive_users.html.twig', ['usersList' => $userList]);
});



// ADD AND EDIT USERS HANDLER

$app->get('/admin/users/{op:edit|add}[/{id:[0-9]+}]', function ($request, $response, $args) {
   if (!isset($_SESSION['user']) || $_SESSION['user']['account_type'] != 'admin') {
      return $this->view->render($response, 'not_found.html.twig');
   }

   if ($args['op'] !== null) {
      $op =  $args['op'];
   } else {
      $op = $request->getParam('op');
   }


   if (($op == 'add' && !empty($args['id']) || $op == 'edit' && empty($args['id']))) {
      $response = $response->withStatus(404);
      return $this->view->render($response, 'not_found.html.twig');
   }

   if ($op == 'edit') {
      $use = DB::queryFirstRow("SELECT * FROM users WHERE id=%i", $args['id']);
      if (!$use) {
         $response = $response->withStatus(404);
         return $this->view->render($response, 'not_found.html.twig');
      }
   } else {
      $use = [];
   }
   return $this->view->render($response, 'admin/users_addedit.html.twig', ['use' => $use, 'op' => $op]);
});


$app->post('/admin/users/{op:edit|add}[/{id:[0-9]+}]', function ($request, $response, $args) {
   $use = DB::queryFirstRow("SELECT * FROM users WHERE id=%i", $args['id']);
   if ($args['op'] !== null) {
      $op =  $args['op'];
   } else {
      $op = $request->getParam('op');
   }

   if (($op == 'add' && !empty($args['id']) || $op == 'edit' && empty($args['id']))) {
      $response = $response->withStatus(404);
      return $this->view->render($response, 'not_found.html.twig', ['use' => $use]);
   }

   $firstName = $request->getParam('firstName');
   $lastName = $request->getParam('lastName');
   $email = $request->getParam('email');
   $phoneNumber = $request->getParam('phone');
   $type = $request->getParam('type');
   $password1 = $request->getParam('password1');
   $password2 = $request->getParam('password2');

   $errorList = [];

   $result = validateName($firstName);
   if ($result !== true) {
      $errorList[] = $result;
   }

   $result = validateName($lastName);
   if ($result !== true) {
      $errorList[] = $result;
   }

   //First check if you are need to validate password. Ex. Updating user info but not their password
   if ($password1 != "" || $op == 'add') {
      $result = validatePassword($password1, $password2);
      if ($result !== true) {
         $errorList[] = $result;
      }
   }

   if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $errorList[] = "Email not valid";
      $email = "";
   } else {
      //is email already in use?
      if ($op == 'edit') {
         $record = DB::queryFirstRow("SELECT * FROM users where email=%s AND id != %d", $email, $args['id']);
      } else {
         $record = DB::queryFirstRow("SELECT * FROM users where email=%s", $email);
      }
      if ($record) {
         $errorList[] = "This email is already registered";
         $email = "";
      }
   }

   $result = validatePhone($phoneNumber);
   if ($result !== true) {
      $errorList[] = $result;
   }

   if ($errorList) {
      return $this->view->render(
         $response,
         'admin/users_addedit.html.twig',
         ['use' => $use, 'errorList' => $errorList, 'val' => ['firstName' => $firstName, 'lastName' => $lastName, 'email' => $email, 'phone' => $phoneNumber], 'op' => $op]
      );
   } else {
      $hash = password_hash($password1, PASSWORD_DEFAULT);
      if ($op == 'add') {
         DB::insert('users', ['first_name' => $firstName, 'last_name' => $lastName, 'email' => $email, 'phone_number' => $phoneNumber, 'password' => $hash, 'account_type' => $type]);
         setFlashMessage("User successfully added!");
         return $response->withRedirect("/admin/users/list");
      } else {
         $data = ['first_name' => $firstName, 'last_name' => $lastName, 'email' => $email, 'phone_number' => $phoneNumber, 'account_type' => $type];
         if ($password1 != "") {
            $data['password'] = $hash;
         }
         DB::update('users', $data, "id=%d", $args['id']);
         setFlashMessage("User successfully updated!");
         return $response->withRedirect("/admin/users/list");
      }
   }
});
//DELETE USERS HANDLER
$app->get('/admin/users/delete[/{id:[0-9]+}]', function ($request, $response, $args) {
   if (!isset($_SESSION['user']) || $_SESSION['user']['account_type'] != 'admin') {
      return $this->view->render($response, 'not_found.html.twig');
   }

   $user = DB::queryFirstRow("SELECT * FROM users WHERE id=%d", $args['id']);
   if (!$user) {
      $response = $response->withStatus(404);
      return $this->view->render($response, 'not_found.html.twig');
   }
   return $this->view->render($response, 'admin/users_delete.html.twig', ['user' => $user]);
});

$app->post('/admin/users/delete[/{id:[0-9]+}]', function ($request, $response, $args) {
   DB::query("UPDATE users SET status='inactive' WHERE id=%i", $args['id']);
   setFlashMessage("User successfully deleted!");
   return $response->withRedirect("/admin/users/list");
});



//DELETE DESTINATION HANDLER
$app->get('/admin/destinations/delete[/{id:[0-9]+}]', function ($request, $response, $args) {
   if (!isset($_SESSION['user']) || $_SESSION['user']['account_type'] != 'admin') {
      return $this->view->render($response, 'not_found.html.twig');
   }

   $destination = DB::queryFirstRow("SELECT * FROM destinations WHERE id=%d", $args['id']);
   if (!$destination) {
      $response = $response->withStatus(404);
      return $this->view->render($response, 'not_found.html.twig');
   }
   return $this->view->render($response, 'admin/destinations_delete.html.twig', ['destination' => $destination]);
});

$app->post('/admin/destinations/delete[/{id:[0-9]+}]', function ($request, $response, $args) {
   DB::query("UPDATE destinations SET status = 'inactive' WHERE id=%i", $args['id']);
   setFlashMessage("Destination successfully deleted!");
   return $response->withRedirect("/admin/destinations/list");
});

//DELETE TESTIMONIAL HANDLER
$app->get('/admin/testimonials/delete[/{id:[0-9]+}]', function ($request, $response, $args) {
   if (!isset($_SESSION['user']) || $_SESSION['user']['account_type'] != 'admin') {
      return $this->view->render($response, 'not_found.html.twig');
   }

   $testimonial = DB::queryFirstRow("SELECT * FROM testimonials WHERE id=%d", $args['id']);
   if (!$testimonial) {
      $response = $response->withStatus(404);
      return $this->view->render($response, 'not_found.html.twig');
   }
   return $this->view->render($response, 'admin/testimonials_delete.html.twig', ['testimonial' => $testimonial]);
});

$app->post('/admin/testimonials/delete[/{id:[0-9]+}]', function ($request, $response, $args) {
   DB::query("DELETE FROM testimonials WHERE id=%i", $args['id']);
   setFlashMessage("Testimonial successfully deleted!");
   return $response->withRedirect("/admin/testimonials/list");
});


$app->get('/admin/destinations/addimage[/{id:[0-9]+}]', function ($request, $response, $args) {
   $destinationId = $args['id'];
   if (!isset($_SESSION['user']) || $_SESSION['user']['account_type'] != 'admin') {
      return $this->view->render($response, 'not_found.html.twig');
   }
   return $this->view->render($response, 'admin/destinations_addphoto.html.twig', ['destinationId' => $destinationId]);
});

$app->post('/admin/destinations/addimage[/{id:[0-9]+}]', function ($request, $response, $args) {
   $destinationId = $args['id'];
   $errors = array('photo' => '');

   $photo = $_FILES['photo'];
   $photoFilePath = "";
   $retval = verifyUploadedPhoto($photoFilePath, $photo);
   if ($retval !== TRUE) {
      $errors['photo'] = $retval;
   }

   if (array_filter($errors)) {
      return $this->view->render($response, 'admin/destinations_addphoto.html.twig', ['errors' => $errors, 'destinationId' => $destinationId]);
   }
   if (!move_uploaded_file($_FILES['photo']['tmp_name'], $photoFilePath)) {
      die("Error moving the uploaded file. Action aborted.");
   }
   $finalFilePath = htmlentities($photoFilePath);

   DB::insert('images', [
      'destination_id' => $destinationId,
      'collage_imagepath' => $finalFilePath
   ]);
   setFlashMessage("Photo successfully added!");
   return $response->withRedirect("/admin/destinations/list");
});



// ADD AND EDIT DESTINATION HANDLER
$app->get('/admin/destinations/{op:edit|add}[/{id:[0-9]+}]', function ($request, $response, $args) {
   if (!isset($_SESSION['user']) || $_SESSION['user']['account_type'] != 'admin') {
      return $this->view->render($response, 'not_found.html.twig');
   }

   if (($args['op'] == 'add' && !empty($args['id']) || $args['op'] == 'edit' && empty($args['id']))) {
      $response = $response->withStatus(404);
      return $this->view->render($response, 'not_found.html.twig');
   }

   if ($args['op'] == 'edit') {
      $destination = DB::queryFirstRow("SELECT * FROM destinations WHERE id=%i", $args['id']);
      if (!$destination) {
         $response = $response->withStatus(404);
         return $this->view->render($response, 'not_found.html.twig');
      }
   } else {
      $destination = [];
   }
   return $this->view->render($response, 'admin/destinations_addedit.html.twig', ['destination' => $destination, 'op' => $args['op']]);
});


$app->post('/admin/destinations/{op:edit|add}[/{id:[0-9]+}]', function ($request, $response, $args) use ($log) {

   $op = $args['op'];

   $destination_description = $destination_name = $photo =  "";
   $errors = array('destination_name' => '', 'destination_description' => '', 'photo' => '');

   if (empty($request->getParam('destination_name'))) {
      $errors['destination_name'] = 'A Destination name is required';
   } else {
      $destination_name = $request->getParam('destination_name');
      if (strlen($destination_name) < 2 || strlen($destination_name) > 50) {
         $errors['destination_name'] = 'Destination name must be 2-50 characters long';
      } else {
         $finaldestination_name = htmlentities($destination_name);
      }
   }

   if (empty($request->getParam('destination_description'))) {
      $errors['destination_description'] = 'A Destination description is required';
   } else {
      $destination_description = $request->getParam('destination_description');
      if (strlen($destination_description) < 2 || strlen($destination_description) > 5000) {
         $errors['itemDescription'] = 'Destination description must be 2-5000 characters long';
      } else {
         $final_destination_description = strip_tags($destination_description, "<p><ul><li><em><strong><i><b><ol><h3><h4><h5><span><pre>");
         $final_destination_description = htmlentities($final_destination_description);
      }
   }


   $photo = $_FILES['photo'];
   $isPhoto = TRUE;
   if ($op == 'add') {
      $photoFilePath = "";
      $retval = verifyUploadedPhoto($photoFilePath, $photo);
      if ($retval !== TRUE) {
         $errors['photo'] = $retval;
      }
   } elseif ($op == 'edit' && $photo['error'] != UPLOAD_ERR_NO_FILE) {
      $photoFilePath = "";
      $retval = verifyUploadedPhoto($photoFilePath, $photo);
      if ($retval !== TRUE) {
         $errors['photo'] = $retval;
      }
   } else {
      $isPhoto = FALSE;
   }


   if (array_filter($errors)) {
      $valuesList = ['destination_name' => $destination_name, 'destination_description' => $destination_description, 'photo' => $photoFilePath];
      return $this->view->render($response, 'admin/destinations_add.html.twig', ['errors' => $errors, 'v' => $valuesList]);
   } else { //This is an add operation
      if ($op == 'add') {

         if (!move_uploaded_file($_FILES['photo']['tmp_name'], $photoFilePath)) {
            echo $_FILES['photo']['tmp_name'];
            echo "   ";
            echo $photoFilePath;

            die("Error moving the uploaded file. Action aborted.");
         }
         $finalFilePath = htmlentities($photoFilePath);

         DB::insert('destinations', [
            'destination_name' => $finaldestination_name,
            'destination_description' => $final_destination_description,
            'destination_imagepath' => $finalFilePath,
         ]);
         setFlashMessage("Destination successfully added!");
      } else { //This is an edit operation
         if ($isPhoto == TRUE) {
            if (!move_uploaded_file($_FILES['photo']['tmp_name'], $photoFilePath)) {
               die("Error moving the uploaded file. Action aborted.");
            }

            $finalFilePath = htmlentities($photoFilePath);
            $data = ['destination_name' => $finaldestination_name, 'destination_description' => $final_destination_description, 'destination_imagepath' => $finalFilePath];
            DB::update('destinations', $data, "id=%d", $args['id']);
            setFlashMessage("Destination successfully updated!");
         } else {
            $data = ['destination_name' => $finaldestination_name, 'destination_description' => $final_destination_description];
            DB::update('destinations', $data, "id=%d", $args['id']);
            setFlashMessage("Destination successfully updated!");
         }
      }
      return $response->withRedirect("/admin/destinations/list");
   }
});

$app->patch('/isMessageRead/{id:[0-9]+}/{checkVal}', function ($request, $response, $args) {

   if ($args['checkVal'] == 'No') {
      DB::query("UPDATE contact_us SET replied = 'No' WHERE id=%i", $args['id']);
   } else {
      DB::query("UPDATE contact_us SET replied = 'Yes' WHERE id=%i", $args['id']);
   }
});
