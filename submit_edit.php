<?php
    include 'global_variables.php';
    error_reporting(E_ERROR | E_PARSE);

    global $db_ip;
    global $db_username;
    global $db_password;
    global $database_name;
    global $teams;

    // Connect to the database
    $mysqli = new mysqli($db_ip, $db_username, $db_password, $database_name);

    // If there was an error when connecting to the database, do not proceed. Instead, ask the user to try again or wait longer if the problem persists.
    if ($mysqli->connect_errno) {
        $mysqli->close();
        echo "<script type='text/javascript'>alert('There was a problem accessing the database. Please try again. If the problem persists, please try again later.');</script>";
        echo "<script>window.top.location='main.html'</script>";
        return;
    }

    // Retrieve the user's email and UUID that were passed in from edit.php's form submission.
    $email = trim($_POST['email']);
    $uuid = trim($_POST['uuid']);

    // Query the database to get the email ID that coresponds to the user's email and UUID.
    $getEmailIdStatement = $mysqli->prepare("SELECT Id FROM Email WHERE Email = ? AND UUID = ?");
    $getEmailIdStatement->bind_param('ss', $email, $uuid);
    $getEmailIdStatement->execute();
    $result = $getEmailIdStatement->get_result();

    if ($result->num_rows == 0) {
        // There's no records to show because the email and UUID pair is not in the database, so alert the user.
        echo '<h2 style="text-align: center;">Changes cannot be made because there is no reocrd of your email in the database.</h2>';
    }
    else {
        // Extract the email ID from the result.
        $row = mysqli_fetch_assoc($result);
        $emailID = $row['Id'];
        $getEmailIdStatement->free_result();

        // Remove all the user's subscriptions.
        $removeSubcriptionStatement = $mysqli->prepare("DELETE FROM Subscription WHERE EmailId = ?");
        $removeSubcriptionStatement->bind_param('s', $emailID);
        $removeSubcriptionStatement->execute();
        $removeSubcriptionStatement->free_result();

        // Boolean to keep track of if any subscriptions were added after removing the existing ones.
        $noTeamsSelected = True;

        // Make a prepared statement for inserting the user's new selections.
        $dbTeamID = '';
        $insertSubscriptionStatement = $mysqli->prepare("INSERT INTO Subscription (EmailId, TeamId) VALUES (?, ?)");
        $insertSubscriptionStatement->bind_param('ii', $emailID, $dbTeamID);

        // For each D1 team, check if it was selected by the user on the edit page.
        foreach ($teams as $name => $id) {
            // Each checkbox's name attribute is the team's name, but spaces and periods are replaced with underscores. Do that here as well before looking it up in $_POST.
            $validCurrentTeamName = str_replace(' ', '_', $name);
            $validCurrentTeamName = str_replace('.', '_', $validCurrentTeamName);
            
            // If the user checked the current team on the edit page, add it to the Subscription table.
            if (isset($_POST[$validCurrentTeamName])) {
                $noTeamsSelected = False;
                
                // Retrieve the team's corresponding team ID.
                $dbTeamID = $id;

                // Insert the subscription into the Subscription table.
                $insertSubscriptionStatement->execute();
            }
        }

        if ($noTeamsSelected) {
            // If the user submitted an edit form without any teams selected, remove their email from the Email table and alert them that they've been removed from all communications.
            $removeEmailStatement = $mysqli->prepare("DELETE FROM Email WHERE Email = ?");
            $removeEmailStatement->bind_param('s', $email);
            $removeEmailStatement->execute();
            $removeEmailStatement->close();
            echo '<h2 style="text-align: center;">Your selections and email have been removed from the database. You may now close this page.</h2>';
        }
        else {
            // If the user submitted an edit form with at least one team selected, alert them that their changes have been made.
            echo '<h2 style="text-align: center;">Your changes have been applied. You may now close this page.</h2>';
        }
    }

    $getEmailIdStatement->close();
    $removeSubcriptionStatement->close();
    $insertSubscriptionStatement->close();
    $mysqli->close();
?>