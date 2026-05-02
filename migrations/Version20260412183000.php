<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260412183000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tables for the current Doctrine entities';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Migration can only be executed safely on mysql.'
        );

        $this->addSql('CREATE TABLE instance_settings (id INT AUTO_INCREMENT NOT NULL, initialization TINYINT(1) NOT NULL, public_id VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE own_client (id INT AUTO_INCREMENT NOT NULL, corporate_id VARCHAR(255) DEFAULT NULL, corporate_id_key VARCHAR(255) DEFAULT NULL, corporate_id_secret VARCHAR(255) DEFAULT NULL, ssl_public_key VARCHAR(5000) DEFAULT NULL, domain VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE process (id INT AUTO_INCREMENT NOT NULL, process_id VARCHAR(255) DEFAULT NULL, auth_id VARCHAR(255) DEFAULT NULL, allowed TINYINT(1) DEFAULT NULL, UNIQUE INDEX UNIQ_PROCESS_PROCESS_ID (process_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE whitelisted_users (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(255) NOT NULL, active TINYINT(1) NOT NULL, UNIQUE INDEX UNIQ_WHITELISTED_USERS_EMAIL (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Migration can only be executed safely on mysql.'
        );

        $this->addSql('DROP TABLE whitelisted_users');
        $this->addSql('DROP TABLE process');
        $this->addSql('DROP TABLE own_client');
        $this->addSql('DROP TABLE instance_settings');
    }
}