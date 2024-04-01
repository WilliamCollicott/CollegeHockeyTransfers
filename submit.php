<?php
    include "website_db_credentials.php";

    error_reporting(E_ERROR | E_PARSE);
    
    function processSubmission() {
        global $db_ip;
        global $db_username;
        global $db_password;
        global $database_name;

        // Connect to the database
        $mysqli = new mysqli($db_ip, $db_username, $db_password, $database_name);

        // If there was an error when connecting to the database, do not proceed. Instead, ask the user to try again or wait longer if the problem persists.
        if ($mysqli->connect_errno) {
            echo "<script type='text/javascript'>alert('There was a problem connecting to the database. Please try again. If the problem persists, please try again later.');</script>";
            return;
        }

        // Get the value of the submit button (selected team names separated by commas).
        $submitButtonValue = $_POST["submit"];

        // If no teams were selected, alert the user and return from the function, which will then re-direct back to the homepage.
        if ($submitButtonValue == '')
        {
            echo "<script type='text/javascript'>alert('You did not select any teams. Please select at least one team before submitting.');</script>";
            $mysqli->close();
            return;
        }

        // Get the email address entered in the text box.
        $email = $_POST["email"];

        // Query the email that was submitted to see if it's already in the database.
        $result = $mysqli->query("SELECT * FROM Email AS E WHERE E.Email='$email'");
        
        // If the email address is already in the database, return and do not proceed.
        if ($result->num_rows == 1) {
            
            echo "<script type='text/javascript'>alert('Your email is already in the database. To change your selections, click the edit link in either the initial confirmation email or any transfer notification emails.');</script>";
            $mysqli -> close();
            return;
        }
        
        // The email is not in the database, so add it to the Email table and add the team(s) to the Subscription table.
        $uuid = uniqid();
        $mysqli->query("INSERT INTO Email (Email, UUID) VALUES ('$email', '$uuid')");

        // Retrieve the email ID that was assigned to the row just inserted into the Email table.
        $result = $mysqli->query("SELECT Id FROM Email AS E WHERE E.Email='$email'");
        $row = mysqli_fetch_assoc($result);
        $emailID = $row['Id'];

        // Construct the array of team names that the user selected.
        $teamArray = explode(',', $submitButtonValue);

        // For each team selected by the user, retrieve its corresponding team ID from the Team table and insert an (email, team ID) pair into the Subscription table.
        for ($i = 0; $i < sizeof($teamArray); $i++)
        {
            // Retrieve the team ID.
            $result = $mysqli->query("SELECT Id FROM Team AS T WHERE T.TeamName='$teamArray[$i]'");
            $row = mysqli_fetch_assoc($result);
            $dbTeamID = $row['Id'];

            // Add the (email, team ID) pair to the Subscription table.
            $mysqli->query("INSERT INTO Subscription (EmailId, TeamId) VALUES ('$emailID', '$dbTeamID')");
        }

        echo "<script type='text/javascript'>alert('Thank you. Your submission has been recorded.');</script>";
        $mysqli -> close();
    }

    if (isset($_POST["submit"])) {
        processSubmission();
        echo "<script>window.top.location='main.html'</script>";
    }
?>