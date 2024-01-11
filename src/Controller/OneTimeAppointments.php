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
use Fig\Http\Message\StatusCodeInterface;
use SFW2\Core\HttpExceptions\HttpBadRequest;
use SFW2\Core\HttpExceptions\HttpNotFound;
use SFW2\Core\Permission\AccessType;
use SFW2\Core\Utils\DateTimeHelper;
use SFW2\Core\Permission\PermissionInterface;
use SFW2\Database\DatabaseInterface;
use SFW2\Routing\AbstractController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use SFW2\Routing\HelperTraits\getRoutingDataTrait;
use SFW2\Routing\ResponseEngine;
use SFW2\Validator\Enumerations\DateCompareEnum;
use SFW2\Validator\Ruleset;
use SFW2\Validator\Validator;
use SFW2\Validator\Validators\IsNotEmpty;
use SFW2\Validator\Validators\IsBool;
use SFW2\Validator\Validators\IsDate;
use SFW2\Validator\Validators\IsTime;

final class OneTimeAppointments extends AbstractController {

    use getRoutingDataTrait;

    public function __construct(
        private readonly DatabaseInterface   $database,
        private readonly DateTimeHelper      $dateTimeHelper,
        private readonly PermissionInterface $permission
    ) {
        $this->removeExhaustedDates();
    }

    /**
     * @throws HttpNotFound
     * @throws Exception
     */
    public function index(Request $request, ResponseEngine $responseEngine): Response
    {
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

    /**
     * @throws Exception
     */
    protected function getEntries(int $pathId): array
    {
        $stmt = /** @lang MySQL */
            "SELECT `Id`, `Description`, `StartDate`, `StartTime`, `EndDate`, `EndTime`, `Changeable`, `Location` " .
            "FROM `{TABLE_PREFIX}_one_time_appointments`" .
            "WHERE `PathId` = %s " .
            "ORDER BY `StartDate`, `StartTime` DESC ";

        $rows = $this->database->select($stmt, [$pathId]);

        $changeable = false;
        $entries = [];

        $deleteAllowed = $this->permission->checkPermission($pathId, 'delete');

        foreach($rows as $row) {
            $entry = [];
            $entry['id'            ] = $row['Id'];
            $entry['date'          ] = $this->dateTimeHelper->getDateString((string)$row['StartDate'], (string)$row['EndDate']);
            $entry['time'          ] = $this->dateTimeHelper->getTimeString((string)$row['StartTime'], (string)$row['EndTime']);
            $entry['description'   ] = $row['Description'];
            $entry['location'      ] = $row['Location'];
            $entry['changeable'    ] = !($row['Changeable'] == '0');
            $entry['delete_allowed'] = $deleteAllowed !== AccessType::VORBIDDEN;
            if($entry['changeable']) {
                $changeable = true;
            }
            $entries[] = $entry;
        }

        return [
            'entries' => $entries,
            'has_changeable' => $changeable,
            'create_allowed' => $this->permission->checkPermission($pathId, 'create') !== AccessType::VORBIDDEN
        ];
    }

    /**
     * @throws HttpBadRequest
     * @throws HttpNotFound
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
            "WHERE `id` = %s " .
            "AND `PathId` = %s";

       # if(!$all) {
       #     $stmt .= "AND `UserId` = '" . $this->database->escape($this->user->getUserId()) . "'";
       # }

        if($this->database->delete($stmt, [$entryId, $this->getPathId($request)]) !== 1) {
            throw new HttpNotFound("no entry found for id <$entryId>");
        }
        return $responseEngine->render($request);
    }

    /**
     * @param Request $request
     * @param ResponseEngine $responseEngine
     * @return Response
     * @noinspection PhpMissingParentCallCommonInspection
     * @throws HttpNotFound
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

    protected function removeExhaustedDates(): void
    {
        $stmt =
            "DELETE FROM `{TABLE_PREFIX}_one_time_appointments` " .
            "WHERE (`EndDate` IS NULL AND DATE_ADD(`StartDate`, INTERVAL 5 DAY) < NOW()) " .
            "OR DATE_ADD(`EndDate`, INTERVAL 5 DAY) < NOW()";

        $this->database->delete($stmt);
    }
}