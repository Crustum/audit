<?php
declare(strict_types=1);

use Crustum\Audit\Test\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Integration', 'Unit');

require __DIR__ . '/Support/manager_helpers.php';
require __DIR__ . '/Support/diagnostic_helpers.php';
require __DIR__ . '/Support/command_helpers.php';
