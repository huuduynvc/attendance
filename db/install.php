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
 * post installation hook for adding data.
 *
 * @package    mod_attendance
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Post installation procedure
 */
function xmldb_attendance_install() {
    global $DB;

    $result = true;
    $arr = array('Diem danh truc tiep' => 2, 'Giao vien diem danh' => 2, 'Tre' => 1, 'Vang' => 0);
//    $dessciption = array('Chu dong','Bi dong', 'Tre', 'Vang');
    $i = 0;
    $arr_acronym = array(0=>'C',1=>'B',2=>'T',3=>'V');
    foreach ($arr as $k => $v) {
        $rec = new stdClass;
//        $rec->attendanceid = 0;
        $rec->acronym = $k;
        // Sanity check - if language translation uses more than the allowed 2 chars.
        if (mb_strlen($rec->acronym) > 2) {
            //$rec->acronym = $k[0];
            $rec->acronym = $arr_acronym[$i];
        }
//        $rec->description = get_string($k.'full', 'attendance');
        $rec->description = $k;
        $i = $i + 1;
        $rec->grade = $v;
        $rec->visible = 1;
        $rec->deleted = 0;

        $result = $result && $DB->insert_record('attendance_statuses', $rec);
//        if (!$DB->record_exists('attendance_statuses', array('attendanceid' => 0, 'acronym' => $rec->acronym))) {
//
//        }
    }

    return $result;
}
