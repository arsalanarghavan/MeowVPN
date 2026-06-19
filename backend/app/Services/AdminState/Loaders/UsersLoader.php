<?php

namespace App\Services\AdminState\Loaders;

use App\Models\SvpUser;
use App\Services\AdminState\AdminRowFormatter;
use App\Services\AdminState\AdminStateContext;
use App\Services\AdminState\AdminStateResult;
use App\Services\AdminState\UserListQuery;

class UsersLoader extends AbstractLoader
{
    protected function shouldLoad(AdminStateContext $ctx): bool
    {
        return $ctx->needsUsersList() || $ctx->needsPendingUsers();
    }

    protected function load(AdminStateContext $ctx, AdminStateResult $result): void
    {
        if (! $this->tableExists('svp_users')) {
            return;
        }

        $unrestricted = $ctx->isAdmin && $ctx->resellerContextId === 0;

        if ($ctx->needsUsersList()) {
            $users = $this->loadUsersList($ctx, $result);
            $result->merge([
                'usersList' => AdminRowFormatter::usersListRows($users, $unrestricted),
            ]);
        }

        if ($ctx->needsPendingUsers()) {
            $pending = $this->loadPendingUsers($ctx, $result);
            $result->merge([
                'pendingUsers' => AdminRowFormatter::usersListRows($pending, $unrestricted),
            ]);
        }
    }

    /** @return array<int, mixed> */
    protected function loadUsersList(AdminStateContext $ctx, AdminStateResult $result): array
    {
        $p = $ctx->page('users');
        $q = SvpUser::query();
        UserListQuery::applyListFilters($q, $ctx->request, false);
        UserListQuery::applySearch($q, (string) $ctx->request->query('users_q', ''));

        if ($ctx->isReseller || $ctx->moderatableUserIds !== []) {
            UserListQuery::applyScope($q, $ctx->moderatableUserIds);
        }

        $total = (clone $q)->count();
        $result->setTotal('users', $total);

        return (clone $q)->offset($p['offset'])->limit($p['per_page'])->get()->all();
    }

    /** @return array<int, mixed> */
    protected function loadPendingUsers(AdminStateContext $ctx, AdminStateResult $result): array
    {
        $p = $ctx->page('pendingUsers');
        $q = SvpUser::query();
        UserListQuery::applyListFilters($q, $ctx->request, true);
        UserListQuery::applySearch($q, (string) $ctx->request->query('users_q', ''));

        if ($ctx->isReseller || $ctx->moderatableUserIds !== []) {
            UserListQuery::applyScope($q, $ctx->moderatableUserIds);
        }

        $total = (clone $q)->count();
        $result->setTotal('pendingUsers', $total);

        return (clone $q)->offset($p['offset'])->limit($p['per_page'])->get()->all();
    }
}
