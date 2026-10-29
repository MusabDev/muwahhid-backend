<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $users = User::query()
            ->when($request->filled('q'), fn ($query) => $query->where(fn ($inner) => $inner
                ->where('name', 'ILIKE', '%'.$request->string('q').'%')
                ->orWhere('email', 'ILIKE', '%'.$request->string('q').'%')))
            ->latest()
            ->paginate($request->integer('per_page', 50));

        return UserResource::collection($users);
    }

    public function update(Request $request, User $user): UserResource
    {
        $data = $request->validate(['is_admin' => ['required', 'boolean']]);

        abort_if($user->is($request->user()) && ! $data['is_admin'], 422, 'You cannot remove your own admin access.');

        $user->forceFill($data)->save();

        return new UserResource($user);
    }
}
