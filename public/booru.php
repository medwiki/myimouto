<?php

$address = $_SERVER['REMOTE_ADDR'];
$referer = $_SERVER['HTTP_REFERER'];
$browser = $_SERVER['HTTP_USER_AGENT'];

$file = fopen("log.html",  "a");  

$time = date("H:i:s dS F");
fwrite( $file, "<b>Time:</b> $time<br>" );

if ($HTTP_SERVER_VARS["HTTP_X_FORWARDED_FOR"]) 
{ 
$ip = $HTTP_SERVER_VARS["HTTP_X_FORWARDED_FOR"]; 
} 
elseif ($HTTP_SERVER_VARS["HTTP_CLIENT_IP"]) 
{ 
$ip = $HTTP_SERVER_VARS["HTTP_CLIENT_IP"]; 
}
elseif ($HTTP_SERVER_VARS["REMOTE_ADDR"]) 
{ 
$ip = $HTTP_SERVER_VARS["REMOTE_ADDR"]; 
} 
elseif (getenv("HTTP_X_FORWARDED_FOR")) 
{ 
$ip = getenv("HTTP_X_FORWARDED_FOR"); 
} 
elseif (getenv("HTTP_CLIENT_IP")) 
{ 
$ip = getenv("HTTP_CLIENT_IP"); 
} 
elseif (getenv("REMOTE_ADDR"))
{ 
$ip = getenv("REMOTE_ADDR"); 
}
{
  fwrite( $file, "<b>IP Address:</b> $ip <br>");
}

if( $referer != null)
{
  fwrite( $file, "<b>Referer:</b> $referer<br>");  
}

fwrite( $file, "<b>Browser:</b> $browser<br/><hr>");  

fclose($file);

?>
