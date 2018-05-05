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
use SFW2\Core\Database;

use SFW2\Controllers\Controller\Helper\RemoveExhaustedDatesTrait;
use SFW2\Controllers\Controller\Helper\DateTimeHelperTrait;
use SFW2\Controllers\Controller\Helper\SeasonTrait;

class GameEncountersController extends AbstractController {

    use RemoveExhaustedDatesTrait;
    use DateTimeHelperTrait;
    use SeasonTrait;

    /**
     * @var Database
     */
    protected $database;

    public function __construct(int $pathId, Database $database) {
        parent::__construct($pathId);
        $this->database = $database;
        $this->removeExhaustedDates();
    }

    public function index($all = false) {
        $content = new Content('SFW2\\Appointments\\GameEncountersController');
        $content->appendJSFile('crud.js');
        $content->assign('title', 'Spielpläne Saison ' . $this->getSeasonDate());
        $content->assign('tables', $this->getTables());
        return $content;
    }

    public function loadEntries() {
        $content = new Content();
        $entries = [];

        $count = 3;
        $start = 0;

        $stmt =
            "SELECT `Id`, `Home`, `Guest`, `Changeable`, `StartDate`, `StartTime` " .
            "FROM `sfw2_game_encounters_item` " .
            "WHERE `GameEncountersId` = '%s' " .
            "LIMIT %s, %s ";

        #$rows = $this->database->select($stmt, [$this->user->getUserId(), $this->pathId, $start, $count]);
        $rows = $this->database->select($stmt, [$_REQUEST['item'], $start, $count]);

        foreach($rows as $row) {
            $entry = [];
            $entry['id'       ] = $row['Id'];
            $entry['home'     ] = $row['Home'];
            $entry['guest'    ] = $row['Guest'];
            $entry['startDate'] = $this->getDate($row['StartDate']);
            $entry['startDay' ] = $this->getDay($row['StartDate']);
            $entry['startTime'] = mb_substr($row['StartTime'], 0, -3);
            #$entry['ownEntry'] = (bool)$row['OwnEntry'];
            $entries[] = $entry;
        }
        $content->assign('entries', $entries);
        return $content;
    }

    protected function getTables() {
        return $this->database->selectKeyValue(
            'Id', 'Caption', 'sfw2_game_encounters', "`PathId` = '%s' ORDER BY `Pos`", [$this->pathId]
        );
    }





    protected function getDates() {
        $stmt =
            "SELECT `sfw2_calendar`.`Id`, `sfw2_calendar`.`Description`, " .
                   "`sfw2_calendar`.`Description2`, " .
                   "`sfw2_calendar`.`StartDate`, `sfw2_calendar`.`StartTime` " .
            "FROM `sfw2_calendar` " .
            "WHERE `sfw2_calendar`.`PathId` = '%s' " .
            "ORDER BY `sfw2_calendar`.`StartDate`, `sfw2_calendar`.`StartTime` ";

        $rs = $this->database->select($stmt, [$this->pathId]);

        $rv = [];
        foreach($rs as $row) {
            $date = [];
            $date['id' ] = $row['Id'];
            $date['startDay'  ] = $this->getDay($row['StartDate']);
            $date['startDate' ] = $this->getDate($row['StartDate']);
            $date['startTime' ] = substr($row['StartTime'], 0, -3);
            $date['desc1'     ] = $row['Description'];
            $date['desc2'     ] = $row['Description2'];
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
        $this->dto->setSaveSuccess(true);
        $this->ctrl->updateModificationDate();
    }

    public function create() {
        $stmt =
            "INSERT INTO `sfw_seasonschedule` " .
            "SET `Date` = '%s', `Time` = '%s', `Description` = '%s', " .
            "`PathId` = '%s'";

        $this->database->insert(
            $stmt,
            [
                $this->dto->getDate('date', true),
                $this->dto->getTime('time', true),
                $this->dto->getText('desc', true),
                $this->pathId
            ]
        );
        #$this->dto->setSaveSuccess();
        #$this->ctrl->updateModificationDate();
    }
}
