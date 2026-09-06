<?php

namespace App\Core;

class EntityContext
{
    public ?int $userId;
    public array $roles;
    public ?string $app;
    public ?string $route;
    public array $assignments;
    public array $request;
    public array $flags;

    public function __construct(
        ?int $userId = null,
        array $roles = [],
        ?string $app = null,
        ?string $route = null,
        array $assignments = [],
        array $request = [],
        array $flags = []
    ) {
        $this->userId = $userId;
        $this->roles = $roles;
        $this->app = $app;
        $this->route = $route;
        $this->assignments = $assignments;
        $this->request = $request;
        $this->flags = $flags;
    }

    public static function anonymous(): self
    {
        return new self();
    }

    public static function admin(?int $userId = 1): self
    {
        return new self($userId, ['admin']);
    }

    public static function fromUser(?array $user): self
    {
        if ($user === null) {
            return self::anonymous();
        }

        $userId = (int)($user['id'] ?? 0) ?: null;
        $roles = [];

        $authorityRole = strtolower(trim((string)($user['authority_role'] ?? '')));
        if ($authorityRole !== '') {
            $roles[] = $authorityRole;
        }

        $aclRoles = (array)($user['acl_roles'] ?? []);
        foreach ($aclRoles as $role) {
            $roleStr = is_string($role) ? trim($role) : '';
            if ($roleStr !== '' && !in_array($roleStr, $roles, true)) {
                $roles[] = $roleStr;
            }
        }

        return new self($userId, $roles);
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function getFlag(string $key, mixed $default = null): mixed
    {
        return $this->flags[$key] ?? $default;
    }

    public function withFlag(string $key, mixed $value): self
    {
        $clone = clone $this;
        $clone->flags[$key] = $value;
        return $clone;
    }

    public function withRequest(array $request): self
    {
        $clone = clone $this;
        $clone->request = $request;
        return $clone;
    }
}
