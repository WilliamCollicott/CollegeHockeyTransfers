let selectedTeams = [];

// Handle the event of the user clicking a teams' button.
function clickTeam(team) {
    buttonClicked = document.getElementById(team);

    if (buttonClicked.style.backgroundColor != "lightgreen") {
        // If the button was previously un-selected, turn it's background to green.
        buttonClicked.style.backgroundColor = "lightgreen";
        buttonClicked.style.borderColor = "darkgreen";
        selectedTeams.push(buttonClicked.id);
    }
    else {
        // If the button was previously selected, turn it's background back to white.
        buttonClicked.style.backgroundColor = "whitesmoke";
        buttonClicked.style.borderColor = "whitesmoke";
        let index = selectedTeams.indexOf(buttonClicked.id);
        selectedTeams.splice(index, 1);
    }

    document.getElementById("submit").value = selectedTeams.toString();
}

// Check the email submission text box for a valid email.
function checkEmail(element) {
    let re = new RegExp(/^[a-zA-Z0-9.!#$%&’*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]{2,})$/);

    if (element.value.search(re) >= 0) {
        // If the email address entered is valid, change the box's color to green and enable the "Submit" button.
        element.style.backgroundColor = "lightgreen";
        document.getElementById("submit").disabled = false;
    }
    else {
        // If the email address entered is invalid, change the box's color to red and disable the "Submit" button.
        element.style.backgroundColor = "lightcoral";
        document.getElementById("submit").disabled = true;
    }
}