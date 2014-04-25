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
    // Get Time stamp.  If none is provided in URL, use today's date
	// temptimestamp is a unix time
	$datahome = "/home/pi/xbee/data/"
	$temptimestamp = mktime(0,0,0,date("m",time()),date("d",time()),date("Y",time()));
	$filename = $datahome.date($temptimestamp,"ymd")
	if(isset($_GET["date"]))
	{
		$filename = $datahome.substr($_GET["date"],0,4).substr($_GET["date"],2,2).substr($_GET["date"],5,2).substr($_GET["date"],8,2).".csv"
		if(file_exists($filename))
		{
		    $temptimestamp = mktime(0,0,0,substr($_GET["date"],5,2),substr($_GET["date"],8,2),substr($_GET["date"],0,4));
		}
	}
	
	$row = 1;
	$aldablights = $hermanlights = $heater = 0;
	$time = $d1 = $d2 = $d3 = $d4 = $plug1 = $plug2 = $plug3 = $plug4 = array();
	$d1min = $d2min = $d3min = 100;
	$d1max = $d2max = $d3max = -100;
	if (($handle = fopen("/home/pi/xbee/data/".substr($_GET["date"],0,4).substr($_GET["date"],2,2).substr($_GET["date"],5,2).substr($_GET["date"],8,2).".csv", "r")) !== FALSE) 
	

	// Put data into arrays
	$indoor_temp = array();
	$outdoor_temp = array();
	if ($res)
	{
		while($row = mysql_fetch_array($res))
		{
			$time_string = $row[0];
			$hour = intval(substr($time_string,11,2));
			$min = intval(substr($time_string,14,2));
			$sec = intval(substr($time_string,17,2));
			$year = intval( substr($time_string,0,4));
			$mon  = intval(substr($time_string,5,2));
			$day  = intval(substr($time_string,8,2));
			$unix_time_stamp = mktime( $hour,$min,$sec,$mon,$day,$year)."000";
			$indoor_temp[] = '['.$unix_time_stamp.','.$row[1].']';
			$outdoor_temp[] = '['.$unix_time_stamp.','.$row[2].']';
		}
	}
	else
	{
		echo mysql_error();
	}
	
	// Close connection
    mysql_close( $handle );
 $html =  '  <title> Room Temperatures - '.date("m/d/Y",$temptimestamp).'</title>
  </head>
  <body>
    <div id ="container">
      <div id="calendar"></div>
      <h1>Room Temperature - '.date("m/d/Y",$temptimestamp).'</h1>
    </div> 
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
    indoor    = [<?php echo implode(',',$indoor_temp) ?>],
	outdoor   = [<?php echo implode(',',$outdoor_temp) ?>],
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
        min: -15,
        max: 105,
        showLabels: true, 
		title: 'Temp \xB0 F',
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
        var string = myDate.getHours() + ":" + (myDate.getMinutes() <10 ?'0':'') + myDate.getMinutes() + "<br/>" + o.y + "\u00B0" + "F";
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
	  { data : indoor, label :'Room Temperature' },
	  { data : outdoor, label :'Outdoor Temperature' },
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
