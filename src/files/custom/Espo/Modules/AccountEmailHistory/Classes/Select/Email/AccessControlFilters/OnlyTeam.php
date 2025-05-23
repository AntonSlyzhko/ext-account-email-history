<?php

namespace Espo\Modules\AccountEmailHistory\Classes\Select\Email\AccessControlFilters;

use Espo\Classes\Select\Email\AccessControlFilters\OnlyTeam as CoreOnlyTeam;
use Espo\Classes\Select\Email\Helpers\JoinHelper;
use Espo\Core\Acl;
use Espo\Core\Select\AccessControl\Filter;
use Espo\Core\Select\SelectBuilderFactory;
use Espo\Entities\Email;
use Espo\Entities\Team;
use Espo\Entities\User;
use Espo\Modules\Crm\Entities\Account;
use Espo\ORM\Name\Attribute;
use Espo\ORM\Query\Part\Condition as Cond;
use Espo\ORM\Query\Part\Join;
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

        $entityTeam = 'entityTeam';
        $subQuery = QueryBuilder::create()
            ->select(Attribute::ID)
            ->from(Email::ENTITY_TYPE)
            ->leftJoin(
                Join::create(Team::RELATIONSHIP_ENTITY_TEAM, 'entityTeam')
                    ->withConditions(
                        Cond::and(
                            Cond::equal(
                                Cond::column("$entityTeam.entityId"),
                                Cond::column(Attribute::ID)
                            ),
                            Cond::equal(
                                Cond::column("$entityTeam.entityType"),
                                Email::ENTITY_TYPE
                            ),
                            Cond::equal(
                                Cond::column("$entityTeam." . Attribute::DELETED),
                                false
                            )
                        )
                    )
            );

        $this->joinHelper->joinEmailUser($subQuery, $this->user->getId());

        $subQuery
            ->where(
                Cond::or(
                    Cond::in(
                        Cond::column("$entityTeam.teamId"),
                        $this->user->getTeamIdList()
                    ),
                    Cond::equal(
                        Cond::column(Email::ALIAS_INBOX . '.userId'),
                        $this->user->getId()
                    ),
                    Cond::in(
                        Cond::column('accountId'),
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
            );

        $queryBuilder->where(
            Cond::in(
                Cond::column(Attribute::ID),
                $subQuery->build()
            )
        );
    }
}
