<?php
declare(strict_types=1);

namespace Ffs2\Client;

use XMLWriter;

/**
 * Réponse XML au client ffs2play (même format que l'ancien serveur, voir web/webmanager*.cpp) :
 *
 * <info>
 *   <your_ip>…</your_ip> <loginStatus>1</loginStatus> <key>…</key> …
 *   <whazzup><Callsign ip="…" local_ip="…" port="…" lastupdate="…" team="…"/>…</whazzup>
 *   <erreur error="0|1|2|3">message</erreur>
 * </info>
 *
 * Le client ne lit que le PREMIER élément erreur : une erreur de niveau 2 provoque sa déconnexion.
 */
final class XmlResponse
{
    /** @var array<string, string> */
    private array $fields = [];
    private ?array $whazzup = null;
    /** @var array<int, string> messages par niveau : 0 information, 1 avertissement, 2 erreur, 3 debug */
    private array $messages = [];

    public function set(string $name, string|int $value): void
    {
        $this->fields[$name] = (string) $value;
    }

    /** @param array<int, array{name: string, ip: string, local_ip: string, port: int|string, lastupdate: string, team?: ?string}> $players */
    public function setWhazzup(array $players): void
    {
        $this->whazzup = $players;
    }

    public function info(int $id, string $extra = ''): void
    {
        $this->add(0, Messages::text($id) . $extra);
    }

    public function warning(int $id): void
    {
        $this->add(1, Messages::text($id));
    }

    public function error(int $id): void
    {
        $this->add(2, Messages::text($id));
    }

    private function add(int $level, string $text): void
    {
        $this->messages[$level] = ($this->messages[$level] ?? '') . $text;
    }

    public function toXml(): string
    {
        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('info');
        foreach ($this->fields as $name => $value) {
            $xml->writeElement($name, $value);
        }
        if ($this->whazzup !== null) {
            $xml->startElement('whazzup');
            foreach ($this->whazzup as $player) {
                $xml->startElement($player['name']);
                $xml->writeAttribute('ip', (string) $player['ip']);
                if (!empty($player['ip6'])) {
                    $xml->writeAttribute('ip6', (string) $player['ip6']);
                }
                $xml->writeAttribute('local_ip', (string) $player['local_ip']);
                $xml->writeAttribute('port', (string) $player['port']);
                $xml->writeAttribute('lastupdate', (string) $player['lastupdate']);
                if (!empty($player['team'])) {
                    $xml->writeAttribute('team', (string) $player['team']);
                }
                $xml->endElement();
            }
            $xml->endElement();
        }
        ksort($this->messages);
        foreach ($this->messages as $level => $text) {
            $xml->startElement('erreur');
            $xml->writeAttribute('error', (string) $level);
            $xml->text($text);
            $xml->endElement();
        }
        $xml->endElement();
        return $xml->outputMemory();
    }

    /** Lecture de la requête XML du client ; null si invalide */
    public static function parseRequest(string $body): ?\SimpleXMLElement
    {
        if (trim($body) === '') {
            return null;
        }
        $encoding = mb_detect_encoding($body, ['UTF-8', 'ISO-8859-1'], true);
        if ($encoding !== false && $encoding !== 'UTF-8') {
            $body = mb_convert_encoding($body, 'UTF-8', $encoding);
        }
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string(trim($body), \SimpleXMLElement::class, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return $xml === false ? null : $xml;
    }
}
