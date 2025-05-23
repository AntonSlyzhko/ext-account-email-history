<?php

namespace Espo\Modules\AccountEmailHistory\Classes\Select\Email\AccessControlFilters;

use Espo\Classes\Select\Email\AccessControlFilters\OnlyOwn as CoreOnlyOwn;
use Espo\Classes\Select\Email\Helpers\JoinHelper;
use Espo\Core\Acl;
use Espo\Core\Name\Field;
use Espo\Core\Select\AccessControl\Filter;
use Espo\Core\Select\SelectBuilderFactory;
use Espo\Entities\Email;
use Espo\Entities\User;
use Espo\Modules\Crm\Entities\Account;
use Espo\ORM\Name\Attribute;
use Espo\ORM\Query\Part\Condition as Cond;
use Espo\ORM\Query\SelectBuilder as QueryBuilder;

/**
 * @noinspection PhpUnused
 */
class OnlyOwn implements Filter
{
    public function __construct(
        private User $user,
        private Acl $acl,
        private JoinHelper $joinHelper,
        private SelectBuilderFactory $selectBuilderFactory,
        private CoreOnlyOwn $coreOnlyOwn
    ) {}

    public function apply(QueryBuilder $queryBuilder): void
    {
        if (!$this->acl->checkScope(Account::ENTITY_TYPE, Acl\Table::ACTION_READ)) {
            $this->coreOnlyOwn->apply($queryBuilder);
            return;
        }

        $this->joinHelper->joinEmailUser($queryBuilder, $this->user->getId());

        $queryBuilder->where(
            Cond::or(
                Cond::equal(
                    Cond::column(Email::ALIAS_INBOX . '.userId'),
                    $this->user->getId()
                ),
                Cond::and(
                    Cond::equal(
                        Cond::column(Field::PARENT . 'Type'),
                        Account::ENTITY_TYPE
                    ),
                    Cond::in(
                        Cond::column(Field::PARENT . 'Id'),
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
    }
}
