<?php
use UniFi_API\Client;

include "database.php";
require_once('lib/unifi/client/src/Client.php');

function array_to_xml(SimpleXMLElement $object, array $data) {
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $new_object = $object->addChild($key);
            array_to_xml($new_object, $value);
        } else {
            // if the key is an integer, it needs text with it to actually work.
            if ($key == (int) $key) {
                $key = "$key";
            }

            $object->addChild($key, $value);
        }
    }
}

$controller_version = "5.10.20";
$Script_version = 49;

try {
    $dbh = new PDO('mysql:host='.$db['host'].';dbname='.$db['name'], $db['user'], $db['pass']);
    $sql = "SELECT controller.* FROM controller;";
    foreach ($dbh->query($sql) as $row) {
        $controllers[$row['controller_id']] = $row;
    }
    $dbh = null;
} catch (PDOException $e) {
    print "Error!: " . $e->getMessage() . "<br/>";
    die();
}

try {
    $dbh = new PDO('mysql:host='.$db['host'].';dbname='.$db['name'], $db['user'], $db['pass']);
    $sql = "SELECT router.*, vendors.*
            FROM router
            INNER JOIN vendors ON (router.router_vendor_id=vendors.vendor_id)
            WHERE router.router_vendor_id=1
            ORDER BY router.controller_id;";
    foreach ($dbh->query($sql) as $row) {
        $db_macs[] = $row['router_mac'];
        $db_devices[$row['router_mac']]['system_data']['geo']['lat'] = $row['router_lat'];
        $db_devices[$row['router_mac']]['system_data']['geo']['lng'] = $row['router_lon'];
        $db_devices[$row['router_mac']]['system_data']['hood'] = $row['router_fff_hood'];
        $db_devices[$row['router_mac']]['system_data']['contact'] = $row['router_fff_contact'];
        $db_devices[$row['router_mac']]['system_data']['nodewatcher_version'] = $Script_version;
    }
    $dbh = null;
} catch (PDOException $e) {
    print "Error!: " . $e->getMessage() . "<br/>";
    die();
}

foreach($controllers as $controller) {
    $unifi_connection = new UniFi_API\Client($controller['controller_user'], $controller['controller_pass'], $controller['controller_url'], $controller['controller_site'], $controller_version, false);
    $login            = $unifi_connection->login();
    $results          = $unifi_connection->list_devices(); // returns a PHP array containing objects

    $originator = 0;
    foreach ($results as $row) {
        if($row->adopted == 1 && array_key_exists($row->mac, $db_devices)) {
            $devices[$row->mac] = $db_devices[$row->mac];
            switch($row->model) {
                case 'U7MSH':
                    $devices[$row->mac]['system_data']['model'] = "UAP-AC-Mesh";
                    break;
                case 'U7LT':
                    $devices[$row->mac]['system_data']['model'] = "UAP-AC-Lite";
                    break;
                default:
                    $devices[$row->mac]['system_data']['model'] = $row->model;
                    break;
            }
            $devices[$row->mac]['system_data']['mac'] = $row->mac;
            $devices[$row->mac]['system_data']['ip'] = $row->ip;
            $devices[$row->mac]['system_data']['hostname'] = $row->name;
            $devices[$row->mac]['client_count'] = 0;
            $devices[$row->mac]['system_data']['firmware_version'] = $row->version;
            $devices[$row->mac]['system_data']['uptime'] = $row->uptime;
            $devices[$row->mac]['system_data']['local_time'] = time();
            $devices[$row->mac]['system_data']['memory_free'] = (int)$row->{'system-stats'}->mem;
            $devices[$row->mac]['system_data']['memory_buffering'] = 0;
            $devices[$row->mac]['system_data']['memory_caching'] = 0;
            $devices[$row->mac]['system_data']['loadavg'] = $row->{'system-stats'}->cpu;
            $devices[$row->mac]['system_data']['uptime'] = $row->{'system-stats'}->uptime;
            $devices[$row->mac]['system_data']['processes'] = '0/0';

            if(@$row->uplink->ap_mac) {
                $devices[$row->mac]['interface_data']['uplink'] = array(
                    'mac_addr' => $row->mac,
                    'name' => 'uplink',
                    'traffic_rx' => $row->uplink->rx_bytes,
                    'traffic_tx' => $row->uplink->tx_bytes,
                    'wlan_channel' => $row->uplink->channel,
                    'wlan_tx_power' => $row->uplink->tx_power
                );
                $devices[$row->mac]['batman_adv_originators']['originator_0'] = array(
                    'originator' => $row->uplink->ap_mac,
                    'nexthop' => $row->uplink->ap_mac,
                    'link_quality' => $row->uplink->satisfaction_reason,
                    'last_seen' => '1',
                    'outgoing_interface' => 'uplink'
                );
                $devices[$row->uplink->ap_mac]['batman_adv_originators']['originator_'.$originator++] = array(
                    'originator' => $row->mac,
                    'nexthop' => $row->mac,
                    'link_quality' => $row->uplink->satisfaction_reason,
                    'last_seen' => '1',
                    'outgoing_interface' => 'downlink'
                );
                $devices[$row->uplink->ap_mac]['interface_data']['downlink'] = array(
                    //'mac_addr' => $row->mac,
                    'name' => 'downlink',
                    'traffic_rx' => $devices[$row->uplink->ap_mac]['interface_data']['downlink']['traffic_rx'] + $row->uplink->rx_bytes,
                    'traffic_tx' => $devices[$row->uplink->ap_mac]['interface_data']['downlink']['traffic_tx'] + $row->uplink->tx_bytes,
                    'wlan_channel' => $row->uplink->channel,
                    'wlan_tx_power' => $row->uplink->tx_power
                );
            }
            else {
                $devices[$row->mac]['interface_data']['uplink'] = array(
                    'mac_addr' => $row->mac,
                    'name' => 'uplink',
                    'traffic_rx' => 0,
                    'traffic_tx' => 0
                );
            }
            if(@$row->vap_table) {
                foreach($row->vap_table as $iface) {
                    $devices[$row->mac]['interface_data'][$iface->name] = array(
                        'name' => $iface->name,
                        //'mtu' => 1500,
                        'mac_addr' => $iface->bssid,
                        'traffic_rx' => $iface->rx_bytes,
                        'traffic_tx' => $iface->tx_bytes,
                        'wlan_ssid' => $iface->essid,
                        'wlan_mode' => $iface->radio,
                        'wlan_channel' => $iface->channel,
                        //'wlan_frequency' => '2.462GHz',
                        'wlan_tx_power' => $iface->tx_power
                    );
                }
            }

            /**
             * state
             * 0 Disconnected
             * 1 Online
             * 6 Heartbeat missing
             * 11 Isolated
             */
            switch (true) {
                case ($row->state == 0 || $row->state == 6 || $row->state == 11):
                    $devices[$row->mac]['system_data']['status'] = 'offline';
                    $devices[$row->mac]['system_data']['uptime'] = 1;
                    break;
                case 1:
                    $devices[$row->mac]['system_data']['status'] = 'online';
                    break;
            }
        }
    }

    $results = $unifi_connection->list_clients();

    foreach($results as $row) {
        $devices[$row->ap_mac]['client_count']++;
    }
}

$alfred = null;

foreach($devices as $device) {
    if(in_array($device['system_data']['mac'], $db_macs)) {
        if($device['system_data']['mac'] != null && $device['system_data']['mac']) {
            $xml = new SimpleXMLElement('<data/>');
            array_to_xml($xml, $device);
            $alfred[$device['system_data']['mac']] = $xml->asXML();
            unset($xml);
        }
    }
}

$data_string = json_encode($alfred);

$ch = curl_init('https://monitoring.freifunk-franken.de/api/alfred2');
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'charset=UTF-8')
);

$result = curl_exec($ch);
var_dump($result);