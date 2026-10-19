<?php

namespace App\Services;

use App\Models\Company as ModelsCompany;
use DateTime;
use Greenter\Model\Client\Client;
use Greenter\Model\Company\Address;
use Greenter\Model\Company\Company;
use Greenter\Model\Sale\FormaPagos\FormaPagoContado;
use Greenter\Model\Sale\Invoice;
use Greenter\Model\Sale\Legend;
use Greenter\Model\Sale\SaleDetail;
use Greenter\Report\HtmlReport;
use Greenter\Report\Resolver\DefaultTemplateResolver;
use Greenter\See;
use Greenter\Ws\Services\SunatEndpoints;
use Illuminate\Support\Facades\Storage;

class SunatService
{
    public function getSee($company)
    {
        $see = new See();
        $see->setCertificate(Storage::get($company->cert_path));
        $see->setService($company->production ? SunatEndpoints::FE_PRODUCCION : SunatEndpoints::FE_BETA);
        $see->setClaveSOL($company->ruc, $company->sol_user, $company->sol_pass);

        return $see;
    }

    public function getInvoice($data)
    {
        $invoice = (new Invoice())
            ->setUblVersion($data['ublVersion'] ?? '2.1') // UBL 2.1
            ->setTipoOperacion($data['tipoOperacion'] ?? null) // Venta - Catalog. 51
            ->setTipoDoc($data['tipoDoc'] ?? null) // Factura - Catalog. 01
            ->setSerie($data['serie'] ?? null)
            ->setCorrelativo($data['correlativo'] ?? null)
            ->setFechaEmision(new DateTime($data['fechaEmision'] ?? null)) // Zona horaria: Lima
            ->setFormaPago(new FormaPagoContado()) // FormaPago: Contado
            ->setTipoMoneda($data['tipoMoneda'] ?? null) // Sol - Catalog. 02
            ->setCompany($this->getCompany($data['company']))
            ->setClient($this->getClient($data['client']))
            ->setMtoOperGravadas($data['mtoOperGravadas'])
            ->setMtoOperExoneradas($data['mtoOperExoneradas'])
            ->setMtoOperInafectas($data['mtoOperInafectas'])
            ->setMtoOperExportacion($data['mtoOperExportacion'])
            ->setMtoOperGratuitas($data['mtoOperGratuitas'])
            ->setMtoIGV($data['mtoIGV'])
            ->setMtoIGVGratuitas($data['mtoIGVGratuitas'])
            ->setIcbper($data['icbper'])
            ->setTotalImpuestos($data['totalImpuestos'])
            ->setValorVenta($data['valorVenta'])
            ->setSubTotal($data['subTotal'])
            ->setRedondeo($data['redondeo'])
            ->setMtoImpVenta($data['mtoImpVenta'])
            ->setDetails($this->getDetails($data['details']))
            ->setLegends($this->getLegends($data['legends']));

        return $invoice;
    }

    public function getCompany($company)
    {
        $company = (new Company())
            ->setRuc($company['ruc'] ?? null)
            ->setRazonSocial($company['razonSocial'] ?? null)
            ->setNombreComercial($company['nombreComercial'] ?? null)
            ->setAddress($this->getAddress($company['address'] ?? null));

        return $company;
    }

    public function getClient($client)
    {
        $client = (new Client())
            ->setTipoDoc($client['tipoDoc'] ?? null) // RUC - Catalog. 06
            ->setNumDoc($client['numDoc'] ?? null)
            ->setRznSocial($client['rznSocial'] ?? null);

        return $client;
    }

    public function getAddress($address)
    {
        $address = (new Address())
            ->setUbigueo($address['ubigueo'] ?? null) // Codigo de ubigeo - Catalog. 01
            ->setDepartamento($address['departamento'] ?? null)
            ->setProvincia($address['provincia'] ?? null)
            ->setDistrito($address['distrito'] ?? null)
            ->setUrbanizacion($address['urbanizacion'] ?? null)
            ->setDireccion($address['direccion'] ?? null)
            ->setCodLocal($address['codLocal'] ?? null); // Codigo de establecimiento asignado por SUNAT, 0000 por defecto.

        return $address;
    }

    public function getDetails($details)
    {
        $items = [];

        foreach ($details as $detail) {
            $items[] = (new SaleDetail())
                ->setCodProducto($detail['codProducto'] ?? null) // Codigo de producto del contribuyente
                ->setUnidad($detail['unidad'] ?? null) // Catalog. 03
                ->setDescripcion($detail['descripcion'] ?? null)
                ->setCantidad($detail['cantidad'] ?? null)
                ->setMtoValorUnitario($detail['mtoValorUnitario'] ?? null)
                ->setMtoValorVenta($detail['mtoValorVenta'] ?? null)
                ->setMtoBaseIgv($detail['mtoBaseIgv'] ?? null)
                ->setPorcentajeIgv($detail['porcentajeIgv'] ?? null) // 18%
                ->setIgv($detail['igv'] ?? null)
                ->setFactorIcbper($detail['factorIcbper'] ?? null)
                ->setIcbper($detail['icbper'] ?? null)
                ->setTipAfeIgv($detail['tipAfeIgv'] ?? null) // Gravado - Catalog. 07
                ->setTotalImpuestos($detail['totalImpuestos'] ?? null)
                ->setMtoPrecioUnitario($detail['mtoPrecioUnitario'] ?? null);
        }

        return $items;
    }

    public function getLegends($legends)
    {
        $items = [];

        foreach ($legends as $legend) {
            $items[] = (new Legend())
                ->setCode($legend['code'] ?? null) // Monto en letras - Catalog. 52
                ->setValue($legend['value'] ?? null);
        }

        return $items;
    }

    public function sunatResponse($result)
    {
        $response['success'] = $result->isSuccess();

        // Verificamos que la conexión con SUNAT fue exitosa.

        if (!$response['success']) {
            // Mostrar error al conectarse a SUNAT.

            $response['error'] = [
                'code' => $result->getError()->getCode(),
                'message' => $result->getError()->getMessage(),
            ];

            return $response;
        }

        $cdr = $result->getCdrResponse();

        $response['cdrResponse'] = [
            'code' => (int)$cdr->getCode(),
            'description' => $cdr->getDescription(),
            'notes' => $cdr->getNotes(),
        ];

        return $response;
    }

    public function saveXml($company, $invoice, $see)
    {
        $path_xml = 'invoices/' . $company->ruc . '/' . $invoice->getName() . '/' . $invoice->getName() . '.xml';

        Storage::put($path_xml, $see->getFactory()->getLastXml());
    }

    public function saveCdr($company, $invoice, $result)
    {
        $path_cdr = 'invoices/' . $company->ruc . '/' . $invoice->getName() . '/R-' . $invoice->getName() . '.zip';

        Storage::put($path_cdr, $result->getCdrZip());
    }

    public function getHtmlReport($invoice, $hash)
    {
        $report = new HtmlReport();

        $resolver = new DefaultTemplateResolver();
        $report->setTemplate($resolver->getTemplate($invoice));

        $ruc = $invoice->getCompany()->getRuc();
        $company = ModelsCompany::where('ruc', $ruc)->where('user_id', auth()->user()->id)->first();

        $params = [
            'system' => [
                'logo' => Storage::get($company->logo_path), // Logo de Empresa
                'hash' => $hash, // Valor Resumen 
            ],
            'user' => [
                'header'     => 'Telf: <b>(01) 123375</b>', // Texto que se ubica debajo de la dirección de empresa
                'extras'     => [
                    // Leyendas adicionales
                    ['name' => 'CONDICION DE PAGO', 'value' => 'Efectivo'],
                    ['name' => 'VENDEDOR', 'value' => 'PRUEBA S.A.C.'],
                ],
                'footer' => '<p>Nro Resolucion: <b>3232323</b></p>'
            ]
        ];

        $html = $report->render($invoice, $params);

        return $html;
    }
}
