<?php
/*
 * Copyright © 2022 Studio Raz. All rights reserved.
 * See LICENCE file for license details.
 */

namespace SR\Gateway\Model;

use SR\Gateway\Api\Config\ConfigInterface;
use SR\Gateway\Api\ModuleStateInterface;

class ModuleState implements ModuleStateInterface
{
    protected ConfigInterface $config;
    private ?bool $forceActive;

    /**
     * @param ConfigInterface $config
     * @param bool|null $forceActive
     */
    public function __construct(ConfigInterface $config, ?bool $forceActive = null)
    {
        $this->config = $config;
        $this->forceActive = $forceActive;
    }

    /**
     * @inheritDoc
     */
    public function isActive($store = null): bool
    {
        if ($this->forceActive !== null) {
            return (bool)$this->forceActive;
        }

        return (bool)$this->config->getActive($store);
    }
}
