<?php
declare(strict_types=1);

namespace Platform\Search;

final class SearchProviderContext
{
    private readonly \Closure $pathAllowed;
    private readonly \Closure $scopeClause;
    private readonly \Closure $tableExpressions;
    private readonly \Closure $phraseWhere;
    private readonly \Closure $tokenWhere;
    private readonly \Closure $queryTokens;
    private readonly \Closure $matches;

    /**
     * @param array<string,mixed> $user
     * @param array<string,mixed> $scopeContext
     * @param callable(string):bool $pathAllowed
     * @param callable(string,array<string,string>,string):array{0:string,1:array<int,int|string>} $scopeClause
     * @param callable(string,array<int,string>):array<int,string> $tableExpressions
     * @param callable(array<int,string>,string):array{0:string,1:array<int,string>} $phraseWhere
     * @param callable(array<int,string>,array<int,string>,bool):array{0:string,1:array<int,string>} $tokenWhere
     * @param callable(string):array<int,string> $queryTokens
     * @param callable(string,array<int,string>):bool $matches
     */
    public function __construct(
        public readonly array $user,
        public readonly array $scopeContext,
        callable $pathAllowed,
        callable $scopeClause,
        callable $tableExpressions,
        callable $phraseWhere,
        callable $tokenWhere,
        callable $queryTokens,
        callable $matches
    ) {
        $this->pathAllowed = \Closure::fromCallable($pathAllowed);
        $this->scopeClause = \Closure::fromCallable($scopeClause);
        $this->tableExpressions = \Closure::fromCallable($tableExpressions);
        $this->phraseWhere = \Closure::fromCallable($phraseWhere);
        $this->tokenWhere = \Closure::fromCallable($tokenWhere);
        $this->queryTokens = \Closure::fromCallable($queryTokens);
        $this->matches = \Closure::fromCallable($matches);
    }

    public function pathAllowed(string $path): bool
    {
        return (bool)($this->pathAllowed)($path);
    }

    /** @param array<string,string> $columnToScopeKey @return array{0:string,1:array<int,int|string>} */
    public function scopeClause(string $table, array $columnToScopeKey, string $alias = ''): array
    {
        return ($this->scopeClause)($table, $columnToScopeKey, $alias);
    }

    /** @param array<int,string> $baseColumns @return array<int,string> */
    public function tableExpressions(string $table, array $baseColumns): array
    {
        return ($this->tableExpressions)($table, $baseColumns);
    }

    /** @param array<int,string> $expressions @return array{0:string,1:array<int,string>} */
    public function phraseWhere(array $expressions, string $query): array
    {
        return ($this->phraseWhere)($expressions, $query);
    }

    /** @param array<int,string> $expressions @param array<int,string> $tokens @return array{0:string,1:array<int,string>} */
    public function tokenWhere(array $expressions, array $tokens, bool $requireAllTokens = true): array
    {
        return ($this->tokenWhere)($expressions, $tokens, $requireAllTokens);
    }

    /** @return array<int,string> */
    public function queryTokens(string $query): array
    {
        return ($this->queryTokens)($query);
    }

    /** @param array<int,string> $fragments */
    public function matches(string $query, array $fragments): bool
    {
        return (bool)($this->matches)($query, $fragments);
    }
}
