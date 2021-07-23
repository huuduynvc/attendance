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
echo $output->heading('Attendance checkin online for'.' : '.format_string($course->fullname));
echo $output->render($tabs);


echo '<div>';

echo '<p></p><table id="table" summary="Attendance checkin online for">
        <thead><tr>
        <th>Time checkin</th>
        <th>Session date</th>
        <th>Duration</th>
        <th>Image register</th>
        <th>Image checkin</th>
        </tr></thead><tbody>';
$a = new local_webservices_frontend();
$image_register = $a->get_image((int)$userid);
$image_checkin = $DB->get_records('attendance_checkin_images', array('studentid' => $userid, 'sessionid' => $sessionid));
$sess = $DB->get_record('attendance_sessions',array('id' => $sessionid));

$data = '';
$url = (new moodle_url("/mod/attendance/take.php?id=$id&sessionid=$sessionid&grouptype=0"))->__toString();
foreach($image_checkin as $img){
    $data .= '<tr>
    <td>'.date('d-m-Y H:i:s',$img->timetaken).'</td>
    <td><a href='.$url.'>'.date('d-m-Y H:i:s',$sess->sessdate).'</a></td>';
    $data .= '<td>'.$sess->duration.'</td>
        <td><img src='. $image_register[$userid]->image_front . '" width="200" height="200" /></td>
    <td>
    <img src="'. $img->image_front . '" width="200" height="200" />
    <img src="'. $img->image_left . '" width="200" height="200" />
    <img src="'. $img->image_right . '" width="200" height="200" />
    </td>
    </tr>';
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


