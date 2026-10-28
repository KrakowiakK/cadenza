<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261028162739 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Generated SQL rebuilt the table without the new columns (SQLite comparator); two ADD COLUMNs are enough.
        $this->addSql('ALTER TABLE cadenza_record ADD COLUMN published BOOLEAN DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE cadenza_record ADD COLUMN draft CLOB DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cadenza_record DROP COLUMN draft');
        $this->addSql('ALTER TABLE cadenza_record DROP COLUMN published');
    }
}
