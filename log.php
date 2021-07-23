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
 * Temporary user management.
 *
 * @package    mod_attendance
 * @copyright  2013 Davo Smith, Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../../config.php');
require_once($CFG->libdir.'/formslib.php');
require_once($CFG->dirroot.'/mod/attendance/locallib.php');

$id = required_param('id', PARAM_INT);

$PAGE->requires->js(new moodle_url('https://code.jquery.com/jquery-3.5.1.js'),true);
$PAGE->requires->css(new moodle_url('https://cdn.datatables.net/1.10.24/css/jquery.dataTables.min.css'));
$PAGE->requires->js(new moodle_url('https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js'),true);

$cm = get_coursemodule_from_id('attendance', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$att = $DB->get_record('attendance', array('id' => $cm->instance), '*', MUST_EXIST);

$att = new mod_attendance_structure($att, $cm, $course);
$PAGE->set_url($att->url_log());

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/attendance:manageattendances', $context);

$PAGE->set_title($course->shortname.": ".$att->name.' - Log');
$PAGE->set_heading($course->fullname);
$PAGE->force_settings_menu(true);
$PAGE->set_cacheable(true);
$PAGE->navbar->add('Log');

$output = $PAGE->get_renderer('mod_attendance');
$tabs = new attendance_tabs($att, attendance_tabs::TAB_LOG);

// Output starts here.
echo $output->header();
echo $output->heading('Attendance log for'.' : '.format_string($course->fullname));
echo $output->render($tabs);


echo '<div>';
//echo '<p style="margin-left:10%;">Attendance logs table</p>';
//if ($logs) {
//    attendance_print_logs($logs, $att);
//}

echo '<p></p>';
echo '<table id="table" summary="Attendance log">
        <thead><tr>';
echo '<th>Time</th>';
echo '<th>Session date</th>';
echo '<th>User fullname</th>';
echo '<th>Affected user</th>';
echo '<th>Event name</th>';
echo '<th>Description</th>';
echo '</tr></thead><tbody>';
echo '</tbody></table>';
echo '</div>';

echo "<script>
$(document).ready( function () {
    $('#table').DataTable({
        'processing':true,
        'serverSide':true,
        'ajax':{
           'url': '../attendance/server_processing.php',
            'data': {
              'attendanceid': $cm->instance      
            },
        },
        		'columns': [
        { data: 'timetaken' },
        { data: 'sessdate' },
        { data: 'usertaken' },
        { data: 'userbetaken' },
        { data: 'eventname' },
        { data: 'description'}
            ],
            'columnDefs': [{
        'targets': [5],
        'orderable': false, 
        }],
    });
} );
</script>";

echo $output->footer($course);

/**
 * Print list of users.
 *
 * @param stdClass $tempusers
 * @param mod_attendance_structure $att
 */
function attendance_print_logs($logs, mod_attendance_structure $att) {
    echo '<p></p>';
    echo '<table id="table" summary="Attendance log">
        <thead><tr>';
    echo '<th>Time</th>';
    echo '<th>User fullname</th>';
    echo '<th>Affected user</th>';
    echo '<th>Description</th>';
    echo '</tr></thead><tbody>';

    $status_name = array(1 => 'Chu dong', 2=>'Bi dong', 3=>'Tre',4=>'Vang');
    foreach ($logs as $log) {
        $time = getdate($log->timetaken);
        $timetaken = sprintf("%02d",round($time['hours'],2)). ':' .sprintf("%02d",round($time['minutes'],2)). ':'  .sprintf("%02d",round($time['seconds'],2));
        echo '<td>'.format_string(date('d-m-Y H:i:s',$log->timetaken)).'</td>';
        echo '<td>'.format_string($log->user1).'</td>';
        echo '<td>'.format_string($log->user2).'</td>';
        echo '<td>'.$log->user1. ' modified status of ' .$log->user2. ' from ' .$status_name[$log->oldstatus]. ' to ' .$status_name[$log->newstatus].'</td>';

        echo '</tr>';
    }
    echo '</tbody></table>';
}


