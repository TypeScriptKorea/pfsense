<?php
/*
 * diag_pf_info.php
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
##|*IDENT=page-diagnostics-pf-info
##|*NAME=Diagnostics: pfInfo
##|*DESCR=Allows access to the 'Diagnostics: pfInfo' page
##|*MATCH=diag_pf_info.php*
##|-PRIV

require_once("guiconfig.inc");

$pgtitle = array(gettext("진단"), gettext("pf정보"));

if (stristr($_POST['Submit'], gettext("아니오"))) {
	header("Location: index.php");
	exit;
}

if ($_REQUEST['getactivity']) {
	$text = `/sbin/pfctl -vvsi`;
	$text .= "<p/>";
	$text .= `/sbin/pfctl -vvsm`;
	$text .= "<p/>";
	$text .= `/sbin/pfctl -vvst`;
	$text .= "<p/>";
	$text .= `/sbin/pfctl -vvsI`;
	echo $text;
	exit;
}

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form(false);
$form->addGlobal(new Form_Input(
	'getactivity',
	null,
	'hidden',
	'yes'
));
$section = new Form_Section('페이지 자동 업데이트');

$section->addInput(new Form_Checkbox(
	'refresh',
	'Refresh',
	'Automatically refresh the output below',
	true
));

$form->add($section);
print $form;

?>
<script type="text/javascript">
//<![CDATA[
	function getpfinfo() {
		if (!$('#refresh').is(':checked')) {
			return;
		}

		$.ajax(
			'/diag_pf_info.php',
			{
				type: 'post',
				data: $(document.forms[0]).serialize(),
				success: function (data) {
					$('#xhrOutput').html(data);
				},
		});
	}

	events.push(function() {
		setInterval('getpfinfo()', 2500);
		getpfinfo();
	});
//]]>
</script>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('출력')?></h2></div>
	<div class="panel panel-body">
		<pre id="xhrOutput"><?=gettext("PF정보를 수집중입니다. 잠시만 기다려주십시오.")?></pre>
	</div>
</div>

<?php include("foot.inc");
