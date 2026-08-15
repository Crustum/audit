<?php
declare(strict_types=1);

namespace Crustum\Audit\Diagnostic;

use Cake\Datasource\ConnectionManager;
use Crustum\Audit\Contract\FixableInterface;
use Crustum\Audit\Diagnostic;
use Crustum\Audit\Result\DiagnosticResult;
use Crustum\Audit\Result\FixResult;
use Crustum\Audit\Result\Link;
use Crustum\Audit\Result\Message;
use Override;

/**
 * Verifies a file-backed SQLite database exists.
 */
final class SqliteDatabaseExists extends Diagnostic implements FixableInterface
{
    public string $name = 'SQLite database exists';

    public string $group = 'database';

    /**
     * Constructor.
     *
     * @param string $connection Connection name.
     * @param string|null $basePath Audited application path.
     */
    public function __construct(
        private readonly string $connection = 'default',
        private readonly ?string $basePath = null,
    ) {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected function messages(): array
    {
        return [
            'not-sqlite' => 'The database connection is not SQLite.',
            'not-file' => 'The SQLite connection does not use a database file.',
            'exists' => 'The SQLite database file exists.',
            'missing' => Message::make(
                'The SQLite database file does not exist.',
                'Create the SQLite database file at {database}.',
                'Create the SQLite database file?',
            )->link(Link::docs('orm/database-basics', 'sqlite-configuration')),
            'already-exists' => 'The SQLite database file already exists.',
            'creation-failed' => 'The SQLite database file could not be created.',
            'created' => 'The SQLite database file was created.',
        ];
    }

    /**
     * @inheritDoc
     */
    public function check(): DiagnosticResult
    {
        $config = ConnectionManager::getConfig($this->connection);
        $driver = is_array($config) ? ($config['driver'] ?? null) : null;
        if (!is_string($driver) || !str_contains(strtolower($driver), 'sqlite')) {
            return $this->skip('not-sqlite');
        }

        $database = $config['database'] ?? null;
        if (!is_string($database) || $database === '' || $database === ':memory:') {
            return $this->skip('not-file');
        }

        if (!$this->isAbsolute($database)) {
            $database = ($this->basePath ?? ROOT) . DS . $database;
        }

        $database = str_replace(['/', '\\'], DS, $database);

        return is_file($database)
            ? $this->pass('exists')->withContext('database', $database)
            : $this->fail('missing', ['database' => $database])
                ->fixable()
                ->withContext('database', $database);
    }

    /**
     * @inheritDoc
     */
    public function fix(DiagnosticResult $result, ?string $option = null): FixResult
    {
        $database = $result->context['database'] ?? null;
        if (!is_string($database)) {
            return $this->fixFailed('creation-failed');
        }

        if (is_file($database)) {
            return $this->fixed('already-exists');
        }

        if (!is_dir(dirname($database))) {
            mkdir(dirname($database), 0775, true);
        }

        if (!is_dir(dirname($database))) {
            return $this->fixFailed('creation-failed');
        }

        return file_put_contents($database, '') !== false
            ? $this->fixed('created')
            : $this->fixFailed('creation-failed');
    }

    /**
     * Determine whether a path is absolute.
     *
     * @param string $path Filesystem path.
     * @return bool
     */
    private function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }
}
