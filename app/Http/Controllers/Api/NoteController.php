<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\SunatService;
use App\Traits\SunatTrait;
use Greenter\Report\XmlUtils;
use Illuminate\Http\Request;

class NoteController extends Controller
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

        $company = Company::where('user_id', auth()->user()->id)->where('ruc', $data['company']['ruc'])->firstOrFail();

        $this->setTotales($data);
        $this->setLeyendas($data);

        $sunat = new SunatService();
        $see = $sunat->getSee($company);
        $note = $sunat->getNote($data);

        $result = $see->send($note);

        $sunat->saveNoteXml($company, $note, $see);

        $response = $sunat->sunatResponse($result);

        $sunat->saveNoteCdr($company, $note, $result);

        $response = $sunat->sunatResponse($result);

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

        $company = Company::where('user_id', auth()->user()->id)->where('ruc', $data['company']['ruc'])->firstOrFail();

        $this->setTotales($data);
        $this->setLeyendas($data);

        $sunat = new SunatService();
        $see = $sunat->getSee($company);
        $note = $sunat->getNote($data);

        $response = [
            'xml' => $see->getXmlSigned($note),
            'hash' => (new XmlUtils)->getHashSign($see->getFactory()->getLastXml()),
        ];

        return response()->json($response, 200);
    }

    public function pdf(Request $request)
    {
        
    }
}
