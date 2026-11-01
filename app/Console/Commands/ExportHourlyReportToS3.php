<?php

namespace App\Console\Commands;

use App\Enums\BetStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Aws\S3\S3Client;
use Carbon\Carbon;


class ExportHourlyReportToS3 extends Command
{
    protected $signature = 'report:export-hourly';
    protected $description = 'Exporta relatório horário para S3 (somente users com tracker preenchido)';

    public function handle(): int
    {
        $to = Carbon::now()->subHours(-1)->startOfHour(); 
        $to2 = Carbon::now()->subHours(0)->startOfHour(); 
        $from = $to2->copy()->subHour(); 
        //$to = Carbon::now();
        //$from = $to->copy()->subHour();
        $operationDate = $to2->format('Y-m-d H:i:s');
        //$toStr   = $to->format('Y-m-d H:i:s');
        //$fromStr = $from->format('Y-m-d H:i:s');


        $registers = DB::table('users')
            ->whereBetween('users.created_at', [$from, $to])
            //->whereNotNull('users.tracker')
            ->select('users.id as user_id')
            ->get();

        $linesRegis = [];
        $linesRegis[] = "registration_date,tracker,user_id,country,user_name,email,currency";

        foreach ($registers as $r) {
            $user = DB::table('users')
                ->where('id', $r->user_id)
                ->first();

            $linesRegis[] = implode(',', [
                $user->created_at,
                $user->tracker,
                $user->id,
                'BR',
                $user->name,
                $user->email,
                'BRL',
            ]);
        }
         if ($registers->isEmpty()) {
            $this->info('Nenhum usuário registrou no período.');
            //return self::SUCCESS;
        }



        $rows = DB::table('bets')
            ->join('users', 'bets.user_id', '=', 'users.id')
            //->whereBetween('bets.created_at', [$from, $to])
            ->where('bets.created_at', '>=', $from)
            ->where('bets.created_at', '<',  $to2)
            //->whereNotNull('users.tracker')
            //->whereRaw("TRIM(users.tracker) <> ''")
            ->groupBy('bets.user_id')
            ->selectRaw('
                bets.user_id as user_id,
                SUM(bets.value) as turnover,
                COUNT(*) as number_of_bets,
                COUNT(DISTINCT bets.game_id) as number_of_games
            ')
            ->get()
            ->keyBy('user_id');

        if ($rows->isEmpty()) {
            $this->info('Nenhum usuário apostou no período.');
            //return self::SUCCESS;
        }
        //dd($from->toDateTimeString(), $to->toDateTimeString());dd($from->toDateTimeString(), $to->toDateTimeString());

        $cashUsers = DB::table('balance_statements')
            ->whereBetween('created_at', [$from, $to2])
            ->where('status', 'paid')
            ->distinct()
            ->pluck('user_id');

        $cashflow = DB::table('balance_statements')
            ->whereBetween('created_at', [$from, $to2])
            ->where('status', 'paid')
            //->whereIn('user_id', $rows->keys())
            ->selectRaw('
        user_id,
        SUM(CASE WHEN type = "deposit" THEN value ELSE 0 END) as deposits,
        SUM(CASE WHEN type = "withdraw" THEN value ELSE 0 END) as withdrawals
            ')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        if ($cashflow->isEmpty()) {
            $this->info('Nenhum usuário movimentou saldo no período.');
            //return self::SUCCESS;
        }    

        $firstDeposits = DB::table('balance_statements as bs')
            ->where('bs.status', 'paid')
            ->where('bs.type', 'deposit')
            ->whereIn('bs.user_id', $cashflow->keys())
            ->whereRaw('bs.created_at = (
                SELECT MIN(created_at)
                FROM balance_statements
                WHERE user_id = bs.user_id
                AND status = "paid"
                AND type = "deposit"
            )')
            ->select('bs.user_id', 'bs.value')
            ->get()
            ->keyBy('user_id');




        $wins = DB::table('bets')
            ->join('games', 'bets.game_id', '=', 'games.id')
            ->whereBetween('bets.created_at', [$from, $to2])
            ->where('bets.status', BetStatus::Win)
            ->whereIn('bets.user_id', $rows->keys())
            ->select('bets.user_id', 'bets.value', 'bets.card_id', 'games.card_left', 'games.card_right')
            ->get();

        $winningsByUser = [];
        foreach ($wins as $w) {
            $userId = (int) $w->user_id;
            $value = (float) $w->value;

            $winningsByUser[$userId] ??= 0.0;

            if ((int) $w->card_id === (int) $w->card_left) {
                $winningsByUser[$userId] += $value * 1.95;
            } 
            elseif ((int) $w->card_id === (int) $w->card_right) {
                $winningsByUser[$userId] += $value * 1.95;
            }
        }

        $combined = collect($rows)->union($cashflow);


        $lines = [];
        $lines[] = "operation_date,user_id,first_deposit,deposits,withdrawals,bonuses,ggr,turnover,number_of_bets,currency";

        foreach ($combined as $userId => $data) {
            $turnover = (float) ($rows[$userId]->turnover ?? 0);
            $numberOfBets = (int) ($rows[$userId]->number_of_bets ?? 0);
            // $numberOfGames = (int) $data->number_of_games;

            $totalWinningsPaid = (float) ($winningsByUser[(int)$userId] ?? 0.0);
            $ggr = $totalWinningsPaid - $turnover;

            $deposits = (float) ($cashflow[$userId]->deposits ?? 0);
            $withdrawals = (float) ($cashflow[$userId]->withdrawals ?? 0);


            $firstDeposit = (float) ($firstDeposits[$userId]->value ?? 0);

            
            
            $bonuses = 0.0;

            $lines[] = implode(',', [
                $operationDate,
                (int) $userId,
                number_format($firstDeposit, 2, '.', ''),
                number_format($deposits, 2, '.', ''),
                number_format($withdrawals, 2, '.', ''),
                number_format($bonuses, 2, '.', ''),
                number_format($ggr, 2, '.', ''),
                number_format($turnover, 2, '.', ''),
                $numberOfBets,
                'BRL',
            ]);
        }

        $csv = implode("\n", $lines) . "\n";
        $csvRegis = implode("\n", $linesRegis) . "\n";



        $date = now()->format('Y-m-d');
        $timestamps = now()->valueOf();

        $fileName = "RondaOnline_SALES_" . trim($date) . trim($timestamps) . ".csv";
        $fileNameRegis = "RondaOnline_REG_" . trim($date) . trim($timestamps) . ".csv";

        Storage::disk('public')->put($fileName, $csv);
        Storage::disk('public')->put($fileNameRegis, $csvRegis);

        Storage::disk('s3')->put($fileName, $csv, [
            // 'ACL' => 'public-read',
            'ContentType' => 'text/csv',
        ]);

        Storage::disk('s3')->put($fileNameRegis, $csvRegis, [
            // 'ACL' => 'public-read',
            'ContentType' => 'text/csv',
        ]);

        $this->info("PUT no S3 concluído: {$fileName} {$fileNameRegis}");

        // if (!Storage::disk('s3')->exists($fileName)) {
        //     $this->error("Arquivo não encontrado no S3 após upload: {$fileName}");
        //     return self::FAILURE;
        // }

        // if (!Storage::disk('s3')->exists($fileNameRegis)) {
        //     $this->error("Arquivo não encontrado no S3 após upload: {$fileNameRegis}");
        //     return self::FAILURE;
        // }

        // $this->info("✅ Confirmado no S3: arquivos existem.");





        return self::SUCCESS;
    }

    private function makeS3ClientFromLaravelConfig(): S3Client
    {
        $cfg = config('filesystems.disks.s3');

        $args = [
            'version' => 'latest',
            'region'  => $cfg['region'],
            'credentials' => [
                'key'    => $cfg['key'],
                'secret' => $cfg['secret'],
            ],
        ];

        // se você usa endpoint custom (minio, etc)
        if (!empty($cfg['endpoint'])) {
            $args['endpoint'] = $cfg['endpoint'];
            $args['use_path_style_endpoint'] = (bool) ($cfg['use_path_style_endpoint'] ?? false);
        }

        return new S3Client($args);
    }
}
