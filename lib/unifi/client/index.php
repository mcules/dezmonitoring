<?php
require_once('src/Client.php');

$controller_user = "freifunk";
$controller_password = "freifunk";
$controller_url = "https://unifi.itstall.de/";
$site_id = "o14c2h6f";
$controller_version = "5.10.20";

$Nodewatcher['contact'] = "freifunk-franken.de@itstall.de";
$Nodewatcher['hood'] = "Campingpark_Kirchzell";
$Nodewatcher['version'] = 49;

$devices["b4:fb:e4:10:8a:16"]['latitude'] = "49.60628128";
$devices["b4:fb:e4:10:8a:16"]['longitude'] = "9.15799499";
$devices["b4:fb:e4:10:89:fc"]['latitude'] = "49.60698350";
$devices["b4:fb:e4:10:89:fc"]['longitude'] = "9.15842414";
$devices["b4:fb:e4:10:8c:9c"]['latitude'] = "49.60769613";
$devices["b4:fb:e4:10:8c:9c"]['longitude'] = "9.15674508";

$unifi_connection = new UniFi_API\Client($controller_user, $controller_password, $controller_url, $site_id, $controller_version, false);
$login            = $unifi_connection->login();
$results          = $unifi_connection->list_devices(); // returns a PHP array containing alarm objects
die(print_r($results));

foreach ($results as $row) {
	if($row->adopted == 1) {
		$devices[$row->mac]['mac'] = $row->mac;
		$devices[$row->mac]['ip'] = $row->ip;
		$devices[$row->mac]['name'] = $row->name;
		$devices[$row->mac]['clients'] = 0;
		$devices[$row->mac]['version'] = $row->version;
		$devices[$row->mac]['uptime'] = $row->uptime;
		$devices[$row->mac]['last_seen'] = $row->last_seen;

		$devices[$row->mac]['system-stats']['mem'] = $row->{'system-stats'}->mem;
		$devices[$row->mac]['system-stats']['cpu'] = $row->{'system-stats'}->cpu;
		$devices[$row->mac]['system-stats']['uptime'] = $row->{'system-stats'}->uptime;

		foreach($row->vap_table as $iface) {
			$devices[$row->mac]['interfaces']['name'] = $iface->name;
			$devices[$row->mac]['interfaces']['ssid'] = $iface->essid;
			$devices[$row->mac]['interfaces']['channel'] = $iface->channel;
			$devices[$row->mac]['interfaces']['mac'] = $iface->bssid;
			$devices[$row->mac]['interfaces']['rx_bytes'] = $iface->rx_bytes;
			$devices[$row->mac]['interfaces']['tx_bytes'] = $iface->tx_bytes;
			$devices[$row->mac]['interfaces']['mode'] = $iface->radio;
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
				$devices[$row->mac]['status'] = 'offline';
				break;
			case 1:
				$devices[$row->mac]['status'] = 'online';
				break;
		}
	}
}

$results = $unifi_connection->list_clients();
#print_r($results);

foreach($results as $row) {
	$devices[$row->ap_mac]['clients']++;
}

#print_r($devices);


foreach($devices as $device) {
	$System_Data = "<status>".$device['status']."</status>";
	$System_Data .= $status_text;
	$System_Data .= "<hostname>".$device['name']."</hostname>";
	$System_Data .= ${description};
	$System_Data .= ${geo};
	$System_Data .= ${position_comment};
	$System_Data .= $Nodewatcher['contact'];
	$System_Data .= "<hood>".$Nodewatcher['hood']."</hood>";
	$System_Data .= "<distname>$distname</distname>";
	$System_Data .= "<distversion>".$device['version']."</distversion>";
	$System_Data .= $cpu;
	$System_Data .= $model;
	$System_Data .= $memory;
	$System_Data .= $load;
	$System_Data .= $device['uptime'];
	$System_Data .= "<local_time>$local_time</local_time>";
	$System_Data .= "<batman_advanced_version>$batman_adv_version</batman_advanced_version>";
	$System_Data .= "<kernel_version>$kernel_version</kernel_version>";
	$System_Data .= $fastd_version;
	$System_Data .= "<nodewatcher_version>".$Nodewatcher['version']."</nodewatcher_version>";
	$System_Data .= "<firmware_version>".$device['version']."</firmware_version>";
	$System_Data .= "<firmware_revision>$BUILD_DATE</firmware_revision>";
	$System_Data .= "<openwrt_core_revision>$OPENWRT_CORE_REVISION</openwrt_core_revision>";
	$System_Data .= "<openwrt_feeds_packages_revision>$OPENWRT_FEEDS_PACKAGES_REVISION</openwrt_feeds_packages_revision>";
	$System_Data .= $vpn_active;
	
	foreach($device['interfaces'] as $Interface) {
		//$interface_data = $interface_data."<$iface><name>$iface</name>$addrs<traffic_rx>$traffic_rx</traffic_rx><traffic_tx>$traffic_tx</traffic_tx>";
        
		//$interface_data = $interface_data.$(iwconfig "${iface}" 2>/dev/null | awk -F':' '\
		///Mode/{ split($2, m, " "); printf "<wlan_mode>"m[1]"</wlan_mode>" }\
		///Cell/{ split($0, c, " "); printf "<wlan_bssid>"c[5]"</wlan_bssid>" }\
		///ESSID/ { split($0, e, "\""); printf "<wlan_essid>"e[2]"</wlan_essid>" }\
		///Freq/{ split($3, f, " "); printf "<wlan_frequency>"f[1]f[2]"</wlan_frequency>" }\
		///Tx-Power/{ split($0, p, "="); sub(/[[:space:]]*$/, "", p[2]); printf "<wlan_tx_power>"p[2]"</wlan_tx_power>" }');
		
        //$interface_data = $interface_data.$(iw dev "${iface}" info 2>/dev/null | awk '\
		///ssid/{ split($0, s, " "); printf "<wlan_ssid>"s[2]"</wlan_ssid>" }\
		///type/ { split($0, t, " "); printf "<wlan_type>"t[2]"</wlan_type>" }\
		///channel/{ split($0, c, " "); printf "<wlan_channel>"c[2]"</wlan_channel>" }\
		///width/{ split($0, w, ": "); sub(/ .*/, "", w[2]); printf "<wlan_width>"w[2]"</wlan_width>" }\ ');
        
		//$interface_data = $interface_data."</$iface>";
	}
	
	$Xml .= "<?xml version='1.0' standalone='yes'?><data>\
				<system_data>".$System_Data."</system_data>\
				<interface_data>$interface_data</interface_data>\
				<switchport>".$device['mac']."</switchport>\
				<client_count>$client_count</client_count>\
				<clients>$dataclient</clients>\
				$dataair\
			</data>";
}