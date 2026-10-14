<?php
declare(strict_types=1);

namespace Ffs2\Core;

/**
 * Applique les scripts database/migrations/NNNN_nom.sql dans l'ordre, une seule fois chacun.
 */
final class Migrator
{
    public function __construct(private Database $db, private string $dir = FFS2_ROOT . '/database/migrations')
    {
    }

    /** @return string[] versions disponibles, triées */
    public function available(): array
    {
        $versions = [];
        foreach (glob($this->dir . '/*.sql') ?: [] as $file) {
            $versions[] = basename($file, '.sql');
        }
        sort($versions, SORT_STRING);
        return $versions;
    }

    /** @return string[] versions déjà appliquées */
    public function applied(): array
    {
        $this->db->execute('CREATE TABLE IF NOT EXISTS {schema_migrations} (
            version VARCHAR(100) NOT NULL PRIMARY KEY,
            applied_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        return array_column($this->db->fetchAll('SELECT version FROM {schema_migrations} ORDER BY version'), 'version');
    }

    /** @return string[] */
    public function pending(): array
    {
        return array_values(array_diff($this->available(), $this->applied()));
    }

    /** @return string[] versions appliquées par cet appel */
    public function migrate(): array
    {
        $done = [];
        foreach ($this->pending() as $version) {
            foreach ($this->statements((string) file_get_contents($this->dir . "/$version.sql")) as $statement) {
                $this->db->execute($statement);
            }
            $this->db->insert('schema_migrations', ['version' => $version, 'applied_at' => now_utc()]);
            $done[] = $version;
        }
        return $done;
    }

    /** Découpe un script SQL en requêtes (séparateur « ; » en fin de ligne, commentaires -- ignorés) */
    private function statements(string $sql): array
    {
        $sql = (string) preg_replace('/^\s*--.*$/m', '', $sql);
        $parts = preg_split('/;\s*$/m', $sql) ?: [];
        return array_values(array_filter(array_map('trim', $parts), fn ($s) => $s !== ''));
    }
}
