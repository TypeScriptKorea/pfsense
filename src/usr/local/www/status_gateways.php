<?php
/*
 * status_gateways.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2018 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2010 Seth Mos <seth.mos@dds.nl>
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
2018.02.28
한글화 번역 시작
*/

##|+PRIV
##|*IDENT=page-status-gateways
##|*NAME=Status: Gateways
##|*DESCR=Allow access to the 'Status: Gateways' page.
##|*MATCH=status_gateways.php*
##|-PRIV

require_once("guiconfig.inc");

define('COLOR', true);

$a_gateways = return_gateways_array();
$gateways_status = array();
$gateways_status = return_gateways_status(true);

$now = time();
$year = date("Y");

$pgtitle = array(gettext("Status"), gettext("Gateways"), gettext("Gateways"));
$pglinks = array("", "@self", "@self");
$shortcut_section = "gateways";
include("head.inc");

/* active tabs */
$tab_array = array();
$tab_array[] = array(gettext("게이트웨이"), true, "status_gateways.php");
$tab_array[] = array(gettext("게이트웨이 "), false, "status_gateway_groups.php");
display_top_tabs($tab_array);
?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('게이트웨이')?></h2></div>
	<div class="panel-body">

<div class="table-responsive">
	<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
		<thead>
			<tr>
				<th><?=gettext("임재원"); ?></th>
				<th><?=gettext("게이트웨이"); ?></th>
				<th><?=gettext("모니터"); ?></th>
				<th><?=gettext("RTT"); ?></th>
				<th><?=gettext("RTTsd"); ?></th>
				<th><?=gettext("Loss"); ?></th>
				<th><?=gettext("Status"); ?></th>
				<th><?=gettext("Description"); ?></th>
			</tr>
		</thead>
		<tbody>
<?php		foreach ($a_gateways as $gname => $gateway) {
?>
			<tr>
				<td>
					<?=htmlspecialchars($gateway['name']);?>
				</td>
				<td>
					<?=lookup_gateway_ip_by_name($gname);?>
				</td>
				<td>
<?php
					if ($gateways_status[$gname]) {
						echo $gateways_status[$gname]['monitorip'];
					} else {
						echo htmlspecialchars($gateway['monitorip']);
					}
?>
				</td>
				<td>
<?php
					if ($gateways_status[$gname]) {
						if (!isset($gateway['monitor_disable'])) {
							echo $gateways_status[$gname]['delay'];
						} 
					} else {
						echo gettext("Pending");
					}
?>
				</td>
				<td>
<?php
					if ($gateways_status[$gname]) {
						if (!isset($gateway['monitor_disable'])) {
							echo $gateways_status[$gname]['stddev'];
						}
					} else {
						echo gettext("Pending");
					}
?>
				</td>
				<td>
<?php
					if ($gateways_status[$gname]) {
						if (!isset($gateway['monitor_disable'])) {
							echo $gateways_status[$gname]['loss'];
						}
					} else {
						echo gettext("Pending");
					}
?>
				</td>
<?php
				if ($gateways_status[$gname]) {
					$status = $gateways_status[$gname];
					if (stristr($status['status'], "force_down")) {
						$online = gettext("오프라인 (forced)");
						$bgcolor = "bg-danger";
					} elseif (stristr($status['status'], "down")) {
						$online = gettext("오프라인");
						$bgcolor = "bg-danger";
					} elseif (stristr($status['status'], "highloss")) {
						$online = gettext("위험, Packetloss") . ': ' . $status['loss'];
						$bgcolor = "bg-danger";
					} elseif (stristr($status['status'], "loss")) {
						$online = gettext("경고, Packetloss") . ': ' . $status['loss'];
						$bgcolor = "bg-warning";
					} elseif (stristr($status['status'], "highdelay")) {
						$online = gettext("위험, Latency") . ': ' . $status['delay'];
						$bgcolor = "bg-danger";
					} elseif (stristr($status['status'], "delay")) {
						$online = gettext("경고, Latency") . ': ' . $status['delay'];
						$bgcolor = "bg-warning";
					} elseif ($status['status'] == "none") {
						if ($status['monitor_disable'] || ($status['monitorip'] == "none")) {
							$online = gettext("온라인 (unmonitored)");
						} else {
							$online = gettext("온라인");
						}
						$bgcolor = "bg-success";
					}
				} else if (isset($gateway['monitor_disable'])) {
					// Note: return_gateways_status() always returns an array entry for all gateways,
					//       so this "else if" never happens.
						$online = gettext("온라인 (unmonitored)");
						$bgcolor = "bg-success";
				} else {
					$online = gettext("Pending");
					$bgcolor = "bg-info";
				}

				$lastchange = $gateways_status[$gname]['lastcheck'];

				if (!COLOR) {
				   $bgcolor = "";
				}
?>

				<td class="<?=$bgcolor?>">
					<strong><?=$online?></strong> <?php
					if (!empty($lastchange)) { ?>
						<br /><i><?=gettext("Last checked")?> <?=$lastchange?></i>
<?php				} ?>
				</td>

				<td>
					<?=htmlspecialchars($gateway['descr']); ?>
				</td>
			</tr>
<?php	} ?>	<!-- End-of-foreach -->
		</tbody>
	</table>
</div>

	</div>
</div>

<?php include("foot.inc"); ?>
