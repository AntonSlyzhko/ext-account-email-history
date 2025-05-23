<?php

namespace Espo\Modules\AccountEmailHistory\Classes\Acl\Email;

use Espo\Classes\Acl\Email\AccessChecker as CoreAccessChecker;
use Espo\Core\Acl\AccessEntityCREDSChecker;
use Espo\Core\Acl\DefaultAccessChecker;
use Espo\Core\Acl\ScopeData;
use Espo\Core\Acl\Table;
use Espo\Core\AclManager;
use Espo\Entities\Email;
use Espo\Entities\User;
use Espo\ORM\Entity;

/**
 * @implements AccessEntityCREDSChecker<Email>
 * @noinspection PhpUnused
 */
class AccessChecker extends CoreAccessChecker
{
    public function __construct(
        private AclManager $aclManager,
        DefaultAccessChecker $defaultAccessChecker
    ) {
        parent::__construct($defaultAccessChecker);
    }

    /**
     * @inheritdoc
     */
    public function checkEntityRead(User $user, Entity $entity, ScopeData $data): bool
    {
        if (parent::checkEntityRead($user, $entity, $data)) {
            return true;
        }

        if ($data->getRead() === Table::LEVEL_NO) {
            return false;
        }

        $account = $entity->getAccount();
        if (is_null($account)) {
            return false;
        }

        return $this->aclManager->checkEntityRead($user, $account);
    }
}
