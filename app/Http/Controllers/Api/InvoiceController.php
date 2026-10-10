<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\SunatService;
use Greenter\Report\XmlUtils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class InvoiceController extends Controller
{
    public function send(Request $request)
    {
        $company = Company::where('user_id', auth()->user()->id)->firstOrFail();

        $sunat = new SunatService();
        $see = $sunat->getSee($company);
        $invoice = $sunat->getInvoice();

        $result = $see->send($invoice);

        $sunat->saveXml($company, $invoice, $see);

        $response = $sunat->sunatResponse($result);

        $sunat->saveCdr($company, $invoice, $result);

        $response['hash'] = (new XmlUtils)->getHashSign($see->getFactory()->getLastXml());

        return response()->json([
            'result' => $response,
        ], 200);
    }
}
