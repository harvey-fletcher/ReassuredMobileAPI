<?php

    //There's a file of common functions.
    include_once('common_functions.php');

    //Extract the right bit of input data
    $_POST = json_decode( $_POST["data"], true );

    //Check the user exists and can access this.
    auth($_POST['email'], $_POST['password']);

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
