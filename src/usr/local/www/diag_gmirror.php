<?php
/*
 * diag_gmirror.php
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
##|*IDENT=page-diagnostics-gmirror
##|*NAME=Diagnostics: GEOM Mirrors
##|*DESCR=Allow access to the 'Diagnostics: GEOM Mirrors' page.
##|*MATCH=diag_gmirror.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("config.inc");
require_once("gmirror.inc");

$pgtitle = array(gettext("진단"), gettext("GEOM Mirrors"));

include("head.inc");

$action_list = array(
	"forget" => gettext("연결된 모든 고객 삭제"),
	"clear" => gettext("디스크 메타데이터 삭제"),
	"insert" => gettext("미러에 고객 입력"),
	"remove" => gettext("미러에 저장된 고객 삭제"),
	"activate" => gettext("미러에 고객 재활성화"),
	"deactivate" => gettext("미러에 고객 비활성화"),
	"rebuild" => gettext("미러 소비자 강제 재구축"),
);

/* User tried to pass a bogus action */
if (!empty($_REQUEST['action']) && !array_key_exists($_REQUEST['action'], $action_list)) {
	header("Location: diag_gmirror.php");
	return;
}

if ($_POST) {
	if (!isset($_POST['confirm']) || ($_POST['confirm'] != gettext("확인"))) {
		header("Location: diag_gmirror.php");
		return;
	}

	$input_errors = "";

	if (($_POST['action'] != "clear") && !is_valid_mirror($_POST['mirror'])) {
		$input_errors[] = gettext("미러 이름이 유효하지 않습니다.");
	}

	if (!empty($_POST['consumer']) && !is_valid_consumer($_POST['consumer'])) {
		$input_errors[] = gettext("고객 이름이 유효하지 않습니다.");
	}

	/* Additional action-specific validation that hasn't already been tested */
	switch ($_POST['action']) {
		case "insert":
			if (!is_consumer_unused($_POST['consumer'])) {
				$input_errors[] = gettext("이미 존재하는 고객입니다. 미러에서 기존 고객을 먼저 제거하십시오.");
			}
			if (gmirror_consumer_has_metadata($_POST['consumer'])) {
				$input_errors[] = gettext("해당 고객을 삽입하기 전에 기존 미러의 메타데이터를 지우십시오.");
			}
			$mstat = gmirror_get_status_single($_POST['mirror']);
			if (strtoupper($mstat) != "COMPLETE") {
				$input_errors[] = gettext("미러가 완료되지않았으므로 고객을 추가할 수 없습니다. 작업이 완료될때까지 기다려주십시오.");
			}
			break;

		case "clear":
			if (!is_consumer_unused($_POST['consumer'])) {
				$input_errors[] = gettext("사용중인 디스크입니다. 먼저 디스크를 비활성화하십시오.");
			}
			if (!gmirror_consumer_has_metadata($_POST['consumer'])) {
				$input_errors[] = gettext("삭제할 데이터가 없습니다.");
			}
			break;

		case "activate":
			if (is_consumer_in_mirror($_POST['consumer'], $_POST['mirror'])) {
				$input_errors[] = gettext("미러에 존재하는 고객입니다.");
			}
			if (!gmirror_consumer_has_metadata($_POST['consumer'])) {
				$input_errors[] = gettext("메타데이터가 존재하지않으므로 재활성화가 불가능합니다.");
			}

			break;

		case "remove":
		case "deactivate":
		case "rebuild":
			if (!is_consumer_in_mirror($_POST['consumer'], $_POST['mirror'])) {
				$input_errors[] = gettext("해당 고객은 지정된 미러에 있어야합니다.");
			}
			break;
	}

	$result = 0;
	if (empty($input_errors)) {
		switch ($_POST['action']) {
			case "forget":
				$result = gmirror_forget_disconnected($_POST['mirror']);
				break;
			case "clear":
				$result = gmirror_clear_consumer($_POST['consumer']);
				break;
			case "insert":
				$result = gmirror_insert_consumer($_POST['mirror'], $_POST['consumer']);
				break;
			case "remove":
				$result = gmirror_remove_consumer($_POST['mirror'], $_POST['consumer']);
				break;
			case "activate":
				$result = gmirror_activate_consumer($_POST['mirror'], $_POST['consumer']);
				break;
			case "deactivate":
				$result = gmirror_deactivate_consumer($_POST['mirror'], $_POST['consumer']);
				break;
			case "rebuild":
				$result = gmirror_force_rebuild($_POST['mirror'], $_POST['consumer']);
				break;
		}

		$redir = "Location: diag_gmirror.php";

		if ($result != 0) {
			$redir .= "?error=" . urlencode($result);
		}

		/* If we reload the page too fast, the gmirror information may be missing or not up-to-date. */
		sleep(3);
		header($redir);
		return;
	}
}

$mirror_status = gmirror_get_status();
$mirror_list = gmirror_get_mirrors();
$unused_disks = gmirror_get_disks();
$unused_consumers = array();

foreach ($unused_disks as $disk) {
	if (is_consumer_unused($disk)) {
		$unused_consumers = array_merge($unused_consumers, gmirror_get_all_unused_consumer_sizes_on_disk($disk));
	}
}

if ($input_errors) {
	print_input_errors($input_errors);
}
if ($_REQUEST["error"] && ($_REQUEST["error"] != 0)) {
	print_info_box(gettext("작업을 수행하는 도중 오류가 발생했습니다. 자세한 내용은 시스템 로그를 확인하십시오."));
}

?>
<form action="diag_gmirror.php" method="POST" id="gmirror_form" name="gmirror_form">

<!-- Confirmation screen -->
<?php
if ($_REQUEST["action"]):  ?>
	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext('동작 확인')?></h2></div>
		<div class="panel-body">
			<strong><?=gettext('선택하신 동작을 확인하십시오: '); ?></strong>
			<span style="color:green"><?=$action_list[$_REQUEST["action"]]; ?></span>
			<input type="hidden" name="action" value="<?=htmlspecialchars($_REQUEST['action']); ?>" />
<?php
	if (!empty($_REQUEST["mirror"])): ?>
			<br /><strong><?=gettext("미러: "); ?></strong>
			<?=htmlspecialchars($_REQUEST['mirror']); ?>
			<input type="hidden" name="mirror" value="<?=htmlspecialchars($_REQUEST['mirror']); ?>" />
<?php
	endif; ?>

<?php
	if (!empty($_REQUEST["consumer"])): ?>
			<br /><strong><?=gettext("고객"); ?>:</strong>
			<?=htmlspecialchars($_REQUEST["consumer"]); ?>
			<input type="hidden" name="consumer" value="<?=htmlspecialchars($_REQUEST["consumer"]); ?>" />
<?php
	endif; ?>
			<br />
			<br />
			<button type="submit" name="confirm" class="btn btn-sm btn-success" value="<?=gettext("확인")?>">
				<i class="fa fa-check icon-embed-btn"></i>
				<?=gettext("확인")?>
			</button>
		</div>
	</div>
<?php
else:
	// Status/display page
	print_info_box(gettext("The options on this page are intended for use by advanced users only. This page is for managing existing mirrors, not creating new mirrors."));
?>

	<!-- GEOM mirror table -->
	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext('GEOM Mirror Information - Mirror Status')?></h2></div>
		<div class="panel-body table-responsive">

<?php
	if (count($mirror_status) > 0): ?>

			<table class="table table-striped table-hover table-condensed">
				<thead>
					<tr>
						<th><?=gettext("이름"); ?></th>
						<th><?=gettext("상태"); ?></th>
						<th><?=gettext("구성"); ?></th>
					</tr>
				</thead>
				<tbody>
<?php
		foreach ($mirror_status as $mirror => $name):
								$components = count($name["components"]); ?>
					<tr>
						<td rowspan="<?=$components; ?>">
							<?=htmlspecialchars($name['name']); ?><br />Size: <?=gmirror_get_mirror_size($name['name']); ?>
						</td>
						<td rowspan="<?=$components; ?>">
							<?=htmlspecialchars($name['status']); ?>
<?php
			if (strtoupper($name['status']) == "DEGRADED"): ?>
							<br />
							<a class="btn btn-xs btn-danger" href="diag_gmirror.php?action=forget&amp;mirror=<?=htmlspecialchars($name['name']); ?>"><i class="fa fa-trash icon-embed-btn"></i><?=gettext("연결이 끊긴 디스크를 삭제하십시오."); ?></a>
<?php
			endif; ?>
						</td>
						<td>
							<?=$name['components'][0]; ?>
							<?php list($cname, $cstatus) = explode(" ", $name['components'][0], 2); ?><br />
<?php
			if ((strtoupper($name['status']) == "COMPLETE") && (count($name["components"]) > 1)): ?>
							<a class="btn btn-xs btn-info" href="diag_gmirror.php?action=rebuild&amp;consumer=<?=htmlspecialchars($cname); ?>&amp;mirror=<?=htmlspecialchars($name['name']); ?>"><i class="fa fa-refresh icon-embed-btn"></i><?=gettext("재구성"); ?></a>
							<a class="btn btn-xs btn-warning" href="diag_gmirror.php?action=deactivate&amp;consumer=<?=htmlspecialchars($cname); ?>&amp;mirror=<?=htmlspecialchars($name['name']); ?>"><i class="fa fa-chain-broken icon-embed-btn"></i><?=gettext("비활성화"); ?></a>
							<a class="btn btn-xs btn-danger" href="diag_gmirror.php?action=remove&amp;consumer=<?=htmlspecialchars($cname); ?>&amp;mirror=<?=htmlspecialchars($name['name']); ?>"><i class="fa fa-trash icon-embed-btn"></i><?=gettext("삭제"); ?></a>
<?php
			endif; ?>
						</td>
					</tr>
<?php
			if (count($name["components"]) > 1):
				$morecomponents = array_slice($name["components"], 1); ?>
<?php
				foreach ($morecomponents as $component): ?>
					<tr>
						<td>
							<?=$component; ?>
							<?php list($cname, $cstatus) = explode(" ", $component, 2); ?><br />
<?php
					if ((strtoupper($name['status']) == "COMPLETE") && (count($name["components"]) > 1)): ?>
							<a class="btn btn-xs btn-info" href="diag_gmirror.php?action=rebuild&amp;consumer=<?=htmlspecialchars($cname); ?>&amp;mirror=<?=htmlspecialchars($name['name']); ?>"><i class="fa fa-refresh icon-embed-btn"></i><?=gettext("Rebuild"); ?></a>
							<a class="btn btn-xs btn-warning" href="diag_gmirror.php?action=deactivate&amp;consumer=<?=htmlspecialchars($cname); ?>&amp;mirror=<?=htmlspecialchars($name['name']); ?>"><i class="fa fa-chain-broken icon-embed-btn"></i><?=gettext("Deactivate"); ?></a>
							<a class="btn btn-xs btn-danger" href="diag_gmirror.php?action=remove&amp;consumer=<?=htmlspecialchars($cname); ?>&amp;mirror=<?=htmlspecialchars($name['name']); ?>"><i class="fa fa-trash icon-embed-btn"></i><?=gettext("Remove"); ?></a>
<?php
					endif; ?>
						</td>
					</tr>
<?php
				endforeach; ?>
<?php
			endif; ?>
<?php
		endforeach; ?>
				</tbody>
			</table>
<?php
	else: ?>
		<?=gettext("미러가 발견되지 않았습니다."); ?>

<?php
	endif; ?>

		</div>
	</div>

<?php print_info_box(gettext("일부 작업은 미러안에 여러 고객이 있는 경우에 한하여 수행할 수 있습니다."), 'default'); ?>

	<!-- Consumer information table -->
	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext('고객 정보 - 유효한 고객')?></h2></div>
		<div class="panel-body table-responsive">
<?php
	if (count($unused_consumers) > 0): ?>
			<table class="table table-striped table-hover table-condensed">
				<thead>
					<tr>
						<th><?=gettext("이름"); ?></th>
						<th><?=gettext("사이즈"); ?></th>
						<th><?=gettext("미러에 추가"); ?></th>
					</tr>
				</thead>

				<tbody>
<?php
		foreach ($unused_consumers as $consumer): ?>
					<tr>
						<td>
							<?=htmlspecialchars($consumer['name']); ?>
						</td>
						<td>
							<?=htmlspecialchars($consumer['size']); ?>
							<?=htmlspecialchars($consumer['humansize']); ?>
						</td>
						<td>
<?php
			$oldmirror = gmirror_get_consumer_metadata_mirror($consumer['name']);

			if ($oldmirror): ?>
							<a class="btn btn-xs btn-success" href="diag_gmirror.php?action=activate&amp;consumer=<?=htmlspecialchars($consumer['name']); ?>&amp;mirror=<?=htmlspecialchars($oldmirror); ?>">
								<i class="fa fa-chain icon-embed-btn"></i>
								<?=sprintf(gettext("Reactivate on %s"), htmlspecialchars($oldmirror)); ?>
							</a>

							<a class="btn btn-xs btn-danger" href="diag_gmirror.php?action=clear&amp;consumer=<?=htmlspecialchars($consumer['name']); ?>">
								<i class="fa fa-trash icon-embed-btn"></i>
								<?=gettext("메타데이터 지우기"); ?>
							</a>
<?php
			else: ?>
<?php
				foreach ($mirror_list as $mirror):
					$mirror_size = gmirror_get_mirror_size($mirror);
					$consumer_size = gmirror_get_unused_consumer_size($consumer['name']);

					if ($consumer_size > $mirror_size): ?>
							<a class="btn btn-xs btn-success" href="diag_gmirror.php?action=insert&amp;consumer=<?=htmlspecialchars($consumer['name']); ?>&amp;mirror=<?=htmlspecialchars($mirror); ?>">
								<i class="fa fa-plus icon-embed-btn"></i>
								<?=htmlspecialchars($mirror); ?>
							</a>
<?php
					endif; ?>
<?php
				endforeach; ?>

<?php
			endif; ?>
						</td>
					</tr>
<?php
		endforeach; ?>
				</tbody>
			</table>
<?php
	else: ?>
		<?=gettext("사용하지않는 고객을 찾을 수 없습니다."); ?>
<?php
	endif; ?>
		</div>
	</div>
<?php
	print_info_box(gettext("고객은 미러의 크기보다 큰 경우에 한하여 추가할 수 있습니다.") . '<br />' .
				   gettext("실패한 미러를 복구하시려면 'Forget' 명령을 수행하신 뒤, 'insert' 명령으로 새 고객을 ."), 'default');
endif; ?>
</form>

<?php
require_once("foot.inc");
