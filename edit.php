<?php
    include 'global_variables.php';
    error_reporting(E_ERROR | E_PARSE);

    global $db_ip;
    global $db_username;
    global $db_password;
    global $database_name;
    global $teams;

    $email = trim($_GET['email']);
    $uuid = trim($_GET['uuid']);

    // Connect to the database
    $mysqli = new mysqli($db_ip, $db_username, $db_password, $database_name);

    // If there was an error when connecting to the database, do not proceed. Instead, ask the user to try again or wait longer if the problem persists.
    if ($mysqli->connect_errno) {
        $mysqli->close();
        echo "<script type='text/javascript'>alert('There was a problem accessing the database. Please try again. If the problem persists, please try again later.');</script>";
        echo "<script>window.top.location='main.html'</script>";
        return;
    }

    $getTeamsStatement = $mysqli->prepare("SELECT TeamName FROM Team AS T JOIN Subscription AS S ON S.TeamId = T.Id JOIN Email AS E ON E.Id = S.EmailId WHERE Email = ? AND UUID = ?");
    $getTeamsStatement->bind_param('ss', $email, $uuid);
    $getTeamsStatement->execute();
    $result = $getTeamsStatement->get_result();

    if ($result->num_rows == 0) {
        // There's no records to show because the email and UUID pair is not in the database, so alert the user.
        echo '<h2 style="text-align: center;">Changes cannot be made because there is no reocrd of your email in the database.</h2>';
    }
    else {
        // The user's email and UUID are in the database, so display a list of all D1 teams and automatically select the ones the user subscribed to.
        $subscribed_teams = array();
        while($row = $result->fetch_row()) {
            array_push($subscribed_teams, $row[0]);
        }

        echo '<p style="font-size: 20px;">Welcome to the edit page! Your current selections are already checked. Click "Submit" when you are finished making changes. To unsubscribe from all teams and remove your email from the database, uncheck all teams and click "Submit".</p>';

        // Echo a form of checkboxes, one for each D1 team.
        echo '<form action="submit_edit.php" method="post">';

        foreach ($teams as $name => $id) {
            // Replace spaces and periods with underscores because the name attribute cannot have either of those characters.
            $currentTeam = $name;
            $validCurrentTeamName = str_replace(' ', '_', $currentTeam);
            $validCurrentTeamName = str_replace('.', '_', $validCurrentTeamName);

            echo '<table>';
            if (in_array($currentTeam, $subscribed_teams)) {
                // The user is subscribed to the team, so check the box automatically.
                echo '<tr><td><input type="checkbox" name="' . $validCurrentTeamName . '" checked/></td><td><label>' . $currentTeam . '</label></td></tr>';
            }
            else {
                // The user is not subscribed to the team, so don't automatically check the box.
                echo '<tr><td><input type="checkbox" name="' . $validCurrentTeamName . '"/></td><td><label>' . $currentTeam . '</label></td></tr>';
            }
            echo '</table>';
        }

        // Include a hidden text box for the user's email and UUID so they can be passed to submit_edit.php upon form submission.
        echo '<input type="hidden" name="email" value="' . $email . '">';
        echo '<input type="hidden" name="uuid" value="' . $uuid . '">';
        echo '<br><br><button><b>SUBMIT</b></button></form>';
    }

    $getTeamsStatement->free_result();
    $mysqli->close();
?>