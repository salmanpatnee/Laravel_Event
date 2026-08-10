<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\RoleEnum;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RegisterController extends Controller
{
    public function create()
    {
        $roles = RoleEnum::cases();

        return view('auth.register', compact('roles'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => ['required', Rule::enum(RoleEnum::class)],
        ]);

        $user = User::create($validated);

        auth()->login($user);

        return redirect()->route('login.create')->with('success', 'Registration successful!');
    }
}
