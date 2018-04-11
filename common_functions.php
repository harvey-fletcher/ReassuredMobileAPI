<?php

    //Every page that uses this, assume we also need api_settings
    include_once('api_settings.php');

    //This function is for authorising users
    function auth($email, $password){
        //This is the query used to select user details
        $query = "SELECT * FROM users WHERE email='" . $email . "' AND password='". $password ."'";

        //Execute the query
        $result = mysqli_query($GLOBALS["conn"], $query);

        //We only want to success if there is a distinct match
        if(mysqli_num_rows($result) != 1){
            stdout(array("error" => "username or password incorrect"));
        }

        //Fetch the user details and store them as a global
        $GLOBALS['USER'] = mysqli_fetch_array($result, MYSQLI_ASSOC);
    }

    //This function prints and then dies
    function stdout($data){
        echo json_encode($data);
        die();
    }
