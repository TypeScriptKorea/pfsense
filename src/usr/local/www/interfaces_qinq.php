<?php
/*
 * interfaces_qinq.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2018 Rubicon Communications, LLC (Netgate)
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
2018.02.26
한글화 번역 시작
*/

##|+PRIV
##|*IDENT=page-interfaces-qinq
##|*NAME=Interfaces: QinQ
##|*DESCR=Allow access to the 'Interfaces: QinQ' page.
##|*MATCH=interfaces_qinq.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");

if (!is_array($config['qinqs']['qinqentry'])) {
	$config['qinqs']['qinqentry'] = array();
}

$a_qinqs = &$config['qinqs']['qinqentry'];

if ($_POST['act'] == "del") {
	$id = $_POST['id'];

	/* check if still in use */
	if (isset($a_qinqs) && vlan_inuse($a_qinqs[$id])) {
		$input_errors[] = gettext("QinQ를 여전히 인터페이스로 사용중입니다. 삭제할 수 없습니다.");
	} elseif (empty($a_qinqs[$id]['vlanif']) || !does_interface_exist($a_qinqs[$id]['vlanif'])) {
		$input_errors[] = gettext("QinQ인터페이스가 존재하지 않습니다.");
	} else {
		$qinq =& $a_qinqs[$id];

		$delmembers = explode(" ", $qinq['members']);
		foreach ($delmembers as $tag) {
			if (qinq_inuse($qinq, $tag)) {
				$input_errors[] = gettext("해당 QinQ의 태그 중 하나가 인터페이스로 계속 사용되고 있기 때문에 삭제할 수 없습니다.");
				break;
			}
		}
	}

	if (empty($input_errors)) {
		$qinq =& $a_qinqs[$id];

		$ngif = str_replace(".", "_", $qinq['vlanif']);
		$delmembers = explode(" ", $qinq['members']);
		foreach ($delmembers as $tag) {
			mwexec("/usr/sbin/ngctl shutdown {$ngif}h{$tag}:  > /dev/null 2>&1");
		}
		mwexec("/usr/sbin/ngctl shutdown {$ngif}qinq: > /dev/null 2>&1");
		mwexec("/usr/sbin/ngctl shutdown {$ngif}: > /dev/null 2>&1");
		pfSense_interface_destroy($qinq['vlanif']);
		unset($a_qinqs[$id]);

		write_config();

		header("Location: interfaces_qinq.php");
		exit;
	}
}

$pgtitle = array(gettext("Interfaces"), gettext("QinQs"));
$shortcut_section = "interfaces";
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$tab_array = array();
$tab_array[] = array(gettext("Interface Assignments"), false, "interfaces_assign.php");
$tab_array[] = array(gettext("Interface Groups"), false, "interfaces_groups.php");
$tab_array[] = array(gettext("Wireless"), false, "interfaces_wireless.php");
$tab_array[] = array(gettext("VLANs"), false, "interfaces_vlan.php");
$tab_array[] = array(gettext("QinQs"), true, "interfaces_qinq.php");
$tab_array[] = array(gettext("PPPs"), false, "interfaces_ppps.php");
$tab_array[] = array(gettext("GREs"), false, "interfaces_gre.php");
$tab_array[] = array(gettext("GIFs"), false, "interfaces_gif.php");
$tab_array[] = array(gettext("Bridges"), false, "interfaces_bridge.php");
$tab_array[] = array(gettext("LAGGs"), false, "interfaces_lagg.php");
display_top_tabs($tab_array);

?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('QinQ Interfaces')?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-striped table-hover table-condensed table-rowdblclickedit">
				<thead>
					<tr>
						<th><?=gettext("인터페이스"); ?></th>
						<th><?=gettext("태그");?></th>
						<th><?=gettext("QinQ 멤버"); ?></th>
						<th><?=gettext("Description"); ?></th>
						<th><?=gettext("Actions"); ?></th>
					</tr>
				</thead>
				<tbody>
<?php foreach ($a_qinqs as $i => $qinq):?>
					<tr>
						<td>
							<?=htmlspecialchars($qinq['if'])?>
						</td>
						<td>
							<?=htmlspecialchars($qinq['tag'])?>
						</td>
						<td>
<?php if (strlen($qinq['members']) > 20):?>
							<?=substr(htmlspecialchars($qinq['members']), 0, 20)?>&hellip;
<?php else:?>
							<?=htmlspecialchars($qinq['members'])?>
<?php endif; ?>
						</td>
						<td>
							<?=htmlspecialchars($qinq['descr'])?>&nbsp;
						</td>
						<td>
							<a class="fa fa-pencil"	title="<?=gettext('Q-in-Q 인터페이스 편집')?>"	href="interfaces_qinq_edit.php?id=<?=$i?>"></a>
							<a class="fa fa-trash"	title="<?=gettext('Q-in-Q 인터페이스 삭제')?>"	href="interfaces_qinq.php?act=del&amp;id=<?=$i?>" usepost></a>
						</td>
					</tr>
<?php
endforeach;
?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<nav class="action-buttons">
	<a href="interfaces_qinq_edit.php" class="btn btn-success btn-sm">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext("추가")?>
	</a>
</nav>

<div class="infoblock">
	<?php print_info_box(sprintf(gettext('NIC는 802.1QQi. 태깅을 제대로 지원합니다(모든 드라이버가 지원하는 기능은 아님). %1$s가 카드를 명시적으로 지원하지 않을 경우, ' .
		'QinQ 태깅은 작동할 수 있으나 MTU가 낮아지면 문제가 발생할 수 있습니다. %1$s' .
		'지원 카드에 대한 자세한 설명은 %2$s핸드북을 참조하시길 바랍니다.'), '<br />', $g['product_name']), 'info', false); ?>
</div>

<?php
include("foot.inc");
