<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This file contains functions used by the log reports
 *
 * This files lists the functions that are used during the log report generation.
 *
 * @package    report_log
 * @copyright  1999 onwards Martin Dougiamas (http://dougiamas.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if (!defined('REPORT_LOG_MAX_DISPLAY')) {
    define('REPORT_LOG_MAX_DISPLAY', 150); // days
}

require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/lib.php');

/**
 * This function is used to generate and display the log activity graph
 *
 * @global stdClass $CFG
 * @param  stdClass $course course instance
 * @param  int    $userid id of the user whose logs are needed
 * @param  string $type type of logs graph needed (usercourse.png/userday.png)
 * @param  int    $date timestamp in GMT (seconds since epoch)
 * @return void
 */

//really should move the graph code from index.php to here?
function report_plog_print_graph($course, $userid, $type, $date=0) {
    global $CFG;

    echo '<img src="'.$CFG->wwwroot.'/report/plog/graph.php?id='.$course->id.
         '&amp;user='.$userid.'&amp;type='.$type.'&amp;date='.$date.'" alt="" />';
}
/**
 * This function is used to generate and display selector form
 *
 * @global stdClass $USER
 * @global stdClass $CFG
 * @global moodle_database $DB
 * @global core_renderer $OUTPUT
 * @global stdClass $SESSION
 * @uses CONTEXT_SYSTEM
 * @uses COURSE_MAX_COURSES_PER_DROPDOWN
 * @uses CONTEXT_COURSE
 * @uses SEPARATEGROUPS
 * @param  stdClass $course course instance
 * @param  int      $selecteduser id of the selected user
 * @param  string   $selecteddate Date selected
 * @param  string   $modname course_module->id
 * @param  string   $modid number or 'site_errors'
 * @param  string   $modaction an action as recorded in the logs
 * @param  int      $selectedgroup Group to display
 * @param  int      $showcourses whether to show courses if we're over our limit.
 * @param  int      $showusers whether to show users if we're over our limit.
 * @param  string   $logformat Format of the logs (downloadascsv, showashtml, downloadasods, downloadasexcel)
 * @return void
 */
function report_plog_print_selector_form($student, $startdate=0, $course,$metric,$test) {

    global $USER, $CFG, $DB, $OUTPUT, $SESSION;
    $sitecontext = context_system::instance();
    $context = context_course::instance($course->id);
    if (has_capability('report/plog:viewcourse', $context)) {
     $showusers = 1;}
    else {
     $showusers = 0;
    }
    //probably remove this - not currently worried about groups (should we be though?)
    /// Setup for group handling.
    if ($course->groupmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {
        $selectedgroup = -1;
        $showgroups = false;
    } else if ($course->groupmode) {
        $showgroups = true;
    } else {
        $selectedgroup = 0;
        $showgroups = false;
    }

    if ($selectedgroup === -1) {
        if (isset($SESSION->currentgroup[$course->id])) {
            $selectedgroup =  $SESSION->currentgroup[$course->id];
        } else {
            $selectedgroup = groups_get_all_groups($course->id, $USER->id);
            if (is_array($selectedgroup)) {
                $selectedgroup = array_shift(array_keys($selectedgroup));
                $SESSION->currentgroup[$course->id] = $selectedgroup;
            } else {
                $selectedgroup = 0;
            }
        }
    }

    // Get all the possible users
    $users = array();

    // Define limitfrom and limitnum for queries below
    // If $showusers is enabled... don't apply limitfrom and limitnum
    $limitfrom = empty($showusers) ? 0 : '';
    $limitnum  = empty($showusers) ? COURSE_MAX_USERS_PER_DROPDOWN + 1 : '';

    //this needs to be modified so we don't include admin and tutors for this course in the list?
    $courseusers = get_enrolled_users($context, '', $selectedgroup, 'u.id, u.firstname, u.lastname', null, $limitfrom, $limitnum);


    if ($showusers) {
        if ($courseusers) {
            foreach ($courseusers as $courseuser) {
                $users[$courseuser->id] = fullname($courseuser, has_capability('moodle/site:viewfullnames', $context));
            }
        }
        $users[$CFG->siteguest] = get_string('guestuser');
    }

    //echo $course->id;
    //$n = "select distinct clusteringname from cluster_runs r where r.courseid = $course->id";
    $n = "select distinct clusteringname from cluster_runs r where r.courseid = $course->id";

    $metrica = $DB->get_records_sql($n);
    //there has to be a better way of doing this...
    $metrics = array();
    foreach($metrica as $row) {
	    $metrics[] = $row->clusteringname;
    };


    $strftimedate = get_string("strftimedate");
    $strftimedaydate = get_string("strftimedaydate");

    asort($users);


    $timenow = time(); // GMT

    // What day is it now for the user, and when is midnight that day (in GMT).
    $timemidnight = $today = usergetmidnight($timenow);

    
    // Put today up the top of the list
    //$dates = array("$timemidnight" => get_string("today").", ".userdate($timenow, $strftimedate) );
    $dates = array();

    if (!$course->startdate or ($course->startdate > $timenow)) {
        $course->startdate = $course->timecreated;
    }

    //want to change this so we have a number of intervals
    //from start of course
    //last week
    //last fortnight
    //last month
    $numdates = 1;
    while ($timemidnight > $course->startdate and $numdates < 5) {
        $timemidnight = $timemidnight - 604800;    //number of seconds in a week
        $timenow = $timenow - 604800;
        $dates["$timemidnight"] = $numdates . " weeks:" . userdate($timenow, $strftimedaydate);
        $numdates++;
    }
    

    echo "<form class=\"logselectform\" action=\"$CFG->wwwroot/report/plog/index.php\" method=\"get\">\n";
    echo "<div>\n";
    echo "<input type=\"hidden\" name=\"chooselog\" value=\"1\" />\n";
    echo "<input type=\"hidden\" name=\"showusers\" value=\"$showusers\" />\n";
    echo "<input type=\"hidden\" name=\"test\" value=\"$test\" />\n";

    if ($showusers) {
        echo html_writer::label(get_string('selctauser'), 'menuuser', false, array('class' => 'accesshide'));
        echo html_writer::select($users, "student", $student );
    }

    echo html_writer::label(get_string('date'), 'menudate', false, array('class' => 'accesshide'));
    echo html_writer::select($dates, "startdate", $startdate, get_string("alldays"));
    echo '<select id="menumetric" class="select menumetric" name="metric">';
    foreach ($metrics as $metric) {
	    echo "<option value='$metric'>$metric</option>";
    }
    echo '</select>';
    //echo html_writer::select($metrics, "metric", $metric, "metric");

    echo '<input type="submit" value="'.get_string('gettheselogs').'" />';
    echo '</div>';
    echo '</form>';
}
