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

        $path_xml = 'invoices/' . $company->ruc . '/' . $invoice->getName() . '/' . $invoice->getName() . '.xml';

        Storage::put($path_xml, $see->getFactory()->getLastXml());

        $response = $sunat->sunatResponse($result);

        $path_cdr = 'invoices/' . $company->ruc . '/' . $invoice->getName() . '/R-' . $invoice->getName() . '.zip';

        Storage::put($path_cdr, $result->getCdrZip());

        $response['hash'] = (new XmlUtils)->getHashSign($see->getFactory()->getLastXml());
        
        return response()->json([
            'result' => $response,
        ], 200);
    }
}
