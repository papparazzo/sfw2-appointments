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

use Exception;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use SFW2\Core\Utils\DateTimeHelper;
use SFW2\Database\DatabaseInterface;
use SFW2\Routing\AbstractController;
use SFW2\Routing\HelperTraits\getRoutingDataTrait;
use SFW2\Routing\ResponseEngine;
#use SFW2\Routing\Result\Content;
#use SFW2\Authority\User;

#use SFW2\Controllers\Controller\Helper\DateTimeHelperTrait;
#use SFW2\Controllers\Controller\Helper\SeasonTrait;

use SFW2\Validator\Ruleset;
use SFW2\Validator\Validator;
use SFW2\Validator\Validators\IsNotEmpty;
use SFW2\Validator\Validators\IsDate;
use SFW2\Validator\Validators\IsTime;

class GameEncounters extends AbstractController {

    use SeasonTrait;
    use getRoutingDataTrait;


    public function __construct(
        protected DatabaseInterface $database,
        protected DateTimeHelper $dateTimeHelper,
        protected string $title = ''
    ) {
        $this->removeExhaustedDates();
    }

    public function index(Request $request, ResponseEngine $responseEngine): Response
    {
        # $content->appendJSFile('GameEncounters.handlebars.js');
        $content = [
            'title' => 'Spielpläne',
            'subTitle' => $this->title
        ];

        return $responseEngine->render(
            $request,
            $content,
            "SFW2\\Appointments\\GameEncounters"
        );
    }

    /**
     * @throws Exception
     */
    public function read(Request $request, ResponseEngine $responseEngine): Response
    {
        $content = new Content('GameEncounters');
        $entries = [];

        $count = (int)filter_input(INPUT_GET, 'count', FILTER_VALIDATE_INT);
        $start = (int)filter_input(INPUT_GET, 'offset', FILTER_VALIDATE_INT);

        $count = $count ?: 500;

        $stmt =
            "SELECT `game_encounters`.`Id`, `Home`, `Guest`, `StartDate`, `StartTime`, " .
            "IF(`game_encounters`.`UserId` = '%s', '1', '0') AS `OwnEntry` " .
            "FROM `{TABLE_PREFIX}_game_encounters` AS `game_encounters` " .
            "LEFT JOIN `{TABLE_PREFIX}_user` AS `user` " .
            "ON `user`.`Id` = `game_encounters`.`UserId` " .
            "WHERE `PathId` = '%s' " .
            "ORDER BY `StartDate`, `StartTime`  DESC " .
            "LIMIT %s, %s ";

        $rows = $this->database->select($stmt, [$this->user->getUserId(), $this->pathId, $start, $count]);
        $cnt = $this->database->selectCount('{TABLE_PREFIX}_game_encounters', "WHERE `PathId` = '%s'", [$this->pathId]);

        foreach($rows as $row) {
            $entry = [];
            $entry['id'       ] = $row['Id'];
            $entry['home'     ] = $row['Home'];
            $entry['guest'    ] = $row['Guest'];
            $entry['startDate'] = $this->dateTimeHelper->getDate($row['StartDate']);
            $entry['startDay' ] = $this->dateTimeHelper->getDate($row['StartDate']);
            $entry['startTime'] = mb_substr($row['StartTime'], 0, -3);
            $entry['ownEntry'] = (bool)$row['OwnEntry'];
            $entries[] = $entry;
        }
        $content->assign('offset', $start + $count);
        $content->assign('hasNext', $start + $count < $cnt);
        $content->assign('entries', $entries);

        return $responseEngine->render(
            $request,
            $content,
            "SFW2\\Appointments\\GameEncounters"
        );
    }

    /**
     * @noinspection PhpMissingParentCallCommonInspection
     * @throws HttpBadRequest | HttpNotFound
     */
    public function delete(Request $request, ResponseEngine $responseEngine): Response
    {
        $entryId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if($entryId === false) {
            throw new ResolverException("invalid data given", ResolverException::INVALID_DATA_GIVEN);
        }
        $stmt =
            "DELETE FROM `{TABLE_PREFIX}_game_encounters` " .
            "WHERE `id` = '%s' " .
            "AND `PathId` = '%s'";

        if(!$all) {
            $stmt .=
                "AND `UserId` = '" . $this->database->escape($this->user->getUserId()) . "'";
        }

        if(!$this->database->delete($stmt, [$entryId, $this->pathId])) {
            throw new ResolverException("no entry found", ResolverException::NO_PERMISSION);
        }
        return $responseEngine->render(
            $request,
            [],
            "SFW2\\Appointments\\GameEncounters"
        );
    }

    /**
     * @noinspection PhpMissingParentCallCommonInspection
     * @throws HttpBadRequest | HttpNotFound
     */
    public function create(Request $request, ResponseEngine $responseEngine): Response
    {
        $content = new Content('GameEncounters');

        $rulset = new Ruleset();
        $rulset->addNewRules('home', new IsNotEmpty());
        $rulset->addNewRules('guest', new IsNotEmpty());
        $rulset->addNewRules('startTime', new IsNotEmpty(), new IsTime());
        $rulset->addNewRules('startDate', new IsNotEmpty(), new IsDate(IsDate::FUTURE_DATE));

        $validator = new Validator($rulset);
        $values = [];

        $error = $validator->validate($_POST, $values);
        $content->assignArray($values);

        if(!$error) {
            $content->setError(true);
            return $content;
        }

        $stmt =
            "INSERT INTO `{TABLE_PREFIX}_game_encounters` " .
            "SET `StartDate` = '%s', `StartTime` = '%s', `Home` = '%s', Guest = '%s',`PathId` = '%s', `UserId` = '%s'";

        $id = $this->database->insert(
            $stmt,
            [
                $values['startDate']['value'],
                $values['startTime']['value'],
                $values['home']['value'],
                $values['guest']['value'],
                $this->pathId,
                $this->user->getUserId()
            ]
        );
        $content->assign('id',        ['value' => $id]);
        $content->assign('startDate', ['value' => $this->getDate($values['startDate']['value'])]);
        $content->assign('startDay',  ['value' => $this->getDay($values['startDate']['value'])]);
        $content->dataWereModified();
        return $content;
    }

    protected function removeExhaustedDates() : void {
        $stmt = "DELETE FROM `{TABLE_PREFIX}_game_encounters` WHERE `StartDate` < NOW() ";
        $this->database->delete($stmt);
    }
}
