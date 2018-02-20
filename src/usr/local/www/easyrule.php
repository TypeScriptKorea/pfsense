<?php
/*
 * easyrule.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2018 Rubicon Communications, LLC (Netgate)
 * Originally Sponsored By Anathematic @ pfSense Forums
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
2018.02.20
한글화 번역 시작
*/

##|+PRIV
##|*IDENT=page-firewall-easyrule
##|*NAME=Firewall: Easy Rule add/status
##|*DESCR=Allow access to the 'Firewall: Easy Rule' add/status page.
##|*MATCH=easyrule.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("easyrule.inc");
require_once("filter.inc");
require_once("shaper.inc");

$retval = 0;
$message = "";
$confirmed = isset($_POST['confirmed']) && $_POST['confirmed'] == 'true';

/* $specialsrcdst must be a defined global for functions being called. */
global $specialsrcdst;
$specialsrcdst = explode(" ", "any pppoe l2tp openvpn");

if ($_POST && $confirmed && isset($_POST['action'])) {
	switch ($_POST['action']) {
		case 'block':
			/* Check that we have a valid host */
			$message = easyrule_parse_block($_POST['int'], $_POST['src'], $_POST['ipproto']);
			break;
		case 'pass':
			$message = easyrule_parse_pass($_POST['int'], $_POST['proto'], $_POST['src'], $_POST['dst'], $_POST['dstport'], $_POST['ipproto']);
			break;
		default:
			$message = gettext("잘못된 동작입니다.");
	}
}

if (stristr($retval, "error") == true) {
	$message = $retval;
}

$pgtitle = array(gettext("방화벽"), gettext("Easy Rule"));
include("head.inc");
if ($input_errors) {
	print_input_errors($input_errors);
}
?>
<form action="easyrule.php" method="post">
	<div class="panel panel-default">
		<div class="panel-heading">
			<h2 class="panel-title">
				<?=gettext("Easy Rule을 추가하는데 필요한 확인");?>
			</h2>
		</div>
		<div class="panel-body">
			<div class="content">
<?php
if (!$confirmed && !empty($_REQUEST['action'])) { ?>
	<?php if ($_REQUEST['action'] == 'block'): ?>
				<b><?=gettext("규칙 타입")?>:</b> <?=htmlspecialchars(ucfirst(gettext($_REQUEST['action'])))?>
				<br/><b><?=gettext("인터페이스")?>:</b> <?=htmlspecialchars(strtoupper($_REQUEST['int']))?>
				<input type="hidden" name="int" value="<?=htmlspecialchars($_REQUEST['int'])?>" />
				<br/><b><?= gettext("발신지") ?>:</b> <?=htmlspecialchars($_REQUEST['src'])?>
				<input type="hidden" name="src" value="<?=htmlspecialchars($_REQUEST['src'])?>" />
				<br/><b><?=gettext("IP 프로토콜")?>:</b> <?=htmlspecialchars(ucfirst($_REQUEST['ipproto']))?>
				<input type="hidden" name="ipproto" value="<?=htmlspecialchars($_REQUEST['ipproto'])?>" />
	<?php elseif ($_REQUEST['action'] == 'pass'): ?>
				<b><?=gettext("규칙 타입")?>:</b> <?=htmlspecialchars(ucfirst(gettext($_REQUEST['action'])))?>
				<br/><b><?=gettext("인터페이스")?>:</b> <?=htmlspecialchars(strtoupper($_REQUEST['int']))?>
				<input type="hidden" name="int" value="<?=htmlspecialchars($_REQUEST['int'])?>" />
				<br/><b><?=gettext("프로토콜")?>:</b> <?=htmlspecialchars(strtoupper($_REQUEST['proto']))?>
				<input type="hidden" name="proto" value="<?=htmlspecialchars($_REQUEST['proto'])?>" />
				<br/><b><?=gettext("발신지")?>:</b> <?=htmlspecialchars($_REQUEST['src'])?>
				<input type="hidden" name="src" value="<?=htmlspecialchars($_REQUEST['src'])?>" />
				<br/><b><?=gettext("수신지")?>:</b> <?=htmlspecialchars($_REQUEST['dst'])?>
				<input type="hidden" name="dst" value="<?=htmlspecialchars($_REQUEST['dst'])?>" />
				<br/><b><?=gettext("수신 포트")?>:</b> <?=htmlspecialchars($_REQUEST['dstport'])?>
				<input type="hidden" name="dstport" value="<?=htmlspecialchars($_REQUEST['dstport'])?>" />
				<br/><b><?=gettext("IP 프로토콜")?>:</b> <?=htmlspecialchars(ucfirst($_REQUEST['ipproto']))?>
				<input type="hidden" name="ipproto" value="<?=htmlspecialchars($_REQUEST['ipproto'])?>" />
	<?php	else:
			$message = gettext("잘못된 동작입니다.");
		endif; ?>
				<br/><br/>
	<?php if (empty($message)): ?>
				<input type="hidden" name="action" value="<?=htmlspecialchars($_REQUEST['action'])?>" />
				<input type="hidden" name="confirmed" value="true" />
				<button type="submit" class="btn btn-success" name="erconfirm" id="erconfirm" value="<?=gettext("확인")?>">
					<i class="fa fa-check icon-embed-btn"></i>
					<?=gettext("확인")?>
				</button>
	<?php endif;
}

if ($message) {
	print_info_box($message);
} elseif (empty($_REQUEST['action'])) {
	print_info_box(
		gettext('해당 페이지는 Easy Rule 설정 페이지로, 규칙을 추가할 때 표시할 오류에 사용됩니다.') . ' ' .
		gettext('감지된 오류는 없으며, 해당 페이지의 역할에 대한 지침 없이 탐색되었습니다.') .
		'<br /><br />' .
		gettext('해당 페이지는 방화벽 로그 페이지의 차단/통과 버튼에서 호출하실 수 있습니다.') .
		', <a href="status_logs_filter.php">' . gettext("상태") . ' &gt; ' . gettext('시스템 로그') . ', ' . gettext('방화벽 탭') . '</a>.<br />');
}
?>
			</div>
		</div>
	</div>
</form>
<?php include("foot.inc"); ?>
