<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
    <link rel="stylesheet" type="text/css" media="all" href="jsDatePick_ltr.min.css" />
	<link rel='stylesheet' type='text/css' href='css/main.css' media='screen' />
    <script type="text/javascript" src="jquery.1.4.2.js"></script>
    <script type="text/javascript" src="jsDatePick.jquery.min.1.3.js"></script>
	<script type="text/javascript">
	  window.onload = function(){		
		g_globalObject = new JsDatePick({
			useMode:1,
			isStripped:true,
			target:"calendar",
			cellColorScheme:"aqua",	
			limitToToday:true,			
		});
		g_globalObject.setOnSelectedDelegate(function(){
			var obj = g_globalObject.getSelectedDay();
			datestring = obj.year + "-" + ('0' + obj.month).slice(-2) + "-" + ('0' + obj.day).slice(-2);
			open('<?php echo $_SERVER['PHP_SELF'];?>?date='+datestring,'_self');
		});
	  };
    </script>
	
<?php
    
    // Location of data
    $datahome = "/home/pi/xbee/data/";
    
    // Time zone
    date_default_timezone_set('America/New_York');
    
    // Default to today
    $temptimestamp = mktime(0,0,0,date("m",time()),date("d",time()),date("Y",time()));
    
    // Check if date is provided in URL
    if(isset($_GET["date"])) 
	{
        // A date has been given in the URL
        $temptimestamp = mktime(0,0,0,substr($_GET["date"],5,2),substr($_GET["date"],8,2),substr($_GET["date"],0,4));
	}
    
    // Construct filename from time stamp
    $filename = $datahome."/".date("Y",$temptimestamp)."/".date("ymd",$temptimestamp).".csv";
        
    // For converting watts to watthr
    $C = 1.0 / 3600.0;
    
    // Check if this file exists
    $data_found = FALSE;
    if(file_exists($filename))
    {
        $data_found = TRUE;
        
        // Init arrays
        $watts_vs_time  = array();
        $watthr_vs_time = array();
        $t0             = 0;
        $dt             = 0;
        $max_watts      = -100;
        
        // Initialize watthr to 0 for the day
        $watthr = 0;
        
        // Open file
        if (($handle = fopen($filename, "r")) !== FALSE) 
        {
            $row = 0;
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE)
            {
                // Skip the header row
                if( $row == 0 )
                {
                    $row += 1;
                    continue;
                 }
                
                // Entries are time_string, unix_time (GMT), amperes, watts
                $time_string = $data[0];
                
                // Time string format is yyyy-mm-ddTHH:MM:SS in local time zone
                // Need to convert back to unix_time in this time zone.
                $unix_time_local = mktime(substr($time_string,11,2), 
                                          substr($time_string,14,2),
                                          substr($time_string,17,2),
                                          substr($time_string,5,2),
                                          substr($time_string,8,2),
                                          substr($time_string,0,4));
                
                // Flotr expects time stamps in milliseconds
                $unix_time_local = $unix_time_local."000";
                
                $watts = $data[3];
                if( $row == 1 )
                {
                    $watthr = 0;
                    $t0 = $data[1];
                }
                else
                {
                    $dt = $data[1] - $t0;
                    // If too much time has passed since reading, don't trust results
                    if( $dt < 3600 )
                    {
                        $watthr += $watts * $dt * $C;
                    }
                    $t0 = $data[1];
                }
                                          
                // Put results in an array which will be plotted
                $watts_vs_time[]  = '['.$unix_time_local.','.$watts.']';
                $watthr_vs_time[] = '['.$unix_time_local.','.$watthr.']';
                $max_watts        = max($max_watts,$watts);
                
                // Increase row count by 1
                $row += 1;
                
            }
        }
    }
    else
    {
        echo "File Not Found ".$filename;
    }
        
    $html =  
'    <title> Power monitoring - '.date("m/d/Y",$temptimestamp).'</title>
  </head>
  <body>
    <div id ="container">
      <div id="calendar"></div>
      <h1>Power monitoroing - '.date("m/d/Y",$temptimestamp).'</h1>
      
      
    ';
    if($max_watts == -100 )
    {
        $html .= '        No data found';
    }
    else
    {
        $html .=  '        <table align="center">';
	$html .=  '          <tr><td>Max power</td><td align="right">'.number_format($max_watts,1).'W</td></tr>';
	$html .=  '          <tr><td>Total intake</td><td align="right">'.number_format($watthr,1).'Wh</td></tr>';
	$html .=  '        </table>';
    }
    $html .=
'   </div> </div>
    <div id="chart1"></div>';
    echo $html;
 ?>   
<script type="text/javascript" src="flotr2.js"></script>
<script type="text/javascript">
function changeImage(ImageID,ImageName)
        {
            document.getElementById(ImageID).src = ImageName+".png";
            document.getElementById(ImageID).alt = ImageName;
        }
  (function () {
    var
    power    = [<?php echo implode(',',$watts_vs_time) ?>],
	energy   = [<?php echo implode(',',$watthr_vs_time) ?>],
	options,
    graph,
	i, x, o;

   options = {
	  colors: ['#CB4B4B','#00A8F0'],
      grid : {
        verticalLines : false,
		outline : 'sw'
      },
      xaxis : {
        mode : 'time', 
        timeMode:'local',
        twelveHourClock: true,
        timeformat: "%I:%M%p",
        labelsAngle : 45,
        noTicks: 10,
		title: 'Time',
      },
      yaxis: {
        showLabels: true, 
		title: 'W',
      },
      y2axis: {
          title: 'Wh'
      },
      selection : {
        mode : 'x'
      },
	mouse : { 
	  track : true,
	  relative : true ,
	  //position: "ne",
	  trackFormatter: function(o){
        var t = parseInt(o.x);
        var myDate = new Date(t);
		//<![CDATA[
        var string = myDate.getHours() + ":" + (myDate.getMinutes() <10 ?'0':'') + myDate.getMinutes() + "<br/>" + o.y;
		 //]]>		
        return string;
      }
	},
	legend : {
      position : 'nw',
      backgroundColor : '#eee' // A light blue background color.
    },
    HtmlText : false
   };
        
  // Draw graph with default options, overwriting with passed options
  function drawGraph (opts) {

    // Clone the options, so the 'options' variable always keeps intact.
    o = Flotr._.extend(Flotr._.clone(options), opts || {});

    // Return a new graph.
    return Flotr.draw(
      chart1,[
	  { data : power, label :'Power (W)', xaxis: 1, yaxis: 1 },
	  { data : energy, label :'Energy (Wh)', xaxis: 1, yaxis: 2  },
	  ],
      o
    );
  }

  graph = drawGraph();      
        
  Flotr.EventAdapter.observe(chart1, 'flotr:select', function(area){
    // Draw selected area
    graph = drawGraph({
      xaxis : { min : area.x1, max : area.x2, mode : 'time', labelsAngle : 45 ,noTicks:10},
      yaxis : { min : area.y1, max : area.y2 }
    });
  });
        
  // When graph is clicked, draw the graph with default area.
  Flotr.EventAdapter.observe(chart1, 'flotr:click', function () { graph = drawGraph(); });
})(document.getElementById("editor-render-0"));

     </script>
	 <!--[if lt IE 9]> <div style=' clear: both; height: 59px; padding:0 0 0 15px; position: relative;'> 
<a href=" http://windows.microsoft.com/en-US/internet-explorer/products/ie/home?ocid=ie6_countdown_bannercode" TARGET="_blank"> 
<img src=" http://storage.ie6countdown.com/assets/100/images/banners/warning_bar_0000_us.jpg" border="0" height="42" width="820" 
 alt="You are using an outdated browser. For a faster, safer browsing experience, upgrade for free today." /></a></div> <![endif]-->

</body>
</html>
