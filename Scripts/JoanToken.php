<?php

    /**

        This script is designed to be run on a cron job once every hour,
        it will refresh the JOAN Meeting room token, so that we will always
        be up to date and able to book meetings.

        Initially, we were changing the token for EVERY request that was made
        but after experimentation, it became apparent that it's much quicker
        to do it once an hour and store the token in the database.

    **/

    //We're going to need sitesettings file
    include_once('api_settings.php');

    //Since this is designed to be run from server side, we're going to call from argv parameter 1
    $argv[1]();

    //This function will refresh the JOAN token and store it in the database.
    function Refresh(){
        //These are the joan credentials, give them short names
        $client = $GLOBALS["external_keys"]["joan"]["client"];
        $secret = $GLOBALS["external_keys"]["joan"]["secret"];

        //Start a curl
        $curl = curl_init();

        //Set the curl options
        JoanSetOpts($curl, "token", "grant_type=client_credentials");
        curl_setopt($curl, CURLOPT_USERPWD, $client . ':' . $secret);

        //Set the curl headers
        $headers = array();
        $headers[] = "Content-Type: application/x-www-form-urlencoded";
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        //Execute the curl request and retrieve the token
        $JoanToken = json_decode(curl_exec($curl), true)["access_token"];

        //Update the token row in the database
        $result = mysqli_query($GLOBALS["conn"], "UPDATE joan_token SET token='" . $JoanToken . "' WHERE id=1");

        //Was it a success or not?
        echo mysqli_affected_rows($GLOBALS["conn"]);
    }

    //This function is only used in this controller so we have not put it in common functions
    //it sets up the required options for making a curl request into the joan api.
    function JoanSetOpts($curl, $page, $postfields){
        curl_setopt($curl, CURLOPT_URL, $GLOBALS["external_endpoints"]["joan"][$page]);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postfields);
        curl_setopt($curl, CURLOPT_POST, 1);

        return $curl;
    }
