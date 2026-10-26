<?php
declare(strict_types=1);

use Cake\Container\Container;
use Crustum\Audit\AuditManager;

if (!function_exists('auditManager')) {
    /**
     * Create a manager with shared diagnostic definitions.
     *
     * @param list<class-string<\Crustum\Audit\Diagnostic>> $diagnostics Diagnostic classes.
     * @return \Crustum\Audit\AuditManager
     */
    function auditManager(array $diagnostics): AuditManager
    {
        $container = new Container();

        foreach ($diagnostics as $diagnostic) {
            $container->addShared($diagnostic);
        }

        return new AuditManager($container)->diagnostics($diagnostics);
    }
}
