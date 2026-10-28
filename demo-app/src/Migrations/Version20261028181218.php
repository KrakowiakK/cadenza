<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261028181218 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Shared leaderboards of game widgets';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE cadenza_widget_score (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, created_at DATETIME NOT NULL, widget VARCHAR(40) NOT NULL, player VARCHAR(24) NOT NULL, score INTEGER NOT NULL)');
        $this->addSql('CREATE INDEX IDX_D8C604F85F91ED032993751 ON cadenza_widget_score (widget, score)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE cadenza_widget_score');
    }
}
