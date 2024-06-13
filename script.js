let selectedTeams = [];

// Handle the event of the user clicking a teams' button.
function clickTeam(team) {
    buttonClicked = document.getElementById(team);

    if (buttonClicked.style.backgroundColor != 'lightgreen') {
        // If the button was previously un-selected, turn it's background to green.
        buttonClicked.style.backgroundColor = 'lightgreen';
        buttonClicked.style.borderColor = 'darkgreen';
        selectedTeams.push(buttonClicked.id);
    }
    else {
        // If the button was previously selected, turn it's background back to white.
        buttonClicked.style.backgroundColor = 'whitesmoke';
        buttonClicked.style.borderColor = 'whitesmoke';
        let index = selectedTeams.indexOf(buttonClicked.id);
        selectedTeams.splice(index, 1);
    }

    document.getElementById('submit').value = selectedTeams.toString();
}

// Check the validity of the email or phone number submission text box.
function checkContactMethod(element) {
    let re;

    // If the email text box was passed in, enforce the email address regular expression. Otherwise, the
    // phone number text box was passed in, so enforce the phone number regular expression.
    if (element.id == 'email text box') {
        re = new RegExp(/^[a-zA-Z0-9.!#$%&’*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]{2,})$/);
    }
    else {
        re = new RegExp(/^[\d]{10}$/);
    }

    // If the entered text is valid, change the box's color to green and enable the 'Submit' button.
    // Otherwise, it's invalid, so change the color to red and disable the 'Submit' button.
    if (element.value.search(re) >= 0) {
        element.style.backgroundColor = 'lightgreen';
        document.getElementById('submit').disabled = false;
    }
    else {
        element.style.backgroundColor = '#ffb3b3';
        document.getElementById('submit').disabled = true;
    }
}

// Check which radio button was clicked, enable it's respective text box in the DOM, and disable the other.
function radioButtonOnClick(element) {
    // No matter which button was clicked, un-hide the submit button and clear both text boxes of any text.
    document.getElementById('submit').hidden = false;
    document.getElementById('email text box').value = '';
    document.getElementById('phone number text box').value = '';

    // Depending on which radio button was passed in ('Email' or 'Text Message'), check the corresponding
    // text box text for compliance with the corresponding regular expression.
    if (element.id == 'email radio button') {
        checkContactMethod(document.getElementById('email text box'));
        document.getElementById('email text box div').hidden = false;
        document.getElementById('phone number text box div').hidden = true;
    }
    else {
        checkContactMethod(document.getElementById('phone number text box'));
        document.getElementById('phone number text box div').hidden = false;
        document.getElementById('email text box div').hidden = true;
    }
}