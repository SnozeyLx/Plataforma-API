<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Rules\CpfValidation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Facades\JWTAuth;


class AuthController extends Controller
{
    /**
     * @unauthenticated
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string']
        ]);

        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        return [
            "success" => true,
            "token" => $token
        ];
    }

    public function refresh()
    {
        $token = Auth::guard("api")->refresh();

        return [
            "success" => true,
            "token" => $token
        ];
    }

    /**
     * @unauthenticated
     */

public function register(Request $request)
{
       try {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'nick_name' => 'nullable|string|max:255',

            'email' => 'required|string|email|max:255|unique:users,email',

            // ✅ unique dentro do array e apontando a coluna cpf
            'cpf' => ['required', 'string', new CpfValidation, 'unique:users,cpf'],

            // alinhe com o front se quiser 8-30
            'password' => 'required|string|min:8|max:30',

            'birth_day' => 'nullable|date',
            'phone' => 'nullable|string|max:20|unique:users,phone',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:50',
            'post_code' => 'nullable|string|max:10',
            'pix' => 'nullable|string|max:255',
            'tracker' => 'nullable|string|max:255',
        ], [
            // ✅ mensagens PT-BR direto aqui (rápido e garantido)
            'phone.unique' => 'Este Telefone já está cadastrado.',
            'email.unique' => 'Este email já está cadastrado.',
            'cpf.unique'   => 'Este CPF já está cadastrado.',
            'email.email'  => 'Email inválido.',
            'email.required' => 'Email é obrigatório.',
            'cpf.required' => 'CPF é obrigatório.',
            'password.required' => 'Senha é obrigatória.',
            'password.min' => 'A senha deve ter no mínimo 8 caracteres.',
            'password.max' => 'A senha deve ter no máximo 30 caracteres.',
        ]);

    } catch (ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Bad request',
            'errors'  => $e->errors(),
        ], 400);
    }

    $nickNameFromEmail = strstr($request->email, '@', true);
    $nickName = $request->nick_name ?? $nickNameFromEmail;

    $user = User::create([
        'name' => $nickName,
        'last_name' => $request->last_name,
        'nick_name' => $nickName,
        'email' => $request->email,
        'password' => bcrypt($request->password),
        'birth_day' => '2000-12-22',
        'phone' => $request->phone,
        'address' => $request->address,
        'city' => $request->city,
        'state' => $request->state,
        'cpf' => $request->cpf,
        'post_code' => $request->post_code,
        'pix' => $request->cpf,
        'pix_type' => "CPF",
        'tracker' => $request->tracker,
        'image_profile' => '1',
    ]);

    $token = JWTAuth::fromUser($user);

    return response()->json([
        'success' => true,
        'user'    => $user,
        'token'   => $token,
    ]);
}

}
