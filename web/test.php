<html xmlns='http://www.w3.org/1999/xhtml' xml:lang='en'>
<head>
<?php

// Connect to database server
$handle = mysql_connect( "localhost", "templogger", "templogger" )
or die("Unable to connect");

// Select temperature database
mysql_select_db( "temperature", $handle )
    or die("Unable to select database");

// Grab data
$res = mysql_query("select * from log where date_and_time > '2014-03-09 20:56:00'" );

// Put data into arrays
$datetime = array();
$indoor_temp = array();
$outdoor_temp = array();

if ($res) 
{
    while($row = mysql_fetch_array($res)) 
    {
        $datetime[] = $row[0];
        $indoor_temp[] = $row[1];
        $outdoor_temp[] = $row[2];
    }
}
else 
{
    echo mysql_error();
}



// Close connection
mysql_close( $handle );

$dateList = implode(',',$datetime);
echo "<html>",$dateList,"</html>";


?>
</head>




