<?php

	if(isset($_POST['submit'])){

		$to = "harvey.fletcher@reassured.co.uk";
		$subject = "New bug report";

		$name = $_POST['name'];
		$synopsis = $_POST['synopsis'];
		$description = $_POST['description'];
		 
		//We want to send a HTML mail so set those headers
		$headers[] = 'MIME-Version: 1.0';
		$headers[] = 'Content-type: text/html; charset=iso-8859-1';

		//Additional headers
		$headers[] = 'From: bugreporting@rmobileapp.co.uk';

		$data = '
			<html>
				<head>
	
				</head>
				<body>
					<table border="1" width="1000">
						<tr>
							<td colspan="2">
								<h1>New Bug Report</h1>
							</td>
							<td width="150px">
								<h1>' . date("d-m-y") . '</h1>
                        	                        </td>
						</tr>
						<tr>
							<td colspan="3" height="25">
                	                               	</td>
						</tr>
						<tr>
							<td colspan="3">
								<p>
									Synopsis: '. $synopsis  .'<br />
									<br />
									Description:<br />
									'. $description .'<br /><br /><br />
									'. $name .'
								</p>
                        	                        </td>
						</tr>
					</table>
				</body>
			</html>
		';

		 mail($to, $subject, $data, implode("\r\n", $headers));

		echo "<h2>Your report has been submitted.<br/>Thanks :)</h2>";

	} else { ?>
		<h1>Submit a new bug report</h1>
		<form method="post" action="bug.php">
			<input type="text" id="name" name="name" required placeholder="Your Name:" style="width: 500"></input><br /><br />
			<input type="text" id="synopsis" name="synopsis" required placeholder="Title:" style="width: 500"></input> <br /><br />
			<textarea id="description" name="description" placeholder="What problem are you experiencing? Please go into some detail..." required style="width: 500; height: 200"></textarea><br /><br />
			<input type="submit" id="submit" name="submit" style="width: 500; height: 50;"></input>
		</form>
<?php	}
?>
