<?php
declare(strict_types=1);

/**
 * Audit Plugin Configuration
 *
 * Host applications copy settings into `config/audit.php` and load via
 * `Configure::load('audit')`. Keys are read as `Configure::read('Audit.*')`.
 */
return [
    'Audit' => [
        /*
         * Diagnostic Selection
         *
         * These selectors use the same syntax as the --only and --except command
         * options: diagnostic class names, groups, Composer packages, or package
         * wildcards. Leave them empty to run every registered diagnostic.
         */
        'only' => [
            // 'crustum/audit',
            // 'vendor/*',
            // 'security',
        ],
        'except' => [
            // \Crustum\Audit\Diagnostic\EnvironmentFileIsGitIgnored::class,
        ],

        /*
         * Environment Modes
         *
         * Group application environment names by the Audit mode whose expectations
         * should apply. Any environment not listed here is treated as production,
         * the strictest mode.
         */
        'environment' => 'local',
        'environments' => [
            'local' => ['local', 'development', 'dev', 'test', 'testing'],
            'production' => ['production', 'staging', 'prod'],
        ],

        /*
         * Host-declared required Configure paths.
         *
         * Map Configure paths to human labels checked by RequiredConfigurationValuesAreSet.
         */
        'requiredConfiguration' => [],
    ],
];
