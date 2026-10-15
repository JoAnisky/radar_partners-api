<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261015071136 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add signal.dismissed_at';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE signal ADD dismissed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE signal DROP dismissed_at');
    }
}
