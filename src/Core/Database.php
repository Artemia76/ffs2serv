<?php
declare(strict_types=1);

namespace Ffs2\Core;

use PDO;
use PDOStatement;

/**
 * Accès MariaDB / MySQL par PDO, requêtes préparées uniquement.
 * Les noms de tables s'écrivent entre accolades ({users}) et reçoivent le préfixe configuré.
 */
final class Database
{
    private PDO $pdo;

    public function __construct(array $config)
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $config['host'] ?? 'localhost',
            (int) ($config['port'] ?? 3306),
            $config['name'] ?? ''
        );
        $this->pdo = new PDO($dsn, (string) ($config['user'] ?? ''), (string) ($config['pass'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
        // toutes les dates sont manipulées en UTC
        $this->pdo->exec("SET time_zone = '+00:00', NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $this->prefix = (string) ($config['prefix'] ?? '');
    }

    private string $prefix;

    public function prefix(): string
    {
        return $this->prefix;
    }

    /** Remplace {table} par le nom préfixé */
    public function sql(string $sql): string
    {
        return (string) preg_replace_callback('/\{([a-z_]+)\}/', fn ($m) => '`' . $this->prefix . $m[1] . '`', $sql);
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        $statement = $this->pdo->prepare($this->sql($sql));
        foreach ($params as $key => $value) {
            $name = is_int($key) ? $key + 1 : ':' . ltrim($key, ':');
            $type = match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $statement->bindValue($name, $value, $type);
        }
        $statement->execute();
        return $statement;
    }

    public function fetch(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetchValue(string $sql, array $params = []): mixed
    {
        $value = $this->query($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    public function execute(string $sql, array $params = []): int
    {
        return $this->query($sql, $params)->rowCount();
    }

    /** INSERT d'un tableau colonne => valeur, retourne l'identifiant créé */
    public function insert(string $table, array $values): int
    {
        $columns = array_keys($values);
        $sql = sprintf(
            'INSERT INTO {%s} (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(fn ($c) => "`$c`", $columns)),
            implode(', ', array_map(fn ($c) => ":$c", $columns))
        );
        $this->query($sql, $values);
        return (int) $this->pdo->lastInsertId();
    }

    /** UPDATE d'un tableau colonne => valeur ; $where est un fragment SQL avec paramètres nommés */
    public function update(string $table, array $values, string $where, array $whereParams = []): int
    {
        $set = implode(', ', array_map(fn ($c) => "`$c` = :set_$c", array_keys($values)));
        $params = [];
        foreach ($values as $column => $value) {
            $params["set_$column"] = $value;
        }
        return $this->execute("UPDATE {{$table}} SET $set WHERE $where", $params + $whereParams);
    }

    public function transaction(callable $callback): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $callback($this);
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }
}
