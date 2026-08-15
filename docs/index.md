# CakePHP Audit Plugin

- [Introduction](#introduction)
- [Installation](#installation)
- [Running Audit](#running-audit)
- [Diagnostic Statuses](#diagnostic-statuses)
    - [Bailing on Failure](#bailing-on-failure)
- [Selecting Diagnostics](#selecting-diagnostics)
- [Configuring Audit](#configuring-audit)
- [Environment Modes](#environment-modes)
- [Default Diagnostics](#default-diagnostics)
- [Creating Diagnostics](#creating-diagnostics)
    - [Diagnostic Helpers](#diagnostic-helpers)
        - [Configuration Values](#configuration-values)
        - [Active Drivers](#active-drivers)
        - [Result Details](#result-details)
- [Registering Diagnostics](#registering-diagnostics)
- [Running Audit Programmatically](#running-audit-programmatically)
- [Output Formats](#output-formats)
    - [Agent-Optimized Output](#agent-optimized-output)

<a name="introduction"></a>
## Introduction

The **Audit** plugin helps you diagnose common configuration, environment, and infrastructure problems in your CakePHP application before they reach your users.

At its core, Audit is built around a simple idea: one diagnostic, one check. Each diagnostic inspects a single thing — such as whether CakePHP can write to its runtime directories — and reports one of several statuses. When the repair is safe and deterministic, a diagnostic can even fix the problem for you. Issues that can't be repaired safely, such as a failed Composer audit, are reported with clear remediation steps instead.

For example, imagine you ship a new release and debug mode is accidentally left enabled on a production server, or your application can no longer write to its cache directory. Running Audit walks your application, spots exactly what needs attention, and — when the repair is safe — offers to fix it for you.

<a name="installation"></a>
## Installation

To get started with Audit, you'll first need to install the plugin via Composer:

```bash
composer require crustum/audit
```

> [!NOTE]
> This plugin should be registered in your `config/plugins.php` file.

```bash
bin/cake plugin load Crustum/Audit
```

> [!TIP]
> **After the plugin registers itself**, it's recommended to install the configuration with the manifest system:

```bash
bin/cake manifest install --plugin Crustum/Audit
```

Installing the configuration creates the `config/audit.php` file, where you may configure diagnostic selection and environment modes, and appends the loading of that file to your application's `config/bootstrap.php` file.

Alternatively, you can load the plugin directly from your `Application.php`:

```php
// In src/Application.php
public function bootstrap(): void
{
    parent::bootstrap();

    $this->addPlugin('Crustum/Audit');
}
```

This will enable Audit by configuring the plugin and registering its default diagnostics.

<a name="running-audit"></a>
## Running Audit

Once the plugin is installed, a new `audit process` console command becomes available:

```bash
bin/cake audit process
```

When a failing diagnostic can be repaired, Audit reports the problem and asks for confirmation before applying the fix:

```text
Storage is writable: The application cannot write to every required runtime directory.

 Create missing runtime directories and make them writable? (yes/no) [yes]
```

Some repairs are better left to a human. When a failure has several valid repairs, Audit presents a select list of the options that actually work, along with an entry to leave the current state untouched. Declining the fix keeps the failure in the report with its remediation text.

To run every available fix without being prompted, pass the `--fix` option:

```bash
bin/cake audit process --fix
```

First-party fixes cover deterministic local repairs, such as creating a missing `config/.env`, generating `SECURITY_SALT`, disabling production debug mode, adding `config/.env` to `.gitignore`, and repairing writable runtime directories. Because `--fix` never prompts, it applies every fix that doesn't require a choice; fixes that need a human decision are reported as ordinary failures with their remediation text.

> [!NOTE]
> Fixes are only available with the CLI and agent [output formats](#output-formats). With agent output, `--fix` applies every available deterministic fix and reports the outcomes in the payload's `fixes` array. Audit rejects `--fix` with the JSON and GitHub report formats so machine-readable reports never modify your application.

<a name="diagnostic-statuses"></a>
## Diagnostic Statuses

Every diagnostic ends with one of the following statuses. Audit uses them to render its output and to decide the command's exit code:

| Status   | Meaning                                                 | Affects exit code          |
| -------- | ------------------------------------------------------- | -------------------------- |
| `pass`   | The check succeeded and nothing is wrong.               | No                         |
| `notice` | Informational context worth surfacing to the developer. | No                         |
| `warn`   | A potential problem that may not require action.        | Only with `--fail-on=warn` |
| `fail`   | The check found a problem that should be resolved.      | Yes                        |
| `skip`   | The check did not apply to the current environment.     | No                         |
| `error`  | The diagnostic threw an exception while running.        | Yes                        |

By default, the command exits with a failing status when a diagnostic fails or errors. If you'd like warnings to be treated as failures too, use `--fail-on=warn`. When Audit should only report issues, use `--fail-on=never`.

<a name="bailing-on-failure"></a>
### Bailing on Failure

During a long run, you may prefer to stop at the first sign of trouble. The `--bail` option stops the run after the first diagnostic that fails or errors. Warnings, notices, passes, and skips never stop the run:

```bash
bin/cake audit process --bail
```

The flag works with every output format and may be combined with diagnostic selectors. Programmatic runs may enable the same behavior with `AuditManager::bail()->run()`.

<a name="selecting-diagnostics"></a>
## Selecting Diagnostics

Sometimes you only want to audit part of your application. Diagnostics may be selected or excluded by class name, group, package, or package wildcard. Multiple values may be passed either by repeating the option or by separating values with commas:

```bash
bin/cake audit process --only=storage

bin/cake audit process --only=StorageIsWritable

bin/cake audit process --only=crustum/audit

bin/cake audit process --except=crustum/*

bin/cake audit process --except=security
```

<a name="configuring-audit"></a>
## Configuring Audit

If you'd like the selection to persist between runs, you can configure it in `config/audit.php`. The `only` and `except` options accept the same selectors as the command line: diagnostic class names, groups, packages, or package wildcards.

```php
'only' => [
    // 'crustum/audit',
    // 'vendor/*',
    // 'security',
],

'except' => [
    // \Crustum\Audit\Diagnostic\EnvironmentFileIsGitIgnored::class,
],
```

Configured `only` selectors act as a persistent allowlist. Passing `--only` at runtime narrows that allowlist further, while configured `except` selectors and `--except` are combined.

The file also accepts a `requiredConfiguration` map of `Configure` paths to human labels that the `RequiredConfigurationValuesAreSet` diagnostic checks. This lets your application declare configuration values that must be present:

```php
'requiredConfiguration' => [
    'Services.stripe.key' => 'Stripe publishable key',
],
```

<a name="environment-modes"></a>
## Environment Modes

Some diagnostics can't be judged in isolation. A synchronous queue transport is a sensible default on a developer's machine, but in production it means queued jobs silently run inside web requests. Debug mode is essential locally and a security problem on a public server.

To judge these diagnostics, Audit resolves your application to one of two modes:

| Mode         | Expectations                                                                                                                             |
| ------------ | ---------------------------------------------------------------------------------------------------------------------------------------- |
| `local`      | The application is under active development. Debug mode and synchronous queue transports are normal.                                     |
| `production` | The application serves real traffic. Debug mode is a security risk and queues should run asynchronously.                                  |

The mode changes the verdict, not just the message. A synchronous queue transport passes on a developer's machine but warns in production, while debug mode fails in production and passes locally. When the queue runs asynchronously in local development, Audit also surfaces a notice reminding you to start a queue worker.

Audit resolves the current application environment name from the `Audit.environment` configuration value, defaulting to `production` when it is not set, and maps that name to a mode through the `Audit.environments` map. If your application uses other environment names, group each one under the appropriate Audit mode in the configuration file:

```php
'environment' => 'local',
'environments' => [
    'local' => ['local', 'development', 'dev', 'test', 'testing'],
    'production' => ['production', 'staging', 'prod'],
],
```

Any environment not listed is treated as `production`, so unfamiliar environments are held to the strictest expectations rather than quietly excused. An unsupported mode name, an environment list that is not an array, or an environment assigned to both modes throws an exception.

Your custom diagnostics can branch on the current mode too. Here, a synchronous queue transport passes on a developer's machine but warns in production:

```php
use Crustum\Audit\EnvironmentMode;

public function check(): DiagnosticResult
{
    if (!$this->queueIsSynchronous()) {
        return $this->pass('async');
    }

    if (EnvironmentMode::current()->isProduction()) {
        return $this->warn('sync-production');
    }

    return $this->pass('sync-local');
}
```

The `pass` and `warn` methods build results from the diagnostic's named messages, which are covered in [Creating Diagnostics](#creating-diagnostics).

<a name="default-diagnostics"></a>
## Default Diagnostics

Audit ships with a focused suite of diagnostics covering common configuration, environment, dependency, database, queue, and storage problems. The default suite includes:

| Group | What the group covers |
| --- | --- |
| Environment | `.env` presence, the `SECURITY_SALT` application secret, PHP version, required and recommended PHP extensions, and timezone. |
| Composer | Composer dependencies are installed before autoload validation runs, Composer can dump a valid autoloader, `composer.lock` freshness, and repairable lock problems can be fixed automatically in local environments. |
| Configuration | Configuration files can be loaded, and host-declared required configuration values are set. |
| Database | The default connection is reachable, the SQLite database file exists when needed, and pending migrations can be applied automatically in local environments. |
| Cache, queue, scheduler, and session | Configured cache drivers are reachable, active Redis connections are checked, synchronous queue transports are flagged outside local environments, scheduled tasks are surfaced as a notice, and the session handler connects. |
| Storage | The runtime directories `tmp/`, `cache/`, and `logs/` are writable and can be repaired. |
| Security | Debug mode matches the environment, `config/.env` is ignored by git, and Composer dependencies are audited. |

A few diagnostics only register when their supporting packages are installed: `MigrationsAreUpToDate` requires `cakephp/migrations`, and the queue diagnostics require `cakephp/queue`. `ScheduledTasksRequireScheduler` is always registered, but reports a `skip` status when `crustum/cakephp-scheduling` is not installed.

<a name="creating-diagnostics"></a>
## Creating Diagnostics

Writing your own diagnostic is straightforward. Like a CakePHP console command, a diagnostic declares its definition as properties. Extend `Crustum\Audit\Diagnostic`, implement a `check` method that returns a `DiagnosticResult`, and Audit derives a name and group from the class name when you don't set them.

The `bake diagnostic` command scaffolds a new diagnostic into `src/Diagnostic/`. Pass the `--fixable` option when the diagnostic should offer a fix:

```bash
bin/cake bake diagnostic QueueWorkerIsRunning --fixable
```

To offer a fix, your diagnostic should implement the `Crustum\Audit\Contract\FixableInterface` contract and mark each repairable failure with `->fixable()`. Audit only attempts fixes for explicitly fixable `fail` results from diagnostics that implement the contract. Other failures and unexpected `error` results are never fixed automatically. Pass one or more `EnvironmentMode` values to limit where an automatic fix may run, such as `->fixable(EnvironmentMode::Local)` for a developer-machine-only repair.

The following diagnostic checks whether the application secret is set. Because CakePHP already provides a safe salt generator, it implements `FixableInterface`:

```php
<?php
declare(strict_types=1);

namespace App\Diagnostic;

use Crustum\Audit\Contract\FixableInterface;
use Crustum\Audit\Diagnostic;
use Crustum\Audit\EnvironmentMode;
use Crustum\Audit\Result\DiagnosticResult;
use Crustum\Audit\Result\FixResult;
use Crustum\Audit\Result\Link;
use Crustum\Audit\Result\Message;
use Crustum\Audit\Support\EnvironmentFile;

class ApplicationSecretIsSet extends Diagnostic implements FixableInterface
{
    public string $name = 'Application secret is set';

    public string $group = 'environment';

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

            'generated' => 'The application secret was generated.',

            'generation-failed' => 'The application secret could not be generated.',
        ];
    }

    public function check(): DiagnosticResult
    {
        if ($this->saltIsConfigured()) {
            return $this->pass('configured');
        }

        return $this->fail('missing')->fixable(EnvironmentMode::Local);
    }

    public function fix(DiagnosticResult $result, ?string $option = null): FixResult
    {
        $path = EnvironmentFile::path('.env');
        if (!is_file($path) || !is_writable($path)) {
            return $this->fixFailed('generation-failed')
                ->withDetails(sprintf('The environment file [%s] could not be updated.', $path));
        }

        $salt = bin2hex(random_bytes(32));
        // Write SECURITY_SALT to config/.env...

        return $this->fixed('generated');
    }
}
```

Diagnostics should explain what failed and how to recover. Copy lives in the `messages()` registry: a plain string is used as the result's summary, while `Message::make()` may also provide remediation text, documentation links, or a confirmation prompt for fixes.

Use `Link::docs()` when pointing at CakePHP documentation and `Link::to()` for other destinations. CakePHP documentation links point at the versioned CakePHP 5 book in human-facing output, while the agent JSON payload receives the clean page URL without the section anchor:

```php
Message::make(
    'Queued jobs run synchronously in production.',
    'Configure Queue to use a background transport such as Redis, database, or a broker URL.',
)->link(Link::docs('plugins', null, 'Queue documentation'));
```

Statuses are declared where the decision is made. Return `$this->pass()`, `$this->fail()`, `$this->warn()`, `$this->notice()`, `$this->skip()`, or `$this->error()` from `check()`, and `$this->fixed()` or `$this->fixFailed()` from `fix()`. Each result also receives a stable machine-readable code derived from the class and message names, such as `application-secret-is-set.missing`.

Summaries and remediation text may interpolate values using `{placeholder}` tokens supplied at the return site:

```php
'unsatisfied' => Message::make(
    'PHP {version} does not satisfy [{constraint}].',
    'Switch PHP versions or update the composer.json PHP constraint.',
),
```

```php
return $this->fail('unsatisfied', [
    'version' => PHP_VERSION,
    'constraint' => $constraint,
]);
```

Reserve tokens for short identifying values such as versions, paths, and counts. Attach unbounded evidence such as exception messages, process output, or lists of failures with `withDetails()` instead.

If the check gathers state the fix will need, store it with `withContext()` on the result. Mark only outcomes the fix can handle with `fixable()`, and only implement `FixableInterface` when the repair is predictable and safe. Scope a fix to `EnvironmentMode::Local` to limit fixes to a development environment.

When a failure has several valid repairs and the right one is a human decision, declare the choices with `fixOptions()` after `fixable()`. The interactive CLI renders them as a select list using the result's confirmation text as the prompt label, and the chosen option value is passed to `fix()`:

```php
return $this->fail('unreachable')
    ->fixable(EnvironmentMode::Local)
    ->fixOptions(['file' => 'File', 'redis' => 'Redis']);
```

```php
public function fix(DiagnosticResult $result, ?string $option = null): FixResult
{
    // $option is one of the declared option values...
}
```

Compute the options in `check()` and filter them to choices that will actually succeed — probe candidate services, verify client packages, and drop anything that would fail — so the select list only offers working repairs. A result with no viable options should stay non-fixable and rely on its remediation text.

Every select list ends with an entry that declines the fix, `Skip — leave unfixed` by default. Pass a `decline` label when naming the current selection reads better, such as `Keep Redis (repair it manually)` — choosing it applies nothing, so the failure renders with its remediation:

```php
->fixOptions(['file' => 'File'], decline: 'Keep Redis (repair it manually)');
```

<a name="diagnostic-helpers"></a>
### Diagnostic Helpers

Many applications and packages end up writing the same kinds of checks, so Audit provides helpers in the `Crustum\Audit\Support` namespace for the recurring mechanics. Reach for these before re-implementing the logic inside a diagnostic.

<a name="configuration-values"></a>
#### Configuration Values

The `Configured` helper reads configuration defensively. Diagnostics must be able to inspect misconfigured applications without failing before they can report, so these methods never throw on unexpected types the way CakePHP's typed configuration accessors can.

`Configured::string()` returns a configured string, treating blank or non-string values as missing:

```php
use Crustum\Audit\Support\Configured;

$config = Configured::string('Audit.queue.config', 'default');
```

`Configured::missing()` returns the given keys that do not hold a usable value. Null values, blank strings, and empty arrays are treated as missing, so a "package requires these configuration values" diagnostic reduces to:

```php
use Crustum\Audit\Support\Configured;
use Crustum\Audit\Support\Details;

public function check(): DiagnosticResult
{
    $missing = Configured::missing([
        'Services.stripe.key',
        'Services.stripe.secret',
    ]);

    if ($missing === []) {
        return $this->pass('set');
    }

    return $this->fail('missing')->withDetails(Details::bullets($missing));
}
```

<a name="active-drivers"></a>
#### Active Drivers

Default configuration values often point at wrapper drivers rather than the driver doing the work. The `ActiveDrivers` helper resolves the concrete CakePHP infrastructure actually in use, so a diagnostic only inspects drivers that matter:

```php
use Crustum\Audit\Support\ActiveDrivers;

$caches = ActiveDrivers::cacheConfigurations();
$connections = ActiveDrivers::databaseConnections();
$session = ActiveDrivers::sessionHandler();
```

<a name="result-details"></a>
#### Result Details

The `Details` helper formats the evidence attached to results with `withDetails()`. `Details::bullets()` renders a list of strings as bullets, `Details::failures()` renders keyed failure messages, and `Details::processOutput()` selects the most useful stream from a finished process, preferring error output and falling back when both streams are empty:

```php
use Crustum\Audit\Support\Details;

Details::bullets(['Services.stripe.key', 'Services.stripe.secret']);

Details::failures(['tmp/' => 'The directory does not exist.']);

Details::processOutput($process->getOutput(), $process->getErrorOutput(), 'The command produced no output.');
```

<a name="registering-diagnostics"></a>
## Registering Diagnostics

Your application can register diagnostics through the `AuditManager`, typically from a service provider:

```php
use App\Diagnostic\ApplicationSecretIsSet;
use Cake\Core\ContainerInterface;
use Crustum\Audit\AuditManager;

// In src/Application.php
public function services(ContainerInterface $container): void
{
    $container->get(AuditManager::class)->diagnostic(ApplicationSecretIsSet::class);
}
```

Packages use the same registration API from their service providers:

```php
use Crustum\Audit\AuditManager;
use Vendor\Package\Diagnostics\QueueWorkerIsRunning;

public function services(ContainerInterface $container): void
{
    $container->get(AuditManager::class)->diagnostic(QueueWorkerIsRunning::class);
}
```

Reports show each diagnostic's source next to its name. The source is the Composer package that provides the diagnostic:

```text
[fail] Storage is writable (crustum/audit): The application cannot write to every required runtime directory.
[pass] SQLite database exists (acme/application): The SQLite database file exists.
[warn] Queue runs asynchronously (acme/application): Queued jobs run synchronously in production.
```

<a name="running-audit-programmatically"></a>
## Running Audit Programmatically

You don't have to use the console command — Audit can be driven from your application code. Resolve the shared `AuditManager` from the application container, then call `run` to execute the registered diagnostics and receive a `DiagnosticReport`:

```php
use Cake\Core\ContainerInterface;
use Crustum\Audit\AuditManager;

$audit = $container->get(AuditManager::class);
$report = $audit->run();

if ($report->hasFailures()) {
    // ...
}
```

Programmatic runs honor the `only` and `except` selectors from Audit's configuration file. To narrow a run further, constrain it with `only` and `except` before calling `run`. Selectors may reference a diagnostic class, class basename, group, or package name — the same values accepted by the command's `--only` and `--except` options:

```php
$report = $audit->only('security')
    ->except(SomeDiagnostic::class)
    ->run();
```

Repeated `only` calls intersect, so each call narrows the run within the previous constraints.

To apply fixes as part of a run, configure the run with `fixUsing`. The callback receives each failing diagnostic that offers a fix and decides what to do: return `false` to skip the fix, `true` to apply a plain fix, or one of the result's fix option values to apply the fix with that choice. When any fixes are applied, Audit re-runs the diagnostics so the report reflects the repaired application:

```php
$report = $audit->fixUsing(
    fn ($outcome) => $outcome->fixRequiresOption() ? false : true,
)->run();

$report->fixes();
```

Returning `true` for an outcome whose result declares fix options throws a `LogicException` — a choice-based fix never runs without a choice.

<a name="output-formats"></a>
## Output Formats

Audit renders readable CLI output by default, but JSON and GitHub Actions annotations are also available when you need machine-readable reports:

```bash
bin/cake audit process --format=json

bin/cake audit process --format=github
```

The JSON report follows the versioned shape of `DiagnosticReport`: a `version`, the full list of `diagnostics`, and any applied `fixes`. Each diagnostic entry carries its class, group, name, Composer `source`, machine-readable `code`, `status`, `summary`, and remediation metadata:

```json
{
    "version": 1,
    "diagnostics": [
        {
            "class": "Crustum\\Audit\\Diagnostic\\EnvironmentFileExists",
            "group": "environment",
            "name": ".env file exists",
            "source": {
                "label": "crustum/audit",
                "package": "crustum/audit",
                "file": "src/Diagnostic/EnvironmentFileExists.php",
                "application": false
            },
            "code": "environment-file-exists.exists",
            "status": "pass",
            "fixable": false,
            "options": [],
            "summary": "The application has an environment file.",
            "details": null,
            "remediation": null,
            "links": {}
        }
    ],
    "fixes": []
}
```

<a name="agent-optimized-output"></a>
### Agent-Optimized Output

When Audit detects that it is running inside an AI coding agent such as Claude Code or Cursor — using [Laravel Agent Detector](https://github.com/laravel/agent-detector) — it defaults to an agent-optimized format instead of CLI output. The format follows the [crustum/essentia](https://github.com/crustum/essentia) convention: a single line of JSON with aggregate counts up front and only actionable outcomes itemized, letting agents use Audit as a minimal, parseable completion gate:

```json
{"tool":"audit","result":"failed","diagnostics":23,"failed":1,"warnings":1,"notices":0,"passed":19,"skipped":2,"issues":[{"name":".env file exists","status":"fail","summary":"The application does not have an environment file.","fix":"Copy .env.example to .env, then review the copied values.","fixable":true}]}
```

Issues marked `fixable` may be fixed by re-running Audit with `--fix`, which applies every available deterministic fix, re-runs the diagnostics, and appends the fix outcomes to the payload. Issues that carry an `options` map instead of the `fixable` flag require a choice `--fix` will not make: the agent should apply the change itself following the issue's `fix` remediation, or escalate the shortlist of options to a human.

An explicit `--format` option always overrides detection, and the agent format may also be requested directly:

```bash
bin/cake audit process --format=cli

bin/cake audit process --format=agent
```

To preview agent output outside an agent, set the `AI_AGENT` environment variable: `AI_AGENT=test bin/cake audit process`.
