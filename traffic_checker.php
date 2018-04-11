<?php

	//API_settings
	include_once('api_settings.php');

	$items = array();
	$output = array();

	$ExistingTrafficEvents = file_get_contents($api_root . "trafficCount.txt");

	$inputStream = shell_exec('curl http://m.highways.gov.uk/feeds/rss/UnplannedEvents.xml');

	$inputStream = str_replace('<', '&lt;', $inputStream);
	$inputStream = str_replace('>', '&gt;<br />', $inputStream);

	$xmlArray = explode('<br />', $inputStream);

	foreach(range(0, 32) as $element){
		array_shift($xmlArray);
	}

	$xmlArray = array_chunk($xmlArray, 38);

	foreach($xmlArray as $item){
		foreach($item as $tag){
			if(strpos($tag, '/')){
				array_push($items, $tag);
			};
		}
	}

	foreach($items as $item){
		array_push($output, substr($item, 0, strpos($item, '&lt;')));
	}

	foreach(range(0,2) as $end){
		array_pop($output);
	}

	$output = array_chunk($output, 19);

	$event = 0;
	foreach(range(0, sizeof($output) - 1) as $alert){
		unset($output[$alert][0]);
		unset($output[$alert][1]);
		unset($output[$alert][3]);
		unset($output[$alert][4]);
                unset($output[$alert][5]);
                unset($output[$alert][6]);
                unset($output[$alert][8]);
		unset($output[$alert][9]);
		unset($output[$alert][10]);
                unset($output[$alert][12]);
                unset($output[$alert][13]);
		unset($output[$alert][14]);
                unset($output[$alert][15]);
                unset($output[$alert][16]);
                unset($output[$alert][17]);
                unset($output[$alert][18]);
		$output[$alert] = array_values($output[$alert]);

	}

	foreach(range(0, sizeof($output) - 1) as $alert){
		$output[$alert][1] = explode("|",$output[$alert][1]);

		foreach($output[$alert][1] as $detail){
			array_push($output[$alert], trim(str_replace("|","",rtrim($detail))));
		}

		unset($output[$alert][1]);
		$output[$alert] = array_values($output[$alert]);
	}

	$data = array();

	foreach($output as $event){
		if((in_array('Hampshire', $event) || in_array('Berkshire', $event)  || in_array('Surrey', $event))&& !in_array('No Delay', $event)){
			unset($event[1]);
			$event = array_values($event);
			array_push($data, json_encode($event, JSON_FORCE_OBJECT));
		}
	}

	if(sizeof($data) > $ExistingTrafficEvents){
		$command = 'curl "' .$api_hostname . 'notifications.php?email=' . $administrator_email  .  '&password=' . $administrator_password  .  '&notification_type=traffic&to_group=all"';
		shell_exec($command);
	}

	unlink($api_root . "traffic.txt");	
	unlink($api_root . "trafficCount.txt");
	file_put_contents($api_root . "traffic.txt", json_encode($data));
	file_put_contents($api_root . "trafficCount.txt", sizeof($data));
?>
