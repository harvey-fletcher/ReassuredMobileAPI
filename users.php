<?php
    //Include the API settings file with all the necessary keys
    include_once('api_settings.php');

    //Include the common functions file
    include_once('common_functions.php');

    //If this page was accessed via a get request, store the variables as post
    if($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $_POST = json_encode($_GET);
    } else {
        $_POST = trim(urldecode(file_get_contents('php://input')), '=');
    }

    //Decode the input into a useable array
    $GLOBALS['_POST'] = json_decode($_POST, true);

    //If we are doing a login, execute the common auth function
    if(isset($GLOBALS['_POST']['login'])){
        //Attempt to authenticate the user
        auth($GLOBALS['_POST']['email'], $GLOBALS['_POST']['password']);

        //Update the stored token for that user.
        updateToken();

        //Return the user details
        echo json_encode($GLOBALS['USER']);

        //Die so the user can't damage anything
        die();
    }

    //If the creation variable is set, we want to validate and create that new user.
    if(isset($GLOBALS['_POST']['create']))CheckUserCreationValidation();

    //This was an added security measure so users would need to activate their account
    //from their reassured email address
    if(isset($GLOBALS['_POST']['activate']))ActivateAccount();

    //If the user wants to update their password
    if(isset($GLOBALS['_POST']['changePass']))ChangePassword();

    //If the user wants to change their name
    if(isset($GLOBALS['_POST']['changeName']))ChangeName();

    function ChangeName(){
        //Authenticate the user.
        auth($GLOBALS['_POST']['email'], $GLOBALS['_POST']['password']);

        //We don't want to update the name straight away, we want to ask their team leader first
        //Get the email address of the team leader
        $query = "SELECT * FROM teams t JOIN users u on t.team_leader_user_id=u.id WHERE t.id=" . $GLOBALS['USER']['team_id'];
        $team_leader = mysqli_fetch_array(mysqli_query($GLOBALS['conn'], $query), MYSQLI_ASSOC);

        //This is the query that will be used to update the record when approved
        $on_approval_query = "UPDATE users SET firstname='" . ucfirst($GLOBALS['_POST']['firstname']) . "', lastname='" . ucfirst($GLOBALS['_POST']['surname']) . "' WHERE id=" . $GLOBALS['USER']['id'];

        //Generate a passkey for the pending action
        $PassKey = substr(hash('sha512', rand(1000, 9999)), 0, 10);

        //Insert the pending action
        $query = "INSERT INTO pending_actions (`action`,`passkey`,`affects_user_id`) VALUES ('" . mysqli_escape_string($GLOBALS['conn'], $on_approval_query) . "', '". $PassKey ."', " . $GLOBALS['USER']['id'] . ")";
        mysqli_query($GLOBALS['conn'], $query);

        //Build a HTML email to send to the team leader
        include_once('Pages/UserChangeNameRequestTemplate.php');

        //Mail the team leader
        HTMLMailer($team_leader['email'], $MailBody, 'Reassured App Name Change Request', 'itservicedesk@reassured.co.uk');

        stdout(array("success" => "An email was sent to your team leader, " . $team_leader['firstname'] . " " . $team_leader['lastname'] . ", they must approve the change."));
    }

    function ChangePassword(){
        //Authenticate the user.
        auth($GLOBALS['_POST']['email'], $GLOBALS['_POST']['password']);

        //This is the password update query
        $query = "UPDATE users SET password='". $GLOBALS['_POST']['newPassword'] ."' WHERE email='". $GLOBALS['_POST']['email'] . "' AND password='". $GLOBALS['_POST']['password'] ."'";
        mysqli_query($GLOBALS['conn'], $query);

        //If there was success, say it.
        if(mysqli_affected_rows($GLOBALS['conn']) == 1){
            stdout(array("success" => "Password has been updated"));
        } else {
            stdout(array("error" => mysqli_error($GLOBALS['conn'])));
        }
    }

    function updateToken(){
       //Delete any rows with matching tokens so that notifications are only displayed on the currently signed in user.
       $query = "DELETE FROM application_tokens WHERE application_token='" . $GLOBALS['USER']['id']  . "' OR user_id = " . $GLOBALS['USER']['id'];
       $result = mysqli_query($GLOBALS['conn'], $query);

       //Insert the new token
       $query = "INSERT INTO application_tokens (`user_id`,`application_token`) VALUES ('". $GLOBALS['USER']['id']  ."','". $GLOBALS['_POST']['token']  ."')";
       $result = mysqli_query($GLOBALS['conn'], $query);
    }

    function CheckUserCreationValidation(){
        //The fields we need are firstname, lastname, team_id and location_id, everything else is optional
        if(!isset($GLOBALS['_POST']['firstname']) || !isset($GLOBALS['_POST']['lastname'])  || !isset($GLOBALS['_POST']['team_id']) || !isset($GLOBALS['_POST']['location_id'])){
            stdout(array("status" => "403", "error" => "You have not specified the correct number of fields."));
        }

        //Assign all the fields with a short name
        $email = $GLOBALS['_POST']['email'];
        $password = $GLOBALS['_POST']['password'];
        $firstname = ucfirst($GLOBALS['_POST']['firstname']);
        $lastname = ucfirst($GLOBALS['_POST']['lastname']);
        $team = $GLOBALS['_POST']['team_id'];
        $location = $GLOBALS['_POST']['location_id'];

        //By default, everything is OK
        $valid = array(1, "Everything OK");

        //Check the email address provided belongs to a reassured domain.
        if(substr($email, -16) != '@reassured.co.uk'){
            $valid = array(0, "Please use your reassured email address");
        }

        //Firstname cannot be blank
        if($firstname == ""){
            $valid = array(0, "Firstname cant be blank");
        }

        //Lastname cannot be blank
        if($lastname == ""){
            $valid = array(0, "Lastname cant be blank");
        }

        //Password can't be blank
        if($password == ""){
            $valid = array(0, "Password can't be null");
        }

        //Team ID cannot be blank
        if($team == ""){
            $valid = array(0, "You must supply a team ID");
        }

        //If the location is blank, it should default to 1.
        if($location == ""){
            $location = 1;
        }

        //If everything is valid, proceed to the next step, else, error
        if($valid[0] == 0){
            stdout(array("status" => "400", "reason" => $valid[1]));
        } else {
            //Check if that email is already used
            $user_exists = mysqli_num_rows(mysqli_query($GLOBALS['conn'], "SELECT * FROM users WHERE email='" . $email . "'"));

            //Error if there is 1 or more rows
            if($user_exists == 0){
                //The user will be required to activate first to confirm they are a ReassuredLtd employee.
                $ActivationCode = substr(hash('sha512', rand(1000, 9999)), 0, 10);

                //The query to insert users
                $insert_user = "INSERT INTO users(`email`, `password`, `firstname`, `lastname`, `team_id`, `location_id`, `activation_code`) VALUES ('" . $email . "','" . $password . "','" . $firstname . "','" . $lastname . "','". $team ."','". $location ."', '". $ActivationCode ."')";

                //Execute that query
                $run_query = mysqli_query($GLOBALS['conn'], $insert_user);

                //How many rows were affected?
                $success = mysqli_affected_rows($GLOBALS['conn']);

                //Was it successful?
                if($success == 1){
                    //Since it was successful, we will need to send an activation link
                    $body = "Hello, " . $firstname . ",<br /><br />Thank you for signing up to the ReassuredMobile smartphone app, please click the link below to activate your account.<br /><br /><a href=\"" . $GLOBALS['api_hostname'] . "/users.php?activate&code=". $ActivationCode ."\">Click here to activate</a>";

                    //Since this mail includes HTML, we want to send a HTMLMailer
                    HTMLMailer($email, $body, "Reassured App Activation", "itservicedesk@reassured.co.uk");

                    stdout(array("status" => "200", "reason" => "New user created.\nPlease check your emails for the activation email."));
                } else {
                    stdout(array("status" => "500", "reason" => "Something went wrong, please try again"));
                }
            }
        }
    }

    function ActivateAccount(){
        //Only attempt account activation if a code was provided, otherwise, fail straight away
        if(isset($_GET['code'])){
            //Only update rows that match the code and have not yet been activated.
            $query = "UPDATE users SET activated=1 WHERE activation_code='". $GLOBALS['_POST']['code'] . "' AND activated='0'";
            mysqli_query($GLOBALS['conn'], $query);

            if(mysqli_affected_rows($GLOBALS['conn']) == 1){
                include_once('Pages/activation_success.html');
            } else {
                include_once('Pages/activation_failure.html');
            }
        } else {
            include_once('Pages/activation_failure.html');
        }
    }

?>
