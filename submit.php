<?php
    include 'global_variables.php';
    error_reporting(E_ERROR | E_PARSE);

    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;
    use PHPMailer\PHPMailer\SMTP;
    use Twilio\Rest\Client;

    require 'vendor\phpmailer\phpmailer\src\Exception.php';
    require 'vendor\phpmailer\phpmailer\src\PHPMailer.php';
    require 'vendor\phpmailer\phpmailer\src\SMTP.php';
    require 'vendor\autoload.php';

    // This method sends the user a confirmation text message listing the selected team(s).
    function sendConfirmationTextMessage($phoneNumber, $account_sid, $auth_token, $twilio_number, $uuid) {
        $e164PhoneNumber = '+1' . $phoneNumber;

        $body = 'CHT Sign-Up Confirmation\n\nYou have selected the following teams:';

        for ($i = 0; $i < sizeof($teamArray); $i++) {
            $body = $body . '\n' . $teamArray[$i];
        }

        $body = $body . '\n\nEdit your selections here: ' . 'http://localhost/CollegeHockeyTransfers/edit.php?phoneNumber=' . $phoneNumber . '&uuid=' . $uuid;

        $client = new Client($account_sid, $auth_token);
        $client->messages->create(
            $e164PhoneNumber,
            array(
                'from' => $twilio_number,
                'body' => $body
            )
        );
    }

    // This method sends the user a confirmation email listing the selected team(s). If the email address is invalid, it's NOT added to the database.
    function sendConfirmationEmail($mailObject, $email, $selectedTeams, $uuid) {
        // Assemble the email's contents.
        $mailObject->Subject = 'CollegeHockeyTransfers Sign-Up Confirmation Email';
        $starterBody = 'Thank you for signing up for CollegeHockeyTransfers. You have signed up to hear about transactions for the following teams:<br>';
        $editLinkLine = '<br>Change or cancel your subscription <a href="http://localhost/CollegeHockeyTransfers/edit.php?email=' . $email . '&uuid=' . $uuid . '">here</a>.';

        // Assemble the email's body using the various parts constructed so far.
        $mailObject->Body = $starterBody . $selectedTeams . $editLinkLine;

        if (!$mailObject->send()) {
            // The email failed to send (if the email address in invalid, that would NOT cause the email to fail to send).
            return 0;
        }

        // The confirmation email was sent successfully.
        return 1;
    }

    // This method handles the user's submission by adding their email address and selected teams to the database.
    function processSubmission() {
        global $db_ip;
        global $db_username;
        global $db_password;
        global $database_name;
        global $teams;
        global $smtp_username;
        global $gmail_app_password;
        global $sender_email;
        global $account_sid;
        global $auth_token;
        global $twilio_number;

        // Connect to the database
        $mysqli = new mysqli($db_ip, $db_username, $db_password, $database_name);

        // If there was an error when connecting to the database, do not proceed. Instead, ask the user to try again or wait longer if the problem persists.
        if ($mysqli->connect_errno) {
            echo "<script type='text/javascript'>alert('There was a problem connecting to the database. Please try again. If the problem persists, please try again later.');</script>";
            return;
        }

        // Get the value of the submit button (selected team names separated by commas).
        $submitButtonValue = $_POST['submit'];

        // If no teams were selected, alert the user and return from the function, which will then re-direct back to the homepage.
        if ($submitButtonValue == '') {
            echo "<script type='text/javascript'>alert('You did not select any teams. Please select at least one team before submitting.');</script>";
            $mysqli->close();
            return;
        }

        // Get the contents of the email and text message text boxes.
        $email = trim($_POST['email']);
        $phoneNumber = trim($_POST['phone_number']);

        if ($email != '') {
            // The user selected emails as their method of communication. Check if the submitted email address is already in the database.
            $checkContactInfoStatement = $mysqli->prepare("SELECT * FROM Contact AS C WHERE C.Email=?");
            $checkContactInfoStatement->bind_param('s', $email);
            $contactMethod = 'email address';
        }
        else {
            // The user selected text messages as their preferred method of communication. Check if the submitted phone number is already in the database.
            $checkContactInfoStatement = $mysqli->prepare("SELECT * FROM Contact AS C WHERE C.PhoneNumber=?");
            $checkContactInfoStatement->bind_param('i', $phoneNumber);
            $contactMethod = 'phone number';
        }

        $checkContactInfoStatement->execute();
        $result = $checkContactInfoStatement->get_result();

        // If the email address/phone number address is already in the database, return and do not proceed.
        if ($result->num_rows == 1) {
            if ($email != '') {
                $alertMessage = "<script type='text/javascript'>alert('Your email address is already in the database. To change your selections or unsubscribe entirely, click the edit link in either the initial confirmation email or any transfer notification email.');</script>";
            }
            else {
                $alertMessage = "<script type='text/javascript'>alert('Your phone number is already in the database. To change your selections or unsubscribe entirely, click the edit link in either the initial confirmation text message or any transfer notification text message.');</script>";
            }

            echo $alertMessage;
            $checkContactInfoStatement->free_result();
            $checkContactInfoStatement->close();
            $mysqli->close();
            return;
        }

        $checkContactInfoStatement->free_result();

        // Construct the array of team names that the user selected.
        $teamArray = explode(',', $submitButtonValue);

        // The email address/phone number is not in the database, so add it to the Contact table and add the team(s) to the Subscription table.
        $uuid = uniqid();
        if ($email != '') {
            $insertContactInfoStatement = $mysqli->prepare("INSERT INTO Contact (Email, UUID) VALUES (?, ?)");
            $insertContactInfoStatement->bind_param('ss', $email, $uuid);
        }
        else {
            $insertContactInfoStatement = $mysqli->prepare("INSERT INTO Contact (PhoneNumber, UUID) VALUES (?, ?)");
            $insertContactInfoStatement->bind_param('is', $phoneNumber, $uuid);
        }

        $insertContactInfoStatement->execute();

        // Set up email object to send the confirmation email to william@collicott.com and possibly the user.
        $mailObject = new PHPMailer(true);

        try {
            // Server settings.
            $mailObject->isSMTP();
            $mailObject->Host = 'smtp.gmail.com';
            $mailObject->SMTPAuth = true;
            $mailObject->Username = $smtp_username;
            $mailObject->Password = $gmail_app_password;
            $mailObject->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mailObject->Port = 587;
            $mailObject->setFrom($sender_email, 'CollegeHockeyTransfers');
            $mailObject->addAddress($email);
            $mailObject->isHTML(true);
        } catch (Exception $e) {
            // An exception occured while setting up the email object. Tell the user to retry or try again later.
            echo "<script type='text/javascript'>alert('An error has occurred, please try again. If the problem persists, please try again later.');</script>";
            $checkContactInfoStatement->close();
            $insertContactInfoStatement->close();
            $mysqli->close();
            return;
        }

        // Assemble an HTML block listing the team(s) the user selected.
        $selectedTeams = '';
        for ($i = 0; $i < sizeof($teamArray); $i++) {
            $selectedTeams = $selectedTeams . '<b>' . $teamArray[$i] . '</b><br>';
        }

        // The email address/phone number is NOT already in the database, so send a confirmation email/text message to the user listing the team(s) they signed up for.
        if ($email != '') {
            if (!sendConfirmationEmail($mailObject, $email, $selectedTeams, $uuid)) {
                // The confirmation email failed to send.
                echo "<script type='text/javascript'>alert('The confirmation email failed to send, please try again. If the problem persists, please try again later.');</script>";
                $checkContactInfoStatement->close();
                $insertContactInfoStatement->close();
                $mysqli->close();
                return;
            }
        }
        else {
            sendConfirmationTextMessage($phoneNumber, $account_sid, $auth_token, $twilio_number, $teamArray, $uuid);
        }

        // Send an email to william@collicott.com indicating the email address/phone number of the user and the team(s) they selected.
        $mailObject->addAddress('william@collicott.com');
        $mailObject->Subject = 'CHT Sign Up Alert';
        $mailObject->Body = $email . ' has signed up for the following teams:<br>' . $selectedTeams;
        $mailObject->send();

        // Retrieve the contact ID that was assigned to the row just inserted into the Contact table.
        if ($email != '')
        {
            $contactIdstatement = $mysqli->prepare("SELECT Id FROM Contact AS C WHERE C.Email=?");
            $contactIdstatement->bind_param('s', $email);
        }
        else {
            $contactIdstatement = $mysqli->prepare("SELECT Id FROM Contact AS C WHERE C.PhoneNumber=?");
            $contactIdstatement->bind_param('i', $phoneNumber);
        }

        $contactIdstatement->execute();
        $result = $contactIdstatement->get_result();
        $row = mysqli_fetch_assoc($result);
        $emailID = $row['Id'];
        $contactIdstatement->free_result();

        $dbTeamID = '';
        $subscriptionStatement = $mysqli->prepare("INSERT INTO Subscription (ContactId, TeamId) VALUES (?, ?)");
        $subscriptionStatement->bind_param('ii', $emailID, $dbTeamID);

        for ($i = 0; $i < sizeof($teamArray); $i++) {
            // Retrieve the team's corresponding team ID.
            $dbTeamID = $teams[$teamArray[$i]];

            // Add the (email, team ID) pair to the Subscription table.
            $subscriptionStatement->execute();
        }

        if ($email != '') {
            echo "<script type='text/javascript'>alert('Thank you. Your submission has been recorded. Please check your email inbox for a confirmation email. If you do not receive it after 5 minutes or so, please double-check that you entered the correct email address.');</script>";
        }
        else {
            echo "<script type='text/javascript'>alert('Thank you. Your submission has been recorded. Please check your phone for a confirmation text message. If you do not receive it after 5 minutes or so, please double-check that you entered the correct phone number.');</script>";
        }

        $checkContactInfoStatement->close();
        $insertContactInfoStatement->close();
        $contactIdstatement->close();
        $subscriptionStatement->close();
        $mysqli->close();
    }

    if (isset($_POST['submit'])) {
        processSubmission();
        echo "<script>window.top.location='index.html'</script>";
    }
?>