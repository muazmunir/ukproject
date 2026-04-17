<?php

namespace App\Console\Concerns;

use Symfony\Component\Process\Process;

trait FindsMysqlClient
{
    protected function findMysqlBinary(string $explicit): ?string
    {
        if ($explicit !== '') {
            return is_file($explicit) ? $explicit : null;
        }

        $finder = new Process(['where', 'mysql']);
        $finder->run();
        if ($finder->isSuccessful()) {
            $line = strtok(trim($finder->getOutput()), "\r\n");
            if (is_string($line) && $line !== '' && is_file($line)) {
                return $line;
            }
        }

        $which = new Process(['which', 'mysql']);
        $which->run();
        if ($which->isSuccessful()) {
            $line = trim($which->getOutput());
            if ($line !== '' && is_file($line)) {
                return $line;
            }
        }

        foreach (glob('C:\\laragon\\bin\\mysql\\mysql-*\\bin\\mysql.exe') ?: [] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
