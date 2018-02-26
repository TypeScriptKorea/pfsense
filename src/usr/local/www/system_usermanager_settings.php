<?php
/*
 * system_usermanager_settings.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2018 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2007 Bill Marquette <bill.marquette@gmail.com>
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
##|*IDENT=page-system-usermanager-settings
##|*NAME=System: User Manager: Settings
##|*DESCR=Allow access to the 'System: User Manager: Settings' page.
##|*WARN=standard-warning-root
##|*MATCH=system_usermanager_settings.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("auth.inc");

// Test LDAP settings in response to an AJAX request from this page.
if ($_REQUEST['ajax']) {

	if (isset($config['system']['authserver'][0]['host'])) {
		$auth_server = $config['system']['authserver'][0]['host'];
		$authserver = $_REQUEST['authserver'];
		$authcfg = auth_get_authserver($authserver);
	}

	if (!$authcfg) {
		printf(gettext('%1$s오류: %2$s%3$s에 대한 설정을 찾을 수 없습니다.'), '<span class="text-danger">', htmlspecialchars($authserver), "</span>");
		exit;
	} else {
		print("<pre>");

		print('<table class="table table-hover table-striped table-condensed">');

		print("<tr><td>" . sprintf(gettext('%1$s%2$s%3$s에 연결 시도 중'), "<td><center>", htmlspecialchars($auth_server), "</center></td>"));
		if (ldap_test_connection($authcfg)) {
			print("<td><span class=\"text-center text-success\">" . gettext("네") . "</span></td></tr>");

			print("<tr><td>" . sprintf(gettext('%1$s%2$s%3$s에 바인딩 시도 중'), "<td><center>", htmlspecialchars($auth_server), "</center></td>"));
			if (ldap_test_bind($authcfg)) {
				print('<td><span class="text-center text-success">' . gettext("네") . "</span></td></tr>");

				print("<tr><td>" . sprintf(gettext('%1$s%2$s%3$s에서 조직 단위를 가져오는 중'), "<td><center>", htmlspecialchars($auth_server), "</center></td>"));
				$ous = ldap_get_user_ous(true, $authcfg);

				if (count($ous)>1) {
					print('<td><span class="text-center text-success">' . gettext("네") . "</span></td></tr>");
					print('<tr ><td colspan="3">');

					if (is_array($ous)) {
						print("<b>" . gettext("조직 구성 단위를 발견하였습니다.") . "</b>");
						print('<table class="table table-hover">');
						foreach ($ous as $ou) {
							print("<tr><td>" . $ou . "</td></tr>");
						}

					print("</td></tr>");
					print("</table>");
					}
				} else {
					print("<td><span class=\"text-alert\">" . gettext("실패") . "</span></td></tr>");
				}

				print("</table><p/>");

			} else {
				print('<td><span class="text-alert">' . gettext("실패") . "</span></td></tr>");
				print("</table><p/>");
			}
		} else {
			print('<td><span class="text-alert">' . gettext("failed") . "</span></td></tr>");
			print("</table><p/>");
		}

		print("</pre>");
		exit;
	}
}

$pconfig['session_timeout'] = $config['system']['webgui']['session_timeout'];

if (isset($config['system']['webgui']['authmode'])) {
	$pconfig['authmode'] = $config['system']['webgui']['authmode'];
} else {
	$pconfig['authmode'] = "Local Database";
}

$pconfig['backend'] = $config['system']['webgui']['backend'];

$pconfig['auth_refresh_time'] = $config['system']['webgui']['auth_refresh_time'];

// Page title for main admin
$pgtitle = array(gettext("System"), gettext("User Manager"), gettext("Settings"));
$pglinks = array("", "system_usermanager.php", "@self");

$save_and_test = false;

if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

	if (isset($_POST['session_timeout'])) {
		$timeout = intval($_POST['session_timeout']);
		if ($timeout != "" && (!is_numeric($timeout) || $timeout <= 0)) {
			$input_errors[] = gettext("세션 만료 시간은 정수여야합니다.");
		}
	}

	if (isset($_POST['auth_refresh_time'])) {
		$timeout = intval($_POST['auth_refresh_time']);
		if (!is_numeric($timeout) || $timeout < 0 || $timeout > 3600 ) {
			$input_errors[] = gettext("새로고침 시간은 0에서 3600사이의 정수여야합니다.");
		}
	}

	if (($_POST['authmode'] == "Local Database") && $_POST['savetest']) {
		$savemsg = gettext("설정이 저장되었지만 로컬 데이터베이스에 대해 지원되지 않기 때문에 테스트가 수행되지 않았습니다.");
	}

	if (!$input_errors) {
		if ($_POST['authmode'] != "Local Database") {
			$authsrv = auth_get_authserver($_POST['authmode']);
			if ($_POST['savetest']) {
				if ($authsrv['type'] == "ldap") {
					$save_and_test = true;
				} else {
					$savemsg = gettext("설정이 저장되었지만 LDAP기반 백엔드에 대해서만 지원되므로 테스트가 수행되지 않았습니다.");
				}
			}
		}

		if (isset($_POST['session_timeout']) && $_POST['session_timeout'] != "") {
			$config['system']['webgui']['session_timeout'] = intval($_POST['session_timeout']);
		} else {
			unset($config['system']['webgui']['session_timeout']);
		}

		if ($_POST['authmode']) {
			$config['system']['webgui']['authmode'] = $_POST['authmode'];
		} else {
			unset($config['system']['webgui']['authmode']);
		}
		
		if (isset($_POST['auth_refresh_time']) && $_POST['auth_refresh_time'] != "") {
			$config['system']['webgui']['auth_refresh_time'] = intval($_POST['auth_refresh_time']);
		} else {
			unset($config['system']['webgui']['auth_refresh_time']);
		}

		write_config();

	}
}

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

$tab_array = array();
$tab_array[] = array(gettext("Users"), false, "system_usermanager.php");
$tab_array[] = array(gettext("Groups"), false, "system_groupmanager.php");
$tab_array[] = array(gettext("Settings"), true, "system_usermanager_settings.php");
$tab_array[] = array(gettext("Authentication Servers"), false, "system_authservers.php");
display_top_tabs($tab_array);

/* Default to pfsense backend type if none is defined */
if (!$pconfig['backend']) {
	$pconfig['backend'] = "pfsense";
}

$form = new Form;

$section = new Form_Section('Settings');

$section->addInput(new Form_Input(
	'session_timeout',
	'Session timeout',
	'number',
	$pconfig['session_timeout'],
	['min' => 0]
))->setHelp('Time in minutes to expire idle management sessions. The default is 4 '.
	'hours (240 minutes). Enter 0 to never expire sessions. NOTE: This is a security '.
	'risk!');

$auth_servers = array();
foreach (auth_get_authserver_list() as $idx_authserver => $auth_server) {
	$auth_servers[ $idx_authserver ] = $auth_server['name'];
}

$section->addInput(new Form_Select(
	'authmode',
	'*Authentication Server',
	$pconfig['authmode'],
	$auth_servers
));

$section->addInput(new Form_Input(
	'auth_refresh_time',
	'Auth Refresh Time',
	'number',
	$pconfig['auth_refresh_time'],
	['min' => 0, 'max' => 3600]
))->setHelp('Time in seconds to cache authentication results. The default is 30 seconds, maximum 3600 (one hour). '.
	'Shorter times result in more frequent queries to authentication servers.');

$form->addGlobal(new Form_Button(
	'savetest',
	'Save & Test',
	null,
	'fa-wrench'
))->addClass('btn-info');

$form->add($section);

$modal = new Modal("LDAP settings", "testresults", true);

$modal->addInput(new Form_StaticText(
	'Test results',
	'<span id="ldaptestop">pfSense LDAP 설정을 테스트 중입니다... 잠시만 기다려주십시오...' . $g['product_name'] . '</span>'
));

$form->add($modal);

print $form;

// If the user clicked "Save & Test" show the modal and populate it with the test results via AJAX
if ($save_and_test) {
?>
<script type="text/javascript">
//<![CDATA[
events.push(function() {

	function test_LDAP() {
		var ajaxRequest;
		var authserver = $('#authmode').val();

		ajaxRequest = $.ajax(
			{
				url: "/system_usermanager_settings.php",
				type: "post",
				data: {
					ajax: "ajax",
					authserver: authserver
				}
			}
		);

		// Deal with the results of the above ajax call
		ajaxRequest.done(function (response, textStatus, jqXHR) {
			$('#ldaptestop').html(response);
		});
	}

	$('#testresults').modal('show');

	test_LDAP();
});
</script>
<?php

}

include("foot.inc");
