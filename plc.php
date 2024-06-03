<?PHP

class PLC {
    private $ipAddress;
    private $port;
    private $slot;
    private $sock;
    private $eipSessionHandle;
    private $cipConnectionID;  
    private $connnectionSerialNumber = 0;
    private $OSerialNumber = 0;

    public function __construct($ipAddress, $port, $slot) {
        $this->ipAddress = $ipAddress;
        $this->port = $port;
        $this->slot = $slot;

        $this->sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($this->sock === false) {
            trigger_error("Error creating socket: " . socket_strerror(socket_last_error()));
        }

        $timeout = array('sec'=>10, 'usec'=>0);
        socket_set_option($this->sock, SOL_SOCKET, SO_SNDTIMEO, $timeout);
        socket_set_option($this->sock, SOL_SOCKET, SO_RCVTIMEO, $timeout);

        $result = socket_connect($this->sock, $this->ipAddress, 44818);
        if ($result === false) {
            trigger_error("Error connecting to PLC: " . socket_strerror(socket_last_error()));
        }

        $this->send_list_identity_request();
        $this->determine_encapsulation();
        $this->registerSession();
        if (!$this->connectToMessageRouter()){
            echo("Not Connected");
        }
    }

    
    public function Disconnect() {
        $connected = disconnectFromMessageRouter($this->sock, $this->eipSessionHandle , $this->port, $this->slot);
        unregisterSession($this->sock, $this->eipSessionHandle);

        $actionSuccessful = someActionThatRequiresSessionHandle($this->sock, $this->eipSessionHandle);

        if (!$actionSuccessful) {
            echo "Unregistration of session handle likely successful";
        } else {
            echo "Unregistration of session handle may not have been successful";
        }

        socket_close($this->sock);
    }

    public function ReadDint($tagname)
    {
        $tagNameLength = strlen($tagname);
        $tagNameLength_hex = sprintf('%02x', $tagNameLength); 
        $tagNameLength_hex = pack('H*', $tagNameLength_hex);
        
        $tagname_hex_array = unpack('H*', $tagname); 
        $packedTagName_hex = implode('', $tagname_hex_array);
        if ($tagNameLength % 2 == 0) {
            $packedTagName_hex = pack('H*', $packedTagName_hex);
        } 
        else {
            $packedTagName_hex .= '00';
            $packedTagName_hex = pack('H*', $packedTagName_hex);
        }
        
        $SequenceNumber = mt_rand(0, 65535);  
        $SequenceNumber_hex = sprintf('%02x', $SequenceNumber); 
        $SequenceNumber_hex = pack('H*', $SequenceNumber_hex);
        
        $requestPathSize = (2 + $tagNameLength + ($tagNameLength % 2)) / 2;
        $requestPathSize_hex = sprintf('%02x', $requestPathSize); 
        $requestPathSize_hex = pack('H*', $requestPathSize_hex);
        
        $subMessageLength = (2 + $tagNameLength + ($tagNameLength % 2)) + 6;
        $subMessageLength_hex = sprintf('%04x', $subMessageLength); 
        $subMessageLength_hex = pack('H*', $subMessageLength_hex);
        $subMessageLength_hex = strrev($subMessageLength_hex);
        
        if ($tagNameLength % 2 == 0) {
            $messageLength = $tagNameLength  + 28;  
            $messageLength_hex = sprintf('%04x', $messageLength); 
            $messageLength_hex = pack('H*', $messageLength_hex);
            $messageLength_hex = strrev($messageLength_hex);
        } 
        else {
            $messageLength = $tagNameLength + 29;  
            $messageLength_hex = sprintf('%04x', $messageLength); 
            $messageLength_hex = pack('H*', $messageLength_hex);
            $messageLength_hex = strrev($messageLength_hex);
        }
        
        $request = "\x70\x00" . $messageLength_hex . $this->eipSessionHandle . "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x02\x00\xA1\x00\x04\x00" . $this->cipConnectionID . "\xB1\x00" . $subMessageLength_hex . $SequenceNumber_hex .  "\x4C" . $requestPathSize_hex. "\x91" . $tagNameLength_hex . $packedTagName_hex . "\x01\x00";
        
        socket_write($this->sock, $request, strlen($request));        
        $response = socket_read($this->sock, 1024);

        $this->displayBuffer($request);
        $this->displayBuffer($response);

        $tagValue = unpack('V',substr($response, 52, 4)); 
        return $tagValue[1];
    }

    public function ReadDintArray($tagname, $arrayLength)
    {
        $tagNameLength = strlen($tagname);
        $tagNameLength_hex = sprintf('%02x', $tagNameLength); 
        $tagNameLength_hex = pack('H*', $tagNameLength_hex);
        
        $tagname_hex_array = unpack('H*', $tagname); 
        $packedTagName_hex = implode('', $tagname_hex_array);
        if ($tagNameLength % 2 == 0) {
            $packedTagName_hex = pack('H*', $packedTagName_hex);
        } 
        else {
            $packedTagName_hex .= '00';
            $packedTagName_hex = pack('H*', $packedTagName_hex);
        }
        
        $SequenceNumber = mt_rand(0, 65535);  
        $SequenceNumber_hex = sprintf('%02x', $SequenceNumber); 
        $SequenceNumber_hex = pack('H*', $SequenceNumber_hex);
        
        $requestPathSize = (2 + $tagNameLength + ($tagNameLength % 2)) / 2;
        $requestPathSize_hex = sprintf('%02x', $requestPathSize); 
        $requestPathSize_hex = pack('H*', $requestPathSize_hex);
        
        $subMessageLength = (6 + $tagNameLength + ($tagNameLength % 2)) + 6;
        $subMessageLength_hex = sprintf('%04x', $subMessageLength); 
        $subMessageLength_hex = pack('H*', $subMessageLength_hex);
        $subMessageLength_hex = strrev($subMessageLength_hex);
        
        if ($tagNameLength % 2 == 0) {
            $messageLength = $tagNameLength  + 28;  
            $messageLength_hex = sprintf('%04x', $messageLength); 
            $messageLength_hex = pack('H*', $messageLength_hex);
            $messageLength_hex = strrev($messageLength_hex);
        }
        else {
            $messageLength = $tagNameLength + 29;
            $messageLength_hex = sprintf('%04x', $messageLength);
            $messageLength_hex = pack('H*', $messageLength_hex);
            $messageLength_hex = strrev($messageLength_hex);
        }

        $arrayLength_hex = sprintf('%04x', $arrayLength + 4);
        $arrayLength_hex = pack('H*', $arrayLength_hex);
        $arrayLength_hex = strrev($arrayLength_hex);
        
        $request = "\x70\x00" . $messageLength_hex . $this->eipSessionHandle . "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x02\x00\xA1\x00\x04\x00" . $this->cipConnectionID . "\xB1\x00" . $subMessageLength_hex . $SequenceNumber_hex .  "\x52" . $requestPathSize_hex. "\x91" . $tagNameLength_hex . $packedTagName_hex . $arrayLength_hex . "\x00\x00\x00\x00";
                    \x70\x00        \x2c\x00           \x1c\x0d\x6d\x00         \x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x02\x00\xa1\x00\x04\x00       \x0e\x76\x8d\xff       \xb1\x00         \x1c\x00                \x21\x08           \x52        \x09\x91\x0f\x54\x65\x73\x74\x5f\x64\x69\x6e\x74\x5f\x61\x72\x72\x61\x79\x00\x0e\x00\x00\x00\x00\x00

        socket_write($this->sock, $request, strlen($request));
        $response = socket_read($this->sock, 1024);

        $this->displayBuffer($request);
        $this->displayBuffer($response);

        // Loop to unpack each 4-byte value
        $tagValues = [];
        for ($i = 0; $i < $arrayLength; $i++) {
            $tagValue = unpack('V', substr($response, 52 + ($i * 4), 4)); // Unpack each 4-byte value
            $tagValues[] = $tagValue[1]; // Add the value to the array
            }

        return $tagValues; // Return the array of tag values
    }

    public function ReadBool($tagname){
        // add code...
    }

    public function ReadBoolArray($tagname, $arrayLength){
        // add code...
    }

    public function ReadReal($tagname){
        // add code...
    }

    public function ReadRealArray($tagname, $arrayLength){
        // add code...
    }


    private function send_list_identity_request() 
    {
        $request = "\x63\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00";
        socket_write($this->sock, $request, strlen($request));
        $response = socket_read($this->sock, 1024); 
        
        $this->displayBuffer($request);
        $this->displayBuffer($response);
    }

  
    private function determine_encapsulation() 
    {
        $request = "\x04\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00";        
        socket_write($this->sock, $request, strlen($request)); 
        $response = socket_read($this->sock, 1024);

        $this->displayBuffer($request);
        $this->displayBuffer($response);
    }
        
        
    private function registerSession() 
    {
        $request = "\x65\x00\x04\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x01\x00\x00\x00";        
        socket_write($this->sock, $request, strlen($request));
        $response = socket_read($this->sock, 1024);
        $this->eipSessionHandle = substr($response, 4, 4);

        $this->displayBuffer($request);
        $this->displayBuffer($response);
    }
        
    private function unregisterSession() 
    {
        $request = "\x66\x00\x00\x00" . $this->eipSessionHandle . "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00";
        socket_write($this->sock, $request, strlen($request));
        
        $this->displayBuffer($request);
    }
        
    private function disconnectFromMessageRouter() 
    {
        $connnectionSerialNumber_hex = sprintf('%04x', $this->connnectionSerialNumber); 
        $connnectionSerialNumber_hex = pack('H*', $connnectionSerialNumber_hex);
            
        $OSerialNumber_hex = sprintf('%08x', $this->OSerialNumber);
        $OSerialNumber_hex = pack('H*', $OSerialNumber_hex);
        
        $port_hex = sprintf('%02x', $this->port);
        $port_hex =  pack('H*', $port_hex); 
        
        $slot_hex = sprintf('%02x', $this->slot); 
        $slot_hex =  pack('H*', $slot_hex); 
    
        $request = "\x6F\x00\x28\x00" . $this->eipSessionHandle . "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x02\x00\x00\x00\x00\x00\xB2\x00\x18\x00\x4E\x02\x20\x06\x24\x01\x0A\x05" . $connnectionSerialNumber_hex . "\xDD\xBA" . $OSerialNumber_hex . "\x03\x00" . $port_hex .  $slot_hex . "\x20\x02\x24\x01";
        
        socket_write($this->sock, $request, strlen($request));
        $response = socket_read($this->sock, 1024);
    
        $this->ConnectionSerialNumber = 0;
        $this->OSerialNumber = 0;
            
        $generalstatus = unpack('V',$response, 42, 1);
        if ($generalstatus == 0) 
        {
            $connected = true;
        }
        else 
        { 
            $connected = false; 
        }

        $this->displayBuffer($request);
        $this->displayBuffer($response);

        return $connected;
    }
        
        
    private function connectToMessageRouter()
    {
        $connected = $this->disconnectFromMessageRouter();

        $this->connnectionSerialNumber = mt_rand(0, 65535);  
        $connnectionSerialNumber_hex = sprintf('%04x', $this->connnectionSerialNumber); 
        $connnectionSerialNumber_hex = pack('H*', $connnectionSerialNumber_hex);	

        $this->OSerialNumber = (mt_rand(0, 0xffff) << 16) | mt_rand(0, 0xffff); 
        $OSerialNumber_hex = sprintf('%08x', $this->OSerialNumber);
        $OSerialNumber_hex = pack('H*', $OSerialNumber_hex);
            
        $port_hex = sprintf('%02x', $this->port);
        $port_hex =  pack('H*', $port_hex);
        
        $slot_hex = sprintf('%02x', $this->slot);
        $slot_hex =  pack('H*', $slot_hex);
    
        $TtoONetworkConnectionID = (mt_rand(0, 0xffff) << 16) | mt_rand(0, 0xffff);
        $TtoONetworkConnectionID_hex = sprintf('%08x', $TtoONetworkConnectionID);
        $TtoONetworkConnectionID_hex = pack('H*', $TtoONetworkConnectionID_hex);
            
        $request = "\x6F\x00\x40\x00" . $this->eipSessionHandle . "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x02\x00\x00\x00\x00\x00\xB2\x00\x30\x00\x54\x02\x20\x06\x24\x01\x0A\x05\x00\x00\x00\x00" . $TtoONetworkConnectionID_hex .  $connnectionSerialNumber_hex . "\xDD\xBA" . $OSerialNumber_hex . "\x01\x00\x00\x00\x80\x84\x1E\x00\xF8\x43\x80\x84\x1E\x00\xF8\x43\xA3\x03" . $port_hex . $slot_hex . "\x20\x02\x24\x01";
       
        socket_write($this->sock, $request, strlen($request));
        $response = socket_read($this->sock, 1024);
        
        $generalstatus = unpack('V',$response, 42, 1);
        if ($connected == true){
            $this->cipConnectionID = substr($response, 44, 4);
            $connected = true;
        }
        else { 
            $connected = false;
        }

        $this->displayBuffer($request);
        $this->displayBuffer($response);

        return $connected;
    }

    private function displayBuffer($message){

        $message_hex =bin2hex($message);
        $message_hex = chunk_split($message_hex,2,"\\x"); 
        $message_hex = "\\x" . substr($message_hex,0,-2); 
        echo "<br>Message:  "; print_r($message_hex);
    }


}
?>