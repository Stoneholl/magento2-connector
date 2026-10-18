<?php

declare(strict_types=1);

namespace Stoneholl\Connector\Model;

use Magento\Framework\Model\AbstractModel;
use Stoneholl\Connector\Model\ResourceModel\State as StateResource;

class State extends AbstractModel
{
    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init(StateResource::class);
    }
}
