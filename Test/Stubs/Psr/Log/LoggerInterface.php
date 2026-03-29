<?php
declare(strict_types=1);

namespace Psr\Log;

interface LoggerInterface
{
    /**
     * @param mixed $message
     * @param array<mixed> $context
     */
    public function emergency($message, array $context = []): void;

    /**
     * @param mixed $message
     * @param array<mixed> $context
     */
    public function alert($message, array $context = []): void;

    /**
     * @param mixed $message
     * @param array<mixed> $context
     */
    public function critical($message, array $context = []): void;

    /**
     * @param mixed $message
     * @param array<mixed> $context
     */
    public function error($message, array $context = []): void;

    /**
     * @param mixed $message
     * @param array<mixed> $context
     */
    public function warning($message, array $context = []): void;

    /**
     * @param mixed $message
     * @param array<mixed> $context
     */
    public function notice($message, array $context = []): void;

    /**
     * @param mixed $message
     * @param array<mixed> $context
     */
    public function info($message, array $context = []): void;

    /**
     * @param mixed $message
     * @param array<mixed> $context
     */
    public function debug($message, array $context = []): void;

    /**
     * @param mixed $level
     * @param mixed $message
     * @param array<mixed> $context
     */
    public function log($level, $message, array $context = []): void;
}
