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

class RecurringAppointmentsController extends AbstractController {

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
        $content = new Content('SFW2\\Appointments\\RecurringAppointments');
        $content->appendJSFile('crud.js');
        $content->assign('periodicalDates', $this->getDates());

        return $content;
    }

    protected function getDates() {
        $stmt =
            "SELECT `sfw2_calendar`.`Id`, `sfw2_calendar`.`Description`, " .
                   "`sfw2_calendar`.`Day`, `sfw2_calendar`.`StartTime`, " .
                   "`sfw2_calendar`.`EndTime` " .
            "FROM `sfw2_calendar` " .
            "WHERE `sfw2_calendar`.`PathId` = '%s' " .
            "AND `sfw2_calendar`.`Day` IS NOT NULL " .
            "ORDER BY `sfw2_calendar`.`Day`, `sfw2_calendar`.`StartTime` ";

        $rs = $this->database->select($stmt, array($this->pathId));

        if(isset($rs[0]['Day'])) {
            $ld = $rs[0]['Day'];
        }

        $rv = array();
        foreach($rs as $row) {
            if($ld != $row['Day']) {
                $ld = $row['Day'];
                $date = array();
                $date['day' ] = '&nbsp;';
                $date['time'] = '&nbsp;';
                $date['desc'] = '';
                $rv[] = $date;
            }

            $date = array();
            $date['id'  ] = $row['Id'];
            $date['day' ] = $this->getDayByOffset($row['Day']);
            $date['from'] = mb_substr($row['StartTime'], 0, -3);
            $date['till'] = mb_substr($row['EndTime'], 0, -3);
            $date['desc'] = $row['Description'];
            $rv[] = $date;
        }
        return $rv;
    }

    public function delete($all = false) {
        $stmt =
            "DELETE FROM `sfw2_calendar` " .
            "WHERE `sfw2_calendar`.`id` = '%s' " .
            "AND `PathId` = '%s'";

        $this->database->delete($stmt, [$id, $this->pathId]);
        #$this->ctrl->updateModificationDate();
    }

    public function create() {
        $tmp['pdfrom'] = $this->dto->getTime('pdfrom', true,  'Die Startzeit');
        $tmp['pdtill'] = $this->dto->getTime('pdtill', false, 'Die Endzeit');
        $tmp['pddesc'] = $this->dto->getText('pddesc', true, 'Die Beschreibung');
        $tmp['pdday' ] = $this->dto->getArrayValue('pdday', true, 'Der Tag', ['1', '2', '3', '4', '5', '6', '7']);

        if(
            !empty($tmp['pdtill']) &&
            intval(str_replace(':', '', $tmp['pdfrom'])) >
            intval(str_replace(':', '', $tmp['pdtill']))
        ) {
            $this->dto->getErrorProvider()->addError(
                SFW_Error_Provider::CMP_TIME,
                array(
                    '<NAME>'  => 'Die Endzeit',
                    '<NAME2>' => 'die Startzeit'
                ),
                'pdtill'
            );
        }

        if($this->dto->getErrorProvider()->hasErrors()) {
            return;
        }

        $stmt =
            "INSERT INTO `sfw2_calendar` " .
            "SET `Day` = '%s', `StartTime` = '%s', `Description` = '%s', " .
            "`PathId` = '%s'";

        if(!empty($tmp['pdtill'])) {
            $stmt .= ", `EndTime` = '" . $this->db->escape($tmp['pdtill']) . "' ";
        }

        $this->database->insert(
            $stmt,
            [$tmp['pdday'], $tmp['pdfrom'], $tmp['pddesc'], $this->pathId]
        );
        #$this->dto->setSaveSuccess();
        #$this->ctrl->updateModificationDate();
        $tmp['pdday' ] = '';
        $tmp['pdfrom'] = '';
        $tmp['pdtill'] = '';
        $tmp['pddesc'] = '';
    }
}