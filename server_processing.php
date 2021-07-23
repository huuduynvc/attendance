<?php
/*
 * Script:    DataTables server-side script for PHP and MySQL
 * Copyright: 2010 - Allan Jardine
 * License:   GPL v2 or BSD (3-point)
 */
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/../../local/webservices/externallib.php');
require_once(__DIR__ . '/../../local/webservices/externallib_frontend.php');
/*
 * Output
 */
$columns = array('timetaken','sessdate','usertaken_name','userbetaken_name','eventname');
$value= $_GET['search']['value'];
$filter = $columns[$_GET['order'][0]['column']];
$order = $_GET['order'][0]['dir'];
$attendanceid = $_GET['attendanceid'];
$a = new local_webservices_frontend();
$result = $a->get_action_logs_pagination($attendanceid,(int)$_GET['start']/10 + 1,(int)$_GET['length'],$value,$filter,$order);
$data = array();
foreach ($result['logs'] as $res){
    $temp = new stdClass();
    $temp->timetaken = date('d-m-Y H:i:s',$res->timetaken);
    $temp->sessdate = date('d-m-Y H:i:s',$res->sessdate);
    $temp->usertaken = $res->usertaken_name;
    if($res->userbetaken == null){
        $temp->userbetaken = "";
    }else{
        $temp->userbetaken = $res->userbetaken_name;
    }
    $temp->eventname = $res->eventname;
    $temp->description = $res->description;
    $data[] = $temp;
}

$output = array(
    "draw" => (int)($_GET['draw']),
    "recordsTotal" => $result['totalrecords'],
    "recordsFiltered" => $result['totalrecords'],
    "data" => $data,
);

echo json_encode($output);