<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Controllers;

use App\Core\Auth;
use App\Core\View;
use Apps\Manufacturing\Services\HandoffBoardService;

final class HandoffBoardController
{
    /**
     * @param array<string,mixed>|null $user
     */
    public static function index(View $view, ?array $user = null): void
    {
        $board = HandoffBoardService::build($_GET, $user);
        $view->render('Manufacturing::handoffs/index.php', [
            'pageTitle' => 'Cross-Role Handoff Board',
            'board' => $board,
        ]);
    }

    /**
     * @param array<string,mixed> $input
     */
    public static function assignOwner(array $input): void
    {
        HandoffBoardService::assignOwner($input);

        $redirect = self::normalizeRedirect((string)($input['redirect'] ?? HandoffBoardService::canonicalUrl()));
        header('Location: ' . $redirect, true, 302);
        exit;
    }

    /**
     * @param array<string,mixed> $input
     */
    public static function escalate(array $input): void
    {
        HandoffBoardService::escalate($input);

        $redirect = self::normalizeRedirect((string)($input['redirect'] ?? HandoffBoardService::canonicalUrl()));
        header('Location: ' . $redirect, true, 302);
        exit;
    }

    private static function normalizeRedirect(string $redirect): string
    {
        $redirect = trim($redirect);
        if ($redirect === '' || !str_starts_with($redirect, '/')) {
            return HandoffBoardService::canonicalUrl();
        }

        if (str_starts_with($redirect, HandoffBoardService::legacyUrl())) {
            return HandoffBoardService::canonicalUrlWithQueryFromLegacy($redirect);
        }

        return $redirect;
    }
}
