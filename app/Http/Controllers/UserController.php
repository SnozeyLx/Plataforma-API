<?php

namespace App\Http\Controllers;

use App\Enums\UserPixType;
use App\Enums\UserRole;
use App\Models\Bet;
use App\Models\Game;
use App\Models\Room;
use App\Models\User;
use App\Rules\CpfValidation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Intervention\Image\Laravel\Facades\Image;
use Intervention\Image\Encoders\AutoEncoder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{

    /**
     * users.update (admin)
     *
     * `Admin`
     */
    public function update(Request $request, User $user) {
        $data = $request->validate([
            'name' => ["nullable","string"],
            'last_name' => ["nullable","string"],
            'nick_name' => ["nullable","string"],
            'role' => ["nullable",Rule::enum(UserRole::class)],
            'cpf' => ['nullable', 'string', new CpfValidation],
            'birth_day' => 'nullable|date', // YYYY-MM-DD
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:50',
            'post_code' => 'nullable|string|max:10',
            'pix' => 'nullable|string|max:255',
            'pix_type' => ["nullable",Rule::enum(UserPixType::class)],
        ]);

        $user->update($data);

        return [
            "success" => true,
            /** @var User */
            "user" => $user
        ];
    }

    public function profile(Request $request) {
        return [
            "success" => true,
            /** @var User */
            "profile" => $request->user()
        ];
    }

    public function profile_update(Request $request) {
        $data = $request->validate([
            'name' => ["nullable","string"],
            'cpf' => ['nullable', 'string', new CpfValidation],
            'birth_day' => 'nullable|date', // YYYY-MM-DD
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:50',
            'post_code' => 'nullable|string|max:10',
            'pix' => 'nullable|string|max:255',
            'pix_type' => ["nullable",Rule::enum(UserPixType::class)],
            'image_profile' => ["nullable","string"]
        ]);

        $request->user()->update($data);

        return [
            "success" => true,
            /** @var User */
            "user" => $request->user()
        ];
    }


     public function password_update(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'current_password' => ['required'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        // Verifica se a senha atual está correta
        if (! Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['A senha atual está incorreta.'],
            ]);
        }

        // Atualiza a senha
        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'message' => 'Senha atualizada com sucesso.'
        ]);
    }


    public function image_update(Request $request) {
        $request->validate([
            /** Max 5mb */
            'image' => [
                'required',
                'image',
                File::image()->max("5mb")
            ]
        ]);

        try {
            $imageFile = $request->file('image');
            $image = Image::read($imageFile);

            if ($image->width() > 800 || $image->height() > 800) {
                $image->scale(width: 800, height: 800);
            }

            $encodedImage = $image->toJpeg(80);

            $filename = Str::uuid() . '.jpg';

            $path = 'pics/' . $filename;
            Storage::disk("public")->put($path, $encodedImage);

            $request->user()->update(["image_profile"=>$path]);

            return [
                "success" => true,
                /** @var User */
                "user" => $request->user()
            ];

        } catch (\Exception $e) {
            Log::emergency($e);
            return response()->json([
                'message' => 'Não foi possível enviar imagem',
            ], 500);
        }

    }


    /**
     * users.rooms
     *
     * Show the rooms from the logged user
     */
    public function rooms(Request $request) {
        $rooms = Room::where("user_id", $request->user()->id)->orderBy("id", "desc")->paginate();

        return [
            "success" => true,
            /** @var LengthAwarePaginator<Room> **/
            "rooms" => $rooms
        ];
    }


    /**
     * users.bets
     *
     * Show the bets from the logged user
     */
    public function bets(Request $request) {
        $bets = Bet::where("user_id", $request->user()->id)->with("game")->orderBy("id", "desc")->paginate();

        return [
            "success" => true,
            /** @var LengthAwarePaginator<Room> **/
            "bets" => $bets
        ];
    }

    /**
     * users.game_history
     *
     * Show the game history from the logged user
     */
    public function gameHistory(Request $request) {
        // Buscar IDs de jogos onde o usuário fez apostas
        $gameIds = Bet::where('user_id', $request->user()->id)
            ->distinct()
            ->pluck('game_id');

        // Buscar os jogos correspondentes
        $games = Game::whereIn('id', $gameIds)
            ->with(['room', 'bets' => function($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            }])
            ->orderBy('id', 'desc')
            ->paginate();

        return [
            "success" => true,
            /** @var LengthAwarePaginator<Game> **/
            "games" => $games
        ];
    }

}
