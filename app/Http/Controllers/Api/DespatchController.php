<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\SunatService;
use Greenter\Report\XmlUtils;
use Illuminate\Http\Request;

class DespatchController extends Controller
{
    public function send(Request $request)
    {
        $data = $request->all();

        $company = Company::where('user_id', auth()->user()->id)->where('ruc', $data['company']['ruc'])->firstOrFail();

        $sunat = new SunatService();
        $despatch = $sunat->getDespatch($data);

        $api = $sunat->getSeeApi($company);
        $result = $api->send($despatch);

        $ticket = $result->getTicket();
        $result = $api->getStatus($ticket);

        $sunat->saveDespatchXml($company, $despatch, $api);
        $sunat->saveDespatchCdr($company, $despatch, $result);

        $response['hash'] = (new XmlUtils)->getHashSign($api->getLastXml());

        return response()->json([
            'result' => $response,
        ], 200);
    }

    public function xml(Request $request) {}

    public function pdf(Request $request) {}
}
