<?php
declare(strict_types=1);

namespace Apps\Shell\Services;

use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use Ratchet\WebSocket\WsServer;
use SplObjectStorage;
use Throwable;
use App\Core\Auth;
use Apps\Platform\Services\UserAssignmentContext;

/**
 * Operator Realtime Service
 * 
 * WebSocket message handler for operator layer real-time KPI updates.
 * Manages operator subscriptions and broadcasts KPI changes.
 * 
 * Features:
 * - Per-operator-per-view subscription tracking
 * - CSRF token validation on connect
 * - KPI data fetching via OperatorRealtimeKpiProvider
 * - Graceful fallback (WebSocket optional)
 * 
 * Usage (from bin/operator-realtime-server):
 *   $realtime = new OperatorRealtimeService();
 *   $app = new WsServer($realtime);
 *   $server = IoServer::factory($app, 8001);
 *   $server->run();
 */
final class OperatorRealtimeService implements MessageComponentInterface
{
    /** @var SplObjectStorage<ConnectionInterface, array{username: string, view: string, prefs: array}> */
    private SplObjectStorage $clients;
    
    private OperatorRealtimeKpiProvider $kpiProvider;
    private OperatorRealtimeCacheManager $cacheManager;

    /**
     * Optional test seam for isolated authentication fixtures. Production uses
     * the existing Auth and UserAssignmentContext authorities below.
     *
     * @var callable(string,string,string,string):bool|null
     */
    private $connectionAuthorizer;

    public function __construct(?callable $connectionAuthorizer = null)
    {
        $this->clients = new SplObjectStorage();
        $this->kpiProvider = new OperatorRealtimeKpiProvider();
        $this->cacheManager = new OperatorRealtimeCacheManager();
        $this->connectionAuthorizer = $connectionAuthorizer;
    }

    /**
     * Handle incoming WebSocket connection
     */
    public function onOpen(ConnectionInterface $conn): void
    {
        $uri = $conn->httpRequest->getUri();
        ['auth' => $auth, 'username' => $username, 'view' => $view] = $this->parseConnectionContext(
            $uri->getPath(),
            $uri->getQuery()
        );

        // Validate CSRF token and session
        $cookieHeader = $this->cookieHeader($conn->httpRequest->getHeader('Cookie'));
        if (!$this->validateConnection($auth, $username, $view, $cookieHeader)) {
            $conn->send(json_encode([
                'type' => 'error',
                'code' => 'unauthorized',
                'message' => 'Realtime authentication failed',
            ]));
            $conn->close();
            return;
        }

        // Store connection with subscription metadata
        $this->clients->offsetSet($conn, [
            'username' => $username,
            'view' => $view,
            'auth_token' => $auth,
            'session_cookie' => $cookieHeader,
            'prefs' => [
                'refresh_interval_ms' => 5000,
                'kpi_keys' => ['summary', 'critical_orders'],
            ],
            'last_heartbeat' => time(),
        ]);

        // Send welcome message
        $conn->send(json_encode([
            'type' => 'connected',
            'message' => "Subscribed to {$view} updates for {$username}",
            'timestamp' => time(),
        ]));

        echo "[" . date('Y-m-d H:i:s') . "] Client connected: {$username}/{$view} (ID: {$conn->resourceId})\n";
    }

    /**
     * Handle incoming client message
     */
    public function onMessage(ConnectionInterface $from, $msg): void
    {
        try {
            $data = json_decode($msg, true);
            if (!is_array($data)) {
                return;
            }

            $type = (string)($data['type'] ?? '');
            $meta = $this->clients->offsetGet($from);

            if (!is_array($meta) || !$this->isConnectionAuthorized($meta)) {
                $this->sendUnauthorized($from);
                $from->close();
                return;
            }

            match ($type) {
                'subscribe' => $this->handleSubscribe($from, $data, $meta),
                'refresh' => $this->handleRefresh($from, $meta),
                'ping' => $this->handlePing($from),
                default => null,
            };
        } catch (Throwable $e) {
            error_log('[OperatorRealtimeService] Error processing message: ' . $e->getMessage());
        }
    }

    /**
     * Handle client disconnect
     */
    public function onClose(ConnectionInterface $conn): void
    {
        $meta = $this->clients->offsetGet($conn);
        if ($meta) {
            echo "[" . date('Y-m-d H:i:s') . "] Client disconnected: {$meta['username']}/{$meta['view']} (ID: {$conn->resourceId})\n";
        }
        $this->clients->offsetUnset($conn);
    }

    /**
     * Handle connection error
     */
    public function onError(ConnectionInterface $conn, Throwable $e): void
    {
        error_log('[OperatorRealtimeService] Error on connection ' . $conn->resourceId . ': ' . $e->getMessage());
        $conn->close();
    }

    /**
     * Validate CSRF token and session authorization
     */
    private function validateConnection(string $token, string $username, string $view = '', string $cookieHeader = ''): bool
    {
        if ($this->connectionAuthorizer !== null) {
            return (bool)($this->connectionAuthorizer)($token, $username, $view, $cookieHeader);
        }

        return $this->validateAgainstSession($token, $username, $view, $cookieHeader);
    }

    /**
     * Validate the existing browser session and operator route authority.
     *
     * The realtime worker has no independent credential store. A missing
     * session cookie, unavailable Auth backend, invalid CSRF token, inactive
     * account, identity mismatch, or denied operator route fails closed.
     */
    private function validateAgainstSession(string $token, string $username, string $view, string $cookieHeader): bool
    {
        if ($token === '' || $username === '' || !$this->isRealtimeView($view) || $cookieHeader === '') {
            return false;
        }

        try {
            $this->seedSessionCookie($cookieHeader);
            Auth::bootSession();
            if (!Auth::isLoggedIn() || !hash_equals(Auth::csrfToken(), $token)) {
                return false;
            }

            $user = Auth::user();
            if (!is_array($user) || (int)($user['id'] ?? 0) <= 0) {
                return false;
            }

            $context = UserAssignmentContext::context()->resolveUserContext($user);
            $authorityRole = strtolower(trim((string)($context['authority_role'] ?? $user['authority_role'] ?? '')));
            if ($authorityRole === 'tv_display') {
                return false;
            }

            $identityHandle = \Apps\Shell\Services\WorkspaceWrapperRegistry::handleFromIdentity([
                'username' => (string)($user['username'] ?? ''),
                'email' => (string)($user['email'] ?? ''),
            ]);
            $requestedHandle = \Apps\Shell\Services\WorkspaceWrapperRegistry::normalizeHandle($username);
            $isPrivileged = in_array($authorityRole, ['platform_admin', 'app_admin', 'sys_admin'], true);
            if ($identityHandle === '' || $requestedHandle === '' || ($identityHandle !== $requestedHandle && !$isPrivileged)) {
                return false;
            }

            $decision = UserAssignmentContext::accessPolicy()->routeAccessDecision(
                $user,
                '/u/' . $requestedHandle . '/' . $view,
                'GET'
            );
            return ($decision['allowed'] ?? false) === true;
        } catch (Throwable $e) {
            error_log('[OperatorRealtimeService] Authentication authority unavailable: ' . $e->getMessage());
            return false;
        } finally {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
        }
    }

    private function isConnectionAuthorized(array $meta): bool
    {
        return $this->validateConnection(
            (string)($meta['auth_token'] ?? ''),
            (string)($meta['username'] ?? ''),
            (string)($meta['view'] ?? ''),
            (string)($meta['session_cookie'] ?? '')
        );
    }

    private function sendUnauthorized(ConnectionInterface $conn): void
    {
        $conn->send(json_encode([
            'type' => 'error',
            'code' => 'unauthorized',
            'message' => 'Realtime authentication failed',
        ]));
    }

    private function isRealtimeView(string $view): bool
    {
        return in_array($view, [
            'dashboard', 'production', 'dispatch', 'coverage', 'qc',
            'machines', 'assembly', 'materials',
        ], true);
    }

    private function cookieHeader(string|array|null $header): string
    {
        return is_array($header) ? implode('; ', $header) : trim((string)$header);
    }

    private function seedSessionCookie(string $cookieHeader): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        $configuredName = trim((string)getenv('ERP_SESSION_NAME'));
        if ($configuredName !== '' && preg_match('/^[A-Za-z0-9_-]{3,40}$/', $configuredName)) {
            session_name($configuredName);
        }

        $sessionName = session_name();
        foreach (explode(';', $cookieHeader) as $part) {
            $pair = explode('=', trim($part), 2);
            if (count($pair) === 2 && trim($pair[0]) === $sessionName) {
                $_COOKIE[$sessionName] = urldecode(trim($pair[1]));
                return;
            }
        }
    }

    /**
     * Handle subscription request with preferences
     */
    private function handleSubscribe(ConnectionInterface $conn, array $data, array &$meta): void
    {
        $prefs = (array)($data['preferences'] ?? []);

        $meta['prefs'] = [
            'refresh_interval_ms' => (int)($prefs['refresh_interval_ms'] ?? 5000),
            'kpi_keys' => (array)($prefs['kpi_keys'] ?? ['summary', 'critical_orders']),
        ];

        $this->clients->offsetSet($conn, $meta);

        $conn->send(json_encode([
            'type' => 'subscribed',
            'preferences' => $meta['prefs'],
            'timestamp' => time(),
        ]));

        // Immediately send current KPI snapshot
        $this->sendKpiUpdate($conn, $meta);
    }

    /**
     * Handle manual refresh request
     */
    private function handleRefresh(ConnectionInterface $conn, array $meta): void
    {
        // Invalidate cache for this user/view
        $this->cacheManager->invalidate($meta['username'], $meta['view']);

        // Send fresh KPI data
        $this->sendKpiUpdate($conn, $meta);
    }

    /**
     * Handle ping/heartbeat from client
     */
    private function handlePing(ConnectionInterface $conn): void
    {
        $meta = $this->clients->offsetGet($conn);
        if ($meta) {
            $meta['last_heartbeat'] = time();
            $this->clients->offsetSet($conn, $meta);
        }

        $conn->send(json_encode([
            'type' => 'pong',
            'timestamp' => time(),
        ]));
    }

    /**
     * Send KPI update to a single connection
     */
    private function sendKpiUpdate(ConnectionInterface $conn, array $meta): void
    {
        if (!$this->isConnectionAuthorized($meta)) {
            $this->sendUnauthorized($conn);
            $conn->close();
            return;
        }

        try {
            $data = $this->kpiProvider->getKpiForView(
                $meta['view'],
                $meta['username'],
                $meta['prefs']['kpi_keys'],
                $this->cacheManager
            );

            $conn->send(json_encode([
                'type' => 'kpi_update',
                'timestamp' => time(),
                ...$data,
            ]));
        } catch (Throwable $e) {
            error_log('[OperatorRealtimeService] Error fetching KPI for ' . $meta['username'] . '/' . $meta['view'] . ': ' . $e->getMessage());
        }
    }

    /**
     * Broadcast KPI update to all clients (called periodically by server loop)
     */
    public function broadcastKpiUpdate(): void
    {
        // Group connections by view + username
        $groups = [];
        foreach ($this->clients as $conn) {
            $meta = $this->clients->offsetGet($conn);
            $key = "{$meta['view']}:{$meta['username']}";

            if (!isset($groups[$key])) {
                $groups[$key] = [];
            }
            $groups[$key][] = $conn;
        }

        // For each group, fetch KPI once and broadcast to all
        foreach ($groups as $key => $conns) {
            [$view, $username] = explode(':', $key);
            
            try {
                // Get first connection to extract preferences
                $firstMeta = $this->clients->offsetGet($conns[0]);
                if (!is_array($firstMeta) || !$this->isConnectionAuthorized($firstMeta)) {
                    foreach ($conns as $conn) {
                        $this->sendUnauthorized($conn);
                        $conn->close();
                    }
                    continue;
                }
                $kpiKeys = $firstMeta['prefs']['kpi_keys'] ?? ['summary', 'critical_orders'];

                $data = $this->kpiProvider->getKpiForView(
                    $view,
                    $username,
                    $kpiKeys,
                    $this->cacheManager
                );

                $message = json_encode([
                    'type' => 'kpi_update',
                    'timestamp' => time(),
                    ...$data,
                ]);

                foreach ($conns as $conn) {
                    $conn->send($message);
                }
            } catch (Throwable $e) {
                error_log('[OperatorRealtimeService] Error broadcasting KPI for ' . $key . ': ' . $e->getMessage());
            }
        }
    }

    /**
     * @return array{auth:string,username:string,view:string}
     */
    public function parseConnectionContext(string $path, string $query): array
    {
        parse_str($query, $params);

        $auth = (string)($params['auth'] ?? '');
        $username = (string)($params['username'] ?? '');
        $view = (string)($params['view'] ?? '');

        $segments = array_values(array_filter(explode('/', trim($path, '/')), static fn ($segment) => $segment !== ''));
        if (($segments[0] ?? '') === 'operator') {
            $username = $username !== '' ? $username : (string)($segments[1] ?? '');
            $view = $view !== '' ? $view : (string)($segments[2] ?? '');
        }

        return [
            'auth' => $auth,
            'username' => $username,
            'view' => $view,
        ];
    }

    /**
     * Get connection count for monitoring
     */
    public function getConnectionCount(): int
    {
        return $this->clients->count();
    }

    /**
     * Get subscribers by view (for monitoring/diagnostics)
     */
    public function getSubscribersByView(): array
    {
        $result = [];
        foreach ($this->clients as $conn) {
            $meta = $this->clients->offsetGet($conn);
            $view = $meta['view'];
            if (!isset($result[$view])) {
                $result[$view] = 0;
            }
            $result[$view]++;
        }
        return $result;
    }
}
