<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261028155043 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE cadenza_page_view (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, day DATE NOT NULL, path VARCHAR(191) NOT NULL, views INTEGER NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C298A859E5A02990B548B0F ON cadenza_page_view (day, path)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE cadenza_page_view');
    }
}
