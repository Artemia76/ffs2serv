<?php
declare(strict_types=1);

namespace Ffs2\Core;

/**
 * Envoi d'e-mails texte UTF-8 sans dépendance :
 *  - smtp : client SMTP minimal (STARTTLS ou SSL implicite, AUTH LOGIN)
 *  - mail : fonction mail() de PHP
 *  - file : écrit le message dans storage/mail (tests, développement)
 */
final class Mailer
{
    public function __construct(private Settings $settings, private string $storageDir, private ?string $forcedTransport = null)
    {
    }

    public function transport(): string
    {
        return $this->forcedTransport ?? $this->settings->get('mail_transport');
    }

    /** Un transport réellement utilisable est-il configuré ? */
    public function isConfigured(): bool
    {
        return match ($this->transport()) {
            'smtp' => $this->settings->get('smtp_host') !== '' && $this->from() !== '',
            'mail' => function_exists('mail') && $this->from() !== '',
            'file' => true,
            default => false,
        };
    }

    public function send(string $to, string $subject, string $body): void
    {
        $from = $this->from();
        $fromName = $this->settings->get('mail_from_name') ?: $this->settings->get('site_name');
        $headers = [
            'Date' => date(DATE_RFC2822),
            'From' => $this->encodeHeader($fromName) . " <$from>",
            'To' => $to,
            'Subject' => $this->encodeHeader($subject),
            'Message-ID' => '<' . bin2hex(random_bytes(12)) . '@' . (substr(strrchr($from, '@') ?: '@localhost', 1)) . '>',
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Transfer-Encoding' => 'base64',
        ];
        $encodedBody = rtrim(chunk_split(base64_encode(str_replace(["\r\n", "\r"], "\n", $body)), 76, "\r\n"));

        match ($this->transport()) {
            'smtp' => $this->sendSmtp($from, $to, $headers, $encodedBody),
            'file' => $this->sendFile($headers, $encodedBody),
            default => $this->sendMail($to, $headers, $encodedBody),
        };
    }

    private function from(): string
    {
        $from = $this->settings->get('mail_from');
        if ($from === '') {
            $host = preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
            $from = 'no-reply@' . $host;
        }
        return $from;
    }

    private function encodeHeader(string $value): string
    {
        return preg_match('/[^\x20-\x7e]/', $value) ? '=?UTF-8?B?' . base64_encode($value) . '?=' : $value;
    }

    private function formatHeaders(array $headers, array $skip = []): string
    {
        $lines = [];
        foreach ($headers as $name => $value) {
            if (!in_array($name, $skip, true)) {
                $lines[] = "$name: $value";
            }
        }
        return implode("\r\n", $lines);
    }

    private function sendFile(array $headers, string $body): void
    {
        $dir = $this->storageDir . '/mail';
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new \RuntimeException("Impossible de créer $dir");
        }
        $file = sprintf('%s/%s-%s.eml', $dir, gmdate('YmdHis'), bin2hex(random_bytes(4)));
        file_put_contents($file, $this->formatHeaders($headers) . "\r\n\r\n" . $body . "\r\n");
    }

    private function sendMail(string $to, array $headers, string $body): void
    {
        $ok = mail($to, $headers['Subject'], $body, $this->formatHeaders($headers, ['To', 'Subject']), '-f' . $this->from());
        if (!$ok) {
            throw new \RuntimeException("La fonction mail() a refusé l'envoi");
        }
    }

    private function sendSmtp(string $from, string $to, array $headers, string $body): void
    {
        $host = $this->settings->get('smtp_host');
        $port = $this->settings->int('smtp_port') ?: 587;
        $encryption = $this->settings->get('smtp_encryption');
        $address = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;

        $socket = @stream_socket_client($address, $errno, $error, 15);
        if ($socket === false) {
            throw new \RuntimeException("Connexion SMTP impossible ($address) : $error");
        }
        stream_set_timeout($socket, 15);
        try {
            $this->expect($socket, 220);
            $ehlo = 'EHLO ' . (gethostname() ?: 'localhost');
            $this->command($socket, $ehlo, 250);
            if ($encryption === 'tls') {
                $this->command($socket, 'STARTTLS', 220);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('Négociation TLS impossible');
                }
                $this->command($socket, $ehlo, 250);
            }
            $user = $this->settings->get('smtp_user');
            if ($user !== '') {
                $this->command($socket, 'AUTH LOGIN', 334);
                $this->command($socket, base64_encode($user), 334);
                $this->command($socket, base64_encode($this->settings->get('smtp_pass')), 235);
            }
            $this->command($socket, "MAIL FROM:<$from>", 250);
            $this->command($socket, "RCPT TO:<$to>", [250, 251]);
            $this->command($socket, 'DATA', 354);
            // les lignes commençant par un point sont doublées (RFC 5321)
            $data = preg_replace('/^\./m', '..', $this->formatHeaders($headers) . "\r\n\r\n" . $body);
            $this->command($socket, $data . "\r\n.", 250);
            $this->command($socket, 'QUIT', 221);
        } finally {
            fclose($socket);
        }
    }

    /** @param resource $socket */
    private function command($socket, string $command, int|array $expected): void
    {
        fwrite($socket, $command . "\r\n");
        $this->expect($socket, $expected);
    }

    /** @param resource $socket */
    private function expect($socket, int|array $expected): void
    {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, (array) $expected, true)) {
            throw new \RuntimeException('Erreur SMTP : ' . trim($response ?: 'pas de réponse'));
        }
    }
}
