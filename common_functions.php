<?php

    //Every page that uses this, assume we also need api_settings
    include_once('api_settings.php');

    //This function is for authorising users
    function auth($email, $password){
        //This is the query used to select user details
        $query = "SELECT * FROM users WHERE email='" . $email . "' AND password='". $password ."' AND activated='1'";

        //Execute the query
        $result = mysqli_query($GLOBALS["conn"], $query);

        //We only want to success if there is a distinct match
        if(mysqli_num_rows($result) != 1){
            stdout(array("error" => "username or password incorrect", "status" => "403"));
        }

        //Fetch the user details and store them as a global
        $GLOBALS['USER'] = mysqli_fetch_array($result, MYSQLI_ASSOC);

        //Say that the user authentication is OK
        $GLOBALS['USER']['status'] = 200;
    }

    //Send an email in HTML format
    function HTMLMailer($to, $body, $subject, $sender){
        //Set up some headers for the mail.
        $headers = "From: " . $sender . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";

        //Send the HTML formatted email
        mail($to, $subject, $body, $headers);
    }

    //This function prints and then dies
    function stdout($data){
        echo json_encode($data);
        die();
    }
