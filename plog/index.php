<?php
// This file is part of iTutor
//
// It is a pilot report plugin, extending the 'clustering agent' 
// by providing a timeline comparison between the calculated clusters 
// and individual student's metrics
//

/** Based on the report_log package, and therefore also GNU GPL
 * licence details to be included
 *
 * @package    report_log
 * @copyright  1999 onwards Martin Dougiamas (http://dougiamas.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require('../../config.php');
require_once($CFG->dirroot.'/course/lib.php');
require_once($CFG->dirroot.'/report/plog/locallib.php');
require_once($CFG->libdir.'/adminlib.php');

$id          = optional_param('id', 0, PARAM_INT);// Course ID
//options inherited from log report, some need removing/changing
$student        = optional_param('student', 0, PARAM_INT); // User to display
$startdate        = optional_param('startdate', 0, PARAM_INT); // Date to display
$test        = optional_param('test', 0, PARAM_INT); // Date to display
$metric        = optional_param('metric', "forum_posts", PARAM_TEXT); // Date to display

//set default params
$params = array();
if ($student !== 0) {
    $params['student'] = $student;
}
if ($startdate !== '') {
    $params['startdate'] = $startdate;
}
if ($test !== 0) {
    $params['test'] = $test;
}
if ($metric !== 0) {
    $params['metric'] = $metric;
}
if ($id !== 0) {
    $params['id'] = $id;
}

$PAGE->set_url('/report/plog/index.php', $params);
$PAGE->set_pagelayout('report');

$host_course = optional_param('host_course', '', PARAM_PATH);// Course ID

if (empty($host_course)) {
    $hostid = $CFG->mnet_localhost_id;
    if (empty($id)) {
        $site = get_site();
        $id = $site->id;
    }
} else {
    list($hostid, $id) = explode('/', $host_course);
}



/*f ($hostid == $CFG->mnet_localhost_id) {
    $course = $DB->get_record('course', array('id'=>$id), '*', MUST_EXIST);

} else {
    $course_stub       = $DB->get_record('mnet_log', array('hostid'=>$hostid, 'course'=>$id), '*', true);
    $course->id        = $id;
    $course->shortname = $course_stub->coursename;
    $course->fullname  = $course_stub->coursename;
}
*/
//Alternative method for obtaining course id
$dummy=$SESSION->fromdiscussion;
preg_match('/id=([0-9]*)$/',$dummy,$matches);
$course->id = $matches[1];
require_login($course);
//print_object($USER);
//cannot get proper capability checking to work, but can test the access value in the $USER object (PNP)
    if (!isset($USER->access)) {
        load_all_capabilities();
    }

if (key_exists("[/1:5]",$USER->access["rdef"]))
  if ($USER->access["rdef"]["/1:5"]["report/plog:viewown"])
	$viewown = true;
  else
	  $viewown = false;
else
	$viewown = false;

if (key_exists("[/1:3]",$USER->access["rdef"]))
if ($USER->access["rdef"]["/1:3"]["report/plog:viewcourse"])
	$viewcourse = true;
else
	$viewcourse=false;
else
	$viewcourse = false;

//

add_to_log($course->id, "course", "report plog", "report/plog/index.php?id=$course->id", $course->id);

if (!empty($page)) {
    $strlogs = get_string('plogs'). ": ". get_string('page', 'report_plog', $page+1);
} else {
    $strlogs = get_string('plogs');
}
$stradministration = get_string('administration');
$strreports = get_string('reports');

session_get_instance()->write_close();


require_once($CFG->libdir .'/filelib.php');

 global $DB;
//so this would want to get the user id of the student of interest
//and needs options for chooseing course and stat
//and then it just needs to draw a graph from the data
//oh, and be refactored properly and be part of a block, of course!


//better defaults required - 
//if (!empty($chooselog)) {
    //$userinfo = get_string('allparticipants');
    //$dateinfo = get_string('alldays');
//
    //if ($user) {
        //$u = $DB->get_record('user', array('id'=>$user, 'deleted'=>0), '*', MUST_EXIST);
        //$userinfo = fullname($u, has_capability('moodle/site:viewfullnames', $context));
    //}
    //if ($date) {
        //$dateinfo = userdate($date, get_string('strftimedaydate'));
    //}
//
//} else {
        $PAGE->set_title($course->shortname .': '. $strlogs);
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();
        $tc=$course->id;
        $id=$student;

        if ($test == 1) {
	    $tc=-1;
	    $id=0;
        }
    //need to get the run IDs for the date range
     $s1 = "select * from cluster_runs r
  join cluster_stats s on r.id=s.clusterid
  where UNIX_TIMESTAMP(r.date) > $startdate and r.courseid = $tc and r.clusteringname = '$metric' ";

   
     $s = "select * from cluster_user_clusters u
	 inner join cluster_runs r on u.clusterid = r.id
  where u.userid=$id and r.courseid = $tc and UNIX_TIMESTAMP(r.date) > $startdate and r.clusteringname = '$metric' ";


     $stats1 = $DB->get_records_sql($s1);
     $indiv_stats = $DB->get_records_sql($s);


 //
 //
 //
 //check whether we got any data back...
 //
 //
 //
     if (empty($stats1)) {
	 echo '<H2>There are no aggregated records for this course</H2><BR/>';
     }
     if (empty($indiv_stats)) {
	 echo '<H2>There are no records for this user</H2><BR/>';
     }
    echo '<div id="graph"></div>';
    echo $OUTPUT->heading('Choose which personal report to see :');

     echo '<script src="http://d3js.org/d3.v3.min.js" charset="utf-8"></script>';
     echo '<script>';
     $indiva = array();
     $indiv = "var indiv = [";
     foreach ($indiv_stats as $row) {
	 $indiva[] = $row->value ;
     };
     $indiv = $indiv . implode(",", $indiva) . "];
 ";
     echo $indiv;

     $dates=array();
     $clusters=array();
 
     $elems = count($stats1);
     $keys = array_keys($stats1);

//print_object($indiv_stats);
//this is causing trouble on the server, but not when run on local machine 
//echo "this works";
//print_object(array_keys($indiv_stats));
//print_object($indiv_stats);
$testkeys=array_keys($indiv_stats);
//echo "</script>";
     for($i=0;$i < count($indiv_stats);$i++) {
	 $clusters[$i]=array();
         $mykey=$testkeys[$i];
         //echo $mykey;
         $mydate=$indiv_stats[$mykey]->date;
         //echo $mydate;
         $dates[] = '"' . $mydate .'"';
     }

     for($i=0;$i < $elems;$i++)  {
          $row = $stats1[$keys[$i]];
	  $clusters[(intval($row->cluster)-1) * 3][] = $row->mean - $row->stddev;
	  $clusters[(intval($row->cluster)-1) * 3 + 1][] = $row->mean ;
	  $clusters[(intval($row->cluster)-1) * 3 + 2][] = $row->mean + $row->stddev;

  //$dates[] = '"' . $row->date .'"';
     };
      echo ("\r\nvar cdata=[");
      for($i=0;$i<count($clusters);$i++) {
	if (count($clusters[$i])>0) {
            echo "[";
            echo (implode(",", $clusters[$i]) . "]");
            if ($i <(count($clusters) -1))
	        echo ",";
	}
      }
      echo "];
  "; 
      echo ("var dates = [" . implode(",", $dates) . "];");
      echo "var n = 4, // number of layers
    m = 10, // number of samples per layer
    xm = 30, //margins
    ym= 80,
    width = 640,
    height = 480,
    showedges = false,
    showline = true,
    interpolate ='cardinal'
    ";
  //expand the colour list for more sections, or allow to be configured via a form
      echo 'var colours=[
      ["#408","#f0f"],
      ["#429","#222"],
      ["#ddd","#3F3"],
      ["#597","#f00"],
      ["#7fc","#2f2"],
      ["#ddd","#377"],
      ["#808","#50f"],
      ["#c77","#277"],
      ["#ddd","#3F3"],
      ["#c77","#277"],
      ["#ddd","#3F3"]
      ]
      ';
     echo 'var dateparse = d3.time.format("%Y-%m-%d %H:%M:%S");

dates.forEach(function(d,i) {
	dates[i]=dateparse.parse(d);
});

var mindate=dates[0]
var maxdate=dates[dates.length-1]

var sx = d3.time.scale()
    .domain([mindate, maxdate])
    .range([0, width]);

var miny=d3.min(d3.merge([d3.merge(cdata),indiv]))
var maxy=d3.max(d3.merge([d3.merge(cdata),indiv]))

var sy = d3.scale.linear()
.domain([miny, maxy])
  .range([height,0]);

var line=d3.svg.line()
  .x(function(d,i) {return sx(dates[i])})
  .y(function(d) {return sy(d); });

var area = d3.svg.area()
    .x(line.x())
    .y0(line.y())
    .y1(0);

var svg = d3.select("#graph").append("svg")
    .attr("width", width+xm*2)
    .attr("height", height+ym*2)
    .append("g")
    .attr("transform", "translate(" +xm + "," + ym + ")");

 // Add the x-axis.
  svg.append("g")
     .attr("class", "xAxis")
     .attr("fill", "none")
     .attr("stroke-width", 1)
     .attr("stroke", "black")
      .attr("transform", "translate(0," + height + ")")
      .call(d3.svg.axis().scale(sx).orient("bottom"))
        .selectAll("text")  
            .style("text-anchor", "end")
            .attr("dx", "-.8em")
            .attr("dy", ".15em")
            .attr("transform", function(d) {
                return "rotate(-65)" 
                });
      
  // Add the y-axis.
  svg.append("g")
      .attr("class", "y axis")
     .attr("fill", "none")
     .attr("stroke-width", 1)
     .attr("stroke", "black")
      .call(d3.svg.axis().scale(sy).orient("left"));

var lineContainer=svg.selectAll("r")
    .data(cdata).enter().append("g")

    lineContainer.append("path")
    .attr("class","area")
    .attr("d", area)//function(d) {return area(d);})
    .attr("opacity", 1)
    .style("fill", function(d,i) { return colours[i][0]; })
    

    if (showedges) {
    lineContainer.append("path")
    .attr("class","line")
    .attr("d", line)
    .attr("fill", "none")
    .attr("stroke-width", 1)
    .attr("stroke", function(d,i) { return colours[i][1]; })
    }

svg.selectAll("r")
.data(indiv)
.enter().append("circle")
 .style("fill","#F22")
 .attr("r", 4.0)
 .attr("cx", function(d,i) {return sx(dates[i]); })
 .attr("cy", function(d) {return sy(d); })

    if (showline) {
    svg.selectAll("t")
   .data(indiv).enter()
    .append("path")
    .attr("class","line")
    .attr("d", line(indiv))
    .attr("fill", "none")
    .attr("stroke-width", 1)
    .attr("stroke", "black")
    }
';
     echo "</script>";
     report_plog_print_selector_form($student, $startdate, $course,$metric, $test);

echo $OUTPUT->footer();
