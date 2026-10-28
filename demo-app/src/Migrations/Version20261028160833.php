<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261028160833 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE cadenza_account (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, password VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, last_login_at DATETIME DEFAULT NULL, email VARCHAR(180) NOT NULL, roles CLOB NOT NULL, name VARCHAR(120) DEFAULT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2F74BCCAE7927C74 ON cadenza_account (email)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE cadenza_account');
    }
}
