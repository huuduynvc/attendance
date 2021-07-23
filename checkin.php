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
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot.'/mod/attendance/locallib.php');

require_once(__DIR__ . '/../../local/webservices/externallib_frontend.php');

$id = required_param('id', PARAM_INT);
$sessionid = required_param('sessionid', PARAM_INT);

$cm = get_coursemodule_from_id('attendance', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$att = $DB->get_record('attendance', array('id' => $cm->instance), '*', MUST_EXIST);

$att = new mod_attendance_structure($att, $cm, $course);

$PAGE->set_url($att->url_checkin());

$rowSession = $DB->get_record('attendance_sessions',array('id' => $sessionid));
$timeCheckin = $rowSession->onlinetime+$rowSession->onlineduration - time() - 2;
if ($timeCheckin < 0){
    $url = new moodle_url('/mod/attendance/view.php', array('id' => $cm->id));
    redirect($url);
}

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
global $USER;

// create token
$shortname = 'moodle_mobile_app';

$systemcontext = context_system::instance();

$service = $DB->get_record('external_services', array('shortname' => $shortname, 'enabled' => 1));

if (empty($service)) {
    // will throw exception if no token found
    throw new moodle_exception('servicenotavailable', 'webservice');
}

// Get an existing token or create a new one.
try {
    $token = external_generate_token_for_current_user($service);
} catch (moodle_exception $e) {
    echo "Error generating token";
}
$privatetoken = $token->privatetoken;
external_log_token_request($token);
$siteadmin = has_capability('moodle/site:config', $systemcontext, $USER->id);

$usertoken = new stdClass;
$usertoken->token = $token->token;
// Private token, only transmitted to https sites and non-admin users.
if (is_https() and !$siteadmin) {
    $usertoken->privatetoken = $privatetoken;
} else {
    $usertoken->privatetoken = null;
}

// set
$PAGE->set_title($course->shortname.": ".$att->name.' - Checkin');
$PAGE->set_heading($course->fullname);
$PAGE->force_settings_menu(true);
$PAGE->set_cacheable(true);
$PAGE->navbar->add('Checkin');

$output = $PAGE->get_renderer('mod_attendance');

// link to js
$myJavascript = new moodle_url("/mod/attendance/js/checkinonline/index.js");
// url moodle
$urlMoodle = new moodle_url("/");
// server URL
$serverURL = get_config('block_user_faces','apiserver')?get_config('block_user_faces','apiserver'):"http://192.168.1.5:5000";

// images
$imgbg = new moodle_url("/blocks/user_faces/bg.png");
$imgfront = new moodle_url("/blocks/user_faces/front.png");
$imgleft = new moodle_url("/blocks/user_faces/left.gif");
$imgright = new moodle_url("/blocks/user_faces/right.gif");
$arrImgRotate = array($imgfront->__toString(),$imgright->__toString(),$imgleft->__toString());
// face-api.js
$myscript = new moodle_url("/blocks/user_faces/face-api.js");

// required css and jquery
$PAGE->requires->css("/blocks/user_faces/style.css");

// Output starts here.
echo $output->header();

//$time = $DB->get_records()
$content   = '
             <div style="margin-top: 3rem; text-align: center">
                  <h5 style="font-weight: 600;">COUNT DOWN TIME CHECKIN:</h5> <p style="font-size: 1.2rem;color: red"><span id="time">'.$timeCheckin.'</span>s</p>
             </div>
             <div id="videoCanvas" style="margin-bottom: 3rem; margin-top: 3rem; padding:0 2rem">
                
                <div>
                    <p style="margin-right: 90px">Điểm danh online:  </p>
                    <div style="display: none" id="detect-model">
                        <br/>
                        <label for="model">Thay đổi module nhận diện:</label>
                        <br/>
                        <select id="model">
                            <option value="tiny">TinyFace Model</option>
                            <option value="ssd">SSD Model</option>
                        </select>
                    </div>
                </div>
                <div>
                    <div style="display: none" id="container-img">
                        <img style="display: inline" id="img-get-left" alt="Your face" width="160" height="160">
                        <img style="display: inline" id="img-get-front" alt="Your face" width="160" height="160">
                        <img style="display: inline" id="img-get-right" alt="Your face" width="160" height="160">               
                    </div>
                    <br>
                    <div id="button-snap" class="button" onclick="handleClickOpenCam()">Mở camera của bạn</div>
                </div>
                <div id="dontshow">
                    <div id="mytext"></div>
                    <video width="500" id="camera" autoplay="false"></video>
                    <img width="500" style="display: none" height="375" id="background-camera" src="'.$imgbg.'">
                    <img width="350" height="350" id="rotateImg" class="rotate" src="'.$imgfront.'">
                    <div style="display: flex; justify-content: center; width: 500px;align-items: center; height: 250px;" id="container-loading"><div class="loader" id="loading"></div></div>
                    <div style="margin-top: 15px">
                        <div class="button-container">
                            <img  id="img-left" alt="Your face" width="160" height="160">
                            <canvas style="border:1px solid" width="160" height="160" id="photoLeft"></canvas>
                            <p id="text-left" class="text">Ảnh trái</p>
                            <div id="recapture-left" onclick="handleResetLeftPicture()" class="button-recapture button-recapture-disable">Chụp lại</div>
                        </div>
                        <div class="button-container">
                            <img id="img-center" alt="Your face" width="160" height="160">           
                            <canvas style="border:1px solid" width="160" height="160" id="photoCenter"></canvas>
                            <p id="text-center" class="text">Ảnh giữa</p>
                            <div id="recapture-center" onclick="handleResetCenterPicture()" class="button-recapture button-recapture-disable">Chụp lại</div>
                        </div>
                        <div class="button-container">
                            <img id="img-right" alt="Your face" width="160" height="160">           
                            <canvas style="border:1px solid" width="160" height="160" id="photoRight"></canvas>
                            <p id="text-right" class="text">Ảnh phải</p>
                            <div id="recapture-right" onclick="handleResetRightPicture()" class="button-recapture button-recapture-disable">Chụp lại</div>
                        </div>
                        <div style="display: flex; margin-top: 10px">
                            <div id="button-submit" class="button button-disable" onclick="handleSubmitPicture()"><span id="loader-sending" class="loader-sending"></span>Điểm danh</div>
                            <div class="button" onclick="handleResetPicture()">Chụp lại ảnh khác</div>
                        </div>
                    </div>
                </div>
                <br/>
                <br/>
                <br/>
                <script src="'.$myscript.'"></script>
                <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
                <script src="https://webrtc.github.io/adapter/adapter-latest.js"></script>
                </script>';
$PAGE->requires->js('/mod/attendance/js/checkinonline/index.js');
$PAGE->requires->js_init_call('init', array($id,$serverURL,$arrImgRotate,$urlMoodle->__toString(),$token->token,$timeCheckin,$id,$sessionid));

echo $content;


echo $output->footer();


