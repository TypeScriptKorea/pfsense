<?php
/*
 * interfaces_assign.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * originally based on m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * Written by Jim McBeath based on existing m0n0wall files
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/*
2018.02.21
한글화 번역 시작
*/

##|+PRIV
##|*IDENT=page-interfaces-assignnetworkports
##|*NAME=Interfaces: Interface Assignments
##|*DESCR=Allow access to the 'Interfaces: Interface Assignments' page.
##|*MATCH=interfaces_assign.php*
##|-PRIV

//$timealla = microtime(true);

$pgtitle = array(gettext("인터페이스"), gettext("Interface Assignments"));
$shortcut_section = "interfaces";

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("ipsec.inc");
require_once("vpn.inc");
require_once("captiveportal.inc");
require_once("rrd.inc");
require_once("interfaces_fast.inc");

global $friendlyifnames;

/*moved most gettext calls to here, we really don't want to be repeatedly calling gettext() within loops if it can be avoided.*/
$gettextArray = array('add'=>gettext('Add'),'addif'=>gettext('Add interface'),'delete'=>gettext('Delete'),'deleteif'=>gettext('Delete interface'),'edit'=>gettext('Edit'),'on'=>gettext('on'));

/*
	In this file, "port" refers to the physical port name,
	while "interface" refers to LAN, WAN, or OPTn.
*/

/* get list without VLAN interfaces */
$portlist = get_interface_list();

/*another *_fast function from interfaces_fast.inc. These functions are basically the same as the 
ones they're named after, except they (usually) take an array and (always) return an array. This means that they only
need to be called once per script run, the returned array contains all the data necessary for repeated use */
$friendlyifnames = convert_real_interface_to_friendly_interface_name_fast();

/* add wireless clone interfaces */
if (is_array($config['wireless']['clone']) && count($config['wireless']['clone'])) {
	foreach ($config['wireless']['clone'] as $clone) {
		$portlist[$clone['cloneif']] = $clone;
		$portlist[$clone['cloneif']]['iswlclone'] = true;
	}
}

/* add VLAN interfaces */
if (is_array($config['vlans']['vlan']) && count($config['vlans']['vlan'])) {
	//$timea = microtime(true);
	foreach ($config['vlans']['vlan'] as $vlan) {
		$portlist[$vlan['vlanif']] = $vlan;
		$portlist[$vlan['vlanif']]['isvlan'] = true;
	}
}

/* add Bridge interfaces */
if (is_array($config['bridges']['bridged']) && count($config['bridges']['bridged'])) {
	foreach ($config['bridges']['bridged'] as $bridge) {
		$portlist[$bridge['bridgeif']] = $bridge;
		$portlist[$bridge['bridgeif']]['isbridge'] = true;
	}
}

/* add GIF interfaces */
if (is_array($config['gifs']['gif']) && count($config['gifs']['gif'])) {
	foreach ($config['gifs']['gif'] as $gif) {
		$portlist[$gif['gifif']] = $gif;
		$portlist[$gif['gifif']]['isgif'] = true;
	}
}

/* add GRE interfaces */
if (is_array($config['gres']['gre']) && count($config['gres']['gre'])) {
	foreach ($config['gres']['gre'] as $gre) {
		$portlist[$gre['greif']] = $gre;
		$portlist[$gre['greif']]['isgre'] = true;
	}
}

/* add LAGG interfaces */
if (is_array($config['laggs']['lagg']) && count($config['laggs']['lagg'])) {
	foreach ($config['laggs']['lagg'] as $lagg) {
		$portlist[$lagg['laggif']] = $lagg;
		$portlist[$lagg['laggif']]['islagg'] = true;
		/* LAGG members cannot be assigned */
		$lagifs = explode(',', $lagg['members']);
		foreach ($lagifs as $lagif) {
			if (isset($portlist[$lagif])) {
				unset($portlist[$lagif]);
			}
		}
	}
}

/* add QinQ interfaces */
if (is_array($config['qinqs']['qinqentry']) && count($config['qinqs']['qinqentry'])) {
	foreach ($config['qinqs']['qinqentry'] as $qinq) {
		$portlist["{$qinq['vlanif']}"]['descr'] = "VLAN {$qinq['tag']} on {$qinq['if']}";
		$portlist["{$qinq['vlanif']}"]['isqinq'] = true;
		/* QinQ members */
		$qinqifs = explode(' ', $qinq['members']);
		foreach ($qinqifs as $qinqif) {
			$portlist["{$qinq['vlanif']}_{$qinqif}"]['descr'] = "QinQ {$qinqif} on VLAN {$qinq['tag']} on {$qinq['if']}";
			$portlist["{$qinq['vlanif']}_{$qinqif}"]['isqinq'] = true;
		}
	}
}

/* add PPP interfaces */
if (is_array($config['ppps']['ppp']) && count($config['ppps']['ppp'])) {
	foreach ($config['ppps']['ppp'] as $pppid => $ppp) {
		$portname = $ppp['if'];
		$portlist[$portname] = $ppp;
		$portlist[$portname]['isppp'] = true;
		$ports_base = basename($ppp['ports']);
		if (isset($ppp['descr'])) {
			$portlist[$portname]['descr'] = strtoupper($ppp['if']). "({$ports_base}) - {$ppp['descr']}";
		} else if (isset($ppp['username'])) {
			$portlist[$portname]['descr'] = strtoupper($ppp['if']). "({$ports_base}) - {$ppp['username']}";
		} else {
			$portlist[$portname]['descr'] = strtoupper($ppp['if']). "({$ports_base})";
		}
	}
}

$ovpn_descrs = array();
if (is_array($config['openvpn'])) {
	if (is_array($config['openvpn']['openvpn-server'])) {
		foreach ($config['openvpn']['openvpn-server'] as $s) {
			$portname = "ovpns{$s['vpnid']}";
			$portlist[$portname] = $s;
			$ovpn_descrs[$s['vpnid']] = $s['description'];
		}
	}
	if (is_array($config['openvpn']['openvpn-client'])) {
		foreach ($config['openvpn']['openvpn-client'] as $c) {
			$portname = "ovpnc{$c['vpnid']}";
			$portlist[$portname] = $c;
			$ovpn_descrs[$c['vpnid']] = $c['description'];
		}
	}
}


$ifdescrs = interface_assign_description_fast($portlist,$friendlyifnames);

if (isset($_REQUEST['add']) && isset($_REQUEST['if_add'])) {
	/* Be sure this port is not being used */
	$portused = false;
	foreach ($config['interfaces'] as $ifname => $ifdata) {
		if ($ifdata['if'] == $_REQUEST['if_add']) {
			$portused = true;
			break;
		}
	}

	if ($portused === false) {
		/* find next free optional interface number */
		if (!$config['interfaces']['lan']) {
			$newifname = gettext("lan");
			$descr = gettext("LAN");
		} else {
			for ($i = 1; $i <= count($config['interfaces']); $i++) {
				if (!$config['interfaces']["opt{$i}"]) {
					break;
				}
			}
			$newifname = 'opt' . $i;
			$descr = "OPT" . $i;
		}
		
		$config['interfaces'][$newifname] = array();
		$config['interfaces'][$newifname]['descr'] = $descr;
		$config['interfaces'][$newifname]['if'] = $_POST['if_add'];
		if (preg_match($g['wireless_regex'], $_POST['if_add'])) {
			$config['interfaces'][$newifname]['wireless'] = array();
			interface_sync_wireless_clones($config['interfaces'][$newifname], false);
		}

		
		uksort($config['interfaces'], "compare_interface_friendly_names");

		/* XXX: Do not remove this. */
		unlink_if_exists("{$g['tmp_path']}/config.cache");

		write_config();

		$action_msg = gettext("Interface has been added.");
		$class = "success";
	}

} else if (isset($_POST['apply'])) {
	if (file_exists("/var/run/interface_mismatch_reboot_needed")) {
		system_reboot();
		$rebootingnow = true;
	} else {
		write_config();

		$changes_applied = true;
		$retval = 0;
		$retval |= filter_configure();
	}

} else if (isset($_POST['Submit'])) {

	unset($input_errors);

	/* input validation */

	/* Build a list of the port names so we can see how the interfaces map */
	$portifmap = array();
	foreach ($portlist as $portname => $portinfo) {
		$portifmap[$portname] = array();
	}

	/* Go through the list of ports selected by the user,
	build a list of port-to-interface mappings in portifmap */
	foreach ($_POST as $ifname => $ifport) {
		if (($ifname == 'lan') || ($ifname == 'wan') || (substr($ifname, 0, 3) == 'opt')) {
			$portifmap[$ifport][] = strtoupper($ifname);
		}
	}

	/* Deliver error message for any port with more than one assignment */
	foreach ($portifmap as $portname => $ifnames) {
		if (count($ifnames) > 1) {
			$errstr = sprintf(gettext('Port %1$s '.
				' was assigned to %2$s' .
				' interfaces:'), $portname, count($ifnames));

			foreach ($portifmap[$portname] as $ifn) {
				$errstr .= " " . convert_friendly_interface_to_friendly_descr(strtolower($ifn)) . " (" . $ifn . ")";
			}

			$input_errors[] = $errstr;
		} else if (count($ifnames) == 1 && preg_match('/^bridge[0-9]/', $portname) && is_array($config['bridges']['bridged']) && count($config['bridges']['bridged'])) {
			foreach ($config['bridges']['bridged'] as $bridge) {
				if ($bridge['bridgeif'] != $portname) {
					continue;
				}

				$members = explode(",", strtoupper($bridge['members']));
				foreach ($members as $member) {
					if ($member == $ifnames[0]) {
						$input_errors[] = sprintf(gettext('이 인터페이스가 %3$s의 멤버이기 때문에 포트 %1$s 를 인터페이스 %2$s로 설정할 수 없습니다.'), $portname, $member, $portname);
						break;
					}
				}
			}
		}
	}

	if (is_array($config['vlans']['vlan'])) {
		foreach ($config['vlans']['vlan'] as $vlan) {
			if (does_interface_exist($vlan['if']) == false) {
				$input_errors[] = sprintf(gettext('Vlan상위 인터페이스 %1$s이(가)더 이상 없으므로 VLAN ID %2$s를 생성할 수 없습니다.'), $vlan['if'], $vlan['tag']);
			}
		}
	}

	if (!$input_errors) {
		/* No errors detected, so update the config */
		foreach ($_POST as $ifname => $ifport) {

			if (($ifname == 'lan') || ($ifname == 'wan') || (substr($ifname, 0, 3) == 'opt')) {

				if (!is_array($ifport)) {
					$reloadif = false;
					if (!empty($config['interfaces'][$ifname]['if']) && $config['interfaces'][$ifname]['if'] <> $ifport) {
						interface_bring_down($ifname);
						/* Mark this to be reconfigured in any case. */
						$reloadif = true;
					}
					$config['interfaces'][$ifname]['if'] = $ifport;
					if (isset($portlist[$ifport]['isppp'])) {
						$config['interfaces'][$ifname]['ipaddr'] = $portlist[$ifport]['type'];
					}

					if (substr($ifport, 0, 3) == 'gre' || substr($ifport, 0, 3) == 'gif') {
						unset($config['interfaces'][$ifname]['ipaddr']);
						unset($config['interfaces'][$ifname]['subnet']);
						unset($config['interfaces'][$ifname]['ipaddrv6']);
						unset($config['interfaces'][$ifname]['subnetv6']);
					}

					/* check for wireless interfaces, set or clear ['wireless'] */
					if (preg_match($g['wireless_regex'], $ifport)) {
						if (!is_array($config['interfaces'][$ifname]['wireless'])) {
							$config['interfaces'][$ifname]['wireless'] = array();
						}
					} else {
						unset($config['interfaces'][$ifname]['wireless']);
					}

					/* make sure there is a descr for all interfaces */
					if (!isset($config['interfaces'][$ifname]['descr'])) {
						$config['interfaces'][$ifname]['descr'] = strtoupper($ifname);
					}

					if ($reloadif == true) {
						if (preg_match($g['wireless_regex'], $ifport)) {
							interface_sync_wireless_clones($config['interfaces'][$ifname], false);
						}
						/* Reload all for the interface. */
						interface_configure($ifname, true);
					}
				}
			}
		}
		write_config();

		enable_rrd_graphing();
	}
} else {
	unset($delbtn);
	if (!empty($_POST['del'])) {
		$delbtn = key($_POST['del']);
	}

	if (isset($delbtn)) {
		$id = $delbtn;

		if (link_interface_to_group($id)) {
			$input_errors[] = gettext("이 인터페이스는 그룹에 속해있습니다. 계속하시려면 그룹에서 해당 항목을 제거하십시오.");
		} else if (link_interface_to_bridge($id)) {
			$input_errors[] = gettext("이 인터페이스는 브리지에 속해있습니다. 계속하시려면 브리지에서 해당 항목을 제거하십시오.");
		} else if (link_interface_to_gre($id)) {
			$input_errors[] = gettext("이 인터페이스는 Gre터널에 속해있습니다. 계속하시려면 터널에서 해당 항목을 제거하십시오.");
		} else if (link_interface_to_gif($id)) {
			$input_errors[] = gettext("이 인터페이스는 gif터널에 속해있습니다. 계속하시려면 터널에서 해당 항목을 제거하십시오.");
		} else if (interface_has_queue($id)) {
			$input_errors[] = gettext("인터페이스에 트래픽 조절 대기열이 구성되어 있습니다.\n계속하려면 인터페이스에 존재하는 모든 대기 열을 제거하십시오.");
		} else {
			unset($config['interfaces'][$id]['enable']);
			$realid = get_real_interface($id);
			interface_bring_down($id);   /* down the interface */

			unset($config['interfaces'][$id]);	/* delete the specified OPTn or LAN*/

			if (is_array($config['dhcpd']) && is_array($config['dhcpd'][$id])) {
				unset($config['dhcpd'][$id]);
				services_dhcpd_configure('inet');
			}

			if (is_array($config['dhcpdv6']) && is_array($config['dhcpdv6'][$id])) {
				unset($config['dhcpdv6'][$id]);
				services_dhcpd_configure('inet6');
			}

			if (count($config['filter']['rule']) > 0) {
				foreach ($config['filter']['rule'] as $x => $rule) {
					if ($rule['interface'] == $id) {
						unset($config['filter']['rule'][$x]);
					}
				}
			}
			if (is_array($config['nat']['rule']) && count($config['nat']['rule']) > 0) {
				foreach ($config['nat']['rule'] as $x => $rule) {
					if ($rule['interface'] == $id) {
						unset($config['nat']['rule'][$x]['interface']);
					}
				}
			}

			write_config();

			/* If we are in firewall/routing mode (not single interface)
			 * then ensure that we are not running DHCP on the wan which
			 * will make a lot of ISP's unhappy.
			 */
			if ($config['interfaces']['lan'] && $config['dhcpd']['wan']) {
				unset($config['dhcpd']['wan']);
			}

			link_interface_to_vlans($realid, "update");

			$action_msg = gettext("인터페이스가 제거되었습니다.");
			$class = "success";
		}
	}
}

/* Create a list of unused ports */
$unused_portlist = array();
$portArray = array_keys($portlist);

$ifaceArray = array_column($config['interfaces'],'if');
$unused = array_diff($portArray,$ifaceArray);
$unused = array_flip($unused);
$unused_portlist = array_intersect_key($portlist,$unused);//*/
unset($unused,$portArray,$ifaceArray);

include("head.inc");

if (file_exists("/var/run/interface_mismatch_reboot_needed")) {
	if ($_POST) {
		if ($rebootingnow) {
			$action_msg = gettext("시스템이 재부팅됩니다. 잠시 기다려주십시오.");
			$class = "success";
		} else {
			$applymsg = gettext("재부팅이 필요합니다. 재부팅하려면 설정을 적용하십시오.");
			$class = "warning";
		}
	} else {
		$action_msg = gettext("인터페이스가 일치하지 않습니다. 오류를 해결하신 뒤 저장하시고 '변경 사항 적용'을 클릭하십시오. 이후 방화벽이 재부팅됩니다.");
		$class = "warning";
	}
}

if (file_exists("/tmp/reload_interfaces")) {
	echo "<p>\n";
	print_apply_box(gettext("인터페이스 구성이 변경되었습니다.") . "<br />" . gettext("변경 사항을 저장하시면 변경 내용이 적용됩니다."));
	echo "<br /></p>\n";
} elseif ($applymsg) {
	print_apply_box($applymsg);
} elseif ($action_msg) {
	print_info_box($action_msg, $class);
} elseif ($changes_applied) {
	print_apply_result_box($retval);
}

pfSense_handle_custom_code("/usr/local/pkg/interfaces_assign/pre_input_errors");

if ($input_errors) {
	print_input_errors($input_errors);
}

$tab_array = array();
$tab_array[] = array(gettext("Interface Assignments"), true, "interfaces_assign.php");
$tab_array[] = array(gettext("Interface Groups"), false, "interfaces_groups.php");
$tab_array[] = array(gettext("Wireless"), false, "interfaces_wireless.php");
$tab_array[] = array(gettext("VLANs"), false, "interfaces_vlan.php");
$tab_array[] = array(gettext("QinQs"), false, "interfaces_qinq.php");
$tab_array[] = array(gettext("PPPs"), false, "interfaces_ppps.php");
$tab_array[] = array(gettext("GREs"), false, "interfaces_gre.php");
$tab_array[] = array(gettext("GIFs"), false, "interfaces_gif.php");
$tab_array[] = array(gettext("Bridges"), false, "interfaces_bridge.php");
$tab_array[] = array(gettext("LAGGs"), false, "interfaces_lagg.php");
display_top_tabs($tab_array);

/*Generate the port select box only once. 
Not indenting the HTML to produce smaller code
and faster page load times */

$portselect='';
foreach ($portlist as $portname => $portinfo) {
	$portselect.='<option value="'.$portname.'"'; 
	$portselect.=">".$ifdescrs[$portname]."</option>\n";
}

?>
<form action="interfaces_assign.php" method="post">
	<div class="table-responsive">
	<table class="table table-striped table-hover">
	<thead>
		<tr>
			<th><?=gettext("인터페이스")?></th>
			<th><?=gettext("네트워크 포트")?></th>
			<th>&nbsp;</th>
		</tr>
	</thead>
	<tbody>
<?php
	$i=0;
	foreach ($config['interfaces'] as $ifname => $iface):
		if ($iface['descr']) {
			$ifdescr = $iface['descr'];
		} else {
			$ifdescr = strtoupper($ifname);
		}
?>
		<tr>
			<td><a href="/interfaces.php?if=<?=$ifname?>"><?=$ifdescr?></a></td>
			<td>
				<select name="<?=$ifname?>" id="<?=$ifname?>" class="form-control">
<?php 
/*port select menu generation loop replaced with pre-prepared select menu to reduce page generation time */
echo str_replace('value="'.$iface['if'].'">','value="'.$iface['if'].'" selected>',$portselect);
?>
				</select>
			</td>
			<td>
<?php if ($ifname != 'wan'):?>
				<button type="submit" name="del[<?=$ifname?>]" class="btn btn-danger btn-sm" title="<?=$gettextArray['deleteif']?>">
					<i class="fa fa-trash icon-embed-btn"></i>
					<?=$gettextArray["delete"]?>
				</button>
<?php endif;?>
			</td>
		</tr>
<?php $i++; 
endforeach;
	if (count($config['interfaces']) < count($portlist)):
?>
		<tr>
			<th>
				<?=gettext("사용 가능한 네트워크 포트:")?>
			</th>
			<td>
				<select name="if_add" id="if_add" class="form-control">
<?php
/* HTML not indented to save on transmission/render time */
foreach ($unused_portlist as $portname => $portinfo):?>
<option value="<?=$portname?>" <?=($portname == $iface['if']) ? ' selected': ''?>><?=$ifdescrs[$portname]?></option>
<?php endforeach;
?>
				</select>
			</td>
			<td>
				<button type="submit" name="add" title="<?=gettext("선택한 인터페이스 추가")?>" value="add interface" class="btn btn-success btn-sm" >
					<i class="fa fa-plus icon-embed-btn"></i>
					<?=$gettextArray["add"]?>
				</button>
			</td>
		</tr>
<?php endif;?>
		</tbody>
	</table>
	</div>

	<button name="Submit" type="submit" class="btn btn-primary" value="<?=gettext('저장')?>"><i class="fa fa-save icon-embed-btn"></i><?=gettext('저장')?></button>
</form>
<br />

<?php
print_info_box(gettext("lagg(4)인터페이스로 구성된 인터페이스는 표시되지 않습니다.") .
    '<br/><br/>' .
    gettext("무선 인터페이스는 무선 탭에서 생성해서 할당 할 수 있습니다."), 'info', false);
?>

<?php include("foot.inc")?>
