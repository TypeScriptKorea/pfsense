<?php
/*
 * services_captiveportal_filemanager.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2018 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2005-2006 Jonathan De Graeve (jonathan.de.graeve@imelda.be)
 * Copyright (c) 2005-2006 Paul Taylor (paultaylor@winn-dixie.com)
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
2018.02.20
한글화 번역 시작
*/

##|+PRIV
##|*IDENT=page-services-captiveportal-filemanager
##|*NAME=Services: Captive Portal: File Manager
##|*DESCR=Allow access to the 'Services: Captive Portal: File Manager' page.
##|*MATCH=services_captiveportal_filemanager.php*
##|-PRIV

function cpelementscmp($a, $b) {
	return strcasecmp($a['name'], $b['name']);
}

function cpelements_sort() {
	global $config, $cpzone;

	usort($config['captiveportal'][$cpzone]['element'], "cpelementscmp");
}

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");
require_once("captiveportal.inc");

$cpzone = $_REQUEST['zone'];

$cpzone = strtolower(htmlspecialchars($cpzone));

if (empty($cpzone)) {
	header("Location: services_captiveportal_zones.php");
	exit;
}

if (!is_array($config['captiveportal'])) {
	$config['captiveportal'] = array();
}

$a_cp =& $config['captiveportal'];

$pgtitle = array(gettext("서비스"), gettext("전속 포"), $a_cp[$cpzone]['zone'], gettext("파일 매니저"));
$pglinks = array("", "services_captiveportal_zones.php", "services_captiveportal.php?zone=" . $cpzone, "@self");
$shortcut_section = "captiveportal";

if (!is_array($a_cp[$cpzone]['element'])) {
	$a_cp[$cpzone]['element'] = array();
}

$a_element =& $a_cp[$cpzone]['element'];

// Calculate total size of all files
$total_size = 0;
foreach ($a_element as $element) {
	$total_size += $element['size'];
}

if ($_POST['Submit']) {
	unset($input_errors);

	if (is_uploaded_file($_FILES['new']['tmp_name'])) {

		if ((!stristr($_FILES['new']['name'], "captiveportal-")) && ($_FILES['new']['name'] != 'favicon.ico')) {
			$name = "captiveportal-" . $_FILES['new']['name'];
		} else {
			$name = $_FILES['new']['name'];
		}
		$size = filesize($_FILES['new']['tmp_name']);

		// is there already a file with that name?
		foreach ($a_element as $element) {
			if ($element['name'] == $name) {
				$input_errors[] = sprintf(gettext("'%s'은 이미 존재하는 파일입니다."), $name);
				break;
			}
		}

		// check total file size
		if (($total_size + $size) > $g['captiveportal_element_sizelimit']) {
			$input_errors[] = sprintf(gettext("%s을(를) 초과하여 업로드할 수 없습니다."),
				format_bytes($g['captiveportal_element_sizelimit']));
		}

		if (!$input_errors) {
			$element = array();
			$element['name'] = $name;
			$element['size'] = $size;
			$element['content'] = base64_encode(file_get_contents($_FILES['new']['tmp_name']));

			$a_element[] = $element;
			cpelements_sort();

			write_config();
			captiveportal_write_elements();
			header("Location: services_captiveportal_filemanager.php?zone={$cpzone}");
			exit;
		}
	}
} else if (($_POST['act'] == "del") && !empty($cpzone) && $a_element[$_POST['id']]) {
	@unlink("{$g['captiveportal_element_path']}/" . $a_element[$_POST['id']]['name']);
	@unlink("{$g['captiveportal_path']}/" . $a_element[$_POST['id']]['name']);
	unset($a_element[$_POST['id']]);
	write_config();
	header("Location: services_captiveportal_filemanager.php?zone={$cpzone}");
	exit;
}

include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$tab_array = array();
$tab_array[] = array(gettext("배치"), false, "services_captiveportal.php?zone={$cpzone}");
$tab_array[] = array(gettext("MACs"), false, "services_captiveportal_mac.php?zone={$cpzone}");
$tab_array[] = array(gettext("허용 IP 주소"), false, "services_captiveportal_ip.php?zone={$cpzone}");
$tab_array[] = array(gettext("허용 호스트 이름"), false, "services_captiveportal_hostname.php?zone={$cpzone}");
$tab_array[] = array(gettext("바우처"), false, "services_captiveportal_vouchers.php?zone={$cpzone}");
$tab_array[] = array(gettext("파일 매니저"), true, "services_captiveportal_filemanager.php?zone={$cpzone}");
display_top_tabs($tab_array, true);

if ($_REQUEST['act'] == 'add') {

	$form = new Form(false);

	$form->setMultipartEncoding();

	$section = new Form_Section('Upload a New File');

	$section->addInput(new Form_Input(
		'zone',
		null,
		'hidden',
		$cpzone
	));

	$section->addInput(new Form_Input(
		'new',
		'File',
		'file'
	));

	$form->add($section);

	$form->addGlobal(new Form_Button(
		'Submit',
		'Upload',
		null,
		'fa-upload'
	))->addClass('btn-primary');

	print($form);
}

if (is_array($a_cp[$cpzone]['element'])):
?>
	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext("설치된 파일")?></h2></div>
		<div class="panel-body">
			<div class="table-responsive">
				<table class="table table-striped table-hover table-condensed">
					<thead>
						<tr>
							<th><?=gettext("이름"); ?></th>
							<th><?=gettext("사이즈"); ?></th>
							<th><?=gettext("행동"); ?></th>
						</tr>
					</thead>
					<tbody>
<?php
	$i = 0;
	foreach ($a_cp[$cpzone]['element'] as $element):
?>
						<tr>
							<td><?=htmlspecialchars($element['name'])?></td>
							<td><?=format_bytes($element['size'])?></td>
							<td>
								<a class="fa fa-trash"	title="<?=gettext("삭제")?>" href="services_captiveportal_filemanager.php?zone=<?=$cpzone?>&amp;act=del&amp;id=<?=$i?>" usepost></a>
							</td>
						</tr>
<?php
		$i++;
	endforeach;

	if ($total_size > 0) :
?>
						<tr>
							<th>
								<?=gettext("총 사이즈");?>
							</th>
							<th>
								<?=format_bytes($total_size);?>
							</th>
							<th></th>
						</tr>
<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
<?php
endif;

?>
	   <nav class="action-buttons">
<?php if (!$_REQUEST['act'] == 'add'): ?>
			<a href="services_captiveportal_filemanager.php?zone=<?=$cpzone?>&amp;act=add" class="btn btn-success">
		   		<i class="fa fa-plus icon-embed-btn"></i>
		   		<?=gettext("추가")?>
		   	</a>
<?php endif; ?>
	   </nav>
<?php
// The notes displayed on the page are large, the page content comparitively small. A "Note" button
// is provided so that you only see the notes if you ask for them
?>
<div class="infoblock panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Notes");?></h2></div>
	<div class="panel-body">
	<?=gettext("captiveportal 이라는 접두어로 전속 포털 HTTP(s)서버의 루트 디렉토리에서 이곳에 업로드된 모든 파일을 사용할 수 있습니다." .
	"favicon.ico같은 아이콘 파일도 업로드될 수 있으며 접두사 없이 그대로 유지됩니다. " .
	"포털 페이지 HTML코드에서 직접 상대 경로를 사용하여 참조할 수 있습니다. " .
	"exam: 파일 매니저를 사용하여 captiveportal-test.jpg란 이름으로 업로드한 이미지를 다음과 같은 방법으로 페이지에 적용할 수 있습니다.")?><br /><br />
	<pre>&lt;img src=&quot;captiveportal-test.jpg&quot; width=... height=...&gt;</pre><br />
	<?=gettext("또한 .php파일을 업로드하여 실행할 수 있습니다. 파일 이름은 다음과 같은 방법으로 처음 페이지에서 사용자 지정 페이지로 전달할 수 있습니다.")?><br /><br />
	<pre>&lt;a href="/captiveportal-aup.php?zone=$PORTAL_ZONE$&amp;redirurl=$PORTAL_REDIRURL$"&gt;<?=gettext("Acceptable usage policy"); ?>&lt;/a&gt;</pre><br />
	<?=sprintf(gettext("파일의 총 크기 제한은 %s입니다."), format_bytes($g['captiveportal_element_sizelimit']))?>
	</div>
</div>
<?php
include("foot.inc");
