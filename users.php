<?php
    //Include the API settings file with all the necessary keys
    include_once('api_settings.php');

    //Include the common functions file
    include_once('common_functions.php');

    //If this page was accessed via a get request, store the variables as post
    if($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $_POST = json_encode($_GET);
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
        $firstname = $GLOBALS['_POST']['firstname'];
        $lastname = $GLOBALS['_POST']['lastname'];
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
        if($password = ""){
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
                //The query to insert users
                $insert_user = "INSERT INTO users(`email`, `password`, `firstname`, `lastname`, `team_id`, `location_id`) VALUES ('" . $email . "','" . $password . "','" . $firstname . "','" . $lastname . "','". $team ."','". $location ."')";

                //Execute that query
                $run_query = mysqli_query($GLOBALS['conn'], $insert_user);

                //How many rows were affected?
                $success = mysqli_affected_rows($GLOBALS['conn']);

                //Was it successful?
                if($success == 1){
                    stdout(array("status" => "200", "reason" => "New user created"));
                } else {
                    stdout(array("status" => "500", "reason" => "Something went wrong, please try again"));
                }
            }
        }
    }

?>
