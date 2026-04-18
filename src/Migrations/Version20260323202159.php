<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260323202159 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop failedlogin table and failed_logins column from user table — replaced by Symfony rate limiter';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS `failedlogin`');
        $this->addSql('ALTER TABLE `user` DROP COLUMN `failed_logins`');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE `failedlogin` (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(255) DEFAULT NULL, ip VARCHAR(255) DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP, deleted_at DATETIME DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE `user` ADD `failed_logins` INT NOT NULL DEFAULT 0');
    }
}
