<?php
/*
 * Copyright © 2022 Studio Raz. All rights reserved.
 * See LICENCE file for license details.
 */

namespace SR\Gateway\Model\Logger\Handler;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Monolog\Handler\AbstractProcessingHandler;
use SR\Gateway\Api\Config\ConfigInterface;
use SR\Gateway\Model\Config\Config;

class DbHandler extends AbstractProcessingHandler
{
    /**
     * It is used to determine whether Log data is stored into DB-Table. Flag.
     */
    public const DB_LOG_HANDLER_FLAG = 'is_db_log';

    private ConfigInterface $config;
    private ResourceConnection $resource;

    /**
     * @param ConfigInterface $config
     * @param ResourceConnection $resource
     * @param int $level
     * @param bool $bubble
     */
    public function __construct(
        ConfigInterface $config,
        ResourceConnection $resource,
        int $level = \Monolog\Logger::DEBUG,
        bool $bubble = true
    ) {
        $this->config = $config;

        parent::__construct($level, $bubble);

        $this->resource = $resource;
    }

    /**
     * @inheritDoc
     */
    public function isHandling($record): bool
    {
        // NOTE: check if the Module is active
        if (!$this->config->getValue(Config::KEY_CONFIG_ACTIVE, Config::GROUP_PATH_GENERAL)) {
            return false;
        }

        // NOTE: check if Level is applicable (default condition)
        if (!parent::isHandling($record)) {
            return false;
        }

        // NOTE: check if DB-Log functionality is active
        if (!$this->config->getValue(Config::KEY_CONFIG_ACTIVE, Config::GROUP_PATH_LOG)) {
            return false;
        }

        // NOTE: check if necessary flag is set in TRUE
        return (bool)($record['context'][self::DB_LOG_HANDLER_FLAG] ?? null);
    }

    /**
     * @inheritDoc
     */
    protected function write($record): void
    {
        // TODO: Implement write() method.
        //     just example was added (how it can be used)

        // TODO: implement Injection to provide the functionality with needed Objects

        $context = $record['context'];

        /** @var \DateTime $datetime */
        $datetime = $record['datetime'] ?? new \DateTime('now');

        // FIXME: Currently, the light solution was implemented for quick Insert operation.

//        $dbLogData = [
//            LogInterface::TYPE_ID => $context[LogInterface::TYPE_ID] ?? null,
//            LogInterface::ACTION_ID => $context[LogInterface::ACTION_ID] ?? null,
//            LogInterface::REQUEST_TEXT => $context[LogInterface::REQUEST_TEXT] ?? null,
//            LogInterface::RESPONSE_TEXT => $context[LogInterface::RESPONSE_TEXT] ?? null,
//            LogInterface::CALLED_AT => $datetime->format('Y-m-d H:i:s'),
//        ];

        try {
//            /** @var AdapterInterface $connection */
//            $connection = $this->resource->getConnection(ResourceConnection::DEFAULT_CONNECTION);
//
//            $connection->insertOnDuplicate(
//                $this->resource->getTableName(Log::ENTITY_DB_TABLE),
//                $dbLogData,
//                array_keys($dbLogData)
//            );
        } catch (\Exception $e) {
            // TODO: implement logic to handle the Exception
        }
    }
}
