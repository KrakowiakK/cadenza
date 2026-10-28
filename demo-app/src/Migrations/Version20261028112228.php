<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261028112228 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE cadenza_media (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, created_at DATETIME NOT NULL, path VARCHAR(255) NOT NULL, mime VARCHAR(64) NOT NULL, width INTEGER NOT NULL, height INTEGER NOT NULL, alt VARCHAR(500) NOT NULL, source VARCHAR(32) NOT NULL, source_url VARCHAR(1000) DEFAULT NULL, author VARCHAR(255) DEFAULT NULL, license VARCHAR(100) DEFAULT NULL, license_url VARCHAR(500) DEFAULT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D5F86F7B548B0F ON cadenza_media (path)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE cadenza_media');
    }
}
