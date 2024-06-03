<?php

$connnectionSerialNumber = 0;
$OSerialNumber = 0;
$connected = false;
$port = 1;
$slot = 0;


function getplctagvalue($ipaddress, $port, $slot, $tagname) {

    $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if ($sock == false) {
        trigger_error("Error creating socket: " . socket_strerror(socket_last_error()));
    }

    $timeout = array('sec'=>10, 'usec'=>0);
    socket_set_option($sock, SOL_SOCKET, SO_SNDTIMEO, $timeout);
    socket_set_option($sock, SOL_SOCKET, SO_RCVTIMEO, $timeout);


    $result = socket_connect($sock, $ipaddress, 44818); 
    if ($result == false) {
        trigger_error("Error connecting to PLC: " . socket_strerror(socket_last_error()));
    }
	
    send_list_identity_request($sock);
    determine_encapsulation($sock);
    $eipSessionHandle = getEipSessionHandle($sock);		
    $cipConnectionID = ConnectToMessageRouter($sock, $eipSessionHandle, $port, $slot);
    $tagValue = PlcRead($sock, $eipSessionHandle, $cipConnectionID, $tagname);
	$connected = disconnectFromMessageRouter($sock, $eipSessionHandle , $port, $slot); 
	socket_close($socket);

	return($tagValue);
}





function send_list_identity_request($sock) {
//echo "<br>"; //echo "<br><b><font color=green>function send_list_identity_request(sock)  </font></b>";  
    // Send "List Identity" request to the PLC
    $list_identity_request = "\x63\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00";
	//echo "<br><b><font color=green><br><br>Sent:  </font></b>"; //print_r('\x63\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00'); //echo "<br>";
    socket_write($sock, $list_identity_request, strlen($list_identity_request));
    
	$list_identity_response = socket_read($sock, 1024); //echo "<br><b><font color=blue> Response: </b></font></b>";  
    
	$list_identity_response_hex =bin2hex($list_identity_response);
	$list_identity_response_hex = chunk_split($list_identity_response_hex,2,"\\x"); 
	$list_identity_response_hex = "\\x" . substr($list_identity_response_hex,0,-2); 
	//echo "<br>"; //print_r($list_identity_response_hex);
	
	//$list_identity_response_string =pack("H*",bin2hex($list_identity_response));
	////echo "<br> $list_identity_response_string: "; //print_r($list_identity_response_string);
	////echo "<br>";
	
//echo "<br>"; //echo "<br><b><font color=green>End of function </font></b>"; 	
	return $list_identity_response;
	
	
}

function determine_encapsulation($sock) {
//echo "<br>"; //echo "<br><b><font color=green>function determine_encapsulation(sock)  </font></b>";  
    $determine_encapsulation_request = "\x04\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00";
	$determine_encapsulation_request_hex =bin2hex($determine_encapsulation_request);
	$determine_encapsulation_request_hex = chunk_split($determine_encapsulation_request_hex,2,"\\x"); 
	$determine_encapsulation_request_hex = "\\x" . substr($determine_encapsulation_request_hex,0,-2); 
	//echo "<br>"; //echo "<br><b><font color=green><br><br>Sent:   </font></b>"; 
	//echo "<br>"; //print_r($determine_encapsulation_request_hex);
	//$determine_encapsulation_request_string =pack("H*",bin2hex($determine_encapsulation_request));
	////echo "<br> String: "; //print_r($determine_encapsulation_request_string);
	//echo "<br>";

	
    socket_write($sock, $determine_encapsulation_request, strlen($determine_encapsulation_request)); 
    $determine_encapsulation_response = socket_read($sock, 1024); //echo "<br><b><font color=blue> Response: </b></font></b>"; 
	
	$determine_encapsulation_response_hex =bin2hex($determine_encapsulation_response);
	$determine_encapsulation_response_hex = chunk_split($determine_encapsulation_response_hex,2,"\\x"); 
	$determine_encapsulation_response_hex = "\\x" . substr($determine_encapsulation_response_hex,0,-2); 
	//echo "<br>"; //print_r($determine_encapsulation_response_hex);
	
	//$response2_string =pack("H*",bin2hex($determine_encapsulation_response));
	////echo "<br> String: "; //print_r($response2_string);
	//echo "<br>";
//echo "<br>"; //echo "<br><b><font color=green>End of function </font></b>"; 	
    return $determine_encapsulation_response;
}


function getEipSessionHandle($sock) {
//echo "<br>"; //echo "<br><b><font color=green>function getEipSessionHandle(sock)  </font></b>";  
    // Send "Register session" request to the PLC
    $register_session_request = "\x65\x00\x04\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x01\x00\x00\x00";
	 
	$register_session_request_hex =bin2hex($register_session_request);
	$register_session_request_hex = chunk_split($register_session_request_hex,2,"\\x"); 
	$register_session_request_hex = "\\x" . substr($register_session_request_hex,0,-2); 
	
	//echo "<br><b><font color=green><br><br>Sent: </font></b>";
	//echo "<br> register_session_request_hex: "; //echo "<br>"; //print_r($register_session_request_hex);
	//echo "<br>";
    socket_write($sock, $register_session_request, strlen($register_session_request));
    $register_session_response = socket_read($sock, 1024); //echo "<br><b><font color=blue> Response: </font></b>";



	$register_session_response_hex =bin2hex($register_session_response);
	$register_session_response_chunk_split = chunk_split($register_session_response_hex,2,"\\x"); 
	$register_session_response_chunk_split = "\\x" . substr($register_session_response_chunk_split,0,-2); 
	//echo "<br> register_session_response_hex: "; //print_r($register_session_response_chunk_split);
	
	$register_session_response_string =pack("H*",bin2hex($register_session_response));
	//echo "<br> register_session_response_string: "; //print_r($register_session_response_string);
	//echo "<br>";

    // Extract EIP session handle from response
    $eipSessionHandle = substr($register_session_response, 4, 4);
	
	$eipSessionHandle_hex =bin2hex($eipSessionHandle);
	$eipSessionHandle_hex = chunk_split($eipSessionHandle_hex,2,"\\x"); 
	$eipSessionHandle_hex = "\\x" . substr($eipSessionHandle_hex,0,-2); 
	
	//echo "<br> eipSessionHandle_hex: "; //print_r($eipSessionHandle_hex);
//echo "<br>"; //echo "<br><b><font color=green>End of function </font></b>"; 		
    return $eipSessionHandle;
}

function unregisterSession($sock, $eipSessionHandle) {
    //echo "<br>"; //echo "<br><b><font color=green>function unregisterSession(sock, eipSessionHandle)  </font></b>";  
        // Send "Unregister session" request to the PLC
        $unregister_session_request = "\x66\x00\x04\x00" . $eipSessionHandle . "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x01\x00\x00\x00";
         
        $unregister_session_request_hex =bin2hex($unregister_session_request);
        $unregister_session_request_hex = chunk_split($unregister_session_request_hex,2,"\\x"); 
        $unregister_session_request_hex = "\\x" . substr($unregister_session_request_hex,0,-2); 
        
        //echo "<br><b><font color=green><br><br>Sent: </font></b>";
        //echo "<br> unregister_session_request_hex: "; //echo "<br>"; //print_r($unregister_session_request_hex);
        //echo "<br>";
        socket_write($sock, $unregister_session_request, strlen($unregister_session_request));
        
    }

function disconnectFromMessageRouter($sock, $eipSessionHandle , $port, $slot) {
//echo "<br>"; //echo "<br><b><font color=green>function disconnectFromMessageRouter(sock, eipSessionHandle , port, slot)  </font></b>";  
		
	$eipSessionHandle_hex =bin2hex($eipSessionHandle);
	$eipSessionHandle_hex = chunk_split($eipSessionHandle_hex,2,"\\x"); 
	$eipSessionHandle_hex = "\\x" . substr($eipSessionHandle_hex,0,-2); 
	
	//echo "<br> eipSessionHandle_hex: "; //print_r($eipSessionHandle_hex);
	
	//echo "<br>";
	
	 
	 $connnectionSerialNumber_hex = sprintf('%04x', $connnectionSerialNumber);  //echo "<br>  connnectionSerialNumber_hex: "; //print_r($connnectionSerialNumber_hex);  
	 $connnectionSerialNumber_hex = pack('H*', $connnectionSerialNumber_hex);	
		
		
     $OSerialNumber_hex = sprintf('%08x', $OSerialNumber);  //echo "<br>  OSerialNumber_hex: "; //print_r($OSerialNumber_hex);
	 $OSerialNumber_hex = pack('H*', $OSerialNumber_hex);
	 
	
	$port_hex = sprintf('%02x', $port); //echo "<br>  port_hex: "; //print_r($port_hex);
	$port_hex =  pack('H*', $port_hex); 
	
	
	
	$slot_hex = sprintf('%02x', $slot); //echo "<br>  slot_hex: "; //print_r($slot_hex);
	$slot_hex =  pack('H*', $slot_hex); 

	   
	 
    //echo "<br>"; //echo "<br><b><font color=green>Disconnect from message router request to the PLC</font></b>"; 
	// Send "Disconnect From Message Router" request to the PLC
    $disconnectFromMessageRouter_request = "\x6F\x00\x28\x00" . $eipSessionHandle . "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x02\x00\x00\x00\x00\x00\xB2\x00\x18\x00\x4E\x02\x20\x06\x24\x01\x0A\x05" . $connnectionSerialNumber_hex . "\xDD\xBA" . $OSerialNumber_hex . "\x03\x00" . $port_hex .  $slot_hex . "\x20\x02\x24\x01";
	
	
	
	$disconnectFromMessageRouter_request_hex =bin2hex($disconnectFromMessageRouter_request);
	$disconnectFromMessageRouter_request_hex = chunk_split($disconnectFromMessageRouter_request_hex,2,"\\x"); 
	$disconnectFromMessageRouter_request_hex = "\\x" . substr($disconnectFromMessageRouter_request_hex,0,-2); 
	
	//echo "<br><b><font color=green><br><br>Sent: </font></b>";
	//echo "<br><br> disconnectFromMessageRouter_request_hex: ";
	//print_r($disconnectFromMessageRouter_request_hex);
	//echo "<br>";

	socket_write($sock, $disconnectFromMessageRouter_request, strlen($disconnectFromMessageRouter_request));
    
	$disconnectFromMessageRouter_response = socket_read($sock, 1024); //echo "<br><b><font color=blue> Response: </font></b>"; 

	$disconnectFromMessageRouter_response_hex =bin2hex($disconnectFromMessageRouter_response);
	
	$disconnectFromMessageRouter_response_chunk_split = chunk_split($disconnectFromMessageRouter_response_hex,2,"\\x"); 
	$disconnectFromMessageRouter_response_chunk_split = "\\x" . substr($disconnectFromMessageRouter_response_chunk_split,0,-2); 
	//echo "<br> disconnectFromMessageRouter_response_hex: "; //print_r($disconnectFromMessageRouter_response_chunk_split);

    $ConnectionSerialNumber = 0;
    $OSerialNumber = 0;

    // Extract General Status from response
  	
	$generalstatus = unpack('V',$disconnectFromMessageRouter_response, 42, 1);
	
	
    if ($generalstatus == 0) {
        $connected = true;
		
		//echo "<br><br> General Status: Not Connected?  ";
		
    }
    else { $connected = false; 
	    //echo "<br><br> General Status: Connected?  ";
		
	}

//echo "<br>"; //echo "<br><b><font color=green>End of function disconnectFromMessageRouter</font></b>"; 
    return $connected;
}


function connectToMessageRouter($sock, $eipSessionHandle , $port, $slot){
//echo "<br><b><font color=green> function connectToMessageRouter(sock, eipSessionHandle , port, slot)</font></b><br>"; 

	
    $connected = disconnectFromMessageRouter($sock, $eipSessionHandle , $port, $slot);
 


//echo "<br>"; //echo "<br><b><font color=green>Connect To Message Router request to the PLC</font></b>"; 	

     $connnectionSerialNumber = mt_rand(0, 65535);  
	 
	 $connnectionSerialNumber_hex = sprintf('%04x', $connnectionSerialNumber);  //echo "<br><b>  connnectionSerialNumber_hex: "; //print_r($connnectionSerialNumber_hex);  
	 $connnectionSerialNumber_hex = pack('H*', $connnectionSerialNumber_hex);	
	 
	       
		
     $OSerialNumber = (mt_rand(0, 0xffff) << 16) | mt_rand(0, 0xffff);         //random number
     $OSerialNumber_hex = sprintf('%08x', $OSerialNumber);  //echo "<br>  OSerialNumber_hex: "; //print_r($OSerialNumber_hex);
	 $OSerialNumber_hex = pack('H*', $OSerialNumber_hex);
	 
	
	$port_hex = sprintf('%02x', $port);
	$port_hex =  pack('H*', $port_hex);
	
	
	
	$slot_hex = sprintf('%02x', $slot);
	$slot_hex =  pack('H*', $slot_hex);

	  
	
	 $TtoONetworkConnectionID = (mt_rand(0, 0xffff) << 16) | mt_rand(0, 0xffff);    //random number
	
	 $TtoONetworkConnectionID_hex = sprintf('%08x', $TtoONetworkConnectionID);  //echo "<br>  TtoONetworkConnectionID_hex: "; //print_r($TtoONetworkConnectionID_hex);
	 $TtoONetworkConnectionID_hex = pack('H*', $TtoONetworkConnectionID_hex);
	 
	 
     
    
	$connectToMessageRouter_request = "\x6F\x00\x40\x00" . $eipSessionHandle . "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x02\x00\x00\x00\x00\x00\xB2\x00\x30\x00\x54\x02\x20\x06\x24\x01\x0A\x05\x00\x00\x00\x00" . $TtoONetworkConnectionID_hex .  $connnectionSerialNumber_hex . "\xDD\xBA" . $OSerialNumber_hex . "\x01\x00\x00\x00\x80\x84\x1E\x00\xF8\x43\x80\x84\x1E\x00\xF8\x43\xA3\x03" . $port_hex . $slot_hex . "\x20\x02\x24\x01";
	
	//echo "<br>";
	
	$connectToMessageRouter_request_hex =bin2hex($connectToMessageRouter_request);
	$connectToMessageRouter_request_chunk_split = chunk_split($connectToMessageRouter_request_hex,2,"\\x"); 
	$connectToMessageRouter_request_chunk_split = "\\x" . substr($connectToMessageRouter_request_chunk_split,0,-2); 
	
	//echo "<br><b><font color=green><br><br>Sent:  </font></b>"; 
	//echo "<br> ConnectToMessageRouter_request_hex: ";
	
	//print_r($connectToMessageRouter_request_chunk_split);
	//echo "<br>";
	
	
    socket_write($sock, $connectToMessageRouter_request, strlen($connectToMessageRouter_request));
    $connectToMessageRouter_response = socket_read($sock, 1024); //echo "<br><b><font color=blue> Response: </font></b>"; 


	$connectToMessageRouter_response_hex =bin2hex($connectToMessageRouter_response);
	$connectToMessageRouter_response_chunk_split = chunk_split($connectToMessageRouter_response_hex,2,"\\x"); 
	$connectToMessageRouter_response_chunk_split = "\\x" . substr($connectToMessageRouter_response_chunk_split,0,-2); 
	//echo "<br> connectToMessageRouter_response: "; //print_r($connectToMessageRouter_response_chunk_split);
	
	

    //$generalstatus = ord($connectToMessageRouter_response_hex[42]);
	
	$generalstatus = unpack('V',$connectToMessageRouter_response, 42, 1);
	
    // Extract Connection ID from response
    $cipConnectionID = substr($connectToMessageRouter_response, 44, 4);
	$cipConnectionID_hex =bin2hex($cipConnectionID);
	$cipConnectionID_chunk_split = chunk_split($cipConnectionID_hex,2,"\\x"); 
	$cipConnectionID_chunk_split = "\\x" . substr($cipConnectionID_chunk_split,0,-2); 
	//echo "<br><br> cipConnectionID: "; //print_r($cipConnectionID_chunk_split);


//echo "<br>"; //echo "<br><b><font color=green>End of function connectToMessageRouter</font></b>"; 
    if ($connected == true){
        return $cipConnectionID;
    }
    else{ return -1;}
}




function PlcRead($sock, $eipSessionHandle, $cipConnectionID, $tagname){
//echo "<br><b><font color=green>function PlcRead(sock, eipSessionHandle, cipConnectionID, tagname) </font></b><br> ";



        $tagNameLength = strlen($tagname);
			
		$tagNameLength_hex = sprintf('%02x', $tagNameLength);  //echo "<br>  tagNameLength_hex: "; //print_r($tagNameLength_hex);
		$tagNameLength_hex = pack('H*', $tagNameLength_hex);
		
		// Convert the $tagname string to an array of hex values 
		$tagname_hex_array = unpack('H*', $tagname); 
		// Implode the array to create a hex string 
		$packedTagName_hex = implode('', $tagname_hex_array); 	//echo "<br> packedTagName_hex: "; //print_r($packedTagName_hex);
		if ($tagNameLength % 2 == 0) {
            // The length is even
            $packedTagName_hex = pack('H*', $packedTagName_hex);
        } else {
            // The length is odd
            $packedTagName_hex .= '00';
            $packedTagName_hex = pack('H*', $packedTagName_hex);
        }
        
		
		//echo "<br>";
		
		$SequenceNumber = mt_rand(0, 65535);       //echo "<br>  SequenceNumber: "; //print_r($SequenceNumber);   //random number
		$SequenceNumber_hex = sprintf('%02x', $SequenceNumber);  //echo "<br>  SequenceNumber_hex: "; //print_r($SequenceNumber_hex);
		$SequenceNumber_hex = pack('H*', $SequenceNumber_hex);
		
		
		$requestPathSize = (2 + $tagNameLength + ($tagNameLength % 2)) / 2;
		//echo "<br> requestPathSize: ", $requestPathSize;
		$requestPathSize_hex = sprintf('%02x', $requestPathSize);  //echo "<br>  requestPathSize_hex: "; //print_r($requestPathSize_hex);
		$requestPathSize_hex = pack('H*', $requestPathSize_hex);
		
		$subMessageLength = (2 + $tagNameLength + ($tagNameLength % 2)) + 6;  // Length of B1 messsage (ie. byte position [42])
		//echo "<br> submessageLength: ", $subMessageLength;
		$subMessageLength_hex = sprintf('%04x', $subMessageLength);  //echo "<br>  subMessageLength_hex: "; //print_r($subMessageLength_hex);
		$subMessageLength_hex = pack('H*', $subMessageLength_hex);
        $subMessageLength_hex = strrev($subMessageLength_hex);
		
        if ($tagNameLength % 2 == 0) {
            // The length is even
            $messageLength = $tagNameLength  + 28;  
		    //echo "<br> messageLength: ", $messageLength;
		    $messageLength_hex = sprintf('%04x', $messageLength);  //echo "<br>  messageLength_hex: "; //print_r($messageLength_hex);
		    $messageLength_hex = pack('H*', $messageLength_hex);
            $messageLength_hex = strrev($messageLength_hex);
        } else {
            // The length is odd
            $messageLength = $tagNameLength + 29;  
		    //echo "<br> messageLength: ", $messageLength;
		    $messageLength_hex = sprintf('%04x', $messageLength);  //echo "<br>  messageLength_hex: "; //print_r($messageLength_hex);
		    $messageLength_hex = pack('H*', $messageLength_hex);
            $messageLength_hex = strrev($messageLength_hex);
        }
        

	    //echo "<br><b><font color=green><br><br>Sent:  </font></b>"; 
        $PlcRead_request = "\x70\x00" . $messageLength_hex . $eipSessionHandle . "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x02\x00\xA1\x00\x04\x00" . $cipConnectionID . "\xB1\x00" . $subMessageLength_hex . $SequenceNumber_hex .  "\x4C" . $requestPathSize_hex. "\x91" . $tagNameLength_hex . $packedTagName_hex . "\x01\x00";
        
        
        $PlcRead_request_hex =bin2hex($PlcRead_request);
        $PlcRead_request_hex = chunk_split($PlcRead_request_hex,2,"\\x"); 
        $PlcRead_request_hex = "\\x" . substr($PlcRead_request_hex,0,-2); 
        //echo "<br><br>PlcRead_request_hex: "; //print_r($PlcRead_request_hex);
	
	
	socket_write($sock, $PlcRead_request, strlen($PlcRead_request));        
	$PlcRead_response = socket_read($sock, 1024); //echo "<br><br><b><font color=blue> Response: </font></b>"; 

	$PlcRead_response_hex =bin2hex($PlcRead_response);
	$PlcRead_response_chunk_split = chunk_split($PlcRead_response_hex,2,"\\x"); 
	$PlcRead_response_chunk_split = "\\x" . substr($PlcRead_response_chunk_split,0,-2); 
	//echo "<br>PlcRead_response_hex: "; //print_r($PlcRead_response_chunk_split);
	
	$tagValue = unpack('V',substr($PlcRead_response, 52, 4)); 
	//echo "<br><br>tagValue: "; //print_r($tagValue[1]);
	//echo "<br>";
//echo "<br>"; //echo "<br><b><font color=green>End of function </font></b>"; 
    return $tagValue[1];
    
}  // end of function

?>