<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261028194204 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Widget revisions: a page pins the version it shows; the versions saved so far are kept as the first ones.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE cadenza_widget_revision (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, created_at DATETIME NOT NULL, widget VARCHAR(40) NOT NULL, version INTEGER NOT NULL, label VARCHAR(80) NOT NULL, code CLOB NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E3FCB4F185F91ED0BF1CD3C3 ON cadenza_widget_revision (widget, version)');
        $this->addSql('INSERT INTO cadenza_widget_revision (created_at, widget, version, label, code) SELECT updated_at, name, version, label, code FROM cadenza_widget');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE cadenza_widget_revision');
    }
}
