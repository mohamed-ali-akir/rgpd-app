<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\ProfileResource;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): ProfileResource
    {
        return new ProfileResource($request->user()->profile()->firstOrNew());
    }

    public function update(UpdateProfileRequest $request): ProfileResource
    {
        $profile = $request->user()->profile()->updateOrCreate([], $request->validated());

        return new ProfileResource($profile);
    }
}
