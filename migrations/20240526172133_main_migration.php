<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class MainMigration extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */

    public function up(): void
    {
        $usersTable = $this->table('users');
        $usersTable->addColumn('first_name', 'string', ['limit' => 50])
                   ->addColumn('last_name', 'string', ['limit' => 50])
                   ->addColumn('phone', 'string', ['limit' => 13])
                   ->addColumn('birth_date', 'date')
                   ->create();
        
        $loansTable = $this->table('loans');
        $loansTable->addColumn('user_id', 'integer', ['signed' => false])
                   ->addColumn('amount', 'integer')
                   ->addColumn('create_time', 'integer', ['signed' => false])
                   ->addColumn('pay_time', 'integer', ['signed' => false])
                   ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
                   ->create();

        $this->execute(<<<SQL

        CREATE TRIGGER create_time_default
        BEFORE INSERT ON loans
        FOR EACH ROW
            BEGIN
            IF NEW.create_time IS NULL THEN
                SET NEW.create_time = UNIX_TIMESTAMP();
            END IF;
        END;
        SQL);
    }


    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS users');
        $this->execute('DROP TABLE IF EXISTS loans');
        $this->execute('DROP TRIGGER IF EXISTS create_time_default');
    }
}
