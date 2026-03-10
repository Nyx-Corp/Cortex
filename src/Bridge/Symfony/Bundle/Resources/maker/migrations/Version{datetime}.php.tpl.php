<?php echo "<?php\n"; ?>

/**
 * @generated from src/Lib/Cortex/src/Bridge/Symfony/Bundle/Resources/maker/migrations/Version{datetime}.php.tpl.php
 * @see src/Lib/Cortex/README.md
 * @see src/Lib/Cortex/docs/bridge-doctrine.md
 */

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version<?php echo $datetime; ?> extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates table <?php echo $table; ?>.';
    }

    /**
     * @example
     *    CREATE TABLE studio_mannequin (
     *        uuid CHAR(36) NOT NULL,
     *        title VARCHAR(255) NOT NULL,
     *        description TEXT DEFAULT NULL,
     *        tags JSON DEFAULT NULL,
     *        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
     *        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
     *
     *        PRIMARY KEY (uuid),
     *        UNIQUE KEY uq_unique_field (unique_field),
     *        KEY idx_index_name (index_field_1, index_field_2),
     *        CONSTRAINT fk_other_table FOREIGN KEY (other_table_uuid) REFERENCES other_table(uuid)
     *            ON DELETE SET NULL ON UPDATE CASCADE
     *    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
     * @example
     *    ALTER TABLE studio_mannequin
     *        DROP COLUMN date,
     *        ADD COLUMN column_name VARCHAR(255) DEFAULT NULL AFTER column_name_2,
     *        ADD CONSTRAINT fk_..... FOREIGN KEY (column_name)
     *            REFERENCES foreign_table(uuid)
     *            ON DELETE SET NULL ON UPDATE CASCADE,
     *        DROP FOREIGN KEY fk_...
     *        DROP INDEX idx_....
     *    ;
     */
    public function up(Schema $schema): void
    {
        $this->addSql(<<<SQL
            CREATE TABLE <?php echo $table; ?> (
                uuid CHAR(36) NOT NULL,

                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                archived_at DATETIME DEFAULT NULL,

                PRIMARY KEY (uuid)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('<?php echo $table; ?>');
    }
}
