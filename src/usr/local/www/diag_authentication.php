<?php
/*
 * diag_authentication.php
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
2018.02.20
한글화 번역 시작
*/

##|+PRIV
##|*IDENT=page-diagnostics-authentication
##|*NAME=Diagnostics: Authentication
##|*DESCR=Allow access to the 'Diagnostics: Authentication' page.
##|*MATCH=diag_authentication.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("radius.inc");

if ($_POST) {
	$pconfig = $_POST;
	unset($input_errors);

	$authcfg = auth_get_authserver($_POST['authmode']);
	if (!$authcfg) {
		$input_errors[] =  sprintf(gettext('%s 은(는) 유효한 인증서버가 아닙니다.'), $_POST['authmode']);
	}

	if (empty($_POST['username']) || empty($_POST['password'])) {
		$input_errors[] = gettext("사용자 이름과 암호를 지정해주십시오.");
	}

	if (!$input_errors) {
		$attributes = array();
		if (authenticate_user($_POST['username'], $_POST['password'], $authcfg, $attributes)) {
			$savemsg = sprintf(gettext('사용자 %s 가 정상적으로 인증되었습니다.'), $_POST['username']);
			$groups = getUserGroups($_POST['username'], $authcfg, $attributes);
			$savemsg .= "&nbsp;" . gettext("해당 사용자는 그룹맴버입니다.") . ": <br /><br />";
			$savemsg .= "<ul>";
			foreach ($groups as $group) {
				$savemsg .= "<li>" . "{$group} " . "</li>";
			}
			$savemsg .= "</ul>";

		} else {
			$input_errors[] = gettext("인증이 실패하였습니다.");
		}
	}
} else {
	if (isset($config['system']['webgui']['authmode'])) {
		$pconfig['authmode'] = $config['system']['webgui']['authmode'];
	} else {
		$pconfig['authmode'] = "Local Database";
	}
}

$pgtitle = array(gettext("진단"), gettext("인증"));
$shortcut_section = "authentication";
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg, 'success', false);
}

$form = new Form(false);

$section = new Form_Section('Authentication Test');

foreach (auth_get_authserver_list() as $key => $auth_server) {
	$serverlist[$key] = $auth_server['name'];
}

$section->addInput(new Form_Select(
	'authmode',
	'*Authentication Server',
	$pconfig['authmode'],
	$serverlist
))->setHelp('Select the authentication server to test against.');

$section->addInput(new Form_Input(
	'username',
	'*Username',
	'text',
	$pconfig['username'],
	['placeholder' => 'Username']
));

$section->addInput(new Form_Input(
	'password',
	'*Password',
	'password',
	$pconfig['password'],
	['placeholder' => 'Password']
));

$form->add($section);

$form->addGlobal(new Form_Button(
	'Submit',
	'Test',
	null,
	'fa-wrench'
))->addClass('btn-primary');

print $form;

include("foot.inc");
