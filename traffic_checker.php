<?php

    //API_settings
    include_once('api_settings.php');

    //Common Functions including sendFCM();
    include_once('common_functions.php');

    //This is the data files
    $DF = array(
            "Count" => __DIR__ . '/trafficCount.txt',
            "Data" => __DIR__ . '/traffic.txt',
        );

    //Blank arrays for storing data
    $items = array();
    $output = array();

    //The existing number of known traffic events
    $ExistingTrafficEvents = file_get_contents($DF['Count']);

    //Initialise a curl to get the data
    $curl = curl_init();

    //Setup the options for the curl
    curl_setopt_array($curl, array(
                CURLOPT_URL => $external_endpoints['highways'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_HTTPHEADER => array(
                        "Cache-Control: no-cache",
                    ),
            )
        );

    //Execute the curl
    $inputStream = curl_exec($curl);

    //Create an XML parser
    $p = xml_parser_create();

    //Parse the XML
    xml_parse_into_struct($p, $inputStream, $outputStream);

    //Remove the two undesired <rss> and <channel> tags
    array_shift($outputStream);
    array_shift($outputStream);
    array_pop($outputStream);
    array_pop($outputStream);

    //There are still 15 undesired tags at the beginning of the array. Remove them.
    foreach(range(0, 15) as $element){
        array_shift($outputStream);
    }

    //Split up the array into individual items
    $output = array_chunk($outputStream, 20);

    //We want to retrieve a subset of data from the fields on all the items
    foreach(range(0, sizeof($output) - 1) as $alert){
        //Split up the details of the event so we get a precise location,
        $details = str_replace("Location : The ", "", explode("\n", $output[$alert][4]["value"])[0]);

        //Set up the item to have only the fields we want
        $output[$alert] = array(
                0 => trim($details),                                                                             //Precise location of the incident
                1 => $output[$alert][3]["value"],                                                                //The name of the road
                2 => explode(" | ", $output[$alert][8]["value"])[2],                                             //The cause of the incident
                3 => $output[$alert][10]["value"] . " " .  explode(" | ", $output[$alert][8]["value"])[1],       //The Road name and direction
                4 => $output[$alert][12]["value"]                                                                //County
            );
    }

    //We only want to output the events which are happening within the 3 county area.
    foreach($output as $event){
        if($event["4"] == "Hampshire" || $event["4"] == "Surrey" || $event["4"] == "Berkshire"){
            $data[] = json_encode($event, JSON_FORCE_OBJECT);
        }
    }

    //Compare the number of traffic events with the previous count, if it is greater,
    //send an FCM notification via the function in common_functions.php
    if(sizeof($data) > $ExistingTrafficEvents){
        $Notification = array(
                "data" => array(
                        "notification_type" => "traffic"
                    )
            );

        sendFCM(array(1), $Notification);
    }

    //Overwrite the data storage files.
    file_put_contents($DF['Data'], '[' . implode(',', $data) . ']');
    file_put_contents($DF['Count'], sizeof($data));

    //What to do if there are extra parameters
    if(sizeof($argv) > 1){
        if(in_array("-v", $argv) !== false){
            print_r($data);
        }

        if(in_array("-c", $argv) !== false){
            file_put_contents($DF['Data'], "[]");
            file_put_contents($DF['Count'], 0);
        }
    }
?>
