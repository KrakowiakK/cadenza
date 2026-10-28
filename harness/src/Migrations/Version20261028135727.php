<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261028135727 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE proposal ADD COLUMN variant_group VARCHAR(26) DEFAULT NULL');
        $this->addSql('ALTER TABLE proposal ADD COLUMN variant_name VARCHAR(80) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__proposal AS SELECT id, status, created_at, decided_at, published_version, draft_id, summary, changes, focus_path, conversation_id FROM proposal');
        $this->addSql('DROP TABLE proposal');
        $this->addSql('CREATE TABLE proposal (id VARCHAR(26) NOT NULL, status VARCHAR(16) NOT NULL, created_at DATETIME NOT NULL, decided_at DATETIME DEFAULT NULL, published_version INTEGER DEFAULT NULL, draft_id INTEGER NOT NULL, summary VARCHAR(255) NOT NULL, changes CLOB NOT NULL, focus_path VARCHAR(255) NOT NULL, conversation_id VARCHAR(26) NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_BFE594729AC0396 FOREIGN KEY (conversation_id) REFERENCES conversation (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO proposal (id, status, created_at, decided_at, published_version, draft_id, summary, changes, focus_path, conversation_id) SELECT id, status, created_at, decided_at, published_version, draft_id, summary, changes, focus_path, conversation_id FROM __temp__proposal');
        $this->addSql('DROP TABLE __temp__proposal');
        $this->addSql('CREATE INDEX IDX_BFE594729AC0396 ON proposal (conversation_id)');
    }
}
