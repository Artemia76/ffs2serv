<?php
declare(strict_types=1);

namespace Ffs2\Client;

use Ffs2\Auth\UserRepository;
use Ffs2\Core\App;
use Ffs2\Domain\FlightTracker;
use Ffs2\Domain\ServerRepository;
use Ffs2\Domain\SlotRepository;

/**
 * Protocole du client ffs2play (POST XML sur action.php), voir web/webmanager*.cpp côté client :
 *   hello      → certificat RSA du serveur
 *   verify     → authentification avec le compte du site, ouverture du slot, liste des pilotes
 *   liveupdate → toutes les 30 s : maintien du slot, position, liste des pilotes
 *   atc        → toutes les 5 s : position (carte temps réel)
 *   logout     → fermeture du slot
 */
final class ClientController
{
    private SlotRepository $slots;
    private FlightTracker $tracker;

    public function __construct(private App $app)
    {
        $this->slots = new SlotRepository($app->db(), $app->appKey());
        $this->tracker = new FlightTracker($app->db());
    }

    public function handle(string $body, string $ip): XmlResponse
    {
        $response = new XmlResponse();
        Messages::setReplacement('site', $this->app->settings()->get('site_name'));

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            $response->error(219);
            $response->set('loginStatus', 0);
            return $response;
        }
        $response->set('your_ip', $ip);

        $xml = XmlResponse::parseRequest($body);
        if ($xml === null) {
            $response->error(201);
            $response->set('loginStatus', 0);
            return $response;
        }

        $this->slots->purge();
        $this->tracker->purge();

        $switch = strtolower(trim((string) $xml->switch->data));
        $version = trim((string) $xml->version);
        if ($switch !== 'hello' && !$this->isSupported($version)) {
            $response->error(202);
            $response->set('loginStatus', 0);
            return $response;
        }

        match ($switch) {
            'hello' => $response->set('certificat', $this->app->keys()->certificate()),
            'verify' => $this->verify($xml, $ip, $version, $response),
            'liveupdate' => $this->liveUpdate($xml, $ip, $response),
            'atc' => $this->atc($xml, $ip),
            'logout' => $this->logout($xml, $response),
            default => null,
        };
        if ($switch === 'hello') {
            $response->set('hello', 'world');
        }
        return $response;
    }

    /** Clients ffs2play 2.x et suivants (version envoyée : ffs2play_<major>_<minor>_<build>) */
    private function isSupported(string $version): bool
    {
        return preg_match('/^ffs2play_(\d+)_/i', $version, $match) === 1 && (int) $match[1] >= 2;
    }

    private function verify(\SimpleXMLElement $xml, string $ip, string $version, XmlResponse $response): void
    {
        $response->set('loginStatus', 0);
        $callsign = trim((string) $xml->verify->pilotID);
        $limiter = $this->app->limiter();
        if ($limiter->tooManyAttempts('client', $ip, $callsign)) {
            $response->error(224);
            return;
        }

        $aesKey = Crypto::rsaDecrypt(trim((string) $xml->verify->AES), $this->app->keys()->privateKey());
        if ($aesKey === null || strlen($aesKey) !== 32) {
            $response->error(215);
            return;
        }

        $users = $this->app->users();
        $user = UserRepository::isValidCallsign($callsign) ? $users->findByCallsign($callsign) : null;
        $password = Crypto::decrypt(trim((string) $xml->verify->password), $aesKey);
        if ($user === null || $password === null || !$users->checkPassword($user, $password)) {
            $limiter->hit('client', $ip, $callsign);
            $response->error(205);
            return;
        }
        $limiter->clear('client', $callsign);
        if ($user['status'] === 'pending') {
            $response->error(222);
            return;
        }
        if ($user['status'] === 'banned') {
            $response->error(223);
            return;
        }

        // serveur choisi sur le site, s'il est toujours accessible
        $servers = new ServerRepository($this->app->db());
        $server = $user['preferred_server_id'] !== null ? $servers->find((int) $user['preferred_server_id']) : null;
        if ($server === null || !$servers->canJoin($server, $user)) {
            $server = $servers->default();
        }

        $localIp = trim((string) $xml->verify->local_ip);
        if ($localIp === '' || strlen($localIp) > 512 || !preg_match('#^[A-Za-z0-9+/=]+$#', $localIp)) {
            $localIp = 'na';
        }
        $port = (int) $xml->verify->port;
        if ($port < 1 || $port > 65535) {
            $port = 0;
        }

        try {
            $key = $this->slots->open($user, (int) $server['id'], $ip, $localIp, $port, $aesKey, $version);
        } catch (\Throwable $e) {
            error_log('FFS2play slot : ' . $e->getMessage());
            $response->error(207);
            return;
        }
        // un vol en cours se poursuit après une reconnexion (coupure réseau) ;
        // les vols réellement abandonnés sont clos par la purge

        Messages::setReplacement('server', (string) $server['name']);
        $response->info(1);
        $response->info(302);
        $response->set('loginStatus', 1);
        $response->set('key', $key);
        $response->set('atc', 1);
        $slot = $this->slots->findByUser((int) $user['id']);
        $response->setWhazzup($this->slots->whazzup($slot, $aesKey));
    }

    private function liveUpdate(\SimpleXMLElement $xml, string $ip, XmlResponse $response): void
    {
        $slot = $this->slots->findByKey(trim((string) $xml->switch->key));
        if ($slot === null) {
            // seule erreur de la réponse : le client se déconnecte et pourra se reconnecter
            $response->error(220);
            return;
        }
        $userId = (int) $slot['user_id'];
        $sim = trim((string) $xml->liveupdate->sim);
        $this->slots->touch($userId, $ip, $sim);
        if (isset($xml->liveupdate)) {
            $position = FlightTracker::parse($xml->liveupdate, true);
            if ($position !== null) {
                $this->tracker->update($userId, (int) $slot['server_id'], $sim !== '' ? $sim : (string) $slot['sim'], $position);
            }
        }
        $response->setWhazzup($this->slots->whazzup($slot, (string) $slot['aes_key_plain']));
    }

    private function atc(\SimpleXMLElement $xml, string $ip): void
    {
        $slot = $this->slots->findByKey(trim((string) $xml->switch->key));
        if ($slot === null || !isset($xml->atc)) {
            return;
        }
        $this->slots->touch((int) $slot['user_id'], $ip);
        $position = FlightTracker::parse($xml->atc, false);
        if ($position !== null) {
            $this->tracker->update((int) $slot['user_id'], (int) $slot['server_id'], (string) $slot['sim'], $position);
        }
    }

    private function logout(\SimpleXMLElement $xml, XmlResponse $response): void
    {
        $slot = $this->slots->findByKey(trim((string) $xml->switch->key));
        if ($slot !== null) {
            $this->tracker->abort((int) $slot['user_id']);
            $this->slots->close((int) $slot['user_id']);
        }
        $response->info(2);
    }
}
