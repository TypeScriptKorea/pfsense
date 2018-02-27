<?php
/*
 * firewall_virtual_ip.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2018 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2005 Bill Marquette <bill.marquette@gmail.com>
 * All rights reserved.
 *
 * originally based on m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
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
2018.02.27
한글화 번역 시작
*/

##|+PRIV
##|*IDENT=page-firewall-virtualipaddresses
##|*NAME=Firewall: Virtual IP Addresses
##|*DESCR=Allow access to the 'Firewall: Virtual IP Addresses' page.
##|*MATCH=firewall_virtual_ip.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");

if (!is_array($config['virtualip']['vip'])) {
	$config['virtualip']['vip'] = array();
}

$a_vip = &$config['virtualip']['vip'];

if ($_POST['apply']) {
	$check_carp = false;
	if (file_exists("{$g['tmp_path']}/.firewall_virtual_ip.apply")) {
		$toapplylist = unserialize(file_get_contents("{$g['tmp_path']}/.firewall_virtual_ip.apply"));
		foreach ($toapplylist as $vid => $ovip) {
			if (!empty($ovip)) {
				interface_vip_bring_down($ovip);
			}
			if ($a_vip[$vid]) {
				switch ($a_vip[$vid]['mode']) {
					case "ipalias":
						interface_ipalias_configure($a_vip[$vid]);
						break;
					case "proxyarp":
						interface_proxyarp_configure($a_vip[$vid]['interface']);
						break;
					case "carp":
						$check_carp = true;
						interface_carp_configure($a_vip[$vid]);
						break;
					default:
						break;
				}
			}
		}
		@unlink("{$g['tmp_path']}/.firewall_virtual_ip.apply");
	}
	/* Before changing check #4633 */
	if ($check_carp === true && !get_carp_status()) {
		set_single_sysctl("net.inet.carp.allow", "1");
	}

	$retval = 0;
	$retval |= filter_configure();

	clear_subsystem_dirty('vip');
}

if ($_POST['act'] == "del") {
	if ($a_vip[$_POST['id']]) {
		/* make sure no inbound NAT mappings reference this entry */
		if (is_array($config['nat']['rule'])) {
			foreach ($config['nat']['rule'] as $rule) {
				if ($rule['destination']['address'] != "") {
					if ($rule['destination']['address'] == $a_vip[$_POST['id']]['subnet']) {
						$input_errors[] = gettext("이 항목은 적어도 하나 이상의 NAT 매핑에서 계속 참조되므로 삭제할 수 없습니다.");
						break;
					}
				}
			}
		}

		/* make sure no OpenVPN server or client references this entry */
		$openvpn_types_a = array("openvpn-server" => gettext("server"), "openvpn-client" => gettext("client"));
		foreach ($openvpn_types_a as $openvpn_type => $openvpn_type_text) {
			if (is_array($config['openvpn'][$openvpn_type])) {
				foreach ($config['openvpn'][$openvpn_type] as $openvpn) {
					if ($openvpn['ipaddr'] <> "") {
						if ($openvpn['ipaddr'] == $a_vip[$_POST['id']]['subnet']) {
							if (strlen($openvpn['description'])) {
								$openvpn_desc = $openvpn['description'];
							} else {
								$openvpn_desc = $openvpn['ipaddr'] . ":" . $openvpn['local_port'];
							}
							$input_errors[] = sprintf(gettext('이 항목은 OpenVPN %1$s %2$s가 계속 참조하기 때문에 삭제할 수 없습니다..'), $openvpn_type_text, $openvpn_desc);
							break;
						}
					}
				}
			}
		}

		if (is_ipaddrv6($a_vip[$_POST['id']]['subnet'])) {
			$is_ipv6 = true;
			$subnet = gen_subnetv6($a_vip[$_POST['id']]['subnet'], $a_vip[$_POST['id']]['subnet_bits']);
			$if_subnet_bits = get_interface_subnetv6($a_vip[$_POST['id']]['interface']);
			$if_subnet = gen_subnetv6(get_interface_ipv6($a_vip[$_POST['id']]['interface']), $if_subnet_bits);
		} else {
			$is_ipv6 = false;
			$subnet = gen_subnet($a_vip[$_POST['id']]['subnet'], $a_vip[$_POST['id']]['subnet_bits']);
			$if_subnet_bits = get_interface_subnet($a_vip[$_POST['id']]['interface']);
			$if_subnet = gen_subnet(get_interface_ip($a_vip[$_POST['id']]['interface']), $if_subnet_bits);
		}

		$subnet .= "/" . $a_vip[$_POST['id']]['subnet_bits'];
		$if_subnet .= "/" . $if_subnet_bits;

		if (is_array($config['gateways']['gateway_item'])) {
			foreach ($config['gateways']['gateway_item'] as $gateway) {
				if ($a_vip[$_POST['id']]['interface'] != $gateway['interface']) {
					continue;
				}
				if ($is_ipv6 && $gateway['ipprotocol'] == 'inet') {
					continue;
				}
				if (!$is_ipv6 && $gateway['ipprotocol'] == 'inet6') {
					continue;
				}
				if (ip_in_subnet($gateway['gateway'], $if_subnet)) {
					continue;
				}


				if (ip_in_subnet($gateway['gateway'], $subnet)) {
					$input_errors[] = gettext("이 항목은 하나 이상의 게이트웨이에서 계속 참조하므로 삭제할 수 없습니다.");
					break;
				}
			}
		}

		if ($a_vip[$_POST['id']]['mode'] == "ipalias") {
			$subnet = gen_subnet($a_vip[$_POST['id']]['subnet'], $a_vip[$_POST['id']]['subnet_bits']) . "/" . $a_vip[$_POST['id']]['subnet_bits'];
			$found_if = false;
			$found_carp = false;
			$found_other_alias = false;

			if ($subnet == $if_subnet) {
				$found_if = true;
			}

			$vipiface = $a_vip[$_POST['id']]['interface'];

			foreach ($a_vip as $vip_id => $vip) {
				if ($vip_id == $_POST['id']) {
					continue;
				}

				if ($vip['interface'] == $vipiface && ip_in_subnet($vip['subnet'], $subnet)) {
					if ($vip['mode'] == "carp") {
						$found_carp = true;
					} else if ($vip['mode'] == "ipalias") {
						$found_other_alias = true;
					}
				}
			}

			if ($found_carp === true && $found_other_alias === false && $found_if === false) {
				$input_errors[] = sprintf(gettext("이 항목은 %s가있는 CARP IP에서 계속 참조하므로 삭제할 수 없습니다."), $vip['descr']);
			}
		} else if ($a_vip[$_POST['id']]['mode'] == "carp") {
			$vipiface = "{$a_vip[$_POST['id']]['interface']}_vip{$a_vip[$_POST['id']]['vhid']}";
			foreach ($a_vip as $vip) {
				if ($vipiface == $vip['interface'] && $vip['mode'] == "ipalias") {
					$input_errors[] = sprintf(gettext("%s가 있는 IP 별칭 항목에서 이 항목을 계속 참조하기 때문에 삭제할 수 없습니다."), $vip['descr']);
				}
			}
		}

		if (!$input_errors) {
			phpsession_begin();
			$user = getUserEntry($_SESSION['Username']);

			if (is_array($user) && userHasPrivilege($user, "user-config-readonly")) {
				header("Location: firewall_virtual_ip.php");
				phpsession_end();
				exit;
			}
			phpsession_end();


			// Special case since every proxyarp vip is handled by the same daemon.
			if ($a_vip[$_POST['id']]['mode'] == "proxyarp") {
				$viface = $a_vip[$_POST['id']]['interface'];
				unset($a_vip[$_POST['id']]);
				interface_proxyarp_configure($viface);
			} else {
				interface_vip_bring_down($a_vip[$_POST['id']]);
				unset($a_vip[$_POST['id']]);
			}
			if (count($config['virtualip']['vip']) == 0) {
				unset($config['virtualip']['vip']);
			}
			write_config(gettext("Deleted a virtual IP."));
			header("Location: firewall_virtual_ip.php");
			exit;
		}
	}
} else if ($_REQUEST['changes'] == "mods" && is_numericint($_REQUEST['id'])) {
	$id = $_REQUEST['id'];
}

$types = array('proxyarp' => gettext('Proxy ARP'),
			   'carp' => gettext('CARP'),
			   'other' => gettext('Other'),
			   'ipalias' => gettext('IP Alias')
			   );

$pgtitle = array(gettext("Firewall"), gettext("가상 IP"));
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
} else if ($_POST['apply']) {
	print_apply_result_box($retval);
} else if (is_subsystem_dirty('vip')) {
	print_apply_box(gettext("VIP 구성이 변경되었습니다.") . "<br />" . gettext("변경사항을 저장하시면 적용됩니다."));
}

/* active tabs
$tab_array = array();
$tab_array[] = array(gettext("Virtual IPs"), true, "firewall_virtual_ip.php");
 $tab_array[] = array(gettext("CARP Settings"), false, "system_hasync.php");
display_top_tabs($tab_array);
*/
?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('가상 IP ')?></h2></div>
	<div class="panel-body table-responsive">
		<table class="table table-striped table-hover table-condensed table-rowdblclickedit sortable-theme-bootstrap" data-sortable>
			<thead>
				<tr>
					<th><?=gettext("가상 IP 주소")?></th>
					<th><?=gettext("인터페이스")?></th>
					<th><?=gettext("타입")?></th>
					<th><?=gettext("Description")?></th>
					<th><?=gettext("Actions")?></th>
				</tr>
			</thead>
			<tbody>
<?php
$interfaces = get_configured_interface_with_descr(true);
$viplist = get_configured_vip_list();

foreach ($viplist as $vipname => $address) {
	$interfaces[$vipname] = $address;
	$interfaces[$vipname] .= " (";
	if (get_vip_descr($address)) {
		$interfaces[$vipname] .= get_vip_descr($address);
	} else {
		$vip = get_configured_vip($vipname);
		$interfaces[$vipname] .= "vhid: {$vip['vhid']}";
	}
	$interfaces[$vipname] .= ")";
}

$interfaces['lo0'] = "Localhost";

$i = 0;
foreach ($a_vip as $vipent):
	if ($vipent['subnet'] != "" or $vipent['range'] != "" or
		$vipent['subnet_bits'] != "" or (isset($vipent['range']['from']) && $vipent['range']['from'] != "")):
?>
				<tr>
					<td>
<?php
	if (($vipent['type'] == "single") || ($vipent['type'] == "network")) {
		if ($vipent['subnet_bits']) {
			print("{$vipent['subnet']}/{$vipent['subnet_bits']}");
		}
	}

	if ($vipent['type'] == "range") {
		print("{$vipent['range']['from']}-{$vipent['range']['to']}");
	}

	if ($vipent['mode'] == "carp") {
		print(" (vhid: {$vipent['vhid']})");
	}
?>
					</td>
					<td>
						<?=htmlspecialchars($interfaces[$vipent['interface']])?>&nbsp;
					</td>
					<td>
						<?=$types[$vipent['mode']]?>
					</td>
					<td>
						<?=htmlspecialchars($vipent['descr'])?>
					</td>
					<td>
						<a class="fa fa-pencil" title="<?=gettext("가상 IP 편집"); ?>" href="firewall_virtual_ip_edit.php?id=<?=$i?>"></a>
						<a class="fa fa-trash"	title="<?=gettext("가상 IP 삭제")?>" href="firewall_virtual_ip.php?act=del&amp;id=<?=$i?>" usepost></a>
					</td>
				</tr>
<?php
	endif;
	$i++;
endforeach;
?>
			</tbody>
		</table>
	</div>
</div>

<nav class="action-buttons">
	<a href="firewall_virtual_ip_edit.php" class="btn btn-sm btn-success">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext('추가')?>
	</a>
</nav>

<div class="infoblock">
	<?php print_info_box(sprintf(gettext('해당 페이지에 정의 된 가상 IP 주소는 %1$sNAT%2$s 매핑에 사용될 수 있습니다.'), '<a href="firewall_nat.php">', '</a>') . '<br />' .
		sprintf(gettext('가상 IP와 인터페이스의 상태를 %1$s이곳에서%2$s 확인하실 수 .'), '<a href="status_carp.php">', '</a>'), 'info', false); ?>
</div>

<?php
include("foot.inc");
