<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SMSModuleController extends Controller
{
    public function sms_index()
    {
        return view('admin-views.business-settings.sms-index');
    }

    public function sms_update(Request $request, $module)
    {
        if ($module == 'log_sms') {
            DB::table('business_settings')->updateOrInsert(['key' => 'log_sms'], [
                'key'        => 'log_sms',
                'value'      => json_encode([
                    'status' => $request['status'],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if ($module == 'payvand_sms') {
            DB::table('business_settings')->updateOrInsert(['key' => 'payvand_sms'], [
                'key'        => 'payvand_sms',
                'value'      => json_encode([
                    'status'         => $request['status'],
                    'endpoint'       => $request['endpoint'],
                    'token'          => $request['token'],
                    'source_address' => $request['source_address'],
                    'otp_template'   => $request['otp_template'],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return back();
    }
}
