<?php

namespace Tests\Support;

use PDO;
use RuntimeException;

class TestingDatabaseBootstrap
{
    /**
     * Recreate the isolated testing database from scratch.
     *
     * The test suite shares a single MySQL service, so an aborted migration can
     * leave behind stale DDL sessions that block the next bootstrap. We kill
     * only sessions attached to the testing database, then drop and recreate the
     * schema so every new PHPUnit process starts from a clean slate.
     */
    public function reset(string $host, string $port, string $database, string $username, string $password): void
    {
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%s', $host, $port),
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );

        $currentConnectionId = (int) $pdo->query('SELECT CONNECTION_ID()')->fetchColumn();

        foreach ($pdo->query('SHOW PROCESSLIST')->fetchAll() as $process) {
            $processId = (int) ($process['Id'] ?? 0);
            $processDatabase = (string) ($process['db'] ?? '');

            if ($processId <= 0 || $processId === $currentConnectionId || $processDatabase !== $database) {
                continue;
            }

            // Only terminate sessions that are attached to the isolated testing
            // database so a stale aborted run cannot block the next bootstrap.
            $pdo->exec('KILL '.$processId);
        }

        $quotedDatabase = '`'.str_replace('`', '``', $database).'`';

        $pdo->exec('DROP DATABASE IF EXISTS '.$quotedDatabase);
        $pdo->exec('CREATE DATABASE '.$quotedDatabase.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }
}
