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
use http\Exception\InvalidArgumentException;
use SFW2\Core\HttpExceptions\HttpBadRequest;
use SFW2\Database\DatabaseInterface;
use SFW2\Routing\AbstractController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;


#use SFW2\Controllers\Controller\Helper\DateTimeHelperTrait;
#use SFW2\Authority\User;

#use SFW2\Core\Config;

use SFW2\Routing\HelperTraits\getPathIdTrait;
use SFW2\Routing\ResponseEngine;
use SFW2\Validator\Enumerations\DateCompareEnum;
use SFW2\Validator\Ruleset;
use SFW2\Validator\Validator;
use SFW2\Validator\Validators\IsNotEmpty;
use SFW2\Validator\Validators\IsBool;
use SFW2\Validator\Validators\IsDate;
use SFW2\Validator\Validators\IsTime;

class OneTimeAppointments extends AbstractController {

    use getPathIdTrait;

    #use DateTimeHelperTrait;

   # protected User $user;
    #protected Config $config;

    public function __construct(
                protected DatabaseInterface $database
                /*, User $user, Config $config*/
    ) {
    #    $this->user = $user;
    #    $this->config = $config;
     #   $this->removeExhaustedDates();
    }

    public function index(Request $request, ResponseEngine $responseEngine): Response
    {
        //$content->appendJSFile('OneTimeAppointments.handlebars.js');
        //$content->appendJSFile('crud.js');

        $content = [
            'caption' => 'Hier findes du alle Termine',
            'modificationDate' => null,
        ];
        $content = array_merge($content, $this->getEntries($this->getPathId($request)));

        return $responseEngine->render(
            $request,
            $content,
            "SFW2\\Appointments\\OneTimeAppointments"
        );
    }

    protected function getEntries(int $pathId): array
    {
        $stmt =
            "SELECT `Id`, `Description`, `StartDate`, `StartTime`, `EndDate`, `EndTime`, `Changeable`, `Location` " .
            "FROM `{TABLE_PREFIX}_one_time_appointments`" .
            "WHERE `PathId` = %s " .
            "ORDER BY `StartDate`, `StartTime` DESC ";

        $rows = $this->database->select($stmt, [$pathId]);

        $changeable = false;
        $entries = [];
        foreach($rows as $row) {
            $entry = [];
            $entry['id'] = $row['Id'];
            $entry['startDate'] = $this->getDay($row['StartDate']) . ', ' . $this->getDate($row['StartDate']);
            $entry['endTime'   ] = $this->getEndTime((string)$row['EndTime']);
            $entry['startTime' ] = $this->getStartTime((string)$row['StartTime'], ($row['EndTime'] !== null));
            if($row['EndDate'] == null) {
                $entry['endDate'   ] = '';
            } else {
                $entry['endDate'   ] = 'bis ' . $this->getDay($row['EndDate']) . ', ' . $this->getDate($row['EndDate']);
            }
            $entry['description' ] = $row['Description'];
            $entry['location'    ] = $row['Location'];
            $entry['changeable'  ] = !($row['Changeable'] == '0');
            if($entry['changeable']) {
                $changeable = true;
            }
            $entries[] = $entry;
        }

        return [
            'entries' => $entries,
            'has_changeable' => $changeable
        ];

    }

    /**
     * @throws HttpBadRequest
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public function delete(Request $request, ResponseEngine $responseEngine): Response
    {
        $entryId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if($entryId === false) {
            throw new HttpBadRequest("invalid entry-id given");
        }
        $stmt =
            "DELETE FROM `{TABLE_PREFIX}_one_time_appointments` " .
            "WHERE `id` = '%s' " .
            "AND `PathId` = '%s'";

       # if(!$all) {
       #     $stmt .= "AND `UserId` = '" . $this->database->escape($this->user->getUserId()) . "'";
       # }

        if(!$this->database->delete($stmt, [$entryId, $this->getPathId($request)])) {
            throw new HttpBadRequest("no entry found for id <$entryId>");
        }
        return $responseEngine->render($request);
    }

    public function create(Request $request, ResponseEngine $responseEngine): Response
    {
        $rulset = new Ruleset();
        $rulset->addNewRules('sdstartdate', new IsNotEmpty(), new IsDate(DateCompareEnum::FUTURE_DATE));
        $rulset->addNewRules('sdstarttime', new IsTime());
        $rulset->addNewRules('sdenddate', new IsDate(DateCompareEnum::FUTURE_DATE), new IsDate(DateCompareEnum::GREATER_THAN, $_POST['sdstartdate']));
        $rulset->addNewRules('sdendtime', new IsTime());
        $rulset->addNewRules('sddesc', new IsNotEmpty());
        $rulset->addNewRules('sdchangeable', new IsBool());

        $validator = new Validator($rulset);
        $values = [];

        $error = $validator->validate($_POST, $values);
        $content->assignArray($values);

        if(!$error) {
            $content->setError(true);
            return $content;
        }

        $stmt =
            "INSERT INTO `{TABLE_PREFIX}_one_time_appointments` " .
            "SET `StartDate` = '%s', `Description` = '%s', `PathId` = '%s', `Changeable` = '%s', `UserId` = '%s' ";

        if(!empty($values['sdstarttime']['value'])) {
            $stmt .= ", `StartTime` = '" . $this->database->escape($values['sdstarttime']['value']) . "' ";
        }
        if(!empty($values['sdendtime']['value'])) {
            $stmt .= ", `EndTime` = '" . $this->database->escape($values['sdendtime']['value']) . "' ";
        }
        if(!empty($values['sdenddate']['value']) && $values['sdenddate']['value'] != $values['sdstartdate']['value']) {
            $stmt .= ", `EndDate` = '" . $this->database->escape($values['sdenddate']['value']) . "' ";
        }

        $id = $this->database->insert(
            $stmt,
            [
                $values['sdstartdate']['value'],
                $values['sddesc']['value'],
                $this->getPathId($request),
                $values['sdchangeable']['value'],
               # $this->user->getUserId()
            ]
        );
        $content->assign('id',        ['value' => $id]);
        $content->assign('startDate', ['value' => $this->getDay($values['sdstartdate']['value']) . ', ' . $this->getDate($values['sdstartdate']['value'])]);
        $content->assign('endTime',   ['value' => $this->getEndTime($values['sdendtime']['value'])]);
        $content->assign('startTime', ['value' => $this->getStartTime($values['sdstarttime']['value'], !empty($values['sdendtime']['value']))]);
        if(empty($values['sdenddate']['value'])) {
            $content->assign('endDate', ['value' => '']);
        } else {
            $content->assign('endDate', ['value' => 'bis ' . $this->getDay($values['sdenddate']['value']) . ', ' . $this->getDate($values['sdenddate']['value'])]);
        }
        $content->assign('desc',       ['value' => $values['sddesc']['value']]);
        $content->assign('changeable', ['value' => (bool)$values['sdchangeable']['value']]);
        $content->dataWereModified();
        return $content;
    }

    protected function removeExhaustedDates() : void {
        $stmt = "DELETE FROM `{TABLE_PREFIX}_one_time_appointments` WHERE `EndDate` < NOW() ";
        $this->database->delete($stmt);
    }

    protected function getStartTime(string $startTime, bool $hasEndTime) : string {
        if($startTime !== '' && $hasEndTime) {
            return 'von ' . $this->getPrintableTime($startTime);
        }
        if($startTime !== '') {
            return 'ab ' . $this->getPrintableTime($startTime);
        }
        return '';
    }

    protected function getEndTime(string $endTime) : string
    {
        if($endTime !== '') {
            return 'bis ' . $this->getPrintableTime($endTime);
        }
        return '';
    }
}