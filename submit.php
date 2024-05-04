<?php
    include 'global_variables.php';
    error_reporting(E_ERROR | E_PARSE);

    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;
    use PHPMailer\PHPMailer\SMTP;

    require 'vendor\phpmailer\phpmailer\src\Exception.php';
    require 'vendor\phpmailer\phpmailer\src\PHPMailer.php';
    require 'vendor\phpmailer\phpmailer\src\SMTP.php';
    require 'vendor\autoload.php';

    // This method sends the user a confirmation email listing the selected team(s). If the email address is invalid, it's NOT added to the database.
    function sendConfirmationEmail($email, $teamArray, $uuid) {
        global $smtp_username;
        global $gmail_app_password;
        global $sender_email;

        // Passing 'true' enables exceptions.
        $mail = new PHPMailer(true);

        try {
            // Server settings.
            //$mail->SMTPDebug = SMTP::DEBUG_SERVER;
            $mail->SMTPDebug = 2;
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = $smtp_username;
            $mail->Password = $gmail_app_password;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            // Set to/from.
            $mail->setFrom($sender_email, 'CollegeHockeyTransfers');
            $mail->addAddress($email);
            //$mail->addReplyTo('info@example.com', 'Information');

            // Assemble the email's contents.
            $mail->isHTML(true);
            $mail->Subject = 'CollegeHockeyTransfers Sign-Up Confirmation Email';

            $body = 'Thank you for signing up for CollegeHockeyTransfers. You have signed up to hear about transactions for the following teams:<br>';

            // List the teams te user subscribed to.
            for ($i = 0; $i < sizeof($teamArray); $i++) {
                $body = $body . '<b>' . $teamArray[$i] . '</b><br>';
            }

            // Add the edit link.
            $body = $body . '<br>Change or cancel your subscription <a href="http://localhost/CollegeHockeyTransfers/edit.php?email=' . $email . '&uuid=' . $uuid . '">here</a>.';

            $mail->Body = $body;
            //$mail->AltBody = 'This is the body in plain text for non-HTML mail clients'; // Do I really need a non-HTML body option?

            if (!$mail->send()) {
                // The email failed to send (if the email address in invalid, that would NOT cause the email to fail to send).
                return 0;
            }

            // The confirmation email was sent successfully.
            return 1;
        } catch (Exception $e) {
            // An exception occured along the way, so return a value meaning neither a success or failure (due to an invalid email address).
            return 0;
        }
    }

    // This method handles the user's submission by adding their email address and selected teams to the database.
    function processSubmission() {
        global $db_ip;
        global $db_username;
        global $db_password;
        global $database_name;
        global $teams;

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

        // Get the email address entered in the text box.
        $email = trim($_POST['email']);

        // Query (using a prepared statement) the email that was submitted to see if it's already in the database.
        $checkEmailstatement = $mysqli->prepare("SELECT * FROM Email AS E WHERE E.Email=?");
        $checkEmailstatement->bind_param('s', $email);
        $checkEmailstatement->execute();
        $result = $checkEmailstatement->get_result();

        // If the email address is already in the database, return and do not proceed.
        if ($result->num_rows == 1) {
            echo "<script type='text/javascript'>alert('Your email is already in the database. To change your selections, click the edit link in either the initial confirmation email or any transfer notification email.');</script>";
            $checkEmailstatement->free_result();
            $checkEmailstatement->close();
            $mysqli->close();
            return;
        }

        $checkEmailstatement->free_result();

        // Construct the array of team names that the user selected.
        $teamArray = explode(',', $submitButtonValue);

        // The email is not in the database, so add it to the Email table and add the team(s) to the Subscription table.
        $uuid = uniqid();
        $insertEmailStatement = $mysqli->prepare("INSERT INTO Email (Email, UUID) VALUES (?, ?)");
        $insertEmailStatement->bind_param('ss', $email, $uuid);
        $insertEmailStatement->execute();

        // The email address is NOT already in the database, so send a confirmation email listing the teams the user signed up for.
        if (!sendConfirmationEmail($email, $teamArray, $uuid)) {
            // The confirmation email failed to send.
            echo "<script type='text/javascript'>alert('The confirmation email failed to send, please try again. If the problem persists, please try again later.');</script>";
            $checkEmailstatement->close();
            $insertEmailStatement->close();
            $mysqli->close();
            return;
        }

        // Retrieve the email ID that was assigned to the row just inserted into the Email table.
        $emailIdstatement = $mysqli->prepare("SELECT Id FROM Email AS E WHERE E.Email=?");
        $emailIdstatement->bind_param('s', $email);
        $emailIdstatement->execute();
        $result = $emailIdstatement->get_result();
        $row = mysqli_fetch_assoc($result);
        $emailID = $row['Id'];
        $emailIdstatement->free_result();

        $dbTeamID = '';
        $subscriptionStatement = $mysqli->prepare("INSERT INTO Subscription (EmailId, TeamId) VALUES (?, ?)");
        $subscriptionStatement->bind_param('ii', $emailID, $dbTeamID);

        for ($i = 0; $i < sizeof($teamArray); $i++) {
            // Retrieve the team's corresponding team ID.
            $dbTeamID = $teams[$teamArray[$i]];

            // Add the (email, team ID) pair to the Subscription table.
            $subscriptionStatement->execute();
        }

        echo "<script type='text/javascript'>alert('Thank you. Your submission has been recorded. Please check your email inbox for a confirmation email. If you do not receive it after 5 minutes or so, please double-check that you entered the correct email address.');</script>";

        $checkEmailstatement->close();
        $insertEmailStatement->close();
        $emailIdstatement->close();
        $subscriptionStatement->close();
        $mysqli->close();
    }

    if (isset($_POST['submit'])) {
        processSubmission();
        echo "<script>window.top.location='main.html'</script>";
    }
?>