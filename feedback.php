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

require_once(__DIR__ . '/../../local/webservices/externallib_frontend.php');

$id = required_param('id', PARAM_INT);
$userid = required_param('userid',PARAM_INT);
$sessionid = required_param('sessionid',PARAM_INT);

$PAGE->requires->js(new moodle_url('https://code.jquery.com/jquery-3.5.1.js'),true);
$PAGE->requires->css(new moodle_url('https://cdn.datatables.net/1.10.24/css/jquery.dataTables.min.css'));
$PAGE->requires->js(new moodle_url('https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js'),true);

$cm = get_coursemodule_from_id('attendance', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$att = $DB->get_record('attendance', array('id' => $cm->instance), '*', MUST_EXIST);

$att = new mod_attendance_structure($att, $cm, $course);
$PAGE->set_url($att->url_feedback());

require_login($course, true, $cm);
$context = context_module::instance($cm->id);


$PAGE->set_title($course->shortname.": ".$att->name.' - Feedback');
$PAGE->set_heading($course->fullname);
$PAGE->force_settings_menu(true);
$PAGE->set_cacheable(true);
$PAGE->navbar->add('Feedback');

$output = $PAGE->get_renderer('mod_attendance');
$tabs = new attendance_tabs($att, attendance_tabs::TAB_FEEDBACK);

// Output starts here.
echo $output->header();
echo $output->heading('Attendance feedback for'.' : '.format_string($course->fullname));
echo $output->render($tabs);


echo '<div>';

echo '<p></p>';
echo '<table id="table" summary="Attendance feedback">
        <thead><tr>';
echo '<th>Time</th>';
echo '<th>Sessdate</th>';
echo '<th>User fullname</th>';
echo '<th>Affected user</th>';
echo '<th>Description</th>';
echo '<th>Image register</th>';
echo '<th>Image feedback</th>';
echo '</tr></thead><tbody>';
$a = new local_webservices_frontend();
$feedbacks = $a->get_feedbacks_by_ids($userid,$sessionid);
$data = '';
foreach ($feedbacks as $feed){
    $img_register = base64_decode($feed->image_register);
    $img = base64_decode($feed->image);
    if($feed->userbetaken_name === null){
        $feed->userbetaken = '';
    }
    $data .= '<tr>
    <td>'.date('d-m-Y H:i:s',$feed->timetaken).'</td>
    <td><a href="/mod/attendance/take.php?id='.$id.'&sessionid='.$sessionid.'&grouptype=0">'.date('d-m-Y H:i:s',$feed->sessdate).'</a></td>
    <td>'.$feed->usertaken_name.'</td>
    <td>'.$feed->userbetaken_name.'</td>
    <td>'.$feed->description.'</td>';
    if($feed->image !== null){
        $data .= '
        <td><img src="'. $feed->image_register . '" width="200" height="200" /></td>
    <td><img src="'. $feed->image . '" width="200" height="200" /></td>
        ';
    }else{
        $data.='<td></td><td></td>';
    }

    $data.='</tr>';
}
echo $data;
echo '</tbody></table>';
echo '</div>';

echo "<script>
$(document).ready( function () {
    $('#table').DataTable();
} );
</script>";

echo $output->footer($course);


