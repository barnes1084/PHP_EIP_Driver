<?PHP
 include "./function_read_plc_dint.inc";
 
$email_to = "earaya@goodyear.com";
$email_subject = "PLC read scheduled task";
$error_message = "Error with script";
$email_headers = "From:earaya@goodyear.com";
$port =1;
   
 //Read tag names from database and genarate the arrays
 
// Connect to the database
$database = "laa6ss01";
include "../../inc/db_connect.inc";
	

$sql=
	" SELECT tag_name, ip, slot, datatype, arraylength ".
	"  FROM plc_tag_values ";

if(!($stmt = ociparse($conn, $sql))) {  $err = ocierror($conn);  die($err['message']);}
if(!ociexecute($stmt)) { $err = ocierror($stmt);  die($err['message']);}
$R =0;	

$previousIP = null;
$plc = null;

while (($row = oci_fetch_array($stmt, OCI_ASSOC)) != false) {
	$tag_name = $row['TAG_NAME'];
	$ip = $row['IP'];
	$slot = $row['SLOT'];
	$datatype = $row['DATATYPE'];
	$arraylength = $row['ARRAYLENGTH'];

	if ($previousIP !== $ip) {
        if ($plc !== null) {
            $plc->Disconnect();
        }
        $plc = new PLC($ip, $port, $slot);
        $previousIP = $ip;
    }

	switch($datatype){
	case "DINT": 
		$value = $plc->ReadDint($tag_name);
		break;
	case "DINTARRAY": 
		print_r("ReadDintArray" + $datatype);
		$value = $plc->ReadDintArray($tag_name, $arraylength);
		break;
	case "BOOL": 
		$value = $plc->ReadDint($tag_name);
		break;
	case "BOOLARRAY": 
		$value = $plc->ReadDintArray($tag_name, $arraylength);
		break;
	case "REAL": 
		$value = $plc->ReadDint($tag_name);
		break;
	case "REALARRAY": 
		$value = $plc->ReadDintArray($tag_name, $arraylength);
		break;
	}
	
	$value = $plc->ReadDint($tag_name);
    echo "<br> Value ", $value;

	 $updateQuery = "UPDATE plc_tag_values SET value = :value WHERE tag_name = :tag_name AND ip = :ip AND slot = :slot AND datatype = :datatype AND arraylength = :arraylength"; 
	 $updateStid = oci_parse($conn, $updateQuery); 
	 oci_bind_by_name($updateStid, ':value', $value); 
	 oci_bind_by_name($updateStid, ':tag_name', $tag_name); 
	 oci_bind_by_name($updateStid, ':ip', $ip); 
	 oci_bind_by_name($updateStid, ':slot', $slot); 
	 oci_bind_by_name($updateStid, ':datatype', $datatype); 
	 oci_bind_by_name($updateStid, ':arraylength', $arraylength); 

	if (!oci_execute($updateStid)) { 
		$error = oci_error($updateStid); 
		$error_message = "UPDATE query error: " . $error['message']; 
		mail($email_to, $email_subject, $error_message, $email_headers); 
		die($error_message); 
	}
}

if ($plc !== null) {
    $plc->Disconnect();
}

// Free the statement resources 
	oci_free_statement($stid); 
	oci_free_statement($updateStid); 
// Close the Oracle connection 
	oci_close($conn);

?>
