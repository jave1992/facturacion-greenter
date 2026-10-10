<?php

namespace App\Services;

use DateTime;
use Greenter\Model\Client\Client;
use Greenter\Model\Company\Address;
use Greenter\Model\Company\Company;
use Greenter\Model\Sale\FormaPagos\FormaPagoContado;
use Greenter\Model\Sale\Invoice;
use Greenter\Model\Sale\Legend;
use Greenter\Model\Sale\SaleDetail;
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
            ->setUblVersion($data['ublVersion']) // UBL 2.1
            ->setTipoOperacion($data['tipoOperacion']) // Venta - Catalog. 51
            ->setTipoDoc($data['tipoDoc']) // Factura - Catalog. 01
            ->setSerie($data['serie'])
            ->setCorrelativo($data['correlativo'])
            ->setFechaEmision(new DateTime($data['fechaEmision'])) // Zona horaria: Lima
            ->setFormaPago(new FormaPagoContado()) // FormaPago: Contado
            ->setTipoMoneda($data['tipoMoneda']) // Sol - Catalog. 02
            ->setCompany($this->getCompany($data['company']))
            ->setClient($this->getClient($data['client']))
            ->setMtoOperGravadas($data['mtoOperGravadas'])
            ->setMtoIGV($data['mtoIGV'])
            ->setTotalImpuestos($data['totalImpuestos'])
            ->setValorVenta($data['valorVenta'])
            ->setSubTotal($data['subTotal'])
            ->setMtoImpVenta($data['mtoImpVenta'])
            ->setDetails($this->getDetails($data['details']))
            ->setLegends($this->getLegends($data['legends']));

        return $invoice;
    }

    public function getCompany($company)
    {
        $company = (new Company())
            ->setRuc($company['ruc'])
            ->setRazonSocial($company['razonSocial'])
            ->setNombreComercial($company['nombreComercial'])
            ->setAddress($this->getAddress($company['address']));

        return $company;
    }

    public function getClient($client)
    {
        $client = (new Client())
            ->setTipoDoc($client['tipoDoc']) // RUC - Catalog. 06
            ->setNumDoc($client['numDoc'])
            ->setRznSocial($client['rznSocial']);

        return $client;
    }

    public function getAddress($address)
    {
        $address = (new Address())
            ->setUbigueo($address['ubigueo']) // Codigo de ubigeo - Catalog. 01
            ->setDepartamento($address['departamento'])
            ->setProvincia($address['provincia'])
            ->setDistrito($address['distrito'])
            ->setUrbanizacion($address['urbanizacion'])
            ->setDireccion($address['direccion'])
            ->setCodLocal($address['codLocal']); // Codigo de establecimiento asignado por SUNAT, 0000 por defecto.

        return $address;
    }

    public function getDetails($details)
    {
        $items = [];

        foreach ($details as $detail) {
            $items[] = (new SaleDetail())
                ->setCodProducto($detail['codProducto'])
                ->setUnidad($detail['unidad']) // Catalog. 03
                ->setDescripcion($detail['descripcion'])
                ->setCantidad($detail['cantidad'])
                ->setMtoValorUnitario($detail['mtoValorUnitario'])
                ->setMtoValorVenta($detail['mtoValorVenta'])
                ->setMtoBaseIgv($detail['mtoBaseIgv'])
                ->setPorcentajeIgv($detail['porcentajeIgv']) // 18%
                ->setIgv($detail['igv'])
                ->setTipAfeIgv($detail['tipAfeIgv']) // Gravado - Catalog. 07
                ->setTotalImpuestos($detail['totalImpuestos'])
                ->setMtoPrecioUnitario($detail['mtoPrecioUnitario']);
        }

        return $items;
    }

    public function getLegends($legends)
    {
        $items = [];

        foreach ($legends as $legend) {
            $items[] = (new Legend())
                ->setCode($legend['code']) // Monto en letras - Catalog. 52
                ->setValue($legend['value']);
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
}
