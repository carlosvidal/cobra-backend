<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class FixPortfolioUserTable extends Migration
{
    public function up()
    {
        // Drop and recreate portfolio_user table with UUID structure
        $this->forge->dropTable('portfolio_user', true);
        
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'portfolio_uuid' => [
                'type' => 'VARCHAR',
                'constraint' => 36,
                'null' => false,
            ],
            'user_uuid' => [
                'type' => 'VARCHAR',
                'constraint' => 36,
                'null' => false,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        
        $this->forge->addKey('id', true);
        $this->forge->addKey(['portfolio_uuid', 'user_uuid'], false, true); // Unique key
        
        $this->forge->createTable('portfolio_user');
    }

    public function down()
    {
        $this->forge->dropTable('portfolio_user');
    }
}