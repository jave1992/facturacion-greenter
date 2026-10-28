<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\SunatService;
use App\Traits\SunatTrait;
use Greenter\Report\XmlUtils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InvoiceController extends Controller
{
    use SunatTrait;

    public function send(Request $request)
    {
        $request->validate([
            'company' => 'required|array',
            'company.address' => 'required|array',
            'client' => 'required|array',
            'details' => 'required|array',
            'details.*' => 'required|array',
        ]);

        $data = $request->all();

        $company = Company::where('user_id', Auth::id())->where('ruc', $data['company']['ruc'])->firstOrFail();

        $this->setTotales($data);
        $this->setLeyendas($data);

        $sunat = new SunatService();
        $see = $sunat->getSee($company);
        $invoice = $sunat->getInvoice($data);

        $result = $see->send($invoice);

        $sunat->saveInvoiceXml($company, $invoice, $see);

        $response = $sunat->sunatResponse($result);

        $sunat->saveInvoiceCdr($company, $invoice, $result);

        $response['hash'] = (new XmlUtils)->getHashSign($see->getFactory()->getLastXml());

        return response()->json([
            'result' => $response,
        ], 200);
    }

    public function xml(Request $request)
    {
        $request->validate([
            'company' => 'required|array',
            'company.address' => 'required|array',
            'client' => 'required|array',
            'details' => 'required|array',
            'details.*' => 'required|array',
        ]);

        $data = $request->all();

        $company = Company::where('user_id', Auth::id())->where('ruc', $data['company']['ruc'])->firstOrFail();

        $this->setTotales($data);
        $this->setLeyendas($data);

        $sunat = new SunatService();
        $see = $sunat->getSee($company);
        $invoice = $sunat->getInvoice($data);

        $response = [
            'xml' => $see->getXmlSigned($invoice),
            'hash' => (new XmlUtils)->getHashSign($see->getFactory()->getLastXml()),
        ];

        return response()->json($response, 200);
    }

    public function pdf(Request $request)
    {
        $request->validate([
            'company' => 'required|array',
            'company.address' => 'required|array',
            'client' => 'required|array',
            'details' => 'required|array',
            'details.*' => 'required|array',
        ]);

        $data = $request->all();

        $company = Company::where('user_id', Auth::id())->where('ruc', $data['company']['ruc'])->firstOrFail();

        $this->setTotales($data);
        $this->setLeyendas($data);

        $sunat = new SunatService();
        $see = $sunat->getSee($company);
        $invoice = $sunat->getInvoice($data);

        $result = $see->send($invoice);

        $response = $sunat->sunatResponse($result);

        $hash = (new XmlUtils)->getHashSign($see->getFactory()->getLastXml());

        $sunat->generatePdfReport($invoice, $hash);
    }
}
