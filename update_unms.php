<?php
include "database.php";
require_once('lib/unms/src/Unms.php');

function array_to_xml(SimpleXMLElement $object, array $data) {
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $new_object = $object->addChild($key);
            array_to_xml($new_object, $value);
        } else {
            if ($key == (int) $key) {
                $key = "$key";
            }

            $object->addChild($key, $value);
        }
    }
}
$Script_version = 49;

try {
    $dbh = new PDO('mysql:host='.$db['host'].';dbname='.$db['name'], $db['user'], $db['pass']);
    $sql = "SELECT controller.*
            FROM controller
            WHERE controller.controller_vendor_id='2';";
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
            WHERE router.router_vendor_id=2
            ORDER BY router.controller_id;";
    foreach ($dbh->query($sql) as $row) {
        $db_macs[] = $row['router_mac'];
        $db_devices[$row['router_mac']]['system_data']['geo']['lat'] = $row['router_lat'];
        $db_devices[$row['router_mac']]['system_data']['geo']['lng'] = $row['router_lon'];
        $db_devices[$row['router_mac']]['batman_adv_originators']['originator_0']['originator'] = $row['router_nexthop'];
        $db_devices[$row['router_mac']]['batman_adv_originators']['originator_0']['nexthop'] = $row['router_nexthop'];
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
    $unms_connection = new Unms($controller['controller_user'], $controller['controller_pass'], $controller['controller_url'], true);
    $login            = $unms_connection->login();
    $results          = $unms_connection->getAllDevices(); // returns a PHP array containing devices of the site

    foreach ($results as $row) {
        if($row->identification->site->id == $controller['controller_site'] && array_key_exists($row->identification->mac, $db_devices)) {
            $devices[$row->identification->mac] = $db_devices[$row->identification->mac];
            $devices[$row->identification->mac]['system_data']['mac'] = $row->identification->id;
            $devices[$row->identification->mac]['system_data']['mac'] = $row->identification->mac;
            $devices[$row->identification->mac]['system_data']['ip'] = $row->ipAddress;
            $devices[$row->identification->mac]['system_data']['model'] = $row->identification->modelName;
            $devices[$row->identification->mac]['system_data']['hostname'] = $row->identification->name;
            $devices[$row->identification->mac]['client_count'] = 0;
            $devices[$row->identification->mac]['system_data']['firmware_version'] = $row->identification->firmwareVersion;
            $devices[$row->identification->mac]['system_data']['uptime'] = $row->overview->uptime;
            $devices[$row->identification->mac]['system_data']['local_time'] = time();
            $devices[$row->identification->mac]['system_data']['memory_free'] = $row->overview->ram;
            $devices[$row->identification->mac]['system_data']['memory_buffering'] = 0;
            $devices[$row->identification->mac]['system_data']['memory_caching'] = 0;
            $devices[$row->identification->mac]['system_data']['loadavg'] = $row->overview->cpu;
            $devices[$row->identification->mac]['system_data']['processes'] = '0/0';

            $Interfaces = $unms_connection->getRouterInterfaces($row->identification->id);

            foreach($Interfaces as $iface) {
                $devices[$row->identification->mac]['interface_data'][$iface->identification->displayName] = array(
                    'name' => $iface->identification->displayName,
                    //'mtu' => 1500,
                    'mac_addr' => $iface->identification->mac,
                    'traffic_rx' => $iface->statistics->rxbytes,
                    'traffic_tx' => $iface->statistics->txbytes,
                    //'wlan_essid' => $iface->essid,
                    //'wlan_mode' => $iface->radio,
                    //'channel' => $iface->channel,
                    //'wlan_frequency' => '2.462GHz',
                    //'wlan_tx_power' => '20 dBm'
                );
            }

            if($row->overview->status == 'active') {
                $devices[$row->identification->mac]['system_data']['status'] = 'online';
            }
            else {
                $devices[$row->identification->mac]['system_data']['status'] = 'offline';
                $devices[$row->identification->mac]['system_data']['uptime'] = 1;
            }

            $devices[$row->identification->mac]['batman_adv_originators']['originator_0']['link_quality'] = '255';
            $devices[$row->identification->mac]['batman_adv_originators']['originator_0']['last_seen'] = '1';
            $devices[$row->identification->mac]['batman_adv_originators']['originator_0']['outgoing_interface'] = 'eth0';
        }
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