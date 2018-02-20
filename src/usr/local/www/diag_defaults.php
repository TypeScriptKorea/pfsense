<?php
/*
 * diag_defaults.php
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
##|*IDENT=page-diagnostics-factorydefaults
##|*NAME=Diagnostics: Factory defaults
##|*DESCR=Allow access to the 'Diagnostics: Factory defaults' page.
##|*WARN=standard-warning-root
##|*MATCH=diag_defaults.php*
##|-PRIV

require_once("guiconfig.inc");

if ($_POST['Submit'] == " " . gettext("아니오") . " ") {
	header("Location: index.php");
	exit;
}

$pgtitle = array(gettext("진단"), gettext("Factory Defaults"));
include("head.inc");
?>

<?php if ($_POST['Submit'] == " " . gettext("예") . " "):
	print_info_box(gettext("시스템이 공장 초기화로 재설정되었으며 현재 재부팅 중입니다."))?>
<pre>
<?php
	reset_factory_defaults();
	system_reboot();
?>
</pre>
<?php else:?>
<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title"><?=gettext("공장 초기화 및 재설정")?></h2>
	</div>
	<div class="panel-body">
		<div class="content">
			<form action="diag_defaults.php" method="post">
				<p><strong><?=gettext('시스템을 공장 초기화로 재설정하면 모든 사용자 구성이 제거되고 다음과 같은 설정이 적용됩니다.:')?></strong></p>
				<ul>
					<li><?=gettext("공장 초기화를 통한 시스템 재구성")?></li>
					<li><?=gettext("LAN IP 주소가 192.168.1.1 로 리셋됩니다.")?></li>
					<li><?=gettext("시스템이 기본 LAN인터페이스에서 DHCP서버로 구성됩니다.")?></li>
					<li><?=gettext("변경사항 설치 후 재부팅됩니다.")?></li>
					<li><?=gettext("WAN 인터페이스가 DHCP 서버에서 자동으로 주소를 가져오도록 설정됩니다.")?></li>
					<li><?=gettext("webConfigurator admin 사용자 이름이 'admin'으로 리셋됩니다.")?></li>
					<li><?=sprintf(gettext("webConfigurator admin 암호가 '%s'(으)로 리셋됩니다."), $g['factory_shipped_password'])?></li>
				</ul>
				<p><strong><?=gettext("Are you sure you want to proceed?")?></strong></p>
				<p>
					<button name="Submit" type="submit" class="btn btn-sm btn-danger" value=" <?=gettext("예")?> " title="<?=gettext("공장 초기화 실행")?>">
						<i class="fa fa-undo"></i>
						<?=gettext("공장 초기화")?>
					</button>
					<button name="Submit" type="submit" class="btn btn-sm btn-success" value=" <?=gettext("")?> " title="<?=gettext("대시보드로 돌아가기")?>">
						<i class="fa fa-save"></i>
						<?=gettext("구성 유지")?>
					</button>
				</p>
			</form>
		</div>
	</div>
</div>
<?php endif?>
<?php include("foot.inc")?>
