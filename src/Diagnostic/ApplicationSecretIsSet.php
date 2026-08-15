<?php
declare(strict_types=1);

namespace Crustum\Audit\Diagnostic;

use Cake\Core\Configure;
use Cake\Utility\Security;
use Crustum\Audit\Contract\FixableInterface;
use Crustum\Audit\Diagnostic;
use Crustum\Audit\EnvironmentMode;
use Crustum\Audit\Result\DiagnosticResult;
use Crustum\Audit\Result\FixResult;
use Crustum\Audit\Result\Link;
use Crustum\Audit\Result\Message;
use Crustum\Audit\Support\EnvironmentFile;
use Override;
use Throwable;

/**
 * Verifies CakePHP's Security.salt / SECURITY_SALT is configured.
 *
 * `Configure::consume('Security.salt')` removes the Configure value during
 * bootstrap, so this diagnostic prefers `env('SECURITY_SALT')` and
 * `Security::getSalt()`. When `.env` defines the salt but the process has no
 * env vars, it checks whether the josegonzalez Dotenv loader in bootstrap is
 * commented out.
 */
final class ApplicationSecretIsSet extends Diagnostic implements FixableInterface
{
    public string $name = 'Application secret is set';

    public string $group = 'environment';

    /**
     * Constructor.
     *
     * @param string|null $basePath Audited environment/config directory.
     */
    public function __construct(private readonly ?string $basePath = null)
    {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected function messages(): array
    {
        $link = Link::docs('development/configuration', 'security-salt');

        return [
            'configured' => 'The application secret is configured.',
            'missing' => Message::make(
                'The application secret is not configured.',
                'Set SECURITY_SALT in config/.env to a unique deployment secret.',
                'Generate a Security.salt value in config/.env?',
            )->link($link),
            'dotenv-disabled' => Message::make(
                'SECURITY_SALT is defined in config/.env but environment variables are not loaded.',
                "Uncomment the josegonzalez Dotenv loader block in config/bootstrap.php so CONFIG . '.env' is parsed into the environment.",
            )->link(Link::docs('development/configuration', 'environment-variables')),
            'generated' => 'The application secret was generated.',
            'generation-failed' => 'The application secret could not be generated.',
        ];
    }

    /**
     * @inheritDoc
     */
    public function check(): DiagnosticResult
    {
        if ($this->saltIsConfigured()) {
            return $this->pass('configured');
        }

        if ($this->envFileDefinesSalt() && !$this->dotenvLoaderIsEnabled()) {
            return $this->fail('dotenv-disabled');
        }

        return $this->fail('missing')->fixable(EnvironmentMode::Local);
    }

    /**
     * @inheritDoc
     */
    public function fix(DiagnosticResult $result, ?string $option = null): FixResult
    {
        $path = EnvironmentFile::path('.env', $this->basePath);
        if (!is_file($path) || !is_writable($path)) {
            return $this->fixFailed('generation-failed')
                ->withDetails(sprintf('The environment file [%s] could not be updated.', $path));
        }

        $salt = bin2hex(random_bytes(32));
        if (!$this->writeVariable($path, 'SECURITY_SALT', $salt)) {
            return $this->fixFailed('generation-failed');
        }

        putenv('SECURITY_SALT=' . $salt);
        $_ENV['SECURITY_SALT'] = $salt;
        $_SERVER['SECURITY_SALT'] = $salt;
        Configure::write('Security.salt', $salt);
        Security::setSalt($salt);

        return $this->fixed('generated');
    }

    /**
     * Determine whether a usable salt is available at runtime.
     *
     * @return bool
     */
    private function saltIsConfigured(): bool
    {
        foreach ([env('SECURITY_SALT'), Configure::read('Security.salt')] as $value) {
            if (is_string($value) && trim($value) !== '') {
                return true;
            }
        }

        try {
            return trim(Security::getSalt()) !== '';
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Determine whether config/.env declares a non-empty SECURITY_SALT.
     *
     * @return bool
     */
    private function envFileDefinesSalt(): bool
    {
        $path = EnvironmentFile::path('.env', $this->basePath);
        if (!is_file($path)) {
            return false;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return false;
        }

        return preg_match('/^\s*SECURITY_SALT\s*=\s*\S+/m', $contents) === 1;
    }

    /**
     * Determine whether bootstrap loads CONFIG/.env through josegonzalez Dotenv.
     *
     * @return bool
     */
    private function dotenvLoaderIsEnabled(): bool
    {
        $path = EnvironmentFile::directory($this->basePath) . DS . 'bootstrap.php';
        if (!is_file($path)) {
            return true;
        }

        $code = $this->phpWithoutComments((string)file_get_contents($path));

        return str_contains($code, 'josegonzalez\Dotenv\Loader');
    }

    /**
     * Strip PHP comments so commented-out Dotenv blocks are ignored.
     *
     * @param string $code PHP source.
     * @return string
     */
    private function phpWithoutComments(string $code): string
    {
        $output = '';
        foreach (token_get_all($code) as $token) {
            if (is_array($token) && ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT)) {
                continue;
            }

            $output .= is_array($token) ? $token[1] : $token;
        }

        return $output;
    }

    /**
     * Write an environment variable.
     *
     * @param string $path Environment file.
     * @param string $name Variable name.
     * @param string $value Variable value.
     * @return bool
     */
    private function writeVariable(string $path, string $name, string $value): bool
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            return false;
        }

        $line = $name . '=' . $value;
        $updated = preg_match('/^' . preg_quote($name, '/') . '=/m', $contents) === 1
            ? preg_replace('/^' . preg_quote($name, '/') . '=.*$/m', $line, $contents)
            : $contents . ($contents !== '' && !str_ends_with($contents, PHP_EOL) ? PHP_EOL : '') . $line . PHP_EOL;

        return is_string($updated) && file_put_contents($path, $updated) !== false;
    }
}
