<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250502205535 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Insert DSOMM metamodel with ID 2';
    }

    public function up(Schema $schema): void
    {
        // Insert the DSOMM record with ID 2
        $this->addSql('INSERT INTO metamodel (id, name, max_score, `default`, created_at, updated_at) 
                      VALUES (2, "DSOMM", 1, 0, NOW(), NOW())');

    }

    public function down(Schema $schema): void
    {
        // Remove the specific record in case of rollback
        $this->addSql('DELETE FROM metamodel WHERE id = 2');
    }
}
