<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Sample TestApp migration used by optional integration diagnostics.
 */
class CreateAuditProbeItems extends BaseMigration
{
    /**
     * Change Method.
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('audit_probe_items');
        $table->addColumn('name', 'string', [
            'default' => null,
            'limit' => 50,
            'null' => false,
        ]);
        $table->create();
    }
}
