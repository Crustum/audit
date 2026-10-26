<?php
declare(strict_types=1);

use Crustum\Audit\Diagnostic\ScheduledTasksRequireScheduler;
use Crustum\Audit\Result\Status;

it('notices registered scheduled tasks and skips when scheduling is unavailable', function (): void {
    expect(new ScheduledTasksRequireScheduler()->check()->status)->toBe(Status::Skip);

    $schedule = new class {
        /**
         * @return list<object>
         */
        public function events(): array
        {
            return [new stdClass()];
        }
    };

    expect(new ScheduledTasksRequireScheduler($schedule)->check()->status)->toBe(Status::Notice);
});

it('passes scheduling when no events are registered', function (): void {
    $schedule = new class {
        /**
         * @return list<object>
         */
        public function events(): array
        {
            return [];
        }
    };

    expect(new ScheduledTasksRequireScheduler($schedule)->check()->status)->toBe(Status::Pass);
});
