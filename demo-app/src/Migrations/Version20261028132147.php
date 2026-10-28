<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261028132147 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__cadenza_media AS SELECT id, created_at, path, mime, width, height, alt, source, source_url, author, license, license_url, external_id, caption, taken_at, tags FROM cadenza_media');
        $this->addSql('DROP TABLE cadenza_media');
        $this->addSql('CREATE TABLE cadenza_media (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, created_at DATETIME NOT NULL, path VARCHAR(255) NOT NULL, mime VARCHAR(64) NOT NULL, width INTEGER NOT NULL, height INTEGER NOT NULL, alt VARCHAR(500) NOT NULL, source VARCHAR(32) NOT NULL, source_url VARCHAR(1000) DEFAULT NULL, author VARCHAR(255) DEFAULT NULL, license VARCHAR(100) DEFAULT NULL, license_url VARCHAR(500) DEFAULT NULL, external_id VARCHAR(191) DEFAULT NULL, caption CLOB DEFAULT NULL, taken_at DATETIME DEFAULT NULL, tags CLOB NOT NULL, latitude DOUBLE PRECISION DEFAULT NULL, longitude DOUBLE PRECISION DEFAULT NULL, album VARCHAR(160) DEFAULT NULL)');
        $this->addSql('INSERT INTO cadenza_media (id, created_at, path, mime, width, height, alt, source, source_url, author, license, license_url, external_id, caption, taken_at, tags) SELECT id, created_at, path, mime, width, height, alt, source, source_url, author, license, license_url, external_id, caption, taken_at, tags FROM __temp__cadenza_media');
        $this->addSql('DROP TABLE __temp__cadenza_media');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D5F86F75F8A7F739F75D7B0 ON cadenza_media (source, external_id)');
        $this->addSql('CREATE INDEX IDX_D5F86F7C48F0688 ON cadenza_media (taken_at)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D5F86F7B548B0F ON cadenza_media (path)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__cadenza_media AS SELECT id, created_at, external_id, caption, taken_at, tags, path, mime, width, height, alt, source, source_url, author, license, license_url FROM cadenza_media');
        $this->addSql('DROP TABLE cadenza_media');
        $this->addSql('CREATE TABLE cadenza_media (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, created_at DATETIME NOT NULL, external_id VARCHAR(191) DEFAULT NULL, caption CLOB DEFAULT NULL, taken_at DATETIME DEFAULT NULL, tags CLOB DEFAULT \'[]\' NOT NULL, path VARCHAR(255) NOT NULL, mime VARCHAR(64) NOT NULL, width INTEGER NOT NULL, height INTEGER NOT NULL, alt VARCHAR(500) NOT NULL, source VARCHAR(32) NOT NULL, source_url VARCHAR(1000) DEFAULT NULL, author VARCHAR(255) DEFAULT NULL, license VARCHAR(100) DEFAULT NULL, license_url VARCHAR(500) DEFAULT NULL)');
        $this->addSql('INSERT INTO cadenza_media (id, created_at, external_id, caption, taken_at, tags, path, mime, width, height, alt, source, source_url, author, license, license_url) SELECT id, created_at, external_id, caption, taken_at, tags, path, mime, width, height, alt, source, source_url, author, license, license_url FROM __temp__cadenza_media');
        $this->addSql('DROP TABLE __temp__cadenza_media');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D5F86F7B548B0F ON cadenza_media (path)');
        $this->addSql('CREATE INDEX IDX_D5F86F7C48F0688 ON cadenza_media (taken_at)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D5F86F75F8A7F739F75D7B0 ON cadenza_media (source, external_id)');
    }
}
