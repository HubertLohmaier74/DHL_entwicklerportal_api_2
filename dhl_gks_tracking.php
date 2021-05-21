<?php

// ........................................................
// Operation modes for commercial api users 
// ........................................................
$operationList = array(
				"STATUS" 	=> "d-get-piece",			// Abfrage des aktuellen Sendungsstatus mit erweiterten Informationen
				"EVENTS"	=> "d-get-piece-events",	// Sendungsverlaufs mit allen Einzelereignissen zur einer Sendung
				"DETAILS"	=> "d-get-piece-detail",	// Kombinierter Aufruf von Sendungsstatus und Laufweg
				"SIGNATURE"	=> "d-get-signature"		// Abfrage der Unterschrift des Empfängers bzw. Ersatzempfängers (Zustellnachweis / POD)
			);
			
// ........................................................
// this list will receive the shipping numbers later in script
// ........................................................
$allShipmentIds = array();

// ........................................................
// load a shipment number that should be tracked
// ........................................................
function loadShipmentNumber($shipmentNo) {
	global $allShipmentIds;
	$allShipmentIds[] = $shipmentNo;
}

// ........................................................
// Create image directory if not existent
// ........................................................
function makeImageDir($dir) {
	if ( !is_dir($dir) ) {
		if ( !mkdir($dir) ) {
			die("Cannot create requested directory on server");
			return FALSE;
		} 
	}
}


// -----------------------------------------------------------------------------------------------------------------------------------------------------
// -----------------------------------------------------------------------------------------------------------------------------------------------------
// The following values are to change for your personal use
// -----------------------------------------------------------------------------------------------------------------------------------------------------
// -----------------------------------------------------------------------------------------------------------------------------------------------------

$mode        	= 'sandbox'; 							// "sandbox" or "production"
$language	 	= 'de';									// "de" or "en"
$username    	= 'Your DHL developer account name'; 	// dhl developer account user name (not email)
$password    	= 'Your DHL developer account pass';	// dhl developer account pass
$appname     	= 'zt12345'; 							// sandbox user
$apppass     	= 'geheim'; 							// sandbox pass
$endpoint    	= 'https://cig.dhl.de/services/' . $mode . '/rest/sendungsverfolgung';
$subdir			= 'api_signature';						// subdirectory where you like to store signature files when using 'SIGNATURE' operation


// ..............................................
// Which operation do you choose? (STATUS, DETAILS, EVENTS, SIGNATURE)
// ..............................................
$operation = $operationList['SIGNATURE'];				// Operation type (see list above)


// ..............................................
// Which shipment numbers do you want to track?
// (!! in sandbox only special test case numbers are allowed)
// ..............................................
loadShipmentNumber('00340434161094015902'); 	// this is a special DHL test case shipping number
loadShipmentNumber('00340434161094022115');		// this is a special DHL test case shipping number



// -----------------------------------------------------------------------------------------------------------------------------------------------------
// DO NOT CHANGE ANYTHING BELOW THIS LINE
// -----------------------------------------------------------------------------------------------------------------------------------------------------
$signature_dir 	= __DIR__ . '/' . trim($subdir, " /") . '/';

if ( count($allShipmentIds) == 0)
	die("No shipment numbers loaded for to use in a tracking request: use function 'loadShipmentNumber' !");


$feed_header = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>';
$feed_body   = ' <data appname="' . $appname . '" language-code="' . $language . '" password="' . $apppass . '" piece-code="" request="feed_operation">';
if ($operation != 'd-get-piece-events') {
	$feed_body	 = str_replace("feed_operation", $operation, $feed_body);
} else {
	$feed_body	 = str_replace("feed_operation", "d-get-piece", $feed_body); // g-get-piece-events needs a previous d-get-piece operation
}
$feed_bottom = '</data>';
$xml     	 = simplexml_load_string( $feed_header . $feed_body . $feed_bottom);


$opts = array(
    'http' => array(
        'method' => "GET",
        'header' => "Authorization: Basic " . base64_encode( "$username:$password" )
    )
);

$context = stream_context_create( $opts );

$status = array();
foreach ( $allShipmentIds as $shipmentid ) {
    $xml->attributes()->{'piece-code'} = $shipmentid;
    $response                              = file_get_contents( $endpoint . '?' . http_build_query( array( 'xml' => $xml->saveXML() ) ), false, $context );
	$responseXml                           = simplexml_load_string( $response );
	switch ($operation) {
		
		case "d-get-piece-detail" : 	
										foreach ( $responseXml->data->data->data as $event ) {
											$status[$shipmentid]['mode'] = $operation;
											$status[$shipmentid]['shipmentid'] = $shipmentid;									// save tracking no.
											$status[$shipmentid]['status'] = (string)$event->attributes()->{'event-short-status'};		// save last status
											$retoure = (string)$event->attributes()->{'ruecksendung'};
											if (strtolower($retoure) == 'false') $retoure = 0; else $retoure = 1;
											$datetime = explode(" ", $event->attributes()->{'event-timestamp'});
											$status[$shipmentid]['details'][] = 
											array (	'TEXT' => (string)$event->attributes()->{'event-short-status'} , 	// save any status
													'DATE' => $datetime[0] ,		// save any date
													'TIME' => $datetime[1] ,		// save any time
													'RETOURE' => $retoure
													);
										}
										break;

		case "d-get-piece-events":
		case "d-get-piece" :			
										$status[$shipmentid]['mode'] = $operation;
										$status[$shipmentid]['shipmentid'] = $shipmentid;									// save tracking no.
										$status[$shipmentid]['status'] = (string)$responseXml->data->attributes()->{'status'};
										$retoure = (string)$responseXml->data->attributes()->{'ruecksendung'};
										if (strtolower($retoure) == 'false') $retoure = 0; else $retoure = 1;
										$datetime = explode(" ", $responseXml->data->attributes()->{'status-timestamp'});
										$status[$shipmentid]['details'] = 
										array (	'TEXT' => (string)$responseXml->data->attributes()->{'short-status'} , 		// save status
												'DATE' => $datetime[0] ,	// save date
												'TIME' => $datetime[1] ,	// save time
												'PIECE-ID' => (string)$responseXml->data->attributes()->{'piece-id'} ,
												'RETOURE' =>  $retoure

												);	
										break;

		case "d-get-signature" :		
										$status[$shipmentid]['mode'] = $operation;
										$status[$shipmentid]['shipmentid'] = $shipmentid;									// save tracking no.
										$status[$shipmentid]['details'] = 
										array (	'FILE' => (string)$responseXml->data->attributes()->{'image'} , 		// save status
												'DATE' => (string)$responseXml->data->attributes()->{'event-date'} ,	// save timestamp
												'TIME' => "n.a." // not available
												);
										break;

	}
}

// --------------------------------------------------------------------------------------------------
// second call for d-get-piece-events
// --------------------------------------------------------------------------------------------------
if ($operation == "d-get-piece-events") {
	foreach ($status AS $piece) {
		$feed_body   = ' <data appname="' . $appname . '" language-code="' . $language . '" password="' . $apppass . '" piece-id="" request="d-get-piece-events">';
		$xml         = simplexml_load_string( $feed_header . $feed_body . $feed_bottom);
		$xml->attributes()->{'piece-id'} = $piece['details']['PIECE-ID'];
		$response    = file_get_contents( $endpoint . '?' . http_build_query( array( 'xml' => $xml->saveXML() ) ), false, $context );
		$responseXml = simplexml_load_string( $response );
		foreach ($responseXml AS $event) {
			$retoure = (string)$event->attributes()->{'ruecksendung'};
			if (strtolower($retoure) == 'false') $retoure = 0; else $retoure = 1;
			$datetime = explode(" ", $event->attributes()->{'event-timestamp'});
			$status[$piece['shipmentid']]['events'][] = array(
				'TEXT' => (string)$event->attributes()->{'event-short-status'} , 	// save any status
				'DATE' => $datetime[0] ,											// save any date
				'TIME' => $datetime[1] ,											// save any time
				'RETOURE' => $retoure												// save any retoure status
													   ); 
		}
	}
} 


// --------------------------------------------------------------------------------------------------
// Print shipment data on screen
// --------------------------------------------------------------------------------------------------

echo "<br><b><u>SENDUNGEN / STATUS: </u></b><pre>";
print_r($status);


// --------------------------------------------------------------------------------------------------
// save signature files (if working with 'd-get-signature' operation)
// --------------------------------------------------------------------------------------------------
if ($operation == "d-get-signature") {
	makeImageDir($signature_dir);
	foreach ($status AS $signature) {
		$binary = pack("H*", $signature['details']['FILE']);
		file_put_contents($signature_dir . $signature['shipmentid'] . ".gif", $binary);
	}
}


// --------------------------------------------------------------------------------------------------
// show signature files on screen (if working with 'd-get-signature' operation)
// --------------------------------------------------------------------------------------------------
if ($operation == "d-get-signature") {
	$files = scandir($signature_dir);
	foreach ($files AS $file)
		if ( !is_dir($file) ) {
			$file = './' . $subdir . '/' . $file;
			echo "<div style='clear: both; border-style: solid; margin: 20px; width: 400px;'>";
				echo "<div style='clear: both; margin: 20px; width: 300px; '>";
				echo "<b>$file</b>";
				echo "</div>";
				echo "<div style='clear: both; margin: 20px; width: 300px;'>";
				if (file_exists($file))
					echo "<img src='$file'/>";
				else
					echo "<br>File does not exist: " . $file;
				echo "</div>";
			echo "</div>";
		}
}

// --------------------------------------------------------------------------------------------------
echo "<hr>END";
// --------------------------------------------------------------------------------------------------

?>
