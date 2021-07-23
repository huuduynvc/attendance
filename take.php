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
 * Take Attendance
 *
 * @package    mod_attendance
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../../config.php');
require_once(dirname(__FILE__).'/locallib.php');

$pageparams = new mod_attendance_take_page_params();

$id                     = required_param('id', PARAM_INT);
$pageparams->attendanceid = required_param('id',PARAM_INT);
$pageparams->sessionid  = required_param('sessionid', PARAM_INT);
$pageparams->grouptype  = required_param('grouptype', PARAM_INT);
$pageparams->sort       = optional_param('sort', ATT_SORT_DEFAULT, PARAM_INT);
$pageparams->copyfrom   = optional_param('copyfrom', null, PARAM_INT);
$pageparams->viewmode   = optional_param('viewmode', null, PARAM_INT);
$pageparams->gridcols   = optional_param('gridcols', null, PARAM_INT);
$pageparams->page       = optional_param('page', 1, PARAM_INT);
$pageparams->perpage    = optional_param('perpage', get_config('attendance', 'resultsperpage'), PARAM_INT);

$cm             = get_coursemodule_from_id('attendance', $id, 0, false, MUST_EXIST);
$course         = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$att            = $DB->get_record('attendance', array('id' => $cm->instance), '*', MUST_EXIST);
// Check this is a valid session for this attendance.
$session        = $DB->get_record('attendance_sessions', array('id' => $pageparams->sessionid, 'attendanceid' => $att->id),
                                  '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/attendance:takeattendances', $context);

$pageparams->group = groups_get_activity_group($cm, true);

$pageparams->init($course->id);
$att = new mod_attendance_structure($att, $cm, $course, $PAGE->context, $pageparams);

$allowedgroups = groups_get_activity_allowed_groups($cm);
if (!empty($pageparams->grouptype) && !array_key_exists($pageparams->grouptype, $allowedgroups)) {
     $group = groups_get_group($pageparams->grouptype);
     throw new moodle_exception('cannottakeforgroup', 'attendance', '', $group->name);
}

if (($formdata = data_submitted()) && confirm_sesskey()) {
    $att->take_from_form_data($formdata);

    $group = 0;
    if ($att->pageparams->grouptype != mod_attendance_structure::SESSION_COMMON) {
        $group = $att->pageparams->grouptype;
    } else {
        if ($att->pageparams->group) {
            $group = $att->pageparams->group;
        }
    }

    $totalusers = count_enrolled_users(context_module::instance($cm->id), 'mod/attendance:canbelisted', $group);
    $usersperpage = $att->pageparams->perpage;

    if (!empty($att->pageparams->page) && $att->pageparams->page && $totalusers && $usersperpage) {
        $numberofpages = ceil($totalusers / $usersperpage);
        if ($att->pageparams->page < $numberofpages) {
            $params = array(
                'sessionid' => $att->pageparams->sessionid,
                'grouptype' => $att->pageparams->grouptype);
            $params['page'] = $att->pageparams->page + 1;
            redirect($att->url_take($params), get_string('moreattendance', 'attendance'));
        }
    }

    redirect($att->url_manage(), get_string('attendancesuccess', 'attendance'));
}

$PAGE->set_url($att->url_take());
$PAGE->set_title($course->shortname. ": ".$att->name);
$PAGE->set_heading($course->fullname);
$PAGE->set_cacheable(true);
$PAGE->navbar->add($att->name);

$output = $PAGE->get_renderer('mod_attendance');
$tabs = new attendance_tabs($att);
$sesstable = new attendance_take_data($att);

// Output starts here.

echo $output->header();
echo $output->heading(get_string('attendanceforthecourse', 'attendance').' :: ' .format_string($course->fullname));

echo $output->render($tabs);
echo '<div style="width:100%; height:50px;">
<input type="text" id="myInput" style="width: 300px; padding: 5px 15px; padding-right: 25px;" onkeyup="myFunction()" placeholder="Search for name or user id...">
<input id="createCheckin" type="submit" class="btn btn-primary" style="float: right; background-color: #f60;" value="Create online checkin">
</div>';
echo $output->render($sesstable);
echo '<script>
function myFunction() {
  // Declare variables
  var input, filter, table, tr, td, i, txtValue;
  input = document.getElementById("myInput");
  filter = input.value.toUpperCase();
  table = document.getElementsByClassName("generaltable")[0];
  tr = table.getElementsByTagName("tr");

  // Loop through all table rows, and hide those who don\'t match the search query
  for (i = 1; i < tr.length; i++) {
    td = tr[i].getElementsByTagName("td")[1];
    td1 = tr[i].getElementsByTagName("td")[2];
    if (td) {
      txtValue = td.textContent || td.innerText;
      txtValue1 = td1.textContent || td1.innerText;
      if (txtValue.toUpperCase().indexOf(filter) > -1 || txtValue1.toUpperCase().indexOf(filter) > -1) {
        tr[i].style.display = "";
      } else {
        tr[i].style.display = "none";
      }
    }
  }
  
  var viewmode = $(\'[name="viewmode"]\');
  console.log(viewmode.val());
  if(viewmode.val() === 2){
    input.hide();
  }
}
</script>';
if($session->sessdate + $session->duration >= time()){
    $url_s = (new moodle_url('/mod/attendance/save_time_online_checkin.php?sessionid='.$att->pageparams->sessionid))->__toString();
    echo "

<script src=\"//cdn.jsdelivr.net/npm/sweetalert2@11\"></script>
<script>
$(document).ready( function () {
    $('#createCheckin').click(function() {
      Swal.fire({
  title: 'Submit time of checkin online',
    input: 'select',
  inputOptions: {
    '5': '5 minutes',
    '10': '10 minutes',
    '15': '15 minutes',
    '30': '30 minutes'
  },
  showCancelButton: true,
  confirmButtonText: 'Save',
  showLoaderOnConfirm: true,
  allowOutsideClick: () => !Swal.isLoading()
}).then((result) => {
    console.log(result);
  if (result.isConfirmed) {
     $.post('$url_s',{ onlineduration: result.value}).done(function( data ) {
         if(data != null){
                 Swal.fire({ title: `Create online checkin successfully!`});
         }else{
                 Swal.fire({title: `Create online checkin failed!`});
         }
     });
  };
});
});
});
</script>";
}else{
    echo "<script>
    $(document).ready( function () {
        $('#createCheckin').hide()
    });
    </script>";
}


echo $output->footer();
