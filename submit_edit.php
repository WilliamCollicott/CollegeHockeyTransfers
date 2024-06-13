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

    global $db_ip;
    global $db_username;
    global $db_password;
    global $database_name;
    global $teams;
    global $smtp_username;
    global $gmail_app_password;
    global $sender_email;

    // Connect to the database
    $mysqli = new mysqli($db_ip, $db_username, $db_password, $database_name);

    // If there was an error when connecting to the database, do not proceed. Instead, ask the user to try again or wait longer if the problem persists.
    if ($mysqli->connect_errno) {
        $mysqli->close();
        echo "<script type='text/javascript'>alert('There was a problem accessing the database. Please try again. If the problem persists, please try again later.');</script>";
        echo "<script>window.top.location='index.html'</script>";
        return;
    }

    // Retrieve the user's email/phone number and UUID that were passed in from edit.php's form submission.
    $email = trim($_POST['email']);
    $phoneNumber = trim($_GET['phoneNumber']);
    $uuid = trim($_POST['uuid']);

    // Query the database to get the ID that coresponds to the user's email/phone number and UUID, depending on form of communication they signed up for.
    if ($email != '')
    {
        $getContactTableIdStatement = $mysqli->prepare("SELECT Id FROM Contact WHERE Email = ? AND UUID = ?");
        $getContactTableIdStatement->bind_param('ss', $email, $uuid);
    }
    else {
        $getContactTableIdStatement = $mysqli->prepare("SELECT Id FROM Contact WHERE PhoneNumber = ? AND UUID = ?");
        $getContactTableIdStatement->bind_param('is', $phoneNumber, $uuid);
    }

    $getContactTableIdStatement->execute();
    $result = $getContactTableIdStatement->get_result();

    if ($result->num_rows == 0) {
        // There's no records to show because the email/phone number and UUID pair is not in the database, so alert the user.
        if ($email != '') {
            echo '<h2 style="text-align: center;">Changes cannot be made because there is no reocrd of your email in the database.</h2>';
        }
        else {
            echo '<h2 style="text-align: center;">Changes cannot be made because there is no reocrd of your phone number in the database.</h2>';
        }
    }
    else {
        // Extract the ID from the result.
        $row = mysqli_fetch_assoc($result);
        $contactID = $row['Id'];
        $getContactTableIdStatement->free_result();

        // Remove all the user's subscriptions.
        $removeSubcriptionStatement = $mysqli->prepare("DELETE FROM Subscription WHERE ContactId = ?");
        $removeSubcriptionStatement->bind_param('i', $contactID);
        $removeSubcriptionStatement->execute();
        $removeSubcriptionStatement->free_result();

        // Boolean to keep track of if any subscriptions were added after removing the existing ones.
        $noTeamsSelected = True;

        // Make a prepared statement for inserting the user's new selections.
        $dbTeamID = '';
        $insertSubscriptionStatement = $mysqli->prepare("INSERT INTO Subscription (ContactId, TeamId) VALUES (?, ?)");
        $insertSubscriptionStatement->bind_param('ii', $contactID, $dbTeamID);

        $selectedTeams = '';

        // For each D1 team, check if it was selected by the user on the edit page.
        foreach ($teams as $name => $id) {
            // Each checkbox's name attribute is the team's name, but spaces and periods are replaced with underscores. Do that here as well before looking it up in $_POST.
            $validCurrentTeamName = str_replace(' ', '_', $name);
            $validCurrentTeamName = str_replace('.', '_', $validCurrentTeamName);
            
            // If the user checked the current team on the edit page, add it to the Subscription table.
            if (isset($_POST[$validCurrentTeamName])) {
                $noTeamsSelected = False;
                $selectedTeams = $selectedTeams . '<b>' . $name . '</b><br>';
                
                // Retrieve the team's corresponding team ID.
                $dbTeamID = $id;

                // Insert the subscription into the Subscription table.
                $insertSubscriptionStatement->execute();
            }
        }

        if ($noTeamsSelected) {
            // If the user submitted an edit form without any teams selected, remove their email/phone number from the Contact table.
            if ($email != '') {
                $removeEmailStatement = $mysqli->prepare("DELETE FROM Contact WHERE Email = ?");
                $removeEmailStatement->bind_param('s', $email);
            }
            else {
                $removeEmailStatement = $mysqli->prepare("DELETE FROM Contact WHERE PhoneNumber = ?");
                $removeEmailStatement->bind_param('i', $phoneNumber);
            }

            $removeEmailStatement->execute();
            $removeEmailStatement->close();

            // Alert the user that they've been removed from all communications.
            echo '<h2 style="text-align: center;">You have completely unsubscribed from CollegeHockeyTransfers and your contact information has been removed from the database. You may now close this page.</h2>';

            // Set the subject and body variables for the email to william@collicott.com indicating the edit action.
            $subject = 'CHT Complete Unsubscribe Alert';
            if ($email != '') {
                $body = $email . ' has completely unsubscribed.';    
            }
            else {
                $body = $phoneNumber . ' has completely unsubscribed.';
            }   
        }
        else {
            // If the user submitted an edit form with at least one team selected, alert them that their changes have been applied.
            echo '<h2 style="text-align: center;">Your changes have been applied. You may now close this page.</h2>';

            // Set the subject and body variables for the email to william@collicott.com indicating the unsubscribe action.
            $subject = 'CHT Edit Alert';
            if ($email != '') {
                $body = $email . ' has changed their subscriptions to the following teams:<br>' . $selectedTeams;    
            }
            else {
                $body = $phoneNumber . ' has changed their subscriptions to the following teams:<br>' . $selectedTeams;
            }
        }

        try {
            // Set up the server settings for emailing william@collicott.com to indicate an edit or unsubscribe action.
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = $smtp_username;
            $mail->Password = $gmail_app_password;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            $mail->setFrom($sender_email, 'CollegeHockeyTransfers');
            $mail->addAddress('william@collicott.com');
            $mail->isHTML(true);

            // Assemble the email and send it.
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->send();
        } catch (Exception $e) {
            // An exception occured along the way, so don't bother sending an email.
        }
    }

    $getContactTableIdStatement->close();
    $removeSubcriptionStatement->close();
    $insertSubscriptionStatement->close();
    $mysqli->close();
?>