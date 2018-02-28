<?php
/*
 * firewall_schedule.php
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
2018.02.28
한글화 번역 시작
*/

##|+PRIV
##|*IDENT=page-firewall-schedules
##|*NAME=Firewall: Schedules
##|*DESCR=Allow access to the 'Firewall: Schedules' page.
##|*MATCH=firewall_schedule.php*
##|-PRIV

define('CLOCK', '<i class="fa fa-clock-o icon-black"></i>');

$dayArray = array (gettext('월'), gettext('화'), gettext('수'), gettext('목'), gettext('금'), gettext('토'), gettext('일'));
$monthArray = array (gettext('1월'), gettext('2월'), gettext('3월'), gettext('4월'), gettext('5월'), gettext('6월'), gettext('7월'), gettext('8월'), gettext('9월'), gettext('10월'), gettext('11월'), gettext('12월'));

require_once("guiconfig.inc");
require_once("filter.inc");
require_once("shaper.inc");

$pgtitle = array(gettext("Firewall"), gettext("Schedules"));

if (!is_array($config['schedules']['schedule'])) {
	$config['schedules']['schedule'] = array();
}

$a_schedules = &$config['schedules']['schedule'];

if ($_POST['act'] == "del") {
	if ($a_schedules[$_POST['id']]) {
		/* make sure rule is not being referenced by any nat or filter rules */
		$is_schedule_referenced = false;
		$referenced_by = false;
		$schedule_name = $a_schedules[$_POST['id']]['name'];

		if (is_array($config['filter']['rule'])) {
			foreach ($config['filter']['rule'] as $rule) {
				//check for this later once this is established
				if ($rule['sched'] == $schedule_name) {
					$referenced_by = $rule['descr'];
					$is_schedule_referenced = true;
					break;
				}
			}
		}

		if ($is_schedule_referenced == true) {
			$savemsg = sprintf(gettext("%s에서 현재 사용중인 일정입니다 삭제할 수 없습니다."), $referenced_by);
		} else {
			unset($a_schedules[$_POST['id']]);
			write_config(gettext("방화벽 일정을 삭제했습니다."));
			header("Location: firewall_schedule.php");
			exit;
		}
	}
}

include("head.inc");

if ($savemsg) {
	print_info_box($savemsg, 'success');
}
?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('')?></h2></div>
	<div class="panel-body table-responsive">
		<table class="table table-striped table-hover table-condensed table-rowdblclickedit">
			<thead>
				<tr>
					<th><!--"Active" indicator--></th>
					<th><?=gettext("이름")?></th>
					<th><?=gettext("범위: 날짜 / 시간 / ")?></th>
					<th><?=gettext("Description")?></th>
					<th><?=gettext("Actions")?></th>
				</tr>
			</thead>
			<tbody>
<?php
$i = 0;
foreach ($a_schedules as $schedule):
	$schedstatus = filter_get_time_based_rule_status($schedule);
?>
				<tr>
					<td>
						<?=($schedstatus) ? '<a title="' . gettext("일정이 현재 활성 상태입니다.") . '">' . CLOCK . '</a>':''?>
					</td>
					<td>
						 <?=htmlspecialchars($schedule['name'])?>
					</td>
					<td>
<?php
	$first = true;
	foreach ($schedule['timerange'] as $timerange) {
		$tempFriendlyTime = "";
		$tempID = "";
		$firstprint = false;

		if ($timerange) {
			$dayFriendly = "";
			$tempFriendlyTime = "";

			//get hours
			$temptimerange = $timerange['hour'];
			$temptimeseparator = strrpos($temptimerange, "-");

			$starttime = substr ($temptimerange, 0, $temptimeseparator);
			$stoptime = substr ($temptimerange, $temptimeseparator+1);

			if ($timerange['month']) {
				$tempmontharray = explode(",", $timerange['month']);
				$tempdayarray = explode(",", $timerange['day']);
				$arraycounter = 0;
				$firstDayFound = false;
				$firstPrint = false;
				foreach ($tempmontharray as $monthtmp) {
					$month = $tempmontharray[$arraycounter];
					$day = $tempdayarray[$arraycounter];

					if (!$firstDayFound) {
						$firstDay = $day;
						$firstmonth = $month;
						$firstDayFound = true;
					}

					$currentDay = $day;
					$nextDay = $tempdayarray[$arraycounter+1];
					$currentDay++;

					if (($currentDay != $nextDay) || ($tempmontharray[$arraycounter] != $tempmontharray[$arraycounter+1])) {
						if ($firstPrint) {
							$dayFriendly .= "<br />";
						}

						$currentDay--;

						if ($currentDay != $firstDay) {
							$dayFriendly .= $monthArray[$firstmonth-1] . " " . $firstDay . " - " . $currentDay ;
						} else {
							$dayFriendly .=	 $monthArray[$month-1] . " " . $day;
						}

						$firstDayFound = false;
						$firstPrint = true;
					}
					$arraycounter++;
				}
			} else {
				$tempdayFriendly = $timerange['position'];
				$firstDayFound = false;
				$tempFriendlyDayArray = explode(",", $tempdayFriendly);
				$currentDay = "";
				$firstDay = "";
				$nextDay = "";
				$counter = 0;

				foreach ($tempFriendlyDayArray as $day) {
					if ($day != "") {
						if (!$firstDayFound) {
							$firstDay = $tempFriendlyDayArray[$counter];
							$firstDayFound = true;
						}

						$currentDay =$tempFriendlyDayArray[$counter];
						//get next day
						$nextDay = $tempFriendlyDayArray[$counter+1];
						$currentDay++;

						if ($currentDay != $nextDay) {
							if ($firstprint) {
								$dayFriendly .= "<br />";
							}

							$currentDay--;

							if ($currentDay != $firstDay) {
								$dayFriendly .= $dayArray[$firstDay-1] . " - " . $dayArray[$currentDay-1];
							} else {
								$dayFriendly .= $dayArray[$firstDay-1];
							}

							$firstDayFound = false;
							$firstprint = true;
						}
						$counter++;
					}
				}
			}

			$timeFriendly = $starttime . "-" . $stoptime;
			$description = htmlspecialchars($timerange['rangedescr']);

			print(($first ? '':'<br />') . $dayFriendly . ' / ' . $timeFriendly . ' / ' . $description);
		}
	$first = false;
	}
?>
					</td>

					<td>
						<?=htmlspecialchars($schedule['descr'])?>&nbsp;
					</td>

					<td>
						<a class="fa fa-pencil" title="<?=gettext("일정 편집"); ?>" href="firewall_schedule_edit.php?id=<?=$i?>"></a>
						<a class="fa fa-trash" title="<?=gettext("일정 삭제")?>" href="firewall_schedule.php?act=del&amp;id=<?=$i?>" usepost></a>

					</td>
				</tr>
<?php
	$i++;
endforeach;
?>
			</tbody>
		</table>
	</div>
</div>

<?=($i > 0) ? CLOCK . gettext(' 예약이 현재 활성 상태입니다.'):''?>

<nav class="action-buttons">
	<a href="firewall_schedule_edit.php" class="btn btn-sm btn-success">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext("추가")?>
	</a>
</nav>

<div class="infoblock">
	<?php print_info_box(gettext('스케줄은 방화벽 규칙에 사용될 시간 범위에 대한 자리 표시자 역할을 합니다.'), 'info', false); ?>
</div>

<?php

include("foot.inc");
