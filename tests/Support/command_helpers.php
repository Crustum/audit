<?php
declare(strict_types=1);

use Cake\Console\Arguments;
use Cake\Console\BaseCommand;
use Cake\Console\ConsoleIoInterface;

if (!function_exists('executeCommand')) {
    /**
     * Execute a command with the given arguments and IO (Cake 6 style).
     *
     * @param \Cake\Console\BaseCommand $command Command instance.
     * @param \Cake\Console\Arguments $args Console arguments.
     * @param \Cake\Console\ConsoleIoInterface $io Console IO.
     * @return int|null
     */
    function executeCommand(BaseCommand $command, Arguments $args, ConsoleIoInterface $io): ?int
    {
        $command->setIo($io);
        $property = new ReflectionProperty($command, 'args');
        $property->setValue($command, $args);

        return $command->execute();
    }
}
