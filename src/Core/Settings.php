<?php
declare(strict_types=1);

namespace Ffs2\Core;

/**
 * Réglages du portail modifiables depuis le back-office (table settings), avec valeurs par défaut.
 */
final class Settings
{
    public const DEFAULTS = [
        'site_name' => 'FFS2play',
        'site_description' => 'Multijoueur pair à pair pour simulateurs de vol',
        'timezone' => 'Europe/Paris',
        'registration_open' => '1',
        'require_email_verification' => '1',
        'users_can_create_servers' => '1',
        'max_servers_per_user' => '2',
        'mail_transport' => 'mail',
        'mail_from' => '',
        'mail_from_name' => 'FFS2play',
        'smtp_host' => '',
        'smtp_port' => '587',
        'smtp_encryption' => 'tls',
        'smtp_user' => '',
        'smtp_pass' => '',
        'tile_url' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
        'tile_attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    ];

    private ?array $values = null;

    public function __construct(private ?Database $db)
    {
    }

    public function get(string $name): string
    {
        $this->load();
        return (string) ($this->values[$name] ?? self::DEFAULTS[$name] ?? '');
    }

    public function bool(string $name): bool
    {
        return $this->get($name) === '1';
    }

    public function int(string $name): int
    {
        return (int) $this->get($name);
    }

    /** @param array<string, string> $values */
    public function save(array $values): void
    {
        foreach ($values as $name => $value) {
            if (!array_key_exists($name, self::DEFAULTS)) {
                continue;
            }
            $this->db?->execute(
                'INSERT INTO {settings} (name, value) VALUES (:name, :value) ON DUPLICATE KEY UPDATE value = VALUES(value)',
                ['name' => $name, 'value' => (string) $value]
            );
            $this->values[$name] = (string) $value;
        }
    }

    private function load(): void
    {
        if ($this->values !== null) {
            return;
        }
        $this->values = [];
        if ($this->db === null) {
            return;
        }
        try {
            foreach ($this->db->fetchAll('SELECT name, value FROM {settings}') as $row) {
                $this->values[$row['name']] = $row['value'];
            }
        } catch (\PDOException) {
            // table absente avant les migrations : valeurs par défaut
        }
    }
}
