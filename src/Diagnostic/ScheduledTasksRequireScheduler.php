<?php
declare(strict_types=1);

namespace Crustum\Audit\Diagnostic;

use Crustum\Audit\Diagnostic;
use Crustum\Audit\Result\DiagnosticResult;
use Crustum\Audit\Result\Link;
use Crustum\Audit\Result\Message;
use Override;

/**
 * Notices when scheduled tasks are registered and need a worker/cron.
 */
final class ScheduledTasksRequireScheduler extends Diagnostic
{
    public string $name = 'Scheduled tasks registered';

    public string $group = 'scheduler';

    /**
     * Constructor.
     *
     * @param object|null $schedule Schedule API exposing events().
     */
    public function __construct(private readonly ?object $schedule = null)
    {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected function messages(): array
    {
        return [
            'unavailable' => 'crustum/cakephp-scheduling is not installed.',
            'no-tasks' => 'The application does not have scheduled tasks.',
            'tasks-registered' => Message::make(
                'The application has scheduled tasks.',
                'Run `bin/cake schedule run` from cron every minute in production, or `bin/cake schedule work` locally.',
            )->link(Link::docs('plugins', null, 'Scheduling documentation')),
        ];
    }

    /**
     * @inheritDoc
     */
    public function check(): DiagnosticResult
    {
        $schedule = $this->scheduleApi();
        if ($schedule === null) {
            return $this->skip('unavailable');
        }

        $events = method_exists($schedule, 'events') ? $schedule->events() : [];

        return $events === []
            ? $this->pass('no-tasks')
            : $this->notice('tasks-registered');
    }

    /**
     * Resolve the scheduling API.
     *
     * @return object|null
     */
    private function scheduleApi(): ?object
    {
        if ($this->schedule !== null) {
            return $this->schedule;
        }

        $class = 'Crustum\\Scheduling\\Schedule';

        return class_exists($class) ? new $class() : null;
    }
}
