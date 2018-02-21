<?php
/*
 * firewall_nat.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2018 Rubicon Communications, LLC (Netgate)
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
2018.02.21
한글화 번역 시작
*/

##|+PRIV
##|*IDENT=page-firewall-nat-portforward
##|*NAME=Firewall: NAT: Port Forward
##|*DESCR=Allow access to the 'Firewall: NAT: Port Forward' page.
##|*MATCH=firewall_nat.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("itemid.inc");

if (!is_array($config['nat']['rule'])) {
	$config['nat']['rule'] = array();
}

$a_nat = &$config['nat']['rule'];

/* update rule order, POST[rule] is an array of ordered IDs */
if (array_key_exists('order-store', $_REQUEST)) {
	if (is_array($_REQUEST['rule']) && !empty($_REQUEST['rule'])) {
		$a_nat_new = array();

		// if a rule is not in POST[rule], it has been deleted by the user
		foreach ($_POST['rule'] as $id) {
			$a_nat_new[] = $a_nat[$id];
		}

		$a_nat = $a_nat_new;


		$config['nat']['separator'] = "";

		if ($_POST['separator']) {
			$idx = 0;
			foreach ($_POST['separator'] as $separator) {
				$config['nat']['separator']['sep' . $idx++] = $separator;
			}
		}

		if (write_config()) {
			mark_subsystem_dirty('filter');
		}

		header("Location: firewall_nat.php");
		exit;
	}
}

/* if a custom message has been passed along, lets process it */
if ($_REQUEST['savemsg']) {
	$savemsg = $_REQUEST['savemsg'];
}

if ($_POST['apply']) {

	$retval = 0;

	$retval |= filter_configure();

	pfSense_handle_custom_code("/usr/local/pkg/firewall_nat/apply");

	if ($retval == 0) {
		clear_subsystem_dirty('natconf');
		clear_subsystem_dirty('filter');
	}

}

if ($_POST['act'] == "del") {
	if ($a_nat[$_POST['id']]) {

		if (isset($a_nat[$_POST['id']]['associated-rule-id'])) {
			delete_id($a_nat[$_POST['id']]['associated-rule-id'], $config['filter']['rule']);
			$want_dirty_filter = true;
		}

		unset($a_nat[$_POST['id']]);

		// Update the separators
		$a_separators = &$config['nat']['separator'];
		$ridx = $_POST['id'];
		$mvnrows = -1;
		move_separators($a_separators, $ridx, $mvnrows);

		if (write_config()) {
			mark_subsystem_dirty('natconf');
			if ($want_dirty_filter) {
				mark_subsystem_dirty('filter');
			}
		}

		header("Location: firewall_nat.php");
		exit;
	}
}

if (isset($_POST['del_x'])) {

	/* delete selected rules */
	if (is_array($_POST['rule']) && count($_POST['rule'])) {
		$a_separators = &$config['nat']['separator'];
		$num_deleted = 0;

		foreach ($_POST['rule'] as $rulei) {
			$target = $rule['target'];

			// Check for filter rule associations
			if (isset($a_nat[$rulei]['associated-rule-id'])) {
				delete_id($a_nat[$rulei]['associated-rule-id'], $config['filter']['rule']);
				mark_subsystem_dirty('filter');
			}

			unset($a_nat[$rulei]);

			// Update the separators
			// As rules are deleted, $ridx has to be decremented or separator position will break
			$ridx = $rulei - $num_deleted;
			$mvnrows = -1;
			move_separators($a_separators, $ridx, $mvnrows);
			$num_deleted++;
		}

		if (write_config()) {
			mark_subsystem_dirty('natconf');
		}

		header("Location: firewall_nat.php");
		exit;
	}
} else if ($_POST['act'] == "toggle") {
	if ($a_nat[$_POST['id']]) {
		if (isset($a_nat[$_POST['id']]['disabled'])) {
			unset($a_nat[$_POST['id']]['disabled']);
			$rule_status = true;
		} else {
			$a_nat[$_POST['id']]['disabled'] = true;
			$rule_status = false;
		}

		// Check for filter rule associations
		if (isset($a_nat[$_POST['id']]['associated-rule-id'])) {
			toggle_id($a_nat[$_POST['id']]['associated-rule-id'],
			    $config['filter']['rule'], $rule_status);
			unset($rule_status);
			mark_subsystem_dirty('filter');
		}

		if (write_config(gettext("Firewall: NAT: Port forward, enable/disable NAT rule"))) {
			mark_subsystem_dirty('natconf');
		}
		header("Location: firewall_nat.php");
		exit;
	}
}

$pgtitle = array(gettext("Firewall"), gettext("NAT"), gettext("Port Forward"));
$pglinks = array("", "@self", "@self");
include("head.inc");

if ($_POST['apply']) {
	print_apply_result_box($retval);
}

if (is_subsystem_dirty('natconf')) {
	print_apply_box(gettext('NAT 구성이 변경되었습니다.') . '<br />' .
					gettext('변경사항을 저장하시면 적용됩니다.'));
}

$tab_array = array();
$tab_array[] = array(gettext("Port Forward"), true, "firewall_nat.php");
$tab_array[] = array(gettext("1:1"), false, "firewall_nat_1to1.php");
$tab_array[] = array(gettext("Outbound"), false, "firewall_nat_out.php");
$tab_array[] = array(gettext("NPt"), false, "firewall_nat_npt.php");
display_top_tabs($tab_array);

$columns_in_table = 13;
?>
<!-- Allow table to scroll when dragging outside of the display window -->
<style>
.table-responsive {
    clear: both;
    overflow-x: visible;
    margin-bottom: 0px;
}
</style>

<form action="firewall_nat.php" method="post" name="iform">
	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext('규칙')?></h2></div>
		<div class="panel-body table-responsive">
			<table id="ruletable" class="table table-striped table-hover table-condensed">
				<thead>
					<tr>
						<th><!-- Checkbox --></th>
						<th><!-- Icon --></th>
						<th><!-- Rule type --></th>
						<th><?=gettext("인터페이스")?></th>
						<th><?=gettext("프로토콜")?></th>
						<th><?=gettext("발신 주소")?></th>
						<th><?=gettext("발신 포트")?></th>
						<th><?=gettext("수신 주소")?></th>
						<th><?=gettext("수신 포트")?></th>
						<th><?=gettext("NAT IP")?></th>
						<th><?=gettext("NAT 포트")?></th>
						<th><?=gettext("종류")?></th>
						<th><?=gettext("Actions")?></th>
					</tr>
				</thead>
				<tbody class='user-entries'>
<?php

$nnats = $i = 0;
$separators = $config['nat']['separator'];

// Get a list of separator rows and use it to call the display separator function only for rows which there are separator(s).
// More efficient than looping through the list of separators on every row.
$seprows = separator_rows($separators);

foreach ($a_nat as $natent):

	// Display separator(s) for section beginning at rule n
	if ($seprows[$nnats]) {
		display_separator($separators, $nnats, $columns_in_table);
	}

	$localport = $natent['local-port'];

	list($dstbeginport, $dstendport) = explode("-", $natent['destination']['port']);

	if ($dstendport) {
		$localendport = $natent['local-port'] + $dstendport - $dstbeginport;
		$localport	 .= '-' . $localendport;
	}

	$alias = rule_columns_with_alias(
		$natent['source']['address'],
		pprint_port($natent['source']['port']),
		$natent['destination']['address'],
		pprint_port($natent['destination']['port']),
		$natent['target'],
		$localport
	);

	/* if user does not have access to edit an interface skip on to the next record */
	if (!have_natpfruleint_access($natent['interface'])) {
		continue;
	}

	if (isset($natent['disabled'])) {
		$iconfn = "pass_d";
		$trclass = 'class="disabled"';
	} else {
		$iconfn = "pass";
		$trclass = '';
	}
?>

					<tr id="fr<?=$nnats;?>" <?=$trclass?> onClick="fr_toggle(<?=$nnats;?>)" ondblclick="document.location='firewall_nat_edit.php?id=<?=$i;?>';">
						<td >
							<input type="checkbox" id="frc<?=$nnats;?>" onClick="fr_toggle(<?=$nnats;?>)" name="rule[]" value="<?=$i;?>"/>
						</td>
						<td>
							<a href="?act=toggle&amp;id=<?=$i?>" usepost>
								<i class="fa fa-check" title="<?=gettext("click to toggle enabled/disabled status")?>"></i>
<?php 	if (isset($natent['nordr'])) { ?>
								&nbsp;<i class="fa fa-hand-stop-o text-danger" title="<?=gettext("제외: 해당 규칙은 이후 적용될 규칙에서 NAT을 제외합니다.")?>"></i>
<?php 	} ?>
							</a>
						</td>
						<td>
<?php
	if ($natent['associated-rule-id'] == "pass"):
?>
							<i class="fa fa-play" title="<?=gettext("이 NAT 항목과 일치하는 모든 트래픽이 전달됩니다.")?>"></i>
<?php
	elseif (!empty($natent['associated-rule-id'])):
?>
							<i class="fa fa-random" title="<?=sprintf(gettext("방화벽 규칙 ID %s 은(는) 해당 규칙에 의해 관리됩니다."), htmlspecialchars($natent['associated-rule-id']))?>"></i>
<?php
	endif;
?>
						</td>
						<td>
							<?=$textss?>
<?php
	if (!$natent['interface']) {
		echo htmlspecialchars(convert_friendly_interface_to_friendly_descr("wan"));
	} else {
		echo htmlspecialchars(convert_friendly_interface_to_friendly_descr($natent['interface']));
	}
?>
							<?=$textse?>
						</td>

						<td>
							<?=$textss?><?=strtoupper($natent['protocol'])?><?=$textse?>
						</td>

						<td>


<?php
	if (isset($alias['src'])):
?>
							<a href="/firewall_aliases_edit.php?id=<?=$alias['src']?>" data-toggle="popover" data-trigger="hover focus" title="<?=gettext('Alias 자세히')?>" data-content="<?=alias_info_popup($alias['src'])?>" data-html="true">
<?php
	endif;
?>
							<?=str_replace('_', '_<wbr>', htmlspecialchars(pprint_address($natent['source'])))?>
<?php
	if (isset($alias['src'])):
?>
							</a>
<?php
	endif;
?>
						</td>
						<td>
<?php
	if (isset($alias['srcport'])):
?>
							<a href="/firewall_aliases_edit.php?id=<?=$alias['srcport']?>" data-toggle="popover" data-trigger="hover focus" title="<?=gettext('Alias 자세히')?>" data-content="<?=alias_info_popup($alias['srcport'])?>" data-html="true">
<?php
	endif;
?>
							<?=str_replace('_', '_<wbr>', htmlspecialchars(pprint_port($natent['source']['port'])))?>
<?php
	if (isset($alias['srcport'])):
?>
							</a>
<?php
	endif;
?>
						</td>

						<td>
<?php
	if (isset($alias['dst'])):
?>
							<a href="/firewall_aliases_edit.php?id=<?=$alias['dst']?>" data-toggle="popover" data-trigger="hover focus" title="<?=gettext('Alias 자세히')?>" data-content="<?=alias_info_popup($alias['dst'])?>" data-html="true">
<?php
	endif;
?>
							<?=str_replace('_', '_<wbr>', htmlspecialchars(pprint_address($natent['destination'])))?>
<?php
	if (isset($alias['dst'])):
?>
							</a>
<?php
	endif;
?>
						</td>
						<td>
<?php
	if (isset($alias['dstport'])):
?>
							<a href="/firewall_aliases_edit.php?id=<?=$alias['dstport']?>" data-toggle="popover" data-trigger="hover focus" title="<?=gettext('Alias 자세히')?>" data-content="<?=alias_info_popup($alias['dstport'])?>" data-html="true">
<?php
	endif;
?>
							<?=str_replace('_', '_<wbr>', htmlspecialchars(pprint_port($natent['destination']['port'])))?>
<?php
	if (isset($alias['dstport'])):
?>
							</a>
<?php
	endif;
?>
						</td>
						<td>
<?php
	if (isset($alias['target'])):
?>
							<a href="/firewall_aliases_edit.php?id=<?=$alias['target']?>" data-toggle="popover" data-trigger="hover focus" title="<?=gettext('Alias 자세히')?>" data-content="<?=alias_info_popup($alias['target'])?>" data-html="true" >
<?php
	endif;
?>

							<?=str_replace('_', '_<wbr>', htmlspecialchars($natent['target']))?>
<?php
	if (isset($alias['target'])):
?>
							</a>
<?php
	endif;
?>
						</td>
						<td>
<?php
	if (isset($alias['targetport'])):
?>
							<a href="/firewall_aliases_edit.php?id=<?=$alias['targetport']?>" data-toggle="popover" data-trigger="hover focus" title="<?=gettext('Alias 자세히')?>" data-content="<?=alias_info_popup($alias['targetport'])?>" data-html="true">
<?php
	endif;
?>
							<?=str_replace('_', '_<wbr>', htmlspecialchars(pprint_port($localport)))?>
<?php
	if (isset($alias['targetport'])):
?>
							</a>
<?php
	endif;
?>
						</td>

						<td>
							<?=htmlspecialchars($natent['descr'])?>
						</td>
						<td>
							<a class="fa fa-pencil" title="<?=gettext("규칙 편집"); ?>" href="firewall_nat_edit.php?id=<?=$i?>"></a>
							<a class="fa fa-clone"	  title="<?=gettext("새로운 NAT 추가")?>" href="firewall_nat_edit.php?dup=<?=$i?>"></a>
							<a class="fa fa-trash"	title="<?=gettext("규칙 삭제")?>" href="firewall_nat.php?act=del&amp;id=<?=$i?>" usepost></a>
						</td>
					</tr>
<?php
	$i++;
	$nnats++;

endforeach;

// There can be separator(s) after the last rule listed.
if ($seprows[$nnats]) {
	display_separator($separators, $nnats, $columns_in_table);
}
?>
				</tbody>
			</table>
		</div>
	</div>

	<nav class="action-buttons">
		<a href="firewall_nat_edit.php?after=-1" class="btn btn-sm btn-success" title="<?=gettext('맨 위에 규칙 추가')?>">
			<i class="fa fa-level-up icon-embed-btn"></i>
			<?=gettext('추가')?>
		</a>
		<a href="firewall_nat_edit.php" class="btn btn-sm btn-success" title="<?=gettext('맨 아래에 규칙 추가')?>">
			<i class="fa fa-level-down icon-embed-btn"></i>
			<?=gettext('추가')?>
		</a>
		<button name="del_x" type="submit" class="btn btn-danger btn-sm" title="<?=gettext('선택한 규칙 삭제')?>">
			<i class="fa fa-trash icon-embed-btn"></i>
			<?=gettext("삭제"); ?>
		</button>
		<button type="submit" id="order-store" name="order-store" class="btn btn-primary btn-sm" disabled title="<?=gettext('순서 저장')?>">
			<i class="fa fa-save icon-embed-btn"></i>
			<?=gettext("Save")?>
		</button>
		<button type="submit" id="addsep" name="addsep" class="btn btn-sm btn-warning" title="<?=gettext('구분 기호 추가')?>">
			<i class="fa fa-plus icon-embed-btn"></i>
			<?=gettext("구분 기호")?>
		</button>
	</nav>
</form>

<script type="text/javascript">
//<![CDATA[
//Need to create some variables here so that jquery/pfSenseHelpers.js can read them
iface = "<?=strtolower($if)?>";
cncltxt = '<?=gettext("취소")?>';
svtxt = '<?=gettext("저장")?>';
svbtnplaceholder = '<?=gettext("설명을 입력하시고 저장하신 뒤, 마지막 위치로 드래그 하십시오.")?>';
configsection = "nat";
dirty = false;

events.push(function() {

<?php if(!isset($config['system']['webgui']['roworderdragging'])): ?>
	// Make rules sortable
	$('table tbody.user-entries').sortable({
		cursor: 'grabbing',
		update: function(event, ui) {
			$('#order-store').removeAttr('disabled');
			dirty = true;
			reindex_rules(ui.item.parent('tbody'));
			dirty = true;
		}
	});
<?php endif; ?>

	// Check all of the rule checkboxes so that their values are posted
	$('#order-store').click(function () {
	   $('[id^=frc]').prop('checked', true);

		// Save the separator bar configuration
		save_separators();

		// Suppress the "Do you really want to leave the page" message
		saving = true;

	});

	// Globals
	saving = false;
	dirty = false;

	// provide a warning message if the user tries to change page before saving
	$(window).bind('beforeunload', function(){
		if (!saving && dirty) {
			return ("<?=gettext('하나 이상의 규칙이 이동되었으나 저장되지 않았습니다.')?>");
		} else {
			return undefined;
		}
	});
});
//]]>
</script>
<?php

if (count($a_nat) > 0) {
?>
<!-- Legend -->
<div>
	<dl class="dl-horizontal responsive">
		<dt><?=gettext('Legend')?></dt>					<dd></dd>
		<dt><i class="fa fa-play"></i></dt>			<dd><?=gettext('넘어가기')?></dd>
		<dt><i class="fa fa-random"></i></dt>		<dd><?=gettext('규칙 링크')?></dd>
	</dl>
</div>

<?php
}

include("foot.inc");
