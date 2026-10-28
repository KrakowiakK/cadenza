<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261028145751 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE cadenza_collection (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, created_at DATETIME NOT NULL, slug VARCHAR(64) NOT NULL, name VARCHAR(160) NOT NULL, fields CLOB NOT NULL, title_field VARCHAR(64) NOT NULL, image_field VARCHAR(64) DEFAULT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8247B890989D9B62 ON cadenza_collection (slug)');
        $this->addSql('CREATE TABLE cadenza_record (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, slug VARCHAR(191) NOT NULL, data CLOB NOT NULL, position INTEGER NOT NULL, collection_id INTEGER NOT NULL, CONSTRAINT FK_B13CC722514956FD FOREIGN KEY (collection_id) REFERENCES cadenza_collection (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B13CC722514956FD989D9B62 ON cadenza_record (collection_id, slug)');
        $this->addSql('CREATE INDEX IDX_B13CC722514956FD ON cadenza_record (collection_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE cadenza_collection');
        $this->addSql('DROP TABLE cadenza_record');
    }
}
