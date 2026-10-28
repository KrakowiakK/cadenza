<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261028171746 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Widgets: living elements the agent writes with their own code';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE cadenza_widget (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, version INTEGER NOT NULL, updated_at DATETIME NOT NULL, last_error CLOB DEFAULT NULL, name VARCHAR(40) NOT NULL, label VARCHAR(80) NOT NULL, description CLOB NOT NULL, code CLOB NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_AFF146635E237E06 ON cadenza_widget (name)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE cadenza_widget');
    }
}
