<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261028130835 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE cadenza_connection (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, connected_at DATETIME NOT NULL, last_sync_at DATETIME DEFAULT NULL, connector VARCHAR(64) NOT NULL, account VARCHAR(255) NOT NULL, sealed_credentials CLOB NOT NULL, expires_at DATETIME DEFAULT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_57FDAEC4148C456E ON cadenza_connection (connector)');
        $this->addSql('CREATE TABLE cadenza_import_job (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, status VARCHAR(16) NOT NULL, done INTEGER NOT NULL, total INTEGER DEFAULT NULL, imported INTEGER NOT NULL, skipped INTEGER NOT NULL, errors CLOB NOT NULL, created_at DATETIME NOT NULL, finished_at DATETIME DEFAULT NULL, connector VARCHAR(64) NOT NULL, method VARCHAR(16) NOT NULL, file VARCHAR(1000) DEFAULT NULL)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__cadenza_media AS SELECT id, created_at, path, mime, width, height, alt, source, source_url, author, license, license_url FROM cadenza_media');
        $this->addSql('DROP TABLE cadenza_media');
        $this->addSql('CREATE TABLE cadenza_media (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, created_at DATETIME NOT NULL, path VARCHAR(255) NOT NULL, mime VARCHAR(64) NOT NULL, width INTEGER NOT NULL, height INTEGER NOT NULL, alt VARCHAR(500) NOT NULL, source VARCHAR(32) NOT NULL, source_url VARCHAR(1000) DEFAULT NULL, author VARCHAR(255) DEFAULT NULL, license VARCHAR(100) DEFAULT NULL, license_url VARCHAR(500) DEFAULT NULL, external_id VARCHAR(191) DEFAULT NULL, caption CLOB DEFAULT NULL, taken_at DATETIME DEFAULT NULL, tags CLOB DEFAULT \'[]\' NOT NULL)');
        $this->addSql('INSERT INTO cadenza_media (id, created_at, path, mime, width, height, alt, source, source_url, author, license, license_url) SELECT id, created_at, path, mime, width, height, alt, source, source_url, author, license, license_url FROM __temp__cadenza_media');
        $this->addSql('DROP TABLE __temp__cadenza_media');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D5F86F7B548B0F ON cadenza_media (path)');
        $this->addSql('CREATE INDEX IDX_D5F86F7C48F0688 ON cadenza_media (taken_at)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D5F86F75F8A7F739F75D7B0 ON cadenza_media (source, external_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE cadenza_connection');
        $this->addSql('DROP TABLE cadenza_import_job');
        $this->addSql('CREATE TEMPORARY TABLE __temp__cadenza_media AS SELECT id, created_at, path, mime, width, height, alt, source, source_url, author, license, license_url FROM cadenza_media');
        $this->addSql('DROP TABLE cadenza_media');
        $this->addSql('CREATE TABLE cadenza_media (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, created_at DATETIME NOT NULL, path VARCHAR(255) NOT NULL, mime VARCHAR(64) NOT NULL, width INTEGER NOT NULL, height INTEGER NOT NULL, alt VARCHAR(500) NOT NULL, source VARCHAR(32) NOT NULL, source_url VARCHAR(1000) DEFAULT NULL, author VARCHAR(255) DEFAULT NULL, license VARCHAR(100) DEFAULT NULL, license_url VARCHAR(500) DEFAULT NULL)');
        $this->addSql('INSERT INTO cadenza_media (id, created_at, path, mime, width, height, alt, source, source_url, author, license, license_url) SELECT id, created_at, path, mime, width, height, alt, source, source_url, author, license, license_url FROM __temp__cadenza_media');
        $this->addSql('DROP TABLE __temp__cadenza_media');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D5F86F7B548B0F ON cadenza_media (path)');
    }
}
