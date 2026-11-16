<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add company.zone, filled from the postal code department';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE company ADD zone VARCHAR(20) DEFAULT NULL');
        $this->addSql("UPDATE company SET zone = CASE
            WHEN LEFT(postal_code, 2) IN ('25', '39', '70', '90') THEN 'FRANCHE_COMTE'
            WHEN LEFT(postal_code, 2) IN ('21', '58', '71', '89') THEN 'BOURGOGNE'
            ELSE 'OTHER' END
            WHERE postal_code ~ '^[0-9]{5}$'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE company DROP zone');
    }
}
