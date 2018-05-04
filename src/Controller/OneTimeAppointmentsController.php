<?php

/**
 *  SFW2 - SimpleFrameWork
 *
 *  Copyright (C) 2017  Stefan Paproth
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program. If not, see <http://www.gnu.org/licenses/agpl.txt>.
 *
 */

namespace SFW2\Appointments\Controller;

use SFW2\Routing\AbstractController;
use SFW2\Routing\Result\Content;
use SFW2\Controllers\Controller\Helper\RemoveExhaustedDatesTrait;
use SFW2\Controllers\Controller\Helper\DateTimeHelperTrait;

use SFW2\Core\Database;
use SFW2\Core\Config;

class OneTimeAppointmentsController extends AbstractController {

    use RemoveExhaustedDatesTrait;
    use DateTimeHelperTrait;

    /**
     * @var Database
     */
    protected $database;

    /**
     * @var Config
     */
    protected $config;

    public function __construct(int $pathId, Database $database, Config $config) {
        parent::__construct($pathId);
        $this->database = $database;
        $this->config = $config;
        $this->removeExhaustedDates();
    }

    public function index($all = false) {
        $content = new Content('SFW2\\Appointments\\OneTimeAppointments');
        $content->assign('singularDates', $this->getDates());
        return $content;
    }

    protected function getDates() {
        $stmt =
            "SELECT `sfw2_calendar`.`Id`, `sfw2_calendar`.`Description`, " .
                   "`sfw2_calendar`.`StartDate`, `sfw2_calendar`.`StartTime`, " .
                   "`sfw2_calendar`.`EndDate`, `sfw2_calendar`.`EndTime`, " .
                   "`sfw2_calendar`.`Changeable` " .
            "FROM `sfw2_calendar` " .
            "WHERE `sfw2_calendar`.`PathId` = '%s' " .
            "AND `sfw2_calendar`.`Day` IS NULL " .
            "ORDER BY `sfw2_calendar`.`StartDate`, " .
                     "`sfw2_calendar`.`StartTime` ";

        $rs = $this->database->select($stmt, array($this->pathId));

        $changeable = false;
        $rv = [];
        foreach($rs as $row) {
            $date = [];
            $date['id'          ] = $row['Id'];
            $date['startDate'   ] = $this->getDate($row['StartDate']);
            $date['startDateDay'] = $this->getDay($row['StartDate']);
            $date['endDate'     ] = $this->getDate($row['EndDate']);
            $date['startTime' ] = mb_substr($row['StartTime'], 0, -3);
            $date['endTime'   ] = mb_substr($row['EndTime'  ], 0, -3);
            $date['desc'      ] = $row['Description'];
            $date['changeable'] = $row['Changeable'] == '0' ? false : true;
            if($date['changeable']) {
                $changeable = true;
            }
            $rv[] = $date;
        }
        return ['data' => $rv, 'changeable' => $changeable];
    }

    public function delete($all = false) {
        $stmt =
            "DELETE FROM `sfw2_calendar` " .
            "WHERE `sfw2_calendar`.`id` = '%s' " .
            "AND `PathId` = '%s'";

        $this->database->delete($stmt, [$id, $this->pathId]);
        $this->dto->setSaveSuccess(true);
        $this->ctrl->updateModificationDate();
    }

    public function create() {
        $tmp['sdstartdate'] = $this->dto->getDate('sdstartdate', true, 'Das Startdatum', true);
        $tmp['sdenddate'] = $this->dto->getDate('sdenddate', false, 'Das Enddatum', true);
        $tmp['sdstarttime'] = $this->dto->getTime('sdstarttime', false, 'Die Startzeit');
        $tmp['sdendtime'] = $this->dto->getTime('sdendtime', false, 'Die Endzeit');
        $tmp['sdchangeable'] = $this->dto->getBool('sdchangeable', 'Die Auswahl');
        $tmp['sddesc'] = $this->dto->getText('sddesc', true, 'Die Beschreibung');

        if(
            !empty($tmp['sdtill']) &&
            intval(str_replace(':', '', $tmp['sdstarttime'])) >
            intval(str_replace(':', '', $tmp['sdendtime']))
        ) {
            $this->dto->getErrorProvider()->addError(
                SFW_Error_Provider::CMP_TIME,
                array(
                    '<NAME>'  => 'Die Endzeit',
                    '<NAME2>' => 'die Startzeit'
                ),
                'sdtill'
            );
        }

        if($this->dto->getErrorProvider()->hasErrors()) {
            return;
        }

        $stmt =
            "INSERT INTO `sfw2_calendar` " .
            "SET `StartDate` = '%s', `Description` = '%s', " .
            "`PathId` = '%s', `Changeable` = '%s' ";

        if(!empty($tmp['sdstarttime'])) {
            $stmt .= ", `StartTime` = '" . $this->db->escape($tmp['sdstarttime']) . "' ";
        }

        if(!empty($tmp['sdendtime'])) {
            $stmt .= ", `EndTime` = '" . $this->db->escape($tmp['sdendtime']) . "' ";
        }

        if(!empty($tmp['sdenddate']) && $tmp['sdenddate'] != $tmp['sdstartdate']) {
            $stmt .= ", `EndDate` = '" . $this->db->escape(
                $this->database->convertToMysqlDate($tmp['sdenddate'])
            ) . "' ";
        }

        $this->database->insert(
            $stmt,
            [
                $this->database->convertToMysqlDate($tmp['sdstartdate']),
                $tmp['sddesc'],
                $this->ctrl->getPathId(),
                $tmp['sdchangeable']?'1':'0'
            ]
        );

        $this->dto->setSaveSuccess();
        $this->ctrl->updateModificationDate();
        $tmp['sdstartdate' ] = '';
        $tmp['sdenddate'   ] = '';
        $tmp['sdstarttime' ] = '';
        $tmp['sdendtime'   ] = '';
        $tmp['sddesc'      ] = '';
        $tmp['sdchangeable'] = false;
    }
}