<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250809232151 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE own_client CHANGE corporate_id corporate_id VARCHAR(255) DEFAULT NULL, CHANGE corporate_id_key corporate_id_key VARCHAR(255) DEFAULT NULL, CHANGE corporate_id_secret corporate_id_secret VARCHAR(255) DEFAULT NULL, CHANGE ssl_public_key ssl_public_key VARCHAR(5000) DEFAULT NULL, CHANGE domain domain VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE own_client CHANGE corporate_id corporate_id VARCHAR(255) NOT NULL, CHANGE corporate_id_key corporate_id_key VARCHAR(255) NOT NULL, CHANGE corporate_id_secret corporate_id_secret VARCHAR(255) NOT NULL, CHANGE ssl_public_key ssl_public_key VARCHAR(5000) NOT NULL, CHANGE domain domain VARCHAR(255) NOT NULL');
    }
}
