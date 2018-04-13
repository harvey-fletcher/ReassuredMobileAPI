<?php
    //Set content type
    header('Content-Type: application/json');

    //There's a file of common functions.
    include_once('common_functions.php');

    //Decode the input and store it
    $GLOBALS['_POST'] = urldecode(file_get_contents("php://input"));

    //If the string starts with an =, remove the =
    if(strpos(substr($GLOBALS['_POST'], 0, 1), '=') !== false){
        $GLOBALS['_POST'] = substr($GLOBALS['_POST'], 1);
    }

    //Now we have removed the leading = if necessary, decode
    $GLOBALS['_POST'] = json_decode($GLOBALS['_POST'], true);

    //Check the user exists and can access this.
    auth($GLOBALS['_POST']['email'], $GLOBALS['_POST']['password']);

    //We need to execute a specific funtion
    //has the user described what function they want?
    if(!isset($_POST['function'])){
        //Error that the user needs to specify a function
        stdout(array("error" => "you need to specify a function"));
    } else {
        //We need to know if the function exists
        if(!function_exists($_POST['function'])){
            stdout(array("error" => "that function doesn't exist"));
        }

        //Execute the requested function
        $_POST['function']();
    }

    //This is used for receiving new service desk requests
    function inbound(){
        //Collate all the values
        $values = array(
                "user_id" => $GLOBALS['USER']['id'],
//                "to_email" => 'harvey.fletcher@reassured.co.uk',
                "to_email" => 'itservicedesk@reassured.co.uk, harvey.fletcher@reassured.co.uk',
                "subject" => $GLOBALS['_POST']['subject'],
                "email_body" => $GLOBALS['_POST']['email_body'] . "\n\nThis ticket was submitted via the reassured mobile app.",
            );

        //Build the query
        $query = "INSERT INTO emails (`user_id`,`to_email`,`subject`,`email_body`) VALUES ('". $values['user_id'] ."','". $values['to_email'] ."','". $values['subject'] ."','". $values['email_body'] ."')";

        //Execute the query
        mysqli_query($GLOBALS['conn'], $query);

        //Send the mail
        mail($values['to_email'], $values['subject'], $values['email_body'], 'From: ' . $GLOBALS['USER']['email']);

        //Output
        stdout(array("success" => mysqli_affected_rows($GLOBALS['conn']) . " rows affected"));
    }

    //This is used for retrieving stored service desk requests
    function retrieve(){
        //We need a query to select all the unsent emails
        $query = "SELECT e.*, u.email, u.firstname AS 'Firstname', u.lastname AS 'Lastname' FROM emails e JOIN users u ON e.user_id=u.id WHERE e.sent='0'";

        //Get the results
        $results = mysqli_query($GLOBALS['conn'], $query);

        //There is an array of emails that is the output
        $emails = array();

        //For all the emails in the results, add them to emails
        while($email = mysqli_fetch_array($results, MYSQLI_ASSOC)){
            array_push($emails, $email);

            //We need to mark the emails as sent
            $query = "UPDATE emails SET sent='1' WHERE id=" . $email['id'];
            mysqli_query($GLOBALS['conn'], $query);
        }

        //Now we need to send out the emails
        stdout($emails);
    }
