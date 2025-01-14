<?php

namespace App\Model\Table;

use Cake\Core\Configure;
use Cake\Database\Expression\QueryExpression;
use Cake\I18n\I18n;
use App\Lib\Error\Exception\InvalidParameterException;
use Cake\I18n\FrozenTime;

/**
 * FoodCoopShop - The open source software for your foodcoop
 *
 * Licensed under the GNU Affero General Public License version 3
 * For full copyright and license information, please see LICENSE
 * Redistributions of files must retain the above copyright notice.
 *
 * @since         FoodCoopShop 2.3.0
 * @license       https://opensource.org/licenses/AGPL-3.0
 * @author        Mario Rothauer <office@foodcoopshop.com>
 * @copyright     Copyright (c) Mario Rothauer, https://www.rothauer-it.com
 * @link          https://www.foodcoopshop.com
 */
class CronjobsTable extends AppTable
{

    public $cronjobRunDay;

    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->hasMany('CronjobLogs', [
            'foreignKey' => 'cronjob_id'
        ]);
    }

    public function run()
    {

        if (empty($this->cronjobRunDay)) {
            $this->cronjobRunDay = Configure::read('app.timeHelper')->getTimeObjectUTC(date(Configure::read('DateFormat.DatabaseWithTimeAlt')))->toUnixString();
        }

        $cronjobs = $this->find('all', [
            'conditions' => [
                'Cronjobs.active' => APP_ON,
            ]
        ])->all();

        $executedCronjobs = [];

        foreach($cronjobs as $cronjob) {

            if (!$this->executeCronjobRespectingTimeInterval($cronjob)) {
                continue;
            }

            $cronjobRunDayObject = new FrozenTime($this->cronjobRunDay);
            // to be able to use local time in fcs_cronjobs:time_interval, the current time needs to be adabped according to the local timezone
            $cronjobRunDayObject = $cronjobRunDayObject->modify(Configure::read('app.timeHelper')->getTimezoneDiffInSeconds($this->cronjobRunDay) . ' seconds');

            $cronjobNotBeforeTimeWithCronjobRunDay = FrozenTime::createFromArray([
                'year' => $cronjobRunDayObject->year,
                'month' => $cronjobRunDayObject->month,
                'day' => $cronjobRunDayObject->day,
                'hour' => $cronjob->not_before_time->i18nFormat('H'),
                'minute' => $cronjob->not_before_time->i18nFormat('mm'),
                'timezone' => 'UTC',
            ]);
            $cronjobNotBeforeTimeWithCronjobRunDay->modify(Configure::read('app.timeHelper')->getTimezoneDiffInSeconds($cronjobNotBeforeTimeWithCronjobRunDay->getTimestamp()) . ' seconds');

            $cronjobLog = $this->CronjobLogs->find('all', [
                'conditions' => [
                    'CronjobLogs.cronjob_id' => $cronjob->id,
                ],
                'order' => [
                    'CronjobLogs.created' => 'DESC'
                ]
            ])
            ->where(function (QueryExpression $exp) use ($cronjobRunDayObject) {
                return $exp->eq('DATE_FORMAT(CronjobLogs.created, \'%Y-%m-%d\')', $cronjobRunDayObject->i18nFormat(Configure::read('DateFormat.Database')));
            })->first();

            $executeCronjob = true;
            $timeIntervalObject = $cronjobNotBeforeTimeWithCronjobRunDay->copy()->modify('- 1' . $cronjob->time_interval);

            if (!(empty($cronjobLog) || $cronjobLog->success == CronjobLogsTable::FAILURE || $cronjobLog->created->lt($timeIntervalObject))) {
                $executeCronjob = false;
            }

            if (!empty($cronjobLog) && (in_array($cronjobLog->success, [CronjobLogsTable::SUCCESS, CronjobLogsTable::RUNNING])) && $cronjobLog->created->gt($cronjobNotBeforeTimeWithCronjobRunDay)) {
                $executeCronjob = false;
            }

            if ($cronjobNotBeforeTimeWithCronjobRunDay->gt($cronjobRunDayObject)) {
                $executeCronjob = false;
            }

            if ($executeCronjob) {
                $executedCronjobs[] = $this->executeCronjobAndSaveLog($cronjob, $cronjobRunDayObject);
            }

        }

        return $executedCronjobs;

    }

    private function executeCronjobAndSaveLog($cronjob, $cronjobRunDayObject)
    {
        $shellName = $cronjob->name . 'Shell';
        if (!file_exists(ROOT . DS . 'src' . DS . 'Shell' . DS . $shellName . '.php')) {
            throw new InvalidParameterException('shell not found: ' . $cronjob->name);
        }
        $shellClass = '\\App\\Shell\\' . $shellName;
        $shell = new $shellClass();

        $databasePreparedCronjobRunDay = Configure::read('app.timeHelper')->getTimeObjectUTC(
            $cronjobRunDayObject->i18nFormat(Configure::read('DateFormat.DatabaseWithTime')
        ));
        $entity = $this->CronjobLogs->newEntity(
            [
                'cronjob_id' => $cronjob->id,
                'created' => $databasePreparedCronjobRunDay,
                'success' => CronjobLogsTable::RUNNING,
            ]
        );
        $this->CronjobLogs->save($entity);

        try {
            $success = $shell->main();
            $success = $success !== true ? CronjobLogsTable::FAILURE : CronjobLogsTable::SUCCESS;
        } catch (\Exception $e) {
            $success = CronjobLogsTable::SUCCESS;
            if (get_class($e) != 'Cake\Network\Exception\SocketException') {
                $success = CronjobLogsTable::FAILURE;
            }
        }

        $entity->success = $success;
        $this->CronjobLogs->save($entity);

        return [
            'name' => $cronjob->name,
            'time_interval' => $cronjob->time_interval,
            'created' => $entity->created->i18nFormat(Configure::read('DateFormat.DatabaseWithTime')),
            'success' => $success,
        ];
    }

    private function executeCronjobRespectingTimeInterval($cronjob)
    {

        $tmpLocale = I18n::getLocale();
        I18n::setLocale('en_US');
        $currentWeekday = Configure::read('app.timeHelper')->getWeekdayName(date('w', $this->cronjobRunDay));
        I18n::setLocale($tmpLocale);

        $currentDayOfMonth = date('j', $this->cronjobRunDay);
        $result = false;

        switch($cronjob->time_interval) {
            case 'day':
                $result = true;
                break;
            case 'week':
                if ($cronjob->weekday == '') {
                    throw new InvalidParameterException('weekday not available');
                }
                $cronjobWeekdayIsCurrentWeekday = $cronjob->weekday == $currentWeekday;
                if ($cronjobWeekdayIsCurrentWeekday) {
                    $result = true;
                }
                break;
            case 'month':
                if ($cronjob->day_of_month == '' || $cronjob->day_of_month > 31) {
                    throw new InvalidParameterException('day of month not available or not valid');
                }
                if ($cronjob->day_of_month == 0) {
                    $cronjob->day_of_month = Configure::read('app.timeHelper')->getNumberOfDays($this->cronjobRunDay);
                }
                $cronjobDayOfMonthIsCurrentDayOfMonth = $cronjob->day_of_month == $currentDayOfMonth;
                if ($cronjobDayOfMonthIsCurrentDayOfMonth) {
                    $result = true;
                }
                break;
        }

        return $result;

    }

}
