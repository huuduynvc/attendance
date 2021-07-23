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
 * Attendance module renderering methods
 *
 * @package    mod_attendance
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/renderables.php');
require_once(dirname(__FILE__).'/renderhelpers.php');
require_once($CFG->libdir.'/tablelib.php');
require_once($CFG->libdir.'/moodlelib.php');

require_once(__DIR__ . '/../../local/webservices/externallib.php');
require_once(__DIR__ . '/../../local/webservices/externallib_frontend.php');

/**
 * Attendance module renderer class
 *
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_attendance_renderer extends plugin_renderer_base {
    // External API - methods to render attendance renderable components.

    /**
     * Renders tabs for attendance
     *
     * @param attendance_tabs $atttabs - tabs to display
     * @return string html code
     */
    protected function render_attendance_tabs(attendance_tabs $atttabs) {
        return print_tabs($atttabs->get_tabs(), $atttabs->currenttab, null, null, true);
    }

    /**
     * Renders filter controls for attendance
     *
     * @param attendance_filter_controls $fcontrols - filter controls data to display
     * @return string html code
     */
    protected function render_attendance_filter_controls(attendance_filter_controls $fcontrols) {
        $classes = 'attfiltercontrols';
        $filtertable = new html_table();
        $filtertable->attributes['class'] = ' ';
        $filtertable->width = '100%';
        $filtertable->align = array('left', 'center', 'right', 'right');

        if (property_exists($fcontrols->pageparams, 'mode') &&
            $fcontrols->pageparams->mode === mod_attendance_view_page_params::MODE_ALL_SESSIONS) {
            $classes .= ' float-right';

            $row = array();
            $row[] = '';
            $row[] = '';
            $row[] = '';
            $row[] = $this->render_grouping_controls($fcontrols);
            $filtertable->data[] = $row;

            $row = array();
            $row[] = '';
            $row[] = '';
            $row[] = '';
            $row[] = $this->render_course_controls($fcontrols);
            $filtertable->data[] = $row;
        }

        $row = array();

        $row[] = $this->render_sess_group_selector($fcontrols);
        $row[] = $this->render_curdate_controls($fcontrols);
        $row[] = $this->render_paging_controls($fcontrols);
        $row[] = $this->render_view_controls($fcontrols);

        $filtertable->data[] = $row;

        $o = html_writer::table($filtertable);
        $o = $this->output->container($o, $classes);

        return $o;
    }

    /**
     * Render group selector
     *
     * @param attendance_filter_controls $fcontrols
     * @return mixed|string
     */
    protected function render_sess_group_selector(attendance_filter_controls $fcontrols) {
        switch ($fcontrols->pageparams->selectortype) {
            case mod_attendance_page_with_filter_controls::SELECTOR_SESS_TYPE:
                $sessgroups = $fcontrols->get_sess_groups_list();
                if ($sessgroups) {
                    $select = new single_select($fcontrols->url(), 'group', $sessgroups,
                                                $fcontrols->get_current_sesstype(), null, 'selectgroup');
                    $select->label = get_string('sessions', 'attendance');
                    $output = $this->output->render($select);

                    return html_writer::tag('div', $output, array('class' => 'groupselector'));
                }
                break;
            case mod_attendance_page_with_filter_controls::SELECTOR_GROUP:
                return groups_print_activity_menu($fcontrols->cm, $fcontrols->url(), true);
        }

        return '';
    }

    /**
     * Render paging controls.
     *
     * @param attendance_filter_controls $fcontrols
     * @return string
     */
    protected function render_paging_controls(attendance_filter_controls $fcontrols) {
        $pagingcontrols = '';

        $group = 0;
        if (!empty($fcontrols->pageparams->group)) {
            $group = $fcontrols->pageparams->group;
        }

        $totalusers = count_enrolled_users(context_module::instance($fcontrols->cm->id), 'mod/attendance:canbelisted', $group);

        if (empty($fcontrols->pageparams->page) || !$fcontrols->pageparams->page || !$totalusers ||
            empty($fcontrols->pageparams->perpage)) {

            return $pagingcontrols;
        }

        $numberofpages = ceil($totalusers / $fcontrols->pageparams->perpage);

        if ($fcontrols->pageparams->page > 1) {
            $pagingcontrols .= html_writer::link($fcontrols->url(array('curdate' => $fcontrols->curdate,
                                                                       'page' => $fcontrols->pageparams->page - 1)),
                                                                 $this->output->larrow());
        }
        $a = new stdClass();
        $a->page = $fcontrols->pageparams->page;
        $a->numpages = $numberofpages;
        $text = get_string('pageof', 'attendance', $a);
        $pagingcontrols .= html_writer::tag('span', $text,
                                            array('class' => 'attbtn'));
        if ($fcontrols->pageparams->page < $numberofpages) {
            $pagingcontrols .= html_writer::link($fcontrols->url(array('curdate' => $fcontrols->curdate,
                                                                       'page' => $fcontrols->pageparams->page + 1)),
                                                                 $this->output->rarrow());
        }

        return $pagingcontrols;
    }

    /**
     * Render date controls.
     *
     * @param attendance_filter_controls $fcontrols
     * @return string
     */
    protected function render_curdate_controls(attendance_filter_controls $fcontrols) {
        global $CFG;

        $curdatecontrols = '';
        if ($fcontrols->curdatetxt) {
            $this->page->requires->strings_for_js(array('calclose', 'caltoday'), 'attendance');
            $jsvals = array(
                    'cal_months'    => explode(',', get_string('calmonths', 'attendance')),
                    'cal_week_days' => explode(',', get_string('calweekdays', 'attendance')),
                    'cal_start_weekday' => $CFG->calendar_startwday,
                    'cal_cur_date'  => $fcontrols->curdate);
            $curdatecontrols = html_writer::script(js_writer::set_variable('M.attendance', $jsvals));

            $this->page->requires->js('/mod/attendance/calendar.js');

            $curdatecontrols .= html_writer::link($fcontrols->url(array('curdate' => $fcontrols->prevcur)),
                                                                         $this->output->larrow());
            $params = array(
                    'title' => get_string('calshow', 'attendance'),
                    'id'    => 'show',
                    'class' => 'btn btn-secondary',
                    'type'  => 'button');
            $buttonform = html_writer::tag('button', $fcontrols->curdatetxt, $params);
            foreach ($fcontrols->url_params(array('curdate' => '')) as $name => $value) {
                $params = array(
                        'type'  => 'hidden',
                        'id'    => $name,
                        'name'  => $name,
                        'value' => $value);
                $buttonform .= html_writer::empty_tag('input', $params);
            }
            $params = array(
                    'id'        => 'currentdate',
                    'action'    => $fcontrols->url_path(),
                    'method'    => 'post'
            );

            $buttonform = html_writer::tag('form', $buttonform, $params);
            $curdatecontrols .= $buttonform;

            $curdatecontrols .= html_writer::link($fcontrols->url(array('curdate' => $fcontrols->nextcur)),
                                                                         $this->output->rarrow());
        }

        return $curdatecontrols;
    }

    /**
     * Render grouping controls (for all sessions report).
     *
     * @param attendance_filter_controls $fcontrols
     * @return string
     */
    protected function render_grouping_controls(attendance_filter_controls $fcontrols) {
        if ($fcontrols->pageparams->mode === mod_attendance_view_page_params::MODE_ALL_SESSIONS) {
            $groupoptions = array(
                'date' => get_string('sessionsbydate', 'attendance'),
                'activity' => get_string('sessionsbyactivity', 'attendance'),
                'course' => get_string('sessionsbycourse', 'attendance')
            );
            $groupcontrols = get_string('groupsessionsby', 'attendance') . ":";
            foreach ($groupoptions as $key => $opttext) {
                if ($key != $fcontrols->pageparams->groupby) {
                    $link = html_writer::link($fcontrols->url(array('groupby' => $key)), $opttext);
                    $groupcontrols .= html_writer::tag('span', $link, array('class' => 'attbtn'));
                } else {
                    $groupcontrols .= html_writer::tag('span', $opttext, array('class' => 'attcurbtn'));
                }
            }
            return html_writer::tag('nobr', $groupcontrols);
        }
        return "";
    }

    /**
     * Render course controls (for all sessions report).
     *
     * @param attendance_filter_controls $fcontrols
     * @return string
     */
    protected function render_course_controls(attendance_filter_controls $fcontrols) {
        if ($fcontrols->pageparams->mode === mod_attendance_view_page_params::MODE_ALL_SESSIONS) {
            $courseoptions = array(
                'all' => get_string('sessionsallcourses', 'attendance'),
                'current' => get_string('sessionscurrentcourses', 'attendance')
            );
            $coursecontrols = "";
            foreach ($courseoptions as $key => $opttext) {
                if ($key != $fcontrols->pageparams->sesscourses) {
                    $link = html_writer::link($fcontrols->url(array('sesscourses' => $key)), $opttext);
                    $coursecontrols .= html_writer::tag('span', $link, array('class' => 'attbtn'));
                } else {
                    $coursecontrols .= html_writer::tag('span', $opttext, array('class' => 'attcurbtn'));
                }
            }
            return html_writer::tag('nobr', $coursecontrols);
        }
        return "";
    }

    /**
     * Render view controls.
     *
     * @param attendance_filter_controls $fcontrols
     * @return string
     */
    protected function render_view_controls(attendance_filter_controls $fcontrols) {
        $views[ATT_VIEW_ALL] = get_string('all', 'attendance');
        $views[ATT_VIEW_ALLPAST] = get_string('allpast', 'attendance');
        $views[ATT_VIEW_MONTHS] = get_string('months', 'attendance');
        $views[ATT_VIEW_WEEKS] = get_string('weeks', 'attendance');
        $views[ATT_VIEW_DAYS] = get_string('days', 'attendance');
        if ($fcontrols->reportcontrol  && $fcontrols->att->grade > 0) {
            $a = $fcontrols->att->get_lowgrade_threshold() * 100;
            $views[ATT_VIEW_NOTPRESENT] = get_string('below', 'attendance', $a);
        }
        if ($fcontrols->reportcontrol) {
            $views[ATT_VIEW_SUMMARY] = get_string('summary', 'attendance');
        }
        $viewcontrols = '';
        foreach ($views as $key => $sview) {
            if ($key != $fcontrols->pageparams->view) {
                $link = html_writer::link($fcontrols->url(array('view' => $key)), $sview);
                $viewcontrols .= html_writer::tag('span', $link, array('class' => 'attbtn'));
            } else {
                $viewcontrols .= html_writer::tag('span', $sview, array('class' => 'attcurbtn'));
            }
        }

        return html_writer::tag('nobr', $viewcontrols);
    }

    /**
     * Renders attendance sessions managing table
     *
     * @param attendance_manage_data $sessdata to display
     * @return string html code
     */
    protected function render_attendance_manage_data(attendance_manage_data $sessdata) {
        $o = $this->render_sess_manage_table($sessdata) . $this->render_sess_manage_control($sessdata);
        $o = html_writer::tag('form', $o, array('method' => 'post', 'action' => $sessdata->url_sessions()->out()));
        $o = $this->output->container($o, 'generalbox attwidth');
        $o = $this->output->container($o, 'attsessions_manage_table');

        return $o;
    }

    /**
     * Render session manage table.
     *
     * @param attendance_manage_data $sessdata
     * @return string
     */
    protected function render_sess_manage_table(attendance_manage_data $sessdata) {
        $this->page->requires->js_init_call('M.mod_attendance.init_manage');

        $table = new html_table();
        $table->width = '100%';
        $table->head = array(
                '#',
                get_string('date', 'attendance'),
                get_string('time', 'attendance'),
//                get_string('sessiontypeshort', 'attendance'),
                get_string('description', 'attendance'),
                get_string('actions'),
                html_writer::checkbox('cb_selector', 0, false, '', array('id' => 'cb_selector'))
            );
        $table->align = array('', 'right', '', 'left', 'left', 'center');
        $table->size = array('1px', '1px', '', '*', '120px', '1px');

        $i = 0;
        foreach ($sessdata->sessions as $key => $sess) {
            $i++;

            $dta = $this->construct_date_time_actions($sessdata, $sess);

            $table->data[$sess->id][] = $i;
            $table->data[$sess->id][] = $dta['date'];
            $table->data[$sess->id][] = $dta['time'];
//            if ($sess->groupid) {
//                if (empty($sessdata->groups[$sess->groupid])) {
//                    $table->data[$sess->id][] = get_string('deletedgroup', 'attendance');
//                    // Remove actions and links on date/time.
//                    $dta['actions'] = '';
//                    $dta['date'] = userdate($sess->sessdate, get_string('strftimedmyw', 'attendance'));
//                    $dta['time'] = $this->construct_time($sess->sessdate, $sess->duration);
//                } else {
//                    $table->data[$sess->id][] = get_string('group') . ': ' . $sessdata->groups[$sess->groupid]->name;
//                }
//            } else {
//                $table->data[$sess->id][] = get_string('commonsession', 'attendance');
//            }
            $table->data[$sess->id][] = $sess->description;
            $table->data[$sess->id][] = $dta['actions'];
            $table->data[$sess->id][] = html_writer::checkbox('sessid[]', $sess->id, false, '',
                                                              array('class' => 'attendancesesscheckbox'));
        }

        return html_writer::table($table);
    }

    /**
     * Implementation of user image rendering.
     *
     * @param attendance_password_icon $helpicon A help icon instance
     * @return string HTML fragment
     */
    protected function render_attendance_password_icon(attendance_password_icon $helpicon) {
        return $this->render_from_template('attendance/attendance_password_icon', $helpicon->export_for_template($this));
    }
    /**
     * Construct date time actions.
     *
     * @param attendance_manage_data $sessdata
     * @param stdClass $sess
     * @return array
     */
    private function construct_date_time_actions(attendance_manage_data $sessdata, $sess) {
        $actions = '';
//        if ((!empty($sess->studentpassword) || ($sess->includeqrcode == 1)) &&
//            (has_capability('mod/attendance:manageattendances', $sessdata->att->context) ||
//            has_capability('mod/attendance:takeattendances', $sessdata->att->context) ||
//            has_capability('mod/attendance:changeattendances', $sessdata->att->context))) {
//
//            $icon = new attendance_password_icon($sess->studentpassword, $sess->id);
//
////            if ($sess->includeqrcode == 1||$sess->rotateqrcode == 1) {
////                $icon->includeqrcode = 1;
////            } else {
////                $icon->includeqrcode = 0;
////            }
//
//            $actions .= $this->render($icon);
//        }

        $date = userdate($sess->sessdate, get_string('strftimedmyw', 'attendance'));
        $time = $this->construct_time($sess->sessdate, $sess->duration);
        if ($sess->lasttaken > 0) {
            if (has_capability('mod/attendance:changeattendances', $sessdata->att->context)) {
                $url = $sessdata->url_take($sess->id, $sess->groupid);
                $title = get_string('changeattendance', 'attendance');

                $date = html_writer::link($url, $date, array('title' => $title));
                $time = html_writer::link($url, $time, array('title' => $title));

                $actions .= $this->output->action_icon($url, new pix_icon('redo', $title, 'attendance'));
            } else {
                $date = '<i>' . $date . '</i>';
                $time = '<i>' . $time . '</i>';
            }
        } else {
            if (has_capability('mod/attendance:takeattendances', $sessdata->att->context)) {
                $url = $sessdata->url_take($sess->id, $sess->groupid);
                $title = get_string('takeattendance', 'attendance');
                $actions .= $this->output->action_icon($url, new pix_icon('t/go', $title));
            }
        }
        global $USER;

        $admins = get_admins();
        $isadmin = false;
        foreach ($admins as $admin){
            if($admin->id === $USER->id){
                $isadmin = true;
                break;
            }
        }

        if (has_capability('mod/attendance:manageattendances', $sessdata->att->context)) {
            if(time() > $sess->sessdate + $sess->duration && !$isadmin){
                $attr = array('onclick' => 'return false;',
                    'style' => 'cursor: no-drop');
                $url = $sessdata->url_sessions($sess->id, mod_attendance_sessions_page_params::ACTION_UPDATE);
                $title = get_string('editsession', 'attendance');
                $actions .= $this->output->action_icon($url, new pix_icon('t/edit', $title,'moodle',array('class'=>'oldsession')),null,$attr);

                $url = $sessdata->url_sessions($sess->id, mod_attendance_sessions_page_params::ACTION_DELETE);
                $title = get_string('deletesession', 'attendance');
                $actions .= $this->output->action_icon($url, new pix_icon('t/delete', $title,'moodle',array('class'=>'oldsession')),null,$attr);
            }else{
                $url = $sessdata->url_sessions($sess->id, mod_attendance_sessions_page_params::ACTION_UPDATE);
                $title = get_string('editsession', 'attendance');
                $actions .= $this->output->action_icon($url, new pix_icon('t/edit', $title));

                $url = $sessdata->url_sessions($sess->id, mod_attendance_sessions_page_params::ACTION_DELETE);
                $title = get_string('deletesession', 'attendance');
                $actions .= $this->output->action_icon($url, new pix_icon('t/delete', $title));
            }

        }

        return array('date' => $date, 'time' => $time, 'actions' => $actions);
    }

    /**
     * Render session manage control.
     *
     * @param attendance_manage_data $sessdata
     * @return string
     */
    protected function render_sess_manage_control(attendance_manage_data $sessdata) {
        $table = new html_table();
        $table->attributes['class'] = ' ';
        $table->width = '100%';
        $table->align = array('left', 'right');

        $table->data[0][] = $this->output->help_icon('hiddensessions', 'attendance',
                get_string('hiddensessions', 'attendance').': '.$sessdata->hiddensessionscount);

        if (has_capability('mod/attendance:manageattendances', $sessdata->att->context)) {
            if ($sessdata->hiddensessionscount > 0) {
                $attributes = array(
                        'type'  => 'submit',
                        'name'  => 'deletehiddensessions',
                        'class' => 'btn btn-secondary',
                        'value' => get_string('deletehiddensessions', 'attendance'));
                $table->data[1][] = html_writer::empty_tag('input', $attributes);
            }

            //hd981
            $options = array(mod_attendance_sessions_page_params::ACTION_CHANGE_DURATION => get_string('changeduration', 'attendance'));
//            $options = array(mod_attendance_sessions_page_params::ACTION_DELETE_SELECTED => get_string('delete'),
//                mod_attendance_sessions_page_params::ACTION_CHANGE_DURATION => get_string('changeduration', 'attendance'));

            $controls = html_writer::select($options, 'action');
            $attributes = array(
                    'type'  => 'submit',
                    'name'  => 'ok',
                    'value' => get_string('ok'),
                    'class' => 'btn btn-secondary');
            $controls .= html_writer::empty_tag('input', $attributes);
        } else {
            $controls = get_string('youcantdo', 'attendance'); // You can't do anything.
        }
        $table->data[0][] = $controls;

        return html_writer::table($table);
    }

    /**
     * Render take data.
     *
     * @param attendance_take_data $takedata
     * @return string
     */
    protected function render_attendance_take_data(attendance_take_data $takedata) {
        //var_dump($takedata->sessioninfo);die();

        user_preference_allow_ajax_update('mod_attendance_statusdropdown', PARAM_TEXT);

        $controls = $this->render_attendance_take_controls($takedata);
        $table = html_writer::start_div('no-overflow');
        if ($takedata->pageparams->viewmode == mod_attendance_take_page_params::SORTED_LIST) {
            $table .= $this->render_attendance_take_list($takedata);
        } else {
            $table .= $this->render_attendance_take_grid($takedata);
        }
        $table .= html_writer::input_hidden_params($takedata->url(array('sesskey' => sesskey(),
                                                                        'page' => $takedata->pageparams->page,
                                                                        'perpage' => $takedata->pageparams->perpage)));
        $table .= html_writer::end_div();

        if((has_capability('mod/attendance:viewsummaryreports', $takedata->att->context) && time() > $takedata->sessioninfo->sessdate + $takedata->sessioninfo->duration) || time() <= $takedata->sessioninfo->sessdate + $takedata->sessioninfo->duration){
            $params = array(
                'type'  => 'submit',
                'class' => 'btn btn-primary',
                'value' => get_string('save', 'attendance'));
            $table .= html_writer::tag('center', html_writer::empty_tag('input', $params));
            $table = html_writer::tag('form', $table, array('method' => 'post', 'action' => $takedata->url_path(),
                'id' => 'attendancetakeform'));
        }else{
            $table .= html_writer::tag('script','
            $(document).ready(function() {
            $(".checkstatus").attr("disabled", "disabled");
            $(\'select[name="setallstatus-select"]\').attr("disabled", "disabled");
            });
            ');
        }


        foreach ($takedata->statuses as $status) {
            $sessionstats[$status->id] = 0;
        }
        // Calculate the sum of statuses for each user.
        $sessionstats[] = array();
        foreach ($takedata->sessionlog as $userlog) {
            foreach ($takedata->statuses as $status) {
                if ($userlog->statusid == $status->id && in_array($userlog->studentid, array_keys($takedata->users))) {
                    $sessionstats[$status->id]++;
                }
            }
        }

        $icon = array( 1=> '<i class="fa fa-check-circle" style="color: green" aria-hidden="true"></i>',
            2=>'<i class="fa fa-user-plus" style="color: blue" aria-hidden="true"></i>',
            3=>'<i class="fa fa-clock-o" style="color:orange;" aria-hidden="true"></i>',
            4=>'<i class="fa fa-times-circle" style="color: red" aria-hidden="true"></i>');


        $statsoutput = '<br/>';
        foreach ($takedata->statuses as $status) {
            $statsoutput .= "$status->description ( ".$icon[$status->id] ." ) = ".$sessionstats[$status->id]." <br/>";
        }

        return $controls.$table.$statsoutput;
    }

    /**
     * Render take controls.
     *
     * @param attendance_take_data $takedata
     * @return string
     */
    protected function render_attendance_take_controls(attendance_take_data $takedata) {

        $urlparams = array('id' => $takedata->cm->id,
            'sessionid' => $takedata->pageparams->sessionid,
            'grouptype' => $takedata->pageparams->grouptype);
        $url = new moodle_url('/mod/attendance/import/marksessions.php', $urlparams);
//        $return = $this->output->single_button($url, get_string('uploadattendance', 'attendance'));

        $table = new html_table();
        $table->attributes['class'] = ' ';

        $table->data[0][] = $this->construct_take_session_info($takedata);
        $table->data[0][] = $this->construct_take_controls($takedata);

        $return = $this->output->container(html_writer::table($table), 'generalbox takecontrols');
        return $return;
    }

    /**
     * Construct take session info.
     *
     * @param attendance_take_data $takedata
     * @return string
     */
    private function construct_take_session_info(attendance_take_data $takedata) {
        $sess = $takedata->sessioninfo;
        $date = userdate($sess->sessdate, get_string('strftimedate'));
        $starttime = attendance_strftimehm($sess->sessdate);
        $endtime = attendance_strftimehm($sess->sessdate + $sess->duration);
        $time = html_writer::tag('nobr', $starttime . ($sess->duration > 0 ? ' - ' . $endtime : ''));
        $sessinfo = $date.' '.$time;
        $sessinfo .= html_writer::empty_tag('br');
        $sessinfo .= html_writer::empty_tag('br');
        $sessinfo .= $sess->description;

        return $sessinfo;
    }

    /**
     * Construct take controls.
     *
     * @param attendance_take_data $takedata
     * @return string
     */
    private function construct_take_controls(attendance_take_data $takedata) {

        $controls = '';
        $context = context_module::instance($takedata->cm->id);
        $group = 0;
        if ($takedata->pageparams->grouptype != mod_attendance_structure::SESSION_COMMON) {
            $group = $takedata->pageparams->grouptype;
        } else {
            if ($takedata->pageparams->group) {
                $group = $takedata->pageparams->group;
            }
        }

        if (!empty($takedata->cm->groupingid)) {
            if ($group == 0) {
                $groups = array_keys(groups_get_all_groups($takedata->cm->course, 0, $takedata->cm->groupingid, 'g.id'));
            } else {
                $groups = $group;
            }
            $users = get_users_by_capability($context, 'mod/attendance:canbelisted',
                            'u.id, u.firstname, u.lastname, u.email',
                            '', '', '', $groups,
                            '', false, true);
            $totalusers = count($users);
        } else {
            $totalusers = count_enrolled_users($context, 'mod/attendance:canbelisted', $group);
        }
        $usersperpage = $takedata->pageparams->perpage;
        if (!empty($takedata->pageparams->page) && $takedata->pageparams->page && $totalusers && $usersperpage) {
            $controls .= html_writer::empty_tag('br');
            $numberofpages = ceil($totalusers / $usersperpage);

            if ($takedata->pageparams->page > 1) {
                $controls .= html_writer::link($takedata->url(array('page' => $takedata->pageparams->page - 1)),
                                                              $this->output->larrow());
            }
            $a = new stdClass();
            $a->page = $takedata->pageparams->page;
            $a->numpages = $numberofpages;
            $text = get_string('pageof', 'attendance', $a);
            $controls .= html_writer::tag('span', $text,
                                          array('class' => 'attbtn'));
            if ($takedata->pageparams->page < $numberofpages) {
                $controls .= html_writer::link($takedata->url(array('page' => $takedata->pageparams->page + 1,
                            'perpage' => $takedata->pageparams->perpage)), $this->output->rarrow());
            }
        }

        if ($takedata->pageparams->grouptype == mod_attendance_structure::SESSION_COMMON and
                ($takedata->groupmode == VISIBLEGROUPS or
                ($takedata->groupmode and has_capability('moodle/site:accessallgroups', $context)))) {
            $controls .= groups_print_activity_menu($takedata->cm, $takedata->url(), true);
        }

        $controls .= html_writer::empty_tag('br');

        $options = array(
            mod_attendance_take_page_params::SORTED_LIST   => get_string('sortedlist', 'attendance'),
            mod_attendance_take_page_params::SORTED_GRID   => get_string('sortedgrid', 'attendance'));
        $select = new single_select($takedata->url(), 'viewmode', $options, $takedata->pageparams->viewmode, null);
        $select->set_label(get_string('viewmode', 'attendance'));
        $select->class = 'singleselect inline';
        $controls .= $this->output->render($select);

        if ($takedata->pageparams->viewmode == mod_attendance_take_page_params::SORTED_LIST) {
            $options = array(
                    0 => get_string('donotusepaging', 'attendance'),
                   get_config('attendance', 'resultsperpage') => get_config('attendance', 'resultsperpage'));
            $select = new single_select($takedata->url(), 'perpage', $options, $takedata->pageparams->perpage, null);
            $select->class = 'singleselect inline';
            $controls .= $this->output->render($select);
        }

        if ($takedata->pageparams->viewmode == mod_attendance_take_page_params::SORTED_GRID) {
            $options = array (1 => '1 '.get_string('column', 'attendance'), '2 '.get_string('columns', 'attendance'),
                                   '3 '.get_string('columns', 'attendance'), '4 '.get_string('columns', 'attendance'),
                                   '5 '.get_string('columns', 'attendance'), '6 '.get_string('columns', 'attendance'),
                                   '7 '.get_string('columns', 'attendance'), '8 '.get_string('columns', 'attendance'),
                                   '9 '.get_string('columns', 'attendance'), '10 '.get_string('columns', 'attendance'));
            $select = new single_select($takedata->url(), 'gridcols', $options, $takedata->pageparams->gridcols, null);
            $select->class = 'singleselect inline';
            $controls .= $this->output->render($select);
        }

        if (isset($takedata->sessions4copy) && count($takedata->sessions4copy) > 0) {
            $controls .= html_writer::empty_tag('br');
            $controls .= html_writer::empty_tag('br');

            $options = array();
            foreach ($takedata->sessions4copy as $sess) {
                $start = attendance_strftimehm($sess->sessdate);
                $end = $sess->duration ? ' - '.attendance_strftimehm($sess->sessdate + $sess->duration) : '';
                $options[$sess->id] = $start . $end;
            }
            $select = new single_select($takedata->url(array(), array('copyfrom')), 'copyfrom', $options);
            $select->set_label(get_string('copyfrom', 'attendance'));
            $select->class = 'singleselect inline';
            $controls .= $this->output->render($select);
        }

        return $controls;
    }

    /**
     * get statusdropdown
     *
     * @return \single_select
     */
    private function statusdropdown() {
        $pref = get_user_preferences('mod_attendance_statusdropdown');
        if (empty($pref)) {
            $pref = 'unselected';
        }
        $options = array('all' => get_string('statusall', 'attendance'),
            'unselected' => get_string('statusunselected', 'attendance'));

        $select = new \single_select(new \moodle_url('/'), 'setallstatus-select', $options,
            $pref, null, 'setallstatus-select');
        $select->label = get_string('setallstatuses', 'attendance');

        return $select;
    }

    /**
     * Render take list.
     *
     * @param attendance_take_data $takedata
     * @return string
     */
    protected function render_attendance_take_list(attendance_take_data $takedata) {
        global $CFG;
        $table = new html_table();
        $table->width = '0%';
        $table->head = array(
                '#',
                $this->construct_fullname_head($takedata)
            );
        $table->align = array('left', 'left');
        $table->size = array('20px', '');
        $table->wrap[1] = 'nowrap';
        // Check if extra useridentity fields need to be added.
        $extrasearchfields = array();
        if (!empty($CFG->showuseridentity) && has_capability('moodle/site:viewuseridentity', $takedata->att->context)) {
            $extrasearchfields = explode(',', $CFG->showuseridentity);
        }
        foreach ($extrasearchfields as $field) {
            $table->head[] = get_string($field);
            $table->align[] = 'left';
        }

        //$actions = $this->output->action_icon('', new pix_icon('t/check', ''));

        $table->head[] = 'Feedback';
        $table->align[] = 'center';
        $table->head[] = '';
        $table->align[] = 'center';
        $table->head[] = 'Status';
        $table->align[] = 'center';
        $table->head[] = '';

//        foreach ($takedata->statuses as $st) {
//            $table->head[] = html_writer::link("#", $st->acronym, array('id' => 'checkstatus'.$st->id,
//                'title' => get_string('setallstatusesto', 'attendance', $st->description)));
//            $table->align[] = 'center';
//            $table->size[] = '20px';
//            // JS to select all radios of this status and prevent default behaviour of # link.
//            $this->page->requires->js_amd_inline("
//                require(['jquery'], function($) {
//                    $('#checkstatus".$st->id."').click(function(e) {
//                     if ($('select[name=\"setallstatus-select\"] option:selected').val() == 'all') {
//                            $('#attendancetakeform').find('.st".$st->id."').prop('checked', true);
//                            M.util.set_user_preference('mod_attendance_statusdropdown','all');
//                        }
//                        else {
//                            $('#attendancetakeform').find('input:indeterminate.st".$st->id."').prop('checked', true);
//                            M.util.set_user_preference('mod_attendance_statusdropdown','unselected');
//                        }
//                        e.preventDefault();
//                    });
//                });");
//
//        }

//        $table->head[] = get_string('remarks', 'attendance');
//        $table->align[] = 'center';
//        $table->size[] = '20px';
//        $table->attributes['class'] = 'generaltable takelist';

        // Show a 'select all' row of radio buttons.
        $row = new html_table_row();
        $row->attributes['class'] = 'setallstatusesrow';
        foreach ($extrasearchfields as $field) {
            $row->cells[] = '';
        }
        $row->cells[] = '';
        $row->cells[] = '';

        $cell = new html_table_cell(html_writer::div($this->output->render($this->statusdropdown()), 'setallstatuses'));

        $cell->colspan = 2;
        $row->cells[] = $cell;

        $actions = new html_table_cell(html_writer::div("
<link href='https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css' rel='stylesheet' />
<style>
	.checkstatus{
		font-family: fontAwesome;
		border: none;
        -moz-appearance: none;
        -webkit-appearance: none;
        padding: 5px;
        padding-left: 8px;
        background: transparent;
	}
	i{
	cursor: pointer;
	}
		.popover {
  position: absolute;
  top: 0;
  left: 0;
  z-index: 1060;
  display: none;
  max-width: 276px;
  padding: 1px;
  font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
  font-style: normal;
  font-weight: 400;
  line-height: 1.42857143;
  line-break: auto;
  text-align: left;
  text-align: start;
  text-decoration: none;
  text-shadow: none;
  text-transform: none;
  letter-spacing: normal;
  word-break: normal;
  word-spacing: normal;
  word-wrap: normal;
  white-space: normal;
  font-size: 14px;
  background-color: #fff;
  background-clip: padding-box;
  border: 1px solid #ccc;
  border: 1px solid rgba(0, 0, 0, 0.2);
  border-radius: 6px;
  -webkit-box-shadow: 0 5px 10px rgba(0, 0, 0, 0.2);
  box-shadow: 0 5px 10px rgba(0, 0, 0, 0.2);
}
.popover.top {
  margin-top: -10px;
}
.popover.right {
  margin-left: 10px;
}
.popover.bottom {
  margin-top: 10px;
}
.popover.left {
  margin-left: -10px;
}
.popover > .arrow {
  border-width: 11px;
}
.popover > .arrow,
.popover > .arrow:after {
  position: absolute;
  display: block;
  width: 0;
  height: 0;
  border-color: transparent;
  border-style: solid;
}
.popover > .arrow:after {
  border-width: 10px;
}
.popover.top > .arrow {
  bottom: -11px;
  left: 50%;
  margin-left: -11px;
  border-top-color: #999999;
  border-top-color: rgba(0, 0, 0, 0.25);
  border-bottom-width: 0;
}
.popover.top > .arrow:after {
  bottom: 1px;
  margin-left: -10px;
  border-top-color: #fff;
  border-bottom-width: 0;
}
.popover.right > .arrow {
  top: 50%;
  left: -11px;
  margin-top: -11px;
  border-right-color: #999999;
  border-right-color: rgba(0, 0, 0, 0.25);
  border-left-width: 0;
}
.popover.right > .arrow:after {
  bottom: -10px;
  left: 1px;
  border-right-color: #fff;
  border-left-width: 0;
}
.popover.bottom > .arrow {
  top: -11px;
  left: 50%;
  margin-left: -11px;
  border-top-width: 0;
  border-bottom-color: #999999;
  border-bottom-color: rgba(0, 0, 0, 0.25);
}
.popover.bottom > .arrow:after {
  top: 1px;
  margin-left: -10px;
  border-top-width: 0;
  border-bottom-color: #fff;
}
.popover.left > .arrow {
  top: 50%;
  right: -11px;
  margin-top: -11px;
  border-right-width: 0;
  border-left-color: #999999;
  border-left-color: rgba(0, 0, 0, 0.25);
}
.popover.left > .arrow:after {
  right: 1px;
  bottom: -10px;
  border-right-width: 0;
  border-left-color: #fff;
}
.popover-title {
  padding: 8px 14px;
  margin: 0;
  font-size: 14px;
  background-color: #f7f7f7;
  border-bottom: 1px solid #ebebeb;
  border-radius: 5px 5px 0 0;
}
.popover-content {
  padding: 9px 14px;
}
</style>
        <select id='radiocheckstatus' class='checkstatus' style='font-size: 20px;cursor: pointer' name='setallstatuses' data-init-value=1>
                    <option style='color: green' value='1' selected>&#xf058;</option>
                    <option style='color: blue' value=2>&#xf234;</option>
                    <option style='color: orange' value=3>&#xf017;</option>
                    <option style='color: red' value=4>&#xf057;</option>
        </select>
        <link href='https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css' rel='stylesheet' />
<script src='https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.js'></script>
<script src='https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js'></script>
        <script>
                $(document).ready(function() {
            $('#radiocheckstatus').css('color','green');
            $('#radiocheckstatus').change(function() {
                var current = $('#radiocheckstatus').val();
                if (current == '1') {
                    $('#radiocheckstatus').css('color','green');
                }else if(current == '2'){
                    $('#radiocheckstatus').css('color','blue');
                }else if(current == '3'){
                    $('#radiocheckstatus').css('color','orange');
                }else{
                    $('#radiocheckstatus').css('color','red');
                }
            });
        });
</script>", 'setallstatuses'));

        // JS to select all radios of this status and prevent default behaviour of # link.
            $this->page->requires->js_amd_inline("
                require(['jquery'], function($) {
                    $('#radiocheckstatus').change(function(e) {
                        var op = $('#radiocheckstatus').val();
                        if ($('select[name=\"setallstatus-select\"] option:selected').val() == 'all') {
                        $('#attendancetakeform').find('.select_status').each(function() {
                                    $(this).val(op);
                if (op == '1') {
                    $(this).css('color','green');
                }else if(op == '2'){
                    $(this).css('color','blue');
                }else if(op == '3'){
                    $(this).css('color','orange');
                }else{
                    $(this).css('color','red');
                }
                         });
                        }
                        else {
                         $('#attendancetakeform').find('.select_status').each(function() {
                            if ($(this).val() === '0'){
                                 $(this).val(op);
                if (op == '1') {
                    $(this).css('color','green');
                }else if(op == '2'){
                    $(this).css('color','blue');
                }else if(op == '3'){
                    $(this).css('color','orange');
                }else{
                    $(this).css('color','red');
                }
                            }
                         });
                        }
                    });
                });"
            );
            $row->cells[] = $actions;

                      //$actions = $this->output->action_icon('', new pix_icon('t/check', ''));
                       //$row->cells[] = $actions;
 //       foreach ($takedata->statuses as $st) {
  //              $actions = $this->output->action_icon('', new pix_icon('t/check', ''));
 //               $row->cells[] = $actions;
//            $attribs = array(
//                'id' => 'radiocheckstatus'.$st->id,
//                'type' => 'radio',
//                'title' => get_string('setallstatusesto', 'attendance', $st->description),
//                'name' => 'setallstatuses',
//                'class' => "st{$st->id}",
//            );
//            $row->cells[] = html_writer::empty_tag('input', $attribs);
//            // Select all radio buttons of the same status.
//            $this->page->requires->js_amd_inline("
//                require(['jquery'], function($) {
//                    $('#radiocheckstatus".$st->id."').click(function(e) {
//                        if ($('select[name=\"setallstatus-select\"] option:selected').val() == 'all') {
//                            $('#attendancetakeform').find('.st".$st->id."').prop('checked', true);
//                            M.util.set_user_preference('mod_attendance_statusdropdown','all');
//                        }
//                        else {
//                            $('#attendancetakeform').find('input:indeterminate.st".$st->id."').prop('checked', true);
//                            M.util.set_user_preference('mod_attendance_statusdropdown','unselected');
//                        }
//                    });
//                });");
//        }
        $row->cells[] = '';
        $table->data[] = $row;

        $i = 0;
        $b = new local_webservices_frontend();
        $img = $b->get_images_by_course_id((int)$takedata->cm->course);
        foreach ($takedata->users as $user) {
            $i++;
            $row = new html_table_row();
            $row->cells[] = $i;
            $fullname = html_writer::link($takedata->url_view(array('studentid' => $user->id)), fullname($user));
            //$fullname = $this->user_picture($user).$fullname; // Show different picture if it is a temporary user.

            $celltext = '';
            if($img[$user->id] != null){

//                $img[0]['image_front'] = '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';
                $celltext = html_writer::img($img[$user->id]->image_front,'',array(
                    'width' => '35',
                    'height' => '35',
                    'class' => 'userpicture defaultuserpic',
                ));
            }else{
                $celltext = $this->user_picture($user);  // Show different picture if it is a temporary user.
            }
            $fullname = $celltext.$fullname;

            $a = new local_webservices_frontend();
            $action_logs = $a->get_action_logs($takedata->sessioninfo->id);
            $ucdata = $this->construct_take_user_controls($takedata, $user,$action_logs);
            if (array_key_exists('warning', $ucdata)) {
                $fullname .= html_writer::empty_tag('br');
                $fullname .= $ucdata['warning'];
            }
            $row->cells[] = $fullname;
            foreach ($extrasearchfields as $field) {
                $row->cells[] = $user->$field;
            }

            if (array_key_exists('colspan', $ucdata)) {
                $cell = new html_table_cell($ucdata['text']);
                $cell->colspan = $ucdata['colspan'];
                $row->cells[] = $cell;
            } else {
                $row->cells = array_merge($row->cells, $ucdata['text']);
            }

            if (array_key_exists('class', $ucdata)) {
                $row->attributes['class'] = $ucdata['class'];
            }

            $table->data[] = $row;
        }

        return html_writer::table($table);
    }

    /**
     * Render take grid.
     *
     * @param attendance_take_data $takedata
     * @return string
     */
    protected function render_attendance_take_grid(attendance_take_data $takedata) {
        $table = new html_table();
        for ($i = 0; $i < $takedata->pageparams->gridcols; $i++) {
            $table->align[] = 'center';
            $table->size[] = '110px';
        }
        $table->attributes['class'] = 'generaltable takegrid';
        $table->headspan = $takedata->pageparams->gridcols;

        $head = array();
        $head[] = html_writer::div($this->output->render($this->statusdropdown()) . "
<link href='https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css' rel='stylesheet' />
<style>
	.checkstatus{
		font-family: fontAwesome;
		border: none;
        -moz-appearance: none;
        -webkit-appearance: none;
        padding: 5px;
        background: transparent;
        text-align: center;
	}
		i{
	cursor: pointer;
	}
	.popover {
  position: absolute;
  top: 0;
  left: 0;
  z-index: 1060;
  display: none;
  max-width: 276px;
  padding: 1px;
  font-family: \"Helvetica Neue\", Helvetica, Arial, sans-serif;
  font-style: normal;
  font-weight: 400;
  line-height: 1.42857143;
  line-break: auto;
  text-align: left;
  text-align: start;
  text-decoration: none;
  text-shadow: none;
  text-transform: none;
  letter-spacing: normal;
  word-break: normal;
  word-spacing: normal;
  word-wrap: normal;
  white-space: normal;
  font-size: 14px;
  background-color: #fff;
  background-clip: padding-box;
  border: 1px solid #ccc;
  border: 1px solid rgba(0, 0, 0, 0.2);
  border-radius: 6px;
  -webkit-box-shadow: 0 5px 10px rgba(0, 0, 0, 0.2);
  box-shadow: 0 5px 10px rgba(0, 0, 0, 0.2);
}
.popover.top {
  margin-top: -10px;
}
.popover.right {
  margin-left: 10px;
}
.popover.bottom {
  margin-top: 10px;
}
.popover.left {
  margin-left: -10px;
}
.popover > .arrow {
  border-width: 11px;
}
.popover > .arrow,
.popover > .arrow:after {
  position: absolute;
  display: block;
  width: 0;
  height: 0;
  border-color: transparent;
  border-style: solid;
}
.popover > .arrow:after {
  border-width: 10px;
}
.popover.top > .arrow {
  bottom: -11px;
  left: 50%;
  margin-left: -11px;
  border-top-color: #999999;
  border-top-color: rgba(0, 0, 0, 0.25);
  border-bottom-width: 0;
}
.popover.top > .arrow:after {
  bottom: 1px;
  margin-left: -10px;
  border-top-color: #fff;
  border-bottom-width: 0;
}
.popover.right > .arrow {
  top: 50%;
  left: -11px;
  margin-top: -11px;
  border-right-color: #999999;
  border-right-color: rgba(0, 0, 0, 0.25);
  border-left-width: 0;
}
.popover.right > .arrow:after {
  bottom: -10px;
  left: 1px;
  border-right-color: #fff;
  border-left-width: 0;
}
.popover.bottom > .arrow {
  top: -11px;
  left: 50%;
  margin-left: -11px;
  border-top-width: 0;
  border-bottom-color: #999999;
  border-bottom-color: rgba(0, 0, 0, 0.25);
}
.popover.bottom > .arrow:after {
  top: 1px;
  margin-left: -10px;
  border-top-width: 0;
  border-bottom-color: #fff;
}
.popover.left > .arrow {
  top: 50%;
  right: -11px;
  margin-top: -11px;
  border-right-width: 0;
  border-left-color: #999999;
  border-left-color: rgba(0, 0, 0, 0.25);
}
.popover.left > .arrow:after {
  right: 1px;
  bottom: -10px;
  border-right-width: 0;
  border-left-color: #fff;
}
.popover-title {
  padding: 8px 14px;
  margin: 0;
  font-size: 14px;
  background-color: #f7f7f7;
  border-bottom: 1px solid #ebebeb;
  border-radius: 5px 5px 0 0;
}
.popover-content {
  padding: 9px 14px;
}
</style>
        <select id='radiocheckstatus' class='checkstatus' style='font-size: 20px; cursor: pointer' name='setallstatuses' data-init-value=1>
                    <option style='color: green' value='1'  selected>&#xf058;</option>
                    <option style='color: blue' value=2 >&#xf234;</option>
                    <option style='color: orangered' value=3 >&#xf017;</option>
                    <option style='color: red' value=4 >&#xf057;</option>
        </select>
        <link href='https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css' rel='stylesheet' />
<script src='https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.js'></script>
<script src='https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js'></script>
        <script>
                $(document).ready(function() {
            $('#radiocheckstatus').css('color','green');
            $('#radiocheckstatus').change(function() {
                var current = $('#radiocheckstatus').val();
                if (current == '1') {
                    $('#radiocheckstatus').css('color','green');
                }else if(current == '2'){
                    $('#radiocheckstatus').css('color','blue');
                }else if(current == '3'){
                    $('#radiocheckstatus').css('color','orange');
                }else{
                    $('#radiocheckstatus').css('color','red');
                }
            });
        });
</script>",'setallstatuses' );

        // JS to select all radios of this status and prevent default behaviour of # link.
        $this->page->requires->js_amd_inline("
                require(['jquery'], function($) {
                    $('#radiocheckstatus').change(function(e) {
                        var op = $('#radiocheckstatus').val();
                        if ($('select[name=\"setallstatus-select\"] option:selected').val() == 'all') {
                        $('#attendancetakeform').find('.select_status').each(function() {
                                    $(this).val(op);
                if (op == '1') {
                    $(this).css('color','green');
                }else if(op == '2'){
                    $(this).css('color','blue');
                }else if(op == '3'){
                    $(this).css('color','orange');
                }else{
                    $(this).css('color','red');
                }
                         });
                        }
                        else {
                         $('#attendancetakeform').find('.select_status').each(function() {
                            if ($(this).val() === '0'){
                                 $(this).val(op);
                if (op == '1') {
                    $(this).css('color','green');
                }else if(op == '2'){
                    $(this).css('color','blue');
                }else if(op == '3'){
                    $(this).css('color','orange');
                }else{
                    $(this).css('color','red');
                }
                            }
                         });
                        }
                    });
                });"
        );
        $table->head[] = implode('&nbsp;&nbsp;', $head);

        $i = 0;
        $row = new html_table_row();
        $b = new local_webservices_frontend();
        $img = $b->get_images_by_course_id((int)$takedata->cm->course);
        foreach ($takedata->users as $user) {
            if($img[$user->id] != null){

                $celltext = html_writer::img($img[$user->id]->image_front,'',array(
                    'width' => '100',
                    'height' => '100',
                    'class' => 'userpicture defaultuserpic',
                ));
            }else{
                $celltext = $this->user_picture($user, array('size' => 100));  // Show different picture if it is a temporary user.
            }
  //          $celltext = $this->user_picture($user, array('size' => 100));  // Show different picture if it is a temporary user.
//            $celltext = html_writer::img('data:image/jpeg;base64,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','',array(
//                'width' => '100',
//                'height' => '100',
//                'class' => 'userpicture defaultuserpic',
//            ));
            $celltext .= html_writer::empty_tag('br');
            $fullname = html_writer::link($takedata->url_view(array('studentid' => $user->id)), fullname($user));
            $celltext .= html_writer::tag('span', $fullname, array('class' => 'fullname'));
            $celltext .= html_writer::empty_tag('br');
            $a = new local_webservices_frontend();
            $action_logs = $a->get_action_logs($takedata->sessioninfo->id);
            $ucdata = $this->construct_take_user_controls($takedata, $user,$action_logs);
            $celltext .= is_array($ucdata['text']) ? implode('', $ucdata['text']) : $ucdata['text'];
            if (array_key_exists('warning', $ucdata)) {
                $celltext .= html_writer::empty_tag('br');
                $celltext .= $ucdata['warning'];
            }

            $cell = new html_table_cell($celltext);
            if (array_key_exists('class', $ucdata)) {
                $cell->attributes['class'] = $ucdata['class'];
            }
            $row->cells[] = $cell;

            $i++;
            if ($i % $takedata->pageparams->gridcols == 0) {
                $table->data[] = $row;
                $row = new html_table_row();
            }
        }
        if ($i % $takedata->pageparams->gridcols > 0) {
            $table->data[] = $row;
        }

        return html_writer::table($table);
    }

    /**
     * Construct full name.
     *
     * @param stdClass $data
     * @return string
     */
    private function construct_fullname_head($data) {
        global $CFG;

        $url = $data->url();
        if ($data->pageparams->sort == ATT_SORT_LASTNAME) {
            $url->param('sort', ATT_SORT_FIRSTNAME);
            $firstname = html_writer::link($url, get_string('firstname'));
            $lastname = get_string('lastname');
        } else if ($data->pageparams->sort == ATT_SORT_FIRSTNAME) {
            $firstname = get_string('firstname');
            $url->param('sort', ATT_SORT_LASTNAME);
            $lastname = html_writer::link($url, get_string('lastname'));
        } else {
            $firstname = html_writer::link($data->url(array('sort' => ATT_SORT_FIRSTNAME)), get_string('firstname'));
            $lastname = html_writer::link($data->url(array('sort' => ATT_SORT_LASTNAME)), get_string('lastname'));
        }

        if ($CFG->fullnamedisplay == 'lastname firstname') {
            $fullnamehead = "$lastname / $firstname";
        } else {
            $fullnamehead = "$firstname / $lastname ";
        }

        return $fullnamehead;
    }

    /**
     * Construct take user controls.
     *
     * @param attendance_take_data $takedata
     * @param stdClass $user
     * @return array
     */
    private function construct_take_user_controls(attendance_take_data $takedata, $user,$action_logs) {
        $a = new local_webservices_frontend();
        $feedbacks = $a->get_feedbacks($takedata->sessioninfo->id);
        $celldata = array();
        if ($user->enrolmentend and $user->enrolmentend < $takedata->sessioninfo->sessdate) {
            $celldata['text'] = get_string('enrolmentend', 'attendance', userdate($user->enrolmentend, '%d.%m.%Y'));
            $celldata['colspan'] = count($takedata->statuses) + 1;
            $celldata['class'] = 'userwithoutenrol';
        } else if (!$user->enrolmentend and $user->enrolmentstatus == ENROL_USER_SUSPENDED) {
            // No enrolmentend and ENROL_USER_SUSPENDED.
            $celldata['text'] = get_string('enrolmentsuspended', 'attendance');
            $celldata['colspan'] = count($takedata->statuses) + 1;
            $celldata['class'] = 'userwithoutenrol';
        } else {
            if ($takedata->updatemode and !array_key_exists($user->id, $takedata->sessionlog)) {
                $celldata['class'] = 'userwithoutdata';
            }

            $celldata['text'] = array();
//            foreach ($takedata->statuses as $st) {
//                $params = array(
//                        'type'  => 'radio',
//                        'name'  => 'user'.$user->id,
//                        'class' => 'st'.$st->id,
//                        'value' => $st->id);
//                if (array_key_exists($user->id, $takedata->sessionlog) and $st->id == $takedata->sessionlog[$user->id]->statusid) {
//                    $params['checked'] = '';
//                }
//
         //       $input = html_writer::empty_tag('input', $params);
//
        //        if ($takedata->pageparams->viewmode == mod_attendance_take_page_params::SORTED_GRID) {
          //          $input = html_writer::tag('nobr', $input . $st->acronym);
           //     }
//
  //              $celldata['text'][] = $input;
//            }
            $temp = false;
//            $a = new local_webservices_external();
//            $action_logs = $a->get_action_logs($takedata->sessioninfo->id);

            $flag = false;
            $feeds = '';
            foreach ($feedbacks as $feed){
                if($feed->usertaken === $user->id){
                    $flag = true;
                    $feeds .= '<p>Time: ' .date('d-m-Y H:i:s',$feed->timetaken). '<br>User taken: '.$feed->usertaken_name.'<br>Detail: '.$feed->description.'</p>';
                }

            }
            if($flag){
                $content_feedbacks = html_writer::div(
                    $feeds,
                    'no-overflow'
                );
                $b = $takedata->cm->id; $c = $takedata->sessioninfo->id;
                $url = new moodle_url('/mod/attendance/feedback.php');
                $celldata['text'][] = html_writer::div("
            <a href='$url?id=$b&userid=$user->id&sessionid=$c'><i class='fa fa-exclamation-triangle' id='feedback$user->id' aria-hidden='true' style='cursor: pointer; margin-top:13px' data-init-value=1 data-toggle='popover' data-container='body' data-trigger='hover' data-placement='auto' title='Feedback logs' data-html='true' data-content='$content_feedbacks'></i></a>
            <script>
                $(document).ready(function(){
                    $('#feedback$user->id').parents('tr').css('color','red');
                });
            </script>
            ");
            }else{
                $celldata['text'][] = '';
            }
            $celldata['text'][] = '';

            foreach ($takedata->statuses as $st) {
                if (array_key_exists($user->id, $takedata->sessionlog) and $st->id == $takedata->sessionlog[$user->id]->statusid) {
                    $temp = true;
                    $logs = '';
                    foreach ($action_logs as $log){
                        if($log->userbetaken === $user->id){
                            $logs .= '<p>Time: ' .date('d-m-Y H:i:s',$log->timetaken). '<br>User taken: '.$log->usertaken_name.'<br>Detail: '.$log->description.'</p>';
                        }

                    }
                    $content_logs = html_writer::div(
                        $logs,
                        'no-overflow'
                    );
                    $url_checkin = (new moodle_url('/mod/attendance/checkin_image.php?id='.$takedata->cm->id.'&userid='.$user->id.'&sessionid='.$takedata->sessioninfo->id))->__toString();
                    $content = "
        <select id='select_status$user->id' class='select_status checkstatus' style='font-size: 20px; cursor: pointer' name='user$user->id' data-init-value=1 data-toggle='popover' data-container='body' data-trigger='hover' data-placement='auto' title='Action logs' data-html='true' data-content='$content_logs' >
                    <option style='color: green' value=1 selected >&#xf058;</option>
                    <option style='color: blue' value=2>&#xf234;</option>
                    <option style='color: orange' value=3 >&#xf017;</option>
                    <option style='color: red' value=4 >&#xf057;</option>
        </select>
                <script>
                $(document).ready(function() {
                $('#select_status$user->id').val($st->id);
                var current = $('#select_status$user->id').val();
                if (current == '1') {
                    $('#select_status$user->id').css('color','green');
                }else if(current == '2'){
                    $('#select_status$user->id').css('color','blue');
                }else if(current == '3'){
                    $('#select_status$user->id').css('color','orange');
                }else{
                    $('#select_status$user->id').css('color','red');
                }
            $('#select_status$user->id').change(function() {
                var current = $('#select_status$user->id').val();
                if (current == '1') {
                    $('#select_status$user->id').css('color','green');
                }else if(current == '2'){
                    $('#select_status$user->id').css('color','blue');
                }else if(current == '3'){
                    $('#select_status$user->id').css('color','orange');
                }else{
                    $('#select_status$user->id').css('color','red');
                }
            });
                $('[data-toggle=\"popover\"]').popover();
        });
</script>";

                    if($takedata->sessionlog[$user->id]->isonlinecheckin){
                        $content .= "<a href=$url_checkin><i class=\"fa fa-paper-plane-o\" aria-hidden=\"true\"></i></a>
<script>
                $(document).ready(function() {
                    $('#select_status$user->id').parent().parent().parent().css({'color':'green',
                    'font-weight':'bold'});
                    $('#select_status$user->id').parent().parent().parent().children('.cell.c1').children('a').css('color','green');
        });
</script>";
                    }
                    $input = html_writer::div($content);

                    if ($takedata->pageparams->viewmode == mod_attendance_take_page_params::SORTED_GRID) {
                          $input = html_writer::tag('nobr', $input);
                     }
                    $celldata['text'][] = $input;
                    break;
                }
            }
            if($temp == false){
                $input = html_writer::div("
        <select id='select_status$user->id' class='select_status checkstatus' style='font-size: 20px;' name='user$user->id' data-init-value=1>
                    <option style='color: #d7c8cd' value=0  selected> &#xf05e; </option>
                    <option style='color: green' value=1 >&#xf058;</option>
                    <option style='color: blue' value=2 >&#xf234;</option>
                    <option style='color: orange' value=3 >&#xf017;</option>
                    <option style='color: red' value=4 >&#xf057;</option>
        </select>
                <script>
                $(document).ready(function() {                  
            $('#select_status$user->id').css('color','#d7c8cd');
            $('#select_status$user->id').change(function() {
                var current = $('#select_status$user->id').val();
                if (current == '1') {
                    $('#select_status$user->id').css('color','green');
                }else if(current == '2'){
                    $('#select_status$user->id').css('color','blue');
                }else if(current == '3'){
                    $('#select_status$user->id').css('color','orange');
                }else if(current == '4'){
                    $('#select_status$user->id').css('color','red');
                }else{
                    $('#select_status$user->id').css('color','#d7c8cd');
                }
            });
        });
                
</script>");
                if ($takedata->pageparams->viewmode == mod_attendance_take_page_params::SORTED_GRID) {
                    $input = html_writer::tag('nobr', $input);
                }
                $celldata['text'][] = $input;
            }

            $params = array(
                    'type'  => 'text',
                'style' => 'display:none',
                    'name'  => 'remarks'.$user->id,
                    'maxlength' => 255);
            if (array_key_exists($user->id, $takedata->sessionlog)) {
                $params['value'] = $takedata->sessionlog[$user->id]->remarks;
            }





            $celldata['text'][] = html_writer::empty_tag('input', $params);








            if ($user->enrolmentstart > $takedata->sessioninfo->sessdate + $takedata->sessioninfo->duration) {
                $celldata['warning'] = get_string('enrolmentstart', 'attendance',
                                                  userdate($user->enrolmentstart, '%H:%M %d.%m.%Y'));
                $celldata['class'] = 'userwithoutenrol';
            }
        }

        return $celldata;
    }

    /**
     * Construct take session controls.
     *
     * @param attendance_take_data $takedata
     * @param stdClass $user
     * @return array
     */
    private function construct_take_session_controls(attendance_take_data $takedata, $user) {
        $celldata = array();
        $celldata['remarks'] = '';
        if ($user->enrolmentend and $user->enrolmentend < $takedata->sessioninfo->sessdate) {
            $celldata['text'] = get_string('enrolmentend', 'attendance', userdate($user->enrolmentend, '%d.%m.%Y'));
            $celldata['colspan'] = count($takedata->statuses) + 1;
            $celldata['class'] = 'userwithoutenrol';
        } else if (!$user->enrolmentend and $user->enrolmentstatus == ENROL_USER_SUSPENDED) {
            // No enrolmentend and ENROL_USER_SUSPENDED.
            $celldata['text'] = get_string('enrolmentsuspended', 'attendance');
            $celldata['colspan'] = count($takedata->statuses) + 1;
            $celldata['class'] = 'userwithoutenrol';
        } else {
            if ($takedata->updatemode and !array_key_exists($user->id, $takedata->sessionlog)) {
                $celldata['class'] = 'userwithoutdata';
            }

            $celldata['text'] = array();
            foreach ($takedata->statuses as $st) {
                $params = array(
                        'type'  => 'radio',
                        'name'  => 'user'.$user->id.'sess'.$takedata->sessioninfo->id,
                        'class' => 'st'.$st->id,
                        'value' => $st->id);
                if (array_key_exists($user->id, $takedata->sessionlog) and $st->id == $takedata->sessionlog[$user->id]->statusid) {
                    $params['checked'] = '';
                }

                $input = html_writer::empty_tag('input', $params);

                if ($takedata->pageparams->viewmode == mod_attendance_take_page_params::SORTED_GRID) {
                    $input = html_writer::tag('nobr', $input . $st->acronym);
                }

                $celldata['text'][] = $input;
            }
            $params = array(
                    'type'  => 'text',
                    'style' => 'display:none',
                    'name'  => 'remarks'.$user->id.'sess'.$takedata->sessioninfo->id,
                    'maxlength' => 255);
            if (array_key_exists($user->id, $takedata->sessionlog)) {
                $params['value'] = $takedata->sessionlog[$user->id]->remarks;
            }
            $input = html_writer::empty_tag('input', $params);
            if ($takedata->pageparams->viewmode == mod_attendance_take_page_params::SORTED_GRID) {
                $input = html_writer::empty_tag('br').$input;
            }
            $celldata['remarks'] = $input;

            if ($user->enrolmentstart > $takedata->sessioninfo->sessdate + $takedata->sessioninfo->duration) {
                $celldata['warning'] = get_string('enrolmentstart', 'attendance',
                                                  userdate($user->enrolmentstart, '%H:%M %d.%m.%Y'));
                $celldata['class'] = 'userwithoutenrol';
            }
        }

        return $celldata;
    }

    /**
     * Render header.
     *
     * @param mod_attendance_header $header
     * @return string
     */
    protected function render_mod_attendance_header(mod_attendance_header $header) {
        if (!$header->should_render()) {
            return '';
        }

        $attendance = $header->get_attendance();

        $heading = format_string($header->get_title(), false, ['context' => $attendance->context]);
        $o = $this->output->heading($heading);

        $o .= $this->output->box_start('generalbox boxaligncenter', 'intro');
        $o .= format_module_intro('attendance', $attendance, $attendance->cm->id);
        $o .= $this->output->box_end();

        return $o;
    }

    /**
     * Render user data.
     *
     * @param attendance_user_data $userdata
     * @return string
     */
    protected function render_attendance_user_data(attendance_user_data $userdata) {
        global $USER;

        $o = $this->render_user_report_tabs($userdata);

        if ($USER->id == $userdata->user->id ||
            $userdata->pageparams->mode === mod_attendance_view_page_params::MODE_ALL_SESSIONS) {

            $o .= $this->construct_user_data($userdata);

        } else {

            $table = new html_table();

            $table->attributes['class'] = 'userinfobox';
            $table->colclasses = array('left side', '');
            // Show different picture if it is a temporary user.
            $table->data[0][] = $this->user_picture($userdata->user, array('size' => 100));
            $table->data[0][] = $this->construct_user_data($userdata);

            $o .= html_writer::table($table);
        }

        return $o;
    }

    /**
     * Render user report tabs.
     *
     * @param attendance_user_data $userdata
     * @return string
     */
    protected function render_user_report_tabs(attendance_user_data $userdata) {
        $tabs = array();

        $tabs[] = new tabobject(mod_attendance_view_page_params::MODE_THIS_COURSE,
                        $userdata->url()->out(true, array('mode' => mod_attendance_view_page_params::MODE_THIS_COURSE)),
                        get_string('thiscourse', 'attendance'));

        // Skip the 'all courses' and 'all sessions' tabs for 'temporary' users.
        if ($userdata->user->type == 'standard') {
//            $tabs[] = new tabobject(mod_attendance_view_page_params::MODE_ALL_COURSES,
//                            $userdata->url()->out(true, array('mode' => mod_attendance_view_page_params::MODE_ALL_COURSES)),
//                            get_string('allcourses', 'attendance'));
//            $tabs[] = new tabobject(mod_attendance_view_page_params::MODE_ALL_SESSIONS,
//                            $userdata->url()->out(true, array('mode' => mod_attendance_view_page_params::MODE_ALL_SESSIONS)),
//                            get_string('allsessions', 'attendance'));
        }

        return print_tabs(array($tabs), $userdata->pageparams->mode, null, null, true);
    }

    /**
     * Construct user data.
     *
     * @param attendance_user_data $userdata
     * @return string
     */
    private function construct_user_data(attendance_user_data $userdata) {
        global $USER;
        $o = '';
        if ($USER->id <> $userdata->user->id) {
            $o = html_writer::tag('h2', fullname($userdata->user));
        }

        if ($userdata->pageparams->mode == mod_attendance_view_page_params::MODE_THIS_COURSE) {
            $o .= $this->render_attendance_filter_controls($userdata->filtercontrols);
            $o .= $this->construct_user_sessions_log($userdata);
            $o .= html_writer::empty_tag('hr');
            $o .= construct_user_data_stat($userdata->summary->get_all_sessions_summary_for($userdata->user->id),
                $userdata->pageparams->view);
        }
// else if ($userdata->pageparams->mode == mod_attendance_view_page_params::MODE_ALL_SESSIONS) {
//            $allsessions = $this->construct_user_allsessions_log($userdata);
//            $o .= html_writer::start_div('allsessionssummary');
//            $o .= html_writer::start_div('float-left');
//            $o .= html_writer::start_div('float-left');
//            $o .= $this->user_picture($userdata->user, array('size' => 100, 'class' => 'userpicture float-left'));
//            $o .= html_writer::end_div();
//            $o .= html_writer::start_div('float-right');
//            $o .= $allsessions->summary;
//            $o .= html_writer::end_div();
//            $o .= html_writer::end_div();
//            $o .= html_writer::start_div('float-right');
//            $o .= $this->render_attendance_filter_controls($userdata->filtercontrols);
//            $o .= html_writer::end_div();
//            $o .= html_writer::end_div();
//            $o .= $allsessions->detail;
//        }
            else {
            $table = new html_table();
            $table->head  = array(get_string('course'),
                get_string('pluginname', 'mod_attendance'),
                get_string('sessionscompleted', 'attendance'),
                get_string('pointssessionscompleted', 'attendance'),
                get_string('percentagesessionscompleted', 'attendance'));
            $table->align = array('left', 'left', 'center', 'center', 'center');
            $table->colclasses = array('colcourse', 'colatt', 'colsessionscompleted',
                                       'colpointssessionscompleted', 'colpercentagesessionscompleted');

            $table2 = clone($table); // Duplicate table for ungraded sessions.
            $totalattendance = 0;
            $totalpercentage = 0;
            foreach ($userdata->coursesatts as $ca) {
                $row = new html_table_row();
                $courseurl = new moodle_url('/course/view.php', array('id' => $ca->courseid));
                $row->cells[] = html_writer::link($courseurl, $ca->coursefullname);
                $attendanceurl = new moodle_url('/mod/attendance/view.php', array('id' => $ca->cmid,
                                                                                      'studentid' => $userdata->user->id,
                                                                                      'view' => ATT_VIEW_ALL));
                $row->cells[] = html_writer::link($attendanceurl, $ca->attname);
                $usersummary = new stdClass();
                if (isset($userdata->summary[$ca->attid])) {
                    $usersummary = $userdata->summary[$ca->attid]->get_all_sessions_summary_for($userdata->user->id);

                    $row->cells[] = $usersummary->numtakensessions;
                    $row->cells[] = $usersummary->pointssessionscompleted;
                    if (empty($usersummary->numtakensessions)) {
                        $row->cells[] = '-';
                    } else {
                        $row->cells[] = $usersummary->percentagesessionscompleted;
                    }

                }
                if (empty($ca->attgrade)) {
                    $table2->data[] = $row;
                } else {
                    $table->data[] = $row;
                    if ($usersummary->numtakensessions > 0) {
                        $totalattendance++;
                        $totalpercentage = $totalpercentage + format_float($usersummary->takensessionspercentage * 100);
                    }
                }
            }
            $row = new html_table_row();
            if (empty($totalattendance)) {
                $average = '-';
            } else {
                $average = format_float($totalpercentage / $totalattendance).'%';
            }

            $col = new html_table_cell(get_string('averageattendancegraded', 'mod_attendance'));
            $col->attributes['class'] = 'averageattendance';
            $col->colspan = 4;

            $col2 = new html_table_cell($average);
            $col2->style = 'text-align: center';
            $row->cells = array($col, $col2);
            $table->data[] = $row;

            if (!empty($table2->data) && !empty($table->data)) {
                // Print graded header if both tables are being shown.
                $o .= html_writer::div("<h3>".get_string('graded', 'mod_attendance')."</h3>");
            }
            if (!empty($table->data)) {
                // Don't bother printing the table if no sessions are being shown.
                $o .= html_writer::table($table);
            }

            if (!empty($table2->data)) {
                // Don't print this if it doesn't contain any data.
                $o .= html_writer::div("<h3>".get_string('ungraded', 'mod_attendance')."</h3>");
                $o .= html_writer::table($table2);
            }
        }

        return $o;
    }

    /**
     * Construct user sessions log.
     *
     * @param attendance_user_data $userdata
     * @return string
     */
    private function construct_user_sessions_log(attendance_user_data $userdata) {
        global $USER;
        $context = context_module::instance($userdata->filtercontrols->cm->id);

        $shortform = false;
        if ($USER->id == $userdata->user->id) {
            // This is a user viewing their own stuff - hide non-relevant columns.
            $shortform = true;
        }

        $table = new html_table();
        $table->attributes['class'] = 'generaltable attwidth boxaligncenter';
        $table->head = array();
        $table->align = array();
        $table->size = array();
        $table->colclasses = array();
        if (!$shortform) {
            $table->head[] = get_string('sessiontypeshort', 'attendance');
            $table->align[] = '';
            $table->size[] = '1px';
            $table->colclasses[] = '';
        }
        $table->head[] = get_string('date');
        $table->head[] = get_string('description', 'attendance');
        $table->head[] = get_string('status', 'attendance');
        $table->head[] = get_string('points', 'attendance');
        //$table->head[] = get_string('remarks', 'attendance');
        //hd981
        $table->head[] = 'Timein';
        $table->head[] = 'Timeout';

        $table->align = array_merge($table->align, array('', 'left', 'center', 'center', 'center','center','center'));
        $table->colclasses = array_merge($table->colclasses, array('datecol', 'desccol', 'statuscol', 'pointscol', 'timein','timeout'));
        $table->size = array_merge($table->size, array('1px', '*', '*', '1px', '*','*'));

        if (has_capability('mod/attendance:takeattendances', $context)) {
            $table->head[] = get_string('action');
            $table->align[] = 'center';
            $table->size[] = '';
        }else{
            $table->head[] = 'Feedback';
            $table->align[] = 'center';
            $table->size[] = '';
        }

        $statussetmaxpoints = attendance_get_statusset_maxpoints($userdata->statuses);

        $icons = array(
            1=>'<i class="fa fa-check-circle" style="color: green;transform: scale(1);cursor: pointer;" aria-hidden="true"></i>',
            2=>'<i class="fa fa-user-plus" style="color: blue;transform: scale(1);cursor: pointer;" aria-hidden="true"></i>',
            3=>'<i class="fa fa-clock-o" style="color:orange;transform: scale(1);cursor: pointer;" aria-hidden="true"></i>',
            4=>'<i class="fa fa-times-circle" style="color: red;transform: scale(1);cursor: pointer;" aria-hidden="true"></i>');

        $i = 0;
        foreach ($userdata->sessionslog as $sess) {
            $i++;

            $row = new html_table_row();
            if (!$shortform) {
                if ($sess->groupid) {
                    $sessiontypeshort = get_string('group') . ': ' . $userdata->groups[$sess->groupid]->name;
                } else {
                    $sessiontypeshort = get_string('commonsession', 'attendance');
                }

                $row->cells[] = html_writer::tag('nobr', $sessiontypeshort);
            }
            $row->cells[] = userdate($sess->sessdate, get_string('strftimedmyw', 'attendance')) .
             " ". $this->construct_time($sess->sessdate, $sess->duration);
            $row->cells[] = $sess->description;

            if (!empty($sess->statusid)) {
                $status = $userdata->statuses[$sess->statusid];
                $iconscheck = array(
                    1=>'<i id="live'.$sess->id.'" class="fa fa-check-circle" style="color: green;transform: scale(1);cursor: pointer;" aria-hidden="true"></i>',
                    2=>'<i id="live'.$sess->id.'" class="fa fa-user-plus" style="color: blue;transform: scale(1);cursor: pointer;" aria-hidden="true"></i>',
                    3=>'<i id="live'.$sess->id.'" class="fa fa-clock-o" style="color:orange;transform: scale(1);cursor: pointer;" aria-hidden="true"></i>',
                    4=>'<i id="live'.$sess->id.'" class="fa fa-times-circle" style="color: red;transform: scale(1);cursor: pointer;" aria-hidden="true"></i>');
                if($sess->onlinetime != null && time() >= $sess->onlinetime &&  time() <= $sess->onlinetime + $sess->onlineduration){
                    $url_s = (new moodle_url('/mod/attendance/checkin.php?id='.$userdata->filtercontrols->cm->id.'&sessionid='.$sess->id))->__toString();
                    $row->cells[] = '<a href='.$url_s.'>'.$iconscheck[$status->id].'</a>
                   <script src="https://code.jquery.com/jquery-3.5.0.js"></script>
      </script>
      <style>
      .scaleAnimate{
      transform: scale(1.5)!important;
      }
</style>
                    <script>
$(document).ready(function(){
  setInterval(function(){ 
      $("#live'.$sess->id.'").toggleClass("scaleAnimate");
}, 500);
});

</script>';
                }else{
                    $row->cells[] = $icons[$status->id];
                }

                //$status = $icon[$userdata->statuses[$sess->statusid]];
                //$row->cells[] = $status->description;
                $row->cells[] = format_float($status->grade, 1, true, true) . ' / ' .
                                    format_float($statussetmaxpoints[$status->setnumber], 1, true, true);
                //$row->cells[] = $sess->remarks;
                //hd981

                $row->cells[] = date("H:i:s",$sess->timein);
                if($sess->timeout !== null){
                    $row->cells[] = date("H:i:s",$sess->timeout);
                }else{
                    $row->cells[] = "?";
                }

            } else if (($sess->sessdate + $sess->duration) < $userdata->user->enrolmentstart) {
                $cell = new html_table_cell(get_string('enrolmentstart', 'attendance',
                                            userdate($userdata->user->enrolmentstart, '%d.%m.%Y')));
                $cell->colspan = 4;
                $row->cells[] = $cell;
            } else if ($userdata->user->enrolmentend and $sess->sessdate > $userdata->user->enrolmentend) {
                $cell = new html_table_cell(get_string('enrolmentend', 'attendance',
                                            userdate($userdata->user->enrolmentend, '%d.%m.%Y')));
                $cell->colspan = 4;
                $row->cells[] = $cell;
            } else {
                list($canmark, $reason) = attendance_can_student_mark($sess, false);
                if ($canmark) {
                    if ($sess->rotateqrcode == 1) {
                        $url = new moodle_url('/mod/attendance/attendance.php');
                        $output = html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sessid',
                                'value' => $sess->id));
                        $output .= html_writer::empty_tag('input', array('type' => 'text', 'name' => 'qrpass',
                                'placeholder' => "Enter password"));
                        $output .= html_writer::empty_tag('input', array('type' => 'submit',
                                'value' => get_string('submit'),
                                'class' => 'btn btn-secondary'));
                        $cell = new html_table_cell(html_writer::tag('form', $output,
                            array('action' => $url->out(), 'method' => 'get')));
                    } else {
                        // Student can mark their own attendance.
                        // URL to the page that lets the student modify their attendance.
                        $url = new moodle_url('/mod/attendance/attendance.php',
                                array('sessid' => $sess->id, 'sesskey' => sesskey()));
                        $cell = new html_table_cell(html_writer::link($url, get_string('submitattendance', 'attendance')));
                    }
                    $cell->colspan = 3;
                    $row->cells[] = $cell;
                } else { // Student cannot mark their own attendace.
                    $url_s = (new moodle_url('/mod/attendance/checkin.php?id='.$userdata->filtercontrols->cm->id.'&sessionid='.$sess->id))->__toString();
                    if($sess->onlinetime != null && time() >= $sess->onlinetime &&  time() <= $sess->onlinetime + $sess->onlineduration){
                        $row->cells[] = '<a href='.$url_s.'><i id="live'.$sess->id.'" class="fa fa-bullseye" style="transform: scale(1); color: red; cursor: pointer" aria-hidden="true"></i></a>
                    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.js"></script>
                          <script src = "https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.3/jquery-ui.min.js">
      </script>
      <style>
      .scaleAnimate{
      transform: scale(1.5)!important;
      }
</style>
                    <script>
$(document).ready(function(){
  setInterval(function(){ 
      $("#live'.$sess->id.'").toggleClass("scaleAnimate");
}, 500);
});

</script>';
                    }else{
                        $row->cells[] = '?';
                    }
                    $row->cells[] = '? / ' . format_float($statussetmaxpoints[$sess->statusset], 1, true, true);
                    $row->cells[] = '?';
                    $row->cells[] = '?';
//                    $row->cells[] = '';
                }
            }

            if (has_capability('mod/attendance:takeattendances', $context)) {
                $params = array('id' => $userdata->filtercontrols->cm->id,
                    'sessionid' => $sess->id,
                    'grouptype' => $sess->groupid);
                $url = new moodle_url('/mod/attendance/take.php', $params);
                $icon = $this->output->pix_icon('redo', get_string('changeattendance', 'attendance'), 'attendance');
                $row->cells[] = html_writer::link($url, $icon);
            }else{
                $id = $userdata->filtercontrols->cm->id;

                $url_f = (new moodle_url('/mod/attendance/view.php?id='.$id))->__toString();

                $row->cells[] = html_writer::div("
                <i id=$sess->id class='fa fa-hand-paper-o' aria-hidden='true' style='cursor: pointer'></i>
                <div id='feedback$sess->id' style='display: none;'>
                    <div class='f-content'>
                        <textarea id='description$sess->id' aria-label='Add a comment...' cols='0' style='color: grey;'></textarea>
                    </div>
                    <div class='f-footer'>
                        <a id='send-button$sess->id' href='javascript:void(0)'>Sent</a>
                        <span>|</span>
                        <a id='cancel-button$sess->id' href='javascript:void(0)'>Cancel</a>
                    </div>
                </div>
                <script
  src=\"https://code.jquery.com/jquery-3.6.0.js\"
  integrity=\"sha256-H+K7U5CnXl1h5ywQfKtSj8PCmoN9aaq30gDh27Xc0jk=\"
  crossorigin=\"anonymous\"></script>
                <script>
                
                $(document).ready(function(){                   
                    $('#$sess->id').click(function() {
                        $('#feedback$sess->id').show();
                        $(this).hide();  
                    });
                    $('#send-button$sess->id').click(function() {
                        let des = $('#description$sess->id').val();
                        $.post('$url_f',{
                            sessionid: $sess->id,
                                description:des })
                          .done(function(data) {
                              $('#feedback$sess->id').hide();
                              $('#$sess->id').show();
                            alert('Send feedback successfully!');
                          });
                    });
                    $('#cancel-button$sess->id').click(function() {
                        $('#$sess->id').show();
                        $('#feedback$sess->id').hide();
                    });
                } );
                </script>
                ");
            }

            $table->data[] = $row;
        }

        return html_writer::table($table);
    }

    /**
     * Construct table showing all sessions, not limited to current course.
     *
     * @param attendance_user_data $userdata
     * @return string
     */
    private function construct_user_allsessions_log(attendance_user_data $userdata) {
        global $USER;

        $allsessions = new stdClass();

        $shortform = false;
        if ($USER->id == $userdata->user->id) {
            // This is a user viewing their own stuff - hide non-relevant columns.
            $shortform = true;
        }

        $groupby = $userdata->pageparams->groupby;

        $table = new html_table();
        $table->attributes['class'] = 'generaltable attwidth boxaligncenter allsessions';
        $table->head = array();
        $table->align = array();
        $table->size = array();
        $table->colclasses = array();
        $colcount = 0;
        $summarywidth = 0;

        // If grouping by date, we need some form of date up front.
        // Only need course column if we are not using course to group
        // (currently date is only option which does not use course).
        if ($groupby === 'date') {
            $table->head[] = '';
            $table->align[] = 'left';
            $table->colclasses[] = 'grouper';
            $table->size[] = '1px';

            $table->head[] = get_string('date');
            $table->align[] = 'left';
            $table->colclasses[] = 'datecol';
            $table->size[] = '1px';
            $colcount++;

            $table->head[] = get_string('course');
            $table->align[] = 'left';
            $table->colclasses[] = 'colcourse';
            $colcount++;
        } else {
            $table->head[] = '';
            $table->align[] = 'left';
            $table->colclasses[] = 'grouper';
            $table->size[] = '1px';
            if ($groupby === 'activity') {
                $table->head[] = '';
                $table->align[] = 'left';
                $table->colclasses[] = 'grouper';
                $table->size[] = '1px';
            }
        }

        // Need activity column unless we are using activity to group.
        if ($groupby !== 'activity') {
            $table->head[] = get_string('pluginname', 'mod_attendance');
            $table->align[] = 'left';
            $table->colclasses[] = 'colcourse';
            $table->size[] = '*';
            $colcount++;
        }

        // If grouping by date, it belongs up front rather than here.
        if ($groupby !== 'date') {
            $table->head[] = get_string('date');
            $table->align[] = 'left';
            $table->colclasses[] = 'datecol';
            $table->size[] = '1px';
            $colcount++;
        }

        // Use "session" instead of "description".
        $table->head[] = get_string('session', 'attendance');
        $table->align[] = 'left';
        $table->colclasses[] = 'desccol';
        $table->size[] = '*';
        $colcount++;

        if (!$shortform) {
            $table->head[] = get_string('sessiontypeshort', 'attendance');
            $table->align[] = '';
            $table->size[] = '*';
            $table->colclasses[] = '';
            $colcount++;
        }

        if (!empty($USER->attendanceediting)) {
            $table->head[] = get_string('status', 'attendance');
            $table->align[] = 'center';
            $table->colclasses[] = 'statuscol';
            $table->size[] = '*';
            $colcount++;
            $summarywidth++;

            $table->head[] = get_string('remarks', 'attendance');
            $table->align[] = 'center';
            $table->colclasses[] = 'remarkscol';
            $table->size[] = '*';
            $colcount++;
            $summarywidth++;
        } else {
            $table->head[] = get_string('status', 'attendance');
            $table->align[] = 'center';
            $table->colclasses[] = 'statuscol';
            $table->size[] = '*';
            $colcount++;
            $summarywidth++;

            $table->head[] = get_string('points', 'attendance');
            $table->align[] = 'center';
            $table->colclasses[] = 'pointscol';
            $table->size[] = '1px';
            $colcount++;
            $summarywidth++;

            $table->head[] = get_string('remarks', 'attendance');
            $table->align[] = 'center';
            $table->colclasses[] = 'remarkscol';
            $table->size[] = '*';
            $colcount++;
            $summarywidth++;
        }

        $statusmaxpoints = array();
        foreach ($userdata->statuses as $attid => $attstatuses) {
            $statusmaxpoints[$attid] = attendance_get_statusset_maxpoints($attstatuses);
        }

        $lastgroup = array(null, null);
        $groups = array();
        $stats = array(
            'course' => array(),
            'activity' => array(),
            'date' => array(),
            'overall' => array(
                'points' => 0,
                'maxpointstodate' => 0,
                'maxpoints' => 0,
                'pcpointstodate' => null,
                'pcpoints' => null,
                'statuses' => array()
            )
        );
        $group = null;
        if ($userdata->sessionslog) {
            foreach ($userdata->sessionslog as $sess) {
                if ($groupby === 'date') {
                    $weekformat = date("YW", $sess->sessdate);
                    if ($weekformat != $lastgroup[0]) {
                        if ($group !== null) {
                            array_push($groups, $group);
                        }
                        $group = array();
                        $lastgroup[0] = $weekformat;
                    }
                    if (!array_key_exists($weekformat, $stats['date'])) {
                        $stats['date'][$weekformat] = array(
                            'points' => 0,
                            'maxpointstodate' => 0,
                            'maxpoints' => 0,
                            'pcpointstodate' => null,
                            'pcpoints' => null,
                            'statuses' => array()
                        );
                    }
                    $statussetmaxpoints = $statusmaxpoints[$sess->attendanceid];
                    // Ensure all possible acronyms for current sess's statusset are available as
                    // keys in status array for period.
                    //
                    // A bit yucky because we can't tell whether we've seen statusset before, and
                    // we usually will have, so much wasted spinning.
                    foreach ($userdata->statuses[$sess->attendanceid] as $attstatus) {
                        if ($attstatus->setnumber === $sess->statusset) {
                            if (!array_key_exists($attstatus->acronym, $stats['date'][$weekformat]['statuses'])) {
                                $stats['date'][$weekformat]['statuses'][$attstatus->acronym] =
                                    array('count' => 0, 'description' => $attstatus->description);
                            }
                            if (!array_key_exists($attstatus->acronym, $stats['overall']['statuses'])) {
                                $stats['overall']['statuses'][$attstatus->acronym] =
                                    array('count' => 0, 'description' => $attstatus->description);
                            }
                        }
                    }
                    // The array_key_exists check is for hidden statuses.
                    if (isset($sess->statusid) && array_key_exists($sess->statusid, $userdata->statuses[$sess->attendanceid])) {
                        $status = $userdata->statuses[$sess->attendanceid][$sess->statusid];
                        $stats['date'][$weekformat]['statuses'][$status->acronym]['count']++;
                        $stats['date'][$weekformat]['points'] += $status->grade;
                        $stats['date'][$weekformat]['maxpointstodate'] += $statussetmaxpoints[$sess->statusset];
                        $stats['overall']['statuses'][$status->acronym]['count']++;
                        $stats['overall']['points'] += $status->grade;
                        $stats['overall']['maxpointstodate'] += $statussetmaxpoints[$sess->statusset];
                    }
                    $stats['date'][$weekformat]['maxpoints'] += $statussetmaxpoints[$sess->statusset];
                    $stats['overall']['maxpoints'] += $statussetmaxpoints[$sess->statusset];
                } else {
                    // By course and perhaps activity.
                    if (
                        ($sess->courseid != $lastgroup[0]) ||
                        ($groupby === 'activity' && $sess->cmid != $lastgroup[1])
                    ) {
                        if ($group !== null) {
                            array_push($groups, $group);
                        }
                        $group = array();
                        $lastgroup[0] = $sess->courseid;
                        $lastgroup[1] = $sess->cmid;
                    }
                    if (!array_key_exists($sess->courseid, $stats['course'])) {
                        $stats['course'][$sess->courseid] = array(
                            'points' => 0,
                            'maxpointstodate' => 0,
                            'maxpoints' => 0,
                            'pcpointstodate' => null,
                            'pcpoints' => null,
                            'statuses' => array()
                        );
                    }
                    $statussetmaxpoints = $statusmaxpoints[$sess->attendanceid];
                    // Ensure all possible acronyms for current sess's statusset are available as
                    // keys in status array for course
                    //
                    // A bit yucky because we can't tell whether we've seen statusset before, and
                    // we usually will have, so much wasted spinning.
                    foreach ($userdata->statuses[$sess->attendanceid] as $attstatus) {
                        if ($attstatus->setnumber === $sess->statusset) {
                            if (!array_key_exists($attstatus->acronym, $stats['course'][$sess->courseid]['statuses'])) {
                                $stats['course'][$sess->courseid]['statuses'][$attstatus->acronym] =
                                    array('count' => 0, 'description' => $attstatus->description);
                            }
                            if (!array_key_exists($attstatus->acronym, $stats['overall']['statuses'])) {
                                $stats['overall']['statuses'][$attstatus->acronym] =
                                    array('count' => 0, 'description' => $attstatus->description);
                            }
                        }
                    }
                    // The array_key_exists check is for hidden statuses.
                    if (isset($sess->statusid) && array_key_exists($sess->statusid, $userdata->statuses[$sess->attendanceid])) {
                        $status = $userdata->statuses[$sess->attendanceid][$sess->statusid];
                        $stats['course'][$sess->courseid]['statuses'][$status->acronym]['count']++;
                        $stats['course'][$sess->courseid]['points'] += $status->grade;
                        $stats['course'][$sess->courseid]['maxpointstodate'] += $statussetmaxpoints[$sess->statusset];
                        $stats['overall']['statuses'][$status->acronym]['count']++;
                        $stats['overall']['points'] += $status->grade;
                        $stats['overall']['maxpointstodate'] += $statussetmaxpoints[$sess->statusset];
                    }
                    $stats['course'][$sess->courseid]['maxpoints'] += $statussetmaxpoints[$sess->statusset];
                    $stats['overall']['maxpoints'] += $statussetmaxpoints[$sess->statusset];

                    if (!array_key_exists($sess->cmid, $stats['activity'])) {
                        $stats['activity'][$sess->cmid] = array(
                            'points' => 0,
                            'maxpointstodate' => 0,
                            'maxpoints' => 0,
                            'pcpointstodate' => null,
                            'pcpoints' => null,
                            'statuses' => array()
                        );
                    }
                    $statussetmaxpoints = $statusmaxpoints[$sess->attendanceid];
                    // Ensure all possible acronyms for current sess's statusset are available as
                    // keys in status array for period
                    //
                    // A bit yucky because we can't tell whether we've seen statusset before, and
                    // we usually will have, so much wasted spinning.
                    foreach ($userdata->statuses[$sess->attendanceid] as $attstatus) {
                        if ($attstatus->setnumber === $sess->statusset) {
                            if (!array_key_exists($attstatus->acronym, $stats['activity'][$sess->cmid]['statuses'])) {
                                $stats['activity'][$sess->cmid]['statuses'][$attstatus->acronym] =
                                    array('count' => 0, 'description' => $attstatus->description);
                            }
                            if (!array_key_exists($attstatus->acronym, $stats['overall']['statuses'])) {
                                $stats['overall']['statuses'][$attstatus->acronym] =
                                    array('count' => 0, 'description' => $attstatus->description);
                            }
                        }
                    }
                    // The array_key_exists check is for hidden statuses.
                    if (isset($sess->statusid) && array_key_exists($sess->statusid, $userdata->statuses[$sess->attendanceid])) {
                        $status = $userdata->statuses[$sess->attendanceid][$sess->statusid];
                        $stats['activity'][$sess->cmid]['statuses'][$status->acronym]['count']++;
                        $stats['activity'][$sess->cmid]['points'] += $status->grade;
                        $stats['activity'][$sess->cmid]['maxpointstodate'] += $statussetmaxpoints[$sess->statusset];
                        $stats['overall']['statuses'][$status->acronym]['count']++;
                        $stats['overall']['points'] += $status->grade;
                        $stats['overall']['maxpointstodate'] += $statussetmaxpoints[$sess->statusset];
                    }
                    $stats['activity'][$sess->cmid]['maxpoints'] += $statussetmaxpoints[$sess->statusset];
                    $stats['overall']['maxpoints'] += $statussetmaxpoints[$sess->statusset];
                }
                array_push($group, $sess);
            }
            array_push($groups, $group);
        }

        $points = $stats['overall']['points'];
        $maxpoints = $stats['overall']['maxpointstodate'];
        $summarytable = new html_table();
        $summarytable->attributes['class'] = 'generaltable table-bordered table-condensed';
        $row = new html_table_row();
        $cell = new html_table_cell(get_string('allsessionstotals', 'attendance'));
        $cell->colspan = 2;
        $cell->header = true;
        $row->cells[] = $cell;
        $summarytable->data[] = $row;
        foreach ($stats['overall']['statuses'] as $acronym => $status) {
            $row = new html_table_row();
            $row->cells[] = $status['description'] . ":";
            $row->cells[] = $status['count'];
            $summarytable->data[] = $row;
        }

        $row = new html_table_row();
        if ($maxpoints !== 0) {
            $pctodate = format_float( $points * 100 / $maxpoints);
            $pointsinfo  = get_string('points', 'attendance') . ": " . $points . "/" . $maxpoints;
            $pointsinfo .= " (" . $pctodate . "%)";
        } else {
            $pointsinfo  = get_string('points', 'attendance') . ": " . $points . "/" . $maxpoints;
        }
        $pointsinfo .= " " . get_string('todate', 'attendance');
        $cell = new html_table_cell($pointsinfo);
        $cell->colspan = 2;
        $row->cells[] = $cell;
        $summarytable->data[] = $row;
        $allsessions->summary = html_writer::table($summarytable);

        $lastgroup = array(null, null);
        foreach ($groups as $group) {

            $statussetmaxpoints = $statusmaxpoints[$sess->attendanceid];

            // For use in headings etc.
            $sess = $group[0];

            if ($groupby === 'date') {
                $row = new html_table_row();
                $row->attributes['class'] = 'grouper';
                $cell = new html_table_cell();
                $cell->rowspan = count($group) + 2;
                $row->cells[] = $cell;
                $week = date("W", $sess->sessdate);
                $year = date("Y", $sess->sessdate);
                // ISO week starts on day 1, Monday.
                $weekstart = date_timestamp_get(date_isodate_set(date_create(), $year, $week, 1));
                $dmywformat = get_string('strftimedmyw', 'attendance');
                $cell = new html_table_cell(get_string('weekcommencing', 'attendance') . ": " . userdate($weekstart, $dmywformat));
                $cell->colspan = $colcount - $summarywidth;
                $cell->rowspan = 2;
                $cell->attributes['class'] = 'groupheading';
                $row->cells[] = $cell;
                $weekformat = date("YW", $sess->sessdate);
                $points = $stats['date'][$weekformat]['points'];
                $maxpoints = $stats['date'][$weekformat]['maxpointstodate'];
                if ($maxpoints !== 0) {
                    $pctodate = format_float( $points * 100 / $maxpoints);
                    $summary  = get_string('points', 'attendance') . ": " . $points . "/" . $maxpoints;
                    $summary .= " (" . $pctodate . "%)";
                } else {
                    $summary  = get_string('points', 'attendance') . ": " . $points . "/" . $maxpoints;
                }
                $summary .= " " . get_string('todate', 'attendance');
                $cell = new html_table_cell($summary);
                $cell->colspan = $summarywidth;
                $row->cells[] = $cell;
                $table->data[] = $row;
                $row = new html_table_row();
                $row->attributes['class'] = 'grouper';
                $summary = array();
                foreach ($stats['date'][$weekformat]['statuses'] as $acronym => $status) {
                    array_push($summary, html_writer::tag('b', $acronym) . $status['count']);
                }
                $cell = new html_table_cell(implode(" ", $summary));
                $cell->colspan = $summarywidth;
                $row->cells[] = $cell;
                $table->data[] = $row;
                $lastgroup[0] = date("YW", $weekstart);
            } else {
                if ($groupby === 'course' || $sess->courseid !== $lastgroup[0]) {
                    $row = new html_table_row();
                    $row->attributes['class'] = 'grouper';
                    $cell = new html_table_cell();
                    $cell->rowspan = count($group) + 2;
                    if ($groupby === 'activity') {
                        $headcell = $cell; // Keep ref to be able to adjust rowspan later.
                        $cell->rowspan += 2;
                        $row->cells[] = $cell;
                        $cell = new html_table_cell();
                        $cell->rowspan = 2;
                    }
                    $row->cells[] = $cell;
                    $courseurl = new moodle_url('/course/view.php', array('id' => $sess->courseid));
                    $cell = new html_table_cell(get_string('course', 'attendance') . ": " .
                        html_writer::link($courseurl, $sess->cname));
                    $cell->colspan = $colcount - $summarywidth;
                    $cell->rowspan = 2;
                    $cell->attributes['class'] = 'groupheading';
                    $row->cells[] = $cell;
                    $points = $stats['course'][$sess->courseid]['points'];
                    $maxpoints = $stats['course'][$sess->courseid]['maxpointstodate'];
                    if ($maxpoints !== 0) {
                        $pctodate = format_float( $points * 100 / $maxpoints);
                        $summary  = get_string('points', 'attendance') . ": " . $points . "/" . $maxpoints;
                        $summary .= " (" . $pctodate . "%)";
                    } else {
                        $summary  = get_string('points', 'attendance') . ": " . $points . "/" . $maxpoints;
                    }
                    $summary .= " " . get_string('todate', 'attendance');
                    $cell = new html_table_cell($summary);
                    $cell->colspan = $summarywidth;
                    $row->cells[] = $cell;
                    $table->data[] = $row;
                    $row = new html_table_row();
                    $row->attributes['class'] = 'grouper';
                    $summary = array();
                    foreach ($stats['course'][$sess->courseid]['statuses'] as $acronym => $status) {
                        array_push($summary, html_writer::tag('b', $acronym) . $status['count']);
                    }
                    $cell = new html_table_cell(implode(" ", $summary));
                    $cell->colspan = $summarywidth;
                    $row->cells[] = $cell;
                    $table->data[] = $row;
                }
                if ($groupby === 'activity') {
                    if ($sess->courseid === $lastgroup[0]) {
                        $headcell->rowspan += count($group) + 2;
                    }
                    $row = new html_table_row();
                    $row->attributes['class'] = 'grouper';
                    $cell = new html_table_cell();
                    $cell->rowspan = count($group) + 2;
                    $row->cells[] = $cell;
                    $attendanceurl = new moodle_url('/mod/attendance/view.php', array('id' => $sess->cmid,
                                                                                      'studentid' => $userdata->user->id,
                                                                                      'view' => ATT_VIEW_ALL));
                    $cell = new html_table_cell(get_string('pluginname', 'mod_attendance') .
                        ": " . html_writer::link($attendanceurl, $sess->attname));
                    $cell->colspan = $colcount - $summarywidth;
                    $cell->rowspan = 2;
                    $cell->attributes['class'] = 'groupheading';
                    $row->cells[] = $cell;
                    $points = $stats['activity'][$sess->cmid]['points'];
                    $maxpoints = $stats['activity'][$sess->cmid]['maxpointstodate'];
                    if ($maxpoints !== 0) {
                        $pctodate = format_float( $points * 100 / $maxpoints);
                        $summary  = get_string('points', 'attendance') . ": " . $points . "/" . $maxpoints;
                        $summary .= " (" . $pctodate . "%)";
                    } else {
                        $summary  = get_string('points', 'attendance') . ": " . $points . "/" . $maxpoints;
                    }
                    $summary .= " " . get_string('todate', 'attendance');
                    $cell = new html_table_cell($summary);
                    $cell->colspan = $summarywidth;
                    $row->cells[] = $cell;
                    $table->data[] = $row;
                    $row = new html_table_row();
                    $row->attributes['class'] = 'grouper';
                    $summary = array();
                    foreach ($stats['activity'][$sess->cmid]['statuses'] as $acronym => $status) {
                        array_push($summary, html_writer::tag('b', $acronym) . $status['count']);
                    }
                    $cell = new html_table_cell(implode(" ", $summary));
                    $cell->colspan = $summarywidth;
                    $row->cells[] = $cell;
                    $table->data[] = $row;
                }
                $lastgroup[0] = $sess->courseid;
                $lastgroup[1] = $sess->cmid;
            }

            // Now iterate over sessions in group...

            foreach ($group as $sess) {
                $row = new html_table_row();

                // If grouping by date, we need some form of date up front.
                // Only need course column if we are not using course to group
                // (currently date is only option which does not use course).
                if ($groupby === 'date') {
                    // What part of date do we want if grouped by it already?
                    $row->cells[] = userdate($sess->sessdate, get_string('strftimedmw', 'attendance')) .
                        " ". $this->construct_time($sess->sessdate, $sess->duration);

                    $courseurl = new moodle_url('/course/view.php', array('id' => $sess->courseid));
                    $row->cells[] = html_writer::link($courseurl, $sess->cname);
                }

                // Need activity column unless we are using activity to group.
                if ($groupby !== 'activity') {
                    $attendanceurl = new moodle_url('/mod/attendance/view.php', array('id' => $sess->cmid,
                                                                                      'studentid' => $userdata->user->id,
                                                                                      'view' => ATT_VIEW_ALL));
                    $row->cells[] = html_writer::link($attendanceurl, $sess->attname);
                }

                // If grouping by date, it belongs up front rather than here.
                if ($groupby !== 'date') {
                    $row->cells[] = userdate($sess->sessdate, get_string('strftimedmyw', 'attendance')) .
                        " ". $this->construct_time($sess->sessdate, $sess->duration);
                }

                $sesscontext = context_module::instance($sess->cmid);
                if (has_capability('mod/attendance:takeattendances', $sesscontext)) {
                    $sessionurl = new moodle_url('/mod/attendance/take.php', array('id' => $sess->cmid,
                                                                                   'sessionid' => $sess->id,
                                                                                   'grouptype' => $sess->groupid));
                    $description = html_writer::link($sessionurl, $sess->description);
                } else {
                    $description = $sess->description;
                }
                $row->cells[] = $description;

                if (!$shortform) {
                    if ($sess->groupid) {
                        $sessiontypeshort = get_string('group') . ': ' . $userdata->groups[$sess->courseid][$sess->groupid]->name;
                    } else {
                        $sessiontypeshort = get_string('commonsession', 'attendance');
                    }
                    $row->cells[] = html_writer::tag('nobr', $sessiontypeshort);
                }

                if (!empty($USER->attendanceediting)) {
                    $context = context_module::instance($sess->cmid);
                    if (has_capability('mod/attendance:takeattendances', $context)) {
                        // Takedata needs:
                        // sessioninfo->sessdate
                        // sessioninfo->duration
                        // statuses
                        // updatemode
                        // sessionlog[userid]->statusid
                        // sessionlog[userid]->remarks
                        // pageparams->viewmode == mod_attendance_take_page_params::SORTED_GRID
                        // and urlparams to be able to use url method later.
                        //
                        // user needs:
                        // enrolmentstart
                        // enrolmentend
                        // enrolmentstatus
                        // id.

                        $nastyhack = new ReflectionClass('attendance_take_data');
                        $takedata = $nastyhack->newInstanceWithoutConstructor();
                        $takedata->sessioninfo = $sess;
                        $takedata->statuses = array_filter($userdata->statuses[$sess->attendanceid], function($x) use ($sess) {
                            return ($x->setnumber == $sess->statusset);
                        });
                        $takedata->updatemode = true;
                        $takedata->sessionlog = array($userdata->user->id => $sess);
                        $takedata->pageparams = new stdClass();
                        $takedata->pageparams->viewmode = mod_attendance_take_page_params::SORTED_GRID;
                        $ucdata = $this->construct_take_session_controls($takedata, $userdata->user);

                        $celltext = join($ucdata['text']);

                        if (array_key_exists('warning', $ucdata)) {
                            $celltext .= html_writer::empty_tag('br');
                            $celltext .= $ucdata['warning'];
                        }
                        if (array_key_exists('class', $ucdata)) {
                            $row->attributes['class'] = $ucdata['class'];
                        }

                        $cell = new html_table_cell($celltext);
                        $row->cells[] = $cell;

                        $celltext = empty($ucdata['remarks']) ? '' : $ucdata['remarks'];
                        $cell = new html_table_cell($celltext);
                        $row->cells[] = $cell;

                    } else {
                        if (!empty($sess->statusid)) {
                            $status = $userdata->statuses[$sess->attendanceid][$sess->statusid];
                            $row->cells[] = $status->description;
                            $row->cells[] = $sess->remarks;
                        }
                    }

                } else {
                    if (!empty($sess->statusid)) {
                        $status = $userdata->statuses[$sess->attendanceid][$sess->statusid];
                        $row->cells[] = $status->description;
                        $row->cells[] = format_float($status->grade, 1, true, true) . ' / ' .
                            format_float($statussetmaxpoints[$status->setnumber], 1, true, true);
                        $row->cells[] = $sess->remarks;
                    } else if (($sess->sessdate + $sess->duration) < $userdata->user->enrolmentstart) {
                        $cell = new html_table_cell(get_string('enrolmentstart', 'attendance',
                        userdate($userdata->user->enrolmentstart, '%d.%m.%Y')));
                        $cell->colspan = 3;
                        $row->cells[] = $cell;
                    } else if ($userdata->user->enrolmentend and $sess->sessdate > $userdata->user->enrolmentend) {
                        $cell = new html_table_cell(get_string('enrolmentend', 'attendance',
                        userdate($userdata->user->enrolmentend, '%d.%m.%Y')));
                        $cell->colspan = 3;
                        $row->cells[] = $cell;
                    } else {
                        list($canmark, $reason) = attendance_can_student_mark($sess, false);
                        if ($canmark) {
                            // Student can mark their own attendance.
                            // URL to the page that lets the student modify their attendance.

                            $url = new moodle_url('/mod/attendance/attendance.php',
                            array('sessid' => $sess->id, 'sesskey' => sesskey()));
                            $cell = new html_table_cell(html_writer::link($url, get_string('submitattendance', 'attendance')));
                            $cell->colspan = 3;
                            $row->cells[] = $cell;
                        } else { // Student cannot mark their own attendace.
                            $row->cells[] = '?';
                            $row->cells[] = '? / ' . format_float($statussetmaxpoints[$sess->statusset], 1, true, true);
                            $row->cells[] = '';
                        }
                    }
                }

                $table->data[] = $row;
            }
        }

        if (!empty($USER->attendanceediting)) {
            $row = new html_table_row();
            $params = array(
                'type'  => 'submit',
                'class' => 'btn btn-primary',
                'value' => get_string('save', 'attendance'));
            $cell = new html_table_cell(html_writer::tag('center', html_writer::empty_tag('input', $params)));
            $cell->colspan = $colcount + (($groupby == 'activity') ? 2 : 1);
            $row->cells[] = $cell;
            $table->data[] = $row;
        }

        $logtext = html_writer::table($table);

        if (!empty($USER->attendanceediting)) {
            $formtext = html_writer::start_div('no-overflow');
            $formtext .= $logtext;
            $formtext .= html_writer::input_hidden_params($userdata->url(array('sesskey' => sesskey())));
            $formtext .= html_writer::end_div();
            // Could use userdata->urlpath if not private or userdata->url_path() if existed, but '' turns
            // out to DTRT.
            $logtext = html_writer::tag('form', $formtext, array('method' => 'post', 'action' => '',
                                                                 'id' => 'attendancetakeform'));
        }
        $allsessions->detail = $logtext;
        return $allsessions;
    }

    /**
     * Construct time for display.
     *
     * @param int $datetime
     * @param int $duration
     * @return string
     */
    private function construct_time($datetime, $duration) {
        $time = html_writer::tag('nobr', attendance_construct_session_time($datetime, $duration));

        return $time;
    }

    /**
     * Render report data.
     *
     * @param attendance_report_data $reportdata
     * @return string
     */
    protected function render_attendance_report_data(attendance_report_data $reportdata) {
        global $COURSE;

        // Initilise Javascript used to (un)check all checkboxes.
        $this->page->requires->js_init_call('M.mod_attendance.init_manage');

        $table = new html_table();
        $table->attributes['class'] = 'generaltable attwidth attreport';

        $userrows = $this->get_user_rows($reportdata);

        if ($reportdata->pageparams->view == ATT_VIEW_SUMMARY) {
            $sessionrows = array();
        } else {
            $sessionrows = $this->get_session_rows($reportdata);
        }

        $setnumber = -1;
        $statusetcount = 0;
        foreach ($reportdata->statuses as $sts) {
            if ($sts->setnumber != $setnumber) {
                $statusetcount++;
                $setnumber = $sts->setnumber;
            }
        }

        $acronymrows = $this->get_acronym_rows($reportdata, true);
        $startwithcontrast = $statusetcount % 2 == 0;
        $summaryrows = $this->get_summary_rows($reportdata, $startwithcontrast);

        // Check if the user should be able to bulk send messages to other users on the course.
        $bulkmessagecapability = has_capability('moodle/course:bulkmessaging', $this->page->context);

        // Extract rows from each part and collate them into one row each.
        $sessiondetailsleft = $reportdata->pageparams->sessiondetailspos == 'left';
        foreach ($userrows as $index => $row) {
            $summaryrow = isset($summaryrows[$index]->cells) ? $summaryrows[$index]->cells : array();
            $sessionrow = isset($sessionrows[$index]->cells) ? $sessionrows[$index]->cells : array();
            if ($sessiondetailsleft) {
                $row->cells = array_merge($row->cells, $sessionrow, $acronymrows[$index]->cells, $summaryrow);
            } else {
                $row->cells = array_merge($row->cells, $acronymrows[$index]->cells, $summaryrow, $sessionrow);
            }
            $table->data[] = $row;
        }

        if ($bulkmessagecapability) { // Require that the user can bulk message users.
            // Display check boxes that will allow the user to send a message to the students that have been checked.
            $output = html_writer::empty_tag('input', array('name' => 'sesskey', 'type' => 'hidden', 'value' => sesskey()));
            $output .= html_writer::empty_tag('input', array('name' => 'id', 'type' => 'hidden', 'value' => $COURSE->id));
            $output .= html_writer::empty_tag('input', array('name' => 'returnto', 'type' => 'hidden', 'value' => s(me())));
            $output .= html_writer::start_div('attendancereporttable');
            $output .= html_writer::table($table).html_writer::tag('div', get_string('users').': '.count($reportdata->users));
            $output .= html_writer::end_div();
            $output .= html_writer::tag('div',
                    html_writer::empty_tag('input', array('type' => 'submit',
                                                                   'value' => get_string('messageselectadd'),
                                                                   'class' => 'btn btn-secondary')),
                    array('class' => 'buttons'));
            $url = new moodle_url('/mod/attendance/messageselect.php');
            return html_writer::tag('form', $output, array('action' => $url->out(), 'method' => 'post'));
        } else {
            return html_writer::table($table).html_writer::tag('div', get_string('users').': '.count($reportdata->users));
        }
    }

    /**
     * Build and return the rows that will make up the left part of the attendance report.
     * This consists of student names, as well as header cells for these columns.
     *
     * @param attendance_report_data $reportdata the report data
     * @return array Array of html_table_row objects
     */
    protected function get_user_rows(attendance_report_data $reportdata) {
        $rows = array();

        $bulkmessagecapability = has_capability('moodle/course:bulkmessaging', $this->page->context);
        $extrafields = get_extra_user_fields($reportdata->att->context);
        $showextrauserdetails = $reportdata->pageparams->showextrauserdetails;
        $params = $reportdata->pageparams->get_significant_params();
        $text = get_string('users');
        if ($extrafields) {
            if ($showextrauserdetails) {
                $params['showextrauserdetails'] = 0;
                $url = $reportdata->att->url_report($params);
                $text .= $this->output->action_icon($url, new pix_icon('t/switch_minus',
                            get_string('hideextrauserdetails', 'attendance')), null, null);
            } else {
                $params['showextrauserdetails'] = 1;
                $url = $reportdata->att->url_report($params);
                $text .= $this->output->action_icon($url, new pix_icon('t/switch_plus',
                            get_string('showextrauserdetails', 'attendance')), null, null);
                $extrafields = array();
            }
        }
        $usercolspan = count($extrafields);

        $row = new html_table_row();
        $cell = $this->build_header_cell($text, false, false);
        $cell->attributes['class'] = $cell->attributes['class'] . ' headcol';
        $row->cells[] = $cell;
        if (!empty($usercolspan)) {
            $row->cells[] = $this->build_header_cell('', false, false, $usercolspan);
        }
        $rows[] = $row;

        $row = new html_table_row();
        $text = '';
        if ($bulkmessagecapability) {
            $text .= html_writer::checkbox('cb_selector', 0, false, '', array('id' => 'cb_selector'));
        }
        $text .= $this->construct_fullname_head($reportdata);
        $cell = $this->build_header_cell($text, false, false);
        $cell->attributes['class'] = $cell->attributes['class'] . ' headcol';
        $row->cells[] = $cell;

        foreach ($extrafields as $field) {
            $row->cells[] = $this->build_header_cell(get_string($field), false, false);
        }

        $rows[] = $row;

        foreach ($reportdata->users as $user) {
            $row = new html_table_row();
            $text = '';
            if ($bulkmessagecapability) {
                $text .= html_writer::checkbox('user'.$user->id, 'on', false, '', array('class' => 'attendancesesscheckbox'));
            }
            $text .= html_writer::link($reportdata->url_view(array('studentid' => $user->id)), fullname($user));
            $cell = $this->build_data_cell($text, false, false, null, null, false);
            $cell->attributes['class'] = $cell->attributes['class'] . ' headcol';
            $row->cells[] = $cell;

            foreach ($extrafields as $field) {
                $row->cells[] = $this->build_data_cell($user->$field, false, false);
            }
            $rows[] = $row;
        }

        $row = new html_table_row();
        $text = ($reportdata->pageparams->view == ATT_VIEW_SUMMARY) ? '' : get_string('summary');
        $cell = $this->build_data_cell($text, false, true, $usercolspan);
        $cell->attributes['class'] = $cell->attributes['class'] . ' headcol';
        $row->cells[] = $cell;
        if (!empty($usercolspan)) {
            $row->cells[] = $this->build_header_cell('', false, false, $usercolspan);
        }
        $rows[] = $row;

        return $rows;
    }

    /**
     * Build and return the rows that will make up the summary part of the attendance report.
     * This consists of countings for each status set acronyms, as well as header cells for these columns.
     *
     * @param attendance_report_data $reportdata the report data
     * @param boolean $startwithcontrast true if the first column must start with contrast (bgcolor)
     * @return array Array of html_table_row objects
     */
    protected function get_acronym_rows(attendance_report_data $reportdata, $startwithcontrast=false) {
        $rows = array();

        $summarycells = array();

        $row1 = new html_table_row();
        $row2 = new html_table_row();

        $setnumber = -1;
        $contrast = !$startwithcontrast;
        $icon = array( 1=> '<i class="fa fa-check-circle" style="color: green" aria-hidden="true"></i>',
            2=>'<i class="fa fa-user-plus" style="color: blue" aria-hidden="true"></i>',
            3=>'<i class="fa fa-clock-o" style="color:orange;" aria-hidden="true"></i>',
            4=>'<i class="fa fa-times-circle" style="color: red" aria-hidden="true"></i>');
        foreach ($reportdata->statuses as $sts) {
            if ($sts->setnumber != $setnumber) {
                $contrast = !$contrast;
                $setnumber = $sts->setnumber;
                $text = attendance_get_setname($reportdata->att->id, $setnumber, false);
                $cell = $this->build_header_cell($text, $contrast);
                $row1->cells[] = $cell;
            }
            $cell->colspan++;
            $sts->contrast = $contrast;
            //$row2->cells[] = $this->build_header_cell($sts->acronym, $contrast);
            $row2->cells[] = $this->build_header_cell($icon[$sts->id], $contrast);
            $summarycells[] = $this->build_data_cell('', $contrast);
        }

        $rows[] = $row1;
        $rows[] = $row2;

        foreach ($reportdata->users as $user) {
            if ($reportdata->pageparams->view == ATT_VIEW_SUMMARY) {
                $usersummary = $reportdata->summary->get_all_sessions_summary_for($user->id);
            } else {
                $usersummary = $reportdata->summary->get_taken_sessions_summary_for($user->id);
            }

            $row = new html_table_row();
            foreach ($reportdata->statuses as $sts) {
                if (isset($usersummary->userstakensessionsbyacronym[$sts->setnumber][$sts->acronym])) {
                    $text = $usersummary->userstakensessionsbyacronym[$sts->setnumber][$sts->acronym];
                } else {
                    $text = 0;
                }
                $row->cells[] = $this->build_data_cell($text, $sts->contrast);
            }

            $rows[] = $row;
        }

        $rows[] = new html_table_row($summarycells);

        return $rows;
    }

    /**
     * Build and return the rows that will make up the summary part of the attendance report.
     * This consists of counts and percentages for taken sessions (all sessions for summary report),
     * as well as header cells for these columns.
     *
     * @param attendance_report_data $reportdata the report data
     * @param boolean $startwithcontrast true if the first column must start with contrast (bgcolor)
     * @return array Array of html_table_row objects
     */
    protected function get_summary_rows(attendance_report_data $reportdata, $startwithcontrast=false) {
        $rows = array();

        $contrast = $startwithcontrast;
        $summarycells = array();

        $row1 = new html_table_row();
        $helpicon = $this->output->help_icon('oversessionstaken', 'attendance');
        $row1->cells[] = $this->build_header_cell(get_string('oversessionstaken', 'attendance') . $helpicon, $contrast, true, 3);

        $row2 = new html_table_row();
        $row2->cells[] = $this->build_header_cell(get_string('sessions', 'attendance'), $contrast);
        $row2->cells[] = $this->build_header_cell(get_string('points', 'attendance'), $contrast);
        $row2->cells[] = $this->build_header_cell(get_string('percentage', 'attendance'), $contrast);
        $summarycells[] = $this->build_data_cell('', $contrast);
        $summarycells[] = $this->build_data_cell('', $contrast);
        $summarycells[] = $this->build_data_cell('', $contrast);

        if ($reportdata->pageparams->view == ATT_VIEW_SUMMARY) {
            $contrast = !$contrast;

            $helpicon = $this->output->help_icon('overallsessions', 'attendance');
            $row1->cells[] = $this->build_header_cell(get_string('overallsessions', 'attendance') . $helpicon, $contrast, true, 3);

            $row2->cells[] = $this->build_header_cell(get_string('sessions', 'attendance'), $contrast);
            $row2->cells[] = $this->build_header_cell(get_string('points', 'attendance'), $contrast);
            $row2->cells[] = $this->build_header_cell(get_string('percentage', 'attendance'), $contrast);
            $summarycells[] = $this->build_data_cell('', $contrast);
            $summarycells[] = $this->build_data_cell('', $contrast);
            $summarycells[] = $this->build_data_cell('', $contrast);

            $contrast = !$contrast;
            $helpicon = $this->output->help_icon('maxpossible', 'attendance');
            $row1->cells[] = $this->build_header_cell(get_string('maxpossible', 'attendance') . $helpicon, $contrast, true, 2);

            $row2->cells[] = $this->build_header_cell(get_string('points', 'attendance'), $contrast);
            $row2->cells[] = $this->build_header_cell(get_string('percentage', 'attendance'), $contrast);
            $summarycells[] = $this->build_data_cell('', $contrast);
            $summarycells[] = $this->build_data_cell('', $contrast);
        }

        $rows[] = $row1;
        $rows[] = $row2;

        foreach ($reportdata->users as $user) {
            if ($reportdata->pageparams->view == ATT_VIEW_SUMMARY) {
                $usersummary = $reportdata->summary->get_all_sessions_summary_for($user->id);
            } else {
                $usersummary = $reportdata->summary->get_taken_sessions_summary_for($user->id);
            }

            $contrast = $startwithcontrast;
            $row = new html_table_row();
            $row->cells[] = $this->build_data_cell($usersummary->numtakensessions, $contrast);
            $row->cells[] = $this->build_data_cell($usersummary->pointssessionscompleted, $contrast);
            $row->cells[] = $this->build_data_cell(format_float($usersummary->takensessionspercentage * 100) . '%', $contrast);

            if ($reportdata->pageparams->view == ATT_VIEW_SUMMARY) {
                $contrast = !$contrast;
                $row->cells[] = $this->build_data_cell($usersummary->numallsessions, $contrast);
                $text = $usersummary->pointsallsessions;
                $row->cells[] = $this->build_data_cell($text, $contrast);
                $row->cells[] = $this->build_data_cell($usersummary->allsessionspercentage, $contrast);

                $contrast = !$contrast;
                $text = $usersummary->maxpossiblepoints;
                $row->cells[] = $this->build_data_cell($text, $contrast);
                $row->cells[] = $this->build_data_cell($usersummary->maxpossiblepercentage, $contrast);
            }

            $rows[] = $row;
        }

        $rows[] = new html_table_row($summarycells);

        return $rows;
    }

    /**
     * Build and return the rows that will make up the attendance report.
     * This consists of details for each selected session, as well as header and summary cells for these columns.
     *
     * @param attendance_report_data $reportdata the report data
     * @param boolean $startwithcontrast true if the first column must start with contrast (bgcolor)
     * @return array Array of html_table_row objects
     */
    protected function get_session_rows(attendance_report_data $reportdata, $startwithcontrast=false) {

        $rows = array();

        $row = new html_table_row();

        $showsessiondetails = $reportdata->pageparams->showsessiondetails;
        $text = get_string('sessions', 'attendance');
        $params = $reportdata->pageparams->get_significant_params();
        if (count($reportdata->sessions) > 1) {
            if ($showsessiondetails) {
                $params['showsessiondetails'] = 0;
                $url = $reportdata->att->url_report($params);
                $text .= $this->output->action_icon($url, new pix_icon('t/switch_minus',
                            get_string('hidensessiondetails', 'attendance')), null, null);
                $colspan = count($reportdata->sessions);
            } else {
                $params['showsessiondetails'] = 1;
                $url = $reportdata->att->url_report($params);
                $text .= $this->output->action_icon($url, new pix_icon('t/switch_plus',
                            get_string('showsessiondetails', 'attendance')), null, null);
                $colspan = 1;
            }
        } else {
            $colspan = 1;
        }

        $params = $reportdata->pageparams->get_significant_params();
        if ($reportdata->pageparams->sessiondetailspos == 'left') {
            $params['sessiondetailspos'] = 'right';
            $url = $reportdata->att->url_report($params);
            $text .= $this->output->action_icon($url, new pix_icon('t/right', get_string('moveright', 'attendance')),
                null, null);
        } else {
            $params['sessiondetailspos'] = 'left';
            $url = $reportdata->att->url_report($params);
            $text = $this->output->action_icon($url, new pix_icon('t/left', get_string('moveleft', 'attendance')),
                    null, null) . $text;
        }

        $row->cells[] = $this->build_header_cell($text, '', true, $colspan);
        $rows[] = $row;

        $row = new html_table_row();
        if ($showsessiondetails && !empty($reportdata->sessions)) {
            foreach ($reportdata->sessions as $sess) {
                $sesstext = userdate($sess->sessdate, get_string('strftimedm', 'attendance'));
                $sesstext .= html_writer::empty_tag('br');
                $sesstext .= attendance_strftimehm($sess->sessdate);
                $capabilities = array(
                    'mod/attendance:takeattendances',
                    'mod/attendance:changeattendances'
                );
                if (is_null($sess->lasttaken) and has_any_capability($capabilities, $reportdata->att->context)) {
                    $sesstext = html_writer::link($reportdata->url_take($sess->id, $sess->groupid), $sesstext,
                        array('class' => 'attendancereporttakelink'));
                }
                $sesstext .= html_writer::empty_tag('br', array('class' => 'attendancereportseparator'));
                if (!empty($sess->description) &&
                    !empty(get_config('attendance', 'showsessiondescriptiononreport'))) {
                    $sesstext .= html_writer::tag('small', format_text($sess->description),
                        array('class' => 'attendancereportcommon'));
                }
                if ($sess->groupid) {
                    if (empty($reportdata->groups[$sess->groupid])) {
                        $sesstext .= html_writer::tag('small', get_string('deletedgroup', 'attendance'),
                            array('class' => 'attendancereportgroup'));
                    } else {
                        $sesstext .= html_writer::tag('small', $reportdata->groups[$sess->groupid]->name,
                            array('class' => 'attendancereportgroup'));
                    }

                } else {
                    $sesstext .= html_writer::tag('small', get_string('commonsession', 'attendance'),
                        array('class' => 'attendancereportcommon'));
                }

                $row->cells[] = $this->build_header_cell($sesstext, false, true, null, null, false);
            }
        } else {
            $row->cells[] = $this->build_header_cell('');
        }
        $rows[] = $row;

        foreach ($reportdata->users as $user) {
            $row = new html_table_row();
            if ($showsessiondetails && !empty($reportdata->sessions)) {
                $cellsgenerator = new user_sessions_cells_html_generator($reportdata, $user);
                foreach ($cellsgenerator->get_cells(true) as $cell) {
                    if ($cell instanceof html_table_cell) {
                        $cell->attributes['class'] .= ' center';
                        $row->cells[] = $cell;
                    } else {
                        $row->cells[] = $this->build_data_cell($cell);
                    }
                }
            } else {
                $row->cells[] = $this->build_data_cell('');
            }
            $rows[] = $row;
        }


        $icon = array( 1=> '<i class="fa fa-check-circle" style="color: green" aria-hidden="true"></i>',
            2=>'<i class="fa fa-user-plus" style="color: blue" aria-hidden="true"></i>',
            3=>'<i class="fa fa-clock-o" style="color:orange;" aria-hidden="true"></i>',
            4=>'<i class="fa fa-times-circle" style="color: red" aria-hidden="true"></i>');
        $row = new html_table_row();
        if ($showsessiondetails && !empty($reportdata->sessions)) {
            foreach ($reportdata->sessions as $sess) {
                $sessionstats = array();
                foreach ($reportdata->statuses as $status) {
                    if ($status->setnumber == $sess->statusset) {
                        $status->count = 0;
                        $sessionstats[$status->id] = $status;
                    }
                }

                foreach ($reportdata->users as $user) {
                    if (!empty($reportdata->sessionslog[$user->id][$sess->id])) {
                        $statusid = $reportdata->sessionslog[$user->id][$sess->id]->statusid;
                        if (isset($sessionstats[$statusid]->count)) {
                            $sessionstats[$statusid]->count++;
                        }
                    }
                }

                $statsoutput = '';
                foreach ($sessionstats as $status) {
//                    $statsoutput .= "$status->description (".$icon[$status->id]. "): {$status->count}<br/>";
                    $statsoutput .= $icon[$status->id] .": {$status->count}<br/>";

                }
                $row->cells[] = $this->build_data_cell($statsoutput);
            }
        } else {
            $row->cells[] = $this->build_header_cell('');
        }
        $rows[] = $row;

        return $rows;
    }

    /**
     * Build and return a html_table_cell for header rows
     *
     * @param html_table_cell|string $cell the cell or a label for a cell
     * @param boolean $contrast true menans the cell must be shown with bgcolor contrast
     * @param boolean $center true means the cell text should be centered. Othersiwe it should be left-aligned.
     * @param int $colspan how many columns should cell spans
     * @param int $rowspan how many rows should cell spans
     * @param boolean $nowrap true means the cell text must be shown with nowrap option
     * @return html_table_cell a html table cell
     */
    protected function build_header_cell($cell, $contrast=false, $center=true, $colspan=null, $rowspan=null, $nowrap=true) {
        $classes = array('header', 'bottom');
        if ($center) {
            $classes[] = 'center';
            $classes[] = 'narrow';
        } else {
            $classes[] = 'left';
        }
        if ($contrast) {
            $classes[] = 'contrast';
        }
        if ($nowrap) {
            $classes[] = 'nowrap';
        }
        return $this->build_cell($cell, $classes, $colspan, $rowspan, true);
    }

    /**
     * Build and return a html_table_cell for data rows
     *
     * @param html_table_cell|string $cell the cell or a label for a cell
     * @param boolean $contrast true menans the cell must be shown with bgcolor contrast
     * @param boolean $center true means the cell text should be centered. Othersiwe it should be left-aligned.
     * @param int $colspan how many columns should cell spans
     * @param int $rowspan how many rows should cell spans
     * @param boolean $nowrap true means the cell text must be shown with nowrap option
     * @return html_table_cell a html table cell
     */
    protected function build_data_cell($cell, $contrast=false, $center=true, $colspan=null, $rowspan=null, $nowrap=true) {
        $classes = array();
        if ($center) {
            $classes[] = 'center';
            $classes[] = 'narrow';
        } else {
            $classes[] = 'left';
        }
        if ($nowrap) {
            $classes[] = 'nowrap';
        }
        if ($contrast) {
            $classes[] = 'contrast';
        }
        return $this->build_cell($cell, $classes, $colspan, $rowspan, false);
    }

    /**
     * Build and return a html_table_cell for header or data rows
     *
     * @param html_table_cell|string $cell the cell or a label for a cell
     * @param Array $classes a list of css classes
     * @param int $colspan how many columns should cell spans
     * @param int $rowspan how many rows should cell spans
     * @param boolean $header true if this should be a header cell
     * @return html_table_cell a html table cell
     */
    protected function build_cell($cell, $classes, $colspan=null, $rowspan=null, $header=false) {
        if (!($cell instanceof html_table_cell)) {
            $cell = new html_table_cell($cell);
        }
        $cell->header = $header;
        $cell->scope = 'col';

        if (!empty($colspan) && $colspan > 1) {
            $cell->colspan = $colspan;
        }

        if (!empty($rowspan) && $rowspan > 1) {
            $cell->rowspan = $rowspan;
        }

        if (!empty($classes)) {
            $classes = implode(' ', $classes);
            if (empty($cell->attributes['class'])) {
                $cell->attributes['class'] = $classes;
            } else {
                $cell->attributes['class'] .= ' ' . $classes;
            }
        }

        return $cell;
    }

    /**
     * Output the status set selector.
     *
     * @param attendance_set_selector $sel
     * @return string
     */
    protected function render_attendance_set_selector(attendance_set_selector $sel) {
        $current = $sel->get_current_statusset();
        $selected = null;
        $opts = array();
        for ($i = 0; $i <= $sel->maxstatusset; $i++) {
            $url = $sel->url($i);
            $display = $sel->get_status_name($i);
            $opts[$url->out(false)] = $display;
            if ($i == $current) {
                $selected = $url->out(false);
            }
        }
        $newurl = $sel->url($sel->maxstatusset + 1);
        $opts[$newurl->out(false)] = get_string('newstatusset', 'mod_attendance');
        if ($current == $sel->maxstatusset + 1) {
            $selected = $newurl->out(false);
        }

        return $this->output->url_select($opts, $selected, null);
    }

    /**
     * Render preferences data.
     *
     * @param stdClass $prefdata
     * @return string
     */
    protected function render_attendance_preferences_data($prefdata) {
        $this->page->requires->js('/mod/attendance/module.js');

        $table = new html_table();
        $table->width = '100%';
        $table->head = array('#',
                             get_string('acronym', 'attendance'),
                             get_string('description'),
                             get_string('points', 'attendance'));
        $table->align = array('center', 'center', 'center', 'center');

//        $table->head[] = get_string('studentavailability', 'attendance').
//            $this->output->help_icon('studentavailability', 'attendance');
//        $table->align[] = 'center';
//
//        $table->head[] = get_string('setunmarked', 'attendance').
//            $this->output->help_icon('setunmarked', 'attendance');
//        $table->align[] = 'center';

        $table->head[] = get_string('action');

        $i = 1;
        foreach ($prefdata->statuses as $st) {
            $emptyacronym = '';
            $emptydescription = '';
            if (isset($prefdata->errors[$st->id]) && !empty(($prefdata->errors[$st->id]))) {
                if (empty($prefdata->errors[$st->id]['acronym'])) {
                    $emptyacronym = $this->construct_notice(get_string('emptyacronym', 'mod_attendance'), 'notifyproblem');
                }
                if (empty($prefdata->errors[$st->id]['description'])) {
                    $emptydescription = $this->construct_notice(get_string('emptydescription', 'mod_attendance') , 'notifyproblem');
                }
            }
            $cells = array();
            $cells[] = $i;
            $cells[] = $this->construct_text_input('acronym['.$st->id.']', 2, 2, $st->acronym) . $emptyacronym;
            $cells[] = $this->construct_text_input('description['.$st->id.']', 30, 30, $st->description) .
                                 $emptydescription;
            $cells[] = $this->construct_text_input('grade['.$st->id.']', 4, 4, $st->grade);
//            $checked = '';
//            if ($st->setunmarked) {
//                $checked = ' checked ';
//            }
//            $cells[] = $this->construct_text_input('studentavailability['.$st->id.']', 4, 5, $st->studentavailability);
//            $cells[] = '<input type="radio" name="setunmarked" value="'.$st->id.'"'.$checked.'>';

            $cells[] = $this->construct_preferences_actions_icons($st, $prefdata);

            $table->data[$i] = new html_table_row($cells);
            $table->data[$i]->id = "statusrow".$i;
            $i++;
        }

        $table->data[$i][] = '*';
        $table->data[$i][] = $this->construct_text_input('newacronym', 2, 2);
        $table->data[$i][] = $this->construct_text_input('newdescription', 30, 30);
        $table->data[$i][] = $this->construct_text_input('newgrade', 4, 4);
//        $table->data[$i][] = $this->construct_text_input('newstudentavailability', 4, 5);

        $table->data[$i][] = $this->construct_preferences_button(get_string('add', 'attendance'),
            mod_attendance_preferences_page_params::ACTION_ADD);

        $o = html_writer::table($table);
        $o .= html_writer::input_hidden_params($prefdata->url(array(), false));
        // We should probably rewrite this to use mforms but for now add sesskey.
        $o .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()))."\n";

        $o .= $this->construct_preferences_button(get_string('update', 'attendance'),
                                                  mod_attendance_preferences_page_params::ACTION_SAVE);
        $o = html_writer::tag('form', $o, array('id' => 'preferencesform', 'method' => 'post',
                                                'action' => $prefdata->url(array(), false)->out_omit_querystring()));
        $o = $this->output->container($o, 'generalbox attwidth');

        return $o;
    }

    /**
     * Render default statusset.
     *
     * @param attendance_default_statusset $prefdata
     * @return string
     */
    protected function render_attendance_default_statusset(attendance_default_statusset $prefdata) {
        return $this->render_attendance_preferences_data($prefdata);
    }

    /**
     * Render preferences data.
     *
     * @param stdClass $prefdata
     * @return string
     */
    protected function render_attendance_pref($prefdata) {

    }

    /**
     * Construct text input.
     *
     * @param string $name
     * @param integer $size
     * @param integer $maxlength
     * @param string $value
     * @return string
     */
    private function construct_text_input($name, $size, $maxlength, $value='') {
        $attributes = array(
                'type'      => 'text',
                'name'      => $name,
                'size'      => $size,
                'maxlength' => $maxlength,
                'value'     => $value,
                'class' => 'form-control');
        return html_writer::empty_tag('input', $attributes);
    }

    /**
     * Construct action icons.
     *
     * @param stdClass $st
     * @param stdClass $prefdata
     * @return string
     */
    private function construct_preferences_actions_icons($st, $prefdata) {
        $params = array('sesskey' => sesskey(),
                        'statusid' => $st->id);
        if ($st->visible) {
            $params['action'] = mod_attendance_preferences_page_params::ACTION_HIDE;
            $showhideicon = $this->output->action_icon(
                    $prefdata->url($params),
                    new pix_icon("t/hide", get_string('hide')));
        } else {
            $params['action'] = mod_attendance_preferences_page_params::ACTION_SHOW;
            $showhideicon = $this->output->action_icon(
                    $prefdata->url($params),
                    new pix_icon("t/show", get_string('show')));
        }
        if (empty($st->haslogs)) {
            $params['action'] = mod_attendance_preferences_page_params::ACTION_DELETE;
            $deleteicon = $this->output->action_icon(
                    $prefdata->url($params),
                    new pix_icon("t/delete", get_string('delete')));
        } else {
            $deleteicon = '';
        }

        return $showhideicon . $deleteicon;
    }

    /**
     * Construct preferences button.
     *
     * @param string $text
     * @param string $action
     * @return string
     */
    private function construct_preferences_button($text, $action) {
        $attributes = array(
                'type'      => 'submit',
                'value'     => $text,
                'class'     => 'btn btn-secondary',
                'onclick'   => 'M.mod_attendance.set_preferences_action('.$action.')');
        return html_writer::empty_tag('input', $attributes);
    }

    /**
     * Construct a notice message
     *
     * @param string $text
     * @param string $class
     * @return string
     */
    private function construct_notice($text, $class = 'notifymessage') {
        $attributes = array('class' => $class);
        return html_writer::tag('p', $text, $attributes);
    }

    /**
     * Show different picture if it is a temporary user.
     *
     * @param stdClass $user
     * @param array $opts
     * @return string
     */
    protected function user_picture($user, array $opts = null) {
        if ($user->type == 'temporary') {
            $attrib = array(
                'width' => '35',
                'height' => '35',
                'class' => 'userpicture defaultuserpic',
            );
            if (isset($opts['size'])) {
                $attrib['width'] = $attrib['height'] = $opts['size'];
            }
            return $this->output->pix_icon('ghost', '', 'mod_attendance', $attrib);
        }

        return $this->output->user_picture($user, $opts);
    }
}
