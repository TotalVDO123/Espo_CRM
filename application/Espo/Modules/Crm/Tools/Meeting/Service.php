<?php
/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2022 Yurii Kuznietsov, Taras Machyshyn, Oleksii Avramenko
 * Website: https://www.espocrm.com
 *
 * EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word.
 ************************************************************************/

namespace Espo\Modules\Crm\Tools\Meeting;

use Espo\Core\Acl;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\HookManager;
use Espo\Core\ORM\Entity as CoreEntity;
use Espo\Core\Utils\Metadata;
use Espo\Entities\Note;
use Espo\Entities\User;
use Espo\Modules\Crm\Entities\Meeting;
use Espo\ORM\EntityManager;
use LogicException;

class Service
{
    private const NOTE_TYPE_EVENT_CONFIRMATION = 'EventConfirmation';

    public function __construct(
        private User $user,
        private EntityManager $entityManager,
        private HookManager $hookManager,
        private Acl $acl,
        private Metadata $metadata
    ) {}

    /**
     * Set an acceptance for a current user.
     *
     * @throws BadRequest
     * @throws NotFound
     * @throws Forbidden
     */
    public function setAcceptance(string $entityType, string $id, string $status): void
    {
        /** @var string[] $statusList */
        $statusList = $this->entityManager
            ->getDefs()
            ->getEntity($entityType)
            ->getField('acceptanceStatus')
            ->getParam('options') ?? [];

        if (!in_array($status, $statusList) || $status === Meeting::ATTENDEE_STATUS_NONE) {
            throw new BadRequest("Acceptance status not allowed.");
        }

        $entity = $this->entityManager->getEntityById($entityType, $id);

        if (!$entity) {
            throw new NotFound();
        }

        if (!$entity instanceof CoreEntity) {
            throw new LogicException();
        }

        if (!$entity->hasLinkMultipleId('users', $this->user->getId())) {
            throw new Forbidden();
        }

        $currentStatus = $this->entityManager
            ->getRDBRepository($entityType)
            ->getRelation($entity, 'users')
            ->getColumn($this->user, 'status');

        if ($currentStatus === $status) {
            return;
        }

        $this->entityManager
            ->getRDBRepository($entityType)
            ->getRelation($entity, 'users')
            ->updateColumnsById($this->user->getId(), ['status' => $status]);

        if ($this->metadata->get(['scopes', $entityType, 'stream'])) {
            $this->createEventConfirmationNote($entity, $status);
        }

        $actionData = [
            'eventName' => $entity->get('name'),
            'eventType' => $entity->getEntityType(),
            'eventId' => $entity->getId(),
            'dateStart' => $entity->get('dateStart'),
            'status' => $status,
            'link' => 'users',
            'inviteeType' => User::ENTITY_TYPE,
            'inviteeId' => $this->user->getId(),
        ];

        $this->hookManager->process($entityType, 'afterConfirmation', $entity, [], $actionData);
    }

    private function createEventConfirmationNote(CoreEntity $entity, string $status): void
    {
        $options = ['createdById' => $this->user->getId()];

        $style = $this->metadata
            ->get(['entityDefs', $entity->getEntityType(), 'fields', 'acceptanceStatus', 'style', $status]);

        $this->entityManager->createEntity(Note::ENTITY_TYPE, [
            'type' => self::NOTE_TYPE_EVENT_CONFIRMATION,
            'parentId' => $entity->getId(),
            'parentType' => $entity->getEntityType(),
            'relatedId' => $this->user->getId(),
            'relatedType' => $this->user->getEntityType(),
            'data' => [
                'status' => $status,
                'style' => $style,
            ],
        ], $options);
    }

    /**
     * @param string[] $ids
     * @throws Forbidden
     */
    public function massSetHeld(string $entityType, array $ids): void
    {
        if (!$this->acl->checkScope($entityType, Acl\Table::ACTION_EDIT)) {
            throw new Forbidden();
        }

        foreach ($ids as $id) {
            $entity = $this->entityManager->getEntity($entityType, $id);

            if ($entity && $this->acl->checkEntityEdit($entity)) {
                $entity->set('status', Meeting::STATUS_HELD);

                $this->entityManager->saveEntity($entity);
            }
        }
    }

    /**
     * @param string[] $ids
     * @throws Forbidden
     */
    public function massSetNotHeld(string $entityType, array $ids): void
    {
        if (!$this->acl->checkScope($entityType, Acl\Table::ACTION_EDIT)) {
            throw new Forbidden();
        }

        foreach ($ids as $id) {
            $entity = $this->entityManager->getEntityById($entityType, $id);

            if ($entity && $this->acl->checkEntityEdit($entity)) {
                $entity->set('status', Meeting::STATUS_NOT_HELD);

                $this->entityManager->saveEntity($entity);
            }
        }
    }
}