<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261028172528 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Widgets remember the frame they were written for';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cadenza_widget ADD COLUMN shape VARCHAR(20) DEFAULT \'wide\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__cadenza_widget AS SELECT id, version, updated_at, last_error, name, label, description, code FROM cadenza_widget');
        $this->addSql('DROP TABLE cadenza_widget');
        $this->addSql('CREATE TABLE cadenza_widget (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, version INTEGER NOT NULL, updated_at DATETIME NOT NULL, last_error CLOB DEFAULT NULL, name VARCHAR(40) NOT NULL, label VARCHAR(80) NOT NULL, description CLOB NOT NULL, code CLOB NOT NULL)');
        $this->addSql('INSERT INTO cadenza_widget (id, version, updated_at, last_error, name, label, description, code) SELECT id, version, updated_at, last_error, name, label, description, code FROM __temp__cadenza_widget');
        $this->addSql('DROP TABLE __temp__cadenza_widget');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_AFF146635E237E06 ON cadenza_widget (name)');
    }
}
