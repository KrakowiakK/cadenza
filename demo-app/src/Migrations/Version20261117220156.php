<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261117220156 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE cadenza_visitor_question (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, created_at DATETIME NOT NULL, question VARCHAR(320) NOT NULL, answer CLOB DEFAULT NULL, answered BOOLEAN NOT NULL, path VARCHAR(255) NOT NULL)');
        $this->addSql('CREATE INDEX IDX_37E9BE6F8B8E8428 ON cadenza_visitor_question (created_at)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE cadenza_visitor_question');
    }
}
