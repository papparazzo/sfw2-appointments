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
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use SFW2\Core\HttpExceptions\HttpBadRequest;
use SFW2\Core\HttpExceptions\HttpNotFound;
use SFW2\Database\DatabaseException;
use SFW2\Database\DatabaseInterface;
use SFW2\Routing\AbstractController;
use SFW2\Routing\HelperTraits\getRoutingDataTrait;
use SFW2\Routing\ResponseEngine;

use SFW2\Validator\Enumerations\CompareEnum;
use SFW2\Validator\Ruleset;
use SFW2\Validator\Validator;
use SFW2\Validator\Validators\IsNotEmpty;
use SFW2\Validator\Validators\IsOneOf;
use SFW2\Validator\Validators\IsTime;

class RecurringAppointments extends AbstractController {

    use getRoutingDataTrait;
   # protected User $user;
   # protected Config $config;

    public function __construct(
        protected DatabaseInterface $database,
    #    User $user, Config $config
    ) {
     #   $this->user = $user;
     #   $this->config = $config;
    }

    public function index(Request $request, ResponseEngine $responseEngine): Response
    {
        $content = [];
        return $responseEngine->render($request, [], "SFW2\\Appointments\\RecurringAppointments");
    }

    /**
     * @throws DatabaseException
     * @throws HttpNotFound
     */
    public function read(Request $request, ResponseEngine $responseEngine): Response
    {
        $count = (int)filter_input(INPUT_GET, 'count', FILTER_VALIDATE_INT);
        $start = (int)filter_input(INPUT_GET, 'offset', FILTER_VALIDATE_INT);

        $count = $count ? $count : 500;

        $stmt =
            "SELECT `Id`, `Description`, `Day`, `StartTime`, `EndTime` " .
            "FROM `{TABLE_PREFIX}_recurring_appointments` " .
            "WHERE `PathId` = %s ";

        /*
        if(!$all) {
            $stmt .= "AND `UserId` = '" . $this->database->escape($this->user->getUserId()) . "' ";
        }
        */
        $stmt .=
            "ORDER BY `Day`, `StartTime` " .
            "LIMIT %s, %s ";

        $rows = $this->database->select($stmt, [$this->getPathId($request), $start, $count]);
        $cnt = $this->database->selectCount('sfw2_recurring_appointments', "WHERE `PathId` = %s", [$this->pathId]);

        $entries = [];
        foreach($rows as $row) {
            $entry = [];
            $entry['id'] = $row['Id'];
            $entry['day' ] = $this->getDayByOffset($row['Day']);
            $entry['from'] = mb_substr($row['StartTime'], 0, -3);
            $entry['till'] = mb_substr($row['EndTime'], 0, -3);
            $entry['desc'] = $row['Description'];
            $entries[] = $entry;
        }
        $content = [];
        #$content->assign('offset', $start + $count);
        #$content->assign('hasNext', $start + $count < $cnt);
        #$content->assign('entries', $entries);
        return $responseEngine->render(
            $request,
            $content,
            "SFW2\\Appointments\\RecurringAppointments"
        );
    }

    /**
     * @throws HttpBadRequest
     * @throws HttpNotFound
     * @throws DatabaseException
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public function delete(Request $request, ResponseEngine $responseEngine): Response
    {
        $entryId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if($entryId === false) {
            throw new HttpBadRequest("invalid entry-id given");
        }

        $stmt =
            "DELETE FROM `{TABLE_PREFIX}_recurring_appointments` " .
            "WHERE `id` = %s " .
            "AND `PathId` = %s";

       # if(!$all) {
       #     $stmt .= "AND `UserId` = '" . $this->database->escape($this->user->getUserId()) . "'";
       # }

        if(!$this->database->delete($stmt, [$entryId, $this->pathId])) {
            throw new HttpNotFound("no entry found for id <$entryId>");
        }
        return $responseEngine->render(
            $request,
            [],
            "SFW2\\Appointments\\RecurringAppointments"
        );
    }

    /**
     * @throws HttpBadRequest
     * @throws HttpNotFound
     * @throws DatabaseException
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public function create(Request $request, ResponseEngine $responseEngine): Response
    {
        $content = [];
        $rulset = new Ruleset();
        $rulset->addNewRules('pdfrom', new IsNotEmpty(), new IsTime());
        $rulset->addNewRules('pdtill', new IsTime(CompareEnum::GREATER_THAN, $_POST['pdfrom']));
        $rulset->addNewRules('pddesc', new IsNotEmpty());
        $rulset->addNewRules('pdday', new IsOneOf([0, 1, 2, 3, 4, 5, 6]));

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
            "INSERT INTO `{TABLE_PREFIX}_recurring_appointments` " .
            "SET `Day` = %s, `StartTime` = %s, `Description` = %s, `PathId` = %s, `UserId` = %s";

        if(!empty($values['pdtill']['value'])) {
            $stmt .= ", `EndTime` = '" . $this->database->escape($values['pdtill']['value']) . "' ";
        }

        $id = $this->database->insert(
            $stmt,
            [
                $values['pdday']['value'],
                $values['pdfrom']['value'],
                $values['pddesc']['value'],
                $this->getPathId($request),
                $this->user->getUserId()
            ]
        );
        $content->assign('id',        ['value' => $id]);

        $content->assign('day', ['value' => $this->getDayByOffset($values['pdday']['value'])]);
        $content->assign('from', ['value' => $values['pdfrom']['value']]);
        $content->assign('till', ['value' => $values['pdtill']['value']]);
        $content->assign('desc', ['value' => $values['pddesc']['value']]);

        return $responseEngine->
            render($request, ['sfw2_payload' => $values])->
            withStatus(StatusCodeInterface::STATUS_UNPROCESSABLE_ENTITY);

    }
}