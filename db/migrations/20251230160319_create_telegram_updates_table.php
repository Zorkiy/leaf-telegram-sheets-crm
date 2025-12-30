<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

class CreateTelegramUpdatesTable extends AbstractMigration {
    public function change(): void {
        $table = $this->table('telegram_updates');

        $table->addColumn('update_id', 'integer')
              ->addColumn('chat_id', 'integer', ['null' => true])
              ->addColumn('username', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('raw_data', 'text', ['null' => true])
              ->addColumn('message_text', 'text', ['null' => true])
              ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['update_id'], ['unique' => true])
              ->create();
    }
}
