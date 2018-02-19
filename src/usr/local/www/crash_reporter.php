<?php
/*
 * crash_reporter.php
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
한글 번역 시작
*/

##|+PRIV
##|*IDENT=page-diagnostics-crash-reporter
##|*NAME=Crash reporter
##|*DESCR=Uploads crash reports to pfSense and or deletes crash reports.
##|*MATCH=crash_reporter.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("captiveportal.inc");
require_once("system.inc");

define("FILE_SIZE", 450000);

function upload_crash_report($files) {
	global $g, $config;

	$post = array();
	$counter = 0;
	foreach ($files as $file) {
		$post["file{$counter}"] = "@{$file}";
		$counter++;
	}
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_VERBOSE, 0);
	curl_setopt($ch, CURLOPT_SAFE_UPLOAD, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	if (!isset($config['system']['do_not_send_uniqueid'])) {
		curl_setopt($ch, CURLOPT_USERAGENT, $g['product_name'] . '/' . $g['product_version'] . ':' . system_get_uniqueid());
	} else {
		curl_setopt($ch, CURLOPT_USERAGENT, $g['product_name'] . '/' . $g['product_version']);
	}
	curl_setopt($ch, CURLOPT_URL, $g['crashreporterurl']);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
	$response = curl_exec($ch);
	return $response;
}

$pgtitle = array(gettext("Diagnostics"), gettext("Crash Reporter"));
include('head.inc');

$crash_report_header = "Crash report begins.  Anonymous machine information:\n\n";
$crash_report_header .= php_uname("m") . "\n";
$crash_report_header .= php_uname("r") . "\n";
$crash_report_header .= php_uname("v") . "\n";
$crash_report_header .= "\nCrash report details:\n";

exec("/bin/cat /tmp/PHP_errors.log", $php_errors);

	if ($_POST['Submit'] == "Yes") {
		echo gettext("처리중...");
		if (!is_dir("/var/crash")) {
			mkdir("/var/crash", 0750, true);
		}
		@file_put_contents("/var/crash/crashreport_header.txt", $crash_report_header);
		if (file_exists("/tmp/PHP_errors.log")) {
			copy("/tmp/PHP_errors.log", "/var/crash/PHP_errors.log");
		}
		exec("find /var/crash -type l -exec rm {} +");
		exec("/usr/bin/gzip /var/crash/*");
		$files_to_upload = glob("/var/crash/*");
		echo "<br/>";
		echo gettext("업로드중...");
		ob_flush();
		flush();
		if (is_array($files_to_upload)) {
			$resp = upload_crash_report($files_to_upload);
			echo "<br/>";
			print_r($resp);
			if (preg_match('/Upload received OK./i', $resp)) {
				array_map('unlink', glob("/var/crash/*"));
				// Erase the contents of the PHP error log
				fclose(fopen("/tmp/PHP_errors.log", 'w'));
				echo "<br/>" . gettext("로컬디스크의 오류 보고서를 삭제하였습니다.");
			}
			echo "<p><a href=\"/\">" . gettext("계속") . "</a>" . "</p>";
		} else {
			echo gettext("크래시 파일을 찾을 수 없습니다.");
		}
	} else if ($_POST['Submit'] == "No") {
		array_map('unlink', glob("/var/crash/*"));
		// Erase the contents of the PHP error log
		fclose(fopen("/tmp/PHP_errors.log", 'w'));
		header("Location: /");
		exit;
	} else {
		$crash_files = glob("/var/crash/*");
		$crash_reports = $crash_report_header;
		if (count($php_errors) > 0) {
			$crash_reports .= "\nPHP Errors:\n";
			$crash_reports .= implode("\n", $php_errors) . "\n\n";
		} else {
			$crash_reports .= "\nNo PHP errors found.\n";
		}
		if (count($crash_files) > 0) {
			foreach ($crash_files as $cf) {
				if (filesize($cf) < FILE_SIZE) {
					$crash_reports .= "\nFilename: {$cf}\n";
					$crash_reports .= file_get_contents($cf);
				}
			}
		} else {
			$crash_reports .= "\nNo FreeBSD crash data found.\n";
		}
?>
	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext("프로그램 버그가 감지되었습니다.")?></h2></div>
		<div class="panel-body">
			<div class="content">
				<p>
					<?=gettext("pfSense개발진에게 디버그 로그를 제출하실 수 있습니다.")?>
					<i><?=gettext("제출하실 내용이 올바른지 다시 확인해주십시오.")?></i>
				</p>
				<textarea readonly style="width: 100%; height: 350px;">
					<?=$crash_reports?>
				</textarea>
				<br/><br/>
				<form action="crash_reporter.php" method="post">
					<button class="btn btn-primary" name="Submit" type="submit" value="Yes">
						<i class="fa fa-upload"></i>
						<?=gettext("네")?> - <?=gettext("검사를 위해 개발자에게 제출하십시오.")?>
					</button>
					<button class="btn btn-warning" name="Submit" type="submit" value="No">
						<i class="fa fa-undo"></i>
						<?=gettext("아니오")?> - <?=gettext("오류 보고서를 삭제하고 대시보드로 돌아갑니다.")?>
					</button>
				</form>
			</div>
		</div>
<?php
	}
?>

<?php include("foot.inc")?>
