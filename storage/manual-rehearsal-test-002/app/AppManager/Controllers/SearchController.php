<?php
declare(strict_types=1);

namespace App\AppManager\Controllers;

use App\Core\SearchService;
use App\Core\Auth;

/**
 * Global data search API endpoint.
 * Handles text search and QR/barcode query resolution.
 * Permission-scoped via SearchService.
 */
final class SearchController
{
    public static function search(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!Auth::isLoggedIn()) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Not authenticated',
            ]);
            exit;
        }

        $user = Auth::user();
        $query = trim((string)($_GET['q'] ?? $_POST['q'] ?? ''));
        $candidateScope = trim((string)($_GET['scope'] ?? $_POST['scope'] ?? ''));
        if ($candidateScope !== '' && preg_match('/^[a-f0-9]{36}$/', $candidateScope) !== 1) {
            $candidateScope = '';
        }

        if (strlen($query) < 2) {
            echo json_encode([
                'success' => true,
                'query' => $query,
                'groups' => [],
            ]);
            exit;
        }

        $result = SearchService::search($query, $user, [
            'candidate_scope' => $candidateScope,
        ]);
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
