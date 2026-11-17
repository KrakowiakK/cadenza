<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261117215012 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cadenza_site_version ADD COLUMN author VARCHAR(80) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__cadenza_site_version AS SELECT id, number, status, created_at, published_at, document, operations, summary, base_number FROM cadenza_site_version');
        $this->addSql('DROP TABLE cadenza_site_version');
        $this->addSql('CREATE TABLE cadenza_site_version (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, number INTEGER DEFAULT NULL, status VARCHAR(16) NOT NULL, created_at DATETIME NOT NULL, published_at DATETIME DEFAULT NULL, document CLOB NOT NULL, operations CLOB NOT NULL, summary VARCHAR(255) NOT NULL, base_number INTEGER DEFAULT NULL)');
        $this->addSql('INSERT INTO cadenza_site_version (id, number, status, created_at, published_at, document, operations, summary, base_number) SELECT id, number, status, created_at, published_at, document, operations, summary, base_number FROM __temp__cadenza_site_version');
        $this->addSql('DROP TABLE __temp__cadenza_site_version');
        $this->addSql('CREATE INDEX IDX_20EB3F007B00651C ON cadenza_site_version (status)');
    }
}
