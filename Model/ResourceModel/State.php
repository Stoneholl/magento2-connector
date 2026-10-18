<?php

declare(strict_types=1);

namespace Stoneholl\Connector\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class State extends AbstractDb
{
    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init('stoneholl_connector_state', 'state_id');
    }
}
