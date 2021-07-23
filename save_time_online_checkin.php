<?php

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/../../local/webservices/externallib.php');
require_once(__DIR__ . '/../../local/webservices/externallib_frontend.php');
/*
 * Output
 */
$sessionid = required_param('sessionid', PARAM_INT);
$onlineduration = required_param('onlineduration', PARAM_INT)*60;

$result = $DB->update_record_raw('attendance_sessions',
    (object)array(
    'id' => $sessionid,
    'lasttaken' => time(),
    'lasttakenby' => 1,
    'onlinetime' => time(),
    'onlineduration' => $onlineduration));

echo json_encode($result);