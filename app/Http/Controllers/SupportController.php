<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class SupportController extends Controller
{
    public function send(Request $request)
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:5000'],
            'from_email' => ['nullable', 'email', 'max:150'], // opcional
            'from_name'  => ['nullable', 'string', 'max:150'], // opcional
        ]);

        $to = config('support.email_to', 'suporte@rondaonline.br');

        Mail::raw($data['message'], function ($mail) use ($data, $to) {
            $mail->to($to)
                ->subject($data['subject']);

            // Se quiser setar reply-to com dados do usuário:
            if (!empty($data['from_email'])) {
                $mail->replyTo($data['from_email'], $data['from_name'] ?? null);
            }
        });

        return response()->json([
            'ok' => true,
            'message' => 'Mensagem enviada com sucesso!'
        ], 200);
    }
}
