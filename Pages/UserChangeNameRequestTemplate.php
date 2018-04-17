<?php

    $MailBody = "
        <body>
            Hello " . $team_leader['firstname'] .",
            <br /><br />
            " . $GLOBALS['USER']['firstname'] . " " . $GLOBALS['USER']['lastname'] . " has made a request via the Reassured Mobile App to change their name to: \"". ucfirst($GLOBALS['_POST']['firstname']) . " " . ucfirst($GLOBALS['_POST']['surname']) ."\"
            <br /><br />
            Please follow <a href=\"". $GLOBALS['api_hostname'] ."/Pages/CompletePendingRequest.php?PassKey=". $PassKey ."\"  >this link</a> to authorise this change.
            <br /><br />
            Thanks,
            ReassuredMobileApp
        </body>";
