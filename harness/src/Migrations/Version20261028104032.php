<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261028104032 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE conversation (id VARCHAR(26) NOT NULL, created_at DATETIME NOT NULL, turns CLOB NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE proposal (id VARCHAR(26) NOT NULL, status VARCHAR(16) NOT NULL, created_at DATETIME NOT NULL, decided_at DATETIME DEFAULT NULL, published_version INTEGER DEFAULT NULL, draft_id INTEGER NOT NULL, summary VARCHAR(255) NOT NULL, changes CLOB NOT NULL, focus_path VARCHAR(255) NOT NULL, conversation_id VARCHAR(26) NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_BFE594729AC0396 FOREIGN KEY (conversation_id) REFERENCES conversation (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_BFE594729AC0396 ON proposal (conversation_id)');
        $this->addSql('CREATE TABLE messenger_messages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, body CLOB NOT NULL, headers CLOB NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL)');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE conversation');
        $this->addSql('DROP TABLE proposal');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
