<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261028165750 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE cadenza_custom_block (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, version INTEGER NOT NULL, updated_at DATETIME NOT NULL, name VARCHAR(40) NOT NULL, label VARCHAR(80) NOT NULL, description CLOB NOT NULL, props CLOB NOT NULL, template CLOB NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_244561435E237E06 ON cadenza_custom_block (name)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE cadenza_custom_block');
    }
}
