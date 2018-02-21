<?php
/*
 * diag_resetstate.php
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
##|*IDENT=page-diagnostics-resetstate
##|*NAME=Diagnostics: Reset states
##|*DESCR=Allow access to the 'Diagnostics: Reset states' page.
##|*MATCH=diag_resetstate.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("filter.inc");

if ($_POST) {
	$savemsg = "";

	if ($_POST['statetable']) {
		filter_flush_state_table();
		if ($savemsg) {
			$savemsg .= " ";
		}
		$savemsg .= gettext("The state table has been flushed successfully.");/*has been flushed??? 무슨뜻인지 모르겠다.*/
	}

	if ($_POST['sourcetracking']) {
		mwexec("/sbin/pfctl -F Sources");
		if ($savemsg) {
			$savemsg .= " <br />";
		}
		$savemsg .= gettext("The source tracking table has been flushed successfully.");
	}
}

$pgtitle = array(gettext("Diagnostics"), gettext("States"), gettext("Reset States"));
$pglinks = array("", "diag_dump_states.php", "@self");
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

$statetablehelp = sprintf(gettext('상태 테이블을 재설정하면 해당 테이블에서 모든 항목이 제거됩니다. 즉, 모든연결이 끊어지며 다시 설정하셔야 한다는 의미입니다.' .
					'이 기능은 방화벽 또는 NAT규칙을 변경한 후 필요할 수 있으며, 특히, 사용중이신 IP매핑 프로토콜(예:PPTP 아니면 IPv6같은 경우)이 있는 경우 필요할 수 있습니다. %1$s' .
					'방화벽은 규칙을 변경할 때 일반적으로 상태 테이블을 그대로 유지합니다. %2$s' .
					'%3$sNOTE:%4$s 상태 테이블 재설정을 위해 &quot;재설정&quot; 버튼을 클릭하면 브라우저 세션이 중단된 것처럼 보일 수 있습니다.' .
					'계속하시려면 페이지를 새로고침 하십시오.'), "<br /><br />", "<br /><br />", "<strong>", "</strong>");

$sourcetablehelp = sprintf(gettext('원본 추적 테이블을 재설정하면 모든 발신/수신 연결이제거됩니다. ' .
					'이는 "고정" 발신/수신 연결이 모든 클라이언트에 대해 지워짐을 의미합니다.%s' .
					'이는 연결상태는 지우지 않고 오로지 발신 추적만을 삭제합니다.'), "<br /><br />");

$tab_array = array();
$tab_array[] = array(gettext("상태"), false, "diag_dump_states.php");

if (isset($config['system']['lb_use_sticky'])) {
	$tab_array[] = array(gettext("발신지 추적"), false, "diag_dump_states_sources.php");
}

$tab_array[] = array(gettext("상태 리셋"), true, "diag_resetstate.php");
display_top_tabs($tab_array);

$form = new Form(false);

$section = new Form_Section('상태 리셋 옵션');

$section->addInput(new Form_Checkbox(
	'statetable',
	'State Table',
	'Reset the firewall state table',
	false
))->setHelp($statetablehelp);

if (isset($config['system']['lb_use_sticky'])) {
	$section->addInput(new Form_Checkbox(
		'sourcetracking',
		'Source Tracking',
		'Reset firewall source tracking',
		false
	))->setHelp($sourcetablehelp);
}

$form->add($section);

$form->addGlobal(new Form_Button(
	'Submit',
	'Reset',
	null,
	'fa-trash'
))->addClass('btn-warning');

print $form;

$nonechecked = gettext("하나 이상의 옵션을 선택하셔야합니다.");
$cfmmsg = gettext("선택한 옵션으로 재설정 하시겠습니까?");
?>

<script type="text/javascript">
//<![CDATA[
	events.push(function(){

		$('form').submit(function(event){
			if ( !($('#statetable').prop("checked") == true) && !($('#sourcetracking').prop("checked") == true)) {
				alert("<?=$nonechecked?>");
				event.preventDefault();
			} else if (!confirm("<?=$cfmmsg?>")) {
				event.preventDefault();
			}
		});
	});
//]]>
</script>

<?php include("foot.inc"); ?>
