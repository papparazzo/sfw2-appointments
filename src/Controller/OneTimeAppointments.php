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

use Fig\Http\Message\StatusCodeInterface;
use SFW2\Core\HttpExceptions\HttpBadRequest;
use SFW2\Database\DatabaseInterface;
use SFW2\Routing\AbstractController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;


#use SFW2\Controllers\Controller\Helper\DateTimeHelperTrait;
#use SFW2\Authority\User;

#use SFW2\Core\Config;

use SFW2\Routing\HelperTraits\getPathTrait;
use SFW2\Routing\ResponseEngine;
use SFW2\Validator\Enumerations\DateCompareEnum;
use SFW2\Validator\Ruleset;
use SFW2\Validator\Validator;
use SFW2\Validator\Validators\IsNotEmpty;
use SFW2\Validator\Validators\IsBool;
use SFW2\Validator\Validators\IsDate;
use SFW2\Validator\Validators\IsTime;

class OneTimeAppointments extends AbstractController {

    use getPathTrait;

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

    /**
     * @param Request $request
     * @param ResponseEngine $responseEngine
     * @return Response
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public function create(Request $request, ResponseEngine $responseEngine): Response
    {
        $rulset = new Ruleset();
        $rulset->addNewRules('sdstartdate', new IsNotEmpty(), new IsDate(DateCompareEnum::FUTURE_DATE));
        $rulset->addNewRules('sdstarttime', new IsNotEmpty(), new IsTime());
        $rulset->addNewRules('sdenddate', new IsDate(DateCompareEnum::FUTURE_DATE), new IsDate(DateCompareEnum::GREATER_THAN, $_POST['sdstartdate']));
        $rulset->addNewRules('sdendtime', new IsTime());
        $rulset->addNewRules('sddesc', new IsNotEmpty());
        $rulset->addNewRules('sdlocation', new IsNotEmpty());
        $rulset->addNewRules('sdchangeable', new IsBool());

        $validator = new Validator($rulset);
        $values = [];

        $error = $validator->validate($_POST, $values);

        if(!$error) {
            return
                $responseEngine->
                render($request, ['sfw2_payload' => $values])->
                withStatus(StatusCodeInterface::STATUS_UNPROCESSABLE_ENTITY);
        }
/*
        $stmt =
            "INSERT INTO `{TABLE_PREFIX}_one_time_appointments` " .
            "SET `PathId` = %s, `StartDate` = %s, `Description` = %s, `Changeable` = %s, `Location` = %s ";

        if(!empty($values['sdstarttime']['value'])) {
            $stmt .= ", `StartTime` = " . $this->database->escape($values['sdstarttime']['value']) . " ";
        }
        if(!empty($values['sdendtime']['value'])) {
            $stmt .= ", `EndTime` = " . $this->database->escape($values['sdendtime']['value']) . " ";
        }
        if(!empty($values['sdenddate']['value']) && $values['sdenddate']['value'] != $values['sdstartdate']['value']) {
            $stmt .= ", `EndDate` = " . $this->database->escape($values['sdenddate']['value']) . " ";
        }
*/

        $stmt =
            "INSERT INTO `{TABLE_PREFIX}_one_time_appointments` (" .
            "`PathId`, `StartDate`, `Description`, `Changeable`, `Location`" .
            ") VALUES(%s, %s, %s, %s, %s)";


        $this->database->insert(
            $stmt,
            [
                $this->getPathId($request),
                $values['sdstartdate']['value'],
                $values['sddesc']['value'],
                $values['sdchangeable']['value'],
                $values['sdlocation']['value'],
               # $this->user->getUserId()
            ]
        );

        return $responseEngine->render($request);
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

    /**
     * @param string $in
     * @return string
     * @deprecated
     * TODO make this a trait
     */
    protected function getDay(string $in): string
    {
        // TODO what shoud we return here?
        return $in;
    }

    /**
     * @param string $in
     * @return string
     * @deprecated
     * TODO make this a trait
     */
    protected function getDate(string $in): string
    {
        // TODO what shoud we return here?
        return $in;
    }

    /**
     * @param string $in
     * @return string
     * @deprecated
     * TODO make this a trait
     */
    protected function getPrintableTime(string $in): string
    {
        // TODO what shoud we return here?
        return $in;
    }
}