<?php
/*
 * services_ntpd_pps.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2018 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2013 Dagorlad
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
2018.02.27
한글화 번역 시작
*/

##|+PRIV
##|*IDENT=page-services-ntpd-pps
##|*NAME=Services: NTP PPS
##|*DESCR=Allow access to the 'Services: NTP PPS' page.
##|*MATCH=services_ntpd_pps.php*
##|-PRIV

require_once("guiconfig.inc");

if (!is_array($config['ntpd'])) {
	$config['ntpd'] = array();
}
if (!is_array($config['ntpd']['pps'])) {
	$config['ntpd']['pps'] = array();
}

if ($_POST) {
	unset($input_errors);

	if (!$input_errors) {
		if (!empty($_POST['ppsport']) && file_exists('/dev/'.$_POST['ppsport'])) {
			$config['ntpd']['pps']['port'] = $_POST['ppsport'];
		} else {
			/* if port is not set, remove all the pps config */
			unset($config['ntpd']['pps']);
		}

		if (!empty($_POST['ppsfudge1'])) {
			$config['ntpd']['pps']['fudge1'] = $_POST['ppsfudge1'];
		} elseif (isset($config['ntpd']['pps']['fudge1'])) {
			unset($config['ntpd']['pps']['fudge1']);
		}

		if (!empty($_POST['ppsstratum']) && ($_POST['ppsstratum']) < 17) {
			$config['ntpd']['pps']['stratum'] = $_POST['ppsstratum'];
		} elseif (isset($config['ntpd']['pps']['stratum'])) {
			unset($config['ntpd']['pps']['stratum']);
		}

		if (!empty($_POST['ppsselect'])) {
			$config['ntpd']['pps']['noselect'] = $_POST['ppsselect'];
		} elseif (isset($config['ntpd']['pps']['noselect'])) {
			unset($config['ntpd']['pps']['noselect']);
		}

		if (!empty($_POST['ppsflag2'])) {
			$config['ntpd']['pps']['flag2'] = $_POST['ppsflag2'];
		} elseif (isset($config['ntpd']['pps']['flag2'])) {
			unset($config['ntpd']['pps']['flag2']);
		}

		if (!empty($_POST['ppsflag3'])) {
			$config['ntpd']['pps']['flag3'] = $_POST['ppsflag3'];
		} elseif (isset($config['ntpd']['pps']['flag3'])) {
			unset($config['ntpd']['pps']['flag3']);
		}

		if (!empty($_POST['ppsflag4'])) {
			$config['ntpd']['pps']['flag4'] = $_POST['ppsflag4'];
		} elseif (isset($config['ntpd']['pps']['flag4'])) {
			unset($config['ntpd']['pps']['flag4']);
		}

		if (!empty($_POST['ppsrefid'])) {
			$config['ntpd']['pps']['refid'] = $_POST['ppsrefid'];
		} elseif (isset($config['ntpd']['pps']['refid'])) {
			unset($config['ntpd']['pps']['refid']);
		}

		write_config("Updated NTP PPS Settings");

		$changes_applied = true;
		$retval = 0;
		$retval |= system_ntp_configure();
	}
}

$pconfig = &$config['ntpd']['pps'];

$pgtitle = array(gettext("Services"), gettext("NTP"), gettext("PPS"));
$pglinks = array("", "services_ntpd.php", "@self");
$shortcut_section = "ntp";
include("head.inc");

if ($input_errors) {
    print_input_errors($input_errors);
}

if ($changes_applied) {
	print_apply_result_box($retval);
}

$tab_array = array();
$tab_array[] = array(gettext("Settings"), false, "services_ntpd.php");
$tab_array[] = array(gettext("ACLs"), false, "services_ntpd_acls.php");
$tab_array[] = array(gettext("Serial GPS"), false, "services_ntpd_gps.php");
$tab_array[] = array(gettext("PPS"), true, "services_ntpd_pps.php");
display_top_tabs($tab_array);

$form = new Form;

$section = new Form_Section('NTP 직렬 PPS 구성');

$section->addInput(new Form_StaticText(
	'Notes',
	'DCF77 (DE), JJY (JP), MSF (GB) 또는 WWVB (US)에서 시간 신호를 수신하는 라디오와 같은 초당 펄스 출력 장치는 NTP의 PPS 참조로 사용될 수 있습니다. ' .
	'직렬 GPS도 사용할 수 있지만, 일반적으로 직렬 GPS드라이버를 사용하는 것이 좋습니다.  ' .
	'PPS신호는 초의 변경에 대한 참조만을 제공하므로 초를 번호로 지정하려면 다른 원본을 하나 이상 참조하십시오. ' . '<br /><br />' .
	'At least 3 additional time sources should be configured under ' .
	'<a href="services_ntpd.php">' . 'Services > NTP > Settings' . '</a>' . ' to reliably supply the time of each PPS pulse.'
));

$serialports = glob("/dev/cua?[0-9]{,.[0-9]}", GLOB_BRACE);

if (!empty($serialports)) {
	$splist = array();

	foreach ($serialports as $port) {
		$shortport = substr($port, 5);
		$splist[$shortport] = $shortport;
	}

	$section->addInput(new Form_Select(
		'ppsport',
		'Serial Port',
		$pconfig['port'],
		['' => gettext('None')] + $splist
	))->setHelp('All serial ports are listed, be sure to pick the port with the PPS source attached. ');
}

$section->addInput(new Form_Input(
	'ppsfudge1',
	'Fudge Time',
	'text',
	$pconfig['fudge1']
))->setHelp('Fudge time is used to specify the PPS signal offset from the actual second such as the transmission delay between the transmitter and the receiver (default: 0.0).');

$section->addInput(new Form_Input(
	'ppsstratum',
	'Stratum',
	'text',
	$pconfig['stratum']
))->setHelp('This may be used to change the PPS Clock stratum (default: 0). This may be useful to, for some reason, have ntpd prefer a different clock and just monitor this source.');

$section->addInput(new Form_Checkbox(
	'ppsflag2',
	'Flags',
	'Enable falling edge PPS signal processing (default: unchecked, rising edge).',
	$pconfig['flag2']
));

$section->addInput(new Form_Checkbox(
	'ppsflag3',
	null,
	'Enable kernel PPS clock discipline (default: unchecked).',
	$pconfig['flag3']
));

$section->addInput(new Form_Checkbox(
	'ppsflag4',
	null,
	'Record a timestamp once for each second, useful for constructing Allan deviation plots (default: unchecked).',
	$pconfig['flag4']
));

$section->addInput(new Form_Input(
	'ppsrefid',
	'Clock ID',
	'text',
	$pconfig['refid'],
	['placeholder' => '1 to 4 characters']
))->setHelp('This may be used to change the PPS Clock ID (default: PPS).');

$form->add($section);
print($form);

include("foot.inc");
