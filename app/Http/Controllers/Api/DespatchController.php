<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\SunatService;
use Greenter\Report\XmlUtils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DespatchController extends Controller
{
    public function send(Request $request)
    {
        $data = $request->all();

        $company = Company::where('user_id', Auth::id())->where('ruc', $data['company']['ruc'])->firstOrFail();

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

    public function xml(Request $request)
    {
        $data = $request->all();

        $company = Company::where('user_id', Auth::id())->where('ruc', $data['company']['ruc'])->firstOrFail();

        $sunat = new SunatService();
        $see = $sunat->getSee($company);
        $despatch = $sunat->getDespatch($data);

        $response = [
            'xml' => $see->getXmlSigned($despatch),
            'hash' => (new XmlUtils)->getHashSign($see->getFactory()->getLastXml()),
        ];

        return response()->json($response, 200);
    }

    public function pdf(Request $request) {}
}
