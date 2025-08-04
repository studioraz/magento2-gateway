<?php
/*
 * Copyright © 2025 Studio Raz. All rights reserved.
 * See LICENCE file for license details.
 */

namespace SR\Gateway\Model\Logger\Handler;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use SR\Gateway\Api\Config\ConfigInterface;
use SR\Gateway\Model\Config\Config;
use Symfony\Component\Console\Output\ConsoleOutput;

class CliHandler extends AbstractProcessingHandler
{
    private ConfigInterface $config;
    private ConsoleOutput $consoleOutput;

    /**
     * @param ConfigInterface $config
     * @param ConsoleOutput $consoleOutput
     * @param int $level
     * @param bool $bubble
     */
    public function __construct(
        ConfigInterface $config,
        ConsoleOutput $consoleOutput,
        int $level = Logger::DEBUG,
        bool $bubble = true
    ) {
        $this->config = $config;
        $this->consoleOutput = $consoleOutput;

        parent::__construct($level, $bubble);
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

        // NOTE: check if PHP-CLI is being used
        return php_sapi_name() === "cli";
    }

    /**
     * @inheritDoc
     */
    protected function write($record): void
    {
        if (is_array($record)) {
            $formatted = $record['formatted'] ?? '';
        } elseif ($record instanceof \Monolog\LogRecord) {
            $formatted = $record->formatted ?? '';
        } else {
            throw new \InvalidArgumentException('Invalid record type');
        }
        $this->consoleOutput->writeln($formatted);
    }
}
