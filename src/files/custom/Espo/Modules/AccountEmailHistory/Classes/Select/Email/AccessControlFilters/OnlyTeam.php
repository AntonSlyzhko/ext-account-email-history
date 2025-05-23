<?php

namespace Espo\Modules\AccountEmailHistory\Classes\Select\Email\AccessControlFilters;

use Espo\Classes\Select\Email\AccessControlFilters\OnlyTeam as CoreOnlyTeam;
use Espo\Classes\Select\Email\Helpers\JoinHelper;
use Espo\Core\Acl;
use Espo\Core\Name\Field;
use Espo\Core\Select\AccessControl\Filter;
use Espo\Core\Select\SelectBuilderFactory;
use Espo\Entities\Email;
use Espo\Entities\Team;
use Espo\Entities\User;
use Espo\Modules\Crm\Entities\Account;
use Espo\ORM\Name\Attribute;
use Espo\ORM\Query\Part\Condition;
use Espo\ORM\Query\SelectBuilder as QueryBuilder;

/**
 * @noinspection PhpUnused
 */
class OnlyTeam implements Filter
{
    public function __construct(
        private User $user,
        private Acl $acl,
        private JoinHelper $joinHelper,
        private SelectBuilderFactory $selectBuilderFactory,
        private CoreOnlyTeam $coreOnlyTeam
    ) {}

    public function apply(QueryBuilder $queryBuilder): void
    {
        if (!$this->acl->checkScope(Account::ENTITY_TYPE, Acl\Table::ACTION_READ)) {
            $this->coreOnlyTeam->apply($queryBuilder);
            return;
        }

        $subQuery = QueryBuilder::create()
            ->select(Attribute::ID)
            ->from(Email::ENTITY_TYPE)
            ->leftJoin(Team::RELATIONSHIP_ENTITY_TEAM, 'entityTeam', [
                'entityTeam.entityId:' => Attribute::ID,
                'entityTeam.entityType' => Email::ENTITY_TYPE,
                'entityTeam.' . Attribute::DELETED => false
            ]);

        $this->joinHelper->joinEmailUser($subQuery, $this->user->getId());

        $subQuery
            ->where([
                'OR' => [
                    'entityTeam.teamId' => $this->user->getTeamIdList(),
                    Email::ALIAS_INBOX . '.userId' => $this->user->getId(),
                ]
            ])
            ->where(
                Condition::or(
                    Condition::in(
                        Condition::column('entityTeam.teamId'),
                        $this->user->getTeamIdList()
                    ),
                    Condition::equal(
                        Condition::column(Email::ALIAS_INBOX . '.userId'),
                        $this->user->getId()
                    ),
                    Condition::and(
                        Condition::equal(
                            Condition::column(Field::PARENT . 'Type'),
                            Account::ENTITY_TYPE
                        ),
                        Condition::in(
                            Condition::column(Field::PARENT . 'Id'),
                            $this->selectBuilderFactory
                                ->create()
                                ->forUser($this->user)
                                ->from(Account::ENTITY_TYPE)
                                ->withAccessControlFilter()
                                ->buildQueryBuilder()
                                ->select([Attribute::ID])
                                ->build()
                        )
                    )
                )
            );

        $queryBuilder->where(
            Condition::in(
                Condition::column(Attribute::ID),
                $subQuery->build()
            )
        );
    }
}
