<?php
/*
 * Copyright © 2022 Studio Raz. All rights reserved.
 * See LICENCE file for license details.
 */

namespace SR\Gateway\Api;

use Magento\Framework\Phrase;

/**
 * Interface LoggerInterface
 * @package SR\Gateway\Api
 */
interface LoggerInterface
{
    /**
     * Logs information (data-set) which is used for debug (file-based)
     *
     * @param array|string|Phrase $data
     * @param array|null $maskKeys
     * @param bool|null $forceDebug
     *
     * @return void
     */
    public function debug($data, ?array $maskKeys = null, ?bool $forceDebug = null): void;

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function critical(string $message, array $context = []): void;

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function info(string $message, array $context = []): void;

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function error(string $message, array $context = []): void;

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function warning(string $message, array $context = []): void;

    /**
     * Normal but significant events.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function notice(string $message, array $context = []): void;
}
