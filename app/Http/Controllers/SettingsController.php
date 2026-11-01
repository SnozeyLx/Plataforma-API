<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Settings\DepositSetting;
use App\Settings\WithdrawSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SettingsController extends Controller
{
    public function index() {
        $settings = Setting::select("id","group","name","payload")->orderBy("group", "asc")->get();

        return [
            "success" => true,
            "data" => $settings
        ];
    }


    public function store(Request $request, Response $response) {
        $request->validate([
            'group' => 'required|string',
            'name' => 'required|string',
            'payload' => 'required',
        ]);

        $setting = Setting::where("group", $request->input("group"))->where("name", $request->input("name"))->firstOrFail();
        if(!$setting) return abort(404);

        switch($request->input("group")) {
        case "deposit":
            $class = DepositSetting::class;
            break;
        case "withdraw":
            $class = WithdrawSetting::class;
            break;
        default:
            $class = null;
            break;
        }

       //(!$class) return response()->json(["success"=>false, "message"=>"Class not found"], 500);

        $init = new $class;
        $init->{$request->input("name")} = $request->input("payload");
        $init->save();

        return [
            "success" => true
        ];
    }
}
