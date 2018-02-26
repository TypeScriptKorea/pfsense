<?php
/*
 * interfaces_wireless_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2018 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2010 Erik Fonnesbeck
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
한글화 번역 시작
2018.02.26
*/

##|+PRIV
##|*IDENT=page-interfaces-wireless-edit
##|*NAME=Interfaces: Wireless: Edit
##|*DESCR=Allow access to the 'Interfaces: Wireless: Edit' page.
##|*MATCH=interfaces_wireless_edit.php*
##|-PRIV

require_once("guiconfig.inc");

if (!is_array($config['wireless'])) {
	$config['wireless'] = array();
}

if (!is_array($config['wireless']['clone'])) {
	$config['wireless']['clone'] = array();
}

$a_clones = &$config['wireless']['clone'];

function clone_inuse($num) {
	global $config, $a_clones;

	$iflist = get_configured_interface_list(true);
	foreach ($iflist as $if) {
		if ($config['interfaces'][$if]['if'] == $a_clones[$num]['cloneif']) {
			return true;
		}
	}

	return false;
}

function clone_compare($a, $b) {
	return strnatcmp($a['cloneif'], $b['cloneif']);
}

$portlist = get_interface_list();

if (is_numericint($_REQUEST['id'])) {
	$id = $_REQUEST['id'];
}

if (isset($id) && $a_clones[$id]) {
	$pconfig['if'] = $a_clones[$id]['if'];
	$pconfig['cloneif'] = $a_clones[$id]['cloneif'];
	$pconfig['mode'] = $a_clones[$id]['mode'];
	$pconfig['descr'] = $a_clones[$id]['descr'];
}

if ($_POST['save']) {
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "if mode");
	$reqdfieldsn = array(gettext("Parent interface"), gettext("Mode"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (!$input_errors) {
		$clone = array();
		$clone['if'] = $_POST['if'];
		$clone['mode'] = $_POST['mode'];
		$clone['descr'] = $_POST['descr'];

		if (isset($id) && $a_clones[$id]) {
			if ($clone['if'] == $a_clones[$id]['if']) {
				$clone['cloneif'] = $a_clones[$id]['cloneif'];
			}
		}

		if (!$clone['cloneif']) {
			$clone_id = 0;
			do {
				$clone_exists = false;
				$clone['cloneif'] = "{$_POST['if']}_wlan{$clone_id}";
				foreach ($a_clones as $existing) {
					if ($clone['cloneif'] == $existing['cloneif']) {
						$clone_exists = true;
						$clone_id++;
						break;
					}
				}
			} while ($clone_exists);
		}

		if (isset($id) && $a_clones[$id]) {
			if (clone_inuse($id)) {
				if ($clone['if'] != $a_clones[$id]['if']) {
					$input_errors[] = gettext("이 무선 클론은 인터페이스로 할당되어 있으므로 수정할 수 없습니다.");
				} else if ($clone['mode'] != $a_clones[$id]['mode']) {
					$input_errors[] = gettext("할당된 인터페이스의 구성 페이지를 사용하여 모드를 변경합니다.");
				}
			}
		}

		if (!$input_errors) {
			if (!interface_wireless_clone($clone['cloneif'], $clone)) {
				$input_errors[] = sprintf(gettext('모드 %1$s와 관련된 인터페이스를 생성하는 동안 오류가 발생했습니다.	 %2$s인터페이스는 선택한 모드를 사용하여 더 많은 클론을 생성하지 못할 수 있습니다.'), $wlan_modes[$clone['mode']], $clone['if']);
			} else {
				if (isset($id) && $a_clones[$id]) {
					if ($clone['if'] != $a_clones[$id]['if']) {
						pfSense_interface_destroy($a_clones[$id]['cloneif']);
					}
					$input_errors[] = sprintf(gettext("ID%s로 만들기"), $id);
					$a_clones[$id] = $clone;
				} else {
					$input_errors[] = gettext("ID 없이 ");
					$a_clones[] = $clone;
				}

				usort($a_clones, "clone_compare");
				write_config();

				header("Location: interfaces_wireless.php");
				exit;
			}
		}
	}
}

function build_parent_list() {
	global $g;

	$parentlist = array();
	$portlist = interface_list_wireless();
	$count = 0;
	foreach ($portlist as $ifn) {
		$parentlist[$ifn['if']] = htmlspecialchars($ifn['descr']);
		$count++;
	}

	if ($count > 0) {
		return($parentlist);
	} else {
		return(array('0' => gettext('사용할 수 없음')));
	}
}

$pgtitle = array(gettext("Interfaces"), gettext("Wireless"), gettext("편집"));
$pglinks = array("", "interfaces_wireless.php", "@self");
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form();

$section = new Form_Section('무선 인터페이스 구성');

$section->addInput(new Form_Select(
	'if',
	'*Parent Interface',
	$pconfig['if'],
	build_parent_list()
));

$section->addInput(new Form_Select(
	'mode',
	'*Mode',
	$pconfig['mode'],
	array(
		'bss' => gettext('Infrastructure (BSS)'),
		'adhoc' => gettext('Ad-hoc (IBSS)'),
		'hostap' => gettext('Access Point')
	)
));

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('A description may be entered here for administrative reference (not parsed).');

$section->addInput(new Form_Input(
	'cloneif',
	null,
	'hidden',
	$pconfig['cloneif']
));

if (isset($id) && $a_clones[$id]) {
	$section->addInput(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));
}

$form->add($section);
print($form);

include("foot.inc");
