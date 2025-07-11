<?php

$name = 'suricata';
$unit_text = 'ICMPv4 pkt/s';
$colours = 'psychedelic';
$descr_len = 15;

if (isset($vars['sinstance'])) {
    $rrd_filename = Rrd::name($device['hostname'], ['app', $name, $app->app_id, 'instance_' . $vars['sinstance'] . '___decoder__event__icmpv6__experimentation_type']);
    $decoder__event__icmpv6__ipv6_trunc_pkt_rrd_filename = Rrd::name($device['hostname'], ['app', $name, $app->app_id, 'instance_' . $vars['sinstance'] . '___decoder__event__icmpv6__ipv6_trunc_pkt']);
    $decoder__event__icmpv6__ipv6_unknown_version_rrd_filename = Rrd::name($device['hostname'], ['app', $name, $app->app_id, 'instance_' . $vars['sinstance'] . '___decoder__event__icmpv6__ipv6_unknown_version']);
    $decoder__event__icmpv6__mld_message_with_invalid_hl_rrd_filename = Rrd::name($device['hostname'], ['app', $name, $app->app_id, 'instance_' . $vars['sinstance'] . '___decoder__event__icmpv6__mld_message_with_invalid_hl']);
    $decoder__event__icmpv6__pkt_too_small_rrd_filename = Rrd::name($device['hostname'], ['app', $name, $app->app_id, 'instance_' . $vars['sinstance'] . '___decoder__event__icmpv6__pkt_too_small']);
    $decoder__event__icmpv6__unassigned_type_rrd_filename = Rrd::name($device['hostname'], ['app', $name, $app->app_id, 'instance_' . $vars['sinstance'] . '___decoder__event__icmpv6__unassigned_type']);
    $decoder__event__icmpv6__unknown_code_rrd_filename = Rrd::name($device['hostname'], ['app', $name, $app->app_id, 'instance_' . $vars['sinstance'] . '___decoder__event__icmpv6__unknown_code']);
    $decoder__event__icmpv6__unknown_type_rrd_filename = Rrd::name($device['hostname'], ['app', $name, $app->app_id, 'instance_' . $vars['sinstance'] . '___decoder__event__icmpv6__unknown_type']);
} else {
    $rrd_filename = Rrd::name($device['hostname'], ['app', $name, $app->app_id, 'totals___decoder__event__icmpv6__experimentation_type']);
    $decoder__event__icmpv6__ipv6_trunc_pkt_rrd_filename = Rrd::name($device['hostname'], ['app', $name, $app->app_id, 'totals___decoder__event__icmpv6__ipv6_trunc_pkt']);
    $decoder__event__icmpv6__ipv6_unknown_version_rrd_filename = Rrd::name($device['hostname'], ['app', $name, $app->app_id, 'totals___decoder__event__icmpv6__ipv6_unknown_version']);
    $decoder__event__icmpv6__mld_message_with_invalid_hl_rrd_filename = Rrd::name($device['hostname'], ['app', $name, $app->app_id, 'totals___decoder__event__icmpv6__mld_message_with_invalid_hl']);
    $decoder__event__icmpv6__pkt_too_small_rrd_filename = Rrd::name($device['hostname'], ['app', $name, $app->app_id, 'totals___decoder__event__icmpv6__pkt_too_small']);
    $decoder__event__icmpv6__unassigned_type_rrd_filename = Rrd::name($device['hostname'], ['app', $name, $app->app_id, 'totals___decoder__event__icmpv6__unassigned_type']);
    $decoder__event__icmpv6__unknown_code_rrd_filename = Rrd::name($device['hostname'], ['app', $name, $app->app_id, 'totals___decoder__event__icmpv6__unknown_code']);
    $decoder__event__icmpv6__unknown_type_rrd_filename = Rrd::name($device['hostname'], ['app', $name, $app->app_id, 'totals___decoder__event__icmpv6__unknown_type']);
}

$rrd_list = [
    [
        'filename' => $rrd_filename,
        'descr' => 'Exp Type',
        'ds' => 'data',
    ],
    [
        'filename' => $decoder__event__icmpv6__ipv6_trunc_pkt_rrd_filename,
        'descr' => 'Trunc Pkt',
        'ds' => 'data',
    ],
    [
        'filename' => $decoder__event__icmpv6__ipv6_unknown_version_rrd_filename,
        'descr' => 'Unknown Ver',
        'ds' => 'data',
    ],
    [
        'filename' => $decoder__event__icmpv6__mld_message_with_invalid_hl_rrd_filename,
        'descr' => 'MLD Msg Inv HL',
        'ds' => 'data',
    ],
    [
        'filename' => $decoder__event__icmpv6__pkt_too_small_rrd_filename,
        'descr' => 'Pkt Too Small',
        'ds' => 'data',
    ],
    [
        'filename' => $decoder__event__icmpv6__unassigned_type_rrd_filename,
        'descr' => 'Unass Type',
        'ds' => 'data',
    ],
    [
        'filename' => $decoder__event__icmpv6__unknown_code_rrd_filename,
        'descr' => 'Unknown Code',
        'ds' => 'data',
    ],
    [
        'filename' => $decoder__event__icmpv6__unknown_type_rrd_filename,
        'descr' => 'Unknown Type',
        'ds' => 'data',
    ],
];

require 'includes/html/graphs/generic_multi_line.inc.php';
