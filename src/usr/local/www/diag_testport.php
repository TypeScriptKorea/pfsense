<?php
/*
 * diag_testport.php
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
##|*IDENT=page-diagnostics-testport
##|*NAME=Diagnostics: Test Port
##|*DESCR=Allow access to the 'Diagnostics: Test Port' page.
##|*MATCH=diag_testport.php*
##|-PRIV

// Calling netcat and parsing the results has been moved to the if ($_POST) section so that the results are known
// before we draw the form and any resulting error messages will appear in the correct place

$allowautocomplete = true;

$pgtitle = array(gettext("진단"), gettext("Test Port"));
require_once("guiconfig.inc");

include("head.inc");

define('NC_TIMEOUT', 10);
$do_testport = false;
$retval = 1;

if ($_POST || $_REQUEST['host']) {
	unset($input_errors);

	/* input validation */
	$reqdfields = explode(" ", "host port");
	$reqdfieldsn = array(gettext("Host"), gettext("Port"));
	do_input_validation($_REQUEST, $reqdfields, $reqdfieldsn, $input_errors);

	if (!is_ipaddr($_REQUEST['host']) && !is_hostname($_REQUEST['host'])) {
		$input_errors[] = gettext("유효한 IP주소 또는 호스트 이름을 적어주십시오.");
	}

	if (!is_port($_REQUEST['port'])) {
		$input_errors[] = gettext("유효한 포트 번호를 적어주십시오.");
	}

	if (($_REQUEST['srcport'] != "") && (!is_numeric($_REQUEST['srcport']) || !is_port($_REQUEST['srcport']))) {
		$input_errors[] = gettext("올바른 포트 번호를 입력하시거나 입력란을 비우십시오.");
	}

	if (is_ipaddrv4($_REQUEST['host']) && ($_REQUEST['ipprotocol'] == "ipv6")) {
		$input_errors[] = gettext("IPv6 주소를 사용하여 IPv4 주소에 연결할 수 없습니다.");
	}
	if (is_ipaddrv6($_REQUEST['host']) && ($_REQUEST['ipprotocol'] == "ipv4")) {
		$input_errors[] = gettext("IPv4 주소를 사용하여 IPv6 주소에 연결할 수 없습니다.");
	}

	if (!$input_errors) {
		$do_testport = true;
		$timeout = NC_TIMEOUT;
	}

	/* Save these request vars even if there were input errors. Then the fields are refilled for the user to correct. */
	$host = $_REQUEST['host'];
	$sourceip = $_REQUEST['sourceip'];
	$port = $_REQUEST['port'];
	$srcport = $_REQUEST['srcport'];
	$showtext = isset($_REQUEST['showtext']);
	$ipprotocol = $_REQUEST['ipprotocol'];

	if ($do_testport) {
?>
		<script type="text/javascript">
			//<![CDATA[
			window.onload=function() {
				document.getElementById("testportCaptured").wrap='off';
			}
			//]]>
		</script>
<?php
		$result = "";
		$ncoutput = "";
		$nc_base_cmd = '/usr/bin/nc';
		$nc_args = "-w " . escapeshellarg($timeout);
		if (!$showtext) {
			$nc_args .= ' -z ';
		}
		if (!empty($srcport)) {
			$nc_args .= ' -p ' . escapeshellarg($srcport) . ' ';
		}

		/* Attempt to determine the interface address, if possible. Else try both. */
		if (is_ipaddrv4($host)) {
			if ($sourceip == "any") {
				$ifaddr = "";
			} else {
				if (is_ipaddr($sourceip)) {
					$ifaddr = $sourceip;
				} else {
					$ifaddr = get_interface_ip($sourceip);
				}
			}
			$nc_args .= ' -4';
		} elseif (is_ipaddrv6($host)) {
			if ($sourceip == "any") {
				$ifaddr = '';
			} else if (is_linklocal($sourceip)) {
				$ifaddr = $sourceip;
			} else {
				$ifaddr = get_interface_ipv6($sourceip);
			}
			$nc_args .= ' -6';
		} else {
			switch ($ipprotocol) {
				case "ipv4":
					$ifaddr = get_interface_ip($sourceip);
					$nc_ipproto = ' -4';
					break;
				case "ipv6":
					$ifaddr = (is_linklocal($sourceip) ? $sourceip : get_interface_ipv6($sourceip));
					$nc_ipproto = ' -6';
					break;
				case "any":
					$ifaddr = get_interface_ip($sourceip);
					$nc_ipproto = (!empty($ifaddr)) ? ' -4' : '';
					if (empty($ifaddr)) {
						$ifaddr = (is_linklocal($sourceip) ? $sourceip : get_interface_ipv6($sourceip));
						$nc_ipproto = (!empty($ifaddr)) ? ' -6' : '';
					}
					break;
			}
			/* Netcat doesn't like it if we try to connect using a certain type of IP without specifying the family. */
			if (!empty($ifaddr)) {
				$nc_args .= $nc_ipproto;
			} elseif ($sourceip == "any") {
				switch ($ipprotocol) {
					case "ipv4":
						$nc_ipproto = ' -4';
						break;
					case "ipv6":
						$nc_ipproto = ' -6';
						break;
				}
				$nc_args .= $nc_ipproto;
			}
		}
		/* Only add on the interface IP if we managed to find one. */
		if (!empty($ifaddr)) {
			$nc_args .= ' -s ' . escapeshellarg($ifaddr) . ' ';
			$scope = get_ll_scope($ifaddr);
			if (!empty($scope) && !strstr($host, "%")) {
				$host .= "%{$scope}";
			}
		}

		$nc_cmd = "{$nc_base_cmd} {$nc_args} " . escapeshellarg($host) . ' ' . escapeshellarg($port) . ' 2>&1';
		exec($nc_cmd, $result, $retval);
	//	echo "NC CMD: {$nc_cmd}\n\n";

		if (!empty($result)) {
			if (is_array($result)) {
				foreach ($result as $resline) {
					$ncoutput .= htmlspecialchars($resline) . "\n";
				}
			} else {
				$ncoutput .= htmlspecialchars($result);
			}
		}
	}
}

// Handle the display of all messages here where the user can readily see them
if ($input_errors) {
	print_input_errors($input_errors);
} elseif ($do_testport) {
	// User asked for a port test
	if ($retval == 0) {
		// Good host & port
		$alert_text = sprintf(gettext('호스트에 대한 포트 테스트: %1$s 포트: %2$s 성공.'), $host, $port);
		if ($showtext) {
			$alert_text .= ' ' . gettext('호스트에서 수신된 모든 텍스트는 양식 아래에 표시됩니다.');
		}
		print_info_box($alert_text, 'success', false);
	} else {
		// netcat exit value != 0
		if ($showtext) {
			$alert_text = gettext('수신된 출력이 없거나 연결에 실패했습니다. "원격 텍스트 표시(ShowRemoteText)"를 선택 취소하십시오.');
		} else {
			$alert_text = gettext('연결에 실패했습니다.');
		}
		print_info_box($alert_text, 'danger', false);
	}
} else {
	// First time, new page
	print_info_box(gettext('해당 페이지는 간단한 TCP연결 테스트를 수행하여 호스트가 특정 포트에서 작동 중이며 연결이 제대로 이루어지는지 확인합니다.') . " " .
		gettext('UDP포트는 이러한 방식으로 연결이 이루어지는지 확인할 수 없으므로 테스트가 작동하지 않습니다.'), 'warning', false);
}

$form = new Form(false);

$section = new Form_Section('테스트 포트');

$section->addInput(new Form_Input(
	'host',
	'*Hostname',
	'text',
	$host,
	['placeholder' => 'Hostname to look up.']
));

$section->addInput(new Form_Input(
	'port',
	'*Port',
	'text',
	$port,
	['placeholder' => 'Port to test.']
));

$section->addInput(new Form_Input(
	'srcport',
	'Source Port',
	'text',
	$srcport,
	['placeholder' => 'Typically left blank.']
));

$section->addInput(new Form_Checkbox(
	'showtext',
	'Remote text',
	'Show remote text',
	$showtext
))->setHelp("포트에 연결할 때 서버가 제공하는 텍스트를 표시합니다. 이 옵션을 선택하면 아래의 패널에 표시하는 데 10초 이상 걸립니다.");

$section->addInput(new Form_Select(
	'sourceip',
	'*Source Address',
	$sourceip,
	['' => 'Any'] + get_possible_traffic_source_addresses(true)
))->setHelp('Select source address for the trace.');

$section->addInput(new Form_Select(
	'ipprotocol',
	'*IP Protocol',
	$ipprotocol,
	array('ipv4' => 'IPv4', 'ipv6' => 'IPv6')
))->setHelp("IPv4나 IPv6을 강제로 사용하는 경우, 해당 프로토콜을 사용하는 결과가 포함되지 않은 호스트 이름을 사용하시면 오류가 발생합니다." .
					" 예를 들어, IPv4를 강제로 사용하고 IPv6IP주소만 반환하는 호스트 이름을 사용하는 경우에는 해당 주소가 작동하지 않습니다..");

$form->add($section);

$form->addGlobal(new Form_Button(
	'Submit',
	'Test',
	null,
	'fa-wrench'
))->addClass('btn-primary');

print $form;

// If the command succeeded, the user asked to see the output and there is output, then show it.
if ($retval == 0 && $showtext && !empty($ncoutput)):
?>
	<div class="panel panel-default">
		<div class="panel-heading">
			<h2 class="panel-title"><?=gettext('수신된 원격 텍스트')?></h2>
		</div>
		<div class="panel-body">
			<pre><?= $ncoutput ?></pre>
		</div>
	</div>
<?php
endif;

include("foot.inc");
